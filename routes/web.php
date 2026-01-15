<?php

use App\Livewire\PagePay;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal (checkout) - Livewire Component
Route::get('/', PagePay::class)->name('home');

// Upsell routes
Route::get('/upsell/painel-das-garotas', function(){
	return view('upsell.painel');
})->name('upsell.painel');

Route::get('/upsell/thank-you', function(){
	return view('upsell.thank');
})->name('upsell.thank');

// Página quando o usuário recusa o upsell
Route::get('/upsell/thank-you-recused', function(){
    return view('upsell.thank-you-recused');
})->name('upsell.thank_recused');

// Card-specific routes (kept for backward compatibility and used by card flows)
Route::get('/upsell/painel-das-garotas-card', function(){
	return view('upsell.painel');
})->name('upsell.painel_card');

Route::get('/upsell/thank-you-card', function(){
	return view('upsell.thank');
})->name('upsell.thank_card');

Route::get('/upsell/thank-you-recused-card', function(){
	return view('upsell.thank-you-recused');
})->name('upsell.thank_recused_card');

// Note: Only '-card' variants are enabled for card flows; do not accept '/card' variants.

// QR-specific upsell pages (for PIX flows)
Route::get('/upsell/painel-das-garotas-qr', function(){
	return view('upsell.painel-qr');
})->name('upsell.painel_qr');

Route::get('/upsell/thank-you-qr', function(){
	return view('upsell.thank-qr');
})->name('upsell.thank_qr');

// Página quando o usuário recusa o upsell (PIX)
Route::get('/upsell/thank-you-recused-qr', function(){
	return view('upsell.thank-you-recused-qr');
})->name('upsell.thank_recused_qr');

// Legal pages
Route::get('/terms-of-use', function(){
    return view('pages.terms');
})->name('terms');

Route::get('/privacy-policy', function(){
    return view('pages.privacy');
})->name('privacy');

Route::get('/support', function(){
    return view('pages.support');
})->name('support');

// Debug: Rota para simular sessão de cliente (apenas para testes locais)
Route::get('/debug/set-last-customer', function(){
	session(['last_order_customer' => [
		'name' => 'Cliente Teste',
		'email' => 'teste@snaphubb.com',
		'phone' => '5511999887766',
		'document' => '11144477735'
	]]);
	return redirect('/upsell/painel-das-garotas')->with('success', 'Sessão de cliente de teste configurada!');
});
