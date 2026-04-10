<?php
session_start();

// Captura o perfil do usuário antes de destruir a sessão
$id_perfil = $_SESSION['usuario']['idperfil'] ?? null;

// ✅ Limpa somente os dados de login
unset($_SESSION['usuario']);

// 🔒 Fecha e salva a sessão
session_write_close();

// ✅ Redireciona com base no perfil
if ($id_perfil == 1) {
    header("Location: index.php");
} else{
      header("Location: index.php");
}
exit;
?>

