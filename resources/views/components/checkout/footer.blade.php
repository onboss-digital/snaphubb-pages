<footer class="bg-black text-gray-400 py-8 border-t border-gray-800">
    <div class="container mx-auto px-4 text-center">
        <!-- Ícones -->
        <div class="flex justify-center items-center space-x-4 mb-4">
            <!-- SSL -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <!-- Stripe -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" />
            </svg>
            <!-- Mercado Pago -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm3.41 13.59L12 12.17l-3.41 3.42L7.17 14l3.42-3.41L7.17 7.17 8.59 5.76 12 9.17l3.41-3.41L16.83 7.17l-3.41 3.42 3.41 3.41-1.42 1.42z" />
            </svg>
            <!-- Visa -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21.3,7.59c-0.34-0.91-1.2-1.59-2.2-1.59H4.9C3.8,6,2.94,6.68,2.6,7.59L1.6,10.6c-0.12,0.32-0.12,0.67,0,1L2.6,14.5c0.34,0.91,1.2,1.59,2.2,1.59H19.1c1,0,1.86-0.68,2.2-1.59l1-3.1c0.12-0.32,0.12-0.67,0-1L21.3,7.59z M19.1,14.5H4.9c-0.31,0-0.58-0.21-0.67-0.5l-1-3.1c-0.04-0.1-0.04-0.22,0-0.32l1-3.1c0.09-0.29,0.36-0.5,0.67-0.5H19.1c0.31,0,0.58,0.21,0.67,0.5l1,3.1c0.04,0.1,0.04,0.22,0,0.32l-1,3.1C19.68,14.29,19.41,14.5,19.1,14.5z" />
            </svg>
            <!-- Mastercard -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24">
                <circle cx="10" cy="12" r="6" fill="#EA001B" />
                <circle cx="14" cy="12" r="6" fill="#F79E1B" />
            </svg>
        </div>

        <!-- Texto de Segurança -->
        <p class="text-sm mb-2">{{ __('payment.secure_payment') }}</p>

        <!-- Certificação -->
        <div class="text-sm mb-4">
            <span>🛡️</span>
            <span>{{ __('payment.certified_by') }}</span>
        </div>

        <!-- Links -->
        <div class="space-x-4 text-xs mb-4">
            <a href="#" class="hover:text-white">{{ __('payment.terms_of_use') }}</a>
            <span>|</span>
            <a href="#" class="hover:text-white">{{ __('payment.privacy_policy') }}</a>
            <span>|</span>
            <a href="#" class="hover:text-white">{{ __('payment.support') }}</a>
        </div>

        <!-- Direitos Autorais e Aviso Legal -->
        <div class="text-xs text-gray-500">
            <p>{{ __('payment.copyright', ['year' => date('Y')]) }}</p>
            <p class="mt-1">{{ __('payment.legal_notice') }}</p>
        </div>
    </div>
</footer>
