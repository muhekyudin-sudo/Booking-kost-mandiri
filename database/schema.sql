-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 16, 2026 at 03:15 AM
-- Server version: 8.0.35
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kost_mandiri`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint UNSIGNED NOT NULL,
  `pnr_code` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `room_unit_id` bigint UNSIGNED NOT NULL,
  `duration_type` enum('daily','weekly','monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_value` int NOT NULL DEFAULT '1',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `payment_scheme` enum('full','dp') COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remaining_due_date` date DEFAULT NULL,
  `status` enum('pending_payment','waiting_approval','active','completed','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `booking_type` enum('online','offline') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `created_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `created_by_staff_id` bigint UNSIGNED DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, 'Yogyakarta', 'yogyakarta', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 'Semarang', 'semarang', '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `invoice_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type` enum('dp_50','full_100','pelunasan_dp','recurring_monthly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('qris','cash') COLLATE utf8mb4_unicode_ci NOT NULL,
  `proof_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `approved_by_staff_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `price_matrices`
--

CREATE TABLE `price_matrices` (
  `id` bigint UNSIGNED NOT NULL,
  `room_class_id` bigint UNSIGNED NOT NULL,
  `daily_price` decimal(12,2) DEFAULT NULL,
  `weekly_price` decimal(12,2) DEFAULT NULL,
  `monthly_price` decimal(12,2) DEFAULT NULL,
  `yearly_price` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_matrices`
--

INSERT INTO `price_matrices` (`id`, `room_class_id`, `daily_price`, `weekly_price`, `monthly_price`, `yearly_price`, `created_at`, `updated_at`) VALUES
(1, 1, '75000.00', '400000.00', '1200000.00', '13000000.00', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 2, '150000.00', '850000.00', '2500000.00', '27000000.00', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(3, 3, '50000.00', '300000.00', '900000.00', '10000000.00', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(4, 4, '200000.00', '1100000.00', '3500000.00', '38000000.00', '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` bigint UNSIGNED NOT NULL,
  `city_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `city_id`, `name`, `address`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kost A Yogyakarta', 'Jl. Contoh No. 1, Yogyakarta', 'Kost campur dekat kampus.', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 2, 'Kost B Semarang', 'Jl. Contoh No. 2, Semarang', 'Kost dekat pusat kota.', '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `receptionist_staff`
--

CREATE TABLE `receptionist_staff` (
  `id` bigint UNSIGNED NOT NULL,
  `property_id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receptionist_staff`
--

INSERT INTO `receptionist_staff` (`id`, `property_id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Budi', 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 1, 'Sari', 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(3, 2, 'Andi', 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(4, 2, 'Rina', 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `room_classes`
--

CREATE TABLE `room_classes` (
  `id` bigint UNSIGNED NOT NULL,
  `property_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `facilities` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_classes`
--

INSERT INTO `room_classes` (`id`, `property_id`, `name`, `description`, `facilities`, `created_at`, `updated_at`) VALUES
(1, 1, 'Standard', 'Kamar standar dengan kasur dan kipas.', '[\"Kasur\", \"Lemari\", \"Kipas Angin\", \"WiFi\"]', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 1, 'Deluxe', 'Kamar full furnished dengan AC.', '[\"Kasur\", \"Lemari\", \"AC\", \"Kamar Mandi Dalam\", \"WiFi\"]', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(3, 2, 'Kosongan', 'Kamar kosong tanpa perabot.', '[\"Kamar Mandi Dalam\"]', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(4, 2, 'Executive', 'Kamar premium lengkap.', '[\"Kasur\", \"Lemari\", \"AC\", \"Kamar Mandi Dalam\", \"Water Heater\", \"WiFi\", \"TV\"]', '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `room_units`
--

CREATE TABLE `room_units` (
  `id` bigint UNSIGNED NOT NULL,
  `room_class_id` bigint UNSIGNED NOT NULL,
  `room_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('available','on_hold','occupied','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_units`
--

INSERT INTO `room_units` (`id`, `room_class_id`, `room_number`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '101', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 1, '102', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(3, 1, '103', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(4, 2, '201', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(5, 2, '202', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(6, 3, '101', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(7, 3, '102', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(8, 4, '301', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(9, 4, '302', 'available', '2026-10-15 03:44:04', '2026-10-15 03:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','tenant','receptionist') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tenant',
  `property_id` bigint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `google_id`, `phone`, `role`, `property_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Pemilik (Admin)', 'admin@kost.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '081200000001', 'admin', NULL, 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(2, 'Resepsionis Kost A', 'resepsionis.a@kost.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '081200000002', 'receptionist', 1, 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(3, 'Resepsionis Kost B', 'resepsionis.b@kost.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '081200000003', 'receptionist', 2, 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04'),
(4, 'Penyewa Contoh', 'penyewa@kost.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '081200000004', 'tenant', NULL, 1, '2026-10-15 03:44:04', '2026-10-15 03:44:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bookings_pnr` (`pnr_code`),
  ADD UNIQUE KEY `uq_bookings_code` (`booking_code`),
  ADD KEY `idx_pnr` (`pnr_code`),
  ADD KEY `idx_bookings_status_expires` (`status`,`expires_at`),
  ADD KEY `idx_bookings_user` (`user_id`),
  ADD KEY `idx_bookings_unit` (`room_unit_id`),
  ADD KEY `fk_bookings_created_by` (`created_by_user_id`),
  ADD KEY `fk_bookings_created_staff` (`created_by_staff_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cities_slug` (`slug`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_invoice` (`invoice_code`),
  ADD KEY `idx_payments_booking` (`booking_id`),
  ADD KEY `idx_payments_status` (`status`),
  ADD KEY `fk_payments_approved_user` (`approved_by_user_id`),
  ADD KEY `fk_payments_approved_staff` (`approved_by_staff_id`);

--
-- Indexes for table `price_matrices`
--
ALTER TABLE `price_matrices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_price_room_class` (`room_class_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_properties_city` (`city_id`);

--
-- Indexes for table `receptionist_staff`
--
ALTER TABLE `receptionist_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_property` (`property_id`);

--
-- Indexes for table `room_classes`
--
ALTER TABLE `room_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_classes_property` (`property_id`);

--
-- Indexes for table `room_units`
--
ALTER TABLE `room_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unit_class_number` (`room_class_id`,`room_number`),
  ADD KEY `idx_units_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_property` (`property_id`),
  ADD UNIQUE KEY `uq_users_google_id` (`google_id`),
  ADD KEY `idx_users_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_matrices`
--
ALTER TABLE `price_matrices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receptionist_staff`
--
ALTER TABLE `receptionist_staff`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_classes`
--
ALTER TABLE `room_classes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_units`
--
ALTER TABLE `room_units`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_created_staff` FOREIGN KEY (`created_by_staff_id`) REFERENCES `receptionist_staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_unit` FOREIGN KEY (`room_unit_id`) REFERENCES `room_units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_approved_staff` FOREIGN KEY (`approved_by_staff_id`) REFERENCES `receptionist_staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_approved_user` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_matrices`
--
ALTER TABLE `price_matrices`
  ADD CONSTRAINT `fk_price_room_class` FOREIGN KEY (`room_class_id`) REFERENCES `room_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_properties_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receptionist_staff`
--
ALTER TABLE `receptionist_staff`
  ADD CONSTRAINT `fk_staff_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_classes`
--
ALTER TABLE `room_classes`
  ADD CONSTRAINT `fk_room_classes_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_units`
--
ALTER TABLE `room_units`
  ADD CONSTRAINT `fk_units_room_class` FOREIGN KEY (`room_class_id`) REFERENCES `room_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL;
--
-- Constraint CHECK (hilang pada export phpMyAdmin): DP tidak untuk sewa harian
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `chk_dp_not_daily` CHECK (NOT (`duration_type` = 'daily' AND `payment_scheme` = 'dp'));

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
