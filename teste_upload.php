<?php
// teste_upload.php — APAGA DEPOIS
echo date('Y-m-d H:i:s') . "\n";
echo "Ficheiro: " . __FILE__ . "\n";
echo "Tamanho upload_cloudinary.php: " . filesize(__DIR__ . '/upload_cloudinary.php') . " bytes\n";
echo "Primeiras 200 chars:\n";
echo substr(file_get_contents(__DIR__ . '/upload_cloudinary.php'), 0, 200) . "\n";
echo "Opcache activo: " . (function_exists('opcache_get_status') ? 'SIM' : 'NÃO') . "\n";
if (function_exists('opcache_get_status')) {
    opcache_reset();
    echo "Opcache limpo.\n";
}