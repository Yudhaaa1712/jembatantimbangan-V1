// electron-app/server/helpers/ticket.test.js
process.env.DB_PATH = ':memory:';
const database = require('../config/database');
const { generateTicketNumber, isTicketExists, activateReservedTicket } = require('./ticket');

describe('ticket.js - Integration Tests (Real SQLite DB)', () => {

  beforeAll(() => {
    // Ensure schema is fresh
    database.db.exec(`
      CREATE TABLE IF NOT EXISTS transaksi_timbangan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        no_tiket TEXT NOT NULL UNIQUE,
        status TEXT,
        created_at TEXT,
        timbang1_locked INTEGER,
        no_polisi TEXT,
        nama_supir TEXT,
        id_supplier INTEGER,
        updated_at TEXT,
        jenis_material TEXT,
        keterangan TEXT,
        harga_per_kg REAL,
        berat_bruto REAL,
        berat_timbangan1 REAL,
        waktu_timbangan1 TEXT,
        operator_id INTEGER
      );
      CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT UNIQUE,
        setting_value TEXT
      );
    `);
  });

  beforeEach(() => {
    // Clear data before each test
    database.db.exec(`DELETE FROM transaksi_timbangan; DELETE FROM settings;`);
    // Seed default prefix
    database.db.exec(`INSERT INTO settings (setting_key, setting_value) VALUES ('ticket_prefix', 'TKT')`);
  });

  test('isTicketExists() should return true if status is not reserved', async () => {
    database.db.exec(`INSERT INTO transaksi_timbangan (no_tiket, status) VALUES ('TKT-260616-001', 'timbang_1')`);
    const exists = await isTicketExists('TKT-260616-001');
    expect(exists).toBe(true);
  });

  test('isTicketExists() should return false if ticket does not exist', async () => {
    const exists = await isTicketExists('TKT-999999-999');
    expect(exists).toBe(false);
  });

  test('isTicketExists() should return false if status is reserved (can be reused)', async () => {
    database.db.exec(`INSERT INTO transaksi_timbangan (no_tiket, status) VALUES ('TKT-260616-002', 'reserved')`);
    const exists = await isTicketExists('TKT-260616-002');
    expect(exists).toBe(false);
  });

  test('generateTicketNumber() should generate unique atomic ticket number', async () => {
    // First ticket should be 001
    const ticket1 = await generateTicketNumber();
    expect(ticket1).toMatch(/^TKT-\d{6}-001$/);

    // Second ticket should be 002
    const ticket2 = await generateTicketNumber();
    expect(ticket2).toMatch(/^TKT-\d{6}-002$/);

    // Verify they are physically in DB with status reserved
    const count = database.db.prepare(`SELECT count(*) as count FROM transaksi_timbangan WHERE status = 'reserved'`).get().count;
    expect(count).toBe(2);
  });

  test('activateReservedTicket() should update basic fields, details and status', async () => {
    // Reserve a ticket first
    const ticket = await generateTicketNumber();

    const data = {
      no_polisi: 'BK 1234 AB',
      nama_supir: 'Budi',
      id_supplier: 12,
      jenis_material: 'TBS',
      keterangan: 'Jalan jelek',
      harga_per_kg: 2000,
      berat_bruto: 10000,
      berat_timbangan1: 10000,
      operator_id: 1
    };

    const success = await activateReservedTicket(ticket, data);
    expect(success).toBe(true);

    // Verify DB
    const row = database.db.prepare(`SELECT * FROM transaksi_timbangan WHERE no_tiket = ?`).get(ticket);
    expect(row.status).toBe('timbang_1');
    expect(row.nama_supir).toBe('Budi');
    expect(row.no_polisi).toBe('BK 1234 AB');
    expect(row.berat_bruto).toBe(10000);
    expect(row.operator_id).toBe(1);
    expect(row.timbang1_locked).toBe(1);
  });

  test('activateReservedTicket() should rollback and throw error if ticket not found', async () => {
    await expect(activateReservedTicket('TKT-INVALID', {})).rejects.toThrow('Tiket reserved tidak ditemukan');
  });
});
