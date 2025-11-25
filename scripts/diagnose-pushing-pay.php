<?php
/**
 * Script de diagnóstico para Pushing Pay PIX
 * Ejecutar: php scripts/diagnose-pushing-pay.php
 */

require __DIR__ . '/../bootstrap/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "================================\n";
echo "DIAGNÓSTICO PUSHING PAY PIX\n";
echo "================================\n\n";

// 1. Verificar variáveis de ambiente
echo "1️⃣ VARIÁVEIS DE AMBIENTE:\n";
echo "   ENVIRONMENT: " . (env('ENVIRONMENT', 'não definido')) . "\n";
echo "   PP_ACCESS_TOKEN_PROD length: " . strlen(env('PP_ACCESS_TOKEN_PROD', '')) . "\n";
echo "   PP_ACCESS_TOKEN_SANDBOX length: " . strlen(env('PP_ACCESS_TOKEN_SANDBOX', '')) . "\n\n";

// 2. Testar serviço
echo "2️⃣ TESTANDO SERVIÇO PUSHING PAY:\n";

try {
    $service = app(\App\Services\PushingPayPixService::class);
    
    $testData = [
        'amount' => 2490,
        'description' => 'Teste Diagnóstico',
    ];
    
    $result = $service->createPixPayment($testData);
    
    if ($result['status'] === 'success') {
        $paymentId = $result['data']['payment_id'] ?? 'desconhecido';
        
        if (strpos($paymentId, 'sim_') === 0) {
            echo "   ❌ MODO SIMULAÇÃO ATIVO\n";
            echo "   Payment ID: " . $paymentId . "\n";
            echo "   Motivo: Token da Pushing Pay não configurado corretamente\n\n";
            echo "   SOLUÇÃO:\n";
            echo "   1. Verificar se .env tem PP_ACCESS_TOKEN_PROD configurado\n";
            echo "   2. Limpar cache: rm bootstrap/cache/config.php\n";
            echo "   3. Executar: php artisan config:cache\n";
            echo "   4. Reiniciar servidor\n";
        } else {
            echo "   ✅ MODO PRODUÇÃO ATIVO\n";
            echo "   Payment ID (UUID): " . $paymentId . "\n";
            echo "   QR Code (base64): " . substr($result['data']['qr_code_base64'] ?? '', 0, 30) . "...\n";
            echo "   QR Code (texto): " . substr($result['data']['qr_code'] ?? '', 0, 50) . "...\n";
        }
    } else {
        echo "   ❌ ERRO: " . ($result['message'] ?? 'Desconhecido') . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ EXCEÇÃO: " . $e->getMessage() . "\n";
}

echo "\n================================\n";
echo "FIM DO DIAGNÓSTICO\n";
echo "================================\n";
?>
