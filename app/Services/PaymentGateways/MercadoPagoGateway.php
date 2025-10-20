<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    protected $client;
    protected $accessToken;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->accessToken = config('services.mercadopago.access_token');
        $this->apiUrl = 'https://api.mercadopago.com';
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
            'transaction_amount' => $paymentData['amount'] / 100,
            'description' => $paymentData['cart'][0]['title'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $paymentData['customer']['email'],
                'first_name' => $paymentData['customer']['name'],
            ],
        ];

        Log::debug('MercadoPago PIX Request Body:', $requestBody);

        try {
            $response = $this->client->post("{$this->apiUrl}/v1/payments", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);

            $body = json_decode($response->getBody(), true);

            return [
                'status' => 'success',
                'data' => [
                    'qr_code' => $body['point_of_interaction']['transaction_data']['qr_code'],
                    'qr_code_base64' => $body['point_of_interaction']['transaction_data']['qr_code_base64'],
                    'transaction_id' => $body['id'],
                ],
            ];
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('MercadoPago PIX Error:', [
                'message' => $e->getMessage(),
                'data' => $paymentData,
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
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
