<?php
session_start();
include "../conexao.php";

$order_id = $_GET['token'] ?? '';  // PayPal envia o token como ?token=...

if (!$order_id) {
    header('Location: /index.php?payment=error');
    exit;
}

$client_id = getenv('PAYPAL_CLIENT_ID');
$secret    = getenv('PAYPAL_SECRET');
$base_url  = getenv('PAYPAL_BASE_URL') ?: 'https://api-m.paypal.com';

// Obter access token
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
    header('Location: /index.php?payment=error');
    exit;
}

$access_token = $token_response['access_token'];

// Capturar o pagamento
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "$base_url/v2/checkout/orders/$order_id/capture",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer $access_token",
    ],
]);
$capture = json_decode(curl_exec($ch), true);
curl_close($ch);

// Verificar se o pagamento foi bem-sucedido
$status = $capture['status'] ?? '';

if ($status !== 'COMPLETED') {
    header('Location: /index.php?payment=failed');
    exit;
}

// Extrair dados da transação
$capture_unit  = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
$transaction_id = $capture_unit['id'] ?? 'N/A';
$amount_paid    = $capture_unit['amount']['value'] ?? 'N/A';
$currency       = $capture_unit['amount']['currency_code'] ?? 'USD';
$payer_email    = $capture['payer']['email_address'] ?? 'N/A';
$payer_name     = ($capture['payer']['name']['given_name'] ?? '') . ' ' . ($capture['payer']['name']['surname'] ?? '');

// Recuperar dados do vídeo da sessão
$pending    = $_SESSION['paypal_pending'] ?? [];
$nome_video = $pending['nome_video'] ?? 'Unknown video';
$id_video   = $pending['id_video'] ?? 0;
$preco      = $pending['preco'] ?? $amount_paid;

// Limpar sessão
unset($_SESSION['paypal_pending']);

// Registar a compra na base de dados (opcional mas recomendado)
try {
    $stmt = $conexao->prepare("
        INSERT INTO compras (id_video, transaction_id, payer_email, payer_name, amount, currency, status, data_compra)
        VALUES (?, ?, ?, ?, ?, ?, 'COMPLETED', NOW())
    ");
    $stmt->execute([$id_video, $transaction_id, $payer_email, trim($payer_name), $amount_paid, $currency]);
} catch (Exception $e) {
    // Não bloqueia o fluxo se a tabela não existir ainda
    error_log('DB insert error: ' . $e->getMessage());
}

// Montar mensagem para o Telegram
$telegram_base = getenv('TELEGRAM_LINK') ?: 'https://t.me/xxxx';
$mensagem = urlencode(
    "✅ Payment Confirmed!\n\n" .
    "🎬 Video: $nome_video\n" .
    "💰 Amount: $currency $amount_paid\n" .
    "🔖 Transaction ID: $transaction_id\n" .
    "👤 Buyer: " . trim($payer_name) . " ($payer_email)\n\n" .
    "Please send me my video access. Thank you!"
);

// Redirecionar para Telegram com os dados da compra
header("Location: $telegram_base?text=$mensagem");
exit;