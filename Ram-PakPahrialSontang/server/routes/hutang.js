/**
 * Hutang Supir Routes
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { isLoggedIn, requireRole } = require('../middleware/auth');

router.use(isLoggedIn);

// Helper function to find or create supir by name
async function findOrCreateSupir(namaSupir) {
  const name = cleanInput(namaSupir).trim().toUpperCase();
  if (!name) return null;

  let supir = await queryOne(`SELECT * FROM supir WHERE UPPER(nama_supir) = ?`, [name]);
  if (!supir) {
    const result = await query(`INSERT INTO supir (nama_supir, total_hutang, status) VALUES (?, 0, 'active')`, [name]);
    supir = {
      id: result.insertId,
      nama_supir: name,
      total_hutang: 0,
      status: 'active'
    };
  }
  return supir;
}

// GET /hutang-supir-api/supir-aktif
router.get('/supir-aktif', async (req, res) => {
  try {
    const data = await query(
      `SELECT DISTINCT s.* FROM supir s
       INNER JOIN hutang_supir_history h ON s.id = h.id_supir
       WHERE s.status = 'active'
       ORDER BY s.nama_supir`
    );
    return jsonResponse(res, true, 'List supir aktif', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /hutang-supir-api/supir/check-debt
router.get('/supir/check-debt', async (req, res) => {
  try {
    const cleanName = cleanInput(req.query.name).trim().toUpperCase();
    if (!cleanName) return jsonResponse(res, false, 'Nama supir tidak valid');
    
    let supir = await queryOne(`SELECT * FROM supir WHERE UPPER(nama_supir) = ?`, [cleanName]);
    if (!supir) {
      supir = {
        id: null,
        nama_supir: cleanName,
        total_hutang: 0,
        status: 'active'
      };
    }
    return jsonResponse(res, true, 'Debt info', supir);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /hutang-supir-api/supir/:id/history
router.get('/supir/:id/history', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const history = await query(
      `SELECT h.*, u.nama_lengkap as nama_operator 
       FROM hutang_supir_history h
       LEFT JOIN users u ON h.operator_id = u.id
       WHERE h.id_supir = ? 
       ORDER BY h.created_at DESC`,
      [id]
    );
    return jsonResponse(res, true, 'History hutang', history);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /hutang-supir-api/bayar
router.post('/bayar', async (req, res) => {
  try {
    const { id_supir, jumlah, keterangan, tanggal } = req.body;
    const amount = parseFloat(jumlah);
    if (!id_supir || isNaN(amount) || amount <= 0) {
      return jsonResponse(res, false, 'Data tidak valid');
    }

    const tgl = tanggal || new Date().toISOString().split('T')[0];

    const supir = await queryOne(`SELECT * FROM supir WHERE id = ?`, [id_supir]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    const newHutang = Math.max(0, supir.total_hutang - amount);
    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE supir SET total_hutang = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`, [newHutang, id_supir]);
      tx.execute(
        `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id) 
         VALUES (?, ?, 'bayar', ?, ?, ?, ?)`,
        [id_supir, tgl, amount, keterangan || 'Pembayaran Manual', newHutang, req.session.user_id]
      );
      tx.commit();
      return jsonResponse(res, true, 'Pembayaran hutang berhasil dicatat');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /hutang-supir-api/tambah
router.post('/tambah', async (req, res) => {
  try {
    const { id_supir, jumlah, keterangan, tanggal } = req.body;
    const amount = parseFloat(jumlah);
    if (!id_supir || isNaN(amount) || amount <= 0) {
      return jsonResponse(res, false, 'Data tidak valid');
    }

    const tgl = tanggal || new Date().toISOString().split('T')[0];

    const supir = await queryOne(`SELECT * FROM supir WHERE id = ?`, [id_supir]);
    if (!supir) return jsonResponse(res, false, 'Supir tidak ditemukan');

    const newHutang = supir.total_hutang + amount;
    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE supir SET total_hutang = ?, updated_at = datetime('now', 'localtime') WHERE id = ?`, [newHutang, id_supir]);
      tx.execute(
        `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id) 
         VALUES (?, ?, 'tambah', ?, ?, ?, ?)`,
        [id_supir, tgl, amount, keterangan || 'Penambahan Manual', newHutang, req.session.user_id]
      );
      tx.commit();
      return jsonResponse(res, true, 'Penambahan hutang berhasil dicatat');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /hutang-supir-api/export-excel
router.get('/export-excel', async (req, res) => {
  try {
    const supirList = await query(`SELECT * FROM supir ORDER BY nama_supir`);
    
    let html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Rekapan Hutang</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; text-decoration: underline; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; font-size: 11px; }
        .data-table th { background-color: #E2EFDA; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .currency-format { text-align: right; mso-number-format:"\\"Rp \\"\\#\\,\\#\\#0;\\"Rp \\"\\-\\#\\,\\#\\#0;\\"-\\""; }
    </style>
</head>
<body>
<div class="header">
    <div class="title">LAPORAN REKAPAN HUTANG</div>
    <div>Tanggal Cetak: ${new Date().toLocaleDateString('id-ID')}</div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th>NO</th>
            <th>NAMA</th>
            <th>NO TELEPON</th>
            <th>ALAMAT</th>
            <th>TOTAL HUTANG</th>
            <th>STATUS</th>
        </tr>
    </thead>
    <tbody>`;

    let totalSemuaHutang = 0;
    supirList.forEach((s, idx) => {
      totalSemuaHutang += s.total_hutang || 0;
      html += `
        <tr>
            <td class="text-center">${idx + 1}</td>
            <td>${s.nama_supir}</td>
            <td class="text-center">${s.no_telepon || '-'}</td>
            <td>${s.alamat || '-'}</td>
            <td class="currency-format">${s.total_hutang || 0}</td>
            <td class="text-center">${s.status.toUpperCase()}</td>
        </tr>`;
    });

    html += `
        <tr style="font-weight:bold; background-color:#F2F2F2;">
            <td colspan="4" class="text-center">TOTAL HUTANG KESELURUHAN</td>
            <td class="currency-format">${totalSemuaHutang}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment;filename="Rekap_Hutang_Supir_${new Date().toISOString().split('T')[0]}.xls"`);
    res.setHeader('Cache-Control', 'max-age=0');
    return res.send(html);
  } catch (err) {
    console.error(err);
    return jsonResponse(res, false, 'Gagal export Excel: ' + err.message);
  }
});

// POST /hutang-supir-api/supir/add
router.post('/supir/add', async (req, res) => {
  try {
    const name = cleanInput(req.body.nama_supir).trim().toUpperCase();
    const telepon = cleanInput(req.body.no_telepon || '').trim();
    const alamat = cleanInput(req.body.alamat || '').trim();
    const initialDebt = parseFloat(req.body.initial_debt) || 0;
    
    if (!name) return jsonResponse(res, false, 'Nama supir harus diisi');

    const existing = await queryOne(`SELECT * FROM supir WHERE UPPER(nama_supir) = ?`, [name]);
    if (existing) {
      return jsonResponse(res, false, 'Supir dengan nama tersebut sudah terdaftar');
    }

    const tx = beginTransaction();
    try {
      const [result] = await tx.execute(
        `INSERT INTO supir (nama_supir, no_telepon, alamat, total_hutang, status) VALUES (?, ?, ?, ?, 'active')`,
        [name, telepon, alamat, initialDebt]
      );
      const supirId = result.insertId;

      // Insert history record
      if (initialDebt > 0) {
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'tambah', ?, 'Hutang awal saat pendaftaran', ?, ?)`,
          [supirId, initialDebt, initialDebt, req.session.user_id]
        );
      } else {
        tx.execute(
          `INSERT INTO hutang_supir_history (id_supir, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
           VALUES (?, date('now', 'localtime'), 'tambah', 0, 'Pendaftaran Supir Baru', 0, ?)`,
          [supirId, req.session.user_id]
        );
      }

      tx.commit();
      return jsonResponse(res, true, 'Supir baru berhasil ditambahkan');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// ─── SUPPLIER DEBT ENDPOINTS ──────────────────────────────────────────────────

// GET /supplier-aktif
router.get('/supplier-aktif', async (req, res) => {
  try {
    const data = await query(
      `SELECT DISTINCT s.* FROM supplier s
       INNER JOIN hutang_supplier_history h ON s.id = h.id_supplier
       WHERE s.status = 'active'
       ORDER BY s.nama_supplier`
    );
    return jsonResponse(res, true, 'List supplier aktif', data);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /supplier/check-debt
router.get('/supplier/check-debt', async (req, res) => {
  try {
    const cleanName = cleanInput(req.query.name).trim().toUpperCase();
    if (!cleanName) return jsonResponse(res, false, 'Nama supplier tidak valid');
    
    let supplier = await queryOne(`SELECT * FROM supplier WHERE UPPER(nama_supplier) = ?`, [cleanName]);
    if (!supplier) {
      supplier = {
        id: null,
        nama_supplier: cleanName,
        total_hutang: 0,
        status: 'active'
      };
    }
    return jsonResponse(res, true, 'Debt info', supplier);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /supplier/:id/history
router.get('/supplier/:id/history', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const history = await query(
      `SELECT h.*, u.nama_lengkap as nama_operator 
       FROM hutang_supplier_history h
       LEFT JOIN users u ON h.operator_id = u.id
       WHERE h.id_supplier = ? 
       ORDER BY h.created_at DESC`,
      [id]
    );
    return jsonResponse(res, true, 'History hutang supplier', history);
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /supplier/bayar
router.post('/supplier/bayar', async (req, res) => {
  try {
    const { id_supplier, jumlah, keterangan, tanggal } = req.body;
    const amount = parseFloat(jumlah);
    if (!id_supplier || isNaN(amount) || amount <= 0) {
      return jsonResponse(res, false, 'Data tidak valid');
    }
    const tgl = tanggal || new Date().toISOString().split('T')[0];

    const supplier = await queryOne(`SELECT * FROM supplier WHERE id = ?`, [id_supplier]);
    if (!supplier) return jsonResponse(res, false, 'Supplier tidak ditemukan');

    const newHutang = Math.max(0, supplier.total_hutang - amount);
    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE supplier SET total_hutang = ?, hutang_terakhir_update = datetime('now', 'localtime') WHERE id = ?`, [newHutang, id_supplier]);
      tx.execute(
        `INSERT INTO hutang_supplier_history (id_supplier, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id) 
         VALUES (?, ?, 'bayar', ?, ?, ?, ?)`,
        [id_supplier, tgl, amount, keterangan || 'Pembayaran Manual', newHutang, req.session.user_id]
      );
      tx.commit();
      return jsonResponse(res, true, 'Pembayaran hutang supplier berhasil dicatat');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /supplier/tambah
router.post('/supplier/tambah', async (req, res) => {
  try {
    const { id_supplier, jumlah, keterangan, tanggal } = req.body;
    const amount = parseFloat(jumlah);
    if (!id_supplier || isNaN(amount) || amount <= 0) {
      return jsonResponse(res, false, 'Data tidak valid');
    }
    const tgl = tanggal || new Date().toISOString().split('T')[0];

    const supplier = await queryOne(`SELECT * FROM supplier WHERE id = ?`, [id_supplier]);
    if (!supplier) return jsonResponse(res, false, 'Supplier tidak ditemukan');

    const newHutang = supplier.total_hutang + amount;
    const tx = beginTransaction();
    try {
      tx.execute(`UPDATE supplier SET total_hutang = ?, hutang_terakhir_update = datetime('now', 'localtime') WHERE id = ?`, [newHutang, id_supplier]);
      tx.execute(
        `INSERT INTO hutang_supplier_history (id_supplier, tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id) 
         VALUES (?, ?, 'tambah', ?, ?, ?, ?)`,
        [id_supplier, tgl, amount, keterangan || 'Penambahan Manual', newHutang, req.session.user_id]
      );
      tx.commit();
      return jsonResponse(res, true, 'Penambahan hutang supplier berhasil dicatat');
    } catch (txErr) {
      tx.rollback();
      throw txErr;
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /supplier/export-excel
router.get('/supplier/export-excel', async (req, res) => {
  try {
    const supplierList = await query(`SELECT * FROM supplier ORDER BY nama_supplier`);
    let html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Rekapan Hutang Supplier</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; text-decoration: underline; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; font-size: 11px; }
        .data-table th { background-color: #DDEBF7; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .currency-format { text-align: right; mso-number-format:"\\"Rp \\"\\#\\,\\#\\#0;\\"Rp \\"\\-\\#\\,\\#\\#0;\\"-\\""; }
    </style>
</head>
<body>
<div class="header">
    <div class="title">LAPORAN REKAPAN HUTANG SUPPLIER</div>
    <div>Tanggal Cetak: ${new Date().toLocaleDateString('id-ID')}</div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th>NO</th>
            <th>KODE SUPPLIER</th>
            <th>NAMA SUPPLIER</th>
            <th>TOTAL HUTANG</th>
            <th>STATUS</th>
        </tr>
    </thead>
    <tbody>`;

    let totalSemuaHutang = 0;
    supplierList.forEach((s, idx) => {
      totalSemuaHutang += s.total_hutang || 0;
      html += `
        <tr>
            <td class="text-center">${idx + 1}</td>
            <td class="text-center">${s.kode_supplier}</td>
            <td>${s.nama_supplier}</td>
            <td class="currency-format">${s.total_hutang || 0}</td>
            <td class="text-center">${s.status.toUpperCase()}</td>
        </tr>`;
    });

    html += `
        <tr style="font-weight:bold; background-color:#F2F2F2;">
            <td colspan="3" class="text-center">TOTAL HUTANG KESELURUHAN</td>
            <td class="currency-format">${totalSemuaHutang}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment;filename="Rekap_Hutang_Supplier_${new Date().toISOString().split('T')[0]}.xls"`);
    res.setHeader('Cache-Control', 'max-age=0');
    return res.send(html);
  } catch (err) {
    console.error(err);
    return jsonResponse(res, false, 'Gagal export Excel: ' + err.message);
  }
});

module.exports = router;
