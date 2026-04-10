<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudinaryUrl = getenv('CLOUDINARY_URL');

if (!$cloudinaryUrl) {
    throw new \Exception("Variável CLOUDINARY_URL não configurada no Render.");
}

// Limpeza de segurança: remove espaços e os sinais < > caso você esqueça algum
$urlLimpa = str_replace(['<', '> ', ' '], '', $cloudinaryUrl);

return new Cloudinary([
    'url' => $urlLimpa
]);