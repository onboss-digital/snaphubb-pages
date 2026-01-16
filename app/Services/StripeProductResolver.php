<?php

namespace App\Services;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class StripeProductResolver
{
    protected StripeClient $stripe;

    public function __construct()
    {
        // Use the configured key name in config/services.php
        $key = config('services.stripe.api_secret_key') ?? env('STRIPE_API_SECRET_KEY') ?? env('STRIPE_SECRET');
        $this->stripe = new StripeClient($key);
    }

    /**
     * Resolve a price for a given Stripe Product id and currency.
     * Returns ['price_id'=>string, 'amount_cents'=>int] or null on failure.
     */
    public function resolvePriceForProduct(string $productId, string $currency = 'BRL'): ?array
    {
        try {
            $resp = $this->stripe->prices->all([
                'product' => $productId,
                'active' => true,
                'limit' => 100,
            ]);

            if (empty($resp->data)) {
                return null;
            }

            // Prefer matching currency
            foreach ($resp->data as $price) {
                if (isset($price->currency) && strtoupper($price->currency) === strtoupper($currency)) {
                    return [
                        'price_id' => $price->id,
                        'amount_cents' => $price->unit_amount,
                    ];
                }
            }

            // Fallback: return first active price
            $p = $resp->data[0];
            return [
                'price_id' => $p->id,
                'amount_cents' => $p->unit_amount,
            ];
        } catch (\Throwable $e) {
            Log::error('StripeProductResolver: error resolving price', ['product' => $productId, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
