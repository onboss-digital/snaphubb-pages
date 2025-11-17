<?php

use App\Http\Controllers\PixController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes para PIX
 * Todos os endpoints retornam JSON
 */
Route::prefix('api/pix')->group(function () {
    // Criar pagamento PIX
    // POST /api/pix/create
    Route::post('/create', [PixController::class, 'createPayment'])->name('pix.create');

    // Consultar status do pagamento PIX
    // GET /api/pix/status/:payment_id
    Route::get('/status/{paymentId}', [PixController::class, 'getPaymentStatus'])->name('pix.status');
});
