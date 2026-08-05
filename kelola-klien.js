/**
 * Kelola Klien - Weighbridge Client Manager
 * Jalankan: node kelola-klien.js
 * 
 * Script ini mengelola folder klien untuk aplikasi Weighbridge.
 * Setiap klien punya folder sendiri dengan source code terpisah,
 * tapi berbagi node_modules dari template (hemat ~488MB per klien).
 */

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { execSync } = require('child_process');

const ROOT_DIR = __dirname;
const TEMPLATE_NAME = 'arroyan-app';
const TEMPLATE_DIR = path.join(ROOT_DIR, TEMPLATE_NAME);

// Folder yang tidak boleh dianggap sebagai klien
const EXCLUDED_DIRS = [TEMPLATE_NAME, 'electron-app', 'node_modules', '.git', '.claude', '.agents'];

// Folder & file yang harus dicopy per klien (source code saja)
const COPY_ITEMS = [
    'electron',
    'server',
    'renderer',
    'styles',
    'build-resources',
    'scripts',
    'package.json',
    'package-lock.json',
    'tailwind.config.js',
    '.gitignore',
    '.env',
    'keygen.js',
    'kill-zombies.bat'
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function copyRecursiveSync(src, dest) {
    if (!fs.existsSync(src)) return;
    const stats = fs.statSync(src);
    
    if (stats.isDirectory()) {
        if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
        fs.readdirSync(src).forEach(child => {
            copyRecursiveSync(path.join(src, child), path.join(dest, child));
        });
    } else {
        fs.copyFileSync(src, dest);
    }
}

function getClients() {
    if (!fs.existsSync(ROOT_DIR)) return [];
    return fs.readdirSync(ROOT_DIR).filter(name => {
        if (EXCLUDED_DIRS.includes(name) || name.startsWith('.')) return false;
        const fullPath = path.join(ROOT_DIR, name);
        return fs.statSync(fullPath).isDirectory() && fs.existsSync(path.join(fullPath, 'package.json'));
    });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}

function getDirSize(dirPath) {
    let totalSize = 0;
    try {
        const items = fs.readdirSync(dirPath);
        for (const item of items) {
            const fullPath = path.join(dirPath, item);
            const stats = fs.lstatSync(fullPath);
            if (stats.isSymbolicLink()) continue; // skip junctions
            if (stats.isDirectory()) {
                totalSize += getDirSize(fullPath);
            } else {
                totalSize += stats.size;
            }
        }
    } catch (e) { /* ignore */ }
    return totalSize;
}

// ─── Core Functions ──────────────────────────────────────────────────────────

function createClient(folderName, clientName) {
    const clientDir = path.join(ROOT_DIR, folderName);

    if (!fs.existsSync(TEMPLATE_DIR)) {
        console.log(`\n❌ Folder template "${TEMPLATE_NAME}" tidak ditemukan!`);
        return false;
    }

    if (fs.existsSync(clientDir)) {
        console.log(`\n❌ Folder "${folderName}" sudah ada!`);
        return false;
    }

    console.log(`\n📦 Membuat folder klien: ${folderName}`);
    console.log(`   Nama klien: ${clientName}`);
    console.log('');

    // 1. Buat folder klien
    fs.mkdirSync(clientDir, { recursive: true });

    // 2. Copy source code
    console.log('   📋 Menyalin source code...');
    for (const item of COPY_ITEMS) {
        const src = path.join(TEMPLATE_DIR, item);
        const dest = path.join(clientDir, item);
        if (fs.existsSync(src)) {
            copyRecursiveSync(src, dest);
            console.log(`      ✓ ${item}`);
        }
    }

    // 3. Buat junction untuk node_modules (hemat ~488MB)
    console.log('   🔗 Membuat link ke shared node_modules...');
    const nmSource = path.join(TEMPLATE_DIR, 'node_modules');
    const nmTarget = path.join(clientDir, 'node_modules');
    try {
        execSync(`mklink /J "${nmTarget}" "${nmSource}"`, { stdio: 'pipe', shell: 'cmd.exe' });
        console.log('      ✓ node_modules (junction → template)');
    } catch (e) {
        console.error('      ❌ Gagal membuat junction. Coba jalankan sebagai Administrator.');
        console.error('         Atau copy manual: xcopy /E /I "' + nmSource + '" "' + nmTarget + '"');
        return false;
    }

    // 4. Buat folder dist untuk build output klien ini
    fs.mkdirSync(path.join(clientDir, 'dist'), { recursive: true });

    // 5. Update package.json dengan info klien
    const pkgPath = path.join(clientDir, 'package.json');
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
    pkg.name = `weighbridge-${folderName}`;
    pkg.description = `Weighbridge - ${clientName} | Sistem Jembatan Timbangan Sawit`;
    pkg.build.appId = `com.arroyan.weighbridge.${folderName}`;
    pkg.build.productName = `Weighbridge - ${clientName}`;
    pkg.build.nsis.shortcutName = `Weighbridge ${clientName}`;
    fs.writeFileSync(pkgPath, JSON.stringify(pkg, null, 2) + '\n', 'utf8');
    console.log('      ✓ package.json dikustomisasi');

    console.log(`\n✅ Klien "${clientName}" berhasil dibuat!`);
    console.log(`   📁 Lokasi: ${clientDir}`);
    console.log(`   💡 Buka di VS Code: code "${clientDir}"`);
    console.log(`   🚀 Jalankan: cd "${clientDir}" && npm start`);
    console.log(`   📦 Build: cd "${clientDir}" && npm run build`);
    
    return true;
}

function listClients() {
    const clients = getClients();
    
    console.log('\n══════════════════════════════════════════════════');
    console.log('   📋 DAFTAR KLIEN WEIGHBRIDGE');
    console.log('══════════════════════════════════════════════════\n');
    
    // Template info
    const templateSize = getDirSize(path.join(TEMPLATE_DIR, 'renderer')) + 
                         getDirSize(path.join(TEMPLATE_DIR, 'server')) + 
                         getDirSize(path.join(TEMPLATE_DIR, 'electron'));
    console.log(`   📌 Template (${TEMPLATE_NAME}/)`);
    console.log(`      Source code: ${formatSize(templateSize)}`);
    console.log('');

    if (clients.length === 0) {
        console.log('   (Belum ada klien. Gunakan opsi "Buat Klien Baru")\n');
        return;
    }

    clients.forEach((name, i) => {
        const clientDir = path.join(ROOT_DIR, name);
        const pkgPath = path.join(clientDir, 'package.json');
        let clientName = name;
        let version = '-';
        
        try {
            const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
            clientName = pkg.build?.productName || pkg.description || name;
            version = pkg.version || '-';
        } catch (e) { /* ignore */ }

        const sourceSize = getDirSize(path.join(clientDir, 'renderer')) + 
                          getDirSize(path.join(clientDir, 'server')) + 
                          getDirSize(path.join(clientDir, 'electron'));
        
        // Check if dist has builds
        const distDir = path.join(clientDir, 'dist');
        let buildInfo = 'Belum di-build';
        if (fs.existsSync(distDir)) {
            const exeFiles = fs.readdirSync(distDir).filter(f => f.endsWith('.exe') && !f.endsWith('.blockmap'));
            if (exeFiles.length > 0) {
                buildInfo = exeFiles[exeFiles.length - 1];
            }
        }

        console.log(`   ${i + 1}. 📁 ${name}/`);
        console.log(`      Nama: ${clientName}`);
        console.log(`      Versi: v${version}`);
        console.log(`      Source: ${formatSize(sourceSize)}`);
        console.log(`      Build: ${buildInfo}`);
        console.log('');
    });

    const nmSize = 488; // MB approximate
    console.log(`   ────────────────────────────────────────────`);
    console.log(`   Shared node_modules: ~${nmSize} MB (dipakai bersama)`);
    console.log(`   Total klien: ${clients.length}`);
    console.log(`   Estimasi hemat: ~${(clients.length * nmSize / 1024).toFixed(1)} GB\n`);
}

function deleteClient(folderName) {
    const clientDir = path.join(ROOT_DIR, folderName);
    
    if (!fs.existsSync(clientDir)) {
        console.log(`\n❌ Folder "${folderName}" tidak ditemukan!`);
        return false;
    }

    // Remove junction first (jangan hapus node_modules asli!)
    const nmJunction = path.join(clientDir, 'node_modules');
    try {
        const stats = fs.lstatSync(nmJunction);
        if (stats.isSymbolicLink() || fs.readlinkSync(nmJunction)) {
            // It's a junction, just remove the junction
            execSync(`rmdir "${nmJunction}"`, { stdio: 'pipe', shell: 'cmd.exe' });
        }
    } catch (e) {
        // If it's a real folder, remove normally
        try {
            fs.rmSync(nmJunction, { recursive: true, force: true });
        } catch (e2) { /* ignore */ }
    }

    // Remove rest of folder
    fs.rmSync(clientDir, { recursive: true, force: true });
    console.log(`\n✅ Klien "${folderName}" berhasil dihapus!`);
    return true;
}

// ─── Interactive Menu ────────────────────────────────────────────────────────

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

function ask(question) {
    return new Promise(resolve => rl.question(question, resolve));
}

async function menu() {
    console.clear();
    console.log('══════════════════════════════════════════════════');
    console.log('   🏗️  WEIGHBRIDGE - KELOLA KLIEN');
    console.log('   Arroyan Jv Teknik');
    console.log('══════════════════════════════════════════════════\n');
    console.log('   1. 📋 Lihat Daftar Klien');
    console.log('   2. ➕ Buat Klien Baru');
    console.log('   3. 🗑️  Hapus Klien');
    console.log('   4. 📂 Buka Klien di VS Code');
    console.log('   5. 🚪 Keluar\n');

    const choice = await ask('   Pilih menu (1-5): ');

    switch (choice.trim()) {
        case '1':
            listClients();
            await ask('\n   Tekan Enter untuk kembali...');
            return menu();

        case '2': {
            console.log('\n   ➕ BUAT KLIEN BARU');
            console.log('   ─────────────────────────────────────\n');
            const folderName = await ask('   Nama folder (huruf kecil, tanpa spasi, contoh: klien-a): ');
            if (!folderName.trim()) { console.log('   ❌ Nama folder tidak boleh kosong!'); await ask('\n   Tekan Enter...'); return menu(); }
            if (/[^a-z0-9\-_]/.test(folderName.trim())) { console.log('   ❌ Gunakan huruf kecil, angka, dash (-) atau underscore (_) saja!'); await ask('\n   Tekan Enter...'); return menu(); }
            
            const clientName = await ask('   Nama perusahaan klien (contoh: PT Sawit Jaya): ');
            if (!clientName.trim()) { console.log('   ❌ Nama klien tidak boleh kosong!'); await ask('\n   Tekan Enter...'); return menu(); }

            const confirm = await ask(`\n   Buat klien "${clientName}" di folder "${folderName}"? (y/n): `);
            if (confirm.toLowerCase() === 'y') {
                createClient(folderName.trim(), clientName.trim());
            } else {
                console.log('   Dibatalkan.');
            }
            await ask('\n   Tekan Enter untuk kembali...');
            return menu();
        }

        case '3': {
            const clients = getClients();
            if (clients.length === 0) { console.log('\n   Belum ada klien.'); await ask('\n   Tekan Enter...'); return menu(); }
            
            console.log('\n   🗑️  HAPUS KLIEN');
            console.log('   ─────────────────────────────────────\n');
            clients.forEach((c, i) => console.log(`   ${i + 1}. ${c}`));
            
            const idx = await ask('\n   Pilih nomor klien yang akan dihapus: ');
            const selected = clients[parseInt(idx) - 1];
            if (!selected) { console.log('   ❌ Pilihan tidak valid!'); await ask('\n   Tekan Enter...'); return menu(); }
            
            const confirm = await ask(`   ⚠️  Yakin hapus "${selected}"? Data TIDAK bisa dikembalikan! (y/n): `);
            if (confirm.toLowerCase() === 'y') {
                deleteClient(selected);
            } else {
                console.log('   Dibatalkan.');
            }
            await ask('\n   Tekan Enter untuk kembali...');
            return menu();
        }

        case '4': {
            const clients = getClients();
            if (clients.length === 0) { console.log('\n   Belum ada klien.'); await ask('\n   Tekan Enter...'); return menu(); }
            
            console.log('\n   📂 BUKA DI VS CODE');
            console.log('   ─────────────────────────────────────\n');
            clients.forEach((c, i) => console.log(`   ${i + 1}. ${c}`));
            
            const idx = await ask('\n   Pilih nomor klien: ');
            const selected = clients[parseInt(idx) - 1];
            if (!selected) { console.log('   ❌ Pilihan tidak valid!'); await ask('\n   Tekan Enter...'); return menu(); }
            
            const clientDir = path.join(ROOT_DIR, selected);
            try {
                execSync(`code "${clientDir}"`, { stdio: 'pipe' });
                console.log(`\n   ✅ VS Code terbuka untuk "${selected}"`);
            } catch (e) {
                console.log(`\n   ❌ Gagal membuka VS Code. Buka manual: code "${clientDir}"`);
            }
            await ask('\n   Tekan Enter untuk kembali...');
            return menu();
        }

        case '5':
            console.log('\n   👋 Sampai jumpa!\n');
            rl.close();
            process.exit(0);

        default:
            console.log('\n   ❌ Pilihan tidak valid!');
            await ask('\n   Tekan Enter untuk kembali...');
            return menu();
    }
}

// ─── CLI Mode (tanpa menu) ───────────────────────────────────────────────────
const args = process.argv.slice(2);
if (args[0] === 'create' && args[1] && args[2]) {
    createClient(args[1], args.slice(2).join(' '));
    process.exit(0);
} else if (args[0] === 'list') {
    listClients();
    process.exit(0);
} else if (args[0] === 'delete' && args[1]) {
    deleteClient(args[1]);
    process.exit(0);
} else {
    menu();
}
