<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $affected = DB::table('plan')->where('pages_upsell_url','like','%painel-das-garotas%')
        ->orWhere('identifier','monthly')
        ->update(['pages_upsell_succes_url' => 'http://127.0.0.1:8003/upsell/painel-das-garotas-qr']);
    echo "UPDATED: $affected\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
