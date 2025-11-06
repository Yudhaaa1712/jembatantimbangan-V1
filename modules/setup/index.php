<?php
require_once '../../config/database.php';

// Cek login
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'modules/auth/login.php');
    exit;
}

// Cek role (hanya admin)
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$page_title = "Setup Portabel - Jembatan Timbangan";
$success_message = '';
$error_message = '';

// Proses form submission
if ($_POST) {
    try {
        // Update konfigurasi Sonic A28E
        HardwareConfig::$sonic_a28e['default_com_port'] = $_POST['com_port'];
        HardwareConfig::$sonic_a28e['baud_rate'] = (int)$_POST['baud_rate'];
        HardwareConfig::$sonic_a28e['timeout'] = (int)$_POST['timeout'];

        // Update konfigurasi bridge server
        HardwareConfig::$bridge_server['port'] = (int)$_POST['bridge_port'];
        HardwareConfig::$bridge_server['polling_interval'] = (int)$_POST['polling_interval'];

        // Update konfigurasi database
        HardwareConfig::$database['host'] = $_POST['db_host'];
        HardwareConfig::$database['username'] = $_POST['db_user'];
        HardwareConfig::$database['password'] = $_POST['db_pass'];
        HardwareConfig::$database['database'] = $_POST['db_name'];

        // Simpan konfigurasi
        HardwareConfig::saveConfigToFile();

        $success_message = "Konfigurasi berhasil disimpan! Sistem siap untuk dipindahkan ke komputer lain.";
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Deteksi port yang tersedia
$available_ports = HardwareConfig::detectAvailablePorts();
$detected_port = HardwareConfig::findSonicA28EPort();

// Test koneksi bridge
$bridge_status = HardwareConfig::testBridgeConnection();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../templates/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Setup Portabel Sistem</h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Status Card -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Status Sistem</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Port Tersedia:</strong><br>
                                            <span class="badge bg-info"><?php echo count($available_ports); ?> port ditemukan</span>
                                            <?php if (!empty($available_ports)): ?>
                                                <small class="text-muted d-block"><?php echo implode(', ', $available_ports); ?></small>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Port Rekomendasi:</strong><br>
                                            <span class="badge bg-success"><?php echo $detected_port; ?></span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Bridge Server Status:</strong><br>
                                            <?php if ($bridge_status): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Terhubung</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Tidak Terhubung</span>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Konfigurasi Portabel:</strong><br>
                                            <?php if (file_exists(__DIR__ . '/../../config/hardware_settings.json')): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation me-1"></i>Belum Aktif</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Setup Form -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Konfigurasi Sonic A28E</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">COM Port</label>
                                        <select class="form-select" name="com_port" required>
                                            <?php foreach ($available_ports as $port): ?>
                                                <option value="<?php echo $port; ?>" <?php echo ($port == HardwareConfig::$sonic_a28e['default_com_port']) ? 'selected' : ''; ?>>
                                                    <?php echo $port; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Port serial untuk komunikasi dengan Sonic A28E</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Baud Rate</label>
                                        <select class="form-select" name="baud_rate" required>
                                            <option value="9600" <?php echo (9600 == HardwareConfig::$sonic_a28e['baud_rate']) ? 'selected' : ''; ?>>9600</option>
                                            <option value="19200" <?php echo (19200 == HardwareConfig::$sonic_a28e['baud_rate']) ? 'selected' : ''; ?>>19200</option>
                                            <option value="38400" <?php echo (38400 == HardwareConfig::$sonic_a28e['baud_rate']) ? 'selected' : ''; ?>>38400</option>
                                            <option value="57600" <?php echo (57600 == HardwareConfig::$sonic_a28e['baud_rate']) ? 'selected' : ''; ?>>57600</option>
                                            <option value="115200" <?php echo (115200 == HardwareConfig::$sonic_a28e['baud_rate']) ? 'selected' : ''; ?>>115200</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Timeout (detik)</label>
                                        <input type="number" class="form-control" name="timeout"
                                               value="<?php echo HardwareConfig::$sonic_a28e['timeout']; ?>" min="1" max="10" required>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>Konfigurasi Bridge Server</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Bridge Server Port</label>
                                        <input type="number" class="form-control" name="bridge_port"
                                               value="<?php echo HardwareConfig::$bridge_server['port']; ?>" min="1000" max="9999" required>
                                        <small class="text-muted">Port untuk server bridge Flask</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Polling Interval (detik)</label>
                                        <input type="number" class="form-control" name="polling_interval"
                                               value="<?php echo HardwareConfig::$bridge_server['polling_interval']; ?>" min="1" max="10" required>
                                        <small class="text-muted">Interval pembacaan data timbangan</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Konfigurasi Database</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Database Host</label>
                                        <input type="text" class="form-control" name="db_host"
                                               value="<?php echo HardwareConfig::$database['host']; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Database Username</label>
                                        <input type="text" class="form-control" name="db_user"
                                               value="<?php echo HardwareConfig::$database['username']; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Database Password</label>
                                        <input type="password" class="form-control" name="db_pass"
                                               value="<?php echo HardwareConfig::$database['password']; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Database Name</label>
                                        <input type="text" class="form-control" name="db_name"
                                               value="<?php echo HardwareConfig::$database['database']; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="card">
                                <div class="card-body">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Simpan Konfigurasi
                                    </button>
                                    <a href="generate_portable.php" class="btn btn-success">
                                        <i class="fas fa-download me-2"></i>Buat Paket Portabel
                                    </a>
                                    <a href="../admin/index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Kembali
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Panduan Setup Portabel</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-1 me-2"></i>Di Komputer Saat Ini:</h6>
                                        <ol>
                                            <li>Hubungkan indikator Sonic A28E ke komputer</li>
                                            <li>Buka halaman setup ini untuk mendeteksi port otomatis</li>
                                            <li>Adjust konfigurasi sesuai kebutuhan</li>
                                            <li>Klik "Simpan Konfigurasi"</li>
                                            <li>Klik "Buat Paket Portabel" untuk mendownload</li>
                                        </ol>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-2 me-2"></i>Di Komputer Baru:</h6>
                                        <ol>
                                            <li>Install XAMPP dan Python 3.7+</li>
                                            <li>Extract paket portabel ke C:/xampp/htdocs/</li>
                                            <li>Jalankan file <code>auto_setup.bat</code></li>
                                            <li>Setup akan otomatis mendeteksi port dan konfigurasi</li>
                                            <li>Sistem siap digunakan tanpa setup manual lagi</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>