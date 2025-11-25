<?php

use App\Http\Controllers\PixController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes para PIX
 * Todos os endpoints retornam JSON
 */
Route::prefix('pix')->group(function () {
    // Criar pagamento PIX com validação de integridade
    // POST /api/pix/create
    Route::post('/create', [PixController::class, 'create'])->name('pix.create');

    // Consultar status do pagamento PIX
    // GET /api/pix/status/:payment_id
    Route::get('/status/{paymentId}', [PixController::class, 'getPaymentStatus'])->name('pix.status');
});

// Mercado Pago webhook (PIX notifications)
Route::post('/webhook/mercadopago', [\App\Http\Controllers\MercadoPagoWebhookController::class, 'handle'])->name('webhook.mercadopago');

// Pushing Pay webhook (PIX notifications)
Route::post('/pix/webhook', [\App\Http\Controllers\PushingPayWebhookController::class, 'handle'])->name('webhook.pushinpay');

// Stripe webhook (payment_intent.succeeded)
Route::post('/webhook/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('webhook.stripe');

// Analytics helper: clear last-order session after client fires Purchase
Route::post('/analytics/clear-last-order', [\App\Http\Controllers\AnalyticsController::class, 'clearLastOrder'])->name('analytics.clear_last_order');
