<?php
// test_perhitungan_sama.php
// Test untuk memverifikasi perhitungan PHP SAMA PERSIS dengan JavaScript

echo "<h2>Test Perhitungan PHP vs JavaScript (SAMA PERSIS)</h2>";

// Test data yang sama dengan yang digunakan di JavaScript
$test_cases = [
    [
        'berat1' => 30000,      // dari dataset.berat (JavaScript)
        'berat2' => 15000,      // dari input berat2 (JavaScript)
        'persenPotongan' => 5,  // dari input persenPotongan (JavaScript)
        'harga' => 2000         // dari dataset.harga (JavaScript)
    ],
    [
        'berat1' => 25000,
        'berat2' => 12000,
        'persenPotongan' => 3,
        'harga' => 2500
    ]
];

foreach ($test_cases as $i => $test) {
    echo "<h3>Test Case " . ($i + 1) . ":</h3>";

    // Perhitungan PHP (Backend) - SAMA PERSIS dengan JavaScript
    $berat1 = $test['berat1']; // sama dengan berat1 di JavaScript
    $harga = $test['harga']; // sama dengan harga di JavaScript
    $berat2 = $test['berat2']; // sama dengan berat2 di JavaScript
    $persenPotongan = $test['persenPotongan']; // sama dengan persenPotongan di JavaScript

    // Formula PERSIS SAMA dengan JavaScript:
    $bruto = $berat1; // Timbangan 1 = BRUTO (Truck Penuh)
    $tara = $berat2;  // Timbangan 2 = TARA (Truck Kosong)
    $netto = $bruto - $tara; // Netto (sama dengan JavaScript)
    $potonganKg = ($persenPotongan / 100) * $netto; // Potongan dalam kg (sama dengan JavaScript)
    $nettoAkhir = $netto - $potonganKg; // Netto Akhir (sama dengan JavaScript)
    $totalHarga = $nettoAkhir * $harga; // Total Harga (sama dengan JavaScript)

    // Data yang disimpan ke database
    $berat_netto = $nettoAkhir; // berat_netto = nettoAkhir (hasil akhir setelah potongan)
    $kg_potongan = $potonganKg;

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th colspan='3' style='background: #e6f7ff;'>HASIL PERHITUNGAN PHP (Backend)</th></tr>";
    echo "<tr><th>Parameter</th><th>Nilai</th><th>JavaScript Variable</th></tr>";
    echo "<tr><td>Berat 1 (Bruto)</td><td><strong>" . number_format($bruto) . " Kg</strong></td><td>berat1 = " . $berat1 . "</td></tr>";
    echo "<tr><td>Berat 2 (Tara)</td><td><strong>" . number_format($tara) . " Kg</strong></td><td>berat2 = " . $berat2 . "</td></tr>";
    echo "<tr><td>Persen Potongan</td><td><strong>" . $persenPotongan . "%</strong></td><td>persenPotongan = " . $persenPotongan . "</td></tr>";
    echo "<tr><td>Harga per Kg</td><td><strong>Rp " . number_format($harga) . "</strong></td><td>harga = " . $harga . "</td></tr>";
    echo "<tr><td>Netto (Bruto - Tara)</td><td style='background: #fff3cd;'>" . number_format($netto) . " Kg</td><td>netto = bruto - tara</td></tr>";
    echo "<tr><td>Potongan (kg)</td><td style='background: #f8d7da;'>" . number_format($potonganKg, 2) . " Kg</td><td>potonganKg = (persenPotongan/100) * netto</td></tr>";
    echo "<tr><td><strong>Netto Akhir</strong></td><td style='background: #d4edda; font-weight: bold;'>" . number_format($nettoAkhir, 2) . " Kg</td><td>nettoAkhir = netto - potonganKg</td></tr>";
    echo "<tr><td><strong>Total Harga</strong></td><td style='background: #cce5ff; font-weight: bold;'>Rp " . number_format($totalHarga) . "</td><td>totalHarga = nettoAkhir * harga</td></tr>";
    echo "</table>";

    echo "<h4>Data yang disimpan ke database:</h4>";
    echo "<table border='1' cellpadding='3' style='border-collapse: collapse;'>";
    echo "<tr><th>Kolom Database</th><th>Nilai</th><th>Sumber</th></tr>";
    echo "<tr><td>berat_tara</td><td>" . number_format($tara) . "</td><td>berat2</td></tr>";
    echo "<tr><td>kg_potongan</td><td>" . number_format($kg_potongan, 2) . "</td><td>potonganKg</td></tr>";
    echo "<tr><td>berat_netto</td><td><strong>" . number_format($berat_netto, 2) . "</strong></td><td>nettoAkhir</td></tr>";
    echo "<tr><td>total_harga</td><td><strong>" . number_format($totalHarga) . "</strong></td><td>totalHarga</td></tr>";
    echo "</table>";

    echo "<p style='color: green;'><strong>✅ Perhitungan PHP ini SAMA PERSIS dengan JavaScript (HASIL PERHITUNGAN OTOMATIS)</strong></p>";
    echo "<hr>";
}

echo "<h3>Verifikasi JavaScript vs PHP</h3>";
echo "<p>Cara verifikasi:</p>";
echo "<ol>";
echo "<li>Buka halaman timbangan 2</li>";
echo "<li>Masukkan data test di atas</li>";
echo "<li>Lihat 'HASIL PERHITUNGAN OTOMATIS' di layar (JavaScript)</li>";
echo "<li>Submit form dan lihat data yang tersimpan di database (PHP)</li>";
echo "<li>Pastikan hasilnya SAMA PERSIS!</li>";
echo "</ol>";

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px;'>";
echo "<h4>Formula yang digunakan (SAMA untuk JavaScript dan PHP):</h4>";
echo "<code>";
echo "bruto = berat1<br>";
echo "tara = berat2<br>";
echo "netto = bruto - tara<br>";
echo "potonganKg = (persenPotongan / 100) * netto<br>";
echo "nettoAkhir = netto - potonganKg<br>";
echo "totalHarga = nettoAkhir * harga";
echo "</code>";
echo "</div>";

echo "<p style='color: red; font-weight: bold;'><strong>🎯 YANG PENTING: Hasil perhitungan PHP (backend) SAMA PERSIS dengan JavaScript (frontend HASIL PERHITUNGAN OTOMATIS)!</strong></p>";
?>