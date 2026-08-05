const fs = require('fs');
const path = require('path');

const dbDir = path.join(process.env.APPDATA || process.env.USERPROFILE || '.', 'weighbridge-arroyan');

console.log('==============================================');
console.log('🧹 PRE-BUILD CLEANUP (MEMBUAT APLIKASI FRESH)');
console.log('==============================================');
console.log('Target direktori: ' + dbDir);

if (fs.existsSync(dbDir)) {
    try {
        // Hapus folder beserta isinya
        fs.rmSync(dbDir, { recursive: true, force: true });
        console.log('✅ Semua database, cache, dan riwayat lama berhasil di-reset.');
    } catch (e) {
        console.error('❌ Gagal menghapus direktori data (Mungkin aplikasi masih terbuka):', e.message);
        // Do not crash the build, just warn
    }
} else {
    console.log('✅ Data sudah kosong.');
}

console.log('🚀 Melanjutkan proses Build (npm run build)...\n');
