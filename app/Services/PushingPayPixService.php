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
        // Sempre usar produção - o token está configurado
        $this->baseUrl = 'https://api.pushinpay.com.br/api';
        
        // Tentar ler token do .env com múltiplas tentativas
        $token = trim(env('PP_ACCESS_TOKEN_PROD', ''), ' "\'');
        
        // Se falhar, tentar verificar via $_ENV ou getenv()
        if (empty($token)) {
            $token = trim(getenv('PP_ACCESS_TOKEN_PROD') ?: '', ' "\'');
        }
        
        // Se ainda não encontrou, tentar com config()
        if (empty($token)) {
            $token = trim(config('services.pushing_pay.token_prod', ''), ' "\'');
        }
        
        $this->accessToken = $token;

        if (empty($this->accessToken)) {
            Log::warning("PushingPayPixService: ⚠️ Token de produção NÃO ENCONTRADO - usando SIMULAÇÃO", [
                'env_check' => env('PP_ACCESS_TOKEN_PROD') ? 'tem valor' : 'vazio',
                'getenv_check' => getenv('PP_ACCESS_TOKEN_PROD') ? 'tem valor' : 'vazio',
                'config_check' => config('services.pushing_pay.token_prod') ? 'tem valor' : 'vazio',
            ]);
            $this->simulate = true;
        } else {
            Log::info("PushingPayPixService: ✅ Token de produção encontrado com " . strlen($this->accessToken) . " caracteres");
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
            if ($this->simulate) {
                $mockId = 'sim_' . time() . rand(1000, 9999);
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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/pix/cashIn", [
                'value' => $value,
                'webhook_url' => $webhookUrl,
            ]);

            $responseData = $response->json();

            Log::info('PushingPayPixService: Response da criação de PIX', [
                'status_code' => $response->status(),
                'response_keys' => array_keys($responseData),
                'full_response' => $responseData,
            ]);

            // O campo de ID pode estar em: 'id', 'payment_id', ou 'transactionId'
            $paymentId = $responseData['id'] ?? $responseData['payment_id'] ?? $responseData['transactionId'] ?? null;

            if ($response->successful() && $paymentId) {
                $qrCode = $responseData['qr_code'] 
                    ?? $responseData['copyAndPaste'] 
                    ?? $responseData['pix_code'] 
                    ?? $responseData['code'] 
                    ?? null;

                $qrCodeBase64 = $responseData['qr_code_base64'] 
                    ?? $responseData['qr_code'] 
                    ?? null;

                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $paymentId,
                        'qr_code_base64' => $qrCodeBase64,
                        'qr_code' => $qrCode,
                        'expiration_date' => now()->addMinutes(30)->toIso8601String(), 
                        'amount' => ($value / 100),
                        'status' => $this->mapStatus($responseData['status'] ?? 'created'),
                    ]
                ];
            }

            Log::error('Pushing Pay PIX Creation Error', [
                'response' => $responseData,
                'status_code' => $response->status(),
            ]);
            return [
                'status' => 'error',
                'message' => $responseData['message'] ?? 'Erro ao criar pagamento PIX na Pushing Pay.',
            ];

        } catch (\Exception $e) {
            Log::error('Pushing Pay PIX Creation Exception', ['exception' => $e->getMessage()]);
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

            Log::info('PushingPayPixService: Consultando status do PIX', [
                'payment_id' => $paymentId,
                'url' => "{$this->baseUrl}/pix/{$paymentId}",
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/pix/{$paymentId}");

            $responseData = $response->json();

            Log::info('PushingPayPixService: Response do status do PIX', [
                'status_code' => $response->status(),
                'payment_id' => $paymentId,
                'response_keys' => array_keys($responseData),
            ]);

            // O campo de ID pode estar em: 'id', 'payment_id', ou 'transactionId'
            $responsePaymentId = $responseData['id'] ?? $responseData['payment_id'] ?? $responseData['transactionId'] ?? null;

            if ($response->successful() && $responsePaymentId) {
                // Mapeamento de status da Pushing Pay para o padrão do Mercado Pago
                $mappedStatus = $this->mapStatus($responseData['status'] ?? 'created');

                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $responsePaymentId,
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
