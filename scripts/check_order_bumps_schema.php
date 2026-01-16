<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=snaphubb-digital;charset=utf8mb4','snaphubb-onboss','HR0pmVdut6KigwLjkzNn');
    $stmt = $pdo->query('SHOW COLUMNS FROM order_bumps');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
