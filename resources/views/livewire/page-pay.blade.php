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
        }

        to {
            opacity: 1;
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

        // Minimal Facebook Pixel on homepage: init + InitiateCheckout only
        (function(){
            var fbPixelId = '{{ env("FB_PIXEL_ID") }}';
            try {
                if (fbPixelId && fbPixelId !== 'YOUR_FACEBOOK_PIXEL_ID') {
                    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
                    try { fbq('init', fbPixelId); fbq('track', 'InitiateCheckout', { value: window.checkoutData.value || 0, currency: window.checkoutData.currency || 'BRL' }); } catch(e){}
                }
            } catch(e){}
        })();

        // Listen for payment method changes to fire add_payment_info (GA4)
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
    <div class="container mx-auto px-4 py-8 max-w-4xl pb-24 md:pb-8">

        <!-- Header -->
        <header class="mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between">

                @php
                $logo = asset('imgs/logo.png');
                @endphp


                <img class="img-fluid logo" src="{{ $logo }}" alt="streamit">

                <!-- Language Selector -->
                <div class="flex items-center space-x-4">
                    <div class="relative text-sm">
                        <form id="language-form" method="POST">
                            @csrf
                            <select id="language-selector" name="lang"
                                wire:change="changeLanguage(event.target.value)"
                                class="bg-[#1F1F1F] text-white rounded-md px-3 py-1.5 border border-gray-700 appearance-none pr-8 focus:outline-none focus:ring-1 focus:ring-[#E50914] hover:border-gray-500 transition-all">
                                @foreach ($availableLanguages as $code => $name)
                                <option value="{{ $code }}"
                                    {{ app()->getLocale() == $code ? 'selected' : '' }}>
                                    {!! $name !!}</option>
                                @endforeach
                            </select>
                        </form>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-white">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                <path d="M7 7l3-3 3 3m0 6l-3 3-3-3" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            @php
            $banners = [
                'br' => [
                    'desktop' => 'https://web.snaphubb.online/wp-content/uploads/2025/09/banner-brasil.jpg',
                    'mobile' => 'https://web.snaphubb.online/wp-content/uploads/2025/10/BRASIL.png',
                ],
                'en' => [
                    'desktop' => 'https://web.snaphubb.online/wp-content/uploads/2025/09/banner-estados-unidos.jpg',
                    'mobile' => 'https://web.snaphubb.online/wp-content/uploads/2025/10/INGLES.png',
                ],
                'es' => [
                    'desktop' => 'https://web.snaphubb.online/wp-content/uploads/2025/09/banner-mexico.jpg',
                    'mobile' => 'https://web.snaphubb.online/wp-content/uploads/2025/10/ESPANHOL.png',
                ],
            ];
            $currentBanners = $banners[$selectedLanguage] ?? $banners['br'];
            @endphp
            <div class="mt-6 w-full rounded-xl overflow-hidden bg-gray-900">
                <!-- Desktop Banner -->
                <img class="w-full hidden md:block" src="{{ $currentBanners['desktop'] }}" alt="Promotional Banner">
                <!-- Mobile Banner -->
                <img class="w-full md:hidden" src="{{ $currentBanners['mobile'] }}" alt="Promotional Banner">
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
                                        x-on:change="selectedPaymentMethod = 'credit_card'; $nextTick(()=>{ const el = document.querySelector('input[name=card_name]'); if(el) el.focus(); })"
                                        class="hidden"
                                        :checked="selectedPaymentMethod === 'credit_card'" />
                                    <label for="method_credit_card" 
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
                                            x-on:change="selectedPaymentMethod = 'pix'; $nextTick(()=>{ const el = document.querySelector('input[name=pix_name]'); if(el) el.focus(); })"
                                            class="hidden"
                                            :checked="selectedPaymentMethod === 'pix'" />
                                        <label for="method_pix" 
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
                                                class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_number') }}</label>
                                            <input name="card_number" type="text" id="card-number"
                                                placeholder="0000 0000 0000 0000"
                                                wire:model.defer="cardNumber"
                                                inputmode="numeric" autocomplete="cc-number" pattern="[0-9\s]{13,19}"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                            @error('cardNumber')
                                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.expiry_date') }}</label>
                                                <input name="card_expiry" type="text" id="card-expiry"
                                                    placeholder="MM/YY" wire:model.defer="cardExpiry"
                                                    inputmode="numeric" autocomplete="cc-exp" pattern="(0[1-9]|1[0-2])\/([0-9]{2})"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                                @error('cardExpiry')
                                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.security_code') }}</label>
                                                <input name="card_cvv" type="text" id="card-cvv" placeholder="CVV"
                                                    wire:model.defer="cardCvv" inputmode="numeric" autocomplete="cc-csc" pattern="[0-9]{3,4}"
                                                    class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
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
                                            class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.card_name') }}</label>
                                        <input name="card_name" type="text"
                                            placeholder="{{ __('payment.card_name') }}" wire:model.defer="cardName"
                                            autocomplete="cc-name" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @error('cardName')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.email') }}:</label>
                                        <input name="email" type="email" placeholder="{{ __('payment.pix_field_email_hint') }}"
                                            wire:model.live.debounce.500ms="email"
                                            autocomplete="email" inputmode="email" spellcheck="false"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
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
                                        @error('email')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-300 mb-1">{{ __('payment.pix_field_phone_label') }} ({{ __('payment.optional') }}):</label>
                                        <input name="phone" id="phone" type="tel" placeholder="{{ __('payment.pix_field_phone_hint') }}"
                                            wire:model="phone" inputmode="tel" autocomplete="tel"
                                            class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
                                        @error('phone')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    @if ($selectedLanguage === 'br')
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-300 mb-1">CPF</label>
                                            <input name="cpf" type="text"
                                                placeholder="000.000.000-00" wire:model.defer="cpf" inputmode="numeric" autocomplete="off"
                                                class="w-full bg-[#2D2D2D] text-white rounded-lg p-3 border border-gray-700 focus:outline-none focus:ring-1 focus:ring-[#E50914] transition-all" />
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

                    <!-- Order Bumps - Hidden when PIX is selected -->
                    @if (!empty($bumps) && $selectedPaymentMethod !== 'pix')
                        <div class=" bg-[#1F1F1F] rounded-xl p-5 border border-gray-700">
                            @foreach ($bumps as $index => $bump)
                                <div class="flex items-start mb-4 last:mb-0">
                                    <div class="flex items-center h-5">
                                        <input id="order-bump-{{ $bump['id'] }}" type="checkbox"
                                            class="w-5 h-5 text-[#E50914] bg-[#2D2D2D] border-gray-600 rounded
                                       focus:ring-[#E50914] focus:ring-opacity-25 focus:ring-2
                                       focus:border-[#E50914] cursor-pointer"
                                            wire:model="bumps.{{ $index }}.active" wire:change="calculateTotals" />
                                    </div>
                                    <label for="order-bump-{{ $bump['id'] }}" class="ml-3 cursor-pointer">
                                        <div class="text-white text-base font-semibold flex items-center">
                                            <svg class="h-5 w-5 text-[#E50914] mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            {{ $bump['title'] }}
                                        </div>
                                        <p class="text-gray-400 text-sm mt-1">{{ $bump['description'] }}</p>
                                        <p class="text-[#E50914] font-medium mt-2">
                                            +{{ $currencies[$selectedCurrency]['symbol'] }}
                                            {{ number_format($bump['price'], 2, ',', '.') }}
                                        </p>
                                    </label>
                                </div>
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

                        <!-- Conteúdo de Cartão - Oculto quando PIX é selecionado -->
                        @if($selectedPaymentMethod !== 'pix')

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
                                {{ $totals['month_price'] ?? '00' }}</del>
                            <span class="text-green-400 text-lg font-bold ml-2"
                                id="current-price">{{ $currencies[$selectedCurrency]['symbol'] }}
                                {{ $totals['month_price_discount'] ?? '00' }}{{ __('payment.per_month') }}</span>
                        </div>

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
                        @if($usingPixMock)
                        <div class="mb-6 p-4 bg-gradient-to-r from-green-900 via-green-800 to-green-900 rounded-lg border border-green-600 text-white shadow-lg">
                            <div class="flex items-start space-x-3">
                                <svg class="w-6 h-6 text-green-300 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-lg font-bold text-green-100">{{ __('payment.mock_offer_title') }}</p>
                                    <p class="text-sm text-green-200 mt-1">{{ __('payment.mock_offer_subtitle') }}</p>
                                    <div class="mt-3 flex items-center space-x-2">
                                        <del class="text-green-300 opacity-75 text-sm">R$ 49,90</del>
                                        <span class="text-2xl font-bold text-white">R$ 24,90</span>
                                    </div>
                                    <p class="text-xs text-green-200 mt-2">{{ __('payment.mock_savings_text') }}</p>
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
                                    {{ $totals['total_price'] ?? '00' }}</del>
                            </div>
                            <div class="flex justify-between text-mb mb-1">
                                <span>{{ __('payment.discount') }}</span>
                                <span class="text-green-400"
                                    id="discount-amount">{{ $currencies[$selectedCurrency]['symbol'] }}
                                    {{ $totals['total_discount'] ?? '00' }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold mt-2 pt-2 border-t border-gray-700">
                                <span>{{ __('payment.total_to_pay') }}</span>
                                <span id="final-price">
                                    {{ $currencies[$selectedCurrency]['symbol'] }}
                                    {{ $totals['final_price'] ?? '00' }}</span>
                                <input type="hidden" id="input-final-price" name="final-price" value="" />
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
                            class="w-full bg-[#E50914] hover:bg-[#B8070F] text-white py-3 text-lg font-bold rounded-xl transition-all block cursor-pointer transform hover:scale-105">
                            {{ __('checkout.cta_button') }}
                        </button>

                        <!-- Trust badges -->
                        <div class="mt-4 flex flex-col items-center space-y-2">
                            <div class="flex items-center space-x-2">
                                <span class="text-green-500">✅</span>
                                <span class="text-sm text-gray-300">{{ __('payment.7_day_guarantee') }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-green-500">🔒</span>
                                <span class="text-sm text-gray-300">{{ __('payment.secure_ssl') }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-green-500">🔁</span>
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
                                    <span class="text-green-400 font-bold text-2xl">{{ $currencies[$selectedCurrency]['symbol'] }} {{ $totals['final_price'] ?? '24,90' }}</span>
                                </div>
                                <div class="bg-green-500/10 border border-green-500/40 rounded p-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></path></svg>
                                    <span class="text-green-300 text-xs font-semibold">Você economiza: R$ 25,00 hoje!</span>
                                </div>
                            </div>

                            <!-- Timing Info -->
                            <div class="space-y-2 mb-4 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">⏳</span>
                                    <span>A oferta via PIX expira em: <strong>5 minutos</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🕒</span>
                                    <span>O código PIX expira em <strong>10 minutos</strong></span>
                                </div>
                            </div>

                            <!-- Benefits -->
                            <div class="space-y-2 mb-4 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🔓</span>
                                    <span>Acesso liberado imediatamente após o pagamento</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🔁</span>
                                    <span>Plano mensal, cancele quando quiser</span>
                                </div>
                            </div>

                            <!-- Social Proof -->
                            <div class="bg-gray-800/50 rounded-lg p-4 mb-4 space-y-2 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">👥</span>
                                    <span id="buying-count"><strong>{{ rand(12, 47) }}</strong> pessoas estão comprando agora</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">📉</span>
                                    <span id="slots-count">Vagas restantes: <strong>{{ $spotsLeft }}</strong></span>
                                </div>
                            </div>

                            <!-- Trust Badges PIX -->
                            <div class="grid grid-cols-2 gap-2 mb-6 text-xs text-center">
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <div class="text-lg mb-1">✔️</div>
                                    <p class="text-gray-300">Garantia de 7 dias</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <div class="text-lg mb-1">🔒</div>
                                    <p class="text-gray-300">Pagamento seguro SSL</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <div class="text-lg mb-1">❌</div>
                                    <p class="text-gray-300">Cancelamento fácil</p>
                                </div>
                                <div class="bg-gray-800/50 rounded-lg p-3">
                                    <div class="text-lg mb-1">👤</div>
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

                                        <p class="text-gray-100 text-base leading-relaxed">"{{ $testimonial['text'] }}"</p>

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

<!-- PIX Modal - Fully Responsive, No Scroll -->
@if($showPixModal)
<div id="pix-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-2 sm:p-4 animate-fade-in" wire:poll.5s="checkPixPaymentStatus" style="animation: fadeIn 0.5s ease-in-out;">
    <div class="w-full max-w-md md:max-w-lg flex flex-col max-h-[90vh] md:max-h-auto">
        <div class="bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 rounded-2xl md:rounded-3xl overflow-hidden flex flex-col border border-slate-700 shadow-2xl">
            
            <!-- Header - Compact -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-3 py-2.5 sm:px-6 sm:py-4 flex items-center justify-between flex-shrink-0">
                <h1 class="text-base sm:text-xl font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Pagamento PIX
                </h1>
                <button onclick="Livewire.dispatch('call', { method: 'closeModal' })" class="text-white/70 hover:text-white transition text-xl">✕</button>
            </div>

            <!-- Content - Scrollable on mobile only -->
            <div class="overflow-y-auto md:overflow-visible px-3 py-3 sm:px-6 sm:py-4 space-y-2.5 sm:space-y-3">
                
                <!-- Price Section - Ultra Compact -->
                <div class="bg-gradient-to-br from-green-500/10 to-green-600/5 border border-green-500/40 rounded-lg p-2.5 sm:p-4">
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-slate-400 text-xs sm:text-sm font-medium truncate pr-2">{{ str_replace(['1/month', '1 mês', '1/mes'], '1x/mês', $product['title'] ?? 'Streaming Snaphubb - 1x/mês') }}</span>
                        <span class="text-slate-500 line-through text-xs sm:text-sm flex-shrink-0">R$ 49,90</span>
                    </div>
                    <div class="flex justify-between items-end gap-2">
                        <div class="min-w-0">
                            <p class="text-green-300 text-xs font-semibold leading-tight">🎉 Desconto PIX</p>
                            <p class="text-green-200 text-xs mt-0.5 leading-tight">Economize R$ 25</p>
                        </div>
                        <p class="text-2xl sm:text-4xl font-bold text-green-400 flex-shrink-0">{{ $currencies[$selectedCurrency]['symbol'] ?? 'R$' }} {{ $totals['final_price'] ?? '24,90' }}</p>
                    </div>
                </div>

                <!-- QR Code Section - Optimized Size -->
                <div class="flex flex-col items-center gap-1.5 bg-slate-800/50 rounded-lg p-2.5 sm:p-4 border border-slate-700">
                    <p class="text-white font-semibold text-xs sm:text-sm">Escaneie o QR Code</p>
                    <div class="bg-white p-2 sm:p-3 rounded-lg shadow-lg">
                        @if(!empty($pixQrImage))
                            <img src="data:image/png;base64,{{ $pixQrImage }}" alt="PIX QR Code" class="w-20 h-20 sm:w-32 sm:h-32 md:w-40 md:h-40" />
                        @else
                            <svg class="w-20 h-20 sm:w-32 sm:h-32 md:w-40 md:h-40" viewBox="0 0 200 200"><rect width="200" height="200" fill="white"/><g fill="black"><rect x="10" y="10" width="40" height="40"/><rect x="150" y="10" width="40" height="40"/><rect x="10" y="150" width="40" height="40"/><rect x="60" y="30" width="8" height="8"/><rect x="70" y="30" width="8" height="8"/><rect x="60" y="45" width="8" height="8"/><rect x="75" y="45" width="8" height="8"/></g></svg>
                        @endif
                    </div>
                    <p class="text-slate-300 text-xs text-center leading-tight">Aponte a câmera<br/>do seu banco</p>
                </div>

                <!-- Copy Code Section -->
                <div class="bg-slate-800/50 rounded-lg p-2.5 sm:p-4 border border-slate-700" id="pix-copy-section">
                    <p class="text-slate-300 text-xs font-semibold mb-1.5 block">Ou copie o código:</p>
                    <div class="bg-slate-950 border-2 border-slate-600 rounded p-1.5 sm:p-2 font-mono text-slate-200 text-xs break-all overflow-x-auto max-h-14 overflow-y-auto mb-2 leading-relaxed">{{ $pixQrCodeText ?? 'Código PIX' }}</div>
                    <button id="copy-pix-btn" class="w-full py-2 sm:py-3 px-3 sm:px-6 rounded-lg font-bold text-xs sm:text-base transition-all duration-300 flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white shadow-lg active:scale-95" onclick="copyPixCode()">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span id="copy-text">Copiar código</span>
                    </button>
                </div>

                <!-- Reassurance Section -->
                <div class="bg-blue-500/10 border border-blue-500/40 rounded-lg p-2 sm:p-3 flex items-start gap-2">
                    <svg class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.338 5.59a6.79 6.79 0 010 9.647A6.811 6.811 0 015 12.5c0-1.995.822-3.815 2.149-5.09.937-.957 2.116-1.687 3.352-2.053A6.902 6.902 0 0112 3a6.94 6.94 0 015.024 2.078l.896.895.708-.707A6.5 6.5 0 008.854 2.5a8.5 8.5 0 00-3.516 8z" clip-rule="evenodd"/></svg>
                    <div class="min-w-0">
                        <p class="text-blue-200 text-xs sm:text-sm font-semibold leading-tight">Protegido com SSL</p>
                        <p class="text-blue-300/70 text-xs mt-0.5 leading-tight">Seus dados são 100% seguros</p>
                    </div>
                </div>

                <!-- Status Info -->
                <div class="bg-amber-500/10 border border-amber-500/40 rounded-lg p-2 sm:p-3">
                    <div class="flex items-start gap-2">
                        <div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse mt-1 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-amber-200 font-semibold text-xs sm:text-sm leading-tight">Aguardando confirmação</p>
                            <p class="text-amber-100/80 text-xs leading-snug mt-0.5">✓ Confirmação em segundos<br/>✓ Acesso imediato<br/>✓ Suporte 24/7</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
function copyPixCode() {
    const pixCodeElement = document.querySelector('#pix-copy-section > div');
    const pixCode = pixCodeElement ? pixCodeElement.textContent.trim() : '';
    
    if (!pixCode || pixCode === 'Código PIX') {
        alert('Código PIX não disponível');
        return;
    }

    const btn = document.getElementById('copy-pix-btn');
    const icon = document.getElementById('copy-icon');
    const text = document.getElementById('copy-text');
    const originalText = text.textContent;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pixCode)
            .then(() => {
                text.textContent = '✓ Código copiado!';
                btn.classList.add('bg-green-600');
                btn.classList.remove('hover:bg-green-600');
                
                setTimeout(() => {
                    text.textContent = originalText;
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('hover:bg-green-600');
                }, 2000);
            })
            .catch(() => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = pixCode;
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    text.textContent = '✓ Código copiado!';
                    setTimeout(() => {
                        text.textContent = originalText;
                    }, 2000);
                } catch (err) {
                    alert('Erro ao copiar. Copie manualmente o código acima.');
                }
                document.body.removeChild(textarea);
            });
    }
}
</script>

<!-- Error Modal -->
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
</script>
@endif
@endpush
</div>
