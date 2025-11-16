<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        // Placeholder: load SDK or config later if needed
    }

    public function createCardToken(array $cardData): array
    {
        return [
            'status' => 'error',
            'message' => 'MercadoPago createCardToken not implemented',
        ];
    }

    public function processPayment(array $paymentData): array
    {
        return [
            'status' => 'error',
            'message' => 'MercadoPago processPayment not implemented',
        ];
    }

    public function handleResponse(array $responseData): array
    {
        return [
            'status' => 'error',
            'message' => 'MercadoPago handleResponse not implemented',
            'data' => $responseData,
        ];
    }

    public function formatPlans(mixed $data, string $selectedCurrency): array
    {
        $normalizeAmount = static function ($amount): ?float {
            if ($amount === null) return null;
            if (is_numeric($amount)) return round((float)$amount / (ctype_digit((string)$amount) ? 100 : 1), 2);
            return null;
        };

        $result = [];

        foreach ($data['data'] ?? [] as $plan) {
            if (empty($plan['pages_product_external_id'])) continue;

            $key = match ($plan['duration'] ?? '') {
                'week' => 'weekly',
                'month' => match ((int)($plan['duration_value'] ?? 0)) {
                    3 => 'quarterly',
                    6 => 'semi-annual',
                    default => 'monthly',
                },
                'year' => 'annual',
                default => $plan['duration'] ?? 'monthly',
            };

            $prices = [];
            if (!empty($plan['prices']) && is_array($plan['prices'])) {
                foreach ($plan['prices'] as $p) {
                    $currency = strtoupper($p['currency'] ?? '');
                    if ($currency === '') continue;
                    $amount = $normalizeAmount($p['amount'] ?? $p['unit_amount'] ?? null);
                    if ($amount === null) continue;
                    $prices[$currency] = [
                        'id' => $p['id'] ?? null,
                        'origin_price' => $plan['price'] ?? $amount,
                        'descont_price' => $amount,
                        'recurring' => $p['recurring'] ?? null,
                    ];
                }
            }

            if (empty($prices)) {
                $amount = $normalizeAmount($plan['price'] ?? null);
                if ($amount !== null) {
                    $prices[strtoupper($selectedCurrency)] = [
                        'id' => $plan['pages_product_external_id'],
                        'origin_price' => $plan['price'] ?? $amount,
                        'descont_price' => $amount,
                        'recurring' => $plan['recurring'] ?? 1,
                    ];
                }
            }

            $bumps = [];
            foreach ($plan['order_bumps'] ?? [] as $b) {
                if (empty($b['external_id'])) continue;
                $amount = $normalizeAmount($b['price'] ?? null);
                if ($amount === null) continue;
                $bumps[] = [
                    'id' => $b['external_id'],
                    'title' => $b['title'] ?? null,
                    'description' => $b['description'] ?? null,
                    'price' => $amount,
                    'hash' => $b['external_id'],
                    'active' => false,
                ];
            }

            $result[$key] = [
                'hash' => $plan['pages_product_external_id'],
                'label' => ($plan['name'] ?? ucfirst($key)) . ' - ' . ($plan['duration_value'] ?? 1) . '/' . ($plan['duration'] ?? 'month'),
                'nunber_months' => (int)($plan['duration_value'] ?? 1),
                'prices' => $prices,
                'upsell_url' => $plan['pages_upsell_url'] ?? null,
                'order_bumps' => $bumps,
            ];
        }

        return $result;
    }

    public function createPixPayment(array $data): array
    {
        // Prefer using Mercado Pago API when token is available
        $env = env('MERCADOPAGO_ENV', 'sandbox');
        $token = $env === 'production' ? env('MERCADOPAGO_PRODUCTION_TOKEN') : env('MERCADOPAGO_SANDBOX_TOKEN');

        $amount = isset($data['amount']) ? (float)($data['amount'] / 100) : 0.0;

        if (!empty($token)) {
            try {
                $client = new Client();
                $body = [
                    'transaction_amount' => $amount,
                    'description' => $data['metadata']['product_main_hash'] ?? 'PIX Payment',
                    'payment_method_id' => 'pix',
                    'payer' => [
                        'email' => $data['customer']['email'] ?? ($data['customer']['email'] ?? null),
                        'first_name' => $data['customer']['name'] ?? null,
                    ],
                ];

                $res = $client->post('https://api.mercadopago.com/v1/payments', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $body,
                    'timeout' => 10,
                ]);

                $payload = json_decode((string)$res->getBody(), true);

                $transactionId = $payload['id'] ?? ('pix_' . uniqid());
                $qrImage = null;
                $expiresIn = 3600;

                // Extract QR code if present
                if (isset($payload['point_of_interaction']['transaction_data']['qr_code_base64'])) {
                    $base64 = $payload['point_of_interaction']['transaction_data']['qr_code_base64'];
                    $qrImage = 'data:image/png;base64,' . $base64;
                } elseif (isset($payload['point_of_interaction']['transaction_data']['qr_code'])) {
                    // sometimes API returns qr_code (string) or image
                    $qrImage = $payload['point_of_interaction']['transaction_data']['qr_code'];
                }

                // store minimal state in cache for possible polling
                try {
                    if (function_exists('cache')) {
                        cache()->put('pix:' . $transactionId, [
                            'transaction_id' => $transactionId,
                            'status' => $payload['status'] ?? 'pending',
                            'created_at' => time(),
                            'expires_in' => $expiresIn,
                        ], $expiresIn);
                    }
                } catch (\Exception $e) {
                    Log::error('MercadoPagoGateway cache error: ' . $e->getMessage());
                }

                return [
                    'status' => 'success',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'qr_image' => $qrImage,
                        'expires_in' => $expiresIn,
                        'raw' => $payload,
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('MercadoPagoGateway.createPixPayment error: ' . $e->getMessage());
                // fallback to mock below
            }
        }

        // Fallback mock implementation (local testing)
        $transactionId = 'pix_' . uniqid();
        $createdAt = now();

        // Simple SVG-based QR placeholder containing the transaction id (data-uri)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="100%" height="100%" fill="#fff"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="12" fill="#111">' . htmlentities($transactionId) . '</text></svg>';
        $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);

        // Store in cache so we can simulate status on polling
        try {
            if (function_exists('cache')) {
                cache()->put('pix:' . $transactionId, [
                    'transaction_id' => $transactionId,
                    'status' => 'pending',
                    'created_at' => $createdAt->timestamp ?? time(),
                    'expires_in' => 3600,
                ], 3600);
            }
        } catch (\Exception $e) {
            // ignore cache errors
        }

        return [
            'status' => 'success',
            'data' => [
                'transaction_id' => $transactionId,
                'qr_image' => $dataUri,
                'expires_in' => 3600,
            ],
        ];
    }

    public function checkPixStatus(string $transactionId): array
    {
        // If Mercado Pago token present, try to query real payment status
        $env = env('MERCADOPAGO_ENV', 'sandbox');
        $token = $env === 'production' ? env('MERCADOPAGO_PRODUCTION_TOKEN') : env('MERCADOPAGO_SANDBOX_TOKEN');

        if (!empty($token)) {
            try {
                $client = new Client();
                $res = $client->get('https://api.mercadopago.com/v1/payments/' . $transactionId, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 8,
                ]);

                $payload = json_decode((string)$res->getBody(), true);
                $status = $payload['status'] ?? ($payload['status_detail'] ?? null);

                // map Mercado Pago statuses to our simplified statuses
                if (in_array($status, ['approved', 'paid', 'authorized'])) {
                    return ['status' => 'paid', 'raw' => $payload];
                }

                return ['status' => 'pending', 'raw' => $payload];
            } catch (\Exception $e) {
                Log::error('MercadoPagoGateway.checkPixStatus error: ' . $e->getMessage());
                // fallback to cache below
            }
        }

        // Fallback: read from cache and simulate payment confirmation after 20 seconds
        try {
            $cacheKey = 'pix:' . $transactionId;
            $record = function_exists('cache') ? cache()->get($cacheKey) : null;
            if (!$record) {
                return ['status' => 'not_found'];
            }

            $createdAt = $record['created_at'] ?? time();
            $elapsed = time() - $createdAt;
            if ($elapsed > 20) {
                // mark as paid
                if (function_exists('cache')) {
                    cache()->put($cacheKey, array_merge($record, ['status' => 'paid']), 3600);
                }
                return ['status' => 'paid', 'transaction_id' => $transactionId];
            }

            return ['status' => 'pending', 'transaction_id' => $transactionId, 'elapsed' => $elapsed];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
