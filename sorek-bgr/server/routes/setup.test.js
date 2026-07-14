// electron-app/server/routes/setup.test.js
process.env.DB_PATH = ':memory:';
const express = require('express');
const request = require('supertest');
const database = require('../config/database');

let mockUser = null;

// Mock auth middleware dynamically
jest.mock('../middleware/auth', () => ({
  isLoggedIn: (req, res, next) => {
    if (!mockUser) {
      return res.status(401).json({ success: false, message: 'Session habis. Silakan login kembali.' });
    }
    req.session = { user_id: mockUser.id, username: mockUser.username, user_role: mockUser.role };
    next();
  },
  requireRole: (...roles) => (req, res, next) => {
    if (!mockUser) {
      return res.status(401).json({ success: false, message: 'Session habis. Silakan login kembali.' });
    }
    if (mockUser.role === 'admin') return next();
    if (roles.length > 0 && !roles.includes(mockUser.role)) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }
    next();
  }
}));

const setupRouter = require('./setup');

describe('setup.js - Settings Routes Integration Tests', () => {
  let app;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/setup', setupRouter);
    // schema is already created by database.js when required
  });

  beforeEach(() => {
    jest.clearAllMocks();
    mockUser = null; // Default to unauthorized
    database.db.exec(`DELETE FROM settings;`);
  });

  test('GET /setup/settings - should fail if not logged in', async () => {
    mockUser = null;
    const res = await request(app).get('/setup/settings');

    expect(res.status).toBe(401);
    expect(res.body.success).toBe(false);
  });

  test('GET /setup/settings - should succeed if logged in and return key-value map', async () => {
    mockUser = { id: 1, username: 'operator1', role: 'operator' };
    
    // Seed DB
    database.db.exec(`
      INSERT INTO settings (setting_key, setting_value) VALUES 
      ('company_name', 'PT. Sawit Jaya'),
      ('ticket_prefix', 'SJ')
    `);

    const res = await request(app).get('/setup/settings');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.data.company_name).toBe('PT. Sawit Jaya');
    expect(res.body.data.ticket_prefix).toBe('SJ');
  });

  test('POST /setup/settings - should fail if not admin', async () => {
    mockUser = { id: 2, username: 'operator2', role: 'operator' };
    const res = await request(app)
      .post('/setup/settings')
      .send({ company_name: 'PT. Sawit Baru' });

    expect(res.status).toBe(403);
    expect(res.body.success).toBe(false);
  });

  test('POST /setup/settings - should succeed if admin and update database', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    
    // Initial data
    database.db.exec(`
      INSERT INTO settings (setting_key, setting_value) VALUES 
      ('company_name', 'Old Name')
    `);

    const res = await request(app)
      .post('/setup/settings')
      .send({
        company_name: 'PT. Sawit Baru',
        serial_indicator_model: 'gsc'
      });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('berhasil disimpan');
    
    // Verify changes directly in DB
    const row1 = database.db.prepare(`SELECT setting_value FROM settings WHERE setting_key = 'company_name'`).get();
    expect(row1.setting_value).toBe('PT. Sawit Baru');

    const row2 = database.db.prepare(`SELECT setting_value FROM settings WHERE setting_key = 'serial_indicator_model'`).get();
    expect(row2.setting_value).toBe('gsc');
  });
});
