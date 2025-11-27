<?php
// config/database.php
// Database Configuration - PORTABLE

// Load hardware configuration
require_once __DIR__ . '/hardware_config.php';

// Use hardware configuration if available, otherwise use defaults
$hardware_config = HardwareConfig::$database;

// Database credentials
define('DB_HOST', $hardware_config['host']);
define('DB_USER', $hardware_config['username']);
define('DB_PASS', $hardware_config['password']);
define('DB_NAME', $hardware_config['database']);

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Session configuration with error handling
if (session_status() == PHP_SESSION_NONE) {
    // Set custom session path with error handling
    $session_path = __DIR__ . '/../sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0755, true);
    }

    // Set session save path
    session_save_path($session_path);

    // Configure session settings
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    ini_set('session.cookie_lifetime', 7200); // 2 hours
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start failed: " . $e->getMessage());
        // Fallback to default session handling
        if (!headers_sent()) {
            ini_set('session.save_path', '');
            session_start();
        }
    }
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Base URL (sesuaikan dengan instalasi Anda)
define('BASE_URL', 'http://localhost/jembatantimbangan/');

// Function: Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function: Check user role
function check_role($allowed_roles = []) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'modules/auth/login.php');
        exit;
    }

    // Allow all roles if empty (for development/demo)
    if (empty($allowed_roles)) {
        return;
    }

    // For now, allow admin access to everything
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
        return;
    }

    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        // For development: show which roles are allowed
        $allowed_text = implode(', ', $allowed_roles);
        $current_role = $_SESSION['user_role'] ?? 'unknown';
        echo "<div style='padding: 20px; background: #fee; border: 2px solid #fcc; border-radius: 5px; margin: 20px;'>";
        echo "<h3>Access Denied</h3>";
        echo "<p>Your role: <strong>$current_role</strong></p>";
        echo "<p>Allowed roles: <strong>$allowed_text</strong></p>";
        echo "<p><a href='" . BASE_URL . "modules/timbangan/timbangan1.php'>← Back to Dashboard</a></p>";
        echo "</div>";
        exit;
    }
}

// Function: Generate ticket number (Thread-safe with retry mechanism)
function generate_ticket_number($conn) {
    global $conn;
    $max_retries = 5;
    $retry_count = 0;

    while ($retry_count < $max_retries) {
        try {
            // Start transaction for atomic ticket generation
            mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

            // Get prefix from settings
            $query = "SELECT setting_value FROM settings WHERE setting_key = 'ticket_prefix' FOR UPDATE";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_assoc($result);
            $prefix = $row['setting_value'] ?? 'TKT';

            $today = date('Y-m-d');
            $date_prefix = date('ymd');

            // Use atomic approach with proper locking and pessimistic locking
            $query = "SELECT COALESCE(MAX(CAST(SUBSTRING(no_tiket, -3) AS UNSIGNED)), 0) as max_num
                      FROM transaksi_timbangan
                      WHERE tanggal = ? AND no_tiket LIKE ?
                      FOR UPDATE";

            $stmt = mysqli_prepare($conn, $query);
            $pattern = $prefix . '-' . $date_prefix . '%';
            mysqli_stmt_bind_param($stmt, "ss", $today, $pattern);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            $max_num = intval($row['max_num'] ?? 0);
            $number = $max_num + 1;

            // Format: TKT-YYMMDD-XXX
            $ticket_number = $prefix . '-' . $date_prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);

            // Pre-reserve the ticket number to prevent duplication
            $reserve_query = "INSERT INTO transaksi_timbangan
                              (no_tiket, tanggal, status, created_at, timbang1_locked)
                              VALUES (?, ?, 'reserved', NOW(), 0)";

            $reserve_stmt = mysqli_prepare($conn, $reserve_query);
            mysqli_stmt_bind_param($reserve_stmt, "ss", $ticket_number, $today);

            if (mysqli_stmt_execute($reserve_stmt)) {
                // Successfully reserved, commit transaction
                mysqli_commit($conn);
                mysqli_stmt_close($reserve_stmt);

                // Log successful generation
                error_log("Ticket generated successfully: $ticket_number on attempt " . ($retry_count + 1));
                return $ticket_number;
            } else {
                // Failed to reserve, rollback and retry
                mysqli_rollback($conn);
                mysqli_stmt_close($reserve_stmt);
                $retry_count++;

                if ($retry_count < $max_retries) {
                    // Add random delay to avoid race conditions
                    usleep(rand(10000, 100000)); // 10ms to 100ms
                    continue;
                }
            }

        } catch (Exception $e) {
            // Rollback on any error and retry
            mysqli_rollback($conn);
            $retry_count++;

            if ($retry_count < $max_retries) {
                // Add random delay to avoid race conditions
                usleep(rand(10000, 100000)); // 10ms to 100ms
                continue;
            }

            error_log("Failed to generate ticket after $max_retries attempts: " . $e->getMessage());
            throw new Exception("Failed to generate unique ticket number after $max_retries attempts");
        }
    }

    // If we get here, all retries failed
    throw new Exception("Failed to generate unique ticket number after $max_retries attempts. Please try again.");
}

// Function: Check if ticket exists and remove reservation if needed
function is_ticket_exists($conn, $no_tiket) {
    $query = "SELECT id, status FROM transaksi_timbangan WHERE no_tiket = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $no_tiket);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // If it's a reserved ticket, allow reuse
        if ($row['status'] === 'reserved') {
            return false;
        }

        return true;
    }

    mysqli_stmt_close($stmt);
    return false;
}

// Function: Activate reserved ticket (FIXED VERSION)
function activate_reserved_ticket($conn, $no_tiket, $data) {
    // Validate required data
    $required_fields = ['no_polisi', 'nama_supir', 'id_supplier', 'jenis_material',
                       'harga_per_kg', 'berat_bruto', 'berat_timbangan1',
                       'keterangan', 'operator_id'];

    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    mysqli_begin_transaction($conn);

    try {
        // Simplified approach: Update step by step for debugging
        error_log("Activating ticket: $no_tiket with data: " . json_encode($data));

        // Step 1: Update basic fields first
        $basic_query = "UPDATE transaksi_timbangan SET
                        no_polisi = ?,
                        nama_supir = ?,
                        id_supplier = ?,
                        updated_at = NOW()
                        WHERE no_tiket = ? AND status = 'reserved'";

        $basic_stmt = mysqli_prepare($conn, $basic_query);
        mysqli_stmt_bind_param($basic_stmt, "ssis",
            $data['no_polisi'],
            $data['nama_supir'],
            $data['id_supplier'],
            $no_tiket
        );

        if (!mysqli_stmt_execute($basic_stmt)) {
            throw new Exception("Failed to update basic fields: " . mysqli_stmt_error($basic_stmt));
        }
        $basic_affected = mysqli_stmt_affected_rows($basic_stmt);
        mysqli_stmt_close($basic_stmt);

        error_log("Basic update affected rows: $basic_affected");

        if ($basic_affected == 0) {
            throw new Exception("No reserved ticket found to update");
        }

        // Step 2: Update material and keterangan
        $detail_query = "UPDATE transaksi_timbangan SET
                          jenis_material = ?,
                          keterangan = ?,
                          harga_per_kg = ?,
                          berat_bruto = ?,
                          berat_timbangan1 = ?
                          WHERE no_tiket = ?";

        $detail_stmt = mysqli_prepare($conn, $detail_query);
        mysqli_stmt_bind_param($detail_stmt, "ssddds",
            $data['jenis_material'],
            $data['keterangan'],
            $data['harga_per_kg'],
            $data['berat_bruto'],
            $data['berat_timbangan1'],
            $no_tiket
        );

        if (!mysqli_stmt_execute($detail_stmt)) {
            throw new Exception("Failed to update detail fields: " . mysqli_stmt_error($detail_stmt));
        }
        mysqli_stmt_close($detail_stmt);

        error_log("Detail update completed");

        // Step 3: Update status and lock
        $status_query = "UPDATE transaksi_timbangan SET
                          status = 'timbang_1',
                          timbang1_locked = 1,
                          waktu_timbangan1 = NOW(),
                          operator_id = ?
                          WHERE no_tiket = ?";

        $status_stmt = mysqli_prepare($conn, $status_query);
        mysqli_stmt_bind_param($status_stmt, "is", $data['operator_id'], $no_tiket);

        if (!mysqli_stmt_execute($status_stmt)) {
            throw new Exception("Failed to update status: " . mysqli_stmt_error($status_stmt));
        }
        mysqli_stmt_close($status_stmt);

        error_log("Status update completed");

        mysqli_commit($conn);
        error_log("Ticket activation successful: $no_tiket");
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Ticket activation failed: " . $e->getMessage());
        throw $e;
    }
}

// Function: Sanitize input
function clean_input($data) {
    global $conn;
    // Handle null values
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Function: JSON response
function json_response($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Function: Set indicator connection mode
function set_indicator_connection($connected = true) {
    $_SESSION['indicator_connected'] = $connected;
}

// Function: Get indicator connection status
function is_indicator_connected() {
    return isset($_SESSION['indicator_connected']) ? $_SESSION['indicator_connected'] : false;
}

// Function: Get current weight from scale (dari bridge service)
function get_current_weight() {
    // Cek status koneksi indikator
    if (!is_indicator_connected()) {
        // Jika indikator tidak terhubung, coba konek ke bridge service
        $bridge_url = 'http://127.0.0.1:5001/get_weight';

        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents($bridge_url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                set_indicator_connection(true);
                return $data['weight'];
            }
        }

        // Jika bridge tidak tersedia, gunakan simulasi lokal
        return get_simulated_weight();
    }

    // Ambil data dari bridge service
    $bridge_url = 'http://127.0.0.1:5001/get_weight';

    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'method' => 'GET'
        ]
    ]);

    $response = @file_get_contents($bridge_url, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            return $data['weight'];
        }
    }

    // Fallback ke simulasi jika bridge error
    return get_simulated_weight();
}

// Function untuk simulasi weight lokal (fallback)
function get_simulated_weight() {
    // Simulasi realistis dengan beberapa mode
    static $weight_mode = 'stable'; // stable, fluctuate, realistic
    static $base_weights = [2500, 3000, 4500, 8000, 12000, 15000, 20000, 25000];
    static $current_weight = 5000;
    static $tick_count = 0;

    $tick_count++;

    switch ($weight_mode) {
        case 'stable':
            // Mode stabil - tidak berubah (untuk testing)
            if (!isset($_SESSION['dummy_weight'])) {
                $_SESSION['dummy_weight'] = 10000; // Fixed 10 ton
            }
            return $_SESSION['dummy_weight'];

        case 'fluctuate':
            // Mode fluktuasi kecil (simulasi nyata)
            if (!isset($_SESSION['dummy_weight'])) {
                $_SESSION['dummy_weight'] = $base_weights[array_rand($base_weights)];
            }

            // Fluktuasi ± 50 kg
            $fluctuation = rand(-50, 50);
            $new_weight = $_SESSION['dummy_weight'] + $fluctuation;

            // Validasi range maksimum saja (minimal berat dihapus)
            if ($new_weight > 50000) $new_weight = 50000;

            $_SESSION['dummy_weight'] = $new_weight;
            return $new_weight;

        case 'realistic':
            // Mode realistis dengan variasi
            if (!isset($_SESSION['dummy_weight'])) {
                $_SESSION['dummy_weight'] = $base_weights[array_rand($base_weights)];
                $_SESSION['weight_direction'] = rand(0, 1) ? 1 : -1;
                $_SESSION['last_change'] = time();
            }

            // Berubah setiap 3-7 detik
            if (time() - $_SESSION['last_change'] > rand(3, 7)) {
                $change = rand(100, 500) * $_SESSION['weight_direction'];
                $_SESSION['dummy_weight'] += $change;

                // Reverse direction terkadang
                if (rand(0, 10) > 7) {
                    $_SESSION['weight_direction'] *= -1;
                }

                $_SESSION['last_change'] = time();
            }

            // Validasi range maksimum saja (minimal berat dihapus)
            if ($_SESSION['dummy_weight'] > 50000) {
                $_SESSION['dummy_weight'] = 50000;
                $_SESSION['weight_direction'] = -1;
            }
            // Tidak ada validasi minimal - berat bisa 0 atau negatif untuk simulasi

            return $_SESSION['dummy_weight'];

        default:
            return $_SESSION['dummy_weight'] ?? 10000;
    }
}

// Function untuk mengatur mode dummy indicator
function set_weight_mode($mode) {
    $_SESSION['weight_mode'] = $mode;
    unset($_SESSION['dummy_weight']); // Reset weight
}

// Function untuk menentukan bobot manual
function set_manual_weight($weight) {
    $_SESSION['dummy_weight'] = (int)$weight;
}

// Function: Format number Indonesian style
function format_number($number, $decimals = 0) {
    if ($decimals == 0) {
        return (string)$number; // Plain number without formatting
    }
    return number_format($number, $decimals, '.', ',');
}

// format_weight function sudah ada di ajax.php, tidak perlu duplikat

// Function: Format rupiah (Indonesian format)
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Function: Clean rupiah value to number
function clean_rupiah_value($rupiah_string) {
    // Remove all non-digit characters
    return (int)preg_replace('/[^0-9]/', '', $rupiah_string);
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>