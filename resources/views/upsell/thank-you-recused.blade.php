<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parabéns — Snaphubb</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
</head>
<body class="min-h-screen bg-black text-white">
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
        <!-- Check Icon -->
        <svg class="w-12 h-12 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>

      <h2 class="text-5xl md:text-6xl font-bold mb-4">Parabéns! 🎉</h2>
      <p class="text-xl md:text-2xl text-gray-300 mb-2">Sua compra foi confirmada com sucesso</p>
      <p class="text-gray-500 mb-8">Prepare-se para descobrir um mundo de entretenimento latino</p>
    </div>

    <!-- Order Summary Card - Simple Version -->
    <div class="bg-gradient-to-br from-gray-900 to-black border border-red-600/40 rounded-2xl p-8 md:p-12 mb-12">
      <h3 class="text-2xl font-bold mb-8">Resumo da sua Compra</h3>

      <div class="space-y-6 mb-8 pb-8 border-b border-gray-800">
        <!-- Single Item -->
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-red-600/20 rounded-lg flex items-center justify-center flex-shrink-0">
              <!-- Play Icon -->
              <svg class="w-8 h-8 text-red-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3v18l15-9L5 3z" fill="currentColor"/></svg>
            </div>
            <div>
              <p class="font-bold text-white">Streaming Snaphubb — 1x mês</p>
              <p class="text-gray-500 text-sm">Acesso imediato</p>
            </div>
          </div>
          <p class="text-lg font-bold text-white">R$ 24,90</p>
        </div>
      </div>

      <!-- Total -->
      <div class="flex justify-between items-end mb-8">
        <div>
          <p class="text-gray-500 text-sm mb-2">Total Pago</p>
          <p class="text-4xl font-bold text-white">R$ 24,90</p>
        </div>
        <div class="text-right">
          <p class="bg-green-600/20 border border-green-600/40 text-green-400 px-4 py-2 rounded-lg text-sm font-semibold">✓ Pagamento Confirmado</p>
        </div>
      </div>

      <!-- Confirmation Email -->
      <div class="bg-black/50 border border-gray-800 rounded-lg p-4 flex items-center gap-3">
        <!-- Mail Icon -->
        <svg class="w-5 h-5 text-blue-400 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <div class="text-sm text-gray-400">
          <p class="mb-1">Um email de confirmação foi enviado para</p>
          <p class="font-semibold text-white">{{ session('last_order_customer.email') ?? 'usuario@email.com' }}</p>
        </div>
      </div>
    </div>

    <!-- Upsell Reminder - Soft -->
    <div class="bg-gradient-to-r from-amber-600/20 to-amber-600/10 border border-amber-600/40 rounded-xl p-6 md:p-8 mb-12">
      <div class="flex items-start gap-4">
        <!-- Zap Icon -->
        <svg class="w-6 h-6 text-amber-500 flex-shrink-0 mt-1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 2L3 14h7l-1 8L21 10h-7l-1-8z" fill="currentColor"/></svg>
        <div>
          <h4 class="font-bold text-white mb-2">💡 Quer aproveitar mais?</h4>
          <p class="text-gray-400 text-sm mb-4">A oferta especial de upgrade para Premium está ainda disponível! Garanta 3 meses + 30 dias grátis com desconto PIX.</p>
          <button class="text-amber-500 hover:text-amber-400 font-semibold text-sm flex items-center gap-2 transition-all">Ver Oferta Especial
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3v18l15-9L5 3z" fill="currentColor"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Next Steps -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
      <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4"><span class="text-red-500 font-bold text-lg">1</span></div>
        <h4 class="font-bold text-white mb-2">Faça Login</h4>
        <p class="text-gray-400 text-sm">Use o email e sua vinculador em seu e-mail de compra para acessar a plataforma</p>
      </div>
      <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4"><span class="text-red-500 font-bold text-lg">2</span></div>
        <h4 class="font-bold text-white mb-2">Valide sua conta</h4>
        <p class="text-gray-400 text-sm">Faça a verificação da conta</p>
      </div>
      <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-red-600/40 transition-all">
        <div class="w-12 h-12 bg-red-600/20 rounded-lg flex items-center justify-center mb-4"><span class="text-red-500 font-bold text-lg">3</span></div>
        <h4 class="font-bold text-white mb-2">Aproveite!</h4>
        <p class="text-gray-400 text-sm">Explore as mais vidades da plataforma</p>
      </div>
    </div>

    <!-- CTA Buttons -->
    <div class="mb-12">
      <a href="https://snaphubb.com/login" class="w-full inline-flex justify-center bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg transition-all duration-300 items-center gap-2 text-lg"> 
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3v18l15-9L5 3z" fill="currentColor"/></svg>
        Ir para Plataforma
      </a>
    </div>

    <!-- Redirect Timer -->
    <div class="bg-blue-600/10 border border-blue-600/30 rounded-lg p-6 text-center mb-12">
      <div class="flex items-center justify-center gap-2 text-blue-400 font-semibold mb-2">
        <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a10 10 0 100 20 10 10 0 000-20z" stroke="currentColor" stroke-width="2"/></svg>
        Redirecionando em <span id="redirect-seconds">5</span>s
      </div>
      <p class="text-gray-400 text-sm">ou clique em "Ir para Plataforma" para começar agora</p>
    </div>

    <!-- What You Get -->
    <div class="bg-gradient-to-r from-red-600/20 to-red-600/10 border border-red-600/40 rounded-2xl p-8 mb-12">
      <h3 class="text-2xl font-bold mb-6">O que você desbloqueou:</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="text-gray-300">+1.000 conteúdos liberados.</span></div>
        <div class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="text-gray-300">Zero anúncios, zero pop-ups, zero travs.</span></div>
        <div class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="text-gray-300">Um único PIX substituir assinaturas que custavam uma fortuna.</span></div>
        <div class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="text-gray-300">Acesso a +10.000 produções latinas</span></div>
      </div>
    </div>

    <!-- Support Section -->
    <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-8">
      <h3 class="text-xl font-bold mb-4">Precisa de Ajuda?</h3>
      <p class="text-gray-400 mb-6">Nosso time de suporte está disponível 24/7 para te ajudar com qualquer dúvida</p>
      <div class="flex flex-col sm:flex-row gap-4">
        <button id="copy-email-btn" class="px-6 py-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-300">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span id="copy-email-text">{{ session('support_email') ?? 'suporte@snaphubb.com' }}</span>
        </button>
        <a href="mailto:suporte@snaphubb.com" class="px-6 py-3 rounded-lg font-semibold bg-gray-800 hover:bg-gray-700 text-gray-300 transition-all flex items-center justify-center gap-2">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v16H4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Fale com Suporte
        </a>
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
        if (lastOrderTx) {
          // Safe local wrapper to send Purchase when fbq is available
          function safeFbqTrackLocal(eventName, params, options){
            if (typeof fbq === 'function') { try { if(options) fbq('track', eventName, params || {}, options); else fbq('track', eventName, params || {}); } catch(e){ console.warn('fbq track failed', e);} return; }
            var tries = 0; var iv = setInterval(function(){ if (typeof fbq === 'function'){ clearInterval(iv); try{ if(options) fbq('track', eventName, params || {}, options); else fbq('track', eventName, params || {});}catch(e){console.warn('fbq track failed after wait', e);} } tries++; if(tries>20){ clearInterval(iv); console.warn('fbq not available to track', eventName);} }, 250);
          }
          safeFbqTrackLocal('Purchase', { value: parseFloat(lastOrderAmount) || 0, currency: 'BRL' }, { eventID: lastOrderTx });
          fetch('/api/analytics/clear-last-order', { method: 'POST', headers: { 'Content-Type': 'application/json' } }).catch(function(e){ console.warn('clear-last-order failed', e); });
        }
      } catch(e) { console.error('thank-you-recused analytics error', e); }
    })();
    (function(){
      // Countdown + redirect
      var secondsEl = document.getElementById('redirect-seconds');
      var seconds = 5;
      var interval = setInterval(function(){
        if(seconds > 0){
          seconds = seconds - 1;
          if(secondsEl) secondsEl.textContent = seconds;
        }
        if(seconds === 0){
          clearInterval(interval);
          // Não redirecionar automaticamente — manter o usuário na página
        }
      }, 1000);

      // Copy email button
      var copyBtn = document.getElementById('copy-email-btn');
      var copyText = document.getElementById('copy-email-text');
      var originalText = copyText ? copyText.textContent : 'suporte@snaphubb.com';
      if(copyBtn){
        copyBtn.addEventListener('click', function(){
          var email = originalText || 'suporte@snaphubb.com';
          if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(email).then(function(){
              if(copyText) copyText.textContent = 'Email Copiado!';
              copyBtn.classList.remove('bg-gray-800');
              copyBtn.classList.add('bg-green-600','text-white');
              setTimeout(function(){
                if(copyText) copyText.textContent = originalText;
                copyBtn.classList.remove('bg-green-600','text-white');
                copyBtn.classList.add('bg-gray-800');
              }, 2000);
            }).catch(function(){
              // fallback
            });
          }
        });
      }
    })();
  </script>
</body>
</html>
