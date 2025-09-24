-- --------------------------------------------------------
-- Host:                         vps2.masbudi.my.id
-- Server version:               8.0.40-0ubuntu0.20.04.1 - (Ubuntu)
-- Server OS:                    Linux
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for appsholat
CREATE DATABASE IF NOT EXISTS `appsholat` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `appsholat`;

-- Dumping structure for table appsholat.jendela_sholat
CREATE TABLE IF NOT EXISTS `jendela_sholat` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `dow` tinyint unsigned NOT NULL,
  `sholat` enum('dzuhur','ashar') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jendela` (`dow`,`sholat`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.jendela_sholat: ~10 rows (approximately)
INSERT IGNORE INTO `jendela_sholat` (`id`, `dow`, `sholat`, `jam_mulai`, `jam_selesai`, `aktif`) VALUES
	(1, 1, 'dzuhur', '11:50:00', '13:00:00', 1),
	(2, 1, 'ashar', '15:15:00', '15:40:00', 1),
	(3, 2, 'dzuhur', '11:30:00', '13:00:00', 1),
	(4, 2, 'ashar', '15:15:00', '15:40:00', 1),
	(5, 3, 'dzuhur', '12:00:00', '12:25:00', 1),
	(6, 3, 'ashar', '15:15:00', '17:40:00', 1),
	(7, 4, 'dzuhur', '12:00:00', '12:25:00', 1),
	(8, 4, 'ashar', '15:15:00', '15:40:00', 1),
	(9, 5, 'dzuhur', '00:00:00', '12:25:00', 1),
	(10, 5, 'ashar', '15:15:00', '15:40:00', 1);

-- Dumping structure for table appsholat.kehadiran
CREATE TABLE IF NOT EXISTS `kehadiran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `sholat` enum('dzuhur','ashar') COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_scan` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `path_foto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_perangkat` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agen_pengguna` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `akurasi_lokasi` int DEFAULT NULL,
  `status_verifikasi` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `alasan` text COLLATE utf8mb4_unicode_ci,
  `diverifikasi_oleh` int unsigned DEFAULT NULL,
  `waktu_verifikasi` datetime DEFAULT NULL,
  `catatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_hari_sholat` (`user_id`,`tanggal`,`sholat`),
  KEY `fk_kehadiran_verifier` (`diverifikasi_oleh`),
  KEY `idx_kehadiran_tanggal` (`tanggal`),
  KEY `idx_kehadiran_sholat` (`sholat`),
  KEY `idx_kehadiran_status` (`status_verifikasi`),
  CONSTRAINT `fk_kehadiran_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_kehadiran_verifier` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.kehadiran: ~4 rows (approximately)
INSERT IGNORE INTO `kehadiran` (`id`, `user_id`, `tanggal`, `sholat`, `waktu_scan`, `path_foto`, `hash_token`, `id_perangkat`, `agen_pengguna`, `ip`, `lat`, `lng`, `akurasi_lokasi`, `status_verifikasi`, `alasan`, `diverifikasi_oleh`, `waktu_verifikasi`, `catatan`, `dibuat_pada`, `diperbarui_pada`) VALUES
	(1, 1, '2025-09-19', 'dzuhur', '2025-09-19 01:09:33', '20250919_dzuhur_1_1758218973.png', 'afe6b11fd9374e5b33c8e2c586910113', 'nqhqgamuqk8mfppv6u2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', '114.10.122.132', -6.983809, 110.409989, 97989, 'approved', NULL, 1, '2025-09-19 01:16:42', NULL, '2025-09-19 01:09:33', '2025-09-19 01:16:42'),
	(3, 5, '2025-09-19', 'dzuhur', '2025-09-19 01:24:00', '20250919_dzuhur_5_1758219840.jpg', 'afe6b11fd9374e5b33c8e2c586910113', 'nqhqgamuqk8mfppv6u2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', '114.10.122.132', -6.983809, 110.409989, 97989, 'pending', NULL, NULL, NULL, NULL, '2025-09-19 01:24:00', NULL),
	(4, 5, '2025-09-23', 'dzuhur', '2025-09-23 11:35:18', '20250923_dzuhur_5_1758602118.jpg', 'd8b47f73706bcde8eabb48d2120a772d', 'bm1sjleh92amfppsvfp', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '103.19.37.15', -6.991694, 110.350124, 13, 'approved', NULL, 1, '2025-09-23 11:37:16', NULL, '2025-09-23 11:35:18', '2025-09-23 11:37:16'),
	(5, 4, '2025-09-23', 'dzuhur', '2025-09-23 11:41:12', '20250923_dzuhur_4_1758602472.jpg', 'd8b47f73706bcde8eabb48d2120a772d', 'lpca30negmmfw2hr6f', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '114.10.124.222', 0.000000, 0.000000, 0, 'approved', NULL, 1, '2025-09-23 11:42:34', NULL, '2025-09-23 11:41:12', '2025-09-23 11:42:34'),
	(6, 1, '2025-09-24', 'ashar', '2025-09-24 17:16:46', '20250924_ashar_1_1758709006.jpg', 'fe5ac8a36e02e44d1e9bb9aacfd2edc6', 'bm1sjleh92amfppsvfp', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '114.10.16.226', -7.107512, 110.281787, 100, 'pending', NULL, NULL, NULL, NULL, '2025-09-24 17:16:24', '2025-09-24 17:16:46');

-- Dumping structure for table appsholat.kelas
CREATE TABLE IF NOT EXISTS `kelas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tingkat` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kelas_nama` (`nama`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.kelas: ~3 rows (approximately)
INSERT IGNORE INTO `kelas` (`id`, `nama`, `tingkat`, `aktif`, `dibuat_pada`) VALUES
	(1, 'X IPA 1', 'X', 1, '2025-09-19 00:43:55'),
	(2, 'X IPA 2', 'X', 1, '2025-09-19 00:43:55'),
	(3, 'XI IPS 1', 'XI', 1, '2025-09-19 00:43:55');

-- Dumping structure for table appsholat.perangkat
CREATE TABLE IF NOT EXISTS `perangkat` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `id_perangkat` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_ua` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pertama_dipakai` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `terakhir_dipakai` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_perangkat` (`user_id`,`id_perangkat`),
  KEY `idx_perangkat_user` (`user_id`),
  CONSTRAINT `fk_perangkat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.perangkat: ~0 rows (approximately)

-- Dumping structure for table appsholat.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.roles: ~3 rows (approximately)
INSERT IGNORE INTO `roles` (`id`, `name`) VALUES
	(1, 'admin'),
	(2, 'guru'),
	(3, 'siswa');

-- Dumping structure for table appsholat.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `svalue` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.settings: ~0 rows (approximately)

-- Dumping structure for table appsholat.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nis` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jk` enum('P','L') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'L',
  `class_id` int unsigned DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('L','P') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `pass_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` tinyint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_class` (`class_id`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_kelas` FOREIGN KEY (`class_id`) REFERENCES `kelas` (`id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table appsholat.users: ~9 rows (approximately)
INSERT IGNORE INTO `users` (`id`, `nis`, `name`, `jk`, `class_id`, `email`, `phone`, `gender`, `catatan`, `pass_hash`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, NULL, 'Admin Sekolah', 'L', NULL, 'admin@appsholat.local', '620000000001', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 1, 1, '2025-09-19 00:43:55', NULL),
	(2, NULL, 'Guru BK', 'L', NULL, 'guru.bk@appsholat.local', '620000000011', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 2, 1, '2025-09-19 00:43:55', NULL),
	(3, NULL, 'Wali Kelas X IPA1', 'L', NULL, 'wali.xipa1@appsholat.local', '620000000012', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 2, 1, '2025-09-19 00:43:55', NULL),
	(4, 'NIS001', 'Ahmad Ramadhan', 'L', 1, 'siswa001@appsholat.local', '620000001001', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(5, 'NIS002', 'Budi Hartono', 'L', 1, 'siswa002@appsholat.local', '620000001002', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(6, 'NIS003', 'Citra Lestari', 'L', 2, 'siswa003@appsholat.local', '620000001003', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(7, 'NIS004', 'Deni Pratama', 'L', 2, 'siswa004@appsholat.local', '620000001004', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(8, 'NIS005', 'Eka Putri', 'L', 3, 'siswa005@appsholat.local', '620000001005', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(9, 'NIS006', 'Fajar Nugraha', 'L', 3, 'siswa006@appsholat.local', '620000001006', NULL, NULL, '$2b$10$X/.wn3H82oktjpsqBWNYTOMeQ8KlnEWs2lN3RckJilnL2nKHfK3ci', 3, 1, '2025-09-19 00:43:55', NULL),
	(10, 'NIS007', 'A B Kusuma', 'L', 2, 'masbudikusuma@gmail.com', '085642898543', NULL, NULL, '$2y$10$4jnMpQ.G9/QmMD0Uh23NIezfXNVs9uDwPOQqUwtx5KCiCfw4dI.Xu', 3, 1, '2025-09-24 07:38:55', '2025-09-24 07:43:49');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
