<?php
// Usage: php simulate_pushinpay_webhook.php approved|refunded [email] [product_id]
require __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;

$type = $argv[1] ?? 'approved';
$url = getenv('WEBHOOK_NOTIFICATION_URL') ?: 'http://127.0.0.1:8005/api/pix/webhook';

$client = new Client(['http_errors' => false]);

$email = $argv[2] ?? 'tester@example.com';
$product = $argv[3] ?? 'prod_pushinpay_123';

if ($type === 'approved') {
    $payload = [
        'event' => 'payment.approved',
        'data' => [
            'id' => 'pp_' . bin2hex(random_bytes(6)),
            'amount' => 2790,
            'currency' => 'BRL',
            'payer' => ['email' => $email],
            'product_id' => $product,
            'reference' => $product
        ]
    ];
} else {
    $payload = [
        'event' => 'payment.refunded',
        'data' => [
            'id' => 'pp_' . bin2hex(random_bytes(6)),
            'refund_id' => 'r_' . bin2hex(random_bytes(6)),
            'amount' => 2790,
            'currency' => 'BRL',
            'payer' => ['email' => $email],
            'product_id' => $product,
            'reference' => $product
        ]
    ];
}

echo "Posting to $url\n";
$resp = $client->post($url, ['json' => $payload]);
echo "Response: " . $resp->getStatusCode() . "\n";
echo (string)$resp->getBody() . "\n";
