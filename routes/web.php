<?php

use App\Livewire\PagePay;
use App\Http\Controllers\Webhook\AbacatePayWebhookController; // ← NOVO
use Illuminate\Support\Facades\Route;

Route::get('/', PagePay::class)->name('home');

// ===== WEBHOOK ABACATEPAY (NOVO) =====
Route::post('/webhook/abacatepay', [AbacatePayWebhookController::class, 'handle'])
    ->name('webhook.abacatepay');

use App\Http\Controllers\AbacatePayWebhookController;

// Webhook do AbacatePay
Route::post('/webhook/abacatepay', [AbacatePayWebhookController::class, 'handle'])
    ->name('webhook.abacatepay');

// Página de sucesso após pagamento PIX
Route::get('/obg/', function () {
    return view('obg'); // ou sua view de sucesso
})->name('payment.success');

// Página de falha/expiração PIX (opcional)
Route::get('/fail-pix/', function () {
    return view('fail-pix'); // ou sua view de falha
})->name('payment.failed');
