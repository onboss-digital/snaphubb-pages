-- Criar tabela de orders se não existir
CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` bigint unsigned NOT NULL,
  `plan` varchar(255) NOT NULL,
  `currency` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `pix_id` varchar(255) NULL UNIQUE INDEX,
  `external_payment_id` varchar(255) NULL UNIQUE INDEX,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
