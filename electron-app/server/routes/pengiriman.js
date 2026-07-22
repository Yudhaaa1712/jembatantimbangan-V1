/**
 * Pengiriman Pabrik Routes
 * Replaces: modules/pengiriman_pabrik/index.php
 * Features: CRUD pengiriman, update berat pabrik (susut), cetak surat jalan
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { isLoggedIn } = require('../middleware/auth');

router.use(isLoggedIn);

// ─── Generate No Surat Jalan ─────────────────────────────────────────────────
async function generateNoSuratJalan() {
  const today = new Date();
  const datePrefix = [
    String(today.getFullYear()).slice(-2),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0')
  ].join('');

  const todayStr = today.toISOString().split('T')[0];
  const pattern = `SJ-${datePrefix}%`;

  const row = await queryOne(
    `SELECT COALESCE(MAX(CAST(SUBSTR(no_surat_jalan, -3) AS INTEGER)), 0) as max_num
     FROM pengiriman_pabrik WHERE tanggal = ? AND no_surat_jalan LIKE ?`,
    [todayStr, pattern]
  );

  const nextNum = (row?.max_num || 0) + 1;
  return `SJ-${datePrefix}-${String(nextNum).padStart(3, '0')}`;
}

// ─── GET /pengiriman/list — Daftar pengiriman ────────────────────────────────
router.get('/list', async (req, res) => {
  try {
    const rows = await query(
      `SELECT pp.*, u.nama_lengkap as operator_nama
       FROM pengiriman_pabrik pp
       LEFT JOIN users u ON pp.operator_id = u.id
       WHERE pp.tanggal >= date('now', 'localtime', '-30 days')
       ORDER BY pp.created_at DESC`
    );
    return jsonResponse(res, true, 'Data pengiriman', rows);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── GET /pengiriman/detail/:id — Detail satu pengiriman ─────────────────────
router.get('/detail/:id', async (req, res) => {
  try {
    const row = await queryOne(
      `SELECT pp.*, u.nama_lengkap as operator_nama
       FROM pengiriman_pabrik pp
       LEFT JOIN users u ON pp.operator_id = u.id
       WHERE pp.id = ?`, [req.params.id]
    );
    if (!row) return jsonResponse(res, false, 'Data tidak ditemukan');

    // Get company settings
    const settings = await query(`SELECT setting_key, setting_value FROM settings`);
    const company = {};
    settings.forEach(s => company[s.setting_key] = s.setting_value);
    
    row.company = company; // Sisipkan company ke data row

    return jsonResponse(res, true, 'Detail pengiriman', row);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── GET /pengiriman/summary — Rekap sisa buah ───────────────────────────────
router.get('/summary', async (req, res) => {
  try {
    // Total Masuk dari transaksi timbangan (yang sudah selesai) = Bruto - Tara (Netto 1)
    const tbsMasuk = await queryOne(`SELECT COALESCE(SUM(berat_timbangan1 - berat_timbangan2), 0) as total FROM transaksi_timbangan WHERE LOWER(jenis_material) = 'tbs' AND status = 'selesai'`);
    const brondolanMasuk = await queryOne(`SELECT COALESCE(SUM(berat_timbangan1 - berat_timbangan2), 0) as total FROM transaksi_timbangan WHERE LOWER(jenis_material) = 'brondolan' AND status = 'selesai'`);
    
    // Total Keluar (Pengiriman Pabrik per jenis)
    const tbsKeluar = await queryOne(`SELECT COALESCE(SUM(netto_ram), 0) as total FROM pengiriman_pabrik WHERE LOWER(jenis_material) = 'tbs'`);
    const brondolanKeluar = await queryOne(`SELECT COALESCE(SUM(netto_ram), 0) as total FROM pengiriman_pabrik WHERE LOWER(jenis_material) = 'brondolan'`);

    const totalTbsMasuk = parseFloat(tbsMasuk?.total || 0);
    const totalBrondolanMasuk = parseFloat(brondolanMasuk?.total || 0);
    const totalTbsKeluar = parseFloat(tbsKeluar?.total || 0);
    const totalBrondolanKeluar = parseFloat(brondolanKeluar?.total || 0);
    
    const sisaTbs = totalTbsMasuk - totalTbsKeluar;
    const sisaBrondolan = totalBrondolanMasuk - totalBrondolanKeluar;

    return jsonResponse(res, true, 'Summary', {
      tbs_masuk: totalTbsMasuk,
      brondolan_masuk: totalBrondolanMasuk,
      sisa_tbs: sisaTbs,
      sisa_brondolan: sisaBrondolan,
      total_masuk: totalTbsMasuk + totalBrondolanMasuk,
      total_keluar: totalTbsKeluar + totalBrondolanKeluar,
      sisa_buah: sisaTbs + sisaBrondolan
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── POST /pengiriman/timbang1 — Timbang Pertama (Kendaraan Masuk) ───────────
router.post('/timbang1', async (req, res) => {
  try {
    const noPolisi = cleanInput(req.body.no_polisi) || '-';
    const idSupir = parseInt(req.body.id_supir) || null;
    const namaSupir = cleanInput(req.body.nama_supir) || '-';
    const namaPabrik = cleanInput(req.body.nama_pabrik) || '-';
    const berat1 = parseFloat(req.body.berat) || 0;
    const keterangan = cleanInput(req.body.keterangan || '');
    const jenis_material = req.body.jenis_material || 'tbs';
    const tkbmWorkers = req.body.tkbm_workers || []; // array of id_tkbm

    const hargaKg = parseFloat(req.body.harga_per_kg) || 0;
    const pinjaman = parseFloat(req.body.pinjaman) || 0;
    const biayaEsJalan = parseFloat(req.body.biaya_es_jalan) || 0;

    if (berat1 <= 0) {
      return jsonResponse(res, false, 'Berat Timbangan 1 harus lebih dari 0');
    }

    const noSJ = await generateNoSuratJalan();

    const tx = beginTransaction();
    try {
      const result = tx.execute(
        `INSERT INTO pengiriman_pabrik 
         (no_surat_jalan, tanggal, no_polisi, id_supir, nama_supir, nama_pabrik, 
          berat_timbangan1, waktu_timbangan1, keterangan, jenis_material, harga_per_kg, pinjaman, biaya_es_jalan, status, operator_id, created_at)
         VALUES (?, CURDATE(), ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'timbang_1', ?, NOW())`,
        [noSJ, noPolisi, idSupir, namaSupir, namaPabrik, berat1, keterangan, jenis_material, hargaKg, pinjaman, biayaEsJalan, req.session.user_id]
      );
      
      const insertId = result[0].insertId;

      if (Array.isArray(tkbmWorkers) && tkbmWorkers.length > 0) {
        for (const idTkbm of tkbmWorkers) {
          tx.execute(
            `INSERT INTO pengiriman_tkbm (id_pengiriman, id_tkbm) VALUES (?, ?)`,
            [insertId, parseInt(idTkbm)]
          );
        }
      }

      tx.commit();
      console.log(`[Pengiriman] Timbang 1: ${noSJ} → ${namaPabrik}, Berat 1: ${berat1} kg`);

      return jsonResponse(res, true, 'Timbang 1 berhasil disimpan', {
        id: insertId,
        no_surat_jalan: noSJ
      });
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, 'Gagal menyimpan: ' + err.message);
  }
});

// ─── GET /pengiriman/pending — Ambil data yang menunggu Timbang 2 ────────────
router.get('/pending', async (req, res) => {
  try {
    const rows = await query(
      `SELECT id, no_surat_jalan, no_polisi, nama_supir, nama_pabrik, jenis_material, berat_timbangan1, waktu_timbangan1 
       FROM pengiriman_pabrik 
       WHERE status = 'timbang_1' 
       ORDER BY waktu_timbangan1 DESC`
    );
    return jsonResponse(res, true, 'Data pending', rows);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── POST /pengiriman/timbang2 — Timbang Kedua (Kendaraan Keluar) ────────────
router.post('/timbang2', async (req, res) => {
  try {
    const id = parseInt(req.body.id);
    const berat2 = parseFloat(req.body.berat) || 0;

    if (!id || berat2 <= 0) {
      return jsonResponse(res, false, 'Pilih surat jalan dan ambil berat yang valid');
    }

    const row = await queryOne(`SELECT berat_timbangan1 FROM pengiriman_pabrik WHERE id = ? AND status = 'timbang_1'`, [id]);
    if (!row) return jsonResponse(res, false, 'Data tidak valid atau sudah diproses');

    const berat1 = parseFloat(row.berat_timbangan1);
    const bruto = Math.max(berat1, berat2);
    const tara = Math.min(berat1, berat2);
    const netto_ram = bruto - tara;
    
    await query(
      `UPDATE pengiriman_pabrik SET 
       berat_timbangan2 = ?, waktu_timbangan2 = NOW(),
       berat_bruto = ?, berat_tara = ?, netto_ram = ?,
       status = 'timbang_2', updated_at = NOW()
       WHERE id = ?`,
      [berat2, bruto, tara, netto_ram, id]
    );

    console.log(`[Pengiriman] Timbang 2: ID ${id}, Berat 2: ${berat2} kg`);

    return jsonResponse(res, true, 'Timbang 2 selesai, Netto berhasil dihitung');
  } catch (err) {
    return jsonResponse(res, false, 'Gagal update: ' + err.message);
  }
});

// ─── POST /pengiriman/update-pabrik — Input berat pabrik (hitung susut) ──────
router.post('/update-pabrik', async (req, res) => {
  try {
    const id = parseInt(req.body.id);
    const nettoPabrik = parseFloat(req.body.netto_pabrik);

    if (!id || isNaN(nettoPabrik) || nettoPabrik < 0) {
      return jsonResponse(res, false, 'Data tidak valid');
    }

    const row = await queryOne(`SELECT netto_ram, status FROM pengiriman_pabrik WHERE id = ?`, [id]);
    if (!row) return jsonResponse(res, false, 'Data tidak ditemukan');
    if (row.status === 'selesai') return jsonResponse(res, false, 'Data sudah selesai');

    const nettoRam = parseFloat(row.netto_ram);
    const susut = nettoRam - nettoPabrik;
    const persenSusut = nettoRam > 0 ? ((susut / nettoRam) * 100) : 0;

    await query(
      `UPDATE pengiriman_pabrik SET 
       netto_pabrik = ?, susut = ?, persen_susut = ?, 
       status = 'selesai', updated_at = NOW()
       WHERE id = ?`,
      [nettoPabrik, susut, persenSusut.toFixed(2), id]
    );

    console.log(`[Pengiriman] Updated pabrik: ID ${id}, Susut: ${susut} kg (${persenSusut.toFixed(2)}%)`);

    return jsonResponse(res, true, 'Berat pabrik berhasil diupdate', {
      susut, persen_susut: persenSusut.toFixed(2)
    });
  } catch (err) {
    return jsonResponse(res, false, 'Gagal update: ' + err.message);
  }
});

// ─── POST /pengiriman/delete — Hapus pengiriman ──────────────────────────────
router.post('/delete', async (req, res) => {
  try {
    const id = parseInt(req.body.id);
    if (!id) return jsonResponse(res, false, 'ID tidak valid');

    await query(`DELETE FROM pengiriman_pabrik WHERE id = ?`, [id]);
    return jsonResponse(res, true, 'Data berhasil dihapus');
  } catch (err) {
    return jsonResponse(res, false, 'Gagal menghapus: ' + err.message);
  }
});

// ─── GET /pengiriman/export-excel — Export to Excel ────────────────────────
router.get('/export-excel', async (req, res) => {
  try {
    const rows = await query(
      `SELECT pp.*, u.nama_lengkap as operator_nama
       FROM pengiriman_pabrik pp
       LEFT JOIN users u ON pp.operator_id = u.id
       ORDER BY pp.tanggal DESC, pp.created_at DESC`
    );

    const settingRow = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'company_name'`);
    const companyName = settingRow ? settingRow.setting_value : 'Laporan Pengiriman Pabrik';

    let html = `
      <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
      <head>
        <meta charset="UTF-8">
        <style>
          .number-format { mso-number-format:"\\#\\,\\#\\#0"; text-align: right; }
          .text-left { text-align: left; }
          .text-center { text-align: center; }
        </style>
      </head>
      <body>
        <h3>${companyName} - Data Pengiriman Pabrik</h3>
        <table border="1">
          <thead>
            <tr style="background-color: #4CAF50; color: white;">
              <th>No. Surat Jalan</th>
              <th>Tanggal</th>
              <th>No. Polisi</th>
              <th>Nama Supir</th>
              <th>Pabrik Tujuan</th>
              <th>Berat Bruto</th>
              <th>Berat Tara</th>
              <th>Netto RAM</th>
              <th>Netto Pabrik</th>
              <th>Susut (Kg)</th>
              <th>Persen Susut (%)</th>
              <th>Status</th>
              <th>Keterangan</th>
              <th>Operator</th>
            </tr>
          </thead>
          <tbody>
    `;

    rows.forEach(d => {
      const tgl = d.tanggal ? new Date(d.tanggal).toLocaleDateString('id-ID') : '-';
      html += `
        <tr>
          <td>${d.no_surat_jalan}</td>
          <td>${tgl}</td>
          <td>${d.no_polisi}</td>
          <td>${d.nama_supir}</td>
          <td>${d.nama_pabrik}</td>
          <td class="number-format">${d.berat_bruto}</td>
          <td class="number-format">${d.berat_tara}</td>
          <td class="number-format">${d.netto_ram}</td>
          <td class="number-format">${d.netto_pabrik !== null ? d.netto_pabrik : ''}</td>
          <td class="number-format">${d.susut !== null ? d.susut : ''}</td>
          <td>${d.persen_susut !== null ? d.persen_susut + '%' : ''}</td>
          <td>${d.status}</td>
          <td>${d.keterangan || ''}</td>
          <td>${d.operator_nama || ''}</td>
        </tr>
      `;
    });

    html += `</tbody></table></body></html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel');
    res.setHeader('Content-Disposition', `attachment; filename=Pengiriman_Pabrik_${new Date().toISOString().split('T')[0]}.xls`);
    res.send(html);
  } catch (err) {
    res.status(500).send('Gagal export: ' + err.message);
  }
});

module.exports = router;
