<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\FacebookConversionsService;
use Illuminate\Support\Facades\Log;

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


