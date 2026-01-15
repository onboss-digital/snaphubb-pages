<!-- Order Summary Card (partial reused by QR pages) -->
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

<!-- Next Steps and CTA reused -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
    <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
            <span class="text-red-500 font-bold text-lg">1</span>
        </div>
        <h4 class="font-bold text-white mb-2">Acesse Sua Conta</h4>
        <p class="text-gray-400 text-sm">Use o email e senha enviados em seu e-mail de compra para acessar a plataforma</p>
    </div>
    <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
            <span class="text-red-500 font-bold text-lg">2</span>
        </div>
        <h4 class="font-bold text-white mb-2">Valide sua conta</h4>
        <p class="text-gray-400 text-sm">Faça a verificação da conta</p>
    </div>
    <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4">
            <span class="text-red-500 font-bold text-lg">3</span>
        </div>
        <h4 class="font-bold text-white mb-2">Aproveite!</h4>
        <p class="text-gray-400 text-sm">Explore as mais votadas da plataforma.</p>
    </div>
</div>

<div class="mb-12">
    <button onclick="window.location.href='https://snaphubb.com/login'" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-lg group">
        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Ir para Plataforma
    </button>
</div>

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
