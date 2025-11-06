<?php
// modules/timbangan/ajax.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

header('Content-Type: application/json');

// Function get_current_weight sudah ada di database.php
// Function get_current_weight_timbangan2 sudah ada di database.php

function format_weight($weight) {
    return number_format($weight, 0, ',', '.');
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'clear_tiket_cache':
        // Clear cache for tiket data
        require_once '../../includes/cache_manager.php';

        $cache_keys = json_decode($_POST['cache_keys'] ?? '[]');
        $cleared_count = 0;

        if (is_array($cache_keys)) {
            foreach ($cache_keys as $key) {
                if (cache_delete($key)) {
                    $cleared_count++;
                }
            }
        }

        // Also clean up expired cache files
        $cache_manager = CacheManager::getInstance();
        $cache_manager->cleanup();

        json_response(true, "Cache cleared successfully ($cleared_count keys)");
        break;

    case 'get_pending_tickets':
        // Ambil semua tiket yang menunggu proses timbangan 2
        $query = "SELECT tt.no_tiket, tt.berat_timbangan1, tt.berat_bruto, tt.no_polisi, tt.nama_supir,
                        tt.jenis_material, tt.harga_per_kg, s.nama_supplier
                 FROM transaksi_timbangan tt
                 LEFT JOIN supplier s ON tt.id_supplier = s.id
                 WHERE tt.status = 'timbang_1'
                 ORDER BY tt.created_at DESC";

        $result = mysqli_query($conn, $query);
        $tickets = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $tickets[] = [
                'no_tiket' => $row['no_tiket'],
                'berat_bruto' => $row['berat_timbangan1'] ?? $row['berat_bruto'],
                'no_polisi' => $row['no_polisi'],
                'nama_supir' => $row['nama_supir'],
                'nama_supplier' => $row['nama_supplier'] ?: 'Unknown',
                'jenis_material' => $row['jenis_material'],
                'harga_per_kg' => $row['harga_per_kg']
            ];
        }

        json_response(true, 'Pending tickets retrieved', $tickets);
        break;
        
    case 'get_kendaraan':
        $id = clean_input($_POST['id']);

        // Use prepared statement
        $stmt = $conn->prepare("SELECT * FROM kendaraan WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            json_response(true, 'Data found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;
        
    case 'search_kendaraan':
        $keyword = clean_input($_POST['keyword']);

        // Use prepared statement to prevent SQL injection
        $keyword_like = "%{$keyword}%";
        $stmt = $conn->prepare("SELECT * FROM kendaraan
                               WHERE (no_polisi LIKE ? OR nama_supir LIKE ?)
                               AND status = 'active'
                               LIMIT 10");
        $stmt->bind_param("ss", $keyword_like, $keyword_like);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        json_response(true, 'Search completed', $data);
        break;

    case 'save_timbangan1':
        $no_tiket = clean_input($_POST['no_tiket']);
        $no_do = clean_input($_POST['no_do']);
        $nama_supir = clean_input($_POST['nama_supir']);
        $id_kendaraan = clean_input($_POST['id_kendaraan']);
        $id_supplier = clean_input($_POST['id_supplier']);
        $jenis_material = clean_input($_POST['jenis_material']);
        $berat_timbangan1 = clean_input($_POST['berat_timbangan1']);

        // Get vehicle data
        $query = "SELECT no_polisi FROM kendaraan WHERE id = '$id_kendaraan'";
        $result = mysqli_query($conn, $query);
        $kendaraan = mysqli_fetch_assoc($result);

        // Insert or update transaction
        $query = "INSERT INTO transaksi_timbangan
            (no_tiket, no_do, nama_supir, id_kendaraan, no_polisi, id_supplier,
             jenis_material, berat_timbangan1, timbang1_locked, waktu_timbangan1, tanggal,
             status, operator_id, created_at)
            VALUES
            ('$no_tiket', '$no_do', '$nama_supir', '$id_kendaraan', '{$kendaraan['no_polisi']}',
             '$id_supplier', '$jenis_material', '$berat_timbangan1', 1, NOW(),
             CURDATE(), 'timbang_1', '{$_SESSION['user_id']}', NOW())
            ON DUPLICATE KEY UPDATE
            no_do = '$no_do',
            nama_supir = '$nama_supir',
            id_kendaraan = '$id_kendaraan',
            no_polisi = '{$kendaraan['no_polisi']}',
            id_supplier = '$id_supplier',
            jenis_material = '$jenis_material',
            berat_timbangan1 = '$berat_timbangan1',
            timbang1_locked = 1,
            waktu_timbangan1 = NOW(),
            status = 'timbang_1',
            operator_id = '{$_SESSION['user_id']}',
            updated_at = NOW()";

        if (mysqli_query($conn, $query)) {
            json_response(true, 'Data Timbangan 1 berhasil disimpan');
        } else {
            json_response(false, 'Gagal menyimpan data: ' . mysqli_error($conn));
        }
        break;

    case 'save_timbangan2':
        $id_transaksi = clean_input($_POST['id_transaksi']);
        $id_customer = clean_input($_POST['id_customer']);
        $berat_timbangan2 = clean_input($_POST['berat_timbangan2']);
        $persen_potongan = clean_input($_POST['persen_potongan']);
        $kg_potongan = clean_input($_POST['kg_potongan']);
        $harga_per_kg = clean_input($_POST['harga_per_kg']);

        // Get transaction data for calculation
        $query = "SELECT berat_timbangan1 FROM transaksi_timbangan WHERE id = '$id_transaksi'";
        $result = mysqli_query($conn, $query);
        $transaksi = mysqli_fetch_assoc($result);

        $berat_t1 = $transaksi['berat_timbangan1'];
        $netto = $berat_t1 - $berat_timbangan2;
        $total_potongan = ($netto * $persen_potongan / 100) + $kg_potongan;
        $netto_akhir = $netto - $total_potongan;
        $total_harga = $netto_akhir * $harga_per_kg;

        // Update transaction (tanpa update kolom generated)
        $query = "UPDATE transaksi_timbangan SET
            id_customer = '$id_customer',
            berat_timbangan2 = '$berat_timbangan2',
            timbang2_locked = 1,
            persen_potongan = '$persen_potongan',
            kg_potongan = '$kg_potongan',
            harga_per_kg = '$harga_per_kg',
            waktu_timbangan2 = NOW(),
            waktu_keluar = NOW(),
            status = 'selesai',
            updated_at = NOW()
            WHERE id = '$id_transaksi'";

        if (mysqli_query($conn, $query)) {
            json_response(true, 'Data Timbangan 2 berhasil disimpan');
        } else {
            json_response(false, 'Gagal menyimpan data: ' . mysqli_error($conn));
        }
        break;

    case 'get_recent_timbangan1':
        $query = "SELECT tt.*, k.no_polisi, s.nama_supplier
                  FROM transaksi_timbangan tt
                  LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
                  LEFT JOIN supplier s ON tt.id_supplier = s.id
                  WHERE tt.status = 'timbang_1'
                  ORDER BY tt.waktu_timbangan1 DESC
                  LIMIT 10";
        $result = mysqli_query($conn, $query);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        json_response(true, 'Data retrieved', $data);
        break;

    case 'get_all_transactions':
        $date = clean_input($_POST['date']);
        $status = clean_input($_POST['status']);
        $material = clean_input($_POST['material']);
        $search = clean_input($_POST['search']);

        $where_conditions = [];
        if ($date) $where_conditions[] = "tt.tanggal = '$date'";
        if ($status) $where_conditions[] = "tt.status = '$status'";
        if ($material) $where_conditions[] = "tt.jenis_material = '$material'";
        if ($search) $where_conditions[] = "(tt.no_tiket LIKE '%$search%' OR tt.no_polisi LIKE '%$search%')";

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT tt.*,
                   k.no_polisi,
                   s.nama_supplier,
                   c.nama_customer,
                   u.nama_lengkap as nama_operator
                  FROM transaksi_timbangan tt
                  LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
                  LEFT JOIN supplier s ON tt.id_supplier = s.id
                  LEFT JOIN customer c ON tt.id_customer = c.id
                  LEFT JOIN users u ON tt.operator_id = u.id
                  $where_clause
                  ORDER BY tt.created_at DESC
                  LIMIT 100";

        $result = mysqli_query($conn, $query);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        // Get summary
        $summary_query = "SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'timbang_1' THEN 1 ELSE 0 END) as t1_count,
                            SUM(CASE WHEN status = 'timbang_2' THEN 1 ELSE 0 END) as t2_count,
                            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai_count
                          FROM transaksi_timbangan tt
                          $where_clause";

        $summary_result = mysqli_query($conn, $summary_query);
        $summary = mysqli_fetch_assoc($summary_result);

        json_response(true, 'Data retrieved', ['data' => $data, 'summary' => $summary]);
        break;

    case 'get_transaction_detail':
        $id = clean_input($_POST['id']);

        $query = "SELECT tt.*,
                   k.no_polisi,
                   s.nama_supplier,
                   c.nama_customer,
                   u.nama_lengkap as nama_operator
                  FROM transaksi_timbangan tt
                  LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
                  LEFT JOIN supplier s ON tt.id_supplier = s.id
                  LEFT JOIN customer c ON tt.id_customer = c.id
                  LEFT JOIN users u ON tt.operator_id = u.id
                  WHERE tt.id = '$id'";

        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            json_response(true, 'Detail found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;

    case 'delete_timbangan1':
        $id = clean_input($_POST['id']);

        // Check authentication first
        if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
            json_response(false, 'Akses ditolak');
            break;
        }

        // Validate ID
        if (!is_numeric($id) || $id <= 0) {
            json_response(false, 'ID tidak valid');
            break;
        }

        // Use prepared statement to check if data is locked
        $stmt = $conn->prepare("SELECT timbang1_locked FROM transaksi_timbangan WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            json_response(false, 'Data tidak ditemukan');
            break;
        }

        $data = $result->fetch_assoc();

        if ($data['timbang1_locked']) {
            json_response(false, 'Data yang sudah di-lock tidak bisa dihapus');
            break;
        }

        // Delete the record using prepared statement
        $stmt = $conn->prepare("DELETE FROM transaksi_timbangan WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Log deletion
            error_log("User {$_SESSION['username']} deleted transaction ID: $id");
            json_response(true, 'Data berhasil dihapus');
        } else {
            json_response(false, 'Gagal menghapus data: ' . $stmt->error);
        }
        break;
        
    case 'get_tara_avg':
        $id = clean_input($_POST['id_kendaraan']);
        $query = "SELECT tara_avg FROM kendaraan WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            json_response(true, 'Tara found', [
                'tara_avg' => $row['tara_avg']
            ]);
        } else {
            json_response(false, 'Kendaraan not found');
        }
        break;
        
    case 'check_ticket':
        $no_tiket = clean_input($_POST['no_tiket']);
        $query = "SELECT * FROM transaksi_timbangan WHERE no_tiket = '$no_tiket'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            json_response(false, 'Nomor tiket sudah digunakan!');
        } else {
            json_response(true, 'Nomor tiket tersedia');
        }
        break;

    case 'get_ticket_data':
        $no_tiket = clean_input($_POST['no_tiket']);

        // Simple query without FOR UPDATE
        $query = "SELECT tt.*, k.no_polisi, s.nama_supplier
                  FROM transaksi_timbangan tt
                  LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
                  LEFT JOIN supplier s ON tt.id_supplier = s.id
                  WHERE tt.no_tiket = ? AND tt.status = 'timbang_1'";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $no_tiket);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);

            // Get weight data
            $weight_timbangan1 = floatval($data['berat_timbangan1'] ?? $data['berat_bruto'] ?? 0);

            // Validate weight integrity
            if ($weight_timbangan1 <= 0) {
                json_response(false, 'Data weight tidak valid! Tiket mungkin bermasalah.');
                break;
            }

            // Add validated weight to response
            $data['validated_weight_timbangan1'] = $weight_timbangan1;
            $data['weight_source'] = empty($data['berat_timbangan1']) ? 'berat_bruto' : 'berat_timbangan1';
            $data['capture_time'] = $data['waktu_masuk'];
            $data['is_locked'] = true;

            json_response(true, 'Data tiket ditemukan', $data);
        } else {
            json_response(false, 'Tiket tidak ditemukan atau sudah selesai');
        }
        break;
        
    case 'get_transaksi':
        $id = clean_input($_POST['id']);
        $query = "SELECT * FROM view_transaksi_lengkap WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            json_response(true, 'Data found', $data);
        } else {
            json_response(false, 'Data not found');
        }
        break;
        
    case 'get_stats_today':
        $today = date('Y-m-d');
        $query = "SELECT 
                    COUNT(*) as total_transaksi,
                    SUM(berat_netto) as total_netto,
                    AVG(berat_netto) as avg_netto
                  FROM transaksi_timbangan 
                  WHERE tanggal = '$today' AND status = 'selesai'";
        $result = mysqli_query($conn, $query);
        $stats = mysqli_fetch_assoc($result);
        
        json_response(true, 'Stats retrieved', $stats);
        break;
        
    case 'toggle_indicator_connection':
        $connect = $_POST['connect'] === 'true';
        set_indicator_connection($connect);
        json_response(true, 'Indicator connection ' . ($connect ? 'enabled' : 'disabled'));
        break;

    case 'get_indicator_status':
        // Cek koneksi ke bridge service (Alternative Sonic A28E Bridge)
        $bridge_url = 'http://127.0.0.1:5001/status';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents($bridge_url, false, $context);
        $bridge_data = null;

        if ($response !== false) {
            $bridge_data = json_decode($response, true);
        }

        if ($bridge_data && isset($bridge_data['connected'])) {
            set_indicator_connection($bridge_data['connected']);
            $weight = $bridge_data['current_weight'] ?? 0;
        } else {
            // Jika bridge tidak tersedia, cek status koneksi indikator
            if (is_indicator_connected()) {
                $weight = get_current_weight();
            } else {
                // Jika tidak terhubung ke indikator, berat = 0
                $weight = 0;
            }
        }

        json_response(true, 'Status retrieved', [
            'connected' => is_indicator_connected(),
            'weight' => $weight,
            'bridge_available' => $bridge_data !== null,
            'bridge_info' => $bridge_data ? [
                'server' => $bridge_data['server'] ?? 'Unknown',
                'version' => $bridge_data['version'] ?? 'Unknown',
                'com_port' => $bridge_data['com_port'] ?? 'Unknown',
                'mode' => $bridge_data['mode'] ?? 'Unknown'
            ] : null
        ]);
        break;

    case 'connect_indicator':
    $indicator_id = clean_input($_POST['indicator_id'] ?? 'timbangan1');
    $port = clean_input($_POST['port'] ?? '');

    if (empty($port)) {
        json_response(false, 'Port tidak boleh kosong');
        break;
    }

    // Cek koneksi ke bridge service
    $bridge_url = 'http://127.0.0.1:5001/api/connect';
    $post_data = json_encode([
        'indicator_id' => $indicator_id,
        'port' => $port
    ]);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $post_data
        ]
    ]);

    $response = @file_get_contents($bridge_url, false, $context);

    if ($response !== false) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            set_indicator_connection(true);
            json_response(true, "Berhasil menghubungkan ke indikator $indicator_id via $port", [
                'connected' => true,
                'port' => $port
            ]);
        } else {
            json_response(false, 'Gagal menghubungkan ke indikator: ' . ($result['error'] ?? 'Unknown error'));
        }
    } else {
        json_response(false, 'Bridge service tidak tersedia. Pastikan Python bridge sudah berjalan.');
    }
    break;

case 'disconnect_indicator':
    $indicator_id = clean_input($_POST['indicator_id'] ?? 'timbangan1');

    // Cek koneksi ke bridge service
    $bridge_url = 'http://127.0.0.1:5001/api/disconnect';
    $post_data = json_encode([
        'indicator_id' => $indicator_id
    ]);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $post_data
        ]
    ]);

    $response = @file_get_contents($bridge_url, false, $context);

    if ($response !== false) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            set_indicator_connection(false);
            json_response(true, "Berputus dari indikator $indicator_id", [
                'connected' => false
            ]);
        } else {
            json_response(false, 'Gagal memutus koneksi: ' . ($result['error'] ?? 'Unknown error'));
        }
    } else {
        json_response(false, 'Bridge service tidak tersedia');
    }
    break;

case 'get_bridge_ports':
    // Get available ports from bridge service
    $bridge_url = 'http://127.0.0.1:5001/api/ports';
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'method' => 'GET'
        ]
    ]);

    $response = @file_get_contents($bridge_url, false, $context);

    if ($response !== false) {
        $result = json_decode($response, true);
        if ($result && isset($result['ports'])) {
            json_response(true, 'Ports retrieved', [
                'ports' => $result['ports'],
                'bridge_available' => true
            ]);
        } else {
            json_response(false, 'Failed to get ports from bridge');
        }
    } else {
        // Fallback: check for standard COM ports
        $ports = [];
        for ($i = 1; $i <= 20; $i++) {
            $port = "COM$i";
            if (@file_exists("\\.\\" . $port)) {
                $ports[] = [
                    'device' => $port,
                    'description' => 'Serial Port ' . $i
                ];
            }
        }

        json_response(true, 'Ports retrieved (fallback)', [
            'ports' => $ports,
            'bridge_available' => false
        ]);
    }
    break;

case 'get_bridge_status':
    // Get status from bridge service
    $bridge_url = 'http://127.0.0.1:5001/api/status';
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'method' => 'GET'
        ]
    ]);

    $response = @file_get_contents($bridge_url, false, $context);

    if ($response !== false) {
        $result = json_decode($response, true);
        if ($result && isset($result['status'])) {
            json_response(true, 'Bridge status retrieved', [
                'bridge_available' => true,
                'status' => $result['status']
            ]);
        } else {
            json_response(false, 'Invalid response from bridge');
        }
    } else {
        json_response(true, 'Bridge not available', [
            'bridge_available' => false,
            'status' => []
        ]);
    }
    break;

default:
        json_response(false, 'Invalid action');
}
?>