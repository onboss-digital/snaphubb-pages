<?php

/**
 * Script para simular webhook do PushinPay
 * 
 * Simula o recebimento de uma notificação de pagamento aprovado
 * Teste rápido para validar a integração webhook
 */

$baseUrl = 'http://127.0.0.1:8005';
$webhookUrl = $baseUrl . '/api/pix/webhook';
$token = '55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zDAs3Iov67688d1b';

// Dados simulados de um pagamento PIX aprovado
$paymentId = 'a0e12c27-0b3c-468c-aff7-f3c75dd21fd6'; // Usar um que existe no banco

$payload = [
    'event' => 'payment.approved',
    'data' => [
        'id' => $paymentId,
        'status' => 'paid',
        'value' => 102,
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => date('Y-m-d H:i:s'),
        'payer' => [
            'name' => 'Luiz Boss',
            'email' => 'luizboss2022@gmail.com',
            'document' => '02260133240',
        ],
        'product_id' => 'push_FPDNELFJMNR521RMK',
        'metadata' => [
            'product_main_hash' => 'prod_SZ4hJ7Q5aDSvVP',
            'bumps_selected' => '',
        ],
    ],
];

echo "==================================================\n";
echo "Teste de Webhook PushinPay\n";
echo "==================================================\n\n";

echo "URL do Webhook: " . $webhookUrl . "\n";
echo "Token: " . $token . "\n";
echo "Payment ID: " . $paymentId . "\n";
echo "Evento: payment.approved\n\n";

echo "Enviando requisição...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-pushinpay-token: ' . $token,
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Status HTTP: " . $httpCode . "\n";
echo "Resposta do servidor:\n";
echo $response . "\n\n";

if ($curlError) {
    echo "Erro cURL: " . $curlError . "\n";
}

if ($httpCode === 302 || $httpCode === 301) {
    echo "\n✅ Webhook recebido e redirecionando (esperado para sucesso)\n";
} else if ($httpCode === 200) {
    echo "\n✅ Webhook recebido com sucesso (200 OK)\n";
} else if ($httpCode === 401) {
    echo "\n❌ Token inválido ou não autorizado (401)\n";
} else if ($httpCode === 404) {
    echo "\n❌ Webhook URL não encontrada (404)\n";
} else {
    echo "\n⚠️ Status inesperado\n";
}

echo "\nVerifique os logs em:\n";
echo "- storage/logs/laravel.log\n";
echo "- storage/logs/payment_checkout.log\n";
