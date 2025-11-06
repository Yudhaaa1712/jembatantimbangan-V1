# INSTRUKSI SETUP AUTO-CONNECT SERIAL PORT (SONIC A283)

## OVERVIEW
Fitur auto-connect serial port untuk indikator timbangan Sonic A283 dengan Web Serial API Chrome.

## REQUIREMENTS
- **Browser**: Google Chrome (versi 89+)
- **Indikator**: Sonic A283
- **Koneksi**: USB-Serial Controller (COM3)
- **OS**: Windows 10/11

## SETUP HARDWARE

### 1. Koneksi USB-Serial
1. Colokkan USB-Serial Controller ke komputer
2. Pastikan driver terinstall (USB-Serial Controller Paired)
3. Cek di Device Manager → Ports (COM & LPT)
4. Pastikan muncul "USB-Serial Port (COM3)"

### 2. Koneksi ke Indikator Sonic A283
1. Kabel serial RS232 dari COM3 ke indikator Sonic A283
2. Pinout:
   - Pin 2: RXD (Receive Data)
   - Pin 3: TXD (Transmit Data)
   - Pin 5: GND (Ground)

## KONFIGURASI INDICATOR SONIC A283

### Settings di Indikator:
- **Baud Rate**: 9600
- **Data Bits**: 8
- **Stop Bits**: 1
- **Parity**: None
- **Flow Control**: None

### Format Output Sonic A283:
- Output berat dalam format: `+ 12345.6 KG` atau `12345.6`
- Update data real-time saat berat berubah

## CARA KERJA AUTO-CONNECT

### First Time Setup (Sekali Saja):
1. Buka website di Chrome
2. Klik tombol **"Connect Indicator"**
3. Pilih **"USB Serial Port (COM3)"** dari dialog
4. Klik **"Connect"**
5. Chrome akan minta permission → **"Allow"**
6. Selesai! Port tersimpan otomatis

### Auto-Connect (Selanjutnya):
- Saat buka website lagi → langsung connect otomatis
- Tidak perlu pilih port lagi
- Permission sudah tersimpan di browser

## PEMAKAIAN DI WEBSITE

### Timbangan 1:
1. Buka `timbangan1.php`
2. Status: "Menunggu koneksi ke indikator..."
3. Auto-connect berjalan → Status: "Terhubung ke Sonic A283"
4. Data berat muncul real-time di display
5. Klik **"CAPTURE TIMBANG"** untuk ambil berat
6. Berat masuk otomatis ke input field

### Timbangan 2:
1. Buka `timbangan2.php`
2. Pilih tiket yang sudah ditimbang di Timbangan 1
3. Auto-connect berjalan → Status: "Terhubung ke Sonic A283"
4. Data berat muncul real-time
5. Klik **"CAPTURE TIMBANG 2"** untuk ambil berat tara
6. Perhitungan otomatis berjalan

## FEATURES

### ✅ Auto-Connect:
- Otomatis connect saat website dibuka (setelah pairing pertama)
- Tidak perlu pilih port lagi
- Permission tersimpan di Chrome

### ✅ Auto-Reconnect:
- Jika koneksi terputus → auto-reconnect setiap 3 detik
- Maksimal 10 kali percobaan
- Status update real-time

### ✅ Real-time Data:
- Data berat update langsung dari indikator
- Format parsing otomatis untuk Sonic A283
- Support berat negatif/zero

### ✅ Error Handling:
- Notifikasi error jika gagal connect
- Fallback ke Enhanced Web Serial API
- Support multiple connection methods

## TROUBLESHOOTING

### Problem: "Indicator Tidak Terhubung"
**Solutions:**
1. Cek koneksi USB ke komputer
2. Buka Device Manager → pastikan COM3 terdeteksi
3. Close/reopen browser
4. Clear browser cache: Chrome → Settings → Privacy → Clear browsing data

### Problem: "Data diterima dari indikator" tapi berat 0
**Solutions:**
1. Cek kabel RS232 ke indikator
2. Pastikan indikator dalam mode transmisi data
3. Cek baud rate di indikator (harus 9600)
4. Restart indikator Sonic A283

### Problem: Dialog port selection tidak muncul
**Solutions:**
1. Pastikan pakai Google Chrome
2. Buka di `http://localhost` atau `https://` (bukan file://)
3. Enable Web Serial API: `chrome://flags/#enable-experimental-web-platform-features`
4. Restart Chrome setelah enable flags

### Problem: Auto-connect tidak berjalan
**Solutions:**
1. Pastikan sudah pairing pertama kali
2. Clear localStorage di browser
3. Lakukan manual connect sekali lagi
4. Refresh halaman

## CODE INTEGRATION

### Files yang ditambahkan:
- `js/auto-serial-connect.js` - Module utama Web Serial API
- `INSTRUKSI_SETUP_SERIAL.md` - Dokumentasi ini

### Files yang dimodifikasi:
- `modules/timbangan/timbangan1.php` - Integrasi auto-connect
- `modules/timbangan/timbangan2.php` - Integrasi auto-connect

### Key Functions:
```javascript
// Initialize Auto Serial Connector
const connector = new AutoSerialConnector({
    targetInputId: 'beratInputForm',
    maxReconnectAttempts: 10,
    reconnectInterval: 3000,
    onConnect: () => console.log('Connected'),
    onData: (weight) => updateDisplay(weight)
});

// Auto-connect
await connector.autoConnect();

// Manual connect
await connector.manualConnect();
```

## TESTING

### Test Connection:
1. Buka Chrome Developer Tools (F12)
2. Tab Console
3. Cari log:
   - `✅ Auto-connect successful`
   - `📈 Auto Serial Weight: 12345.67`

### Test Weight Parsing:
1. Di console, jalankan: `testWeightUpdateTimbangan1(5000)`
2. Display harus update ke `5,000 Kg`
3. Input field harus terisi `5000`

### Test Auto-Reconnect:
1. Cabut USB serial
2. Status berubah ke "Indicator Tidak Terhubung"
3. Colok kembali USB
4. Auto-reconnect dalam 3 detik
5. Status kembali "Terhubung ke Sonic A283"

## SECURITY NOTES

- Web Serial API hanya berjalan di secure context (https/localhost)
- Permission disimpan per-origin di browser
- User harus explicit click untuk first-time pairing
- Tidak ada background connection tanpa user interaction

## MAINTENANCE

### Rutin:
- Cek kabel serial connection
- Update browser Chrome ke versi terbaru
- Backup configuration settings

### Monitor:
- Console logs untuk error tracking
- Connection stability
- Weight parsing accuracy

---
**Created**: 2025-11-06
**Version**: 1.0
**Compatible**: Sonic A283 + Chrome 89+ + Windows 10/11