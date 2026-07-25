<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Cloudinary\Cloudinary;

/**
 * Configuração via variáveis de ambiente separadas.
 * Isso evita o erro de "string given" ao processar a URL única.
 */
return new Cloudinary([
    'cloud' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => getenv('CLOUDINARY_API_KEY'),
        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
    ],
    'url' => [
        'secure' => true
    ]
]);