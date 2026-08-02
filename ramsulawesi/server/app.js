/**
 * Express Server — Weighbridge Arroyan
 * Replaces Apache + PHP backend
 */
require('dotenv').config();
const express = require('express');

// ─── Global Error Handlers (PENTING: mencegah server mati mendadak) ──────────
process.on('uncaughtException', (err) => {
  console.error('[FATAL] Uncaught Exception:', err.message);
  console.error(err.stack);
  // JANGAN exit — biarkan server tetap jalan
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[FATAL] Unhandled Promise Rejection:', reason);
  // JANGAN exit — biarkan server tetap jalan
});
const session = require('express-session');
const path = require('path');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 3737;

// ─── Middleware ────────────────────────────────────────────────────────────────
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cors({ origin: `http://localhost:${PORT}`, credentials: true }));

// Global Anti-Cache Middleware (Mencegah Electron menyimpan memori halaman lama)
app.use((req, res, next) => {
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
  res.setHeader('Pragma', 'no-cache');
  res.setHeader('Expires', '0');
  res.setHeader('Surrogate-Control', 'no-store');
  next();
});

// Session (replaces PHP $_SESSION)
app.use(session({
  secret: 'weighbridge-arroyan-2024-secret-key',
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    secure: false,
    maxAge: 8 * 60 * 60 * 1000, // 8 jam (same as PHP session.gc_maxlifetime)
    sameSite: 'lax'
  }
}));

// ─── Static Files ──────────────────────────────────────────────────────────────
app.use('/assets', express.static(path.join(__dirname, '..', 'renderer', 'assets')));
app.use('/js', express.static(path.join(__dirname, '..', 'renderer', 'js')));

// ─── Routes ────────────────────────────────────────────────────────────────────
const authRoutes       = require('./routes/auth');
const timbanganRoutes  = require('./routes/timbangan');
const transaksiRoutes  = require('./routes/transaksi');
const masterdataRoutes = require('./routes/masterdata');
const pengirimanRoutes = require('./routes/pengiriman');
const usersRoutes      = require('./routes/users');
const setupRoutes      = require('./routes/setup');
const licenseRoutes    = require('./routes/license');
const kasRoutes        = require('./routes/kas');
const hutangRoutes     = require('./routes/hutang');
const upahRoutes = require('./routes/upah');
const tkbmRoutes = require('./routes/tkbm');
const pembayaranRoutes = require('./routes/pembayaran');
const { isLicensed } = require('./helpers/license');

// --- Global License Check Middleware ---
app.use((req, res, next) => {
  // Allow static files, license API, activation page, and logout
  const allowedPaths = ['/assets', '/js', '/license', '/activation', '/auth/logout'];
  if (allowedPaths.some(p => req.path.startsWith(p))) {
    return next();
  }
  
  if (!isLicensed()) {
    if (req.xhr || req.headers.accept?.includes('application/json')) {
      return res.status(403).json({ success: false, message: 'Aplikasi belum diaktivasi atau lisensi tidak valid.', redirect: '/activation' });
    }
    return res.redirect('/activation');
  }
  next();
});

// --- Register Routes ---
app.use('/auth', authRoutes);
app.use('/timbangan', timbanganRoutes);
app.use('/transaksi', transaksiRoutes);
app.use('/pengiriman', pengirimanRoutes);
app.use('/masterdata', masterdataRoutes);
app.use('/users', usersRoutes);
app.use('/setup', setupRoutes);
app.use('/kas', kasRoutes);
app.use('/hutang', hutangRoutes);
app.use('/upah-api', upahRoutes);
app.use('/license', licenseRoutes);
app.use('/hutang', hutangRoutes);
app.use('/hutang-supir-api', hutangRoutes);      // kompatibilitas: check-debt & supir-aktif (Timbangan/Upah)
app.use('/hutang-supplier-api', hutangRoutes);   // kompatibilitas: check-debt (Timbangan)
app.use('/tkbm-api', tkbmRoutes);
app.use('/pembayaran-api', pembayaranRoutes);

// ─── Page Routes (serve HTML) ──────────────────────────────────────────────────
const { isLoggedIn } = require('./middleware/auth');
const pagesDir = path.join(__dirname, '..', 'renderer', 'pages');

// Activation page (public if not licensed)
app.get('/activation', (req, res) => {
  if (isLicensed()) return res.redirect('/');
  res.sendFile(path.join(pagesDir, 'activation.html'));
});

// Root → redirect to login or timbangan1
app.get('/', (req, res) => {
  if (req.session && req.session.user_id) {
    res.redirect('/timbangan/1');
  } else {
    res.redirect('/auth/login');
  }
});

// Auth pages
app.get('/auth/login', (req, res) => {
  if (req.session && req.session.user_id) return res.redirect('/timbangan/1');
  res.sendFile(path.join(pagesDir, 'login.html'));
});

// Protected pages (require login)
app.get('/timbangan/1',    isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'timbangan1.html')));
app.get('/timbangan/2',    isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'timbangan2.html')));
app.get('/transaksi',      isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'transaksi.html')));
app.get('/masterdata',     isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'masterdata.html')));
app.get('/pengiriman',     isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'pengiriman.html')));
app.get('/keuangan',       isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'keuangan.html')));
app.get('/users',          isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'users.html')));
app.get('/setup',          isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'setup.html')));
app.get('/hutang',         isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'hutang.html')));
app.get('/upah',           isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'upah.html')));
app.get('/tkbm',           isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'tkbm.html')));
app.get('/view-data',      isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'view_data.html')));
app.get('/pembayaran',     isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'pembayaran.html')));

// Print ticket page
app.get('/print-ticket/:no_tiket', isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'print_ticket.html')));

// Print surat jalan pengiriman pabrik
app.get('/surat-jalan/:id', isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'surat_jalan.html')));

// Print riwayat hutang (kartu piutang)
app.get('/hutang-print/:type/:id', isLoggedIn, (req, res) => res.sendFile(path.join(pagesDir, 'print_hutang.html')));

app.post('/timbangan/debug-calc', (req, res) => {
  try {
    require('fs').writeFileSync(path.join(__dirname, '..', '..', 'debug_calc.json'), JSON.stringify(req.body, null, 2), 'utf8');
  } catch(e) {
    console.error('Debug write error:', e);
  }
  res.json({ success: true });
});

// 404
app.use((req, res) => {
  res.status(404).sendFile(path.join(pagesDir, '404.html'));
});

// Error handler
app.use((err, req, res, next) => {
  console.error('[Express Error]', err);
  res.status(500).json({ success: false, message: 'Internal server error', error: err.message });
});

// ─── Start Server ─────────────────────────────────────────────────────────────
const server = app.listen(PORT, '127.0.0.1', () => {
  console.log(`Server running on port ${PORT}`);
});

server.on('error', (err) => {
  console.error('[Server] Listen error:', err.message);
});

module.exports = app;
