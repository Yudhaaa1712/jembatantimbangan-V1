/**
 * WEIGHBRIDGE LICENSE GENERATOR — Arroyan Jv Teknik
 *
 * File ini TIDAK ikut ter-build ke installer (lihat "files" di package.json).
 * Ia butuh license-keys/private.pem yang hanya ada di komputer Anda.
 *
 * Jalankan:  node keygen.js
 */
const readline = require('readline');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const PRODUCT = 'weighbridge-arroyan';
const KEY_PREFIX = 'WBA1';
// Private key dicari berurutan:
//   1. license-keys/private.pem di folder ini (folder klien)
//   2. ../arroyan-app/license-keys/private.pem (template — sumber utama)
//   3. license-keys/private.pem di folder induk (D:\jembatantimbangan)
// Semua klien memakai public key yang sama, jadi satu private key cukup.
const PRIVATE_KEY_CANDIDATES = [
  path.join(__dirname, 'license-keys', 'private.pem'),
  path.join(__dirname, '..', 'arroyan-app', 'license-keys', 'private.pem'),
  path.join(__dirname, '..', 'license-keys', 'private.pem')
];

function resolvePrivateKeyPath() {
  return PRIVATE_KEY_CANDIDATES.find(p => fs.existsSync(p)) || null;
}

function loadPrivateKey() {
  const keyPath = resolvePrivateKeyPath();
  if (!keyPath) {
    throw new Error(
      'Private key tidak ditemukan. Lokasi yang dicek:\n' +
      PRIVATE_KEY_CANDIDATES.map(p => '     - ' + p).join('\n') + '\n' +
      '   Tanpa file ini lisensi tidak bisa dibuat. Pastikan Anda menyimpan cadangannya!'
    );
  }
  return crypto.createPrivateKey(fs.readFileSync(keyPath, 'utf8'));
}

function generateLicenseKey(hardwareId, expiryDays = 0) {
  const payload = {
    hwid: hardwareId,
    expiry: expiryDays > 0
      ? new Date(Date.now() + expiryDays * 86400000).toISOString()
      : 'permanent',
    product: PRODUCT,
    issued: new Date().toISOString(),
    id: crypto.randomBytes(6).toString('hex')
  };

  const payloadB64 = Buffer.from(JSON.stringify(payload), 'utf8').toString('base64url');
  const signature = crypto.sign(null, Buffer.from(payloadB64, 'utf8'), loadPrivateKey());

  return `${KEY_PREFIX}.${payloadB64}.${signature.toString('base64url')}`;
}

// ─── CLI ──────────────────────────────────────────────────────────────────────

function runCli() {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

  console.log('=============================================');
  console.log('   🔑 WEIGHBRIDGE LICENSE GENERATOR 🔑');
  console.log('=============================================\n');

  rl.question('Masukkan Hardware ID klien: ', (hwid) => {
    if (!hwid.trim()) {
      console.log('Hardware ID tidak boleh kosong!');
      return rl.close();
    }

    rl.question('Masa aktif (dalam hari) [Ketik 0 untuk permanen]: ', (daysInput) => {
      const days = parseInt(daysInput) || 0;

      try {
        const licenseKey = generateLicenseKey(hwid.trim(), days);
        const masa = days > 0
          ? `${days} hari (berlaku s/d ${new Date(Date.now() + days * 86400000).toLocaleString('id-ID')})`
          : 'PERMANEN';

        console.log('\n=============================================');
        console.log('✅ BERHASIL! INI ADALAH KUNCI LISENSINYA:');
        console.log(`   Masa aktif: ${masa}`);
        console.log('=============================================\n');
        console.log(licenseKey);
        console.log('\n=============================================');
        console.log('Silakan copy (blok teks di atas lalu klik kanan) dan kirimkan ke klien.');
      } catch (err) {
        console.log('\n❌ Terjadi kesalahan saat membuat lisensi: ', err.message);
      }

      rl.close();
    });
  });
}

// CLI hanya jalan kalau file ini dieksekusi langsung (`node keygen.js`),
// bukan saat di-require oleh test.
if (require.main === module) runCli();

module.exports = { generateLicenseKey };
