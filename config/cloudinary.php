<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;

// Busca a URL de ambiente
$cloudinaryUrl = getenv('CLOUDINARY_URL');

if (!$cloudinaryUrl) {
    throw new \Exception("Erro: CLOUDINARY_URL não configurada no Render.");
}

/**
 * LIMPEZA DE SEGURANÇA:
 * Remove os caracteres < e > caso tenham sido colados por engano no painel do Render.
 * Remove também espaços em branco.
 */
$urlLimpa = str_replace(['<', '>', ' '], '', $cloudinaryUrl);

try {
    // Retorna a instância configurada com a URL higienizada
    return new Cloudinary([
        'url' => $urlLimpa
    ]);
} catch (\Exception $e) {
    // Se ainda assim der erro, mostra uma mensagem amigável
    die("Falha na configuração do Cloudinary: " . $e->getMessage());
}