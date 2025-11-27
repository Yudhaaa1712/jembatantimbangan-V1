-- Migration: Add hutang fields to supplier table
-- File: add_supplier_hutang.sql
-- Purpose: Menambahkan tracking hutang supplier

-- Tambahkan field hutang ke tabel supplier
ALTER TABLE supplier
ADD COLUMN total_hutang DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Total hutang supplier' AFTER kontak_person,
ADD COLUMN hutang_terakhir_update DATETIME NULL COMMENT 'Waktu terakhir update hutang' AFTER total_hutang;

-- Update comment untuk tabel
ALTER TABLE supplier COMMENT = 'Data supplier dengan tracking hutang';

-- Insert log migrasi (jika ada tabel migration_log)
INSERT INTO migration_log (migration_name, executed_at, status)
VALUES ('add_supplier_hutang', NOW(), 'success')
ON DUPLICATE KEY UPDATE executed_at = NOW(), status = 'success';