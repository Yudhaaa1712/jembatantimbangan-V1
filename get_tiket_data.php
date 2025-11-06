<?php
// get_tiket_data.php
require_once 'config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(false, 'Unauthorized', null);
}

if (isset($_GET['no_tiket'])) {
    $no_tiket = clean_input($_GET['no_tiket']);

    $stmt = mysqli_prepare($conn, "SELECT * FROM transaksi_timbangan WHERE no_tiket = ? AND status = 'timbangan1'");
    mysqli_stmt_bind_param($stmt, "s", $no_tiket);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        json_response(true, 'Data ditemukan', [
            'no_kendaraan' => $row['no_kendaraan'],
            'nama_pengemudi' => $row['nama_pengemudi'],
            'nama_suplier' => $row['nama_suplier'],
            'material' => $row['material'],
            'harga' => $row['harga'],
            'berat' => $row['berat']
        ]);
    } else {
        json_response(false, 'Tiket tidak ditemukan atau sudah diproses', null);
    }
    mysqli_stmt_close($stmt);
} else {
    json_response(false, 'No tiket not provided', null);
}
?>