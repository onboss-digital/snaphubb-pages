<?php
/**
 * Script de teste para validar Pushing Pay em Produção
 * Execute via: php test-pushing-pay-production.php
 */

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use Illuminate\Support\Facades\Env;
use App\Services\PushingPayPixService;

echo "=== TESTE PUSHING PAY EM PRODUÇÃO ===\n\n";

echo "1. Verificando variáveis de ambiente:\n";
$tokenProd = env('PP_ACCESS_TOKEN_PROD');
$tokenSandbox = env('PP_ACCESS_TOKEN_SANDBOX');
$environment = env('ENVIRONMENT', 'sandbox');

echo "   - ENVIRONMENT: " . ($environment ?: 'NÃO CONFIGURADO') . "\n";
echo "   - PP_ACCESS_TOKEN_PROD: " . (strlen($tokenProd) > 0 ? '✓ Configurado (' . strlen($tokenProd) . ' chars)' : '✗ VAZIO') . "\n";
echo "   - PP_ACCESS_TOKEN_SANDBOX: " . (strlen($tokenSandbox) > 0 ? '✓ Configurado (' . strlen($tokenSandbox) . ' chars)' : '✗ VAZIO') . "\n\n";

echo "2. Testando instância do serviço:\n";
try {
    $service = app(PushingPayPixService::class);
    echo "   ✓ Serviço instanciado com sucesso\n\n";
    
    echo "3. Testando criação de PIX:\n";
    $testData = [
        'amount' => 2490,
        'description' => 'Teste PIX Produção',
        'customerEmail' => 'teste@teste.com',
    ];
    
    $result = $service->createPixPayment($testData);
    
    if ($result['status'] === 'success' && isset($result['data']['payment_id'])) {
        $paymentId = $result['data']['payment_id'];
        
        // Verificar se é modo simulação
        if (strpos($paymentId, 'sim_') === 0) {
            echo "   ⚠️  MODO SIMULAÇÃO DETECTADO!\n";
            echo "   - Payment ID: " . $paymentId . "\n";
            echo "   - Motivo: Token de Pushing Pay não configurado corretamente\n\n";
            echo "   AÇÃO NECESSÁRIA:\n";
            echo "   1. Verifique o arquivo .env no servidor\n";
            echo "   2. Confirme que PP_ACCESS_TOKEN_PROD está com o valor correto\n";
            echo "   3. Execute: php artisan config:cache\n";
            echo "   4. Reinicie o PHP/servidor\n";
        } else {
            echo "   ✓ PIX CRIADO COM SUCESSO EM PRODUÇÃO!\n";
            echo "   - Payment ID: " . $paymentId . "\n";
            echo "   - QR Code (base64): " . substr($result['data']['qr_code_base64'] ?? '', 0, 50) . "...\n";
            echo "   - QR Code (texto): " . substr($result['data']['qr_code'] ?? '', 0, 50) . "...\n";
        }
    } else {
        echo "   ✗ Erro ao criar PIX:\n";
        echo "   - Status: " . ($result['status'] ?? 'DESCONHECIDO') . "\n";
        echo "   - Mensagem: " . ($result['message'] ?? 'SEM MENSAGEM') . "\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Erro ao instanciar serviço:\n";
    echo "   - " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>
