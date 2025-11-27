<?php
// Test print ticket with new layout
require_once 'config/database.php';

// Test dengan data sample
$no_tiket = 'TKT-251124-001';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Struk Baru</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .test-button {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 5px;
        }
        .test-button:hover {
            background: #059669;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .info {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Testing Struk Baru - Data Timbangan 2</h1>

        <h2 class="info">📊 Data Yang Diuji:</h2>
        <p><strong>No. Tiket:</strong> <?= $no_tiket ?></p>

        <h2>🔗 Links Testing:</h2>

        <div>
            <a href="test_receipt.php" class="test-button">📋 Test Layout Data</a>
            <a href="modules/timbangan/print_ticket.php?no_tiket=<?= $no_tiket ?>" class="test-button" target="_blank">🖨️ Cetak Struk Baru</a>
            <a href="modules/transaksi/" class="test-button">📈 Lihat Data Transaksi</a>
        </div>

        <h2>✅ Yang Sudah Diperbaiki:</h2>

        <div class="success">
            <h3>1. Tabel Transaksi ✓</h3>
            <ul>
                <li>✓ <code>berat_timbangan1</code> - Data dari timbangan 1 (bruto)</li>
                <li>✓ <code>berat_timbangan2</code> - Data dari timbangan 2 (tara)</li>
                <li>✓ <code>berat_tara</code> - Tara weight</li>
                <li>✓ <code>persen_potongan</code> - Persentase potongan</li>
                <li>✓ <code>kg_potongan</code> - Potongan dalam kg</li>
                <li>✓ <code>berat_netto</code> - Netto akhir setelah potongan</li>
                <li>✓ <code>harga_per_kg</code> - Harga per kilogram</li>
                <li>✓ <code>total_harga</code> - Total harga perhitungan</li>
                <li>✓ <code>potong_hutang</code> - Potong hutang supplier</li>
            </ul>
        </div>

        <div class="success">
            <h3>2. Layout Struk Compact ✓</h3>
            <ul>
                <li>✓ <strong>UKURAN KERTAS</strong> - A4 Landscape dengan margin optimal (10mm 15mm)</li>
                <li>✓ <strong>FONT SIZE</strong> - Disesuaikan agar compact (14px base)</li>
                <li>✓ <strong>DATA TIMBANGAN</strong> - Layout horizontal: BRUTO - TARA = NETTO</li>
                <li>✓ <strong>POTONGAN</strong> - Single line: Potongan (4%): 48.0 Kg | NETTO AKHIR: 1,152.0 Kg</li>
                <li>✓ <strong>HARGA</strong> - Single line: Harga: Rp 4,000/Kg | TOTAL: Rp 4,608,000</li>
                <li>✓ <strong>TOTAL AKHIR 2</strong> - Highlight: TOTAL AKHIR 2: Rp 3,608,000 (setelah potong hutang)</li>
                <li>✓ <strong>SPACING</strong> - Compact dengan gap 5-10px</li>
            </ul>
        </div>

        <div class="success">
            <h3>3. Konsistensi Data ✓</h3>
            <ul>
                <li>✓ Query UPDATE timbangan 2 sudah menyimpan semua field dengan benar</li>
                <li>✓ Struk menggunakan data langsung dari database (konsisten)</li>
                <li>✓ Perhitungan di struk sama dengan perhitungan di timbangan 2</li>
                <li>✓ Format Total Akhir 2 = Total Harga - Potong Hutang</li>
            </ul>
        </div>

        <div class="info">
            <h3>🎯 Format Perhitungan yang Ditampilkan:</h3>
            <ol>
                <li><strong>Timbangan 1</strong> - <strong>Timbangan 2</strong> = <strong>Netto Awal</strong></li>
                <li><strong>Netto Awal</strong> × <strong>Persentase Potongan</strong> = <strong>Kg Potongan</strong></li>
                <li><strong>Netto Awal</strong> - <strong>Kg Potongan</strong> = <strong>Netto Akhir</strong></li>
                <li><strong>Netto Akhir</strong> × <strong>Harga/kg</strong> = <strong>Total Harga</strong></li>
                <li><strong>Total Harga</strong> - <strong>Potong Hutang</strong> = <strong>Total Akhir 2</strong> ✓</li>
            </ol>
        </div>
    </div>

    <div class="test-container">
        <h2>📝 Sample Data Verification:</h2>
        <?php
        $query = "SELECT * FROM transaksi_timbangan WHERE no_tiket = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $no_tiket);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);

        if ($data) {
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Value</th><th>Keterangan</th></tr>";

            $fields = [
                'berat_timbangan1' => 'Timbangan 1 (Bruto)',
                'berat_timbangan2' => 'Timbangan 2 (Tara)',
                'berat_tara' => 'Tara (Tersimpan)',
                'persen_potongan' => 'Persen Potongan (%)',
                'kg_potongan' => 'Potongan (kg)',
                'berat_netto' => 'Netto Akhir (kg)',
                'harga_per_kg' => 'Harga per kg',
                'total_harga' => 'Total Harga',
                'potong_hutang' => 'Potong Hutang'
            ];

            foreach ($fields as $field => $label) {
                $value = $data[$field] ?? 0;
                echo "<tr>";
                echo "<td><strong>{$label}</strong></td>";
                echo "<td>" . number_format($value, 2, ',', '.') . "</td>";
                echo "<td><small>Saved from Timbangan 2</small></td>";
                echo "</tr>";
            }

            // Calculate Total Akhir 2
            $total_harga = $data['total_harga'] ?? 0;
            $potong_hutang = $data['potong_hutang'] ?? 0;
            $total_akhir2 = max(0, $total_harga - $potong_hutang);

            echo "<tr style='background: #e8f5e8; font-weight: bold;'>";
            echo "<td>TOTAL AKHIR 2</td>";
            echo "<td style='color: #059669;'>" . number_format($total_akhir2, 2, ',', '.') . "</td>";
            echo "<td>Total Harga - Potong Hutang</td>";
            echo "</tr>";

            echo "</table>";
        } else {
            echo "<p class='info'>Data tidak ditemukan untuk testing.</p>";
        }
        ?>
    </div>

</body>
</html>