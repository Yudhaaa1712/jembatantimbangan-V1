/**
 * Timbangan Routes
 * Replaces: modules/timbangan/ajax.php (25+ actions)
 *           modules/timbangan/proses.php
 */
const express = require('express');
const router = express.Router();
const { query, queryOne, pool, jsonResponse, cleanInput, beginTransaction } = require('../config/database');
const { isLoggedIn, requireRole, getCurrentUser } = require('../middleware/auth');
const { generateTicketNumber, isTicketExists, activateReservedTicket } = require('../helpers/ticket');
const { cacheGet, cacheSet, cacheDelete } = require('../helpers/cache');
const { catatHutangTx } = require('../helpers/hutang');

// All timbangan routes require login
router.use(isLoggedIn);

// ─── GET /timbangan/data — Initial page data (suppliers, material config, etc.)
router.get('/data', async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const cacheKey = `active_suppliers_list`;

    let suppliers = cacheGet(cacheKey);
    if (!suppliers) {
      suppliers = await query(`SELECT id, nama_supplier, default_harga, default_potongan, total_hutang FROM supplier WHERE status = 'active' AND is_temporary = 0 ORDER BY nama_supplier`);
      cacheSet(cacheKey, suppliers, 3600);
    }

    const materialQuery = await query(`SELECT setting_value FROM settings WHERE setting_key = 'material_list' LIMIT 1`);
    let materials = ['tbs', 'brondolan'];
    if (materialQuery[0]?.setting_value) {
      try {
        let parsed = JSON.parse(materialQuery[0].setting_value);
        if (typeof parsed === 'string') parsed = JSON.parse(parsed);
        if (Array.isArray(parsed)) materials = parsed;
      } catch(e) { console.error('Parse materials error:', e); }
    }

    const ticketPrefix = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'ticket_prefix'`);
    console.log('[DEBUG] materials array check:', Array.isArray(materials), materials, typeof materials);

    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    res.json({
      success: true,
      data: {
        suppliers,
        materials,
        ticket_prefix: ticketPrefix?.setting_value || 'TKT',
        user: { id: user.id, nama: user.nama_lengkap, role: user.role }
      }
    });
  } catch (err) {
    console.error('[Timbangan] /data error:', err);
    jsonResponse(res, false, 'Gagal mengambil data: ' + err.message);
  }
});

// ─── POST /timbangan/ajax — Main AJAX handler (replaces ajax.php switch)
router.post('/ajax', async (req, res) => {
  const action = req.body.action || '';
  const user = getCurrentUser(req);

  try {
    switch (action) {

      // ── Clear ticket cache ───────────────────────────────────────────────
      case 'clear_tiket_cache': {
        const cacheKeys = JSON.parse(req.body.cache_keys || '[]');
        let cleared = 0;
        for (const key of cacheKeys) {
          cacheDelete(key);
          cleared++;
        }
        return jsonResponse(res, true, `Cache cleared (${cleared} keys)`);
      }

      // ── Get pending tickets (waiting for timbangan 2) ────────────────────
      case 'get_pending_tickets': {
        const tickets = await query(
          `SELECT tt.id, tt.no_tiket, tt.berat_timbangan1, tt.berat_bruto, tt.no_polisi,
                  tt.nama_supir, tt.id_supir, tt.jenis_material, tt.harga_per_kg, tt.keterangan, tt.mode_timbangan, s.nama_supplier,
                  s.default_harga, s.default_potongan, tt.is_langsir, tt.jumlah_trip_langsir
           FROM transaksi_timbangan tt
           LEFT JOIN supplier s ON tt.id_supplier = s.id
           WHERE tt.status = 'timbang_1' AND tt.berat_timbangan1 > 0
           ORDER BY tt.created_at DESC`
        );
        return jsonResponse(res, true, 'Pending tickets retrieved', tickets.map(t => ({
          id:            t.id,
          no_tiket:      t.no_tiket,
          berat_bruto:   t.berat_timbangan1 || t.berat_bruto,
          no_polisi:     t.no_polisi,
          nama_supir:    t.nama_supir,
          id_supir:      t.id_supir,
          nama_supplier: t.nama_supplier || 'Unknown',
          default_harga: t.default_harga || 0,
          default_potongan: t.default_potongan || 0,
          jenis_material: t.jenis_material,
          harga_per_kg:  t.harga_per_kg,
          keterangan:    t.keterangan,
          mode_timbangan: t.mode_timbangan || 'beli',
          is_langsir: t.is_langsir,
          jumlah_trip_langsir: t.jumlah_trip_langsir
        })));
      }

      // ── Get kendaraan by ID ──────────────────────────────────────────────
      case 'get_kendaraan': {
        const id = cleanInput(req.body.id);
        const data = await queryOne(`SELECT * FROM kendaraan WHERE id = ? LIMIT 1`, [id]);
        if (data) return jsonResponse(res, true, 'Data found', data);
        return jsonResponse(res, false, 'Data not found');
      }

      // ── Search kendaraan ─────────────────────────────────────────────────
      case 'search_kendaraan': {
        const kw = `%${cleanInput(req.body.keyword)}%`;
        const data = await query(
          `SELECT * FROM kendaraan WHERE (no_polisi LIKE ? OR nama_supir LIKE ?) AND status = 'active' LIMIT 10`,
          [kw, kw]
        );
        return jsonResponse(res, true, 'Search completed', data);
      }

      // ── Save Timbangan Masuk ─────────────────────────────────────────────────
      case 'save_timbangan1': {
        const noTiket       = cleanInput(req.body.no_tiket);
        const noDo          = cleanInput(req.body.no_do);
        const namaSuppir    = cleanInput(req.body.nama_supir);
        const idKendaraan   = cleanInput(req.body.id_kendaraan);
        const idSupplier    = cleanInput(req.body.id_supplier);
        const jenisMaterial = cleanInput(req.body.jenis_material);
        const beratT1       = parseFloat(req.body.berat_timbangan1) || 0;
        const keterangan    = cleanInput(req.body.keterangan || '');
        const hargaPerKg    = parseFloat(req.body.harga_per_kg) || 0;

        const kendaraan = await queryOne(`SELECT no_polisi FROM kendaraan WHERE id = ? LIMIT 1`, [idKendaraan]);
        if (!kendaraan) return jsonResponse(res, false, 'Kendaraan tidak ditemukan');

        await query(
          `INSERT INTO transaksi_timbangan
             (no_tiket, no_do, nama_supir, id_kendaraan, no_polisi, id_supplier,
              jenis_material, berat_timbangan1, timbang1_locked, waktu_timbangan1,
              tanggal, status, operator_id, keterangan, harga_per_kg, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), CURDATE(), 'timbang_1', ?, ?, ?, NOW())
           ON CONFLICT(no_tiket) DO UPDATE SET
             no_do = excluded.no_do, nama_supir = excluded.nama_supir,
             id_kendaraan = excluded.id_kendaraan, no_polisi = excluded.no_polisi,
             id_supplier = excluded.id_supplier, jenis_material = excluded.jenis_material,
             berat_timbangan1 = excluded.berat_timbangan1, timbang1_locked = 1,
             waktu_timbangan1 = excluded.waktu_timbangan1, status = excluded.status,
             operator_id = excluded.operator_id, keterangan = excluded.keterangan,
             harga_per_kg = excluded.harga_per_kg, updated_at = NOW()`,
          [noTiket, noDo, namaSuppir, idKendaraan, kendaraan.no_polisi,
           idSupplier, jenisMaterial, beratT1, user.id, keterangan, hargaPerKg]
        );

        if (req.body.is_langsir == 1 || req.body.is_langsir == '1' || req.body.is_langsir === true) {
          const txRow = await queryOne(`SELECT id FROM transaksi_timbangan WHERE no_tiket = ?`, [noTiket]);
          if (txRow) {
            const detailCount = await queryOne(`SELECT COUNT(*) as c FROM transaksi_timbangan_langsir WHERE id_transaksi = ?`, [txRow.id]);
            if (detailCount.c == 0) {
              await query(`INSERT INTO transaksi_timbangan_langsir (id_transaksi, berat_bruto, waktu_timbang) VALUES (?, ?, NOW())`, [txRow.id, beratT1]);
              await query(`UPDATE transaksi_timbangan SET is_langsir = 1, jumlah_trip_langsir = 1 WHERE id = ?`, [txRow.id]);
            }
          }
        }

        return jsonResponse(res, true, 'Data Timbangan Masuk berhasil disimpan');
      }

      // ── Append Langsir Trip ──────────────────────────────────────────────
      case 'append_langsir_trip': {
        const idTransaksi = parseInt(req.body.id_transaksi);
        const newBruto = parseFloat(req.body.berat_timbangan1) || 0;
        
        if (!idTransaksi || newBruto <= 0) return jsonResponse(res, false, 'Data tidak valid');
        
        const existing = await queryOne(`SELECT berat_timbangan1, jumlah_trip_langsir, status FROM transaksi_timbangan WHERE id = ?`, [idTransaksi]);
        if (!existing) return jsonResponse(res, false, 'Transaksi tidak ditemukan');
        if (existing.status !== 'timbang_1') return jsonResponse(res, false, 'Transaksi sudah selesai, tidak bisa ditambah trip');
        
        const accumulatedBruto = existing.berat_timbangan1 + newBruto;
        const newTripCount = (existing.jumlah_trip_langsir || 1) + 1;
        
        await query(`UPDATE transaksi_timbangan SET berat_timbangan1 = ?, jumlah_trip_langsir = ?, updated_at = NOW() WHERE id = ?`, [accumulatedBruto, newTripCount, idTransaksi]);
        await query(`INSERT INTO transaksi_timbangan_langsir (id_transaksi, berat_bruto, waktu_timbang) VALUES (?, ?, NOW())`, [idTransaksi, newBruto]);
        
        return jsonResponse(res, true, `Trip langsir ke-${newTripCount} berhasil ditambahkan!`);
      }

      // ── Save Timbangan Keluar ─────────────────────────────────────────────────
      case 'save_timbangan2': {
        const idTransaksi   = parseInt(req.body.id_transaksi);
        const idCustomer    = parseInt(req.body.id_customer) || 0;
        const beratT2       = parseFloat(req.body.berat_timbangan2) || 0;
        const persenPot     = parseFloat(req.body.persen_potongan) || 0;
        const kgPotongan    = parseFloat(req.body.kg_potongan) || 0;
        const hargaPerKg    = parseFloat(req.body.harga_per_kg) || 0;
        const potonganHutang = parseFloat(req.body.potongan_hutang_rp) || 0;
        const potonganMuat   = parseFloat(req.body.potongan_muat_rp) || 0;
        const keterangan    = cleanInput(req.body.keterangan || '');

        if (!idTransaksi) return jsonResponse(res, false, 'ID transaksi tidak valid');

        const transaksi = await queryOne(
          `SELECT berat_timbangan1, no_tiket, nama_supir, id_supplier, mode_timbangan, potongan_jalan, potongan_pupuk_rp, potongan_hutang_rp, is_langsir, jumlah_trip_langsir 
           FROM transaksi_timbangan WHERE id = ? LIMIT 1`, [idTransaksi]
        );
        if (!transaksi) return jsonResponse(res, false, 'Data transaksi tidak ditemukan');

        const isJual = transaksi.mode_timbangan === 'jual';
        
        const netto = isJual 
          ? (beratT2 - transaksi.berat_timbangan1) 
          : (transaksi.berat_timbangan1 - beratT2);
        
        if (netto <= 0) {
          return jsonResponse(res, false, 'Berat Netto harus lebih besar dari 0. Periksa kembali berat timbangan 1 dan timbangan 2.');
        }
        
        // For 'beli', the true Bruto is T1 (which may be accumulated). Tara is T2 (single). 
        // But for database consistency, we store T2 as berat_timbangan2, and we can set berat_tara to T2.
        const updateBruto = isJual ? beratT2 : transaksi.berat_timbangan1;
        const updateTara  = isJual ? transaksi.berat_timbangan1 : beratT2;

        const nettoAkhir = netto - kgPotongan;
        const totalHarga = nettoAkhir * hargaPerKg;

        // Check if Hutang feature is active
        let isHutangActive = false;
        const featureSetting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'active_features'`);
        if (featureSetting && featureSetting.setting_value) {
          try {
            const features = JSON.parse(featureSetting.setting_value);
            isHutangActive = features.hutang === true;
          } catch(e) {}
        }

        let supirId = null;
        let currentDebt = 0;
        if (isHutangActive && transaksi.nama_supir) {
          const name = cleanInput(transaksi.nama_supir).trim().toUpperCase();
          if (name) {
            let supir = await queryOne(`SELECT * FROM supir WHERE UPPER(nama_supir) = ?`, [name]);
            if (supir) {
              supirId = supir.id;
              currentDebt = supir.total_hutang || 0;
            }
          }
        }

        let finalPotonganHutang = 0;
        let finalNewDebt = currentDebt;

        if (isHutangActive && supirId) {
          finalPotonganHutang = Math.max(0, Math.min(potonganHutang, currentDebt, totalHarga));
          finalNewDebt = Math.max(0, currentDebt - finalPotonganHutang);
        }

        // Supplier Debt deduction
        let supplierId = transaksi.id_supplier;
        let currentSupplierDebt = 0;
        if (supplierId) {
          const sup = await queryOne(`SELECT total_hutang FROM supplier WHERE id = ?`, [supplierId]);
          if (sup) {
            currentSupplierDebt = sup.total_hutang || 0;
          }
        }

        const potonganHutangSupplier = parseFloat(req.body.potongan_hutang_supplier_rp) || 0;
        let finalPotonganHutangSupplier = 0;
        let finalNewSupplierDebt = currentSupplierDebt;

        if (supplierId) {
          finalPotonganHutangSupplier = Math.max(0, Math.min(potonganHutangSupplier, currentSupplierDebt, totalHarga - finalPotonganHutang));
          finalNewSupplierDebt = Math.max(0, currentSupplierDebt - finalPotonganHutangSupplier);
        }

        // Execute save in transaction
        const tx = beginTransaction();
        try {
          tx.execute(
            `UPDATE transaksi_timbangan SET
               id_customer = ?, berat_timbangan2 = ?, timbang2_locked = 1,
               berat_bruto = ?, berat_tara = ?,
               persen_potongan = ?, kg_potongan = ?, harga_per_kg = ?,
               total_harga = ?, berat_netto = ?, netto_akhir = ?,
               potongan_hutang_rp = ?, potongan_muat_rp = ?, sisa_hutang_snapshot = ?,
               potongan_hutang_supplier_rp = ?, sisa_hutang_supplier_snapshot = ?,
               keterangan = ?, waktu_timbangan2 = datetime('now', 'localtime'), waktu_keluar = datetime('now', 'localtime'),
               status = 'selesai', updated_at = datetime('now', 'localtime')
             WHERE id = ?`,
            [idCustomer, beratT2, updateBruto, updateTara,
             persenPot, kgPotongan, hargaPerKg,
             totalHarga, netto, nettoAkhir, finalPotonganHutang, potonganMuat, finalNewDebt,
             finalPotonganHutangSupplier, finalNewSupplierDebt,
             keterangan, idTransaksi]
          );

          // Potongan hutang supir (otomatis) → buku besar terpadu
          if (isHutangActive && supirId && finalPotonganHutang > 0) {
            catatHutangTx(tx, {
              type: 'supir', partyId: supirId, jenis: 'bayar', jumlah: finalPotonganHutang,
              keterangan: `Potong otomatis tiket ${transaksi.no_tiket}`,
              idReferensi: idTransaksi, sumber: 'timbangan', operatorId: req.session.user_id
            });
          }

          // Potongan hutang supplier (otomatis) → buku besar terpadu
          if (supplierId && finalPotonganHutangSupplier > 0) {
            catatHutangTx(tx, {
              type: 'supplier', partyId: supplierId, jenis: 'bayar', jumlah: finalPotonganHutangSupplier,
              keterangan: `Potong otomatis tiket ${transaksi.no_tiket}`,
              idReferensi: idTransaksi, sumber: 'timbangan', operatorId: req.session.user_id
            });
          }

          tx.commit();
        } catch (txErr) {
          tx.rollback();
          throw txErr;
        }

        

        // -- Google Sheet Integration (Fire and Forget) --
        try {
          const setting = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
          if (setting && setting.setting_value && setting.setting_value.startsWith('http')) {
            const fullData = await queryOne(`
              SELECT tt.*, s.nama_supplier 
              FROM transaksi_timbangan tt 
              LEFT JOIN supplier s ON tt.id_supplier = s.id 
              WHERE tt.id = ?`, [idTransaksi]);
            
            if (fullData) {
              const settingT1 = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'timbangan1_fields'`);
              const settingT2 = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'timbangan2_fields'`);
              let t1Fields = { no_kendaraan: true, nama_pengemudi: true, nama_suplier: true, material: true, harga: true, keterangan: true };
              let t2Fields = { persen_potongan: true, harga: true, potongan_hutang: true, potongan_muat: true, keterangan: true };
              if (settingT1) try { t1Fields = JSON.parse(settingT1.setting_value); } catch(e){}
              if (settingT2) try { t2Fields = JSON.parse(settingT2.setting_value); } catch(e){}

              const extractTimeOnly = (str) => {
                if (!str) return '-';
                const timeStr = String(str).includes(' ') ? String(str).split(' ')[1] : String(str);
                return timeStr ? timeStr.slice(0, 8) : '-';
              };

              const sheetData = {
                sheet_type: 'transaksi',
                no_tiket: fullData.no_tiket,
                tanggal: new Date(fullData.tanggal).toLocaleDateString('id-ID'), // e.g. 30/05/2026
                waktu_masuk: extractTimeOnly(fullData.waktu_timbangan1),
                waktu_keluar: extractTimeOnly(fullData.waktu_timbangan2),
                no_polisi: fullData.no_polisi || '',
                nama_supir: fullData.nama_supir || '',
                nama_supplier: fullData.nama_supplier || '',
                jenis_material: fullData.jenis_material || '',
                bruto: new Intl.NumberFormat('id-ID').format(Math.round(fullData.berat_bruto || 0)) + ' kg',
                tara: new Intl.NumberFormat('id-ID').format(Math.round(fullData.berat_tara || 0)) + ' kg',
                netto1: new Intl.NumberFormat('id-ID').format(Math.round(fullData.berat_netto || 0)) + ' kg',
                potongan_persen: parseFloat(fullData.persen_potongan || 0) + '%',
                potongan_kg: new Intl.NumberFormat('id-ID').format(Math.round(fullData.kg_potongan || 0)) + ' kg',
                netto2: new Intl.NumberFormat('id-ID').format(Math.round(fullData.berat_netto || 0)) + ' kg',
                harga: 'Rp.' + new Intl.NumberFormat('id-ID').format(Math.round(fullData.harga_per_kg || 0)),
                total_awal: 'Rp.' + new Intl.NumberFormat('id-ID').format(Math.round(parseFloat(fullData.total_harga) || 0)),
                total_potongan_rp: 'Rp.' + new Intl.NumberFormat('id-ID').format(Math.round((parseFloat(fullData.potongan_jalan) || 0) + (parseFloat(fullData.potongan_pupuk_rp) || 0) + (parseFloat(fullData.potongan_hutang_rp) || 0) + (parseFloat(fullData.potongan_muat_rp) || 0) )),
                total_akhir: 'Rp.' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round((parseFloat(fullData.total_harga) || 0) - ((parseFloat(fullData.potongan_jalan) || 0) + (parseFloat(fullData.potongan_pupuk_rp) || 0) + (parseFloat(fullData.potongan_hutang_rp) || 0) + (parseFloat(fullData.potongan_muat_rp) || 0) )))),
                keterangan: fullData.keterangan || '',
                operator: user.nama_lengkap || 'Operator'
              };

              if (t1Fields.no_kendaraan === false) delete sheetData.no_polisi;
              if (t1Fields.nama_pengemudi === false) delete sheetData.nama_supir;
              if (t1Fields.nama_suplier === false) delete sheetData.nama_supplier;
              if (t1Fields.material === false) delete sheetData.jenis_material;
              if (t2Fields.persen_potongan === false) {
                 delete sheetData.potongan_persen;
                 delete sheetData.potongan_kg;
              }
              if (t1Fields.harga === false && t2Fields.harga === false) {
                 delete sheetData.harga;
                 delete sheetData.total_awal;
                 delete sheetData.total_potongan_rp;
                 delete sheetData.total_akhir;
              }
              if (t1Fields.keterangan === false && t2Fields.keterangan === false) {
                 delete sheetData.keterangan;
              }
              // Send using native https module because Node 16 (Electron 22) does not support global fetch natively
              try {
                console.log('[GoogleSheet] Sending payload for tiket:', sheetData.no_tiket);
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
                  console.log('[GoogleSheet] Status:', resSheet.statusCode);
                });

                reqSheet.on('error', (e) => {
                  console.error('[GoogleSheet] Request Execution Error:', e.message);
                });

                reqSheet.write(JSON.stringify(sheetData));
                reqSheet.end();
              } catch(fetchErr) {
                console.error('[GoogleSheet] Setup Error:', fetchErr.message);
              }
            }
          }
        } catch (e) {
          console.error('[GoogleSheet] DB fetch/formatting error:', e.message);
        }        // ── Auto-deduct/deposit Kas (Uang Kas bertambah/berkurang berdasarkan mode_timbangan) ─────────────
        try {
          const potJln = parseFloat(transaksi?.potongan_jalan || 0);
          const potPpk = parseFloat(transaksi?.potongan_pupuk_rp || 0);
          const potHut = isHutangActive ? finalPotonganHutang : parseFloat(transaksi?.potongan_hutang_rp || 0);
          const potHutSupplier = finalPotonganHutangSupplier;
          const potMuat = potonganMuat;
          const totalAkhir = Math.max(0, totalHarga - (potJln + potPpk + potHut + potHutSupplier + potMuat));

          if (totalAkhir > 0) {
            // Ambil saldo terakhir absolut (carry-over)
            const lastKas = await queryOne(
              `SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`
            );
            const saldoSebelum = lastKas ? parseFloat(lastKas.saldo_setelah) : 0;
            
            const isBeli = (transaksi?.mode_timbangan || 'beli') !== 'jual';
            const jenisKas = isBeli ? 'keluar' : 'masuk';
            const saldoSesudah = isBeli ? (saldoSebelum - totalAkhir) : (saldoSebelum + totalAkhir);

            // Ambil no_tiket + info supplier untuk referensi
            const tiketInfo = await queryOne(
              `SELECT tt.no_tiket, tt.jenis_material, tt.nama_supir, s.nama_supplier
               FROM transaksi_timbangan tt
               LEFT JOIN supplier s ON tt.id_supplier = s.id
               WHERE tt.id = ?`, [idTransaksi]
            );

            // Format keterangan: "TBS UJANG" atau "BRONDOLAN SIRUN"
            const materialLabel = (tiketInfo?.jenis_material || '').toUpperCase();
            const supirLabel = (tiketInfo?.nama_supir || '').toUpperCase();
            const actionLabel = isBeli ? '' : 'PENJUALAN ';
            const kasKeterangan = `${actionLabel}${materialLabel} ${supirLabel}`.trim() || `${isBeli ? 'Pembelian' : 'Penjualan'} buah - ${tiketInfo?.no_tiket || 'N/A'}`;

            await query(
              `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, id_transaksi, no_tiket, saldo_setelah, operator_id, created_at)
               VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW())`,
              [
                jenisKas,
                totalAkhir,
                kasKeterangan,
                idTransaksi,
                tiketInfo?.no_tiket || null,
                saldoSesudah,
                req.session.user_id
              ]
            );
            console.log(`[Kas] Auto-${isBeli ? 'deduct' : 'deposit'}: ${isBeli ? '-' : '+'}Rp ${totalAkhir.toLocaleString('id-ID')} (${tiketInfo?.no_tiket}). Sisa: Rp ${saldoSesudah.toLocaleString('id-ID')}`);
            
            // Sync auto-deduct/deposit kas ke Google Sheet
            const settingKas = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_url'`);
            if (settingKas && settingKas.setting_value && settingKas.setting_value.startsWith('http')) {
              try {
                const sheetDataKas = {
                  sheet_type: 'keuangan',
                  tanggal: new Date().toLocaleDateString('id-ID'),
                  keterangan: kasKeterangan,
                  debit: isBeli ? '' : 'Rp. ' + new Intl.NumberFormat('id-ID').format(totalAkhir),
                  kredit: isBeli ? 'Rp. ' + new Intl.NumberFormat('id-ID').format(totalAkhir) : '',
                  saldo: 'Rp. ' + new Intl.NumberFormat('id-ID').format(saldoSesudah),
                  waktu: new Date().toLocaleTimeString('id-ID'),
                  operator: user.nama_lengkap || 'Operator'
                };
                const https = require('https');
                const urlObj = new URL(settingKas.setting_value);
                const reqSheetKas = https.request({
                  hostname: urlObj.hostname,
                  path: urlObj.pathname + urlObj.search,
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(JSON.stringify(sheetDataKas))
                  }
                }, (resSheet) => {});
                reqSheetKas.on('error', () => {});
                reqSheetKas.write(JSON.stringify(sheetDataKas));
                reqSheetKas.end();
              } catch (e) {}
            }
          }
        } catch (kasErr) {
          console.error('[Kas] Auto-deduct/deposit error (non-fatal):', kasErr.message);
        }

        return jsonResponse(res, true, 'Data Timbangan Keluar berhasil disimpan');
      }

      // ── Get recent timbangan 1 data ──────────────────────────────────────
      case 'get_recent_timbangan1': {
        const data = await query(
          `SELECT tt.*, k.no_polisi, s.nama_supplier
           FROM transaksi_timbangan tt
           LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
           LEFT JOIN supplier s ON tt.id_supplier = s.id
           WHERE tt.status = 'timbang_1'
           ORDER BY tt.waktu_timbangan1 DESC LIMIT 10`
        );
        return jsonResponse(res, true, 'Data retrieved', data);
      }

      // ── Get all transactions (with filters) ──────────────────────────────
      case 'get_all_transactions': {
        const date     = req.body.date || '';
        const status   = req.body.status || '';
        const material = req.body.material || '';
        const search   = req.body.search || '';

        let whereClauses = [`tt.status != 'reserved'`];
        let params = [];

        if (date)     { whereClauses.push(`tt.tanggal = ?`);           params.push(date); }
        if (status)   { whereClauses.push(`tt.status = ?`);            params.push(status); }
        if (material) { whereClauses.push(`tt.jenis_material = ?`);    params.push(material); }
        if (search)   { whereClauses.push(`(tt.no_tiket LIKE ? OR tt.no_polisi LIKE ?)`); params.push(`%${search}%`, `%${search}%`); }

        const whereStr = `WHERE ${whereClauses.join(' AND ')}`;

        const data = await query(
          `SELECT tt.*, k.no_polisi, s.nama_supplier, c.nama_customer, u.nama_lengkap as nama_operator
           FROM transaksi_timbangan tt
           LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
           LEFT JOIN supplier s ON tt.id_supplier = s.id
           LEFT JOIN customers c ON tt.id_customer = c.id
           LEFT JOIN users u ON tt.operator_id = u.id
           ${whereStr}
           ORDER BY tt.created_at DESC LIMIT 100`, params
        );

        const [summaryRows] = await pool.execute(
          `SELECT COUNT(*) as total,
             SUM(CASE WHEN status='timbang_1' THEN 1 ELSE 0 END) as t1_count,
             SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) as selesai_count
           FROM transaksi_timbangan tt ${whereStr}`, params
        );

        return jsonResponse(res, true, 'Data retrieved', { data, summary: summaryRows[0] });
      }

      // ── Get transaction detail ────────────────────────────────────────────
      case 'get_transaction_detail': {
        const id = parseInt(req.body.id);
        if (!id) return jsonResponse(res, false, 'ID tidak valid');
        const data = await queryOne(
          `SELECT tt.*, k.no_polisi, s.nama_supplier, c.nama_customer, u.nama_lengkap as nama_operator
           FROM transaksi_timbangan tt
           LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
           LEFT JOIN supplier s ON tt.id_supplier = s.id
           LEFT JOIN customers c ON tt.id_customer = c.id
           LEFT JOIN users u ON tt.operator_id = u.id
           WHERE tt.id = ?`, [id]
        );
        if (data) return jsonResponse(res, true, 'Detail found', data);
        return jsonResponse(res, false, 'Data not found');
      }

      // ── Delete / cancel timbangan 1 ──────────────────────────────────────
      case 'delete_timbangan1': {
        if (user.role !== 'admin' && user.role !== 'operator') return jsonResponse(res, false, 'Hanya admin atau operator yang bisa membatalkan transaksi');
        const id = parseInt(req.body.id);
        const reason = cleanInput(req.body.cancel_reason) || 'Dibatalkan dari Timbangan Masuk';
        if (!id) return jsonResponse(res, false, 'ID tidak valid');

        const trx = await queryOne(`SELECT id, no_tiket, status FROM transaksi_timbangan WHERE id = ? LIMIT 1`, [id]);
        if (!trx) return jsonResponse(res, false, 'Data tidak ditemukan');
        if (trx.status === 'dibatalkan') return jsonResponse(res, false, 'Transaksi sudah dibatalkan');

        await query(
          `UPDATE transaksi_timbangan SET status='dibatalkan', cancelled_at=NOW(), cancelled_by=?, cancel_reason=?, updated_at=NOW() WHERE id=?`,
          [user.id, reason, id]
        );
        return jsonResponse(res, true, 'Transaksi berhasil dibatalkan');
      }

      // ── Get tara average ─────────────────────────────────────────────────
      case 'get_tara_avg': {
        const idKendaraan = parseInt(req.body.id_kendaraan);
        if (!idKendaraan) return jsonResponse(res, false, 'ID kendaraan tidak valid');
        const row = await queryOne(`SELECT tara_avg FROM kendaraan WHERE id = ? LIMIT 1`, [idKendaraan]);
        if (row) return jsonResponse(res, true, 'Tara found', { tara_avg: row.tara_avg });
        return jsonResponse(res, false, 'Kendaraan not found');
      }

      // ── Check ticket availability ─────────────────────────────────────────
      case 'check_ticket': {
        const noTiket = cleanInput(req.body.no_tiket);
        const exists = await isTicketExists(noTiket);
        if (exists) return jsonResponse(res, false, 'Nomor tiket sudah digunakan!');
        return jsonResponse(res, true, 'Nomor tiket tersedia');
      }

      // ── Get ticket data (for timbangan 2) ────────────────────────────────
      case 'get_ticket_data': {
        const noTiket = cleanInput(req.body.no_tiket);
        const data = await queryOne(
          `SELECT tt.*, k.no_polisi, s.nama_supplier
           FROM transaksi_timbangan tt
           LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
           LEFT JOIN supplier s ON tt.id_supplier = s.id
           WHERE tt.no_tiket = ? AND tt.status = 'timbang_1'`, [noTiket]
        );
        if (!data) return jsonResponse(res, false, 'Tiket tidak ditemukan atau sudah selesai');
        const weightT1 = parseFloat(data.berat_timbangan1) || parseFloat(data.berat_bruto) || 0;
        if (weightT1 <= 0) return jsonResponse(res, false, 'Data berat tidak valid');
        data.validated_weight_timbangan1 = weightT1;
        data.is_locked = true;
        return jsonResponse(res, true, 'Data tiket ditemukan', data);
      }

      // ── Get transaction (by id) ───────────────────────────────────────────
      case 'get_transaksi': {
        const id = parseInt(req.body.id);
        if (!id) return jsonResponse(res, false, 'ID tidak valid');
        const data = await queryOne(`SELECT * FROM view_transaksi_lengkap WHERE id = ? LIMIT 1`, [id]);
        if (data) return jsonResponse(res, true, 'Data found', data);
        return jsonResponse(res, false, 'Data not found');
      }

      // ── Get today stats ───────────────────────────────────────────────────
      case 'get_stats_today': {
        const today = new Date().toISOString().split('T')[0];
        const stats = await queryOne(
          `SELECT COUNT(*) as total_transaksi, SUM(berat_netto) as total_netto, AVG(berat_netto) as avg_netto
           FROM transaksi_timbangan WHERE tanggal = ? AND status = 'selesai'`, [today]
        );
        return jsonResponse(res, true, 'Stats retrieved', stats);
      }

      // ── Indicator connection ──────────────────────────────────────────────
      case 'toggle_indicator_connection': {
        req.session.indicator_connected = req.body.connect === 'true';
        return jsonResponse(res, true, `Indicator ${req.session.indicator_connected ? 'enabled' : 'disabled'}`);
      }

      case 'get_indicator_status': {
        return jsonResponse(res, true, 'Status retrieved', {
          connected: req.session.indicator_connected || false,
          weight: 0,
          bridge_available: false
        });
      }

      // ── Get bridge ports ──────────────────────────────────────────────────
      case 'get_bridge_ports': {
        // Handled by Web Serial API in frontend — return empty list
        return jsonResponse(res, true, 'Ports via Web Serial API', {
          ports: [],
          bridge_available: false,
          use_web_serial: true
        });
      }

      // ── Refresh supplier list ─────────────────────────────────────────────
      case 'refresh_supplier_list': {
        const cacheKey = `active_suppliers_list`;
        cacheDelete(cacheKey);
        const suppliers = await query(`SELECT id, nama_supplier FROM supplier WHERE status = 'active' ORDER BY nama_supplier`);
        cacheSet(cacheKey, suppliers, 3600);
        return jsonResponse(res, true, 'Supplier list refreshed', { suppliers });
      }

      // ── Get supplier hutang ───────────────────────────────────────────────
      case 'get_supplier_hutang': {
        const supplierId = parseInt(req.body.supplier_id);
        if (!supplierId) return jsonResponse(res, false, 'Supplier ID tidak valid');
        const sup = await queryOne(
          `SELECT id, nama_supplier, total_hutang, hutang_terakhir_update FROM supplier WHERE id = ? AND status = 'active'`,
          [supplierId]
        );
        if (!sup) return jsonResponse(res, false, 'Supplier tidak ditemukan');
        return jsonResponse(res, true, 'Supplier data retrieved', {
          supplier_id:           sup.id,
          nama_supplier:         sup.nama_supplier,
          total_hutang:          parseFloat(sup.total_hutang || 0),
          hutang_terakhir_update: sup.hutang_terakhir_update
        });
      }

      default:
        return jsonResponse(res, false, `Action tidak dikenal: ${action}`);
    }

  } catch (err) {
    console.error(`[Timbangan] Action=${action} error:`, err);
    return jsonResponse(res, false, 'Server error: ' + err.message);
  }
});

// ─── POST /timbangan/generate-ticket — Generate ticket number
router.post('/generate-ticket', async (req, res) => {
  try {
    const noTiket = await generateTicketNumber();
    return jsonResponse(res, true, 'Ticket generated', { no_tiket: noTiket });
  } catch (err) {
    return jsonResponse(res, false, 'Gagal membuat nomor tiket: ' + err.message);
  }
});

// ─── POST /timbangan/save-timbangan1 — Save timbangan 1 (form POST from page)
router.post('/save-timbangan1', async (req, res) => {
  const user = getCurrentUser(req);
  try {
    const noKendaraan  = cleanInput(req.body.no_kendaraan).toUpperCase() || '-';
    const namaPengemudi = cleanInput(req.body.nama_pengemudi) || '-';
    const namaSupplier = cleanInput(req.body.nama_suplier).toUpperCase(); // kosong = tanpa supplier
    const material     = (cleanInput(req.body.material) || 'tbs').toLowerCase();
    const harga        = parseFloat(req.body.harga) || 0;
    const berat        = parseFloat(req.body.berat) || 0;
    const keterangan   = cleanInput(req.body.keterangan || '');

    if (berat <= 0) {
      return jsonResponse(res, false, 'Berat tidak valid (harus lebih dari 0)');
    }

    // Automatically add new material to material_list setting if not already there
    try {
      const materialQuery = await queryOne(`SELECT setting_value FROM settings WHERE setting_key = 'material_list'`);
      let materialsList = ['tbs', 'brondolan'];
      if (materialQuery && materialQuery.setting_value) {
        try {
          let parsed = JSON.parse(materialQuery.setting_value);
          if (typeof parsed === 'string') parsed = JSON.parse(parsed);
          if (Array.isArray(parsed)) materialsList = parsed;
        } catch(e) {}
      }
      if (!materialsList.includes(material)) {
        materialsList.push(material);
        await query(
          `INSERT INTO settings (setting_key, setting_value) VALUES ('material_list', ?) 
           ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value`,
          [JSON.stringify(materialsList)]
        );
      }
    } catch (e) {
      console.error('[Timbangan] Auto-save material failed:', e);
    }

    const noTiket = await generateTicketNumber();

    // Find supir
    let supirId = null;
    if (namaPengemudi && namaPengemudi !== '-') {
      const driverName = namaPengemudi.trim().toUpperCase();
      let supir = await queryOne(`SELECT id FROM supir WHERE UPPER(nama_supir) = ?`, [driverName]);
      if (supir) {
        supirId = supir.id;
      }
    }

    // Find or create supplier — HANYA jika nama supplier diisi.
    // Jika kosong, biarkan tanpa supplier (id_supplier = null) agar tampil "-", bukan "UMUM".
    let supplierId = null;
    if (namaSupplier && namaSupplier !== '-') {
      const supplier = await queryOne(`SELECT id FROM supplier WHERE UPPER(nama_supplier) = ?`, [namaSupplier]);
      if (supplier) {
        supplierId = supplier.id;
      } else {
        const kode = 'SUP-' + new Date().toISOString().slice(2,8).replace(/-/g,'') + '-' + String(Math.floor(Math.random()*999)+1).padStart(3,'0');
        const result = await query(`INSERT INTO supplier (kode_supplier, nama_supplier, status, is_temporary, created_at) VALUES (?, ?, 'active', 1, NOW())`, [kode, namaSupplier]);
        supplierId = result.insertId;
      }
    }

    const isJual = (req.body.mode_timbangan || 'beli') === 'jual';
    const isLangsir = (req.body.is_langsir == 1 || req.body.is_langsir == '1' || req.body.is_langsir === true) ? 1 : 0;
    
    const data = {
      no_polisi:     noKendaraan,
      nama_supir:    namaPengemudi,
      id_supir:      supirId,
      id_supplier:   supplierId,
      jenis_material: material,
      harga_per_kg:  harga,
      berat_bruto:   isJual ? 0 : berat,
      berat_tara:    isJual ? berat : 0,
      berat_timbangan1: berat,
      keterangan,
      mode_timbangan: req.body.mode_timbangan || 'beli',
      operator_id:   user.id,
      is_langsir:    isLangsir,
      jumlah_trip_langsir: isLangsir ? 1 : 0
    };

    await activateReservedTicket(noTiket, data);
    
    if (isLangsir === 1) {
      const txRow = await queryOne(`SELECT id FROM transaksi_timbangan WHERE no_tiket = ?`, [noTiket]);
      if (txRow) {
        await query(`INSERT INTO transaksi_timbangan_langsir (id_transaksi, berat_bruto, waktu_timbang) VALUES (?, ?, NOW())`, [txRow.id, berat]);
      }
    }
    
    return jsonResponse(res, true, `Timbangan Masuk berhasil disimpan. Tiket: ${noTiket}`, { no_tiket: noTiket });

  } catch (err) {
    console.error('[Timbangan] save-timbangan1 error:', err);
    return jsonResponse(res, false, 'Gagal menyimpan: ' + err.message);
  }
});

module.exports = router;
