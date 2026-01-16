<?php
namespace App\Http\Controllers;

use App\Services\FacebookConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;

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

                // Idempotency: avoid processing same payment twice
                if (!Cache::has($cacheKey)) {
                    DB::beginTransaction();
                    try {
                        // Create/Update Subscription for the user if we can resolve email -> user
                        $user = null;
                        if (!empty($email)) {
                            $user = User::where('email', $email)->first();
                        }

                        // Try to resolve product/plan from metadata (price/product)
                        $productId = null;
                        if (is_array($pi)) {
                            $productId = $pi['metadata']['product_id'] ?? $pi['metadata']['price_id'] ?? ($pi['charges']['data'][0]['metadata']['product_id'] ?? null);
                        } else {
                            $productId = $pi->metadata->product_id ?? $pi->metadata->price_id ?? (isset($pi->charges->data[0]) ? ($pi->charges->data[0]->metadata->product_id ?? null) : null);
                        }

                        $plan = null;
                        if ($productId) {
                            $plan = Plan::where('stripe_product_id', $productId)
                                ->orWhere('external_product_id', $productId)
                                ->first();
                        }

                        if ($user && $plan) {
                            // Determine start and end dates
                            $now = now();
                            $existing = Subscription::where('user_id', $user->id)
                                ->where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
                                ->orderBy('end_date', 'desc')
                                ->first();

                            if ($existing && now()->lt(\Carbon\Carbon::parse($existing->end_date))) {
                                // extend from existing end_date
                                $start = \Carbon\Carbon::parse($existing->end_date);
                            } else {
                                $start = $now;
                            }

                            $end = (clone $start)->addDays(30);

                            // Create new subscription record
                            $subscription = Subscription::create([
                                'plan_id' => $plan->id,
                                'user_id' => $user->id,
                                'start_date' => $start->toDateTimeString(),
                                'end_date' => $end->toDateTimeString(),
                                'status' => config('constant.SUBSCRIPTION_STATUS.ACTIVE'),
                                'amount' => $amount ?? null,
                                'name' => $plan->name ?? null,
                                'payment_id' => $paymentId,
                            ]);

                            // Record transaction (idempotent check)
                            $existsTx = SubscriptionTransactions::where('transaction_id', $paymentId)->first();
                            if (!$existsTx) {
                                SubscriptionTransactions::create([
                                    'subscriptions_id' => $subscription->id,
                                    'user_id' => $user->id,
                                    'amount' => $amount ?? 0,
                                    'payment_type' => 'stripe',
                                    'payment_status' => 'approved',
                                    'transaction_id' => $paymentId,
                                    'other_transactions_details' => json_encode($pi),
                                ]);
                            }
                        }

                        Cache::put($cacheKey, true, now()->addDays(7));
                        DB::commit();
                        Log::info('StripeWebhook: processed and sent CAPI + subscription sync', ['payment_id' => $paymentId]);
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error('StripeWebhook: processing error during subscription sync', ['error' => $e->getMessage()]);
                    }
                }

                return response()->json(['status' => 'ok'], 200);
            } catch (\Throwable $e) {
                Log::error('StripeWebhook: processing error', ['error' => $e->getMessage()]);
                return response()->json(['status' => 'error'], 500);
            }
        }

        // Handle refunds / disputes
        if (in_array($type, ['charge.refunded', 'refund.created', 'refund.updated', 'charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed', 'charge.refund.updated', 'refund.failed'])) {
            try {
                $obj = is_array($event) ? ($event['data']['object'] ?? []) : ($event->data->object ?? null);

                // Determine possible identifiers to find original transaction
                $refundId = null;
                if (is_array($obj)) {
                    $refundId = $obj['id'] ?? ($obj['refunds']['data'][0]['id'] ?? null);
                    $chargeId = $obj['charge'] ?? ($obj['id'] ?? null);
                    $paymentIntentId = $obj['payment_intent'] ?? null;
                } else {
                    $refundId = $obj->id ?? (isset($obj->refunds) && isset($obj->refunds->data[0]) ? $obj->refunds->data[0]->id : null);
                    $chargeId = $obj->charge ?? ($obj->id ?? null);
                    $paymentIntentId = $obj->payment_intent ?? null;
                }

                $relatedIds = array_filter([$refundId, $chargeId, $paymentIntentId]);

                // Try to find a matching subscription transaction
                $tx = null;
                if (!empty($relatedIds)) {
                    $tx = SubscriptionTransactions::whereIn('transaction_id', $relatedIds)->first();
                }

                // If not found by transaction_id, try to search by other details (JSON stored)
                if (!$tx && is_array($obj) && !empty($obj['id'])) {
                    $tx = SubscriptionTransactions::where('other_transactions_details', 'like', '%' . $obj['id'] . '%')->first();
                }

                if ($tx) {
                    $cacheKey = 'stripe_refund_processed:' . ($refundId ?? $type . ':' . ($tx->transaction_id ?? '')); 
                    if (!Cache::has($cacheKey)) {
                        DB::beginTransaction();
                        try {
                            // Create refund/chargeback transaction (idempotent by transaction_id)
                            $refundTxId = $refundId ?? ($type . '_' . uniqid());
                            $exists = SubscriptionTransactions::where('transaction_id', $refundTxId)->first();
                            if (!$exists) {
                                SubscriptionTransactions::create([
                                    'subscriptions_id' => $tx->subscriptions_id,
                                    'user_id' => $tx->user_id,
                                    'amount' => ($tx->amount) ?? 0,
                                    'payment_type' => 'stripe',
                                    'payment_status' => in_array($type, ['charge.dispute.created','charge.dispute.updated','charge.dispute.closed']) ? 'chargeback' : 'refunded',
                                    'transaction_id' => $refundTxId,
                                    'other_transactions_details' => json_encode($obj),
                                ]);
                            }

                            // Mark subscription as inactive/suspended
                            $subscription = Subscription::find($tx->subscriptions_id);
                            if ($subscription) {
                                $subscription->status = config('constant.SUBSCRIPTION_STATUS.INACTIVE');
                                $subscription->save();
                            }

                            Cache::put($cacheKey, true, now()->addDays(7));
                            DB::commit();
                            Log::info('StripeWebhook: refund/dispute processed, subscription suspended', ['tx' => $tx->id, 'refund_id' => $refundTxId, 'event' => $type]);
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            Log::error('StripeWebhook: error processing refund/dispute', ['error' => $e->getMessage()]);
                        }
                    }
                } else {
                    Log::warning('StripeWebhook: refund/dispute received but related subscription transaction not found', ['event' => $type, 'obj' => $obj]);
                }

                return response()->json(['status' => 'ok'], 200);
            } catch (\Throwable $e) {
                Log::error('StripeWebhook: refund processing error', ['error' => $e->getMessage()]);
                return response()->json(['status' => 'error'], 500);
            }
        }

        return response()->json(['status' => 'ignored'], 200);
    }
}
