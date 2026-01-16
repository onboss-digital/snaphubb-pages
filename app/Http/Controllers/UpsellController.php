<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use App\Services\PaymentGateways\StripeGateway;
use Illuminate\Support\Facades\Log;

class UpsellController extends BaseController
{
    public function charge(Request $request, StripeGateway $gateway)
    {
        $data = $request->validate([
            'product_external_id' => 'required|string',
            'payment_method_id' => 'required|string',
            'customer_email' => 'sometimes|email',
            'customer_name' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'amount' => 'sometimes', // fallback amount when Stripe price not available
            'upsell_success_url' => 'sometimes|url',
            'upsell_failed_url' => 'sometimes|url',
            'offer_hash' => 'sometimes|string',
        ]);

        $productId = $data['product_external_id'];
        $currency = strtoupper($data['currency'] ?? 'BRL');

        try {
            // Try to resolve Stripe product prices
            $productResp = $gateway->getProductWithPrices($productId);

            $cartItem = [
                'title' => 'Upsell',
                'product_hash' => $productId,
                'quantity' => 1,
            ];

            if (($productResp['status'] ?? null) === 'success' && !empty($productResp['prices'])) {
                // pick first price matching currency (getProductWithPrices returns prices with currency key inside)
                $found = null;
                foreach ($productResp['prices'] as $p) {
                    if (strtoupper($p['currency'] ?? '') === $currency || empty($found)) {
                        $found = $p;
                        if (strtoupper($p['currency'] ?? '') === $currency) break;
                    }
                }

                if (!empty($found) && !empty($found['id'])) {
                    $cartItem['price_id'] = $found['id'];
                    $cartItem['currency'] = $currency;
                    $cartItem['price'] = $found['unit_amount'] ?? null;
                    // StripeGateway expects amount/price_id or fallback price
                } else {
                    // No matching price id, fallthrough to fallback amount
                }
            }

            if (empty($cartItem['price_id'])) {
                // require fallback amount param
                if (empty($data['amount'])) {
                    return response()->json(['status' => 'error', 'message' => 'No Stripe price found and no fallback amount provided.'], 422);
                }
                // accept amount either in cents or units
                $amt = $data['amount'];
                $amtFloat = is_numeric($amt) ? (float)$amt : null;
                if ($amtFloat === null) return response()->json(['status' => 'error', 'message' => 'Invalid amount provided.'], 422);
                // If value looks like cents (>=1000) keep, else convert units to cents
                $unitAmount = ($amtFloat >= 1000) ? (int)round($amtFloat) : (int)round($amtFloat * 100);
                $cartItem['price'] = $unitAmount; // StripeGateway will detect fallback
                $cartItem['currency'] = strtolower($currency);
            }

                // Build idempotency key (frontend may also provide `Idempotency-Key` header)
                $idempotencyKey = $request->header('Idempotency-Key') ?? md5(($data['offer_hash'] ?? $productId) . '|' . ($data['payment_method_id'] ?? '') . '|' . ($data['customer_email'] ?? ''));

                $paymentPayload = [
                'payment_method_id' => $data['payment_method_id'],
                'customer' => [
                    'email' => $data['customer_email'] ?? null,
                    'name' => $data['customer_name'] ?? 'Cliente',
                ],
                'cart' => [ $cartItem ],
                'metadata' => [
                    'offer_hash' => $data['offer_hash'] ?? $productId,
                    'product_external_id' => $productId,
                ],
                'offer_hash' => $data['offer_hash'] ?? $productId,
                'upsell_url' => $data['upsell_success_url'] ?? null,
                'upsell_success_url' => $data['upsell_success_url'] ?? null,
                'upsell_failed_url' => $data['upsell_failed_url'] ?? null,
            ];

                Log::channel('payment_checkout')->info('UpsellController::charge - payload prepared', ['product' => $productId, 'customer' => $paymentPayload['customer'], 'idempotency' => $idempotencyKey]);

                $result = $gateway->processPayment($paymentPayload, $idempotencyKey);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('UpsellController::charge error', ['error' => $e->getMessage(), 'product' => $productId]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
