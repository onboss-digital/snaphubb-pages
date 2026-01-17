@push('head')
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/ico" href="{{ asset('imgs/mini_logo.png') }}" />

@php
$gateway = config('services.default_payment_gateway', 'stripe');
@endphp

<style>
    body {
        font-family: 'Urbanist', sans-serif;
        background-color: #121212;
        color: white;
    }

    .animate-fade {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.98);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* PIX Modal Responsive Styles */
    #pix-modal {
        animation: fadeIn 0.5s ease-in-out;
    }

    #pix-modal img {
        max-width: 100%;
        height: auto;
    }

    /* Backdrop blur effect */
    #pix-modal-backdrop {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        background-color: rgba(0, 0, 0, 0.5);
        opacity: 1;
        transition: opacity 0.3s ease-in-out;
    }

    #pix-modal-backdrop.hidden {
        opacity: 0;
        pointer-events: none;
    }

    /* Modal backdrop - only blur background, not content */
    body.pix-modal-open {
        overflow: hidden;
    }

    /* Breakpoints para máxima responsividade */
    @media (max-width: 383px) {
        #pix-modal {
            padding: 12px;
        }
    }

    @media (min-width: 640px) and (max-width: 1023px) {
        #pix-modal > div {
            max-width: 32rem;
        }
    }

    @media (min-width: 1024px) {
        #pix-modal > div {
            max-width: 48rem;
        }
    }

    .sticky-summary {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 50;
        transform: translateY(0);
        /* Always visible on mobile */
        transition: none;
    }
    /* Price origin indicators */
    .price-fallback { color: #00F5D4 !important; }
    /* When totals come from backend, show them in green to indicate authoritative price */
    .price-backend { color: #34D399 !important; }
    .animate-fallback-pulse { animation: fallbackPulse 1.6s ease-in-out infinite; }
    @keyframes fallbackPulse {
        0% { opacity: 1; }
        50% { opacity: 0.85; }
        100% { opacity: 1; }
    }
    /* Order bump PIX description responsiveness */
    .bump-card-pix .bump-desc {
        white-space: normal;
        overflow: hidden;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2; /* mobile default: 2 lines */
    }
    @media (min-width: 768px) {
        .bump-card-pix .bump-desc { -webkit-line-clamp: 3; }
    }
    @media (min-width: 1024px) {
        .bump-card-pix .bump-desc { -webkit-line-clamp: 4; }
    }
</style>
<script type="text/javascript">
    (function(c, l, a, r, i, t, y) {
        c[a] = c[a] || function() {
            (c[a].q = c[a].q || []).push(arguments)
        };
        t = l.createElement(r);
        t.async = 1;
        t.src = "https://www.clarity.ms/tag/" + i;
        y = l.getElementsByTagName(r)[0];
        y.parentNode.insertBefore(t, y);
    })(window, document, "clarity", "script", "rtcb4op3g8");
</script>

<!-- Google Analytics (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-G6FBHCNW8X"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);} gtag('js', new Date());
    gtag('config', 'G-G6FBHCNW8X');
</script>
<!-- End Google Analytics -->
@endpush

@push('scripts')
<script>
    window.APP_DEBUG = @json(config('app.debug'));
    if (!window.APP_DEBUG) {
        (function(){
            var noop = function(){};
            try {
                console.log = noop;
                console.info = noop;
                console.warn = noop;
                console.error = noop;
                console.debug = noop;
            } catch (e) {
                // ignore
            }
        })();
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    try{
        // Build checkout data from server variables
        window.checkoutData = {
            value: parseFloat("{{ isset($totals['final_price']) ? str_replace(',', '.', str_replace('.', '', $totals['final_price'])) : '0' }}"),
            currency: '{{ $selectedCurrency ?? 'BRL' }}',
            content_ids: {!! json_encode(isset($product['hash']) ? [$product['hash']] : []) !!}
        };

        // InitiateCheckout (GA4) — Facebook Pixel is initialized only on homepage below

        if (typeof gtag === 'function') {
            gtag('event', 'begin_checkout', {
                currency: window.checkoutData.currency || 'BRL',
                value: window.checkoutData.value || 0,
                items: (window.checkoutData.content_ids || []).map(function(id){ return {id: id, item_brand: 'Snaphubb', item_category: 'checkout'}; })
            });
        }

        // Facebook Pixel: Load and initialize with PageView event
        window.fbPixelId = '{{ config("analytics.fb_pixel_ids")[0] ?? "" }}' || '{{ env("FB_PIXEL_ID") }}';
        window.fbPixelDebug = {
            pixelId: window.fbPixelId,
            initialized: false,
            trackReady: false,
            errors: []
        };
        
        (function(){
            try {
                if (!window.fbPixelId || window.fbPixelId === 'YOUR_FACEBOOK_PIXEL_ID') {
                    console.warn('⚠️ Facebook Pixel ID not configured or invalid');
                    window.fbPixelDebug.errors.push('Pixel ID not configured');
                    return;
                }
                
                console.log('📍 Starting Facebook Pixel initialization for ID:', window.fbPixelId);
                
                // Step 1: Inject base fbq snippet (creates queue)
                !function(f,b,e,v,n,t,s){
                    if(f.fbq) {
                        console.log('✓ fbq already exists, skipping re-initialization');
                        return;
                    }
                    
                    console.log('📝 Creating fbq queue function...');
                    n=f.fbq=function(){
                        n.callMethod ? n.callMethod.apply(n,arguments) : n.queue.push(arguments);
                    };
                    if(!f._fbq)f._fbq=n;
                    n.push=n;
                    n.loaded=!0;
                    n.version='2.0';
                    n.queue=[];
                    
                    console.log('🔧 Creating and injecting fbevents.js script tag...');
                    t=b.createElement(e);
                    t.async=!0;
                    t.src=v;
                    
                    t.onload=function(){
                        console.log('✅ fbevents.js LOADED successfully');
                        window.fbPixelDebug.loaded = true;
                    };
                    
                    t.onerror=function(err){
                        console.error('❌ fbevents.js FAILED to load:', err);
                        window.fbPixelDebug.errors.push('Script load error: ' + (err ? err.message : 'unknown'));
                    };
                    
                    s=b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t,s);
                    console.log('📌 fbevents.js script injected into DOM');
                }(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
                
                // Step 2: Queue init + PageView (will execute when fbevents.js loads)
                console.log('📤 Queueing fbq commands...');
                fbq('init', window.fbPixelId);
                fbq('track', 'PageView');
                fbq('track', 'InitiateCheckout', { 
                    value: window.checkoutData.value || 0, 
                    currency: window.checkoutData.currency || 'BRL' 
                });
                
                window.fbPixelDebug.initialized = true;
                console.log('✓ Facebook Pixel queued commands for ID:', window.fbPixelId);
            } catch(e){
                console.error('❌ Facebook Pixel setup error:', e);
                window.fbPixelDebug.errors.push(e.message);
            }
        })();

        // DIAGNOSTIC: Check when fbq.track becomes available (detailed logging)
        (function() {
            var attempts = 0;
            var maxAttempts = 50; // 5 seconds total (50 * 100ms)
            var checkInterval = setInterval(function() {
                attempts++;
                var fbqType = typeof window.fbq;
                var fbqTrackType = (fbqType === 'function' && typeof window.fbq.track === 'function') ? 'function' : 'undefined';
                
                if (fbqTrackType === 'function') {
                    window.fbPixelDebug.trackReady = true;
                    console.log('✅ fbq.track is READY after ' + (attempts * 100) + 'ms');
                    clearInterval(checkInterval);
                } else if (attempts === 1 || attempts === 10 || attempts === 25 || attempts === maxAttempts) {
                    // Log at key intervals to avoid spam
                    console.log('⏳ Attempt ' + attempts + '/' + maxAttempts + ' - fbq type: ' + fbqType + ', fbq.track type: ' + fbqTrackType);
                }
                
                if (attempts >= maxAttempts) {
                    if (fbqType === 'undefined') {
                        console.error('❌ CRITICAL: window.fbq is still UNDEFINED after 5 seconds');
                        console.error('This means fbevents.js script either:');
                        console.error('  1. Failed to load (network error, CSP blocked, etc)');
                        console.error('  2. Did not execute/initialize properly');
                        console.error('  3. Script URL is wrong or unreachable');
                    } else {
                        console.warn('⚠️ fbq.track not ready, but fbq exists. Still loading...');
                    }
                    console.log('📊 Debug info:', window.fbPixelDebug);
                    clearInterval(checkInterval);
                }
            }, 100);
        })();
        var paymentInputs = document.querySelectorAll('input[name="payment_method"]');
        paymentInputs.forEach(function(inp){
            inp.addEventListener('change', function(e){
                var method = e.target.value || (e.target.getAttribute('value') || 'unknown');
                if (typeof gtag === 'function') {
                    gtag('event', 'add_payment_info', {payment_type: method});
                }
            });
        });

        // Listen for checkout-success Livewire event (server-side approved)
        if (window.Livewire) {
            Livewire.on('checkout-success', function(payload){
                var purchase = (payload && payload.purchaseData) ? payload.purchaseData : payload || {};
                var value = parseFloat(purchase.value || window.checkoutData.value || 0);
                var currency = purchase.currency || window.checkoutData.currency || 'BRL';
                var content_ids = purchase.content_ids || window.checkoutData.content_ids || [];

                // Facebook Purchase (use safe wrapper in case fbq not yet ready)
                // Include eventID for deduplication with server-side CAPI (if available in payload)
                var eventId = (purchase && purchase.transaction_id) ? purchase.transaction_id : null;
                var fbParams = {
                    value: value,
                    currency: currency,
                    content_ids: content_ids,
                    content_type: 'product'
                };
                // Facebook Purchase removed — only InitiateCheckout will be emitted from homepage

                // GA4 purchase
                if (typeof gtag === 'function') {
                    gtag('event', 'purchase', {
                        transaction_id: purchase.transaction_id || null,
                        value: value,
                        currency: currency,
                        items: (content_ids || []).map(function(id){ return {id: id, item_brand: 'Snaphubb', item_category: 'purchase'}; })
                    });
                }
            });
        }
    }catch(e){ console.error('analytics error', e); }
});
</script>
@endpush


<div>
        <!-- Loader overlay (now controlled by Livewire state: $isProcessingCard || $showProcessingModal)
            Uses opacity transition for subtle darkening effect. -->
        <div id="payment-loader" class="fixed inset-0 z-50 flex items-center justify-center bg-black text-white px-4 {{ ($isProcessingCard || $showProcessingModal) ? 'opacity-100' : 'opacity-0 pointer-events-none' }}" style="backdrop-filter: blur(4px); background-color: rgba(0,0,0,0.6); transition: opacity 260ms ease;">
        <div class="max-w-md w-full text-center p-6 rounded-lg bg-gray-900 border border-gray-800">
            <div id="payment-loader-icon" class="mb-4">
                <svg class="mx-auto animate-spin h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
            <h2 id="payment-loader-title" class="text-lg font-semibold">{{ $loadingMessage ?? __('payment.processing_payment') }}</h2>
            <p id="payment-loader-sub" class="text-sm text-gray-300 mt-2">{{ $loadingMessage ? '' : 'Isso pode levar alguns segundos.' }}</p>
        </div>
    </div>
    <div class="container mx-auto px-4 py-8 max-w-4xl pb-24 md:pb-8">

        <!-- Header -->
        <header class="mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between">

                @php
                $logo = asset('imgs/logo.png');
                @endphp


                <img class="img-fluid logo" src="{{ $logo }}" alt="streamit">
                <div class="ml-4">
                    <div x-data="{open:false}" class="relative inline-block text-left">
                        <button @click.prevent="open = !open" type="button" class="inline-flex items-center px-3 py-1 rounded bg-gray-800 border border-gray-700 text-sm text-white">
                            {{ $availableLanguages[$selectedLanguage] ?? 'Português' }}
                            <svg class="w-3 h-3 ml-2" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.08 1.04l-4.25 4.25a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"/></svg>
                        </button>
                        <div x-show="open" @click.away="open=false" x-cloak class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-gray-900 border border-gray-700 z-50">
                            <div class="py-1">
                                @foreach($availableLanguages as $code => $label)
                                    <a href="#" wire:click.prevent="changeLanguage('{{ $code }}')" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
            // Banners reagem ao idioma selecionado
            $banners = [
                'br' => [
                    'desktop' => asset('imgs/banners/desktop-brasil.jpg'),
                    'mobile' => asset('imgs/banners/mobile-brasil.jpg'),
                ],
                'en' => [
                    'desktop' => asset('imgs/banners/desktop-america.jpg'),
                    'mobile' => asset('imgs/banners/mobile-america.jpg'),
                ],
                'es' => [
                    'desktop' => asset('imgs/banners/desktop-latam.jpg'),
                    'mobile' => asset('imgs/banners/mobile-latam.jpg'),
                ],
            ];
            
            // Use o idioma selecionado ou padrão para português
            $currentLang = $selectedLanguage ?? 'br';
            $currentBanners = $banners[$currentLang] ?? $banners['br'];
            @endphp
            <div class="mt-6 w-full rounded-xl overflow-hidden bg-gray-900">
                <!-- Desktop Banner -->
                @if(!empty($currentBanners['desktop']))
                    <img class="w-full hidden md:block" src="{{ $currentBanners['desktop'] }}" alt="Promotional Banner">
                @endif
                <!-- Mobile Banner -->
                @if(!empty($currentBanners['mobile']))
                    <img class="w-full md:hidden" src="{{ $currentBanners['mobile'] }}" alt="Promotional Banner">
                @endif
            </div>

            <h1 class="text-3xl md:text-4xl font-bold text-white mt-6 text-center">
                @if (App::getLocale() === 'en')
                    You are one step away from accessing the private + exclusive streaming in Latin America!
                @elseif (App::getLocale() === 'es')
                    ¡Estás a un paso de acceder al streaming privado + exclusivo de América Latina!
                @else
                    Você está a um passo de acessar o streaming privado + exclusivo da américa latina!
                @endif
            </h1>
            <p class="text-lg text-gray-300 mt-2 text-center">{{ __('checkout.subtitle') }}</p>
        </header>
        <form accept="" method="POST" id="payment-form">
            @csrf
            <script>
                (function(){
                    function el(sel){return document.querySelector(sel)}
                    var formReady = function(){
                        var form = el('#payment-form');
                        var loader = el('#payment-loader');
                        var title = el('#payment-loader-title');
                        var sub = el('#payment-loader-sub');
                        var lang = '{{ $selectedLanguage ?? 'br' }}';
                        var strings = {
                            processing: {br: 'Aguarde, estamos processando o pagamento…', en: 'Please wait, processing payment…', es: 'Aguarde, estamos processando el pago…'},
                            success: {br: 'Parabéns! Compra aprovada.', en: 'Success! Payment approved.', es: '¡Felicidades! Compra aprobada.'},
                            failed: {br: 'Pagamento não aprovado. Verifique os dados e tente novamente.', en: 'Payment failed. Please verify details and try again.', es: 'Pago no aprobado. Verifique y vuelva a intentar.'}
                        };
                        var loaderShownAt = null;
                        var MIN_LOADER_MS = 3000; // mostrar loader no mínimo 3s
                        var CONFIRMATION_MS = 2000; // mostrar mensagem de confirmação por ~2s

                        function show(key){ if (!loader) return; title.textContent = strings[key][lang] || strings[key]['br']; sub.style.display='block'; loader.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); loaderShownAt = Date.now(); }
                        function update(key){ if (!loader) return; title.textContent = strings[key][lang] || strings[key]['br']; }
                        function hide(){ if (!loader) return; loader.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); loaderShownAt = null; }

                        function whenMinLoaderElapsed(callback){
                            if (!loaderShownAt) return callback();
                            var elapsed = Date.now() - loaderShownAt;
                            var wait = Math.max(0, MIN_LOADER_MS - elapsed);
                            setTimeout(callback, wait);
                        }

                        // NOTE: Overlay visibility is now controlled by Livewire server state
                        // (variables: $isProcessingCard, $showProcessingModal). We intentionally
                        // do not auto-show the overlay on form submit/click to avoid duplicate
                        // UX when the inline button spinner is used.
                        var checkoutBtn = document.getElementById('checkout-button');
                        var paymentLoader = document.getElementById('payment-loader');
                        var paymentLoaderTitle = document.getElementById('payment-loader-title');
                        if (checkoutBtn && paymentLoader) {
                            // Reusable validation function for credit-card flow
                            function validateCardForm() {
                                try {
                                    var selected = (document.querySelector('input[name="payment_method"]:checked') || {}).value || '';
                                    if (selected !== 'credit_card') return { ok: true };

                                    var cardName = (document.querySelector('input[name="card_name"]') || {}).value || '';
                                    var email = (document.querySelector('input[name="email"]') || {}).value || '';
                                    var cardNumberEl = document.getElementById('card-number');
                                    var paymentMethodIdEl = document.getElementById('payment-method-id');

                                    var emailValid = /\S+@\S+\.\S+/.test(email);
                                    var hasCardNumber = false;

                                    if (cardNumberEl) {
                                        var v = cardNumberEl.value.replace(/\s+/g, '');
                                        hasCardNumber = v.length >= 12;
                                    } else if (paymentMethodIdEl) {
                                        hasCardNumber = (paymentMethodIdEl.value || '').length > 5;
                                    }

                                    if (!cardName.trim() || !emailValid || !hasCardNumber) {
                                        return { ok: false, msg: 'Preencha os campos obrigatórios do cartão: número do cartão, nome impresso no cartão e e-mail.' };
                                    }
                                    return { ok: true };
                                } catch (err) { return { ok: false, msg: 'Erro de validação' }; }
                            }

                            function handleCheckoutClick(e) {
                                try {
                                    var res = validateCardForm();
                                    if (!res.ok) {
                                        e.stopImmediatePropagation();
                                        e.preventDefault();
                                        var msg = res.msg || 'Preencha os campos obrigatórios do cartão.';
                                        try {
                                            var previous = document.getElementById('client-card-validation-msg');
                                            if (previous) previous.remove();
                                            if (paymentLoaderTitle && paymentLoaderTitle.parentNode) {
                                                var div = document.createElement('div');
                                                div.id = 'client-card-validation-msg';
                                                div.style.color = '#ffd1d1';
                                                div.style.fontSize = '13px';
                                                div.style.marginTop = '8px';
                                                div.textContent = msg;
                                                paymentLoaderTitle.parentNode.appendChild(div);
                                                setTimeout(function(){ try{ div.remove(); }catch(e){} }, 6000);
                                            } else {
                                                alert(msg);
                                            }
                                        } catch (err) { alert(msg); }
                                        return;
                                    }

                                    if (!paymentLoader.classList.contains('opacity-0')) return;
                                    paymentLoader.classList.remove('opacity-0','pointer-events-none');
                                    paymentLoader.classList.add('opacity-100');
                                    if (paymentLoaderTitle) {
                                        paymentLoaderTitle.textContent = '{{ addslashes(__('payment.processing_payment')) }}';
                                    }
                                } catch (err) { /* ignore */ }
                            }

                            // Attach to main and sticky checkout buttons (capture to run before Livewire)
                            checkoutBtn.addEventListener('click', handleCheckoutClick, true);
                            var sticky = document.getElementById('sticky-checkout-button');
                            if (sticky) sticky.addEventListener('click', handleCheckoutClick, true);
                        }

                        if (window.Livewire) {
                            Livewire.on('checkout-success', function(payload){
                                try{
                                    whenMinLoaderElapsed(function(){
                                        update('success');
                                        // aguardar confirmação e então redirecionar/ocultar
                                        setTimeout(function(){
                                            if (payload && payload.redirect_url) {
                                                window.location.href = payload.redirect_url;
                                            } else {
                                                hide();
                                            }
                                        }, CONFIRMATION_MS);
                                    });
                                }catch(e){ hide(); }
                            });

                            Livewire.on('checkout-failed', function(payload){ 
                                try{
                                    whenMinLoaderElapsed(function(){
                                        title.textContent = (payload && payload.message) ? payload.message : strings.failed[lang];
                                        // após mensagem de erro, redireciona se informado ou esconde
                                        setTimeout(function(){
                                            if (payload && payload.redirect_url) {
                                                window.location.href = payload.redirect_url;
                                            } else {
                                                hide();
                                            }
                                        }, CONFIRMATION_MS);
                                    });
                                }catch(e){ hide(); }
                            });
                        }
                    };
                    if (document.readyState === 'complete' || document.readyState === 'interactive') formReady(); else document.addEventListener('DOMContentLoaded', formReady);
                })();
            </script>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                <!-- Coluna de Pagamento e Resumo do Pedido -->
                <div class="flex flex-col order-1 md:order-1">
                    <!-- Benefits -->
                    <div class=" bg-[#1F1F1F] rounded-xl p-6 mb-6">
                        <h2 class="text-xl font-semibold text-white mb-4">{{ __('checkout.benefits_title') }}</h2>
                        <div class="space-y-4" id="benefits-container">
                            @foreach (__('checkout.benefits') as $benefit)
                                <div class="flex items-start space-x-3">
                                    <div class="p-2 bg-[#E50914] rounded-lg mt-1">
                                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-white">{{ $benefit['title'] }}</h3>
                                        <p class="text-sm text-gray-400">{{ $benefit['text'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div x-data="{ selectedPaymentMethod: @entangle('selectedPaymentMethod') }" id="payment-method-section"
                        class=" bg-[#1F1F1F] rounded-xl p-6 mb-6 scroll-mt-8">
                        <h2 class="text-xl font-semibold text-white mb-4">{{ __('payment.payment_method') }}</h2>
                        @php
                            $pushinSupported = $plans[$selectedPlan]['gateways']['pushinpay']['supported'] ?? false;
                        @endphp
                        <div class="space-y-4">
                            <div class="flex items-center justify-start p-2 rounded-lg border border-gray-700">
                                <span
                                    class="text-xs font-semibold text-gray-400 uppercase mr-4">{{ __('payment.safe_environment') }}</span>
                                <div class="flex items-center" bis_skin_checked="1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        fill="currentColor" class="text-green-500 mr-1 bi bi-lock" viewBox="0 0 16 16">
                                        <path
                                            d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z">
                                        </path>
                                    </svg>
                                    <span class="text-xs text-gray-400">{{ __('payment.your_data_is_safe') }}</span>
                                </div>
                            </div>

                            <!-- Payment Method Selector -->
                            <div class="grid grid-cols-1 md:grid-cols-1 gap-4 items-stretch">
                                <!-- Credit Card Option -->
                                <div class="relative">
                                    <input type="radio" id="method_credit_card" name="payment_method" value="credit_card"
                                        wire:model="selectedPaymentMethod"
                                        x-model="selectedPaymentMethod"
                                        wire:click="$set('selectedPaymentMethod', 'credit_card')"
                                        x-on:change="selectedPaymentMethod = 'credit_card'; $nextTick(()=>{ const el = document.querySelector('input[name=card_name]'); if(el) el.focus(); })"
                                        class="hidden"
                                        :checked="selectedPaymentMethod === 'credit_card'" />
                                    <label for="method_credit_card" 
                                        wire:click="$set('selectedPaymentMethod', 'credit_card')"
                                        role="button"
                                        tabindex="0"
                                        :class="selectedPaymentMethod === 'credit_card' 
                                            ? 'block relative cursor-pointer p-4 rounded-xl border-2 h-28 flex items-center payment-option border-[#E50914] bg-gradient-to-r from-[#2b2b2b] to-[#161616]'
                                            : 'block relative cursor-pointer p-4 rounded-xl border-2 h-28 flex items-center payment-option border-gray-700 bg-[#0f0f10] hover:border-gray-600'">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center gap-4">
                                                <div class="icon-box p-2 rounded-md bg-gradient-to-br from-[#111827] to-[#0b1220] ring-1 ring-[#7C3AED]/20 flex items-center justify-center">
                                                    <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                                                        <rect x="3.5" y="8.5" width="6" height="2" rx="0.5" fill="currentColor" opacity="0.9" />
                                                    </svg>
                                                </div>
                                                <div class="flex flex-col justify-center items-start text-left">
                                                    <div class="flex items-center gap-3">
                                                        <p class="text-white font-semibold text-left">{{ __('payment.credit_card') }}</p>
                                                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-white/6 text-green-300">{{ __('payment.instant_confirmation') }}</span>
                                                    </div>
                                                    <p class="text-gray-400 text-sm leading-tight">&nbsp;</p>
                                                </div>
                                            </div>
                                            <div class="select-indicator flex items-center justify-center rounded-full border-2"
                                                :class="selectedPaymentMethod === 'credit_card' ? 'border-[#E50914] bg-[#E50914]' : 'border-gray-600'">
                                                <svg x-show="selectedPaymentMethod === 'credit_card'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <!-- PIX Option (only for Brasil) -->
                                @if ($selectedLanguage === 'br')
                                    <div class="relative">
                                        <input type="radio" id="method_pix" name="payment_method" value="pix"
                                            wire:model="selectedPaymentMethod"
                                                x-model="selectedPaymentMethod"
                                                wire:click="$set('selectedPaymentMethod', 'pix')"
                                            x-on:change="selectedPaymentMethod = 'pix'; $nextTick(()=>{ const el = document.querySelector('input[name=pix_name]'); if(el) el.focus(); })"
                                            class="hidden"
                                            :checked="selectedPaymentMethod === 'pix'" />
                                        <label for="method_pix" 
                                            wire:click="$set('selectedPaymentMethod', 'pix')"
                                                role="button"
                                                tabindex="0"
                                            :class="selectedPaymentMethod === 'pix'
                                                ? 'block relative cursor-pointer p-4 rounded-xl border-2 h-28 flex items-center border-green-400 bg-gradient-to-r from-[#0f1f12] to-[#07120a]'
                                                : 'block relative cursor-pointer p-4 rounded-xl border-2 h-28 flex items-center border-gray-700 bg-[#0f0f10] hover:border-gray-600'">
                                            <div class="flex items-center justify-between w-full">
                                                    <div class="flex items-center gap-4">
                                                        <div class="icon-box text-2xl p-2 rounded-md bg-gradient-to-br from-[#061212] to-[#06231a] ring-1 ring-green-400/20 flex items-center justify-center">
                                                        <!-- QR / PIX Icon -->
                                                        <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                            <rect x="3" y="3" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.5" />
                                                            <rect x="17" y="3" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.5" />
                                                            <rect x="3" y="17" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.5" />
                                                            <path d="M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" fill="currentColor" opacity="0.9" />
                                                        </svg>
                                                    </div>
                                                    @php
                                                        $orig = $totals['total_price'] ?? null;
                                                        $final = $totals['final_price'] ?? null;
                                                        $origFloat = null;
                                                        $finalFloat = null;
                                                        if ($orig) {
                                                            $origFloat = floatval(str_replace(',', '.', str_replace('.', '', $orig)));
                                                        }
                                                        if ($final) {
                                                            $finalFloat = floatval(str_replace(',', '.', str_replace('.', '', $final)));
                                                        }
                                                        $discountPercent = null;
                                                        if ($origFloat && $finalFloat && $origFloat > $finalFloat) {
                                                            $discountPercent = round((($origFloat - $finalFloat) / $origFloat) * 100);
                                                        }
                                                    @endphp
                                                    <div class="flex flex-col justify-center items-start text-left">
                                                        <div class="flex items-center gap-3">
                                                            <p class="text-white font-semibold text-left">{{ __('payment.pix_title') }}</p>
                                                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-white/6 text-green-300">{{ __('payment.instant_confirmation') }}</span>
                                                            @if($discountPercent)
                                                                <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full bg-gradient-to-r from-green-500 to-emerald-400 text-white">{{ $discountPercent }}% OFF</span>
                                                            @endif
                                                        </div>
                                                        <p class="text-gray-400 text-sm leading-tight">
                                                            @if($discountPercent && $final)
                                                                <span class="text-gray-400 line-through mr-2">{{ $orig }}</span>
                                                                <span class="text-white font-semibold">{{ $final }}</span>
                                                            @else
                                                                {{ __('payment.pix_description') ?? 'Instant transfer' }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-center w-7 h-7 rounded-full border-2"
                                                    :class="selectedPaymentMethod === 'pix' ? 'border-green-400 bg-green-400' : 'border-gray-600'">
                                                    <svg x-show="selectedPaymentMethod === 'pix'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                @endif
                            </div>

                            <!-- Credit Card Form -->
                            <div x-show="selectedPaymentMethod === 'credit_card'" id="card-form-section">
                                <div class="space-y-4 mt-4">
                                    @if($cardValidationError)
                                        <div class="bg-red-500/10 border border-red-500 rounded-lg p-3 mb-4">
                                            <p class="text-red-500 text-sm font-medium text-center">{{ $cardValidationError }}</p>
                                        </div>
                                    @endif
                                    @if ($gateway !== 'stripe')
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_number') }} @if(isset($fieldErrors['cardNumber']))<span class="text-red-500">*</span>@endif</label>
                                            <input name="card_number" type="text" id="card-number"
                                                placeholder="0000 0000 0000 0000"
                                                wire:model.defer="cardNumber"
                                                inputmode="numeric" autocomplete="cc-number" pattern="[0-9\s]{13,19}" maxlength="19"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['cardNumber'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                            @if(isset($fieldErrors['cardNumber']))
                                                <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['cardNumber'] }}</span>
                                            @endif
                                            @error('cardNumber')
                                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.expiry_date') }} @if(isset($fieldErrors['cardExpiry']))<span class="text-red-500">*</span>@endif</label>
                                                <input name="card_expiry" type="text" id="card-expiry"
                                                    placeholder="MM/YY" wire:model.defer="cardExpiry"
                                                    inputmode="numeric" autocomplete="cc-exp" pattern="(0[1-9]|1[0-2])\/([0-9]{2})" maxlength="5"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['cardExpiry'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                                @if(isset($fieldErrors['cardExpiry']))
                                                    <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['cardExpiry'] }}</span>
                                                @endif
                                                @error('cardExpiry')
                                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.security_code') }} @if(isset($fieldErrors['cardCvv']))<span class="text-red-500">*</span>@endif</label>
                                                <input name="card_cvv" type="text" id="card-cvv" placeholder="CVV"
                                                    wire:model.defer="cardCvv" inputmode="numeric" autocomplete="cc-csc" pattern="[0-9]{3,4}" maxlength="4"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['cardCvv'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                                @if(isset($fieldErrors['cardCvv']))
                                                    <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['cardCvv'] }}</span>
                                                @endif
                                                @error('cardCvv')
                                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    @else
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_number') }}</label>
                                            <div id="card-element"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all"
                                                wire:ignore></div>
                                            <div id="card-errors"></div>
                                            <input name="payment_method_id" type="hidden"
                                                wire:model.defer="paymentMethodId" id="payment-method-id">
                                        </div>
                                    @endif

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_name') }} @if(isset($fieldErrors['cardName']))<span class="text-red-500">*</span>@endif</label>
                                        <input name="card_name" type="text"
                                            placeholder="{{ __('payment.card_name') }}" wire:model.live="cardName"
                                            autocomplete="cc-name" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['cardName'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['cardName']))
                                            <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['cardName'] }}</span>
                                        @endif
                                        @error('cardName')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_field_email_label') }}: @if(isset($fieldErrors['email']))<span class="text-red-500">*</span>@endif</label>
                                        <input name="email" type="email" placeholder="{{ __('payment.card_field_email_hint') }}"
                                            wire:model.live.debounce.500ms="email"
                                            autocomplete="email" inputmode="email" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['email'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['email']))
                                            <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['email'] }}</span>
                                        @else
                                            <div id="email-suggestion" class="text-xs mt-1">
                                                @if(isset($emailCheckStatus) && $emailCheckStatus === 'checking')
                                                    <span class="text-yellow-400">{{ $emailCheckMessage ?? 'Verificando...' }}</span>
                                                @elseif(isset($emailCheckStatus) && $emailCheckStatus === 'exists')
                                                    <span class="text-red-500">{{ $emailCheckMessage ?? 'Usuário já existe' }}</span>
                                                @elseif(isset($emailCheckStatus) && $emailCheckStatus === 'not_found')
                                                    <span class="text-green-400">{{ $emailCheckMessage ?? 'E-mail disponível' }}</span>
                                                @elseif(isset($emailCheckStatus) && $emailCheckStatus === 'error')
                                                    <span class="text-gray-400">{{ $emailCheckMessage ?? 'Erro ao verificar e-mail' }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @error('email')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_field_phone_label') }} ({{ __('payment.optional') }}):</label>
                                        <input name="phone" id="phone" type="tel" placeholder="{{ __('payment.card_field_phone_hint') }}"
                                            wire:model="phone" inputmode="tel" autocomplete="tel"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['phone'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['phone']))
                                            <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['phone'] }}</span>
                                        @endif
                                        @error('phone')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    @if ($selectedLanguage === 'br')
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-300 mb-1">CPF</label>
                                            <input name="cpf" type="text"
                                                placeholder="000.000.000-00" wire:model.live="cpf" inputmode="numeric" autocomplete="off"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 @if(isset($fieldErrors['cpf'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                            @if(isset($fieldErrors['cpf']))
                                                <span class="text-red-500 text-xs mt-1 block">{{ $fieldErrors['cpf'] }}</span>
                                            @endif
                                            @error('cpf')
                                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- PIX Form -->
                            <div x-show="selectedPaymentMethod === 'pix'" id="pix-form-section">
                                <div class="space-y-4 mt-4">
                                    @if($pixValidationError)
                                        <div class="bg-red-500/10 border border-red-500 rounded-lg p-3 mb-4">
                                            <p class="text-red-500 text-sm font-medium text-center">{{ $pixValidationError }}</p>
                                        </div>
                                    @endif
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.pix_field_name_label') }}</label>
                                        <input name="pix_name" type="text" placeholder="{{ __('payment.pix_field_name_hint') }}"
                                            wire:model.defer="pixName" autocomplete="name" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500 transition-all" />
                                        @error('pixName')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.pix_field_email_label') }}:</label>
                                        <input name="pix_email" type="email" placeholder="{{ __('payment.pix_field_email_hint') }}"
                                            wire:model.live.debounce.500ms="pixEmail" autocomplete="email" inputmode="email" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500 transition-all" />
                                        <div id="pix-email-suggestion" class="text-xs text-yellow-400 mt-1"></div>
                                        @error('pixEmail')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.pix_field_cpf_label') }}</label>
                                        <input name="pix_cpf" type="text" placeholder="000.000.000-00"
                                            wire:model.defer="pixCpf" inputmode="numeric" autocomplete="off"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500 transition-all" />
                                        @error('pixCpf')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.pix_field_phone_label') }} ({{ __('payment.optional') }}):</label>
                                        <input name="pix_phone" id="pix_phone" type="tel" placeholder="{{ __('payment.pix_field_phone_hint') }}"
                                            wire:model="pixPhone" inputmode="tel" autocomplete="tel"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500 transition-all" />
                                        @error('pixPhone')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <button type="button" onclick="startClientPixFlow(event)"
                                        class="w-full bg-green-500 hover:bg-green-600 text-white py-3 px-4 text-base font-semibold rounded-lg shadow-lg transition-all flex items-center justify-center gap-2 mt-4 cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        {{ __('payment.generate_pix') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Bumps - Design diferente para Card vs PIX -->
                    @php
                        $pushinSupported = $plans[$selectedPlan]['gateways']['pushinpay']['supported'] ?? false;
                    @endphp
                    @if (!empty($bumps) && !($selectedPaymentMethod === 'pix' && $pushinSupported === false))
                        <div class="space-y-3">
                            @if($selectedPaymentMethod === 'pix')
                                <h3 class="text-white font-semibold text-sm mb-4">✨ {{ __('payment.add_ons_title') }}</h3>
                            @else
                                <h3 class="text-white font-semibold text-sm mb-4">{{ __('payment.add_ons_title') }}</h3>
                            @endif
                            
                            @foreach ($bumps as $index => $bump)
                                @php
                                    $langCode = match($selectedLanguage) {
                                        'en' => 'title_en',
                                        'es' => 'title_es',
                                        default => 'title'
                                    };
                                    $descLangCode = match($selectedLanguage) {
                                        'en' => 'description_en',
                                        'es' => 'description_es',
                                        default => 'description'
                                    };
                                    $bumpTitle = $bump[$langCode] ?? $bump['title'] ?? '';
                                    $bumpDesc = $bump[$descLangCode] ?? $bump['description'] ?? '';
                                    $isRecommended = $bump['recommended'] ?? false;
                                    $badge = $bump['badge'] ?? null;
                                    $badgeColor = $bump['badge_color'] ?? 'red';
                                    $socialProof = $bump['social_proof_count'] ?? null;
                                    $urgencyText = $bump['urgency_text'] ?? null;
                                    $originalPrice = $bump['original_price'] ?? null;
                                    $discountPct = $bump['discount_percentage'] ?? null;
                                    $price = $bump['price'] ?? $bump['original_price'] ?? $bump['amount'] ?? 0;
                                    $icon = $bump['icon'] ?? 'lock';
                                @endphp
                                
                                @if($selectedPaymentMethod === 'pix')
                                    <!-- PIX Design: Minimalista e compacto -->
                                    <div class="bump-card-pix relative bg-gradient-to-r from-blue-600/20 to-blue-500/10 rounded-lg p-3 border border-blue-500/30 hover:border-blue-400/60 transition-all duration-300 cursor-pointer flex items-center justify-between"
                                        @click="document.getElementById('order-bump-{{ $bump['id'] }}').click()">
                                        
                                        <div class="flex items-center gap-3 flex-1">
                                            <input id="order-bump-{{ $bump['id'] }}" type="checkbox"
                                                class="w-5 h-5 text-blue-500 bg-blue-900/30 border-blue-400 rounded
                                                focus:ring-blue-500 focus:ring-opacity-25 focus:ring-2
                                                focus:border-blue-500 cursor-pointer flex-shrink-0"
                                                wire:model="bumps.{{ $index }}.active" wire:change="calculateTotals" />
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-blue-300 font-semibold text-sm truncate">{{ $bumpTitle }}</span>
                                                    @if($badge)
                                                        <span class="text-xs px-2 py-0.5 bg-blue-500 text-white rounded whitespace-nowrap">{{ $badge }}</span>
                                                    @endif
                                                </div>
                                                <p class="text-gray-400 text-xs bump-desc">{{ $bumpDesc }}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right ml-2 flex-shrink-0">
                                            @if($discountPct)
                                                <div class="text-green-400 text-xs font-bold mb-1">-{{ $discountPct }}%</div>
                                            @endif
                                            <span class="font-bold text-sm {{ isset($dataOrigin) && $dataOrigin['bumps'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback' : 'text-blue-400') }}">
                                                +{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <!-- Card Design: Completo e detalhado -->
                                    <div class="bump-card group relative bg-gradient-to-br from-[#2D2D2D] to-[#1F1F1F] rounded-lg p-4 border border-gray-700 hover:border-[#E50914] transition-all duration-300 cursor-pointer"
                                        @click="document.getElementById('order-bump-{{ $bump['id'] }}').click()">
                                    
                                    <!-- Badge (se tiver) -->
                                    @if($badge)
                                        <div class="absolute -top-3 -right-3">
                                            <span class="inline-block px-3 py-1 text-xs font-bold text-white rounded-full
                                                @if($badgeColor === 'gold') bg-yellow-500 
                                                @elseif($badgeColor === 'blue') bg-blue-600 
                                                @else bg-[#E50914] @endif">
                                                {{ $badge }}
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Recomendado Badge -->
                                    @if($isRecommended)
                                        <div class="absolute -top-3 -left-3">
                                            <span class="inline-block px-2 py-1 text-xs font-bold text-white bg-green-600 rounded-full">
                                                ⭐ {{ __('payment.recommended') }}
                                            </span>
                                        </div>
                                    @endif
                                    
                                    <!-- Content -->
                                    <div class="flex gap-4">
                                        <!-- Checkbox -->
                                        <div class="flex items-start pt-1">
                                            <input id="order-bump-{{ $bump['id'] }}" type="checkbox"
                                                class="w-5 h-5 text-[#E50914] bg-[#2D2D2D] border-gray-600 rounded
                                                focus:ring-[#E50914] focus:ring-opacity-25 focus:ring-2
                                                focus:border-[#E50914] cursor-pointer"
                                                wire:model="bumps.{{ $index }}.active" wire:change="calculateTotals"
                                                @if($isRecommended) checked @endif />
                                        </div>
                                        
                                        <!-- Texto -->
                                        <div class="flex-1 min-w-0">
                                            <!-- Título com Ícone -->
                                            <div class="flex items-center gap-2 mb-1">
                                                @php
                                                    $iconSvg = match($icon) {
                                                        'video' => '<svg class="w-4 h-4 text-[#E50914]" fill="currentColor" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>',
                                                        'book' => '<svg class="w-4 h-4 text-[#E50914]" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H6V4h12v16z"/></svg>',
                                                        'star' => '<svg class="w-4 h-4 text-[#E50914]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2l-2.81 6.63L2 9.24l5.46 4.73L5.82 21z"/></svg>',
                                                        default => '<svg class="w-4 h-4 text-[#E50914]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>',
                                                    };
                                                @endphp
                                                <div class="text-white font-semibold text-sm">{{ $bumpTitle }}</div>
                                            </div>
                                            
                                            <!-- Descrição -->
                                            <p class="text-gray-400 text-xs mb-2 line-clamp-2">{{ $bumpDesc }}</p>
                                            
                                            <!-- Prova Social (se tiver) -->
                                            @if($socialProof)
                                                <div class="text-yellow-500 text-xs font-semibold mb-2">
                                                    ⭐⭐⭐⭐⭐ {{ number_format($socialProof, 0, ',', '.') }}+ {{ __('payment.people_bought') }}
                                                </div>
                                            @endif
                                            
                                            <!-- Urgência (se tiver) -->
                                            @if($urgencyText)
                                                <div class="text-red-400 text-xs font-semibold mb-2 flex items-center gap-1">
                                                    ⚡ {{ $urgencyText }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Preço -->
                                    <div class="mt-3 pt-3 border-t border-gray-700">
                                        @if($originalPrice)
                                            <div class="flex items-center gap-2 justify-between">
                                                <div>
                                                    <span class="text-gray-500 text-xs line-through">
                                                        {{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($originalPrice, 2, ',', '.') }}
                                                    </span>
                                                    @if($discountPct)
                                                        <span class="ml-2 text-green-400 text-xs font-bold">-{{ $discountPct }}%</span>
                                                    @endif
                                                </div>
                                                <span class="font-bold text-sm {{ isset($dataOrigin) && $dataOrigin['bumps'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback' : 'text-[#E50914]') }}">
                                                    {{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="text-right">
                                                <span class="font-bold text-sm {{ isset($dataOrigin) && $dataOrigin['bumps'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback' : 'text-[#E50914]') }}">
                                                    +{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <!-- Coluna de Resumo do Pedido -->
                <div class="md:col-span-1 order-2 md:order-2">
                    <!-- Card de resumo de cartão - oculto quando PIX é selecionado -->
                    <div class="bg-[#1F1F1F] rounded-xl p-6 sticky top-6" wire:poll.1s="decrementTimer"
                        wire:poll.15000ms="decrementSpotsLeft" wire:poll.8000ms="updateLiveActivity">
                        <h2 class="text-xl font-semibold text-white mb-4 text-center">
                            {{ __('checkout.order_summary_title') }}</h2>

                        <!-- Conteúdo de Cartão - mostrado apenas quando Cartão estiver selecionado -->
                        @if($selectedPaymentMethod === 'credit_card')

                        <!-- Timer -->
                        <div
                            class="bg-gray-800 border border-red-600 rounded-lg p-3 mb-6 flex items-center justify-center animate-pulse">
                            <svg class="w-6 h-6 text-red-600 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-white font-medium">{{ __('checkout.offer_expires_in') }} <span
                                    id="countdown-timer"
                                    class="font-bold text-red-500 tracking-wider">{{ sprintf('%02d:%02d', $countdownMinutes, $countdownSeconds) }}</span></span>
                        </div>

                        <!-- Plan selection -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-300 mb-2">{{ __('payment.select_plan') }}</label>

                            <div class="relative">
                                <select id="plan-selector" name="plan"
                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 appearance-none pr-10 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all cursor-pointer"
                                    wire:model="selectedPlan" wire:change="calculateTotals">
                                    @foreach ($plans as $value => $plan)
                                        <option value="{{ $value }}">{{ $plan['label'] }}</option>
                                    @endforeach
                                </select>
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white">
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                        <path d="M7 7l3-3 3 3m0 6l-3 3-3-3" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                                    </svg>
                                </div>
                            </div>

                            <p class="text-gray-400 text-sm mt-2">{{ __('payment.flexible_plan') }}</p>
                        </div>

                        <!-- Price anchor -->
                        <div class="mb-2 text-center">
                                        <del class="text-gray-400 text-sm">{{ $currencies[$selectedCurrency]['symbol'] }}
                                            {{ number_format($totals['month_price'] ?? 0, 2, ',', '.') }}</del>
                                        <span class="text-lg font-bold ml-2 {{ (isset($dataOrigin) && $dataOrigin['totals'] === 'backend') ? 'text-green-400 price-backend' : ((isset($dataOrigin) && ($selectedPaymentMethod === 'credit_card')) ? 'price-fallback animate-fallback-pulse' : 'text-green-400') }}"
                                            id="current-price">{{ $currencies[$selectedCurrency]['symbol'] }}
                                            {{ number_format($totals['month_price_discount'] ?? 0, 2, ',', '.') }} {{ __('payment.per_month') }}</span>
                                    </div>

                        {{-- Debug temporário removido para evitar exposição no frontend. --}}

                        <!-- Price breakdown -->
                        <div class="border-t border-gray-700 pt-5 my-4 space-t-2">
                            <!-- Coupon area -->
                            {{-- <div>
                                <div class="flex space-x-2">
                                    <input name="coupon_code" type="text" id="coupon-input"
                                        placeholder="{{ __('payment.coupon_code') }}"
                        class="w-2/3 bg-[#2D2D2D] text-white rounded-lg p-2 text-sm border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                        <button type="button" id="apply-coupon"
                            class="w-1/3 bg-gray-700 hover:bg-gray-600 text-white text-sm py-2 px-2 rounded-lg transition-all">
                            {{ __('payment.apply') }}
                        </button>
                    </div>
                    <div id="coupon-message" class="text-xs mt-1 text-green-400 hidden">
                        {{ __('payment.coupon_success') }}
                    </div>
                </div> --}}
                        </div>


                        <!-- Banner Promocional PIX Mock -->
                        @if(isset($usingPixMock) && $usingPixMock)
                        <div class="mb-6 p-4 bg-gradient-to-r from-green-900 via-green-800 to-green-900 rounded-lg border border-green-600 text-white shadow-lg">
                            <div class="flex items-start space-x-3">
                                <svg class="w-6 h-6 text-green-300 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-lg font-bold text-green-100">{{ __('payment.mock_offer_title') }}</p>
                                    <p class="text-sm text-green-200 mt-1">{{ __('payment.mock_offer_subtitle') }}</p>
                                    <div class="mt-3 flex items-center space-x-2">
                                        <del class="text-green-300 opacity-75 text-sm">{{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['total_price'] ?? 0, 2, ',', '.') }}</del>
                                        <span class="text-2xl font-bold text-white">{{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['final_price'] ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <p class="text-xs text-green-200 mt-2">{{ $selectedLanguage === 'br' ? __('payment.mock_savings_text') : str_replace('R$', $currencies[$selectedCurrency]['symbol'], __('payment.mock_savings_text')) }} {{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['total_discount'] ?? 0, 2, ',', '.') }}!</p>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Resumo Final com Descontos -->
                        <div id="priceSummary" class="mb-6 p-4 bg-gray-800 rounded-lg text-white">
                            <p class="text-base font-semibold mb-2">{{ __('payment.final_summary') }}</p>
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ __('payment.original_price') }}</span>
                                <del class="text-gray-400">{{ $currencies[$selectedCurrency]['symbol'] }}
                                    {{ number_format($totals['total_price'] ?? 0, 2, ',', '.') }}</del>
                            </div>
                            <div class="flex justify-between text-mb mb-1">
                                <span>{{ __('payment.discount') }}</span>
                                <span class="text-green-400"
                                    id="discount-amount">{{ $currencies[$selectedCurrency]['symbol'] }}
                                    {{ number_format($totals['total_discount'] ?? 0, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold mt-2 pt-2 border-t border-gray-700">
                                <span>{{ __('payment.total_to_pay') }}</span>
                                <span id="final-price" class="{{ isset($dataOrigin) && $dataOrigin['totals'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback animate-fallback-pulse' : '') }}">
                                    {{ $currencies[$selectedCurrency]['symbol'] }}
                                    {{ number_format($totals['final_price'] ?? 0, 2, ',', '.') }}</span>
                                <input type="hidden" id="input-final-price" name="final-price" value="{{ isset($totals['final_price']) ? str_replace(',', '.', str_replace('.', '', $totals['final_price'])) : (float)0 }}" />
                            </div>
                        </div>

                        <!-- Limited spots -->
                        <div class="bg-[#2D2D2D] rounded-lg p-3 mb-4 text-center">
                            <span class="font-medium">{{ __('checkout.remaining_vacancies') }}: <strong><span
                                        id="spots-left">{{ $spotsLeft }}</span></strong></span>
                        </div>

                        <!-- Live Activity Indicator -->
                        <div class="bg-[#2D2D2D] rounded-lg p-3 mb-6 text-center">
                            <span class="text-gray-400">{!! __('checkout.live_activity', [
                                'count' => '<strong id="activityCounter" class="text-white">' . $activityCount . '</strong>',
                            ]) !!}</span>
                        </div>

                        <!-- Verificação de Ambiente Seguro -->
                        <div id="seguranca"
                            class="w-full bg-gray-800 p-4 rounded-lg flex items-center gap-3 text-sm text-gray-300 animate-pulse mb-4 @if (!$showSecure) hidden @endif">
                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M2.003 5.884L10 2l7.997 3.884v4.632c0 5.522-3.936 10.74-7.997 11.484-4.061-.744-7.997-5.962-7.997-11.484V5.884z" />
                            </svg>
                            {{ __('payment.checking_secure') }}
                        </div>

                        <button id="checkout-button" type="button" wire:click.prevent="startCheckout"
                            @if($isProcessingCard) disabled aria-busy="true" @endif
                            class="w-full bg-[#E50914] hover:bg-[#B8070F] text-white py-3 text-lg font-bold rounded-xl transition-all block cursor-pointer transform hover:scale-105 @if($isProcessingCard) opacity-70 cursor-not-allowed hover:scale-100 @endif">
                            @if($isProcessingCard)
                                <span class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span>{{ $loadingMessage ?? __('payment.processing_payment') }}</span>
                                </span>
                            @else
                                {{ __('checkout.cta_button') }}
                            @endif
                        </button>

                        <!-- Trust badges -->
                        <div class="mt-4 flex flex-col items-center space-y-2">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span class="text-sm text-gray-300">{{ __('payment.7_day_guarantee') }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <span class="text-sm text-gray-300">{{ __('payment.secure_ssl') }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2-8.83"></path></svg>
                                <span class="text-sm text-gray-300">{{ __('payment.easy_cancel') }}</span>
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <div class="flex items-center justify-center space-x-2 mb-2">
                                <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span class="text-sm text-gray-300">{{ __('payment.anonymous') }}</span>
                            </div>

                            <div class="flex justify-center space-x-3 text-xs text-gray-500">
                                <a href="#"
                                    class="hover:text-gray-300 transition-colors">{{ __('payment.terms') }}</a>
                                <a href="#"
                                    class="hover:text-gray-300 transition-colors">{{ __('payment.privacy') }}</a>
                                <a href="#"
                                    class="hover:text-gray-300 transition-colors">{{ __('payment.support') }}</a>
                            </div>
                        </div>
                        @endif
                        <!-- ========== FIM CONTEÚDO CARTÃO ========== -->

                        <!-- ========== RESUMO PIX - Aparece quando PIX é selecionado ========== -->
                        @if($selectedPaymentMethod === 'pix')
                        <div class="mt-8 pt-8 border-t border-gray-700" wire:poll.15000ms="decrementSpotsLeft">
                            <!-- Product Image - Topo -->
                            <div class="mb-6 rounded-lg overflow-hidden bg-gray-800/50">
                                @if(!empty($product['image']))
                                    <img src="{{ $product['image'] }}" alt="Produto" class="w-full h-auto object-cover max-h-80 md:max-h-96">
                                @else
                                    <div class="w-full h-64 md:h-80 bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                                        <svg class="w-24 h-24 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            <h2 class="text-xl font-semibold text-white mb-6 text-center">Confira as informações antes de concluir:</h2>

                            <!-- Product Info -->
                            <div class="bg-gray-800/50 rounded-lg p-4 mb-4">
                                <p class="text-gray-300 text-xs font-semibold mb-2">PRODUTO</p>
                                <p class="text-white font-bold text-lg">{{ str_replace(['1/month', '1 mês', '1/mes'], '1x/mês', $product['title'] ?? 'Streaming Snaphubb - 1x/mês') }}</p>
                            </div>

                            <!-- Price Breakdown -->
                            <div class="bg-gray-800/50 rounded-lg p-4 mb-4 space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-300 text-sm">Preço Original:</span>
                                    <span class="text-gray-400 line-through text-sm">R$ 49,90</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-green-400 text-sm font-semibold">Desconto PIX:</span>
                                    <span class="text-green-400 font-bold text-sm">– R$ 25,00</span>
                                </div>
                                <div class="border-t border-gray-700 pt-3 flex justify-between items-center">
                                    <span class="text-white font-bold text-base">Total a Pagar:</span>
                                    <span class="font-bold text-2xl {{ (isset($dataOrigin) && $dataOrigin['totals'] === 'backend') ? 'price-backend' : ((isset($dataOrigin) && ($selectedPaymentMethod === 'credit_card')) ? 'price-fallback animate-fallback-pulse' : 'text-green-400') }}">
                                        {{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['final_price'] ?? 0, 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="bg-green-500/10 border border-green-500/40 rounded p-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></path></svg>
                                    <span class="text-green-300 text-xs font-semibold">Você economiza: R$ 25,00 hoje!</span>
                                </div>
                            </div>

                            <!-- Timing Info -->
                            <div class="space-y-2 mb-4 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                    <span>A oferta via PIX expira em: <strong>5 minutos</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span>O código PIX expira em <strong>10 minutos</strong></span>
                                </div>
                            </div>

                            <!-- Benefits -->
                            <div class="space-y-2 mb-4 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                    <span>Acesso liberado imediatamente após o pagamento</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2-8.83"></path></svg>
                                    <span>Plano mensal, cancele quando quiser</span>
                                </div>
                            </div>

                            <!-- Social Proof -->
                            <div class="bg-gray-800/50 rounded-lg p-4 mb-4 space-y-2 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                    <span id="buying-count"><strong>{{ rand(12, 47) }}</strong> pessoas estão comprando agora</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                    <span id="slots-count">Vagas restantes: <strong>{{ $spotsLeft }}</strong></span>
                                </div>
                            </div>

                            <!-- Trust Badges PIX -->
                            <div class="grid grid-cols-2 gap-2 mb-6 text-xs text-center">
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-500 mx-auto mb-1" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                    <p class="text-gray-300">Garantia de 7 dias</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-500 mx-auto mb-1 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                    <p class="text-gray-300">Pagamento seguro SSL</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-500 mx-auto mb-1 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15" stroke="white" stroke-width="2"></line><line x1="9" y1="9" x2="15" y2="15" stroke="white" stroke-width="2"></line></svg>
                                    <p class="text-gray-300">Cancelamento fácil</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-500 mx-auto mb-1" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <p class="text-gray-300">Compra anônima e segura</p>
                                </div>
                            </div>

                            <!-- CTA Button PIX -->
                            <button type="button" onclick="startClientPixFlow(event)"
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-4 text-lg font-bold rounded-xl transition-all block cursor-pointer transform hover:scale-105 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Gerar Código PIX Agora
                            </button>

                            <p class="text-gray-400 text-xs text-center mt-3">Clique acima para gerar seu código PIX e escaneie com seu banco</p>
                        </div>
                        @endif
                        <!-- ========== FIM RESUMO PIX ========== -->
                    </div>
                </div>

                <!-- Coluna de Depoimentos (Aparece por último no mobile) -->
                <div class="md:col-span-2 order-3">
                    <div class="bg-transparent rounded-xl">
                        <h2 class="text-2xl font-bold text-white mb-6 text-center">
                            {{ __('checkout.testimonials_title') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if (is_array($testimonials) && !empty($testimonials))
                                @foreach ($testimonials as $index => $testimonial)
                                    @php
                                        // Gera dias de forma dinâmica mas consistente baseado no índice e data atual
                                        // Isso faz com que os dias mudem a cada 1-2 dias de forma realista
                                        $dayOfYear = (int)date('z'); // Dia do ano (0-365)
                                        $baseDay = (int)($dayOfYear / 2); // Muda a cada 2 dias
                                        $seed = $baseDay + (int)$index; // Seed único por testimonial
                                        
                                        // Gera um número pseudo-aleatório consistente (1-14 dias)
                                        $daysAgo = (int)((($seed * 9301 + 49297) % 233280) % 14 + 1);
                                        
                                        // Formata a mensagem de tempo
                                        if (isset($testimonial['time'])) {
                                            $displayTime = $testimonial['time'];
                                        } elseif ($daysAgo == 1) {
                                            $displayTime = 'há 1 dia';
                                        } elseif ($daysAgo < 7) {
                                            $displayTime = 'há ' . strval($daysAgo) . ' dias';
                                        } elseif ($daysAgo < 14) {
                                            $displayTime = 'há 1 semana';
                                        } else {
                                            $displayTime = 'há 2 semanas';
                                        }
                                    @endphp
                                    <div class="rounded-2xl bg-[#0f172a]/90 border border-gray-700/60 shadow-[0_15px_45px_rgba(0,0,0,0.55)] p-6 flex flex-col gap-5 transition duration-300 hover:-translate-y-1 hover:border-[#7C3AED]">
                                        <div class="flex items-start gap-4">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($testimonial['name']) }}&color=FFFFFF&background=0F1724&bold=true&size=64"
                                                alt="Avatar"
                                                class="w-14 h-14 rounded-full ring-2 ring-[#7C3AED] object-cover flex-shrink-0">
                                            <div class="flex-1">
                            
                                                <h3 class="text-white font-semibold text-lg leading-tight">{{ $testimonial['name'] }}</h3>
                                                @if(!empty($testimonial['location']))
                                                    <p class="text-sm text-gray-400">{{ $testimonial['location'] }}</p>
                                                @endif
                                                <div class="mt-2 flex items-center space-x-1 text-yellow-400">
                                                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.063 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69L9.05 2.927z"/></svg>
                                                    <span class="text-sm text-white/80">4.8</span>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="text-gray-100 text-base leading-relaxed">"{{ $testimonial['quote'] }}"</p>

                                        <div class="flex items-center justify-between text-xs text-gray-400">
                                            <span class="uppercase tracking-widest text-[11px] text-gray-500">Avaliação verificada</span>
                                            <span class="text-gray-300">{{ $displayTime }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

            </div>

<!-- Sticky Summary -->
<div id="sticky-summary" class="sticky-summary bg-[#1F1F1F] border-t border-gray-700 md:hidden p-4 {{ $selectedPaymentMethod === 'pix' ? 'hidden' : '' }}">
    <div class="container mx-auto flex flex-col items-center justify-center gap-2">
        <button type="button" id="sticky-checkout-button"
            wire:click.prevent="startCheckout"
            class="bg-[#E50914] hover:bg-[#B8070F] text-white py-2 px-6 text-base font-semibold rounded-full shadow-lg w-auto min-w-[180px] max-w-xs mx-auto transition-all flex items-center justify-center cursor-pointer">
            <span class="truncate">{{ __('checkout.cta_button') }}</span>
        </button>
    </div>
</div>

</form>

<x-checkout.footer />


<!-- Full Screen Loader -->
<div wire:loading.flex wire:target="isProcessingCard" class="fixed inset-0 bg-black bg-opacity-80 flex flex-col justify-center items-center text-white z-[9999]">
    <svg class="animate-spin h-10 w-10 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V4a10 10 0 00-10 10h2zm8 8a8 8 0 01-8-8H4a10 10 0 0010 10v-2z"></path>
    </svg>
    <p class="text-lg font-semibold">{{ __('payment.loader_processing') }}</p>
    <p class="text-sm mt-2 text-gray-400">{{ __('payment.loader_wait') }}</p>
</div>


<!-- Client-side PIX loader (show 3s before calling Livewire) -->
<div id="client-pix-loader" class="hidden fixed inset-0 bg-black bg-opacity-80 flex-col justify-center items-center text-white z-[10000]">
    <div class="flex flex-col items-center justify-center h-full">
        <svg class="animate-spin h-12 w-12 text-green-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V4a10 10 0 00-10 10h2zm8 8a8 8 0 01-8-8H4a10 10 0 0010 10v-2z"></path>
        </svg>
        <p class="text-lg font-semibold">Gerando código PIX… Aguarde 3 segundos.</p>
        <p class="text-sm text-gray-300 mt-2">Em seguida o QR code será exibido automaticamente.</p>
    </div>
</div>

<script>
    function startClientPixFlow(e){
        if(e && e.preventDefault) e.preventDefault();
        console.log('startClientPixFlow: iniciando...');
        
        // Validar campos antes de mostrar o loader
        const pixName = document.querySelector('input[name="pix_name"]');
        const pixEmail = document.querySelector('input[name="pix_email"]');
        const pixCpf = document.querySelector('input[name="pix_cpf"]');
        
        // Verificar se os campos estão preenchidos
        const isNameValid = pixName && pixName.value.trim().length > 0;
        const isEmailValid = pixEmail && pixEmail.value.trim().length > 0 && /\S+@\S+\.\S+/.test(pixEmail.value);
        const isCpfValid = pixCpf && pixCpf.value.replace(/\D/g, '').length >= 11;
        
        if (!isNameValid || !isEmailValid || !isCpfValid) {
            console.log('Campos não preenchidos, chamando validação do backend...');
            // Chamar diretamente o backend para validar e mostrar mensagem
            if(window.Livewire){
                Livewire.dispatch('clientGeneratePix');
            }
            return;
        }
        
        // Se os campos estão preenchidos, mostrar o loader
        const loader = document.getElementById('client-pix-loader');
        if(!loader) {
            console.error('Loader não encontrado!');
            return;
        }
        loader.classList.remove('hidden');
        loader.style.display = 'flex';
        loader.style.opacity = '0';
        // Fade in suave
        setTimeout(() => {
            loader.style.transition = 'opacity 0.3s ease-in-out';
            loader.style.opacity = '1';
        }, 10);
        console.log('Loader exibido, chamando Livewire imediatamente...');

        // Fallback timeout para esconder loader caso algo dê errado (30s)
        const fallback = setTimeout(function(){
            console.warn('Fallback: escondendo loader após 30s');
            if(loader){ 
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.classList.add('hidden'); 
                    loader.style.display='none';
                }, 300);
            }
        }, 30000);
        // armazenar globalmente para que o listener possa limpar
        window._clientPixFallback = fallback;

        // Chamar Livewire imediatamente para gerar o PIX
        if(window.Livewire){
            Livewire.dispatch('clientGeneratePix');
            console.log('Evento clientGeneratePix disparado');
        } else {
            console.error('Livewire não está disponível!');
        }

    }

    // Listener global para esconder o loader quando o servidor sinalizar que o PIX está pronto
    window.addEventListener('pix-ready', function(event){
        console.log('Evento pix-ready recebido:', event.detail);
        const loader = document.getElementById('client-pix-loader');
        try{ clearTimeout(window._clientPixFallback); }catch(e){}
        if(loader){ 
            // Fade out suave
            loader.style.transition = 'opacity 0.5s ease-out';
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.classList.add('hidden'); 
                loader.style.display='none';
                console.log('Loader escondido após pix-ready');
            }, 500);
        }
    });

    // Listener para scroll até o formulário PIX quando houver erro de validação
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('scroll-to-pix-form', () => {
            const pixForm = document.getElementById('pix-form-section');
            if (pixForm) {
                pixForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Focar no primeiro campo vazio
                setTimeout(() => {
                    const firstInput = pixForm.querySelector('input[name="pix_name"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 500);
            }
        });

        Livewire.on('scroll-to-card-form', () => {
            const cardForm = document.getElementById('card-form-section');
            if (cardForm) {
                cardForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(() => {
                    const firstInput = cardForm.querySelector('input[name="card_name"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 500);
            }
        });

        // Listen for closeModal event to remove blur effect
        Livewire.on('closeModal', () => {
            document.body.classList.remove('pix-modal-open');
            console.log('[PIX Modal] Classe pix-modal-open removida do body');
        });
    });
</script>


</div>



<!-- Upsell Modal -->
<div id="upsell-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showUpsellModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl max-w-md w-full mx-4">
        <div class="p-6">
            <button id="close-upsell" wire:click.prevent="rejectUpsell"
                class="absolute top-3 right-3 text-gray-400 hover:text-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="text-center mb-4">
                <div class="bg-[#E50914] rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white">{{ __('payment.save_more') }}</h3>
                <p class="text-gray-300 mt-2">{{ __('payment.semi_annual_free_months') }}</p>
            </div>

            <div class="bg-[#2D2D2D] rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-300">{{ __('payment.monthly') }} {{ __('payment.current') }}</span>
                    <span class="text-white font-medium" id="upsell-monthly">
                        {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                        {{ $modalData['actual_month_value'] ?? 00 }} {{ __('payment.per_month') }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-white font-medium">
                        {{ __('payment.semi-annual') }}
                    </span>
                    <span class="text-[#E50914] font-bold" id="upsell-annual">
                        {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                        {{ $modalData['offer_month_value'] ?? 00 }}
                        {{ __('payment.per_month') }}
                    </span>
                </div>
                <div class="mt-2 text-green-500 text-sm text-right" id="upsell-savings">
                    {{ __('payment.savings') }}
                    {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                    {{ $modalData['offer_total_discount'] ?? 00 }}
                    {{ __('payment.semi-annual') }}
                </div>
                <div class="mt-2 text-sm text-right" id="upsell-savings">
                    {{ __('payment.total_to_pay') }}
                    {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                    {{ $modalData['offer_total_value'] ?? 00 }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button id="upsell-reject" wire:click.prevent="rejectUpsell"
                    class="py-3 text-white font-medium rounded-lg border border-gray-600 hover:bg-[#2D2D2D] transition-colors">
                    {{ __('payment.keep_plan') }}
                </button>
                <button id="upsell-accept" wire:click.prevent="acceptUpsell"
                    class="py-3 bg-[#E50914] hover:bg-[#B8070F] text-white font-bold rounded-lg transition-colors">
                    {{ __('payment.want_to_save') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div id="processing-modal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showProcessingModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl p-8 max-w-md w-full mx-4 text-center">
        <div class="mb-4">
            <div
                class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-[#E50914] border-r-2 border-b-2 border-transparent">
            </div>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">{{ $loadingMessage }}</h3>
        <p class="text-gray-300">{{ __('payment.please_wait') }}</p>
    </div>
</div>

<!-- PIX Modal - Novo do zero baseado em código simples -->
@if($showPixModal)
<div class="modal-overlay" id="pix-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 9999; overflow-y: auto;">
    <div class="modal" style="background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%); border-radius: 16px; border: 1px solid rgba(76, 175, 80, 0.2); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(76, 175, 80, 0.1); max-width: 900px; width: 100%; overflow: hidden; my-auto: auto;">
        
        <!-- Header -->
        <div class="modal-header" style="background: linear-gradient(135deg, #1db854 0%, #149c3d 100%); padding: 24px; display: flex; align-items: center; justify-content: space-between; color: white;">
            <div class="modal-header-title" style="display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 600;">
                <div class="modal-header-icon" style="width: 24px; height: 24px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;"><svg class="w-3 h-3 sm:w-4 sm:h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                PIX
            </div>
            <button class="close-btn" onclick="document.getElementById('pix-modal-overlay').style.display='none'; @this.dispatch('closeModal');" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; transition: opacity 0.3s; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">×</button>
        </div>

        <!-- Content -->
        <div class="modal-content" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 40px;">
            
            <!-- QR Code Section -->
            <div class="qr-section" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;">
                <div class="qr-label" style="color: #a0aec0; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Escanear o QR Code</div>
                <div class="qr-code" style="width: 220px; height: 220px; background: white; border-radius: 12px; padding: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                    @if(!empty($pixQrImage))
                        <img src="@if(strpos($pixQrImage, 'data:') === false)data:image/png;base64,@endif{{ $pixQrImage }}" alt="QR Code" style="width: 100%; height: 100%; object-fit: contain;">
                    @else
                        <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; text-align: center; color: #999; font-size: 12px;">Carregando...</div>
                    @endif
                </div>
                <div class="qr-footer" style="font-size: 12px; color: #718096; text-align: center;">Câmera do banco</div>
            </div>

            <!-- Payment Section -->
            <div class="payment-section" style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Código PIX -->
                <div class="payment-info" style="background: rgba(45, 212, 191, 0.05); border: 1px solid rgba(45, 212, 191, 0.2); border-radius: 12px; padding: 20px;">
                    <div class="info-label" style="color: #a0aec0; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Código PIX</div>
                    <div class="info-value" style="color: #e2e8f0; font-size: 14px; line-height: 1.6; word-break: break-all; font-family: 'Monaco', 'Courier New', monospace; max-height: 80px; overflow-y: auto;" id="pix-code-display">{{ $pixQrCodeText ?? 'Código PIX' }}</div>
                    <button class="copy-btn" onclick="copyPixCode(event)" style="width: 100%; background: linear-gradient(135deg, #1db854 0%, #149c3d 100%); color: white; border: none; padding: 14px 24px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px;">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                        <span id="copy-text">Copiar código</span>
                    </button>
                    <div id="copy-message" style="display: none; color: #4caf50; font-size: 14px; font-weight: 600; text-align: center; margin-top: 12px; padding: 12px; background: rgba(76, 175, 80, 0.1); border-radius: 8px; border: 1px solid rgba(76, 175, 80, 0.2);">
                        Código copiado com sucesso - Vá até a opção "Copiar e colar do seu banco"
                    </div>
                </div>

                <!-- Price Section -->
                <div class="price-section" style="background: rgba(76, 175, 80, 0.08); border: 1px solid rgba(76, 175, 80, 0.2); border-radius: 12px; padding: 24px;">
                    <div class="price-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; color: #cbd5e0; font-size: 14px;">
                        <span>{{ str_replace(['1/month', '1 mês', '1/mes'], '1x/mês', substr($product['title'] ?? 'Streaming', 0, 30)) }}</span>
                        <span>R$ 49,90</span>
                    </div>
                    <div class="price-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; color: #cbd5e0; font-size: 14px;">
                        <span>Desconto PIX</span>
                        <span style="display: inline-block; background: rgba(76, 175, 80, 0.15); color: #4caf50; font-size: 12px; padding: 4px 8px; border-radius: 6px; font-weight: 500;">-R$ 25,00</span>
                    </div>
                    <div class="price-item" style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid rgba(76, 175, 80, 0.2); font-size: 16px; font-weight: 600; color: #4caf50;">
                        <span>Total a Pagar:</span>
                        <span>R$ {{ number_format($totals['final_price'] ?? 0, 2, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="security-section" style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(76, 175, 80, 0.1);">
                    <div class="security-item" style="display: flex; align-items: center; gap: 10px; color: #cbd5e0; font-size: 13px;">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-4M20.49 15a9 9 0 0 1-14.85 4"></path></svg>
                        <span>Confirmação em segundos</span>
                    </div>
                    <div class="security-item" style="display: flex; align-items: center; gap: 10px; color: #cbd5e0; font-size: 13px;">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Acesso imediato - Suporte 24/7</span>
                    </div>
                    <div class="security-item" style="display: flex; align-items: center; gap: 10px; color: #cbd5e0; font-size: 13px;">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>100% seguro</span>
                    </div>
                </div>

                <!-- Timer -->
                <div class="timer-section" style="display: flex; align-items: center; gap: 8px; color: #f97316; font-size: 13px; margin-top: 16px; padding: 12px; background: rgba(249, 115, 22, 0.08); border-radius: 8px;">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-yellow-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Válido por <span id="pix-timer" style="font-weight: 600; color: #f97316;">05:00</span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 1024px) {
        .modal {
            max-width: 90% !important;
        }
    }
    
    @media (max-width: 768px) {
        .modal {
            max-width: 95% !important;
            border-radius: 14px !important;
        }
        
        .modal-header {
            padding: 20px !important;
        }
        
        .modal-header-title {
            font-size: 16px !important;
            gap: 8px !important;
        }
        
        .modal-header-icon {
            width: 20px !important;
            height: 20px !important;
            font-size: 12px !important;
        }
        
        .close-btn {
            font-size: 28px !important;
            width: 32px !important;
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .modal-content {
            grid-template-columns: 1fr !important;
            gap: 24px !important;
            padding: 20px !important;
        }
        
        .qr-section {
            order: 2 !important;
            gap: 16px !important;
        }
        
        .qr-label {
            font-size: 12px !important;
        }
        
        .qr-code {
            width: 200px !important;
            height: 200px !important;
            padding: 10px !important;
        }
        
        .qr-footer {
            font-size: 11px !important;
        }
        
        .payment-section {
            order: 1 !important;
            gap: 16px !important;
        }
        
        .payment-info,
        .price-section {
            padding: 16px !important;
        }
        
        .info-label {
            font-size: 11px !important;
            margin-bottom: 6px !important;
        }
        
        .info-value {
            font-size: 13px !important;
            max-height: 70px !important;
        }
        
        .copy-btn {
            padding: 12px 16px !important;
            font-size: 14px !important;
            gap: 6px !important;
        }
        
        .price-item {
            font-size: 13px !important;
            margin-bottom: 10px !important;
            gap: 8px !important;
        }
        
        .price-item:last-child {
            font-size: 14px !important;
        }
    }
    
    @media (max-width: 480px) {
        .modal {
            max-width: 98% !important;
            border-radius: 12px !important;
            max-height: 85vh !important;
            overflow-y: auto !important;
        }
        
        .modal-header {
            padding: 16px !important;
            gap: 8px !important;
            flex-wrap: wrap !important;
        }
        
        .modal-header-title {
            font-size: 15px !important;
            gap: 6px !important;
        }
        
        .modal-header-icon {
            width: 18px !important;
            height: 18px !important;
            font-size: 11px !important;
        }
        
        .close-btn {
            font-size: 26px !important;
            width: 28px !important;
            height: 28px !important;
            padding: 0 !important;
            min-width: 28px !important;
        }
        
        .modal-content {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
            padding: 16px !important;
        }
        
        .qr-section {
            order: 2 !important;
            gap: 12px !important;
            padding-top: 0 !important;
        }
        
        .qr-label {
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
        }
        
        .qr-code {
            width: 160px !important;
            height: 160px !important;
            padding: 8px !important;
            border-radius: 10px !important;
        }
        
        .qr-footer {
            font-size: 10px !important;
        }
        
        .payment-section {
            order: 1 !important;
            gap: 12px !important;
        }
        
        .payment-info,
        .price-section {
            padding: 12px !important;
            border-radius: 10px !important;
        }
        
        .info-label {
            font-size: 10px !important;
            margin-bottom: 6px !important;
            letter-spacing: 0.3px !important;
        }
        
        .info-value {
            font-size: 12px !important;
            max-height: 60px !important;
            line-height: 1.4 !important;
        }
        
        .copy-btn {
            width: 100% !important;
            padding: 10px 12px !important;
            font-size: 13px !important;
            gap: 4px !important;
            border-radius: 8px !important;
            margin-top: 6px !important;
        }
        
        .price-item {
            font-size: 12px !important;
            margin-bottom: 8px !important;
            flex-wrap: wrap !important;
            gap: 4px !important;
        }
        
        .price-item:last-child {
            font-size: 13px !important;
            padding-top: 12px !important;
        }
        
        .security-section {
            gap: 8px !important;
            margin-top: 12px !important;
            padding-top: 12px !important;
        }
        
        .security-item {
            font-size: 12px !important;
            gap: 8px !important;
        }
        
        .timer-section {
            font-size: 12px !important;
            margin-top: 12px !important;
            padding: 10px !important;
            gap: 6px !important;
        }
    }
    
    @media (max-width: 360px) {
        .modal {
            max-width: 100% !important;
            margin: 0 10px !important;
            border-radius: 10px !important;
            max-height: 90vh !important;
        }
        
        .modal-header {
            padding: 12px !important;
        }
        
        .modal-header-title {
            font-size: 14px !important;
        }
        
        .modal-content {
            padding: 12px !important;
            gap: 12px !important;
        }
        
        .qr-code {
            width: 140px !important;
            height: 140px !important;
        }
        
        .info-value {
            font-size: 11px !important;
            max-height: 50px !important;
        }
        
        .copy-btn {
            padding: 8px 10px !important;
            font-size: 12px !important;
        }
        
        .price-item {
            font-size: 11px !important;
        }
    }
</style>@endif

<!-- Global Scripts para PIX Modal -->
<script>
let pixQRTimer = 300; // 5 minutes

function copyPixCode(e) {
    if (e) e.preventDefault();
    
    console.log('🔍 copyPixCode chamado!');
    
    const pixCodeDisplay = document.getElementById('pix-code-display');
    console.log('pixCodeDisplay:', pixCodeDisplay);
    if (!pixCodeDisplay) {
        console.error('❌ Elemento pix-code-display não encontrado');
        return;
    }
    
    const pixCode = pixCodeDisplay.textContent.trim();
    console.log('📋 Código PIX:', pixCode.substring(0, 20) + '...');
    
    if (!pixCode || pixCode === 'Código PIX') {
        alert('Código PIX não disponível');
        return;
    }

    const btn = document.querySelector('.copy-btn');
    const text = document.getElementById('copy-text');
    const copyMessage = document.getElementById('copy-message');
    const originalText = text.textContent;
    
    console.log('btn:', btn);
    console.log('text:', text);
    console.log('copyMessage:', copyMessage);

    // Tentar com Clipboard API
    if (navigator.clipboard) {
        navigator.clipboard.writeText(pixCode).then(() => {
            console.log('✅ Clipboard API sucesso!');
            updateCopyUI(text, btn, copyMessage, originalText);
        }).catch((err) => {
            console.error('❌ Clipboard API falhou:', err);
            fallbackCopy(pixCode, text, btn, copyMessage, originalText);
        });
    } else {
        console.log('⚠️ Clipboard API não disponível, usando fallback');
        fallbackCopy(pixCode, text, btn, copyMessage, originalText);
    }
}

function updateCopyUI(text, btn, copyMessage, originalText) {
    // Atualizar texto do botão
    text.textContent = 'Código copiado!';
    if (btn) {
        btn.style.background = 'linear-gradient(135deg, #4caf50 0%, #388e3c 100%)';
    }
    
    // Mostrar mensagem de instrução
    if (copyMessage) {
        copyMessage.textContent = 'Código copiado com sucesso - Vá até a opção "Copiar e colar do seu banco"';
        copyMessage.style.display = 'block';
        copyMessage.style.visibility = 'visible';
        copyMessage.style.opacity = '1';
        console.log('✅ Copy message shown:', copyMessage);
    } else {
        console.error('❌ Copy message element not found!');
    }
    
    setTimeout(() => {
        text.textContent = originalText;
        if (btn) {
            btn.style.background = 'linear-gradient(135deg, #1db854 0%, #149c3d 100%)';
        }
        if (copyMessage) {
            copyMessage.style.display = 'none';
        }
    }, 4000);
}

function fallbackCopy(pixCode, text, btn, copyMessage, originalText) {
    try {
        const textarea = document.createElement('textarea');
        textarea.value = pixCode;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textarea);
        
        if (successful) {
            updateCopyUI(text, btn, copyMessage, originalText);
        } else {
            alert('Falha ao copiar. Por favor, copie manualmente: ' + pixCode);
        }
    } catch (err) {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar o código. Por favor, tente novamente.');
    }
}

// Iniciar timer quando modal aparecer
let timerInterval = null;
let timerStarted = false;

// MutationObserver removed: timer is now started via Livewire 'pix-ready' event
// (see resources/js/pages/pay.js for the pix-ready handler which calls startTimer).

function startTimer(timerEl) {
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    
    clearInterval(timerInterval); // Limpar interval anterior se existir
    
    function updateTimer() {
        if (timerEl && pixQRTimer >= 0) {
            timerEl.textContent = formatTime(pixQRTimer);
            
            if (pixQRTimer === 0) {
                timerEl.parentElement.style.color = '#ef4444';
                console.log('⏰ PIX expirado!');
                clearInterval(timerInterval);
                timerInterval = null;
            } else if (pixQRTimer <= 60) {
                timerEl.style.color = '#ef4444';
            }
            
            pixQRTimer--;
        }
    }
    
    updateTimer(); // Executar uma vez imediatamente
    timerInterval = setInterval(updateTimer, 1000);
}
</script>
<div id="error-modal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showErrorModal) hidden @endif"
    x-data="{ show: @entangle('showErrorModal') }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-90"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-90"
    @keydown.escape.window="show = false"
>
    <div class="bg-[#1F1F1F] rounded-2xl p-8 max-w-md w-full mx-4 text-center border-2 border-red-500/50 shadow-2xl shadow-red-500/10">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-500/20 mb-4">
            <svg class="h-8 w-8 text-red-500" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-white mb-2">{{ __('payment.processing_error') }}</h3>
        <p class="text-gray-300 text-base whitespace-pre-wrap text-left">{{ $errorMessage }}</p>
        <button id="close-error" wire:click.prevent="closeModal"
            class="mt-6 bg-[#E50914] hover:bg-[#B8070F] text-white py-3 px-8 text-lg font-bold rounded-xl transition-all w-full transform hover:scale-105">
            {{ __('payment.close') }}
        </button>
    </div>
</div>

<!-- Error Modal -->
<div id="success-modal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showSuccessModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl p-8 max-w-md w-full mx-4 text-center">
        <h3 class="text-xl font-bold text-white mb-2">{{ __('payment.success') }}</h3>
            <button id="close-error" wire:click.prevent="closeModal"
                class="bg-[#42b70a] hover:bg-[#2a7904] text-white py-2 px-6 text-base font-semibold rounded-full shadow-lg w-auto min-w-[180px] max-w-xs mx-auto transition-all flex items-center justify-center cursor-pointer">
                Close
            </button>
    </div>
</div>

<!-- Downsell Modal -->
<div id="downsell-modal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 @if (!$showDownsellModal) hidden @endif">
    <div class="bg-[#1F1F1F] rounded-xl max-w-md w-full mx-4">
        <div class="p-6">
            <button id="close-downsell" wire:click.prevent="rejectDownsell"
                class="absolute top-3 right-3 text-gray-400 hover:text-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="text-center mb-4">
                <div class="bg-[#E50914] rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.921-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white">{{ __('payment.special_offer') }}</h3>
                <p class="text-gray-300 mt-2">{{ __('payment.try_quarterly') }}</p>
            </div>

            <div class="bg-[#2D2D2D] rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-300">{{ __('payment.monthly') }} {{ __('payment.current') }}</span>
                    <span class="text-white font-medium" id="downsell-monthly">
                        {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                        {{ $modalData['actual_month_value'] ?? 00 }} {{ __('payment.per_month') }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-white font-medium">{{ __('payment.quarterly') }}
                    </span>
                    <span class="text-[#E50914] font-bold" id="downsell-quarterly">
                        {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                        {{ $modalData['offer_month_value'] ?? 00 }}
                        {{ __('payment.per_month') }}

                    </span>
                </div>
                <div class="mt-2 text-green-500 text-sm text-right" id="downsell-savings">
                    {{ __('payment.savings') }}
                    {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                    {{ $modalData['offer_total_discount'] ?? 00 }}
                    {{ __('payment.quarterly') }}
                </div>
                <div class="mt-2 text-sm text-right" id="upsell-savings">
                    {{ __('payment.total_to_pay') }}
                    {{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }}
                    {{ $modalData['offer_total_value'] ?? 00 }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button id="downsell-reject" wire:click.prevent="rejectDownsell"
                    class="py-3 text-white font-medium rounded-lg border border-gray-600 hover:bg-[#2D2D2D] transition-colors">
                    {{ __('payment.no_thanks') }}
                </button>
                <button id="downsell-accept" wire:click.prevent="acceptDownsell"
                    class="py-3 bg-[#E50914] hover:bg-[#B8070F] text-white font-bold rounded-lg transition-colors">
                    {{ __('payment.want_offer') }}
                </button>
            </div>
        </div>
    </div>
</div>




<!-- Modal Usuário Existente -->
<!-- Modal Usuário Existente removido (checagem de email desativada) -->

<!-- Stripe JS -->
@push('scripts')
@vite('resources/js/pages/pay.js')
@if($gateway === 'stripe')
<script src="https://js.stripe.com/v3/"></script>
<script>
    let stripeCard = null;
    const stripe = Stripe("{{ config('services.stripe.api_public_key') }}");

    function initializeStripe() {
        if (stripeCard) {
            stripeCard.destroy();
            stripeCard = null;
        }

        if (!document.getElementById('card-element')) {
            return;
        }

        const elements = stripe.elements();
        const style = {
            base: {
                color: '#ffffffff',
                fontFamily: '"Urbanist", sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#868686ff'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };
        stripeCard = elements.create('card', {
            style: style,
            hidePostalCode: true
        });

        stripeCard.mount('#card-element');

        stripeCard.on('change', async (event) => {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }

            if (event.complete) {
                const { error, paymentMethod } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: stripeCard,
                    billing_details: {
                        name: document.querySelector('input[name="card_name"]').value,
                        email: document.querySelector('input[name="email"]').value,
                        phone: document.querySelector('input[name="phone"]').value,
                    },
                });

                if (error) {
                    displayError.textContent = error.message;
                } else {
                    @this.set('paymentMethodId', paymentMethod.id);
                }
            }
        });
    }

    document.addEventListener('livewire:init', () => {
        initializeStripe();

        Livewire.hook('message.processed', (message, component) => {
            initializeStripe();
        });

        Livewire.on('checkout-success', (event) => {
            const purchaseData = event.purchaseData;
            // Keep Facebook Purchase client event (do not remove)
            try {
                if (typeof fbq === 'function') {
                    fbq('track', 'Purchase', {
                        value: purchaseData.value,
                        currency: purchaseData.currency,
                        content_ids: purchaseData.content_ids,
                        content_type: purchaseData.content_type,
                        transaction_id: purchaseData.transaction_id
                    }, { eventID: purchaseData.transaction_id });
                }
            } catch(e) {}
        });

        Livewire.on('validation:failed', () => {
            const paymentSection = document.getElementById('payment-method-section');
            if (paymentSection) {
                paymentSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Clipboard copy listener triggered by Livewire server event
        window.addEventListener('copy-to-clipboard', (e) => {
            const text = e.detail && e.detail.text ? e.detail.text : '';
            if (!text) {
                alert('Nada para copiar');
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Código copiado para área de transferência');
                }).catch(() => {
                    alert('Falha ao copiar o código');
                });
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    alert('Código copiado para área de transferência');
                } catch (err) {
                    alert('Falha ao copiar o código');
                }
                document.body.removeChild(textarea);
            }
        });
    });

    window.addEventListener('redirectToExternal', event => {
        window.location.href = event.detail.url;
    });

    // Listener para redirecionar ao upsell após PIX aprovado
    Livewire.on('redirect-success', (event) => {
        console.log('🔴 [PagePay] redirect-success event received:', event);
        console.log('🔴 [PagePay] redirect URL:', event.url);
        
        if (!event.url) {
            console.error('🔴 [PagePay] ERROR: No URL provided in redirect-success event');
            return;
        }
        
        // Pequeno delay para garantir que Livewire processou tudo
        setTimeout(() => {
            console.log('🔴 [PagePay] REDIRECTING NOW to:', event.url);
            window.location.href = event.url;
        }, 100);
    });

    // Sincronizar Alpine com Livewire
    Livewire.hook('component.mounted', () => {
        // Quando o componente monta, faz dispatch de evento para Alpine saber
        window.dispatchEvent(new CustomEvent('livewire:initialized'));
    });

    Livewire.hook('component.updating', ({ component, updateQueue }) => {
        // Atualizar variáveis no Alpine quando Livewire muda
        if (updateQueue.selectedPaymentMethod !== undefined) {
            const methodSection = document.getElementById('payment-method-section');
            if (methodSection && methodSection.__x !== undefined) {
                methodSection.__x.selectedPaymentMethod = updateQueue.selectedPaymentMethod;
            }
        }
    });
</script>
@endif
@endpush
</div>
