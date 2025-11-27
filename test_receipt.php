<?php
// Test receipt display
require_once 'config/database.php';

echo "<h2>Testing Receipt Format</h2>";

// Get sample transaction
$query = "SELECT tt.*, s.nama_supplier, u.nama_lengkap as operator_nama
          FROM transaksi_timbangan tt
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          LEFT JOIN users u ON tt.operator_id = u.id
          WHERE tt.no_tiket = 'TKT-251124-001'";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if ($data) {
    echo "<h3>Transaction Data: " . $data['no_tiket'] . "</h3>";

    // Extract data like in print_ticket.php
    $bruto = $data['berat_bruto'] ?? $data['berat_timbangan1'] ?? 0;
    $tara = $data['berat_tara'] ?? $data['berat_timbangan2'] ?? 0;
    $berat_timbangan1 = $data['berat_timbangan1'] ?? 0; // Timbangan 1
    $berat_timbangan2 = $data['berat_timbangan2'] ?? 0; // Timbangan 2
    $netto_awal = $bruto - $tara; // Netto dasar (sebelum potongan)
    $persen_potongan = $data['persen_potongan'] ?? 0;
    $harga_per_kg = $data['harga_per_kg'] ?? 0;
    $kg_potongan = $data['kg_potongan'] ?? 0; // Potongan dalam kg
    $berat_netto = $data['berat_netto'] ?? 0; // Netto akhir setelah potongan
    $total_harga = $data['total_harga'] ?? 0; // Total harga yang sudah dihitung
    $potong_hutang = $data['potong_hutang'] ?? 0; // Potong hutang
    $sisa_hutang = $data['sisa_hutang'] ?? 0; // Sisa hutang setelah potong
    $total_akhir2 = max(0, $total_harga - $potong_hutang); // Total akhir setelah potong hutang

    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>No. Tiket</td><td>" . $data['no_tiket'] . "</td></tr>";
    echo "<tr><td>Supplier</td><td>" . ($data['nama_supplier'] ?? '-') . "</td></tr>";
    echo "<tr><td>No. Polisi</td><td>" . $data['no_polisi'] . "</td></tr>";
    echo "<tr><td>Material</td><td>" . $data['jenis_material'] . "</td></tr>";
    echo "<tr><td>Bruto</td><td>" . number_format($bruto, 2, ',', '.') . " Kg</td></tr>";
    echo "<tr><td>Tara</td><td>" . number_format($tara, 2, ',', '.') . " Kg</td></tr>";
    echo "<tr><td>Netto</td><td>" . number_format($netto, 2, ',', '.') . " Kg</td></tr>";
    echo "<tr><td>Persentase Potongan</td><td>" . number_format($persen_potongan, 2) . "%</td></tr>";
    echo "<tr><td>Kg Potongan</td><td>" . number_format($kg_potongan, 2, ',', '.') . " Kg</td></tr>";
    echo "<tr><td>Netto Akhir</td><td>" . number_format($netto_akhir, 2, ',', '.') . " Kg</td></tr>";
    echo "<tr><td>Harga per Kg</td><td>Rp " . number_format($harga_per_kg, 2, ',', '.') . "</td></tr>";
    echo "<tr><td>Total Harga</td><td>Rp " . number_format($total_harga, 2, ',', '.') . "</td></tr>";
    echo "<tr><td>Potong Hutang</td><td>Rp " . number_format($potong_hutang, 2, ',', '.') . "</td></tr>";
    echo "<tr><td><strong>Sisa Hutang</strong></td><td><strong style='color: #dc2626;'>Rp " . number_format($sisa_hutang, 2, ',', '.') . "</strong></td></tr>";
    echo "<tr><td><strong>Total Akhir 2</strong></td><td><strong>Rp " . number_format($total_akhir2, 2, ',', '.') . "</strong></td></tr>";
    echo "</table>";

    echo "<h3>Receipt Preview Sections:</h3>";

    // Show Total calculation section
    echo "<div style='border: 2px solid #000; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Harga:</strong> Rp " . number_format($harga_per_kg, 2, ',', '.') . "/Kg | ";
    echo "<strong>TOTAL:</strong> Rp " . number_format($total_harga, 2, ',', '.');
    echo "</div>";

    // Show detailed calculation section
    echo "<h3>Perhitungan Detail (New Layout):</h3>";

    // Data Timbangan
    echo "<div style='border: 2px solid #000; padding: 15px; margin: 10px 0;'>";
    echo "<h4 style='text-align: center;'>DATA TIMBANGAN</h4>";
    echo "<table style='width: 100%; text-align: center;'>";
    echo "<tr><td><strong>TIMBANGAN 1</strong></td><td> - </td><td><strong>TIMBANGAN 2</strong></td><td> = </td><td><strong>NETTO AWAL</strong></td></tr>";
    echo "<tr><td>" . number_format($berat_timbangan1, 0, ',', '.') . " Kg</td><td> - </td><td>" . number_format($berat_timbangan2, 0, ',', '.') . " Kg</td><td> = </td><td><strong>" . number_format($netto_awal, 0, ',', '.') . " Kg</strong></td></tr>";
    echo "<tr><td>(BRUTO)</td><td></td><td>(TARA)</td><td></td><td></td></tr>";
    echo "</table>";
    echo "</div>";

    // Perhitungan Potongan
    if ($persen_potongan > 0 || $kg_potongan > 0) {
        echo "<div style='border: 1px dashed #000; padding: 10px; margin: 10px 0;'>";
        echo "<h4 style='text-align: center;'>PERHITUNGAN POTONGAN</h4>";
        echo "<table style='width: 100%; text-align: center;'>";
        echo "<tr><td>Potongan</td><td>×</td><td>Netto Awal</td><td>=</td><td>Kg Potongan</td></tr>";
        echo "<tr><td><strong>" . number_format($persen_potongan, 2) . "%</strong></td><td>×</td><td>" . number_format($netto_awal, 0, ',', '.') . " Kg</td><td>=</td><td><strong style='color: #856404;'>" . number_format($kg_potongan, 2, ',', '.') . " Kg</strong></td></tr>";
        echo "</table>";
        echo "<div style='text-align: center; background: #e8f5e8; padding: 8px; margin-top: 10px; border-radius: 5px;'>";
        echo "<strong>NETTO AKHIR: " . number_format($berat_netto, 2, ',', '.') . " Kg</strong>";
        echo "</div>";
        echo "</div>";
    }

    // Perhitungan Harga
    echo "<div style='border: 2px solid #000; padding: 15px; margin: 10px 0;'>";
    echo "<h4 style='text-align: center;'>PERHITUNGAN HARGA</h4>";
    echo "<table style='width: 100%; text-align: center;'>";
    echo "<tr><td>Netto Akhir</td><td>×</td><td>Harga</td><td>=</td><td>Total Harga</td></tr>";
    echo "<tr><td><strong>" . number_format($berat_netto, 0, ',', '.') . " Kg</strong></td><td>×</td><td><strong>Rp " . number_format($harga_per_kg, 0, ',', '.') . "</strong></td><td>=</td><td><strong style='color: #1565c0;'>Rp " . number_format($total_harga, 0, ',', '.') . "</strong></td></tr>";
    echo "</table>";
    echo "</div>";

    // Potong Hutang dan Total Akhir
    if ($potong_hutang > 0) {
        echo "<div style='border: 2px solid #dc2626; background: #fef2f2; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong style='color: #dc2626;'>POTONG HUTANG: Rp " . number_format($potong_hutang, 0, ',', '.') . "</strong>";
        echo "</div>";
    }

    echo "<div style='border: 3px double #10b981; background: #f0fdf4; padding: 15px; text-align: center; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4 style='color: #059669;'>TOTAL AKHIR 2</h4>";
    echo "<h2 style='color: #059669;'>Rp " . number_format($total_akhir2, 0, ',', '.') . "</h2>";
    if ($potong_hutang > 0) {
        echo "<em style='color: #059669;'>(Setelah Potong Hutang)</em>";
    }
    echo "</div>";

    echo "<div style='border: 2px solid #10b981; padding: 10px; margin: 10px 0; background: #f0fdf4;'>";
    if ($potong_hutang > 0) {
        echo "<span style='color: #dc2626;'><strong>Potong Hutang:</strong> Rp " . number_format($potong_hutang, 2, ',', '.') . "</span> | ";
    }
    echo "<span style='color: #10b981; font-size: 18px;'><strong>TOTAL AKHIR 2:</strong> Rp " . number_format($total_akhir2, 2, ',', '.') . "</span>";
    echo "</div>";

} else {
    echo "Transaction not found!";
}
?>