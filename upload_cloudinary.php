<?php
include "verifica_login.php";
require_once __DIR__ . '/crypto_helper.php';
$cloudinary = require __DIR__ . '/cloudinary_config.php';   // ← CORRIGIDO

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['erro' => 'Nenhum ficheiro recebido ou erro no upload.']);
    exit;
}

$tipo = $_POST['tipo'] ?? '';
if (!in_array($tipo, ['video', 'imagem'], true)) {
    echo json_encode(['erro' => 'Tipo de ficheiro inválido.']);
    exit;
}

// ── Validações básicas de tamanho/mime ──────────────────────────────────
$tamanhoMax = $tipo === 'video' ? 100 * 1024 * 1024 : 5 * 1024 * 1024; // 100MB / 5MB
if ($_FILES['arquivo']['size'] > $tamanhoMax) {
    echo json_encode(['erro' => 'Ficheiro excede o tamanho máximo permitido.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['arquivo']['tmp_name']);
finfo_close($finfo);

$mimesPermitidos = $tipo === 'video'
    ? ['video/mp4', 'video/webm', 'video/ogg']
    : ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime, $mimesPermitidos, true)) {
    echo json_encode(['erro' => 'Formato de ficheiro não suportado (' . $mime . ').']);
    exit;
}

$tmpOriginal = $_FILES['arquivo']['tmp_name'];
$tmpCifrado  = tempnam(sys_get_temp_dir(), 'enc_');

try {
    // ── 1. Gerar material criptográfico único para este ficheiro ────────
    $material = CryptoHelper::gerarMaterialParaNovoFicheiro();

    // ── 2. Encriptar o ficheiro para um temporário local ─────────────────
    CryptoHelper::encriptarFicheiro($tmpOriginal, $tmpCifrado, $material['file_key_raw'], $material['iv_hex']);

    // ── 3. Gerar public_id aleatório (nenhuma pista do conteúdo/nome) ────
    $publicId = ($tipo === 'video' ? 'vp_' : 'ip_') . bin2hex(random_bytes(16));

    // ── 4. Upload do blob cifrado como recurso "raw" ──────────────────────
    $uploadApi = $cloudinary->uploadApi();
    $resultado = $uploadApi->upload($tmpCifrado, [
        'resource_type' => 'raw',
        'public_id'     => $publicId,
        'folder'        => 'encrypted_media', // pasta genérica, sem relação com o filme
        'overwrite'     => false,
        'use_filename'  => false,
        'unique_filename' => false,
    ]);

    if (empty($resultado['public_id'])) {
        throw new RuntimeException('Cloudinary não devolveu public_id.');
    }

    // ── 5. Responder ao front-end com tudo o que precisa ser gravado na BD
    //     (a chave em claro NUNCA é enviada — só key_enc, já cifrada) ─────
    echo json_encode([
        'public_id' => $resultado['public_id'],
        'iv'        => $material['iv_hex'],
        'key_enc'   => $material['key_enc_b64'],
        'tipo'      => $tipo,
        'mime'      => $mime,
        'tamanho'   => filesize($tmpCifrado),
    ]);

} catch (Throwable $e) {
    error_log('ERRO UPLOAD ENCRIPTADO: ' . $e->getMessage());
    echo json_encode(['erro' => 'Falha ao processar o upload: ' . $e->getMessage()]);
} finally {
    // ── 6. Limpar temporários SEMPRE, mesmo em erro ───────────────────────
    if (file_exists($tmpCifrado)) {
        @unlink($tmpCifrado);
    }
    // Não apagamos $tmpOriginal manualmente — o PHP remove uploads tmp automaticamente no fim do request
}