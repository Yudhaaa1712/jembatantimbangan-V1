-- Database Schema untuk Master Data Jembatan Timbangan
-- Jalankan query ini di phpMyAdmin atau melalui command line

-- =============================================
-- TABLE: SUPPLIER
-- =============================================
CREATE TABLE IF NOT EXISTS `supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_supplier` varchar(20) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `kontak_person` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_supplier` (`kode_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: KENDARAAN
-- =============================================
CREATE TABLE IF NOT EXISTS `kendaraan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_polisi` varchar(15) NOT NULL,
  `jenis_kendaraan` enum('truk','tronton','container','pickup','lainnya') NOT NULL DEFAULT 'truk',
  `pemilik` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `nama_supir` varchar(50) DEFAULT NULL,
  `kapasitas_maksimal` decimal(10,2) DEFAULT NULL,
  `id_supplier` int(11) DEFAULT NULL,
  `rfid_tag` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_polisi` (`no_polisi`),
  KEY `id_supplier` (`id_supplier`),
  CONSTRAINT `kendaraan_ibfk_1` FOREIGN KEY (`id_supplier`) REFERENCES `supplier` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: MATERIAL TYPES
-- =============================================
CREATE TABLE IF NOT EXISTS `material_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_material` varchar(20) DEFAULT NULL,
  `jenis_material` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kategori` varchar(20) DEFAULT 'umum',
  `satuan` varchar(10) DEFAULT 'Kg',
  `harga_per_kg` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `jenis_material` (`jenis_material`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: CUSTOMER (Optional untuk future development)
-- =============================================
CREATE TABLE IF NOT EXISTS `customer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_customer` varchar(20) NOT NULL,
  `nama_customer` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `kontak_person` varchar(50) DEFAULT NULL,
  `kredit_limit` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive','blacklist') NOT NULL DEFAULT 'active',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_customer` (`kode_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: SETTINGS
-- =============================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: USER ROLES (Optional)
-- =============================================
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: ACTIVITY LOGS
-- =============================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: VEHICLE CATEGORIES
-- =============================================
CREATE TABLE IF NOT EXISTS `vehicle_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_capacity` decimal(10,2) DEFAULT NULL,
  `min_capacity` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: PRICING HISTORY
-- =============================================
CREATE TABLE IF NOT EXISTS `pricing_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_type_id` int(11) NOT NULL,
  `old_price` decimal(15,2) DEFAULT NULL,
  `new_price` decimal(15,2) NOT NULL,
  `effective_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `material_type_id` (`material_type_id`),
  KEY `effective_date` (`effective_date`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `pricing_history_ibfk_1` FOREIGN KEY (`material_type_id`) REFERENCES `material_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pricing_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INSERT DEFAULT DATA
-- =============================================

-- Default Material Types
INSERT IGNORE INTO `material_types` (`jenis_material`, `deskripsi`, `kategori`, `satuan`, `harga_per_kg`) VALUES
('tbs', 'Tandan Buah Segar - Fresh Fruit Bunches', 'bahan_baku', 'Kg', 2000.00),
('cpo', 'Crude Palm Oil - Minyak Sawit Mentah', 'produksi', 'Kg', 15000.00),
('kernel', 'Palm Kernel - Inti Sawit', 'produksi', 'Kg', 8000.00),
('brondolan', 'Brondolan - Sisa TBS', 'limbah', 'Kg', 500.00),
('lainnya', 'Material lainnya', 'umum', 'Kg', 0.00);

-- Default Vehicle Categories
INSERT IGNORE INTO `vehicle_categories` (`category_name`, `description`, `max_capacity`, `min_capacity`) VALUES
('truk', 'Truk standar', 15000.00, 1000.00),
('tronton', 'Truk tronton besar', 35000.00, 10000.00),
('container', 'Kontainer', 40000.00, 15000.00),
('pickup', 'Pickup kecil', 3000.00, 500.00),
('lainnya', 'Jenis kendaraan lainnya', 50000.00, 0.00);

-- Default System Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('company_name', 'PT. Jembatan Timbangan', 'Nama perusahaan'),
('company_address', 'Jl. Industri No. 123, Jakarta', 'Alamat perusahaan'),
('company_phone', '021-12345678', 'No. telepon perusahaan'),
('company_email', 'info@timbangan.com', 'Email perusahaan'),
('ticket_prefix', 'TKT', 'Prefix nomor tiket'),
('currency', 'IDR', 'Mata uang'),
('decimal_places', '0', 'Jumlah desimal'),
('working_hours_start', '06:00', 'Jam mulai operasional'),
('working_hours_end', '22:00', 'Jam selesai operasional'),
('auto_refresh_interval', '30', 'Interval auto refresh (detik)'),
('timezone', 'Asia/Jakarta', 'Timezone sistem'),
('date_format', 'd/m/Y', 'Format tanggal'),
('time_format', '24', 'Format waktu (12/24)'),
('language', 'id', 'Bahasa sistem'),
('backup_schedule', 'daily', 'Jadwal backup'),
('max_file_upload', '10', 'Max file upload (MB)'),
('enable_rfid', '0', 'Enable RFID feature'),
('enable_auto_print', '1', 'Auto print receipt'),
('enable_notifications', '1', 'Enable notifications'),
('enable_audit_log', '1', 'Enable audit log');

-- Default User Roles
INSERT IGNORE INTO `user_roles` (`role_name`, `display_name`, `description`, `permissions`) VALUES
('admin', 'Administrator', 'Full system access', '{"all": ["create", "read", "update", "delete"]}'),
('operator', 'Operator', 'Weighing operations access', '{"timbangan": ["create", "read", "update"], "transaksi": ["read"]}'),
('viewer', 'Viewer', 'Read only access', '{"transaksi": ["read"], "laporan": ["read"]}');