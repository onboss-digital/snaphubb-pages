<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upsell Recusado (PIX) - Snaphubb</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
</head>
<body class="bg-black text-white min-h-screen">
    <div class="relative z-10 max-w-4xl mx-auto px-6 py-12 md:py-24">
        <div class="border-b border-red-900/30 pb-8 mb-12">
            <h1 class="text-2xl font-bold text-red-600">SNAPHUBB (PIX)</h1>
        </div>

        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-4">Oferta recusada</h2>
            <p class="text-gray-400 mb-6">Você optou por não aceitar a oferta adicional. Aproveite seu acesso principal.</p>
        </div>

        @include('upsell._thank_summary')

    </div>
</body>
</html>
