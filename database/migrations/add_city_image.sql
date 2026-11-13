-- Jalankan satu kali pada database yang sudah memiliki tabel cities.
ALTER TABLE `cities`
  ADD COLUMN `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
  AFTER `description`;
