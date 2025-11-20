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
        Log::info('Received Mercado Pago webhook', ['body' => $request->all()]);

        // Try common fields for payment id
        $paymentId = $request->input('data.id') ?? $request->input('resource.id') ?? $request->input('id') ?? $request->input('collection.id') ?? null;

        if (!$paymentId) {
            Log::warning('MercadoPagoWebhook: payment id not found in payload', ['payload' => $request->all()]);
            return response()->json(['status' => 'ignored', 'message' => 'no payment id'], 200);
        }

        $cacheKey = 'mp_webhook_processed:' . $paymentId;
        if (Cache::has($cacheKey)) {
            Log::info('MercadoPagoWebhook: already processed', ['payment_id' => $paymentId]);
            return response()->json(['status' => 'ok', 'message' => 'already processed'], 200);
        }

        try {
            $result = $this->pixService->getPaymentStatus($paymentId);
            if (!isset($result['status']) || $result['status'] !== 'success') {
                Log::warning('MercadoPagoWebhook: could not get payment status', ['payment_id' => $paymentId, 'result' => $result]);
                return response()->json(['status' => 'error', 'message' => 'could not retrieve status'], 422);
            }

            $data = $result['data'] ?? [];
            $status = strtolower($data['status'] ?? ($data['payment_status'] ?? 'unknown'));

            Log::info('MercadoPagoWebhook: payment status', ['payment_id' => $paymentId, 'status' => $status]);

            if ($status === 'approved' || $status === 'paid' || $status === 'success') {
                // Mark as processed to avoid double-processing
                Cache::put($cacheKey, true, now()->addDays(7));

                // Build payload for FB CAPI
                $value = isset($data['amount']) ? ($data['amount'] / 100) : ($data['value'] ?? 0);
                $currency = $data['currency'] ?? 'BRL';
                $contentIds = isset($data['cart']) ? array_map(function($i){ return $i['product_hash'] ?? ($i['id'] ?? null); }, $data['cart']) : null;

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
                            'value' => $value,
                            'currency' => $currency,
                            'email' => $data['payer']['email'] ?? null,
                            'phone' => $data['payer']['phone'] ?? null,
                            'client_ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'content_ids' => $contentIds,
                            'content_type' => 'product',
                            'event_source_url' => $request->headers->get('referer') ?? url('/'),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('MercadoPagoWebhook: FB CAPI send failed', ['error' => $e->getMessage(), 'payment_id' => $paymentId]);
                    }
                }

                // TODO: Provision product / mark order as paid in DB
                Log::info('MercadoPagoWebhook: processed approved payment', ['payment_id' => $paymentId]);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('MercadoPagoWebhook: general error', ['error' => $e->getMessage(), 'payment_id' => $paymentId]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
