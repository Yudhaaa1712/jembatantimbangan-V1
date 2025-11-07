# Instruksi Verifikasi Perbaikan Timbangan 2

## ✅ **PERBAIKAN SUDAH SELESAI!**

### **Masalah Yang Diperbaiki:**
- Hasil perhitungan otomatis di timbangan 2 sudah BENAR tapi yang tersimpan ke database SALAH
- Data yang tersimpan ke database tidak lengkap (hanya berat_tara, berat_timbangan2, persen_potongan, kg_potongan)
- Struk mencetak data yang salah dari database

### **Solusi Yang Diterapkan:**
1. **Update `timbangan2.php`**: Menyimpan `berat_netto` dan `total_harga` dengan hasil perhitungan yang BENAR
2. **Update `print_ticket.php`**: Menggunakan data yang sudah benar dari database

---

## **Cara Verifikasi:**

### **1. Test Proses Timbangan 2**
- Buka halaman timbangan 2
- Pilih tiket yang sudah timbang 1
- Masukkan persen potongan (contoh: 5%)
- Capture berat timbangan 2
- Lihat hasil perhitungan otomatis yang muncul

### **2. Check Database**
```sql
SELECT
    no_tiket,
    berat_bruto,
    berat_tara,
    persen_potongan,
    kg_potongan,
    berat_netto,
    total_harga,
    status
FROM transaksi_timbangan
WHERE no_tiket = '[NOMOR_TIKET_TEST]';
```

**Yang harus diperiksa:**
- `berat_netto` = **Netto Akhir** (setelah potongan)
- `total_harga` = **Netto Akhir × Harga per kg**

### **3. Cetak Struk**
- Klik "Cetak Struk" setelah proses timbangan 2 selesai
- Pastikan data di struk sama dengan perhitungan otomatis yang muncul di layar

---

## **Contoh Perhitungan Yang Benar:**

```
Timbangan 1 (Bruto): 30.000 kg
Timbangan 2 (Tara): 15.000 kg
Netto: 30.000 - 15.000 = 15.000 kg
Potongan 5%: 15.000 × 5% = 750 kg
Netto Akhir: 15.000 - 750 = 14.250 kg
Total Harga: 14.250 × Rp 2.000 = Rp 28.500.000
```

**Data yang tersimpan di database:**
- `berat_tara` = 15.000
- `kg_potongan` = 750.00
- `berat_netto` = **14.250** (ini adalah netto akhir setelah potongan)
- `total_harga` = **28.500.000**

**Data yang dicetak di struk:**
- Berat Bersih (Netto) = **14.250 kg**
- Total Harga = **Rp 28.500.000**

---

## **File Yang Diubah:**
1. `modules/timbangan/timbangan2.php` (baris 37-61)
2. `modules/timbangan/print_ticket.php` (baris 378-394)
3. `test_fix_timbangan2.php` (file test baru)

---

## **✅ Verifikasi Berhasil Jika:**
- ✅ Hasil perhitungan di layar timbangan 2 BENAR
- ✅ Data tersimpan ke database dengan BENAR
- ✅ Struk mencetak data yang SAMA dengan layar
- ✅ Tidak ada error saat proses timbangan 2

**Status: PERBAIKAN SELESAI! 🎉**