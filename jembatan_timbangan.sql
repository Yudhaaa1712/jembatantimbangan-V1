-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 10 Nov 2025 pada 09.21
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jembatan_timbangan`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateTicketNumber` (OUT `ticket_no` VARCHAR(20))   BEGIN
    DECLARE today_count INT DEFAULT 0;
    DECLARE date_prefix VARCHAR(10);

    SET date_prefix = DATE_FORMAT(CURDATE(), '%y%m%d');

    SELECT COUNT(*) INTO today_count
    FROM transaksi_timbangan
    WHERE tanggal = CURDATE();

    SET ticket_no = CONCAT('TKT-', date_prefix, '-', LPAD(today_count + 1, 3, '0'));
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetTransactionStats` (IN `start_date` DATE, IN `end_date` DATE, OUT `total_trans` INT, OUT `total_weight` DECIMAL(15,2), OUT `total_revenue` DECIMAL(20,2))   BEGIN
    SELECT
        COUNT(*),
        COALESCE(SUM(berat_netto), 0),
        COALESCE(SUM(total_harga), 0)
    INTO total_trans, total_weight, total_revenue
    FROM transaksi_timbangan
    WHERE tanggal BETWEEN start_date AND end_date
    AND status = 'selesai';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'CREATE', 'transaksi_timbangan', 5, 'Transaksi baru: TKT-251102-004', NULL, NULL, '2025-11-01 19:49:13'),
(2, 1, 'CREATE', 'transaksi_timbangan', 6, 'Transaksi baru: TKT-TEST-001', NULL, NULL, '2025-11-01 19:49:17'),
(3, 1, 'INSERT', 'transaksi_timbangan', 6, 'Tambah transaksi TKT-TEST-001', NULL, NULL, '2025-11-01 19:49:17'),
(4, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 2, 'Status timbang_1 -> selesai untuk TKT-251102-002', NULL, NULL, '2025-11-01 19:49:30'),
(5, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 5, 'Status timbang_1 -> selesai untuk TKT-251102-004', NULL, NULL, '2025-11-01 20:15:37'),
(6, 1, 'CREATE', 'transaksi_timbangan', 8, 'Transaksi baru: TKT-251102-005', NULL, NULL, '2025-11-02 05:54:25'),
(7, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 8, 'Status timbang_1 -> selesai untuk TKT-251102-005', NULL, NULL, '2025-11-02 05:54:47'),
(8, 1, 'CREATE', 'transaksi_timbangan', 9, 'Transaksi baru: TKT-251102-006', NULL, NULL, '2025-11-02 06:58:06'),
(9, 1, 'CREATE', 'transaksi_timbangan', 10, 'Transaksi baru: TKT-251102-007', NULL, NULL, '2025-11-02 06:58:14'),
(10, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 10, 'Status timbang_1 -> selesai untuk TKT-251102-007', NULL, NULL, '2025-11-02 06:58:14'),
(11, 1, 'CREATE', 'transaksi_timbangan', 11, 'Transaksi baru: TKT-251102-008', NULL, NULL, '2025-11-02 06:58:28'),
(12, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 11, 'Status timbang_1 -> selesai untuk TKT-251102-008', NULL, NULL, '2025-11-02 06:58:28'),
(13, 1, 'CREATE', 'transaksi_timbangan', 12, 'Transaksi baru: TKT-251102-001', NULL, NULL, '2025-11-02 07:09:54'),
(14, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 12, 'Status timbang_1 -> selesai untuk TKT-251102-001', NULL, NULL, '2025-11-02 07:09:54'),
(15, 1, 'CREATE', 'transaksi_timbangan', 13, 'Transaksi baru: TKT-251102-002', NULL, NULL, '2025-11-02 07:09:54'),
(16, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 13, 'Status timbang_1 -> selesai untuk TKT-251102-002', NULL, NULL, '2025-11-02 07:09:54'),
(17, 1, 'CREATE', 'transaksi_timbangan', 14, 'Transaksi baru: TKT-251102-003', NULL, NULL, '2025-11-02 07:12:06'),
(18, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 14, 'Status timbang_1 -> selesai untuk TKT-251102-003', NULL, NULL, '2025-11-02 07:12:55'),
(19, NULL, 'CREATE', 'transaksi_timbangan', 15, 'Transaksi baru: TEST-110156', NULL, NULL, '2025-11-03 04:01:56'),
(20, NULL, 'CREATE', 'transaksi_timbangan', 16, 'Transaksi baru: TEST-69082a926d068', NULL, NULL, '2025-11-03 04:07:46'),
(21, 1, 'CREATE', 'transaksi_timbangan', 17, 'Transaksi baru: TKT-251103-001', NULL, NULL, '2025-11-03 04:19:36'),
(22, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 17, 'Status timbang_1 -> selesai untuk TKT-251103-001', NULL, NULL, '2025-11-03 04:19:53'),
(23, 1, 'CREATE', 'transaksi_timbangan', 18, 'Transaksi baru: TKT-251103-002', NULL, NULL, '2025-11-03 04:50:38'),
(24, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 18, 'Status timbang_1 -> selesai untuk TKT-251103-002', NULL, NULL, '2025-11-03 04:51:02'),
(25, 1, 'CREATE', 'transaksi_timbangan', 19, 'Transaksi baru: TKT-251103-003', NULL, NULL, '2025-11-03 05:00:28'),
(26, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 19, 'Status timbang_1 -> selesai untuk TKT-251103-003', NULL, NULL, '2025-11-03 05:00:47'),
(27, 1, 'CREATE', 'transaksi_timbangan', 21, 'Transaksi baru: TKT-TEST-20251103120', NULL, NULL, '2025-11-03 05:03:24'),
(28, 1, 'CREATE', 'transaksi_timbangan', 22, 'Transaksi baru: TKT-251103-004', NULL, NULL, '2025-11-03 05:06:19'),
(29, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 22, 'Status timbang_1 -> selesai untuk TKT-251103-004', NULL, NULL, '2025-11-03 05:06:31'),
(30, 1, 'CREATE', 'transaksi_timbangan', 23, 'Transaksi baru: TKT-251103-005', NULL, NULL, '2025-11-03 05:08:29'),
(31, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 23, 'Status timbang_1 -> selesai untuk TKT-251103-005', NULL, NULL, '2025-11-03 05:08:41'),
(32, 1, 'CREATE', 'transaksi_timbangan', 24, 'Transaksi baru: TKT-251103-001', NULL, NULL, '2025-11-03 07:27:19'),
(33, 1, 'CREATE', 'transaksi_timbangan', 25, 'Transaksi baru: TKT-251103-002', NULL, NULL, '2025-11-03 10:25:56'),
(34, 1, 'CREATE', 'transaksi_timbangan', 26, 'Transaksi baru: TKT-251103-003', NULL, NULL, '2025-11-03 10:36:39'),
(35, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 24, 'Status timbang_1 -> selesai untuk TKT-251103-001', NULL, NULL, '2025-11-03 10:36:49'),
(36, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 25, 'Status timbang_1 -> selesai untuk TKT-251103-002', NULL, NULL, '2025-11-03 10:36:49'),
(37, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 26, 'Status timbang_1 -> selesai untuk TKT-251103-003', NULL, NULL, '2025-11-03 10:36:49'),
(38, 1, 'CREATE', 'transaksi_timbangan', 27, 'Transaksi baru: TKT-251103-004', NULL, NULL, '2025-11-03 10:37:27'),
(39, 1, 'CREATE', 'transaksi_timbangan', 28, 'Transaksi baru: TKT-251103-005', NULL, NULL, '2025-11-03 11:03:13'),
(40, 1, 'CREATE', 'transaksi_timbangan', 29, 'Transaksi baru: TKT-251103-006', NULL, NULL, '2025-11-03 11:13:05'),
(41, NULL, 'CREATE', 'transaksi_timbangan', 31, 'Transaksi baru: TKT-251103-TEST-182', NULL, NULL, '2025-11-03 11:15:18'),
(42, 1, 'CREATE', 'transaksi_timbangan', 32, 'Transaksi baru: TKT-251103-001', NULL, NULL, '2025-11-03 11:39:34'),
(43, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 32, 'Status timbang_1 -> selesai untuk TKT-251103-001', NULL, NULL, '2025-11-03 11:39:49'),
(44, 1, 'CREATE', 'transaksi_timbangan', 33, 'Transaksi baru: TKT-251103-002', NULL, NULL, '2025-11-03 11:40:12'),
(45, 1, 'CREATE', 'transaksi_timbangan', 34, 'Transaksi baru: TKT-251103-003', NULL, NULL, '2025-11-03 11:50:50'),
(46, NULL, 'CREATE', 'transaksi_timbangan', 35, 'Transaksi baru: TKT-251103-WF-129', NULL, NULL, '2025-11-03 11:51:38'),
(47, 1, 'CREATE', 'transaksi_timbangan', 36, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 06:35:11'),
(48, 1, 'CREATE', 'transaksi_timbangan', 37, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 06:38:47'),
(49, 1, 'CREATE', 'transaksi_timbangan', 38, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 06:54:37'),
(50, 1, 'CREATE', 'transaksi_timbangan', 39, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 06:59:47'),
(51, 1, 'CREATE', 'transaksi_timbangan', 40, 'Transaksi baru: TKT-251104-003', NULL, NULL, '2025-11-04 07:06:24'),
(52, 1, 'CREATE', 'transaksi_timbangan', 41, 'Transaksi baru: TKT-251104-004', NULL, NULL, '2025-11-04 07:14:29'),
(53, 1, 'CREATE', 'transaksi_timbangan', 42, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 07:25:49'),
(54, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 42, 'Status timbang_1 -> selesai untuk TKT-251104-001', NULL, NULL, '2025-11-04 07:39:36'),
(55, 1, 'CREATE', 'transaksi_timbangan', 43, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 07:40:10'),
(56, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 43, 'Status timbang_1 -> selesai untuk TKT-251104-002', NULL, NULL, '2025-11-04 07:44:13'),
(57, 1, 'CREATE', 'transaksi_timbangan', 44, 'Transaksi baru: TKT-251104-003', NULL, NULL, '2025-11-04 07:52:29'),
(58, 1, 'CREATE', 'transaksi_timbangan', 45, 'Transaksi baru: TKT-251104-004', NULL, NULL, '2025-11-04 09:03:48'),
(59, 1, 'CREATE', 'transaksi_timbangan', 46, 'Transaksi baru: TKT-251104-005', NULL, NULL, '2025-11-04 09:15:28'),
(60, 1, 'STATUS_CHANGE', 'transaksi_timbangan', 45, 'Status timbang_1 -> selesai untuk TKT-251104-004', NULL, NULL, '2025-11-04 09:23:04'),
(61, NULL, 'CREATE', 'transaksi_timbangan', 47, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 09:36:08'),
(62, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 47, 'Status timbang_1 -> selesai untuk TKT-251104-001', NULL, NULL, '2025-11-04 09:37:44'),
(63, NULL, 'CREATE', 'transaksi_timbangan', 48, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 09:38:56'),
(64, NULL, 'CREATE', 'transaksi_timbangan', 49, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 09:52:04'),
(65, NULL, 'CREATE', 'transaksi_timbangan', 50, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 09:57:17'),
(66, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 49, 'Status timbang_1 -> selesai untuk TKT-251104-001', NULL, NULL, '2025-11-04 09:59:58'),
(67, NULL, 'CREATE', 'transaksi_timbangan', 51, 'Transaksi baru: TKT-251104-003', NULL, NULL, '2025-11-04 10:17:29'),
(68, NULL, 'CREATE', 'transaksi_timbangan', 52, 'Transaksi baru: TKT-251104-004', NULL, NULL, '2025-11-04 10:21:29'),
(69, NULL, 'CREATE', 'transaksi_timbangan', 53, 'Transaksi baru: TKT-251104-005', NULL, NULL, '2025-11-04 10:50:50'),
(70, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 53, 'Status timbang_1 -> selesai untuk TKT-251104-005', NULL, NULL, '2025-11-04 10:54:24'),
(71, NULL, 'CREATE', 'transaksi_timbangan', 54, 'Transaksi baru: TKT-251104-006', NULL, NULL, '2025-11-04 11:00:43'),
(72, NULL, 'CREATE', 'transaksi_timbangan', 55, 'Transaksi baru: TKT-251104-007', NULL, NULL, '2025-11-04 11:04:41'),
(73, NULL, 'CREATE', 'transaksi_timbangan', 56, 'Transaksi baru: TKT-251104-008', NULL, NULL, '2025-11-04 11:05:39'),
(74, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 56, 'Status timbang_1 -> selesai untuk TKT-251104-008', NULL, NULL, '2025-11-04 11:05:58'),
(75, NULL, 'CREATE', 'transaksi_timbangan', 57, 'Transaksi baru: TKT-251104-009', NULL, NULL, '2025-11-04 11:11:10'),
(76, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 57, 'Status timbang_1 -> selesai untuk TKT-251104-009', NULL, NULL, '2025-11-04 11:11:31'),
(77, 1, 'CREATE', 'transaksi_timbangan', 59, 'Transaksi baru: TT20251104001', NULL, NULL, '2025-11-04 15:25:00'),
(78, 1, 'CREATE', 'transaksi_timbangan', 60, 'Transaksi baru: TT20251104002', NULL, NULL, '2025-11-04 15:25:00'),
(79, 1, 'CREATE', 'transaksi_timbangan', 61, 'Transaksi baru: TT20251104003', NULL, NULL, '2025-11-04 15:25:00'),
(80, NULL, 'CREATE', 'transaksi_timbangan', 62, 'Transaksi baru: TKT-251104-001', NULL, NULL, '2025-11-04 16:21:52'),
(81, NULL, 'CREATE', 'transaksi_timbangan', 63, 'Transaksi baru: TKT-251104-002', NULL, NULL, '2025-11-04 16:26:23'),
(82, NULL, 'CREATE', 'transaksi_timbangan', 64, 'Transaksi baru: TKT-251105-001', NULL, NULL, '2025-11-04 19:45:11'),
(83, NULL, 'CREATE', 'transaksi_timbangan', 65, 'Transaksi baru: TKT-251105-002', NULL, NULL, '2025-11-05 01:35:09'),
(84, NULL, 'CREATE', 'transaksi_timbangan', 66, 'Transaksi baru: TKT-251105-003', NULL, NULL, '2025-11-05 01:48:01'),
(85, NULL, 'CREATE', 'transaksi_timbangan', 67, 'Transaksi baru: TKT-251105-004', NULL, NULL, '2025-11-05 02:11:46'),
(86, NULL, 'CREATE', 'transaksi_timbangan', 68, 'Transaksi baru: TKT-251105-005', NULL, NULL, '2025-11-05 02:23:05'),
(87, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 68, 'Status timbang_1 -> selesai untuk TKT-251105-005', NULL, NULL, '2025-11-05 02:24:01'),
(88, NULL, 'CREATE', 'transaksi_timbangan', 69, 'Transaksi baru: TEST-20251105092617', NULL, NULL, '2025-11-05 02:26:17'),
(89, NULL, 'CREATE', 'transaksi_timbangan', 70, 'Transaksi baru: TKT-251105-006', NULL, NULL, '2025-11-05 02:29:37'),
(90, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 70, 'Status timbang_1 -> selesai untuk TKT-251105-006', NULL, NULL, '2025-11-05 02:30:37'),
(91, NULL, 'CREATE', 'transaksi_timbangan', 71, 'Transaksi baru: TKT-251105-007', NULL, NULL, '2025-11-05 02:35:39'),
(92, NULL, 'CREATE', 'transaksi_timbangan', 72, 'Transaksi baru: TKT-251105-008', NULL, NULL, '2025-11-05 02:38:00'),
(93, NULL, 'CREATE', 'transaksi_timbangan', 73, 'Transaksi baru: TKT-251105-009', NULL, NULL, '2025-11-05 02:44:28'),
(94, NULL, 'CREATE', 'transaksi_timbangan', 74, 'Transaksi baru: TKT-251105-010', NULL, NULL, '2025-11-05 02:47:29'),
(95, NULL, 'CREATE', 'transaksi_timbangan', 75, 'Transaksi baru: TKT-251105-011', NULL, NULL, '2025-11-05 03:01:04'),
(96, NULL, 'CREATE', 'transaksi_timbangan', 76, 'Transaksi baru: MANUAL-2025110510153', NULL, NULL, '2025-11-05 03:15:39'),
(97, NULL, 'CREATE', 'transaksi_timbangan', 77, 'Transaksi baru: TEST-251105-001', NULL, NULL, '2025-11-05 03:31:22'),
(98, NULL, 'CREATE', 'transaksi_timbangan', 78, 'Transaksi baru: TKT-251105-012', NULL, NULL, '2025-11-05 03:32:22'),
(99, NULL, 'CREATE', 'transaksi_timbangan', 79, 'Transaksi baru: TKT-251105-013', NULL, NULL, '2025-11-05 03:33:07'),
(100, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 79, 'Status timbang_1 -> selesai untuk TKT-251105-013', NULL, NULL, '2025-11-05 03:36:35'),
(101, NULL, 'CREATE', 'transaksi_timbangan', 80, 'Transaksi baru: TEST-251105-065', NULL, NULL, '2025-11-05 03:40:58'),
(102, NULL, 'CREATE', 'transaksi_timbangan', 81, 'Transaksi baru: TEST-251105-465', NULL, NULL, '2025-11-05 03:42:55'),
(103, NULL, 'CREATE', 'transaksi_timbangan', 82, 'Transaksi baru: TKT-251105-014', NULL, NULL, '2025-11-05 03:55:09'),
(104, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 82, 'Status timbang_1 -> selesai untuk TKT-251105-014', NULL, NULL, '2025-11-05 03:55:53'),
(105, NULL, 'CREATE', 'transaksi_timbangan', 83, 'Transaksi baru: TEST-T2-251105-001', NULL, NULL, '2025-11-05 04:06:05'),
(106, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 83, 'Status timbang_1 -> selesai untuk TEST-T2-251105-001', NULL, NULL, '2025-11-05 04:06:05'),
(107, NULL, 'CREATE', 'transaksi_timbangan', 84, 'Transaksi baru: TKT-251105-015', NULL, NULL, '2025-11-05 04:11:40'),
(108, NULL, 'CREATE', 'transaksi_timbangan', 85, 'Transaksi baru: TKT-251105-016', NULL, NULL, '2025-11-05 04:19:00'),
(109, NULL, 'CREATE', 'transaksi_timbangan', 86, 'Transaksi baru: TKT-251106-001', NULL, NULL, '2025-11-06 09:01:33'),
(110, NULL, 'CREATE', 'transaksi_timbangan', 87, 'Transaksi baru: TKT-251106-002', NULL, NULL, '2025-11-06 09:56:01'),
(111, NULL, 'CREATE', 'transaksi_timbangan', 88, 'Transaksi baru: TKT-251106-003', NULL, NULL, '2025-11-06 10:31:33'),
(112, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 88, 'Status timbang_1 -> selesai untuk TKT-251106-003', NULL, NULL, '2025-11-06 10:47:06'),
(113, NULL, 'CREATE', 'transaksi_timbangan', 89, 'Transaksi baru: TKT-251107-001', NULL, NULL, '2025-11-06 18:21:15'),
(114, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 89, 'Status timbang_1 -> selesai untuk TKT-251107-001', NULL, NULL, '2025-11-06 18:22:32'),
(115, NULL, 'CREATE', 'transaksi_timbangan', 90, 'Transaksi baru: TKT-251107-002', NULL, NULL, '2025-11-06 18:42:56'),
(116, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 90, 'Status timbang_1 -> selesai untuk TKT-251107-002', NULL, NULL, '2025-11-06 18:47:10'),
(117, NULL, 'CREATE', 'transaksi_timbangan', 91, 'Transaksi baru: TKT-251107-003', NULL, NULL, '2025-11-06 18:48:01'),
(118, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 91, 'Status timbang_1 -> selesai untuk TKT-251107-003', NULL, NULL, '2025-11-06 18:48:18'),
(119, NULL, 'CREATE', 'transaksi_timbangan', 92, 'Transaksi baru: TKT-251107-004', NULL, NULL, '2025-11-06 18:49:42'),
(120, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 92, 'Status timbang_1 -> selesai untuk TKT-251107-004', NULL, NULL, '2025-11-06 18:50:49'),
(121, NULL, 'CREATE', 'transaksi_timbangan', 93, 'Transaksi baru: TKT-251107-005', NULL, NULL, '2025-11-07 02:36:48'),
(122, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 93, 'Status timbang_1 -> selesai untuk TKT-251107-005', NULL, NULL, '2025-11-07 02:38:58'),
(123, NULL, 'CREATE', 'transaksi_timbangan', 94, 'Transaksi baru: TKT-251107-006', NULL, NULL, '2025-11-07 02:46:25'),
(124, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 94, 'Status timbang_1 -> selesai untuk TKT-251107-006', NULL, NULL, '2025-11-07 02:46:55'),
(125, NULL, 'CREATE', 'transaksi_timbangan', 95, 'Transaksi baru: TKT-251107-007', NULL, NULL, '2025-11-07 02:47:37'),
(126, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 95, 'Status timbang_1 -> selesai untuk TKT-251107-007', NULL, NULL, '2025-11-07 02:48:21'),
(127, NULL, 'CREATE', 'transaksi_timbangan', 96, 'Transaksi baru: TKT-251107-008', NULL, NULL, '2025-11-07 02:58:48'),
(128, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 96, 'Status timbang_1 -> selesai untuk TKT-251107-008', NULL, NULL, '2025-11-07 02:59:37'),
(129, NULL, 'CREATE', 'transaksi_timbangan', 97, 'Transaksi baru: TKT-251107-009', NULL, NULL, '2025-11-07 03:11:41'),
(130, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 97, 'Status timbang_1 -> selesai untuk TKT-251107-009', NULL, NULL, '2025-11-07 03:12:17'),
(131, NULL, 'CREATE', 'transaksi_timbangan', 98, 'Transaksi baru: TKT-251107-010', NULL, NULL, '2025-11-07 07:11:26'),
(132, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 98, 'Status timbang_1 -> selesai untuk TKT-251107-010', NULL, NULL, '2025-11-07 07:11:45'),
(133, NULL, 'CREATE', 'transaksi_timbangan', 99, 'Transaksi baru: TKT-251107-011', NULL, NULL, '2025-11-07 08:26:42'),
(134, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 99, 'Status timbang_1 -> selesai untuk TKT-251107-011', NULL, NULL, '2025-11-07 08:27:04'),
(135, NULL, 'CREATE', 'transaksi_timbangan', 100, 'Transaksi baru: TKT-251107-012', NULL, NULL, '2025-11-07 08:38:20'),
(136, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 100, 'Status timbang_1 -> selesai untuk TKT-251107-012', NULL, NULL, '2025-11-07 08:39:20'),
(137, NULL, 'CREATE', 'transaksi_timbangan', 101, 'Transaksi baru: TKT-251107-013', NULL, NULL, '2025-11-07 08:59:17'),
(138, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 101, 'Status timbang_1 -> selesai untuk TKT-251107-013', NULL, NULL, '2025-11-07 09:00:30'),
(139, NULL, 'CREATE', 'transaksi_timbangan', 102, 'Transaksi baru: TKT-251110-001', NULL, NULL, '2025-11-10 06:11:16'),
(140, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 102, 'Status timbang_1 -> selesai untuk TKT-251110-001', NULL, NULL, '2025-11-10 06:11:49'),
(141, NULL, 'CREATE', 'transaksi_timbangan', 103, 'Transaksi baru: TKT-251110-002', NULL, NULL, '2025-11-10 06:49:08'),
(142, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 103, 'Status timbang_1 -> selesai untuk TKT-251110-002', NULL, NULL, '2025-11-10 06:49:41'),
(143, NULL, 'CREATE', 'transaksi_timbangan', 104, 'Transaksi baru: TKT-251110-003', NULL, NULL, '2025-11-10 08:02:13'),
(144, NULL, 'CREATE', 'transaksi_timbangan', 105, 'Transaksi baru: TKT-251110-004', NULL, NULL, '2025-11-10 08:02:40'),
(145, NULL, 'CREATE', 'transaksi_timbangan', 106, 'Transaksi baru: TKT-251110-005', NULL, NULL, '2025-11-10 08:13:45'),
(146, NULL, 'STATUS_CHANGE', 'transaksi_timbangan', 106, 'Status timbang_1 -> selesai untuk TKT-251110-005', NULL, NULL, '2025-11-10 08:14:46'),
(147, NULL, 'CREATE', 'transaksi_timbangan', 107, 'Transaksi baru: TKT-251110-006', NULL, NULL, '2025-11-10 08:16:46'),
(148, NULL, 'CREATE', 'transaksi_timbangan', 108, 'Transaksi baru: TKT-251110-007', NULL, NULL, '2025-11-10 08:17:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `kode_customer` varchar(20) NOT NULL,
  `nama_customer` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `kontak_person` varchar(100) DEFAULT NULL,
  `kredit_limit` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive','blacklist') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `no_invoice` varchar(30) NOT NULL,
  `transaksi_id` int(11) DEFAULT NULL,
  `id_customer` int(11) DEFAULT NULL,
  `tanggal_invoice` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `ppn_percent` decimal(5,2) DEFAULT 11.00,
  `ppn_amount` decimal(15,2) DEFAULT 0.00,
  `diskon_percent` decimal(5,2) DEFAULT 0.00,
  `diskon_amount` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL,
  `status_pembayaran` enum('unpaid','partial','paid','overdue') DEFAULT 'unpaid',
  `tanggal_bayar` date DEFAULT NULL,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `keterangan_invoice` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kendaraan`
--

CREATE TABLE `kendaraan` (
  `id` int(11) NOT NULL,
  `no_polisi` varchar(20) NOT NULL,
  `jenis_kendaraan` enum('truk','tronton','container','pickup','lainnya') DEFAULT 'truk',
  `kapasitas_maksimal` decimal(10,2) DEFAULT 0.00,
  `pemilik` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kendaraan`
--

INSERT INTO `kendaraan` (`id`, `no_polisi`, `jenis_kendaraan`, `kapasitas_maksimal`, `pemilik`, `no_telepon`, `alamat`, `status`, `created_at`, `updated_at`) VALUES
(25, 'B 1234 ABC', 'tronton', 30000.00, 'Ahmad', NULL, NULL, 'active', '2025-11-06 18:40:01', '2025-11-06 18:40:01'),
(26, 'B 5678 DEF', 'tronton', 25000.00, 'Budi', NULL, NULL, 'active', '2025-11-06 18:40:01', '2025-11-06 18:40:01'),
(27, 'B 9012 GHI', 'truk', 8000.00, 'Chandra', NULL, NULL, 'active', '2025-11-06 18:40:01', '2025-11-06 18:40:01'),
(28, 'B 3456 JKL', 'tronton', 28000.00, 'Doni', NULL, NULL, 'active', '2025-11-06 18:40:01', '2025-11-06 18:40:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `kode_material` varchar(20) NOT NULL,
  `nama_material` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-cube',
  `satuan` varchar(20) DEFAULT 'Kg',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `materials`
--

INSERT INTO `materials` (`id`, `kode_material`, `nama_material`, `deskripsi`, `icon`, `satuan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'tbs', 'TBS (Tandan Buah Segar)', 'Tandan Buah Segar dari kebun sawit', 'fa-apple-alt', 'Kg', 'active', '2025-11-05 03:25:28', '2025-11-05 03:25:28'),
(4, 'brondolan', 'Brondolan', 'Buah sawit yang jatuh/terlepas', 'fa-leaf', 'Kg', 'active', '2025-11-05 03:25:28', '2025-11-05 03:25:28'),
(6, 'TAT284', 'Tarik Ongkos', '', 'fa-cube', 'Kg', 'active', '2025-11-08 17:28:40', '2025-11-08 17:28:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'ticket_prefix', 'TKT', 'Prefix untuk nomor tiket', '2025-11-01 19:45:52', '2025-11-01 19:45:52'),
(2, 'company_name', 'Jembatan Timbangan Sawit', 'Nama perusahaan', '2025-11-01 19:45:52', '2025-11-01 19:45:52'),
(3, 'company_address', 'Alamat Perusahaan', 'Alamat perusahaan', '2025-11-01 19:45:52', '2025-11-01 19:45:52'),
(4, 'company_phone', '0000-0000', 'Telepon perusahaan', '2025-11-01 19:45:52', '2025-11-01 19:45:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `supplier`
--

CREATE TABLE `supplier` (
  `id` int(11) NOT NULL,
  `kode_supplier` varchar(20) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `kontak_person` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','blacklist') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `supplier`
--

INSERT INTO `supplier` (`id`, `kode_supplier`, `nama_supplier`, `alamat`, `no_telepon`, `email`, `npwp`, `kontak_person`, `status`, `created_at`, `updated_at`) VALUES
(11, 'SUP-251102-739', 'RAM 3 PUTRA', NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-02 07:12:06', '2025-11-02 07:12:06'),
(13, 'SUP-251103-040', 'RAM RAMLAN', NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-03 05:00:28', '2025-11-03 05:00:28'),
(23, 'SUP-251104-489', 'RAM YUDHA', NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-04 06:59:47', '2025-11-04 06:59:47'),
(24, 'SUP-251104-377', 'RAM 3 BUNDA', NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-04 07:06:24', '2025-11-04 07:06:24'),
(25, 'RAM808', 'RAM IWAN JAYA', '', '', '', '', '', 'active', '2025-11-08 17:27:59', '2025-11-08 17:27:59'),
(26, 'RAM580', 'RAM YUDHA JAYA', '', '', '', '', '', 'active', '2025-11-09 07:46:34', '2025-11-09 07:46:34'),
(27, 'RAM828', 'RAM JOVAN ', '', '', '', '', '', 'active', '2025-11-10 06:48:22', '2025-11-10 06:48:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_timbangan`
--

CREATE TABLE `transaksi_timbangan` (
  `id` int(11) NOT NULL,
  `no_tiket` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_timbangan1` time DEFAULT NULL,
  `waktu_keluar` time DEFAULT NULL,
  `waktu_timbangan2` time DEFAULT NULL,
  `id_kendaraan` int(11) DEFAULT NULL,
  `no_polisi` varchar(20) NOT NULL,
  `nama_supir` varchar(100) DEFAULT NULL,
  `id_supplier` int(11) DEFAULT NULL,
  `id_customer` int(11) DEFAULT NULL,
  `jenis_material` enum('tbs','cpo','kernel','brondolan','lainnya') NOT NULL DEFAULT 'tbs',
  `berat_bruto` decimal(10,2) DEFAULT 0.00,
  `berat_timbangan1` decimal(10,2) DEFAULT 0.00,
  `timbang1_locked` tinyint(1) DEFAULT 0,
  `berat_tara` decimal(10,2) DEFAULT 0.00,
  `berat_timbangan2` decimal(10,2) DEFAULT 0.00,
  `timbang2_locked` tinyint(1) DEFAULT 0,
  `persen_potongan` decimal(5,2) DEFAULT 0.00,
  `kg_potongan` decimal(10,2) DEFAULT 0.00,
  `berat_netto` decimal(10,2) GENERATED ALWAYS AS (case when `berat_timbangan1` > 0 and `berat_timbangan2` > 0 then `berat_timbangan1` - `berat_timbangan2` - ((`berat_timbangan1` - `berat_timbangan2`) * `persen_potongan` / 100 + `kg_potongan`) else 0 end) STORED,
  `harga_per_kg` decimal(10,2) DEFAULT 0.00,
  `total_harga` decimal(15,2) GENERATED ALWAYS AS (case when `berat_timbangan1` > 0 and `berat_timbangan2` > 0 and `harga_per_kg` > 0 then (`berat_timbangan1` - `berat_timbangan2` - ((`berat_timbangan1` - `berat_timbangan2`) * `persen_potongan` / 100 + `kg_potongan`)) * `harga_per_kg` else 0 end) STORED,
  `keterangan` text DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `foto_keluar` varchar(255) DEFAULT NULL,
  `status` enum('timbang_1','timbang_2','selesai','batal') DEFAULT 'timbang_1',
  `operator_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Trigger `transaksi_timbangan`
--
DELIMITER $$
CREATE TRIGGER `log_transaksi_insert` AFTER INSERT ON `transaksi_timbangan` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, description, table_name, record_id)
    VALUES (NEW.operator_id, 'CREATE', CONCAT('Transaksi baru: ', NEW.no_tiket), 'transaksi_timbangan', NEW.id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_transaksi_update` AFTER UPDATE ON `transaksi_timbangan` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_logs (user_id, action, description, table_name, record_id)
        VALUES (NEW.operator_id, 'STATUS_CHANGE',
               CONCAT('Status ', OLD.status, ' -> ', NEW.status, ' untuk ', NEW.no_tiket),
               'transaksi_timbangan', NEW.id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','operator','viewer') DEFAULT 'operator',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$kvtScJ5wjrd86pvZcTlP4.l8J1kkp0YmECtQjZPysFyMNgM4CrkWO', 'Administrator', 'admin@timbangan.com', 'admin', 'active', NULL, '2025-11-01 18:54:40', '2025-11-02 05:22:39'),
(2, 'operator1', '$2y$10$xgJ7Rx5fEz0pv1Msd/DjyOkuXUPjSijej0iOp1HeTFrErRHy7.9wO', 'Operator Timbang 1', 'op1@timbangan.com', 'operator', 'active', NULL, '2025-11-03 09:40:16', '2025-11-03 09:59:07'),
(3, 'operator2', 'y\02IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operator Timbang 2', 'op2@timbangan.com', 'operator', 'active', NULL, '2025-11-03 09:40:16', '2025-11-03 09:40:16'),
(4, 'viewer1', 'y\02IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Viewer Transaksi', 'view@timbangan.com', 'viewer', 'active', NULL, '2025-11-03 09:40:16', '2025-11-03 09:58:55'),
(5, 'yudha', '$2y$10$e5kPuYLhLbBSNZVuyefwXe8.5pEUC1dp3GFM0VwQPlcuhUEW0Iqz.', 'yudha', '', 'operator', 'active', NULL, '2025-11-03 10:17:07', '2025-11-04 12:37:31'),
(6, 'cindi', '$2y$10$ba6NfqCtmz.Ed.yw.UToCuOnMFMCyrMBXU0OK9jL6xIeecXAizdAe', 'cindi', 'cindi@gmail.com', 'operator', 'active', NULL, '2025-11-03 10:21:54', '2025-11-03 10:25:22'),
(7, 'iwan sueri', '$2y$10$3pykJG4OSPed0p/fdjn4kueZp8GES4jbejhRpzjL8fvOG2o6BalaS', 'iwan sueri', NULL, 'operator', 'active', NULL, '2025-11-04 06:28:39', '2025-11-04 06:28:39'),
(8, 'admin2', '$2y$10$6LqM578sOKXaqCq8CfBG4e5u9TSOBwMSxKg4n7BMvZXuj9/fj1QZ6', 'admin2', NULL, 'admin', 'active', NULL, '2025-11-07 09:50:18', '2025-11-07 09:50:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `can_create` tinyint(1) DEFAULT 0,
  `can_read` tinyint(1) DEFAULT 1,
  `can_update` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_laporan_bulanan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_laporan_bulanan` (
`tahun` int(4)
,`bulan` int(2)
,`periode` varchar(7)
,`total_transaksi` bigint(21)
,`transaksi_selesai` bigint(21)
,`total_bruto` decimal(32,2)
,`total_tara` decimal(32,2)
,`total_netto` decimal(32,2)
,`total_omzet` decimal(37,2)
,`rata_harga_per_kg` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_laporan_harian`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_laporan_harian` (
`tanggal` date
,`total_transaksi` bigint(21)
,`transaksi_selesai` bigint(21)
,`total_bruto` decimal(32,2)
,`total_tara` decimal(32,2)
,`total_netto` decimal(32,2)
,`total_omzet` decimal(37,2)
,`rata_netto` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_transaksi_complete`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_transaksi_complete` (
`id` int(11)
,`no_tiket` varchar(20)
,`tanggal` date
,`waktu_masuk` time
,`waktu_timbangan1` time
,`waktu_keluar` time
,`waktu_timbangan2` time
,`id_kendaraan` int(11)
,`no_polisi` varchar(20)
,`nama_supir` varchar(100)
,`id_supplier` int(11)
,`id_customer` int(11)
,`jenis_material` enum('tbs','cpo','kernel','brondolan','lainnya')
,`berat_bruto` decimal(10,2)
,`berat_timbangan1` decimal(10,2)
,`timbang1_locked` tinyint(1)
,`berat_tara` decimal(10,2)
,`berat_timbangan2` decimal(10,2)
,`timbang2_locked` tinyint(1)
,`persen_potongan` decimal(5,2)
,`kg_potongan` decimal(10,2)
,`berat_netto` decimal(10,2)
,`harga_per_kg` decimal(10,2)
,`total_harga` decimal(15,2)
,`keterangan` text
,`foto_masuk` varchar(255)
,`foto_keluar` varchar(255)
,`status` enum('timbang_1','timbang_2','selesai','batal')
,`operator_id` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`jenis_kendaraan` enum('truk','tronton','container','pickup','lainnya')
,`pemilik_kendaraan` varchar(100)
,`kode_supplier` varchar(20)
,`nama_supplier` varchar(100)
,`operator_name` varchar(100)
,`customer_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_transaksi_lengkap`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_transaksi_lengkap` (
`id` int(11)
,`no_tiket` varchar(20)
,`tanggal` date
,`waktu_masuk` time
,`waktu_timbangan1` time
,`waktu_keluar` time
,`waktu_timbangan2` time
,`no_polisi` varchar(20)
,`jenis_kendaraan` enum('truk','tronton','container','pickup','lainnya')
,`pemilik_kendaraan` varchar(100)
,`nama_supir` varchar(100)
,`kode_supplier` varchar(20)
,`nama_supplier` varchar(100)
,`jenis_material` enum('tbs','cpo','kernel','brondolan','lainnya')
,`berat_timbangan1` decimal(10,2)
,`berat_timbangan2` decimal(10,2)
,`berat_netto` decimal(10,2)
,`persen_potongan` decimal(5,2)
,`kg_potongan` decimal(10,2)
,`harga_per_kg` decimal(10,2)
,`total_harga` decimal(15,2)
,`keterangan` text
,`status` enum('timbang_1','timbang_2','selesai','batal')
,`operator_name` varchar(100)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_profit_loss_timbangan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_profit_loss_timbangan` (
`periode` varchar(7)
,`total_transaksi` bigint(21)
,`total_berat_kg` decimal(32,2)
,`total_penjualan` decimal(37,2)
,`estimated_hpp` decimal(40,4)
,`estimated_profit` decimal(40,4)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `view_laporan_bulanan`
--
DROP TABLE IF EXISTS `view_laporan_bulanan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_laporan_bulanan`  AS SELECT year(`transaksi_timbangan`.`tanggal`) AS `tahun`, month(`transaksi_timbangan`.`tanggal`) AS `bulan`, date_format(`transaksi_timbangan`.`tanggal`,'%Y-%m') AS `periode`, count(0) AS `total_transaksi`, count(case when `transaksi_timbangan`.`status` = 'selesai' then 1 end) AS `transaksi_selesai`, sum(`transaksi_timbangan`.`berat_timbangan1`) AS `total_bruto`, sum(`transaksi_timbangan`.`berat_timbangan2`) AS `total_tara`, sum(`transaksi_timbangan`.`berat_netto`) AS `total_netto`, sum(`transaksi_timbangan`.`total_harga`) AS `total_omzet`, avg(`transaksi_timbangan`.`harga_per_kg`) AS `rata_harga_per_kg` FROM `transaksi_timbangan` WHERE `transaksi_timbangan`.`status` = 'selesai' GROUP BY year(`transaksi_timbangan`.`tanggal`), month(`transaksi_timbangan`.`tanggal`) ORDER BY year(`transaksi_timbangan`.`tanggal`) DESC, month(`transaksi_timbangan`.`tanggal`) DESC ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_laporan_harian`
--
DROP TABLE IF EXISTS `view_laporan_harian`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_laporan_harian`  AS SELECT `transaksi_timbangan`.`tanggal` AS `tanggal`, count(0) AS `total_transaksi`, count(case when `transaksi_timbangan`.`status` = 'selesai' then 1 end) AS `transaksi_selesai`, sum(`transaksi_timbangan`.`berat_timbangan1`) AS `total_bruto`, sum(`transaksi_timbangan`.`berat_timbangan2`) AS `total_tara`, sum(`transaksi_timbangan`.`berat_netto`) AS `total_netto`, sum(`transaksi_timbangan`.`total_harga`) AS `total_omzet`, avg(`transaksi_timbangan`.`berat_netto`) AS `rata_netto` FROM `transaksi_timbangan` WHERE `transaksi_timbangan`.`status` = 'selesai' GROUP BY `transaksi_timbangan`.`tanggal` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_transaksi_complete`
--
DROP TABLE IF EXISTS `view_transaksi_complete`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_transaksi_complete`  AS SELECT `tt`.`id` AS `id`, `tt`.`no_tiket` AS `no_tiket`, `tt`.`tanggal` AS `tanggal`, `tt`.`waktu_masuk` AS `waktu_masuk`, `tt`.`waktu_timbangan1` AS `waktu_timbangan1`, `tt`.`waktu_keluar` AS `waktu_keluar`, `tt`.`waktu_timbangan2` AS `waktu_timbangan2`, `tt`.`id_kendaraan` AS `id_kendaraan`, `tt`.`no_polisi` AS `no_polisi`, `tt`.`nama_supir` AS `nama_supir`, `tt`.`id_supplier` AS `id_supplier`, `tt`.`id_customer` AS `id_customer`, `tt`.`jenis_material` AS `jenis_material`, `tt`.`berat_bruto` AS `berat_bruto`, `tt`.`berat_timbangan1` AS `berat_timbangan1`, `tt`.`timbang1_locked` AS `timbang1_locked`, `tt`.`berat_tara` AS `berat_tara`, `tt`.`berat_timbangan2` AS `berat_timbangan2`, `tt`.`timbang2_locked` AS `timbang2_locked`, `tt`.`persen_potongan` AS `persen_potongan`, `tt`.`kg_potongan` AS `kg_potongan`, `tt`.`berat_netto` AS `berat_netto`, `tt`.`harga_per_kg` AS `harga_per_kg`, `tt`.`total_harga` AS `total_harga`, `tt`.`keterangan` AS `keterangan`, `tt`.`foto_masuk` AS `foto_masuk`, `tt`.`foto_keluar` AS `foto_keluar`, `tt`.`status` AS `status`, `tt`.`operator_id` AS `operator_id`, `tt`.`created_at` AS `created_at`, `tt`.`updated_at` AS `updated_at`, `k`.`jenis_kendaraan` AS `jenis_kendaraan`, `k`.`pemilik` AS `pemilik_kendaraan`, `s`.`kode_supplier` AS `kode_supplier`, `s`.`nama_supplier` AS `nama_supplier`, `u`.`nama_lengkap` AS `operator_name`, CASE WHEN `tt`.`id_customer` is not null THEN `c`.`nama_customer` ELSE 'Walk-in Customer' END AS `customer_name` FROM ((((`transaksi_timbangan` `tt` left join `kendaraan` `k` on(`tt`.`id_kendaraan` = `k`.`id`)) left join `supplier` `s` on(`tt`.`id_supplier` = `s`.`id`)) left join `users` `u` on(`tt`.`operator_id` = `u`.`id`)) left join `customers` `c` on(`tt`.`id_customer` = `c`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_transaksi_lengkap`
--
DROP TABLE IF EXISTS `view_transaksi_lengkap`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_transaksi_lengkap`  AS SELECT `tt`.`id` AS `id`, `tt`.`no_tiket` AS `no_tiket`, `tt`.`tanggal` AS `tanggal`, `tt`.`waktu_masuk` AS `waktu_masuk`, `tt`.`waktu_timbangan1` AS `waktu_timbangan1`, `tt`.`waktu_keluar` AS `waktu_keluar`, `tt`.`waktu_timbangan2` AS `waktu_timbangan2`, `tt`.`no_polisi` AS `no_polisi`, `k`.`jenis_kendaraan` AS `jenis_kendaraan`, `k`.`pemilik` AS `pemilik_kendaraan`, `tt`.`nama_supir` AS `nama_supir`, `s`.`kode_supplier` AS `kode_supplier`, `s`.`nama_supplier` AS `nama_supplier`, `tt`.`jenis_material` AS `jenis_material`, `tt`.`berat_timbangan1` AS `berat_timbangan1`, `tt`.`berat_timbangan2` AS `berat_timbangan2`, `tt`.`berat_netto` AS `berat_netto`, `tt`.`persen_potongan` AS `persen_potongan`, `tt`.`kg_potongan` AS `kg_potongan`, `tt`.`harga_per_kg` AS `harga_per_kg`, `tt`.`total_harga` AS `total_harga`, `tt`.`keterangan` AS `keterangan`, `tt`.`status` AS `status`, `u`.`nama_lengkap` AS `operator_name`, `tt`.`created_at` AS `created_at`, `tt`.`updated_at` AS `updated_at` FROM (((`transaksi_timbangan` `tt` left join `kendaraan` `k` on(`tt`.`id_kendaraan` = `k`.`id`)) left join `supplier` `s` on(`tt`.`id_supplier` = `s`.`id`)) left join `users` `u` on(`tt`.`operator_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_profit_loss_timbangan`
--
DROP TABLE IF EXISTS `v_profit_loss_timbangan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_profit_loss_timbangan`  AS SELECT date_format(`transaksi_timbangan`.`tanggal`,'%Y-%m') AS `periode`, count(0) AS `total_transaksi`, sum(`transaksi_timbangan`.`berat_netto`) AS `total_berat_kg`, sum(`transaksi_timbangan`.`total_harga`) AS `total_penjualan`, sum(`transaksi_timbangan`.`total_harga`) * 0.70 AS `estimated_hpp`, sum(`transaksi_timbangan`.`total_harga`) * 0.30 AS `estimated_profit` FROM `transaksi_timbangan` WHERE `transaksi_timbangan`.`status` = 'selesai' GROUP BY date_format(`transaksi_timbangan`.`tanggal`,'%Y-%m') ORDER BY date_format(`transaksi_timbangan`.`tanggal`,'%Y-%m') DESC ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_customer` (`kode_customer`),
  ADD KEY `idx_kode_customer` (`kode_customer`),
  ADD KEY `idx_nama_customer` (`nama_customer`);

--
-- Indeks untuk tabel `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_invoice` (`no_invoice`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `idx_no_invoice` (`no_invoice`),
  ADD KEY `idx_customer` (`id_customer`),
  ADD KEY `idx_status_pembayaran` (`status_pembayaran`);

--
-- Indeks untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_polisi` (`no_polisi`),
  ADD KEY `idx_no_polisi` (`no_polisi`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_material` (`kode_material`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_supplier` (`kode_supplier`),
  ADD KEY `idx_kode_supplier` (`kode_supplier`),
  ADD KEY `idx_nama_supplier` (`nama_supplier`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `transaksi_timbangan`
--
ALTER TABLE `transaksi_timbangan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_tiket` (`no_tiket`),
  ADD KEY `idx_no_tiket` (`no_tiket`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_kendaraan` (`id_kendaraan`),
  ADD KEY `idx_supplier` (`id_supplier`),
  ADD KEY `idx_operator` (`operator_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_polisi` (`no_polisi`),
  ADD KEY `idx_tanggal_status` (`tanggal`,`status`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_module` (`user_id`,`module`);

--
-- Indeks untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_preference` (`user_id`,`preference_key`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT untuk tabel `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `transaksi_timbangan`
--
ALTER TABLE `transaksi_timbangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi_timbangan` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi_timbangan`
--
ALTER TABLE `transaksi_timbangan`
  ADD CONSTRAINT `transaksi_timbangan_ibfk_1` FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transaksi_timbangan_ibfk_2` FOREIGN KEY (`id_supplier`) REFERENCES `supplier` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transaksi_timbangan_ibfk_3` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
