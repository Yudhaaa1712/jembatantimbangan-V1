# Aplikasi Jembatan Timbangan Sawit

Aplikasi web-based untuk sistem jembatan timbangan digital yang terintegrasi dengan indikator timbangan Sonic A28E. Aplikasi ini digunakan untuk mencatat transaksi timbangan kelapa sawit dengan fitur cetak struk, laporan, dan manajemen data master.

## 🚀 Fitur Utama

- **Sistem Timbangan Real-time** - Integrasi dengan indikator Sonic A28E via serial port
- **2 Sesi Timbangan** - Timbangan 1 dan Timbangan 2 dengan antarmuka terpisah
- **Manajemen Transaksi** - Pencatatan masuk/keluar dengan perhitungan otomatis
- **Cetak Struk/Tiket** - Format tiket dengan barcode untuk tracking
- **Master Data** - Kendaraan, Supplier, Customer, Material
- **Laporan** - Export data ke Excel/PDF dengan filter tanggal
- **Sistem User** - Login dengan role-based access (Admin/Operator)
- **Simulasi Timbangan** - Mode demo untuk testing tanpa hardware

## 📋 Persyaratan Sistem

### Hardware Requirements
- **OS:** Windows 7/10/11 (Recommended) atau Linux
- **RAM:** Minimum 4GB, Recommended 8GB
- **Storage:** 500MB available space
- **Serial Port:** COM port (untuk indikator timbangan) atau USB-to-Serial adapter
- **Printer:** Thermal printer (untuk cetak struk)

### Software Requirements
- **Web Server:** XAMPP 7.4+ (Apache + MySQL + PHP) atau equivalent
- **PHP Version:** 7.4 atau 8.x
- **MySQL:** 5.7+ atau MariaDB 10.3+
- **Python:** 3.7+ (untuk bridge service indikator)
- **Web Browser:** Chrome, Firefox, atau Edge modern

## 🛠️ Panduan Instalasi

### Step 1: Install XAMPP
1. Download XAMPP dari https://www.apachefriends.org/
2. Install XAMPP dengan Apache dan MySQL
3. Start Apache dan MySQL services melalui XAMPP Control Panel

### Step 2: Clone/Download Project
```bash
# Clone dari repository (jika ada)
git clone [repository-url] jembatantimbangan

# Atau extract zip file ke folder:
# C:\xampp\htdocs\jembatantimbangan\
```

### Step 3: Setup Database
1. Buka phpMyAdmin: http://localhost/phpmyadmin
2. Buat database baru:
   - Nama database: `jembatan_timbangan`
   - Collation: `utf8mb4_unicode_ci`
3. Import file SQL:
   - Buka `database_schema.sql` (ada di folder root)
   - Atau jalankan otomatis via browser: http://localhost/jembatantimbangan/setup_database.php

### Step 4: Konfigurasi Database
Edit file konfigurasi sesuai environment:
```php
// config/hardware_config.php (line 39-46)
public static $database = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '', // isi password MySQL Anda
    'database' => 'jembatan_timbangan',
    'charset' => 'utf8mb4',
    'timezone' => 'Asia/Jakarta'
];
```

### Step 5: Konfigurasi Base URL
Edit file `config/database.php` line 62:
```php
define('BASE_URL', 'http://localhost/jembatantimbangan/');
```
Ganti sesuai dengan domain/IP address jika diinstall di server lain.

### Step 6: Setup Folder Permissions
Pastikan folder berikut memiliki permission write:
```bash
# Windows: Biasanya tidak perlu setup permission
# Linux/Mac:
chmod 755 sessions/
chmod 755 cache/
chmod 755 temp/
chmod 755 uploads/
```

### Step 7: Setup Python Bridge Service (Untuk Hardware Integration)

#### Install Dependencies Python
```bash
pip install pyserial flask requests
```

#### Jalankan Bridge Service
```bash
# Masuk ke folder API
cd api/

# Jalankan bridge server
python serial_bridge.py
```

Bridge service akan berjalan di http://127.0.0.1:5001

### Step 8: Konfigurasi Indikator Timbangan

#### Otomatis (Recommended)
- Akses: http://localhost/jembatantimbangan/modules/setup/index.php
- Klik "Auto Detect" untuk mencari COM port
- Save konfigurasi

#### Manual Edit
Edit `config/hardware_config.php`:
```php
public static $sonic_a28e = [
    'default_com_port' => 'COM3', // ganti sesuai port
    'baud_rate' => 9600,
    'timeout' => 1
];
```

## 🔧 Konfigurasi LAN/Network Access

### Akses dari Komputer Lain
1. **Edit hosts file** (setiap komputer client):
   ```
   # Windows: C:\Windows\System32\drivers\etc\hosts
   192.168.1.100 timbangan.local

   # Linux/Mac: /etc/hosts
   192.168.1.100 timbangan.local
   ```

2. **Edit Apache Virtual Host** (server):
   ```apache
   # C:\xampp\apache\conf\extra\httpd-vhosts.conf
   <VirtualHost *:80>
       ServerName timbangan.local
       DocumentRoot "C:/xampp/htdocs/jembatantimbangan"
   </VirtualHost>
   ```

3. **Edit BASE_URL** di `config/database.php`:
   ```php
   define('BASE_URL', 'http://timbangan.local/');
   ```

### Firewall Configuration
- Buka port 80 (Apache) di Windows Firewall
- Jika menggunakan port lain, buka port tersebut
- Untuk akses internet, configure port forwarding di router

## 👤 Default Login

- **Username:** `admin`
- **Password:** `admin123`

⚠️ **PENTING:** Ganti password default setelah first login!

## 📁 Struktur Folder

```
jembatantimbangan/
├── config/                 # Konfigurasi database & hardware
├── modules/               # Modul aplikasi
│   ├── auth/             # Login/logout
│   ├── timbangan/        # Sistem timbangan
│   ├── master/           # Master data (kendaraan, supplier)
│   ├── laporan/          # Laporan & export
│   └── admin/            # Admin panel
├── includes/             # Shared functions & templates
├── api/                  # API endpoints & bridge service
├── sessions/             # Session storage
├── cache/                # Cache files
├── database_schema.sql   # Database structure
└── README.md            # This file
```

## 🎯 Penggunaan Dasar

### 1. Login ke Sistem
1. Buka browser: http://localhost/jembatantimbangan/
2. Login dengan default credentials
3. Sistem akan redirect ke halaman timbangan

### 2. Proses Timbangan (Standard Flow)
1. **Input Kendaraan** - Masukkan nomor polisi atau scan RFID
2. **Timbangan Masuk** - Catat berat kosong (Tara)
3. **Input Data** - Supplier, material, keterangan
4. **Timbangan Keluar** - Catat berat bruto
5. **Cetak Tiket** - Sistem cetak struk otomatis

### 3. Mode Simulasi (Testing)
Jika hardware tidak tersedia:
1. Login sebagai admin
2. Akses: http://localhost/jembatantimbangan/modules/admin/weight_control.php
3. Pilih mode simulasi (stable/fluctuate/realistic)
4. Sistem akan generate data timbangan dummy

## 🔌 Integrasi Hardware

### Indikator Sonic A28E
- **Connection:** Serial RS232/RS485
- **Protocol:** Sonic A28E native protocol
- **Settings:** 9600 baud, 8N1, no parity
- **Port:** COM3 (default), auto-detect available

### Printer Thermal
- **Connection:** USB/LAN/Serial
- **Paper:** 80mm thermal paper roll
- **Resolution:** 203 DPI (recommended)
- **Driver:** ESC/POS compatible

### Optional Hardware
- **RFID Reader:** untuk automatic vehicle identification
- **LED Display:** untuk weight visualization
- **Traffic Light:** untuk vehicle flow control
- **Camera:** untuk photo capture

## 🚨 Troubleshooting

### Database Connection Error
```php
// Check MySQL service running
// Verify database credentials
// Test connection: http://localhost/jembatantimbangan/setup_database.php
```

### Serial Port Communication Error
```bash
# Check available COM ports
wmic path Win32_SerialPort get DeviceID

# Test with Python directly
cd api/
python test_serial.py
```

### Bridge Service Not Responding
```bash
# Check if bridge running
netstat -an | findstr :5001

# Restart bridge service
cd api/
python serial_bridge.py
```

### Session/Login Issues
- Check folder permissions: `sessions/` must be writable
- Clear browser cache and cookies
- Check PHP session configuration

### Performance Issues
- Enable PHP OPcache
- Use MySQL indexes
- Configure Apache KeepAlive
- Setup reverse proxy for production

## 🔒 Security Notes

### Production Setup Checklist
- [ ] Change default admin password
- [ ] Disable PHP error reporting (`display_errors = Off`)
- [ ] Enable HTTPS with SSL certificate
- [ ] Setup database user with limited privileges
- [ ] Enable Apache security modules
- [ ] Configure firewall rules
- [ ] Regular backups (database + files)
- [ ] Monitor access logs

### Recommended Security Settings
```php
// config/database.php - Production
error_reporting(0);
ini_set('display_errors', 0);

// .htaccess - Apache
ServerSignature Off
LimitRequestBody 10485760
```

## 📞 Support

### Technical Support
- **Email:** support@timbangan.com
- **Phone:** +62-XXX-XXXX-XXXX
- **Documentation:** [Online Documentation Link]

### Training Available
- On-site training for operators
- Remote technical support
- Customization services
- Hardware procurement assistance

## 📝 Changelog

### Version 2.0.0
- Added dual timbangan support
- Enhanced reporting features
- Improved hardware compatibility
- Mobile responsive design

### Version 1.5.0
- Initial RFID integration
- Barcode generation
- PDF export feature

### Version 1.0.0
- Basic timbangan functionality
- Database integration
- User authentication

## 📜 License

© 2024 PT. Timbangan Digital Indonesia. All rights reserved.

Proprietary software - distribution, reproduction, or modification without written permission is prohibited.

---

**Installation Complete! 🎉**

Setelah mengikuti semua langkah di atas, aplikasi sudah siap digunakan. Untuk bantuan teknis, hubungi support team kami.