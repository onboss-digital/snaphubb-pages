<?php
// Simple verifier for pages_upsell_product_external_id
$url = 'http://127.0.0.1:8000/api/get-plans?lang=br';
$currentUrl = 'http://127.0.0.1:8002/upsell/painel-das-garotas-card';
$hashCandidate = 'painel_das_garotas';

try {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        echo "ERROR: could not fetch $url\n";
        exit(2);
    }
    $raw = json_decode($json, true);
    $plans = $raw['data'] ?? $raw;
    if (!is_array($plans)) {
        echo "ERROR: unexpected response format\n";
        exit(3);
    }
    $found = null;
    foreach ($plans as $p) {
        if (!is_array($p)) continue;
        if (!empty($p['pages_upsell_url'])) {
            $u = $p['pages_upsell_url'];
            if (strpos($currentUrl, $u) !== false || strpos($u, $currentUrl) !== false) {
                $found = $p; break;
            }
        }
    }
    if (!$found) {
        foreach ($plans as $p) {
            if (!is_array($p)) continue;
            if ((isset($p['hash']) && $p['hash'] == $hashCandidate)
                || (isset($p['identifier']) && $p['identifier'] == $hashCandidate)
                || (isset($p['external_id']) && $p['external_id'] == $hashCandidate)) {
                $found = $p; break;
            }
        }
    }
    if (!$found) {
        echo "No matching plan found for URL or hash.\n";
        exit(0);
    }
    $upsellProd = $found['pages_upsell_product_external_id'] ?? $found['pages_upsell_product_external'] ?? null;
    $mainProd = $found['pages_product_external_id'] ?? $found['external_id'] ?? null;
    echo "Matched plan identifier: " . ($found['identifier'] ?? ($found['hash'] ?? 'n/a')) . "\n";
    echo "pages_upsell_product_external_id: " . ($upsellProd ?? 'null') . "\n";
    echo "pages_product_external_id: " . ($mainProd ?? 'null') . "\n";
    if ($upsellProd) {
        echo "OK: upsell product external id will be used.\n";
    } else {
        echo "WARNING: no upsell product external id found; fallback to hash.\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(4);
}
