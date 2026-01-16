<?php
// Usage: php simulate_stripe_webhook.php succeeded|refunded [options]
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$type = $argv[1] ?? 'succeeded';
$url = getenv('STRIPE_WEBHOOK_URL') ?: 'http://127.0.0.1:8005/webhook/stripe';

$client = new Client(['http_errors' => false]);

if ($type === 'succeeded') {
    $payload = [
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_' . bin2hex(random_bytes(6)),
                'amount' => 2790,
                'currency' => 'brl',
                'charges' => ['data' => [[
                    'billing_details' => ['email' => $argv[2] ?? 'tester@example.com', 'phone' => '+5511999999999'],
                    'metadata' => ['product_id' => $argv[3] ?? 'prod_test_123']
                ]]],
                'metadata' => ['product_id' => $argv[3] ?? 'prod_test_123']
            ]
        ]
    ];
} else {
    // refunded
    $payload = [
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_' . bin2hex(random_bytes(6)),
                'charge' => 'ch_' . bin2hex(random_bytes(6)),
                'payment_intent' => 'pi_' . bin2hex(random_bytes(6)),
                'amount' => 2790,
                'currency' => 'brl'
            ]
        ]
    ];
}

echo "Posting to $url\n";
$resp = $client->post($url, ['json' => $payload]);
echo "Response: " . $resp->getStatusCode() . "\n";
echo (string)$resp->getBody() . "\n";
