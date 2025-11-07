-- Insert sample kendaraan data
INSERT INTO kendaraan (no_polisi, jenis_kendaraan, kapasitas_maksimal, pemilik, status) VALUES
('B 1234 ABC', 'tronton', 30000, 'Ahmad', 'active'),
('B 5678 DEF', 'tronton', 25000, 'Budi', 'active'),
('B 9012 GHI', 'truk', 8000, 'Chandra', 'active'),
('B 3456 JKL', 'tronton', 28000, 'Doni', 'active')
ON DUPLICATE KEY UPDATE jenis_kendaraan=VALUES(jenis_kendaraan), kapasitas_maksimal=VALUES(kapasitas_maksimal), pemilik=VALUES(pemilik), status=VALUES(status);