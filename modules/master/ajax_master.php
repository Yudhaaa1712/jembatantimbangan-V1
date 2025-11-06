<?php
// modules/master/ajax_master.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

switch ($action) {
    // ==================== KENDARAAN ====================
    case 'get_kendaraan':
        $id = clean_input($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM kendaraan WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            json_response(true, 'Data found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;

    case 'save_kendaraan':
        $no_polisi = clean_input($_POST['no_polisi']);
        $nama_supir = clean_input($_POST['nama_supir']);
        $jenis_kendaraan = clean_input($_POST['jenis_kendaraan']);
        $kapasitas = clean_input($_POST['kapasitas']);
        $tara_avg = clean_input($_POST['tara_avg']);
        $keterangan = clean_input($_POST['keterangan'] ?? '');

        // Validation
        if (empty($no_polisi)) {
            json_response(false, 'Nomor polisi wajib diisi');
            break;
        }

        // Check if no_polisi already exists
        $stmt = $conn->prepare("SELECT id FROM kendaraan WHERE no_polisi = ? AND id != ? LIMIT 1");
        $id = clean_input($_POST['id'] ?? '');
        $stmt->bind_param("ss", $no_polisi, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            json_response(false, 'Nomor polisi sudah ada');
            break;
        }

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE kendaraan SET
                no_polisi = ?, nama_supir = ?, jenis_kendaraan = ?,
                kapasitas = ?, tara_avg = ?, keterangan = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->bind_param("sssisss", $no_polisi, $nama_supir, $jenis_kendaraan,
                               $kapasitas, $tara_avg, $keterangan, $id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO kendaraan
                (no_polisi, nama_supir, jenis_kendaraan, kapasitas, tara_avg, keterangan, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("sssis", $no_polisi, $nama_supir, $jenis_kendaraan,
                               $kapasitas, $tara_avg, $keterangan);
        }

        if ($stmt->execute()) {
            json_response(true, $id ? 'Data berhasil diupdate' : 'Data berhasil disimpan');
        } else {
            json_response(false, 'Gagal menyimpan data: ' . $stmt->error);
        }
        break;

    case 'delete_kendaraan':
        $id = clean_input($_POST['id']);

        // Check if kendaraan is used in transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_kendaraan = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            json_response(false, 'Kendaraan tidak dapat dihapus karena sudah digunakan dalam transaksi');
            break;
        }

        // Soft delete
        $stmt = $conn->prepare("UPDATE kendaraan SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute()) {
            json_response(true, 'Data berhasil dihapus');
        } else {
            json_response(false, 'Gagal menghapus data: ' . $stmt->error);
        }
        break;

    // ==================== SUPPLIER ====================
    case 'get_supplier':
        $id = clean_input($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM supplier WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            json_response(true, 'Data found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;

    case 'save_supplier':
        $nama_supplier = clean_input($_POST['nama_supplier']);
        $alamat = clean_input($_POST['alamat'] ?? '');
        $telepon = clean_input($_POST['telepon'] ?? '');
        $kontak = clean_input($_POST['kontak'] ?? '');
        $keterangan = clean_input($_POST['keterangan'] ?? '');

        if (empty($nama_supplier)) {
            json_response(false, 'Nama supplier wajib diisi');
            break;
        }

        $id = clean_input($_POST['id'] ?? '');

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE supplier SET
                nama_supplier = ?, alamat = ?, telepon = ?, kontak = ?, keterangan = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->bind_param("ssssss", $nama_supplier, $alamat, $telepon, $kontak, $keterangan, $id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO supplier
                (nama_supplier, alamat, telepon, kontak, keterangan, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("sssss", $nama_supplier, $alamat, $telepon, $kontak, $keterangan);
        }

        if ($stmt->execute()) {
            json_response(true, $id ? 'Data berhasil diupdate' : 'Data berhasil disimpan');
        } else {
            json_response(false, 'Gagal menyimpan data: ' . $stmt->error);
        }
        break;

    case 'delete_supplier':
        $id = clean_input($_POST['id']);

        // Check if supplier is used in transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_supplier = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            json_response(false, 'Supplier tidak dapat dihapus karena sudah digunakan dalam transaksi');
            break;
        }

        $stmt = $conn->prepare("UPDATE supplier SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute()) {
            json_response(true, 'Data berhasil dihapus');
        } else {
            json_response(false, 'Gagal menghapus data: ' . $stmt->error);
        }
        break;

    // ==================== CUSTOMER ====================
    case 'get_customer':
        $id = clean_input($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM customer WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            json_response(true, 'Data found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;

    case 'save_customer':
        $nama_customer = clean_input($_POST['nama_customer']);
        $alamat = clean_input($_POST['alamat'] ?? '');
        $telepon = clean_input($_POST['telepon'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $kontak = clean_input($_POST['kontak'] ?? '');
        $keterangan = clean_input($_POST['keterangan'] ?? '');

        if (empty($nama_customer)) {
            json_response(false, 'Nama customer wajib diisi');
            break;
        }

        $id = clean_input($_POST['id'] ?? '');

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE customer SET
                nama_customer = ?, alamat = ?, telepon = ?, email = ?, kontak = ?, keterangan = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->bind_param("sssssss", $nama_customer, $alamat, $telepon, $email, $kontak, $keterangan, $id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO customer
                (nama_customer, alamat, telepon, email, kontak, keterangan, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("ssssss", $nama_customer, $alamat, $telepon, $email, $kontak, $keterangan);
        }

        if ($stmt->execute()) {
            json_response(true, $id ? 'Data berhasil diupdate' : 'Data berhasil disimpan');
        } else {
            json_response(false, 'Gagal menyimpan data: ' . $stmt->error);
        }
        break;

    case 'delete_customer':
        $id = clean_input($_POST['id']);

        // Check if customer is used in transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_customer = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            json_response(false, 'Customer tidak dapat dihapus karena sudah digunakan dalam transaksi');
            break;
        }

        $stmt = $conn->prepare("UPDATE customer SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute()) {
            json_response(true, 'Data berhasil dihapus');
        } else {
            json_response(false, 'Gagal menghapus data: ' . $stmt->error);
        }
        break;

    // ==================== SEARCH ====================
    case 'search_kendaraan':
        $keyword = clean_input($_POST['keyword'] ?? '');
        $keyword_like = "%{$keyword}%";

        $stmt = $conn->prepare("SELECT id, no_polisi, nama_supir FROM kendaraan
                               WHERE (no_polisi LIKE ? OR nama_supir LIKE ?)
                               AND status = 'active'
                               LIMIT 10");
        $stmt->bind_param("ss", $keyword_like, $keyword_like);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        json_response(true, 'Search completed', $data);
        break;

    case 'search_supplier':
        $keyword = clean_input($_POST['keyword'] ?? '');
        $keyword_like = "%{$keyword}%";

        $stmt = $conn->prepare("SELECT id, nama_supplier FROM supplier
                               WHERE nama_supplier LIKE ? AND status = 'active'
                               LIMIT 10");
        $stmt->bind_param("s", $keyword_like);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        json_response(true, 'Search completed', $data);
        break;

    case 'search_customer':
        $keyword = clean_input($_POST['keyword'] ?? '');
        $keyword_like = "%{$keyword}%";

        $stmt = $conn->prepare("SELECT id, nama_customer FROM customer
                               WHERE nama_customer LIKE ? AND status = 'active'
                               LIMIT 10");
        $stmt->bind_param("s", $keyword_like);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        json_response(true, 'Search completed', $data);
        break;

    default:
        json_response(false, 'Invalid action');
}
?>