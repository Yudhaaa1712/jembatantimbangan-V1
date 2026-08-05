/**
 * Auth Routes
 * Replaces: modules/auth/login.php, modules/auth/logout.php
 */
const express = require('express');
const bcrypt = require('bcryptjs');
const router = express.Router();
const { query, jsonResponse } = require('../config/database');

// POST /auth/login
router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body;

    if (!username || !password) {
      return jsonResponse(res, false, 'Username dan password harus diisi');
    }

    // Find active user (replaces prepared statement query)
    const users = await query(
      `SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1`,
      [username.trim()]
    );

    if (users.length === 0) {
      return jsonResponse(res, false, 'Username atau password salah!');
    }

    const user = users[0];

    // Verify password (bcrypt, same as PHP password_verify)
    const isValid = await bcrypt.compare(password, user.password);
    if (!isValid) {
      return jsonResponse(res, false, 'Username atau password salah!');
    }

    // Set session (replaces $_SESSION)
    req.session.user_id     = user.id;
    req.session.username    = user.username;
    req.session.nama_lengkap = user.nama_lengkap;
    req.session.user_role   = user.role;
    req.session.login_time  = Date.now();

    console.log(`[Auth] Login: ${username} (${user.role})`);
    return jsonResponse(res, true, 'Login berhasil!', { redirect: '/timbangan/1' });

  } catch (err) {
    console.error('[Auth] Login error:', err);
    return jsonResponse(res, false, 'Terjadi kesalahan sistem');
  }
});

// POST /auth/logout
router.post('/logout', (req, res) => {
  req.session.destroy((err) => {
    if (err) console.error('[Auth] Logout error:', err);
    res.json({ success: true, redirect: '/auth/login' });
  });
});

// GET /auth/session — check current session info
router.get('/session', (req, res) => {
  if (!req.session.user_id) {
    return res.json({ loggedIn: false });
  }
  res.json({
    loggedIn: true,
    user_id:      req.session.user_id,
    username:     req.session.username,
    nama_lengkap: req.session.nama_lengkap,
    user_role:    req.session.user_role,
  });
});

module.exports = router;
