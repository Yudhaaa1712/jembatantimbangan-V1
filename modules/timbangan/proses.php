<?php
// modules/timbangan/proses.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

$action = $_POST['action'] ?? '';

if ($action == 'save') {
    // Validate and sanitize input
    $no_tiket = trim($_POST['no_tiket'] ?? '');
    $nama_supir = trim($_POST['nama_supir'] ?? '');
    $id_kendaraan = filter_var($_POST['id_kendaraan'] ?? 0, FILTER_VALIDATE_INT);
    $id_supplier = filter_var($_POST['id_supplier'] ?? 0, FILTER_VALIDATE_INT);
    $jenis_material = trim($_POST['jenis_material'] ?? '');
    $berat_bruto = filter_var($_POST['berat_bruto'] ?? 0, FILTER_VALIDATE_FLOAT);
    $berat_tara = filter_var($_POST['berat_tara'] ?? 0, FILTER_VALIDATE_FLOAT);
    $harga_per_kg = filter_var($_POST['harga_per_kg'] ?? 0, FILTER_VALIDATE_FLOAT);
    $keterangan = trim($_POST['keterangan'] ?? '');

    $tanggal = date('Y-m-d');
    $waktu_masuk = date('H:i:s');
    $waktu_keluar = date('H:i:s');
    $operator_id = $_SESSION['user_id'];

    // Enhanced validation
    if (empty($no_tiket) || empty($nama_supir) || !$id_kendaraan || !$id_supplier || empty($jenis_material)) {
        json_response(false, 'Mohon lengkapi semua field yang wajib diisi!');
    }

    if ($berat_bruto <= 0 || $berat_tara <= 0) {
        json_response(false, 'Berat bruto dan tara harus lebih dari 0!');
    }

    if ($berat_tara >= $berat_bruto) {
        json_response(false, 'Berat tara tidak boleh lebih besar dari bruto!');
    }

    // Validate material type
    $valid_materials = ['tbs', 'cpo', 'kernel', 'brondolan', 'lainnya'];
    if (!in_array($jenis_material, $valid_materials)) {
        json_response(false, 'Jenis material tidak valid!');
    }

    // Get no_polisi from kendaraan using prepared statement
    $query = "SELECT no_polisi FROM kendaraan WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_kendaraan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) == 0) {
        json_response(false, 'Kendaraan tidak ditemukan!');
    }

    $kendaraan = mysqli_fetch_assoc($result);
    $no_polisi = $kendaraan['no_polisi'];

    // Check if ticket already exists using new safe function
    if (is_ticket_exists($conn, $no_tiket)) {
        json_response(false, 'Nomor tiket sudah digunakan!');
    }
    
    // Hitung nilai timbangan
    $berat_timbangan1 = $berat_bruto;
    $berat_timbangan2 = $berat_tara;

    // Insert data using prepared statement
    $query = "INSERT INTO transaksi_timbangan (
                no_tiket, tanggal, waktu_masuk, waktu_keluar,
                id_kendaraan, no_polisi, nama_supir,
                id_supplier, jenis_material,
                berat_bruto, berat_tara,
                berat_timbangan1, berat_timbangan2,
                harga_per_kg, keterangan,
                status, operator_id
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssisssdddddssii",
        $no_tiket, $tanggal, $waktu_masuk, $waktu_keluar,
        $id_kendaraan, $no_polisi, $nama_supir,
        $id_supplier, $jenis_material,
        $berat_bruto, $berat_tara,
        $berat_timbangan1, $berat_timbangan2,
        $harga_per_kg, $keterangan,
        $status, $operator_id
    );

    $status = 'selesai';

    if (mysqli_stmt_execute($stmt)) {
        $insert_id = mysqli_insert_id($conn);

        // Update tara average kendaraan using prepared statement
        $query_update_tara = "UPDATE kendaraan
                              SET tara_avg = (
                                  SELECT AVG(berat_tara)
                                  FROM transaksi_timbangan
                                  WHERE id_kendaraan = ?
                                  AND status = 'selesai'
                              )
                              WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $query_update_tara);
        mysqli_stmt_bind_param($update_stmt, "ii", $id_kendaraan, $id_kendaraan);
        mysqli_stmt_execute($update_stmt);

        json_response(true, 'Transaksi berhasil disimpan dengan nomor tiket: ' . $no_tiket, [
            'id' => $insert_id,
            'no_tiket' => $no_tiket,
            'berat_timbangan1' => $berat_timbangan1,
            'berat_timbangan2' => $berat_timbangan2
        ]);
    } else {
        $error_msg = mysqli_error($conn);
        error_log("Database error in proses.php: " . $error_msg);
        json_response(false, 'Gagal menyimpan data: ' . $error_msg);
    }
    
} elseif ($action == 'update') {
    // Update transaksi
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $nama_supir = trim($_POST['nama_supir'] ?? '');
    $id_supplier = filter_var($_POST['id_supplier'] ?? 0, FILTER_VALIDATE_INT);
    $jenis_material = trim($_POST['jenis_material'] ?? '');
    $harga_per_kg = filter_var($_POST['harga_per_kg'] ?? 0, FILTER_VALIDATE_FLOAT);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $potong_hutang = filter_var($_POST['potong_hutang'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Validate inputs
    if (!$id || empty($nama_supir) || !$id_supplier || empty($jenis_material)) {
        json_response(false, 'Data tidak lengkap!');
    }

    // Validate material type
    $valid_materials = ['tbs', 'cpo', 'kernel', 'brondolan', 'lainnya'];
    if (!in_array($jenis_material, $valid_materials)) {
        json_response(false, 'Jenis material tidak valid!');
    }

    // Validate potong hutang
    if ($potong_hutang < 0) {
        json_response(false, 'Potong hutang tidak boleh negatif!');
    }

    // Get supplier data for validation
    $supplier_query = "SELECT total_hutang FROM supplier WHERE id = ?";
    $supplier_stmt = mysqli_prepare($conn, $supplier_query);
    mysqli_stmt_bind_param($supplier_stmt, 'i', $id_supplier);
    mysqli_stmt_execute($supplier_stmt);
    $supplier_result = mysqli_stmt_get_result($supplier_stmt);
    $supplier_data = mysqli_fetch_assoc($supplier_result);

    if (!$supplier_data) {
        json_response(false, 'Supplier tidak ditemukan!');
    }

    $total_hutang_supplier = floatval($supplier_data['total_hutang'] ?? 0);

    // Validate potong hutang tidak melebihi total hutang supplier
    if ($potong_hutang > $total_hutang_supplier) {
        json_response(false, 'Potong hutang (Rp ' . number_format($potong_hutang, 0, ',', '.') . ') melebihi total hutang supplier (Rp ' . number_format($total_hutang_supplier, 0, ',', '.') . ')');
    }

    $sisa_hutang = max(0, $total_hutang_supplier - $potong_hutang);

    // Update using prepared statement
    $query = "UPDATE transaksi_timbangan SET
                nama_supir = ?,
                id_supplier = ?,
                jenis_material = ?,
                harga_per_kg = ?,
                keterangan = ?,
                potong_hutang = ?,
                sisa_hutang = ?
              WHERE id = ?";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sisdisddi", $nama_supir, $id_supplier, $jenis_material, $harga_per_kg, $keterangan, $potong_hutang, $sisa_hutang, $id);

    mysqli_stmt_close($supplier_stmt);

    if (mysqli_stmt_execute($stmt)) {
        // Update hutang supplier jika ada potong hutang
        if ($potong_hutang > 0) {
            require_once '../../../includes/masterdata_handler.php';
            $supplier_handler = new SupplierHandler($conn);

            try {
                $update_result = $supplier_handler->updateHutang($id_supplier, $potong_hutang, 'Potong hutang dari transaksi #' . $id);
                error_log("HUTANG UPDATE: Supplier ID $id_supplier, Potong: Rp $potong_hutang, Sisa: Rp " . $update_result['new_hutang']);
            } catch (Exception $e) {
                error_log("Gagal update hutang supplier: " . $e->getMessage());
                json_response(false, 'Transaksi berhasil tapi gagal update hutang supplier: ' . $e->getMessage());
            }
        }

        json_response(true, 'Data berhasil diupdate!');
    } else {
        json_response(false, 'Gagal update data: ' . mysqli_error($conn));
    }

} elseif ($action == 'delete') {
    // Delete transaksi
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(false, 'ID tidak valid!');
    }

    // Check if can delete (only admin or within 24 hours)
    $query = "SELECT tanggal, operator_id FROM transaksi_timbangan WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) == 0) {
        json_response(false, 'Data tidak ditemukan!');
    }

    $row = mysqli_fetch_assoc($result);

    $can_delete = false;
    if ($_SESSION['user_role'] == 'admin') {
        $can_delete = true;
    } elseif ($row['operator_id'] == $_SESSION['user_id']) {
        $date_diff = strtotime('now') - strtotime($row['tanggal'] ?? date('Y-m-d'));
        if ($date_diff < 86400) { // 24 hours
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        json_response(false, 'Anda tidak memiliki izin untuk menghapus transaksi ini!');
    }

    // Delete using prepared statement
    $query = "DELETE FROM transaksi_timbangan WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($delete_stmt, "i", $id);

    if (mysqli_stmt_execute($delete_stmt)) {
        json_response(true, 'Data berhasil dihapus!');
    } else {
        json_response(false, 'Gagal menghapus data: ' . mysqli_error($conn));
    }
    
} else {
    json_response(false, 'Invalid action');
}
?>