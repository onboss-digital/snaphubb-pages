<?php

use App\Livewire\PagePay;
use App\Livewire\StreamingCheckout;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal (checkout) - Livewire Component
Route::get('/', StreamingCheckout::class)->name('home');

// Rota para comprar acesso ao Painel de Voting (compra única, vitalícia)
Route::get('/buy-painel', \App\Livewire\PainelVotingCheckout::class)->name('buy.painel');

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

// DEBUG: Simula verificação de status PIX via PagePay para teste local
Route::get('/debug/pix-test/{id}', function ($id) {
	// Somente disponível em ambiente local
	if (!app()->environment('local') && !config('app.debug')) {
		abort(403, 'Not allowed');
	}

	try {
		$component = app()->make(\App\Livewire\PagePay::class);

		// Injeta um pixService fake que retorna status baseado em query ?status=paid
		$status = request()->query('status', 'paid');
		$fake = new class($status) {
			private $status;
			public function __construct($s) { $this->status = $s; }
			public function getPaymentStatus($id) {
				return ['data' => ['status' => $this->status]];
			}
		};

		// pixService is a private property on the Livewire component; use Reflection to inject our fake
		$ref = new \ReflectionClass($component);
		if ($ref->hasProperty('pixService')) {
			$prop = $ref->getProperty('pixService');
			$prop->setAccessible(true);
			$prop->setValue($component, $fake);
		}

		$component->pixTransactionId = $id;

		// Chama o método de checagem (igual ao polling)
		$component->checkPixPaymentStatus();

		return response()->json([
			'ok' => true,
			'pixTransactionId' => $component->pixTransactionId,
			'pixStatus' => $component->pixStatus,
			'showPixModal' => $component->showPixModal,
		]);
	} catch (\Throwable $e) {
		return response()->json(['ok' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
	}
});
