<?php

require 'vendor/autoload.php';

$env_file = '.env';
$env_vars = [];

foreach (file($env_file) as $line) {
    $line = trim($line);
    if (empty($line) || $line[0] === '#') continue;
    
    [$key, $value] = explode('=', $line, 2) + [null, null];
    if ($key && $value) {
        $env_vars[$key] = trim($value, '"\'');
    }
}

$db_host = $env_vars['DB_HOST'] ?? '127.0.0.1';
$db_user = $env_vars['DB_USERNAME'] ?? 'root';
$db_pass = $env_vars['DB_PASSWORD'] ?? '';
$db_name = $env_vars['DB_DATABASE'] ?? 'snaphubb-digital';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar se tabela existe
    $result = $pdo->query("SHOW TABLES LIKE 'orders'")->fetch();
    
    if ($result) {
        echo "❌ Tabela 'orders' já existe!\n";
    } else {
        $sql = <<<SQL
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` bigint unsigned NOT NULL,
  `plan` varchar(255) NOT NULL,
  `currency` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `pix_id` varchar(255) NULL,
  `external_payment_id` varchar(255) NULL,
  `payment_status` varchar(255) DEFAULT 'pending',
  `status` varchar(255) DEFAULT 'pending',
  `paid_at` timestamp NULL,
  `external_payment_status` varchar(255) NULL,
  `amount` decimal(10,2) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  KEY `user_id` (`user_id`),
  KEY `pix_id` (`pix_id`),
  KEY `external_payment_id` (`external_payment_id`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $pdo->exec($sql);
        echo "✅ Tabela 'orders' criada com sucesso!\n";
        
        // Criar índices únicos se precisar
        try {
            $pdo->exec("ALTER TABLE orders ADD UNIQUE INDEX unique_pix_id (pix_id)");
        } catch (Exception $e) {
            // Índice pode já existir
        }
        
        try {
            $pdo->exec("ALTER TABLE orders ADD UNIQUE INDEX unique_external_payment_id (external_payment_id)");
        } catch (Exception $e) {
            // Índice pode já existir
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}
