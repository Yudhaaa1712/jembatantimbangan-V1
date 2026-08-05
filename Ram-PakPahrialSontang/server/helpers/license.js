/**
 * License Manager — Hardware Binding with AES-256
 * Weighbridge - Arroyan Jv Teknik
 *
 * How it works:
 * 1. Generate Hardware ID from CPU, MAC, Disk serial (multi-factor)
 * 2. License key is HMAC-SHA256(hardwareId + salt) encrypted with AES-256
 * 3. License stored in encrypted JSON file
 * 4. Verified on every app start
 */
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { machineIdSync } = require('node-machine-id');

const LICENSE_FILE = path.join(os.homedir(), '.weighbridge', 'license.dat');
const SALT = 'WB-ARROYAN-2024-SALT-KEY';
const ENCRYPTION_KEY = crypto.scryptSync('weighbridge-arroyan-master', SALT, 32);

// ─── Hardware ID Generation ──────────────────────────────────────────────────

function getCpuInfo() {
  const cpus = os.cpus();
  return cpus.length > 0 ? `${cpus[0].model}-${cpus.length}` : 'unknown';
}

function getMachineId() {
  try {
    // Uses OS-level UUIDs (very stable, survives network/dongle/hostname changes)
    return machineIdSync({ original: true });
  } catch {
    return 'unknown-machine-id';
  }
}

function generateHardwareId() {
  const components = [
    getCpuInfo(),
    getMachineId()
  ];
  const combined = components.join('|');
  return crypto.createHmac('sha256', SALT).update(combined).digest('hex');
}

// ─── Encryption ──────────────────────────────────────────────────────────────

function encrypt(text) {
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv('aes-256-cbc', ENCRYPTION_KEY, iv);
  const encrypted = Buffer.concat([cipher.update(text, 'utf8'), cipher.final()]);
  return iv.toString('hex') + ':' + encrypted.toString('hex');
}

function decrypt(encryptedText) {
  const [ivHex, encHex] = encryptedText.split(':');
  const iv = Buffer.from(ivHex, 'hex');
  const encrypted = Buffer.from(encHex, 'hex');
  const decipher = crypto.createDecipheriv('aes-256-cbc', ENCRYPTION_KEY, iv);
  return Buffer.concat([decipher.update(encrypted), decipher.final()]).toString('utf8');
}

// ─── License Key Generation ──────────────────────────────────────────────────

function generateLicenseKey(hardwareId, expiryDays = 0) {
  const expiry = expiryDays > 0
    ? new Date(Date.now() + expiryDays * 86400000).toISOString()
    : 'permanent';

  const payload = JSON.stringify({
    hwid: hardwareId,
    expiry,
    product: 'weighbridge-arroyan',
    issued: new Date().toISOString()
  });

  return encrypt(payload);
}

// ─── License Verification ────────────────────────────────────────────────────

function isLicensed() {
  try {
    if (!fs.existsSync(LICENSE_FILE)) return false;

    const encryptedData = fs.readFileSync(LICENSE_FILE, 'utf8').trim();
    if (!encryptedData) return false;

    const payload = JSON.parse(decrypt(encryptedData));

    // Check product
    if (payload.product !== 'weighbridge-arroyan') return false;

    // Check expiry
    if (payload.expiry !== 'permanent') {
      const expiry = new Date(payload.expiry);
      if (expiry < new Date()) return false;
    }

    // Check hardware binding
    const currentHwid = generateHardwareId();
    if (payload.hwid !== currentHwid) return false;

    return true;
  } catch {
    return false;
  }
}

function activateLicense(licenseKey) {
  try {
    const payload = JSON.parse(decrypt(licenseKey));

    // Validate product
    if (payload.product !== 'weighbridge-arroyan') {
      return { success: false, message: 'Kunci lisensi tidak valid (produk tidak cocok)' };
    }

    // Validate hardware
    const currentHwid = generateHardwareId();
    if (payload.hwid !== currentHwid) {
      return { success: false, message: 'Kunci lisensi tidak cocok dengan perangkat ini' };
    }

    // Check expiry
    if (payload.expiry !== 'permanent') {
      const expiry = new Date(payload.expiry);
      if (expiry < new Date()) {
        return { success: false, message: 'Kunci lisensi sudah kedaluwarsa' };
      }
    }

    // Save license
    const dir = path.dirname(LICENSE_FILE);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(LICENSE_FILE, licenseKey, 'utf8');

    return { success: true, message: 'Aktivasi berhasil!', expiry: payload.expiry };
  } catch (err) {
    return { success: false, message: 'Kunci lisensi tidak valid: ' + err.message };
  }
}

function getLicenseInfo() {
  try {
    if (!fs.existsSync(LICENSE_FILE)) return null;
    const encryptedData = fs.readFileSync(LICENSE_FILE, 'utf8').trim();
    const payload = JSON.parse(decrypt(encryptedData));
    return {
      hwid: payload.hwid,
      expiry: payload.expiry,
      issued: payload.issued,
      currentHwid: generateHardwareId(),
      isValid: isLicensed()
    };
  } catch {
    return null;
  }
}

module.exports = { generateHardwareId, generateLicenseKey, isLicensed, activateLicense, getLicenseInfo };
