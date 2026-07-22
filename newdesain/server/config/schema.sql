-- ============================================
-- SQLite Schema for Weighbridge - Arroyan Jv Teknik
-- ============================================

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  nama_lengkap TEXT NOT NULL,
  password TEXT NOT NULL,
  role TEXT CHECK(role IN ('admin','operator')) DEFAULT 'operator',
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT,
  last_login TEXT
);

CREATE TABLE IF NOT EXISTS pengaturan_gaji (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarif_per_kg REAL DEFAULT 0,
  tarif_supir REAL DEFAULT 0,
  tarif_pemuat REAL DEFAULT 0,
  keterangan TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  level TEXT DEFAULT 'INFO',
  message TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  setting_key TEXT NOT NULL UNIQUE,
  setting_value TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS supplier (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kode_supplier TEXT NOT NULL UNIQUE,
  nama_supplier TEXT NOT NULL,
  total_hutang REAL DEFAULT 0,
  hutang_terakhir_update TEXT,
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  default_harga REAL DEFAULT 0,
  default_potongan REAL DEFAULT 0,
  is_temporary INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS kendaraan (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  no_polisi TEXT NOT NULL UNIQUE,
  nama_supir TEXT,
  tara_avg REAL DEFAULT 0,
  jenis_kendaraan TEXT DEFAULT 'truk',
  kepemilikan TEXT DEFAULT 'Perusahaan',
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nama_customer TEXT NOT NULL,
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS transaksi_timbangan (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  no_tiket TEXT NOT NULL UNIQUE,
  no_do TEXT,
  nama_supir TEXT,
  id_kendaraan INTEGER,
  no_polisi TEXT,
  id_supplier INTEGER,
  id_customer INTEGER,
  id_supir INTEGER,
  id_gaji INTEGER,
  jenis_material TEXT,
  berat_bruto REAL DEFAULT 0,
  berat_tara REAL DEFAULT 0,
  berat_timbangan1 REAL DEFAULT 0,
  berat_timbangan2 REAL DEFAULT 0,
  timbang1_locked INTEGER DEFAULT 0,
  timbang2_locked INTEGER DEFAULT 0,
  berat_netto REAL DEFAULT 0,
  netto_akhir REAL DEFAULT 0,
  persen_potongan REAL DEFAULT 0,
  kg_potongan REAL DEFAULT 0,
  harga_per_kg REAL DEFAULT 0,
  total_harga REAL DEFAULT 0,
  potongan_jalan REAL DEFAULT 0,
  potongan_pupuk_rp REAL DEFAULT 0,
  potongan_hutang_rp REAL DEFAULT 0,
  potongan_muat_rp REAL DEFAULT 0,
  sisa_hutang_snapshot REAL,
  potongan_hutang_supplier_rp REAL DEFAULT 0,
  sisa_hutang_supplier_snapshot REAL,
  mode_timbangan TEXT DEFAULT 'beli',
  tanggal TEXT,
  waktu_timbangan1 TEXT,
  waktu_timbangan2 TEXT,
  waktu_keluar TEXT,
  status TEXT CHECK(status IN ('reserved','timbang_1','timbang_2','selesai','dibatalkan')) DEFAULT 'reserved',
  operator_id INTEGER,
  cancelled_at TEXT,
  cancelled_by INTEGER,
  cancel_reason TEXT,
  keterangan TEXT,
  is_langsir INTEGER DEFAULT 0,
  jumlah_trip_langsir INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS transaksi_timbangan_langsir (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_transaksi INTEGER NOT NULL,
  berat_bruto REAL NOT NULL DEFAULT 0,
  waktu_timbang TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tanggal TEXT NOT NULL,
  jenis TEXT CHECK(jenis IN ('masuk','keluar')) NOT NULL,
  jumlah REAL NOT NULL DEFAULT 0,
  keterangan TEXT,
  id_transaksi INTEGER,
  no_tiket TEXT,
  saldo_setelah REAL NOT NULL DEFAULT 0,
  operator_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pengiriman_pabrik (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  no_surat_jalan TEXT NOT NULL UNIQUE,
  tanggal TEXT NOT NULL,
  no_polisi TEXT NOT NULL,
  nama_supir TEXT NOT NULL,
  nama_pabrik TEXT NOT NULL,
  jenis_material TEXT DEFAULT 'tbs',
  berat_timbangan1 REAL DEFAULT 0,
  waktu_timbangan1 TEXT,
  berat_timbangan2 REAL DEFAULT 0,
  waktu_timbangan2 TEXT,
  berat_bruto REAL DEFAULT 0,
  berat_tara REAL DEFAULT 0,
  netto_ram REAL DEFAULT 0,
  netto_pabrik REAL,
  susut REAL,
  persen_susut REAL,
  keterangan TEXT,
  status TEXT CHECK(status IN ('timbang_1','timbang_2','pending','selesai')) DEFAULT 'timbang_1',
  operator_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS supir (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nama_supir TEXT NOT NULL,
  no_telepon TEXT,
  alamat TEXT,
  total_hutang REAL DEFAULT 0,
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS hutang_supir_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_supir INTEGER NOT NULL,
  tanggal TEXT NOT NULL,
  jenis TEXT CHECK(jenis IN ('tambah','bayar')) NOT NULL,
  jumlah REAL NOT NULL DEFAULT 0,
  keterangan TEXT,
  id_transaksi INTEGER,
  saldo_setelah REAL NOT NULL DEFAULT 0,
  operator_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_supir) REFERENCES supir(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hutang_supplier_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_supplier INTEGER NOT NULL,
  tanggal TEXT NOT NULL,
  jenis TEXT CHECK(jenis IN ('tambah','bayar')) NOT NULL,
  jumlah REAL NOT NULL DEFAULT 0,
  keterangan TEXT,
  id_transaksi INTEGER,
  saldo_setelah REAL NOT NULL DEFAULT 0,
  operator_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_supplier) REFERENCES supplier(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pengaturan_gaji (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarif_per_kg REAL NOT NULL DEFAULT 3000,
  keterangan TEXT DEFAULT 'Tarif default',
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_by INTEGER
);

CREATE TABLE IF NOT EXISTS gaji_supir (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_supir INTEGER NOT NULL,
  periode_mulai TEXT NOT NULL,
  periode_akhir TEXT NOT NULL,
  total_berat_kg REAL DEFAULT 0,
  total_trip INTEGER DEFAULT 0,
  tarif_per_kg REAL DEFAULT 0,
  gaji_kotor REAL DEFAULT 0,
  total_potongan REAL DEFAULT 0,
  gaji_bersih REAL DEFAULT 0,
  status TEXT CHECK(status IN ('draft','final','paid')) DEFAULT 'draft',
  catatan TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  created_by INTEGER,
  FOREIGN KEY (id_supir) REFERENCES supir(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS potongan_gaji (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_gaji INTEGER NOT NULL,
  jenis_potongan TEXT NOT NULL,
  keterangan TEXT,
  jumlah REAL NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_gaji) REFERENCES gaji_supir(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  action TEXT NOT NULL,
  description TEXT,
  ip_address TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE VIEW IF NOT EXISTS view_transaksi_lengkap AS
SELECT tt.*, s.nama_supplier, c.nama_customer, u.nama_lengkap as nama_operator, k.no_polisi as plat_kendaraan
FROM transaksi_timbangan tt
LEFT JOIN supplier s ON tt.id_supplier = s.id
LEFT JOIN customers c ON tt.id_customer = c.id
LEFT JOIN users u ON tt.operator_id = u.id
LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id;

CREATE TABLE IF NOT EXISTS karyawan_tkbm (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nama_karyawan TEXT NOT NULL,
  no_telepon TEXT,
  alamat TEXT,
  total_hutang REAL DEFAULT 0,
  status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS pengiriman_tkbm (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_pengiriman INTEGER NOT NULL,
  id_tkbm INTEGER NOT NULL,
  FOREIGN KEY (id_pengiriman) REFERENCES pengiriman_pabrik(id) ON DELETE CASCADE,
  FOREIGN KEY (id_tkbm) REFERENCES karyawan_tkbm(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tkbm_hutang_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_tkbm INTEGER NOT NULL,
  tanggal TEXT NOT NULL,
  jenis TEXT CHECK(jenis IN ('tambah','bayar')) NOT NULL,
  jumlah REAL NOT NULL DEFAULT 0,
  keterangan TEXT,
  id_gaji INTEGER,
  saldo_setelah REAL NOT NULL DEFAULT 0,
  operator_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_tkbm) REFERENCES karyawan_tkbm(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS gaji_tkbm (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_tkbm INTEGER NOT NULL,
  periode_mulai TEXT NOT NULL,
  periode_akhir TEXT NOT NULL,
  total_berat_kg REAL DEFAULT 0,
  total_trip INTEGER DEFAULT 0,
  gaji_kotor REAL DEFAULT 0,
  total_potongan REAL DEFAULT 0,
  gaji_bersih REAL DEFAULT 0,
  status TEXT CHECK(status IN ('draft','final','paid')) DEFAULT 'draft',
  catatan TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  created_by INTEGER,
  FOREIGN KEY (id_tkbm) REFERENCES karyawan_tkbm(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS potongan_gaji_tkbm (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_gaji INTEGER NOT NULL,
  jenis_potongan TEXT NOT NULL,
  keterangan TEXT,
  jumlah REAL NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_gaji) REFERENCES gaji_tkbm(id) ON DELETE CASCADE
);
