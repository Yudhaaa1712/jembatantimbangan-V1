<?php
// fix_database_existing.php
// Fix data transaksi yang sudah ada di database agar sesuai dengan perhitungan JavaScript

require_once 'config/database.php';

echo "<h2>🔧 FIX DATABASE - Data Transaksi Existing</h2>";

// Ambil semua transaksi dengan status 'selesai'
$query = "SELECT id, no_tiket, berat_bruto, berat_tara, persen_potongan, harga_per_kg, kg_potongan, berat_netto, total_harga
          FROM transaksi_timbangan
          WHERE status = 'selesai'
          ORDER BY updated_at DESC";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    echo "<p>Tidak ada transaksi dengan status 'selesai' yang perlu di-fix.</p>";
    exit;
}

echo "<p>Ditemukan " . mysqli_num_rows($result) . " transaksi yang akan di-fix...</p>";

$fixed_count = 0;
$error_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    echo "<h3>Memperbaiki: " . $row['no_tiket'] . "</h3>";

    // Hitung ulang dengan formula JavaScript (YANG BENAR)
    $bruto = $row['berat_bruto'];
    $tara = $row['berat_tara'];
    $persenPotongan = $row['persen_potongan'];
    $harga = $row['harga_per_kg'];

    // Formula JavaScript
    $netto = $bruto - $tara;
    $potonganKg = ($persenPotongan / 100) * $netto;
    $nettoAkhir = $netto - $potonganKg;
    $totalHarga = $nettoAkhir * $harga;

    echo "<table border='1' cellpadding='3' style='border-collapse: collapse; font-size: 12px;'>";
    echo "<tr><th colspan='3'>Perhitungan JavaScript (YANG BENAR)</th></tr>";
    echo "<tr><td>Bruto</td><td>" . number_format($bruto) . "</td><td rowspan='6'></td></tr>";
    echo "<tr><td>Tara</td><td>" . number_format($tara) . "</td></tr>";
    echo "<tr><td>Netto</td><td>" . number_format($netto) . "</td></tr>";
    echo "<tr><td>Potongan " . $persenPotongan . "%</td><td>" . number_format($potonganKg, 2) . " kg</td></tr>";
    echo "<tr><td><strong>Netto Akhir</strong></td><td><strong>" . number_format($nettoAkhir, 2) . "</strong></td></tr>";
    echo "<tr><td><strong>Total Harga</strong></td><td><strong>" . number_format($totalHarga) . "</strong></td></tr>";
    echo "</table>";

    echo "<table border='1' cellpadding='3' style='border-collapse: collapse; font-size: 12px;'>";
    echo "<tr><th colspan='3'>Database Saat Ini</th></tr>";
    echo "<tr><td>berat_netto</td><td>" . number_format($row['berat_netto'], 2) . "</td><td>" . (abs($row['berat_netto'] - $nettoAkhir) < 0.01 ? "✅" : "❌") . "</td></tr>";
    echo "<tr><td>total_harga</td><td>" . number_format($row['total_harga']) . "</td><td>" . ($row['total_harga'] == $totalHarga ? "✅" : "❌") . "</td></tr>";
    echo "</table>";

    // Update jika ada perbedaan
    if (abs($row['berat_netto'] - $nettoAkhir) >= 0.01 || $row['total_harga'] != $totalHarga) {
        $update_query = "UPDATE transaksi_timbangan SET
                        kg_potongan = ?,
                        berat_netto = ?,
                        total_harga = ?
                        WHERE id = ?";

        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "dddi",
            $potonganKg,
            $nettoAkhir,
            $totalHarga,
            $row['id']
        );

        if (mysqli_stmt_execute($update_stmt)) {
            echo "<p style='color: green;'>✅ BERHASIL di-update!</p>";
            $fixed_count++;
        } else {
            echo "<p style='color: red;'>❌ GAGAL di-update: " . mysqli_error($conn) . "</p>";
            $error_count++;
        }
        mysqli_stmt_close($update_stmt);
    } else {
        echo "<p style='color: blue;'>ℹ️ Data sudah benar, tidak perlu di-update.</p>";
    }

    echo "<hr>";
}

echo "<h2>📊 Hasil Akhir:</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Jumlah</th></tr>";
echo "<tr><td>✅ Berhasil di-fix</td><td><strong>" . $fixed_count . "</strong></td></tr>";
echo "<tr><td>❌ Gagal di-fix</td><td><strong>" . $error_count . "</strong></td></tr>";
echo "<tr><td>📊 Total Diproses</td><td><strong>" . ($fixed_count + $error_count) . "</strong></td></tr>";
echo "</table>";

if ($fixed_count > 0) {
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
    echo "<strong>✅ " . $fixed_count . " transaksi berhasil di-fix!</strong><br>";
    echo "Sekarang data di database sudah SAMA dengan perhitungan JavaScript (HASIL PERHITUNGAN OTOMATIS).";
    echo "</div>";
}

if ($error_count > 0) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
    echo "<strong>❌ " . $error_count . " transaksi gagal di-fix!</strong><br>";
    echo "Harap cek log error di atas.";
    echo "</div>";
}

echo "<h3>🎯 Verifikasi:</h3>";
echo "<p>Setelah fix ini, test transaksi baru di timbangan 2 untuk memastikan:</p>";
echo "<ol>";
echo "<li>Hasil perhitungan otomatis di layar (JavaScript)</li>";
echo "<li>Data yang tersimpan di database</li>";
echo "<li>Data yang dicetak di struk</li>";
echo "</ol>";
echo "<p><strong>Semua harus SAMA PERSIS! 🎯</strong></p>";
?>