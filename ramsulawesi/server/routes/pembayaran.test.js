// electron-app/server/routes/pembayaran.test.js
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
  getCurrentUser: (req) => ({ id: 1, username: 'admin', role: 'admin', nama_lengkap: 'Administrator' })
}));

const pembayaranRouter = require('./pembayaran');

/** Saldo kas terakhir (carry-over), sama seperti yang dipakai route. */
function saldoKas() {
  const r = database.db.prepare(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`).get();
  return r ? parseFloat(r.saldo_setelah) : 0;
}

function buatTiket(noTiket, noPolisi, idSupplier, totalAkhir, extra = {}) {
  database.db.prepare(`
    INSERT INTO transaksi_timbangan
      (no_tiket, tanggal, no_polisi, id_supplier, mode_timbangan, status, timbang2_locked,
       total_harga, total_akhir, status_bayar)
    VALUES (?, '2026-07-29', ?, ?, ?, ?, 1, ?, ?, ?)
  `).run(
    noTiket, noPolisi, idSupplier,
    extra.mode || 'beli',
    extra.status || 'selesai',
    totalAkhir, totalAkhir,
    extra.statusBayar || 'belum_bayar'
  );
  return database.db.prepare(`SELECT id FROM transaksi_timbangan WHERE no_tiket = ?`).get(noTiket).id;
}

describe('pembayaran.js - Pembayaran supplier (rapel beberapa tiket)', () => {
  let app;
  let idSupplier;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/pembayaran-api', pembayaranRouter);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    database.db.exec(`
      DELETE FROM pembayaran_detail;
      DELETE FROM pembayaran;
      DELETE FROM kas;
      DELETE FROM transaksi_timbangan;
      DELETE FROM supplier;
    `);
    database.db.prepare(
      `INSERT INTO supplier (kode_supplier, nama_supplier) VALUES ('S01','PT SAWIT JAYA')`
    ).run();
    idSupplier = database.db.prepare(`SELECT id FROM supplier LIMIT 1`).get().id;
    // Modal awal kas
    database.db.prepare(
      `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah)
       VALUES ('2026-07-29','masuk',50000000,'Modal awal',50000000)`
    ).run();
  });

  test('GET /rekap-supplier - menjumlahkan tiket belum dibayar per supplier', async () => {
    buatTiket('TKT-001', 'BM 1234 XY', idSupplier, 4200000);
    buatTiket('TKT-002', 'BM 5678 AB', idSupplier, 3850000);
    buatTiket('TKT-003', 'BM 9012 CD', idSupplier, 5100000);

    const res = await request(app).get('/pembayaran-api/rekap-supplier');

    expect(res.body.success).toBe(true);
    expect(res.body.data.total_tiket).toBe(3);
    expect(res.body.data.total_semua).toBe(13150000);
    expect(res.body.data.supplier[0].nama_supplier).toBe('PT SAWIT JAYA');
  });

  test('GET /rekap-supplier - tiket lunas & mode jual tidak ikut dihitung', async () => {
    buatTiket('TKT-001', 'BM 1', idSupplier, 1000000);
    buatTiket('TKT-002', 'BM 2', idSupplier, 2000000, { statusBayar: 'lunas' });
    buatTiket('TKT-003', 'BM 3', idSupplier, 3000000, { mode: 'jual' });
    buatTiket('TKT-004', 'BM 4', idSupplier, 4000000, { status: 'dibatalkan' });

    const res = await request(app).get('/pembayaran-api/rekap-supplier');

    expect(res.body.data.total_tiket).toBe(1);
    expect(res.body.data.total_semua).toBe(1000000);
  });

  test('POST /simpan - 3 tiket jadi SATU baris kas keluar dan semua ditandai lunas', async () => {
    const ids = [
      buatTiket('TKT-001', 'BM 1234 XY', idSupplier, 4200000),
      buatTiket('TKT-002', 'BM 5678 AB', idSupplier, 3850000),
      buatTiket('TKT-003', 'BM 9012 CD', idSupplier, 5100000)
    ];
    expect(saldoKas()).toBe(50000000); // timbang keluar belum menyentuh kas

    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'transfer', tiket_ids: ids, keterangan: 'Transfer BRI' });

    expect(res.body.success).toBe(true);
    expect(res.body.data.total).toBe(13150000);
    expect(res.body.data.jumlah_tiket).toBe(3);

    const kasKeluar = database.db.prepare(`SELECT * FROM kas WHERE jenis='keluar'`).all();
    expect(kasKeluar).toHaveLength(1);
    expect(kasKeluar[0].jumlah).toBe(13150000);
    expect(saldoKas()).toBe(50000000 - 13150000);

    const tiket = database.db.prepare(
      `SELECT status_bayar, metode_bayar FROM transaksi_timbangan`
    ).all();
    expect(tiket.every(t => t.status_bayar === 'lunas')).toBe(true);
    expect(tiket.every(t => t.metode_bayar === 'transfer')).toBe(true);

    const detail = database.db.prepare(`SELECT COUNT(*) c FROM pembayaran_detail`).get();
    expect(detail.c).toBe(3);
  });

  test('POST /simpan - menolak tiket yang sudah lunas (tidak bisa dibayar dua kali)', async () => {
    const id = buatTiket('TKT-001', 'BM 1', idSupplier, 1000000, { statusBayar: 'lunas' });

    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'tunai', tiket_ids: [id] });

    expect(res.body.success).toBe(false);
    expect(res.body.message).toMatch(/TKT-001/);
    expect(database.db.prepare(`SELECT COUNT(*) c FROM kas WHERE jenis='keluar'`).get().c).toBe(0);
  });

  test('POST /simpan - menolak metode selain tunai/transfer', async () => {
    const id = buatTiket('TKT-001', 'BM 1', idSupplier, 1000000);

    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'hutang', tiket_ids: [id] });

    expect(res.body.success).toBe(false);
    expect(saldoKas()).toBe(50000000);
  });

  test('POST /simpan - menolak tiket dari supplier berbeda dalam satu pembayaran', async () => {
    database.db.prepare(`INSERT INTO supplier (kode_supplier, nama_supplier) VALUES ('S02','CV LAIN')`).run();
    const idLain = database.db.prepare(`SELECT id FROM supplier WHERE kode_supplier='S02'`).get().id;
    const a = buatTiket('TKT-001', 'BM 1', idSupplier, 1000000);
    const b = buatTiket('TKT-002', 'BM 2', idLain, 2000000);

    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'tunai', tiket_ids: [a, b] });

    expect(res.body.success).toBe(false);
    expect(res.body.message).toMatch(/supplier yang sama/i);
    expect(saldoKas()).toBe(50000000);
  });

  test('POST /simpan - tanpa tiket terpilih ditolak', async () => {
    const res = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'tunai', tiket_ids: [] });

    expect(res.body.success).toBe(false);
    expect(saldoKas()).toBe(50000000);
  });

  test('POST /batal - saldo kembali utuh dan tiket kembali belum lunas', async () => {
    const ids = [
      buatTiket('TKT-001', 'BM 1', idSupplier, 4200000),
      buatTiket('TKT-002', 'BM 2', idSupplier, 3850000)
    ];
    const simpan = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'tunai', tiket_ids: ids });
    expect(saldoKas()).toBe(50000000 - 8050000);

    const batal = await request(app)
      .post('/pembayaran-api/batal')
      .send({ id: simpan.body.data.id, alasan: 'Salah input' });

    expect(batal.body.success).toBe(true);
    expect(saldoKas()).toBe(50000000);
    expect(database.db.prepare(`SELECT COUNT(*) c FROM kas WHERE jenis='keluar'`).get().c).toBe(0);

    const tiket = database.db.prepare(`SELECT status_bayar, id_pembayaran FROM transaksi_timbangan`).all();
    expect(tiket.every(t => t.status_bayar === 'belum_bayar')).toBe(true);
    expect(tiket.every(t => t.id_pembayaran === null)).toBe(true);

    const header = database.db.prepare(`SELECT status FROM pembayaran`).get();
    expect(header.status).toBe('dibatalkan');
  });

  test('POST /batal - pembayaran yang sudah dibatalkan tidak bisa dibatalkan lagi', async () => {
    const id = buatTiket('TKT-001', 'BM 1', idSupplier, 1000000);
    const simpan = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'tunai', tiket_ids: [id] });
    await request(app).post('/pembayaran-api/batal').send({ id: simpan.body.data.id });

    const lagi = await request(app).post('/pembayaran-api/batal').send({ id: simpan.body.data.id });

    expect(lagi.body.success).toBe(false);
    expect(saldoKas()).toBe(50000000);
  });

  test('GET /detail/:id - mengembalikan header dan rincian tiket', async () => {
    const ids = [
      buatTiket('TKT-001', 'BM 1', idSupplier, 1000000),
      buatTiket('TKT-002', 'BM 2', idSupplier, 2000000)
    ];
    const simpan = await request(app)
      .post('/pembayaran-api/simpan')
      .send({ metode: 'transfer', tiket_ids: ids });

    const res = await request(app).get(`/pembayaran-api/detail/${simpan.body.data.id}`);

    expect(res.body.success).toBe(true);
    expect(res.body.data.header.total).toBe(3000000);
    expect(res.body.data.header.metode).toBe('transfer');
    expect(res.body.data.detail).toHaveLength(2);
  });

  test('saldo_setelah tetap konsisten dengan urutan mutasi kas', async () => {
    const a = buatTiket('TKT-001', 'BM 1', idSupplier, 4200000);
    const b = buatTiket('TKT-002', 'BM 2', idSupplier, 3850000);
    await request(app).post('/pembayaran-api/simpan').send({ metode: 'tunai', tiket_ids: [a] });
    await request(app).post('/pembayaran-api/simpan').send({ metode: 'transfer', tiket_ids: [b] });

    let berjalan = 0;
    for (const r of database.db.prepare(`SELECT jenis, jumlah, saldo_setelah FROM kas ORDER BY id`).all()) {
      berjalan += r.jenis === 'masuk' ? r.jumlah : -r.jumlah;
      expect(r.saldo_setelah).toBeCloseTo(berjalan, 2);
    }
    expect(saldoKas()).toBe(50000000 - 8050000);
  });
});
