<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushingPayPixService
{
    /**
     * URL base da API da Pushing Pay.
     * @var string
     */
    protected $baseUrl;

    /**
     * Token de acesso (Bearer Token).
     * @var string
     */
    protected $accessToken;
    /**
     * Se true, o serviço irá simular respostas (modo de desenvolvimento)
     * quando o token não estiver configurado.
     * @var bool
     */
    protected $simulate = false;

    public function __construct()
    {
        $environment = env('ENVIRONMENT', 'sandbox');
        
        // Ler token e remover aspas se existirem
        $tokenProd = trim(env('PP_ACCESS_TOKEN_PROD', ''), ' "\'');
        $tokenSandbox = trim(env('PP_ACCESS_TOKEN_SANDBOX', ''), ' "\'');
        
        Log::error('🔍 [PUSHING PAY INIT] Detecção de Token', [
            'environment' => $environment,
            'ENVIRONMENT_VAR' => getenv('ENVIRONMENT'),
            'PP_ACCESS_TOKEN_PROD_raw' => env('PP_ACCESS_TOKEN_PROD'),
            'PP_ACCESS_TOKEN_PROD_trimmed' => $tokenProd,
            'PP_ACCESS_TOKEN_PROD_exists' => !empty($tokenProd),
            'PP_ACCESS_TOKEN_PROD_length' => strlen($tokenProd),
            'PP_ACCESS_TOKEN_SANDBOX_exists' => !empty($tokenSandbox),
            'PP_ACCESS_TOKEN_SANDBOX_length' => strlen($tokenSandbox),
        ]);
        
        if ($environment === 'production') {
            $this->baseUrl = 'https://api.pushinpay.com.br/api';
            $this->accessToken = $tokenProd;
            Log::error('🔍 [PUSHING PAY PROD] Configuração de Produção', [
                'baseUrl' => $this->baseUrl,
                'token_found' => !empty($this->accessToken),
                'token_preview' => !empty($this->accessToken) ? substr($this->accessToken, 0, 20) . '...' : 'VAZIO',
            ]);
        } else {
            // Assumindo que o usuário configurará o ambiente sandbox
            $this->baseUrl = 'https://api-sandbox.pushinpay.com.br/api';
            $this->accessToken = $tokenSandbox;
            Log::error('🔍 [PUSHING PAY SANDBOX] Configuração de Sandbox', [
                'baseUrl' => $this->baseUrl,
                'token_found' => !empty($this->accessToken),
            ]);
        }

        if (empty($this->accessToken)) {
            Log::error("❌ [PUSHING PAY] Access Token NÃO CONFIGURADO para '{$environment}' - ATIVANDO MODO SIMULAÇÃO!");
            // Não lançar exceção em ambiente de desenvolvimento; ativar simulação
            $this->simulate = true;
        } else {
            Log::error("✅ [PUSHING PAY] Access Token encontrado - usando API REAL");
            $this->simulate = false;
        }
    }

    /**
     * Cria um novo pagamento PIX.
     *
     * @param array $data {
     *     @var int $amount Valor em centavos (mínimo 50).
     *     @var string $description Descrição do pagamento (não usado diretamente na API Pushing Pay, mas mantido para compatibilidade).
     *     @var string $customerEmail Email do cliente (não usado diretamente na API Pushing Pay, mas mantido para compatibilidade).
     *     @var string $customerName Nome do cliente (não usado diretamente na API Pushing Pay, mas mantido para compatibilidade).
     *     @var string|null $webhook_url URL para notificação de status.
     * }
     * @return array
     */
    public function createPixPayment(array $data): array
    {
        $value = $data['amount'] ?? 0;
        $webhookUrl = $data['webhook_url'] ?? null; 

        if ($value < 50) {
            return [
                'status' => 'error',
                'message' => 'O valor mínimo para PIX é de 50 centavos.',
            ];
        }

        try {
            // Se estamos em modo de simulação, retornar uma resposta mock sem chamar a API externa
            if ($this->simulate) {
                $mockId = 'sim_' . time() . rand(1000, 9999);
                Log::error('❌ [PUSHING PAY] MODO SIMULAÇÃO ATIVADO - PIX SIMULADO', [
                    'mock_id' => $mockId, 
                    'value' => $value,
                    'reason' => 'Token não foi encontrado no construtor',
                ]);
                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $mockId,
                        'qr_code_base64' => base64_encode('SIMULATED_QR_' . $mockId),
                        'qr_code' => '00020126360014BR.GOV.BCB.PIX0114SIMULATED' . $mockId,
                        'expiration_date' => now()->addMinutes(30)->toIso8601String(),
                        'amount' => ($value / 100),
                        'status' => 'pending',
                    ],
                ];
            }

            Log::error('🚀 [PUSHING PAY] Enviando requisição para API REAL', [
                'url' => "{$this->baseUrl}/pix/cashIn",
                'value' => $value,
                'webhook_url' => $webhookUrl,
                'token_preview' => substr($this->accessToken, 0, 20) . '...',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/pix/cashIn", [
                'value' => $value,
                'webhook_url' => $webhookUrl,
            ]);

            $responseData = $response->json();

            // Log completo da resposta para debug em produção
            Log::error('📨 [PUSHING PAY] Resposta da API', [
                'status_code' => $response->status(),
                'response_keys' => array_keys($responseData ?? []),
                'response' => $responseData,
                'environment' => $this->baseUrl,
            ]);

            if ($response->successful() && isset($responseData['id'])) {
                // Tenta diferentes nomes de campo para o código PIX
                $qrCode = $responseData['qr_code'] 
                    ?? $responseData['copyAndPaste'] 
                    ?? $responseData['pix_code'] 
                    ?? $responseData['code'] 
                    ?? null;

                $qrCodeBase64 = $responseData['qr_code_base64'] 
                    ?? $responseData['qrCodeBase64']
                    ?? $responseData['qr_code'] 
                    ?? null;

                Log::error('✅ [PUSHING PAY] PIX Criado com Sucesso!', [
                    'payment_id' => $responseData['id'],
                    'qr_code_found' => !empty($qrCode),
                    'qr_code' => $qrCode ? substr($qrCode, 0, 50) . '...' : 'NOT_FOUND',
                    'qr_code_base64_found' => !empty($qrCodeBase64),
                    'all_response_fields' => json_encode($responseData),
                ]);

                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $responseData['id'],
                        'qr_code_base64' => $qrCodeBase64,
                        'qr_code' => $qrCode,
                        'expiration_date' => now()->addMinutes(30)->toIso8601String(), 
                        'amount' => ($value / 100),
                        'status' => $this->mapStatus($responseData['status'] ?? 'created'),
                    ]
                ];
            }

            Log::error('❌ [PUSHING PAY] Erro ao Criar PIX', [
                'response' => $responseData,
                'status_code' => $response->status(),
            ]);
            return [
                'status' => 'error',
                'message' => $responseData['message'] ?? 'Erro ao criar pagamento PIX na Pushing Pay.',
            ];

        } catch (\Exception $e) {
            Log::error('💥 [PUSHING PAY] Exceção', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'status' => 'error',
                'message' => 'Exceção ao comunicar com a Pushing Pay: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Consulta o status de um pagamento PIX.
     *
     * @param string $paymentId ID da transação Pushing Pay.
     * @return array
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            // Em modo de simulação, retornamos um status pendente por padrão
            if ($this->simulate) {
                Log::channel('payment_checkout')->debug('PushingPayPixService: Simulando getPaymentStatus', ['payment_id' => $paymentId]);
                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $paymentId,
                        'payment_status' => 'pending',
                        'status_detail' => null,
                        'amount' => null,
                    ]
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/pix/cashIn/{$paymentId}");

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['id'])) {
                // Mapeamento de status da Pushing Pay para o padrão do Mercado Pago
                $mappedStatus = $this->mapStatus($responseData['status'] ?? 'created');

                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $responseData['id'],
                        'payment_status' => $mappedStatus,
                        'status_detail' => null,
                        'amount' => (($responseData['value'] ?? 0) / 100),
                    ]
                ];
            }

            Log::error('Pushing Pay PIX Status Error', ['response' => $responseData]);
            return [
                'status' => 'error',
                'message' => $responseData['message'] ?? 'Erro ao consultar status do pagamento PIX na Pushing Pay.',
            ];

        } catch (\Exception $e) {
            Log::error('Pushing Pay PIX Status Exception', ['exception' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Exceção ao comunicar com a Pushing Pay: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mapeia o status da Pushing Pay para o padrão do Mercado Pago.
     *
     * Pushing Pay Status: created | paid | canceled | expired
     * Mercado Pago Status: pending | approved | rejected | cancelled | expired
     *
     * @param string $ppStatus Status da Pushing Pay.
     * @return string
     */
    protected function mapStatus(string $ppStatus): string
    {
        switch (strtolower($ppStatus)) {
            case 'paid':
                return 'approved';
            case 'created':
                return 'pending';
            case 'canceled':
                return 'cancelled';
            case 'expired':
                return 'expired';
            default:
                return 'pending'; // Default para status desconhecido
        }
    }
}
