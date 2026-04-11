<?php
//verifica_login.php
// Certifique-se de que não há NADA antes do <?php acima
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    $urlAtual = $_SERVER['REQUEST_URI'];
    $_SESSION['url_destino'] = $urlAtual;
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario'];