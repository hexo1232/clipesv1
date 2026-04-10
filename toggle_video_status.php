<?php
// toggle_video_status.php
include "conexao.php";
include "verifica_login.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

// Apenas Admin pode alterar status
if ($id_perfil != 1) {
    echo "<script>alert('Acesso negado!'); window.location.href='ver_videos.php';</script>";
    exit;
}

$id_video = intval($_GET['id_video'] ?? 0);
$novo_status = intval($_GET['status'] ?? 1);

if ($id_video <= 0) {
    echo "<script>alert('Vídeo inválido!'); window.location.href='gerenciar_videos.php';</script>";
    exit;
}

$stmt = $conexao->prepare("UPDATE video SET ativo = ? WHERE id_video = ?");
$stmt->bind_param("ii", $novo_status, $id_video);

if ($stmt->execute()) {
    $status_texto = $novo_status ? "ativado" : "desativado";
    echo "<script>alert('✅ Vídeo $status_texto com sucesso!'); window.location.href='gerenciar_videos.php';</script>";
} else {
    echo "<script>alert('❌ Erro ao alterar status!'); window.location.href='gerenciar_videos.php';</script>";
}

$stmt->close();
$conexao->close();
?>