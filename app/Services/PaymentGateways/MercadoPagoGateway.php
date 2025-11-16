<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;

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
        // For bkp-local we provide a lightweight mock implementation so the UI can work locally.
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
        // Read from cache and simulate payment confirmation after 20 seconds
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
