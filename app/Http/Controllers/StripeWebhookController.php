<?php
namespace App\Http\Controllers;

use App\Services\FacebookConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StripeWebhookController extends Controller
{
    private FacebookConversionsService $fbService;

    public function __construct(FacebookConversionsService $fbService)
    {
        $this->fbService = $fbService;
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        // Optional verification using STRIPE_WEBHOOK_SECRET
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');
        $event = null;

        if ($webhookSecret) {
            try {
                if (!class_exists('\\Stripe\\Webhook')) {
                    Log::warning('StripeWebhook: stripe/php not installed; skipping signature verification.');
                    $event = json_decode($payload, true);
                } else {
                    \Stripe\Stripe::setApiKey(env('STRIPE_API_SECRET_KEY'));
                    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
                }
            } catch (\UnexpectedValueException $e) {
                Log::error('StripeWebhook: Invalid payload', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'invalid payload'], 400);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::error('StripeWebhook: Invalid signature', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'invalid signature'], 400);
            } catch (\Throwable $e) {
                Log::error('StripeWebhook: verification error', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'verification error'], 500);
            }
        } else {
            // No secret configured — parse payload
            $event = json_decode($payload, true);
        }

        $type = is_array($event) ? ($event['type'] ?? null) : ($event->type ?? null);

        Log::info('StripeWebhook received', ['type' => $type]);

        if ($type === 'payment_intent.succeeded' || $type === 'charge.succeeded') {
            // Extract payment intent data
            $pi = is_array($event) ? ($event['data']['object'] ?? []) : ($event->data->object ?? null);
            $paymentId = is_array($pi) ? ($pi['id'] ?? null) : ($pi->id ?? null);

            if (!$paymentId) {
                Log::warning('StripeWebhook: payment id not found');
                return response()->json(['status' => 'ignored'], 200);
            }

            $cacheKey = 'stripe_webhook_processed:' . $paymentId;
            if (Cache::has($cacheKey)) {
                Log::info('StripeWebhook: already processed', ['payment_id' => $paymentId]);
                return response()->json(['status' => 'ok'], 200);
            }

            try {
                // Build payload for FB CAPI
                $amount = null;
                $currency = null;
                $email = null;
                $phone = null;

                if (is_array($pi)) {
                    $amount = isset($pi['amount']) ? ($pi['amount'] / 100) : ($pi['amount_received'] ?? null);
                    $currency = $pi['currency'] ?? null;
                    $charges = $pi['charges']['data'][0] ?? null;
                    if ($charges) {
                        $billing = $charges['billing_details'] ?? [];
                        $email = $billing['email'] ?? $pi['receipt_email'] ?? null;
                        $phone = $billing['phone'] ?? null;
                    }
                } else {
                    $amount = isset($pi->amount) ? ($pi->amount / 100) : null;
                    $currency = $pi->currency ?? null;
                    $charge = $pi->charges->data[0] ?? null;
                    if ($charge) {
                        $billing = $charge->billing_details ?? null;
                        $email = $billing->email ?? $pi->receipt_email ?? null;
                        $phone = $billing->phone ?? null;
                    }
                }

                $contentIds = null; // leave null unless you map metadata

                $fbIds = [];
                if (env('FB_PIXEL_IDS')) {
                    $fbIds = array_filter(array_map('trim', explode(',', env('FB_PIXEL_IDS'))));
                } elseif (env('FB_PIXEL_ID')) {
                    $fbIds = [env('FB_PIXEL_ID')];
                }

                foreach ($fbIds as $pixelId) {
                    try {
                        $this->fbService->sendPurchaseEvent($pixelId, [
                            'event_id' => $paymentId,
                            'value' => $amount,
                            'currency' => strtoupper($currency ?? 'BRL'),
                            'email' => $email,
                            'phone' => $phone,
                            'client_ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'content_ids' => $contentIds,
                            'content_type' => 'product',
                            'event_source_url' => config('app.url') . '/checkout',
                        ]);
                        Log::info('StripeWebhook: FB CAPI event sent', ['payment_id' => $paymentId, 'pixel' => $pixelId]);
                    } catch (\Throwable $e) {
                        Log::error('StripeWebhook: FB CAPI send failed', ['error' => $e->getMessage(), 'payment_id' => $paymentId, 'pixel' => $pixelId]);
                    }
                }

                Cache::put($cacheKey, true, now()->addDays(7));
                Log::info('StripeWebhook: processed and sent CAPI', ['payment_id' => $paymentId]);
                return response()->json(['status' => 'ok'], 200);
            } catch (\Throwable $e) {
                Log::error('StripeWebhook: processing error', ['error' => $e->getMessage()]);
                return response()->json(['status' => 'error'], 500);
            }
        }

        return response()->json(['status' => 'ignored'], 200);
    }
}
