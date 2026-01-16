<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parabéns - Snaphubb (PIX)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
</head>
<body class="bg-black text-white min-h-screen">
    <!-- Reuse thank.blade.php content but indicate PIX/QR context where appropriate -->

    <div class="relative z-10 max-w-4xl mx-auto px-6 py-12 md:py-24">
        <div class="border-b border-red-900/30 pb-8 mb-12">
            <h1 class="text-2xl font-bold text-red-600">SNAPHUBB (PIX)</h1>
        </div>

        <div class="text-center mb-16">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-full mb-8 animate-bounce">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h2 class="text-5xl md:text-6xl font-bold mb-4">Parabéns! 🎉</h2>
            <p class="text-xl md:text-2xl text-gray-300 mb-2">Seu pagamento via PIX foi confirmado com sucesso</p>
            <p class="text-gray-500 mb-8">Obrigado por usar PIX — aproveite seu acesso.</p>
        </div>

        <!-- Reuse rest of thank page summary -->
        @include('upsell._thank_summary')

    </div>

    <script>
        // minimal script to fire analytics similar to thank.blade.php
    </script>
</body>
</html>
