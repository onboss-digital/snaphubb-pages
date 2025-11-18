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
