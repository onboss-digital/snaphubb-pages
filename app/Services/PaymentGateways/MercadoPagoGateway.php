<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    protected $client;
    protected $accessToken;
    protected $apiUrl;
    protected $environment;

    public function __construct()
    {
        $this->client = new Client();
        $this->environment = config('services.mercadopago.env', 'sandbox');
        
        // Select token based on environment
        if ($this->environment === 'production') {
            $this->accessToken = config('services.mercadopago.production_token');
        } else {
            $this->accessToken = config('services.mercadopago.sandbox_token');
        }
        
        // Fallback to generic token if specific one not found
        if (!$this->accessToken) {
            $this->accessToken = config('services.mercadopago.access_token');
        }
        
        $this->apiUrl = 'https://api.mercadopago.com';
        
        Log::info('MercadoPagoGateway initialized', [
            'environment' => $this->environment,
            'api_url' => $this->apiUrl,
        ]);
    }

    /**
     * Get current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if running in sandbox
     */
    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    public function createCardToken(array $cardData): array
    {
        // Not used for PIX payments
        return [];
    }

    public function processPayment(array $paymentData): array
    {
        if ($paymentData['payment_method'] === 'pix') {
            return $this->createPixPayment($paymentData);
        }

        // Handle other payment methods if needed
        return [
            'status' => 'error',
            'message' => 'Unsupported payment method',
        ];
    }

    public function createPixPayment(array $paymentData): array
    {
        $requestBody = [
            'transaction_amount' => (float) ($paymentData['amount'] / 100),
            'description' => $paymentData['description'] ?? 'Pagamento',
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $paymentData['customer']['email'],
                'first_name' => $paymentData['customer']['name'],
            ],
        ];

        Log::channel('payment_checkout')->info('MercadoPago PIX Request:', [
            'endpoint' => "{$this->apiUrl}/v1/payments",
            'request_body' => $requestBody,
            'access_token_configured' => !empty($this->accessToken),
        ]);

        try {
            $response = $this->client->post("{$this->apiUrl}/v1/payments", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                    'X-Idempotency-Key' => (string) Str::uuid(),
                ],
                'json' => $requestBody,
            ]);

            $body = json_decode($response->getBody(), true);

            Log::channel('payment_checkout')->info('MercadoPago PIX Response:', [
                'status_code' => $response->getStatusCode(),
                'response_body' => $body,
            ]);

            return [
                'status' => 'success',
                'data' => [
                    'qr_code' => $body['point_of_interaction']['transaction_data']['qr_code'] ?? null,
                    'qr_code_base64' => $body['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
                    'transaction_id' => $body['id'] ?? null,
                ],
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            Log::channel('payment_checkout')->error('MercadoPago PIX Client Error:', [
                'statusCode' => $statusCode,
                'body' => $body,
                'data' => $paymentData,
            ]);

            $message = 'Um erro desconhecido ocorreu.';
            if ($statusCode == 403) {
                $message = 'Acesso proibido (403). Verifique suas credenciais de API.';
            } elseif ($statusCode == 400) {
                $message = 'Requisição inválida (400). Verifique os dados enviados.';
                if (isset($body['cause'][0]['description'])) {
                    $message .= ' - ' . $body['cause'][0]['description'];
                }
            } elseif (isset($body['message'])) {
                $message = $body['message'];
            }

            return [
                'status' => 'error',
                'message' => $message,
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::channel('payment_checkout')->error('MercadoPago PIX Connection Error:', [
                'message' => $e->getMessage(),
                'data' => $paymentData,
            ]);

            return [
                'status' => 'error',
                'message' => 'Erro de conexão com a API do Mercado Pago. Tente novamente mais tarde.',
            ];
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('MercadoPago PIX General Error:', [
                'message' => $e->getMessage(),
                'data' => $paymentData,
            ]);

            return [
                'status' => 'error',
                'message' => 'A general error occurred while processing the PIX payment.',
            ];
        }
    }

    public function handleResponse(array $responseData): array
    {
        // Not implemented for this flow
        return [];
    }

    public function formatPlans(mixed $data, string $selectedCurrency): array
    {
        // Not used for PIX payments
        return $data;
    }

    public function getPaymentStatus($transactionId)
    {
        try {
            $response = $this->client->get("{$this->apiUrl}/v1/payments/{$transactionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            return [
                'status' => 'success',
                'data' => [
                    'status' => $body['status'],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
