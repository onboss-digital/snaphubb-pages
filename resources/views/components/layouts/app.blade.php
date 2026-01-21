<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Snaphubb Checkout' }}</title>

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
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .sticky-summary {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            transform: translateY(0);
            transition: none;
        }
    </style>

    <!-- Microsoft Clarity -->
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
        function gtag(){dataLayer.push(arguments);} 
        gtag('js', new Date());
        gtag('config', 'G-G6FBHCNW8X');
    </script>

    @include('partials.analytics')

    {{-- Alpine PRIMEIRO --}}
    <script defer src="https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js"></script>

    {{-- Livewire PRIMEIRO --}}
    @livewireStyles
    @livewireScripts

    {{-- Vite POR ÚLTIMO --}}
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pages/pay.js'])

    @stack('head')
</head>

<body>
    {{ $slot }}

    @stack('scripts')

    {{-- FORÇAR BOOT DO LIVEWIRE (Desativado para evitar execução duplicada) --}}
    {{--
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.Livewire && typeof window.Livewire.start === 'function') {
                console.log('🔥 Livewire.start() forçado');
                window.Livewire.start();
            } else {
                console.error('❌ Livewire não disponível no DOMContentLoaded');
            }
        });
    </script>
    --}}
</body>
</html>
