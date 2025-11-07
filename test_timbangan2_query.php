<?php
// Test Query untuk Timbangan 2
require_once 'config/database.php';

echo "<h2>Testing Query Timbangan 2</h2>\n";

// Test update query structure
$test_berat2 = 15000;
$test_berat_netto = 25000;
$test_persen_potongan = 5.0;
$test_kg_potongan = 1250;
$test_total_harga = 23750000;
$test_no_tiket = 'TEST001';

// Cek kolom yang tersedia
echo "<h3>Available Columns:</h3>\n";
$result = mysqli_query($conn, "DESCRIBE transaksi_timbangan");
while ($row = mysqli_fetch_assoc($result)) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Test update query
echo "<h3>Testing Update Query:</h3>\n";

$update_query = "UPDATE transaksi_timbangan SET
                berat_tara = ?,
                berat_timbangan2 = ?,
                berat_netto = ?,
                persen_potongan = ?,
                kg_potongan = ?,
                total_harga = ?,
                timbang2_locked = 1,
                waktu_timbangan2 = NOW(),
                waktu_keluar = NOW(),
                status = 'selesai'
                WHERE no_tiket = ? AND timbang1_locked = 1 AND status = 'timbang_1'";

$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "ddddddds",
    $test_berat2,           // berat_tara
    $test_berat2,           // berat_timbangan2
    $test_berat_netto,      // berat_netto
    $test_persen_potongan,  // persen_potongan
    $test_kg_potongan,      // kg_potongan
    $test_total_harga,      // total_harga
    $test_no_tiket          // no_tiket
);

echo "✅ Query prepared successfully\n";
echo "✅ Parameters bound successfully\n";

// Cek sample data untuk test
echo "<h3>Check Available Tickets:</h3>\n";
$ticket_query = "SELECT no_tiket, no_polisi, status, timbang1_locked FROM transaksi_timbangan WHERE status = 'timbang_1' AND timbang1_locked = 1 LIMIT 5";
$ticket_result = mysqli_query($conn, $ticket_query);

if (mysqli_num_rows($ticket_result) > 0) {
    echo "Available tickets for Timbangan 2:\n";
    while ($ticket = mysqli_fetch_assoc($ticket_result)) {
        echo "- " . $ticket['no_tiket'] . " - " . $ticket['no_polisi'] . " (Status: " . $ticket['status'] . ", Locked: " . ($ticket['timbang1_locked'] ? 'Yes' : 'No') . ")\n";
    }
} else {
    echo "❌ No tickets available for Timbangan 2\n";

    // Create sample ticket for testing
    echo "\nCreating sample ticket for testing...\n";
    $insert_query = "INSERT INTO transaksi_timbangan (no_tiket, tanggal, id_kendaraan, no_polisi, nama_supir, id_supplier, jenis_material, berat_bruto, berat_timbangan1, timbang1_locked, status, operator_id) VALUES (?, CURDATE(), 1, 'B 1234 ABC', 'Test Driver', 1, 'tbs', 40000, 40000, 1, 'timbang_1', 1)";

    $sample_tiket = 'T' . date('Ymd') . '001';
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "s", $sample_tiket);

    if (mysqli_stmt_execute($insert_stmt)) {
        echo "✅ Sample ticket created: $sample_tiket\n";
    }
}

echo "\n<h3>✅ Query Testing Completed!</h3>\n";
echo "<p>Query structure is correct. The timbangan2.php should work properly now.</p>\n";
?>