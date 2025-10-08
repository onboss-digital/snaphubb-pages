<?php

use App\Livewire\PagePay;
use App\Http\Controllers\Webhook\AbacatePayWebhookController; // ← NOVO
use Illuminate\Support\Facades\Route;

Route::get('/', PagePay::class)->name('home');

// ===== WEBHOOK ABACATEPAY (NOVO) =====
Route::post('/webhook/abacatepay', [AbacatePayWebhookController::class, 'handle'])
    ->name('webhook.abacatepay');
