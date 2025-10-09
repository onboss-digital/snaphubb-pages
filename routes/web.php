<?php

use App\Livewire\PagePay;
use App\Http\Controllers\Webhook\AbacatePayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal (checkout)
Route::get('/', PagePay::class)->name('home');

// ===== WEBHOOK ABACATEPAY =====
Route::post('/webhook/abacatepay', [AbacatePayWebhookController::class, 'handle'])
    ->name('webhook.abacatepay');

// ===== PÁGINAS DE RETORNO PIX =====

// Página de sucesso após pagamento PIX
Route::get('/obg/', function () {
    return view('obg');
})->name('payment.success');

// Página de falha/expiração PIX (opcional)
Route::get('/fail-pix/', function () {
    return view('fail-pix');
})->name('payment.failed');
