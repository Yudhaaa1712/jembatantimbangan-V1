/**
 * Kas (Cash Management) Routes
 * Weighbridge - Arroyan Jv Teknik
 * 
 * Fitur: Manajemen uang kas untuk pembelian buah sawit
 * - Deposit kas (admin only)
 * - Pengeluaran manual (admin only)
 * - Auto-deduct saat beli buah (dari timbangan.js)
 * - Saldo realtime
 * - Buku kas / ledger bulanan
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, pool, jsonResponse } = require('../config/database');
const { isLoggedIn, requireRole } = require('../middleware/auth');
const { recalculateKasBalances, buildKeterangan } = require('../helpers/kasHelper');

router.use(isLoggedIn);

// Helper function to send data to Google Sheet
async function syncKasToGoogleSheet(jenis, jumlah, keterangan, saldo_setelah, operator_id) {
  try {
    const setting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
    if (setting && setting.setting_value && setting.setting_value.startsWith('http')) {
      // Get operator name
      const op = await queryOne(`SELECT nama_lengkap FROM users WHERE id = ?`, [operator_id]);
      const operatorNama = op ? op.nama_lengkap : 'Admin';

      const sheetData = {
        sheet_type: 'keuangan',
        tanggal: new Date().toLocaleDateString('id-ID'), // e.g. 30/05/2026
        keterangan: keterangan || '',
        debit: jenis === 'masuk' ? 'Rp. ' + new Intl.NumberFormat('id-ID').format(jumlah) : '',
        kredit: jenis === 'keluar' ? 'Rp. ' + new Intl.NumberFormat('id-ID').format(jumlah) : '',
        saldo: 'Rp. ' + new Intl.NumberFormat('id-ID').format(saldo_setelah),
        waktu: new Date().toLocaleTimeString('id-ID'),
        operator: operatorNama
      };

      const https = require('https');
      const urlObj = new URL(setting.setting_value);
      const options = {
        hostname: urlObj.hostname,
        path: urlObj.pathname + urlObj.search,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(JSON.stringify(sheetData))
        }
      };

      const reqSheet = https.request(options, (resSheet) => {
        console.log('[GoogleSheet-Kas] Status:', resSheet.statusCode);
      });
      reqSheet.on('error', (e) => {
        console.error('[GoogleSheet-Kas] Error:', e.message);
      });
      reqSheet.write(JSON.stringify(sheetData));
      reqSheet.end();
    }
  } catch (err) {
    console.error('[GoogleSheet-Kas] Failed:', err.message);
  }
}


/**
 * Keterangan yang ditampilkan di Buku Kas.
 *
 * Sekarang keterangan sudah ditulis sesuai SOP di sumbernya masing-masing
 * (lihat helpers/kasHelper.js), jadi baris apa adanya dipakai langsung.
 * Dulu keterangan baris bertiket SELALU ditimpa jadi "MATERIAL SUPIR", sehingga
 * baris pembatalan tiket tampil sama persis dengan baris pembeliannya.
 * Penyusunan dari material+supir kini hanya cadangan untuk data lama yang
 * keterangannya kosong.
 */
function ketBukuKas(row) {
  const tersimpan = (row.keterangan || '').trim();
  if (tersimpan) return tersimpan;
  if (row.id_transaksi && row.jenis_material) {
    return buildKeterangan('PEMBELIAN ' + row.jenis_material, row.nama_supir, row.no_tiket);
  }
  return '';
}

/**
 * Baris manual = benar-benar diketik di halaman Keuangan, jadi boleh dihapus.
 * Baris yang lahir dari menu lain (timbangan, pembayaran, hutang, gaji) punya
 * pasangan catatan di tempat lain dan harus dibatalkan dari menu asalnya.
 */
function isBarisManual(row) {
  return !row.id_transaksi && !row.id_pembayaran && !row.id_hutang_ledger
    && !row.id_gaji_supir && !row.id_gaji_tkbm;
}

// Helper date filter
function buildDateCondition(dateFilter, startDate, endDate) {
  switch (dateFilter) {
    case 'today':      return { sql: `k.tanggal = date('now', 'localtime')`, params: [] };
    case 'yesterday':  return { sql: `k.tanggal = date('now', 'localtime', '-1 day')`, params: [] };
    case 'week':       return { sql: `k.tanggal >= date('now', 'localtime', '-7 days')`, params: [] };
    case 'half_month': return { sql: `k.tanggal >= date('now', 'localtime', '-15 days')`, params: [] };
    case 'month':      return { sql: `strftime('%m', k.tanggal) = strftime('%m', 'now', 'localtime') AND strftime('%Y', k.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'half_year':  return { sql: `k.tanggal >= date('now', 'localtime', '-6 months')`, params: [] };
    case 'year':       return { sql: `strftime('%Y', k.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'custom_range': return { sql: `k.tanggal BETWEEN ? AND ?`, params: [startDate, endDate] };
    default:           return { sql: `k.tanggal = date('now', 'localtime')`, params: [] };
  }
}

function getFilterStartDate(dateFilter, customStart) {
  if (dateFilter === 'custom_range') return customStart;
  const now = new Date();
  // adjust timezone to local
  const offset = now.getTimezoneOffset() * 60000;
  const local = new Date(now.getTime() - offset);
  
  if (dateFilter === 'today') {
    return local.toISOString().split('T')[0];
  }
  if (dateFilter === 'yesterday') {
    local.setDate(local.getDate() - 1);
    return local.toISOString().split('T')[0];
  }
  if (dateFilter === 'week') {
    local.setDate(local.getDate() - 7);
    return local.toISOString().split('T')[0];
  }
  if (dateFilter === 'half_month') {
    local.setDate(local.getDate() - 15);
    return local.toISOString().split('T')[0];
  }
  if (dateFilter === 'month') {
    return local.toISOString().substring(0, 8) + '01';
  }
  if (dateFilter === 'half_year') {
    local.setMonth(local.getMonth() - 6);
    return local.toISOString().split('T')[0];
  }
  if (dateFilter === 'year') {
    return local.getFullYear() + '-01-01';
  }
  return local.toISOString().split('T')[0];
}

// ─── GET Saldo Kas Saat Ini ───────────────────────────────────────────────────
router.get('/saldo', async (req, res) => {
  try {
    // Ambil saldo dari record terakhir absolut (carry-over)
    const last = await queryOne(
      `SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`
    );
    const saldo = last ? parseFloat(last.saldo_setelah) : 0;

    // Hitung total masuk dan total keluar khusus hari ini
    const totals = await queryOne(`
      SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END), 0) as total_masuk,
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END), 0) as total_keluar
      FROM kas
      WHERE tanggal = CURDATE()
    `);

    return jsonResponse(res, true, 'Saldo kas', {
      saldo: saldo,
      total_masuk: parseFloat(totals.total_masuk),
      total_keluar: parseFloat(totals.total_keluar)
    });
  } catch (err) {
    console.error('[Kas] Error get saldo:', err);
    return jsonResponse(res, false, 'Gagal mengambil saldo kas');
  }
});

// ─── GET Riwayat Kas (5 terakhir untuk UI) ────────────────────────────────────
router.get('/history', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 10;
    const rows = await query(
      `SELECT k.*, u.nama_lengkap as operator_nama 
       FROM kas k 
       LEFT JOIN users u ON k.operator_id = u.id 
       ORDER BY k.id DESC 
       LIMIT ${limit}`
    );
    return jsonResponse(res, true, 'Riwayat kas', rows);
  } catch (err) {
    console.error('[Kas] Error get history:', err);
    return jsonResponse(res, false, 'Gagal mengambil riwayat kas');
  }
});

// ─── GET Ledger / Buku Kas ────────────────────────────────────────────
router.get('/ledger', async (req, res) => {
  try {
    if (req.session.user_role !== 'admin') {
      return jsonResponse(res, false, 'Hanya admin yang bisa mengakses buku kas');
    }

    const dateFilter = req.query.date_filter || 'today';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    const resolvedStartDate = getFilterStartDate(dateFilter, startDate);

    // Saldo awal adalah saldo akhir hari sebelum tanggal filter mulai
    const prevEntry = await queryOne(
      `SELECT saldo_setelah FROM kas WHERE tanggal < ? ORDER BY id DESC LIMIT 1`,
      [resolvedStartDate]
    );
    const saldoAwalValue = prevEntry ? parseFloat(prevEntry.saldo_setelah) : 0;

    // Ambil semua entry kas
    const rows = await query(`
      SELECT k.id, k.tanggal, k.jenis, k.jumlah, k.keterangan,
             k.id_transaksi, k.no_tiket, k.id_pembayaran, k.id_hutang_ledger,
             k.id_gaji_supir, k.id_gaji_tkbm, k.saldo_setelah, k.created_at,
             tt.jenis_material, tt.nama_supir,
             s.nama_supplier,
             u.nama_lengkap as operator_nama
      FROM kas k
      LEFT JOIN transaksi_timbangan tt ON k.id_transaksi = tt.id
      LEFT JOIN supplier s ON tt.id_supplier = s.id
      LEFT JOIN users u ON k.operator_id = u.id
      WHERE ${dateSql}
      ORDER BY k.id ASC
    `, dateParams);

    const formattedRows = rows.map(row => ({
      id: row.id,
      tanggal: row.tanggal,
      jenis: row.jenis,
      jumlah: parseFloat(row.jumlah),
      keterangan: ketBukuKas(row),
      saldo_setelah: parseFloat(row.saldo_setelah),
      id_transaksi: row.id_transaksi,
      no_tiket: row.no_tiket,
      id_pembayaran: row.id_pembayaran,
      id_hutang_ledger: row.id_hutang_ledger,
      id_gaji_supir: row.id_gaji_supir,
      id_gaji_tkbm: row.id_gaji_tkbm,
      is_manual: isBarisManual(row),
      operator_nama: row.operator_nama,
      created_at: row.created_at
    }));

    // Hitung totals
    const totals = await queryOne(`
      SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END), 0) as total_debit,
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END), 0) as total_kredit,
        COUNT(*) as total_entries
      FROM kas k
      WHERE ${dateSql}
    `, dateParams);

    // Saldo akhir adalah saldo awal + debit - kredit
    const totalDebit = parseFloat(totals.total_debit);
    const totalKredit = parseFloat(totals.total_kredit);
    const saldoAkhir = saldoAwalValue + totalDebit - totalKredit;

    return jsonResponse(res, true, 'Buku kas', {
      date_filter: dateFilter,
      start_date: startDate,
      end_date: endDate,
      saldo_awal: saldoAwalValue,
      saldo_akhir: saldoAkhir,
      total_debit: totalDebit,
      total_kredit: totalKredit,
      total_entries: totals.total_entries,
      data: formattedRows
    });
  } catch (err) {
    console.error('[Kas] Error get ledger:', err);
    return jsonResponse(res, false, 'Gagal mengambil buku kas: ' + err.message);
  }
});

// ─── POST Tambah Kas (Deposit) — Admin Only ──────────────────────────────────
router.post('/tambah', async (req, res) => {
  try {
    // Cek admin
    if (req.session.user_role !== 'admin') {
      return jsonResponse(res, false, 'Hanya admin yang bisa menambah kas');
    }

    const jumlah = parseFloat(String(req.body.jumlah).replace(/[^0-9]/g, ''));
    // Keterangan bebas diketik admin, tapi tetap dirapikan sesuai SOP:
    // HURUF BESAR, spasi ganda dibuang. Default "MODAL MASUK" bila dikosongkan.
    const keterangan = buildKeterangan(req.body.keterangan || 'MODAL MASUK');

    if (!jumlah || jumlah <= 0) {
      return jsonResponse(res, false, 'Jumlah kas harus lebih dari 0');
    }

    // Ambil saldo terakhir absolut (carry-over)
    const last = await queryOne(
      `SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`
    );
    const saldoSebelum = last ? parseFloat(last.saldo_setelah) : 0;
    const saldoSesudah = saldoSebelum + jumlah;

    // Insert record kas masuk
    await query(
      `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id, created_at)
       VALUES (CURDATE(), 'masuk', ?, ?, ?, ?, NOW())`,
      [jumlah, keterangan, saldoSesudah, req.session.user_id]
    );

    // Sync to Google Sheets
    syncKasToGoogleSheet('masuk', jumlah, keterangan, saldoSesudah, req.session.user_id);

    console.log(`[Kas] Deposit: +Rp ${jumlah.toLocaleString('id-ID')} oleh user ${req.session.user_id}. Saldo: Rp ${saldoSesudah.toLocaleString('id-ID')}`);

    return jsonResponse(res, true, `Berhasil menambah kas Rp ${jumlah.toLocaleString('id-ID')}`, {
      saldo: saldoSesudah
    });
  } catch (err) {
    console.error('[Kas] Error tambah kas:', err);
    return jsonResponse(res, false, 'Gagal menambah kas: ' + err.message);
  }
});

// ─── POST Pengeluaran Manual — Admin Only ─────────────────────────────────────
router.post('/pengeluaran', async (req, res) => {
  try {
    if (req.session.user_role !== 'admin') {
      return jsonResponse(res, false, 'Hanya admin yang bisa menambah pengeluaran');
    }

    const jumlah = parseFloat(String(req.body.jumlah).replace(/[^0-9]/g, ''));
    const keterangan = buildKeterangan(req.body.keterangan || '');

    if (!jumlah || jumlah <= 0) {
      return jsonResponse(res, false, 'Jumlah pengeluaran harus lebih dari 0');
    }

    if (!keterangan) {
      return jsonResponse(res, false, 'Keterangan harus diisi');
    }

    // Ambil saldo terakhir absolut (carry-over)
    const last = await queryOne(
      `SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`
    );
    const saldoSebelum = last ? parseFloat(last.saldo_setelah) : 0;
    const saldoSesudah = saldoSebelum - jumlah;

    // Insert record kas keluar (manual, tanpa id_transaksi)
    await query(
      `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, id_transaksi, no_tiket, saldo_setelah, operator_id, created_at)
       VALUES (CURDATE(), 'keluar', ?, ?, NULL, NULL, ?, ?, NOW())`,
      [jumlah, keterangan, saldoSesudah, req.session.user_id]
    );

    // Sync to Google Sheets
    syncKasToGoogleSheet('keluar', jumlah, keterangan, saldoSesudah, req.session.user_id);

    console.log(`[Kas] Pengeluaran manual: -Rp ${jumlah.toLocaleString('id-ID')} (${keterangan}). Saldo: Rp ${saldoSesudah.toLocaleString('id-ID')}`);

    return jsonResponse(res, true, `Pengeluaran Rp ${jumlah.toLocaleString('id-ID')} berhasil dicatat`, {
      saldo: saldoSesudah
    });
  } catch (err) {
    console.error('[Kas] Error pengeluaran:', err);
    return jsonResponse(res, false, 'Gagal mencatat pengeluaran: ' + err.message);
  }
});

// ─── DELETE Hapus Entry Kas Manual — Admin Only ───────────────────────────────
router.delete('/hapus/:id', async (req, res) => {
  try {
    if (req.session.user_role !== 'admin') {
      return jsonResponse(res, false, 'Hanya admin yang bisa menghapus entry kas');
    }

    const id = parseInt(req.params.id);
    if (!id) return jsonResponse(res, false, 'ID tidak valid');

    // Cek entry exist dan manual (bukan auto dari transaksi)
    const entry = await queryOne(`SELECT * FROM kas WHERE id = ?`, [id]);
    if (!entry) return jsonResponse(res, false, 'Entry tidak ditemukan');
    if (entry.id_transaksi) {
      return jsonResponse(res, false, 'Tidak bisa menghapus entry otomatis dari transaksi. Batalkan transaksinya jika perlu.');
    }
    // Baris kas milik pembayaran supplier tidak boleh dihapus dari sini: tiketnya
    // akan tetap berstatus lunas sementara uangnya kembali ke saldo — pembukuan
    // jadi tidak seimbang. Satu-satunya jalan adalah membatalkan pembayarannya.
    if (entry.id_pembayaran) {
      return jsonResponse(res, false,
        'Tidak bisa menghapus entry pembayaran supplier dari sini. Hapus pembayarannya di menu Pembayaran agar tiketnya ikut dikembalikan.');
    }
    // Sama halnya dengan kasbon/pelunasan hutang: menghapus baris kasnya saja akan
    // membuat saldo kas dan buku besar hutang tidak lagi sejalan.
    if (entry.id_hutang_ledger) {
      return jsonResponse(res, false,
        'Tidak bisa menghapus entry kasbon/pelunasan hutang dari sini. Perbaiki lewat menu Manajemen Hutang agar saldo hutangnya ikut menyesuaikan.');
    }
    // Pembayaran upah juga punya pasangan catatan (slip gaji + penanda trip yang
    // sudah digaji). Menghapus baris kasnya saja membuat gaji tercatat lunas
    // sementara uangnya balik ke saldo.
    if (entry.id_gaji_supir || entry.id_gaji_tkbm) {
      return jsonResponse(res, false,
        'Tidak bisa menghapus entry pembayaran upah dari sini. Batalkan pembayaran gajinya di menu Upah agar slip dan trip-nya ikut dikembalikan.');
    }

    const jumlahHapus = parseFloat(entry.jumlah);
    const jenisHapus = entry.jenis;

    // Hapus entry
    await query(`DELETE FROM kas WHERE id = ?`, [id]);

    // --- Kirim Notifikasi Hapus ke Google Sheet ---
    try {
      const setting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
      if (setting && setting.setting_value && setting.setting_value.startsWith('http')) {
        const sheetData = {
          sheet_type: 'keuangan',
          action: 'delete',
          id: id,
          keterangan: entry.keterangan
        };
        const https = require('https');
        const urlObj = new URL(setting.setting_value);
        const options = {
          hostname: urlObj.hostname,
          path: urlObj.pathname + urlObj.search,
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(JSON.stringify(sheetData))
          }
        };
        const reqSheet = https.request(options);
        reqSheet.on('error', (e) => console.error('[GoogleSheet] Error deleting kas:', e.message));
        reqSheet.write(JSON.stringify(sheetData));
        reqSheet.end();
      }
    } catch (err) {
      console.error('[GoogleSheet] Setup error:', err.message);
    }
    // ----------------------------------------------

    // Recalculate saldo_setelah untuk semua entry setelah yang dihapus
    const runningSaldo = await recalculateKasBalances();

    console.log(`[Kas] Entry #${id} dihapus (${jenisHapus}: Rp ${jumlahHapus.toLocaleString('id-ID')})`);

    return jsonResponse(res, true, 'Entry berhasil dihapus', { saldo: runningSaldo });
  } catch (err) {
    console.error('[Kas] Error hapus:', err);
    return jsonResponse(res, false, 'Gagal menghapus entry: ' + err.message);
  }
});

// GET /kas/export-excel — Export kas ledger to Excel
router.get('/export-excel', async (req, res) => {
  try {
    if (req.session.user_role !== 'admin') {
      return res.status(403).send('Hanya admin yang bisa mengekspor buku kas');
    }

    const dateFilter = req.query.date_filter || 'today';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    const resolvedStartDate = getFilterStartDate(dateFilter, startDate);

    // Saldo awal adalah saldo akhir hari sebelum tanggal filter mulai
    const prevEntry = await queryOne(
      `SELECT saldo_setelah FROM kas WHERE tanggal < ? ORDER BY id DESC LIMIT 1`,
      [resolvedStartDate]
    );
    const saldoAwalValue = prevEntry ? parseFloat(prevEntry.saldo_setelah) : 0;

    // Ambil semua entry kas
    const rows = await query(`
      SELECT k.id, k.tanggal, k.jenis, k.jumlah, k.keterangan, 
             k.id_transaksi, k.no_tiket, k.id_pembayaran, k.id_hutang_ledger, k.saldo_setelah, k.created_at,
             tt.jenis_material, tt.nama_supir,
             s.nama_supplier,
             u.nama_lengkap as operator_nama
      FROM kas k
      LEFT JOIN transaksi_timbangan tt ON k.id_transaksi = tt.id
      LEFT JOIN supplier s ON tt.id_supplier = s.id
      LEFT JOIN users u ON k.operator_id = u.id
      WHERE ${dateSql}
      ORDER BY k.id ASC
    `, dateParams);

    // Hitung totals
    const totals = await queryOne(`
      SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END), 0) as total_debit,
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END), 0) as total_kredit
      FROM kas k
      WHERE ${dateSql}
    `, dateParams);

    const totalDebit = parseFloat(totals.total_debit);
    const totalKredit = parseFloat(totals.total_kredit);
    const saldoAkhir = saldoAwalValue + totalDebit - totalKredit;

    let dateLabelText = dateFilter === 'today' ? 'Hari Ini' : `${startDate} s/d ${endDate}`;
    if (dateFilter === 'yesterday') dateLabelText = 'Kemarin';
    if (dateFilter === 'this_month') dateLabelText = 'Bulan Ini';
    if (dateFilter === 'last_30_days') dateLabelText = '30 Hari Terakhir';

    // Format tanggal helper
    const formatTanggal = (dateStr) => {
      if (!dateStr) return '';
      const parts = dateStr.split('-');
      if (parts.length !== 3) return dateStr;
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    // Escape HTML helper
    const escapeHtml = (text) => {
      if (!text) return '';
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };

    // Format rupiah helper for text display
    const formatRupiahVal = (val) => {
      if (val === undefined || val === null || val === '') return 'Rp 0';
      const num = parseFloat(val);
      if (isNaN(num)) return 'Rp 0';
      return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
    };

    let html = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head><meta charset="UTF-8">
    <style>
      td, th { font-family: Arial; font-size: 11px; border: 1px solid #000; padding: 5px 8px; }
      th { background: #1a1a1a; color: #fff; font-weight: bold; text-align: center; }
      .title { font-size: 16px; font-weight: bold; border: none; text-align: center; }
      .subtitle { font-size: 12px; border: none; text-align: center; color: #666; }
      .debit { color: #16a34a; font-weight: bold; }
      .kredit { color: #dc2626; font-weight: bold; }
      .saldo { font-weight: bold; }
      .total-row td { background: #f0f0f0; font-weight: bold; font-size: 12px; }
      .saldo-awal td { background: #fefce8; font-style: italic; }
      .right { text-align: right; }
      .center { text-align: center; }
      .currency-format { text-align: right; white-space: nowrap; mso-number-format:"\\"Rp \\"\\#\\,\\#\\#0;\\"Rp \\"\\-\\#\\,\\#\\#0;\\"-\\""; }
    </style>
    </head><body>
    <table>
      <tr><td colspan="6" class="title">KEUANGAN PERIODE: ${dateLabelText.toUpperCase()}</td></tr>
      <tr><td colspan="6" class="subtitle">Dicetak: ${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID')}</td></tr>
      <tr><td colspan="6"></td></tr>
      <tr>
        <th style="width:40px;">NO</th>
        <th style="width:90px;">TANGGAL</th>
        <th style="width:250px;">KETERANGAN</th>
        <th style="width:120px;">DEBIT</th>
        <th style="width:120px;">KREDIT</th>
        <th style="width:140px;">SALDO</th>
      </tr>
      <tr class="saldo-awal">
        <td class="center">-</td>
        <td>-</td>
        <td>SALDO SEBELUMNYA</td>
        <td></td>
        <td></td>
        <td class="currency-format saldo">${saldoAwalValue}</td>
      </tr>`;

    rows.forEach((row, i) => {
      const isDebit = row.jenis === 'masuk';
      const keterangan = ketBukuKas(row);
      html += `<tr>
        <td class="center">${i + 1}</td>
        <td>${formatTanggal(row.tanggal)}</td>
        <td>${escapeHtml(keterangan)}</td>
        <td class="currency-format ${isDebit ? 'debit' : ''}">${isDebit ? parseFloat(row.jumlah) : ''}</td>
        <td class="currency-format ${!isDebit ? 'kredit' : ''}">${!isDebit ? parseFloat(row.jumlah) : ''}</td>
        <td class="currency-format saldo">${parseFloat(row.saldo_setelah)}</td>
      </tr>`;
    });

    html += `
      <tr class="total-row">
        <td colspan="3" style="font-weight:bold;">TOTAL</td>
        <td class="currency-format debit">${totalDebit}</td>
        <td class="currency-format kredit">${totalKredit}</td>
        <td class="currency-format saldo">${saldoAkhir}</td>
      </tr>
    </table>
    </body>
    </html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment;filename="Laporan_Keuangan_${new Date().toISOString().split('T')[0]}.xls"`);
    res.setHeader('Cache-Control', 'max-age=0');
    return res.send(html);
  } catch (err) {
    console.error('[Kas] export-excel error:', err);
    return res.status(500).send('Gagal export Excel: ' + err.message);
  }
});

module.exports = router;
