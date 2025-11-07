<?php
// modules/timbangan/timbangan2.php
require_once '../../config/database.php';
require_once '../../includes/material_functions.php';
require_once '../../includes/cache_manager.php';

$page_title = 'Timbangan 2';
require_once '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_tiket1 = clean_input($_POST['no_tiket1']);
    $persen_potongan = floatval(str_replace(',', '.', clean_input($_POST['persen_potongan'])));
    $berat2 = floatval(clean_input($_POST['berat2']));

    // Get data dari timbangan 1
    $query = "SELECT tt.*, s.nama_supplier FROM transaksi_timbangan tt LEFT JOIN supplier s ON tt.id_supplier = s.id WHERE tt.no_tiket = ? AND tt.status = 'timbang_1' AND tt.timbang1_locked = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $no_tiket1);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data_timbangan1 = mysqli_fetch_assoc($result);

    if ($data_timbangan1) {
        // SAMAKAN dengan perhitungan JavaScript (HASIL PERHITUNGAN OTOMATIS)
        // Ambil data yang sama dengan yang digunakan JavaScript
        $berat1 = $data_timbangan1['berat_bruto']; // sama dengan berat1 di JavaScript
        $harga = $data_timbangan1['harga_per_kg']; // sama dengan harga di JavaScript
        $berat2 = $berat2; // sama dengan berat2 di JavaScript
        $persenPotongan = $persen_potongan; // sama dengan persenPotongan di JavaScript

        // Formula PERSIS SAMA dengan JavaScript:
        // Timbangan 1 = BRUTO (Truck Penuh), Timbangan 2 = TARA (Truck Kosong)
        $bruto = $berat1; // Timbangan 1 = BRUTO (Truck Penuh)
        $tara = $berat2;  // Timbangan 2 = TARA (Truck Kosong)
        $netto = $bruto - $tara; // Netto (sama dengan JavaScript)
        $potonganKg = ($persenPotongan / 100) * $netto; // Potongan dalam kg (sama dengan JavaScript)
        $nettoAkhir = $netto - $potonganKg; // Netto Akhir (sama dengan JavaScript)
        $totalHarga = $nettoAkhir * $harga; // Total Harga (sama dengan JavaScript)

        // Untuk database, gunakan nama variabel yang sesuai
        $netto_bt = $netto; // untuk kompatibilitas
        $total_potongan = $potonganKg; // untuk kompatibilitas
        $netto_akhir = $nettoAkhir; // untuk kompatibilitas
        $total_harga = $totalHarga; // untuk kompatibilitas

        // Calculate additional fields - SAMAKAN dengan JavaScript
        $berat_netto = $nettoAkhir; // berat_netto = nettoAkhir (hasil akhir setelah potongan) - SAMA dengan JavaScript
        $kg_potongan = $total_potongan; // potonganKg dari JavaScript

        // Update transaksi timbangan dengan data lengkap dan hasil perhitungan yang SAMA PERSIS dengan JavaScript
        // berat_netto akan diisi dengan netto_akhir (hasil akhir setelah potongan) agar sama dengan HASIL PERHITUNGAN OTOMATIS
        // JANGAN UBAH DATA PERHITUNGAN DI DATABASE!
        // Update hanya data dasar, biarkan perhitungan lama tetap ada
        $update_query = "UPDATE transaksi_timbangan SET
                        berat_tara = ?,
                        berat_timbangan2 = ?,
                        persen_potongan = ?,
                        timbang2_locked = 1,
                        waktu_timbangan2 = NOW(),
                        waktu_keluar = NOW(),
                        status = 'selesai'
                        WHERE no_tiket = ? AND timbang1_locked = 1 AND status = 'timbang_1'";

        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ddds",
            $tara,             // berat_tara
            $tara,             // berat_timbangan2
            $persenPotongan,   // persen_potongan
            $no_tiket1         // no_tiket
        );

        if (mysqli_stmt_execute($update_stmt)) {
            // Check if update was successful
            if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                $success_message = "Data timbangan 2 berhasil disimpan untuk tiket: " . htmlspecialchars($no_tiket1);

                // Tampilkan popup untuk cetak struk dengan data yang BENAR (sesuai JavaScript)
                // Database tidak diubah, tapi popup tampilkan hasil yang benar
                echo "<script>
                    setTimeout(function() {
                        Swal.fire({
                            title: 'Transaksi Selesai!',
                            html: '<p>Data transaksi berhasil disimpan.</p><p>No. Tiket: <strong>" . htmlspecialchars($no_tiket1) . "</strong></p><p>Netto Akhir: <strong>" . number_format($nettoAkhir, 2, ',', '.') . " Kg</strong></p><p>Total Harga: <strong>Rp " . number_format($totalHarga, 0, ',', '.') . "</strong></p>',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Cetak Struk',
                            cancelButtonText: 'Selesai'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('print_ticket.php?no_tiket=" . urlencode($no_tiket1) . "', '_blank');
                            }
                        });
                    }, 500);
                </script>";
            } else {
                $error_message = "Tiket sudah diproses atau tidak ditemukan. Mohon periksa nomor tiket.";
            }
        } else {
            $error_message = "Gagal menyimpan data: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    } else {
        $error_message = "Data tiket tidak ditemukan atau sudah diproses!";
    }
    mysqli_stmt_close($stmt);
}

// Get data untuk dropdown tiket yang available - with shorter cache
$cache_key_tiket = 'tiket_list_' . date('Y-m-d-H-i'); // Cache per menit
$tiket_list = cache_get($cache_key_tiket);

if ($tiket_list === null) {
    $tiket_list = [];
    $query = "SELECT tt.no_tiket, tt.no_polisi, tt.nama_supir, s.nama_supplier, tt.jenis_material, tt.berat_bruto, tt.harga_per_kg, tt.tanggal
              FROM transaksi_timbangan tt
              LEFT JOIN supplier s ON tt.id_supplier = s.id
              WHERE tt.status = 'timbang_1' AND tt.timbang1_locked = 1
              ORDER BY tt.created_at DESC
              LIMIT 50"; // Add LIMIT for performance
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $tiket_list[] = $row;
    }
    cache_set($cache_key_tiket, $tiket_list, 300); // Cache for 5 minutes
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
                                <i class="fas fa-weight me-2"></i>TIMBANGAN 2
                            </h5>
                            <small class="text-light opacity-75">Proses Akhir Timbangan dan Perhitungan</small>
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

                    <form method="POST" id="timbangan2Form">
                        <div class="row g-3">
                            <!-- Pilih Nomor Tiket -->
                            <div class="col-md-12">
                                <label class="form-label text-warning">
                                    <i class="fas fa-ticket-alt me-1"></i>Pilih Nomor Tiket dari Timbangan 1
                                    <button type="button" class="btn btn-sm btn-outline-info ms-2" id="refreshTiketBtn" title="Refresh Data Tiket">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </label>
                                <select name="no_tiket1" class="form-select bg-dark text-white border-secondary" id="tiketSelector" required>
                                    <option value="">Pilih Tiket</option>
                                    <?php foreach ($tiket_list as $tiket): ?>
                                        <option value="<?= $tiket['no_tiket'] ?>"
                                                data-kendaraan="<?= $tiket['no_polisi'] ?>"
                                                data-pengemudi="<?= $tiket['nama_supir'] ?>"
                                                data-suplier="<?= $tiket['nama_supplier'] ?>"
                                                data-material="<?= $tiket['jenis_material'] ?>"
                                                data-harga="<?= $tiket['harga_per_kg'] ?>"
                                                data-berat="<?= $tiket['berat_bruto'] ?>">
                                            <?= $tiket['no_tiket'] ?> - <?= $tiket['no_polisi'] ?> - <?= $tiket['nama_supplier'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle"></i> Data refresh setiap 5 menit. Klik refresh untuk update langsung.
                                </small>
                            </div>

                            <!-- Data dari Timbangan 1 (Readonly) -->
                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-truck me-1"></i>Nomor Kendaraan
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displayKendaraan" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-user me-1"></i>Nama Pengemudi
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displayPengemudi" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-building me-1"></i>Nama Suplier
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displaySuplier" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-box me-1"></i>Material
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displayMaterial" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-tag me-1"></i>Harga per Kg
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displayHarga" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-info">
                                    <i class="fas fa-weight me-1"></i>Berat Timbangan 1
                                </label>
                                <input type="text" class="form-control bg-secondary text-white" id="displayBerat1" readonly>
                            </div>

                            <!-- Input Timbangan 2 -->
                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-percentage me-1"></i>Persen Potongan (%)
                                </label>
                                <input type="number" name="persen_potongan" class="form-control bg-dark text-white border-secondary"
                                       id="persenPotongan" step="0.01" min="0" max="100" value="0" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-warning">
                                    <i class="fas fa-weight me-1"></i>Display Timbangan 2
                                </label>
                                <input type="text" class="form-control bg-dark text-white border-secondary"
                                       id="weightDisplay2" value="0 Kg" readonly>
                            </div>

                            <!-- Tombol Capture Timbangan 2 -->
                            <div class="col-md-5">
                                <div class="card bg-secondary border-0">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="display-4 text-danger fw-bold" id="weightDisplay2Large">0 Kg</div>
                                                <small class="text-light opacity-75" id="weightStatus2">Menghubungkan ke indikator...</small>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-outline-info btn-sm" id="toggleConnection2">
                                                        <i class="fas fa-plug me-2"></i>Connect Indicator
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="button" class="btn btn-outline-warning btn-lg" id="captureWeight2">
                                                    <i class="fas fa-camera me-2"></i>CAPTURE TIMBANG 2
                                                </button>
                                                <input type="hidden" name="berat2" id="beratInput2" value="0" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Perhitungan Otomatis -->
                            <div class="col-7">
                                <div class="card bg-gradient-success border-0">
                                    <div class="card-body">
                                        <h6 class="card-title text-white mb-3">
                                            <i class="fas fa-calculator me-2"></i>HASIL PERHITUNGAN OTOMATIS
                                        </h6>
                                        <div class="row text-center g-2 ">
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Bruto </small>
                                                    <h4 class="text-white mb-0" id="hasilBruto">0 Kg</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Tara</small>
                                                    <h4 class="text-white mb-0" id="hasilTara">0 Kg</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Netto</small>
                                                    <h4 class="text-white mb-0" id="hasilNettoBT">0 Kg</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Potongan</small>
                                                    <h4 class="text-danger mb-0" id="hasilPotongan">0 Kg</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Netto Akhir</small>
                                                    <h4 class="text-success mb-0" id="hasilNettoAkhir">0 Kg</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="p-3 bg-dark rounded">
                                                    <small class="text-warning d-block mb-1">Total Harga</small>
                                                    <h4 class="text-info mb-0" id="hasilTotalHarga">Rp 0</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="timbangan1.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>KEMBALI KE TIMBANGAN 1
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg px-5" id="saveButton" disabled>
                                        <i class="fas fa-save me-2"></i>SIMPAN & CETAK STRUK
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

.bg-gradient-success {
    background: linear-gradient(135deg, #16a34a, #15803d) !important;
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

#weightDisplay2Large {
    font-family: 'Courier New', monospace;
    text-shadow: 0 0 10px rgba(220, 38, 38, 0.5);
    animation: pulse 2s infinite;
}

#weightDisplay2Large.captured {
    animation: none;
    color: #22c55e !important;
    text-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
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
function loadSerialModules2() {
    // Only load if we have weight display elements
    if (document.getElementById('weightDisplay2Large')) {
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
                    initializeAutoSerialConnector2().then(success => {
                        // Fallback to Enhanced Web Serial API if auto-connect fails
                        if (!success) {
                            setTimeout(function() {
                                if (!serialConnector2 || !serialConnector2.isConnected) {
                                    initializeEnhancedWebSerialTimbangan2();
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
    document.addEventListener('DOMContentLoaded', loadSerialModules2);
} else {
    loadSerialModules2();
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

// Load data tiket ketika dipilih
document.getElementById('tiketSelector').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    if (this.value) {
        const displayKendaraan = document.getElementById('displayKendaraan');
        const displayPengemudi = document.getElementById('displayPengemudi');
        const displaySuplier = document.getElementById('displaySuplier');
        const displayMaterial = document.getElementById('displayMaterial');
        const displayHarga = document.getElementById('displayHarga');
        const displayBerat1 = document.getElementById('displayBerat1');
        const saveButton = document.getElementById('saveButton');

        if (displayKendaraan) displayKendaraan.value = selectedOption.dataset.kendaraan || '';
        if (displayPengemudi) displayPengemudi.value = selectedOption.dataset.pengemudi || '';
        if (displaySuplier) displaySuplier.value = selectedOption.dataset.suplier || '';

        // Konversi kode material ke nama deskriptif (dari PHP)
        const materialCodes = <?php echo get_material_js_mapping(); ?>;
        const materialCode = selectedOption.dataset.material;
        const materialName = materialCodes[materialCode] || materialCode;

        if (displayMaterial) {
            displayMaterial.value = materialName;
        }

        const harga = parseInt(selectedOption.dataset.harga) || 0;
        const berat = parseInt(selectedOption.dataset.berat) || 0;

        if (displayHarga) {
            displayHarga.value = 'Rp ' + harga.toLocaleString('id-ID');
        }
        if (displayBerat1) {
            displayBerat1.value = berat.toLocaleString('id-ID') + ' Kg';
        }

        // Enable save button
        if (saveButton) saveButton.disabled = false;

        // Reset capture state when changing ticket
        isCaptured2 = false;
        const beratInput2 = document.getElementById('beratInput2');
        if (beratInput2) {
            beratInput2.value = '0';
            beratInput2.readOnly = false;
            beratInput2.style.backgroundColor = '';
            beratInput2.style.border = '';
        }

        // CLEAR LOCK VARIABLES
        window.isWeightLocked2 = false;
        window.capturedWeight2 = 0;

        const captureBtn = document.getElementById('captureWeight2');
        if (captureBtn) {
            captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>CAPTURE TIMBANG 2';
            captureBtn.classList.remove('btn-success');
            captureBtn.classList.add('btn-outline-warning');
            captureBtn.disabled = false;
        }

        const weightDisplay2Large = document.getElementById('weightDisplay2Large');
        if (weightDisplay2Large) {
            weightDisplay2Large.classList.remove('captured');
            weightDisplay2Large.style.color = '';
            weightDisplay2Large.style.textShadow = '';
            weightDisplay2Large.innerHTML = '0 Kg'; // Reset dari innerHTML ke textContent
        }

        // Restart weight simulation
        if (weightInterval2) clearInterval(weightInterval2);
        weightInterval2 = setInterval(updateWeight2, 3000);
        updateWeight2();

        // Hitung otomatis
        hitungOtomatis();
    } else {
        const displayKendaraan = document.getElementById('displayKendaraan');
        const displayPengemudi = document.getElementById('displayPengemudi');
        const displaySuplier = document.getElementById('displaySuplier');
        const displayMaterial = document.getElementById('displayMaterial');
        const displayHarga = document.getElementById('displayHarga');
        const displayBerat1 = document.getElementById('displayBerat1');
        const saveButton = document.getElementById('saveButton');

        if (displayKendaraan) displayKendaraan.value = '';
        if (displayPengemudi) displayPengemudi.value = '';
        if (displaySuplier) displaySuplier.value = '';
        if (displayMaterial) displayMaterial.value = '';
        if (displayHarga) displayHarga.value = '';
        if (displayBerat1) displayBerat1.value = '';
        if (saveButton) saveButton.disabled = true;
        resetHasilPerhitungan();
    }
});

// Auto Serial Connector untuk Timbangan 2
let serialConnector2 = null;
let currentWeight2 = 0;
let lastWeightUpdate2 = 0;
let timbangan2Indicator = null;

// Initialize Auto Serial Connector (moved to loadSerialModules2)
// document.addEventListener('DOMContentLoaded', async function() {
//     await initializeAutoSerialConnector2();
//
//     // Fallback to Enhanced Web Serial API
//     setTimeout(function() {
//         if (!serialConnector2 || !serialConnector2.isConnected) {
//             initializeEnhancedWebSerialTimbangan2();
//         }
//     }, 500);
// });

// Initialize Auto Serial Connector for Timbangan 2
async function initializeAutoSerialConnector2() {
    if (!window.AutoSerialConnector) {
        console.warn('AutoSerialConnector not available, will use fallback');
        return false;
    }

    if (!serialConnector2) {
        serialConnector2 = new AutoSerialConnector({
            targetInputId: 'beratInput2',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                console.log('✅ Auto Serial Connected (Timbangan 2)');
                updateConnectionUI2(true);
                showNotification2('Terhubung ke indikator Sonic A283', 'success');
            },
            onDisconnect: () => {
                console.log('❌ Auto Serial Disconnected (Timbangan 2)');
                updateConnectionUI2(false);
            },
            onData: (weight) => {
                console.log('📈 Auto Serial Weight (Timbangan 2):', weight);
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplayAutoSerial2(weight);
            },
            onError: (error) => {
                console.error('❌ Auto Serial Error (Timbangan 2):', error);
                showNotification2('Error koneksi serial: ' + error.message, 'error');
                updateConnectionUI2(false);
            }
        });
    }

    // Try auto-connect first
    const autoConnected = await serialConnector2.autoConnect();
    if (autoConnected) {
        console.log('✅ Auto-connect successful (Timbangan 2)');
        return true;
    }

    // If auto-connect fails, wait for manual connection
    console.log('⏳ Auto-connect failed, waiting for manual connection (Timbangan 2)');
    return false;
}

// Update weight display from Auto Serial Connector for Timbangan 2
function updateWeightDisplayAutoSerial2(weight) {
    const weightDisplay2 = document.getElementById('weightDisplay2');
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');
    const beratInput2 = document.getElementById('beratInput2');

    // Update display timbangan
    if (weightDisplay2Large) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked2) {
            weightDisplay2Large.innerHTML = `${weight.toLocaleString('id-ID')} Kg<br><small style="color: #ef4444;">(Locked: ${window.capturedWeight2.toLocaleString('id-ID')} Kg)</small>`;
            weightDisplay2Large.style.color = '#fbbf24'; // Warna kuning untuk menandakan locked
        } else {
            weightDisplay2Large.textContent = weight.toLocaleString('id-ID') + ' Kg';
        }
    }

    if (weightDisplay2) {
        if (window.WeightIndicatorUtils) {
            if (window.isWeightLocked2) {
                weightDisplay2.value = `${window.WeightIndicatorUtils.formatWeight(weight)} (Locked: ${window.WeightIndicatorUtils.formatWeight(window.capturedWeight2)})`;
            } else {
                weightDisplay2.value = window.WeightIndicatorUtils.formatWeight(weight);
            }
        } else {
            if (window.isWeightLocked2) {
                weightDisplay2.value = `${weight.toLocaleString('id-ID')} Kg (Locked: ${window.capturedWeight2.toLocaleString('id-ID')} Kg)`;
            } else {
                weightDisplay2.value = weight.toLocaleString('id-ID') + ' Kg';
            }
        }
    }

    // Update status
    if (weightStatus2) {
        if (window.isWeightLocked2) {
            weightStatus2.textContent = 'Berat terkunci - Timbangan masih aktif';
            weightStatus2.className = 'text-warning opacity-75';
        } else if (weight > 0) {
            weightStatus2.textContent = 'Data diterima dari Sonic A28E';
            weightStatus2.className = 'text-success opacity-75';
        } else {
            weightStatus2.textContent = 'Terhubung';
            weightStatus2.className = 'text-light opacity-75';
        }
    }

    // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
    if (beratInput2 && !window.isWeightLocked2) {
        currentWeight2 = weight;
        lastWeightUpdate2 = Date.now();
    } else if (beratInput2 && window.isWeightLocked2) {
        // Pastikan form input tetap menampilkan berat yang di-lock
        beratInput2.value = window.capturedWeight2;
    }
}

// Initialize Enhanced Web Serial API for Timbangan 2
function initializeEnhancedWebSerialTimbangan2() {
    // Check if Enhanced Web Serial API is available
    if (!window.WeightIndicators) {
        console.warn('Enhanced Web Serial API not loaded yet for Timbangan 2, using fallback mode');
        document.getElementById('weightStatus2').textContent = 'Enhanced Web Serial API tidak tersedia. Menggunakan fallback.';
        document.getElementById('weightStatus2').className = 'text-warning opacity-75';
        updateConnectionUI2(false);
        return;
    }

    try {
        // Get or create indicator for timbangan 2
        timbangan2Indicator = window.WeightIndicators.getIndicator('timbangan2');

        // Check if indicator was created successfully
        if (!timbangan2Indicator) {
            throw new Error('Failed to create timbangan2 indicator instance');
        }

        console.log('Setting up Enhanced Web Serial callbacks for Timbangan 2...');

        // Set up callbacks using the MultiWeightManager
        window.WeightIndicators.onWeightUpdate(function(indicatorId, weight) {
            if (indicatorId === 'timbangan2') {
                console.log('📈 [Timbangan2] Weight callback received:', weight);
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplay2WebSerial(weight);
            }
        });

        window.WeightIndicators.onConnectionChange(function(indicatorId, connected) {
            if (indicatorId === 'timbangan2') {
                console.log('🔌 [Timbangan2] Connection callback received:', connected);
                updateConnectionUI2(connected);
            }
        });

        window.WeightIndicators.onError(function(indicatorId, error) {
            if (indicatorId === 'timbangan2') {
                console.error('❌ [Timbangan2] Serial Error callback received:', error);
                showNotification2('Error Timbangan 2: ' + error, 'error');
                updateConnectionUI2(false);
            }
        });

        // Alternative: Set callbacks directly on indicator
        if (typeof timbangan2Indicator.onWeightUpdate === 'function') {
            timbangan2Indicator.onWeightUpdate(function(weight) {
                console.log('📈 [Timbangan2] Direct weight callback received:', weight);
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplay2WebSerial(weight);
            });

            timbangan2Indicator.onConnectionChange(function(connected) {
                console.log('🔌 [Timbangan2] Direct connection callback received:', connected);
                updateConnectionUI2(connected);
            });

            timbangan2Indicator.onError(function(error) {
                console.error('❌ [Timbangan2] Direct serial Error callback received:', error);
                showNotification2('Error Timbangan 2: ' + error, 'error');
                updateConnectionUI2(false);
            });
        }

        // Initial UI update
        updateConnectionUI2(false);
        console.log('✅ Enhanced Web Serial API callbacks initialized successfully for Timbangan 2');

        // Test callback setup
        console.log('🔍 Callbacks test for Timbangan 2:');
        console.log('- MultiWeightManager available:', !!window.WeightIndicators);
        console.log('- Timbangan2 indicator created:', !!timbangan2Indicator);
        console.log('- Browser support:', timbangan2Indicator.isSupported());

        // Add test function for manual weight testing
        window.testWeightUpdateTimbangan2 = function(testWeight) {
            console.log('🧪 Testing weight update for Timbangan 2 with:', testWeight);
            updateWeightDisplay2WebSerial(testWeight);
        };

        console.log('💡 Test function available: testWeightUpdateTimbangan2(weight)');

    } catch (error) {
        console.error('Failed to initialize Enhanced Web Serial API for Timbangan 2:', error);
        document.getElementById('weightStatus2').textContent = 'Gagal inisialisasi Enhanced Web Serial API';
        document.getElementById('weightStatus2').className = 'text-danger opacity-75';
    }
}

// Toggle indicator connection for timbangan 2 dengan Enhanced Web Serial API
document.getElementById('toggleConnection2').addEventListener('click', async function() {
    // Try Auto Serial Connector first
    if (serialConnector2) {
        if (serialConnector2.isConnected) {
            await serialConnector2.disconnect();
            updateConnectionUI2(false);
            showNotification2('Terputus dari indikator Sonic A283', 'info');
        } else {
            const success = await serialConnector2.manualConnect();
            if (success) {
                updateConnectionUI2(true);
                showNotification2('Terhubung ke indikator Sonic A283', 'success');
            } else {
                showNotification2('Gagal menghubungkan ke indikator Sonic A283', 'error');
            }
        }
        return;
    }

    // Fallback to Enhanced Web Serial API
    if (!window.WeightIndicators) {
        showNotification2('Enhanced Web Serial API tidak tersedia. Menggunakan fallback AJAX', 'warning');
        // Use original AJAX method as fallback
        const toggleBtn = document.getElementById('toggleConnection2');
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
                    updateWeightDisplay2();
                }
            }
        });
        return;
    }

    try {
        const toggleBtn = document.getElementById('toggleConnection2');
        const indicator = window.WeightIndicators.getIndicator('timbangan2');

        // Check if indicator exists and has isConnected property
        if (!indicator || typeof indicator.isConnected === 'undefined') {
            showNotification2('Error: Indicator tidak tersedia. Silakan refresh halaman.', 'error');
            console.error('Connection error timbangan 2: Indicator not properly initialized');
            return;
        }

        const isConnected = indicator.isConnected;

        if (isConnected) {
            // Disconnect
            const success = await indicator.disconnect();
            if (success) {
                updateConnectionUI2(false);
                showNotification2('Terputus dari indikator timbangan 2', 'info');
            }
        } else {
            // Connect
            const success = await indicator.connect();
            if (success) {
                updateConnectionUI2(true);
                showNotification2('Terhubung ke indikator timbangan 2', 'success');
            } else {
                showNotification2('Gagal menghubungkan ke indikator timbangan 2', 'error');
            }
        }
    } catch (error) {
        console.error('Connection error timbangan 2:', error);
        showNotification2('Error koneksi timbangan 2: ' + error.message, 'error');
    }
});

// Update weight display for timbangan 2 via Web Serial API
function updateWeightDisplay2WebSerial(weight) {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightDisplay2 = document.getElementById('weightDisplay2');
    const weightStatus2 = document.getElementById('weightStatus2');
    const beratInput2 = document.getElementById('beratInput2');

    // Update display timbangan
    if (weightDisplay2Large && window.WeightIndicatorUtils) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked2) {
            weightDisplay2Large.innerHTML = `${window.WeightIndicatorUtils.formatWeight(weight)}<br><small style="color: #ef4444;">(Locked: ${window.WeightIndicatorUtils.formatWeight(window.capturedWeight2)})</small>`;
            weightDisplay2Large.style.color = '#fbbf24'; // Warna kuning untuk menandakan locked
        } else {
            weightDisplay2Large.textContent = window.WeightIndicatorUtils.formatWeight(weight);
        }
    }

    if (weightDisplay2 && window.WeightIndicatorUtils) {
        if (window.isWeightLocked2) {
            weightDisplay2.value = `${window.WeightIndicatorUtils.formatWeight(weight)} (Locked: ${window.WeightIndicatorUtils.formatWeight(window.capturedWeight2)})`;
        } else {
            weightDisplay2.value = window.WeightIndicatorUtils.formatWeight(weight);
        }
    }

    // Update status
    if (weightStatus2) {
        if (window.isWeightLocked2) {
            weightStatus2.textContent = 'Berat terkunci - Timbangan masih aktif';
            weightStatus2.className = 'text-warning opacity-75';
        } else if (weight > 0) {
            weightStatus2.textContent = 'Data diterima dari indikator';
            weightStatus2.className = 'text-success opacity-75';
        } else {
            weightStatus2.textContent = 'Menunggu data dari indikator...';
            weightStatus2.className = 'text-light opacity-75';
        }
    }

    // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
    if (beratInput2 && !window.isWeightLocked2) {
        currentWeight2 = weight;
        lastWeightUpdate2 = Date.now();
    } else if (beratInput2 && window.isWeightLocked2) {
        // Pastikan form input tetap menampilkan berat yang di-lock
        beratInput2.value = window.capturedWeight2;
    }
}

// Update connection UI untuk Timbangan 2
function updateConnectionUI2(connected) {
    const weightStatus2 = document.getElementById('weightStatus2');
    const toggleBtn = document.getElementById('toggleConnection2');

    if (connected) {
        if (weightStatus2) {
            weightStatus2.textContent = 'Terhubung ke Sonic A283';
            weightStatus2.className = 'text-success opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Disconnect Indicator';
            toggleBtn.className = 'btn btn-outline-danger btn-sm';
        }
    } else {
        if (weightStatus2) {
            weightStatus2.textContent = 'Indicator Tidak Terhubung';
            weightStatus2.className = 'text-danger opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Connect Indicator';
            toggleBtn.className = 'btn btn-outline-info btn-sm';
        }
    }
}

// Show notification untuk Timbangan 2
function showNotification2(message, type) {
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

// Update weight display for timbangan 2 (Fallback AJAX)
function updateWeightDisplay2() {
    // Check if Auto Serial Connector is available and connected
    try {
        if (window.serialConnector2 && window.serialConnector2.isConnected) {
            // Data sudah diupdate via callback
            return;
        }
    } catch (error) {
        console.log('Error checking Auto Serial status in timbangan 2, using fallback:', error);
    }

    // Check if Enhanced Web Serial API is available and connected
    try {
        if (window.WeightIndicators) {
            const indicator = window.WeightIndicators.getIndicator('timbangan2');
            if (indicator && indicator.isConnected) {
                // Data sudah diupdate via callback
                return;
            }
        }
    } catch (error) {
        console.log('Error checking Enhanced Web Serial status in timbangan 2, using fallback:', error);
    }

    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightDisplay2 = document.getElementById('weightDisplay2');
    const weightStatus2 = document.getElementById('weightStatus2');
    const toggleBtn = document.getElementById('toggleConnection2');
    const beratInput2 = document.getElementById('beratInput2');

    // If no serial connector is available, show 0 weight
    if (!window.serialConnector2) {
        if (weightDisplay2Large) weightDisplay2Large.textContent = '0 Kg';
        if (weightDisplay2) weightDisplay2.value = '0 Kg';
        if (weightStatus2) {
            weightStatus2.textContent = 'Indicator Tidak Terhubung';
            weightStatus2.className = 'text-danger opacity-75';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Connect Indicator';
            toggleBtn.className = 'btn btn-outline-info btn-sm';
        }
        return;
    }

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: { action: 'get_indicator_status' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const weight = response.data.weight;

                // Update weight display dengan lock indicator
                if (weightDisplay2Large) {
                    if (window.isWeightLocked2) {
                        weightDisplay2Large.innerHTML = `${weight.toLocaleString('id-ID')} Kg<br><small style="color: #ef4444;">(Locked: ${window.capturedWeight2.toLocaleString('id-ID')} Kg)</small>`;
                        weightDisplay2Large.style.color = '#fbbf24';
                    } else {
                        weightDisplay2Large.textContent = weight.toLocaleString('id-ID') + ' Kg';
                    }
                }

                if (weightDisplay2) {
                    if (window.isWeightLocked2) {
                        weightDisplay2.value = `${weight.toLocaleString('id-ID')} Kg (Locked: ${window.capturedWeight2.toLocaleString('id-ID')} Kg)`;
                    } else {
                        weightDisplay2.value = weight.toLocaleString('id-ID') + ' Kg';
                    }
                }

                // Update connection status
                if (response.data.connected) {
                    if (weightStatus2) {
                        if (window.isWeightLocked2) {
                            weightStatus2.textContent = 'Berat terkunci - Terhubung via Bridge Service';
                            weightStatus2.className = 'text-warning opacity-75';
                        } else {
                            weightStatus2.textContent = 'Terhubung via Bridge Service';
                            weightStatus2.className = 'text-success opacity-75';
                        }
                    }
                    if (toggleBtn) {
                        toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Disconnect Indicator';
                        toggleBtn.className = 'btn btn-outline-danger btn-sm';
                    }
                } else {
                    if (weightStatus2) {
                        weightStatus2.textContent = 'Tidak terhubung';
                        weightStatus2.className = 'text-danger opacity-75';
                    }
                    if (toggleBtn) {
                        toggleBtn.innerHTML = '<i class="fas fa-plug me-2"></i>Connect Indicator';
                        toggleBtn.className = 'btn btn-outline-info btn-sm';
                    }
                }

                // JANGAN UPDATE FORM INPUT JIKA SUDAH DI-LOCK
                if (beratInput2 && !window.isWeightLocked2) {
                    currentWeight2 = weight;
                    lastWeightUpdate2 = Date.now();
                } else if (beratInput2 && window.isWeightLocked2) {
                    // Pastikan form input tetap menampilkan berat yang di-lock
                    beratInput2.value = window.capturedWeight2;
                }
            } else {
                if (weightStatus2) {
                    weightStatus2.textContent = 'AJAX Error';
                    weightStatus2.className = 'text-danger opacity-75';
                }
            }
        },
        error: function(xhr, status, error) {
            if (weightStatus2) {
                weightStatus2.textContent = 'Connection Error: ' + error;
                weightStatus2.className = 'text-danger opacity-75';
            }
        }
    });
}

// Simulasi berat timbangan 2 dengan kontrol
let weightInterval2 = null;
let isCaptured2 = false;

function updateWeight2() {
    updateWeightDisplay2();
}

// Start initial weight update
console.log('Starting Timbangan2 weight updates...');
weightInterval2 = setInterval(updateWeight2, 2000);
updateWeight2(); // Initial call

// Capture berat timbangan 2
document.getElementById('captureWeight2').addEventListener('click', function() {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const beratInput2 = document.getElementById('beratInput2');
    const weightDisplay2 = document.getElementById('weightDisplay2');

    if (!weightDisplay2Large || !beratInput2) {
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
    if (serialConnector2 && serialConnector2.isConnected) {
        isConnected = true;
    } else if (window.WeightIndicators) {
        isConnected = window.WeightIndicators.get('timbangan2') && window.WeightIndicators.get('timbangan2').isConnected;
    }

    if (!isConnected) {
        showNotification2('Silakan hubungkan ke indikator terlebih dahulu', 'warning');
        return;
    }

    // Check if we have recent weight data
    const now = Date.now();
    const timeSinceLastUpdate = now - lastWeightUpdate2;

    if (timeSinceLastUpdate > 5000) { // 5 seconds
        showNotification2('Data timbangan terlalu lama. Pastikan indikator terhubung.', 'warning');
        return;
    }

    const weightValue = currentWeight2;

    if (weightValue <= 0) {
        showNotification2('Berat tidak valid. Silakan coba lagi.', 'warning');
        return;
    }

    // SIMPAN BERAT YANG SUDAH DI-CAPTURE
    window.capturedWeight2 = weightValue; // Global variable untuk menyimpan berat yang di-lock

    // Set captured state
    isCaptured2 = true;
    beratInput2.value = weightValue;
    beratInput2.readOnly = true; // FORM INPUT DI-LOCK SEHINGGA TIDAK BISA DIUBAH
    beratInput2.style.backgroundColor = '#2d3748'; // Visual feedback bahwa form terkunci
    beratInput2.style.border = '2px solid #22c55e'; // Border hijau menandakan terkunci

    // Update display juga agar sama
    if (weightDisplay2) {
        if (window.WeightIndicatorUtils) {
            weightDisplay2.value = window.WeightIndicatorUtils.formatWeight(weightValue);
        } else {
            weightDisplay2.value = weightValue.toLocaleString('id-ID') + ' Kg';
        }
    }

    // Visual feedback - stop animation
    weightDisplay2Large.classList.add('captured');
    weightDisplay2Large.style.color = '#22c55e';
    weightDisplay2Large.style.textShadow = '0 0 20px rgba(34, 197, 94, 0.5)';

    // Visual feedback button
    this.innerHTML = '<i class="fas fa-lock me-2"></i>BERAT TERKUNCI!';
    this.classList.remove('btn-outline-warning');
    this.classList.add('btn-success');
    this.disabled = true;

    // Stop weight update interval untuk display, tapi tetap terima data untuk monitoring
    if (weightInterval2) {
        clearInterval(weightInterval2);
        weightInterval2 = null;
    }

    // Set flag bahwa berat sudah di-lock
    window.isWeightLocked2 = true;

    // Hitung otomatis setelah capture
    hitungOtomatis();

    // Show success toast
    showNotification2(`Berat ${window.WeightIndicatorUtils ? window.WeightIndicatorUtils.formatWeight(weightValue) : weightValue.toLocaleString('id-ID') + ' Kg'} berhasil di-capture dan dikunci!`, 'success');
});

// Hitung otomatis dengan formula yang benar
function hitungOtomatis() {
    const tiketSelector = document.getElementById('tiketSelector');
    const persenPotonganElement = document.getElementById('persenPotongan');
    const beratInput2Element = document.getElementById('beratInput2');

    if (!tiketSelector || !tiketSelector.value) return;

    const selectedOption = tiketSelector.options[tiketSelector.selectedIndex];
    const berat1 = parseInt(selectedOption.dataset.berat) || 0; // Bruto
    const harga = parseInt(selectedOption.dataset.harga) || 0;
    const persenPotongan = persenPotonganElement ? parseFloat(persenPotonganElement.value) || 0 : 0;
    const berat2 = beratInput2Element ? parseInt(beratInput2Element.value) || 0 : 0; // Tara

    // Formula yang benar:
    // Timbangan 1 = BRUTO (Truck Penuh), Timbangan 2 = TARA (Truck Kosong)
    // 1. Bruto - Tara = Netto
    // 2. Netto x (Potongan % / 100) = Potongan (kg)
    // 3. Netto - Potongan (kg) = Netto Akhir
    // 4. Netto Akhir x Harga per kg = Total Harga

    const bruto = berat1; // Timbangan 1 = BRUTO (Truck Penuh)
    const tara = berat2;  // Timbangan 2 = TARA (Truck Kosong)
    const netto = bruto - tara; // Netto
    const potonganKg = (persenPotongan / 100) * netto; // Potongan dalam kg
    const nettoAkhir = netto - potonganKg; // Netto Akhir
    const totalHarga = nettoAkhir * harga; // Total Harga

    // Update display dengan safe access
    const elements = {
        hasilBruto: document.getElementById('hasilBruto'),
        hasilTara: document.getElementById('hasilTara'),
        hasilNettoBT: document.getElementById('hasilNettoBT'),
        hasilPotongan: document.getElementById('hasilPotongan'),
        hasilNettoAkhir: document.getElementById('hasilNettoAkhir'),
        hasilTotalHarga: document.getElementById('hasilTotalHarga')
    };

    if (elements.hasilBruto) elements.hasilBruto.textContent = bruto.toLocaleString('id-ID') + ' Kg';
    if (elements.hasilTara) elements.hasilTara.textContent = tara.toLocaleString('id-ID') + ' Kg';
    if (elements.hasilNettoBT) elements.hasilNettoBT.textContent = netto.toLocaleString('id-ID') + ' Kg';
    if (elements.hasilPotongan) elements.hasilPotongan.textContent = potonganKg.toFixed(2).toLocaleString('id-ID') + ' Kg';
    if (elements.hasilNettoAkhir) elements.hasilNettoAkhir.textContent = nettoAkhir.toFixed(2).toLocaleString('id-ID') + ' Kg';
    if (elements.hasilTotalHarga) elements.hasilTotalHarga.textContent = 'Rp ' + Math.round(totalHarga).toLocaleString('id-ID');
}

// Event listeners untuk perhitungan otomatis
const persenPotonganElement = document.getElementById('persenPotongan');
if (persenPotonganElement) {
    persenPotonganElement.addEventListener('input', hitungOtomatis);
}

function resetHasilPerhitungan() {
    const elements = {
        hasilBruto: document.getElementById('hasilBruto'),
        hasilTara: document.getElementById('hasilTara'),
        hasilNettoBT: document.getElementById('hasilNettoBT'),
        hasilPotongan: document.getElementById('hasilPotongan'),
        hasilNettoAkhir: document.getElementById('hasilNettoAkhir'),
        hasilTotalHarga: document.getElementById('hasilTotalHarga')
    };

    if (elements.hasilBruto) elements.hasilBruto.textContent = '0 Kg';
    if (elements.hasilTara) elements.hasilTara.textContent = '0 Kg';
    if (elements.hasilNettoBT) elements.hasilNettoBT.textContent = '0 Kg';
    if (elements.hasilPotongan) elements.hasilPotongan.textContent = '0 Kg';
    if (elements.hasilNettoAkhir) elements.hasilNettoAkhir.textContent = '0 Kg';
    if (elements.hasilTotalHarga) elements.hasilTotalHarga.textContent = 'Rp 0';
}

// Form validation
const timbangan2Form = document.getElementById('timbangan2Form');
if (timbangan2Form) {
    timbangan2Form.addEventListener('submit', function(e) {
        const beratInput2Element = document.getElementById('beratInput2');
        const tiketSelectorElement = document.getElementById('tiketSelector');

        if (!beratInput2Element || !tiketSelectorElement) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Form element tidak ditemukan. Silakan refresh halaman.',
                confirmButtonColor: '#dc2626'
            });
            return;
        }

        const berat2 = beratInput2Element.value;
        const selectedTiket = tiketSelectorElement.value;

        // Validasi tiket harus dipilih
        if (!selectedTiket) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan pilih nomor tiket terlebih dahulu!',
                confirmButtonColor: '#dc2626'
            });
            return;
        }

        // Validasi berat 2 harus di-capture (tanpa minimal berat)
        if (berat2 === '0' || berat2 === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan capture berat timbangan 2 terlebih dahulu!',
                confirmButtonColor: '#dc2626'
            });
            return;
        }

        // Validasi persen potongan
        const persenPotongan = persenPotonganElement ? parseFloat(persenPotonganElement.value) || 0 : 0;
        if (persenPotongan < 0 || persenPotongan > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Persen potongan harus antara 0% - 100%!',
                confirmButtonColor: '#dc2626'
            });
            return;
        }

        // Konfirmasi sebelum simpan
        e.preventDefault();

        // Get data for confirmation
        const selectedOption = tiketSelectorElement.options[tiketSelectorElement.selectedIndex];
        const noKendaraan = selectedOption.dataset.kendaraan || 'N/A';
        const namaSuplier = selectedOption.dataset.suplier || 'N/A';
        const berat1 = parseInt(selectedOption.dataset.berat) || 0;
        const harga = parseInt(selectedOption.dataset.harga) || 0;

        const berat2Int = parseInt(berat2);
        // CORRECT: Timbangan 1 = BRUTO, Timbangan 2 = TARA
        const bruto = berat1;     // Timbangan 1 (Penuh)
        const tara = berat2Int;   // Timbangan 2 (Kosong)
        const netto = bruto - tara;
        const potonganKg = (persenPotongan / 100) * netto;
        const nettoAkhir = netto - potonganKg;
        const totalHarga = nettoAkhir * harga;

        Swal.fire({
            title: 'Simpan Data Timbangan 2?',
            html: `
                <div style="text-align: left;">
                    <p><strong>No. Kendaraan:</strong> ${noKendaraan}</p>
                    <p><strong>Suplier:</strong> ${namaSuplier}</p>
                    <hr>
                    <p><strong>Bruto (Timbang 1):</strong> ${bruto.toLocaleString('id-ID')} Kg</p>
                    <p><strong>Tara (Timbang 2):</strong> ${tara.toLocaleString('id-ID')} Kg</p>
                    <p><strong>Netto:</strong> ${netto.toLocaleString('id-ID')} Kg</p>
                    <p><strong>Potongan ${persenPotongan}%:</strong> ${potonganKg.toFixed(2).toLocaleString('id-ID')} Kg</p>
                    <hr>
                    <p><strong>Netto Akhir:</strong> <span style="color: #22c55e; font-size: 1.2em;">${nettoAkhir.toFixed(2).toLocaleString('id-ID')} Kg</span></p>
                    <p><strong>Total Harga:</strong> <span style="color: #3b82f6; font-size: 1.2em;">Rp ${Math.round(totalHarga).toLocaleString('id-ID')}</span></p>
                </div>
                <p class="text-warning mt-3"><em>Data akan disimpan dan status tiket akan berubah menjadi selesai!</em></p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Simpan & Cetak Struk',
            cancelButtonText: 'Batal',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
}

// Refresh tiket data function
document.getElementById('refreshTiketBtn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;

    // Show loading state
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';
    btn.disabled = true;

    // Clear cache for tiket data
    const cacheKeys = [];
    const currentMinute = new Date().getMinutes();

    // Clear current and previous minute caches
    for (let i = 0; i < 3; i++) {
        const minute = (currentMinute - i + 60) % 60;
        cacheKeys.push(`tiket_list_${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}-${String(new Date().getDate()).padStart(2, '0')}-${String(new Date().getHours()).padStart(2, '0')}-${String(minute).padStart(2, '0')}`);
    }

    // Clear cache via AJAX
    fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=clear_tiket_cache&cache_keys=' + encodeURIComponent(JSON.stringify(cacheKeys))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to refresh data
            window.location.reload();
        } else {
            // Fallback: reload anyway
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error refreshing cache:', error);
        // Fallback: reload anyway
        window.location.reload();
    })
    .finally(() => {
        // Restore button state (in case reload doesn't work)
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }, 2000);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
