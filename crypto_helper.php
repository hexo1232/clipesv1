<?php
// crypto_helper.php

class CryptoHelper
{
    const CHUNK_SIZE = 1024 * 1024; // 1MB — sempre múltiplo de 16 (tamanho do bloco AES)

    private static function masterKey(): string
    {
        $b64 = getenv('CRYPTO_MASTER_KEY');
        if (!$b64) {
            throw new RuntimeException('CRYPTO_MASTER_KEY não definida no ambiente.');
        }
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('CRYPTO_MASTER_KEY inválida (esperado 32 bytes em base64).');
        }
        return $key;
    }

    public static function gerarMaterialParaNovoFicheiro(): array
    {
        $fileKey = random_bytes(32);
        $iv      = random_bytes(16);
        $keyEnc  = self::encriparChaveComMaster($fileKey);

        return [
            'file_key_raw' => $fileKey,
            'iv_hex'       => bin2hex($iv),
            'key_enc_b64'  => $keyEnc,
        ];
    }

    private static function encriparChaveComMaster(string $fileKey): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $fileKey, 'aes-256-gcm', self::masterKey(),
            OPENSSL_RAW_DATA, $iv, $tag
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao cifrar a chave do ficheiro.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decifrarChaveComMaster(string $keyEncB64): string
    {
        $blob = base64_decode($keyEncB64, true);
        if ($blob === false || strlen($blob) < 28) {
            throw new RuntimeException('key_enc inválida.');
        }
        $iv         = substr($blob, 0, 12);
        $tag        = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);

        $fileKey = openssl_decrypt(
            $ciphertext, 'aes-256-gcm', self::masterKey(),
            OPENSSL_RAW_DATA, $iv, $tag
        );
        if ($fileKey === false) {
            throw new RuntimeException('Falha ao decifrar a chave do ficheiro (integridade comprometida).');
        }
        return $fileKey;
    }

    /**
     * Soma um número de "blocos" (16 bytes cada) a um IV de 16 bytes,
     * tratando o IV como um inteiro big-endian de 128 bits.
     * Isto é o que permite calcular o IV correto para qualquer offset do ficheiro,
     * seja a encriptar sequencialmente, seja a decifrar um trecho aleatório (seek).
     */
    public static function somarBlocosAoIv(string $ivBin, int $blocos): string
    {
        $iv = $ivBin;
        $carry = $blocos;
        for ($i = 15; $i >= 0 && $carry > 0; $i--) {
            $byte = ord($iv[$i]) + ($carry & 0xFF);
            $carry = $carry >> 8;
            if ($byte > 0xFF) {
                $byte -= 0x100;
                $carry += 1;
            }
            $iv[$i] = chr($byte);
        }
        return $iv;
    }

    /**
     * Encripta um ficheiro inteiro em disco, em blocos de CHUNK_SIZE,
     * mantendo o keystream contínuo entre blocos.
     */
    public static function encriptarFicheiro(string $caminhoOrigem, string $caminhoDestino, string $fileKey, string $ivHex): void
    {
        $ivBin = hex2bin($ivHex);
        $in  = fopen($caminhoOrigem, 'rb');
        $out = fopen($caminhoDestino, 'wb');
        if (!$in || !$out) {
            throw new RuntimeException('Não foi possível abrir ficheiros para encriptação.');
        }

        $offset = 0;
        while (!feof($in)) {
            $chunk = fread($in, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') break;

            $blocosProcessados = intdiv($offset, 16);
            $ivChunk = self::somarBlocosAoIv($ivBin, $blocosProcessados);

            $cifrado = openssl_encrypt(
                $chunk, 'aes-256-ctr', $fileKey,
                OPENSSL_RAW_DATA, $ivChunk
            );
            if ($cifrado === false) {
                fclose($in); fclose($out);
                throw new RuntimeException('Falha ao encriptar chunk do ficheiro.');
            }

            fwrite($out, $cifrado);
            $offset += strlen($chunk);
        }

        fclose($in);
        fclose($out);
    }

    /**
     * Decifra um trecho (range) de bytes cifrados, dado o offset absoluto no ficheiro original.
     * Usado pelo proxy de streaming para servir Range requests sem decifrar o ficheiro todo.
     */
    public static function decifrarTrecho(string $cifradoBin, string $fileKey, string $ivHex, int $offsetAbsoluto): string
    {
        $ivBin = hex2bin($ivHex);
        $blocosProcessados = intdiv($offsetAbsoluto, 16);
        $ivChunk = self::somarBlocosAoIv($ivBin, $blocosProcessados);

        $plano = openssl_decrypt(
            $cifradoBin, 'aes-256-ctr', $fileKey,
            OPENSSL_RAW_DATA, $ivChunk
        );
        if ($plano === false) {
            throw new RuntimeException('Falha ao decifrar trecho do ficheiro.');
        }
        return $plano;
    }
}