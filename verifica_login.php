


<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    // Captura a URL de destino
    $urlAtual = $_SERVER['REQUEST_URI'];
    $_SESSION['url_destino'] = $urlAtual;

    header("Location: login.php");
    exit;
}
// Define a variável $usuario com os dados da sessão
$usuario = $_SESSION['usuario'];
?>