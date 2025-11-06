-- Database Schema for Jembatan Timbangan Sawit
-- Generated automatically

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS jembatan_timbangan_sawit
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jembatan_timbangan_sawit;

-- ==================== USERS TABLE ====================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'operator') DEFAULT 'operator',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('admin', '0192023a7bbd73250516f069df18b500', 'Administrator', 'admin@timbangan.com', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- ==================== KENDARAAN TABLE ====================
CREATE TABLE IF NOT EXISTS kendaraan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_polisi VARCHAR(20) UNIQUE NOT NULL,
    nama_supir VARCHAR(100),
    jenis_kendaraan VARCHAR(50),
    kapasitas DECIMAL(10,2) DEFAULT 0,
    tara_avg DECIMAL(10,2) DEFAULT 0,
    keterangan TEXT,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== SUPPLIER TABLE ====================
CREATE TABLE IF NOT EXISTS supplier (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_supplier VARCHAR(100) NOT NULL,
    alamat TEXT,
    telepon VARCHAR(20),
    kontak VARCHAR(100),
    keterangan TEXT,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== CUSTOMER TABLE ====================
CREATE TABLE IF NOT EXISTS customer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_customer VARCHAR(100) NOT NULL,
    alamat TEXT,
    telepon VARCHAR(20),
    email VARCHAR(100),
    kontak VARCHAR(100),
    keterangan TEXT,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== TRANSAKSI TIMBANGAN TABLE ====================
CREATE TABLE IF NOT EXISTS transaksi_timbangan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_tiket VARCHAR(50) UNIQUE NOT NULL,
    no_do VARCHAR(50),
    tanggal DATE NOT NULL,
    waktu_masuk DATETIME,
    waktu_keluar DATETIME,

    -- Data Kendaraan
    id_kendaraan INT,
    no_polisi VARCHAR(20),
    nama_supir VARCHAR(100),

    -- Data Supplier/Customer
    id_supplier INT,
    id_customer INT,

    -- Data Material
    jenis_material ENUM('tbs', 'cpo', 'kernel', 'brondolan'),

    -- Data Berat
    berat_bruto DECIMAL(10,2) DEFAULT 0,
    berat_tara DECIMAL(10,2) DEFAULT 0,
    berat_netto DECIMAL(10,2) DEFAULT 0,

    -- Data Potongan
    persen_potongan DECIMAL(5,2) DEFAULT 0,
    kg_potongan DECIMAL(10,2) DEFAULT 0,
    netto_akhir DECIMAL(10,2) DEFAULT 0,

    -- Data Harga
    harga_per_kg DECIMAL(15,2) DEFAULT 0,
    total_harga DECIMAL(15,2) DEFAULT 0,

    -- Status
    status ENUM('timbang_1', 'timbang_2', 'selesai') DEFAULT 'timbang_1',

    -- Lock flags
    timbang1_locked BOOLEAN DEFAULT FALSE,
    timbang2_locked BOOLEAN DEFAULT FALSE,

    -- Operator
    operator_id INT,
    operator_id2 INT,

    -- Additional Info
    keterangan TEXT,

    -- Timestamps
    waktu_timbangan1 DATETIME,
    waktu_timbangan2 DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (id_kendaraan) REFERENCES kendaraan(id),
    FOREIGN KEY (id_supplier) REFERENCES supplier(id),
    FOREIGN KEY (id_customer) REFERENCES customer(id),
    FOREIGN KEY (operator_id) REFERENCES users(id),
    FOREIGN KEY (operator_id2) REFERENCES users(id),

    -- Indexes
    INDEX idx_no_tiket (no_tiket),
    INDEX idx_tanggal (tanggal),
    INDEX idx_status (status),
    INDEX idx_kendaraan (id_kendaraan),
    INDEX idx_supplier (id_supplier),
    INDEX idx_customer (id_customer)
);

-- ==================== SAMPLE DATA ====================
-- Insert sample kendaraan
INSERT INTO kendaraan (no_polisi, nama_supir, jenis_kendaraan, kapasitas, tara_avg) VALUES
('B 1234 ABC', 'Ahmad', 'Tronton', 30000, 15000),
('B 5678 DEF', 'Budi', 'Tronton', 25000, 12000),
('B 9012 GHI', 'Chandra', 'Colt Diesel', 8000, 4000),
('B 3456 JKL', 'Doni', 'Tronton', 28000, 14000)
ON DUPLICATE KEY UPDATE no_polisi=no_polisi;

-- Insert sample supplier
INSERT INTO supplier (nama_supplier, alamat, telepon, kontak) VALUES
('PT. Sawit Jaya', 'Jl. Sawit No. 1, Medan', '061-123456', 'Pak Haji'),
('PT. Kelapa Sejahtera', 'Jl. Kelapa No. 2, Pekanbaru', '062-789012', 'Ibu Siti'),
('UD. Tani Makmur', 'Jl. Tani No. 3, Palembang', '063-345678', 'Pak Joko')
ON DUPLICATE KEY UPDATE nama_supplier=nama_supplier;

-- Insert sample customer
INSERT INTO customer (nama_customer, alamat, telepon, kontak) VALUES
('PT. Minyak Sawit Raya', 'Jl. Industri No. 1, Batam', '0778-123456', 'Pak Robert'),
('PT. Karya Agro Mandiri', 'Jl. Agro No. 2, Jakarta', '021-789012', 'Ibu Maria'),
('PT. Agro Sejati', 'Jl. Agro Sejahtera No. 3, Surabaya', '031-345678', 'Pak Hendra')
ON DUPLICATE KEY UPDATE nama_customer=nama_customer;

-- ==================== VIEWS ====================
-- Create comprehensive view for transactions
CREATE OR REPLACE VIEW view_transaksi_lengkap AS
SELECT
    tt.*,
    k.no_polisi,
    k.nama_supir as nama_supir_kendaraan,
    k.jenis_kendaraan,
    k.tara_avg as tara_rata_kendaraan,
    s.nama_supplier,
    s.alamat as alamat_supplier,
    s.telepon as telepon_supplier,
    c.nama_customer,
    c.alamat as alamat_customer,
    c.telepon as telepon_customer,
    u1.nama_lengkap as nama_operator1,
    u2.nama_lengkap as nama_operator2,
    CASE
        WHEN tt.status = 'timbang_1' THEN 'Timbangan 1'
        WHEN tt.status = 'timbang_2' THEN 'Timbangan 2'
        WHEN tt.status = 'selesai' THEN 'Selesai'
        ELSE tt.status
    END as status_text
FROM transaksi_timbangan tt
LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
LEFT JOIN supplier s ON tt.id_supplier = s.id
LEFT JOIN customer c ON tt.id_customer = c.id
LEFT JOIN users u1 ON tt.operator_id = u1.id
LEFT JOIN users u2 ON tt.operator_id2 = u2.id;

-- Create view for daily summary
CREATE OR REPLACE VIEW view_daily_summary AS
SELECT
    tanggal,
    COUNT(*) as total_transaksi,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as transaksi_selesai,
    SUM(CASE WHEN status = 'selesai' THEN berat_netto ELSE 0 END) as total_netto,
    AVG(CASE WHEN status = 'selesai' THEN berat_netto ELSE NULL END) as rata_netto,
    SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_pendapatan,
    COUNT(CASE WHEN jenis_material = 'tbs' THEN 1 END) as jumlah_tbs,
    COUNT(CASE WHEN jenis_material = 'cpo' THEN 1 END) as jumlah_cpo,
    COUNT(CASE WHEN jenis_material = 'kernel' THEN 1 END) as jumlah_kernel,
    COUNT(CASE WHEN jenis_material = 'brondolan' THEN 1 END) as jumlah_brondolan
FROM transaksi_timbangan
GROUP BY tanggal
ORDER BY tanggal DESC;

-- Create view for monthly summary
CREATE OR REPLACE VIEW view_monthly_summary AS
SELECT
    DATE_FORMAT(tanggal, '%Y-%m') as bulan,
    DATE_FORMAT(tanggal, '%M %Y') as bulan_text,
    COUNT(*) as total_transaksi,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as transaksi_selesai,
    SUM(CASE WHEN status = 'selesai' THEN berat_netto ELSE 0 END) as total_netto,
    SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_pendapatan
FROM transaksi_timbangan
GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), DATE_FORMAT(tanggal, '%M %Y')
ORDER BY bulan DESC;

-- ==================== TRIGGERS ====================
-- Trigger to update timestamp on insert
DELIMITER //
CREATE TRIGGER before_transaksi_insert
BEFORE INSERT ON transaksi_timbangan
FOR EACH ROW
BEGIN
    IF NEW.waktu_masuk IS NULL THEN
        SET NEW.waktu_masuk = NOW();
    END IF;
END//
DELIMITER ;

-- Trigger to update timestamp on update
DELIMITER //
CREATE TRIGGER before_transaksi_update
BEFORE UPDATE ON transaksi_timbangan
FOR EACH ROW
BEGIN
    IF NEW.status = 'timbang_2' AND OLD.status = 'timbang_1' AND NEW.waktu_timbangan2 IS NULL THEN
        SET NEW.waktu_timbangan2 = NOW();
    END IF;
END//
DELIMITER ;

-- ==================== STORED PROCEDURES ====================
-- Procedure to generate ticket number
DELIMITER //
CREATE PROCEDURE generate_ticket_number()
BEGIN
    DECLARE today_date DATE;
    DECLARE counter INT;
    DECLARE ticket_number VARCHAR(50);

    SET today_date = CURDATE();

    SELECT COUNT(*) + 1 INTO counter
    FROM transaksi_timbangan
    WHERE DATE(created_at) = today_date;

    SET ticket_number = CONCAT('T', DATE_FORMAT(today_date, '%Y%m%d'), LPAD(counter, 3, '0'));

    SELECT ticket_number;
END//
DELIMITER ;

-- Procedure to get today's statistics
DELIMITER //
CREATE PROCEDURE get_today_stats()
BEGIN
    SELECT
        COUNT(*) as total_transaksi,
        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as selesai_transaksi,
        SUM(CASE WHEN status = 'selesai' THEN berat_netto ELSE 0 END) as total_netto,
        SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_harga,
        AVG(CASE WHEN status = 'selesai' THEN berat_netto ELSE NULL END) as rata_netto
    FROM transaksi_timbangan
    WHERE DATE(tanggal) = CURDATE();
END//
DELIMITER ;

-- ==================== INDEXES FOR PERFORMANCE ====================
-- Add additional indexes for better performance
CREATE INDEX IF NOT EXISTS idx_transaksi_tanggal_status ON transaksi_timbangan(tanggal, status);
CREATE INDEX IF NOT EXISTS idx_transaksi_supplier_tanggal ON transaksi_timbangan(id_supplier, tanggal);
CREATE INDEX IF NOT EXISTS idx_transaksi_customer_tanggal ON transaksi_timbangan(id_customer, tanggal);
CREATE INDEX IF NOT EXISTS idx_transaksi_material_tanggal ON transaksi_timbangan(jenis_material, tanggal);
CREATE INDEX IF NOT EXISTS idx_transaksi_created_at ON transaksi_timbangan(created_at);

-- ==================== FINAL NOTES ====================
-- This script creates:
-- 1. Complete database structure
-- 2. Sample data for testing
-- 3. Views for easy reporting
-- 4. Triggers for automation
-- 5. Stored procedures for common operations
-- 6. Indexes for performance

-- To use this script:
-- 1. Create database in MySQL
-- 2. Run this script to create all tables and data
-- 3. Update config/database.php with your database credentials
-- 4. Test the application

-- Default login credentials:
-- Username: admin
-- Password: admin123

-- Important: Change default password in production!