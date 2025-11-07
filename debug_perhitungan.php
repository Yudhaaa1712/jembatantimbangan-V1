<?php
// debug_perhitungan.php
// Debug perhitungan untuk menemukan perbedaan

require_once 'config/database.php';

echo "<h2>DEBUG PERHITUNGAN JavaScript vs PHP</h2>";

// Test dengan data real dari database (TKT-251107-007)
$test_data = [
    'berat1' => 380,      // dari database berat_bruto
    'berat2' => 120,      // dari database berat_tara
    'persenPotongan' => 3, // dari database persen_potongan
    'harga' => 3000       // dari database harga_per_kg
];

echo "<h3>Test dengan Data Real: TKT-251107-007</h3>";

// Tampilkan input
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Parameter</th><th>Nilai</th></tr>";
echo "<tr><td>Berat 1 (Bruto)</td><td>" . $test_data['berat1'] . "</td></tr>";
echo "<tr><td>Berat 2 (Tara)</td><td>" . $test_data['berat2'] . "</td></tr>";
echo "<tr><td>Persen Potongan</td><td>" . $test_data['persenPotongan'] . "%</td></tr>";
echo "<tr><td>Harga per Kg</td><td>" . number_format($test_data['harga']) . "</td></tr>";
echo "</table>";

// Perhitungan JavaScript (YANG BENAR)
echo "<h4>Perhitungan JavaScript (HASIL PERHITUNGAN OTOMATIS) - YANG BENAR:</h4>";
$bruto_js = $test_data['berat1'];
$tara_js = $test_data['berat2'];
$netto_js = $bruto_js - $tara_js;
$potonganKg_js = ($test_data['persenPotongan'] / 100) * $netto_js;
$nettoAkhir_js = $netto_js - $potonganKg_js;
$totalHarga_js = $nettoAkhir_js * $test_data['harga'];

echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #e6f7ff;'>";
echo "<tr><th>Step</th><th>Formula</th><th>Hasil</th></tr>";
echo "<tr><td>1</td><td>bruto = berat1</td><td>" . $bruto_js . "</td></tr>";
echo "<tr><td>2</td><td>tara = berat2</td><td>" . $tara_js . "</td></tr>";
echo "<tr><td>3</td><td>netto = bruto - tara</td><td>" . $bruto_js . " - " . $tara_js . " = <strong>" . $netto_js . "</strong></td></tr>";
echo "<tr><td>4</td><td>potonganKg = (persen/100) * netto</td><td>(" . $test_data['persenPotongan'] . "/100) * " . $netto_js . " = <strong>" . number_format($potonganKg_js, 2) . "</strong></td></tr>";
echo "<tr><td>5</td><td>nettoAkhir = netto - potonganKg</td><td>" . $netto_js . " - " . number_format($potonganKg_js, 2) . " = <strong>" . number_format($nettoAkhir_js, 2) . "</strong></td></tr>";
echo "<tr><td>6</td><td>totalHarga = nettoAkhir * harga</td><td>" . number_format($nettoAkhir_js, 2) . " * " . $test_data['harga'] . " = <strong>" . number_format($totalHarga_js) . "</strong></td></tr>";
echo "</table>";

// Perhitungan PHP saat ini
echo "<h4>Perhitungan PHP (Backend) - SAAT INI:</h4>";
$berat1 = $test_data['berat1'];
$harga = $test_data['harga'];
$berat2 = $test_data['berat2'];
$persenPotongan = $test_data['persenPotongan'];

// Formula PHP saat ini
$bruto = $berat1;
$tara = $berat2;
$netto = $bruto - $tara;
$potonganKg = ($persenPotongan / 100) * $netto;
$nettoAkhir = $netto - $potonganKg;
$totalHarga = $nettoAkhir * $harga;

echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #fff3cd;'>";
echo "<tr><th>Step</th><th>Formula</th><th>Hasil</th><th>Banding JS</th></tr>";
echo "<tr><td>1</td><td>bruto = berat1</td><td>" . $bruto . "</td><td>" . ($bruto == $bruto_js ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>2</td><td>tara = berat2</td><td>" . $tara . "</td><td>" . ($tara == $tara_js ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>3</td><td>netto = bruto - tara</td><td>" . $bruto . " - " . $tara . " = <strong>" . $netto . "</strong></td><td>" . ($netto == $netto_js ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>4</td><td>potonganKg = (persen/100) * netto</td><td>(" . $persenPotongan . "/100) * " . $netto . " = <strong>" . number_format($potonganKg, 2) . "</strong></td><td>" . ($potonganKg == $potonganKg_js ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>5</td><td>nettoAkhir = netto - potonganKg</td><td>" . $netto . " - " . number_format($potonganKg, 2) . " = <strong>" . number_format($nettoAkhir, 2) . "</strong></td><td>" . ($nettoAkhir == $nettoAkhir_js ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>6</td><td>totalHarga = nettoAkhir * harga</td><td>" . number_format($nettoAkhir, 2) . " * " . $harga . " = <strong>" . number_format($totalHarga) . "</strong></td><td>" . ($totalHarga == $totalHarga_js ? "✅" : "❌") . "</td></tr>";
echo "</table>";

// Data yang diharapkan vs yang tersimpan
echo "<h4>Data Database:</h4>";
$result = mysqli_query($conn, "SELECT * FROM transaksi_timbangan WHERE no_tiket = 'TKT-251107-007'");
$row = mysqli_fetch_assoc($result);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Kolom</th><th>Database</th><th>Harusnya (JS)</th><th>Status</th></tr>";
echo "<tr><td>berat_netto</td><td>" . number_format($row['berat_netto'], 2) . "</td><td>" . number_format($nettoAkhir_js, 2) . "</td><td>" . (abs($row['berat_netto'] - $nettoAkhir_js) < 0.01 ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>total_harga</td><td>" . number_format($row['total_harga']) . "</td><td>" . number_format($totalHarga_js) . "</td><td>" . ($row['total_harga'] == $totalHarga_js ? "✅" : "❌") . "</td></tr>";
echo "</table>";

echo "<h3>Kesimpulan:</h3>";
if (abs($row['berat_netto'] - $nettoAkhir_js) < 0.01 && $row['total_harga'] == $totalHarga_js) {
    echo "<p style='color: green;'><strong>✅ PERHITUNGAN SUDAH BENAR!</strong></p>";
} else {
    echo "<p style='color: red;'><strong>❌ MASIH ADA PERBEDAAN!</strong></p>";
    echo "<p>Data JavaScript (benar): nettoAkhir = " . number_format($nettoAkhir_js, 2) . ", totalHarga = " . number_format($totalHarga_js) . "</p>";
    echo "<p>Data Database (salah): berat_netto = " . number_format($row['berat_netto'], 2) . ", total_harga = " . number_format($row['total_harga']) . "</p>";
}
?>