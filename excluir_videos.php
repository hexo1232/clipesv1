<?php
// excluir_videos.php
include "conexao.php";
include "verifica_login.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

// Apenas Admin pode excluir
if ($id_perfil != 1) {
    echo "<script>alert('Acesso negado!'); window.location.href='ver_videos.php';</script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['videos_ids']) && is_array($_POST['videos_ids'])) {
    $idsParaExcluir = $_POST['videos_ids'];

    $conexao->begin_transaction();

    try {
        // Preparar statements
        $stmtVideo = $conexao->prepare("SELECT caminho_previa FROM video WHERE id_video = ?");
        $stmtImagens = $conexao->prepare("SELECT caminho_imagem FROM video_imagem WHERE id_video = ?");
        $stmtDeleteVideo = $conexao->prepare("DELETE FROM video WHERE id_video = ?");

        foreach ($idsParaExcluir as $id_video) {
            $id_video = intval($id_video);

            // 1. Obter e deletar arquivo de prévia
            $stmtVideo->bind_param("i", $id_video);
            $stmtVideo->execute();
            $resVideo = $stmtVideo->get_result();
            if ($video = $resVideo->fetch_assoc()) {
                if ($video['caminho_previa'] && file_exists($video['caminho_previa'])) {
                    unlink($video['caminho_previa']);
                }
            }

            // 2. Obter e deletar arquivos de imagem
            $stmtImagens->bind_param("i", $id_video);
            $stmtImagens->execute();
            $resImagens = $stmtImagens->get_result();
            while ($img = $resImagens->fetch_assoc()) {
                if ($img['caminho_imagem'] && file_exists($img['caminho_imagem'])) {
                    unlink($img['caminho_imagem']);
                }
            }

            // 3. Deletar registro do vídeo (CASCADE vai deletar relações)
            $stmtDeleteVideo->bind_param("i", $id_video);
            $stmtDeleteVideo->execute();
        }

        $conexao->commit();

        echo "<script>alert('✅ Vídeos excluídos com sucesso!'); window.location.href='gerenciar_videos.php';</script>";
        exit();

    } catch (mysqli_sql_exception $e) {
        $conexao->rollback();
        echo "<script>alert('❌ Erro ao excluir vídeos: " . addslashes($e->getMessage()) . "'); window.location.href='gerenciar_videos.php';</script>";
        exit();
    } finally {
        if (isset($stmtVideo)) $stmtVideo->close();
        if (isset($stmtImagens)) $stmtImagens->close();
        if (isset($stmtDeleteVideo)) $stmtDeleteVideo->close();
        $conexao->close();
    }
} else {
    echo "<script>alert('❌ Nenhum vídeo foi selecionado para exclusão.'); window.location.href='gerenciar_videos.php';</script>";
    exit();
}
?>