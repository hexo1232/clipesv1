<?php
session_start();
header('Content-Type: application/json');
echo json_encode([
    'debug_session' => session_id(),
    'usuario'       => $_SESSION['usuario'] ?? 'NAO AUTENTICADO',
    'post_tipo'     => $_POST['tipo'] ?? 'sem tipo',
    'file_recebido' => isset($_FILES['arquivo']) ? $_FILES['arquivo']['name'] : 'nenhum',
]);
exit;
include "verifica_login.php";
include "conexao.php";

$cloudinary = require __DIR__ . '/config/cloudinary.php';

header('Content-Type: application/json');

// Garante que só utilizadores autenticados acedem
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['erro' => 'Não autenticado.']);
    exit;
}

$tipo    = trim($_POST['tipo'] ?? '');   // 'video' ou 'imagem'
$arquivo = $_FILES['arquivo'] ?? null;

// ── Validações básicas ──
if (empty($tipo) || !in_array($tipo, ['video', 'imagem'])) {
    echo json_encode(['erro' => 'Tipo de ficheiro inválido. Use "video" ou "imagem".']);
    exit;
}

if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
    $erros_php = [
        UPLOAD_ERR_INI_SIZE   => 'Ficheiro excede upload_max_filesize no php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'Ficheiro excede MAX_FILE_SIZE do formulário.',
        UPLOAD_ERR_PARTIAL    => 'Ficheiro foi enviado apenas parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum ficheiro foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o ficheiro no disco.',
        UPLOAD_ERR_EXTENSION  => 'Uma extensão PHP interrompeu o upload.',
    ];
    $codigo  = $arquivo['error'] ?? -1;
    $detalhe = $erros_php[$codigo] ?? "Código de erro desconhecido: $codigo";
    echo json_encode(['erro' => "Erro no upload do ficheiro: $detalhe"]);
    exit;
}

if (!file_exists($arquivo['tmp_name']) || !is_readable($arquivo['tmp_name'])) {
    echo json_encode(['erro' => 'Ficheiro temporário inacessível no servidor.']);
    exit;
}

// ── Upload para a Cloudinary ──
try {
    if ($tipo === 'video') {
        // Vídeos podem demorar — aumenta o limite de tempo só para este script
        set_time_limit(300);

        $result = $cloudinary->uploadApi()->upload(
            $arquivo['tmp_name'],
            [
                'resource_type' => 'video',
                'folder'        => 'videos/previas',
            ]
        );
    } else {
        set_time_limit(120);

        $result = $cloudinary->uploadApi()->upload(
            $arquivo['tmp_name'],
            [
                'resource_type' => 'image',
                'folder'        => 'videos/imagens',
            ]
        );
    }

    if (empty($result['secure_url'])) {
        echo json_encode(['erro' => 'A Cloudinary não devolveu uma URL válida.']);
        exit;
    }

    echo json_encode([
        'url'       => $result['secure_url'],
        'public_id' => $result['public_id'] ?? '',
    ]);

} catch (Exception $e) {
    error_log("ERRO UPLOAD CLOUDINARY [$tipo]: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro ao enviar para a Cloudinary: ' . $e->getMessage()]);
}