// electron-app/server/routes/kas.test.js
process.env.DB_PATH = ':memory:';
const express = require('express');
const request = require('supertest');
const database = require('../config/database');

// Mock auth middleware to bypass authentication and roles checks
jest.mock('../middleware/auth', () => ({
  isLoggedIn: (req, res, next) => {
    req.session = { user_id: 1, username: 'admin', user_role: 'admin', nama_lengkap: 'Administrator' };
    next();
  },
  requireRole: (...roles) => (req, res, next) => next(),
  getCurrentUser: (req) => ({ id: 1, username: 'admin', role: 'admin', nama_lengkap: 'Administrator' })
}));

const kasRouter = require('./kas');

describe('kas.js - Route API Integration Tests', () => {
  let app;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/kas', kasRouter);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    database.db.exec(`DELETE FROM kas;`);
  });

  test('GET /kas/saldo - should return correct current balance', async () => {
    // Harus memakai tanggal LOKAL database (date('now','localtime')), bukan
    // toISOString() yang selalu UTC — kalau beda zona waktu, baris seed jatuh di
    // tanggal lain dan total masuk/keluar hari ini terbaca 0.
    const today = database.db.prepare(`SELECT date('now','localtime') AS d`).get().d;
    database.db.exec(`
      INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah) VALUES 
      ('${today}', 'masuk', 10000000, 'Deposit', 10000000),
      ('${today}', 'keluar', 5000000, 'Expense', 5000000);
    `);

    const res = await request(app).get('/kas/saldo');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.data.saldo).toBe(5000000);
    expect(res.body.data.total_masuk).toBe(10000000);
    expect(res.body.data.total_keluar).toBe(5000000);
  });

  test('POST /kas/tambah - should successfully add deposit to kas', async () => {
    // Initial balance: 2.000.000
    database.db.exec(`
      INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah) VALUES 
      ('2023-01-01', 'masuk', 2000000, 'Init', 2000000);
    `);

    const res = await request(app)
      .post('/kas/tambah')
      .send({ jumlah: 1000000, keterangan: 'Deposit Mingguan' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('Berhasil menambah kas');
    
    // Check DB
    const row = database.db.prepare(`SELECT * FROM kas ORDER BY id DESC LIMIT 1`).get();
    expect(row.jenis).toBe('masuk');
    expect(row.jumlah).toBe(1000000);
    expect(row.saldo_setelah).toBe(3000000); // 2m + 1m
    expect(row.keterangan).toBe('DEPOSIT MINGGUAN');
  });

  test('POST /kas/pengeluaran - should successfully add manual expense if balance is sufficient', async () => {
    // Initial balance: 5.000.000
    database.db.exec(`
      INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah) VALUES 
      ('2023-01-01', 'masuk', 5000000, 'Init', 5000000);
    `);

    const res = await request(app)
      .post('/kas/pengeluaran')
      .send({ jumlah: 1500000, keterangan: 'Beli ATK Kantor' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('berhasil dicatat');
    
    // Check DB
    const row = database.db.prepare(`SELECT * FROM kas ORDER BY id DESC LIMIT 1`).get();
    expect(row.jenis).toBe('keluar');
    expect(row.jumlah).toBe(1500000);
    expect(row.saldo_setelah).toBe(3500000); // 5m - 1.5m
    expect(row.keterangan).toBe('BELI ATK KANTOR');
  });

  test('POST /kas/pengeluaran - should allow negative balance (overdraft) and succeed', async () => {
    // Initial balance: 500.000
    database.db.exec(`
      INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah) VALUES 
      ('2023-01-01', 'masuk', 500000, 'Init', 500000);
    `);

    const res = await request(app)
      .post('/kas/pengeluaran')
      .send({ jumlah: 1000000, keterangan: 'Beli Solar' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    
    // Check DB
    const row = database.db.prepare(`SELECT * FROM kas ORDER BY id DESC LIMIT 1`).get();
    expect(row.saldo_setelah).toBe(-500000); // 500k - 1m
    expect(row.keterangan).toBe('BELI SOLAR');
  });
});
