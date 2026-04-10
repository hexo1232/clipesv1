<?php
require 'vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudinary = new Cloudinary(
    getenv('CLOUDINARY_URL')
);