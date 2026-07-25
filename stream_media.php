<?php
// ════════════════════════════════════════════════════════════════════════════
//  stream_media.php
//  Proxy que busca o blob cifrado na Cloudinary, desencripta em tempo real
//  e devolve ao browser — com suporte a Range requests (necessário para o
//  seek do <video> funcionar normalmente).
//
//  Uso: stream_media.php?id=123&tipo=video   (prévia)
//       stream_media.php?id=123&tipo=imagem  (capa)
// ════════════════════════════════════════════════════════════════════════════

include "conexao.php";
require_once __DIR__ . '/crypto_helper.php';

$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idVideo = intval($_GET['id'] ?? 0);
$tipo    = $_GET['tipo'] ?? '';

if ($idVideo <= 0 || !in_array($tipo, ['video', 'imagem'], true)) {
    http_response_code(400);
    exit('Parâmetros inválidos.');
}

// ── Prévias e capas são conteúdo promocional público (mesma lógica do
//    index.php actual, que já mostra isto sem exigir login). Se no futuro
//    quiser restringir, adicione aqui a verificação de sessão. ──────────

try {
    if ($tipo === 'video') {
        $stmt = $conexao->prepare(
            "SELECT previa_public_id AS public_id, previa_iv AS iv, previa_key_enc AS key_enc,
                    previa_mime AS mime, previa_tamanho_bytes AS tamanho
             FROM video WHERE id_video = ? AND ativo = true"
        );
    } else {
        $stmt = $conexao->prepare(
            "SELECT imagem_public_id AS public_id, imagem_iv AS iv, imagem_key_enc AS key_enc,
                    imagem_mime AS mime, imagem_tamanho_bytes AS tamanho
             FROM video_imagem WHERE id_video = ? AND imagem_principal = true"
        );
    }
    $stmt->execute([$idVideo]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('ERRO STREAM_MEDIA (consulta): ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno.');
}

if (!$media || empty($media['public_id'])) {
    http_response_code(404);
    exit('Media não encontrada.');
}

$cloudName = getenv('CLOUDINARY_CLOUD_NAME');
if (!$cloudName) {
    http_response_code(500);
    exit('Configuração da Cloudinary em falta.');
}

$urlCloudinary = "https://res.cloudinary.com/{$cloudName}/raw/upload/{$media['public_id']}";
$tamanhoTotal  = (int) $media['tamanho'];
$mimeType      = $media['mime'] ?: 'application/octet-stream';

try {
    $fileKey = CryptoHelper::decifrarChaveComMaster($media['key_enc']);
} catch (Throwable $e) {
    error_log('ERRO STREAM_MEDIA (chave): ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao processar media.');
}

// ── Determinar o range pedido pelo browser ──────────────────────────────
$rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;
$inicio = 0;
$fim    = $tamanhoTotal - 1;
$isPartial = false;

if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
    $isPartial = true;
    if ($m[1] !== '') $inicio = (int) $m[1];
    if ($m[2] !== '') $fim = (int) $m[2];
    if ($fim >= $tamanhoTotal) $fim = $tamanhoTotal - 1;
    if ($inicio > $fim || $inicio < 0) {
        http_response_code(416);
        header("Content-Range: bytes */{$tamanhoTotal}");
        exit;
    }
}

// ── Alinhar o range pedido a múltiplos de 16 bytes (bloco AES), pois
//    precisamos decifrar blocos completos mesmo que o browser peça um
//    offset "no meio" de um bloco. Depois cortamos o excesso. ───────────
$inicioAlinhado = intdiv($inicio, 16) * 16;
$excessoInicio  = $inicio - $inicioAlinhado;
// Ler um pouco além do fim também, por segurança de alinhamento
$fimAlinhado = min($tamanhoTotal - 1, ((intdiv($fim, 16) + 1) * 16) - 1);

// ── Buscar o trecho cifrado na Cloudinary via cURL com Range ────────────
$ch = curl_init($urlCloudinary);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Range: bytes={$inicioAlinhado}-{$fimAlinhado}"],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_FOLLOWLOCATION => true,
]);
$cifradoBin = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($cifradoBin === false || !in_array($httpCode, [200, 206], true)) {
    error_log("ERRO STREAM_MEDIA: falha ao buscar da Cloudinary (HTTP {$httpCode})");
    http_response_code(502);
    exit('Falha ao obter media.');
}

// ── Decifrar o trecho obtido, usando o offset alinhado ───────────────────
try {
    $planoBin = CryptoHelper::decifrarTrecho($cifradoBin, $fileKey, $media['iv'], $inicioAlinhado);
} catch (Throwable $e) {
    error_log('ERRO STREAM_MEDIA (decifrar): ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao decifrar media.');
}

// ── Cortar exactamente o trecho que o browser pediu ──────────────────────
$tamanhoUtil = $fim - $inicio + 1;
$planoFinal  = substr($planoBin, $excessoInicio, $tamanhoUtil);

// ── Enviar resposta ────────────────────────────────────────────────────
header('Content-Type: ' . $mimeType);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

if ($isPartial) {
    http_response_code(206);
    header("Content-Range: bytes {$inicio}-{$fim}/{$tamanhoTotal}");
    header('Content-Length: ' . strlen($planoFinal));
} else {
    http_response_code(200);
    header('Content-Length: ' . strlen($planoFinal));
}

echo $planoFinal;