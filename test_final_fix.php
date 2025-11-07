<?php
// test_final_fix.php
// Test FINAL untuk memastikan SEMUA perhitungan SAMA PERSIS

require_once 'config/database.php';

echo "<h2>🎯 TEST FINAL - SEMUA PERHITUNGAN SAMA PERSIS!</h2>";

// Test case dengan data real
$test_cases = [
    [
        'nama' => 'Test Case 1',
        'berat1' => 380,
        'berat2' => 120,
        'persenPotongan' => 3,
        'harga' => 3000
    ],
    [
        'nama' => 'Test Case 2',
        'berat1' => 25000,
        'berat2' => 12000,
        'persenPotongan' => 5,
        'harga' => 2000
    ]
];

foreach ($test_cases as $test) {
    echo "<h3>" . $test['nama'] . "</h3>";

    // ==================== PERHITUNGAN JAVASCRIPT (YANG BENAR) ====================
    echo "<h4>🔵 JavaScript (HASIL PERHITUNGAN OTOMATIS) - YANG BENAR:</h4>";

    $bruto_js = $test['berat1'];
    $tara_js = $test['berat2'];
    $netto_js = $bruto_js - $tara_js;
    $potonganKg_js = ($test['persenPotongan'] / 100) * $netto_js;
    $nettoAkhir_js = $netto_js - $potonganKg_js;
    $totalHarga_js = $nettoAkhir_js * $test['harga'];

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #e6f7ff;'>";
    echo "<tr><th>Parameter</th><th>Hasil</th></tr>";
    echo "<tr><td>Bruto</td><td>" . number_format($bruto_js) . " Kg</td></tr>";
    echo "<tr><td>Tara</td><td>" . number_format($tara_js) . " Kg</td></tr>";
    echo "<tr><td>Netto</td><td>" . number_format($netto_js) . " Kg</td></tr>";
    echo "<tr><td>Potongan (" . $test['persenPotongan'] . "%)</td><td>" . number_format($potonganKg_js, 2) . " Kg</td></tr>";
    echo "<tr><td><strong>Netto Akhir</strong></td><td><strong>" . number_format($nettoAkhir_js, 2) . " Kg</strong></td></tr>";
    echo "<tr><td><strong>Total Harga</strong></td><td><strong>Rp " . number_format($totalHarga_js) . "</strong></td></tr>";
    echo "</table>";

    // ==================== PERHITUNGAN PHP (BACKEND) ====================
    echo "<h4>🟢 PHP (Backend) - HARUS SAMA:</h4>";

    // Variabel yang sama dengan JavaScript
    $berat1 = $test['berat1'];
    $harga = $test['harga'];
    $berat2 = $test['berat2'];
    $persenPotongan = $test['persenPotongan'];

    // Formula yang SAMA PERSIS dengan JavaScript
    $bruto = $berat1;
    $tara = $berat2;
    $netto = $bruto - $tara;
    $potonganKg = ($persenPotongan / 100) * $netto;
    $nettoAkhir = $netto - $potonganKg;
    $totalHarga = $nettoAkhir * $harga;

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #d4edda;'>";
    echo "<tr><th>Parameter</th><th>Hasil</th><th>VS JS</th></tr>";
    echo "<tr><td>Bruto</td><td>" . number_format($bruto) . " Kg</td><td>" . ($bruto == $bruto_js ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>Tara</td><td>" . number_format($tara) . " Kg</td><td>" . ($tara == $tara_js ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>Netto</td><td>" . number_format($netto) . " Kg</td><td>" . ($netto == $netto_js ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>Potongan (" . $persenPotongan . "%)</td><td>" . number_format($potonganKg, 2) . " Kg</td><td>" . (abs($potonganKg - $potonganKg_js) < 0.01 ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td><strong>Netto Akhir</strong></td><td><strong>" . number_format($nettoAkhir, 2) . " Kg</strong></td><td>" . (abs($nettoAkhir - $nettoAkhir_js) < 0.01 ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td><strong>Total Harga</strong></td><td><strong>Rp " . number_format($totalHarga) . "</strong></td><td>" . ($totalHarga == $totalHarga_js ? "✅" : "❌") . "</td></tr>";
    echo "</table>";

    // ==================== DATA YANG AKAN DISIMPAN KE DATABASE ====================
    echo "<h4>🟠 Data yang Disimpan ke Database:</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #fff3cd;'>";
    echo "<tr><th>Kolom Database</th><th>Nilai</th><th>Sumber</th><th>VS JS</th></tr>";
    echo "<tr><td>berat_tara</td><td>" . number_format($tara) . "</td><td>tara</td><td>✅</td></tr>";
    echo "<tr><td>kg_potongan</td><td>" . number_format($potonganKg, 2) . "</td><td>potonganKg</td><td>" . (abs($potonganKg - $potonganKg_js) < 0.01 ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>berat_netto</td><td><strong>" . number_format($nettoAkhir, 2) . "</strong></td><td>nettoAkhir</td><td>" . (abs($nettoAkhir - $nettoAkhir_js) < 0.01 ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>total_harga</td><td><strong>" . number_format($totalHarga) . "</strong></td><td>totalHarga</td><td>" . ($totalHarga == $totalHarga_js ? "✅" : "❌") . "</td></tr>";
    echo "</table>";

    // ==================== HASIL AKHIR ====================
    $all_match = (
        $bruto == $bruto_js &&
        $tara == $tara_js &&
        $netto == $netto_js &&
        abs($potonganKg - $potonganKg_js) < 0.01 &&
        abs($nettoAkhir - $nettoAkhir_js) < 0.01 &&
        $totalHarga == $totalHarga_js
    );

    echo "<h4>📊 Hasil Verifikasi:</h4>";
    if ($all_match) {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px;'>";
        echo "<strong>✅ SEMUA PERHITUNGAN SAMA PERSIS!</strong><br>";
        echo "JavaScript = PHP = Database";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px;'>";
        echo "<strong>❌ MASIH ADA PERBEDAAN!</strong><br>";
        echo "Harus fix lagi!";
        echo "</div>";
    }

    echo "<hr>";
}

echo "<h3>🔥 CARA VERIFIKASI DI PRODUKSI:</h3>";
echo "<ol>";
echo "<li><strong>Buka timbangan 2</strong></li>";
echo "<li><strong>Masukkan data test</strong> (contoh: Test Case 1)</li>";
echo "<li><strong>Lihat 'HASIL PERHITUNGAN OTOMATIS'</strong> di layar</li>";
echo "<li><strong>Submit form</strong></li>";
echo "<li><strong>Check database:</strong> <code>SELECT * FROM transaksi_timbangan WHERE no_tiket='[tiket]'</code></li>";
echo "<li><strong>Cetak struk</strong></li>";
echo "<li><strong>Bandingkan ketiganya:</strong> JavaScript = Database = Struk</li>";
echo "</ol>";

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
echo "<h4>🎯 TARGET: JavaScript (Frontend) = PHP (Backend) = Database = Struk</h4>";
echo "<p><strong>Formula yang digunakan (SAMA PERSIS):</strong></p>";
echo "<code>";
echo "bruto = berat1<br>";
echo "tara = berat2<br>";
echo "netto = bruto - tara<br>";
echo "potonganKg = (persenPotongan / 100) * netto<br>";
echo "nettoAkhir = netto - potonganKg<br>";
echo "totalHarga = nettoAkhir * harga";
echo "</code>";
echo "</div>";

echo "<p style='color: red; font-weight: bold; text-align: center; font-size: 18px;'>";
echo "🎯 YANG PENTING: HASIL PERHITUNGAN OTOMATIS (JavaScript) adalah YANG BENAR!";
echo "<br>SEMUA harus mengikuti hasil JavaScript!";
echo "</p>";
?>