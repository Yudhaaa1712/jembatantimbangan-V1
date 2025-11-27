# Quick Installation Guide

## 🚀 Instalasi Cepat (5 Menit)

### 1. Install XAMPP
Download & install XAMPP dari https://www.apachefriends.org/

### 2. Extract Project
Copy folder `jembatantimbangan` ke `C:\xampp\htdocs\`

### 3. Setup Database
Buka: http://localhost/phpmyadmin
- Buat database: `jembatan_timbangan`
- Import file: `database_schema.sql`

### 4. Install Python Dependencies
```bash
pip install pyserial flask requests
```

### 5. Start Services
- Start Apache & MySQL di XAMPP Control Panel
- Jalankan bridge: `cd api && python serial_bridge.py`

### 6. Login
- Buka: http://localhost/jembatantimbangan/
- Username: `admin`
- Password: `admin123`

**Selesai! 🎉**