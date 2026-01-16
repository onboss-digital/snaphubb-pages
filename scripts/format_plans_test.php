<?php
// Ad-hoc test: fetch backend plans and run StripeGateway::formatPlans
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$url = 'http://127.0.0.1:8000/api/get-plans?lang=br';
$json = @file_get_contents($url);
if ($json === false) {
    echo "ERROR: could not fetch $url\n";
    exit(1);
}
$data = json_decode($json, true);
if (!is_array($data)) {
    echo "ERROR: invalid JSON from $url\n";
    exit(1);
}

$gw = app(\App\Services\PaymentGateways\StripeGateway::class);
$res = $gw->formatPlans($data, 'BRL');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
