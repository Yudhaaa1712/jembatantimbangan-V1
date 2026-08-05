/**
 * License Routes
 * Replaces: license/activate.php, license/check.php
 */
const express = require('express');
const router = express.Router();
const { generateHardwareId, isLicensed, activateLicense, getLicenseInfo } = require('../helpers/license');

// GET /license/status
router.get('/status', (req, res) => {
  const licensed = isLicensed();
  const info = getLicenseInfo();
  res.json({
    success: true,
    licensed,
    isLicensed: licensed,          // alias untuk frontend
    hwid: generateHardwareId(),
    info,
    licenseData: info              // alias untuk frontend
  });
});

// GET /license/hwid
router.get('/hwid', (req, res) => {
  const hwid = generateHardwareId();
  res.json({ success: true, hwid });
});

// POST /license/activate
router.post('/activate', (req, res) => {
  const licenseKey = req.body.license_key;
  if (!licenseKey) return res.json({ success: false, message: 'Kunci lisensi diperlukan' });
  const result = activateLicense(licenseKey);
  res.json(result);
});

// CATATAN KEAMANAN:
// Endpoint POST /license/generate SENGAJA DIHAPUS. Dulu endpoint itu bisa
// membuat lisensi hanya dengan master key yang tertulis di source code, jadi
// siapa pun yang membaca kode bisa membuat lisensi permanen sendiri.
// Pembuatan lisensi sekarang HANYA lewat `node keygen.js` di komputer developer,
// yang membutuhkan private key Ed25519 (license-keys/private.pem).

module.exports = router;
