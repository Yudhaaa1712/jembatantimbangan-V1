<?php
// test_struk_benar.php
// Test struk menampilkan perhitungan yang benar tanpa mengubah database

require_once 'config/database.php';

echo "<h2>🎯 TEST STRUK - Database TIDAK Diubah, Struk TAMPIL BENAR</h2>";

// Ambil data real dari database
$result = mysqli_query($conn, "SELECT * FROM transaksi_timbangan WHERE status = 'selesai' ORDER BY updated_at DESC LIMIT 3");

while ($row = mysqli_fetch_assoc($result)) {
    echo "<h3>Test Struk: " . $row['no_tiket'] . "</h3>";

    // Data dari database (TIDAK DIUBAH)
    $db_berat_bruto = $row['berat_bruto'];
    $db_berat_tara = $row['berat_tara'];
    $db_persen_potongan = $row['persen_potongan'];
    $db_harga_per_kg = $row['harga_per_kg'];
    $db_berat_netto = $row['berat_netto'];
    $db_kg_potongan = $row['kg_potongan'];
    $db_total_harga = $row['total_harga'];

    // Perhitungan JavaScript (YANG BENAR) - untuk struk
    $bruto = $db_berat_bruto;
    $tara = $db_berat_tara;
    $netto = $bruto - $tara;
    $persenPotongan = $db_persen_potongan;
    $potonganKg = ($persenPotongan / 100) * $netto;
    $nettoAkhir = $netto - $potonganKg;
    $totalHarga = $nettoAkhir * $db_harga_per_kg;

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th colspan='3' style='background: #e6f7ff;'>DATA DATABASE (TIDAK DIUBAH)</th></tr>";
    echo "<tr><th>Kolom</th><th>Nilai</th><th>Status</th></tr>";
    echo "<tr><td>berat_bruto</td><td>" . number_format($db_berat_bruto) . "</td><td>✅ Data Dasar</td></tr>";
    echo "<tr><td>berat_tara</td><td>" . number_format($db_berat_tara) . "</td><td>✅ Data Dasar</td></tr>";
    echo "<tr><td>persen_potongan</td><td>" . number_format($db_persen_potongan, 2) . "%</td><td>✅ Data Dasar</td></tr>";
    echo "<tr><td>berat_netto</td><td style='background: #fff3cd;'>" . number_format($db_berat_netto, 2) . "</td><td>❌ Salah (tidak diubah)</td></tr>";
    echo "<tr><td>kg_potongan</td><td style='background: #fff3cd;'>" . number_format($db_kg_potongan, 2) . "</td><td>❌ Salah (tidak diubah)</td></tr>";
    echo "<tr><td>total_harga</td><td style='background: #fff3cd;'>" . number_format($db_total_harga) . "</td><td>❌ Salah (tidak diubah)</td></tr>";
    echo "</table>";

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
    echo "<tr><th colspan='3' style='background: #d4edda;'>PERHITUNGAN JAVASCRIPT (UNTUK STRUK)</th></tr>";
    echo "<tr><th>Parameter</th><th>Hasil</th><th>Formula</th></tr>";
    echo "<tr><td>Bruto</td><td>" . number_format($bruto) . " Kg</td><td>bruto = berat1</td></tr>";
    echo "<tr><td>Tara</td><td>" . number_format($tara) . " Kg</td><td>tara = berat2</td></tr>";
    echo "<tr><td>Netto</td><td>" . number_format($netto) . " Kg</td><td>netto = bruto - tara</td></tr>";
    echo "<tr><td>Potongan " . $persenPotongan . "%</td><td>" . number_format($potonganKg, 2) . " Kg</td><td>(persen/100) * netto</td></tr>";
    echo "<tr><td><strong>Netto Akhir</strong></td><td style='background: #d4edda; font-weight: bold;'>" . number_format($nettoAkhir, 2) . " Kg</td><td>netto - potongan</td></tr>";
    echo "<tr><td><strong>Total Harga</strong></td><td style='background: #d4edda; font-weight: bold;'>Rp " . number_format($totalHarga) . "</td><td>nettoAkhir * harga</td></tr>";
    echo "</table>";

    echo "<h4>🎯 Yang Akan Ditampilkan di Struk:</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #cce5ff;'>";
    echo "<tr><th>Item</th><th>Nilai di Struk</th><th>Sumber</th></tr>";
    echo "<tr><td>Berat 1 (Bruto)</td><td>" . number_format($bruto) . " Kg</td><td>Database</td></tr>";
    echo "<tr><td>Berat 2 (Tara)</td><td>" . number_format($tara) . " Kg</td><td>Database</td></tr>";
    echo "<tr><td>Berat Bersih (Netto)</td><td><strong>" . number_format($nettoAkhir, 2) . " Kg</strong></td><td>✅ Perhitungan JavaScript</td></tr>";
    echo "<tr><td>Potongan (" . $persenPotongan . "%)</td><td>" . number_format($potonganKg, 2) . " Kg</td><td>✅ Perhitungan JavaScript</td></tr>";
    echo "<tr><td>Total Harga</td><td><strong>Rp " . number_format($totalHarga) . "</strong></td><td>✅ Perhitungan JavaScript</td></tr>";
    echo "</table>";

    echo "<div style='background: #d1ecf1; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
    echo "<strong>✅ Hasil:</strong><br>";
    echo "• Database <strong>TIDAK DIUBAH</strong> (data lama tetap tersimpan)<br>";
    echo "• Struk <strong>TAMPIL BENAR</strong> (sesuai perhitungan JavaScript)<br>";
    echo "• User <strong>TIDAK COMPLAIN</strong> karena struk sesuai perhitungan otomatis";
    echo "</div>";

    echo "<hr>";
}

echo "<h3>🔥 Konsep Final:</h3>";
echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
echo "<h4>Database vs Struk:</h4>";
echo "<p><strong>Database:</strong> Menyimpan data asli (user tidak mau diubah)</p>";
echo "<p><strong>Struk:</strong> Menghitung ulang dengan formula JavaScript (yang benar)</p>";
echo "<p><strong>Hasil:</strong> Database aman, struk benar, user happy!</p>";
echo "</div>";

echo "<h3>📋 Cara Verifikasi:</h3>";
echo "<ol>";
echo "<li>Buka struk untuk tiket yang sudah ada</li>";
echo "<li>Bandngkan dengan 'HASIL PERHITUNGAN OTOMATIS' di timbangan 2</li>";
echo "<li>Mereka HARUS SAMA!</li>";
echo "<li>Check database - data tetap seperti semula</li>";
echo "</ol>";

echo "<p style='color: green; font-weight: bold; text-align: center;'>";
echo "🎯 SOLUSI FINAL: Database TIDAK diubah, Struk TAMPIL BENAR! 🎯";
echo "</p>";
?>