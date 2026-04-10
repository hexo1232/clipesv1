<?php
include "verifica_login.php";
include "conexao.php"; // Deve ser a versão PDO que configuramos



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

    try {
        $conexao->beginTransaction();

        // Preparar as consultas (Reutilizamos o mesmo statement dentro do loop)
        $stmtVideo = $conexao->prepare("SELECT caminho_previa FROM video WHERE id_video = ?");
        $stmtImagens = $conexao->prepare("SELECT caminho_imagem FROM video_imagem WHERE id_video = ?");
        $stmtDeleteVideo = $conexao->prepare("DELETE FROM video WHERE id_video = ?");

        foreach ($idsParaExcluir as $id_video) {
            $id_video = intval($id_video);

            // 1. Obter e deletar arquivo de prévia
            $stmtVideo->execute([$id_video]);
            $video = $stmtVideo->fetch(PDO::FETCH_ASSOC);
            
            if ($video && !empty($video['caminho_previa']) && file_exists($video['caminho_previa'])) {
                unlink($video['caminho_previa']);
            }

            // 2. Obter e deletar arquivos de imagem
            $stmtImagens->execute([$id_video]);
            $imagens = $stmtImagens->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($imagens as $img) {
                if (!empty($img['caminho_imagem']) && file_exists($img['caminho_imagem'])) {
                    unlink($img['caminho_imagem']);
                }
            }

            // 3. Deletar registro do vídeo (O CASCADE no banco deve cuidar das tabelas relacionadas)
            $stmtDeleteVideo->execute([$id_video]);
        }

        $conexao->commit();

        echo "<script>alert('✅ Vídeos excluídos com sucesso!'); window.location.href='gerenciar_videos.php';</script>";
        exit();

    } catch (Exception $e) {
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }
        echo "<script>alert('❌ Erro ao excluir vídeos: " . addslashes($e->getMessage()) . "'); window.location.href='gerenciar_videos.php';</script>";
        exit();
    }
} else {
    echo "<script>alert('❌ Nenhum vídeo foi selecionado para exclusão.'); window.location.href='gerenciar_videos.php';</script>";
    exit();
}
?>