<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushingPayPixService
{
    protected $baseUrl = 'https://api.sandbox.pushinpay.com/v1';
    protected $accessToken;
    protected $environment;

    public function __construct()
    {
        $this->environment = env('ENVIRONMENT', 'production');
        $this->accessToken = env('PP_ACCESS_TOKEN_PRODUCTION') ?? env('PP_ACCESS_TOKEN_PROD');
        
        // Ajustar URL baseado no ambiente
        if ($this->environment === 'sandbox') {
            $this->baseUrl = 'https://api.sandbox.pushinpay.com/v1';
        } else {
            $this->baseUrl = 'https://api.pushinpay.com/v1';
        }
    }

    /**
     * Criar um pagamento PIX
     */
    public function createPixPayment(array $pixData): array
    {
        try {
            Log::info('PushingPayPixService: Criando pagamento PIX', [
                'amount' => $pixData['amount'] ?? null,
                'customer_email' => $pixData['customer']['email'] ?? null,
            ]);

            $payload = [
                'amount' => (int)(($pixData['amount'] ?? 0) * 100), // Converter para centavos
                'currency' => $pixData['currency'] ?? 'BRL',
                'customer' => [
                    'name' => $pixData['customer']['name'] ?? '',
                    'email' => $pixData['customer']['email'] ?? '',
                    'tax_id' => $pixData['customer']['cpf'] ?? '',
                    'phone' => $pixData['customer']['phone'] ?? '',
                ],
                'description' => $pixData['description'] ?? 'Pagamento Snaphubb',
                'return_url' => $pixData['return_url'] ?? url('/'),
                'webhook_url' => $pixData['webhook_url'] ?? url('/api/webhook/generic-pix'),
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/charges", $payload);

            if (!$response->successful()) {
                Log::error('PushingPayPixService: Erro ao criar PIX', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Erro ao gerar PIX',
                    'error' => $response->json()['message'] ?? 'Erro desconhecido',
                ];
            }

            $data = $response->json();
            
            Log::info('PushingPayPixService: PIX criado com sucesso', [
                'payment_id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
            ]);

            return [
                'success' => true,
                'data' => [
                    'payment_id' => $data['id'] ?? null,
                    'qr_code' => $data['qr_code'] ?? null,
                    'qr_code_base64' => $data['qr_code_url'] ?? null,
                    'copia_e_cola' => $data['qr_code'] ?? null,
                    'expiration_date' => $data['expires_at'] ?? null,
                    'amount' => $data['amount'] / 100 ?? null, // Voltar ao valor normal
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PushingPayPixService Exception: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar PIX',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obter status de um pagamento
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
            ])->get("{$this->baseUrl}/charges/{$paymentId}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'status' => 'unknown',
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('PushingPayPixService getPaymentStatus Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }
}
