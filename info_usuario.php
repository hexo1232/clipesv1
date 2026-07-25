<?php
// Garante que o usuário esteja autenticado e disponível
require_once "verifica_login.php";



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

