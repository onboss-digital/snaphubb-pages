<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payment.privacy_policy') }} - SNAPHUBB</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-4xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">SNAPHUBB</h1>
                    <a href="{{ route('home') }}" class="text-blue-600 hover:text-blue-800">← {{ __('payment.return') }}</a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="max-w-4xl mx-auto px-4 py-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">{{ __('payment.privacy_policy') }}</h2>
                
                <div class="prose prose-sm max-w-none text-gray-700">
                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">1. {{ __('payment.information_collection') }}</h3>
                        <p>{{ __('payment.privacy_collection_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">2. {{ __('payment.information_usage') }}</h3>
                        <p>{{ __('payment.privacy_usage_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">3. {{ __('payment.data_protection') }}</h3>
                        <p>{{ __('payment.privacy_protection_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">4. {{ __('payment.cookies') }}</h3>
                        <p>{{ __('payment.privacy_cookies_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">5. {{ __('payment.third_parties') }}</h3>
                        <p>{{ __('payment.privacy_third_party_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">6. {{ __('payment.user_rights') }}</h3>
                        <p>{{ __('payment.privacy_rights_content') }}</p>
                    </section>

                    <section class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">7. {{ __('payment.contact_us') }}</h3>
                        <p>{{ __('payment.privacy_contact_content') }}</p>
                    </section>
                </div>

                <div class="mt-12 pt-8 border-t border-gray-200">
                    <p class="text-sm text-gray-500">{{ __('payment.privacy_last_updated') }}: January 2025</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white mt-12">
            @include('components.checkout.footer')
        </footer>
    </div>
</body>
</html>
