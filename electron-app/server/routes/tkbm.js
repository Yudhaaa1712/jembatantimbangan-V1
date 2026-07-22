const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput } = require('../config/database');
const { isLoggedIn, requireRole } = require('../middleware/auth');

router.use(isLoggedIn);

// Ambil semua data karyawan TKBM
router.get('/list', async (req, res) => {
  try {
    const data = await query(`SELECT * FROM karyawan_tkbm ORDER BY status ASC, nama_karyawan ASC`);
    return jsonResponse(res, true, 'Data Karyawan TKBM', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// Ambil data TKBM aktif saja (untuk dropdown/checkbox)
router.get('/active', async (req, res) => {
  try {
    const data = await query(`SELECT id, nama_karyawan FROM karyawan_tkbm WHERE status = 'active' ORDER BY nama_karyawan ASC`);
    return jsonResponse(res, true, 'Data Karyawan TKBM Aktif', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// Tambah karyawan TKBM
router.post('/add', requireRole('admin'), async (req, res) => {
  try {
    const nama = cleanInput(req.body.nama_karyawan);
    const telp = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');

    if (!nama) return jsonResponse(res, false, 'Nama karyawan tidak boleh kosong');

    await query(
      `INSERT INTO karyawan_tkbm (nama_karyawan, no_telepon, alamat) VALUES (?, ?, ?)`,
      [nama, telp, alamat]
    );
    
    return jsonResponse(res, true, 'Karyawan TKBM berhasil ditambahkan');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// Update karyawan TKBM
router.put('/update/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama_karyawan);
    const telp = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');
    const status = cleanInput(req.body.status || 'active');

    if (!nama) return jsonResponse(res, false, 'Nama karyawan tidak boleh kosong');

    const result = await query(
      `UPDATE karyawan_tkbm SET nama_karyawan = ?, no_telepon = ?, alamat = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      [nama, telp, alamat, status, id]
    );
    
    if (result.changes === 0) return jsonResponse(res, false, 'Data tidak ditemukan');

    return jsonResponse(res, true, 'Data karyawan TKBM berhasil diupdate');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// Hapus karyawan TKBM
router.delete('/delete/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    
    // Cek apakah pernah ada riwayat kerja (pengiriman_tkbm)
    const riwayat = await queryOne(`SELECT id FROM pengiriman_tkbm WHERE id_tkbm = ? LIMIT 1`, [id]);
    if (riwayat) {
      // Set inactive saja kalau pernah kerja
      await query(`UPDATE karyawan_tkbm SET status = 'inactive' WHERE id = ?`, [id]);
      return jsonResponse(res, true, 'Karyawan memiliki riwayat kerja, status diubah menjadi Non-Aktif');
    }

    await query(`DELETE FROM karyawan_tkbm WHERE id = ?`, [id]);
    return jsonResponse(res, true, 'Data karyawan TKBM berhasil dihapus');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

module.exports = router;
