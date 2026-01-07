<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payment.support') }} - SNAPHUBB</title>
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
                <h2 class="text-3xl font-bold text-gray-900 mb-8">{{ __('payment.support') }}</h2>
                
                <div class="space-y-8">
                    <!-- FAQ Section -->
                    <section class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('payment.faq') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">{{ __('payment.faq_payment') }}</h4>
                                <p class="text-gray-600">{{ __('payment.faq_payment_answer') }}</p>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">{{ __('payment.faq_secure') }}</h4>
                                <p class="text-gray-600">{{ __('payment.faq_secure_answer') }}</p>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">{{ __('payment.faq_issue') }}</h4>
                                <p class="text-gray-600">{{ __('payment.faq_issue_answer') }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- Contact Section -->
                    <section class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('payment.contact_support') }}</h3>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                </svg>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ __('payment.email') }}</p>
                                    <a href="mailto:support@snaphubb.com" class="text-blue-600 hover:text-blue-800">support@snaphubb.com</a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Response Time -->
                    <section class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('payment.response_time') }}</h3>
                        <p class="text-gray-700">{{ __('payment.response_time_text') }}</p>
                    </section>
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
