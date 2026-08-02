/**
 * Setup / Settings Routes
 * Replaces: modules/setup/index.php
 */
const express = require('express');
const bcrypt = require('bcryptjs');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { cacheFlush } = require('../helpers/cache');
const { isLoggedIn, requireRole } = require('../middleware/auth');

router.use(isLoggedIn);

// GET /setup/settings
router.get('/settings', async (req, res) => {
  try {
    const settings = await query(`SELECT setting_key, setting_value FROM settings ORDER BY setting_key`);
    const map = {};
    settings.forEach(s => map[s.setting_key] = s.setting_value);
    return jsonResponse(res, true, 'Settings', map);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// POST /setup/settings
router.post('/settings', requireRole('admin'), async (req, res) => {
  try {
    const updates = req.body; // { key: value }
    for (const [key, value] of Object.entries(updates)) {
      const safeKey = cleanInput(key);
      // Don't cleanInput on JSON values (timbangan fields config)
      const safeValue = String(value);
      await query(
        `INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value`,
        [safeKey, safeValue]
      );
    }
    return jsonResponse(res, true, 'Pengaturan berhasil disimpan');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// POST /setup/clear-data (Danger Zone)
router.post('/clear-data', requireRole('admin'), async (req, res) => {
  try {
    const { password } = req.body;
    if (!password) {
      return jsonResponse(res, false, 'Password konfirmasi harus diisi');
    }

    // Get current user password
    const user = await queryOne(`SELECT password FROM users WHERE id = ?`, [req.session.user_id]);
    if (!user) {
      return jsonResponse(res, false, 'Pengguna tidak ditemukan');
    }

    // Verify password
    const isValid = await bcrypt.compare(password, user.password);
    if (!isValid) {
      return jsonResponse(res, false, 'Konfirmasi password salah');
    }

    // Execute clear queries in transaction
    const tx = beginTransaction();
    try {
      // Transaksi & operasional
      tx.execute(`DELETE FROM transaksi_timbangan`);
      tx.execute(`DELETE FROM transaksi_timbangan_langsir`);
      tx.execute(`DELETE FROM kas`);
      tx.execute(`DELETE FROM pengiriman_tkbm`);
      tx.execute(`DELETE FROM pengiriman_pabrik`);
      // Gaji & potongan
      tx.execute(`DELETE FROM potongan_gaji`);
      tx.execute(`DELETE FROM gaji_supir`);
      tx.execute(`DELETE FROM potongan_gaji_tkbm`);
      tx.execute(`DELETE FROM gaji_tkbm`);
      // Riwayat hutang
      tx.execute(`DELETE FROM hutang_ledger`);
      tx.execute(`DELETE FROM hutang_supir_history`);
      tx.execute(`DELETE FROM hutang_supplier_history`);
      tx.execute(`DELETE FROM tkbm_hutang_history`);
      // Master data
      tx.execute(`DELETE FROM supplier`);
      tx.execute(`DELETE FROM supir`);
      tx.execute(`DELETE FROM kendaraan`);
      tx.execute(`DELETE FROM customers`);
      tx.execute(`DELETE FROM pabrik`);
      tx.execute(`DELETE FROM karyawan_tkbm`);
      tx.execute(`DELETE FROM kontak`);
      // Log
      tx.execute(`DELETE FROM activity_logs`);
      tx.execute(`DELETE FROM system_logs`);
      // Settings & users
      tx.execute(`UPDATE settings SET setting_value = '["tbs","brondolan"]' WHERE setting_key = 'material_list'`);
      tx.execute(`DELETE FROM users WHERE username != 'admin'`);
      tx.commit();
      // Flush semua cache in-memory agar data lama tidak muncul lagi
      cacheFlush();
      return jsonResponse(res, true, 'Semua data transaksi, kas, master data (supplier, supir, kendaraan, pabrik, customer, TKBM), gaji, riwayat hutang, log, dan pengguna non-admin berhasil dihapus permanen.');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    console.error('[Setup] clear-data error:', err);
    return jsonResponse(res, false, 'Gagal mengosongkan data: ' + err.message);
  }
});

// ─── EMAIL FUNCTIONS ────────────────────────────────────────────────────────
const nodemailer = require('nodemailer');

async function getSmtpConfig() {
  const settings = await query(`SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_user','smtp_pass')`);
  const map = {};
  settings.forEach(s => map[s.setting_key] = s.setting_value);
  return map;
}

async function createTransporter() {
  const smtp = await getSmtpConfig();
  if (!smtp.smtp_host || !smtp.smtp_user || !smtp.smtp_pass) {
    throw new Error('Konfigurasi SMTP belum lengkap. Silakan atur di Master Data -> Konfigurasi Email');
  }
  const port = parseInt(smtp.smtp_port) || 465;
  return nodemailer.createTransport({
    host: smtp.smtp_host,
    port: port,
    secure: port === 465,
    auth: { user: smtp.smtp_user, pass: smtp.smtp_pass }
  });
}

// POST /setup/test-email — Send test email
router.post('/test-email', requireRole('admin'), async (req, res) => {
  try {
    const { recipient } = req.body;
    if (!recipient) return jsonResponse(res, false, 'Alamat email tujuan harus diisi');

    const smtp = await getSmtpConfig();
    const transporter = await createTransporter();

    await transporter.sendMail({
      from: `"Weighbridge Arroyan" <${smtp.smtp_user}>`,
      to: recipient,
      subject: 'Test Email - Weighbridge Arroyan',
      html: `<div style="font-family:Arial,sans-serif;padding:20px;">
        <h2 style="color:#1a1a2e;">✅ Test Email Berhasil!</h2>
        <p>Email ini dikirim dari aplikasi <strong>Weighbridge Arroyan</strong> untuk memverifikasi konfigurasi SMTP.</p>
        <p style="color:#666;">Waktu kirim: ${new Date().toLocaleString('id-ID')}</p>
      </div>`
    });

    return jsonResponse(res, true, 'Email test berhasil terkirim ke ' + recipient);
  } catch (err) {
    console.error('[Setup] test-email error:', err);
    return jsonResponse(res, false, 'Gagal mengirim email: ' + err.message);
  }
});

// POST /setup/send-report-email — Send report Excel via email
router.post('/send-report-email', requireRole('admin'), async (req, res) => {
  try {
    const { recipient, report_type, date_filter, start_date, end_date } = req.body;
    if (!recipient) return jsonResponse(res, false, 'Alamat email tujuan harus diisi');

    const smtp = await getSmtpConfig();
    const transporter = await createTransporter();

    const port = req.socket.localPort;
    const cookie = req.headers.cookie || '';

    const attachments = [];
    const dateLabel = date_filter === 'today' ? new Date().toISOString().split('T')[0] : `${start_date}_${end_date}`;

    // Helper: fetch a URL using Node's http module and return a Buffer
    function httpGet(urlPath) {
      return new Promise((resolve, reject) => {
        const http = require('http');
        const options = {
          hostname: '127.0.0.1',
          port: port,
          path: urlPath,
          method: 'GET',
          headers: { cookie }
        };
        const request = http.request(options, (response) => {
          const chunks = [];
          response.on('data', chunk => chunks.push(chunk));
          response.on('end', () => {
            resolve({ statusCode: response.statusCode, buffer: Buffer.concat(chunks) });
          });
        });
        request.on('error', reject);
        request.end();
      });
    }

    // Fetch Transaksi Excel
    if (report_type === 'transaksi' || report_type === 'semua') {
      const urlPath = `/transaksi/export-excel?date_filter=${date_filter || 'today'}&start_date=${start_date || ''}&end_date=${end_date || ''}&status=selesai`;
      const result = await httpGet(urlPath);
      if (result.statusCode === 200) {
        attachments.push({
          filename: `Laporan_Timbangan_${dateLabel}.xls`,
          content: result.buffer,
          contentType: 'application/vnd.ms-excel'
        });
      }
    }

    // Fetch Keuangan Excel
    if (report_type === 'keuangan' || report_type === 'semua') {
      const urlPath = `/kas/export-excel?date_filter=${date_filter || 'today'}&start_date=${start_date || ''}&end_date=${end_date || ''}`;
      const result = await httpGet(urlPath);
      if (result.statusCode === 200) {
        attachments.push({
          filename: `Laporan_Keuangan_${dateLabel}.xls`,
          content: result.buffer,
          contentType: 'application/vnd.ms-excel'
        });
      }
    }

    // Fetch Pengiriman Pabrik Excel
    if (report_type === 'pengiriman' || report_type === 'semua') {
      const urlPath = `/pengiriman/export-excel?date_filter=${date_filter || 'today'}&start_date=${start_date || ''}&end_date=${end_date || ''}`;
      const result = await httpGet(urlPath);
      if (result.statusCode === 200) {
        attachments.push({
          filename: `Laporan_Pengiriman_${dateLabel}.xls`,
          content: result.buffer,
          contentType: 'application/vnd.ms-excel'
        });
      }
    }

    if (attachments.length === 0) {
      return jsonResponse(res, false, 'Tidak ada laporan yang bisa dikirim. Pastikan ada data untuk periode yang dipilih.');
    }

    const reportNames = attachments.map(a => a.filename).join(', ');

    await transporter.sendMail({
      from: `"Weighbridge Arroyan" <${smtp.smtp_user}>`,
      to: recipient,
      subject: `Laporan Timbangan - ${dateLabel}`,
      html: `<div style="font-family:Arial,sans-serif;padding:20px;">
        <h2 style="color:#1a1a2e;">📊 Laporan Timbangan</h2>
        <p>Berikut terlampir laporan dari aplikasi <strong>Weighbridge Arroyan</strong>:</p>
        <ul>${attachments.map(a => `<li>${a.filename}</li>`).join('')}</ul>
        <p style="color:#666;">Periode: ${dateLabel}<br>Dikirim pada: ${new Date().toLocaleString('id-ID')}</p>
      </div>`,
      attachments
    });

    return jsonResponse(res, true, `Laporan berhasil dikirim ke ${recipient} (${reportNames})`);
  } catch (err) {
    console.error('[Setup] send-report-email error:', err);
    return jsonResponse(res, false, 'Gagal mengirim laporan: ' + err.message);
  }
});

module.exports = router;
