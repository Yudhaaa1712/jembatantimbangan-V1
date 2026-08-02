/**
 * Manajemen Hutang Terpadu (generik untuk semua jenis pihak)
 * Tipe didukung: supir, supplier, tkbm, petani, karyawan (lihat helpers/hutang.js)
 *
 * Mount:
 *  - /hutang            (utama, dipakai halaman Manajemen Hutang)
 *  - /hutang-supir-api  & /hutang-supplier-api  (kompatibilitas endpoint lama
 *    yang masih dipanggil halaman Timbangan & Upah: check-debt, supir-aktif)
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { isLoggedIn } = require('../middleware/auth');
const { PARTIES, getPartyConfig, partyFilter, selectCols, catatHutangTx } = require('../helpers/hutang');

router.use(isLoggedIn);

// ─── Daftar tipe pihak (untuk membangun menu di UI) ──────────────────────────
router.get('/types', (req, res) => {
  const types = Object.entries(PARTIES).map(([key, c]) => ({
    key, label: c.label, isKontak: !!c.isKontak
  }));
  return jsonResponse(res, true, 'Tipe hutang', types);
});

// ─── LEGACY: /supir-aktif (dipakai halaman Upah) ─────────────────────────────
router.get('/supir-aktif', async (req, res) => {
  try {
    const data = await query(
      `SELECT id, nama_supir, no_telepon, alamat, total_hutang, status FROM supir
       WHERE status = 'active' ORDER BY nama_supir`
    );
    return jsonResponse(res, true, 'List supir aktif', data);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── LEGACY: /:type/check-debt?name= (dipakai halaman Timbangan) ─────────────
router.get('/:type/check-debt', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    const name = cleanInput(req.query.name).trim().toUpperCase();
    if (!name) return jsonResponse(res, false, 'Nama tidak valid');

    const f = partyFilter(c);
    const params = [name, ...f.params];
    let party = await queryOne(`SELECT ${selectCols(c)} FROM ${c.table} WHERE UPPER(${c.nameCol}) = ?${f.sql}`, params);
    if (!party) party = { id: null, nama: name, total_hutang: 0, status: 'active' };
    return jsonResponse(res, true, 'Debt info', party);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── LIST semua pihak sebuah tipe (dgn saldo hutang) ─────────────────────────
router.get('/:type/list', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    const search = cleanInput(req.query.search || '').toUpperCase();
    const f = partyFilter(c);
    let sql = `SELECT ${selectCols(c)} FROM ${c.table} WHERE 1=1${f.sql}`;
    const params = [...f.params];
    if (search) { sql += ` AND UPPER(${c.nameCol}) LIKE ?`; params.push('%' + search + '%'); }
    sql += ` ORDER BY total_hutang DESC, ${c.nameCol} ASC`;
    return jsonResponse(res, true, 'List', await query(sql, params));
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── DETAIL satu pihak (untuk halaman cetak) ─────────────────────────────────
router.get('/:type/detail/:id', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    const id = parseInt(req.params.id);
    const f = partyFilter(c);
    const party = await queryOne(`SELECT ${selectCols(c)} FROM ${c.table} WHERE id = ?${f.sql}`, [id, ...f.params]);
    if (!party) return jsonResponse(res, false, 'Data tidak ditemukan');
    return jsonResponse(res, true, 'Detail', { party, label: c.label });
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── RIWAYAT hutang sebuah pihak (dari buku besar) ───────────────────────────
router.get('/:type/history/:id', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    const id = parseInt(req.params.id);
    const history = await query(
      `SELECT h.*, u.nama_lengkap AS nama_operator
       FROM hutang_ledger h
       LEFT JOIN users u ON h.operator_id = u.id
       WHERE h.party_type = ? AND h.party_id = ?
       ORDER BY h.created_at DESC, h.id DESC`,
      [req.params.type, id]
    );
    return jsonResponse(res, true, 'Riwayat hutang', history);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── BAYAR hutang (mengurangi saldo) ─────────────────────────────────────────
router.post('/:type/bayar', async (req, res) => {
  return mutasiHutang(req, res, 'bayar', 'Pembayaran manual');
});

// ─── TAMBAH hutang (menambah saldo) ──────────────────────────────────────────
router.post('/:type/tambah', async (req, res) => {
  return mutasiHutang(req, res, 'tambah', 'Penambahan manual');
});

async function mutasiHutang(req, res, jenis, defaultKet) {
  try {
    const c = getPartyConfig(req.params.type);
    const id = parseInt(req.body.id);
    const jumlah = parseFloat(req.body.jumlah);
    const keterangan = cleanInput(req.body.keterangan || '') || defaultKet;
    const tanggal = req.body.tanggal || null;
    if (!id || isNaN(jumlah) || jumlah <= 0) return jsonResponse(res, false, 'Data tidak valid');

    const tx = beginTransaction();
    try {
      const saldo = catatHutangTx(tx, {
        type: req.params.type, partyId: id, jenis, jumlah, keterangan,
        sumber: 'manual', operatorId: req.session.user_id, tanggal
      });
      tx.commit();
      const kata = jenis === 'bayar' ? 'Pembayaran' : 'Penambahan';
      return jsonResponse(res, true, `${kata} hutang ${c.label} berhasil dicatat`, { saldo });
    } catch (txErr) { tx.rollback(); throw txErr; }
  } catch (err) { return jsonResponse(res, false, err.message); }
}

// ─── KONTAK: tambah pihak baru (hanya petani/karyawan) ───────────────────────
router.post('/:type/kontak', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    if (!c.isKontak) return jsonResponse(res, false, `${c.label} ditambahkan lewat menu Master Data, bukan di sini`);

    const nama = cleanInput(req.body.nama).toUpperCase();
    const telepon = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');
    const initialDebt = parseFloat(req.body.initial_debt) || 0;
    if (!nama) return jsonResponse(res, false, 'Nama harus diisi');

    const existing = await queryOne(`SELECT id FROM kontak WHERE UPPER(nama) = ? AND tipe = ?`, [nama, c.tipe]);
    if (existing) return jsonResponse(res, false, `${c.label} dengan nama tersebut sudah ada`);

    const tx = beginTransaction();
    try {
      const [result] = tx.execute(
        `INSERT INTO kontak (nama, tipe, no_telepon, alamat, total_hutang, status, created_at)
         VALUES (?, ?, ?, ?, 0, 'active', datetime('now','localtime'))`,
        [nama, c.tipe, telepon, alamat]
      );
      const id = result.insertId;
      if (initialDebt > 0) {
        catatHutangTx(tx, {
          type: req.params.type, partyId: id, jenis: 'tambah', jumlah: initialDebt,
          keterangan: 'Hutang awal saat pendaftaran', sumber: 'manual', operatorId: req.session.user_id
        });
      }
      tx.commit();
      return jsonResponse(res, true, `${c.label} baru berhasil ditambahkan`, { id });
    } catch (txErr) { tx.rollback(); throw txErr; }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── KONTAK: edit (hanya petani/karyawan) ────────────────────────────────────
router.put('/:type/kontak/:id', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    if (!c.isKontak) return jsonResponse(res, false, `${c.label} diubah lewat menu Master Data`);
    const id = parseInt(req.params.id);
    const nama = cleanInput(req.body.nama).toUpperCase();
    const telepon = cleanInput(req.body.no_telepon || '');
    const alamat = cleanInput(req.body.alamat || '');
    const status = cleanInput(req.body.status) || 'active';
    if (!id || !nama) return jsonResponse(res, false, 'Data tidak valid');

    await query(
      `UPDATE kontak SET nama=?, no_telepon=?, alamat=?, status=?, updated_at=datetime('now','localtime')
       WHERE id=? AND tipe=?`,
      [nama, telepon, alamat, status, id, c.tipe]
    );
    return jsonResponse(res, true, `${c.label} berhasil diperbarui`);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── KONTAK: hapus permanen (hanya petani/karyawan) ──────────────────────────
router.delete('/:type/kontak/:id', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    if (!c.isKontak) return jsonResponse(res, false, `${c.label} dihapus lewat menu Master Data`);
    const id = parseInt(req.params.id);
    const tx = beginTransaction();
    try {
      tx.execute(`DELETE FROM hutang_ledger WHERE party_type = ? AND party_id = ?`, [req.params.type, id]);
      tx.execute(`DELETE FROM kontak WHERE id = ? AND tipe = ?`, [id, c.tipe]);
      tx.commit();
      return jsonResponse(res, true, `${c.label} berhasil dihapus permanen`);
    } catch (txErr) { tx.rollback(); throw txErr; }
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// ─── EXPORT Excel rekap hutang sebuah tipe ───────────────────────────────────
router.get('/:type/export', async (req, res) => {
  try {
    const c = getPartyConfig(req.params.type);
    const f = partyFilter(c);
    const list = await query(`SELECT ${selectCols(c)} FROM ${c.table} WHERE 1=1${f.sql} ORDER BY ${c.nameCol}`, f.params);

    let total = 0;
    let rows = '';
    list.forEach((s, i) => {
      total += s.total_hutang || 0;
      rows += `<tr>
        <td class="text-center">${i + 1}</td>
        <td>${s.nama}</td>
        <td class="text-center">${s.no_telepon || '-'}</td>
        <td>${s.alamat || '-'}</td>
        <td class="currency-format">${s.total_hutang || 0}</td>
        <td class="text-center">${(s.status || '').toUpperCase()}</td>
      </tr>`;
    });

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Rekap Hutang ${c.label}</title>
<style>
  body{font-family:Arial,sans-serif;}
  .title{font-size:18px;font-weight:bold;text-decoration:underline;text-align:center;}
  table{width:100%;border-collapse:collapse;margin-top:12px;}
  th,td{border:1px solid #000;padding:5px;font-size:11px;}
  th{background:#E2EFDA;text-align:center;}
  .text-center{text-align:center;} .currency-format{text-align:right;mso-number-format:"\\#\\,\\#\\#0";}
</style></head><body>
<div class="title">LAPORAN REKAP HUTANG ${c.label.toUpperCase()}</div>
<div style="text-align:center">Tanggal Cetak: ${new Date().toLocaleDateString('id-ID')}</div>
<table><thead><tr>
  <th>NO</th><th>NAMA</th><th>NO TELEPON</th><th>ALAMAT</th><th>TOTAL HUTANG</th><th>STATUS</th>
</tr></thead><tbody>${rows}
<tr style="font-weight:bold;background:#F2F2F2;">
  <td colspan="4" class="text-center">TOTAL HUTANG KESELURUHAN</td>
  <td class="currency-format">${total}</td><td></td>
</tr></tbody></table></body></html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment;filename="Rekap_Hutang_${c.label}_${new Date().toISOString().split('T')[0]}.xls"`);
    return res.send(html);
  } catch (err) { return jsonResponse(res, false, 'Gagal export: ' + err.message); }
});

module.exports = router;
