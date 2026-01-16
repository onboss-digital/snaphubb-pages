<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\FacebookConversionsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;

class PushingPayWebhookController extends Controller
{
    private FacebookConversionsService $fbService;

    public function __construct(FacebookConversionsService $fbService)
    {
        $this->fbService = $fbService;
    }

    /**
     * Handle incoming webhook from Pushing Pay
     * 
     * Pushing Pay sends webhook notifications for payment status changes:
     * - payment.approved (pagamento aprovado)
     * - payment.declined (pagamento recusado)
     * - payment.canceled (pagamento cancelado)
     */
    public function handle(Request $request)
    {
        Log::info('Pushing Pay webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            // Extract webhook data
            $payload = $request->all();
            
            // Validate required fields
            if (empty($payload['event']) || empty($payload['data'])) {
                Log::error('Pushing Pay webhook missing required fields', ['payload' => $payload]);
                return response()->json(['error' => 'Invalid payload'], 400);
            }

            $event = $payload['event'];
            $data = $payload['data'];
            
            // Extract payment ID (may be in 'id', 'payment_id', or 'transactionId')
            $paymentId = $data['id'] ?? $data['payment_id'] ?? $data['transactionId'] ?? null;
            
            if (!$paymentId) {
                Log::error('Pushing Pay webhook missing payment ID', ['data' => $data]);
                return response()->json(['error' => 'No payment ID'], 400);
            }

            // Route based on event type
            switch ($event) {
                case 'payment.approved':
                case 'payment.confirmed':
                    return $this->handlePaymentApproved($paymentId, $data);
                    
                case 'payment.declined':
                case 'payment.refused':
                    return $this->handlePaymentDeclined($paymentId, $data);
                    
                case 'payment.canceled':
                    return $this->handlePaymentCanceled($paymentId, $data);
                    
                case 'payment.refunded':
                case 'payment.refund':
                case 'payment.chargeback':
                case 'payment.reversed':
                    return $this->handlePaymentRefunded($paymentId, $data);
                    
                default:
                    Log::warning('Pushing Pay webhook unknown event', ['event' => $event, 'data' => $data]);
                    return response()->json(['success' => true], 200);
            }
        } catch (\Exception $e) {
            Log::error('Pushing Pay webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return 200 OK to prevent Pushing Pay from retrying
            return response()->json(['error' => 'Processing error'], 200);
        }
    }

    /**
     * Handle payment refunded / chargeback event
     */
    private function handlePaymentRefunded($paymentId, $data)
    {
        Log::info('Processing payment refunded/chargeback', ['paymentId' => $paymentId, 'data' => $data]);

        try {
            $order = Order::where('pix_id', $paymentId)
                ->orWhere('external_payment_id', $paymentId)
                ->first();

            if (!$order) {
                Log::warning('Payment refunded but order not found', ['paymentId' => $paymentId]);
                return response()->json(['success' => true], 200);
            }

            // Update order/payment record
            if (method_exists($order, 'payments')) {
                $order->payments()->updateOrCreate(
                    ['external_payment_id' => $paymentId],
                    [
                        'status' => 'refunded',
                        'provider' => 'pushinpay',
                        'data' => $data,
                    ]
                );
            }

            // Attempt to mark subscription inactive and record transaction
            try {
                $cacheKey = 'pushinpay_refund_processed:' . $paymentId;
                if (!Cache::has($cacheKey)) {
                    DB::beginTransaction();
                    try {
                        $user = $order->user ?? null;
                        if (!$user && !empty($data['payer']['email'])) {
                            $user = User::where('email', $data['payer']['email'])->first();
                        }

                        // try to find related subscription transaction
                        $tx = SubscriptionTransactions::where('transaction_id', $paymentId)->first();
                        if (!$tx) {
                            // fallback: try by order id in other_transactions_details
                            $tx = SubscriptionTransactions::where('other_transactions_details', 'like', '%' . ($order->id ?? '') . '%')->first();
                        }

                        if ($tx) {
                            $refundTxId = $data['refund_id'] ?? ($paymentId . '_refund');
                            $exists = SubscriptionTransactions::where('transaction_id', $refundTxId)->first();
                            if (!$exists) {
                                SubscriptionTransactions::create([
                                    'subscriptions_id' => $tx->subscriptions_id,
                                    'user_id' => $tx->user_id,
                                    'amount' => $tx->amount ?? 0,
                                    'payment_type' => 'pix',
                                    'payment_status' => 'refunded',
                                    'transaction_id' => $refundTxId,
                                    'other_transactions_details' => json_encode($data),
                                ]);
                            }

                            $subscription = Subscription::find($tx->subscriptions_id);
                            if ($subscription) {
                                $subscription->status = config('constant.SUBSCRIPTION_STATUS.INACTIVE');
                                $subscription->save();
                            }
                        }

                        Cache::put($cacheKey, true, now()->addDays(7));
                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error('Pushing Pay webhook refund processing error', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Pushing Pay webhook refund outer error', ['error' => $e->getMessage()]);
            }

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing payment refunded', [
                'paymentId' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle payment approved event
     */
    private function handlePaymentApproved($paymentId, $data)
    {
        Log::info('Processing payment approved', ['paymentId' => $paymentId, 'data' => $data]);

        try {
            // Find order by PIX payment ID
            $order = Order::where('pix_id', $paymentId)
                ->orWhere('external_payment_id', $paymentId)
                ->first();

            if (!$order) {
                Log::warning('Payment approved but order not found', ['paymentId' => $paymentId]);
                return response()->json(['success' => true], 200);
            }

            // Update order status to paid if not already
            if ($order->status !== 'paid') {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'external_payment_status' => 'approved',
                ]);

                Log::info('Order marked as paid', [
                    'orderId' => $order->id,
                    'userId' => $order->user_id,
                    'amount' => $order->amount,
                ]);

                // 🔥 Send Purchase event to Facebook Conversions API
                $pixelIds = $this->getPixelIds();
                if (!empty($pixelIds) && $order->user) {
                    foreach ($pixelIds as $pixelId) {
                        $this->fbService->sendPurchaseEvent($pixelId, [
                            'email' => $order->user->email,
                            'phone' => $order->user->phone ?? '',
                            'value' => $order->amount,
                            'currency' => 'BRL',
                            'event_id' => $paymentId,
                            'content_ids' => [$order->id],
                            'content_type' => 'product',
                            'client_ip' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'event_source_url' => url('/'),
                        ]);
                    }
                    Log::info('Facebook Purchase event sent for order', [
                        'orderId' => $order->id,
                        'paymentId' => $paymentId,
                        'pixelCount' => count($pixelIds),
                    ]);
                }
            }

            // Log successful payment
            if (method_exists($order, 'payments')) {
                $order->payments()->updateOrCreate(
                    ['external_payment_id' => $paymentId],
                    [
                        'status' => 'approved',
                        'provider' => 'pushinpay',
                        'data' => $data,
                    ]
                );
            }

            // Create/Update Subscription when payment approved (idempotent)
            try {
                $cacheKey = 'pushinpay_webhook_processed:' . $paymentId;
                if (!Cache::has($cacheKey)) {
                    DB::beginTransaction();
                    try {
                        $user = $order->user ?? null;
                        if (!$user && !empty($data['payer']['email'])) {
                            $user = User::where('email', $data['payer']['email'])->first();
                        }

                        $productId = $data['product_id'] ?? $data['metadata']['product_id'] ?? $data['reference'] ?? null;
                        $plan = null;
                        if ($productId) {
                            $plan = Plan::where('pushinpay_product_id', $productId)
                                ->orWhere('pushinpay_reference', $productId)
                                ->orWhere('external_product_id', $productId)
                                ->first();
                        }

                        if ($user && $plan) {
                            $now = now();
                            $existing = Subscription::where('user_id', $user->id)
                                ->where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
                                ->orderBy('end_date', 'desc')
                                ->first();

                            if ($existing && now()->lt(\Carbon\Carbon::parse($existing->end_date))) {
                                $start = \Carbon\Carbon::parse($existing->end_date);
                            } else {
                                $start = $now;
                            }

                            $end = (clone $start)->addDays(30);

                            $subscription = Subscription::create([
                                'plan_id' => $plan->id,
                                'user_id' => $user->id,
                                'start_date' => $start->toDateTimeString(),
                                'end_date' => $end->toDateTimeString(),
                                'status' => config('constant.SUBSCRIPTION_STATUS.ACTIVE'),
                                'amount' => $order->amount ?? null,
                                'name' => $plan->name ?? null,
                                'payment_id' => $paymentId,
                            ]);

                            $existsTx = SubscriptionTransactions::where('transaction_id', $paymentId)->first();
                            if (!$existsTx) {
                                SubscriptionTransactions::create([
                                    'subscriptions_id' => $subscription->id,
                                    'user_id' => $user->id,
                                    'amount' => $order->amount ?? 0,
                                    'payment_type' => 'pix',
                                    'payment_status' => 'approved',
                                    'transaction_id' => $paymentId,
                                    'other_transactions_details' => json_encode($data),
                                ]);
                            }
                        }

                        Cache::put($cacheKey, true, now()->addDays(7));
                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error('Pushing Pay webhook subscription sync error', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Pushing Pay webhook subscription outer error', ['error' => $e->getMessage()]);
            }

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing payment approved', [
                'paymentId' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return 200 to prevent retry
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle payment declined event
     */
    private function handlePaymentDeclined($paymentId, $data)
    {
        Log::info('Processing payment declined', ['paymentId' => $paymentId, 'data' => $data]);

        try {
            $order = Order::where('pix_id', $paymentId)
                ->orWhere('external_payment_id', $paymentId)
                ->first();

            if ($order && $order->status !== 'declined') {
                $order->update([
                    'status' => 'declined',
                    'external_payment_status' => 'declined',
                ]);

                Log::info('Order marked as declined', [
                    'orderId' => $order->id,
                    'reason' => $data['decline_reason'] ?? 'Unknown',
                ]);
            }

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing payment declined', [
                'paymentId' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle payment canceled event
     */
    private function handlePaymentCanceled($paymentId, $data)
    {
        Log::info('Processing payment canceled', ['paymentId' => $paymentId, 'data' => $data]);

        try {
            $order = Order::where('pix_id', $paymentId)
                ->orWhere('external_payment_id', $paymentId)
                ->first();

            if ($order && $order->status !== 'canceled') {
                $order->update([
                    'status' => 'canceled',
                    'external_payment_status' => 'canceled',
                ]);

                Log::info('Order marked as canceled', ['orderId' => $order->id]);
            }

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing payment canceled', [
                'paymentId' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Get Facebook pixel IDs from .env configuration
     */
    private function getPixelIds(): array
    {
        $pixelIds = [];
        
        // Check for multiple pixel IDs (comma separated)
        if (env('FB_PIXEL_IDS')) {
            $pixelIds = array_map('trim', explode(',', env('FB_PIXEL_IDS')));
            $pixelIds = array_filter($pixelIds);
        }
        
        // Fall back to single FB_PIXEL_ID if configured
        if (empty($pixelIds) && env('FB_PIXEL_ID') && env('FB_PIXEL_ID') !== 'YOUR_FACEBOOK_PIXEL_ID') {
            $pixelIds[] = env('FB_PIXEL_ID');
        }
        
        return $pixelIds;
    }
}


