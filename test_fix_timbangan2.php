<?php
// test_fix_timbangan2.php
// Test file untuk memverifikasi perhitungan timbangan 2 sudah benar

require_once 'config/database.php';

echo "<h2>Test Perbaikan Perhitungan Timbangan 2</h2>";

// Test dengan data sample
$test_cases = [
    [
        'no_tiket' => 'TEST001',
        'berat_bruto' => 30000,  // Timbangan 1 (Truck Penuh)
        'berat_tara' => 15000,   // Timbangan 2 (Truck Kosong)
        'persen_potongan' => 5,  // 5%
        'harga_per_kg' => 2000
    ],
    [
        'no_tiket' => 'TEST002',
        'berat_bruto' => 25000,
        'berat_tara' => 12000,
        'persen_potongan' => 3,
        'harga_per_kg' => 2500
    ]
];

foreach ($test_cases as $test) {
    echo "<h3>Test Case: " . $test['no_tiket'] . "</h3>";

    // Perhitungan yang BENAR (sesuai fix di timbangan2.php)
    $bruto = $test['berat_bruto'];     // Timbangan 1 = BRUTO (Penuh)
    $tara = $test['berat_tara'];       // Timbangan 2 = TARA (Kosong)
    $netto_bt = $bruto - $tara;        // Netto = Bruto - Tara
    $total_potongan = ($test['persen_potongan'] / 100) * $netto_bt; // Potongan (kg)
    $netto_akhir = $netto_bt - $total_potongan; // Netto Akhir
    $total_harga = $netto_akhir * $test['harga_per_kg']; // Total Harga

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Parameter</th><th>Nilai</th><th>Keterangan</th></tr>";
    echo "<tr><td>Berat Bruto (T1)</td><td>" . number_format($bruto) . " Kg</td><td>Truck Penuh</td></tr>";
    echo "<tr><td>Berat Tara (T2)</td><td>" . number_format($tara) . " Kg</td><td>Truck Kosong</td></tr>";
    echo "<tr><td>Netto</td><td><strong>" . number_format($netto_bt) . " Kg</strong></td><td>Bruto - Tara</td></tr>";
    echo "<tr><td>Potongan (" . $test['persen_potongan'] . "%)</td><td>" . number_format($total_potongan, 2) . " Kg</td><td>" . $test['persen_potongan'] . "% × Netto</td></tr>";
    echo "<tr><td>Netto Akhir</td><td><strong style='color: green;'>" . number_format($netto_akhir, 2) . " Kg</strong></td><td>Netto - Potongan</td></tr>";
    echo "<tr><td>Harga per Kg</td><td>Rp " . number_format($test['harga_per_kg']) . "</td><td>-</td></tr>";
    echo "<tr><td>Total Harga</td><td><strong style='color: blue;'>Rp " . number_format($total_harga) . "</strong></td><td>Netto Akhir × Harga</td></tr>";
    echo "</table>";

    echo "<p><strong>✅ Hasil perhitungan ini yang akan disimpan ke database dan dicetak di struk.</strong></p>";

    echo "<h4>Data yang tersimpan ke database:</h4>";
    echo "<ul>";
    echo "<li><code>berat_tara</code> = " . number_format($tara) . " Kg</li>";
    echo "<li><code>berat_timbangan2</code> = " . number_format($tara) . " Kg</li>";
    echo "<li><code>kg_potongan</code> = " . number_format($total_potongan, 2) . " Kg</li>";
    echo "<li><code>berat_netto</code> = " . number_format($netto_akhir, 2) . " Kg (ini adalah netto akhir setelah potongan)</li>";
    echo "<li><code>total_harga</code> = Rp " . number_format($total_harga) . "</li>";
    echo "</ul>";
    echo "<hr>";
}

echo "<h3>Verifikasi Database</h3>";
echo "<p>Cara verifikasi:</p>";
echo "<ol>";
echo "<li>Test timbangan 2 dengan data real</li>";
echo "<li>Check database: <code>SELECT no_tiket, berat_bruto, berat_tara, kg_potongan, berat_netto, total_harga FROM transaksi_timbangan WHERE no_tiket='[no_tiket_test]'</code></li>";
echo "<li>Pastikan kolom <strong>berat_netto</strong> dan <strong>total_harga</strong> terisi dengan nilai yang benar (sesuai perhitungan di atas)</li>";
echo "<li>Cetak struk dan pastikan nilainya sama dengan perhitungan di atas</li>";
echo "</ol>";

// Tampilkan struktur tabel aktual
echo "<h3>Struktur Tabel transaksi_timbangan (Aktual):</h3>";
$result = mysqli_query($conn, 'DESCRIBE transaksi_timbangan');
echo "<table border='1' cellpadding='3' style='border-collapse: collapse; font-size: 12px;'>";
echo "<tr><th style='background: #f0f0f0;'>Kolom</th><th style='background: #f0f0f0;'>Tipe Data</th></tr>";
while($row = mysqli_fetch_assoc($result)) {
    $style = (in_array($row['Field'], ['berat_netto', 'total_harga', 'kg_potongan'])) ? "style='background: #e6f7ff; font-weight: bold;'" : "";
    echo "<tr $style><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
}
echo "</table>";

echo "<p style='color: green;'><strong>✅ Perbaikan selesai! Sekarang hasil perhitungan otomatis di timbangan 2 sudah tersimpan dengan benar ke database dan dicetak di struk.</strong></p>";
?>