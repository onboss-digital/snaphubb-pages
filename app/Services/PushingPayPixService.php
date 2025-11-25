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
        
        Log::warning('PushingPayPixService::__construct', [
            'environment' => $environment,
            'PP_ACCESS_TOKEN_PROD' => env('PP_ACCESS_TOKEN_PROD') ? 'SET' : 'EMPTY',
            'PP_ACCESS_TOKEN_SANDBOX' => env('PP_ACCESS_TOKEN_SANDBOX') ? 'SET' : 'EMPTY',
        ]);
        
        if ($environment === 'production') {
            $this->baseUrl = 'https://api.pushinpay.com.br/api';
            $this->accessToken = env('PP_ACCESS_TOKEN_PROD');
        } else {
            // Assumindo que o usuário configurará o ambiente sandbox
            $this->baseUrl = 'https://api-sandbox.pushinpay.com.br/api';
            $this->accessToken = env('PP_ACCESS_TOKEN_SANDBOX');
        }

        if (empty($this->accessToken)) {
            Log::warning("PushingPayPixService: Access Token não configurado para o ambiente '{$environment}'. Usando modo de simulação local.");
            // Não lançar exceção em ambiente de desenvolvimento; ativar simulação
            $this->simulate = true;
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
        // A Pushing Pay recomenda o uso de Webhook para notificação de status.
        // Se o seu sistema já usa Polling, você pode manter o webhook_url como null
        // ou usar o endpoint de status para consulta.
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
                Log::channel('payment_checkout')->info('PushingPayPixService: Simulando criação de PIX (token ausente)', ['mock_id' => $mockId, 'value' => $value]);
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
                // Campos como 'description', 'customerEmail' e 'customerName' não são suportados
                // diretamente na chamada de criação da Pushing Pay, mas podem ser usados no seu sistema
                // para identificação interna ou no webhook.
            ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['id'])) {
                return [
                    'status' => 'success',
                    'data' => [
                        'payment_id' => $responseData['id'], // ID da transação Pushing Pay
                        'qr_code_base64' => $responseData['qr_code_base64'] ?? null,
                        'qr_code' => $responseData['qr_code'] ?? null, // Código copia e cola (Pix Copia e Cola)
                        // A Pushing Pay não retorna a data de expiração, assumimos 30 minutos (padrão Mercado Pago)
                        'expiration_date' => now()->addMinutes(30)->toIso8601String(), 
                        'amount' => ($value / 100),
                        'status' => $this->mapStatus($responseData['status'] ?? 'created'), // 'pending'
                    ]
                ];
            }

            Log::error('Pushing Pay PIX Creation Error', ['response' => $responseData]);
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
