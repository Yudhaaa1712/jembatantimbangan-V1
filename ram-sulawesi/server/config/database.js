/**
 * Database Configuration (SQLite version)
 * Replaces MySQL connection with better-sqlite3
 */
const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

// Resolve database folder name dynamically from package.json name to avoid shared settings
let dbFolderName = 'weighbridge-arroyan';
try {
  const pkgPath = path.join(__dirname, '..', '..', 'package.json');
  if (fs.existsSync(pkgPath)) {
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
    if (pkg.name) {
      dbFolderName = pkg.name;
    }
  }
} catch (e) {
  console.error('[DB] Failed to read package.json for DB folder:', e);
}

const dbDir = path.join(process.env.APPDATA || process.env.USERPROFILE || '.', dbFolderName);
if (!fs.existsSync(dbDir)) {
  fs.mkdirSync(dbDir, { recursive: true });
}

const dbPath = process.env.DB_PATH || path.join(dbDir, 'database.db');
const db = new Database(dbPath, { verbose: null });

// Ensure schema is created on startup
const schemaPath = path.join(__dirname, 'schema.sql');
if (fs.existsSync(schemaPath)) {
  const schemaSql = fs.readFileSync(schemaPath, 'utf8');
  db.exec(schemaSql);
}

// Migration: Check if total_hutang exists in supir table
try {
  const tableInfo = db.prepare("PRAGMA table_info(supir)").all();
  const hasTotalHutang = tableInfo.some(col => col.name === 'total_hutang');
  if (!hasTotalHutang) {
    db.prepare("ALTER TABLE supir ADD COLUMN total_hutang REAL DEFAULT 0").run();
    console.log('[DB Migration] Added total_hutang column to supir table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating supir table:', e);
}

// Migration: Check if default_harga, default_potongan, and is_temporary exist in supplier table
try {
  const tableInfo = db.prepare("PRAGMA table_info(supplier)").all();
  if (!tableInfo.some(col => col.name === 'default_harga')) {
    db.prepare("ALTER TABLE supplier ADD COLUMN default_harga REAL DEFAULT 0").run();
    console.log('[DB Migration] Added default_harga column to supplier table');
  }
  if (!tableInfo.some(col => col.name === 'default_potongan')) {
    db.prepare("ALTER TABLE supplier ADD COLUMN default_potongan REAL DEFAULT 0").run();
    console.log('[DB Migration] Added default_potongan column to supplier table');
  }
  if (!tableInfo.some(col => col.name === 'is_temporary')) {
    db.prepare("ALTER TABLE supplier ADD COLUMN is_temporary INTEGER DEFAULT 0").run();
    console.log('[DB Migration] Added is_temporary column to supplier table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating supplier table:', e);
}

// Migration: Check if id_supir, id_gaji_supir, harga_per_kg, pinjaman, biaya_es_jalan exist in pengiriman_pabrik
try {
  const tableInfo = db.prepare("PRAGMA table_info(pengiriman_pabrik)").all();
  if (!tableInfo.some(col => col.name === 'id_supir')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN id_supir INTEGER").run();
    console.log('[DB Migration] Added id_supir column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'id_gaji_supir')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN id_gaji_supir INTEGER").run();
    console.log('[DB Migration] Added id_gaji_supir column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'harga_per_kg')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN harga_per_kg REAL DEFAULT 0").run();
    console.log('[DB Migration] Added harga_per_kg column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'pinjaman')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN pinjaman REAL DEFAULT 0").run();
    console.log('[DB Migration] Added pinjaman column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'biaya_es_jalan')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN biaya_es_jalan REAL DEFAULT 0").run();
    console.log('[DB Migration] Added biaya_es_jalan column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'status_bayar')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN status_bayar TEXT DEFAULT 'belum_bayar'").run();
    console.log('[DB Migration] Added status_bayar column to pengiriman_pabrik table');
  }
  // Penanda potongan pinjaman supir yang sudah disinkronkan ke Manajemen Hutang
  if (!tableInfo.some(col => col.name === 'pinjaman_diproses')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN pinjaman_diproses INTEGER DEFAULT 0").run();
    console.log('[DB Migration] Added pinjaman_diproses column to pengiriman_pabrik table');
  }
  if (!tableInfo.some(col => col.name === 'id_potong_ledger')) {
    db.prepare("ALTER TABLE pengiriman_pabrik ADD COLUMN id_potong_ledger INTEGER").run();
    console.log('[DB Migration] Added id_potong_ledger column to pengiriman_pabrik table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating pengiriman_pabrik table:', e);
}

// Migration: Check if potongan_muat_rp exists in transaksi_timbangan
try {
  const tableInfo = db.prepare("PRAGMA table_info(transaksi_timbangan)").all();
  const hasPotonganMuat = tableInfo.some(col => col.name === 'potongan_muat_rp');
  if (!hasPotonganMuat) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN potongan_muat_rp REAL DEFAULT 0").run();
    console.log('[DB Migration] Added potongan_muat_rp column to transaksi_timbangan table');
  }
  
  // Migration for Langsir feature
  const hasIsLangsir = tableInfo.some(col => col.name === 'is_langsir');
  if (!hasIsLangsir) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN is_langsir INTEGER DEFAULT 0").run();
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN jumlah_trip_langsir INTEGER DEFAULT 1").run();
    console.log('[DB Migration] Added langsir columns to transaksi_timbangan table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating transaksi_timbangan table:', e);
}

// Migration: Check and create hutang_supplier_history table
try {
  db.prepare(`
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
    )
  `).run();
  console.log('[DB Migration] Checked/Created hutang_supplier_history table');
} catch (e) {
  console.error('[DB Migration] Error migrating hutang_supplier_history table:', e);
}

// Migration: Check and create pabrik table
try {
  db.prepare(`
    CREATE TABLE IF NOT EXISTS pabrik (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nama_pabrik TEXT NOT NULL UNIQUE,
      tarif_angkut REAL DEFAULT 0,
      status TEXT CHECK(status IN ('active','inactive')) DEFAULT 'active',
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT
    )
  `).run();

  // TIDAK ADA SEED DEFAULT DI SINI.
  // Dulu tabel ini diisi otomatis (RSM/RSI/INTAN/SAI) setiap kali barisnya kosong,
  // sehingga nama pabrik "bawaan" muncul lagi tiap aplikasi dinyalakan setelah
  // user menghapusnya, dan ikut terbawa ke instalasi di tempat klien lain.
  // Daftar pabrik/PKS sepenuhnya diisi user lewat Master Data → Pabrik (PKS) & Tarif.
  console.log('[DB Migration] Checked/Created pabrik table');
} catch (e) {
  console.error('[DB Migration] Error migrating pabrik table:', e);
}

// Migration: Check new columns in transaksi_timbangan
try {
  const tableInfo = db.prepare("PRAGMA table_info(transaksi_timbangan)").all();
  
  const hasIdSupir = tableInfo.some(col => col.name === 'id_supir');
  if (!hasIdSupir) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN id_supir INTEGER").run();
    console.log('[DB Migration] Added id_supir column to transaksi_timbangan');
  }

  const hasMode = tableInfo.some(col => col.name === 'mode_timbangan');
  if (!hasMode) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN mode_timbangan TEXT DEFAULT 'beli'").run();
    console.log('[DB Migration] Added mode_timbangan column to transaksi_timbangan');
  }

  const hasPotHutangSupplier = tableInfo.some(col => col.name === 'potongan_hutang_supplier_rp');
  if (!hasPotHutangSupplier) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN potongan_hutang_supplier_rp REAL DEFAULT 0").run();
    console.log('[DB Migration] Added potongan_hutang_supplier_rp column to transaksi_timbangan');
  }

  const hasSisaHutangSupplierSnapshot = tableInfo.some(col => col.name === 'sisa_hutang_supplier_snapshot');
  if (!hasSisaHutangSupplierSnapshot) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN sisa_hutang_supplier_snapshot REAL").run();
    console.log('[DB Migration] Added sisa_hutang_supplier_snapshot column to transaksi_timbangan');
  }

  const hasIdGaji = tableInfo.some(col => col.name === 'id_gaji');
  if (!hasIdGaji) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN id_gaji INTEGER").run();
    console.log('[DB Migration] Added id_gaji column to transaksi_timbangan');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating transaksi_timbangan columns:', e);
}

// Migration: Status pembayaran supplier.
// Sebelumnya "timbang keluar" langsung memotong kas seolah uang sudah keluar.
// Sekarang tiket dicatat sebagai kewajiban dulu (belum_bayar); kas baru bergerak
// ketika pembayaran benar-benar dilakukan — lihat server/routes/pembayaran.js.
const EXPR_TOTAL_AKHIR = `MAX(0, COALESCE(total_harga,0) - (
  COALESCE(potongan_jalan,0) + COALESCE(potongan_pupuk_rp,0) + COALESCE(potongan_hutang_rp,0)
  + COALESCE(potongan_hutang_supplier_rp,0) + COALESCE(potongan_muat_rp,0)))`;
try {
  const cols = db.prepare("PRAGMA table_info(transaksi_timbangan)").all().map(c => c.name);
  if (!cols.includes('status_bayar')) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN status_bayar TEXT DEFAULT 'belum_bayar'").run();
    console.log('[DB Migration] Added status_bayar column to transaksi_timbangan');
  }
  if (!cols.includes('metode_bayar')) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN metode_bayar TEXT").run();
    console.log('[DB Migration] Added metode_bayar column to transaksi_timbangan');
  }
  if (!cols.includes('tanggal_bayar')) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN tanggal_bayar TEXT").run();
    console.log('[DB Migration] Added tanggal_bayar column to transaksi_timbangan');
  }
  if (!cols.includes('id_pembayaran')) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN id_pembayaran INTEGER").run();
    console.log('[DB Migration] Added id_pembayaran column to transaksi_timbangan');
  }
  if (!cols.includes('total_akhir')) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN total_akhir REAL DEFAULT 0").run();
    console.log('[DB Migration] Added total_akhir column to transaksi_timbangan');
  }

  // Isi total_akhir untuk tiket yang belum punya nilainya (nilai yang harus dibayar
  // ke supplier setelah semua potongan). Idempoten: hanya menyentuh baris bernilai 0.
  const filled = db.prepare(
    `UPDATE transaksi_timbangan SET total_akhir = ${EXPR_TOTAL_AKHIR}
     WHERE COALESCE(total_akhir,0) = 0 AND status = 'selesai'`
  ).run();
  if (filled.changes > 0) console.log(`[DB Migration] Backfilled total_akhir for ${filled.changes} tiket`);

  db.prepare(
    `CREATE INDEX IF NOT EXISTS idx_transaksi_status_bayar
     ON transaksi_timbangan(status_bayar, id_supplier)`
  ).run();

  // Sekali saja: tiket yang sudah selesai SEBELUM fitur ini ada sudah terlanjur
  // memotong kas, jadi ditandai lunas supaya laporan lama tidak berubah dan tiket
  // tersebut tidak muncul lagi di antrian pembayaran.
  const flag = db.prepare("SELECT setting_value FROM settings WHERE setting_key = 'migrasi_status_bayar_v1'").get();
  if (!flag) {
    const legacy = db.prepare(
      `UPDATE transaksi_timbangan SET status_bayar = 'lunas', tanggal_bayar = COALESCE(tanggal_bayar, tanggal)
       WHERE status = 'selesai' AND COALESCE(status_bayar,'belum_bayar') = 'belum_bayar'`
    ).run();
    db.prepare(
      `INSERT INTO settings (setting_key, setting_value, created_at)
       VALUES ('migrasi_status_bayar_v1', ?, datetime('now','localtime'))`
    ).run(String(legacy.changes));
    console.log(`[DB Migration] Tiket lama ditandai lunas: ${legacy.changes}`);
  }
} catch (e) {
  console.error('[DB Migration] Error migrating status pembayaran:', e.message);
}

// Migration: tautan baris kas → pembayaran supplier.
// Tanpa kolom ini, baris kas hasil pembayaran terbaca sebagai "entri manual"
// di Buku Kas sehingga bisa dihapus admin — saldo kas jadi melenceng sementara
// tiketnya tetap berstatus lunas. Kolom ini yang mengunci baris tersebut.
try {
  const cols = db.prepare("PRAGMA table_info(kas)").all().map(c => c.name);
  if (!cols.includes('id_pembayaran')) {
    db.prepare("ALTER TABLE kas ADD COLUMN id_pembayaran INTEGER").run();
    console.log('[DB Migration] Added id_pembayaran column to kas');
  }
  db.prepare("CREATE INDEX IF NOT EXISTS idx_kas_pembayaran ON kas(id_pembayaran)").run();

  // Data lama: pembayaran menyimpan id_kas, jadi tautannya bisa dipulihkan.
  // Sekalian bersihkan no_tiket yang dulu diisi nomor pembayaran (BYR-...),
  // karena kolom itu semestinya hanya untuk nomor tiket timbangan.
  const linked = db.prepare(
    `UPDATE kas SET id_pembayaran = (SELECT p.id FROM pembayaran p WHERE p.id_kas = kas.id)
     WHERE id_pembayaran IS NULL AND EXISTS (SELECT 1 FROM pembayaran p WHERE p.id_kas = kas.id)`
  ).run();
  db.prepare("UPDATE kas SET no_tiket = NULL WHERE id_pembayaran IS NOT NULL").run();
  if (linked.changes > 0) console.log(`[DB Migration] Tautan kas→pembayaran dipulihkan: ${linked.changes}`);
} catch (e) {
  console.error('[DB Migration] Error migrating kas.id_pembayaran:', e.message);
}

// Migration: tautan mutasi hutang tunai ↔ baris kas.
// Kasbon yang diserahkan tunai berarti uang keluar dari kas, dan setoran
// pelunasan berarti uang masuk. Sebelumnya kedua hal itu hanya tercatat di buku
// besar hutang sehingga saldo kas tidak mencerminkan uang fisik. Tautan dua arah
// ini yang memastikan keduanya tidak bisa lepas satu dari yang lain.
// Mutasi non-tunai (hutang pupuk/barang, saldo awal pendaftaran) tetap tanpa kas.
try {
  const ledgerCols = db.prepare("PRAGMA table_info(hutang_ledger)").all().map(c => c.name);
  if (!ledgerCols.includes('id_kas')) {
    db.prepare("ALTER TABLE hutang_ledger ADD COLUMN id_kas INTEGER").run();
    console.log('[DB Migration] Added id_kas column to hutang_ledger');
  }
  const kasCols = db.prepare("PRAGMA table_info(kas)").all().map(c => c.name);
  if (!kasCols.includes('id_hutang_ledger')) {
    db.prepare("ALTER TABLE kas ADD COLUMN id_hutang_ledger INTEGER").run();
    console.log('[DB Migration] Added id_hutang_ledger column to kas');
  }
  db.prepare("CREATE INDEX IF NOT EXISTS idx_kas_hutang_ledger ON kas(id_hutang_ledger)").run();
} catch (e) {
  console.error('[DB Migration] Error migrating tautan hutang-kas:', e.message);
}

// Migration: tautan baris kas → pembayaran upah (gaji supir / gaji TKBM).
// Tanpa kolom ini, baris kas pembayaran upah terlihat seperti entri manual di
// Buku Kas sehingga admin bisa menghapusnya sementara slip gajinya tetap ada —
// uang "kembali" ke saldo padahal gajinya tercatat sudah dibayar.
try {
  const kasCols = db.prepare("PRAGMA table_info(kas)").all().map(c => c.name);
  if (!kasCols.includes('id_gaji_supir')) {
    db.prepare("ALTER TABLE kas ADD COLUMN id_gaji_supir INTEGER").run();
    console.log('[DB Migration] Added id_gaji_supir column to kas');
  }
  if (!kasCols.includes('id_gaji_tkbm')) {
    db.prepare("ALTER TABLE kas ADD COLUMN id_gaji_tkbm INTEGER").run();
    console.log('[DB Migration] Added id_gaji_tkbm column to kas');
  }
  db.prepare("CREATE INDEX IF NOT EXISTS idx_kas_gaji_supir ON kas(id_gaji_supir)").run();
  db.prepare("CREATE INDEX IF NOT EXISTS idx_kas_gaji_tkbm ON kas(id_gaji_tkbm)").run();
} catch (e) {
  console.error('[DB Migration] Error migrating tautan gaji-kas:', e.message);
}


// Migration: tara per trip langsir.
// Sebelumnya tabel langsir hanya menyimpan bruto tiap trip, lalu netto dihitung
// dari (bruto akumulasi - SATU tara). Akibatnya tara mobil pada trip ke-2 dan
// seterusnya ikut terhitung sebagai buah — netto membengkak sebesar
// (jumlah_trip - 1) x tara. Sekarang tiap trip menyimpan tara & netto sendiri.
try {
  const cols = db.prepare("PRAGMA table_info(transaksi_timbangan_langsir)").all().map(c => c.name);
  const tambah = (nama, ddl) => {
    if (!cols.includes(nama)) {
      db.prepare(`ALTER TABLE transaksi_timbangan_langsir ADD COLUMN ${nama} ${ddl}`).run();
      console.log(`[DB Migration] Added ${nama} column to transaksi_timbangan_langsir`);
    }
  };
  tambah('urutan', 'INTEGER DEFAULT 1');
  tambah('id_kendaraan', 'INTEGER');
  tambah('no_polisi', 'TEXT');
  tambah('nama_supir', 'TEXT');
  tambah('berat_tara', 'REAL DEFAULT 0');
  tambah('berat_netto', 'REAL DEFAULT 0');
  tambah('waktu_tara', 'TEXT');
  tambah('status', "TEXT DEFAULT 'bruto'");
  tambah('operator_id', 'INTEGER');
  db.prepare("CREATE INDEX IF NOT EXISTS idx_langsir_transaksi ON transaksi_timbangan_langsir(id_transaksi, urutan)").run();

  // Nomori ulang trip lama berdasarkan urutan id. Tidak bisa memakai syarat
  // "urutan IS NULL atau 0" karena kolomnya ber-DEFAULT 1 — seluruh baris lama
  // akan bernomor 1 dan daftar trip jadi tidak terurut. Dijalankan sekali saja,
  // ditandai lewat tabel settings. Hasilnya idempoten (murni turunan urutan id).
  const flagUrutan = db.prepare("SELECT setting_value FROM settings WHERE setting_key = 'migrasi_urutan_langsir_v1'").get();
  if (!flagUrutan) {
    const r = db.prepare(`
      UPDATE transaksi_timbangan_langsir SET urutan = (
        SELECT COUNT(*) FROM transaksi_timbangan_langsir b
        WHERE b.id_transaksi = transaksi_timbangan_langsir.id_transaksi AND b.id <= transaksi_timbangan_langsir.id
      )
    `).run();
    db.prepare(
      `INSERT INTO settings (setting_key, setting_value, created_at)
       VALUES ('migrasi_urutan_langsir_v1', ?, datetime('now','localtime'))`
    ).run(String(r.changes));
    if (r.changes > 0) console.log(`[DB Migration] Nomor urut trip langsir diperbaiki: ${r.changes} baris`);
  }

  // Plat nomor trip lama diambil dari tiket induknya (dulu hanya ada satu plat).
  db.prepare(`
    UPDATE transaksi_timbangan_langsir SET
      no_polisi = COALESCE(no_polisi, (SELECT tt.no_polisi FROM transaksi_timbangan tt WHERE tt.id = id_transaksi)),
      nama_supir = COALESCE(nama_supir, (SELECT tt.nama_supir FROM transaksi_timbangan tt WHERE tt.id = id_transaksi))
    WHERE no_polisi IS NULL OR nama_supir IS NULL
  `).run();

  // Trip milik tiket yang sudah selesai ditandai 'selesai' agar tidak dianggap
  // menggantung. Taranya tetap 0 — data itu memang tidak pernah direkam.
  db.prepare(`
    UPDATE transaksi_timbangan_langsir SET status = 'selesai'
    WHERE COALESCE(status,'bruto') <> 'selesai'
      AND id_transaksi IN (SELECT id FROM transaksi_timbangan WHERE status IN ('selesai','dibatalkan'))
  `).run();
} catch (e) {
  console.error('[DB Migration] Error migrating tara per trip langsir:', e.message);
}

// Migration: Backfill buku besar hutang (hutang_ledger) untuk saldo lama.
// Sebagian data punya total_hutang di master TAPI belum punya baris riwayat di
// hutang_ledger (mis. diinput sebelum buku besar ada). Tanpa ini, saldo tampil
// di tabel/daftar tetapi tidak terbaca di riwayat/laporan. Idempoten: hanya
// menambah entri "saldo awal" bila pihak tsb belum punya riwayat sama sekali.
try {
  const today = new Date().toISOString().split('T')[0];
  const hasLedger = db.prepare("SELECT 1 FROM hutang_ledger WHERE party_type = ? AND party_id = ? LIMIT 1");
  const insLedger = db.prepare(
    `INSERT INTO hutang_ledger (party_type, party_id, tanggal, jenis, jumlah, keterangan, sumber, saldo_setelah, created_at)
     VALUES (?, ?, ?, 'tambah', ?, 'Saldo awal (data sebelum riwayat dicatat)', 'manual', ?, datetime('now','localtime'))`
  );
  const backfill = (partyType, rows) => {
    for (const r of rows) {
      if (!hasLedger.get(partyType, r.id)) {
        insLedger.run(partyType, r.id, today, r.total_hutang, r.total_hutang);
      }
    }
  };
  backfill('supir',    db.prepare("SELECT id, total_hutang FROM supir WHERE total_hutang > 0").all());
  backfill('supplier', db.prepare("SELECT id, total_hutang FROM supplier WHERE total_hutang > 0 AND is_temporary = 0").all());
  backfill('tkbm',     db.prepare("SELECT id, total_hutang FROM karyawan_tkbm WHERE total_hutang > 0").all());
  for (const r of db.prepare("SELECT id, total_hutang, tipe FROM kontak WHERE total_hutang > 0").all()) {
    if (!hasLedger.get(r.tipe, r.id)) insLedger.run(r.tipe, r.id, today, r.total_hutang, r.total_hutang);
  }
} catch (e) {
  console.error('[DB Migration] Backfill hutang_ledger error:', e.message);
}

// Migration: kolom tarif_pemuat & potongan_lainnya di gaji_tkbm (dipakai saat payout TKBM).
try {
  const cols = db.prepare("PRAGMA table_info(gaji_tkbm)").all().map(c => c.name);
  if (!cols.includes('tarif_pemuat'))    db.prepare("ALTER TABLE gaji_tkbm ADD COLUMN tarif_pemuat REAL DEFAULT 0").run();
  if (!cols.includes('potongan_lainnya')) db.prepare("ALTER TABLE gaji_tkbm ADD COLUMN potongan_lainnya REAL DEFAULT 0").run();
} catch (e) {
  console.error('[DB Migration] gaji_tkbm migration error:', e.message);
}

// Migration: kolom id_gaji_tkbm di pengiriman_tkbm (untuk menandai trip yang sudah dibayar).
try {
  const cols = db.prepare("PRAGMA table_info(pengiriman_tkbm)").all().map(c => c.name);
  if (!cols.includes('id_gaji_tkbm')) {
    db.prepare("ALTER TABLE pengiriman_tkbm ADD COLUMN id_gaji_tkbm INTEGER").run();
    console.log('[DB Migration] Added id_gaji_tkbm column to pengiriman_tkbm');
  }
} catch (e) {
  console.error('[DB Migration] pengiriman_tkbm migration error:', e.message);
}

// Migration: Pastikan pengaturan_gaji punya kolom tarif & satu baris penampung.
// Upah TKBM = netto (kg) × tarif_pemuat ÷ jumlah pekerja.
// Semua tarif dimulai dari 0 — diisi user lewat Rekap Gaji TKBM / tab Pengaturan.
// Tidak ada nilai bawaan di kode supaya tarif tidak ikut terbawa antar instalasi.
try {
  const cols = db.prepare("PRAGMA table_info(pengaturan_gaji)").all().map(c => c.name);
  if (!cols.includes('tarif_supir'))  db.prepare("ALTER TABLE pengaturan_gaji ADD COLUMN tarif_supir REAL DEFAULT 0").run();
  if (!cols.includes('tarif_pemuat')) db.prepare("ALTER TABLE pengaturan_gaji ADD COLUMN tarif_pemuat REAL DEFAULT 0").run();
  const cnt = db.prepare("SELECT COUNT(*) as c FROM pengaturan_gaji").get().c;
  if (cnt === 0) {
    db.prepare("INSERT INTO pengaturan_gaji (tarif_per_kg, tarif_supir, tarif_pemuat) VALUES (0, 0, 0)").run();
    console.log('[DB Migration] Seed baris pengaturan_gaji (semua tarif 0, diisi user)');
  }
} catch (e) {
  console.error('[DB Migration] pengaturan_gaji default error:', e.message);
}

// Seed default settings dan user admin.
//
// Dulu blok ini hanya jalan bila tabel `settings` benar-benar kosong. Padahal
// migrasi di atas sudah menitipkan penanda ('migrasi_status_bayar_v1',
// 'migrasi_urutan_langsir_v1', dst) ke tabel yang sama, jadi pada instalasi baru
// jumlah barisnya tidak pernah 0 dan user admin TIDAK PERNAH dibuat.
// Sekarang setiap bagian diperiksa sendiri-sendiri: setting disisipkan dengan
// INSERT OR IGNORE (setting_key sudah UNIQUE), dan admin dibuat hanya bila
// tabel users memang masih kosong — sehingga aman dijalankan berulang kali.
try {
  const seedSetting = db.prepare(
    "INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)"
  );
  const defaults = [
    ['ticket_prefix', 'TKT'],
    ['company_name', 'PERUSAHAAN JAYA'],
    ['company_address', 'ALAMAT PERUSAHAAN'],
    ['company_phone', '08123456789'],
    ['material_list', '["tbs","brondolan"]']
  ];
  let dibuat = 0;
  for (const [k, v] of defaults) dibuat += seedSetting.run(k, v).changes;
  if (dibuat > 0) console.log(`[DB] Seeded default settings: ${dibuat}`);

  const jumlahUser = db.prepare("SELECT count(*) as count FROM users").get().count;
  if (jumlahUser === 0) {
    db.prepare(
      `INSERT INTO users (username, nama_lengkap, password, role, status)
       VALUES ('admin', 'Administrator', ?, 'admin', 'active')`
    ).run('$2a$10$nK1PO9wBOnUiAgcIHP9ntOU5E6FqrHA8TT5dczk3MVNw2HgEI2tLK');
    console.log('[DB] Seeded default admin user');
  }
} catch (e) {
  console.error('[DB] Seed default settings/admin error:', e.message);
}

// Migration (sekali jalan): rapikan keterangan kas lama ke SOP baru
// "KATEGORI - NAMA - REFERENSI" (lihat helpers/kasHelper.js). Hanya teks yang
// diubah — nominal, tanggal, dan saldo tidak disentuh sama sekali.
try {
  const sudah = db.prepare(
    "SELECT setting_value FROM settings WHERE setting_key = 'kas_keterangan_sop_v1'"
  ).get();

  if (!sudah) {
    const rapikan = (s) => String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();
    const gabung = (...bagian) => bagian.map(rapikan).filter(Boolean).join(' - ');
    const tglRingkas = (t) => {
      const p = String(t || '').trim().split('-');
      return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : rapikan(t);
    };
    const periode = (a, b) => {
      const x = tglRingkas(a), y = tglRingkas(b);
      return !x && !y ? '' : (x === y ? x : `${x} S/D ${y}`);
    };

    const baris = db.prepare(
      `SELECT k.id, k.keterangan, k.jenis, k.id_transaksi, k.no_tiket, k.id_pembayaran,
              tt.jenis_material, tt.nama_supir, tt.mode_timbangan, tt.no_tiket AS tiket_tt,
              p.no_pembayaran, p.nama_supplier AS bayar_supplier, p.metode AS bayar_metode
       FROM kas k
       LEFT JOIN transaksi_timbangan tt ON k.id_transaksi = tt.id
       LEFT JOIN pembayaran p ON k.id_pembayaran = p.id`
    ).all();

    const upd = db.prepare("UPDATE kas SET keterangan = ? WHERE id = ?");
    let diubah = 0;

    const konversi = (r) => {
      const lama = rapikan(r.keterangan);

      // 1. Baris dari pembayaran supplier
      if (r.id_pembayaran) {
        const metode = rapikan(r.bayar_metode);
        const inti = gabung('PEMBAYARAN SUPPLIER', r.bayar_supplier, r.no_pembayaran);
        return metode ? `${inti} (${metode})` : inti;
      }

      // 2. Baris dari tiket timbangan
      if (r.id_transaksi) {
        const tiket = r.no_tiket || r.tiket_tt;
        // Baris pembalik (uang kembali) vs baris transaksi aslinya
        const isBalik = /BATAL|PEMBATALAN/.test(lama);
        if (isBalik) return gabung('BATAL TRANSAKSI', r.nama_supir, tiket);
        const jual = (r.mode_timbangan || 'beli') === 'jual';
        const metode = (lama.match(/\((TUNAI|TRANSFER|BON|HUTANG)\)/) || [])[1];
        const inti = gabung(`${jual ? 'PENJUALAN' : 'PEMBELIAN'} ${r.jenis_material || 'BUAH'}`,
          r.nama_supir, tiket);
        return metode && !jual ? `${inti} (${metode})` : inti;
      }

      // 3. Mutasi hutang tunai
      let m = lama.match(/^PELUNASAN HUTANG (.+?) - (.+)$/);
      if (m) return gabung(`BAYAR HUTANG ${m[1]}`, m[2]);

      // 4. Upah / gaji
      m = lama.match(/^PEMBAYARAN UPAH (SUPIR|TKBM) - (.+?) \((\S+) - (\S+)\)$/);
      if (m) return gabung(`UPAH ${m[1]}`, m[2], periode(m[3], m[4]));

      m = lama.match(/^PEMBATALAN PEMBAYARAN UPAH SUPIR #(\d+) - (.+)$/);
      if (m) return gabung('BATAL UPAH SUPIR', m[2], `GJS#${m[1]}`);

      m = lama.match(/^PEMBATALAN PEMBAYARAN UPAH TKBM ID #(\d+)$/);
      if (m) return gabung('BATAL UPAH TKBM', '', `GJT#${m[1]}`);

      // 5. Potong pinjaman dari gaji
      m = lama.match(/^POTONG PINJAMAN DARI GAJI SUPIR - (.+?) PERIODE (\S+) S\/D (\S+)$/);
      if (m) return gabung('POTONG PINJAMAN SUPIR', m[1], periode(m[2], m[3]));

      m = lama.match(/^PEMBATALAN POTONG PINJAMAN GAJI (.+?) - (.+)$/);
      if (m) return gabung(`BATAL POTONG PINJAMAN ${m[1]}`, m[2]);

      // 6. Pembayaran supplier lama tanpa tautan id_pembayaran
      m = lama.match(/^PEMBAYARAN (.+?) - \d+ TIKET(?: \((\w+)\))?$/);
      if (m) {
        const inti = gabung('PEMBAYARAN SUPPLIER', m[1]);
        return m[2] ? `${inti} (${m[2]})` : inti;
      }

      // 7. Sisanya (entri manual) cukup dirapikan hurufnya
      return lama;
    };

    const jalankan = db.transaction(() => {
      for (const r of baris) {
        const baru = konversi(r);
        if (baru && baru !== (r.keterangan || '')) { upd.run(baru, r.id); diubah++; }
      }
      db.prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('kas_keterangan_sop_v1', ?)"
      ).run(new Date().toISOString());
    });
    jalankan();
    console.log(`[DB Migration] Keterangan kas dirapikan ke SOP: ${diubah} baris`);
  }
} catch (e) {
  console.error('[DB Migration] Error merapikan keterangan kas:', e.message);
}

// Enable WAL mode for better concurrency
db.pragma('journal_mode = WAL');

console.log('[DB] Connected to SQLite: ' + dbPath);

/**
 * Pre-process SQL to convert MySQL functions to SQLite
 */
function processSql(sql) {
  return sql
    .replace(/\bNOW\(\)/gi, "datetime('now', 'localtime')")
    .replace(/\bCURDATE\(\)/gi, "date('now', 'localtime')")
    .replace(/DATE_SUB\(CURDATE\(\), INTERVAL (\d+) DAY\)/gi, "date('now', 'localtime', '-$1 days')")
    .replace(/DATE_SUB\(CURDATE\(\), INTERVAL (\d+) MONTH\)/gi, "date('now', 'localtime', '-$1 months')");
}

/**
 * Execute a query with prepared statement
 * For SELECT, returns array of rows. For INSERT/UPDATE/DELETE returns { insertId, affectedRows }
 */
function query(sql, params = []) {
  sql = processSql(sql);
  const stmt = db.prepare(sql);
  
  if (stmt.reader) {
    return stmt.all(...params);
  } else {
    const info = stmt.run(...params);
    return { insertId: info.lastInsertRowid, affectedRows: info.changes };
  }
}

/**
 * Execute query and return first row only
 */
function queryOne(sql, params = []) {
  sql = processSql(sql);
  const stmt = db.prepare(sql);
  if (stmt.reader) {
    return stmt.get(...params) || null;
  }
  return null;
}

/**
 * Transaction wrapper
 */
function beginTransaction() {
  db.exec('BEGIN TRANSACTION');
  
  return {
    execute: (sql, params = []) => {
      sql = processSql(sql);
      const stmt = db.prepare(sql);
      if (stmt.reader) {
        return [stmt.all(...params), []]; // return [rows, fields] format
      } else {
        const info = stmt.run(...params);
        return [{ insertId: info.lastInsertRowid, affectedRows: info.changes }, []];
      }
    },
    commit: () => {
      db.exec('COMMIT');
    },
    rollback: () => {
      db.exec('ROLLBACK');
    },
    release: () => {
      // Not needed for SQLite
    }
  };
}

// Pool wrapper for compatibility with mysql2 `pool.execute`
const pool = {
  execute: (sql, params = []) => {
    sql = processSql(sql);
    const stmt = db.prepare(sql);
    if (stmt.reader) {
      return [stmt.all(...params), []];
    } else {
      const info = stmt.run(...params);
      return [{ insertId: info.lastInsertRowid, affectedRows: info.changes }, []];
    }
  },
  getConnection: () => {
    return {
      beginTransaction: () => {
        db.exec('BEGIN TRANSACTION');
      },
      execute: (sql, params = []) => {
        sql = processSql(sql);
        const stmt = db.prepare(sql);
        if (stmt.reader) {
          return [stmt.all(...params), []];
        } else {
          const info = stmt.run(...params);
          return [{ insertId: info.lastInsertRowid, affectedRows: info.changes }, []];
        }
      },
      commit: () => {
        db.exec('COMMIT');
      },
      rollback: () => {
        db.exec('ROLLBACK');
      },
      release: () => {}
    };
  }
};

/**
 * Format Rupiah
 */
function formatRupiah(number) {
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
}

/**
 * Clean/sanitize input
 */
function cleanInput(data) {
  if (data === null || data === undefined) return '';
  return String(data).trim();
}

/**
 * JSON response helper
 */
function jsonResponse(res, success, message, data = null) {
  return res.json({ success, message, data });
}

module.exports = { db, pool, query, queryOne, beginTransaction, formatRupiah, cleanInput, jsonResponse, EXPR_TOTAL_AKHIR };
