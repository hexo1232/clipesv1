<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define a variável $usuario se o login estiver ativo
$usuario = $_SESSION['usuario'] ?? null;


// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
  // Salva a página que o usuário tentou acessar
    $_SESSION['url_origem'] = $_SERVER['REQUEST_URI'];
}



?>
