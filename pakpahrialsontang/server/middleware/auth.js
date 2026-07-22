/**
 * Auth Middleware
 * Replaces: is_logged_in(), check_role() from database.php
 */

/**
 * Middleware: require login
 * Replaces: check_role([]) with redirect to login
 */
function isLoggedIn(req, res, next) {
  if (req.session && req.session.user_id) {
    return next();
  }
  // For AJAX requests return JSON, for page requests redirect
  if (req.xhr || req.headers.accept?.includes('application/json')) {
    return res.status(401).json({ success: false, message: 'Session habis. Silakan login kembali.', redirect: '/auth/login' });
  }
  return res.redirect('/auth/login');
}

/**
 * Middleware: require specific roles
 * Replaces: check_role(['admin', 'operator'])
 */
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.session || !req.session.user_id) {
      if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.status(401).json({ success: false, message: 'Tidak terautentikasi.', redirect: '/auth/login' });
      }
      return res.redirect('/auth/login');
    }

    const userRole = req.session.user_role;

    // Admin can access everything
    if (userRole === 'admin') return next();

    if (roles.length > 0 && !roles.includes(userRole)) {
      if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.status(403).json({ success: false, message: `Akses ditolak. Role Anda: ${userRole}. Dibutuhkan: ${roles.join(', ')}.` });
      }
      return res.status(403).send(`<div style="padding:20px;font-family:Arial">
        <h3>Akses Ditolak</h3>
        <p>Role Anda: <strong>${userRole}</strong></p>
        <p>Dibutuhkan: <strong>${roles.join(', ')}</strong></p>
        <a href="javascript:history.back()">← Kembali</a>
      </div>`);
    }

    next();
  };
}

/**
 * Get current user from session
 */
function getCurrentUser(req) {
  return {
    id:           req.session.user_id,
    username:     req.session.username,
    nama_lengkap: req.session.nama_lengkap,
    role:         req.session.user_role,
  };
}

module.exports = { isLoggedIn, requireRole, getCurrentUser };
