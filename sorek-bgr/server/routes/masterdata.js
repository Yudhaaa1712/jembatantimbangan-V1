/**
 * Masterdata Routes
 * Replaces: modules/masterdata/ (supplier, kendaraan, customer, activity_logs)
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, pool, jsonResponse, cleanInput } = require('../config/database');
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
    const existing = await queryOne(`SELECT id FROM supplier WHERE nama_supplier = ?`, [nama]);
    if (existing) return jsonResponse(res, false, 'Supplier sudah ada');
    const kode = 'SUP-' + new Date().toISOString().slice(2,8).replace(/-/g,'') + '-' + String(Math.floor(Math.random()*999)+1).padStart(3,'0');
    const result = await query(`INSERT INTO supplier (kode_supplier, nama_supplier, status, created_at, default_harga, default_potongan) VALUES (?,?,'active',CURRENT_TIMESTAMP,?,?)`, [kode, nama, defaultHarga, defaultPotongan]);
    cacheDelete('active_suppliers_list');
    return jsonResponse(res, true, 'Supplier berhasil ditambahkan', { id: result.insertId || result.lastID });
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.put('/supplier/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama_supplier).toUpperCase();
    const status = cleanInput(req.body.status) || 'active';
    const defaultHarga = parseFloat(req.body.default_harga) || 0;
    const defaultPotongan = parseFloat(req.body.default_potongan) || 0;
    await query(`UPDATE supplier SET nama_supplier=?, status=?, default_harga=?, default_potongan=?, updated_at=CURRENT_TIMESTAMP WHERE id=?`, [nama, status, defaultHarga, defaultPotongan, id]);
    cacheDelete('active_suppliers_list');
    return jsonResponse(res, true, 'Supplier berhasil diupdate');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

router.delete('/supplier/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await query(`UPDATE supplier SET status='inactive', updated_at=NOW() WHERE id=?`, [id]);
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
