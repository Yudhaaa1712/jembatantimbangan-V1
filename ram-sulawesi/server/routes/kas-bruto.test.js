// server/routes/kas-bruto.test.js
//
// PENCATATAN BRUTO — pelunasan hutang harus terbaca sebagai PEMASUKAN.
//
// Aturan yang dijaga di sini:
//   1. Potongan hutang dicatat sebagai baris uang MASUK tersendiri.
//   2. Uang keluarnya ditulis penuh (sebelum potongan hutang).
//   3. Saldo akhir TIDAK berubah dibanding pencatatan neto — hanya tampilannya
//      yang lebih rinci. Ini yang paling penting: uang di brankas harus tetap
//      cocok dengan saldo di aplikasi.
//   4. Pembatalan menarik kembali SEMUA baris pasangannya, tidak menyisakan satu.
process.env.DB_PATH = ':memory:';
const express = require('express');
const request = require('supertest');
const database = require('../config/database');

jest.mock('../middleware/auth', () => ({
  isLoggedIn: (req, res, next) => {
    req.session = { user_id: 1, username: 'admin', user_role: 'admin', nama_lengkap: 'Administrator' };
    next();
  },
  requireRole: (...roles) => (req, res, next) => next(),
  getCurrentUser: () => ({ id: 1, username: 'admin', role: 'admin', nama_lengkap: 'Administrator' })
}));

const timbanganRouter = require('./timbangan');
const pembayaranRouter = require('./pembayaran');
const transaksiRouter = require('./transaksi');

const MODAL_AWAL = 50000000;

function barisKas() {
  return database.db.prepare(`SELECT jenis, jumlah, keterangan, saldo_setelah FROM kas ORDER BY id ASC`).all();
}
function saldoKas() {
  const r = database.db.prepare(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`).get();
  return r ? parseFloat(r.saldo_setelah) : 0;
}
/** Saldo dihitung ulang dari nol — harus selalu sama dengan kolom saldo_setelah. */
function saldoDihitungUlang() {
  return barisKas().reduce((s, b) => s + (b.jenis === 'masuk' ? 1 : -1) * b.jumlah, 0);
}

describe('Pencatatan bruto - potongan hutang tampil sebagai pemasukan', () => {
  let app, idSupir, idSupplier;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/timbangan', timbanganRouter);
    app.use('/pembayaran-api', pembayaranRouter);
    app.use('/transaksi', transaksiRouter);
  });

  beforeEach(() => {
    database.db.exec(`
      DELETE FROM kas; DELETE FROM hutang_ledger; DELETE FROM transaksi_timbangan;
      DELETE FROM supir; DELETE FROM supplier; DELETE FROM pembayaran; DELETE FROM pembayaran_detail;
    `);
    // Fitur hutang harus aktif — kalau tidak, potongan hutang tidak dianggap
    // pelunasan dan memang tidak boleh dicatat sebagai pemasukan.
    database.db.prepare(
      `INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES ('active_features', ?)`
    ).run(JSON.stringify({ hutang: true }));

    database.db.prepare(`INSERT INTO supir (nama_supir, total_hutang, status) VALUES ('UJANG', 5000000, 'active')`).run();
    idSupir = database.db.prepare(`SELECT id FROM supir LIMIT 1`).get().id;
    database.db.prepare(`INSERT INTO supplier (kode_supplier, nama_supplier) VALUES ('S1', 'PT SAWIT JAYA')`).run();
    idSupplier = database.db.prepare(`SELECT id FROM supplier LIMIT 1`).get().id;
    database.db.prepare(
      `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah)
       VALUES (date('now','localtime'), 'masuk', ?, 'MODAL MASUK', ?)`
    ).run(MODAL_AWAL, MODAL_AWAL);
  });

  /** Tiket 10.000 kg x Rp 1.000 = Rp 10jt, potong hutang supir Rp 3jt. */
  function buatTiket(noTiket, { potonganHutang = 3000000 } = {}) {
    database.db.prepare(`
      INSERT INTO transaksi_timbangan
        (no_tiket, tanggal, status, berat_timbangan1, no_polisi, nama_supir, id_supir,
         id_supplier, jenis_material, harga_per_kg, potongan_hutang_rp, mode_timbangan)
      VALUES (?, date('now','localtime'), 'timbang_1', 12000, 'BK 1234 XX', 'UJANG', ?, ?, 'TBS', 1000, ?, 'beli')
    `).run(noTiket, idSupir, idSupplier, potonganHutang);
    return database.db.prepare(`SELECT id FROM transaksi_timbangan WHERE no_tiket = ?`).get(noTiket).id;
  }

  function timbangKeluar(id, extra = {}) {
    return request(app).post('/timbangan/ajax').send({
      action: 'save_timbangan2',
      id_transaksi: id,
      berat_timbangan2: 2000,      // netto 10.000 kg
      persen_potongan: 0,
      kg_potongan: 0,
      harga_per_kg: 1000,
      potongan_hutang_rp: 3000000,
      ...extra
    });
  }

  test('bayar tunai di timbangan → 2 baris, saldo berkurang sebesar yang benar-benar dibayar', async () => {
    const id = buatTiket('TKT-BRUTO-001');
    const res = await timbangKeluar(id, { metode_bayar: 'tunai' });
    expect(res.body.success).toBe(true);

    const baris = barisKas().filter(b => b.keterangan !== 'MODAL MASUK');
    expect(baris).toHaveLength(2);

    const keluar = baris.find(b => b.jenis === 'keluar');
    const masuk = baris.find(b => b.jenis === 'masuk');

    // Uang keluar ditulis PENUH, bukan sudah dipotong hutang
    expect(keluar.jumlah).toBe(10000000);
    expect(keluar.keterangan).toContain('PEMBELIAN TBS - UJANG');

    // Potongan hutang muncul sebagai PEMASUKAN
    expect(masuk.jumlah).toBe(3000000);
    expect(masuk.keterangan).toBe('POTONG HUTANG SUPIR - UJANG - TKT-BRUTO-001');

    // Yang paling penting: saldo tetap berkurang Rp 7jt (uang fisik yang keluar)
    expect(saldoKas()).toBe(MODAL_AWAL - 7000000);
    expect(saldoDihitungUlang()).toBe(saldoKas());

    // Hutang supir ikut berkurang
    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(2000000);
  });

  test('tiket bayar-nanti → tidak ada baris kas sampai dibayar lewat menu Pembayaran', async () => {
    const id = buatTiket('TKT-BRUTO-002');
    await timbangKeluar(id); // tanpa metode_bayar

    expect(barisKas().filter(b => b.keterangan !== 'MODAL MASUK')).toHaveLength(0);
    expect(saldoKas()).toBe(MODAL_AWAL);

    // Hutang tetap dipotong saat timbang, itu memang terjadi saat itu juga
    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(2000000);
  });

  test('dibayar lewat menu Pembayaran → tetap 2 baris dan saldo sama dengan bayar langsung', async () => {
    const id = buatTiket('TKT-BRUTO-003');
    await timbangKeluar(id);

    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ tiket_ids: [id], metode: 'tunai' });
    expect(res.body.success).toBe(true);

    const baris = barisKas().filter(b => b.keterangan !== 'MODAL MASUK');
    expect(baris).toHaveLength(2);
    expect(baris.find(b => b.jenis === 'keluar').jumlah).toBe(10000000);
    expect(baris.find(b => b.jenis === 'masuk').jumlah).toBe(3000000);

    // Hasil akhirnya identik dengan tiket yang dibayar langsung di timbangan
    expect(saldoKas()).toBe(MODAL_AWAL - 7000000);
    expect(saldoDihitungUlang()).toBe(saldoKas());
  });

  test('pembayaran dibatalkan → kedua baris ikut ditarik, saldo kembali utuh', async () => {
    const id = buatTiket('TKT-BRUTO-004');
    await timbangKeluar(id);
    await request(app).post('/pembayaran-api/simpan').send({ tiket_ids: [id], metode: 'tunai' });

    const idBayar = database.db.prepare(`SELECT id FROM pembayaran LIMIT 1`).get().id;
    const res = await request(app).post('/pembayaran-api/batal').send({ id: idBayar });
    expect(res.body.success).toBe(true);

    // Tidak boleh ada baris pasangan yang tertinggal
    expect(barisKas().filter(b => b.keterangan !== 'MODAL MASUK')).toHaveLength(0);
    expect(saldoKas()).toBe(MODAL_AWAL);
    expect(saldoDihitungUlang()).toBe(MODAL_AWAL);
  });

  test('tiket tunai dibatalkan → saldo kembali utuh dan hutang supir dipulihkan', async () => {
    const id = buatTiket('TKT-BRUTO-005');
    await timbangKeluar(id, { metode_bayar: 'tunai' });
    expect(saldoKas()).toBe(MODAL_AWAL - 7000000);

    const res = await request(app)
      .post('/transaksi/cancel')
      .send({ id, action_type: 'batal', cancel_reason: 'Uji pembatalan' });
    expect(res.body.success).toBe(true);

    // Uang yang sudah keluar kembali seluruhnya
    expect(saldoKas()).toBe(MODAL_AWAL);
    expect(saldoDihitungUlang()).toBe(MODAL_AWAL);

    // Hutang yang sempat dipotong dikembalikan
    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(5000000);
  });

  test('kolom saldo di Buku Kas selalu berurutan, tidak melompat', async () => {
    const a = buatTiket('TKT-BRUTO-006');
    await timbangKeluar(a, { metode_bayar: 'tunai' });
    const b = buatTiket('TKT-BRUTO-007', { potonganHutang: 0 });
    await timbangKeluar(b, { metode_bayar: 'transfer', potongan_hutang_rp: 0 });

    let berjalan = 0;
    for (const row of barisKas()) {
      berjalan += (row.jenis === 'masuk' ? 1 : -1) * row.jumlah;
      expect(row.saldo_setelah).toBe(berjalan);
    }
  });
});
