<?php
require __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;

return new Cloudinary([
    'url' => getenv('CLOUDINARY_URL')
]);