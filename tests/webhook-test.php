<?php

/**
 * Script de teste do webhook da Pushing Pay
 * 
 * Simula um pagamento aprovado para testar o recebimento e processamento
 * do webhook em https://pay.snaphubb.com/api/pix/webhook
 * 
 * Uso:
 *   php tests/webhook-test.php [local|production]
 */

// Determinar ambiente
$environment = $argv[1] ?? 'local';
$baseUrl = ($environment === 'production') 
    ? 'https://pay.snaphubb.com'
    : 'http://127.0.0.1:8000';

$webhookUrl = $baseUrl . '/api/pix/webhook';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TESTE DE WEBHOOK - PUSHING PAY PIX                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "📍 Ambiente: " . strtoupper($environment) . "\n";
echo "🔗 URL Webhook: " . $webhookUrl . "\n";
echo "⏱️  Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Simular payload de pagamento aprovado
$paymentId = 'PIX_' . uniqid();
$payload = [
    'event' => 'payment.approved',
    'data' => [
        'id' => $paymentId,
        'transaction_id' => 'TXN_' . uniqid(),
        'amount' => 24.90,
        'currency' => 'BRL',
        'status' => 'approved',
        'timestamp' => date('c'),
        'payer' => [
            'name' => 'Test Payer',
            'email' => 'test@example.com',
            'phone' => '11999999999',
        ],
        'metadata' => [
            'order_id' => '12345',
            'user_id' => '1',
            'payment_method' => 'pix',
        ],
    ],
];

echo "📤 Enviando payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Enviar requisição
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $webhookUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: WebhookTest/1.0',
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => ($environment === 'production'),
    CURLOPT_VERBOSE => true,
]);

// Capturar verbose output
$verboseHandle = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);

echo "⏳ Enviando requisição...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Ler verbose output
rewind($verboseHandle);
$verboseOutput = stream_get_contents($verboseHandle);
fclose($verboseHandle);

curl_close($ch);

// Resultado
echo "═══════════════════════════════════════════════════════════\n";
echo "RESULTADO DA REQUISIÇÃO\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✓ HTTP Status: " . $httpCode . "\n";

if ($error) {
    echo "✗ Erro CURL: " . $error . "\n\n";
} else {
    echo "✓ Conectado com sucesso\n\n";
}

if ($response) {
    echo "📥 Resposta do servidor:\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    } else {
        echo $response . "\n\n";
    }
}

// Verificar sucesso
echo "═══════════════════════════════════════════════════════════\n";
if ($httpCode === 200 && !$error) {
    echo "✅ WEBHOOK RECEBIDO COM SUCESSO!\n";
    echo "\n📋 Verificar logs em: storage/logs/laravel.log\n";
    echo "🔍 Procurar por: 'Pushing Pay webhook received'\n";
    echo "🔍 Procurar por: 'Payment ID: " . $paymentId . "'\n";
} else {
    echo "❌ ERRO AO ENVIAR WEBHOOK\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Verificar se o servidor está rodando\n";
    echo "2. Verificar se a URL está correta: " . $webhookUrl . "\n";
    echo "3. Verificar logs em storage/logs/laravel.log\n";
    
    if ($environment === 'production') {
        echo "4. Verificar SSL certificate\n";
        echo "5. Testar localmente primeiro com 'php tests/webhook-test.php local'\n";
    }
}
echo "═══════════════════════════════════════════════════════════\n";

// Script para verificar logs
echo "\n💡 Para verificar se foi processado, execute:\n";
if ($environment === 'production') {
    echo "   tail -f storage/logs/laravel.log | grep -i 'pushing pay'\n";
} else {
    echo "   tail -f storage/logs/laravel.log\n";
}

echo "\n";
