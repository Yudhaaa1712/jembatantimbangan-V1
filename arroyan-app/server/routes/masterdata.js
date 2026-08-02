/**
 * Masterdata Routes
 * Replaces: modules/masterdata/ (supplier, kendaraan, customer, activity_logs)
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, pool, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { cacheDelete } = require('../helpers/cache');
const { catatHutangTx } = require('../helpers/hutang');
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
      const result = tx.execute(`INSERT INTO supplier (kode_supplier, nama_supplier, status, created_at, default_harga, default_potongan, total_hutang, hutang_terakhir_update) VALUES (?,?,'active',CURRENT_TIMESTAMP,?,?,0,datetime('now', 'localtime'))`, [kode, nama, defaultHarga, defaultPotongan]);
      const supplierId = result[0].insertId;

      if (totalHutang > 0) {
        catatHutangTx(tx, {
          type: 'supplier', partyId: supplierId, jenis: 'tambah', jumlah: totalHutang,
          keterangan: 'Hutang awal saat pendaftaran', sumber: 'manual', operatorId: req.session.user_id
        });
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
      // Update field non-hutang (saldo hutang dikelola lewat buku besar)
      tx.execute(`UPDATE supplier SET nama_supplier=?, status=?, default_harga=?, default_potongan=?, hutang_terakhir_update=datetime('now', 'localtime'), updated_at=CURRENT_TIMESTAMP WHERE id=?`, [nama, status, defaultHarga, defaultPotongan, id]);

      if (totalHutang !== oldHutang) {
        const diff = totalHutang - oldHutang;
        catatHutangTx(tx, {
          type: 'supplier', partyId: id, jenis: diff > 0 ? 'tambah' : 'bayar', jumlah: Math.abs(diff),
          keterangan: 'Penyesuaian nominal hutang (edit supplier)', sumber: 'manual', operatorId: req.session.user_id
        });
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
    const tx = beginTransaction();
    try {
      tx.execute(`DELETE FROM hutang_ledger WHERE party_type = 'supplier' AND party_id = ?`, [id]);
      tx.execute(`DELETE FROM supplier WHERE id = ?`, [id]);
      tx.commit();
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
    cacheDelete('active_suppliers_list');
    return jsonResponse(res, true, 'Supplier berhasil dihapus permanen');
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
    await query(`DELETE FROM kendaraan WHERE id=?`, [parseInt(req.params.id)]);
    return jsonResponse(res, true, 'Kendaraan berhasil dihapus permanen');
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
        `INSERT INTO supir (nama_supir, no_telepon, alamat, total_hutang, status, created_at) VALUES (?, ?, ?, 0, 'active', datetime('now', 'localtime'))`,
        [nama, telepon, alamat]
      );
      const supirId = result[0].insertId;

      if (totalHutang > 0) {
        catatHutangTx(tx, {
          type: 'supir', partyId: supirId, jenis: 'tambah', jumlah: totalHutang,
          keterangan: 'Hutang awal saat pendaftaran', sumber: 'manual', operatorId: req.session.user_id
        });
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
      // Update field non-hutang (saldo hutang dikelola lewat buku besar)
      tx.execute(
        `UPDATE supir SET nama_supir=?, no_telepon=?, alamat=?, status=?, updated_at=datetime('now', 'localtime') WHERE id=?`,
        [nama, telepon, alamat, status, id]
      );

      if (totalHutang !== oldHutang) {
        const diff = totalHutang - oldHutang;
        catatHutangTx(tx, {
          type: 'supir', partyId: id, jenis: diff > 0 ? 'tambah' : 'bayar', jumlah: Math.abs(diff),
          keterangan: 'Penyesuaian nominal hutang (edit supir)', sumber: 'manual', operatorId: req.session.user_id
        });
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
    const tx = beginTransaction();
    try {
      tx.execute(`DELETE FROM potongan_gaji WHERE id_gaji IN (SELECT id FROM gaji_supir WHERE id_supir = ?)`, [id]);
      tx.execute(`DELETE FROM gaji_supir WHERE id_supir = ?`, [id]);
      tx.execute(`DELETE FROM hutang_ledger WHERE party_type = 'supir' AND party_id = ?`, [id]);
      tx.execute(`DELETE FROM supir WHERE id = ?`, [id]);
      tx.commit();
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
    return jsonResponse(res, true, 'Supir berhasil dihapus permanen');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── PABRIK (PKS) ─────────────────────────────────────────────────────────────

router.get('/pabrik', async (req, res) => {
  try {
    const search = req.query.search || '';
    let sql = `SELECT * FROM pabrik WHERE 1=1`;
    const params = [];
    if (search) {
      sql += ` AND nama_pabrik LIKE ?`;
      params.push(`%${search}%`);
    }
    sql += ` ORDER BY nama_pabrik ASC`;
    const data = await query(sql, params);
    return jsonResponse(res, true, 'Pabrik list', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

router.post('/pabrik', requireRole('admin'), async (req, res) => {
  try {
    const nama = cleanInput(req.body.nama_pabrik).toUpperCase();
    const tarif = parseFloat(req.body.tarif_angkut) || 0;

    if (!nama) return jsonResponse(res, false, 'Nama Pabrik tidak boleh kosong');

    const existing = await queryOne(`SELECT id FROM pabrik WHERE LOWER(nama_pabrik) = LOWER(?)`, [nama]);
    if (existing) return jsonResponse(res, false, 'Pabrik dengan nama tersebut sudah ada');

    const result = await query(
      `INSERT INTO pabrik (nama_pabrik, tarif_angkut, status, created_at) VALUES (?, ?, 'active', datetime('now', 'localtime'))`,
      [nama, tarif]
    );

    return jsonResponse(res, true, 'Pabrik berhasil ditambahkan', { id: result.insertId });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

router.put('/pabrik/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama_pabrik).toUpperCase();
    const tarif = parseFloat(req.body.tarif_angkut) || 0;
    const status = cleanInput(req.body.status) || 'active';

    if (!id || !nama) return jsonResponse(res, false, 'Data tidak valid');

    await query(
      `UPDATE pabrik SET nama_pabrik = ?, tarif_angkut = ?, status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`,
      [nama, tarif, status, id]
    );

    return jsonResponse(res, true, 'Pabrik berhasil diperbarui');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

router.delete('/pabrik/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await query(`DELETE FROM pabrik WHERE id = ?`, [id]);
    return jsonResponse(res, true, 'Pabrik berhasil dihapus permanen');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
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
