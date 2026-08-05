/**
 * License Manager — Ed25519 Signature + Hardware Binding
 * Weighbridge - Arroyan Jv Teknik
 *
 * PENTING: File ini HANYA bisa MEMVERIFIKASI lisensi, tidak bisa membuatnya.
 * Pembuatan lisensi butuh PRIVATE KEY yang hanya ada di komputer developer
 * (license-keys/private.pem — tidak pernah ikut ter-build ke installer).
 *
 * Cara kerja:
 * 1. Hardware ID dibuat dari CPU + machine UUID (HMAC-SHA256).
 * 2. Kunci lisensi = payload(base64url) + tanda tangan Ed25519.
 *    Dipalsukan tidak bisa tanpa private key, walaupun app.asar dibongkar.
 * 3. Lisensi disimpan di ~/.weighbridge/license.dat
 * 4. Anti-mundur jam: waktu terakhir dilihat disimpan terenkripsi di 2 lokasi.
 *    Kalau jam sistem dimundurkan lebih dari 1 hari, aplikasi dikunci.
 * 5. Diverifikasi setiap request (middleware global di server/app.js).
 */
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { machineIdSync } = require('node-machine-id');

// ─── Konstanta ────────────────────────────────────────────────────────────────

const PRODUCT = 'weighbridge-arroyan';
const KEY_PREFIX = 'WBA1';
const SALT = 'WB-ARROYAN-2024-SALT-KEY';

// Public key — aman untuk ikut didistribusikan. Hanya bisa memverifikasi.
const LICENSE_PUBLIC_KEY = `-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAE+ImzEpXVuNnajiFb7Q55VwdsGh5okecXM/5gqq7JKI=
-----END PUBLIC KEY-----`;

// Toleransi mundurnya jam sistem sebelum aplikasi dikunci (1 hari).
const CLOCK_ROLLBACK_TOLERANCE_MS = 24 * 60 * 60 * 1000;
// Tulis ulang penanda waktu paling cepat setiap 5 menit (hemat I/O).
const HEARTBEAT_INTERVAL_MS = 5 * 60 * 1000;

const LICENSE_FILE = path.join(os.homedir(), '.weighbridge', 'license.dat');

// State disimpan di dua lokasi independen. Menghapus salah satunya tidak
// mematikan proteksi, karena nilai yang dipakai adalah yang paling baru.
function getStatePaths() {
  const paths = [path.join(os.homedir(), '.weighbridge', 'state.dat')];
  try {
    let folder = 'weighbridge-arroyan';
    const pkgPath = path.join(__dirname, '..', '..', 'package.json');
    if (fs.existsSync(pkgPath)) {
      const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
      if (pkg.name) folder = pkg.name;
    }
    const base = process.env.APPDATA || process.env.USERPROFILE || os.homedir();
    paths.push(path.join(base, folder, '.licstate'));
  } catch {
    /* abaikan — satu lokasi sudah cukup untuk jalan */
  }
  return paths;
}

// ─── Hardware ID ──────────────────────────────────────────────────────────────

function getCpuInfo() {
  const cpus = os.cpus();
  return cpus.length > 0 ? `${cpus[0].model}-${cpus.length}` : 'unknown';
}

function getMachineId() {
  try {
    // UUID level OS — stabil, tahan terhadap ganti jaringan/dongle/hostname
    return machineIdSync({ original: true });
  } catch {
    return 'unknown-machine-id';
  }
}

// Hardware ID tidak berubah selama aplikasi berjalan, dan machineIdSync
// memanggil proses OS (mahal). Middleware memeriksa lisensi di SETIAP request,
// jadi hasilnya wajib di-cache agar aplikasi tidak melambat.
let _hwidCache = null;

function generateHardwareId() {
  if (_hwidCache) return _hwidCache;
  const combined = [getCpuInfo(), getMachineId()].join('|');
  _hwidCache = crypto.createHmac('sha256', SALT).update(combined).digest('hex');
  return _hwidCache;
}

// ─── Enkripsi state (terikat ke hardware) ─────────────────────────────────────

// scryptSync sengaja dibuat lambat — hitung sekali saja, lalu simpan di memori.
let _stateKeyCache = null;

function getStateKey() {
  // Diturunkan dari hardware ID → file state tidak bisa disalin antar komputer
  if (!_stateKeyCache) {
    _stateKeyCache = crypto.scryptSync(generateHardwareId(), SALT + '-state', 32);
  }
  return _stateKeyCache;
}

function encryptState(obj) {
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv('aes-256-cbc', getStateKey(), iv);
  const enc = Buffer.concat([cipher.update(JSON.stringify(obj), 'utf8'), cipher.final()]);
  return iv.toString('hex') + ':' + enc.toString('hex');
}

function decryptState(text) {
  const [ivHex, encHex] = String(text).trim().split(':');
  const decipher = crypto.createDecipheriv('aes-256-cbc', getStateKey(), Buffer.from(ivHex, 'hex'));
  const dec = Buffer.concat([decipher.update(Buffer.from(encHex, 'hex')), decipher.final()]);
  return JSON.parse(dec.toString('utf8'));
}

// ─── Anti-mundur jam sistem ───────────────────────────────────────────────────

function readLastSeen() {
  let latest = 0;
  for (const p of getStatePaths()) {
    try {
      if (!fs.existsSync(p)) continue;
      const state = decryptState(fs.readFileSync(p, 'utf8'));
      const t = Number(state.lastSeen) || 0;
      if (t > latest) latest = t;
    } catch {
      /* file rusak/dirusak → abaikan lokasi ini */
    }
  }
  return latest;
}

function writeLastSeen(timestamp) {
  const payload = encryptState({ lastSeen: timestamp, hwid: generateHardwareId() });
  for (const p of getStatePaths()) {
    try {
      fs.mkdirSync(path.dirname(p), { recursive: true });
      fs.writeFileSync(p, payload, 'utf8');
    } catch {
      /* lokasi tidak bisa ditulis → lokasi lain masih menutupi */
    }
  }
}

/**
 * @returns {boolean} true kalau jam sistem terdeteksi dimundurkan.
 */
function isClockTampered() {
  const now = Date.now();
  const lastSeen = readLastSeen();
  if (lastSeen && now < lastSeen - CLOCK_ROLLBACK_TOLERANCE_MS) return true;
  if (now > lastSeen + HEARTBEAT_INTERVAL_MS) writeLastSeen(now);
  return false;
}

// ─── Parsing & verifikasi kunci lisensi ───────────────────────────────────────

/**
 * Membongkar kunci lisensi dan memeriksa tanda tangannya.
 * @returns {{ok: true, payload: object} | {ok: false, reason: string}}
 */
function parseLicenseKey(licenseKey) {
  try {
    const cleaned = String(licenseKey).replace(/\s+/g, '');
    const parts = cleaned.split('.');
    if (parts.length !== 3 || parts[0] !== KEY_PREFIX) {
      return { ok: false, reason: 'Format kunci lisensi tidak dikenali' };
    }

    const [, payloadB64, sigB64] = parts;
    const signed = crypto.verify(
      null,
      Buffer.from(payloadB64, 'utf8'),
      LICENSE_PUBLIC_KEY,
      Buffer.from(sigB64, 'base64url')
    );
    if (!signed) return { ok: false, reason: 'Tanda tangan lisensi tidak sah (kunci palsu)' };

    const payload = JSON.parse(Buffer.from(payloadB64, 'base64url').toString('utf8'));
    if (payload.product !== PRODUCT) {
      return { ok: false, reason: 'Kunci lisensi tidak valid (produk tidak cocok)' };
    }
    return { ok: true, payload };
  } catch (err) {
    return { ok: false, reason: 'Kunci lisensi tidak valid: ' + err.message };
  }
}

/**
 * Memeriksa isi lisensi terhadap kondisi komputer saat ini.
 * @returns {{ok: true} | {ok: false, reason: string}}
 */
function checkPayload(payload) {
  if (payload.hwid !== generateHardwareId()) {
    return { ok: false, reason: 'Kunci lisensi tidak cocok dengan perangkat ini' };
  }
  if (payload.expiry !== 'permanent') {
    const expiry = new Date(payload.expiry);
    if (isNaN(expiry.getTime())) return { ok: false, reason: 'Tanggal kedaluwarsa tidak valid' };
    if (expiry < new Date()) return { ok: false, reason: 'Kunci lisensi sudah kedaluwarsa' };
  }
  return { ok: true };
}

// ─── API publik ───────────────────────────────────────────────────────────────

function isLicensed() {
  try {
    if (!fs.existsSync(LICENSE_FILE)) return false;

    const data = fs.readFileSync(LICENSE_FILE, 'utf8').trim();
    if (!data) return false;

    const parsed = parseLicenseKey(data);
    if (!parsed.ok) return false;

    // Lisensi permanen tidak terpengaruh jam sistem
    if (parsed.payload.expiry !== 'permanent' && isClockTampered()) return false;

    return checkPayload(parsed.payload).ok;
  } catch {
    return false;
  }
}

function activateLicense(licenseKey) {
  const parsed = parseLicenseKey(licenseKey);
  if (!parsed.ok) return { success: false, message: parsed.reason };

  if (parsed.payload.expiry !== 'permanent' && isClockTampered()) {
    return {
      success: false,
      message: 'Jam sistem komputer terdeteksi dimundurkan. Perbaiki tanggal & jam Windows, lalu coba lagi.'
    };
  }

  const check = checkPayload(parsed.payload);
  if (!check.ok) return { success: false, message: check.reason };

  try {
    fs.mkdirSync(path.dirname(LICENSE_FILE), { recursive: true });
    fs.writeFileSync(LICENSE_FILE, String(licenseKey).replace(/\s+/g, ''), 'utf8');
    writeLastSeen(Date.now());
  } catch (err) {
    return { success: false, message: 'Gagal menyimpan lisensi: ' + err.message };
  }

  return { success: true, message: 'Aktivasi berhasil!', expiry: parsed.payload.expiry };
}

function getLicenseInfo() {
  try {
    if (!fs.existsSync(LICENSE_FILE)) return null;

    const data = fs.readFileSync(LICENSE_FILE, 'utf8').trim();
    const parsed = parseLicenseKey(data);
    if (!parsed.ok) {
      return { isValid: false, reason: parsed.reason, currentHwid: generateHardwareId() };
    }

    const { hwid, expiry, issued } = parsed.payload;
    const tampered = expiry !== 'permanent' && isClockTampered();
    const check = checkPayload(parsed.payload);

    let daysLeft = null;
    if (expiry !== 'permanent') {
      daysLeft = Math.max(0, Math.ceil((new Date(expiry) - new Date()) / 86400000));
    }

    return {
      hwid,
      expiry,
      issued,
      daysLeft,
      currentHwid: generateHardwareId(),
      isValid: !tampered && check.ok,
      reason: tampered ? 'Jam sistem terdeteksi dimundurkan' : (check.ok ? null : check.reason)
    };
  } catch {
    return null;
  }
}

module.exports = {
  generateHardwareId,
  isLicensed,
  activateLicense,
  getLicenseInfo,
  parseLicenseKey
};
