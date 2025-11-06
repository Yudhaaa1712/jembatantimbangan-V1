-- Create table timbangan2 if it doesn't exist
CREATE TABLE IF NOT EXISTS timbangan2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_tiket VARCHAR(50) UNIQUE NOT NULL,
    no_tiket1 VARCHAR(50) NOT NULL,
    no_kendaraan VARCHAR(50) NOT NULL,
    nama_pengemudi VARCHAR(100) NOT NULL,
    nama_suplier VARCHAR(100) NOT NULL,
    material VARCHAR(100) NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    berat1 DECIMAL(10,2) NOT NULL,
    persen_potongan DECIMAL(5,2) DEFAULT 0,
    berat2 DECIMAL(10,2) NOT NULL,
    bruto DECIMAL(10,2) NOT NULL,
    tara DECIMAL(10,2) NOT NULL,
    netto_bt DECIMAL(10,2) NOT NULL,
    total_potongan DECIMAL(10,2) DEFAULT 0,
    netto_akhir DECIMAL(10,2) NOT NULL,
    total_harga DECIMAL(15,2) NOT NULL,
    tanggal DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Check if table exists and add missing columns if needed
-- Note: Only run these ALTER statements if the table already exists without these columns

-- Add persen_potongan column if it doesn't exist
ALTER TABLE timbangan2
ADD COLUMN IF NOT EXISTS persen_potongan DECIMAL(5,2) DEFAULT 0 AFTER berat1;

-- Add total_potongan column if it doesn't exist
ALTER TABLE timbangan2
ADD COLUMN IF NOT EXISTS total_potongan DECIMAL(10,2) DEFAULT 0 AFTER netto_bt;

-- Add indexes for better performance
ALTER TABLE timbangan2
ADD INDEX IF NOT EXISTS idx_no_tiket (no_tiket),
ADD INDEX IF NOT EXISTS idx_no_tiket1 (no_tiket1),
ADD INDEX IF NOT EXISTS idx_tanggal (tanggal);