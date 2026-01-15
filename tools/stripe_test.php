<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PaymentGateways\StripeGateway;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

function runScenario(string $name, array $responses) {
    $mock = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    $client = new Client(['handler' => $handler, 'base_uri' => config('services.stripe.api_url')]);
    $gateway = new StripeGateway($client);

    $paymentData = [
        'payment_method_id' => 'pm_test_123',
        'customer' => [
            'name' => 'Teste Cliente',
            'email' => 'test@example.com',
            'phone_number' => '+5511999999999',
        ],
        'cart' => [
            [
                'product_hash' => 'prod_test',
                'title' => 'Plano Teste',
                // use fallback amount in cents to exercise fallback branch
                'price' => 2790,
                'price_id' => null,
                'quantity' => 1,
                'recurring' => null,
            ]
        ],
        'upsell_url' => 'http://127.0.0.1:8002/upsell/painel-das-garotas-card',
        'upsell_success_url' => 'http://127.0.0.1:8002/upsell/thank-you-card',
        'upsell_failed_url' => 'http://127.0.0.1:8002/upsell/thank-you-card',
        'upsell_offer_refused_url' => 'http://127.0.0.1:8002/upsell/thank-you-recused-card',
        'offer_hash' => 'offer_test',
        'metadata' => [],
    ];

    echo "--- Scenario: $name ---\n";
    try {
        $res = $gateway->processPayment($paymentData);
        echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (\Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}

// 1) Success scenario: sequence of expected Stripe API calls
$successResponses = [
    // GET /customers -> no existing
    new Response(200, [], json_encode(['data' => []])),
    // POST /customers -> created
    new Response(200, [], json_encode(['id' => 'cus_test'])),
    // GET /payment_methods/{id} -> no customer attached
    new Response(200, [], json_encode(['id' => 'pm_test_123', 'customer' => null])),
    // POST attach -> returns payment method attached
    new Response(200, [], json_encode(['id' => 'pm_test_123', 'customer' => 'cus_test'])),
    // POST /customers/{id} invoice update
    new Response(200, [], json_encode(['id' => 'cus_test'])),
    // POST /setup_intents -> succeeded
    new Response(200, [], json_encode(['status' => 'succeeded'])),
    // POST /payment_intents -> succeeded
    new Response(200, [], json_encode(['id' => 'pi_test', 'status' => 'succeeded'])),
];

// 2) Failure scenario: setup_intents not succeeded
$failureResponses = [
    new Response(200, [], json_encode(['data' => []])),
    new Response(200, [], json_encode(['id' => 'cus_test'])),
    new Response(200, [], json_encode(['id' => 'pm_test_123', 'customer' => null])),
    new Response(200, [], json_encode(['id' => 'pm_test_123', 'customer' => 'cus_test'])),
    new Response(200, [], json_encode(['id' => 'cus_test'])),
    // setup_intents -> requires_payment_method
    new Response(200, [], json_encode(['status' => 'requires_payment_method'])),
];

runScenario('success', $successResponses);
runScenario('failure', $failureResponses);
