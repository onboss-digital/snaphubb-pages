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
