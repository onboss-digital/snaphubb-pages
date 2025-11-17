<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serviço para gerenciar pagamentos PIX via Mercado Pago
 * Suporta ambientes de produção e sandbox automaticamente
 */
class MercadoPagoPixService
{
    private Client $client;
    private string $accessToken;
    private string $environment;
    private string $apiUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        // Determina o ambiente baseado na variável ENVIRONMENT
        $this->environment = env('ENVIRONMENT', 'sandbox');
        
        // Seleciona o token baseado no ambiente
        if ($this->environment === 'production') {
            $this->accessToken = env('MP_ACCESS_TOKEN_PROD', '');
        } else {
            $this->accessToken = env('MP_ACCESS_TOKEN_SANDBOX', '');
        }
        
        // Inicializa o cliente HTTP com verificação SSL desabilitada em debug
        $this->client = new Client([
            'verify' => env('APP_ENV') !== 'local',
        ]);

        Log::channel('payment_checkout')->info('MercadoPagoPixService initialized', [
            'environment' => $this->environment,
            'has_token' => !empty($this->accessToken),
        ]);
    }

    /**
     * Cria um pagamento PIX no Mercado Pago
     * 
     * @param array $paymentData Dados do pagamento
     *  - amount: float - Valor em centavos (ex: 10000 = R$ 100,00)
     *  - description: string - Descrição do pagamento
     *  - customerEmail: string - Email do cliente
     *  - customerName: string - Nome do cliente
     * 
     * @return array Resposta com status e dados do PIX ou erro
     */
    public function createPixPayment(array $paymentData): array
    {
        // Validação básica
        if (empty($this->accessToken)) {
            Log::error('MercadoPagoPixService: Token de acesso não configurado');
            return [
                'status' => 'error',
                'message' => 'Token do Mercado Pago não configurado. Contate o suporte.',
            ];
        }

        if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
            return [
                'status' => 'error',
                'message' => 'Valor do pagamento inválido.',
            ];
        }

        try {
            // Converte centavos para reais (Mercado Pago espera valor em reais)
            $amount = (float) ($paymentData['amount'] / 100);
            
            // Prepara o corpo da requisição
            $requestBody = [
                'transaction_amount' => $amount,
                'description' => $paymentData['description'] ?? 'Pagamento PIX',
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $paymentData['customerEmail'] ?? 'customer@email.com',
                    'first_name' => $paymentData['customerName'] ?? 'Cliente',
                ],
            ];

            Log::channel('payment_checkout')->info('MercadoPagoPixService: Criando pagamento PIX', [
                'environment' => $this->environment,
                'amount' => $amount,
            ]);

            // Realiza a requisição para o Mercado Pago
            $response = $this->client->post("{$this->apiUrl}/v1/payments", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                    'X-Idempotency-Key' => (string) Str::uuid(),
                ],
                'json' => $requestBody,
            ]);

            $body = json_decode($response->getBody(), true);

            Log::channel('payment_checkout')->info('MercadoPagoPixService: Resposta do Mercado Pago', [
                'status_code' => $response->getStatusCode(),
                'payment_id' => $body['id'] ?? null,
            ]);

            // Extrai os dados PIX da resposta
            $qrCodeData = $body['point_of_interaction']['transaction_data'] ?? [];
            
            return [
                'status' => 'success',
                'data' => [
                    'payment_id' => $body['id'] ?? null,
                    'qr_code_base64' => $qrCodeData['qr_code_base64'] ?? null,
                    'qr_code' => $qrCodeData['qr_code'] ?? null,
                    'expiration_date' => $body['date_of_expiration'] ?? null,
                    'amount' => $amount,
                    'status' => $body['status'] ?? 'pending',
                ],
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->handleClientException($e, $paymentData);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('MercadoPagoPixService: Erro de conexão com Mercado Pago', [
                'message' => $e->getMessage(),
            ]);
            return [
                'status' => 'error',
                'message' => 'Erro de conexão com o Mercado Pago. Tente novamente mais tarde.',
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPagoPixService: Erro geral ao criar pagamento PIX', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'status' => 'error',
                'message' => 'Erro ao processar o pagamento. Tente novamente mais tarde.',
            ];
        }
    }

    /**
     * Consulta o status de um pagamento PIX
     * 
     * @param int $paymentId ID do pagamento retornado ao criar o PIX
     * 
     * @return array Resposta com status do pagamento ou erro
     */
    public function getPaymentStatus(int $paymentId): array
    {
        if (empty($this->accessToken)) {
            return [
                'status' => 'error',
                'message' => 'Token do Mercado Pago não configurado.',
            ];
        }

        try {
            Log::channel('payment_checkout')->debug('MercadoPagoPixService: Consultando status do pagamento', [
                'payment_id' => $paymentId,
            ]);

            $response = $this->client->get("{$this->apiUrl}/v1/payments/{$paymentId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            Log::channel('payment_checkout')->debug('MercadoPagoPixService: Status consultado com sucesso', [
                'payment_id' => $paymentId,
                'payment_status' => $body['status'] ?? 'unknown',
            ]);

            return [
                'status' => 'success',
                'data' => [
                    'payment_id' => $body['id'] ?? $paymentId,
                    'payment_status' => $body['status'] ?? 'pending',
                    'status_detail' => $body['status_detail'] ?? null,
                    'amount' => $body['transaction_amount'] ?? null,
                ],
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                Log::warning('MercadoPagoPixService: Pagamento não encontrado', [
                    'payment_id' => $paymentId,
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Pagamento não encontrado.',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Erro ao consultar status do pagamento.',
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPagoPixService: Erro ao consultar status', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);
            return [
                'status' => 'error',
                'message' => 'Erro ao consultar o status do pagamento.',
            ];
        }
    }

    /**
     * Trata exceções de cliente HTTP
     * 
     * @param \GuzzleHttp\Exception\ClientException $e Exceção do Guzzle
     * @param array $paymentData Dados do pagamento original
     * 
     * @return array Array com erro estruturado
     */
    private function handleClientException(\GuzzleHttp\Exception\ClientException $e, array $paymentData): array
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);

        Log::error('MercadoPagoPixService: Erro HTTP ao criar pagamento', [
            'status_code' => $statusCode,
            'body' => $body,
        ]);

        $message = 'Um erro desconhecido ocorreu ao processar o pagamento.';

        if ($statusCode === 403) {
            $message = 'Acesso proibido. Verifique suas credenciais de API do Mercado Pago.';
        } elseif ($statusCode === 400) {
            $message = 'Requisição inválida. Verifique os dados enviados.';
            if (isset($body['cause'][0]['description'])) {
                $message .= ' (' . $body['cause'][0]['description'] . ')';
            }
        } elseif (isset($body['message'])) {
            $message = $body['message'];
        }

        return [
            'status' => 'error',
            'message' => $message,
        ];
    }

    /**
     * Obtém o ambiente atual (production/sandbox)
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Verifica se está em modo sandbox
     */
    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }
}
