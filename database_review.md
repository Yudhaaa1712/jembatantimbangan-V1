# DATABASE REVIEW - Current vs Future Features

## ✅ **CURRENT WORKFLOW ANALYSIS**

### **Timbangan 1 Workflow (Manual Input):**
1. **Input Manual:**
   - ✅ No. Polisi → Cari/Buat kendaraan
   - ✅ Nama Supir → Manual input
   - ✅ Nama Supplier → Cari/Buat supplier
   - ✅ Material → Dropdown (TBS, CPO, Kernel, Brondolan)
   - ✅ Harga → Manual input
   - ✅ Keterangan → Manual input

2. **Process:**
   - ✅ Capture weight → simpan ke `berat_timbangan1`
   - ✅ Auto-generate ticket number
   - ✅ Auto-create kendaraan/supplier jika belum ada
   - ✅ Status = 'timbang_1'

### **Timbangan 2 Workflow:**
1. **Select Ticket** → Load real-time data dari timbangan1
2. **Display:** No. Polisi, Supir, Supplier, Material, Harga
3. **Capture Tara** → simpan ke `berat_timbangan2`
4. **Calculate Netto** → Auto calculation
5. **Status Change** → 'selesai'

## 🎯 **CURRENT DATABASE ASSESSMENT**

### **✅ Already Covered:**
- **Authentication** → `users` table
- **Core Transactions** → `transaksi_timbangan` table
- **Master Data** → `kendaraan`, `supplier` tables
- **Settings** → `settings` table
- **Logging** → `activity_logs` table

### **❓ Potential Gaps for Future Features:**

#### **1. Customer Management (Fitur Potensial)**
```sql
-- Table untuk customer/pembeli
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_customer VARCHAR(20) UNIQUE NOT NULL,
    nama_customer VARCHAR(100) NOT NULL,
    alamat TEXT,
    no_telepon VARCHAR(20),
    email VARCHAR(100),
    npwp VARCHAR(30),
    kontak_person VARCHAR(100),
    kredit_limit DECIMAL(15,2) DEFAULT 0,
    status ENUM('active', 'inactive', 'blacklist') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### **2. Inventory/Warehouse Management**
```sql
-- Table untuk inventory material
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jenis_material ENUM('tbs', 'cpo', 'kernel', 'brondolan') NOT NULL,
    quantity_kg DECIMAL(15,2) DEFAULT 0,
    lokasi_penyimpanan VARCHAR(100),
    kualitas_grade VARCHAR(20),
    tanggal_produksi DATE,
    tanggal_kadaluarsa DATE,
    status ENUM('available', 'reserved', 'used', 'expired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### **3. Pengiriman/Delivery Management**
```sql
-- Table untuk pengiriman
CREATE TABLE pengiriman (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_surat_jalan VARCHAR(30) UNIQUE NOT NULL,
    transaksi_id INT,
    id_customer INT,
    tujuan_pengiriman TEXT,
    tanggal_pengiriman DATE,
    waktu_berangkat TIME,
    waktu_sampai TIME,
    status_pengiriman ENUM('prepare', 'on_transit', 'delivered', 'cancelled') DEFAULT 'prepare',
    pengemudi VARCHAR(100),
    no_telepon_pengemudi VARCHAR(20),
    keterangan_pengiriman TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi_timbangan(id),
    FOREIGN KEY (id_customer) REFERENCES customers(id)
);
```

#### **4. Keuangan/Invoicing**
```sql
-- Table untuk invoice
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_invoice VARCHAR(30) UNIQUE NOT NULL,
    transaksi_id INT,
    id_customer INT,
    tanggal_invoice DATE,
    jatuh_tempo DATE,
    total_amount DECIMAL(15,2) NOT NULL,
    ppn_percent DECIMAL(5,2) DEFAULT 11,
    ppn_amount DECIMAL(15,2) DEFAULT 0,
    diskon_percent DECIMAL(5,2) DEFAULT 0,
    diskon_amount DECIMAL(15,2) DEFAULT 0,
    grand_total DECIMAL(15,2) NOT NULL,
    status_pembayaran ENUM('unpaid', 'partial', 'paid', 'overdue') DEFAULT 'unpaid',
    tanggal_bayar DATE,
    metode_pembayaran VARCHAR(50),
    keterangan_invoice TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi_timbangan(id),
    FOREIGN KEY (id_customer) REFERENCES customers(id)
);

-- Table untuk pembayaran
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT,
    tanggal_bayar DATE,
    jumlah_bayar DECIMAL(15,2) NOT NULL,
    metode_pembayaran ENUM('cash', 'transfer', 'cek', 'giro', 'lainnya') NOT NULL,
    bank VARCHAR(50),
    no_referensi VARCHAR(100),
    keterangan_pembayaran TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);
```

#### **5. Reporting & Analytics**
```sql
-- Table untuk saved reports
CREATE TABLE saved_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_report VARCHAR(100) NOT NULL,
    jenis_report ENUM('harian', 'mingguan', 'bulanan', 'tahunan', 'custom') NOT NULL,
    parameter_filter TEXT,
    file_path VARCHAR(255),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT,
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

-- Table untuk analytics cache
CREATE TABLE analytics_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(100) UNIQUE NOT NULL,
    cache_data LONGTEXT,
    cache_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### **6. User Preferences & Permissions**
```sql
-- Table untuk user permissions detail
CREATE TABLE user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    module VARCHAR(50) NOT NULL,
    can_create TINYINT(1) DEFAULT 0,
    can_read TINYINT(1) DEFAULT 1,
    can_update TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table untuk user preferences
CREATE TABLE user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## 🚨 **CURRENT ISSUES FOUND:**

### **1. Manual Input Flow Not Optimal:**
- ❌ Kendaraan: Baru dibuat auto, tapi data kurang lengkap (pemilik, kontak, dll)
- ❌ Supplier: Baru dibuat auto, tapi data kurang lengkap (alamat, kontak, dll)
- ❌ Tidak ada validation untuk no. polisi format

### **2. Missing Validation:**
- ❌ No. polisi format validation
- ❌ Weight range validation (min/max)
- ❌ Material quantity validation
- ❌ Price validation against market rates

### **3. Audit Trail Gaps:**
- ❌ Tidak ada log untuk weight changes
- ❌ Tidak ada log untuk price changes
- ❌ Tidak ada log untuk status changes detail

## ✅ **RECOMMENDATIONS:**

### **Immediate (Required for Current Features):**
1. **Add validation functions** untuk manual input
2. **Improve auto-creation** dengan lebih detail data
3. **Add detailed activity logs** untuk weight & price changes
4. **Add user preferences** untuk default values

### **Short Term (Next 3 Months):**
1. **Customer management** system
2. **Basic reporting** enhancements
3. **Invoice generation** feature
4. **Export/import** functionality

### **Long Term (6+ Months):**
1. **Full inventory management**
2. **Delivery tracking** system
3. **Financial reporting**
4. **Mobile app** integration
5. **API integration** dengan ERP systems

## 🎯 **CONCLUSION:**

**Current database 80% ready** untuk current workflow, but needs:
- ✅ Auto-creation improvements (FIXED)
- ✅ Validation enhancements (NEEDED)
- ✅ Better logging (NEEDED)
- ✅ Future scalability tables (PREPARED)

**Next Steps:**
1. Implement current fixes
2. Add validation layer
3. Prepare future tables when needed