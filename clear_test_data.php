<?php
// Clear Test Data - Membersihkan data testing
require_once 'config/database.php';

echo "<h2>Bersihkan Data Testing</h2>\n";

if ($_POST['confirm'] == 'YES') {
    // Delete test transactions (created today for testing)
    $delete_query = "DELETE FROM transaksi_timbangan WHERE DATE(created_at) = CURDATE() AND no_tiket LIKE 'T" . date('Ymd') . "%'";
    $result = mysqli_query($conn, $delete_query);
    $deleted_rows = mysqli_affected_rows($conn);

    echo "<p>✅ Berhasil menghapus <strong>$deleted_rows</strong> data testing transaksi.</p>\n";

    // Clear cache
    if (function_exists('cache_clear_all')) {
        cache_clear_all();
        echo "<p>✅ Cache berhasil dibersihkan.</p>\n";
    }

    echo "<p><a href='test_transaksi.php'>← Kembali ke Test Page</a></p>\n";
} else {
    echo "<form method='POST'>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ PERINGATAN: Ini akan menghapus semua data transaksi testing yang dibuat hari ini!</p>";
    echo "<p>Ketik 'YES' untuk konfirmasi:</p>";
    echo "<input type='text' name='confirm' required>";
    echo "<button type='submit' class='btn btn-danger'>Hapus Data Testing</button>";
    echo "</form>";
}
?>