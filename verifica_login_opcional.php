<?php
// verifica_login_opcional.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define a variável $usuario se o login estiver ativo
$usuario = $_SESSION['usuario'] ?? null;

// Se NÃO estiver logado, salvamos a URL atual para retorno após login
if (!$usuario) {
    $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'];
}