/**
 * Electron Main Process
 * Weighbridge - Arroyan Jv Teknik
 * Entry point: starts Express server then opens BrowserWindow
 */

const { app, BrowserWindow, ipcMain, Menu, Tray, dialog, shell } = require('electron');
const path = require('path');
const { fork } = require('child_process');
const fs = require('fs');
const net = require('net');

let mainWindow = null;
let tray = null;
let serverProcess = null;
let SERVER_PORT = 3737;
const isDev = process.argv.includes('--dev');

function getFreePort(startingPort) {
  return new Promise((resolve) => {
    const server = net.createServer();
    server.listen(startingPort, '127.0.0.1', () => {
      const port = server.address().port;
      server.close(() => resolve(port));
    });
    server.on('error', () => {
      resolve(getFreePort(startingPort + 1));
    });
  });
}

// ─── Prevent multiple instances ───────────────────────────────────────────────
const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });
}

// ─── Start Express Server ──────────────────────────────────────────────────────
let serverRestartCount = 0;
const MAX_SERVER_RESTARTS = 5;
let isResettingDatabase = false;

function startServer() {
  return new Promise((resolve, reject) => {
    const serverPath = path.join(__dirname, '..', 'server', 'app.js');
    const dbDir = app.getPath('userData');
    const dbPath = path.join(dbDir, 'database.db');
    serverProcess = fork(serverPath, [], {
      execPath: process.execPath,
      env: { 
        ...process.env, 
        ELECTRON_RUN_AS_NODE: '1',
        PORT: SERVER_PORT, 
        NODE_ENV: isDev ? 'development' : 'production',
        DB_PATH: dbPath
      },
      silent: true
    });

    serverProcess.stdout.on('data', (data) => {
      const msg = data.toString();
      console.log('[Server]', msg.trim());
      if (msg.includes('Server running on port')) {
        resolve();
      }
    });

    serverProcess.stderr.on('data', (data) => {
      console.error('[Server Error]', data.toString().trim());
    });

    serverProcess.on('error', (err) => {
      console.error('[Server] Failed to start:', err);
      reject(err);
    });

    // PENTING: Handle server crash — auto-restart agar aplikasi tidak blank
    serverProcess.on('exit', (code, signal) => {
      console.error(`[Server] Process exited! code=${code}, signal=${signal}`);
      serverProcess = null;

      // Jangan restart jika aplikasi sedang ditutup atau sedang mereset database
      if (app.isQuitting || isResettingDatabase) return;

      if (serverRestartCount < MAX_SERVER_RESTARTS) {
        serverRestartCount++;
        console.log(`[Server] Auto-restarting... (${serverRestartCount}/${MAX_SERVER_RESTARTS})`);
        
        // Restart server setelah 2 detik
        setTimeout(() => {
          startServer().then(() => {
            console.log('[Server] Restarted successfully!');
            // Reload halaman agar UI kembali normal
            if (mainWindow && !mainWindow.isDestroyed()) {
              mainWindow.reload();
            }
          }).catch(err => {
            console.error('[Server] Restart failed:', err);
          });
        }, 2000);
      } else {
        console.error('[Server] Max restart attempts reached!');
        if (mainWindow && !mainWindow.isDestroyed()) {
          dialog.showErrorBox(
            'Server Error',
            'Server aplikasi telah crash berulang kali dan tidak bisa dipulihkan.\nSilakan restart aplikasi secara manual.\n\nJika masalah berlanjut, hubungi teknisi.'
          );
        }
      }
    });

    // Timeout fallback
    setTimeout(resolve, 10000);
  });
}

// ─── Create Main Window ────────────────────────────────────────────────────────
function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1366,
    height: 768,
    minWidth: 1024,
    minHeight: 600,
    title: 'Weighbridge - Arroyan Jv Teknik',
    icon: path.join(__dirname, '..', 'build-resources', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      webSecurity: true,
    },
    show: false, // Show after ready-to-show
    backgroundColor: '#1a1a2e',
    titleBarStyle: 'default',
  });

  // ── Web Serial API: Handle port selection ─────────────────────────────────
  mainWindow.webContents.session.on('select-serial-port', (event, portList, webContents, callback) => {
    event.preventDefault();
    if (portList && portList.length > 0) {
      mainWindow.webContents.send('show-port-selector', portList);
      
      ipcMain.once('port-selected', (e, portId) => {
        callback(portId);
      });
    } else {
      callback('');
    }
  });

  // Grant serial port permissions
  mainWindow.webContents.session.setPermissionCheckHandler((webContents, permission) => {
    if (permission === 'serial') return true;
    return true;
  });

  mainWindow.webContents.session.setDevicePermissionHandler((details) => {
    if (details.deviceType === 'serial') return true;
    return false;
  });

  // Handle port added/removed events
  mainWindow.webContents.session.on('serial-port-added', (event, port) => {
    console.log('[Serial] Port added:', port.portName);
  });

  mainWindow.webContents.session.on('serial-port-removed', (event, port) => {
    console.log('[Serial] Port removed:', port.portName);
  });

  // ── Load app with retry ───────────────────────────────────────────────────
  let retries = 15;
  function loadAppUrl() {
    mainWindow.loadURL(`http://127.0.0.1:${SERVER_PORT}/`).catch(err => {
      console.warn(`[Electron] Failed to load URL: http://127.0.0.1:${SERVER_PORT}/ (${err.code}), retrying... (${retries} left)`);
      if (retries > 0) {
        retries--;
        setTimeout(loadAppUrl, 1000);
      }
    });
  }

  // Clear Chromium cache to prevent loading old cached HTML/JS files
  mainWindow.webContents.session.clearCache().then(() => {
    console.log('[Electron] Cache cleared successfully');
    loadAppUrl();
  }).catch(err => {
    console.error('[Electron] Failed to clear cache:', err);
    loadAppUrl();
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    if (isDev) mainWindow.webContents.openDevTools();
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Intercept external links but allow print ticket, surat jalan & riwayat hutang
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.includes('/print-ticket') || url.includes('/surat-jalan') || url.includes('/hutang-print')) {
        return { 
            action: 'allow',
            overrideBrowserWindowOptions: {
                autoHideMenuBar: true,
                parent: mainWindow,
                modal: true
            }
        };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  // Build app menu
  buildMenu();
}

// ─── App Menu ─────────────────────────────────────────────────────────────────
function buildMenu() {
  const template = [
    {
      label: 'Aplikasi',
      submenu: [
        { label: 'Reload', accelerator: 'F5', click: () => mainWindow && mainWindow.reload() },
        { label: 'Reset Database', click: handleResetDatabase },
        { type: 'separator' },
        { label: 'Keluar', accelerator: 'Alt+F4', click: () => app.quit() }
      ]
    },
    {
      label: 'Tampilan',
      submenu: [
        { label: 'Layar Penuh', accelerator: 'F11', click: () => mainWindow && mainWindow.setFullScreen(!mainWindow.isFullScreen()) },
        { label: 'Zoom In', accelerator: 'CmdOrCtrl+=', click: () => mainWindow && mainWindow.webContents.setZoomLevel(mainWindow.webContents.getZoomLevel() + 0.5) },
        { label: 'Zoom Out', accelerator: 'CmdOrCtrl+-', click: () => mainWindow && mainWindow.webContents.setZoomLevel(mainWindow.webContents.getZoomLevel() - 0.5) },
        { label: 'Reset Zoom', accelerator: 'CmdOrCtrl+0', click: () => mainWindow && mainWindow.webContents.setZoomLevel(0) },
      ]
    },
    {
      label: 'Bantuan',
      submenu: [
        { label: 'Tentang Aplikasi', click: showAbout },
        ...(isDev ? [{ type: 'separator' }, { label: 'DevTools', accelerator: 'F12', click: () => mainWindow && mainWindow.webContents.toggleDevTools() }] : [])
      ]
    }
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

function showAbout() {
  dialog.showMessageBox(mainWindow, {
    type: 'info',
    title: 'Tentang Aplikasi',
    message: 'Weighbridge - Arroyan Jv Teknik',
    detail: `Sistem Jembatan Timbangan Sawit\nVersi: ${app.getVersion()}\n© 2024 Arroyan Jv Teknik\n\nNode.js: ${process.versions.node}\nElectron: ${process.versions.electron}`,
    buttons: ['OK']
  });
}

// ─── IPC Handlers ─────────────────────────────────────────────────────────────
ipcMain.handle('get-app-version', () => app.getVersion());
ipcMain.handle('get-app-path', () => app.getPath('userData'));
ipcMain.handle('open-external', (event, url) => shell.openExternal(url));
ipcMain.handle('show-print-dialog', async () => {
  const result = await mainWindow.webContents.print({}, (success, errorType) => {
    if (!success) console.error('Print failed:', errorType);
  });
  return result;
});
ipcMain.handle('print-content', async (event, content) => {
  // Print specific content
  mainWindow.webContents.print({ silent: false, printBackground: true });
});

// ─── Database Versioning & Reset Helpers ──────────────────────────────────────
function checkDatabaseVersion() {
  const dbDir = app.getPath('userData');
  if (!fs.existsSync(dbDir)) {
    fs.mkdirSync(dbDir, { recursive: true });
  }

  const versionFilePath = path.join(dbDir, 'version.json');
  const dbPath = path.join(dbDir, 'database.db');
  const currentVersion = app.getVersion();

  let lastVersion = null;
  if (fs.existsSync(versionFilePath)) {
    try {
      const data = JSON.parse(fs.readFileSync(versionFilePath, 'utf8'));
      lastVersion = data.version;
    } catch (e) {
      console.error('[Main] Failed to read version.json:', e);
    }
  }

  // PENTING: JANGAN reset database ketika versi aplikasi berubah.
  // Server-side (server/config/database.js) sudah memiliki mekanisme migrasi otomatis yang aman untuk menambah kolom baru.
  // Menghapus database saat update versi sangat merusak data produksi klien.
  if (fs.existsSync(dbPath)) {
    if (lastVersion && lastVersion !== currentVersion) {
      console.log(`[Main] Version changed from ${lastVersion} to ${currentVersion}. Backing up database for safety...`);
      // Lakukan backup saja sebagai pengaman, tapi JANGAN hapus/reset database!
      try {
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const backupName = `database_backup_auto_v${lastVersion || 'unknown'}_to_v${currentVersion}_${timestamp}.db`;
        const backupPath = path.join(dbDir, backupName);
        fs.copyFileSync(dbPath, backupPath);
        console.log(`[Main] Auto backup created successfully at: ${backupPath}`);
      } catch (err) {
        console.error('[Main] Auto backup failed:', err);
      }
    }
  }

  // Update/buat version.json dengan versi terbaru
  try {
    fs.writeFileSync(versionFilePath, JSON.stringify({ version: currentVersion }, null, 2), 'utf8');
  } catch (e) {
    console.error('[Main] Failed to write version.json:', e);
  }
}

function backupAndResetDatabase(dbDir, dbPath, oldVersion, newVersion) {
  try {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupName = `database_backup_v${oldVersion || 'unknown'}_${timestamp}.db`;
    const backupPath = path.join(dbDir, backupName);
    
    // Cadangkan file
    fs.copyFileSync(dbPath, backupPath);
    console.log(`[Main] Database backup created at: ${backupPath}`);

    // Hapus file utama
    fs.unlinkSync(dbPath);
    console.log(`[Main] Old database removed. A new one will be created.`);
    
    // Hapus file temporary SQLite jika ada
    const walPath = `${dbPath}-wal`;
    const shmPath = `${dbPath}-shm`;
    if (fs.existsSync(walPath)) fs.unlinkSync(walPath);
    if (fs.existsSync(shmPath)) fs.unlinkSync(shmPath);
  } catch (err) {
    console.error('[Main] Failed to backup/reset database:', err);
  }
}

async function handleResetDatabase() {
  if (!mainWindow) return;

  const { response } = await dialog.showMessageBox(mainWindow, {
    type: 'warning',
    buttons: ['Batal', 'Ya, Reset Database'],
    defaultId: 0,
    title: 'Konfirmasi Reset Database',
    message: 'Apakah Anda yakin ingin mereset database?',
    detail: 'Tindakan ini akan menghapus semua data transaksi dan mengembalikan database ke kondisi awal.\n\nDatabase lama Anda akan dicadangkan secara otomatis di folder data aplikasi.',
    cancelId: 0
  });

  if (response === 1) {
    try {
      isResettingDatabase = true;

      // 1. Matikan Express server
      if (serverProcess) {
        console.log('[Main] Stopping server for database reset...');
        await new Promise(resolve => {
          serverProcess.once('exit', resolve);
          serverProcess.kill();
        });
        serverProcess = null;
      }

      // Beri waktu OS Windows melepaskan file lock SQLite
      await new Promise(resolve => setTimeout(resolve, 500));

      // 2. Lakukan backup dan reset
      const dbDir = app.getPath('userData');
      const dbPath = path.join(dbDir, 'database.db');
      
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const backupName = `database_backup_manual_${timestamp}.db`;
      const backupPath = path.join(dbDir, backupName);

      if (fs.existsSync(dbPath)) {
        fs.copyFileSync(dbPath, backupPath);
        fs.unlinkSync(dbPath);
        
        // Hapus WAL/SHM jika ada
        const walPath = `${dbPath}-wal`;
        const shmPath = `${dbPath}-shm`;
        if (fs.existsSync(walPath)) fs.unlinkSync(walPath);
        if (fs.existsSync(shmPath)) fs.unlinkSync(shmPath);
      }

      // 3. Jalankan kembali server
      isResettingDatabase = false;
      serverRestartCount = 0;
      await startServer();

      // 4. Reload window
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.reload();
      }

      dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: 'Sukses',
        message: 'Database berhasil direset dan dicadangkan.',
        detail: `Database lama dicadangkan sebagai:\n${backupName}\n\ndi folder: ${dbDir}`,
        buttons: ['OK']
      });

    } catch (err) {
      console.error('[Main] Reset database failed:', err);
      isResettingDatabase = false;
      dialog.showErrorBox('Error', 'Gagal mereset database: ' + err.message);
      
      if (!serverProcess) {
        startServer().then(() => {
          if (mainWindow && !mainWindow.isDestroyed()) mainWindow.reload();
        });
      }
    }
  }
}

// ─── App Lifecycle ─────────────────────────────────────────────────────────────
app.whenReady().then(async () => {
  try {
    console.log('[Main] Checking database version...');
    checkDatabaseVersion();
    console.log('[Main] Finding free port...');
    SERVER_PORT = await getFreePort(3737);
    console.log('[Main] Starting Express server on port ' + SERVER_PORT + '...');
    await startServer();
    console.log('[Main] Express server ready, creating window...');
    createWindow();
  } catch (err) {
    console.error('[Main] Failed to start:', err);
    dialog.showErrorBox('Error', 'Gagal memulai server aplikasi: ' + err.message);
    app.quit();
  }
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    if (serverProcess) {
      serverProcess.kill();
    }
    app.quit();
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});

app.on('before-quit', () => {
  app.isQuitting = true;
  if (serverProcess) {
    serverProcess.kill();
  }
});
