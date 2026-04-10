<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Cloudinary;

// Tenta pegar a URL da variável de ambiente
$cloudinaryUrl = getenv('CLOUDINARY_URL');

if (!$cloudinaryUrl) {
    throw new \Exception("Erro: Variável CLOUDINARY_URL não encontrada no ambiente.");
}

return new Cloudinary([
    'url' => $cloudinaryUrl
]);