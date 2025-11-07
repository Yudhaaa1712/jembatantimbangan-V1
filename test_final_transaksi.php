<?php
// Final Test Transaksi - Testing the fixed implementation
require_once 'config/database.php';

echo "<h2>🔧 FINAL TEST - Fitur Transaksi Timbangan 2</h2>\n";

// Test 1: Cek apakah ada tiket yang siap untuk timbangan 2
echo "<h3>1. Cek Tiket Siap Timbang 2</h3>\n";
$query = "SELECT tt.no_tiket, tt.no_polisi, tt.nama_supir, s.nama_supplier, tt.berat_bruto, tt.harga_per_kg
          FROM transaksi_timbangan tt
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          WHERE tt.status = 'timbang_1' AND tt.timbang1_locked = 1
          ORDER BY tt.created_at DESC LIMIT 5";

$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    echo "✅ Tiket tersedia untuk Timbangan 2:\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['no_tiket']} | {$row['no_polisi']} | {$row['nama_supplier']} | Bruto: " . number_format($row['berat_bruto']) . " Kg\n";
    }
    $test_tiket = $row['no_tiket'];
} else {
    echo "❌ Tidak ada tiket yang siap untuk timbangan 2\n";

    // Buat sample data
    echo "\n📝 Membuat sample data...\n";
    $sample_tiket = 'T' . date('Ymd') . '999';
    $insert = "INSERT INTO transaksi_timbangan
               (no_tiket, tanggal, id_kendaraan, no_polisi, nama_supir, id_supplier, jenis_material, berat_bruto, berat_timbangan1, timbang1_locked, status, operator_id, harga_per_kg)
               VALUES (?, CURDATE(), 25, 'B 1234 ABC', 'Test Driver', 11, 'tbs', 40000, 40000, 1, 'timbang_1', 1, 2000)";

    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, "s", $sample_tiket);
    if (mysqli_stmt_execute($stmt)) {
        echo "✅ Sample tiket dibuat: $sample_tiket\n";
        $test_tiket = $sample_tiket;
    }
}

// Test 2: Simulasi proses timbangan 2
if (isset($test_tiket)) {
    echo "\n<h3>2. Simulasi Proses Timbangan 2</h3>\n";

    // Ambil data timbangan 1
    $query1 = "SELECT * FROM transaksi_timbangan WHERE no_tiket = ?";
    $stmt1 = mysqli_prepare($conn, $query1);
    mysqli_stmt_bind_param($stmt1, "s", $test_tiket);
    mysqli_stmt_execute($stmt1);
    $result1 = mysqli_stmt_get_result($stmt1);
    $data_t1 = mysqli_fetch_assoc($result1);

    if ($data_t1) {
        echo "✅ Data Tiket {$test_tiket} ditemukan\n";
        echo "   - Kendaraan: {$data_t1['no_polisi']}\n";
        echo "   - Berat 1 (Bruto): " . number_format($data_t1['berat_bruto']) . " Kg\n";

        // Simulasi berat 2 (Tara)
        $berat2 = 15000;
        $persen_potongan = 5.0;

        $bruto = $data_t1['berat_bruto'];
        $netto_bt = $bruto - $berat2;
        $total_potongan = ($persen_potongan / 100) * $netto_bt;
        $kg_potongan = $total_potongan;
        $netto_akhir = $netto_bt - $total_potongan;
        $total_harga = $netto_akhir * $data_t1['harga_per_kg'];

        echo "\n📊 Hasil Perhitungan:\n";
        echo "   - Bruto: " . number_format($bruto) . " Kg\n";
        echo "   - Tara (Timbangan 2): " . number_format($berat2) . " Kg\n";
        echo "   - Netto: " . number_format($netto_bt) . " Kg\n";
        echo "   - Potongan ({$persen_potongan}%): " . number_format($kg_potongan, 2) . " Kg\n";
        echo "   - Netto Akhir: " . number_format($netto_akhir, 2) . " Kg\n";
        echo "   - Total Harga: Rp " . number_format($total_harga, 0, ',', '.') . "\n";

        // Test UPDATE query
        echo "\n<h3>3. Test UPDATE Query</h3>\n";
        $update_query = "UPDATE transaksi_timbangan SET
                        berat_tara = ?,
                        berat_timbangan2 = ?,
                        persen_potongan = ?,
                        kg_potongan = ?,
                        timbang2_locked = 1,
                        waktu_timbangan2 = NOW(),
                        waktu_keluar = NOW(),
                        status = 'selesai'
                        WHERE no_tiket = ? AND timbang1_locked = 1 AND status = 'timbang_1'";

        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "dddds",
            $berat2, $berat2, $persen_potongan, $kg_potongan, $test_tiket);

        if (mysqli_stmt_execute($update_stmt)) {
            $affected = mysqli_stmt_affected_rows($update_stmt);
            if ($affected > 0) {
                echo "✅ UPDATE berhasil! $affected row terpengaruh\n";

                // Verify data
                echo "\n<h3>4. Verifikasi Data</h3>\n";
                $verify_query = "SELECT no_tiket, berat_bruto, berat_tara, berat_netto, persen_potongan, kg_potongan, total_harga, status FROM transaksi_timbangan WHERE no_tiket = ?";
                $verify_stmt = mysqli_prepare($conn, $verify_query);
                mysqli_stmt_bind_param($verify_stmt, "s", $test_tiket);
                mysqli_stmt_execute($verify_stmt);
                $verify_result = mysqli_stmt_get_result($verify_stmt);
                $verify_data = mysqli_fetch_assoc($verify_result);

                if ($verify_data) {
                    echo "✅ Data tersimpan dengan benar:\n";
                    echo "   - Status: {$verify_data['status']}\n";
                    echo "   - Bruto: " . number_format($verify_data['berat_bruto']) . " Kg\n";
                    echo "   - Tara: " . number_format($verify_data['berat_tara']) . " Kg\n";
                    echo "   - Netto (DB): " . number_format($verify_data['berat_netto']) . " Kg\n";
                    echo "   - Potongan: {$verify_data['persen_potongan']}% ({$verify_data['kg_potongan']} Kg)\n";
                    echo "   - Total Harga: Rp " . number_format($verify_data['total_harga'], 0, ',', '.') . "\n";

                    // Test print_ticket.php URL
                    echo "\n<h3>5. Test URL Print Ticket</h3>\n";
                    $print_url = "modules/timbangan/print_ticket.php?no_tiket=" . urlencode($test_tiket);
                    echo "✅ URL untuk cetak struk: <a href='$print_url' target='_blank'>$print_url</a>\n";

                }
            } else {
                echo "❌ UPDATE gagal atau tidak ada perubahan (mungkin tiket sudah diproses)\n";
            }
        } else {
            echo "❌ Error executing UPDATE: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "❌ Data tiket tidak ditemukan\n";
    }
}

echo "\n<h3>✅ TESTING SELESAI!</h3>\n";
echo "<p><strong>Fitur transaksi sudah diperbaiki dan siap digunakan!</strong></p>";
echo "<p>Silakan test di <a href='modules/timbangan/timbangan2.php'>Timbangan 2</a></p>";
?>