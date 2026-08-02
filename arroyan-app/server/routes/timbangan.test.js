// electron-app/server/routes/timbangan.test.js
process.env.DB_PATH = ':memory:';
const express = require('express');
const request = require('supertest');
const database = require('../config/database');

// Mock auth middleware
jest.mock('../middleware/auth', () => ({
  isLoggedIn: (req, res, next) => {
    req.session = { user_id: 1, username: 'operator', user_role: 'operator', nama_lengkap: 'Operator Timbangan' };
    next();
  },
  requireRole: (...roles) => (req, res, next) => next(),
  getCurrentUser: (req) => ({ id: 1, username: 'operator', role: 'operator', nama_lengkap: 'Operator Timbangan' })
}));

const timbanganRouter = require('./timbangan');

describe('timbangan.js - Route API Integration Tests', () => {
  let app;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/timbangan', timbanganRouter);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    database.db.exec(`
      DELETE FROM transaksi_timbangan;
      DELETE FROM kas;
      DELETE FROM settings;
      DELETE FROM supplier;
      DELETE FROM customers;
    `);
  });

  test('POST /timbangan/ajax (get_pending_tickets)', async () => {
    database.db.exec(`
      INSERT INTO supplier (id, kode_supplier, nama_supplier) VALUES (1, 'SUP1', 'Petani A');
      INSERT INTO transaksi_timbangan 
      (id, no_tiket, status, berat_timbangan1, no_polisi, nama_supir, id_supplier, jenis_material, harga_per_kg, keterangan)
      VALUES 
      (1, 'TKT-260616-001', 'timbang_1', 9000, 'BK 9999 CC', 'Joko', 1, 'TBS', 2000, 'Normal');
    `);

    const res = await request(app)
      .post('/timbangan/ajax')
      .send({ action: 'get_pending_tickets' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.data.length).toBe(1);
    expect(res.body.data[0].no_tiket).toBe('TKT-260616-001');
    expect(res.body.data[0].nama_supplier).toBe('Petani A');
  });

  test('POST /timbangan/ajax (save_timbangan2) with auto-deduct kas', async () => {
    // 1. Insert initial data
    database.db.exec(`
      INSERT INTO supplier (id, kode_supplier, nama_supplier) VALUES (2, 'SUP2', 'Petani B');
      INSERT INTO customers (id, nama_customer) VALUES (5, 'Customer A');
      
      INSERT INTO transaksi_timbangan 
      (id, no_tiket, status, berat_timbangan1, no_polisi, nama_supir, id_supplier, jenis_material, harga_per_kg, potongan_jalan, potongan_pupuk_rp, potongan_hutang_rp)
      VALUES 
      (12, 'TKT-260616-002', 'timbang_1', 10000, 'BK 1111 DD', 'Jono', 2, 'TBS', 2000, 100000, 200000, 150000);

      INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id)
      VALUES ('2023-01-01', 'masuk', 5000000, 'Saldo awal', 5000000, 1);
    `);

    // Request body: id_transaksi, berat_timbangan2=3000 (netto1=7000), kg_potongan=100 (netto2=6900), harga_per_kg=2000
    // Gross: 6900 * 2000 = 13.800.000
    // Deductions: 100k + 200k + 150k = 450k
    // Net Total (totalAkhir): 13.800.000 - 450.000 = 13.350.000
    const res = await request(app)
      .post('/timbangan/ajax')
      .send({
        action: 'save_timbangan2',
        id_transaksi: 12,
        id_customer: 5,
        berat_timbangan2: 3000,
        persen_potongan: 0,
        kg_potongan: 100,
        harga_per_kg: 2000,
        keterangan: 'Bersih'
      });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);

    // Verify database update timbangan2
    const trx = database.db.prepare(`SELECT * FROM transaksi_timbangan WHERE id = 12`).get();
    expect(trx.status).toBe('selesai');
    expect(trx.berat_timbangan2).toBe(3000);
    expect(trx.berat_netto).toBe(7000);
    expect(trx.netto_akhir).toBe(6900);
    expect(trx.total_harga).toBe(13800000);
    // Verify database insert kas
    // Saldo setelah = 5.000.000 - 13.350.000 = -8.350.000 
    const kas = database.db.prepare(`SELECT * FROM kas WHERE id_transaksi = 12`).get();
    expect(kas).toBeDefined();
    expect(kas.jumlah).toBe(13350000);
    expect(kas.jenis).toBe('keluar');
    expect(kas.keterangan).toContain('TBS JONO');
    expect(kas.saldo_setelah).toBe(-8350000);
  });
});
