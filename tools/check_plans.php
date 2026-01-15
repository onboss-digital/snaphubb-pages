<?php
$urls = [
    'http://127.0.0.1:8000/api/plans',
    'http://127.0.0.1:8000/plans',
    'http://127.0.0.1:8002/api/plans',
    'http://127.0.0.1:8002/plans',
];
foreach ($urls as $url) {
    echo "=== $url ===\n";
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'header' => "Accept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $context = stream_context_create($opts);
    $start = microtime(true);
    $result = @file_get_contents($url, false, $context);
    $elapsed = round((microtime(true) - $start) * 1000);
    if ($result === false) {
        $err = error_get_last();
        echo "ERROR: " . ($err['message'] ?? 'unknown') . " (took {$elapsed}ms)\n\n";
    } else {
        echo "OK (took {$elapsed}ms):\n";
        // print first 1200 chars to avoid flooding
        echo substr($result, 0, 1200) . (strlen($result) > 1200 ? "\n...truncated...\n" : "\n");
        echo "\n";
    }
}
