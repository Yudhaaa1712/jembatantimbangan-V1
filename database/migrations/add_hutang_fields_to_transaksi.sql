-- Migration: Add hutang fields to transaksi_timbangan table
-- File: add_hutang_fields_to_transaksi.sql
-- Purpose: Menambahkan tracking potong hutang di transaksi

-- Tambahkan field hutang ke tabel transaksi_timbangan
ALTER TABLE transaksi_timbangan
ADD COLUMN potong_hutang DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Jumlah hutang yang dipotong dalam transaksi' AFTER berat_timbangan2,
ADD COLUMN sisa_hutang DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Sisa hutang setelah potong' AFTER potong_hutang;

-- Update comment untuk tabel
ALTER TABLE transaksi_timbangan COMMENT = 'Data transaksi timbangan dengan tracking hutang';

-- Insert log migrasi (jika ada tabel migration_log)
INSERT INTO migration_log (migration_name, executed_at, status)
VALUES ('add_hutang_fields_to_transaksi', NOW(), 'success')
ON DUPLICATE KEY UPDATE executed_at = NOW(), status = 'success';