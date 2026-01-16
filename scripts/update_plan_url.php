<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (class_exists('\Modules\Subscriptions\Models\Plan')) {
    $p = \Modules\Subscriptions\Models\Plan::where('identifier','monthly')->first();
    if (!$p) {
        $p = \Modules\Subscriptions\Models\Plan::where('pages_upsell_url','like','%painel-das-garotas%')->first();
    }
    if ($p) {
        $old = $p->pages_upsell_succes_url ?? '(null)';
        $p->pages_upsell_succes_url = 'http://127.0.0.1:8003/upsell/painel-das-garotas-qr';
        $p->save();
        echo "UPDATED\nOld: $old\nNew: " . $p->pages_upsell_succes_url . "\n";
    } else {
        echo "PLAN_NOT_FOUND\n";
    }
} else {
    echo "PLAN_CLASS_NOT_FOUND\n";
}
