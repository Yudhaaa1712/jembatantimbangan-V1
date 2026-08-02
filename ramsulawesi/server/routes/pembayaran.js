/**
 * Pembayaran Supplier Routes
 * Weighbridge - Arroyan Jv Teknik
 *
 * Memisahkan "timbang keluar" dari "uang keluar".
 *
 * Alur:
 *   1. Timbang keluar menyimpan tiket dengan status_bayar = 'belum_bayar'
 *      (kecuali operator memilih bayar tunai/transfer saat itu juga).
 *   2. Tiket menumpuk di antrian — satu supplier bisa punya 4-5 tiket dengan
 *      mobil berbeda dalam sehari.
 *   3. Saat uang benar-benar diserahkan, beberapa tiket dirapel jadi SATU baris
 *      `pembayaran` + SATU baris kas keluar, lalu semua tiket menjadi 'lunas'.
 *
 * Prinsip: satu pembayaran = satu mutasi kas. Tidak ada kas yang tercatat
 * tanpa baris pembayaran, dan sebaliknya.
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, beginTransaction, jsonResponse, cleanInput } = require('../config/database');
const { isLoggedIn, requireRole, getCurrentUser } = require('../middleware/auth');
const { recalculateKasBalances } = require('../helpers/kasHelper');

router.use(isLoggedIn);

const METODE_VALID = ['tunai', 'transfer'];

// Tiket yang masuk antrian pembayaran: pembelian yang sudah selesai ditimbang,
// belum dibayar, dan nilainya lebih dari nol. Penjualan ('jual') tidak ikut —
// pada penjualan uang justru masuk dan sudah tercatat saat timbang keluar.
const WHERE_BELUM_BAYAR = `
  tt.status = 'selesai'
  AND COALESCE(tt.mode_timbangan,'beli') <> 'jual'
  AND COALESCE(tt.status_bayar,'belum_bayar') = 'belum_bayar'
  AND COALESCE(tt.total_akhir,0) > 0
`;

/**
 * Nomor pembayaran: BYR-DDMMYY-XXX, urut per hari.
 * Dipanggil di dalam transaksi supaya nomornya tidak bentrok.
 */
function generateNoPembayaran(tx) {
  const now = new Date();
  const dd = String(now.getDate()).padStart(2, '0');
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const yy = String(now.getFullYear()).slice(-2);
  const datePrefix = `${dd}${mm}${yy}`;
  const [rows] = tx.execute(
    `SELECT COALESCE(MAX(CAST(SUBSTR(no_pembayaran, -3) AS INTEGER)), 0) AS max_num
     FROM pembayaran WHERE no_pembayaran LIKE ?`,
    [`BYR-${datePrefix}%`]
  );
  const next = parseInt(rows[0]?.max_num || 0) + 1;
  return `BYR-${datePrefix}-${String(next).padStart(3, '0')}`;
}

// ─── GET Rekap utang per supplier ─────────────────────────────────────────────
// Dipakai halaman Pembayaran (daftar supplier) dan panel Keuangan.
router.get('/rekap-supplier', async (req, res) => {
  try {
    const rows = await query(`
      SELECT
        COALESCE(tt.id_supplier, 0)                AS id_supplier,
        COALESCE(s.nama_supplier, '(Tanpa supplier)') AS nama_supplier,
        COUNT(*)                                    AS jumlah_tiket,
        SUM(tt.total_akhir)                         AS total,
        MIN(tt.tanggal)                             AS tiket_terlama,
        MAX(tt.tanggal)                             AS tiket_terbaru
      FROM transaksi_timbangan tt
      LEFT JOIN supplier s ON tt.id_supplier = s.id
      WHERE ${WHERE_BELUM_BAYAR}
      GROUP BY COALESCE(tt.id_supplier, 0), COALESCE(s.nama_supplier, '(Tanpa supplier)')
      ORDER BY total DESC
    `);

    const totalSemua = rows.reduce((a, r) => a + parseFloat(r.total || 0), 0);
    const totalTiket = rows.reduce((a, r) => a + parseInt(r.jumlah_tiket || 0), 0);

    return jsonResponse(res, true, 'Rekap utang supplier', {
      supplier: rows,
      total_semua: totalSemua,
      total_tiket: totalTiket
    });
  } catch (err) {
    console.error('[Pembayaran] rekap-supplier error:', err.message);
    return jsonResponse(res, false, 'Gagal mengambil rekap utang supplier');
  }
});

// ─── GET Daftar tiket belum bayar ─────────────────────────────────────────────
router.get('/tiket-belum-bayar', async (req, res) => {
  try {
    const idSupplier = req.query.id_supplier !== undefined && req.query.id_supplier !== ''
      ? parseInt(req.query.id_supplier)
      : null;

    const params = [];
    let filter = '';
    if (idSupplier !== null && !isNaN(idSupplier)) {
      if (idSupplier === 0) {
        filter = ' AND tt.id_supplier IS NULL';
      } else {
        filter = ' AND tt.id_supplier = ?';
        params.push(idSupplier);
      }
    }
    if (req.query.start_date && req.query.end_date) {
      filter += ' AND tt.tanggal BETWEEN ? AND ?';
      params.push(req.query.start_date, req.query.end_date);
    }

    const rows = await query(`
      SELECT tt.id, tt.no_tiket, tt.tanggal, tt.no_polisi, tt.nama_supir,
             tt.jenis_material, tt.berat_netto, tt.netto_akhir, tt.harga_per_kg,
             tt.total_harga, tt.total_akhir, tt.waktu_keluar,
             COALESCE(tt.id_supplier, 0) AS id_supplier,
             COALESCE(s.nama_supplier, '(Tanpa supplier)') AS nama_supplier
      FROM transaksi_timbangan tt
      LEFT JOIN supplier s ON tt.id_supplier = s.id
      WHERE ${WHERE_BELUM_BAYAR} ${filter}
      ORDER BY tt.tanggal ASC, tt.id ASC
    `, params);

    const total = rows.reduce((a, r) => a + parseFloat(r.total_akhir || 0), 0);
    return jsonResponse(res, true, 'Tiket belum dibayar', { tiket: rows, total, jumlah: rows.length });
  } catch (err) {
    console.error('[Pembayaran] tiket-belum-bayar error:', err.message);
    return jsonResponse(res, false, 'Gagal mengambil daftar tiket');
  }
});

// ─── POST Simpan pembayaran (rapel beberapa tiket → 1 kas keluar) ─────────────
router.post('/simpan', requireRole('admin', 'operator'), async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const metode = cleanInput(req.body.metode || '').toLowerCase();
    const keterangan = cleanInput(req.body.keterangan || '');
    const tanggal = cleanInput(req.body.tanggal || '') || null;

    if (!METODE_VALID.includes(metode)) {
      return jsonResponse(res, false, 'Metode pembayaran harus tunai atau transfer');
    }

    // tiket_ids bisa datang sebagai array atau string "1,2,3"
    let ids = req.body.tiket_ids;
    if (typeof ids === 'string') ids = ids.split(',');
    if (!Array.isArray(ids)) ids = [];
    ids = [...new Set(ids.map(v => parseInt(v)).filter(v => !isNaN(v) && v > 0))];

    if (ids.length === 0) return jsonResponse(res, false, 'Pilih minimal satu tiket untuk dibayar');

    const placeholders = ids.map(() => '?').join(',');
    const tiket = await query(`
      SELECT tt.id, tt.no_tiket, tt.total_akhir, tt.id_supplier, tt.status, tt.status_bayar,
             tt.mode_timbangan, s.nama_supplier
      FROM transaksi_timbangan tt
      LEFT JOIN supplier s ON tt.id_supplier = s.id
      WHERE tt.id IN (${placeholders})
    `, ids);

    if (tiket.length !== ids.length) {
      return jsonResponse(res, false, 'Sebagian tiket tidak ditemukan. Muat ulang halaman.');
    }

    // Validasi ulang di server — daftar di layar bisa saja sudah basi kalau ada
    // operator lain yang membayar tiket yang sama dari komputer berbeda.
    const bermasalah = tiket.filter(t =>
      t.status !== 'selesai' ||
      (t.mode_timbangan || 'beli') === 'jual' ||
      (t.status_bayar || 'belum_bayar') !== 'belum_bayar' ||
      parseFloat(t.total_akhir || 0) <= 0
    );
    if (bermasalah.length > 0) {
      return jsonResponse(res, false,
        `Tiket berikut tidak bisa dibayar (sudah lunas / dibatalkan): ${bermasalah.map(t => t.no_tiket).join(', ')}`);
    }

    // Satu pembayaran hanya untuk satu supplier — supaya rekap utang per supplier
    // tetap terbaca dan nota pembayarannya jelas milik siapa.
    const supplierSet = new Set(tiket.map(t => t.id_supplier || 0));
    if (supplierSet.size > 1) {
      return jsonResponse(res, false, 'Semua tiket dalam satu pembayaran harus milik supplier yang sama');
    }
    const idSupplier = tiket[0].id_supplier || null;
    const namaSupplier = tiket[0].nama_supplier || '(Tanpa supplier)';

    const total = tiket.reduce((a, t) => a + parseFloat(t.total_akhir || 0), 0);
    if (total <= 0) return jsonResponse(res, false, 'Total pembayaran harus lebih dari 0');

    const lastKas = await queryOne(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
    const saldoSebelum = lastKas ? parseFloat(lastKas.saldo_setelah) : 0;
    const saldoSesudah = saldoSebelum - total;

    let noPembayaran = null;
    let idPembayaran = null;

    const tx = beginTransaction();
    try {
      noPembayaran = generateNoPembayaran(tx);
      const tglSql = tanggal ? '?' : `date('now','localtime')`;
      const tglParams = tanggal ? [tanggal] : [];

      const [insBayar] = tx.execute(
        `INSERT INTO pembayaran
           (no_pembayaran, tanggal, id_supplier, nama_supplier, metode, total, jumlah_tiket,
            keterangan, operator_id, status, created_at)
         VALUES (?, ${tglSql}, ?, ?, ?, ?, ?, ?, ?, 'aktif', datetime('now','localtime'))`,
        [noPembayaran, ...tglParams, idSupplier, namaSupplier, metode, total, tiket.length,
         keterangan || null, user.id]
      );
      idPembayaran = insBayar.insertId;

      const kasKeterangan =
        `PEMBAYARAN ${namaSupplier.toUpperCase()} - ${tiket.length} TIKET (${metode.toUpperCase()})`;

      // no_tiket sengaja dikosongkan: baris ini mewakili banyak tiket sekaligus.
      // Penandanya id_pembayaran — dipakai Buku Kas untuk mengunci penghapusan.
      const [insKas] = tx.execute(
        `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, no_tiket, id_pembayaran, saldo_setelah, operator_id, created_at)
         VALUES (${tglSql}, 'keluar', ?, ?, NULL, ?, ?, ?, datetime('now','localtime'))`,
        [...tglParams, total, kasKeterangan, idPembayaran, saldoSesudah, user.id]
      );

      tx.execute(`UPDATE pembayaran SET id_kas = ? WHERE id = ?`, [insKas.insertId, idPembayaran]);

      for (const t of tiket) {
        tx.execute(
          `INSERT INTO pembayaran_detail (id_pembayaran, id_transaksi, no_tiket, jumlah, created_at)
           VALUES (?, ?, ?, ?, datetime('now','localtime'))`,
          [idPembayaran, t.id, t.no_tiket, t.total_akhir]
        );
        // Kunci status_bayar hanya bila masih 'belum_bayar' — kalau ada proses lain
        // yang menyerobot lebih dulu, affectedRows = 0 dan kita batalkan semuanya.
        const [upd] = tx.execute(
          `UPDATE transaksi_timbangan
           SET status_bayar = 'lunas', metode_bayar = ?, id_pembayaran = ?,
               tanggal_bayar = ${tglSql}, updated_at = datetime('now','localtime')
           WHERE id = ? AND COALESCE(status_bayar,'belum_bayar') = 'belum_bayar'`,
          [metode, idPembayaran, ...tglParams, t.id]
        );
        if (upd.affectedRows === 0) {
          throw new Error(`Tiket ${t.no_tiket} baru saja dibayar oleh proses lain. Muat ulang halaman.`);
        }
      }

      tx.commit();
    } catch (txErr) {
      tx.rollback();
      return jsonResponse(res, false, txErr.message);
    }

    // Saldo berjalan di seluruh baris kas dihitung ulang supaya kolom saldo_setelah
    // tetap benar walaupun pembayaran dicatat mundur (backdate).
    await recalculateKasBalances();
    syncPembayaranToGoogleSheet(noPembayaran, namaSupplier, total, metode, user.nama_lengkap, saldoSesudah);

    console.log(`[Pembayaran] ${noPembayaran}: ${namaSupplier} ${tiket.length} tiket Rp ${total.toLocaleString('id-ID')} (${metode})`);
    return jsonResponse(res, true,
      `Pembayaran ke ${namaSupplier} tersimpan. ${tiket.length} tiket ditandai LUNAS.`,
      { id: idPembayaran, no_pembayaran: noPembayaran, total, jumlah_tiket: tiket.length, metode }
    );
  } catch (err) {
    console.error('[Pembayaran] simpan error:', err.message);
    return jsonResponse(res, false, err.message);
  }
});

// ─── GET Riwayat pembayaran ───────────────────────────────────────────────────
router.get('/riwayat', async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 50, 500);
    const params = [];
    let filter = '';
    if (req.query.start_date && req.query.end_date) {
      filter = ' WHERE p.tanggal BETWEEN ? AND ?';
      params.push(req.query.start_date, req.query.end_date);
    }
    const rows = await query(`
      SELECT p.*, u.nama_lengkap AS operator_nama
      FROM pembayaran p
      LEFT JOIN users u ON p.operator_id = u.id
      ${filter}
      ORDER BY p.id DESC
      LIMIT ${limit}
    `, params);
    return jsonResponse(res, true, 'Riwayat pembayaran', rows);
  } catch (err) {
    console.error('[Pembayaran] riwayat error:', err.message);
    return jsonResponse(res, false, 'Gagal mengambil riwayat pembayaran');
  }
});

// ─── GET Detail satu pembayaran ───────────────────────────────────────────────
router.get('/detail/:id', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const header = await queryOne(`
      SELECT p.*, u.nama_lengkap AS operator_nama
      FROM pembayaran p LEFT JOIN users u ON p.operator_id = u.id
      WHERE p.id = ? LIMIT 1
    `, [id]);
    if (!header) return jsonResponse(res, false, 'Pembayaran tidak ditemukan');

    const detail = await query(`
      SELECT pd.*, tt.tanggal, tt.no_polisi, tt.nama_supir, tt.jenis_material,
             tt.netto_akhir, tt.harga_per_kg
      FROM pembayaran_detail pd
      LEFT JOIN transaksi_timbangan tt ON pd.id_transaksi = tt.id
      WHERE pd.id_pembayaran = ?
      ORDER BY pd.id ASC
    `, [id]);

    return jsonResponse(res, true, 'Detail pembayaran', { header, detail });
  } catch (err) {
    console.error('[Pembayaran] detail error:', err.message);
    return jsonResponse(res, false, 'Gagal mengambil detail pembayaran');
  }
});

// ─── POST Batalkan pembayaran ─────────────────────────────────────────────────
// Kebalikan penuh dari /simpan: baris kas dihapus, tiket kembali 'belum_bayar'.
router.post('/batal', requireRole('admin'), async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const id = parseInt(req.body.id);
    const alasan = cleanInput(req.body.alasan || '') || 'Dibatalkan oleh admin';

    const header = await queryOne(`SELECT * FROM pembayaran WHERE id = ? LIMIT 1`, [id]);
    if (!header) return jsonResponse(res, false, 'Pembayaran tidak ditemukan');
    if (header.status === 'dibatalkan') return jsonResponse(res, false, 'Pembayaran sudah dibatalkan');

    const tx = beginTransaction();
    try {
      tx.execute(
        `UPDATE transaksi_timbangan
         SET status_bayar = 'belum_bayar', metode_bayar = NULL, tanggal_bayar = NULL,
             id_pembayaran = NULL, updated_at = datetime('now','localtime')
         WHERE id_pembayaran = ?`,
        [id]
      );
      if (header.id_kas) tx.execute(`DELETE FROM kas WHERE id = ?`, [header.id_kas]);
      tx.execute(
        `UPDATE pembayaran
         SET status = 'dibatalkan', cancelled_at = datetime('now','localtime'),
             cancelled_by = ?, cancel_reason = ?
         WHERE id = ?`,
        [user.id, alasan, id]
      );
      tx.commit();
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }

    await recalculateKasBalances();
    console.log(`[Pembayaran] ${header.no_pembayaran} dibatalkan oleh ${user.username}`);
    return jsonResponse(res, true,
      `Pembayaran ke ${header.nama_supplier || 'supplier'} dibatalkan. ${header.jumlah_tiket} tiket kembali BELUM LUNAS.`);
  } catch (err) {
    console.error('[Pembayaran] batal error:', err.message);
    return jsonResponse(res, false, err.message);
  }
});

/** Kirim ringkasan pembayaran ke Google Sheet (fire and forget, tidak memblokir). */
function syncPembayaranToGoogleSheet(noPembayaran, namaSupplier, total, metode, operator, saldo) {
  try {
    const setting = queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
    if (!setting || !setting.setting_value || !setting.setting_value.startsWith('http')) return;
    const payload = {
      sheet_type: 'keuangan',
      tanggal: new Date().toLocaleDateString('id-ID'),
      keterangan: `PEMBAYARAN ${namaSupplier} - ${noPembayaran} (${metode.toUpperCase()})`,
      debit: '',
      kredit: 'Rp. ' + new Intl.NumberFormat('id-ID').format(total),
      saldo: 'Rp. ' + new Intl.NumberFormat('id-ID').format(saldo || 0),
      waktu: new Date().toLocaleTimeString('id-ID'),
      operator: operator || 'Operator'
    };
    const https = require('https');
    const urlObj = new URL(setting.setting_value);
    const body = JSON.stringify(payload);
    const r = https.request({
      hostname: urlObj.hostname,
      path: urlObj.pathname + urlObj.search,
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) }
    }, () => {});
    r.on('error', (e) => console.error('[GoogleSheet-Pembayaran]', e.message));
    r.write(body);
    r.end();
  } catch (e) {
    console.error('[GoogleSheet-Pembayaran] setup error:', e.message);
  }
}

module.exports = router;
