<?php
// modules/timbangan/timbangan1.php
require_once '../../config/database.php';
require_once '../../includes/material_functions.php';
require_once '../../includes/cache_manager.php';

$page_title = 'Timbangan 1';
require_once '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Log POST data
    error_log("=== PHP POST DEBUG ===");
    error_log("Raw POST data: " . print_r($_POST, true));
    error_log("Material POST: " . var_export($_POST['material'] ?? 'NOT_SET', true));
    error_log("Harga POST: " . var_export($_POST['harga'] ?? 'NOT_SET', true));

    $no_kendaraan = clean_input($_POST['no_kendaraan']);
    $nama_pengemudi = clean_input($_POST['nama_pengemudi']);
    $nama_suplier = clean_input($_POST['nama_suplier']);
    $material = clean_input($_POST['material']);
    $harga = clean_input($_POST['harga']);
    $berat = clean_input($_POST['berat']);
    $keterangan = clean_input($_POST['keterangan'] ?? '');

    // DEBUG: Log setelah cleaning
    error_log("After cleaning - Material: '$material', Harga: '$harga', Berat: '$berat'");

    // Validate required fields
    $validation_errors = [];

    if (empty($no_kendaraan)) {
        $validation_errors[] = "Nomor kendaraan wajib diisi";
    }

    if (empty($nama_pengemudi)) {
        $validation_errors[] = "Nama pengemudi wajib diisi";
    }

    if (empty($nama_suplier)) {
        $validation_errors[] = "Nama supplier wajib dipilih";
    }

    // Material validation with fallback
    if (empty($material)) {
        $material = 'tbs'; // Default material (lowercase)
        error_log("Material was empty, set to default: '$material'");
    }

    // Normalize material to lowercase
    $material = strtolower($material);

    // Validate material is in allowed list
    $allowed_materials = ['tbs', 'brondolan'];
    if (!in_array($material, $allowed_materials)) {
        $material = 'tbs'; // Default to valid material
        error_log("Invalid material '$material', set to default 'tbs'");
    }

    // Harga validation
    if (empty($harga) || !is_numeric($harga) || $harga <= 0) {
        $validation_errors[] = "Harga harus diisi dengan angka yang valid";
    }

    // Berat validation
    if (empty($berat) || !is_numeric($berat) || $berat <= 0) {
        $validation_errors[] = "Berat harus diisi dengan angka yang valid";
    }

    // If there are validation errors, show them and stop
    if (!empty($validation_errors)) {
        $error_message = "Validasi gagal: " . implode(", ", $validation_errors);
        error_log("Validation errors: " . print_r($validation_errors, true));
    } else {
        error_log("✅ Form validation passed. Proceeding with insert...");

        try {
            // Generate nomor tiket yang aman dengan reserved mechanism
            $no_tiket = generate_ticket_number($conn);

            // Get supplier ID first
            $supplier_id = null;
            $supplier_query = "SELECT id FROM supplier WHERE nama_supplier = ?";
            $supplier_stmt = mysqli_prepare($conn, $supplier_query);
            mysqli_stmt_bind_param($supplier_stmt, "s", $nama_suplier);
            mysqli_stmt_execute($supplier_stmt);
            $supplier_result = mysqli_stmt_get_result($supplier_stmt);
            if ($supplier_row = mysqli_fetch_assoc($supplier_result)) {
                $supplier_id = $supplier_row['id'];
            } else {
                $error_message = "Supplier tidak ditemukan: " . $nama_suplier;
                error_log("❌ Supplier not found: $nama_suplier");
            }
            mysqli_stmt_close($supplier_stmt);

            // Only proceed if supplier found
            if ($supplier_id) {
                // Prepare data for activation
                $data = [
                    'no_polisi' => $no_kendaraan,
                    'nama_supir' => $nama_pengemudi,
                    'id_supplier' => $supplier_id,
                    'jenis_material' => $material,
                    'harga_per_kg' => $harga,
                    'berat_bruto' => $berat,
                    'berat_timbangan1' => $berat,
                    'keterangan' => $keterangan,
                    'operator_id' => $_SESSION['user_id']
                ];

                // Activate the reserved ticket with actual data
                if (activate_reserved_ticket($conn, $no_tiket, $data)) {
                    error_log("✅ TICKET ACTIVATION SUCCESS for tiket: $no_tiket");
                    $success_message = "Data timbangan 1 berhasil disimpan dengan nomor tiket: " . $no_tiket;
                } else {
                    // Fallback ke sistem lama jika activation gagal
                    error_log("⚠️ TICKET ACTIVATION FAILED, using fallback for tiket: $no_tiket");

                    // Hapus reserved ticket yang gagal
                    $delete_query = "DELETE FROM transaksi_timbangan WHERE no_tiket = ? AND status = 'reserved'";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "s", $no_tiket);
                    mysqli_stmt_execute($delete_stmt);
                    mysqli_stmt_close($delete_stmt);

                    // Gunakan sistem insert langsung sebagai fallback
                    $fallback_query = "INSERT INTO transaksi_timbangan
                                      (no_tiket, no_polisi, nama_supir, id_supplier, jenis_material,
                                       harga_per_kg, berat_bruto, berat_timbangan1, keterangan,
                                       tanggal, created_at, status, timbang1_locked, waktu_timbangan1, operator_id)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 'timbang_1', 1, NOW(), ?)";

                    $fallback_stmt = mysqli_prepare($conn, $fallback_query);
                    mysqli_stmt_bind_param($fallback_stmt, "sssisdddsi",
                        $no_tiket, $no_kendaraan, $nama_pengemudi, $supplier_id,
                        $material, $harga, $berat, $berat, $keterangan, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($fallback_stmt)) {
                        error_log("✅ FALLBACK INSERT SUCCESS for tiket: $no_tiket");
                        $success_message = "Data timbangan 1 berhasil disimpan dengan nomor tiket: " . $no_tiket . " (fallback mode)";
                    } else {
                        $error_message = "Gagal menyimpan data (fallback juga gagal): " . mysqli_error($conn);
                        error_log("❌ FALLBACK INSERT FAILED for tiket: $no_tiket");
                    }
                    mysqli_stmt_close($fallback_stmt);
                }
            }

        } catch (Exception $e) {
            // Fallback ke sistem generate tiket sederhana jika ada error
            error_log("⚠️ NEW SYSTEM ERROR, using simple fallback: " . $e->getMessage());

            // Generate tiket sederhana tanpa reservation
            $today = date('Y-m-d');
            $date_prefix = date('ymd');
            $simple_query = "SELECT COALESCE(MAX(CAST(SUBSTRING(no_tiket, -3) AS UNSIGNED)), 0) as max_num
                            FROM transaksi_timbangan
                            WHERE tanggal = ? AND no_tiket LIKE 'TKT-{$date_prefix}%'";

            $simple_stmt = mysqli_prepare($conn, $simple_query);
            mysqli_stmt_bind_param($simple_stmt, "s", $today);
            mysqli_stmt_execute($simple_stmt);
            $simple_result = mysqli_stmt_get_result($simple_stmt);
            $simple_row = mysqli_fetch_assoc($simple_result);
            mysqli_stmt_close($simple_stmt);

            $max_num = intval($simple_row['max_num'] ?? 0);
            $number = $max_num + 1;
            $no_tiket = 'TKT-' . $date_prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);

            // Get supplier ID
            $supplier_id = null;
            $supplier_query = "SELECT id FROM supplier WHERE nama_supplier = ?";
            $supplier_stmt = mysqli_prepare($conn, $supplier_query);
            mysqli_stmt_bind_param($supplier_stmt, "s", $nama_suplier);
            mysqli_stmt_execute($supplier_stmt);
            $supplier_result = mysqli_stmt_get_result($supplier_stmt);
            if ($supplier_row = mysqli_fetch_assoc($supplier_result)) {
                $supplier_id = $supplier_row['id'];
            }
            mysqli_stmt_close($supplier_stmt);

            if ($supplier_id) {
                $fallback_query = "INSERT INTO transaksi_timbangan
                                  (no_tiket, no_polisi, nama_supir, id_supplier, jenis_material,
                                   harga_per_kg, berat_bruto, berat_timbangan1, keterangan,
                                   tanggal, created_at, status, timbang1_locked, waktu_timbangan1, operator_id)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 'timbang_1', 1, NOW(), ?)";

                $fallback_stmt = mysqli_prepare($conn, $fallback_query);
                mysqli_stmt_bind_param($fallback_stmt, "sssisdddsi",
                    $no_tiket, $no_kendaraan, $nama_pengemudi, $supplier_id,
                    $material, $harga, $berat, $berat, $keterangan, $_SESSION['user_id']);

                if (mysqli_stmt_execute($fallback_stmt)) {
                    error_log("✅ EMERGENCY FALLBACK SUCCESS for tiket: $no_tiket");
                    $success_message = "Data timbangan 1 berhasil disimpan dengan nomor tiket: " . $no_tiket . " (emergency mode)";
                } else {
                    $error_message = "Gagal menyimpan data: " . mysqli_error($conn);
                    error_log("❌ EMERGENCY FALLBACK FAILED: " . mysqli_error($conn));
                }
                mysqli_stmt_close($fallback_stmt);
            } else {
                $error_message = "Supplier tidak ditemukan dan sistem fallback mengalami error: " . $e->getMessage();
            }
        }
    }
}

// Get data untuk dropdown suplier - with shorter cache time
$cache_key = 'supplier_list_' . date('Y-m-d-H'); // Cache per jam bukan per hari
$suplier_list = cache_get($cache_key);

if ($suplier_list === null) {
    $suplier_list = [];
    $query = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $suplier_list[] = $row;
    }
    cache_set($cache_key, $suplier_list, 300); // Cache for 5 minutes only
}
?>

<div class="container-fluid vh-100 py-2" style="max-height: 100vh; overflow: hidden;">
    <div class="row h-100">
        <div class="col-12">
            <div class="card border-0 bg-dark text-light shadow-lg h-100">
                <div class="card-header bg-gradient border-0 py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-0 text-white">
                               TIMBANGAN 1
                            </h4>
                            <small class="text-light opacity-75">Input Data Awal Timbangan</small>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-danger" id="currentDateTime" style="font-size: 0.9rem;"></div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-2" style="overflow-y: auto; max-height: calc(100vh - 80px);">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show py-1" role="alert">
                            <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 0.8rem;"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-1" role="alert">
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 0.8rem;"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="timbangan1Form">
                        <div class="row g-3">

                            <!-- Timbangan Section -->
                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <!-- Data Kendaraan -->
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">No. Kendaraan</label>
                                                <input type="text" name="no_kendaraan" class="form-control"
                                                       placeholder="BM 1234" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Pengemudi</label>
                                                <input type="text" name="nama_pengemudi" class="form-control"
                                                       placeholder="Nama" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Suplier</label>
                                                <div class="input-group">
                                                    <select name="nama_suplier" class="form-select" required>
                                                        <option value="">Pilih Suplier</option>
                                                        <?php foreach ($suplier_list as $suplier): ?>
                                                            <option value="<?= $suplier['nama_supplier'] ?>"><?= $suplier['nama_supplier'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Material</label>
                                                <select name="material" class="form-select" required>
                                                    <?php echo get_material_options('tbs'); ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Harga & Keterangan -->
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-3">
                                                <label class="form-label">Harga per Kg</label>
                                                <input type="text" name="harga_display" class="form-control rupiah-input"
                                                       id="hargaInput" placeholder="0 (format: 1.000.000)" required>
                                                <input type="hidden" name="harga" id="hargaHidden" value="0">
                                            </div>
                                            <div class="col-md-9">
                                                <label class="form-label">Keterangan</label>
                                                <input type="text" class="form-control"
                                                      name="keterangan" placeholder="Opsional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- DISPLAY PANEL -->
                                        <div class="card h-100">
                                            <label class="form-label">DISPLAY</label>
                                            <div class="display-panel h-75">
                                                <div class="display-value" id="weightDisplay">0 KG</div>
                                                <div class="display-status" id="weightStatus">Menunggu...</div>
                                                <button type="button" class="btn btn-info w-100 mt-3" id="toggleConnection">CONNECT</button>
                                            </div>

                                            <!-- INPUT MANUAL BERAT (Seperti Timbangan 2) -->
                                            <div class="mt-3">
                                                <label class="form-label" style="color: #28a745; font-weight: bold;">INPUT</label>
                                                    <input type="number" class="form-control" name="berat" id="beratInputForm" value="0" step="1" min="0"
                                                        style="background: #28a745; color: #fff; font-size: 24px; font-weight: bold; height: auto;">
                                                <div class="mt-3 d-grid gap-2">
                                                    <button type="button" class="btn btn-success" id="captureWeight">CAPTURE</button>
                                                    <button type="submit" class="btn btn-success">SIMPAN</button>
                                                    <button type="reset" class="btn btn-outline-light">RESET</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Style Sama dengan Timbangan 2 */

/* Container Style */
.container-fluid {
    background: #212529;
    padding: 20px;
    max-height: 100vh;
    overflow-y: auto;
}

/* Box Style - Sama seperti timbangan 2 */
.card {
    background: #000000ff;
    border: none;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.card-header.bg-gradient {
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
}

/* Form Controls - Sama seperti timbangan 2 */
.form-control, .form-select {
    background: #6c757d;
    border: none;
    color: #fff;
    border-radius: 8px;
    font-size: 0.95rem;
    padding: 0.75rem;
    width: 100%;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    outline: none;
}

.form-control::placeholder, .form-select::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.form-control:read-only, .form-select:read-only {
    opacity: 0.7;
    background: #495057;
}

/* Labels - Sama seperti timbangan 2 */
.form-label {
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: block;
}

/* Buttons - Sama seperti timbangan 2 */
.btn {
    background: transparent;
    border: 2px solid #fff;
    color: #fff;
    font-size: 11px;
    padding: 8px 15px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
    letter-spacing: 1px;
    border-radius: 6px;
}

/* Input Group untuk supplier dropdown */
.input-group {
    display: flex;
    align-items: stretch;
}

.input-group .form-select {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    border-right: none;
    flex: 1;
}

.input-group .btn {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    border-left: none;
    min-width: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.input-group .btn:hover {
    z-index: 1;
}

.btn:hover {
    background: #fff;
    color: #343a40;
}

.btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.btn-success {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
}

.btn-success:hover {
    background: #218838;
    border-color: #1e7e34;
}

.btn-info {
    background: #17a2b8;
    border-color: #17a2b8;
    color: #fff;
}

.btn-info:hover {
    background: #138496;
    border-color: #117a8b;
}

.btn-warning {
    background: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.btn-warning:hover {
    background: #e0a800;
    border-color: #d39e00;
}

.btn-outline-light {
    background: transparent;
    border-color: #fff;
    color: #fff;
}

.btn-outline-light:hover {
    background: #fff;
    color: #343a40;
}

/* Display Panel - Sama seperti timbangan 2 */
.display-panel {
    background: #343a40;
    border: none;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Weight Display - Sama seperti timbangan 2 */
#weightDisplay {
    font-size: 48px;
    font-weight: 700;
    color: #dc3545;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    margin: 10px 0;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

#beratInputForm {
    background: #28a745;
    border: none;
    color: #fff;
    border-radius: 8px;
    font-size: 24px;
    font-weight: bold;
    height: auto;
    text-align: center;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

#beratInputForm:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    outline: none;
}

#beratInputForm::-webkit-outer-spin-button,
#beratInputForm::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Card Body */
.card-body {
    padding: 1.5rem !important;
}

/* Alerts - Sama seperti timbangan 2 */
.alert {
    background: #343a40 !important;
    border: none !important;
    color: #fff !important;
    border-radius: 8px;
    font-size: 0.9rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .form-control, .form-select {
        font-size: 0.9rem;
        padding: 0.6rem;
    }

    #weightDisplay {
        font-size: 2rem !important;
    }

    .btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .col-md-3, .col-md-4, .col-md-8 {
        margin-bottom: 1rem;
    }

    #beratInputForm {
        font-size: 1.5rem !important;
    }
}

/* No scrollbars */
html, body {
    overflow: hidden;
}

.container-fluid.vh-100 {
    max-height: 100vh;
    overflow: hidden;
}
</style>

<!-- jQuery -->
<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<!-- SweetAlert2 -->
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>
<!-- Lazy load serial modules only when needed -->
<script>
// Load serial modules dynamically when DOM is ready
function loadSerialModules() {
    // Only load if we have weight display elements
    if (document.getElementById('weightDisplay')) {
        // Load auto-serial-connect first
        const script1 = document.createElement('script');
        script1.src = '<?php echo BASE_URL; ?>js/auto-serial-connect.js';
        script1.async = true;
        document.head.appendChild(script1);

        // Load enhanced web serial API after auto-serial-connect is loaded
        script1.onload = function() {
            const script2 = document.createElement('script');
            script2.src = '<?php echo BASE_URL; ?>assets/js/enhanced-web-serial-api.js';
            script2.async = true;
            document.head.appendChild(script2);

            // Initialize Auto Serial Connector after modules are loaded
            script2.onload = function() {
                setTimeout(function() {
                    initializeAutoSerialConnector().then(success => {
                        // Fallback to Enhanced Web Serial API if auto-connect fails
                        if (!success) {
                            setTimeout(function() {
                                if (!serialConnector || !serialConnector.isConnected) {
                                    initializeEnhancedWebSerialTimbangan1();
                                }
                            }, 500);
                        }
                    });
                }, 100);
            };
        };
    }
}

// Load modules when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadSerialModules);
} else {
    loadSerialModules();
}
</script>

<script>
// Update date and time
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('id-ID', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Material Mapping dari PHP
const materialCodes = <?php echo get_material_js_mapping(); ?>;

// Ensure material is always selected
document.addEventListener('DOMContentLoaded', function() {
    const materialSelect = document.querySelector('select[name="material"]');
    if (materialSelect && !materialSelect.value) {
        materialSelect.value = 'tbs'; // Set default
    }
});

// Format Rupiah - menggunakan global functions
document.getElementById('hargaInput').addEventListener('input', function(e) {
    // Parse current value
    let numValue = parseRupiah(e.target.value);

    // Update hidden field dengan numeric value
    document.getElementById('hargaHidden').value = numValue;
});

document.getElementById('hargaInput').addEventListener('blur', function(e) {
    let numValue = parseRupiah(e.target.value);
    if (numValue > 0) {
        e.target.value = formatRupiahInput(numValue);
    } else {
        e.target.value = '';
    }
    document.getElementById('hargaHidden').value = numValue;
});

document.getElementById('hargaInput').addEventListener('focus', function(e) {
    let numValue = parseRupiah(e.target.value);
    if (numValue > 0) {
        e.target.value = numValue; // Show numeric value for editing
    }
});

// Auto Serial Connector untuk Timbangan 1
let serialConnector = null;
let currentWeight = 0;
let lastWeightUpdate = 0;
let timbangan1Indicator = null;

// Initialize Auto Serial Connector (moved to loadSerialModules)
// document.addEventListener('DOMContentLoaded', async function() {
//     await initializeAutoSerialConnector();
//
//     // Fallback to Enhanced Web Serial API
//     setTimeout(function() {
//         if (!serialConnector || !serialConnector.isConnected) {
//             initializeEnhancedWebSerialTimbangan1();
//         }
//     }, 500);
// });

// Initialize Auto Serial Connector
async function initializeAutoSerialConnector() {
    if (!window.AutoSerialConnector) {
        return false;
    }

    if (!serialConnector) {
        serialConnector = new AutoSerialConnector({
            targetInputId: 'beratInputForm',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                updateConnectionUI(true);
                showNotification('Terhubung ke indikator Sonic A283', 'success');
            },
            onDisconnect: () => {
                updateConnectionUI(false);
            },
            onData: (weight) => {
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayAutoSerial(weight);
            },
            onError: (error) => {
                console.error('Serial Connection Error:', error);
                showNotification('Error koneksi serial: ' + error.message, 'error');
                updateConnectionUI(false);
            }
        });
    }

    // Try auto-connect first
    const autoConnected = await serialConnector.autoConnect();
    if (autoConnected) {
        return true;
    }

    // If auto-connect fails, wait for manual connection
    return false;
}

// Update weight display from Auto Serial Connector
function updateWeightDisplayAutoSerial(weight) {
    const weightDisplay = document.getElementById('weightDisplay');
    const weightStatus = document.getElementById('weightStatus');
    const beratInputForm = document.getElementById('beratInputForm');

    // Update display timbangan
    if (weightDisplay) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked) {
            weightDisplay.innerHTML = `${weight.toLocaleString('id-ID')} Kg<br><small style="color: #ef4444;">(Locked: ${window.capturedWeight.toLocaleString('id-ID')} Kg)</small>`;
            weightDisplay.style.color = '#fbbf24'; // Warna kuning untuk menandakan locked
        } else {
            weightDisplay.textContent = weight.toLocaleString('id-ID') + ' Kg';
        }
    }

    // Update status
    if (weightStatus) {
        if (window.isWeightLocked) {
            weightStatus.textContent = 'Berat terkunci - Timbangan masih aktif';
            weightStatus.className = 'text-warning opacity-75';
        } else if (weight > 0) {
            weightStatus.textContent = 'Data diterima dari Sonic A283';
            weightStatus.className = 'text-success opacity-75';
        } else {
            weightStatus.textContent = 'Terhubung';
            weightStatus.className = 'text-light opacity-75';
        }
    }

    // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
    if (beratInputForm && !window.isWeightLocked) {
        currentWeight = weight;
        lastWeightUpdate = Date.now();
    } else if (beratInputForm && window.isWeightLocked) {
        // Pastikan form input tetap menampilkan berat yang di-lock
        beratInputForm.value = window.capturedWeight;
    }
}

// Initialize Enhanced Web Serial API for Timbangan 1
function initializeEnhancedWebSerialTimbangan1() {
    // Check if Enhanced Web Serial API is available
    if (!window.WeightIndicators) {
        document.getElementById('weightStatus').textContent = 'Enhanced Web Serial API tidak tersedia. Menggunakan fallback.';
        document.getElementById('weightStatus').className = 'text-warning opacity-75';
        updateConnectionUI(false);
        return;
    }

    try {
        // Get or create indicator for timbangan 1
        timbangan1Indicator = window.WeightIndicators.getIndicator('timbangan1');

        // Check if indicator was created successfully
        if (!timbangan1Indicator) {
            throw new Error('Failed to create timbangan1 indicator instance');
        }

        // Set up callbacks using the MultiWeightManager
        window.WeightIndicators.onWeightUpdate(function(indicatorId, weight) {
            if (indicatorId === 'timbangan1') {
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayWebSerial(weight);
            }
        });

        window.WeightIndicators.onConnectionChange(function(indicatorId, connected) {
            if (indicatorId === 'timbangan1') {
                updateConnectionUI(connected);
            }
        });

        window.WeightIndicators.onError(function(indicatorId, error) {
            if (indicatorId === 'timbangan1') {
                console.error('Timbangan 1 Error:', error);
                showNotification('Error Timbangan 1: ' + error, 'error');
                updateConnectionUI(false);
            }
        });

        // Alternative: Set callbacks directly on indicator
        if (typeof timbangan1Indicator.onWeightUpdate === 'function') {
            timbangan1Indicator.onWeightUpdate(function(weight) {
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayWebSerial(weight);
            });

            timbangan1Indicator.onConnectionChange(function(connected) {
                updateConnectionUI(connected);
            });

            timbangan1Indicator.onError(function(error) {
                console.error('Timbangan 1 Direct Error:', error);
                showNotification('Error Timbangan 1: ' + error, 'error');
                updateConnectionUI(false);
            });
        }

        // Initial UI update
        updateConnectionUI(false);

    } catch (error) {
        console.error('Failed to initialize Enhanced Web Serial API for Timbangan 1:', error);
        document.getElementById('weightStatus').textContent = 'Gagal inisialisasi Enhanced Web Serial API';
        document.getElementById('weightStatus').className = 'text-danger opacity-75';
    }
}

// Toggle indicator connection for timbangan 1
document.getElementById('toggleConnection').addEventListener('click', async function() {
    // Try Auto Serial Connector first
    if (serialConnector) {
        if (serialConnector.isConnected) {
            await serialConnector.disconnect();
            updateConnectionUI(false);
            showNotification('Terputus dari indikator Sonic A283', 'info');
        } else {
            const success = await serialConnector.manualConnect();
            if (success) {
                updateConnectionUI(true);
                showNotification('Terhubung ke indikator Sonic A283', 'success');
            } else {
                showNotification('Gagal menghubungkan ke indikator Sonic A283', 'error');
            }
        }
        return;
    }

    // Fallback to Enhanced Web Serial API
    if (!window.WeightIndicators) {
        showNotification('Enhanced Web Serial API tidak tersedia. Menggunakan fallback AJAX', 'warning');
        // Use original AJAX method as fallback
        const toggleBtn = document.getElementById('toggleConnection');
        const currentText = toggleBtn.innerHTML;
        const isConnected = currentText.includes('Disconnect');

        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                action: 'toggle_indicator_connection',
                connect: !isConnected
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateWeightDisplay();
                }
            }
        });
        return;
    }

    try {
        const toggleBtn = document.getElementById('toggleConnection');
        const indicator = window.WeightIndicators.get('timbangan1');

        // Check if indicator exists and has isConnected property
        if (!indicator || typeof indicator.isConnected === 'undefined') {
            showNotification('Error: Indicator tidak tersedia. Silakan refresh halaman.', 'error');
            return;
        }

        const isConnected = indicator.isConnected;

        if (isConnected) {
            // Disconnect
            const success = await indicator.disconnect();
            if (success) {
                updateConnectionUI(false);
                showNotification('Terputus dari indikator timbangan 1', 'info');
            }
        } else {
            // Connect
            const success = await indicator.connect();
            if (success) {
                updateConnectionUI(true);
                showNotification('Terhubung ke indikator timbangan 1', 'success');
            } else {
                showNotification('Gagal menghubungkan ke indikator timbangan 1', 'error');
            }
        }
    } catch (error) {
        console.error('Connection error timbangan 1:', error);
        showNotification('Error koneksi timbangan 1: ' + error.message, 'error');
    }
});

// Update weight display for timbangan 1 via Web Serial API
function updateWeightDisplayWebSerial(weight) {
    const weightDisplay = document.getElementById('weightDisplay');
    const weightStatus = document.getElementById('weightStatus');
    const beratInputForm = document.getElementById('beratInputForm');

    // Update display timbangan
    if (weightDisplay && window.WeightIndicatorUtils) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked) {
            weightDisplay.innerHTML = `${window.WeightIndicatorUtils.formatWeight(weight)}<br><small style="color: #ef4444;">(Locked: ${window.capturedWeight.toLocaleString('id-ID')} Kg)</small>`;
            weightDisplay.style.color = '#fbbf24'; // Warna kuning untuk menandakan locked
        } else {
            weightDisplay.textContent = window.WeightIndicatorUtils.formatWeight(weight);
        }
    }

    // Update status
    if (weightStatus) {
        if (window.isWeightLocked) {
            weightStatus.textContent = 'Berat terkunci - Timbangan masih aktif';
            weightStatus.className = 'text-warning opacity-75';
        } else if (weight > 0) {
            weightStatus.textContent = 'Data diterima dari indikator';
            weightStatus.className = 'text-success opacity-75';
        } else {
            weightStatus.textContent = 'Menunggu data dari indikator...';
            weightStatus.className = 'text-light opacity-75';
        }
    }

    // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
    if (beratInputForm && !window.isWeightLocked) {
        currentWeight = weight;
        lastWeightUpdate = Date.now();
    } else if (beratInputForm && window.isWeightLocked) {
        // Pastikan form input tetap menampilkan berat yang di-lock
        beratInputForm.value = window.capturedWeight;
    }
}

// Update connection UI untuk Timbangan 1
function updateConnectionUI(connected) {
    const weightStatus = document.getElementById('weightStatus');
    const toggleBtn = document.getElementById('toggleConnection');

    if (connected) {
        if (weightStatus) {
            weightStatus.textContent = 'Terhubung ke Sonic A283';
            weightStatus.className = 'text-success opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'Disconnect Indicator';
            toggleBtn.className = 'btn btn-outline-danger btn-sm w-100';
        }
    } else {
        if (weightStatus) {
            weightStatus.textContent = 'Indicator Tidak Terhubung';
            weightStatus.className = 'text-danger opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'Connect Indicator';
            toggleBtn.className = 'btn btn-outline-info btn-sm w-100';
        }
    }
}

// Show notification
function showNotification(message, type) {
    const icon = type === 'success' ? 'success' :
                 type === 'error' ? 'error' :
                 type === 'info' ? 'info' : 'warning';

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

// Update weight display for timbangan 1 (Fallback AJAX)
function updateWeightDisplay() {
    // Check if Enhanced Web Serial API is available and connected
    try {
        if (window.WeightIndicators) {
            const indicator = window.WeightIndicators.get('timbangan1');
            if (indicator && indicator.isConnected) {
                // Data sudah diupdate via callback
                return;
            }
        }
    } catch (error) {
        console.log('Error checking Enhanced Web Serial status in timbangan 1, using fallback:', error);
    }

    const weightDisplay = document.getElementById('weightDisplay');
    const weightStatus = document.getElementById('weightStatus');
    const toggleBtn = document.getElementById('toggleConnection');
    const beratInputForm = document.getElementById('beratInputForm');

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: { action: 'get_indicator_status' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const weight = response.data.weight;

                // Update weight display dengan lock indicator
                if (weightDisplay) {
                    if (window.isWeightLocked) {
                        weightDisplay.innerHTML = `${weight.toLocaleString('id-ID')} Kg<br><small style="color: #ef4444;">(Locked: ${window.capturedWeight.toLocaleString('id-ID')} Kg)</small>`;
                        weightDisplay.style.color = '#fbbf24';
                    } else {
                        weightDisplay.textContent = weight.toLocaleString('id-ID') + ' Kg';
                    }
                }

                // Update connection status
                if (response.data.connected) {
                    if (weightStatus) {
                        if (window.isWeightLocked) {
                            weightStatus.textContent = 'Berat terkunci - Terhubung via Bridge Service';
                            weightStatus.className = 'text-warning opacity-75';
                        } else {
                            weightStatus.textContent = 'Terhubung via Bridge Service';
                            weightStatus.className = 'text-success opacity-75';
                        }
                    }
                    if (toggleBtn) {
                        toggleBtn.innerHTML = 'Disconnect Indicator';
                        toggleBtn.className = 'btn btn-outline-danger btn-sm w-100';
                    }
                } else {
                    if (weightStatus) {
                        weightStatus.textContent = 'Tidak terhubung';
                        weightStatus.className = 'text-danger opacity-75';
                    }
                    if (toggleBtn) {
                        toggleBtn.innerHTML = 'Connect Indicator';
                        toggleBtn.className = 'btn btn-outline-info btn-sm w-100';
                    }
                }

                // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
                if (beratInputForm && !window.isWeightLocked) {
                    currentWeight = weight;
                    lastWeightUpdate = Date.now();
                } else if (beratInputForm && window.isWeightLocked) {
                    // Pastikan form input tetap menampilkan berat yang di-lock
                    beratInputForm.value = window.capturedWeight;
                }
            } else {
                if (weightStatus) {
                    weightStatus.textContent = 'AJAX Error';
                    weightStatus.className = 'text-danger opacity-75';
                }
            }
        }
    });
}

// Simulasi berat timbangan dengan kontrol
let weightInterval = null;
let isCaptured = false;

function updateWeight() {
    updateWeightDisplay();
}

// Start initial weight update
weightInterval = setInterval(updateWeight, 2000);
updateWeight(); // Initial call

// Capture berat timbangan 1
document.getElementById('captureWeight').addEventListener('click', function() {
    const weightDisplay = document.getElementById('weightDisplay');
    const beratInputForm = document.getElementById('beratInputForm');

    if (!weightDisplay || !beratInputForm) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Element tidak ditemukan. Silakan refresh halaman.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Check if connected via Auto Serial Connector or Enhanced Web Serial
    let isConnected = false;
    if (serialConnector && serialConnector.isConnected) {
        isConnected = true;
    } else if (window.WeightIndicators) {
        isConnected = window.WeightIndicators.get('timbangan1') && window.WeightIndicators.get('timbangan1').isConnected;
    }

    if (!isConnected) {
        showNotification('Silakan hubungkan ke indikator terlebih dahulu', 'warning');
        return;
    }

    // Check if we have recent weight data
    const now = Date.now();
    const timeSinceLastUpdate = now - lastWeightUpdate;

    if (timeSinceLastUpdate > 5000) { // 5 seconds
        showNotification('Data timbangan terlalu lama. Pastikan indikator terhubung.', 'warning');
        return;
    }

    const weightValue = currentWeight;

    if (weightValue <= 0) {
        showNotification('Berat tidak valid. Silakan coba lagi.', 'warning');
        return;
    }

    // SIMPAN BERAT YANG SUDAH DI-CAPTURE
    window.capturedWeight = weightValue; // Global variable untuk menyimpan berat yang di-lock

    // Set captured state
    isCaptured = true;
    beratInputForm.value = weightValue;
    beratInputForm.readOnly = true; // FORM INPUT DI-LOCK SEHINGGA TIDAK BISA DIUBAH
    beratInputForm.style.backgroundColor = '#2d3748'; // Visual feedback bahwa form terkunci
    beratInputForm.style.border = '2px solid #22c55e'; // Border hijau menandakan terkunci

    // Visual feedback - stop animation
    weightDisplay.style.animation = 'none';
    weightDisplay.style.color = '#22c55e';
    weightDisplay.style.textShadow = '0 0 20px rgba(34, 197, 94, 0.5)';

    // Visual feedback button
    this.innerHTML = 'BERAT TERKUNCI!';
    this.classList.remove('btn-outline-warning');
    this.classList.add('btn-success');
    this.disabled = true;

    // Stop weight update interval untuk display, tapi tetap terima data untuk monitoring
    if (weightInterval) {
        clearInterval(weightInterval);
        weightInterval = null;
    }

    // Set flag bahwa berat sudah di-lock
    window.isWeightLocked = true;

    // Show success toast
    showNotification(`Berat ${weightValue.toLocaleString('id-ID')} Kg berhasil di-capture dan dikunci!`, 'success');
});

// Reset form
function resetForm() {
    if (confirm('Reset form? Semua data akan dihapus.')) {
        document.getElementById('timbangan1Form').reset();
        document.getElementById('hargaInput').value = 'Rp 0';
        document.getElementById('hargaValue').value = '0';
        document.getElementById('beratInputForm').value = '0';
        document.getElementById('beratInputForm').readOnly = true;

        // RESET FORM INPUT STYLE
        const beratInputForm = document.getElementById('beratInputForm');
        if (beratInputForm) {
            beratInputForm.style.backgroundColor = '';
            beratInputForm.style.border = '';
        }

        // Reset weight display
        const weightDisplay = document.getElementById('weightDisplay');
        if (weightDisplay) {
            weightDisplay.style.animation = 'pulse 2s infinite';
            weightDisplay.style.color = '';
            weightDisplay.style.textShadow = '';
            weightDisplay.innerHTML = '0 Kg'; // Reset dari innerHTML ke textContent
        }

        // Reset capture button
        const captureBtn = document.getElementById('captureWeight');
        if (captureBtn) {
            captureBtn.innerHTML = 'CAPTURE TIMBANG';
            captureBtn.classList.remove('btn-success');
            captureBtn.classList.add('btn-outline-warning');
            captureBtn.disabled = false;
        }

        // CLEAR LOCK VARIABLES
        window.isWeightLocked = false;
        window.capturedWeight = 0;

        // Restart weight updates
        isCaptured = false;
        if (!weightInterval) {
            weightInterval = setInterval(updateWeight, 2000);
        }

        // Initial weight update
        updateWeight();
    }
}

// Form validation
document.getElementById('timbangan1Form').addEventListener('submit', function(e) {
    const beratInputForm = document.getElementById('beratInputForm');

    if (!beratInputForm) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Form element tidak ditemukan. Silakan refresh halaman.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    const berat = beratInputForm.value;

    // Validasi berat harus di-capture (tanpa minimal berat)
    if (berat === '0' || berat === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Silakan capture berat timbangan 1 terlebih dahulu!',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Get form data for validation
    const formData = new FormData(this);
    const material = formData.get('material');
    const harga = formData.get('harga');

    // Validasi material (dengan fallback ke default)
    if (!material || material === '' || material === null) {
        // Set default material ke select field jika kosong
        const materialSelect = document.querySelector('select[name="material"]');
        if (materialSelect) {
            materialSelect.value = 'tbs';
            // Trigger change event untuk update display
            materialSelect.dispatchEvent(new Event('change'));
        }
    }

    // Konfirmasi sebelum simpan
    e.preventDefault();

    // Get form data for confirmation
    const noKendaraan = formData.get('no_kendaraan');
    const namaSuplier = formData.get('nama_suplier');
    const beratFloat = parseFloat(berat);

    Swal.fire({
        title: 'Simpan Data Timbangan 1?',
        html: `
            <div style="text-align: left;">
                <p><strong>No. Kendaraan:</strong> ${noKendaraan}</p>
                <p><strong>Suplier:</strong> ${namaSuplier}</p>
                <p><strong>Material:</strong> ${material}</p>
                <p><strong>Harga:</strong> ${harga}</p>
                <hr>
                <p><strong>Berat Timbangan 1:</strong> <span style="color: #22c55e; font-size: 1.2em;">${beratFloat.toLocaleString('id-ID')} Kg</span></p>
            </div>
            <p class="text-warning mt-3"><em>Data akan disimpan dan dapat dilanjutkan ke Timbangan 2!</em></p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

// Refresh supplier list function
function refreshSupplierList() {
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: { action: 'refresh_supplier_list' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update supplier dropdown
                const select = document.querySelector('select[name="nama_suplier"]');
                const currentValue = select.value;

                // Clear existing options except first one
                select.innerHTML = '<option value="">Pilih Suplier</option>';

                // Add new options
                response.suppliers.forEach(function(supplier) {
                    const option = document.createElement('option');
                    option.value = supplier.nama_supplier;
                    option.textContent = supplier.nama_supplier;
                    select.appendChild(option);
                });

                // Restore previous selection if still exists
                if (currentValue) {
                    select.value = currentValue;
                }

                showNotification('Daftar supplier berhasil diperbarui!', 'success');
            } else {
                showNotification('Gagal memperbarui daftar supplier', 'error');
            }
        },
        error: function() {
            showNotification('Terjadi kesalahan saat memperbarui daftar supplier', 'error');
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>