const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput } = require('../config/database');
const { isLoggedIn, requireRole } = require('../middleware/auth');

router.use(isLoggedIn);

// Ambil daftar pengaturan tarif upah
router.get('/pengaturan', async (req, res) => {
  try {
    const data = await queryOne(`SELECT * FROM pengaturan_gaji LIMIT 1`);
    return jsonResponse(res, true, 'Data pengaturan', data || { tarif_per_kg: 0, tarif_supir: 0, tarif_pemuat: 0 });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// Simpan pengaturan tarif
router.post('/pengaturan', requireRole('admin'), async (req, res) => {
  try {
    const tarifPetani = parseFloat(req.body.tarif_petani) || 0;
    const tarifSupir = parseFloat(req.body.tarif_supir) || 0;
    const tarifPemuat = parseFloat(req.body.tarif_pemuat) || 0;
    
    // Check if columns exist (migration)
    try {
        await query(`ALTER TABLE pengaturan_gaji ADD COLUMN tarif_supir REAL DEFAULT 0`);
        await query(`ALTER TABLE pengaturan_gaji ADD COLUMN tarif_pemuat REAL DEFAULT 0`);
    } catch(e) {} // Ignore if already exist

    const existing = await queryOne(`SELECT id FROM pengaturan_gaji LIMIT 1`);
    if (existing) {
      await query(`UPDATE pengaturan_gaji SET tarif_per_kg = ?, tarif_supir = ?, tarif_pemuat = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?`, 
        [tarifPetani, tarifSupir, tarifPemuat, existing.id]);
    } else {
      await query(`INSERT INTO pengaturan_gaji (tarif_per_kg, tarif_supir, tarif_pemuat) VALUES (?, ?, ?)`, 
        [tarifPetani, tarifSupir, tarifPemuat]);
    }
    return jsonResponse(res, true, 'Pengaturan upah berhasil disimpan');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

module.exports = router;
