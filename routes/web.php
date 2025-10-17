<?php

use App\Livewire\PagePay;
use App\Http\Controllers\Webhook\AbacatePayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal (checkout) - Livewire Component
Route::get('/', PagePay::class);

// ===== WEBHOOK ABACATEPAY =====
Route::post('/webhook/abacatepay', [AbacatePayWebhookController::class, 'handle'])
    ->name('webhook.abacatepay');


