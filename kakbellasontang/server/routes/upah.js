const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
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

// GET /upah-api/supir-gaji-preview — Hitung preview gaji supir berdasarkan filter tanggal
router.get('/supir-gaji-preview', async (req, res) => {
  try {
    const idSupir = parseInt(req.query.id_supir);
    const startDate = cleanInput(req.query.start_date);
    const endDate = cleanInput(req.query.end_date);

    if (!idSupir || !startDate || !endDate) {
      return jsonResponse(res, false, 'Parameter tidak lengkap');
    }

    const supir = await queryOne(`SELECT * FROM supir WHERE id = ?`, [idSupir]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    // Ambil tarif supir dari setting
    const config = await queryOne(`SELECT tarif_supir FROM pengaturan_gaji LIMIT 1`);
    const rate = config ? parseFloat(config.tarif_supir) : 0;

    // Ambil transaksi selesai untuk supir ini di periode tersebut yang belum digaji
    const transactions = await query(
      `SELECT id, no_surat_jalan as no_tiket, tanggal, no_polisi, netto_ram as netto_akhir, berat_timbangan1, berat_timbangan2, jenis_material, nama_pabrik 
       FROM pengiriman_pabrik 
       WHERE id_supir = ? AND status = 'selesai' AND id_gaji_supir IS NULL AND tanggal BETWEEN ? AND ?
       ORDER BY tanggal ASC`,
      [idSupir, startDate, endDate]
    );

    let totalWeight = 0;
    let totalTrip = transactions.length;

    transactions.forEach(t => {
      totalWeight += parseFloat(t.netto_akhir || 0);
    });

    const totalHargaGaji = totalWeight * rate;

    return jsonResponse(res, true, 'Preview gaji supir', {
      supir: {
        id: supir.id,
        nama_supir: supir.nama_supir,
        total_hutang: supir.total_hutang || 0
      },
      tarif_per_kg: rate,
      total_berat_kg: totalWeight,
      total_trip: totalTrip,
      gaji_kotor: totalHargaGaji,
      transaksi: transactions
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/gaji — Simpan pembayaran gaji supir
router.post('/gaji', requireRole('admin'), async (req, res) => {
  try {
    const { id_supir, start_date, end_date, tarif_per_kg, gaji_kotor, potongan_hutang, potongan_lainnya, catatan } = req.body;
    
    const supirId = parseInt(id_supir);
    const rate = parseFloat(tarif_per_kg) || 0;
    const dirtyWage = parseFloat(gaji_kotor) || 0;
    const potHutang = parseFloat(potongan_hutang) || 0;
    const potLainnya = parseFloat(potongan_lainnya) || 0;

    if (!supirId || !start_date || !end_date) {
      return jsonResponse(res, false, 'Parameter tidak lengkap');
    }

    const supir = await queryOne(`SELECT * FROM supir WHERE id = ?`, [supirId]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    // Hitung ulang transaksi untuk verifikasi data konsisten
    const transactions = await query(
      `SELECT id, netto_ram as netto_akhir FROM pengiriman_pabrik 
       WHERE id_supir = ? AND status = 'selesai' AND id_gaji_supir IS NULL AND tanggal BETWEEN ? AND ?`,
      [supirId, start_date, end_date]
    );

    if (transactions.length === 0) {
      return jsonResponse(res, false, 'Tidak ada transaksi yang belum dibayar pada periode tersebut');
    }

    let totalWeight = 0;
    transactions.forEach(t => {
      totalWeight += parseFloat(t.netto_akhir || 0);
    });

    const calculatedDirtyWage = totalWeight * rate;
    const finalPotHutang = Math.min(potHutang, supir.total_hutang || 0);
    const finalCleanWage = Math.max(0, calculatedDirtyWage - (finalPotHutang + potLainnya));

    const tx = beginTransaction();
    try {
      // 1. Simpan header gaji
      const result = tx.execute(
        `INSERT INTO gaji_supir (id_supir, periode_mulai, periode_akhir, total_berat_kg, total_trip, tarif_per_kg, gaji_kotor, total_potongan, gaji_bersih, status, catatan, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)`,
        [supirId, start_date, end_date, totalWeight, transactions.length, rate, calculatedDirtyWage, (finalPotHutang + potLainnya), finalCleanWage, catatan || '', req.session.user_id]
      );
      const gajiId = result[0].insertId;

      // 2. Simpan rincian potongan
      if (finalPotHutang > 0) {
        tx.execute(
          `INSERT INTO potongan_gaji (id_gaji, jenis_potongan, keterangan, jumlah) VALUES (?, 'hutang', 'Potongan hutang supir', ?)`,
          [gajiId, finalPotHutang]
        );

        // Potong hutang supir
        const newDebt = Math.max(0, (supir.total_hutang || 0) - finalPotHutang);
        tx.execute(`UPDATE supir SET total_hutang = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`, [newDebt, supirId]);

        // Simpan history hutang
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, id_transaksi, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'bayar', ?, ?, NULL, ?, ?)`,
          [supirId, finalPotHutang, `Potongan Gaji Periode ${start_date} s/d ${end_date}`, newDebt, req.session.user_id]
        );
      }

      if (potLainnya > 0) {
        tx.execute(
          `INSERT INTO potongan_gaji (id_gaji, jenis_potongan, keterangan, jumlah) VALUES (?, 'lainnya', ?, ?)`,
          [gajiId, catatan || 'Potongan Lainnya', potLainnya]
        );
      }

      // 3. Link transaksi ke gajiId
      tx.execute(
        `UPDATE pengiriman_pabrik SET id_gaji_supir = ? 
         WHERE id_supir = ? AND status = 'selesai' AND id_gaji_supir IS NULL AND tanggal BETWEEN ? AND ?`,
        [gajiId, supirId, start_date, end_date]
      );

      // 4. Catat ke kas keluar
      if (finalCleanWage > 0) {
        const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
        const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
        const saldoSesudah = saldoSebelum - finalCleanWage;

        tx.execute(
          `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (date('now', 'localtime'), 'keluar', ?, ?, ?, ?)`,
          [finalCleanWage, `PEMBAYARAN UPAH SUPIR - ${supir.nama_supir.toUpperCase()} (${start_date} - ${end_date})`, saldoSesudah, req.session.user_id]
        );
      }

      tx.commit();
      return jsonResponse(res, true, 'Pembayaran gaji supir berhasil disimpan', { id: gajiId });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/gaji/list — Riwayat gaji supir
router.get('/gaji/list', async (req, res) => {
  try {
    const data = await query(
      `SELECT g.*, s.nama_supir, u.nama_lengkap as nama_operator 
       FROM gaji_supir g 
       LEFT JOIN supir s ON g.id_supir = s.id 
       LEFT JOIN users u ON g.created_by = u.id 
       ORDER BY g.created_at DESC`
    );
    return jsonResponse(res, true, 'Riwayat gaji supir', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/gaji/:id — Detail riwayat gaji supir
router.get('/gaji/:id', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const gaji = await queryOne(
      `SELECT g.*, s.nama_supir, u.nama_lengkap as nama_operator 
       FROM gaji_supir g 
       LEFT JOIN supir s ON g.id_supir = s.id 
       LEFT JOIN users u ON g.created_by = u.id 
       WHERE g.id = ?`,
      [id]
    );

    if (!gaji) return jsonResponse(res, false, 'Data gaji tidak ditemukan');

    const potongan = await query(`SELECT * FROM potongan_gaji WHERE id_gaji = ?`, [id]);
    const transaksi = await query(`SELECT id, no_surat_jalan as no_tiket, tanggal, no_polisi, netto_ram as netto_akhir, jenis_material FROM pengiriman_pabrik WHERE id_gaji_supir = ?`, [id]);

    return jsonResponse(res, true, 'Detail gaji supir', {
      gaji,
      potongan,
      transaksi
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// DELETE /upah-api/gaji/:id — Batalkan pembayaran gaji
router.delete('/gaji/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const gaji = await queryOne(`SELECT * FROM gaji_supir WHERE id = ?`, [id]);
    if (!gaji) return jsonResponse(res, false, 'Data gaji tidak ditemukan');

    const supir = await queryOne(`SELECT * FROM supir WHERE id = ?`, [gaji.id_supir]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    const tx = beginTransaction();
    try {
      // 1. Reset link id_gaji_supir di pengiriman pabrik
      tx.execute(`UPDATE pengiriman_pabrik SET id_gaji_supir = NULL WHERE id_gaji_supir = ?`, [id]);

      // 2. Kembalikan potongan hutang jika ada
      const [potHutangRow] = tx.execute(`SELECT jumlah FROM potongan_gaji WHERE id_gaji = ? AND jenis_potongan = 'hutang'`, [id]);
      if (potHutangRow.length > 0) {
        const refundHutang = parseFloat(potHutangRow[0].jumlah);
        const newDebt = (supir.total_hutang || 0) + refundHutang;
        tx.execute(`UPDATE supir SET total_hutang = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`, [newDebt, gaji.id_supir]);

        // Catat history
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'tambah', ?, ?, ?, ?)`,
          [gaji.id_supir, refundHutang, `Pembatalan Gaji #${id} periode ${gaji.periode_mulai} s/d ${gaji.periode_akhir}`, newDebt, req.session.user_id]
        );
      }

      // 3. Hapus detail potongan
      tx.execute(`DELETE FROM potongan_gaji WHERE id_gaji = ?`, [id]);

      // 4. Catat refund kas masuk
      if (gaji.gaji_bersih > 0) {
        const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
        const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
        const saldoSesudah = saldoSebelum + gaji.gaji_bersih;

        tx.execute(
          `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (date('now', 'localtime'), 'masuk', ?, ?, ?, ?)`,
          [gaji.gaji_bersih, `PEMBATALAN PEMBAYARAN UPAH SUPIR #${id} - ${supir.nama_supir.toUpperCase()}`, saldoSesudah, req.session.user_id]
        );
      }

      // 5. Hapus header gaji
      tx.execute(`DELETE FROM gaji_supir WHERE id = ?`, [id]);

      tx.commit();
      return jsonResponse(res, true, 'Pembayaran upah berhasil dibatalkan');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ==========================================
// API UPAH TKBM
// ==========================================

// GET /upah-api/tkbm-gaji-preview — Hitung preview gaji TKBM
router.get('/tkbm-gaji-preview', async (req, res) => {
  try {
    const idTkbm = parseInt(req.query.id_tkbm);
    const startDate = cleanInput(req.query.start_date);
    const endDate = cleanInput(req.query.end_date);

    if (!idTkbm || !startDate || !endDate) return jsonResponse(res, false, 'Parameter tidak lengkap');

    const tkbm = await queryOne(`SELECT * FROM karyawan_tkbm WHERE id = ?`, [idTkbm]);
    if (!tkbm) return jsonResponse(res, false, 'TKBM tidak ditemukan');

    const config = await queryOne(`SELECT tarif_pemuat FROM pengaturan_gaji LIMIT 1`);
    const rate = config ? parseFloat(config.tarif_pemuat) : 0;

    // Cari trip pengiriman_pabrik yang melibatkan TKBM ini, yang sudah selesai dan belum digaji
    const sql = `
      SELECT pt.id as id_pengiriman_tkbm, p.id as id_pengiriman, p.no_surat_jalan as no_tiket, p.tanggal, p.no_polisi, p.netto_ram, p.nama_pabrik,
             (SELECT COUNT(*) FROM pengiriman_tkbm WHERE id_pengiriman = p.id) as jumlah_pekerja
      FROM pengiriman_tkbm pt
      JOIN pengiriman_pabrik p ON pt.id_pengiriman = p.id
      WHERE pt.id_tkbm = ? AND p.status = 'selesai' AND pt.id_gaji_tkbm IS NULL AND p.tanggal BETWEEN ? AND ?
      ORDER BY p.tanggal ASC
    `;
    const transactions = await query(sql, [idTkbm, startDate, endDate]);

    let totalGaji = 0;
    
    transactions.forEach(t => {
      const netto = parseFloat(t.netto_ram || 0);
      const totalGajiTrip = netto * rate;
      const pekerjaCount = parseInt(t.jumlah_pekerja) || 1;
      const gajiPerOrang = totalGajiTrip / pekerjaCount;
      t.gaji_bagian = gajiPerOrang;
      totalGaji += gajiPerOrang;
    });

    return jsonResponse(res, true, 'Preview gaji TKBM', {
      tkbm: { id: tkbm.id, nama_karyawan: tkbm.nama_karyawan },
      tarif_pemuat: rate,
      total_trip: transactions.length,
      gaji_kotor: totalGaji,
      transaksi: transactions
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/tkbm-gaji — Simpan pembayaran gaji TKBM
router.post('/tkbm-gaji', requireRole('admin'), async (req, res) => {
  try {
    const { id_tkbm, start_date, end_date, tarif_pemuat, gaji_kotor, potongan_lainnya, catatan } = req.body;
    
    const idTkbm = parseInt(id_tkbm);
    const rate = parseFloat(tarif_pemuat) || 0;
    const dirtyWage = parseFloat(gaji_kotor) || 0;
    const potLainnya = parseFloat(potongan_lainnya) || 0;
    const finalCleanWage = Math.max(0, dirtyWage - potLainnya);

    if (!idTkbm || !start_date || !end_date) return jsonResponse(res, false, 'Parameter tidak lengkap');

    const tkbm = await queryOne(`SELECT * FROM karyawan_tkbm WHERE id = ?`, [idTkbm]);
    if (!tkbm) return jsonResponse(res, false, 'TKBM tidak ditemukan');

    const tx = beginTransaction();
    try {
      // Validasi ulang
      const sql = `
        SELECT pt.id, p.netto_ram, (SELECT COUNT(*) FROM pengiriman_tkbm WHERE id_pengiriman = p.id) as jumlah_pekerja
        FROM pengiriman_tkbm pt
        JOIN pengiriman_pabrik p ON pt.id_pengiriman = p.id
        WHERE pt.id_tkbm = ? AND p.status = 'selesai' AND pt.id_gaji_tkbm IS NULL AND p.tanggal BETWEEN ? AND ?
      `;
      const transactions = await tx.execute(sql, [idTkbm, start_date, end_date]);
      
      if (transactions.length === 0) throw new Error('Tidak ada transaksi yang belum dibayar');

      let calculatedTotalGaji = 0;
      transactions.forEach(t => {
        const netto = parseFloat(t.netto_ram || 0);
        const totalGajiTrip = netto * rate;
        const pekerjaCount = parseInt(t.jumlah_pekerja) || 1;
        calculatedTotalGaji += (totalGajiTrip / pekerjaCount);
      });

      // 1. Insert header gaji
      const result = tx.execute(
        `INSERT INTO gaji_tkbm (id_tkbm, periode_mulai, periode_akhir, total_trip, tarif_pemuat, gaji_kotor, potongan_lainnya, gaji_bersih, status, catatan, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)`,
        [idTkbm, start_date, end_date, transactions.length, rate, calculatedTotalGaji, potLainnya, (calculatedTotalGaji - potLainnya), catatan || '', req.session.user_id]
      );
      const gajiId = result[0].insertId;

      // 2. Link trip ke gaji
      const arrIds = transactions.map(t => t.id).join(',');
      tx.execute(`UPDATE pengiriman_tkbm SET id_gaji_tkbm = ? WHERE id IN (${arrIds})`, [gajiId]);

      // 3. Catat ke kas keluar
      const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
      const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
      const saldoSesudah = saldoSebelum - (calculatedTotalGaji - potLainnya);

      tx.execute(
        `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
         VALUES (date('now', 'localtime'), 'keluar', ?, ?, ?, ?)`,
        [(calculatedTotalGaji - potLainnya), `PEMBAYARAN UPAH TKBM - ${tkbm.nama_karyawan.toUpperCase()} (${start_date} - ${end_date})`, saldoSesudah, req.session.user_id]
      );

      tx.commit();
      return jsonResponse(res, true, 'Pembayaran gaji TKBM berhasil disimpan', { id: gajiId });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/tkbm-gaji/list — Riwayat gaji TKBM
router.get('/tkbm-gaji/list', async (req, res) => {
  try {
    const data = await query(
      `SELECT g.*, t.nama_karyawan, u.nama_lengkap as nama_operator 
       FROM gaji_tkbm g 
       LEFT JOIN karyawan_tkbm t ON g.id_tkbm = t.id 
       LEFT JOIN users u ON g.created_by = u.id 
       ORDER BY g.created_at DESC`
    );
    return jsonResponse(res, true, 'Riwayat gaji TKBM', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/tkbm-gaji/:id — Detail riwayat gaji TKBM
router.get('/tkbm-gaji/:id', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const gaji = await queryOne(
      `SELECT g.*, t.nama_karyawan, u.nama_lengkap as nama_operator 
       FROM gaji_tkbm g 
       LEFT JOIN karyawan_tkbm t ON g.id_tkbm = t.id 
       LEFT JOIN users u ON g.created_by = u.id 
       WHERE g.id = ?`,
      [id]
    );

    if (!gaji) return jsonResponse(res, false, 'Data gaji tidak ditemukan');

    const sql = `
      SELECT pt.id, p.no_surat_jalan as no_tiket, p.tanggal, p.no_polisi, p.netto_ram, p.nama_pabrik,
             (SELECT COUNT(*) FROM pengiriman_tkbm WHERE id_pengiriman = p.id) as jumlah_pekerja
      FROM pengiriman_tkbm pt
      JOIN pengiriman_pabrik p ON pt.id_pengiriman = p.id
      WHERE pt.id_gaji_tkbm = ?
    `;
    const transaksi = await query(sql, [id]);
    
    // hitung ulang gaji bagian untuk display
    transaksi.forEach(t => {
      const netto = parseFloat(t.netto_ram || 0);
      const totalGajiTrip = netto * gaji.tarif_pemuat;
      const pekerjaCount = parseInt(t.jumlah_pekerja) || 1;
      t.gaji_bagian = totalGajiTrip / pekerjaCount;
    });

    return jsonResponse(res, true, 'Detail gaji TKBM', { gaji, transaksi });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// DELETE /upah-api/tkbm-gaji/:id — Batalkan pembayaran gaji TKBM
router.delete('/tkbm-gaji/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const gaji = await queryOne(`SELECT * FROM gaji_tkbm WHERE id = ?`, [id]);
    if (!gaji) return jsonResponse(res, false, 'Data gaji tidak ditemukan');

    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE pengiriman_tkbm SET id_gaji_tkbm = NULL WHERE id_gaji_tkbm = ?`, [id]);

      const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
      const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
      const nominal = parseFloat(gaji.gaji_bersih);
      
      if (nominal > 0) {
        const saldoSesudah = saldoSebelum + nominal;
        tx.execute(
          `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (date('now', 'localtime'), 'masuk', ?, ?, ?, ?)`,
          [nominal, `PEMBATALAN PEMBAYARAN UPAH TKBM ID #${id}`, saldoSesudah, req.session.user_id]
        );
      }

      tx.execute(`DELETE FROM gaji_tkbm WHERE id = ?`, [id]);
      tx.commit();
      
      return jsonResponse(res, true, 'Pembayaran gaji TKBM berhasil dibatalkan');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

module.exports = router;
