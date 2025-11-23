<?php
namespace App\Http\Controllers;

use App\Services\MercadoPagoPixService;
use App\Services\FacebookConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MercadoPagoWebhookController extends Controller
{
    private MercadoPagoPixService $pixService;
    private FacebookConversionsService $fbService;

    public function __construct(MercadoPagoPixService $pixService, FacebookConversionsService $fbService)
    {
        $this->pixService = $pixService;
        $this->fbService = $fbService;
    }

    /**
     * Handle Mercado Pago webhook.
     * Expected payloads vary; we try to extract a payment id and then check status.
     */
    public function handle(Request $request)
    {
        Log::info('🔴 [Webhook] Received Mercado Pago webhook', [
            'body' => $request->all(),
            'timestamp' => now(),
        ]);

        // Try common fields for payment id
        $paymentId = $request->input('data.id') ?? $request->input('resource.id') ?? $request->input('id') ?? $request->input('collection.id') ?? null;

        if (!$paymentId) {
            Log::warning('🔴 [Webhook] payment id not found in payload', ['payload' => $request->all()]);
            return response()->json(['status' => 'ignored', 'message' => 'no payment id'], 200);
        }

        Log::info('🔴 [Webhook] Payment ID extracted', ['payment_id' => $paymentId]);

        $cacheKey = 'mp_webhook_processed:' . $paymentId;
        if (Cache::has($cacheKey)) {
            Log::info('🔴 [Webhook] Already processed (from cache)', ['payment_id' => $paymentId]);
            return response()->json(['status' => 'ok', 'message' => 'already processed'], 200);
        }

        try {
            Log::info('🔴 [Webhook] Calling getPaymentStatus', ['payment_id' => $paymentId]);
            $result = $this->pixService->getPaymentStatus($paymentId);
            
            if (!isset($result['status']) || $result['status'] !== 'success') {
                Log::warning('🔴 [Webhook] getPaymentStatus returned error', [
                    'payment_id' => $paymentId,
                    'result' => $result,
                ]);
                return response()->json(['status' => 'error', 'message' => 'could not retrieve status'], 422);
            }

            $data = $result['data'] ?? [];
            $status = strtolower($data['status'] ?? ($data['payment_status'] ?? 'unknown'));

            Log::info('🔴 [Webhook] Payment status retrieved', [
                'payment_id' => $paymentId,
                'status' => $status,
                'email' => $data['payer']['email'] ?? 'NOT FOUND',
                'phone' => $data['payer']['phone'] ?? 'NOT FOUND',
            ]);

            if ($status === 'approved' || $status === 'paid' || $status === 'success') {
                Log::info('🔴 [Webhook] Payment is APPROVED - processing', ['payment_id' => $paymentId]);
                
                // Mark as processed to avoid double-processing
                Cache::put($cacheKey, true, now()->addDays(7));

                // Build payload for FB CAPI
                $value = isset($data['amount']) ? ($data['amount'] / 100) : ($data['value'] ?? 0);
                $currency = $data['currency'] ?? 'BRL';
                
                // Extract content_ids from cart items
                $contentIds = [];
                if (isset($data['cart']) && is_array($data['cart'])) {
                    foreach ($data['cart'] as $item) {
                        $id = $item['product_hash'] ?? ($item['id'] ?? ($item['sku'] ?? null));
                        if ($id) {
                            $contentIds[] = (string) $id;
                        }
                    }
                }

                // Extract email and phone from payer
                $email = null;
                $phone = null;
                if (isset($data['payer']) && is_array($data['payer'])) {
                    $email = $data['payer']['email'] ?? null;
                    $phone = $data['payer']['phone'] ?? null;
                }

                Log::info('🔴 [Webhook] Preparing FB CAPI payload', [
                    'payment_id' => $paymentId,
                    'email' => $email,
                    'phone' => $phone,
                    'amount' => $value,
                    'currency' => $currency,
                    'content_ids_count' => count($contentIds),
                ]);

                $fbIds = [];
                if (env('FB_PIXEL_IDS')) {
                    $fbIds = array_filter(array_map('trim', explode(',', env('FB_PIXEL_IDS'))));
                } elseif (env('FB_PIXEL_ID')) {
                    $fbIds = [env('FB_PIXEL_ID')];
                }

                if (empty($fbIds)) {
                    Log::warning('🔴 [Webhook] No Facebook pixel configured', ['payment_id' => $paymentId]);
                } else {
                    Log::info('🔴 [Webhook] Found FB pixels', [
                        'payment_id' => $paymentId,
                        'pixel_count' => count($fbIds),
                        'pixels' => $fbIds,
                    ]);
                }

                foreach ($fbIds as $pixelId) {
                    try {
                        Log::info('🔴 [Webhook] Sending to FB CAPI', [
                            'payment_id' => $paymentId,
                            'pixel_id' => $pixelId,
                        ]);
                        
                        $this->fbService->sendPurchaseEvent($pixelId, [
                            'event_id' => $paymentId,
                            'value' => $value,
                            'currency' => strtoupper($currency),
                            'email' => $email,
                            'phone' => $phone,
                            'client_ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'content_ids' => !empty($contentIds) ? $contentIds : null,
                            'content_type' => 'product',
                            'event_source_url' => config('app.url') . '/checkout',
                        ]);
                        
                        Log::info('🔴 [Webhook] FB CAPI event sent successfully', [
                            'payment_id' => $paymentId,
                            'pixel' => $pixelId,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('🔴 [Webhook] FB CAPI send failed', [
                            'error' => $e->getMessage(),
                            'payment_id' => $paymentId,
                            'pixel' => $pixelId,
                        ]);
                    }
                }

                Log::info('🔴 [Webhook] APPROVED PAYMENT PROCESSED', [
                    'payment_id' => $paymentId,
                    'fb_pixels_count' => count($fbIds),
                ]);
            } else {
                Log::info('🔴 [Webhook] Payment is NOT approved (status: ' . $status . ')', [
                    'payment_id' => $paymentId,
                ]);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('🔴 [Webhook] GENERAL ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_id' => $paymentId,
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
