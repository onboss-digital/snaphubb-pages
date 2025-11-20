<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oferta Exclusiva - Snaphubb</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('partials.analytics')
</head>
<body class="bg-black text-white min-h-screen">
    <livewire:upsell-offer />
    @livewireScripts
</body>
</html>
