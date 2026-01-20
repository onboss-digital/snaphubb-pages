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
    
    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .animate-pulse-subtle {
        animation: toastSlideIn 0.5s ease-out forwards;
    }
    
    #toast-bump-push_UGdkMrh9VR3 {
        transition: opacity 0.4s ease-in, transform 0.4s ease-in;
    }
    
    .opacity-0 {
        opacity: 0 !important;
    }
    
    .opacity-100 {
        opacity: 1 !important;
    }
    
    .scale-95 {
        transform: scale(0.9) !important;
    }
    
    .scale-100 {
        transform: scale(1) !important;
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
        // Test Livewire availability
        if (window.Livewire) {
            console.log('✅ LIVEWIRE LOADED AND AVAILABLE', window.Livewire);
            console.log('Livewire version:', window.Livewire.version);
            console.log('Livewire dispatch available:', typeof window.Livewire.dispatch);
            console.log('Livewire.find available:', typeof window.Livewire.find);
            console.log('Livewire.findClosest available:', typeof window.Livewire.findClosest);
            console.log('All Livewire methods:', Object.getOwnPropertyNames(window.Livewire).sort());
        } else {
            console.error('❌ LIVEWIRE NOT AVAILABLE! window.Livewire is', typeof window.Livewire);
        }

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

                // ✅ REDIRECT se houver URL
                if (purchase.redirect_url) {
                    console.log('✅ Redirecionando para:', purchase.redirect_url);
                    setTimeout(function() {
                        window.location.href = purchase.redirect_url;
                    }, 2000); // Aguarda 2s para mostrar sucesso
                    return;
                }

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
    <!-- ✅ Loader em HTML puro (não depende de Livewire, aparece IMEDIATAMENTE) -->
    <div id="simple-payment-loader" style="display: none !important; visibility: hidden !important; pointer-events: none !important;">
        <div class="loader-box">
            <!-- Ícone de sucesso -->
            <svg class="loader-icon loader-icon-success" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" width="70" height="70">
                <circle cx="32" cy="32" r="30" fill="none" stroke="#00ff88" stroke-width="2" opacity="0.3"/>
                <circle cx="32" cy="32" r="30" fill="none" stroke="#00ff88" stroke-width="3" stroke-dasharray="188" stroke-dashoffset="188" style="animation: drawCircle 0.6s ease-out forwards;"/>
                <path d="M 24 34 L 30 40 L 42 28" fill="none" stroke="#00ff88" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="20" stroke-dashoffset="20" style="animation: drawCheck 0.5s ease-out 0.3s forwards;"/>
                <style>
                    @keyframes drawCircle {
                        to { stroke-dashoffset: 0; }
                    }
                    @keyframes drawCheck {
                        to { stroke-dashoffset: 0; }
                    }
                </style>
            </svg>
            
            <!-- Ícone de erro -->
            <svg class="loader-icon loader-icon-error" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" width="70" height="70">
                <circle cx="32" cy="32" r="30" fill="none" stroke="#ff4444" stroke-width="2" opacity="0.3"/>
                <circle cx="32" cy="32" r="30" fill="none" stroke="#ff4444" stroke-width="3" stroke-dasharray="188" stroke-dashoffset="188" style="animation: drawErrorCircle 0.6s ease-out forwards;"/>
                <path d="M 24 24 L 40 40" stroke="#ff4444" stroke-width="3" stroke-linecap="round" stroke-dasharray="23" stroke-dashoffset="23" style="animation: drawX 0.4s ease-out 0.2s forwards;"/>
                <path d="M 40 24 L 24 40" stroke="#ff4444" stroke-width="3" stroke-linecap="round" stroke-dasharray="23" stroke-dashoffset="23" style="animation: drawX 0.4s ease-out 0.4s forwards;"/>
                <style>
                    @keyframes drawErrorCircle {
                        to { stroke-dashoffset: 0; }
                    }
                    @keyframes drawX {
                        to { stroke-dashoffset: 0; }
                    }
                </style>
            </svg>
            
            <!-- Spinner de carregamento -->
            <div class="loader-spinner"></div>
            
            <div class="loader-text">Processando seu pagamento…</div>
            <div class="loader-sub">Isso pode levar alguns segundos.</div>
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
                        var loader = el('#simple-payment-loader');
                        var title = el('#payment-loader-title') || el('.loader-text');
                        var sub = el('#payment-loader-sub') || el('.loader-sub');
                        var lang = '{{ $selectedLanguage ?? 'br' }}';
                        var strings = {
                            processing: {br: 'Aguarde, estamos processando o pagamento…', en: 'Please wait, processing payment…', es: 'Aguarde, estamos processando el pago…'},
                            success: {br: 'Parabéns! Compra aprovada.', en: 'Success! Payment approved.', es: '¡Felicidades! Compra aprobada.'},
                            failed: {br: 'Pagamento não aprovado. Verifique os dados e tente novamente.', en: 'Payment failed. Please verify details and try again.', es: 'Pago no aprobado. Verifique y vuelva a intentar.'}
                        };
                        var loaderShownAt = null;
                        var MIN_LOADER_MS = 3000; // mostrar loader no mínimo 3s
                        var CONFIRMATION_MS = 2000; // mostrar mensagem de confirmação por ~2s

                        function show(key){
                            if (!loader) {
                                console.error('Loader element not found!');
                                return;
                            }
                            if (title) title.textContent = strings[key] ? (strings[key][lang] || strings[key]['br']) : 'Processando...';
                            if (sub) sub.style.display = 'block';
                            loader.classList.add('show', 'loading');
                            loader.classList.remove('success', 'failed');
                            document.body.classList.add('overflow-hidden');
                            loaderShownAt = Date.now();
                            console.log('✅ Loader shown with state:', key);
                        }

                        function update(key){
                            if (!loader) return;
                            if (title) title.textContent = strings[key] ? (strings[key][lang] || strings[key]['br']) : 'Processando...';
                            
                            // Remove all state classes first
                            loader.classList.remove('loading', 'failed', 'success');
                            
                            // Add appropriate state class
                            if (key === 'success') {
                                loader.classList.add('show', 'success');
                                console.log('✅ Loader updated to SUCCESS state');
                            } else if (key === 'failed') {
                                loader.classList.add('show', 'failed');
                                console.log('❌ Loader updated to FAILED state');
                            } else {
                                loader.classList.add('show', 'loading');
                                console.log('⏳ Loader updated to LOADING state');
                            }
                        }

                        function hide(){
                            if (!loader) return;
                            loader.classList.remove('show', 'loading', 'success', 'failed');
                            document.body.classList.remove('overflow-hidden');
                            loaderShownAt = null;
                            console.log('❌ Loader hidden');
                        }

                        function whenMinLoaderElapsed(callback){
                            if (!loaderShownAt) return callback();
                            var elapsed = Date.now() - loaderShownAt;
                            var wait = Math.max(0, MIN_LOADER_MS - elapsed);
                            console.log('⏱️ Waiting', wait, 'ms before executing callback');
                            setTimeout(callback, wait);
                        }

                        // Loader mostra automaticamente quando Livewire muda $isProcessingCard para true
                        console.log('✅ Formulário de pagamento inicializado');

                        if (window.Livewire) {
                            console.log('✅ Livewire is AVAILABLE and ready!', window.Livewire);
                            
                            // Listen for payment-success browser event (Livewire 2.x compatible)
                            window.Livewire.on('payment-success', function(data){
                                console.log('✅ payment-success browser event RECEIVED', data);
                                
                                if (data && data.redirectUrl) {
                                    // Delay to show success state, then redirect with a hard redirect
                                    whenMinLoaderElapsed(function(){
                                        console.log('📤 Showing success state in loader');
                                        update('success');
                                        
                                        // Wait for confirmation message, then redirect using a hard navigation
                                        setTimeout(function(){
                                            console.log('🔄 Performing HARD redirect to:', data.redirectUrl);
                                            // Use hard reload to bypass any Livewire re-rendering
                                            window.location.href = data.redirectUrl;
                                            // Give it 100ms, then reload if somehow Livewire interferes
                                            setTimeout(function() {
                                                window.location.href = data.redirectUrl;
                                            }, 100);
                                        }, CONFIRMATION_MS);
                                    });
                                }
                            });
                            
                            // Listen for the checkout-success event dispatched from server (fallback)
                            window.Livewire.on('checkout-success', function(payload){
                                console.log('✅ Livewire checkout-success event RECEIVED', payload);
                                try{
                                    var redirectUrl = null;
                                    
                                    // Extract redirect URL from various possible locations
                                    if (payload && payload.purchaseData && payload.purchaseData.redirect_url) {
                                        redirectUrl = payload.purchaseData.redirect_url;
                                    } else if (payload && payload.redirect_url) {
                                        redirectUrl = payload.redirect_url;
                                    }
                                    
                                    if (redirectUrl) {
                                        whenMinLoaderElapsed(function(){
                                            console.log('📤 Showing success state in loader');
                                            update('success');
                                            
                                            // Wait for confirmation message, then redirect
                                            setTimeout(function(){
                                                console.log('🔄 Performing HARD redirect to:', redirectUrl);
                                                window.location.href = redirectUrl;
                                                // Double-ensure redirect
                                                setTimeout(function() {
                                                    window.location.href = redirectUrl;
                                                }, 100);
                                            }, CONFIRMATION_MS);
                                        });
                                    } else {
                                        console.log('⚠️ No redirect URL found, hiding loader');
                                        hide();
                                    }
                                }catch(e){ console.error('❌ Error in checkout-success handler:', e); hide(); }
                            });

                            Livewire.on('checkout-failed', function(payload){
                                console.log('❌ Livewire checkout-failed event RECEIVED', payload);
                                try{
                                    whenMinLoaderElapsed(function(){
                                        var errorMsg = (payload && payload.message) ? payload.message : strings.failed[lang];
                                        if (title) title.textContent = errorMsg;
                                        update('failed');
                                        
                                        // após mensagem de erro, redireciona se informado ou esconde
                                        setTimeout(function(){
                                            if (payload && payload.redirect_url) {
                                                window.location.href = payload.redirect_url;
                                            } else {
                                                hide();
                                            }
                                        }, CONFIRMATION_MS);
                                    });
                                }catch(e){ console.error('❌ Error in checkout-failed handler:', e); hide(); }
                            });

                            // Show loader when $isProcessingCard changes to true
                            // This is typically handled by Livewire reactivity
                            // But we need to manually show it when form is submitted
                            window.showPaymentLoader = function() {
                                show('processing');
                            };
                        } else {
                            console.error('❌ LIVEWIRE NOT AVAILABLE!');
                        }
                    };
                    if (document.readyState === 'complete' || document.readyState === 'interactive') formReady(); else document.addEventListener('DOMContentLoaded', formReady);
                })();
            </script>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-10">


                <!-- Coluna de Benefícios -->
                <div class="flex flex-col order-4">
                    <!-- Benefits -->
                    <div class="bg-[#1F1F1F] rounded-xl p-6 md:p-8 mb-3 md:mb-6">
                        <h2 class="text-xl md:text-2xl font-semibold text-white mb-4 md:mb-6">{{ __('checkout.benefits_title') }}</h2>
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

                    <!-- Botão Ativar Conta - Mobile Only -->
                    <div class="md:hidden flex justify-center mb-6">
                        <button type="button"
                            @click="document.getElementById('payment-method-section').scrollIntoView({ behavior: 'smooth' })"
                            class="w-full px-6 py-4 bg-gradient-to-r from-[#E50914] to-red-600 text-white font-bold text-lg rounded-lg hover:from-red-600 hover:to-red-700 transition-all shadow-lg hover:shadow-xl active:scale-95">
                            {{ __('checkout.activate_account') ?? 'ATIVAR CONTA' }}
                        </button>
                    </div>
                </div>

                <!-- Payment Methods Section - separate grid item -->
                <div class="flex flex-col order-2">
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
                                                class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.card_number') }} @if(isset($fieldErrors['cardNumber']))<span class="text-red-500">*</span>@endif</label>
                                            <input name="card_number" type="text" id="card-number"
                                                placeholder="0000 0000 0000 0000"
                                                wire:model.defer="cardNumber"
                                                inputmode="numeric" autocomplete="cc-number" pattern="[0-9\s]{13,19}" maxlength="19"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['cardNumber'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                            @if(isset($fieldErrors['cardNumber']))
                                                <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['cardNumber'] }}</span>
                                            @endif
                                            @error('cardNumber')
                                                <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 sm:gap-4">
                                            <div>
                                                <label
                                                    class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.expiry_date') }} @if(isset($fieldErrors['cardExpiry']))<span class="text-red-500">*</span>@endif</label>
                                                <input name="card_expiry" type="text" id="card-expiry"
                                                    placeholder="MM/YY" wire:model.defer="cardExpiry"
                                                    inputmode="numeric" autocomplete="cc-exp" pattern="(0[1-9]|1[0-2])\/([0-9]{2})" maxlength="5"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['cardExpiry'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                                @if(isset($fieldErrors['cardExpiry']))
                                                    <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['cardExpiry'] }}</span>
                                                @endif
                                                @error('cardExpiry')
                                                    <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.security_code') }} @if(isset($fieldErrors['cardCvv']))<span class="text-red-500">*</span>@endif</label>
                                                <input name="card_cvv" type="text" id="card-cvv" placeholder="CVV"
                                                    wire:model.defer="cardCvv" inputmode="numeric" autocomplete="cc-csc" pattern="[0-9]{3,4}" maxlength="4"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['cardCvv'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                                @if(isset($fieldErrors['cardCvv']))
                                                    <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['cardCvv'] }}</span>
                                                @endif
                                                @error('cardCvv')
                                                    <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
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
                                            class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.card_name') }} @if(isset($fieldErrors['cardName']))<span class="text-red-500">*</span>@endif</label>
                                        <input name="card_name" type="text"
                                            placeholder="{{ __('payment.card_name') }}" wire:model.live="cardName"
                                            autocomplete="cc-name" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['cardName'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['cardName']))
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['cardName'] }}</span>
                                        @endif
                                        @error('cardName')
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.card_field_email_label') }}: @if(isset($fieldErrors['email']))<span class="text-red-500">*</span>@endif</label>
                                        <input name="email" type="email" placeholder="{{ __('payment.card_field_email_hint') }}"
                                            wire:model.live.debounce.500ms="email"
                                            autocomplete="email" inputmode="email" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['email'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['email']))
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['email'] }}</span>
                                        @else
                                            <div id="email-suggestion" class="text-xs sm:text-sm mt-1 sm:mt-2">
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
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">{{ __('payment.card_field_phone_label') }} ({{ __('payment.optional') }}):</label>
                                        <input name="phone" id="phone" type="tel" placeholder="{{ __('payment.card_field_phone_hint') }}"
                                            wire:model="phone" inputmode="tel" autocomplete="tel"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['phone'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @if(isset($fieldErrors['phone']))
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['phone'] }}</span>
                                        @endif
                                        @error('phone')
                                            <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    @if ($selectedLanguage === 'br')
                                        <div>
                                            <label
                                                class="block text-xs sm:text-sm font-medium text-gray-300 mb-1 sm:mb-2">CPF</label>
                                            <input name="cpf" type="text"
                                                placeholder="000.000.000-00" wire:model.live="cpf" inputmode="numeric" autocomplete="off"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-2 sm:p-3 text-sm sm:text-base @if(isset($fieldErrors['cpf'])) border-2 border-red-500 @else border border-gray-700 @endif focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                            @if(isset($fieldErrors['cpf']))
                                                <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $fieldErrors['cpf'] }}</span>
                                            @endif
                                            @error('cpf')
                                                <span class="text-red-500 text-xs sm:text-sm mt-1 sm:mt-2 block">{{ $message }}</span>
                                            @enderror

                                            <!-- Mobile Only Button - Below CPF (Only for Credit Card) -->
                                            <div class="md:hidden mt-4" x-show="selectedPaymentMethod === 'credit_card'">
                                                <button type="button" wire:click.prevent="startCheckout"
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
                                                        ATIVAR MINHA CONTA
                                                    @endif
                                                </button>
                                            </div>
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

                                    <!-- Order Bumps for PIX (mobile: appears before button) -->
                                    @php
                                        $pushinSupported = $plans[$selectedPlan]['gateways']['pushinpay']['supported'] ?? false;
                                    @endphp
                                    @if (!empty($bumps) && !($selectedPaymentMethod === 'pix' && $pushinSupported === false))
                                        <div class="space-y-3 mt-4">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"></path>
                                                </svg>
                                                <h3 class="text-white font-semibold text-sm">{{ __('payment.add_ons_title') }}</h3>
                                            </div>
                                            
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
                                                
                                                <!-- PIX Design: Minimalista e compacto -->
                                                <div class="bump-card-pix relative bg-gradient-to-r from-blue-600/20 to-blue-500/10 rounded-lg p-3 md:p-4 border border-blue-500/30 hover:border-blue-400/60 transition-all duration-300 flex flex-col md:grid md:grid-cols-12 md:items-center gap-3 md:gap-4">
                                                    
                                                    <!-- Toast Notification - Glass Effect -->
                                                    <div id="toast-bump-{{ $bump['id'] }}" class="fixed inset-0 flex items-center justify-center z-50 hidden pointer-events-none p-4">
                                                        <div class="bg-gradient-to-r from-emerald-500/25 to-green-500/15 backdrop-blur-2xl rounded-3xl px-8 py-7 md:px-10 md:py-8 shadow-2xl border border-emerald-400/40 flex flex-col items-center gap-3 max-w-sm w-full animate-pulse-subtle pointer-events-auto text-center">
                                                            <!-- Ícone com glow -->
                                                            <div class="relative">
                                                                <div class="absolute inset-0 bg-emerald-400 blur-xl opacity-60 rounded-full w-16 h-16"></div>
                                                                <svg class="w-12 h-12 text-emerald-300 relative z-10" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            </div>
                                                            
                                                            <!-- Texto -->
                                                            <div class="flex flex-col gap-2">
                                                                <span class="font-bold text-lg md:text-xl text-white">{{ $bumpTitle }}</span>
                                                                <span class="text-sm md:text-base text-emerald-100 font-semibold">+ {{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}</span>
                                                                <span class="text-xs md:text-sm text-emerald-200/80">adicionado ao pedido</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center gap-3 md:col-span-8 cursor-pointer w-full">
                                                        <input id="order-bump-{{ $bump['id'] }}" type="checkbox"
                                                            class="w-5 h-5 text-blue-500 bg-blue-900/30 border-blue-400 rounded
                                                            focus:ring-blue-500 focus:ring-opacity-25 focus:ring-2
                                                            focus:border-blue-500 cursor-pointer flex-shrink-0"
                                                            wire:model.live="bumps.{{ $index }}.active"
                                                            wire:change="toggleBumpSelection({{ $index }})"
                                                            onchange="
                                                                console.log('✅ Bump {{ $bump['title'] }} marcado:', this.checked);
                                                                const toast = document.getElementById('toast-bump-{{ $bump['id'] }}');
                                                                if(this.checked) {
                                                                    toast.classList.remove('hidden');
                                                                    void toast.offsetWidth;
                                                                    toast.classList.add('opacity-100', 'scale-100');
                                                                    toast.classList.remove('opacity-0', 'scale-95');
                                                                    const hideTimer = setTimeout(() => {
                                                                        toast.classList.remove('opacity-100', 'scale-100');
                                                                        toast.classList.add('opacity-0', 'scale-95');
                                                                        setTimeout(() => {
                                                                            toast.classList.add('hidden');
                                                                        }, 400);
                                                                    }, 4500);
                                                                    toast.dataset.timer = hideTimer;
                                                                }
                                                            " />
                                                        
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="text-blue-300 font-semibold text-sm">{{ $bumpTitle }}</span>
                                                                @if($badge)
                                                                    <span class="text-xs px-2 py-0.5 bg-blue-500 text-white rounded whitespace-nowrap">{{ $badge }}</span>
                                                                @endif
                                                            </div>
                                                            <p class="text-gray-400 text-xs bump-desc">{{ $bumpDesc }}</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-right md:col-span-4 md:text-right md:flex md:justify-end">
                                                        @if($discountPct)
                                                            <div class="text-green-400 text-xs font-bold mb-1 md:mb-0 md:mr-2">-{{ $discountPct }}%</div>
                                                        @endif
                                                        <span class="font-bold text-sm {{ isset($dataOrigin) && $dataOrigin['bumps'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback' : 'text-blue-400') }}">
                                                            +{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

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

                    <!-- Order Bumps for Card (CREDIT CARD ONLY) -->
                    @php
                        $pushinSupported = $plans[$selectedPlan]['gateways']['pushinpay']['supported'] ?? false;
                    @endphp
                    @if (!empty($bumps) && $selectedPaymentMethod === 'credit_card')
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"></path>
                                </svg>
                                <h3 class="text-white font-semibold text-sm">{{ __('payment.add_ons_title') }}</h3>
                            </div>
                            
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
                                
                                <!-- Card Design: Completo e detalhado -->
                                <div class="bump-card group relative bg-gradient-to-br from-[#2D2D2D] to-[#1F1F1F] rounded-lg p-3 md:p-4 border border-gray-700 hover:border-[#E50914] transition-all duration-300 cursor-pointer"
                                    @click="document.getElementById('order-bump-{{ $bump['id'] }}').click()">
                                
                                <!-- Badge (se tiver) -->
                                @if($badge)
                                    <div class="absolute -top-2 md:-top-3 -right-2 md:-right-3">
                                        <span class="inline-block px-2 md:px-3 py-0.5 md:py-1 text-xs font-bold text-white rounded-full
                                            @if($badgeColor === 'gold') bg-yellow-500 
                                            @elseif($badgeColor === 'blue') bg-blue-600 
                                            @else bg-[#E50914] @endif">
                                            {{ $badge }}
                                        </span>
                                    </div>
                                @endif

                                <!-- Recomendado Badge -->
                                @if($isRecommended)
                                    <div class="absolute -top-2 md:-top-3 -left-2 md:-left-3">
                                        <span class="inline-block px-2 py-0.5 md:py-1 text-xs font-bold text-white bg-green-600 rounded-full">
                                            ⭐ {{ __('payment.recommended') }}
                                        </span>
                                    </div>
                                @endif
                                
                                <!-- Content -->
                                <div class="flex flex-col md:flex-row gap-3 md:gap-4">
                                    <!-- Checkbox -->
                                    <div class="flex items-start pt-0.5 md:pt-1 flex-shrink-0">
                                        <input id="order-bump-{{ $bump['id'] }}" type="checkbox"
                                            class="w-5 h-5 text-[#E50914] bg-[#2D2D2D] border-gray-600 rounded
                                            focus:ring-[#E50914] focus:ring-opacity-25 focus:ring-2
                                            focus:border-[#E50914] cursor-pointer"
                                            wire:model.live="bumps.{{ $index }}.active"
                                            wire:change="calculateTotals" />
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
                                <div class="mt-3 md:mt-3 pt-3 md:pt-3 border-t border-gray-700">
                                    @if($originalPrice)
                                        <div class="flex flex-col md:flex-row md:items-center md:gap-2 md:justify-between">
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
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Coluna de Resumo do Pedido -->
                <div class="md:col-span-1 order-1">
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
                        <div id="priceSummary" class="mb-6 p-3 md:p-4 bg-gray-800 rounded-lg text-white">
                            <p class="text-base md:text-lg font-semibold mb-3 md:mb-4">{{ __('payment.final_summary') }}</p>
                            
                            <!-- Preço Recorrente do Plano -->
                            <div class="flex justify-between text-xs md:text-sm mb-3 md:mb-4">
                                <span class="flex-1">{{ __('payment.subscription_price') }} <span class="text-xs text-gray-400">/mês</span></span>
                                <span class="font-semibold text-right whitespace-nowrap ml-2">{{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['month_price'] ?? 0, 2, ',', '.') }}</span>
                            </div>
                            
                            <!-- Order Bumps Individuais (se houver) -->
                            @if(!empty($bumps))
                                @foreach($bumps as $bumpIdx => $bump)
                                    @php
                                        $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
                                        if ($isActive) {
                                            $langCode = match($selectedLanguage) {
                                                'en' => 'title_en',
                                                'es' => 'title_es',
                                                default => 'title'
                                            };
                                            $bumpTitle = $bump[$langCode] ?? $bump['title'] ?? '';
                                            $bumpPrice = $bump['price'] ?? $bump['original_price'] ?? $bump['amount'] ?? 0;
                                        }
                                    @endphp
                                    
                                    @if($isActive)
                                        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2 mb-2 p-2 md:p-3 bg-blue-900/10 rounded border border-blue-500/20">
                                            <span class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs md:text-sm break-words">{{ $bumpTitle }}</span>
                                                <span class="inline-block px-1.5 py-0.5 text-xs bg-blue-600 text-white rounded whitespace-nowrap">{{ __('payment.one_time') }}</span>
                                            </span>
                                            <span class="font-semibold text-blue-300 text-xs md:text-sm">+{{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($bumpPrice, 2, ',', '.') }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                            
                            <!-- Total a Pagar -->
                            <div class="flex justify-between items-center text-xs md:text-sm font-bold mt-3 md:mt-4 pt-3 md:pt-4 border-t border-gray-700">
                                <span>{{ __('payment.total_to_pay') }}</span>
                                <span id="final-price" class="text-right whitespace-nowrap ml-2 {{ isset($dataOrigin) && $dataOrigin['totals'] === 'backend' ? 'price-backend' : (isset($dataOrigin) ? 'price-fallback animate-fallback-pulse' : '') }}">
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
                            <div class="bg-gray-800/50 rounded-lg p-4 mb-4" wire:key="pix-summary-{{ json_encode($bumps) }}">
                                <!-- Preço Recorrente do Plano -->
                                <div class="flex justify-between items-center pb-3 border-b border-gray-700 mb-3">
                                    <span class="text-gray-300 text-sm">{{ __('payment.subscription_price') }} <span class="text-xs text-gray-400">/mês</span></span>
                                    <span class="text-white font-semibold text-sm">{{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['month_price'] ?? 0, 2, ',', '.') }}</span>
                                </div>
                                
                                <!-- Confirmação de Order Bumps Adicionados (se houver) -->
                                <!-- DEBUG: Bumps total = {{ count($bumps ?? []) }} -->
                                @if(!empty($bumps))
                                    @foreach($bumps as $bumpIdx => $bump)
                                        <!-- DEBUG: Bump {{ $bumpIdx }} - ID: {{ $bump['id'] ?? 'null' }} - Active: {{ $bump['active'] ?? 'false' }} -->
                                        @php
                                            $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
                                            if ($isActive) {
                                                $langCode = match($selectedLanguage) {
                                                    'en' => 'title_en',
                                                    'es' => 'title_es',
                                                    default => 'title'
                                                };
                                                $bumpTitle = $bump[$langCode] ?? $bump['title'] ?? '';
                                                $price = $bump['price'] ?? $bump['original_price'] ?? $bump['amount'] ?? 0;
                                            }
                                        @endphp
                                        
                                        @if($isActive)
                                            <!-- RENDERIZANDO BUMP ATIVO -->
                                            <div class="flex items-center justify-between py-2 px-2 mb-2 bg-green-500/10 rounded border border-green-500/30">
                                                <div class="flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span class="text-green-300 text-xs md:text-sm font-semibold">{{ $bumpTitle }}</span>
                                                </div>
                                                <span class="text-green-400 font-bold text-xs md:text-sm">+{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($price, 2, ',', '.') }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <!-- DEBUG: Nenhum bump disponível -->
                                @endif
                                
                                <!-- Total a Pagar -->
                                <div class="flex justify-between items-center pt-3 border-t border-gray-700">
                                    <span class="text-white font-bold text-base">{{ __('payment.total_to_pay') }}</span>
                                    <span class="font-bold text-2xl {{ (isset($dataOrigin) && $dataOrigin['totals'] === 'backend') ? 'price-backend' : ((isset($dataOrigin) && ($selectedPaymentMethod === 'credit_card')) ? 'price-fallback animate-fallback-pulse' : 'text-green-400') }}">
                                        {{ $currencies[$selectedCurrency]['symbol'] }} {{ number_format($totals['final_price'] ?? 0, 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="bg-green-500/10 border border-green-500/40 rounded p-2 flex items-center gap-2 mt-3">
                                    <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></path></svg>
                                    <span class="text-green-300 text-xs font-semibold">{{ __('payment.flexible_plan') }}</span>
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

        // Listen for start-pix-polling event from Livewire
        Livewire.on('start-pix-polling', (data) => {
            const transactionId = data.transactionId || @js($pixTransactionId ?? null);
            const userEmail = @js(auth('web')->check() ? auth('web')->user()->email : '');
            
            console.log('[PIX Polling] Starting polling for transaction:', transactionId, 'email:', userEmail);
            
            if (transactionId && userEmail) {
                startPixPaymentPolling(transactionId, userEmail);
            } else {
                console.warn('[PIX Polling] Missing transactionId or userEmail for polling');
            }
        });
    });
</script>


</div>

<!-- PIX Modal - NOVO COM TAILWIND PURO -->
@if($showPixModal)
<!-- Modal Background -->
<div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-4" id="pixModalBackdrop">
    <!-- Modal Container - Premium Design -->
    <div class="bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[95vh] overflow-y-auto border border-gray-800">
        
        <!-- Header - Clean & Minimal -->
        <div class="sticky top-0 bg-gradient-to-r from-green-600 to-emerald-600 px-6 sm:px-8 py-6 sm:py-7 flex items-center justify-between z-10 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-md">
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </div>
                <h2 class="text-white text-xl sm:text-2xl font-bold tracking-tight">PIX</h2>
            </div>
            <button onclick="@this.dispatch('closeModal');" class="text-white hover:bg-white hover:bg-opacity-10 rounded-lg p-2 transition-all duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Content -->
        <div class="p-6 sm:p-8 space-y-6">
            <!-- Main Grid -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                
                <!-- QR Code Section - Left Column -->
                <div class="md:col-span-2 flex flex-col items-center justify-center">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-5">Scan para pagar</div>
                    <div class="w-40 h-40 sm:w-48 sm:h-48 bg-white rounded-2xl p-4 shadow-lg flex items-center justify-center relative">
                        @if(!empty($pixQrImage))
                            @php
                                $pixSrc = strpos($pixQrImage, 'data:') === false ? 'data:image/png;base64,' . $pixQrImage : $pixQrImage;
                            @endphp
                            <img src="{{ $pixSrc }}" alt="QR Code PIX" class="w-full h-full object-contain">
                        @else
                            <div class="flex flex-col items-center justify-center text-center">
                                <div class="animate-spin w-10 h-10 border-3 border-emerald-500 border-t-transparent rounded-full mb-3"></div>
                                <span class="text-sm text-gray-500">Carregando...</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Details - Right Column -->
                <div class="md:col-span-3 flex flex-col gap-5">
                    
                    <!-- Código PIX Section -->
                    <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-5 sm:p-6 backdrop-blur-sm">
                        <div class="text-xs font-semibold text-emerald-400 uppercase tracking-widest mb-3">Código PIX (Copiar e Colar)</div>
                        <div id="pixCodeDisplay" class="bg-gray-950 rounded-lg p-4 mb-4 text-gray-300 text-xs sm:text-sm font-mono max-h-20 overflow-y-auto border border-gray-600 cursor-pointer hover:border-emerald-500 transition-colors select-all">
                            {{ $pixQrCodeText ?? 'Código PIX' }}
                        </div>
                        <button onclick="copyPixCode(event)" type="button" class="w-full bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold py-3 sm:py-3.5 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-emerald-500/25">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                            <span id="copyText">Copiar Código</span>
                        </button>
                        <div id="copyMessage" class="hidden text-emerald-300 text-xs sm:text-sm font-semibold text-center mt-3 p-3 bg-emerald-500/10 rounded-lg border border-emerald-500/30 animate-in fade-in duration-300">
                            ✓ Código copiado. Abra o app do seu banco e conclua a transação.
                        </div>
                    </div>

                    <!-- Price Summary -->
                    <div class="bg-gray-800 bg-opacity-70 border border-gray-600 rounded-xl p-5 sm:p-6 space-y-3 sm:space-y-4">
                        <!-- Product -->
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-100 font-medium">{{ str_replace(['1/month', '1 mês', '1/mes'], '1x/mês', substr($product['title'] ?? 'Produto', 0, 40)) }}</span>
                            <span class="text-white font-bold">{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format(($totals['total_price'] ?? 0) - ($totals['bumps_total'] ?? 0), 2, ',', '.') }}</span>
                        </div>

                        <!-- Bumps - Debug -->
                        @php
                            // Debug: Log what we have
                            $activeBumps = [];
                            if (is_array($bumps) && !empty($bumps)) {
                                foreach ($bumps as $index => $bump) {
                                    if (is_array($bump)) {
                                        // Check multiple ways to detect active
                                        $isActive = false;
                                        if (isset($bump['active'])) {
                                            $isActive = (bool)$bump['active'];
                                        }
                                        if ($isActive) {
                                            $activeBumps[] = $bump;
                                        }
                                    }
                                }
                            }
                        @endphp

                        <!-- Show bumps if any -->
                        @forelse($activeBumps as $bump)
                            <div class="flex justify-between items-center text-sm pt-3 border-t border-gray-700">
                                <span class="text-emerald-400 font-medium">+ {{ $bump['title'] ?? 'Bump' }}</span>
                                <span class="text-emerald-400 font-semibold">+ {{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($bump['price'] ?? $bump['original_price'] ?? $bump['amount'] ?? 0, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            {{-- Sem bumps adicionais --}}
                        @endforelse

                        <!-- Divider -->
                        <div class="border-t border-gray-600"></div>

                        <!-- Subtotal & Discount -->
                        <div class="flex justify-between items-center text-xs text-gray-200 pt-3 border-t border-gray-600">
                            <span class="font-medium">Subtotal</span>
                            <span class="font-semibold">{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($totals['total_price'] ?? 0, 2, ',', '.') }}</span>
                        </div>

                        @if(isset($totals['total_discount']) && $totals['total_discount'] > 0)
                            <div class="flex justify-between items-center text-xs text-blue-300">
                                <span class="font-medium">Desconto PIX</span>
                                <span class="font-semibold">- {{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($totals['total_discount'], 2, ',', '.') }}</span>
                            </div>
                        @endif

                        <!-- Total -->
                        <div class="border-t border-gray-600 pt-4 flex justify-between items-center">
                            <span class="text-white font-bold text-base">Total a Pagar</span>
                            <span class="text-emerald-400 font-bold text-2xl">{{ $currencies[$selectedCurrency]['symbol'] }}{{ number_format($totals['final_price'] ?? $totals['total_price'] ?? 0, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="bg-gray-800 bg-opacity-60 rounded-xl p-5 sm:p-6 border border-gray-600">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        <div>
                            <div class="text-xs font-semibold text-gray-200">Confirmação</div>
                            <div class="text-xs text-gray-400">Em segundos</div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        <div>
                            <div class="text-xs font-semibold text-gray-200">Acesso 24/7</div>
                            <div class="text-xs text-gray-400">Suporte incluso</div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        <div>
                            <div class="text-xs font-semibold text-gray-200">100% Seguro</div>
                            <div class="text-xs text-gray-400">Criptografado</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timer - Premium Design -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-600/20 via-orange-600/20 to-amber-600/20 border border-red-500/40 p-6 sm:p-7">
                <!-- Background gradient animation -->
                <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 via-transparent to-orange-500/5 animate-pulse"></div>
                
                <div class="relative flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-4">
                    <!-- Left: Icon + Label -->
                    <div class="flex items-center gap-3">
                        <div class="relative w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center border border-red-500/50">
                            <svg class="w-6 h-6 text-red-400 animate-spin" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Oferta expira em</span>
                            <span class="text-sm text-gray-300">Aproveite agora</span>
                        </div>
                    </div>
                    
                    <!-- Right: Timer Value -->
                    <div class="flex items-baseline gap-2">
                        <span id="timerValue" class="text-4xl sm:text-5xl font-black text-red-400 font-mono tracking-tighter">05:00</span>
                        <span class="text-xs font-semibold text-gray-400 uppercase mb-1">minutos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pixQRTimer = 300;

// Debug: log bumps state toda vez que muda
Livewire.on('updated', (propertyName, value) => {
    if (propertyName === 'bumps') {
        console.log('✅ Bumps atualizados:', value);
        console.log('📊 Bumps ativos:', value.filter(b => b.active));
    }
});

// Debug no console quando modal abre
document.addEventListener('DOMContentLoaded', () => {
    console.log('🔍 [PIX Modal] Iniciado');
    console.log('🔍 $bumps data:', @json($bumps ?? []));
    console.log('🔍 $totals data:', @json($totals ?? []));
});

// Observar mudanças no modal
const observer = new MutationObserver(() => {
    const modal = document.getElementById('pixModalBackdrop');
    if (modal && modal.offsetParent !== null) { // offsetParent !== null significa que está visível
        console.log('✅ [PIX Modal] Visível');
        console.log('✅ Bumps ativos no modal:', @json($bumps ?? []));
        // Iniciar timer quando modal fica visível
        const timerEl = document.getElementById('timerValue');
        if (timerEl && !timerStarted) {
            timerStarted = true;
            startTimer(timerEl);
            console.log('✅ Timer iniciado!');
        }
    } else {
        // Modal foi fechado
        timerStarted = false;
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
            console.log('⏹️ Timer parado');
        }
    }
});

observer.observe(document.body, { subtree: true, attributes: true, childList: true });

function updatePixTimer() {
    if (pixQRTimer > 0) {
        pixQRTimer--;
        const minutes = Math.floor(pixQRTimer / 60);
        const seconds = pixQRTimer % 60;
        const timerDisplay = document.getElementById('timerValue');
        if (timerDisplay) {
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }
}

const timerInterval = setInterval(updatePixTimer, 1000);

window.addEventListener('beforeunload', () => {
    clearInterval(timerInterval);
});
</script>



<!-- Global Scripts para PIX Modal -->
<script>
let pixQRTimer = 300; // 5 minutes

function copyPixCode(e) {
    if (e) e.preventDefault();
    
    const pixCodeDisplay = document.getElementById('pixCodeDisplay');
    if (!pixCodeDisplay) {
        console.error('❌ Elemento pixCodeDisplay não encontrado');
        return;
    }
    
    const pixCode = pixCodeDisplay.textContent.trim();
    
    if (!pixCode || pixCode === 'Código PIX') {
        alert('Código PIX não disponível');
        return;
    }

    const copyText = document.getElementById('copyText');
    const copyMessage = document.getElementById('copyMessage');
    const originalText = copyText ? copyText.textContent : 'Copiar Código';

    // Tentar com Clipboard API (navegadores modernos)
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pixCode).then(() => {
            showCopySuccess(copyMessage, copyText, originalText);
        }).catch(() => {
            fallbackCopy(pixCode, copyMessage, copyText, originalText);
        });
    } else {
        fallbackCopy(pixCode, copyMessage, copyText, originalText);
    }
}

function showCopySuccess(copyMessage, copyText, originalText) {
    if (copyText) {
        copyText.textContent = '✓ Copiado!';
    }
    
    if (copyMessage) {
        copyMessage.classList.remove('hidden');
        setTimeout(() => {
            copyMessage.classList.add('hidden');
            if (copyText) {
                copyText.textContent = originalText;
            }
        }, 3000);
    } else {
        if (copyText) {
            setTimeout(() => {
                copyText.textContent = originalText;
            }, 3000);
        }
    }
}

function fallbackCopy(pixCode, copyMessage, copyText, originalText) {
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
            showCopySuccess(copyMessage, copyText, originalText);
        } else {
            alert('Falha ao copiar. Por favor, copie manualmente.');
        }
    } catch (err) {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar o código.');
    }
}

// Iniciar timer quando modal aparecer
let timerInterval = null;
let timerStarted = false;

function startTimer(timerEl) {
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    
    // Reset timer to 5 minutes when modal opens
    pixQRTimer = 300;
    
    clearInterval(timerInterval); // Limpar interval anterior se existir
    
    console.log('⏱️ Timer iniciado: 5:00');
    
    function updateTimer() {
        if (timerEl && pixQRTimer >= 0) {
            const timeString = formatTime(pixQRTimer);
            timerEl.textContent = timeString;
            
            // Mudar cor conforme o tempo passa
            if (pixQRTimer === 0) {
                timerEl.style.color = '#ef4444';
                timerEl.style.fontSize = '3rem';
                timerEl.parentElement.parentElement.style.borderColor = '#dc2626';
                console.log('⏰ PIX expirado!');
                clearInterval(timerInterval);
                timerInterval = null;
            } else if (pixQRTimer <= 60) {
                // Piscar nos últimos 60 segundos
                timerEl.style.color = '#ff6b6b';
                timerEl.style.animation = 'pulse 1s infinite';
            }
            
            pixQRTimer--;
        }
    }
    
    // Executar uma vez imediatamente
    updateTimer();
    // Depois executar a cada segundo
    timerInterval = setInterval(updateTimer, 1000);
}
</script>
@endif

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