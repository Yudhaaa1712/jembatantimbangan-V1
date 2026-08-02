const express = require('express');
const router = express.Router();
const ExcelJS = require('exceljs');
const { query, queryOne, jsonResponse, cleanInput, beginTransaction, formatRupiah } = require('../config/database');
const { isLoggedIn, requireRole } = require('../middleware/auth');
const { catatHutangTx } = require('../helpers/hutang');

router.use(isLoggedIn);

// ─── Helper date filter (sama seperti transaksi.js & pengiriman.js) ──────────
function buildDateCondition(dateFilter, startDate, endDate) {
  switch (dateFilter) {
    case 'today':      return { sql: `pp.tanggal = date('now', 'localtime')`, params: [] };
    case 'yesterday':  return { sql: `pp.tanggal = date('now', 'localtime', '-1 day')`, params: [] };
    case 'week':       return { sql: `pp.tanggal >= date('now', 'localtime', '-7 days')`, params: [] };
    case 'half_month': return { sql: `pp.tanggal >= date('now', 'localtime', '-15 days')`, params: [] };
    case 'month':      return { sql: `strftime('%m', pp.tanggal) = strftime('%m', 'now', 'localtime') AND strftime('%Y', pp.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'half_year':  return { sql: `pp.tanggal >= date('now', 'localtime', '-6 months')`, params: [] };
    case 'year':       return { sql: `strftime('%Y', pp.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'custom_range': return { sql: `pp.tanggal BETWEEN ? AND ?`, params: [startDate, endDate] };
    default:           return { sql: `pp.tanggal = date('now', 'localtime')`, params: [] };
  }
}

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
      `SELECT id, no_surat_jalan as no_tiket, tanggal, no_polisi, COALESCE(netto_pabrik,0) as netto_akhir, berat_timbangan1, berat_timbangan2, jenis_material, nama_pabrik
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

// GET /upah-api/rekap-supir-pabrik — Rekap Gaji Supir per Pabrik (Format Excel Klien)
router.get('/rekap-supir-pabrik', async (req, res) => {
  try {
    const supirQuery = cleanInput(req.query.id_supir || req.query.nama_supir || '');
    const dateFilter = req.query.date_filter || 'month';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    let sql = `
      SELECT
        pp.id, pp.no_surat_jalan, pp.tanggal, pp.nama_pabrik, pp.nama_supir, pp.id_supir,
        pp.berat_bruto, pp.berat_tara, pp.netto_ram, pp.netto_pabrik,
        COALESCE(pp.harga_per_kg, 0) as harga_per_kg,
        COALESCE(pp.pinjaman, 0) as pinjaman,
        COALESCE(pp.biaya_es_jalan, 0) as biaya_es_jalan,
        COALESCE(pp.status_bayar, 'belum_bayar') as status_bayar,
        COALESCE(pp.pinjaman_diproses, 0) as pinjaman_diproses,
        pp.status
      FROM pengiriman_pabrik pp
      WHERE ${dateSql}
    `;
    const params = [...dateParams];

    if (supirQuery) {
      if (!isNaN(parseInt(supirQuery))) {
        const supirObj = await queryOne(`SELECT nama_supir FROM supir WHERE id = ?`, [parseInt(supirQuery)]);
        const supirNama = supirObj ? supirObj.nama_supir : '';
        sql += ` AND (pp.id_supir = ? OR LOWER(pp.nama_supir) = LOWER(?) OR LOWER(pp.nama_supir) = LOWER(?))`;
        params.push(parseInt(supirQuery), supirQuery, supirNama);
      } else {
        sql += ` AND LOWER(pp.nama_supir) LIKE LOWER(?)`;
        params.push(`%${supirQuery}%`);
      }
    }

    sql += ` ORDER BY pp.tanggal ASC, pp.id ASC`;

    const rows = await query(sql, params);

    let totalTonase = 0;
    let totalHasil = 0;
    let totalPinjaman = 0;
    let totalPinjamanBelum = 0; // pinjaman yang belum disinkron ke Manajemen Hutang
    let totalEsJalan = 0;
    let totalHasilBersih = 0;

    const dataFormatted = rows.map(r => {
      const tonase = parseFloat(r.netto_pabrik || 0); // Tonase Angkut (netto DO pabrik)
      const hargaKg = parseFloat(r.harga_per_kg || 0);
      const hasil = tonase * hargaKg;
      const pinjaman = parseFloat(r.pinjaman || 0);
      const esJalan = parseFloat(r.biaya_es_jalan || 0);
      const hBersih = hasil - pinjaman - esJalan;
      const diproses = parseInt(r.pinjaman_diproses || 0) === 1;

      totalTonase += tonase;
      totalHasil += hasil;
      totalPinjaman += pinjaman;
      if (!diproses) totalPinjamanBelum += pinjaman;
      totalEsJalan += esJalan;
      totalHasilBersih += hBersih;

      return {
        id: r.id,
        no_surat_jalan: r.no_surat_jalan,
        tanggal: r.tanggal,
        nama_pabrik: r.nama_pabrik,
        nama_supir: r.nama_supir,
        id_supir: r.id_supir,
        tonase_angkut: tonase,
        harga_per_kg: hargaKg,
        hasil: hasil,
        pinjaman: pinjaman,
        biaya_es_jalan: esJalan,
        hasil_bersih: hBersih,
        pinjaman_diproses: diproses ? 1 : 0,
        status_bayar: r.status_bayar || 'belum_bayar',
        status: r.status
      };
    });

    // Info hutang saat ini bila 1 supir dipilih (untuk panel Potong Pinjaman)
    let hutangSaatIni = null;
    let idSupirTerpilih = null;
    if (supirQuery && !isNaN(parseInt(supirQuery))) {
      const s = await queryOne(`SELECT id, COALESCE(total_hutang,0) AS total_hutang FROM supir WHERE id = ?`, [parseInt(supirQuery)]);
      if (s) { hutangSaatIni = parseFloat(s.total_hutang) || 0; idSupirTerpilih = s.id; }
    }

    return jsonResponse(res, true, 'Data Rekap Supir Pabrik', {
      items: dataFormatted,
      summary: {
        total_tonase: totalTonase,
        total_hasil: totalHasil,
        total_pinjaman: totalPinjaman,
        total_pinjaman_belum_diproses: totalPinjamanBelum,
        total_es_jalan: totalEsJalan,
        total_hasil_bersih: totalHasilBersih,
        total_trip: dataFormatted.length,
        hutang_saat_ini: hutangSaatIni,
        id_supir: idSupirTerpilih
      }
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/update-rekap-item — Update Harga/Kg, Pinjaman, atau Es+Jalan per Item Pengiriman
router.post('/update-rekap-item', async (req, res) => {
  try {
    const id = parseInt(req.body.id);
    const hargaKg = parseFloat(req.body.harga_per_kg) || 0;
    const pinjaman = parseFloat(req.body.pinjaman) || 0;
    const biayaEsJalan = parseFloat(req.body.biaya_es_jalan) || 0;

    if (!id) {
      return jsonResponse(res, false, 'ID Pengiriman tidak valid');
    }

    await query(
      `UPDATE pengiriman_pabrik SET 
       harga_per_kg = ?, pinjaman = ?, biaya_es_jalan = ?, updated_at = datetime('now', 'localtime')
       WHERE id = ?`,
      [hargaKg, pinjaman, biayaEsJalan, id]
    );

    return jsonResponse(res, true, 'Item rekap berhasil diperbarui');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/toggle-status-bayar — Tandai item rekap Lunas / Belum Bayar
router.post('/toggle-status-bayar', async (req, res) => {
  try {
    const id = parseInt(req.body.id);
    const statusBayar = req.body.status_bayar === 'lunas' ? 'lunas' : 'belum_bayar';

    if (!id) {
      return jsonResponse(res, false, 'ID Pengiriman tidak valid');
    }

    await query(
      `UPDATE pengiriman_pabrik SET status_bayar = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`,
      [statusBayar, id]
    );

    return jsonResponse(res, true, statusBayar === 'lunas' ? 'Ditandai Lunas' : 'Ditandai Belum Bayar', { id, status_bayar: statusBayar });
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
      `SELECT id, COALESCE(netto_pabrik,0) as netto_akhir FROM pengiriman_pabrik
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

        // Potong hutang supir (otomatis) → buku besar terpadu
        catatHutangTx(tx, {
          type: 'supir', partyId: supirId, jenis: 'bayar', jumlah: finalPotHutang,
          keterangan: `Potongan Gaji Periode ${start_date} s/d ${end_date}`,
          idReferensi: gajiId, sumber: 'gaji', operatorId: req.session.user_id
        });
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
    const transaksi = await query(`SELECT id, no_surat_jalan as no_tiket, tanggal, no_polisi, COALESCE(netto_pabrik,0) as netto_akhir, jenis_material FROM pengiriman_pabrik WHERE id_gaji_supir = ?`, [id]);

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
        // Kembalikan hutang supir (otomatis) → buku besar terpadu
        catatHutangTx(tx, {
          type: 'supir', partyId: gaji.id_supir, jenis: 'tambah', jumlah: refundHutang,
          keterangan: `Pembatalan Gaji #${id} periode ${gaji.periode_mulai} s/d ${gaji.periode_akhir}`,
          idReferensi: id, sumber: 'gaji', operatorId: req.session.user_id
        });
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
      SELECT pt.id as id_pengiriman_tkbm, p.id as id_pengiriman, p.no_surat_jalan as no_tiket, p.tanggal, p.no_polisi, COALESCE(p.netto_pabrik,0) AS netto_ram, p.nama_pabrik,
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
        SELECT pt.id, COALESCE(p.netto_pabrik,0) AS netto_ram, (SELECT COUNT(*) FROM pengiriman_tkbm WHERE id_pengiriman = p.id) as jumlah_pekerja
        FROM pengiriman_tkbm pt
        JOIN pengiriman_pabrik p ON pt.id_pengiriman = p.id
        WHERE pt.id_tkbm = ? AND p.status = 'selesai' AND pt.id_gaji_tkbm IS NULL AND p.tanggal BETWEEN ? AND ?
      `;
      const [transactions] = await tx.execute(sql, [idTkbm, start_date, end_date]);

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
      SELECT pt.id, p.no_surat_jalan as no_tiket, p.tanggal, p.no_polisi, COALESCE(p.netto_pabrik,0) AS netto_ram, p.nama_pabrik,
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

// ─── Helper: ambil trips + roster TKBM untuk sebuah periode ──────────────────
async function getTkbmTripsRoster(dateSql, dateParams) {
  const trips = await query(`
    SELECT pp.id, pp.tanggal, COALESCE(pp.netto_pabrik,0) AS netto_ram, pp.no_polisi, pp.nama_pabrik,
           GROUP_CONCAT(pt.id_tkbm) AS worker_ids
    FROM pengiriman_pabrik pp
    JOIN pengiriman_tkbm pt ON pt.id_pengiriman = pp.id
    WHERE pp.status IN ('timbang_2','selesai') AND COALESCE(pp.netto_pabrik,0) > 0 AND ${dateSql}
    GROUP BY pp.id
    ORDER BY pp.tanggal ASC, pp.id ASC
  `, dateParams);

  const rosterIds = new Set();
  trips.forEach(t => String(t.worker_ids || '').split(',').forEach(x => x && rosterIds.add(parseInt(x))));
  let roster = [];
  if (rosterIds.size) {
    roster = await query(`SELECT id, nama_karyawan FROM karyawan_tkbm WHERE id IN (${[...rosterIds].join(',')}) ORDER BY nama_karyawan`);
  }
  roster = roster.map(r => ({ id: r.id, nama: r.nama_karyawan, inisial: (r.nama_karyawan || '?')[0].toUpperCase() }));
  return { trips, roster };
}

// Bangun baris slip untuk seorang TKBM dari daftar trips
function buildSlipRows(trips, idTkbm, rate) {
  let totalUpah = 0, totalTripIkut = 0, totalTonaseIkut = 0;
  const rows = trips.map(t => {
    const ids = String(t.worker_ids || '').split(',').filter(Boolean).map(Number);
    const tonase = parseFloat(t.netto_ram || 0);
    const total = ids.length || 1;
    const ikut = ids.includes(idTkbm);
    const jumlah = ikut ? (tonase * rate) / total : null;
    if (ikut) { totalUpah += jumlah; totalTripIkut++; totalTonaseIkut += tonase; }
    return { tanggal: t.tanggal, tonase, per_kg: rate, worker_ids: ids, total, jumlah };
  });
  return { rows, summary: { total_trip_ikut: totalTripIkut, total_tonase_ikut: totalTonaseIkut, total_upah: totalUpah } };
}

async function getTarifPemuat() {
  const config = await queryOne(`SELECT tarif_pemuat FROM pengaturan_gaji LIMIT 1`);
  return config && config.tarif_pemuat != null ? parseFloat(config.tarif_pemuat) : 30;
}

// GET /upah-api/slip-tkbm — Slip gaji TKBM (JSON untuk tampilan)
// id_tkbm terisi  → slip per pekerja (Jumlah Uang hanya utk trip yang dia ikut)
// id_tkbm kosong  → mode SEMUA: semua trip tampil, Jumlah Uang = upah per orang trip tsb
router.get('/slip-tkbm', async (req, res) => {
  try {
    const idTkbm = parseInt(req.query.id_tkbm) || 0;
    const dateFilter = req.query.date_filter || 'month';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];
    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    const rate = await getTarifPemuat();
    const { trips, roster } = await getTkbmTripsRoster(dateSql, dateParams);

    if (idTkbm) {
      const tkbm = await queryOne(`SELECT id, nama_karyawan FROM karyawan_tkbm WHERE id = ?`, [idTkbm]);
      if (!tkbm) return jsonResponse(res, false, 'TKBM tidak ditemukan');
      const { rows, summary } = buildSlipRows(trips, idTkbm, rate);
      return jsonResponse(res, true, 'Slip gaji TKBM', {
        tkbm: { id: tkbm.id, nama: tkbm.nama_karyawan }, tarif: rate, roster, rows, summary
      });
    }

    // Mode SEMUA TKBM
    let totalTonase = 0, totalUpahSemua = 0;
    const rows = trips.map(t => {
      const ids = String(t.worker_ids || '').split(',').filter(Boolean).map(Number);
      const tonase = parseFloat(t.netto_ram || 0);
      const total = ids.length || 1;
      const jumlah = (tonase * rate) / total; // upah per orang pada trip ini
      totalTonase += tonase;
      totalUpahSemua += tonase * rate;        // total upah dibayarkan utk trip (semua orang)
      return { tanggal: t.tanggal, tonase, per_kg: rate, worker_ids: ids, total, jumlah };
    });

    return jsonResponse(res, true, 'Slip gaji TKBM (semua)', {
      tkbm: { id: 0, nama: 'SEMUA TKBM' }, tarif: rate, roster, rows,
      summary: { total_trip_ikut: rows.length, total_tonase_ikut: totalTonase, total_upah: totalUpahSemua }
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── Style helper ExcelJS ────────────────────────────────────────────────────
const THIN = { style: 'thin', color: { argb: 'FF000000' } };
const BORDER_ALL = { top: THIN, left: THIN, bottom: THIN, right: THIN };
function styleHeaderRow(row) {
  row.eachCell(c => {
    c.font = { bold: true, color: { argb: 'FFFFFFFF' } };
    c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1A1A2E' } };
    c.alignment = { horizontal: 'center', vertical: 'middle' };
    c.border = BORDER_ALL;
  });
}

// GET /upah-api/slip-tkbm/export — Excel .xlsx desain slip (1 sheet/pekerja bila id_tkbm kosong)
router.get('/slip-tkbm/export', async (req, res) => {
  try {
    const idTkbm = parseInt(req.query.id_tkbm) || 0;
    const dateFilter = req.query.date_filter || 'month';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];
    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    const rate = await getTarifPemuat();
    const { trips, roster } = await getTkbmTripsRoster(dateSql, dateParams);

    const targets = idTkbm ? roster.filter(r => r.id === idTkbm) : roster;
    if (!targets.length) return res.status(404).send('Tidak ada data TKBM pada periode ini');

    // Peta hutang terkini + total potong (bayar) TKBM selama periode (Manajemen Hutang)
    const tIds = targets.map(t => t.id);
    const debtMapT = {}, potongMapT = {};
    if (tIds.length) {
      const idList = tIds.join(',');
      const dr = await query(`SELECT id, COALESCE(total_hutang,0) AS h FROM karyawan_tkbm WHERE id IN (${idList})`);
      dr.forEach(d => { debtMapT[d.id] = parseFloat(d.h) || 0; });
      const ledgerDateSql = dateSql.replace(/pp\.tanggal/g, 'h.tanggal');
      const pr = await query(
        `SELECT h.party_id AS pid, COALESCE(SUM(h.jumlah),0) AS s
         FROM hutang_ledger h
         WHERE h.party_type='tkbm' AND h.jenis='bayar' AND h.party_id IN (${idList}) AND ${ledgerDateSql}
         GROUP BY h.party_id`, dateParams);
      pr.forEach(p => { potongMapT[p.pid] = parseFloat(p.s) || 0; });
    }

    const wb = new ExcelJS.Workbook();

    const GREEN = 'FFC6E0B4';   // pita hijau muda
    const nRoster = roster.length;
    const COL_TOTAL = 5 + nRoster;      // kolom Total
    const COL_UANG  = 6 + nRoster;      // kolom Jumlah Uang
    const HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const now = new Date();
    const tglCetak = `${HARI[now.getDay()]}, ${now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}`;

    for (const person of targets) {
      const { rows, summary } = buildSlipRows(trips, person.id, rate);
      const safeName = (person.nama || 'TKBM').replace(/[\\\/\?\*\[\]:]/g, ' ').slice(0, 28) || 'TKBM';
      const ws = wb.addWorksheet(safeName, { views: [{ showGridLines: false }] });

      // ── Pita hijau (baris 1-5) ──
      for (let r = 1; r <= 5; r++) {
        for (let c = 1; c <= COL_UANG; c++) {
          ws.getCell(r, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
        }
      }
      // Judul di tengah (baris 1-2, selebar tabel)
      ws.mergeCells(1, 1, 2, COL_UANG);
      const title = ws.getCell(1, 1);
      title.value = 'SLIP GAJI KARYAWAN';
      title.font = { bold: true, size: 16 };
      title.alignment = { horizontal: 'center', vertical: 'middle' };
      title.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
      // Info kiri (tiap baris digabung beberapa kolom agar tidak terpotong)
      const infoSpan = Math.min(4, COL_UANG);
      ws.mergeCells(3, 1, 3, infoSpan);
      ws.mergeCells(4, 1, 4, infoSpan);
      ws.mergeCells(5, 1, 5, infoSpan);
      ws.getCell(3, 1).value = tglCetak;                ws.getCell(3, 1).font = { bold: true, size: 10 };
      ws.getCell(4, 1).value = 'Nama : ' + person.nama; ws.getCell(4, 1).font = { bold: true, size: 10 };
      ws.getCell(5, 1).value = 'Posisi : TKBM';         ws.getCell(5, 1).font = { bold: true, size: 10 };

      // ── Header tabel (baris 7): TKBM digabung di atas kolom inisial ──
      const hr = 7;
      ws.getCell(hr, 1).value = 'No.';
      ws.getCell(hr, 2).value = 'Tanggal';
      ws.getCell(hr, 3).value = 'Tonase';
      ws.getCell(hr, 4).value = 'Per Kg';
      if (nRoster > 1) ws.mergeCells(hr, 5, hr, 4 + nRoster);
      ws.getCell(hr, 5).value = 'TKBM';
      ws.getCell(hr, COL_TOTAL).value = 'Total';
      ws.getCell(hr, COL_UANG).value = 'Jumlah Uang';
      for (let c = 1; c <= COL_UANG; c++) {
        const cell = ws.getCell(hr, c);
        cell.font = { bold: true };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
        cell.border = BORDER_ALL;
      }
      ws.getRow(hr).height = 20;

      // ── Isi ──
      rows.forEach((row, i) => {
        const r = ws.addRow([ i + 1,
          row.tanggal ? new Date(row.tanggal) : null,
          row.tonase, row.per_kg,
          ...roster.map(w => row.worker_ids.includes(w.id) ? w.inisial : '-'),
          row.total, row.jumlah != null ? Math.round(row.jumlah) : null ]);
        r.eachCell({ includeEmpty: true }, (c, col) => { if (col <= COL_UANG) c.border = BORDER_ALL; });
        r.getCell(1).font = { bold: true };
        r.getCell(1).alignment = { horizontal: 'center' };
        r.getCell(2).numFmt = 'd/m/yyyy';
        r.getCell(2).alignment = { horizontal: 'center' };
        r.getCell(3).numFmt = '#,##0';
        r.getCell(4).alignment = { horizontal: 'center' };
        for (let c = 5; c <= 4 + nRoster; c++) r.getCell(c).alignment = { horizontal: 'center' };
        r.getCell(COL_TOTAL).alignment = { horizontal: 'center' };
        r.getCell(COL_UANG).numFmt = '"Rp."* #,##0';   // Rp. kiri, angka kanan (gaya akuntansi)
      });

      // ── Baris TOTAL ──
      const totalRow = ws.addRow(['', 'TOTAL', summary.total_tonase_ikut, '',
        ...roster.map(() => ''), summary.total_trip_ikut + ' trip', Math.round(summary.total_upah)]);
      totalRow.eachCell({ includeEmpty: true }, (c, col) => {
        if (col <= COL_UANG) {
          c.border = BORDER_ALL; c.font = { bold: true };
          c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
        }
      });
      totalRow.getCell(3).numFmt = '#,##0';
      totalRow.getCell(COL_TOTAL).alignment = { horizontal: 'center' };
      totalRow.getCell(COL_UANG).numFmt = '"Rp."* #,##0';

      // ── Blok ringkasan: Total Gaji, Pinjaman, Potong Pinjaman, Gaji Diterima ──
      const totalGajiT = Math.round(summary.total_upah);
      const debtT = debtMapT[person.id] || 0;
      const potongT = potongMapT[person.id] || 0;
      const diterimaT = Math.max(0, totalGajiT - potongT);
      const LBL_A = Math.max(1, COL_UANG - 4), LBL_B = COL_UANG - 1, VAL_C = COL_UANG;
      ws.addRow([]); // jarak

      // Judul blok
      const hRowT = ws.addRow([]);
      ws.mergeCells(hRowT.number, LBL_A, hRowT.number, VAL_C);
      const hCellT = ws.getCell(hRowT.number, LBL_A);
      hCellT.value = 'KETERANGAN GAJI';
      hCellT.font = { bold: true };
      hCellT.alignment = { horizontal: 'center', vertical: 'middle' };
      for (let c = LBL_A; c <= VAL_C; c++) {
        ws.getCell(hRowT.number, c).border = BORDER_ALL;
        ws.getCell(hRowT.number, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
      }

      const ringkasanT = [
        ['Total Gaji', totalGajiT],
        ['Pinjaman', debtT],
        ['Potong Pinjaman', potongT],
        ['Gaji Diterima', diterimaT],
      ];
      ringkasanT.forEach((item, idx) => {
        const isLast = idx === ringkasanT.length - 1;
        const r = ws.addRow([]);
        if (LBL_A < VAL_C) ws.mergeCells(r.number, LBL_A, r.number, LBL_B);
        const lc = ws.getCell(r.number, LBL_A);
        lc.value = item[0];
        lc.font = { bold: true }; lc.alignment = { horizontal: 'right', vertical: 'middle' };
        const vc = ws.getCell(r.number, VAL_C);
        vc.value = item[1]; vc.numFmt = '#,##0'; vc.font = { bold: isLast }; vc.alignment = { horizontal: 'right' };
        for (let c = LBL_A; c <= VAL_C; c++) {
          ws.getCell(r.number, c).border = BORDER_ALL;
          if (isLast) ws.getCell(r.number, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
        }
      });

      // ── Lebar kolom ──
      ws.getColumn(1).width = 6;
      ws.getColumn(2).width = 12;
      ws.getColumn(3).width = 11;
      ws.getColumn(4).width = 8;
      for (let c = 5; c <= 4 + nRoster; c++) ws.getColumn(c).width = 5;
      ws.getColumn(COL_TOTAL).width = 8;
      ws.getColumn(COL_UANG).width = 16;
    }

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="Slip_Gaji_TKBM_${new Date().toISOString().split('T')[0]}.xlsx"`);
    await wb.xlsx.write(res);
    res.end();
  } catch (err) {
    res.status(500).send('Gagal export: ' + err.message);
  }
});

// GET /upah-api/rekap-supir/export — Excel .xlsx rekap supir (1 sheet/supir bila id kosong)
router.get('/rekap-supir/export', async (req, res) => {
  try {
    const supirQuery = req.query.id_supir || '';
    const dateFilter = req.query.date_filter || 'month';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];
    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    let sql = `SELECT pp.id, pp.tanggal, pp.nama_pabrik, pp.nama_supir, pp.id_supir,
                      COALESCE(pp.netto_pabrik,0) AS netto_ram, COALESCE(pp.harga_per_kg,0) AS harga_per_kg,
                      COALESCE(pp.pinjaman,0) AS pinjaman, COALESCE(pp.biaya_es_jalan,0) AS biaya_es_jalan,
                      COALESCE(pp.status_bayar,'belum_bayar') AS status_bayar
               FROM pengiriman_pabrik pp WHERE ${dateSql}`;
    const params = [...dateParams];
    if (supirQuery) {
      if (!isNaN(parseInt(supirQuery))) {
        const so = await queryOne(`SELECT nama_supir FROM supir WHERE id = ?`, [parseInt(supirQuery)]);
        sql += ` AND (pp.id_supir = ? OR LOWER(pp.nama_supir) = LOWER(?))`;
        params.push(parseInt(supirQuery), so ? so.nama_supir : '');
      } else { sql += ` AND LOWER(pp.nama_supir) LIKE LOWER(?)`; params.push(`%${supirQuery}%`); }
    }
    sql += ` ORDER BY pp.nama_supir, pp.tanggal ASC, pp.id ASC`;
    const rows = await query(sql, params);
    if (!rows.length) return res.status(404).send('Tidak ada data pada periode ini');

    // Kelompokkan per supir
    const groups = {};
    rows.forEach(r => {
      const key = r.nama_supir || '-';
      (groups[key] = groups[key] || []).push(r);
    });

    const GREEN = 'FFC6E0B4';
    const HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const now = new Date();
    const tglCetak = `${HARI[now.getDay()]}, ${now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}`;
    const NCOL = 11; // jumlah kolom tabel supir

    // Peta hutang supir terkini (Manajemen Hutang) untuk blok ringkasan
    const debtRows = await query(`SELECT UPPER(nama_supir) AS nm, COALESCE(total_hutang,0) AS h FROM supir`);
    const debtMap = {};
    debtRows.forEach(d => { debtMap[d.nm] = parseFloat(d.h) || 0; });

    const wb = new ExcelJS.Workbook();
    for (const [supir, list] of Object.entries(groups)) {
      const safeName = supir.replace(/[\\\/\?\*\[\]:]/g, ' ').slice(0, 28) || 'Supir';
      const ws = wb.addWorksheet(safeName, { views: [{ showGridLines: false }] });

      // Pita hijau + judul + info (gaya sama dengan slip TKBM)
      for (let r = 1; r <= 5; r++) for (let c = 1; c <= NCOL; c++)
        ws.getCell(r, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
      ws.mergeCells(1, 1, 2, NCOL);
      const title = ws.getCell(1, 1);
      title.value = 'REKAP GAJI SUPIR';
      title.font = { bold: true, size: 16 };
      title.alignment = { horizontal: 'center', vertical: 'middle' };
      title.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
      ws.mergeCells(3, 1, 3, 4); ws.mergeCells(4, 1, 4, 4); ws.mergeCells(5, 1, 5, 4);
      ws.getCell(3, 1).value = tglCetak;             ws.getCell(3, 1).font = { bold: true, size: 10 };
      ws.getCell(4, 1).value = 'Nama : ' + supir;    ws.getCell(4, 1).font = { bold: true, size: 10 };
      ws.getCell(5, 1).value = 'Posisi : Supir';     ws.getCell(5, 1).font = { bold: true, size: 10 };
      ws.addRow([]); // baris 6 kosong sebagai jarak

      const headerRow = ws.addRow(['No', 'Tgl', 'PKS', 'Driver', 'Tonase', 'Harga/Kg', 'Hasil', 'Pinjaman', 'Es+Jalan', 'H.Bersih', 'Status']);
      headerRow.eachCell(c => {
        c.font = { bold: true };
        c.alignment = { horizontal: 'center', vertical: 'middle' };
        c.border = BORDER_ALL;
      });
      headerRow.height = 20;

      let tTon = 0, tHasil = 0, tPinj = 0, tEs = 0, tBersih = 0;
      list.forEach((r, i) => {
        const tonase = parseFloat(r.netto_ram || 0);
        const harga = parseFloat(r.harga_per_kg || 0);
        const hasil = tonase * harga;
        const pinjaman = parseFloat(r.pinjaman || 0);
        const es = parseFloat(r.biaya_es_jalan || 0);
        const bersih = hasil - pinjaman - es;
        tTon += tonase; tHasil += hasil; tPinj += pinjaman; tEs += es; tBersih += bersih;
        const row = ws.addRow([ i + 1, r.tanggal, r.nama_pabrik || '-', r.nama_supir || '-',
          tonase, harga, hasil, pinjaman, es, bersih, r.status_bayar === 'lunas' ? 'LUNAS' : 'BELUM BAYAR' ]);
        row.eachCell(c => c.border = BORDER_ALL);
        [5, 6, 7, 8, 9, 10].forEach(ci => row.getCell(ci).numFmt = '#,##0');
      });
      const totalRow = ws.addRow(['', '', '', 'JUMLAH', tTon, '', tHasil, tPinj, tEs, tBersih, '']);
      totalRow.eachCell({ includeEmpty: true }, (c, col) => {
        if (col <= NCOL) {
          c.border = BORDER_ALL; c.font = { bold: true };
          c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
        }
      });
      [5, 7, 8, 9, 10].forEach(ci => totalRow.getCell(ci).numFmt = '#,##0');

      // ── Blok ringkasan: Total Gaji, Pinjaman, Potong Pinjaman, Gaji Diterima ──
      const debtSupir = debtMap[(supir || '').toUpperCase()] || 0;
      const LBL_A = 7, LBL_B = 10, VAL_C = 11; // label digabung kolom 7-10, nilai kolom 11
      ws.addRow([]); // jarak

      // Judul blok
      const hRow = ws.addRow([]);
      ws.mergeCells(hRow.number, LBL_A, hRow.number, VAL_C);
      const hCell = ws.getCell(hRow.number, LBL_A);
      hCell.value = 'KETERANGAN GAJI';
      hCell.font = { bold: true };
      hCell.alignment = { horizontal: 'center', vertical: 'middle' };
      for (let c = LBL_A; c <= VAL_C; c++) {
        ws.getCell(hRow.number, c).border = BORDER_ALL;
        ws.getCell(hRow.number, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
      }

      const ringkasan = [
        ['Total Gaji (Hasil)', tHasil],
        ['Pinjaman', debtSupir],
        ['Potong Pinjaman', tPinj],
        ['Gaji Diterima', tBersih],
      ];
      ringkasan.forEach((item, idx) => {
        const isLast = idx === ringkasan.length - 1;
        const r = ws.addRow([]);
        ws.mergeCells(r.number, LBL_A, r.number, LBL_B);
        const lc = ws.getCell(r.number, LBL_A);
        lc.value = item[0]; lc.font = { bold: true }; lc.alignment = { horizontal: 'right', vertical: 'middle' };
        const vc = ws.getCell(r.number, VAL_C);
        vc.value = item[1]; vc.numFmt = '#,##0'; vc.font = { bold: isLast }; vc.alignment = { horizontal: 'right' };
        for (let c = LBL_A; c <= VAL_C; c++) {
          ws.getCell(r.number, c).border = BORDER_ALL;
          if (isLast) ws.getCell(r.number, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GREEN } };
        }
      });

      ws.getColumn(2).width = 14; ws.getColumn(3).width = 12; ws.getColumn(4).width = 16;
      [7, 8, 9, 10].forEach(ci => ws.getColumn(ci).width = 14);
      ws.getColumn(11).width = 16;
    }

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="Rekap_Gaji_Supir_${new Date().toISOString().split('T')[0]}.xlsx"`);
    await wb.xlsx.write(res);
    res.end();
  } catch (err) {
    res.status(500).send('Gagal export: ' + err.message);
  }
});

// ==========================================
// POTONG PINJAMAN DARI GAJI (terhubung Manajemen Hutang)
// Dipakai panel "Potongan Pinjaman" di Rekap Gaji Supir & Slip Gaji TKBM.
// type: 'supir' | 'tkbm'
// ==========================================

// Registry kecil: map type -> tabel master + kolom nama (utk kas keterangan)
const POTONG_PARTY = {
  supir: { table: 'supir',         nameCol: 'nama_supir',    label: 'SUPIR' },
  tkbm:  { table: 'karyawan_tkbm', nameCol: 'nama_karyawan', label: 'TKBM' },
};

// GET /upah-api/hutang-saat-ini/:type/:id — saldo hutang terkini seorang pihak
router.get('/hutang-saat-ini/:type/:id', async (req, res) => {
  try {
    const cfg = POTONG_PARTY[req.params.type];
    if (!cfg) return jsonResponse(res, false, 'Tipe tidak valid');
    const id = parseInt(req.params.id);
    if (!id) return jsonResponse(res, false, 'ID tidak valid');

    const row = await queryOne(
      `SELECT id, ${cfg.nameCol} AS nama, COALESCE(total_hutang,0) AS total_hutang FROM ${cfg.table} WHERE id = ?`,
      [id]
    );
    if (!row) return jsonResponse(res, false, 'Data tidak ditemukan');
    return jsonResponse(res, true, 'Hutang saat ini', row);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/potong-pinjaman/riwayat/:type/:id — riwayat potong pinjaman dari gaji
router.get('/potong-pinjaman/riwayat/:type/:id', async (req, res) => {
  try {
    const cfg = POTONG_PARTY[req.params.type];
    if (!cfg) return jsonResponse(res, false, 'Tipe tidak valid');
    const id = parseInt(req.params.id);
    if (!id) return jsonResponse(res, false, 'ID tidak valid');

    const data = await query(
      `SELECT h.id, h.tanggal, h.jumlah, h.keterangan, h.saldo_setelah, h.created_at, u.nama_lengkap AS nama_operator
       FROM hutang_ledger h
       LEFT JOIN users u ON h.operator_id = u.id
       WHERE h.party_type = ? AND h.party_id = ? AND h.jenis = 'bayar' AND h.sumber = 'gaji'
       ORDER BY h.id DESC LIMIT 20`,
      [req.params.type, id]
    );
    return jsonResponse(res, true, 'Riwayat potong pinjaman', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /upah-api/tkbm-potong-info — info potong pinjaman TKBM (dari Manajemen Hutang)
// Query: id_tkbm, date_filter, start_date, end_date
// Mengembalikan hutang saat ini + total pembayaran hutang TKBM selama periode slip.
router.get('/tkbm-potong-info', async (req, res) => {
  try {
    const idTkbm = parseInt(req.query.id_tkbm);
    if (!idTkbm) return jsonResponse(res, false, 'TKBM belum dipilih');

    const tkbm = await queryOne(`SELECT id, nama_karyawan AS nama, COALESCE(total_hutang,0) AS total_hutang FROM karyawan_tkbm WHERE id = ?`, [idTkbm]);
    if (!tkbm) return jsonResponse(res, false, 'TKBM tidak ditemukan');

    const dateFilter = req.query.date_filter || 'month';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];
    const { sql: dateSqlPP, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);
    const dateSql = dateSqlPP.replace(/pp\.tanggal/g, 'h.tanggal'); // buku besar pakai kolom tanggal sendiri

    const row = await queryOne(
      `SELECT COALESCE(SUM(h.jumlah),0) AS potong_periode
       FROM hutang_ledger h
       WHERE h.party_type = 'tkbm' AND h.party_id = ? AND h.jenis = 'bayar' AND ${dateSql}`,
      [idTkbm, ...dateParams]
    );

    return jsonResponse(res, true, 'Info potong TKBM', {
      id: tkbm.id, nama: tkbm.nama,
      total_hutang: parseFloat(tkbm.total_hutang) || 0,
      potong_periode: parseFloat(row ? row.potong_periode : 0) || 0
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/proses-potong-supir — sinkronkan total kolom Pinjaman periode ke Manajemen Hutang
// Body: { id_supir, date_filter, start_date, end_date }
router.post('/proses-potong-supir', requireRole('admin'), async (req, res) => {
  try {
    const idSupir = parseInt(req.body.id_supir);
    if (!idSupir) return jsonResponse(res, false, 'Pilih supir terlebih dahulu (bukan "Semua Supir")');

    const supir = await queryOne(`SELECT id, nama_supir AS nama, COALESCE(total_hutang,0) AS total_hutang FROM supir WHERE id = ?`, [idSupir]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    const dateFilter = req.body.date_filter || 'month';
    const startDate  = req.body.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.body.end_date   || new Date().toISOString().split('T')[0];
    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    // Trip periode ini milik supir, punya pinjaman, dan belum diproses
    const trips = await query(
      `SELECT pp.id, COALESCE(pp.pinjaman,0) AS pinjaman
       FROM pengiriman_pabrik pp
       WHERE ${dateSql}
         AND (pp.id_supir = ? OR LOWER(pp.nama_supir) = LOWER(?))
         AND COALESCE(pp.pinjaman,0) > 0
         AND COALESCE(pp.pinjaman_diproses,0) = 0`,
      [...dateParams, idSupir, supir.nama]
    );

    const total = trips.reduce((s, t) => s + (parseFloat(t.pinjaman) || 0), 0);
    if (total <= 0) return jsonResponse(res, false, 'Tidak ada potongan Pinjaman baru untuk diproses pada periode ini');

    const debt = parseFloat(supir.total_hutang) || 0;
    if (debt <= 0) return jsonResponse(res, false, 'Supir ini tidak memiliki hutang di Manajemen Hutang');
    if (total > debt) {
      return jsonResponse(res, false, `Total potong Pinjaman (${formatRupiah(total)}) melebihi sisa hutang (${formatRupiah(debt)}). Sesuaikan kolom Pinjaman atau tambah hutang di Manajemen Hutang.`);
    }

    const periodeText = ` Periode ${startDate} s/d ${endDate}`;

    const tx = beginTransaction();
    try {
      // 1. Kurangi hutang di buku besar terpadu
      const saldo = catatHutangTx(tx, {
        type: 'supir', partyId: idSupir, jenis: 'bayar', jumlah: total,
        keterangan: `Potongan Pinjaman gaji supir${periodeText}`,
        sumber: 'gaji', operatorId: req.session.user_id
      });

      // Ambil id ledger yang baru saja dibuat (untuk penanda trip)
      const [ledRows] = tx.execute(`SELECT id FROM hutang_ledger WHERE party_type='supir' AND party_id=? ORDER BY id DESC LIMIT 1`, [idSupir]);
      const ledgerId = ledRows.length ? ledRows[0].id : null;

      // 2. Catat kas keluar
      const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
      const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
      tx.execute(
        `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
         VALUES (date('now', 'localtime'), 'keluar', ?, ?, ?, ?)`,
        [total, `POTONG PINJAMAN DARI GAJI SUPIR - ${(supir.nama || '').toUpperCase()}${periodeText}`, saldoSebelum - total, req.session.user_id]
      );

      // 3. Tandai trip sudah diproses + tautkan ke ledger
      const ids = trips.map(t => t.id).join(',');
      tx.execute(`UPDATE pengiriman_pabrik SET pinjaman_diproses = 1, id_potong_ledger = ? WHERE id IN (${ids})`, [ledgerId]);

      tx.commit();
      return jsonResponse(res, true, `Potong pinjaman ${formatRupiah(total)} berhasil disinkron. Sisa hutang: ${formatRupiah(saldo)}`, { saldo, total });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /upah-api/potong-pinjaman/batal — batalkan sebuah potongan (kembalikan hutang + kas masuk)
// Body: { ledger_id }
router.post('/potong-pinjaman/batal', requireRole('admin'), async (req, res) => {
  try {
    const ledgerId = parseInt(req.body.ledger_id);
    if (!ledgerId) return jsonResponse(res, false, 'ID riwayat tidak valid');

    const entry = await queryOne(
      `SELECT * FROM hutang_ledger WHERE id = ? AND jenis = 'bayar' AND sumber = 'gaji'`,
      [ledgerId]
    );
    if (!entry) return jsonResponse(res, false, 'Data potongan tidak ditemukan / sudah dibatalkan');

    const cfg = POTONG_PARTY[entry.party_type];
    if (!cfg) return jsonResponse(res, false, 'Tipe tidak valid');

    const party = await queryOne(`SELECT ${cfg.nameCol} AS nama FROM ${cfg.table} WHERE id = ?`, [entry.party_id]);

    const tx = beginTransaction();
    try {
      // 1. Kembalikan hutang (tambah)
      catatHutangTx(tx, {
        type: entry.party_type, partyId: entry.party_id, jenis: 'tambah', jumlah: entry.jumlah,
        keterangan: `Pembatalan potong pinjaman gaji (ref #${ledgerId})`,
        sumber: 'gaji', operatorId: req.session.user_id
      });

      // 2. Reset penanda trip supir yang tertaut ke potongan ini
      if (entry.party_type === 'supir') {
        tx.execute(`UPDATE pengiriman_pabrik SET pinjaman_diproses = 0, id_potong_ledger = NULL WHERE id_potong_ledger = ?`, [ledgerId]);
      }

      // 3. Hapus baris ledger potongan asli agar tidak muncul lagi di riwayat
      tx.execute(`DELETE FROM hutang_ledger WHERE id = ?`, [ledgerId]);

      // 3. Catat kas masuk (mengembalikan kas keluar sebelumnya)
      const [lastKas] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
      const saldoSebelum = lastKas.length > 0 ? parseFloat(lastKas[0].saldo_setelah) : 0;
      const saldoSesudah = saldoSebelum + parseFloat(entry.jumlah);
      tx.execute(
        `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
         VALUES (date('now', 'localtime'), 'masuk', ?, ?, ?, ?)`,
        [entry.jumlah, `PEMBATALAN POTONG PINJAMAN GAJI ${cfg.label} - ${(party ? party.nama : '').toUpperCase()}`, saldoSesudah, req.session.user_id]
      );

      tx.commit();
      return jsonResponse(res, true, 'Potong pinjaman berhasil dibatalkan');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

module.exports = router;
