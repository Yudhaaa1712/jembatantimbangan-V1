// electron-app/server/routes/auth.test.js
const express = require('express');
const request = require('supertest');
const bcrypt = require('bcryptjs');
const database = require('../config/database');

// Mock database config
jest.mock('../config/database', () => ({
  query: jest.fn(),
  jsonResponse: (res, success, message, data = null) => res.json({ success, message, data })
}));

const authRouter = require('./auth');

describe('auth.js - Authentication Routes Unit Tests', () => {
  let app;
  let sessionData;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    // Mock session middleware
    app.use((req, res, next) => {
      req.session = {
        ...sessionData,
        destroy: jest.fn().mockImplementation((cb) => {
          sessionData = {};
          cb(null);
        })
      };
      // Helper to capture session writes
      req.session.save = (cb) => { if (cb) cb(); };
      next();
    });
    app.use('/auth', authRouter);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    sessionData = {}; // Clear session between tests
  });

  test('POST /auth/login - should fail if username or password is missing', async () => {
    const res = await request(app)
      .post('/auth/login')
      .send({ username: 'admin' }); // password missing

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Username dan password harus diisi');
  });

  test('POST /auth/login - should fail if user not found (inactive or incorrect)', async () => {
    database.query.mockResolvedValueOnce([]); // No user found

    const res = await request(app)
      .post('/auth/login')
      .send({ username: 'unknown', password: 'password123' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Username atau password salah');
    expect(database.query).toHaveBeenCalledWith(
      expect.stringContaining("SELECT * FROM users WHERE username = ?"),
      ['unknown']
    );
  });

  test('POST /auth/login - should fail if password does not match', async () => {
    const hashedPassword = await bcrypt.hash('correct_password', 10);
    database.query.mockResolvedValueOnce([{
      id: 1,
      username: 'admin',
      nama_lengkap: 'Administrator',
      password: hashedPassword,
      role: 'admin',
      status: 'active'
    }]);

    const res = await request(app)
      .post('/auth/login')
      .send({ username: 'admin', password: 'wrong_password' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Username atau password salah');
  });

  test('POST /auth/login - should succeed and set session if credentials are valid', async () => {
    const hashedPassword = await bcrypt.hash('correct_password', 10);
    database.query.mockResolvedValueOnce([{
      id: 5,
      username: 'operator1',
      nama_lengkap: 'Operator Satu',
      password: hashedPassword,
      role: 'operator',
      status: 'active'
    }]);

    // Setup an interceptor to verify session assignment in middleware
    let capturedSession = null;
    app.use((req, res, next) => {
      capturedSession = req.session;
      next();
    });

    const res = await request(app)
      .post('/auth/login')
      .send({ username: 'operator1', password: 'correct_password' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('Login berhasil');
    expect(res.body.data.redirect).toBe('/timbangan/1');
  });

  test('GET /auth/session - should return loggedIn: false if not authenticated', async () => {
    const res = await request(app).get('/auth/session');

    expect(res.status).toBe(200);
    expect(res.body.loggedIn).toBe(false);
  });

  test('GET /auth/session - should return session info if authenticated', async () => {
    sessionData = {
      user_id: 12,
      username: 'spv_user',
      nama_lengkap: 'Supervisor',
      user_role: 'supervisor'
    };

    const res = await request(app).get('/auth/session');

    expect(res.status).toBe(200);
    expect(res.body.loggedIn).toBe(true);
    expect(res.body.user_id).toBe(12);
    expect(res.body.username).toBe('spv_user');
    expect(res.body.nama_lengkap).toBe('Supervisor');
    expect(res.body.user_role).toBe('supervisor');
  });

  test('POST /auth/logout - should clear session and return success', async () => {
    sessionData = { user_id: 12, username: 'spv_user' };

    const res = await request(app).post('/auth/logout');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.redirect).toBe('/auth/login');
  });
});
