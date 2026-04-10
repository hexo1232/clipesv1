<?php
// usuario_logica.php
require_once "verifica_login.php";

// Define a variável $usuario com os dados da sessão
$usuario = $_SESSION['usuario'];

// Gera as iniciais do nome e apelido
$nome = $usuario['nome'] ?? '';
$apelido = $usuario['apelido'] ?? '';
$iniciais = strtoupper(substr($nome, 0, 1) . substr($apelido, 0, 1));
$nomeCompleto = "$nome $apelido";

// Função para gerar cor única baseada no nome
function gerarCor($texto) {
    $hash = md5($texto);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    return "rgb($r, $g, $b)";
}

$corAvatar = gerarCor($nomeCompleto);

// Não há tag de fechamento PHP, que é uma boa prática