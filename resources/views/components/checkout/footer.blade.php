<footer class="bg-black text-gray-400 py-6 border-t border-gray-800">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
            <div class="flex items-center space-x-4">
                <img src="{{ asset('images/icons/ssl.svg') }}" alt="SSL" class="h-5 md:h-6">
                <img src="{{ asset('images/icons/stripe.svg') }}" alt="Stripe" class="h-5 md:h-6">
                <img src="{{ asset('images/icons/mercado-pago.svg') }}" alt="Mercado Pago" class="h-5 md:h-6">
                <img src="{{ asset('images/icons/visa.svg') }}" alt="Visa" class="h-5 md:h-6">
                <img src="{{ asset('images/icons/mastercard.svg') }}" alt="Mastercard" class="h-5 md:h-6">
            </div>

            <p class="text-sm text-center">{{ __('footer.secure_payment') }}</p>

            <div class="text-sm text-center">
                <span>🛡️</span>
                <span>{{ __('footer.certified_by') }}</span>
            </div>
        </div>

        <div class="mt-6 text-center space-x-4 text-xs">
            <a href="#" class="hover:text-white">{{ __('footer.terms_of_use') }}</a>
            <span>|</span>
            <a href="#" class="hover:text-white">{{ __('footer.privacy_policy') }}</a>
            <span>|</span>
            <a href="#" class="hover:text-white">{{ __('footer.support') }}</a>
        </div>

        <div class="mt-4 text-center text-xs text-gray-500">
            <p>{{ __('footer.copyright', ['year' => date('Y')]) }}</p>
            <p class="mt-1">{{ __('footer.legal_notice') }}</p>
        </div>
    </div>
</footer>
