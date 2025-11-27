<?php
// modules/timbangan/hutang_ajax.php - Supplier Hutang Management API (Backup - not used)
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check login and role
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Handle both GET and POST requests
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'update_hutang':
            $supplier_id = filter_var($_REQUEST['supplier_id'] ?? 0, FILTER_VALIDATE_INT);
            $jumlah_bayar = filter_var($_REQUEST['jumlah_bayar'] ?? 0, FILTER_VALIDATE_FLOAT);
            $keterangan = trim($_REQUEST['keterangan'] ?? '');

            if ($supplier_id <= 0 || $jumlah_bayar <= 0) {
                throw new Exception('Data tidak valid');
            }

            // Get current supplier data
            $check_query = "SELECT total_hutang FROM supplier WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'i', $supplier_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $supplier_data = mysqli_fetch_assoc($check_result);

            if (!$supplier_data) {
                throw new Exception('Supplier tidak ditemukan');
            }

            $current_hutang = floatval($supplier_data['total_hutang'] ?? 0);

            if ($jumlah_bayar > $current_hutang) {
                throw new Exception("Jumlah bayar (Rp " . number_format($jumlah_bayar, 0, ',', '.') . ") melebihi total hutang (Rp " . number_format($current_hutang, 0, ',', '.') . ")");
            }

            $new_hutang = $current_hutang - $jumlah_bayar;

            // Update hutang in database
            $update_query = "UPDATE supplier SET total_hutang = ?, hutang_terakhir_update = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'di', $new_hutang, $supplier_id);

            if (mysqli_stmt_execute($update_stmt)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Hutang berhasil diperbarui!',
                    'data' => [
                        'previous_hutang' => $current_hutang,
                        'jumlah_bayar' => $jumlah_bayar,
                        'new_hutang' => $new_hutang
                    ]
                ]);
            } else {
                throw new Exception('Failed to update hutang: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($update_stmt);
            mysqli_stmt_close($check_stmt);
            break;

        case 'get_supplier_hutang':
            $supplier_id = filter_var($_REQUEST['supplier_id'] ?? 0, FILTER_VALIDATE_INT);

            if ($supplier_id <= 0) {
                throw new Exception('Supplier ID tidak valid');
            }

            $query = "SELECT id, nama_supplier, total_hutang, hutang_terakhir_update FROM supplier WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (!$result || mysqli_num_rows($result) == 0) {
                throw new Exception('Supplier tidak ditemukan');
            }

            $supplier = mysqli_fetch_assoc($result);

            echo json_encode([
                'success' => true,
                'data' => [
                    'supplier_id' => $supplier['id'],
                    'nama_supplier' => $supplier['nama_supplier'],
                    'total_hutang' => floatval($supplier['total_hutang'] ?? 0),
                    'hutang_terakhir_update' => $supplier['hutang_terakhir_update']
                ]
            ]);

            mysqli_stmt_close($stmt);
            break;

        default:
            throw new Exception('Action tidak valid');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>