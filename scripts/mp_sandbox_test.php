<?php
// Quick test script to call Mercado Pago sandbox to create a PIX payment.
// Reads token from .env file in project root (simple parser).

function readEnv($path) {
    $result = [];
    if (!file_exists($path)) return $result;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $val = trim($val, "\"'");
        $result[$key] = $val;
    }
    return $result;
}

$env = readEnv(__DIR__ . '/../.env');
$mp_env = $env['MERCADOPAGO_ENV'] ?? 'sandbox';
$token = ($mp_env === 'production') ? ($env['MERCADOPAGO_PRODUCTION_TOKEN'] ?? '') : ($env['MERCADOPAGO_SANDBOX_TOKEN'] ?? '');

if (empty($token)) {
    echo "No Mercado Pago token found in .env (MERCADOPAGO_SANDBOX_TOKEN or MERCADOPAGO_PRODUCTION_TOKEN)\n";
    exit(1);
}

$payload = [
    'transaction_amount' => 1.00,
    'description' => 'Test PIX via sandbox',
    'payment_method_id' => 'pix',
    'payer' => [
        'email' => 'sandbox-user@example.com',
        'first_name' => 'Sandbox',
    ],
];

$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
    'x-idempotency-key: ' . uniqid('mp_', true),
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "cURL error: $err\n";
    exit(1);
}

echo "HTTP status: $code\n";
echo "Response:\n";
echo $res . "\n";

// Try to extract QR
$data = json_decode($res, true);
if (isset($data['point_of_interaction']['transaction_data']['qr_code_base64'])) {
    echo "QR base64 found\n";
} elseif (isset($data['point_of_interaction']['transaction_data']['qr_code'])) {
    echo "QR code found (string)\n";
} else {
    echo "No QR code in response. Debug: \n";
    print_r(array_keys($data));
}
