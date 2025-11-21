<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parabéns - Snaphubb</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
</head>
<body class="bg-black text-white min-h-screen">
    <!-- Animated Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-red-600/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-red-600/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <!-- Content -->
    <div class="relative z-10 max-w-4xl mx-auto px-6 py-12 md:py-24">
        
        <!-- Header -->
        <div class="border-b border-red-900/30 pb-8 mb-12">
            <h1 class="text-2xl font-bold text-red-600">SNAPHUBB</h1>
        </div>

        <!-- Success Animation -->
        <div class="text-center mb-16">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-full mb-8 animate-bounce">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h2 class="text-5xl md:text-6xl font-bold mb-4">
                Parabéns! 🎉
            </h2>
            <p class="text-xl md:text-2xl text-gray-300 mb-2">
                Sua compra foi confirmada com sucesso
            </p>
            <p class="text-gray-500 mb-8">
                Prepare-se para descobrir um mundo de entretenimento sem limites
            </p>
        </div>

        <!-- Order Summary Card -->
        <div class="bg-gradient-to-br from-gray-900 to-black border border-red-600/40 rounded-2xl p-8 md:p-12 mb-12">
            <h3 class="text-2xl font-bold mb-8">Resumo da sua Compra</h3>

            <div class="space-y-6 mb-8 pb-8 border-b border-gray-800">
                <!-- Item 1 -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-red-600/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-white">Streaming Snaphubb — 1x mês</p>
                            <p class="text-gray-500 text-sm">Acesso imediato</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-white">R$ 24,90</p>
                </div>

                <!-- Item 2 - Premium -->
                <div class="flex justify-between items-center p-4 bg-red-600/10 rounded-lg border border-red-600/30">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-red-600/40 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-white">✨ Painel das Garotas — Vitalício</p>
                            <p class="text-gray-400 text-sm">De R$ 99,90 por R$ 37,00</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-green-500">R$ 37,00</p>
                </div>
            </div>

            <!-- Total -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <p class="text-gray-500 text-sm mb-2">Total Pago</p>
                    <p class="text-4xl font-bold text-white">R$ 61,90</p>
                </div>
                <div class="text-right">
                    <p class="bg-green-600/20 border border-green-600/40 text-green-400 px-4 py-2 rounded-lg text-sm font-semibold">
                        ✓ Pagamento Confirmado
                    </p>
                </div>
            </div>

            <!-- Confirmation Email -->
            <div class="bg-black/50 border border-gray-800 rounded-lg p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <div class="text-sm text-gray-400">
                    <p class="mb-1">Um email de confirmação foi enviado para</p>
                    <p class="font-semibold text-white">{{ session('last_order_customer.email') ?? 'seu-email@email.com' }}</p>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            
            <!-- Step 1 -->
            <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
                <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-red-500 font-bold text-lg">1</span>
                </div>
                <h4 class="font-bold text-white mb-2">Acesse Sua Conta</h4>
                <p class="text-gray-400 text-sm">Use o email e senha enviados em seu e-mail de compra para acessar a plataforma</p>
            </div>

            <!-- Step 2 -->
            <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
                <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-red-500 font-bold text-lg">2</span>
                </div>
                <h4 class="font-bold text-white mb-2">Valide sua conta</h4>
                <p class="text-gray-400 text-sm">Faça a verificação da conta</p>
            </div>

            <!-- Step 3 -->
            <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
                <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-red-500 font-bold text-lg">3</span>
                </div>
                <h4 class="font-bold text-white mb-2">Aproveite!</h4>
                <p class="text-gray-400 text-sm">Explore as mais votadas da plataforma.</p>
            </div>
        </div>

        <!-- CTA Button -->
        <div class="mb-12">
            <button onclick="window.location.href='https://snaphubb.com/login'" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-lg group">
                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Ir para Plataforma
            </button>
        </div>

        <!-- Benefits Highlight -->
        <div class="bg-gradient-to-r from-red-600/20 to-red-600/10 border border-red-600/40 rounded-2xl p-8 mb-12">
            <h3 class="text-2xl font-bold mb-6">O que você desbloqueou:</h3>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <p class="text-gray-300 font-semibold">+1.000 conteúdos liberados.</p>
                        <p class="text-gray-400 text-sm">Tudo do Only, Privacy e mais em um único lugar. A maior biblioteca premium da internet na sua mão.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <p class="text-gray-300 font-semibold">Zero anúncios, zero pop-ups, zero travas.</p>
                        <p class="text-gray-400 text-sm">Navegação 100% limpa para você focar apenas no que interessa.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <p class="text-gray-300 font-semibold">Um único PIX substitui assinaturas que custariam uma fortuna.</p>
                        <p class="text-gray-400 text-sm">Economia brutal com sigilo total na sua fatura.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Section -->
        <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-8">
            <h3 class="text-xl font-bold mb-4">Precisa de Ajuda?</h3>
            <p class="text-gray-400 mb-6">Nosso time de suporte está disponível 24/7 para te ajudar</p>
            <div class="flex flex-col sm:flex-row gap-4">
                <button
                    onclick="copyEmail()"
                    id="copy-email-btn"
                    class="px-6 py-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-300"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span id="email-text">suporte@snaphubb.com</span>
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-12 pt-8 border-t border-gray-800 text-gray-500 text-sm space-y-2">
            <p>🔒 Você está seguro com Snaphubb</p>
            <p>Política de Privacidade • Termos de Serviço • Central de Ajuda</p>
        </div>
    </div>

    <script>
        (function(){
            try {
                var lastOrderTx = "{{ session('last_order_transaction') ?? '' }}";
                var lastOrderAmount = "{{ session('last_order_amount') ?? '' }}";
                var lastOrderEmail = "{{ session('last_order_customer.email') ?? '' }}";
                if (lastOrderTx) {
                    // safe wrapper for fbq
                    function safeFbqTrackLocal(eventName, params, options){
                        if (typeof fbq === 'function') { try { if(options) fbq('track', eventName, params || {}, options); else fbq('track', eventName, params || {}); } catch(e){ console.warn('fbq track failed', e);} return; }
                        var tries = 0; var iv = setInterval(function(){
                            if (typeof fbq === 'function'){ clearInterval(iv); try{ if(options) fbq('track', eventName, params || {}, options); else fbq('track', eventName, params || {}); }catch(e){console.warn('fbq track failed after wait', e);} }
                            tries++; if(tries>20){ clearInterval(iv); console.warn('fbq not available to track', eventName);} }, 250);
                    }

                    var params = { value: parseFloat(lastOrderAmount) || 0, currency: 'BRL' };
                    var options = { eventID: lastOrderTx };
                    safeFbqTrackLocal('Purchase', params, options);

                    // Notify server to clear session so refresh won't retrigger
                    fetch('/api/analytics/clear-last-order', { method: 'POST', headers: { 'Content-Type': 'application/json' } }).catch(function(e){ console.warn('clear-last-order failed', e); });
                }
            } catch(e) { console.error('thank analytics error', e); }
        })();
        // Copy email function
        function copyEmail() {
            const email = 'suporte@snaphubb.com';
            navigator.clipboard.writeText(email).then(() => {
                const btn = document.getElementById('copy-email-btn');
                const text = document.getElementById('email-text');
                
                btn.classList.remove('bg-gray-800', 'hover:bg-gray-700');
                btn.classList.add('bg-green-600');
                text.textContent = 'Email Copiado!';
                
                setTimeout(() => {
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('bg-gray-800', 'hover:bg-gray-700');
                    text.textContent = 'suporte@snaphubb.com';
                }, 2000);
            });
        }
    </script>
</body>
</html>
