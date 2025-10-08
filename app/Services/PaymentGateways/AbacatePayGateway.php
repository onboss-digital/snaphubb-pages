<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AbacatePayGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $redirectSuccessURL = "https://web.snaphubb.online/obg/";
    private string $redirectFailURL = "https://web.snaphubb.online/fail2/";
    private int $defaultExpiration;
    private Client $client;

    public function __construct(Client $client = null)
    {
        $this->apiKey = config('services.abacatepay.api_key');
        $this->baseUrl = config('services.abacatepay.api_url');
        $this->defaultExpiration = config('services.abacatepay.pix_expiration', 1800);

        $this->client = $client ?: new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'verify' => !env('APP_DEBUG'),
        ]);
    }

    /**
     * Faz requisição HTTP para a API do AbacatePay
     */
    private function request(string $method, string $endpoint, array $data = [])
    {
        try {
            $options = [];

            if (in_array(strtolower($method), ['post', 'put', 'patch']) && !empty($data)) {
                $options['json'] = $data;
            } elseif (!empty($data)) {
                $options['query'] = $data;
            }

            $response = $this->client->request(strtoupper($method), $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $body = $e->getResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            $errorMessage = $body['error']['message'] ?? $body['message'] ?? $e->getMessage();
            
            Log::channel('payment_checkout')->error('AbacatePayGateway: API Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $errorMessage,
                'body' => $body,
            ]);

            throw new \Exception($errorMessage);
        }
    }

    /**
     * Cria um QR Code PIX para pagamento
     * 
     * @param array $paymentData Dados do pagamento
     * @return array Resposta com dados do PIX
     */
    public function createPixQr(array $paymentData): array
    {
        try {
            $payload = [
                'amount' => $paymentData['amount'], // em centavos
                'expiresIn' => $paymentData['expiresIn'] ?? $this->defaultExpiration,
            ];

            // Adicionar customer se fornecido
            if (!empty($paymentData['customer'])) {
                $payload['customer'] = [
                    'name' => $paymentData['customer']['name'] ?? null,
                    'email' => $paymentData['customer']['email'] ?? null,
                    'cellphone' => $paymentData['customer']['phone_number'] ?? null,
                    'taxId' => $paymentData['customer']['document'] ?? null,
                ];
            }

            // Adicionar metadata (UTMs, etc)
            if (!empty($paymentData['metadata'])) {
                $payload['metadata'] = $paymentData['metadata'];
            }

            $response = $this->request('POST', '/pixQrCode/create', $payload);

            Log::channel('payment_checkout')->info('AbacatePayGateway: PIX QR Code criado', [
                'id' => $response['id'] ?? null,
                'amount' => $payload['amount'],
                'status' => $response['status'] ?? null,
            ]);

            return [
                'status' => 'success',
                'data' => $response,
            ];
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('AbacatePayGateway: Erro ao criar PIX', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica o status de um pagamento PIX
     * 
     * @param string $pixId ID do PIX
     * @return array Status do pagamento
     */
    public function checkPixStatus(string $pixId): array
    {
        try {
            $response = $this->request('GET', "/pixQrCode/{$pixId}");

            return [
                'status' => 'success',
                'data' => $response,
            ];
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('AbacatePayGateway: Erro ao verificar status', [
                'pix_id' => $pixId,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implementação da interface PaymentGatewayInterface
     * Para PIX, não criamos token de cartão
     */
    public function createCardToken(array $cardData): array
    {
        return [
            'status' => 'error',
            'message' => 'PIX não utiliza token de cartão.',
        ];
    }

    /**
     * Processa pagamento PIX
     * Cria o QR Code e retorna os dados para exibição
     */
    public function processPayment(array $paymentData): array
    {
        try {
            // Para PIX, criamos o QR Code
            $result = $this->createPixQr($paymentData);

            if ($result['status'] === 'error') {
                return $result;
            }

            $pixData = $result['data'];

            return [
                'status' => 'success',
                'data' => [
                    'pix_id' => $pixData['id'],
                    'brCode' => $pixData['brCode'],
                    'brCodeBase64' => $pixData['brCodeBase64'],
                    'amount' => $pixData['amount'],
                    'status' => $pixData['status'],
                    'expiresAt' => $pixData['expiresAt'],
                    'expiresIn' => $pixData['expiresIn'],
                ],
            ];
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('AbacatePayGateway: Erro ao processar pagamento', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formata resposta do gateway
     */
    public function handleResponse(array $responseData): array
    {
        $status = $responseData['status'] ?? 'PENDING';

        if ($status === 'PAID') {
            return [
                'status' => 'success',
                'transaction_id' => $responseData['id'] ?? null,
                'redirect_url' => $this->redirectSuccessURL,
                'data' => $responseData,
            ];
        }

        if (in_array($status, ['EXPIRED', 'CANCELLED', 'FAILED'])) {
            return [
                'status' => 'error',
                'message' => 'Pagamento não concluído.',
                'redirect_url' => $this->redirectFailURL,
                'data' => $responseData,
            ];
        }

        return [
            'status' => 'pending',
            'message' => 'Aguardando pagamento.',
            'data' => $responseData,
        ];
    }

    /**
     * Busca produto com preços
     * Para AbacatePay, não temos produtos no gateway, então retornamos estrutura vazia
     */
    public function getProductWithPrices(string $productId): array
    {
        return [
            'status' => 'success',
            'product' => [
                'id' => $productId,
                'name' => 'Produto PIX',
                'description' => 'Pagamento via PIX',
            ],
            'prices' => [],
        ];
    }

    /**
     * Formata planos para o formato esperado pelo PagePay
     * Para PIX, mantemos a mesma estrutura dos planos vindos da API
     */
    public function formatPlans(mixed $data, string $selectedCurrency): array
    {
        $result = [];

        foreach ($data['data'] ?? [] as $plan) {
            // Definição da chave do plano
            $key = match ($plan['duration']) {
                'week'  => 'weekly',
                'month' => match ((int)$plan['duration_value']) {
                    3       => 'quarterly',
                    6       => 'semi-annual',
                    default => 'monthly',
                },
                'year'  => 'annual',
                default => $plan['duration'],
            };

            // Para PIX, usamos os preços diretamente da API
            $prices = [];
            if (!empty($plan['price'])) {
                $prices[$selectedCurrency] = [
                    'id' => null, // PIX não precisa de price_id
                    'origin_price' => $plan['price'],
                    'descont_price' => $plan['price'],
                    'recurring' => null,
                ];
            }

            // Order Bumps para PIX
            $bumps = array_values(array_filter(array_map(function ($order_bump) use ($selectedCurrency) {
                if (empty($order_bump['external_id'])) return null;

                return [
                    'id' => $order_bump['external_id'],
                    'title' => $order_bump['title'],
                    'text_button' => $order_bump['text_button'],
                    'description' => $order_bump['description'],
                    'price' => $order_bump['price'] ?? 0,
                    'price_id' => null,
                    'recurring' => null,
                    'currency' => $selectedCurrency,
                    'hash' => $order_bump['external_id'],
                    'active' => false,
                ];
            }, $plan['order_bumps'] ?? [])));

            $result[$key] = [
                'hash' => $plan['pages_product_external_id'] ?? $plan['id'],
                'label' => ucfirst($plan['name']) . " - {$plan['duration_value']}/{$plan['duration']}",
                'nunber_months' => $plan['duration_value'],
                'prices' => $prices,
                'upsell_url' => $plan['pages_upsell_url'] ?? null,
                'order_bumps' => $bumps,
            ];
        }

        return $result;
    }

    /**
     * Valida assinatura de webhook
     * 
     * @param string $payload Payload do webhook
     * @param string $signature Assinatura recebida
     * @return bool
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.abacatepay.webhook_secret');
        
        if (empty($secret)) {
            Log::warning('AbacatePayGateway: Webhook secret não configurado');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}
