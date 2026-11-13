-- Fitur Lupa Password (reset lewat link email).
-- Jalankan satu kali di phpMyAdmin (tab SQL). Backup database dulu.

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,      -- 64 karakter heksadesimal acak
  `expires_at` datetime NOT NULL,                             -- batas waktu (15 menit setelah dibuat)
  `used_at` datetime DEFAULT NULL,                            -- terisi saat token sudah dipakai
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_token_unique` (`token`),
  KEY `password_resets_user_index` (`user_id`),
  KEY `password_resets_expires_index` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
