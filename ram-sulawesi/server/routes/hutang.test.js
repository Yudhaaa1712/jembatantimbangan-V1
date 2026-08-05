// server/routes/hutang.test.js
// Memastikan setiap mutasi hutang TUNAI di Manajemen Hutang ikut tercatat di
// Buku Kas (dan mutasi non-tunai tidak menyentuh kas sama sekali).
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

const hutangRouter = require('./hutang');

function saldoKas() {
  const r = database.db.prepare(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`).get();
  return r ? parseFloat(r.saldo_setelah) : 0;
}
function kasTerakhir() {
  return database.db.prepare(`SELECT * FROM kas ORDER BY id DESC LIMIT 1`).get();
}

describe('hutang.js - sinkronisasi Manajemen Hutang ke Keuangan', () => {
  let app, idSupir;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/hutang', hutangRouter);
  });

  beforeEach(() => {
    database.db.exec(`DELETE FROM kas; DELETE FROM hutang_ledger; DELETE FROM supir;`);
    database.db.prepare(
      `INSERT INTO supir (nama_supir, total_hutang, status) VALUES ('AHMAD', 1000000, 'active')`
    ).run();
    idSupir = database.db.prepare(`SELECT id FROM supir LIMIT 1`).get().id;
    database.db.prepare(
      `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah)
       VALUES (date('now','localtime'), 'masuk', 5000000, 'MODAL MASUK', 5000000)`
    ).run();
  });

  test('kasbon tunai → hutang bertambah DAN kas berkurang', async () => {
    const res = await request(app)
      .post(`/hutang/supir/tambah`)
      .send({ id: idSupir, jumlah: 300000, is_tunai: true });

    expect(res.body.success).toBe(true);

    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(1300000);

    const kas = kasTerakhir();
    expect(kas.jenis).toBe('keluar');
    expect(kas.jumlah).toBe(300000);
    expect(kas.keterangan).toBe('KASBON SUPIR - AHMAD');
    expect(saldoKas()).toBe(4700000);

    // Tautan dua arah supaya baris kasnya tidak bisa dihapus lepas dari Buku Kas
    expect(kas.id_hutang_ledger).not.toBeNull();
    const ledger = database.db.prepare(`SELECT * FROM hutang_ledger ORDER BY id DESC LIMIT 1`).get();
    expect(ledger.id_kas).toBe(kas.id);
  });

  test('pelunasan tunai → hutang berkurang DAN kas bertambah', async () => {
    const res = await request(app)
      .post(`/hutang/supir/bayar`)
      .send({ id: idSupir, jumlah: 400000, is_tunai: true });

    expect(res.body.success).toBe(true);

    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(600000);

    const kas = kasTerakhir();
    expect(kas.jenis).toBe('masuk');
    expect(kas.jumlah).toBe(400000);
    expect(kas.keterangan).toBe('BAYAR HUTANG SUPIR - AHMAD');
    expect(saldoKas()).toBe(5400000);
  });

  test('mutasi non-tunai (hutang barang) tidak menyentuh kas', async () => {
    const res = await request(app)
      .post(`/hutang/supir/tambah`)
      .send({ id: idSupir, jumlah: 250000, is_tunai: false, keterangan: 'Hutang pupuk' });

    expect(res.body.success).toBe(true);

    const supir = database.db.prepare(`SELECT total_hutang FROM supir WHERE id = ?`).get(idSupir);
    expect(supir.total_hutang).toBe(1250000);

    // Hanya baris modal awal yang ada — tidak ada baris kas baru
    const jumlahBaris = database.db.prepare(`SELECT COUNT(*) AS c FROM kas`).get().c;
    expect(jumlahBaris).toBe(1);
    expect(saldoKas()).toBe(5000000);
  });

  test('mutasi backdate tetap membuat kolom saldo di Buku Kas berurutan', async () => {
    await request(app).post(`/hutang/supir/bayar`)
      .send({ id: idSupir, jumlah: 100000, is_tunai: true, tanggal: '2020-01-01' });

    const baris = database.db.prepare(`SELECT jenis, jumlah, saldo_setelah FROM kas ORDER BY id ASC`).all();
    let berjalan = 0;
    for (const b of baris) {
      berjalan += (b.jenis === 'masuk' ? 1 : -1) * b.jumlah;
      expect(b.saldo_setelah).toBe(berjalan);
    }
  });
});
