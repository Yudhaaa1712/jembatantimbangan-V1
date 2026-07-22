/**
 * Masterdata Routes
 * Replaces: modules/masterdata/ (supplier, kendaraan, customer, activity_logs)
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, pool, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { cacheDelete } = require('../helpers/cache');
const { isLoggedIn, requireRole, getCurrentUser } = require('../middleware/auth');

router.use(isLoggedIn);

// ─── SUPPLIER ─────────────────────────────────────────────────────────────────

router.get('/supplier', async (req, res) => {
  try {
    const search = req.query.search || '';
    let sql = `SELECT s.*, 
                      COALESCE((SELECT COUNT(*) FROM transaksi_timbangan WHERE id_supplier = s.id AND status='selesai'), 0) as total_transaksi
               FROM supplier s WHERE s.is_temporary = 0`;
    const params = [];
    if (search) { 
      sql += ` AND (s.nama_supplier LIKE ? OR s.kode_supplier LIKE ?)`; 
      params.push(`%${search}%`, `%${search}%`); 
    }
    sql += ` ORDER BY s.nama_supplier`;
    const data = await query(sql, params);
    return jsonResponse(res, true, 'Supplier list', data);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.post('/supplier', requireRole('admin'), async (req, res) => {
  try {
    const nama = cleanInput(req.body.nama_supplier).toUpperCase();
    const defaultHarga = parseFloat(req.body.default_harga) || 0;
    const defaultPotongan = parseFloat(req.body.default_potongan) || 0;
    const totalHutang = parseFloat(req.body.total_hutang) || 0;
    const existing = await queryOne(`SELECT id FROM supplier WHERE nama_supplier = ?`, [nama]);
    if (existing) return jsonResponse(res, false, 'Supplier sudah ada');
    const kode = 'SUP-' + new Date().toISOString().slice(2,8).replace(/-/g,'') + '-' + String(Math.floor(Math.random()*999)+1).padStart(3,'0');
    
    const tx = beginTransaction();
    try {
      const result = tx.execute(`INSERT INTO supplier (kode_supplier, nama_supplier, status, created_at, default_harga, default_potongan, total_hutang, hutang_terakhir_update) VALUES (?,?,'active',CURRENT_TIMESTAMP,?,?,?,datetime('now', 'localtime'))`, [kode, nama, defaultHarga, defaultPotongan, totalHutang]);
      const supplierId = result[0].insertId;
      
      if (totalHutang > 0) {
        tx.execute(
          `INSERT INTO hutang_supplier_history (id_supplier, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'tambah', ?, 'Hutang awal saat pendaftaran', ?, ?)`,
          [supplierId, totalHutang, totalHutang, req.session.user_id]
        );
      }
      tx.commit();
      cacheDelete('active_suppliers_list');
      return jsonResponse(res, true, 'Supplier berhasil ditambahkan', { id: supplierId });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.put('/supplier/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama_supplier).toUpperCase();
    const status = cleanInput(req.body.status) || 'active';
    const defaultHarga = parseFloat(req.body.default_harga) || 0;
    const defaultPotongan = parseFloat(req.body.default_potongan) || 0;
    const totalHutang = parseFloat(req.body.total_hutang) || 0;
    
    const oldSup = await queryOne(`SELECT total_hutang FROM supplier WHERE id = ?`, [id]);
    if (!oldSup) return jsonResponse(res, false, 'Supplier tidak ditemukan');
    const oldHutang = oldSup.total_hutang || 0;
    
    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE supplier SET nama_supplier=?, status=?, default_harga=?, default_potongan=?, total_hutang=?, hutang_terakhir_update=datetime('now', 'localtime'), updated_at=CURRENT_TIMESTAMP WHERE id=?`, [nama, status, defaultHarga, defaultPotongan, totalHutang, id]);
      
      if (totalHutang !== oldHutang) {
        const diff = totalHutang - oldHutang;
        const jenis = diff > 0 ? 'tambah' : 'bayar';
        const jumlah = Math.abs(diff);
        tx.execute(
          `INSERT INTO hutang_supplier_history (id_supplier, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), ?, ?, 'Penyesuaian nominal hutang (edit supplier)', ?, ?)`,
          [id, jenis, jumlah, totalHutang, req.session.user_id]
        );
      }
      tx.commit();
      cacheDelete('active_suppliers_list');
      return jsonResponse(res, true, 'Supplier berhasil diupdate');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.delete('/supplier/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await query(`UPDATE supplier SET status='inactive', updated_at=CURRENT_TIMESTAMP WHERE id=?`, [id]);
    cacheDelete('active_suppliers_list');
    return jsonResponse(res, true, 'Supplier dinonaktifkan');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── KENDARAAN ────────────────────────────────────────────────────────────────

router.get('/kendaraan', async (req, res) => {
  try {
    const search = req.query.search || '';
    let sql = `SELECT * FROM kendaraan`;
    const params = [];
    if (search) { sql += ` WHERE no_polisi LIKE ? OR nama_supir LIKE ?`; params.push(`%${search}%`, `%${search}%`); }
    sql += ` ORDER BY no_polisi`;
    return jsonResponse(res, true, 'Kendaraan list', await query(sql, params));
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.post('/kendaraan', requireRole('admin', 'operator'), async (req, res) => {
  try {
    const noPolisi = cleanInput(req.body.no_polisi).toUpperCase();
    const namaSopir = cleanInput(req.body.nama_supir);
    const taraAvg   = parseFloat(req.body.tara_avg) || 0;
    const jenis     = cleanInput(req.body.jenis_kendaraan) || 'truk';
    const existing = await queryOne(`SELECT id FROM kendaraan WHERE no_polisi=?`, [noPolisi]);
    if (existing) return jsonResponse(res, false, 'No polisi sudah terdaftar');
    const result = await query(
      `INSERT INTO kendaraan (no_polisi, nama_supir, tara_avg, jenis_kendaraan, status, created_at) VALUES (?,?,?,?,'active',NOW())`,
      [noPolisi, namaSopir, taraAvg, jenis]
    );
    return jsonResponse(res, true, 'Kendaraan berhasil ditambahkan', { id: result.insertId });
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.put('/kendaraan/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const noPolisi  = cleanInput(req.body.no_polisi).toUpperCase();
    const namaSopir = cleanInput(req.body.nama_supir);
    const taraAvg   = parseFloat(req.body.tara_avg) || 0;
    const status    = cleanInput(req.body.status) || 'active';
    await query(`UPDATE kendaraan SET no_polisi=?, nama_supir=?, tara_avg=?, status=?, updated_at=NOW() WHERE id=?`, [noPolisi, namaSopir, taraAvg, status, id]);
    return jsonResponse(res, true, 'Kendaraan berhasil diupdate');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.delete('/kendaraan/:id', requireRole('admin'), async (req, res) => {
  try {
    await query(`UPDATE kendaraan SET status='inactive', updated_at=NOW() WHERE id=?`, [parseInt(req.params.id)]);
    return jsonResponse(res, true, 'Kendaraan dinonaktifkan');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// Helper date filter for tonase
function buildDateCondition(dateFilter, startDate, endDate) {
  switch (dateFilter) {
    case 'today':      return { sql: `DATE(t.tanggal) = DATE('now', 'localtime')`, params: [] };
    case 'yesterday':  return { sql: `DATE(t.tanggal) = DATE('now', 'localtime', '-1 day')`, params: [] };
    case 'week':       return { sql: `DATE(t.tanggal) >= DATE('now', 'localtime', '-7 days')`, params: [] };
    case 'half_month': return { sql: `DATE(t.tanggal) >= DATE('now', 'localtime', '-15 days')`, params: [] };
    case 'month':      return { sql: `strftime('%m', t.tanggal) = strftime('%m', 'now', 'localtime') AND strftime('%Y', t.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'half_year':  return { sql: `DATE(t.tanggal) >= DATE('now', 'localtime', '-6 months')`, params: [] };
    case 'year':       return { sql: `strftime('%Y', t.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'custom_range':
      if (startDate && endDate) {
        return { sql: `DATE(t.tanggal) >= DATE(?) AND DATE(t.tanggal) <= DATE(?)`, params: [startDate, endDate] };
      } else if (startDate) {
        return { sql: `DATE(t.tanggal) >= DATE(?)`, params: [startDate] };
      } else if (endDate) {
        return { sql: `DATE(t.tanggal) <= DATE(?)`, params: [endDate] };
      }
      return { sql: `1=1`, params: [] };
    case 'all': return { sql: `1=1`, params: [] };
    default:
      if (startDate || endDate) {
        let conds = [];
        let p = [];
        if (startDate) { conds.push(`DATE(t.tanggal) >= DATE(?)`); p.push(startDate); }
        if (endDate) { conds.push(`DATE(t.tanggal) <= DATE(?)`); p.push(endDate); }
        return { sql: conds.join(' AND '), params: p };
      }
      return { sql: `1=1`, params: [] };
  }
}

// Rekapitulasi Tonase Kendaraan & Supir (BM & Supir Tonnage)
router.get('/tonase-kendaraan', async (req, res) => {
  try {
    const dateFilter = req.query.date_filter || '';
    const startDate  = req.query.start_date;
    const endDate    = req.query.end_date;
    const search     = req.query.search || '';

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    let whereClause = `WHERE t.status = 'selesai' AND ${dateSql}`;
    const params = [...dateParams];

    if (search) {
      whereClause += ` AND (t.no_polisi LIKE ? OR t.nama_supir LIKE ?)`;
      params.push(`%${search}%`, `%${search}%`);
    }

    let sql = `
      SELECT 
        UPPER(t.no_polisi) as no_polisi,
        COALESCE(UPPER(NULLIF(t.nama_supir,'')), UPPER(NULLIF(k.nama_supir,'')), '-') as nama_supir,
        COUNT(t.id) as total_trip,
        SUM(COALESCE(t.netto_akhir, t.berat_netto, 0)) as total_netto_kg,
        ROUND(SUM(COALESCE(t.netto_akhir, t.berat_netto, 0)) / 1000.0, 2) as total_tonase,
        MAX(t.tanggal) as transaksi_terakhir
      FROM transaksi_timbangan t
      LEFT JOIN kendaraan k ON UPPER(t.no_polisi) = UPPER(k.no_polisi)
      ${whereClause}
      GROUP BY UPPER(t.no_polisi), COALESCE(UPPER(NULLIF(t.nama_supir,'')), UPPER(NULLIF(k.nama_supir,'')), '-')
      ORDER BY total_netto_kg DESC
    `;

    const rawData = await query(sql, params);

    let grandTotalTonase = 0;
    let grandTotalTrip = 0;

    rawData.forEach(item => {
      grandTotalTonase += (item.total_tonase || 0);
      grandTotalTrip += (item.total_trip || 0);
    });

    return jsonResponse(res, true, 'Rekap Tonase Kendaraan & Supir', {
      items: rawData,
      summary: {
        grand_total_tonase: Math.round(grandTotalTonase * 100) / 100,
        grand_total_trip: grandTotalTrip
      }
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── CUSTOMER ─────────────────────────────────────────────────────────────────

router.get('/customer', async (req, res) => {
  try {
    return jsonResponse(res, true, 'Customer list', await query(`SELECT * FROM customers ORDER BY nama_customer`));
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.post('/customer', requireRole('admin'), async (req, res) => {
  try {
    const nama = cleanInput(req.body.nama_customer).toUpperCase();
    const result = await query(`INSERT INTO customers (nama_customer, status, created_at) VALUES (?,'active',NOW())`, [nama]);
    return jsonResponse(res, true, 'Customer ditambahkan', { id: result.insertId });
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── SUPIR (DRIVERS) ──────────────────────────────────────────────────────────

router.get('/supir', async (req, res) => {
  try {
    const search = req.query.search || '';
    let sql = `SELECT * FROM supir`;
    const params = [];
    if (search) {
      sql += ` WHERE nama_supir LIKE ?`;
      params.push(`%${search}%`);
    }
    sql += ` ORDER BY nama_supir`;
    const data = await query(sql, params);
    return jsonResponse(res, true, 'Driver list', data);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.post('/supir', requireRole('admin'), async (req, res) => {
  try {
    const nama = cleanInput(req.body.nama_supir).toUpperCase();
    const telepon = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');
    const totalHutang = parseFloat(req.body.total_hutang) || 0;
    
    if (!nama) return jsonResponse(res, false, 'Nama supir harus diisi');
    const existing = await queryOne(`SELECT id FROM supir WHERE UPPER(nama_supir) = ?`, [nama]);
    if (existing) return jsonResponse(res, false, 'Supir dengan nama tersebut sudah terdaftar');
    
    const tx = beginTransaction();
    try {
      const result = tx.execute(
        `INSERT INTO supir (nama_supir, no_telepon, alamat, total_hutang, status, created_at) VALUES (?, ?, ?, ?, 'active', datetime('now', 'localtime'))`,
        [nama, telepon, alamat, totalHutang]
      );
      const supirId = result[0].insertId;
      
      if (totalHutang > 0) {
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'tambah', ?, 'Hutang awal saat pendaftaran', ?, ?)`,
          [supirId, totalHutang, totalHutang, req.session.user_id]
        );
      }
      tx.commit();
      return jsonResponse(res, true, 'Supir berhasil ditambahkan', { id: supirId });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.put('/supir/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama_supir).toUpperCase();
    const telepon = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');
    const status = cleanInput(req.body.status) || 'active';
    const totalHutang = parseFloat(req.body.total_hutang) || 0;
    
    const oldSup = await queryOne(`SELECT total_hutang FROM supir WHERE id = ?`, [id]);
    if (!oldSup) return jsonResponse(res, false, 'Supir tidak ditemukan');
    const oldHutang = oldSup.total_hutang || 0;
    
    const tx = beginTransaction();
    try {
      tx.execute(
        `UPDATE supir SET nama_supir=?, no_telepon=?, alamat=?, status=?, total_hutang=?, updated_at=datetime('now', 'localtime') WHERE id=?`,
        [nama, telepon, alamat, status, totalHutang, id]
      );
      
      if (totalHutang !== oldHutang) {
        const diff = totalHutang - oldHutang;
        const jenis = diff > 0 ? 'tambah' : 'bayar';
        const jumlah = Math.abs(diff);
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), ?, ?, 'Penyesuaian nominal hutang (edit supir)', ?, ?)`,
          [id, jenis, jumlah, totalHutang, req.session.user_id]
        );
      }
      tx.commit();
      return jsonResponse(res, true, 'Supir berhasil diupdate');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.delete('/supir/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await query(`UPDATE supir SET status='inactive', updated_at=datetime('now', 'localtime') WHERE id=?`, [id]);
    return jsonResponse(res, true, 'Supir dinonaktifkan');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── ACTIVITY LOGS ────────────────────────────────────────────────────────────

router.get('/activity-logs', requireRole('admin'), async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 100;
    const logs = await query(
      `SELECT al.*, u.nama_lengkap FROM activity_logs al
       LEFT JOIN users u ON al.user_id = u.id
       ORDER BY al.created_at DESC LIMIT ?`, [limit]
    );
    return jsonResponse(res, true, 'Activity logs', logs);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

module.exports = router;
