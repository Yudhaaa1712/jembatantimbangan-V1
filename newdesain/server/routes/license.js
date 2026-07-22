/**
 * License Routes
 * Replaces: license/activate.php, license/check.php
 */
const express = require('express');
const router = express.Router();
const { generateHardwareId, isLicensed, activateLicense, getLicenseInfo, generateLicenseKey } = require('../helpers/license');

// GET /license/status
router.get('/status', (req, res) => {
  const licensed = isLicensed();
  const info = getLicenseInfo();
  res.json({ success: true, licensed, info });
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

// POST /license/generate (admin tool — only for dev/internal use)
router.post('/generate', (req, res) => {
  const masterKey = req.body.master_key;
  if (masterKey !== 'ARROYAN-MASTER-2024') {
    return res.json({ success: false, message: 'Master key tidak valid' });
  }
  const hwid = req.body.hwid || generateHardwareId();
  const expiryDays = parseInt(req.body.expiry_days) || 0;
  const licenseKey = generateLicenseKey(hwid, expiryDays);
  res.json({ success: true, license_key: licenseKey, hwid });
});

module.exports = router;
