<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Page Title' }}</title>

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

    <!-- Facebook Pixel -->
    <script>
        !(function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)})(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
        try{fbq('init', '611486964514786');fbq('consent','grant');}catch(e){console.warn('FB Pixel init failed', e);}
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=611486964514786&ev=PageView&noscript=1"/></noscript>

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pages/pay.js'])

    <!-- Alpine.js for lightweight reactivity used by PIX modal -->
    <script defer src="https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js"></script>

    @stack('head')
    @livewireStyles

</head>

<body>
    {{ $slot }}
    @stack('scripts')
    @livewireScripts
</body>

</html>