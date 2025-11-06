<?php
// modules/timbangan/timbangan_kompak.php
require_once '../../config/database.php';
require_once '../../includes/material_functions.php';
require_once '../../includes/cache_manager.php';

$page_title = 'Timbangan Kompak - All-in-One';
require_once '../../includes/header.php';

// Handle form submission untuk timbangan 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'timbang1') {
    $no_kendaraan = clean_input($_POST['no_kendaraan']);
    $nama_pengemudi = clean_input($_POST['nama_pengemudi']);
    $nama_suplier = clean_input($_POST['nama_suplier']);
    $material = clean_input($_POST['material']);
    $harga = clean_input($_POST['harga']);
    $berat = clean_input($_POST['berat']);

    // Validate required fields
    $validation_errors = [];
    if (empty($no_kendaraan)) $validation_errors[] = "Nomor kendaraan wajib diisi";
    if (empty($nama_pengemudi)) $validation_errors[] = "Nama pengemudi wajib diisi";
    if (empty($nama_suplier)) $validation_errors[] = "Nama supplier wajib dipilih";
    if (empty($material)) $material = 'tbs';
    if (empty($harga) || !is_numeric($harga) || $harga <= 0) $validation_errors[] = "Harga harus diisi dengan angka yang valid";
    if (empty($berat) || !is_numeric($berat) || $berat <= 0) $validation_errors[] = "Berat harus diisi dengan angka yang valid";

    if (!empty($validation_errors)) {
        $error_message = "Validasi gagal: " . implode(", ", $validation_errors);
    } else {
        // Generate nomor tiket
        $no_tiket = generate_ticket_number($conn);

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
            // Insert data ke database
            $query = "INSERT INTO transaksi_timbangan
                      (no_tiket, no_polisi, nama_supir, id_supplier, jenis_material, harga_per_kg, berat_bruto, berat_timbangan1, tanggal, created_at, status, timbang1_locked, waktu_timbangan1)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 'timbang_1', 1, NOW())";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssisddd", $no_tiket, $no_kendaraan, $nama_pengemudi, $supplier_id, $material, $harga, $berat, $berat);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Data timbangan 1 berhasil disimpan dengan nomor tiket: " . $no_tiket;
            } else {
                $error_message = "Gagal menyimpan data: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Supplier tidak ditemukan: " . $nama_suplier;
        }
    }
}

// Handle form submission untuk timbangan 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'timbang2') {
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
        $bruto = $data_timbangan1['berat_bruto'];
        $tara = $berat2;
        $netto_bt = $bruto - $tara;
        $total_potongan = ($persen_potongan / 100) * $netto_bt;
        $netto_akhir = $netto_bt - $total_potongan;
        $total_harga = $netto_akhir * $data_timbangan1['harga_per_kg'];

        $no_tiket2 = generate_ticket_number($conn);

        $update_query = "UPDATE transaksi_timbangan SET
                        berat_tara = ?,
                        berat_timbangan2 = ?,
                        timbang2_locked = 1,
                        persen_potongan = ?,
                        kg_potongan = ?,
                        waktu_timbangan2 = NOW(),
                        status = 'selesai'
                        WHERE no_tiket = ?";

        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "dddds", $berat2, $berat2, $persen_potongan, $total_potongan, $no_tiket1);

        if (mysqli_stmt_execute($update_stmt)) {
            $success_message2 = "Data timbangan 2 berhasil disimpan dengan nomor tiket: " . $no_tiket2;
        } else {
            $error_message2 = "Gagal menyimpan data: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    } else {
        $error_message2 = "Data tiket tidak ditemukan atau sudah diproses!";
    }
    mysqli_stmt_close($stmt);
}

// Get data dropdown dengan caching
$cache_key_suplier = 'supplier_list_' . date('Y-m-d');
$suplier_list = cache_get($cache_key_suplier);
if ($suplier_list === null) {
    $suplier_list = [];
    $query = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $suplier_list[] = $row;
    }
    cache_set($cache_key_suplier, $suplier_list, 3600);
}

$cache_key_tiket = 'tiket_list_' . date('Y-m-d-H-i'); // Cache per menit
$tiket_list = cache_get($cache_key_tiket);
if ($tiket_list === null) {
    $tiket_list = [];
    $query = "SELECT tt.no_tiket, tt.no_polisi, tt.nama_supir, s.nama_supplier, tt.jenis_material, tt.berat_bruto, tt.harga_per_kg, tt.tanggal
              FROM transaksi_timbangan tt
              LEFT JOIN supplier s ON tt.id_supplier = s.id
              WHERE tt.status = 'timbang_1' AND tt.timbang1_locked = 1
              ORDER BY tt.created_at DESC
              LIMIT 50";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $tiket_list[] = $row;
    }
    cache_set($cache_key_tiket, $tiket_list, 300); // Cache for 5 minutes
}
?>

<style>
/* Compact Layout Styles */
body {
    overflow: hidden;
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
}

.main-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 0;
    margin: 0;
}

.header-compact {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    padding: 8px 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.header-compact h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.content-compact {
    flex: 1;
    display: flex;
    padding: 10px;
    gap: 15px;
    overflow: hidden;
}

.timbangan-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #1a1a1a;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #2a2a2a, #1f1f1f);
    padding: 12px 16px;
    border-bottom: 2px solid #dc2626;
    display: flex;
    justify-content: between;
    align-items: center;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: #fff;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #16a34a;
    color: white;
}

.status-inactive {
    background: #dc2626;
    color: white;
}

.section-content {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
}

/* Form Styles Compact */
.form-compact .row {
    margin-bottom: 8px;
}

.form-compact .form-label {
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 4px;
    color: #fbbf24;
    text-transform: uppercase;
}

.form-compact .form-control,
.form-compact .form-select {
    background: #2a2a2a;
    border: 1px solid #495057;
    color: #fff;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.form-compact .form-control:focus,
.form-compact .form-select:focus {
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);
    background-color: #333;
}

/* Weight Display Compact */
.weight-display-compact {
    background: #1f1f1f;
    border: 2px solid #495057;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    margin-bottom: 10px;
    position: relative;
    overflow: hidden;
}

.weight-display-compact::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #dc2626, #b91c1c);
}

.weight-value-compact {
    font-size: 2rem;
    font-weight: 700;
    color: #22c55e;
    font-family: 'Courier New', monospace;
    text-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
    margin: 5px 0;
}

.weight-status-compact {
    font-size: 0.75rem;
    color: #aaa;
    margin-bottom: 10px;
}

.weight-controls-compact {
    display: flex;
    gap: 8px;
}

.btn-compact {
    padding: 8px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    text-transform: uppercase;
    transition: all 0.2s ease;
    flex: 1;
}

.btn-connect-compact {
    background: #3b82f6;
    color: white;
}

.btn-connect-compact:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-disconnect-compact {
    background: #dc2626;
    color: white;
}

.btn-capture-compact {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    font-weight: 700;
}

.btn-capture-compact:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-1px);
}

.btn-capture-compact.captured {
    background: #16a34a;
    cursor: not-allowed;
}

/* Calculation Results Compact */
.calculation-compact {
    background: #1f1f1f;
    border-radius: 8px;
    padding: 12px;
    margin-top: 10px;
}

.calculation-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #333;
}

.calculation-row:last-child {
    border-bottom: none;
    padding-top: 8px;
    margin-top: 4px;
    border-top: 2px solid #dc2626;
}

.calc-label-compact {
    font-size: 0.75rem;
    color: #aaa;
}

.calc-value-compact {
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
}

.calc-total-compact .calc-label-compact {
    color: #fbbf24;
    font-weight: 700;
}

.calc-total-compact .calc-value-compact {
    color: #22c55e;
    font-size: 1rem;
}

/* Action Buttons */
.action-buttons-compact {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-action-compact {
    flex: 1;
    padding: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 8px;
    border: none;
    text-transform: uppercase;
    transition: all 0.2s ease;
}

.btn-primary-compact {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

.btn-primary-compact:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-secondary-compact {
    background: #495057;
    color: white;
}

.btn-secondary-compact:hover {
    background: #343a40;
    transform: translateY(-1px);
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .content-compact {
        flex-direction: column;
    }

    .timbangan-section {
        max-height: 50vh;
    }
}

/* Scrollbar styling */
.section-content::-webkit-scrollbar {
    width: 6px;
}

.section-content::-webkit-scrollbar-track {
    background: #1f1f1f;
}

.section-content::-webkit-scrollbar-thumb {
    background: #495057;
    border-radius: 3px;
}

.section-content::-webkit-scrollbar-thumb:hover {
    background: #6c757d;
}

/* Alert styling compact */
.alert-compact {
    padding: 8px 12px;
    font-size: 0.85rem;
    border-radius: 6px;
    margin-bottom: 10px;
    border: none;
}

.alert-success-compact {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border-left: 3px solid #22c55e;
}

.alert-error-compact {
    background: rgba(220, 38, 38, 0.1);
    color: #dc2626;
    border-left: 3px solid #dc2626;
}
</style>

<div class="main-container">
    <!-- Compact Header -->
    <div class="header-compact">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="text-white mb-0">
                    <i class="fas fa-weight me-2"></i>SISTEM TIMBANGAN KOMPAK
                </h5>
                <small class="text-light opacity-75">All-in-One Weighing System</small>
            </div>
            <div>
                <div class="badge bg-danger" id="currentDateTime"></div>
            </div>
        </div>
    </div>

    <!-- Content Area -->
    <div class="content-compact">
        <!-- TIMBANGAN 1 SECTION -->
        <div class="timbangan-section">
            <div class="section-header">
                <h6 class="section-title">
                    <i class="fas fa-weight me-2"></i>TIMBANGAN 1 - BRUTO
                </h6>
                <span class="status-badge status-inactive" id="status1">MENUNGGU</span>
            </div>

            <div class="section-content">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-compact alert-success-compact">
                        <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-compact alert-error-compact">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                    </div>
                <?php endif; ?>

                <!-- Weight Display Timbangan 1 -->
                <div class="weight-display-compact">
                    <div class="weight-value-compact" id="weightDisplay1">0 Kg</div>
                    <div class="weight-status-compact" id="weightStatus1">Indicator Tidak Terhubung</div>
                    <div class="weight-controls-compact">
                        <button type="button" class="btn btn-compact btn-connect-compact" id="toggleConnection1">
                            <i class="fas fa-plug me-1"></i>CONNECT
                        </button>
                        <button type="button" class="btn btn-compact btn-capture-compact" id="captureWeight1">
                            <i class="fas fa-camera me-1"></i>CAPTURE
                        </button>
                    </div>
                </div>

                <!-- Form Timbangan 1 -->
                <form method="POST" class="form-compact" id="timbangan1Form">
                    <input type="hidden" name="action" value="timbang1">

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">No. Kendaraan</label>
                            <input type="text" name="no_kendaraan" class="form-control" placeholder="BM 1234 ABC" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Pengemudi</label>
                            <input type="text" name="nama_pengemudi" class="form-control" placeholder="Nama pengemudi" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Supplier</label>
                            <select name="nama_suplier" class="form-select" required>
                                <option value="">Pilih Suplier</option>
                                <?php foreach ($suplier_list as $suplier): ?>
                                    <option value="<?= $suplier['nama_supplier'] ?>"><?= $suplier['nama_supplier'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Material</label>
                            <select name="material" class="form-select" required>
                                <?php echo get_material_options('tbs'); ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Harga per Kg</label>
                            <input type="text" name="harga_display" class="form-control" id="hargaInput1" placeholder="Rp 0" required>
                            <input type="hidden" name="harga" id="hargaValue1" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Berat (Kg)</label>
                            <input type="number" class="form-control" name="berat" id="beratInput1" value="0" readonly>
                        </div>
                    </div>

                    <div class="action-buttons-compact">
                        <button type="button" class="btn btn-action-compact btn-secondary-compact" onclick="resetForm1()">
                            <i class="fas fa-redo me-2"></i>RESET
                        </button>
                        <button type="submit" class="btn btn-action-compact btn-primary-compact">
                            <i class="fas fa-save me-2"></i>SIMPAN T1
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TIMBANGAN 2 SECTION -->
        <div class="timbangan-section">
            <div class="section-header">
                <h6 class="section-title">
                    <i class="fas fa-weight me-2"></i>TIMBANGAN 2 - TARA
                </h6>
                <span class="status-badge status-inactive" id="status2">MENUNGGU</span>
            </div>

            <div class="section-content">
                <?php if (isset($success_message2)): ?>
                    <div class="alert alert-success alert-compact alert-success-compact">
                        <i class="fas fa-check-circle me-2"></i><?= $success_message2 ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message2)): ?>
                    <div class="alert alert-danger alert-compact alert-error-compact">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error_message2 ?>
                    </div>
                <?php endif; ?>

                <!-- Tiket Selection -->
                <div class="mb-3">
                    <label class="form-label">
                        Pilih Tiket Timbang 1
                        <button type="button" class="btn btn-sm btn-outline-info ms-2" id="refreshTiketBtnKompak" title="Refresh Data Tiket">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </label>
                    <select class="form-select" id="tiketSelectorKompak">
                        <option value="">-- Pilih Tiket --</option>
                        <?php foreach ($tiket_list as $tiket): ?>
                            <option value="<?= $tiket['no_tiket'] ?>"
                                    data-kendaraan="<?= $tiket['no_polisi'] ?>"
                                    data-pengemudi="<?= $tiket['nama_supir'] ?>"
                                    data-suplier="<?= $tiket['nama_supplier'] ?>"
                                    data-material="<?= $tiket['jenis_material'] ?>"
                                    data-harga="<?= $tiket['harga_per_kg'] ?>"
                                    data-berat="<?= $tiket['berat_bruto'] ?>">
                                <?= $tiket['no_tiket'] ?> - <?= $tiket['no_polisi'] ?> (<?= $tiket['berat_bruto'] ?> Kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1">
                        <i class="fas fa-info-circle"></i> Data refresh setiap 5 menit. Klik refresh untuk update langsung.
                    </small>
                </div>

                <!-- Data Timbangan 1 (Readonly) -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">No. Kendaraan</label>
                        <input type="text" class="form-control" id="displayKendaraanKompak" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Pengemudi</label>
                        <input type="text" class="form-control" id="displayPengemudiKompak" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" id="displaySuplierKompak" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Material</label>
                        <input type="text" class="form-control" id="displayMaterialKompak" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Berat Timbangan 1</label>
                        <input type="text" class="form-control bg-dark text-success" id="displayBerat1Kompak" readonly>
                    </div>
                </div>

                <!-- Weight Display Timbangan 2 -->
                <div class="weight-display-compact">
                    <div class="weight-value-compact" id="weightDisplay2">0 Kg</div>
                    <div class="weight-status-compact" id="weightStatus2">Indicator Tidak Terhubung</div>
                    <div class="weight-controls-compact">
                        <button type="button" class="btn btn-compact btn-connect-compact" id="toggleConnection2">
                            <i class="fas fa-plug me-1"></i>CONNECT
                        </button>
                        <button type="button" class="btn btn-compact btn-capture-compact" id="captureWeight2">
                            <i class="fas fa-camera me-1"></i>CAPTURE
                        </button>
                    </div>
                </div>

                <!-- Form Timbangan 2 -->
                <form method="POST" class="form-compact" id="timbangan2Form">
                    <input type="hidden" name="action" value="timbang2">

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">No. Tiket</label>
                            <input type="text" name="no_tiket1" id="tiketInput2" readonly required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Potongan (%)</label>
                            <input type="number" name="persen_potongan" class="form-control" value="0" step="0.01" min="0" max="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Berat Timbangan 2 (Kg)</label>
                            <input type="number" class="form-control bg-dark text-warning" id="beratInput2" name="berat2" value="0" readonly>
                        </div>
                    </div>

                    <!-- Calculation Results -->
                    <div class="calculation-compact">
                        <div class="calculation-row">
                            <span class="calc-label-compact">Bruto (T1)</span>
                            <span class="calc-value-compact" id="hasilBrutoKompak">0 Kg</span>
                        </div>
                        <div class="calculation-row">
                            <span class="calc-label-compact">Tara (T2)</span>
                            <span class="calc-value-compact" id="hasilTaraKompak">0 Kg</span>
                        </div>
                        <div class="calculation-row">
                            <span class="calc-label-compact">Netto</span>
                            <span class="calc-value-compact" id="hasilNettoKompak">0 Kg</span>
                        </div>
                        <div class="calculation-row">
                            <span class="calc-label-compact">Potongan</span>
                            <span class="calc-value-compact text-danger" id="hasilPotonganKompak">0 Kg</span>
                        </div>
                        <div class="calculation-row calc-total-compact">
                            <span class="calc-label-compact">Netto Akhir</span>
                            <span class="calc-value-compact" id="hasilNettoAkhirKompak">0 Kg</span>
                        </div>
                        <div class="calculation-row calc-total-compact">
                            <span class="calc-label-compact">Total Harga</span>
                            <span class="calc-value-compact text-info" id="hasilTotalHargaKompak">Rp 0</span>
                        </div>
                    </div>

                    <div class="action-buttons-compact">
                        <a href="timbangan1.php" class="btn btn-action-compact btn-secondary-compact">
                            <i class="fas fa-arrow-left me-2"></i>KEMBALI
                        </a>
                        <button type="submit" class="btn btn-action-compact btn-primary-compact" id="saveButton2" disabled>
                            <i class="fas fa-save me-2"></i>SIMPAN & SELESAI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
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

// Material codes
const materialCodes = <?php echo get_material_js_mapping(); ?>;

// Format Rupiah Timbangan 1
document.getElementById('hargaInput1').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value) {
        e.target.value = 'Rp ' + parseInt(value).toLocaleString('id-ID');
        document.getElementById('hargaValue1').value = parseInt(value);
    } else {
        e.target.value = 'Rp 0';
        document.getElementById('hargaValue1').value = '0';
    }
});

// Load serial modules untuk timbangan 1
function loadSerialModules1() {
    if (document.getElementById('weightDisplay1')) {
        const script1 = document.createElement('script');
        script1.src = '<?php echo BASE_URL; ?>js/auto-serial-connect.js';
        script1.async = true;
        document.head.appendChild(script1);

        script1.onload = function() {
            const script2 = document.createElement('script');
            script2.src = '<?php echo BASE_URL; ?>assets/js/enhanced-web-serial-api.js';
            script2.async = true;
            document.head.appendChild(script2);

            script2.onload = function() {
                setTimeout(function() {
                    initializeAutoSerialConnectorKompak1();
                }, 100);
            };
        };
    }
}

// Load serial modules untuk timbangan 2
function loadSerialModules2() {
    if (document.getElementById('weightDisplay2')) {
        const script1 = document.createElement('script');
        script1.src = '<?php echo BASE_URL; ?>js/auto-serial-connect.js';
        script1.async = true;
        document.head.appendChild(script1);

        script1.onload = function() {
            const script2 = document.createElement('script');
            script2.src = '<?php echo BASE_URL; ?>assets/js/enhanced-web-serial-api.js';
            script2.async = true;
            document.head.appendChild(script2);

            script2.onload = function() {
                setTimeout(function() {
                    initializeAutoSerialConnectorKompak2();
                }, 100);
            };
        };
    }
}

// Initialize Auto Serial Connector untuk timbangan 1
async function initializeAutoSerialConnectorKompak1() {
    if (!window.AutoSerialConnector) return false;

    if (!window.serialConnectorKompak1) {
        window.serialConnectorKompak1 = new AutoSerialConnector({
            targetInputId: 'beratInput1',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                console.log('✅ Timbangan 1 Connected');
                updateConnectionUI1(true);
                updateStatus1('TERHUBUNG');
            },
            onDisconnect: () => {
                console.log('❌ Timbangan 1 Disconnected');
                updateConnectionUI1(false);
                updateStatus1('TERPUTUS');
            },
            onData: (weight) => {
                console.log('📈 Timbangan 1 Weight:', weight);
                updateWeightDisplay1(weight);
            },
            onError: (error) => {
                console.error('❌ Timbangan 1 Error:', error);
                showNotification('Error Timbangan 1: ' + error.message, 'error');
                updateConnectionUI1(false);
                updateStatus1('ERROR');
            }
        });
    }

    const autoConnected = await window.serialConnectorKompak1.autoConnect();
    if (!autoConnected) {
        setTimeout(() => {
            if (!window.serialConnectorKompak1 || !window.serialConnectorKompak1.isConnected) {
                initializeEnhancedWebSerialTimbangan1();
            }
        }, 500);
    }
    return autoConnected;
}

// Initialize Auto Serial Connector untuk timbangan 2
async function initializeAutoSerialConnectorKompak2() {
    if (!window.AutoSerialConnector) return false;

    if (!window.serialConnectorKompak2) {
        window.serialConnectorKompak2 = new AutoSerialConnector({
            targetInputId: 'beratInput2',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                console.log('✅ Timbangan 2 Connected');
                updateConnectionUI2(true);
                updateStatus2('TERHUBUNG');
            },
            onDisconnect: () => {
                console.log('❌ Timbangan 2 Disconnected');
                updateConnectionUI2(false);
                updateStatus2('TERPUTUS');
            },
            onData: (weight) => {
                console.log('📈 Timbangan 2 Weight:', weight);
                updateWeightDisplay2(weight);
            },
            onError: (error) => {
                console.error('❌ Timbangan 2 Error:', error);
                showNotification('Error Timbangan 2: ' + error.message, 'error');
                updateConnectionUI2(false);
                updateStatus2('ERROR');
            }
        });
    }

    const autoConnected = await window.serialConnectorKompak2.autoConnect();
    if (!autoConnected) {
        setTimeout(() => {
            if (!window.serialConnectorKompak2 || !window.serialConnectorKompak2.isConnected) {
                initializeEnhancedWebSerialTimbangan2();
            }
        }, 500);
    }
    return autoConnected;
}

// Update weight displays
function updateWeightDisplay1(weight) {
    document.getElementById('weightDisplay1').textContent = weight.toLocaleString('id-ID') + ' Kg';
}

function updateWeightDisplay2(weight) {
    document.getElementById('weightDisplay2').textContent = weight.toLocaleString('id-ID') + ' Kg';
}

// Update connection UI
function updateConnectionUI1(connected) {
    const toggleBtn = document.getElementById('toggleConnection1');
    if (connected) {
        toggleBtn.innerHTML = '<i class="fas fa-plug me-1"></i>DISCONNECT';
        toggleBtn.className = 'btn btn-compact btn-disconnect-compact';
        document.getElementById('weightStatus1').textContent = 'Terhubung ke Sonic A283';
    } else {
        toggleBtn.innerHTML = '<i class="fas fa-plug me-1"></i>CONNECT';
        toggleBtn.className = 'btn btn-compact btn-connect-compact';
        document.getElementById('weightStatus1').textContent = 'Indicator Tidak Terhubung';
    }
}

function updateConnectionUI2(connected) {
    const toggleBtn = document.getElementById('toggleConnection2');
    if (connected) {
        toggleBtn.innerHTML = '<i class="fas fa-plug me-1"></i>DISCONNECT';
        toggleBtn.className = 'btn btn-compact btn-disconnect-compact';
        document.getElementById('weightStatus2').textContent = 'Terhubung ke Sonic A283';
    } else {
        toggleBtn.innerHTML = '<i class="fas fa-plug me-1"></i>CONNECT';
        toggleBtn.className = 'btn btn-compact btn-connect-compact';
        document.getElementById('weightStatus2').textContent = 'Indicator Tidak Terhubung';
    }
}

// Update status badges
function updateStatus1(status) {
    const badge = document.getElementById('status1');
    badge.textContent = status;
    if (status === 'TERHUBUNG') {
        badge.className = 'status-badge status-active';
    } else {
        badge.className = 'status-badge status-inactive';
    }
}

function updateStatus2(status) {
    const badge = document.getElementById('status2');
    badge.textContent = status;
    if (status === 'TERHUBUNG') {
        badge.className = 'status-badge status-active';
    } else {
        badge.className = 'status-badge status-inactive';
    }
}

// Toggle connection buttons
document.getElementById('toggleConnection1').addEventListener('click', async function() {
    if (window.serialConnectorKompak1) {
        if (window.serialConnectorKompak1.isConnected) {
            await window.serialConnectorKompak1.disconnect();
            updateConnectionUI1(false);
            updateStatus1('TERPUTUS');
        } else {
            const success = await window.serialConnectorKompak1.manualConnect();
            if (success) {
                updateConnectionUI1(true);
                updateStatus1('TERHUBUNG');
            }
        }
    }
});

document.getElementById('toggleConnection2').addEventListener('click', async function() {
    if (window.serialConnectorKompak2) {
        if (window.serialConnectorKompak2.isConnected) {
            await window.serialConnectorKompak2.disconnect();
            updateConnectionUI2(false);
            updateStatus2('TERPUTUS');
        } else {
            const success = await window.serialConnectorKompak2.manualConnect();
            if (success) {
                updateConnectionUI2(true);
                updateStatus2('TERHUBUNG');
            }
        }
    }
});

// Capture weight buttons
document.getElementById('captureWeight1').addEventListener('click', function() {
    const beratInput = document.getElementById('beratInput1');
    const weightDisplay = document.getElementById('weightDisplay1');

    const isConnected = window.serialConnectorKompak1 && window.serialConnectorKompak1.isConnected;
    if (!isConnected) {
        showNotification('Silakan hubungkan ke indikator terlebih dahulu', 'warning');
        return;
    }

    const weightText = weightDisplay.textContent;
    const weight = parseFloat(weightText.replace(/[^\d.-]/g, ''));

    if (weight > 0) {
        beratInput.value = weight;
        this.classList.add('captured');
        this.innerHTML = '<i class="fas fa-check me-1"></i>TERTANGKAP!';
        showNotification(`Berat ${weight.toLocaleString('id-ID')} Kg berhasil di-capture!`, 'success');
    } else {
        showNotification('Berat tidak valid. Silakan coba lagi.', 'warning');
    }
});

document.getElementById('captureWeight2').addEventListener('click', function() {
    const beratInput = document.getElementById('beratInput2');
    const weightDisplay = document.getElementById('weightDisplay2');

    const isConnected = window.serialConnectorKompak2 && window.serialConnectorKompak2.isConnected;
    if (!isConnected) {
        showNotification('Silakan hubungkan ke indikator terlebih dahulu', 'warning');
        return;
    }

    const weightText = weightDisplay.textContent;
    const weight = parseFloat(weightText.replace(/[^\d.-]/g, ''));

    if (weight > 0) {
        beratInput.value = weight;
        this.classList.add('captured');
        this.innerHTML = '<i class="fas fa-check me-1"></i>TERTANGKAP!';
        hitungOtomatisKompak();
        showNotification(`Berat ${weight.toLocaleString('id-ID')} Kg berhasil di-capture!`, 'success');
    } else {
        showNotification('Berat tidak valid. Silakan coba lagi.', 'warning');
    }
});

// Tiket selector untuk timbangan 2
document.getElementById('tiketSelectorKompak').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    if (this.value) {
        const displayKendaraan = document.getElementById('displayKendaraanKompak');
        const displayPengemudi = document.getElementById('displayPengemudiKompak');
        const displaySuplier = document.getElementById('displaySuplierKompak');
        const displayMaterial = document.getElementById('displayMaterialKompak');
        const displayBerat1 = document.getElementById('displayBerat1Kompak');
        const tiketInput = document.getElementById('tiketInput2');
        const saveButton = document.getElementById('saveButton2');

        if (displayKendaraan) displayKendaraan.value = selectedOption.dataset.kendaraan || '';
        if (displayPengemudi) displayPengemudi.value = selectedOption.dataset.pengemudi || '';
        if (displaySuplier) displaySuplier.value = selectedOption.dataset.suplier || '';

        const materialCode = selectedOption.dataset.material;
        const materialName = materialCodes[materialCode] || materialCode;
        if (displayMaterial) displayMaterial.value = materialName;

        if (displayBerat1) displayBerat1.value = selectedOption.dataset.berat || '0';
        if (tiketInput) tiketInput.value = this.value;
        if (saveButton) saveButton.disabled = false;

        hitungOtomatisKompak();
    } else {
        // Reset fields
        const fields = ['displayKendaraanKompak', 'displayPengemudiKompak', 'displaySuplierKompak', 'displayMaterialKompak', 'displayBerat1Kompak', 'tiketInput2'];
        fields.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = '';
        });

        document.getElementById('saveButton2').disabled = true;
        resetHasilPerhitunganKompak();
    }
});

// Hitung otomatis
function hitungOtomatisKompak() {
    const bruto = parseFloat(document.getElementById('displayBerat1Kompak').value) || 0;
    const tara = parseFloat(document.getElementById('beratInput2').value) || 0;
    const persenPotongan = parseFloat(document.querySelector('input[name="persen_potongan"]').value) || 0;

    const nettoBT = bruto - tara;
    const totalPotongan = (persenPotongan / 100) * nettoBT;
    const nettoAkhir = nettoBT - totalPotongan;

    // Get harga dari material (dari tiket yang dipilih)
    const selectedOption = document.getElementById('tiketSelectorKompak').options[document.getElementById('tiketSelectorKompak').selectedIndex];
    const harga = parseFloat(selectedOption.dataset.harga) || 0;
    const totalHarga = nettoAkhir * harga;

    // Update display
    document.getElementById('hasilBrutoKompak').textContent = bruto.toLocaleString('id-ID') + ' Kg';
    document.getElementById('hasilTaraKompak').textContent = tara.toLocaleString('id-ID') + ' Kg';
    document.getElementById('hasilNettoKompak').textContent = nettoBT.toLocaleString('id-ID') + ' Kg';
    document.getElementById('hasilPotonganKompak').textContent = totalPotongan.toLocaleString('id-ID') + ' Kg';
    document.getElementById('hasilNettoAkhirKompak').textContent = nettoAkhir.toLocaleString('id-ID') + ' Kg';
    document.getElementById('hasilTotalHargaKompak').textContent = 'Rp ' + totalHarga.toLocaleString('id-ID');
}

function resetHasilPerhitunganKompak() {
    const fields = ['hasilBrutoKompak', 'hasilTaraKompak', 'hasilNettoKompak', 'hasilPotonganKompak', 'hasilNettoAkhirKompak', 'hasilTotalHargaKompak'];
    fields.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            if (id === 'hasilTotalHargaKompak') {
                element.textContent = 'Rp 0';
            } else {
                element.textContent = '0 Kg';
            }
        }
    });
}

// Reset form timbangan 1
function resetForm1() {
    if (confirm('Reset form timbangan 1?')) {
        document.getElementById('timbangan1Form').reset();
        document.getElementById('hargaInput1').value = 'Rp 0';
        document.getElementById('hargaValue1').value = '0';
        document.getElementById('beratInput1').value = '0';

        const captureBtn = document.getElementById('captureWeight1');
        if (captureBtn) {
            captureBtn.classList.remove('captured');
            captureBtn.innerHTML = '<i class="fas fa-camera me-1"></i>CAPTURE';
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

// Load modules saat DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        loadSerialModules1();
        loadSerialModules2();
    });
} else {
    loadSerialModules1();
    loadSerialModules2();
}

// Enhanced Web Serial API fallback functions (simplified versions)
function initializeEnhancedWebSerialTimbangan1() {
    console.log('Enhanced Web Serial fallback for Timbangan 1');
}

function initializeEnhancedWebSerialTimbangan2() {
    console.log('Enhanced Web Serial fallback for Timbangan 2');
}

// Form validation
document.getElementById('timbangan1Form').addEventListener('submit', function(e) {
    const berat = document.getElementById('beratInput1').value;
    if (berat === '0' || berat === '') {
        e.preventDefault();
        showNotification('Silakan capture berat timbangan 1 terlebih dahulu!', 'warning');
        return;
    }
});

document.getElementById('timbangan2Form').addEventListener('submit', function(e) {
    const berat2 = document.getElementById('beratInput2').value;
    if (berat2 === '0' || berat2 === '') {
        e.preventDefault();
        showNotification('Silakan capture berat timbangan 2 terlebih dahulu!', 'warning');
        return;
    }
});

// Refresh tiket data function untuk timbangan kompak
document.getElementById('refreshTiketBtnKompak').addEventListener('click', function() {
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
    fetch('modules/timbangan/ajax.php', {
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