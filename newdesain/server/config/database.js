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

// Migration: Check kepemilikan in kendaraan (fitur newdesain)
try {
  const tableInfoKendaraan = db.prepare("PRAGMA table_info(kendaraan)").all();
  if (!tableInfoKendaraan.some(col => col.name === 'kepemilikan')) {
    db.prepare("ALTER TABLE kendaraan ADD COLUMN kepemilikan TEXT DEFAULT 'Perusahaan'").run();
    console.log('[DB Migration] Added kepemilikan column to kendaraan table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating kendaraan table:', e);
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
  
  const countRow = db.prepare("SELECT COUNT(*) as cnt FROM pabrik").get();
  if (countRow && countRow.cnt === 0) {
    const insertStmt = db.prepare("INSERT INTO pabrik (nama_pabrik, tarif_angkut, status) VALUES (?, ?, 'active')");
    insertStmt.run('RSM', 110);
    insertStmt.run('RSI', 95);
    insertStmt.run('INTAN', 80);
    insertStmt.run('SAI', 95);
    console.log('[DB Migration] Seeded default pabrik data (RSM, RSI, INTAN, SAI)');
  }
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

// Migration: Pastikan pengaturan_gaji punya kolom tarif & baris default (tarif_pemuat = 30).
// Upah TKBM = netto (kg) × tarif_pemuat ÷ jumlah pekerja. Default pemuat Rp 30/kg, tetap bisa diubah.
try {
  const cols = db.prepare("PRAGMA table_info(pengaturan_gaji)").all().map(c => c.name);
  if (!cols.includes('tarif_supir'))  db.prepare("ALTER TABLE pengaturan_gaji ADD COLUMN tarif_supir REAL DEFAULT 0").run();
  if (!cols.includes('tarif_pemuat')) db.prepare("ALTER TABLE pengaturan_gaji ADD COLUMN tarif_pemuat REAL DEFAULT 0").run();
  const cnt = db.prepare("SELECT COUNT(*) as c FROM pengaturan_gaji").get().c;
  if (cnt === 0) {
    db.prepare("INSERT INTO pengaturan_gaji (tarif_per_kg, tarif_supir, tarif_pemuat) VALUES (0, 0, 30)").run();
    console.log('[DB Migration] Seed pengaturan_gaji default (tarif_pemuat = 30)');
  }
} catch (e) {
  console.error('[DB Migration] pengaturan_gaji default error:', e.message);
}

// Seed default settings and admin user if empty
const hasSettings = db.prepare("SELECT count(*) as count FROM settings").get().count;
if (hasSettings === 0) {
  const seedSql = `
    INSERT INTO settings (setting_key, setting_value) VALUES 
    ('ticket_prefix', 'TKT'),
    ('company_name', 'PERUSAHAAN JAYA'),
    ('company_address', 'ALAMAT PERUSAHAAN'),
    ('company_phone', '08123456789'),
    ('material_list', '["tbs","brondolan"]');

    INSERT INTO users (username, nama_lengkap, password, role, status) VALUES 
    ('admin', 'Administrator', '$2a$10$nK1PO9wBOnUiAgcIHP9ntOU5E6FqrHA8TT5dczk3MVNw2HgEI2tLK', 'admin', 'active');
  `;
  db.exec(seedSql);
  console.log('[DB] Seeded default settings and admin user');
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

module.exports = { db, pool, query, queryOne, beginTransaction, formatRupiah, cleanInput, jsonResponse };
