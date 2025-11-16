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
}
