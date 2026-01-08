<?php

use App\Http\Controllers\PixController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BumpController;
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

/**
 * API Routes para Banners
 */
Route::prefix('banners')->group(function () {
    Route::post('/upload', [BannerController::class, 'upload'])->name('banners.upload');
    Route::get('/list', [BannerController::class, 'list'])->name('banners.list');
    Route::delete('/{filename}', [BannerController::class, 'delete'])->name('banners.delete');
});

/**
 * API Routes para Order Bumps
 */
Route::prefix('bumps')->group(function () {
    // GET /api/bumps - todos os bumps ativos
    // GET /api/bumps?method=card - bumps para cartão
    // GET /api/bumps?method=pix - bumps para PIX
    Route::get('/', [BumpController::class, 'list'])->name('bumps.list');

    // GET /api/bumps/by-method - bumps separados por método de pagamento
    Route::get('/by-method', [BumpController::class, 'byMethod'])->name('bumps.by_method');
});

// Pushing Pay webhook (PIX notifications)
Route::post('/pix/webhook', [\App\Http\Controllers\PushingPayWebhookController::class, 'handle'])->name('webhook.pushinpay');

// Stripe webhook (payment_intent.succeeded)
Route::post('/webhook/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('webhook.stripe');

// Analytics helper: clear last-order session after client fires Purchase
Route::post('/analytics/clear-last-order', [\App\Http\Controllers\AnalyticsController::class, 'clearLastOrder'])->name('analytics.clear_last_order');
