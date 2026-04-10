<?php
// verifica_login_opcional.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define a variável $usuario se o login estiver ativo, caso contrário fica null
$usuario = $_SESSION['usuario'] ?? null;

// Se NÃO estiver logado, salvamos a URL atual para caso ele decida logar depois
if (!$usuario) {
    // Salva a página atual para que, se ele clicar em "Entrar", 
    // o login saiba para onde voltar.
    $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'];
}
?>