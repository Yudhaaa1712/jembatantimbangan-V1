// electron-app/server/routes/users.test.js
process.env.DB_PATH = ':memory:';
const express = require('express');
const request = require('supertest');
const bcrypt = require('bcryptjs');
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
  },
  getCurrentUser: (req) => {
    return mockUser ? { id: mockUser.id, username: mockUser.username, role: mockUser.role } : null;
  }
}));

const usersRouter = require('./users');

describe('users.js - User Management Routes Integration Tests', () => {
  let app;

  beforeAll(() => {
    app = express();
    app.use(express.json());
    app.use('/users', usersRouter);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    mockUser = null;
    database.db.exec(`DELETE FROM users;`);
  });

  test('GET /users/list - should fail if not admin', async () => {
    mockUser = { id: 2, username: 'operator1', role: 'operator' };
    const res = await request(app).get('/users/list');

    expect(res.status).toBe(403);
    expect(res.body.success).toBe(false);
  });

  test('GET /users/list - should succeed if admin and return list', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (1, 'admin', 'Admin', 'hash1', 'admin', 'active'),
      (2, 'operator1', 'Operator', 'hash2', 'operator', 'active');
    `);

    const res = await request(app).get('/users/list');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.data.length).toBe(2);
    expect(res.body.data[0].username).toBe('admin');
  });

  test('POST /users/add - should fail on missing fields', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    const res = await request(app)
      .post('/users/add')
      .send({ username: 'newuser' }); // password & nama_lengkap missing

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Data tidak lengkap');
  });

  test('POST /users/add - should fail if username already exists', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (1, 'existing', 'Admin', 'hash1', 'admin', 'active');
    `);

    const res = await request(app)
      .post('/users/add')
      .send({ username: 'existing', password: 'password123', nama_lengkap: 'Existing User' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Username sudah digunakan');
  });

  test('POST /users/add - should hash password and save new user if valid', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };

    const res = await request(app)
      .post('/users/add')
      .send({ username: 'newuser', password: 'password123', nama_lengkap: 'New User', role: 'operator' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('User berhasil ditambahkan');
    
    // Check DB
    const row = database.db.prepare(`SELECT * FROM users WHERE username = 'newuser'`).get();
    expect(row).toBeDefined();
    expect(row.nama_lengkap).toBe('New User');
    expect(row.role).toBe('operator');

    // Check password hashing validity
    const isHashValid = await bcrypt.compare('password123', row.password);
    expect(isHashValid).toBe(true);
  });

  test('PUT /users/:id - should update user successfully', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (2, 'operator1', 'Old Name', 'hash2', 'operator', 'active');
    `);

    const res = await request(app)
      .put('/users/2')
      .send({ nama_lengkap: 'Updated Operator', role: 'operator', status: 'inactive' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('User berhasil diupdate');
    
    // Check DB
    const row = database.db.prepare(`SELECT * FROM users WHERE id = 2`).get();
    expect(row.nama_lengkap).toBe('Updated Operator');
    expect(row.status).toBe('inactive');
  });

  test('POST /users/:id/reset-password - should fail if password is too short', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    const res = await request(app)
      .post('/users/2/reset-password')
      .send({ new_password: '123' }); // Too short

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Password minimal 6 karakter');
  });

  test('POST /users/:id/reset-password - should succeed if password is valid', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (2, 'operator1', 'Old Name', 'hash2', 'operator', 'active');
    `);

    const res = await request(app)
      .post('/users/2/reset-password')
      .send({ new_password: 'secretpassword' });

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('Password berhasil direset');
    
    // Verify hashing was applied
    const row = database.db.prepare(`SELECT * FROM users WHERE id = 2`).get();
    const match = await bcrypt.compare('secretpassword', row.password);
    expect(match).toBe(true);
  });

  test('DELETE /users/:id - should fail if trying to soft delete self', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };

    const res = await request(app).delete('/users/1');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Tidak bisa menonaktifkan akun sendiri');
  });

  test('DELETE /users/:id - should succeed soft deleting another user', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (2, 'operator1', 'Old Name', 'hash2', 'operator', 'active');
    `);

    const res = await request(app).delete('/users/2');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('User dinonaktifkan');
    
    // Verify DB
    const row = database.db.prepare(`SELECT * FROM users WHERE id = 2`).get();
    expect(row.status).toBe('inactive');
  });

  test('DELETE /users/:id/hard - should fail if trying to hard delete self', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };

    const res = await request(app).delete('/users/1/hard');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toContain('Tidak bisa menghapus akun sendiri');
  });

  test('DELETE /users/:id/hard - should succeed hard deleting another user', async () => {
    mockUser = { id: 1, username: 'admin', role: 'admin' };
    database.db.exec(`
      INSERT INTO users (id, username, nama_lengkap, password, role, status) VALUES 
      (2, 'operator1', 'Old Name', 'hash2', 'operator', 'active');
    `);

    const res = await request(app).delete('/users/2/hard');

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.message).toContain('User berhasil dihapus permanen');
    
    // Verify DB
    const row = database.db.prepare(`SELECT * FROM users WHERE id = 2`).get();
    expect(row).toBeUndefined(); // Should be fully deleted
  });
});
