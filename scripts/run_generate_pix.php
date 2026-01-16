<?php
// Script de teste: inicializa o app Laravel e chama PagePay->generatePix()
chdir(__DIR__ . '/..');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Livewire\PagePay;
use Illuminate\Support\Facades\Log;

try {
    $pp = new PagePay();

    // Forçar idioma/plan/bump ativo para fluxo PIX
    $pp->selectedLanguage = 'br';
    $pp->selectedCurrency = 'BRL';
    $pp->selectedPlan = 'monthly';
    $pp->email = 'test+pix@local.test';
    $pp->cardName = 'Teste Pix';

    // Mount para executar inicializações do componente
    if (method_exists($pp, 'mount')) {
        $pp->mount();
    }

    // Recarregar planos e bumps do backend
    if (method_exists($pp, 'getPlans')) {
        $pp->plans = $pp->getPlans();
    }
    if (method_exists($pp, 'loadBumpsByMethod')) {
        $pp->loadBumpsByMethod('pix');
    }

    // Recalcular totals e gerar PIX
    if (method_exists($pp, 'calculateTotals')) {
        $pp->calculateTotals();
    }

    if (method_exists($pp, 'generatePix')) {
        $pp->generatePix();
        echo "generatePix() called\n";
    } else {
        echo "generatePix() not available\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
