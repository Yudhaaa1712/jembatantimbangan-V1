/**
 * Users Routes
 * Replaces: modules/users/index.php
 */
const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { query, queryOne, jsonResponse, cleanInput } = require('../config/database');
const { isLoggedIn, requireRole, getCurrentUser } = require('../middleware/auth');

router.use(isLoggedIn);

// GET /users/list
router.get('/list', requireRole('admin'), async (req, res) => {
  try {
    const users = await query(`SELECT id, username, nama_lengkap, role, status, created_at, last_login FROM users ORDER BY nama_lengkap`);
    return jsonResponse(res, true, 'Users list', users);
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// POST /users/add
router.post('/add', requireRole('admin'), async (req, res) => {
  try {
    const username    = cleanInput(req.body.username);
    const namaLengkap = cleanInput(req.body.nama_lengkap);
    const password    = req.body.password;
    const role        = cleanInput(req.body.role) || 'operator';

    if (!username || !password || !namaLengkap) return jsonResponse(res, false, 'Data tidak lengkap');

    const existing = await queryOne(`SELECT id FROM users WHERE username=?`, [username]);
    if (existing) return jsonResponse(res, false, 'Username sudah digunakan');

    const hashedPassword = await bcrypt.hash(password, 10);
    const result = await query(
      `INSERT INTO users (username, nama_lengkap, password, role, status, created_at) VALUES (?,?,?,?,'active',NOW())`,
      [username, namaLengkap, hashedPassword, role]
    );
    return jsonResponse(res, true, 'User berhasil ditambahkan', { id: result.insertId });
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// PUT /users/:id
router.put('/:id', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const namaLengkap = cleanInput(req.body.nama_lengkap);
    const role   = cleanInput(req.body.role);
    const status = cleanInput(req.body.status);
    await query(`UPDATE users SET nama_lengkap=?, role=?, status=?, updated_at=NOW() WHERE id=?`, [namaLengkap, role, status, id]);
    return jsonResponse(res, true, 'User berhasil diupdate');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// POST /users/:id/reset-password
router.post('/:id/reset-password', requireRole('admin'), async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const newPass = req.body.new_password;
    if (!newPass || newPass.length < 6) return jsonResponse(res, false, 'Password minimal 6 karakter');
    const hashed = await bcrypt.hash(newPass, 10);
    await query(`UPDATE users SET password=?, updated_at=NOW() WHERE id=?`, [hashed, id]);
    return jsonResponse(res, true, 'Password berhasil direset');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// DELETE /users/:id
router.delete('/:id', requireRole('admin'), async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const id = parseInt(req.params.id);
    if (id === user.id) return jsonResponse(res, false, 'Tidak bisa menonaktifkan akun sendiri');
    await query(`UPDATE users SET status='inactive', updated_at=NOW() WHERE id=?`, [id]);
    return jsonResponse(res, true, 'User dinonaktifkan');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

// DELETE /users/:id/hard
router.delete('/:id/hard', requireRole('admin'), async (req, res) => {
  try {
    const user = getCurrentUser(req);
    const id = parseInt(req.params.id);
    if (id === user.id) return jsonResponse(res, false, 'Tidak bisa menghapus akun sendiri');
    await query(`DELETE FROM users WHERE id=?`, [id]);
    return jsonResponse(res, true, 'User berhasil dihapus permanen');
  } catch (err) { return jsonResponse(res, false, err.message); }
});

module.exports = router;
