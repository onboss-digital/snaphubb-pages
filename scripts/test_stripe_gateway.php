<?php

// Script de teste: inicializa o Laravel app e chama StripeGateway::getProductWithPrices
// Execução: php scripts/test_stripe_gateway.php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var App\Services\PaymentGateways\StripeGateway $gateway */
$gateway = $app->make(App\Services\PaymentGateways\StripeGateway::class);

$productId = $argv[1] ?? 'prod_SZ4hVf6tt1aH5G';

try {
    $res = $gateway->getProductWithPrices($productId);
    echo "=== getProductWithPrices result for {$productId} ===\n";
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    // Print price summary if available
    if (!empty($res['prices'])) {
        echo "\nPrices:\n";
        foreach ($res['prices'] as $p) {
            echo sprintf("- id: %s | amount: %s | currency: %s | recurring: %s\n",
                $p['id'] ?? 'null',
                isset($p['unit_amount']) ? $p['unit_amount'] : ($p['amount'] ?? 'null'),
                $p['currency'] ?? 'N/A',
                json_encode($p['recurring'] ?? null)
            );
        }
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
