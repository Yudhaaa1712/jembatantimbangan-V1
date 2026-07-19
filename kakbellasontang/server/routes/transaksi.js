/**
 * Transaksi Routes
 * Replaces: modules/transaksi/index.php, receipt.php, export_excel_advanced.php
 */
const express = require('express');
const router = express.Router();
const ExcelJS = require('exceljs');
const { query, queryOne, pool, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { isLoggedIn, requireRole, getCurrentUser } = require('../middleware/auth');
const { recalculateKasBalances } = require('../helpers/kasHelper');

router.use(isLoggedIn);

// Helper date filter
function buildDateCondition(dateFilter, startDate, endDate) {
  switch (dateFilter) {
    case 'today':      return { sql: `tt.tanggal = date('now', 'localtime')`, params: [] };
    case 'yesterday':  return { sql: `tt.tanggal = date('now', 'localtime', '-1 day')`, params: [] };
    case 'week':       return { sql: `tt.tanggal >= date('now', 'localtime', '-7 days')`, params: [] };
    case 'half_month': return { sql: `tt.tanggal >= date('now', 'localtime', '-15 days')`, params: [] };
    case 'month':      return { sql: `strftime('%m', tt.tanggal) = strftime('%m', 'now', 'localtime') AND strftime('%Y', tt.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'half_year':  return { sql: `tt.tanggal >= date('now', 'localtime', '-6 months')`, params: [] };
    case 'year':       return { sql: `strftime('%Y', tt.tanggal) = strftime('%Y', 'now', 'localtime')`, params: [] };
    case 'custom_range': return { sql: `tt.tanggal BETWEEN ? AND ?`, params: [startDate, endDate] };
    default:           return { sql: `tt.tanggal = date('now', 'localtime')`, params: [] };
  }
}

// Helper: Calculate row values consistently
const { calculateRowValues } = require('../helpers/math');

// GET /transaksi/list — Get transactions with date filter
router.get('/list', async (req, res) => {
  try {
    const dateFilter = req.query.date_filter || 'today';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);

    const transactions = await query(
      `SELECT tt.*,
              tt.no_polisi as no_polisi_display,
              s.nama_supplier,
              u.nama_lengkap as operator_nama,
              tt.berat_netto as netto1_calc
       FROM transaksi_timbangan tt
       LEFT JOIN supplier s ON tt.id_supplier = s.id
       LEFT JOIN users u ON tt.operator_id = u.id
       WHERE ${dateSql} AND ((tt.status = 'selesai' AND tt.timbang2_locked = 1) OR tt.status = 'dibatalkan')
       ORDER BY tt.created_at DESC`,
      dateParams
    );

    const [statsRows] = await pool.execute(
      `SELECT
         COUNT(*) as total_transaksi,
         COALESCE(SUM(tt.berat_bruto), 0) as total_bruto,
         COALESCE(SUM(tt.berat_netto), 0) as total_netto,
         COALESCE(SUM(tt.total_harga), 0) as total_harga,
         COALESCE(AVG(tt.berat_bruto), 0) as rata_bruto
       FROM transaksi_timbangan tt
       WHERE ${dateSql} AND tt.status = 'selesai' AND tt.timbang2_locked = 1`,
      dateParams
    );

    const settingT1 = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'timbangan1_fields'`);
    const settingT2 = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'timbangan2_fields'`);
    let t1Fields = { no_kendaraan: true, nama_pengemudi: true, nama_suplier: true, material: true, harga: true, keterangan: true };
    let t2Fields = { persen_potongan: true, harga: true, potongan_hutang: true, potongan_muat: true, keterangan: true };
    if (settingT1) try { t1Fields = JSON.parse(settingT1.setting_value); } catch(e){}
    if (settingT2) try { t2Fields = JSON.parse(settingT2.setting_value); } catch(e){}

    return res.json({
      success: true,
      data: transactions,
      stats: statsRows[0],
      total: transactions.length,
      fields: { t1: t1Fields, t2: t2Fields }
    });
  } catch (err) {
    console.error('[Transaksi] /list error:', err);
    return jsonResponse(res, false, 'Gagal mengambil data: ' + err.message);
  }
});

// GET /transaksi/detail/:id
router.get('/detail/:id', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const data = await queryOne(
      `SELECT tt.*, s.nama_supplier, c.nama_customer, u.nama_lengkap as nama_operator
       FROM transaksi_timbangan tt
       LEFT JOIN supplier s ON tt.id_supplier = s.id
       LEFT JOIN customers c ON tt.id_customer = c.id
       LEFT JOIN users u ON tt.operator_id = u.id
       WHERE tt.id = ?`, [id]
    );
    if (data) return jsonResponse(res, true, 'Detail found', data);
    return jsonResponse(res, false, 'Data not found');
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /transaksi/receipt/:no_tiket — Get receipt data for printing
router.get('/receipt/:no_tiket', async (req, res) => {
  try {
    const noTiket = req.params.no_tiket;
    const data = await queryOne(
      `SELECT tt.*, s.nama_supplier, s.total_hutang as sisa_hutang_supplier, c.nama_customer, u.nama_lengkap as nama_operator
       FROM transaksi_timbangan tt
       LEFT JOIN supplier s ON tt.id_supplier = s.id
       LEFT JOIN customers c ON tt.id_customer = c.id
       LEFT JOIN users u ON tt.operator_id = u.id
       WHERE tt.no_tiket = ?`, [noTiket]
    );
    if (!data) return jsonResponse(res, false, 'Tiket tidak ditemukan');

    const calc = calculateRowValues(data);

    const settings = await query(`SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name','company_address','company_phone')`);
    const settingsMap = {};
    settings.forEach(s => settingsMap[s.setting_key] = s.setting_value);
    
    // Fetch Langsir details if any
    let langsirTrips = [];
    if (data.is_langsir === 1) {
      langsirTrips = await query(`SELECT berat_bruto, waktu_timbang FROM transaksi_timbangan_langsir WHERE id_transaksi = ? ORDER BY id ASC`, [data.id]);
    }

    return jsonResponse(res, true, 'Receipt data', {
      ...data,
      bruto: calc.bruto,
      tara: calc.tara,
      netto_awal: calc.netto1,
      kg_potongan: calc.kgPot,
      netto_akhir: calc.netto2,
      total_awal: calc.totGross,
      pot_pupuk: calc.potPpk,
      pot_jalan: calc.potJln,
      pot_hutang: calc.potHut,
      pot_hutang_supplier: calc.potHutSupplier,
      pot_muat: calc.potMuat,
      total_pot_rp: calc.totPot,
      total_akhir: calc.sisa,
      company: settingsMap,
      langsir_trips: langsirTrips
    });
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// POST /transaksi/cancel — Cancel transaction
router.post('/cancel', requireRole('admin', 'operator'), async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const id = parseInt(req.body.id);
    const actionType = req.body.action_type;
    const reason = cleanInput(req.body.cancel_reason) || 'Dibatalkan oleh operator';

    const trx = await queryOne(`SELECT id, no_tiket, status, total_harga, potongan_hutang_rp FROM transaksi_timbangan WHERE id = ? LIMIT 1`, [id]);
    if (!trx) return jsonResponse(res, false, 'Data tidak ditemukan');

    // ── Kembalikan kas jika transaksi sudah pernah mengurangi kas ──
    async function refundKas(idTransaksi, noTiket) {
      try {
        const kasRecord = await queryOne(
          `SELECT id, jumlah FROM kas WHERE id_transaksi = ? AND jenis = 'keluar' LIMIT 1`,
          [idTransaksi]
        );
        if (kasRecord) {
          const lastKas = await queryOne(`SELECT saldo_setelah FROM kas WHERE tanggal = CURDATE() ORDER BY id DESC LIMIT 1`);
          const saldoSebelum = lastKas ? parseFloat(lastKas.saldo_setelah) : 0;
          const jumlahRefund = parseFloat(kasRecord.jumlah);
          const saldoSesudah = saldoSebelum + jumlahRefund;

          await query(
            `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, id_transaksi, no_tiket, saldo_setelah, operator_id)
             VALUES (CURDATE(), 'masuk', ?, ?, ?, ?, ?, ?)`,
            [jumlahRefund, `Pembatalan transaksi - ${noTiket}`, idTransaksi, noTiket, saldoSesudah, user.id]
          );
          console.log(`[Kas] Refund: +Rp ${jumlahRefund.toLocaleString('id-ID')} (${noTiket}). Saldo: Rp ${saldoSesudah.toLocaleString('id-ID')}`);
        }
      } catch (kasErr) {
        console.error('[Kas] Refund error (non-fatal):', kasErr.message);
      }
    }

    // --- Fungsi Helper Kirim ke Google Sheet ---
    async function notifyGoogleSheet(noTiket, actionType) {
      try {
        const setting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
        if (setting && setting.setting_value && setting.setting_value.startsWith('http')) {
          const sheetData = {
            sheet_type: 'transaksi',
            action: actionType, // 'delete' atau 'batal'
            no_tiket: noTiket
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
            console.log(`[GoogleSheet] Sync ${actionType} untuk tiket ${noTiket} - Status:`, resSheet.statusCode);
          });
          reqSheet.on('error', (e) => {
            console.error('[GoogleSheet] Error:', e.message);
          });
          reqSheet.write(JSON.stringify(sheetData));
          reqSheet.end();
        }
      } catch (err) {
        console.error('[GoogleSheet] Setup error:', err.message);
      }
    }
    // -------------------------------------------

    if (actionType === 'delete') {
      const fullTrx = await queryOne(`SELECT id_supir, id_supplier, potongan_hutang_rp, potongan_hutang_supplier_rp FROM transaksi_timbangan WHERE id = ?`, [id]);
      if (fullTrx) {
        const tx = beginTransaction();
        try {
          // Restore supir debt if deducted
          if (fullTrx.id_supir && parseFloat(fullTrx.potongan_hutang_rp) > 0) {
            tx.execute(`UPDATE supir SET total_hutang = total_hutang + ? WHERE id = ?`, [parseFloat(fullTrx.potongan_hutang_rp), fullTrx.id_supir]);
            tx.execute(`DELETE FROM hutang_supir_history WHERE id_transaksi = ?`, [id]);
          }
          // Restore supplier debt if deducted
          if (fullTrx.id_supplier && parseFloat(fullTrx.potongan_hutang_supplier_rp) > 0) {
            tx.execute(`UPDATE supplier SET total_hutang = total_hutang + ? WHERE id = ?`, [parseFloat(fullTrx.potongan_hutang_supplier_rp), fullTrx.id_supplier]);
            tx.execute(`DELETE FROM hutang_supplier_history WHERE id_transaksi = ?`, [id]);
          }
          // Delete kas record
          tx.execute(`DELETE FROM kas WHERE id_transaksi = ?`, [id]);
          // Delete transaction
          tx.execute(`DELETE FROM transaksi_timbangan WHERE id = ?`, [id]);
          tx.commit();
        } catch (txErr) {
          tx.rollback();
          throw txErr;
        }
        
        // Recalculate kas balances
        await recalculateKasBalances();
      }
      
      await notifyGoogleSheet(trx.no_tiket, 'delete');
      return jsonResponse(res, true, 'Transaksi berhasil dihapus permanen');
    } else if (actionType === 'ulang_timbang') {
      await refundKas(id, trx.no_tiket);
      await query(
        `UPDATE transaksi_timbangan SET 
         status='timbang_1', 
         berat_timbangan2=0, 
         timbang2_locked=0, 
         persen_potongan=0, 
         kg_potongan=0, 
         potongan_hutang_rp=0, 
         total_harga=0, 
         berat_netto=0,
         updated_at=NOW() 
         WHERE id=?`,
        [id]
      );
      await notifyGoogleSheet(trx.no_tiket, 'batal'); // Ulang timbang berarti batal
      return jsonResponse(res, true, 'Transaksi dikembalikan ke status Timbangan 1');
    } else {
      if (trx.status === 'dibatalkan') return jsonResponse(res, false, 'Transaksi sudah dibatalkan');
      await refundKas(id, trx.no_tiket);
      await query(
        `UPDATE transaksi_timbangan SET status='dibatalkan', cancelled_at=NOW(), cancelled_by=?, cancel_reason=?, updated_at=NOW() WHERE id=?`,
        [user.id, reason, id]
      );
      await notifyGoogleSheet(trx.no_tiket, 'batal');
      return jsonResponse(res, true, 'Transaksi berhasil dibatalkan');
    }
  } catch (err) {
    return jsonResponse(res, false, err.message);
  }
});

// GET /transaksi/export-excel — Export to Excel (2 tabel: TBS & Brondolan + Sisa Kas)
router.get('/export-excel', requireRole('admin'), async (req, res) => {
  try {
    const dateFilter = req.query.date_filter || 'today';
    const startDate  = req.query.start_date || new Date().toISOString().split('T')[0];
    const endDate    = req.query.end_date   || new Date().toISOString().split('T')[0];

    const { sql: dateSql, params: dateParams } = buildDateCondition(dateFilter, startDate, endDate);
    const statusRaw = req.query.status || 'selesai';
    const status = ['selesai', 'dibatalkan', 'timbang_1', 'timbang_2'].includes(statusRaw) ? statusRaw : 'selesai';
    
    const finalSql = `${dateSql} AND tt.status = '${status}' AND tt.timbang2_locked = 1`;

    const rows = await query(
      `SELECT tt.*, s.nama_supplier, u.nama_lengkap as operator_nama
       FROM transaksi_timbangan tt
       LEFT JOIN supplier s ON tt.id_supplier = s.id
       LEFT JOIN users u ON tt.operator_id = u.id
       WHERE ${finalSql}
       ORDER BY tt.tanggal ASC, tt.waktu_timbangan1 ASC`,
      dateParams
    );

    // ── Ambil saldo kas ──
    const lastKas = await queryOne(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
    const saldoKas = lastKas ? parseFloat(lastKas.saldo_setelah) : 0;

    // ── Ambil daftar material dari settings ──
    const materialSetting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'material_list'`);
    let materialList = ['tbs', 'brondolan'];
    if (materialSetting && materialSetting.setting_value) {
      try { 
        let parsed = JSON.parse(materialSetting.setting_value); 
        if (typeof parsed === 'string') parsed = JSON.parse(parsed);
        if (Array.isArray(parsed)) materialList = parsed;
      } catch(e) {}
    }

    // ── Pisahkan data berdasarkan material (dinamis) ──
    const materialGroups = {};
    const knownMaterialsLower = materialList.map(m => m.toLowerCase());

    // Group rows by material
    for (const row of rows) {
      const mat = (row.jenis_material || '').toLowerCase();
      if (!materialGroups[mat]) materialGroups[mat] = [];
      materialGroups[mat].push(row);
    }

    function formatAngka(val, decimals=0) {
        if (!val || val == 0) return 0;
        return parseFloat(val);
    }

    function formatRupiah(val) {
        if (!val || val <= 0) return 0;
        return parseFloat(val);
    }

    const settings = await query(`SELECT setting_key, setting_value FROM settings`);
    const cName = settings.find(s => s.setting_key === 'company_name')?.setting_value || 'NAMA PERUSAHAAN';
    const cAddr = settings.find(s => s.setting_key === 'company_address')?.setting_value || 'ALAMAT PERUSAHAAN';
    const cPhone = settings.find(s => s.setting_key === 'company_phone')?.setting_value || 'TELEPON PERUSAHAAN';

    const sMap = {};
    settings.forEach(s => sMap[s.setting_key] = s.setting_value);
    
    let t1Fields = { no_kendaraan: true, nama_pengemudi: true, nama_suplier: true, material: true, harga: true, keterangan: true };
    let t2Fields = { persen_potongan: true, harga: true, potongan_hutang: true, keterangan: true };
    let activeFeatures = { hutang: false };
    
    if (sMap.timbangan1_fields) {
      try { t1Fields = JSON.parse(sMap.timbangan1_fields); } catch(e) {}
    }
    if (sMap.timbangan2_fields) {
      try { t2Fields = JSON.parse(sMap.timbangan2_fields); } catch(e) {}
    }
    if (sMap.active_features) {
      try { activeFeatures = JSON.parse(sMap.active_features); } catch(e) {}
    }

    const showPrice = (t1Fields.harga !== false || t2Fields.harga !== false);
    const showHutang = (activeFeatures.hutang === true && t2Fields.potongan_hutang !== false);
    const showHutangSupplier = (activeFeatures.hutang === true && t2Fields.potongan_hutang !== false);
    const showMuat = (t2Fields.potongan_muat !== false);
    const showKas = (activeFeatures.keuangan !== false);
    const showKet = (t1Fields.keterangan !== false || t2Fields.keterangan !== false);

    let totalCols = 8; // base: NO, TIKET, TGL, W1, W2, NETTO1, NETTO2, OPERATOR
    if (t1Fields.no_kendaraan !== false) totalCols++;
    if (t1Fields.nama_pengemudi !== false) totalCols++;
    if (t1Fields.nama_suplier !== false) totalCols++;
    totalCols += 2; // BRUTO, TARA
    if (t2Fields.persen_potongan !== false) totalCols += 2;
    if (showPrice) totalCols += 3; // Harga, Total Awal, Total Akhir
    if (showHutang) totalCols++;
    if (showHutangSupplier) totalCols++;
    if (showMuat) totalCols++;
    if (showKas) totalCols++;
    if (showKet) totalCols++;

    let kendColspan = 0;
    if (t1Fields.no_kendaraan !== false) kendColspan++;
    if (t1Fields.nama_pengemudi !== false) kendColspan++;

    // ── Fungsi: Render satu tabel material ──
    function renderTable(materialName, dataRows, runningKas) {
      let tableHtml = '';

      tableHtml += `<tr><td colspan="${totalCols}" style="background:#1a1a2e;color:#fff;font-size:14px;font-weight:bold;padding:10px;text-align:center;">TABEL ${materialName.toUpperCase()}</td></tr>`;
      
      // Header row 1
      tableHtml += `<tr>
        <th rowspan="2">NO</th>
        <th rowspan="2">NO. TIKET</th>
        <th rowspan="2">TANGGAL</th>
        <th colspan="2">WAKTU</th>`;
      if (kendColspan > 0) {
        tableHtml += `<th colspan="${kendColspan}">KENDARAAN</th>`;
      }
      if (t1Fields.nama_suplier !== false) {
        tableHtml += `<th rowspan="2">SUPPLIER</th>`;
      }
      tableHtml += `<th colspan="3">TIMBANGAN (KG)</th>`;
      if (t2Fields.persen_potongan !== false) {
        tableHtml += `<th colspan="2">POTONGAN BERAT</th>`;
      }
      tableHtml += `<th rowspan="2">NETTO 2 (KG)</th>`;
      if (showPrice) {
        tableHtml += `<th rowspan="2">HARGA</th>
        <th rowspan="2">TOTAL AWAL</th>`;
      }
      if (showHutang) {
        tableHtml += `<th rowspan="2">POTONG HUTANG SUPIR</th>`;
      }
      if (showHutangSupplier) {
        tableHtml += `<th rowspan="2">POTONG HUTANG SUPPLIER</th>`;
      }
      if (showMuat) {
        tableHtml += `<th rowspan="2">UPAH BONGKAR</th>`;
      }
      if (showPrice) {
        tableHtml += `<th rowspan="2">TOTAL AKHIR</th>`;
      }
      if (showKas) {
        tableHtml += `<th rowspan="2">SISA KAS</th>`;
      }
      if (showKet) {
        tableHtml += `<th rowspan="2">KETERANGAN</th>`;
      }
      tableHtml += `<th rowspan="2">OPERATOR</th>
      </tr>`;

      // Header row 2
      tableHtml += `<tr>
        <th>MASUK</th><th>KELUAR</th>`;
      if (t1Fields.no_kendaraan !== false) tableHtml += `<th>NO. POLISI</th>`;
      if (t1Fields.nama_pengemudi !== false) tableHtml += `<th>SUPIR</th>`;
      tableHtml += `<th>BRUTO</th><th>TARA</th><th>NETTO 1</th>`;
      if (t2Fields.persen_potongan !== false) tableHtml += `<th>%</th><th>KG</th>`;
      tableHtml += `</tr>`;

      if (dataRows.length === 0) {
        tableHtml += `<tr><td colspan="${totalCols}" class="text-center font-italic">Tidak ada data ${materialName} untuk periode ini</td></tr>`;
        return { html: tableHtml, totals: { bruto:0, netto1:0, netto2:0, potonganKg:0, totalAwal:0, potRp:0, totalAkhir:0 }, runningKas };
      }

      let no = 1, gt = { bruto:0, netto1:0, netto2:0, potonganKg:0, totalAwal:0, potRp:0, potHut:0, potHutSupplier:0, potMuat:0, totalAkhir:0 };

      for (const data of dataRows) {
        const calc = calculateRowValues(data);
        
        // Running kas berkurang setiap transaksi
        runningKas -= calc.sisa;

        gt.bruto += calc.bruto; gt.netto1 += calc.netto1; gt.netto2 += calc.netto2;
        gt.potonganKg += calc.kgPot; gt.totalAwal += calc.totGross; gt.potRp += calc.totPot; gt.potHut += calc.potHut; gt.potHutSupplier += calc.potHutSupplier; gt.potMuat += calc.potMuat; gt.totalAkhir += calc.sisa;
        
        const tgl = data.tanggal ? new Date(data.tanggal).toLocaleDateString('id-ID') : '-';
        const extractTime8 = (str) => {
            if (!str) return '-';
            const timeStr = String(str).includes(' ') ? String(str).split(' ')[1] : String(str);
            return timeStr ? timeStr.slice(0, 8) : '-';
        };
        const w1 = extractTime8(data.waktu_timbangan1);
        const w2 = extractTime8(data.waktu_keluar);
        
        tableHtml += `<tr>
            <td class="text-center">${no++}</td>
            <td class="text-center font-bold">${data.no_tiket}</td>
            <td class="text-center">${tgl}</td>
            <td class="text-center text-string">${w1}</td>
            <td class="text-center text-string">${w2}</td>`;
        if (t1Fields.no_kendaraan !== false) {
            tableHtml += `<td class="text-center">${data.no_polisi || '-'}</td>`;
        }
        if (t1Fields.nama_pengemudi !== false) {
            tableHtml += `<td>${data.nama_supir || '-'}</td>`;
        }
        if (t1Fields.nama_suplier !== false) {
            tableHtml += `<td>${data.nama_supplier || '-'}</td>`;
        }
        tableHtml += `
            <td class="number-format">${formatAngka(calc.bruto, 2)}</td>
            <td class="number-format">${formatAngka(calc.tara, 2)}</td>
            <td class="number-format" style="font-weight: bold;">${formatAngka(calc.netto1, 2)}</td>`;
        if (t2Fields.persen_potongan !== false) {
            tableHtml += `
            <td class="text-right">${calc.persen}%</td>
            <td class="number-format" style="font-weight: bold; color: #F59E0B;">${formatAngka(calc.kgPot, 2)}</td>`;
        }
        tableHtml += `
            <td class="number-format" style="font-weight: bold;">${formatAngka(calc.netto2, 2)}</td>`;
        if (showPrice) {
            tableHtml += `
            <td class="currency-format">${formatRupiah(calc.hrg)}</td>
            <td class="currency-format" style="font-weight: bold;">${formatRupiah(calc.totGross)}</td>`;
        }
        if (showHutang) {
            tableHtml += `
            <td class="currency-format" style="color: #DC2626;">${formatRupiah(calc.potHut)}</td>`;
        }
        if (showHutangSupplier) {
            tableHtml += `
            <td class="currency-format" style="color: #3B82F6;">${formatRupiah(calc.potHutSupplier)}</td>`;
        }
        if (showMuat) {
            tableHtml += `
            <td class="currency-format" style="color: #F59E0B;">${formatRupiah(calc.potMuat)}</td>`;
        }
        if (showPrice) {
            tableHtml += `
            <td class="currency-format" style="font-weight: bold; color: #22C55E;">${formatRupiah(calc.sisa)}</td>`;
        }
        if (showKas) {
            tableHtml += `
            <td class="currency-format" style="font-weight: bold; color: #2563EB;">${formatRupiah(runningKas >= 0 ? runningKas : 0)}</td>`;
        }
        if (showKet) {
            tableHtml += `
            <td style="max-width: 200px; word-wrap: break-word;">${data.keterangan || '-'}</td>`;
        }
        tableHtml += `
            <td>${data.operator_nama || '-'}</td>
        </tr>`;
      }

      // Grand total row
      const labelColspan = 5 + kendColspan + (t1Fields.nama_suplier !== false ? 1 : 0);
      tableHtml += `<tr class="total-row">
        <td colspan="${labelColspan}" class="merge-cell">GRAND TOTAL ${materialName.toUpperCase()}</td>
        <td class="number-format">${formatAngka(gt.bruto, 2)}</td>
        <td></td>
        <td class="number-format">${formatAngka(gt.netto1, 2)}</td>`;
      if (t2Fields.persen_potongan !== false) {
        tableHtml += `<td></td>
        <td class="number-format">${formatAngka(gt.potonganKg, 2)}</td>`;
      }
      tableHtml += `
        <td class="number-format">${formatAngka(gt.netto2, 2)}</td>`;
      if (showPrice) {
        tableHtml += `<td></td>
        <td class="currency-format">${formatRupiah(gt.totalAwal)}</td>`;
      }
      if (showHutang) {
        tableHtml += `<td class="currency-format">${formatRupiah(gt.potHut)}</td>`;
      }
      if (showHutangSupplier) {
        tableHtml += `<td class="currency-format">${formatRupiah(gt.potHutSupplier)}</td>`;
      }
      if (showMuat) {
        tableHtml += `<td class="currency-format">${formatRupiah(gt.potMuat)}</td>`;
      }
      if (showPrice) {
        tableHtml += `<td class="currency-format">${formatRupiah(gt.totalAkhir)}</td>`;
      }
      if (showKas) {
        tableHtml += `<td class="currency-format">${formatRupiah(runningKas >= 0 ? runningKas : 0)}</td>`;
      }
      if (showKet) {
        tableHtml += `<td></td>`;
      }
      tableHtml += `
        <td></td>
      </tr>`;

      return { html: tableHtml, totals: gt, runningKas };
    }

    // ── Build full HTML ──
    let html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export Excel</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .company-header { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; text-align: center; }
        .company-address { font-size: 14px; text-align: center; }
        .company-phone { font-size: 14px; margin-bottom: 10px; text-align: center; mso-number-format:"\@"; }
        .report-title { font-size: 18px; font-weight: bold; text-decoration: underline; margin-top: 10px; text-align: center; }
        .report-period { font-size: 12px; margin-bottom: 20px; text-align: center; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; font-size: 11px; }
        .data-table th { background-color: #E2EFDA; font-weight: bold; text-align: center; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .font-italic { font-style: italic; }
        .total-row { background-color: #DC2626 !important; color: #FFFFFF !important; font-weight: bold; font-size: 12px; }
        .total-row td { border: 1px solid #000; padding: 8px 6px; }
        .rekap-row td { border: 1px solid #000; padding: 6px; font-size: 12px; }
        .merge-cell { text-align: center; font-weight: bold; }
        .number-format { text-align: right; mso-number-format:"\\#\\,\\#\\#0"; }
        .currency-format { text-align: right; white-space: nowrap; mso-number-format:"\\"Rp \\"\\#\\,\\#\\#0;\\"Rp \\"\\-\\#\\,\\#\\#0;\\"-\\""; }
        .text-string { mso-number-format:"\\@"; }
    </style>
</head>
<body>
<table class="data-table">
    <tbody>
    <tr><td align="center" colspan="${totalCols}" style="border:none;font-size:24px;font-weight:bold;padding:8px 0;">${cName}</td></tr>
    <tr><td align="center" colspan="${totalCols}" style="border:none;font-size:14px;padding:2px 0;">${cAddr}</td></tr>
    <tr><td align="center" colspan="${totalCols}" style="border:none;font-size:14px;padding:2px 0;mso-number-format:'\@';">'${cPhone}</td></tr>
    <tr><td align="center" colspan="${totalCols}" style="border:none;height:10px;"></td></tr>
    <tr><td align="center" colspan="${totalCols}" style="border:none;font-size:18px;font-weight:bold;text-decoration:underline;padding:6px 0;">LAPORAN TRANSAKSI TIMBANGAN</td></tr>
    <tr><td align="center" colspan="${totalCols}" style="border:none;font-size:12px;padding:2px 0 15px 0;">Periode: ${new Date(startDate).toLocaleDateString('id-ID')} - ${new Date(endDate).toLocaleDateString('id-ID')}</td></tr>`;

    // Running kas dimulai dari saldo + total belanja periode ini (untuk mundur dari awal)
    // Hitung total belanja di periode ini dulu
    let totalBelanjaPeriode = 0;
    for (const data of rows) {
      const calc = calculateRowValues(data);
      totalBelanjaPeriode += calc.sisa;
    }
    // Saldo awal = saldo sekarang + belanja periode ini (karena kita hitung mundur)
    const saldoAwal = saldoKas + totalBelanjaPeriode;
    let runningKas = saldoAwal;

    // ── Render tabel per material (dinamis dari material_list) ──
    // Kumpulkan semua material yang perlu ditampilkan:
    // 1. Material dari setting material_list yang punya data
    // 2. Material yang ada di data tapi tidak ada di material_list (kategori "Lainnya")
    const renderedMaterials = []; // { name, result }
    const processedMaterialKeys = new Set();

    for (const mat of materialList) {
      const matLower = mat.toLowerCase();
      const matRows = materialGroups[matLower] || [];
      processedMaterialKeys.add(matLower);

      // Hanya render tabel jika material punya data
      if (matRows.length > 0) {
        if (renderedMaterials.length > 0) {
          html += `<tr><td colspan="${totalCols}" style="border:none;height:20px;"></td></tr>`;
        }
        const result = renderTable(mat, matRows, runningKas);
        html += result.html;
        runningKas = result.runningKas;
        renderedMaterials.push({ name: mat, result });
      }
    }

    // Cek material yang ada di data tapi tidak ada di material_list
    const unknownRows = [];
    for (const [matKey, matRows] of Object.entries(materialGroups)) {
      if (!processedMaterialKeys.has(matKey)) {
        unknownRows.push(...matRows);
      }
    }
    if (unknownRows.length > 0) {
      html += `<tr><td colspan="${totalCols}" style="border:none;height:20px;"></td></tr>`;
      const lainResult = renderTable('Lainnya', unknownRows, runningKas);
      html += lainResult.html;
      runningKas = lainResult.runningKas;
      renderedMaterials.push({ name: 'Lainnya', result: lainResult });
    }

    // ── Rekapitulasi (dinamis) ──
    html += `<tr><td colspan="${totalCols}" style="border:none;height:20px;"></td></tr>`;
    html += `<tr><td colspan="${totalCols}" style="background:#1a1a2e;color:#fff;font-size:14px;font-weight:bold;padding:10px;text-align:center;">REKAPITULASI</td></tr>`;
    
    let grandTotalAkhir = 0;
    for (const { name, result } of renderedMaterials) {
      grandTotalAkhir += result.totals.totalAkhir;
      if (showPrice) {
        html += `<tr class="rekap-row">
          <td colspan="${Math.max(1, totalCols - 7)}" style="font-weight:bold;">Total Tonase ${name.toUpperCase()}</td>
          <td colspan="3" class="number-format" style="font-weight: bold;">${formatAngka(result.totals.netto2)} KG</td>
          <td colspan="4" class="currency-format" style="font-weight: bold;">${formatRupiah(result.totals.totalAkhir)}</td>
        </tr>`;
      } else {
        html += `<tr class="rekap-row">
          <td colspan="${Math.max(1, totalCols - 3)}" style="font-weight:bold;">Total Tonase ${name.toUpperCase()}</td>
          <td colspan="3" class="number-format" style="font-weight: bold;">${formatAngka(result.totals.netto2)} KG</td>
        </tr>`;
      }
    }
    
    if (showPrice) {
      html += `<tr class="rekap-row" style="background:#FEF3C7;">
        <td colspan="${Math.max(1, totalCols - 7)}" style="font-weight:bold;font-size:13px;">GRAND TOTAL BELANJA</td>
        <td colspan="3"></td>
        <td colspan="4" class="currency-format" style="font-weight: bold; font-size:13px;">${formatRupiah(grandTotalAkhir)}</td>
      </tr>`;
    }

    if (showKas) {
      if (showPrice) {
        html += `<tr class="rekap-row" style="background:#DBEAFE;">
          <td colspan="${Math.max(1, totalCols - 7)}" style="font-weight:bold;font-size:13px;">SALDO KAS AWAL</td>
          <td colspan="3"></td>
          <td colspan="4" class="currency-format" style="font-weight: bold; font-size:13px;">${formatRupiah(saldoAwal)}</td>
        </tr>`;
        html += `<tr class="rekap-row" style="background:#D1FAE5;">
          <td colspan="${Math.max(1, totalCols - 7)}" style="font-weight:bold;font-size:14px;color:#065F46;">SISA KAS</td>
          <td colspan="3"></td>
          <td colspan="4" class="currency-format" style="font-weight: bold; font-size:14px;color:#065F46;">${formatRupiah(saldoKas)}</td>
        </tr>`;
      } else {
        html += `<tr class="rekap-row" style="background:#DBEAFE;">
          <td colspan="${Math.max(1, totalCols - 4)}" style="font-weight:bold;font-size:13px;">SALDO KAS AWAL</td>
          <td colspan="4" class="currency-format" style="font-weight: bold; font-size:13px;">${formatRupiah(saldoAwal)}</td>
        </tr>`;
        html += `<tr class="rekap-row" style="background:#D1FAE5;">
          <td colspan="${Math.max(1, totalCols - 4)}" style="font-weight:bold;font-size:14px;color:#065F46;">SISA KAS</td>
          <td colspan="4" class="currency-format" style="font-weight: bold; font-size:14px;color:#065F46;">${formatRupiah(saldoKas)}</td>
        </tr>`;
      }
    }

    html += `</tbody>
</table>
</body></html>`;

    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment;filename="Laporan_Timbangan_${new Date().toISOString().split('T')[0]}.xls"`);
    res.setHeader('Cache-Control', 'max-age=0');
    return res.send(html);
  } catch (err) {
    console.error('[Transaksi] export-excel error:', err);
    return jsonResponse(res, false, 'Gagal export Excel: ' + err.message);
  }
});

module.exports = router;
