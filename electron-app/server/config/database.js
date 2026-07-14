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
  const hasDefaultHarga = tableInfo.some(col => col.name === 'default_harga');
  if (!hasDefaultHarga) {
    db.prepare("ALTER TABLE supplier ADD COLUMN default_harga REAL DEFAULT 0").run();
    console.log('[DB Migration] Added default_harga column to supplier table');
  }
  const hasDefaultPotongan = tableInfo.some(col => col.name === 'default_potongan');
  if (!hasDefaultPotongan) {
    db.prepare("ALTER TABLE supplier ADD COLUMN default_potongan REAL DEFAULT 0").run();
    console.log('[DB Migration] Added default_potongan column to supplier table');
  }
  const hasIsTemporary = tableInfo.some(col => col.name === 'is_temporary');
  if (!hasIsTemporary) {
    db.prepare("ALTER TABLE supplier ADD COLUMN is_temporary INTEGER DEFAULT 0").run();
    console.log('[DB Migration] Added is_temporary column to supplier table');
  }
} catch (e) {
  console.error('[DB Migration] Error migrating supplier table:', e);
}

// Migration: Check if potongan_muat_rp exists in transaksi_timbangan
try {
  const tableInfo = db.prepare("PRAGMA table_info(transaksi_timbangan)").all();
  const hasPotonganMuat = tableInfo.some(col => col.name === 'potongan_muat_rp');
  if (!hasPotonganMuat) {
    db.prepare("ALTER TABLE transaksi_timbangan ADD COLUMN potongan_muat_rp REAL DEFAULT 0").run();
    console.log('[DB Migration] Added potongan_muat_rp column to transaksi_timbangan table');
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
