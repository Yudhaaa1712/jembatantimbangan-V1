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
        $material = 'TBS'; // Default material
        error_log("Material was empty, set to default: '$material'");
    }

    // Validate material is in allowed list
    $allowed_materials = ['TBS','brondolan'];
    if (!in_array($material, $allowed_materials)) {
        $material = 'TBS'; // Default to valid material
        error_log("Invalid material '$material', set to default 'TBS'");
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

        // Generate nomor tiket
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
            // Insert data ke database
            $query = "INSERT INTO transaksi_timbangan
                      (no_tiket, no_polisi, nama_supir, id_supplier, jenis_material, harga_per_kg, berat_bruto, berat_timbangan1, tanggal, created_at, status, timbang1_locked, waktu_timbangan1)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 'timbang_1', 1, NOW())";

            // DEBUG: Log query parameters
            error_log("=== QUERY DEBUG ===");
            error_log("Query: " . $query);
            error_log("Parameters: no_tiket=$no_tiket, no_polisi=$no_kendaraan, nama_supir=$nama_pengemudi, supplier_id=$supplier_id");
            error_log("Material: '$material', Harga: '$harga', Berat: '$berat'");

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssisddd", $no_tiket, $no_kendaraan, $nama_pengemudi, $supplier_id, $material, $harga, $berat, $berat);

            if (mysqli_stmt_execute($stmt)) {
                error_log("✅ INSERT SUCCESS for tiket: $no_tiket");
                $success_message = "Data timbangan 1 berhasil disimpan dengan nomor tiket: " . $no_tiket;
            } else {
                $error_msg = mysqli_error($conn);
                error_log("❌ INSERT FAILED: " . $error_msg);
                $error_message = "Gagal menyimpan data: " . $error_msg;
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get data untuk dropdown suplier - with caching
$cache_key = 'supplier_list_' . date('Y-m-d');
$suplier_list = cache_get($cache_key);

if ($suplier_list === null) {
    $suplier_list = [];
    $query = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $suplier_list[] = $row;
    }
    cache_set($cache_key, $suplier_list, 3600); // Cache for 1 hour
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 bg-dark text-light shadow-lg">
                <div class="card-header bg-gradient border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0 text-white">
                                <i class="fas fa-weight me-2"></i>TIMBANGAN 1
                            </h5>
                            <small class="text-light opacity-75">Input Data Awal Timbangan</small>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-danger fs-6" id="currentDateTime"></div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="timbangan1Form">
                        <div class="row g-3">
                            <!-- Nomor Kendaraan -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-truck me-1"></i>Nomor Kendaraan
                                </label>
                                <input type="text" name="no_kendaraan" class="form-control bg-dark text-white border-secondary"
                                       placeholder="BM" required>
                            </div>

                            <!-- Nama Pengemudi -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-user me-1"></i>Nama Pengemudi
                                </label>
                                <input type="text" name="nama_pengemudi" class="form-control bg-dark text-white border-secondary"
                                       placeholder="Masukkan nama pengemudi" required>
                            </div>

                            <!-- Nama Suplier -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-building me-1"></i>Nama Suplier
                                </label>
                                <select name="nama_suplier" class="form-select bg-dark text-white border-secondary" required>
                                    <option value="">Pilih Suplier</option>
                                    <?php foreach ($suplier_list as $suplier): ?>
                                        <option value="<?= $suplier['nama_supplier'] ?>"><?= $suplier['nama_supplier'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Material -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-box me-1"></i>Material
                                </label>
                                <select name="material" class="form-select bg-dark text-white border-secondary" required>
                                    <?php echo get_material_options('TBS'); ?>
                                </select>
                            </div>

                            <!-- Harga -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-tag me-1"></i>Harga per Kg
                                </label>
                                <input type="text" name="harga_display" class="form-control bg-dark text-white border-secondary"
                                       id="hargaInput" placeholder="Rp 0" required>
                                <input type="hidden" name="harga" id="hargaValue" value="0">
                            </div>

                            <!-- Display Timbangan 1 -->
                            <div class="col-md-6">
                                <div class="card bg-secondary border-0">
                                    <div class="card-body">
                                        <label class="form-label text-warning fs-5">
                                            <i class="fas fa-weight me-1"></i>Display Timbangan 1
                                        </label>
                                        <div class="display-4 text-danger fw-bold" id="weightDisplay">0 Kg</div>
                                        <small class="text-light opacity-75" id="weightStatus">Menunggu koneksi ke indikator...</small>
                                        <div class="mt-2 mb-3">
                                            <button type="button" class="btn btn-outline-info btn-sm w-100" id="toggleConnection">
                                                <i class="fas fa-plug me-2"></i>Connect Indicator
                                            </button>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-outline-warning btn-lg w-100" id="captureWeight">
                                                <i class="fas fa-camera me-2"></i>CAPTURE TIMBANG
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Input Hasil Timbang 1 -->
                            <div class="col-md-6">
                                <div class="card bg-dark border-0">
                                    <div class="card-body">
                                        <label class="form-label text-info fs-5">
                                            <i class="fas fa-edit me-1"></i>Input Hasil Timbang 1 (BRUTO)
                                        </label>
                                        <div class="form-group mb-3">
                                            <label class="form-label text-light">Berat Timbangan 1 (Kg)</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                   name="berat" id="beratInputForm" value="0"
                                                   placeholder="Masukkan berat dalam Kg" step="1" min="0" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-redo me-2"></i>RESET
                                    </button>
                                    <button type="submit" class="btn btn-success btn-lg px-5">
                                        <i class="fas fa-save me-2"></i>SIMPAN DATA
                                    </button>
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
.card-header.bg-gradient {
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
}

.form-control, .form-select {
    border-radius: 8px;
    border-width: 2px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #dc2626;
    box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
    background-color: #2a2a2a;
}

.form-label {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

#weightDisplay {
    font-family: 'Courier New', monospace;
    text-shadow: 0 0 10px rgba(220, 38, 38, 0.5);
    animation: pulse 2s infinite;
}

#beratInputForm {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 1.5rem;
    text-align: center;
}

#beratInputForm::-webkit-outer-spin-button,
#beratInputForm::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.8; }
    100% { opacity: 1; }
}

.card {
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}
</style>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

// Format Rupiah
document.getElementById('hargaInput').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value) {
        e.target.value = 'Rp ' + parseInt(value).toLocaleString('id-ID');
        // Update hidden field with numeric value
        document.getElementById('hargaValue').value = parseInt(value);
    } else {
        e.target.value = 'Rp 0';
        // Update hidden field with 0
        document.getElementById('hargaValue').value = '0';
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
        console.warn('AutoSerialConnector not available, will use fallback');
        return false;
    }

    if (!serialConnector) {
        serialConnector = new AutoSerialConnector({
            targetInputId: 'beratInputForm',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                console.log('✅ Auto Serial Connected');
                updateConnectionUI(true);
                showNotification('Terhubung ke indikator Sonic A283', 'success');
            },
            onDisconnect: () => {
                console.log('❌ Auto Serial Disconnected');
                updateConnectionUI(false);
            },
            onData: (weight) => {
                console.log('📈 Auto Serial Weight:', weight);
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayAutoSerial(weight);
            },
            onError: (error) => {
                console.error('❌ Auto Serial Error:', error);
                showNotification('Error koneksi serial: ' + error.message, 'error');
                updateConnectionUI(false);
            }
        });
    }

    // Try auto-connect first
    const autoConnected = await serialConnector.autoConnect();
    if (autoConnected) {
        console.log('✅ Auto-connect successful');
        return true;
    }

    // If auto-connect fails, wait for manual connection
    console.log('⏳ Auto-connect failed, waiting for manual connection');
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
        console.warn('Enhanced Web Serial API not loaded yet for Timbangan 1, using fallback mode');
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

        console.log('Setting up Enhanced Web Serial callbacks for Timbangan 1...');

        // Set up callbacks using the MultiWeightManager
        window.WeightIndicators.onWeightUpdate(function(indicatorId, weight) {
            if (indicatorId === 'timbangan1') {
                console.log('📈 [Timbangan1] Weight callback received:', weight);
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayWebSerial(weight);
            }
        });

        window.WeightIndicators.onConnectionChange(function(indicatorId, connected) {
            if (indicatorId === 'timbangan1') {
                console.log('🔌 [Timbangan1] Connection callback received:', connected);
                updateConnectionUI(connected);
            }
        });

        window.WeightIndicators.onError(function(indicatorId, error) {
            if (indicatorId === 'timbangan1') {
                console.error('❌ [Timbangan1] Serial Error callback received:', error);
                showNotification('Error Timbangan 1: ' + error, 'error');
                updateConnectionUI(false);
            }
        });

        // Alternative: Set callbacks directly on indicator
        if (typeof timbangan1Indicator.onWeightUpdate === 'function') {
            timbangan1Indicator.onWeightUpdate(function(weight) {
                console.log('📈 [Timbangan1] Direct weight callback received:', weight);
                currentWeight = weight;
                lastWeightUpdate = Date.now();
                updateWeightDisplayWebSerial(weight);
            });

            timbangan1Indicator.onConnectionChange(function(connected) {
                console.log('🔌 [Timbangan1] Direct connection callback received:', connected);
                updateConnectionUI(connected);
            });

            timbangan1Indicator.onError(function(error) {
                console.error('❌ [Timbangan1] Direct serial Error callback received:', error);
                showNotification('Error Timbangan 1: ' + error, 'error');
                updateConnectionUI(false);
            });
        }

        // Initial UI update
        updateConnectionUI(false);
        console.log('✅ Enhanced Web Serial API callbacks initialized successfully for Timbangan 1');

        // Test callback setup
        console.log('🔍 Callbacks test for Timbangan 1:');
        console.log('- MultiWeightManager available:', !!window.WeightIndicators);
        console.log('- Timbangan1 indicator created:', !!timbangan1Indicator);
        console.log('- Browser support:', timbangan1Indicator.isSupported());

        // Add test function for manual weight testing
        window.testWeightUpdateTimbangan1 = function(testWeight) {
            console.log('🧪 Testing weight update for Timbangan 1 with:', testWeight);
            updateWeightDisplayWebSerial(testWeight);
        };

        console.log('💡 Test function available: testWeightUpdateTimbangan1(weight)');

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
            console.error('Connection error timbangan 1: Indicator not properly initialized');
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
            toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Disconnect Indicator';
            toggleBtn.className = 'btn btn-outline-danger btn-sm w-100';
        }
    } else {
        if (weightStatus) {
            weightStatus.textContent = 'Indicator Tidak Terhubung';
            weightStatus.className = 'text-danger opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Connect Indicator';
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
                        toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Disconnect Indicator';
                        toggleBtn.className = 'btn btn-outline-danger btn-sm w-100';
                    }
                } else {
                    if (weightStatus) {
                        weightStatus.textContent = 'Tidak terhubung';
                        weightStatus.className = 'text-danger opacity-75';
                    }
                    if (toggleBtn) {
                        toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Connect Indicator';
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
        },
        error: function(xhr, status, error) {
            if (weightStatus) {
                weightStatus.textContent = 'Connection Error: ' + error;
                weightStatus.className = 'text-danger opacity-75';
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
console.log('Starting Timbangan1 weight updates...');
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
    this.innerHTML = '<i class="fas fa-lock me-2"></i>BERAT TERKUNCI!';
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
            captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>CAPTURE TIMBANG';
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

    // DEBUG: Log semua form data
    console.log('=== FORM SUBMISSION DEBUG ===');
    console.log('FormData entries:', Array.from(formData.entries()));
    console.log('Material from form:', material);
    console.log('Harga from form:', harga);
    console.log('Material select value:', document.querySelector('select[name="material"]').value);

    // Validasi material (dengan fallback ke default)
    if (!material || material === '' || material === null) {
        console.warn('Material kosong, menggunakan default TBS');
        // Set default material ke select field jika kosong
        const materialSelect = document.querySelector('select[name="material"]');
        if (materialSelect) {
            materialSelect.value = 'TBS';
            // Trigger change event untuk update display
            materialSelect.dispatchEvent(new Event('change'));
            console.log('Set material to default TBS');
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
</script>

<?php require_once '../../includes/footer.php'; ?>