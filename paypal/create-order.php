<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://clipesv1.onrender.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include "../conexao.php";

// Ler dados enviados pelo frontend
$input    = json_decode(file_get_contents('php://input'), true);
$id_video = intval($input['id_video'] ?? 0);

if (!$id_video) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de vídeo inválido']);
    exit;
}

// Buscar vídeo na base de dados
$stmt = $conexao->prepare("SELECT id_video, nome_video, preco FROM video WHERE id_video = ? AND ativo = true");
$stmt->execute([$id_video]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    echo json_encode(['error' => 'Vídeo não encontrado']);
    exit;
}

$preco      = number_format((float)$video['preco'], 2, '.', '');
$nome_video = $video['nome_video'];

// Obter access token do PayPal
$client_id = getenv('PAYPAL_CLIENT_ID');
$secret    = getenv('PAYPAL_SECRET');
$base_url  = getenv('PAYPAL_BASE_URL') ?: 'https://api-m.paypal.com';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "$base_url/v1/oauth2/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_USERPWD        => "$client_id:$secret",
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$token_response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($token_response['access_token'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao autenticar com PayPal']);
    exit;
}

$access_token = $token_response['access_token'];

// Criar a ordem de pagamento
$order_data = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => 'video_' . $id_video,
        'description'  => 'DarkVelvetClub - ' . $nome_video,
        'amount'       => [
            'currency_code' => 'USD',
            'value'         => $preco,
        ],
    ]],
    'application_context' => [
        'brand_name'          => 'DarkVelvetClub',
        'landing_page'        => 'NO_PREFERENCE',
        'user_action'         => 'PAY_NOW',
        'return_url'          => 'https://clipesv1.onrender.com/paypal/capture-order.php',
        'cancel_url'          => 'https://clipesv1.onrender.com/index.php?payment=cancelled',
    ],
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "$base_url/v2/checkout/orders",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($order_data),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer $access_token",
    ],
]);
$order_response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($order_response['id'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao criar ordem PayPal']);
    exit;
}

// Guardar na sessão para usar na captura

$_SESSION['paypal_pending'] = [
    'order_id'   => $order_response['id'],
    'id_video'   => $id_video,
    'nome_video' => $nome_video,
    'preco'      => $preco,
];

echo json_encode([
    'id'       => $order_response['id'],
    'status'   => $order_response['status'],
]);