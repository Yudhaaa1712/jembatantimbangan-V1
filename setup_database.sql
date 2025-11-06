

-- Create Database
CREATE DATABASE IF NOT EXISTS jembatan_timbangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jembatan_timbangan;

-- Drop existing tables (fresh install)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS transaksi_timbangan;
DROP TABLE IF EXISTS supplier;
DROP TABLE IF EXISTS kendaraan;
DROP TABLE IF EXISTS users;


CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'operator', 'viewer') DEFAULT 'operator',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE kendaraan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_polisi VARCHAR(20) UNIQUE NOT NULL,
    jenis_kendaraan ENUM('truk', 'tronton', 'container', 'pickup', 'lainnya') DEFAULT 'truk',
    kapasitas_maksimal DECIMAL(10,2) DEFAULT 0,
    pemilik VARCHAR(100),
    no_telepon VARCHAR(20),
    alamat TEXT,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_no_polisi (no_polisi),
    INDEX idx_status (status)
);

-- Sample Vehicle Data
INSERT INTO kendaraan (no_polisi, jenis_kendaraan, kapasitas_maksimal, pemilik, status) VALUES
('B 1234 AB', 'truk', 50000, 'PT. Logistik Jaya', 'active'),
('B 5678 CD', 'tronton', 80000, 'PT. Angkutan Berat', 'active'),
('B 9012 EF', 'container', 100000, 'PT. Container Indonesia', 'active'),
('B 3456 GH', 'pickup', 25000, 'CV. Angkutan Cepat', 'active'),
('B 7890 IJ', 'truk', 60000, 'PT. Sawit Mandiri', 'active');


CREATE TABLE supplier (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_supplier VARCHAR(20) UNIQUE NOT NULL,
    nama_supplier VARCHAR(100) NOT NULL,
    alamat TEXT,
    no_telepon VARCHAR(20),
    email VARCHAR(100),
    npwp VARCHAR(30),
    kontak_person VARCHAR(100),
    status ENUM('active', 'inactive', 'blacklist') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kode_supplier (kode_supplier),
    INDEX idx_nama_supplier (nama_supplier),
    INDEX idx_status (status)
);

-- Sample Supplier Data
INSERT INTO supplier (kode_supplier, nama_supplier, alamat, no_telepon, status) VALUES
('SUP-251102-001', 'PT. Sawit Maju Jaya', 'Jl. Industri No. 123, Jakarta Utara', '021-5551234', 'active'),
('SUP-251102-002', 'PT. Karya Mandiri', 'Jl. Pertanian No. 456, Bogor', '021-5555678', 'active'),
('SUP-251102-003', 'CV. Agro Lestari', 'Jl. Perkebunan No. 789, Tangerang', '021-5559012', 'active'),
('SUP-251102-004', 'PT. Mitra Sejahtera', 'Jl. Industri Raya No. 100, Bekasi', '021-5553456', 'active'),
('SUP-251102-005', 'PT. Tropical Harvest', 'Jl. Sawit No. 200, Medan', '061-5557890', 'active');

-- =====================================================
-- 4. TABLE TRANSAKSI TIMBANGAN (MAIN TABLE)
-- =====================================================
CREATE TABLE transaksi_timbangan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_tiket VARCHAR(20) UNIQUE NOT NULL,
    tanggal DATE NOT NULL,
    waktu_masuk TIME,
    waktu_timbangan1 TIME,
    waktu_keluar TIME,
    waktu_timbangan2 TIME,

    -- Data Kendaraan
    id_kendaraan INT,
    no_polisi VARCHAR(20) NOT NULL,
    nama_supir VARCHAR(100),

    -- Data Supplier & Customer
    id_supplier INT,
    id_customer INT,

    -- Data Material
    jenis_material ENUM('tbs', 'cpo', 'kernel', 'brondolan', 'lainnya') NOT NULL,

    -- DATA BERAT (PALING KRUSIAL!)
    berat_bruto DECIMAL(10,2) DEFAULT 0.00,               -- Backup berat
    berat_timbangan1 DECIMAL(10,2) DEFAULT 0.00,           -- **Weight asli Timbang 1**
    timbang1_locked TINYINT(1) DEFAULT 0,                  -- Lock status Timbang 1
    berat_tara DECIMAL(10,2) DEFAULT 0.00,                -- Backup tara
    berat_timbangan2 DECIMAL(10,2) DEFAULT 0.00,           -- **Weight asli Timbang 2**
    timbang2_locked TINYINT(1) DEFAULT 0,                  -- Lock status Timbang 2

    -- Data Potongan
    persen_potongan DECIMAL(5,2) DEFAULT 0.00,
    kg_potongan DECIMAL(10,2) DEFAULT 0.00,

    -- DATA BERAT HASIL (AUTO CALCULATED)
    berat_netto DECIMAL(10,2) GENERATED ALWAYS AS (
        CASE
            WHEN berat_timbangan1 > 0 AND berat_timbangan2 > 0 THEN
                (berat_timbangan1 - berat_timbangan2) -
                (((berat_timbangan1 - berat_timbangan2) * persen_potongan / 100) + kg_potongan)
            ELSE 0
        END
    ) STORED,

    -- Data Harga
    harga_per_kg DECIMAL(10,2) DEFAULT 0.00,
    total_harga DECIMAL(15,2) GENERATED ALWAYS AS (
        CASE
            WHEN berat_timbangan1 > 0 AND berat_timbangan2 > 0 AND harga_per_kg > 0 THEN
                ((berat_timbangan1 - berat_timbangan2) -
                (((berat_timbangan1 - berat_timbangan2) * persen_potongan / 100) + kg_potongan)) * harga_per_kg
            ELSE 0
        END
    ) STORED,

    -- Data Tambahan
    keterangan TEXT,
    foto_masuk VARCHAR(255),
    foto_keluar VARCHAR(255),

    -- Status & Tracking
    status ENUM('timbang_1', 'timbang_2', 'selesai', 'batal') DEFAULT 'timbang_1',
    operator_id INT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes for Performance
    INDEX idx_no_tiket (no_tiket),
    INDEX idx_tanggal (tanggal),
    INDEX idx_status (status),
    INDEX idx_kendaraan (id_kendaraan),
    INDEX idx_supplier (id_supplier),
    INDEX idx_operator (operator_id),
    INDEX idx_created_at (created_at),
    INDEX idx_polisi (no_polisi),
    INDEX idx_tanggal_status (tanggal, status),

    -- Foreign Keys
    FOREIGN KEY (id_kendaraan) REFERENCES kendaraan(id) ON DELETE SET NULL,
    FOREIGN KEY (id_supplier) REFERENCES supplier(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 5. TABLE SETTINGS (Configuration)
-- =====================================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('nama_perusahaan', 'PT. Jembatan Timbangan Sawit', 'Nama perusahaan'),
('alamat_perusahaan', 'Jl. Industri No. 123, Jakarta Utara', 'Alamat perusahaan'),
('telepon_perusahaan', '021-5551234', 'Telepon perusahaan'),
('email_perusahaan', 'info@timbangan-sawit.com', 'Email perusahaan'),
('auto_backup', '1', 'Backup otomatis aktif'),
('backup_path', '/backups/', 'Path folder backup'),
('max_weight_capacity', '100000', 'Kapasitas maksimal timbangan (kg)'),
('min_weight', '100', 'Berat minimal valid (kg)'),
('tolerance_weight', '50', 'Toleransi berat (kg)'),
('default_potongan', '3', 'Default potongan (%)'),
('currency', 'IDR', 'Mata uang'),
('timezone', 'Asia/Jakarta', 'Timezone aplikasi'),
('logo_url', '/assets/logo.png', 'URL logo perusahaan');

-- =====================================================
-- 6. TABLE ACTIVITY LOGS (Audit Trail)
-- =====================================================
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    table_name VARCHAR(50),
    record_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_table_record (table_name, record_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 7. VIEWS FOR REPORTS
-- =====================================================

-- View untuk transaksi lengkap
CREATE VIEW view_transaksi_lengkap AS
SELECT
    tt.id,
    tt.no_tiket,
    tt.tanggal,
    tt.waktu_masuk,
    tt.waktu_timbangan1,
    tt.waktu_keluar,
    tt.waktu_timbangan2,
    tt.no_polisi,
    k.jenis_kendaraan,
    k.pemilik as pemilik_kendaraan,
    tt.nama_supir,
    s.kode_supplier,
    s.nama_supplier,
    tt.jenis_material,
    tt.berat_timbangan1,
    tt.berat_timbangan2,
    tt.berat_netto,
    tt.persen_potongan,
    tt.kg_potongan,
    tt.harga_per_kg,
    tt.total_harga,
    tt.keterangan,
    tt.status,
    u.nama_lengkap as operator_name,
    tt.created_at,
    tt.updated_at
FROM transaksi_timbangan tt
LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
LEFT JOIN supplier s ON tt.id_supplier = s.id
LEFT JOIN users u ON tt.operator_id = u.id;

-- View untuk laporan harian
CREATE VIEW view_laporan_harian AS
SELECT
    tanggal,
    COUNT(*) as total_transaksi,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as transaksi_selesai,
    SUM(berat_timbangan1) as total_bruto,
    SUM(berat_timbangan2) as total_tara,
    SUM(berat_netto) as total_netto,
    SUM(total_harga) as total_omzet,
    AVG(berat_netto) as rata_netto
FROM transaksi_timbangan
WHERE status = 'selesai'
GROUP BY tanggal;

-- =====================================================
-- 8. STORED PROCEDURES (Helper Functions)
-- =====================================================

DELIMITER //

-- Generate nomor tiket otomatis
CREATE PROCEDURE GenerateTicketNumber(OUT ticket_no VARCHAR(20))
BEGIN
    DECLARE today_count INT DEFAULT 0;
    DECLARE date_prefix VARCHAR(10);

    SET date_prefix = DATE_FORMAT(CURDATE(), '%y%m%d');

    SELECT COUNT(*) INTO today_count
    FROM transaksi_timbangan
    WHERE tanggal = CURDATE();

    SET ticket_no = CONCAT('TKT-', date_prefix, '-', LPAD(today_count + 1, 3, '0'));
END //

-- Get transaction statistics
CREATE PROCEDURE GetTransactionStats(
    IN start_date DATE,
    IN end_date DATE,
    OUT total_trans INT,
    OUT total_weight DECIMAL(15,2),
    OUT total_revenue DECIMAL(20,2)
)
BEGIN
    SELECT
        COUNT(*),
        COALESCE(SUM(berat_netto), 0),
        COALESCE(SUM(total_harga), 0)
    INTO total_trans, total_weight, total_revenue
    FROM transaksi_timbangan
    WHERE tanggal BETWEEN start_date AND end_date
    AND status = 'selesai';
END //

DELIMITER ;

-- =====================================================
-- 9. TRIGGERS (Automation)
-- =====================================================

DELIMITER //

-- Auto-log activity
CREATE TRIGGER log_transaksi_insert
AFTER INSERT ON transaksi_timbangan
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description, table_name, record_id)
    VALUES (NEW.operator_id, 'CREATE', CONCAT('Transaksi baru: ', NEW.no_tiket), 'transaksi_timbangan', NEW.id);
END //

CREATE TRIGGER log_transaksi_update
AFTER UPDATE ON transaksi_timbangan
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_logs (user_id, action, description, table_name, record_id)
        VALUES (NEW.operator_id, 'STATUS_CHANGE',
               CONCAT('Status ', OLD.status, ' -> ', NEW.status, ' untuk ', NEW.no_tiket),
               'transaksi_timbangan', NEW.id);
    END IF;
END //

DELIMITER ;

-- =====================================================
-- 10. SAMPLE DATA FOR TESTING
-- =====================================================

-- Sample transactions untuk testing
INSERT INTO transaksi_timbangan (
    no_tiket, tanggal, waktu_masuk, id_kendaraan, no_polisi, nama_supir,
    id_supplier, jenis_material, berat_timbangan1, timbang1_locked,
    harga_per_kg, status, operator_id
) VALUES
('TKT-251102-001', '2025-11-02', '08:30:00', 1, 'B 1234 AB', 'Ahmad',
 1, 'tbs', 25000.50, 1, 5000.00, 'timbang_2', 1),
('TKT-251102-002', '2025-11-02', '09:15:00', 2, 'B 5678 CD', 'Budi',
 2, 'cpo', 45000.75, 1, 7500.00, 'timbang_1', 1),
('TKT-251102-003', '2025-11-02', '10:00:00', 3, 'B 9012 EF', 'Chandra',
 3, 'kernel', 15000.00, 1, 10000.00, 'timbang_1', 1);

-- =====================================================
-- 11. FUTURE FEATURES TABLES (PREPARED)
-- =====================================================

-- Customer Management Table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_customer VARCHAR(20) UNIQUE NOT NULL,
    nama_customer VARCHAR(100) NOT NULL,
    alamat TEXT,
    no_telepon VARCHAR(20),
    email VARCHAR(100),
    npwp VARCHAR(30),
    kontak_person VARCHAR(100),
    kredit_limit DECIMAL(15,2) DEFAULT 0,
    status ENUM('active', 'inactive', 'blacklist') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kode_customer (kode_customer),
    INDEX idx_nama_customer (nama_customer)
);

-- Invoice Management Table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_invoice VARCHAR(30) UNIQUE NOT NULL,
    transaksi_id INT,
    id_customer INT,
    tanggal_invoice DATE,
    jatuh_tempo DATE,
    total_amount DECIMAL(15,2) NOT NULL,
    ppn_percent DECIMAL(5,2) DEFAULT 11,
    ppn_amount DECIMAL(15,2) DEFAULT 0,
    diskon_percent DECIMAL(5,2) DEFAULT 0,
    diskon_amount DECIMAL(15,2) DEFAULT 0,
    grand_total DECIMAL(15,2) NOT NULL,
    status_pembayaran ENUM('unpaid', 'partial', 'paid', 'overdue') DEFAULT 'unpaid',
    tanggal_bayar DATE,
    metode_pembayaran VARCHAR(50),
    keterangan_invoice TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi_timbangan(id),
    FOREIGN KEY (id_customer) REFERENCES customers(id),
    INDEX idx_no_invoice (no_invoice),
    INDEX idx_customer (id_customer),
    INDEX idx_status_pembayaran (status_pembayaran)
);

-- User Preferences Table
CREATE TABLE user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_preference (user_id, preference_key)
);

-- Detailed User Permissions
CREATE TABLE user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    module VARCHAR(50) NOT NULL,
    can_create TINYINT(1) DEFAULT 0,
    can_read TINYINT(1) DEFAULT 1,
    can_update TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_module (user_id, module)
);

-- =====================================================
-- 12. VIEWS FOR ENHANCED REPORTING
-- =====================================================

-- Enhanced Transaction View with Customer Info
CREATE VIEW view_transaksi_complete AS
SELECT
    tt.*,
    k.jenis_kendaraan,
    k.pemilik as pemilik_kendaraan,
    s.kode_supplier,
    s.nama_supplier,
    u.nama_lengkap as operator_name,
    CASE
        WHEN tt.id_customer IS NOT NULL THEN c.nama_customer
        ELSE 'Walk-in Customer'
    END as customer_name
FROM transaksi_timbangan tt
LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
LEFT JOIN supplier s ON tt.id_supplier = s.id
LEFT JOIN users u ON tt.operator_id = u.id
LEFT JOIN customers c ON tt.id_customer = c.id;

-- Monthly Report View
CREATE VIEW view_laporan_bulanan AS
SELECT
    YEAR(tanggal) as tahun,
    MONTH(tanggal) as bulan,
    DATE_FORMAT(tanggal, '%Y-%m') as periode,
    COUNT(*) as total_transaksi,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as transaksi_selesai,
    SUM(berat_timbangan1) as total_bruto,
    SUM(berat_timbangan2) as total_tara,
    SUM(berat_netto) as total_netto,
    SUM(total_harga) as total_omzet,
    AVG(harga_per_kg) as rata_harga_per_kg
FROM transaksi_timbangan
WHERE status = 'selesai'
GROUP BY YEAR(tanggal), MONTH(tanggal)
ORDER BY tahun DESC, bulan DESC;

-- =====================================================
-- SETUP COMPLETE!
-- =====================================================

SELECT 'DATABASE SETUP COMPLETED!' AS status,
       COUNT(*) as total_tables
FROM information_schema.tables
WHERE table_schema = 'jembatan_timbangan';

-- Test data count
SELECT 'Sample Data Inserted:' as info,
       (SELECT COUNT(*) FROM users) as users,
       (SELECT COUNT(*) FROM kendaraan) as kendaraan,
       (SELECT COUNT(*) FROM supplier) as supplier,
       (SELECT COUNT(*) FROM transaksi_timbangan) as transaksi,
       (SELECT COUNT(*) FROM customers) as customers,
       (SELECT COUNT(*) FROM invoices) as invoices;

SELECT 'Current Features: READY!' as current_status,
       'Future Features: PREPARED!' as future_status,
       'Database: OPTIMIZED!' as database_status;