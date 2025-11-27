<?php
// modules/timbangan/timbangan2.php
require_once '../../config/database.php';
require_once '../../includes/material_functions.php';
require_once '../../includes/cache_manager.php';

// Fungsi format Rupiah PHP yang benar (1.000.000, 500.000, 100.000)
function formatRupiah($amount) {
    if ($amount === 0 || !$amount) return 'Rp 0';
    return 'Rp ' . number_format($amount, 0, '.', '.');
}

$page_title = 'Timbangan 2';
require_once '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_tiket1 = clean_input($_POST['no_tiket1']);
    $persen_potongan = floatval(str_replace(',', '.', clean_input($_POST['persen_potongan'])));
    $berat2 = floatval(clean_input($_POST['berat2']));
    $keterangan = clean_input($_POST['keterangan'] ?? '');
    $potong_hutang = floatval(clean_input($_POST['potong_hutang_hidden'] ?? 0));
    $sisa_hutang = floatval(clean_input($_POST['sisa_hutang'] ?? 0));
    $total_akhir2 = floatval(clean_input($_POST['total_akhir2'] ?? 0));

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
        // Simpan semua hasil perhitungan ke database agar konsisten (hanya field yang ada di tabel)
        $update_query = "UPDATE transaksi_timbangan SET
                        berat_tara = ?,
                        berat_timbangan2 = ?,
                        persen_potongan = ?,
                        keterangan = ?,
                        potong_hutang = ?,
                        timbang2_locked = 1,
                        waktu_timbangan2 = NOW(),
                        waktu_keluar = NOW(),
                        status = 'selesai',
                        berat_netto = ?,
                        kg_potongan = ?,
                        total_harga = ?
                        WHERE no_tiket = ? AND timbang1_locked = 1 AND status = 'timbang_1'";

        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "dddsdddds",
            $tara,             // berat_tara
            $tara,             // berat_timbangan2
            $persenPotongan,   // persen_potongan
            $keterangan,       // keterangan
            $potong_hutang,    // potong_hutang
            $berat_netto,      // berat_netto
            $kg_potongan,      // kg_potongan
            $total_harga,      // total_harga
            $no_tiket1         // no_tiket
        );

        if (mysqli_stmt_execute($update_stmt)) {
            // Check if update was successful
            if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                // Update hutang supplier jika ada potongan hutang
                $hutang_message = '';
                if ($potong_hutang > 0 && isset($data_timbangan1['id_supplier'])) {
                    $supplier_id = $data_timbangan1['id_supplier'];

                    // Get current hutang
                    $hutang_query = "SELECT total_hutang FROM supplier WHERE id = ?";
                    $hutang_stmt = mysqli_prepare($conn, $hutang_query);
                    mysqli_stmt_bind_param($hutang_stmt, "i", $supplier_id);
                    mysqli_stmt_execute($hutang_stmt);
                    $hutang_result = mysqli_stmt_get_result($hutang_stmt);
                    $supplier_data = mysqli_fetch_assoc($hutang_result);
                    mysqli_stmt_close($hutang_stmt);

                    if ($supplier_data) {
                        $current_hutang = floatval($supplier_data['total_hutang'] ?? 0);
                        $new_hutang = max(0, $current_hutang - $potong_hutang);

                        // Update supplier hutang
                        $update_hutang_query = "UPDATE supplier SET total_hutang = ?, hutang_terakhir_update = NOW() WHERE id = ?";
                        $update_hutang_stmt = mysqli_prepare($conn, $update_hutang_query);
                        mysqli_stmt_bind_param($update_hutang_stmt, "di", $new_hutang, $supplier_id);
                        mysqli_stmt_execute($update_hutang_stmt);
                        mysqli_stmt_close($update_hutang_stmt);

                        $hutang_message = "<p>Hutang Supplier: <strong>Rp " . number_format($new_hutang, 0, ',', '.') . "</strong> (Dipotong: Rp " . number_format($potong_hutang, 0, ',', '.') . ")</p>";
                    }
                }

                $success_message = "Data timbangan 2 berhasil disimpan untuk tiket: " . htmlspecialchars($no_tiket1);

                // Tampilkan popup untuk cetak struk dengan data yang BENAR (sesuai JavaScript)
                // Database tidak diubah, tapi popup tampilkan hasil yang benar
                // Hitung Total Akhir 2 untuk ditampilkan di sukses message
                $totalHarga2 = max(0, $totalHarga - $potong_hutang);

                // Langsung print struk tanpa konfirmasi
                echo "<script>
                    setTimeout(function() {
                        // Tampilkan notifikasi sukses singkat
                        Swal.fire({
                            title: 'Data Tersimpan!',
                            html: '<p>No. Tiket: <strong>" . htmlspecialchars($no_tiket1) . "</strong></p><p>Netto: " . number_format($nettoAkhir, 0, ',', '.') . " Kg</p><p>Mencetak struk...</p>',
                            icon: 'success',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        });

                        // Langsung buka print struk
                        setTimeout(function() {
                            window.open('print_ticket.php?no_tiket=" . urlencode($no_tiket1) . "', '_blank');
                        }, 500);
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
    $query = "SELECT tt.no_tiket, tt.no_polisi, tt.nama_supir, tt.id_supplier, s.nama_supplier, tt.jenis_material, tt.berat_bruto, tt.harga_per_kg, tt.tanggal, tt.keterangan
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

<style>
/* Dark Theme seperti Timbangan 1 - Layout Tetap Sama */

/* Container Style */
.container-fluid {
    background: #212529;
    padding: 20px;
    max-height: 100vh;
    overflow-y: auto;
}

/* Box Style - Layout tetap sama, warna diubah */
.terminal-box {
    background: #000000ff;
    border: none;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Labels */
.terminal-label {
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: block;
}

/* Select Dropdown */
.terminal-select {
    background: #6c757d;
    border: none;
    color: #fff;
    border-radius: 8px;
    font-size: 12px;
    padding: 8px;
    width: 100%;
    text-transform: uppercase;
    transition: all 0.2s ease;
}

.terminal-select:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    outline: none;
}

.terminal-select option {
    background: #6c757d;
    color: #fff;
}

/* Input Fields */
.terminal-input {
    background: #6c757d;
    border: none;
    color: #fff;
    border-radius: 8px;
    font-size: 12px;
    padding: 8px;
    width: 100%;
    text-align: center;
    text-transform: uppercase;
    transition: all 0.2s ease;
}

.terminal-input:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    outline: none;
}

.terminal-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.terminal-input:read-only {
    opacity: 0.7;
    background: #495057;
}

/* Buttons */
.terminal-btn {
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

.terminal-btn:hover {
    background: #fff;
    color: #343a40;
}

.terminal-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.terminal-btn-primary {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
}

.terminal-btn-primary:hover {
    background: #218838;
    border-color: #1e7e34;
}

/* Button Warnings - seperti di timbangan 1 */
.terminal-btn-warning {
    background: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.terminal-btn-warning:hover {
    background: #e0a800;
    border-color: #d39e00;
}

/* Button Info - seperti di timbangan 1 */
.terminal-btn-info {
    background: #17a2b8;
    border-color: #17a2b8;
    color: #fff;
}

.terminal-btn-info:hover {
    background: #138496;
    border-color: #117a8b;
}

/* Display Panel */
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

.display-value {
    font-size: 48px;
    font-weight: 700;
    color: #dc3545;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    margin: 10px 0;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.display-status {
    font-size: 11px;
    color: #fff;
    opacity: 0.75;
    margin-top: 5px;
}

/* Results Grid */
.results-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.result-item {
    background: #343a40;
    border: none;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.result-label {
    font-size: 9px;
    color: #fff;
    opacity: 0.7;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.result-value {
    font-size: 14px;
    color: #fff;
    font-weight: bold;
}

/* Alert Override */
.alert {
    background: #343a40 !important;
    border: none !important;
    color: #fff !important;
    border-radius: 8px;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .display-value {
        font-size: 32px;
    }
    
    /* Stack layout vertically on mobile */
    div[style*="grid-template-columns: 1fr 1.5fr"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: repeat(6, 1fr) auto"] {
        grid-template-columns: 1fr !important;
    }
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    background: #000;
}

::-webkit-scrollbar-track {
    background: #000;
    border: 1px solid #0f0;
}

::-webkit-scrollbar-thumb {
    background: #0f0;
}

/* Animation for display */
@keyframes blink {
    0%, 50%, 100% { opacity: 1; }
    25%, 75% { opacity: 0.7; }
}

.display-value.captured {
    animation: blink 0.5s ease-in-out 3;
}
</style>

<div class="container-fluid">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-dismissible fade show" role="alert">
           <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="timbangan2Form">
        <!-- PILIH TIKET - FULL WIDTH -->
        <div class="terminal-box">
            <label class="terminal-label">Pilih Tiket</label>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px;">
                <select name="no_tiket1" class="terminal-select" id="tiketSelector" required>
                    <option value="">-- PILIH TIKET --</option>
                    <?php foreach ($tiket_list as $tiket): ?>
                        <option value="<?= $tiket['no_tiket'] ?>"
                                data-kendaraan="<?= $tiket['no_polisi'] ?>"
                                data-pengemudi="<?= $tiket['nama_supir'] ?>"
                                data-suplier="<?= $tiket['nama_supplier'] ?>"
                                data-supplier-id="<?= $tiket['id_supplier'] ?>"
                                data-material="<?= $tiket['jenis_material'] ?>"
                                data-harga="<?= $tiket['harga_per_kg'] ?>"
                                data-berat="<?= $tiket['berat_bruto'] ?>"
                                data-keterangan="<?= htmlspecialchars($tiket['keterangan'] ?? '') ?>">
                        <?= $tiket['no_tiket'] ?> - <?= $tiket['no_polisi'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="terminal-btn" id="refreshTiketBtn">REFRESH</button>
            </div>
        </div>

        <!-- MAIN LAYOUT: LEFT (DATA) + RIGHT (DISPLAY) -->
        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 15px; margin-bottom: 15px;">
            
            <!-- LEFT SIDE: DATA TIMBANGAN 1 -->
            <div class="terminal-box">
                <label class="terminal-label">Data Timbangan 1</label>
                
                <!-- 2 COLUMNS LAYOUT -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    
                    <!-- COLUMN 1 -->
                    <div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">No Kendaraan</label>
                            <input type="text" class="terminal-input" id="displayKendaraan">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Supir</label>
                            <input type="text" class="terminal-input" id="displayPengemudi" >
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Supplier</label>
                            <input type="text" class="terminal-input" id="displaySuplier" >
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Material</label>
                            <input type="text" class="terminal-input" id="displayMaterial">
                        </div>
                        <div>
                            <label class="terminal-label" style="font-size: 9px;">Harga</label>
                            <input type="text" class="terminal-input" id="displayHarga">
                        </div>
                    </div>
                    
                    <!-- COLUMN 2 -->
                    <div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Bruto (Timbangan1)</label>
                            <input type="text" class="terminal-input" id="displayBerat1" >
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Potongan</label>
                            <input type="number" name="persen_potongan" class="terminal-input" id="persenPotongan" 
                                   step="0.01" min="0" max="100" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Keterangan</label>
                            <input type="text" name="keterangan" class="terminal-input" >
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Total Hutang</label>
                            <input type="text" class="terminal-input" id="totalHutangDisplay" readonly style="background: #495057; color: #ef4444; text-align: right;">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="terminal-label" style="font-size: 9px;">Potong Hutang</label>
                            <input type="text" name="potong_hutang" class="terminal-input rupiah-input" id="potongHutangInput"
                                   placeholder="0 (format: 1.000.000)" style="text-align: right;">
                            <input type="hidden" name="potong_hutang_hidden" id="potongHutangHidden" value="0">
                        </div>
                        <div>
                            <label class="terminal-label" style="font-size: 9px;">Sisa Hutang</label>
                            <input type="text" class="terminal-input" id="sisaHutangDisplay" readonly
                                   style="background: #495057; color: #10b981; text-align: right;">
                            <input type="hidden" name="sisa_hutang" id="sisaHutangHidden" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: DISPLAY PANEL -->
            <div class="terminal-box">
                <label class="terminal-label">Display</label>
                <div class="display-panel">
                    <div class="display-value" id="weightDisplay2Large">0 KG</div>
                    <div class="display-status" id="weightStatus2">Menunggu...</div>
                    <button type="button" class="terminal-btn terminal-btn-info" id="toggleConnection2"
                            style="margin-top: 15px; width: 100%; padding: 8px;">CONNECT</button>
                </div>

                <!-- INPUT MANUAL BERAT (Seperti Timbangan 1) -->
                <div style="margin-top: 15px;">
                    <label class="terminal-label" style="color: #28a745; font-weight: bold;">INPUT</label>
                    <input type="number" class="terminal-input" id="beratInputForm2" step="1" min="0"
                           style="background: #28a745; color: #fff; font-size: 24px; font-weight: bold; height: auto;">
                    <div style="margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button type="button" class="terminal-btn terminal-btn-warning" id="useManualWeight2"
                                style="width: 100%; padding: 8px;">CAPTURE MANUAL</button>
                        <button type="button" class="terminal-btn terminal-btn-primary" id="captureWeight2"
                                style="width: 100%; padding: 8px;">CAPTURE INDIKATOR</button>
                    </div>
                </div>
            </div>

        </div>

        <input type="hidden" name="berat2" id="beratInput2" required>
<input type="hidden" name="total_akhir2" id="totalAkhir2Hidden" value="0">

        <!-- HASIL PERHITUNGAN - FULL WIDTH BOTTOM -->
        <div class="terminal-box">
            <label class="terminal-label">Hasil Perhitungan</label>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr) auto; gap: 10px; align-items: stretch;">
                <div class="result-item">
                    <div class="result-label">Bruto</div>
                    <div class="result-value" id="hasilBruto">0</div>
                </div>
                <div class="result-item">
                    <div class="result-label">Tara</div>
                    <div class="result-value" id="hasilTara">0</div>
                </div>
                <div class="result-item">
                    <div class="result-label">Netto Awal</div>
                    <div class="result-value" id="hasilNettoBT">0</div>
                </div>
                <div class="result-item">
                    <div class="result-label">Potongan</div>
                    <div class="result-value" id="hasilPotongan">0</div>
                </div>
                <div class="result-item">
                    <div class="result-label">Netto Akhir</div>
                    <div class="result-value" id="hasilNettoAkhir">0</div>
                </div>
                <div class="result-item">
                    <div class="result-label">Total Akhir</div>
                    <div class="result-value" id="hasilTotalHarga">0</div>
                </div>
                <div class="result-item" style="border: 2px solid #10b981; background: linear-gradient(135deg, #064e3b, #065f46);">
                    <div class="result-label" style="color: #10b981;">Total Akhir 2</div>
                    <div class="result-value" id="hasilTotalHarga2" style="color: #10b981;">0</div>
                </div>
                <button type="submit" class="terminal-btn terminal-btn-primary" id="saveButton" disabled
                        style="height: 100%; padding: 15px 25px; white-space: nowrap;">SIMPAN DAN CETAK</button>
            </div>
            <div style="margin-top: 10px; padding: 8px; background: #374151; border-radius: 8px; display: none;" id="potongHutangInfo">
                <small style="color: #f59e0b;">
                    <strong>💰 Info:</strong> Total Akhir 2 = Total Akhir - Potong Hutang
                </small>
            </div>
        </div>

        <!-- HUTANG SUPPLIER SECTION -->
        <div class="terminal-box" id="hutangSection" style="display: none; border: 2px solid #dc2626;">
            <label class="terminal-label" style="color: #dc2626;">📊 Detail Hutang Supplier</label>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; color: #fff;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                            <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase;">No. Tiket</th>
                            <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase;">Tanggal</th>
                            <th style="padding: 12px; text-align: right; font-size: 11px; text-transform: uppercase;">Total Harga</th>
                            <th style="padding: 12px; text-align: right; font-size: 11px; text-transform: uppercase;">Status Bayar</th>
                        </tr>
                    </thead>
                    <tbody id="hutangTableBody">
                        <tr>
                            <td colspan="4" style="padding: 20px; text-align: center; opacity: 0.7;">
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <div style="width: 20px; height: 20px; border: 3px solid #dc2626; border-top: 3px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px;"></div>
                                    Loading data hutang...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: linear-gradient(135deg, #495057, #343a40); font-weight: bold;">
                            <td colspan="2" style="padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase;">💰 Total Hutang:</td>
                            <td style="padding: 12px; text-align: right; color: #fbbf24; font-size: 14px; font-weight: 700;" id="totalHutangTable">Rp 0</td>
                            <td style="padding: 12px;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>

  </form>
</div>

<!-- jQuery -->
<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<!-- SweetAlert2 -->
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>
<!-- Function definitions moved here to fix scope issues -->
<script>
// Initialize Auto Serial Connector for Timbangan 2
async function initializeAutoSerialConnector2() {
    if (!window.AutoSerialConnector) {
        return false;
    }

    if (!serialConnector2) {
        serialConnector2 = new AutoSerialConnector({
            targetInputId: 'beratInput2',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            onConnect: () => {
                updateConnectionUI2(true);
                showNotification2('Terhubung ke indikator Sonic A283', 'success');
            },
            onDisconnect: () => {
                updateConnectionUI2(false);
            },
            onData: (weight) => {
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplayAutoSerial2(weight);
            },
            onError: (error) => {
                console.error('Auto Serial Error (Timbangan 2):', error);
                showNotification2('Error koneksi serial: ' + error.message, 'error');
                updateConnectionUI2(false);
            }
        });
    }

    // Try auto-connect first
    const autoConnected = await serialConnector2.autoConnect();
    if (autoConnected) {
        return true;
    }

    return false;
}

// Update weight display from Auto Serial Connector for Timbangan 2
function updateWeightDisplayAutoSerial2(weight) {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');
    const beratInput2 = document.getElementById('beratInput2');

    // Reset warna display ketika menerima data dari indikator
    resetDisplayColor();

    // Update display timbangan
    if (weightDisplay2Large) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked2) {
            weightDisplay2Large.innerHTML = `${weight.toLocaleString('id-ID')} KG<br><small style="color: #ffc107; opacity: 0.7;">(Locked: ${window.capturedWeight2.toLocaleString('id-ID')} KG)</small>`;
        } else {
            weightDisplay2Large.textContent = weight.toLocaleString('id-ID') + ' KG';
        }
    }

    // Update status
    if (weightStatus2) {
        if (window.isWeightLocked2) {
            weightStatus2.textContent = 'Berat terkunci - Timbangan masih aktif';
        } else if (weight > 0) {
            weightStatus2.textContent = 'Data diterima dari Sonic A28E';
        } else {
            weightStatus2.textContent = 'Terhubung';
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
</script>

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
// Update date and time untuk Timbangan 2
function updateDateTime2() {
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
    const dateTimeElement = document.getElementById('currentDateTime2');
    if (dateTimeElement) {
        dateTimeElement.textContent = now.toLocaleDateString('id-ID', options);
    }
}
updateDateTime2();
setInterval(updateDateTime2, 1000);

// Load data tiket ketika dipilih
document.getElementById('tiketSelector').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    // Reset hutang section first
    const hutangSection = document.getElementById('hutangSection');
    if (hutangSection) {
        hutangSection.style.display = 'none';
    }

    if (this.value) {
        const displayKendaraan = document.getElementById('displayKendaraan');
        const displayPengemudi = document.getElementById('displayPengemudi');
        const displaySuplier = document.getElementById('displaySuplier');
        const displayMaterial = document.getElementById('displayMaterial');
        const displayHarga = document.getElementById('displayHarga');
        const displayBerat1 = document.getElementById('displayBerat1');
        const keteranganTextarea = document.querySelector('input[name="keterangan"]');
        const saveButton = document.getElementById('saveButton');

        if (displayKendaraan) displayKendaraan.value = selectedOption.dataset.kendaraan || '';
        if (displayPengemudi) displayPengemudi.value = selectedOption.dataset.pengemudi || '';
        if (displaySuplier) displaySuplier.value = selectedOption.dataset.suplier || '';

        // Load hutang supplier
        const supplierId = selectedOption.dataset.supplierId;
        if (supplierId) {
            loadSupplierHutang(supplierId);
        }

        // Isi keterangan dari data timbangan 1
        const keteranganInput = document.querySelector('input[name="keterangan"]');
        if (keteranganInput) {
            const keteranganDariTiket = selectedOption.dataset.keterangan || '';
            keteranganInput.value = keteranganDariTiket;
        }

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
        const beratInputForm2 = document.getElementById('beratInputForm2');

        if (beratInput2) {
            beratInput2.value = '0';
            beratInput2.readOnly = false;
        }

        // Reset form input manual
        if (beratInputForm2) {
            beratInputForm2.value = '0';
            beratInputForm2.readOnly = false;
        }

        // CLEAR LOCK VARIABLES
        window.isWeightLocked2 = false;
        window.capturedWeight2 = 0;

        // Reset capture button
        const captureBtn = document.getElementById('captureWeight2');
        if (captureBtn) {
            captureBtn.innerHTML = 'BUTTON CAPTURE';
            captureBtn.disabled = false;
        }

        const weightDisplay2Large = document.getElementById('weightDisplay2Large');
        if (weightDisplay2Large) {
            weightDisplay2Large.classList.remove('captured');
            weightDisplay2Large.innerHTML = '0 KG';
        }

        // Reset status
        const weightStatus2 = document.getElementById('weightStatus2');
        if (weightStatus2) {
            weightStatus2.textContent = 'Menghubungkan...';
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
        const keteranganTextarea = document.querySelector('input[name="keterangan"]');
        const saveButton = document.getElementById('saveButton');

        if (displayKendaraan) displayKendaraan.value = '';
        if (displayPengemudi) displayPengemudi.value = '';
        if (displaySuplier) displaySuplier.value = '';
        if (displayMaterial) displayMaterial.value = '';
        if (displayHarga) displayHarga.value = '';
        if (displayBerat1) displayBerat1.value = '';
        if (keteranganTextarea) keteranganTextarea.value = '';
        if (saveButton) saveButton.disabled = true;
        resetHasilPerhitungan();
    }
});

// Auto Serial Connector untuk Timbangan 2
let serialConnector2 = null;
let currentWeight2 = 0;
let lastWeightUpdate2 = 0;
let timbangan2Indicator = null;

// Initialize Enhanced Web Serial API for Timbangan 2
function initializeEnhancedWebSerialTimbangan2() {
    // Check if Enhanced Web Serial API is available
    if (!window.WeightIndicators) {
        document.getElementById('weightStatus2').textContent = 'Enhanced Web Serial API tidak tersedia. Menggunakan fallback.';
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

        // Set up callbacks using the MultiWeightManager
        window.WeightIndicators.onWeightUpdate(function(indicatorId, weight) {
            if (indicatorId === 'timbangan2') {
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplay2WebSerial(weight);
            }
        });

        window.WeightIndicators.onConnectionChange(function(indicatorId, connected) {
            if (indicatorId === 'timbangan2') {
                updateConnectionUI2(connected);
            }
        });

        window.WeightIndicators.onError(function(indicatorId, error) {
            if (indicatorId === 'timbangan2') {
                console.error('Serial Error callback received (Timbangan 2):', error);
                showNotification2('Error Timbangan 2: ' + error, 'error');
                updateConnectionUI2(false);
            }
        });

        // Alternative: Set callbacks directly on indicator
        if (typeof timbangan2Indicator.onWeightUpdate === 'function') {
            timbangan2Indicator.onWeightUpdate(function(weight) {
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplay2WebSerial(weight);
            });

            timbangan2Indicator.onConnectionChange(function(connected) {
                updateConnectionUI2(connected);
            });

            timbangan2Indicator.onError(function(error) {
                console.error('Direct serial Error callback received (Timbangan 2):', error);
                showNotification2('Error Timbangan 2: ' + error, 'error');
                updateConnectionUI2(false);
            });
        }

        // Initial UI update
        updateConnectionUI2(false);

        // Add test function for manual weight testing
        window.testWeightUpdateTimbangan2 = function(testWeight) {
            updateWeightDisplay2WebSerial(testWeight);
        };

    } catch (error) {
        console.error('Failed to initialize Enhanced Web Serial API for Timbangan 2:', error);
        document.getElementById('weightStatus2').textContent = 'Gagal inisialisasi Enhanced Web Serial API';
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
    const weightStatus2 = document.getElementById('weightStatus2');
    const beratInput2 = document.getElementById('beratInput2');

    // Reset warna display ketika menerima data dari indikator
    resetDisplayColor();

    // Update display timbangan
    if (weightDisplay2Large && window.WeightIndicatorUtils) {
        // JIKA BERAT SUDAH DI-LOCK, TUNJUKAN BERAT ASLI TAPI DENGAN INDIKATOR
        if (window.isWeightLocked2) {
            weightDisplay2Large.innerHTML = `${window.WeightIndicatorUtils.formatWeight(weight)}<br><small style="color: #ffc107; opacity: 0.7;">(Locked: ${window.WeightIndicatorUtils.formatWeight(window.capturedWeight2)})</small>`;
        } else {
            weightDisplay2Large.textContent = window.WeightIndicatorUtils.formatWeight(weight);
        }
    }

    // Update status
    if (weightStatus2) {
        if (window.isWeightLocked2) {
            weightStatus2.textContent = 'Berat terkunci - Timbangan masih aktif';
        } else if (weight > 0) {
            weightStatus2.textContent = 'Data diterima dari indikator';
        } else {
            weightStatus2.textContent = 'Menunggu data dari indikator...';
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
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'DISCONNECT';
        }
    } else {
        if (weightStatus2) {
            weightStatus2.textContent = 'Indicator Tidak Terhubung';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'CONNECT';
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
        // Silently fallback to AJAX
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
        // Silently fallback to AJAX
    }

    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');
    const toggleBtn = document.getElementById('toggleConnection2');
    const beratInput2 = document.getElementById('beratInput2');

    // If no serial connector is available, show 0 weight
    if (!window.serialConnector2) {
        if (weightDisplay2Large) weightDisplay2Large.textContent = '0 KG';
        if (weightStatus2) {
            weightStatus2.textContent = 'Indicator Tidak Terhubung';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'CONNECT';
        }
        return;
    }
}

// Simulasi berat timbangan 2 dengan kontrol
let weightInterval2 = null;
let isCaptured2 = false;

function updateWeight2() {
    updateWeightDisplay2();
}

// Start initial weight update
weightInterval2 = setInterval(updateWeight2, 2000);
updateWeight2(); // Initial call

// Fungsi untuk menggunakan input manual tanpa mengganggu koneksi indikator
document.getElementById('useManualWeight2').addEventListener('click', function() {
    const beratInputForm2 = document.getElementById('beratInputForm2');
    const beratInput2 = document.getElementById('beratInput2');
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');

    if (!beratInputForm2 || !beratInput2 || !weightDisplay2Large) {
        showNotification2('Element tidak ditemukan. Silakan refresh halaman.', 'error');
        return;
    }

    const manualWeight = parseFloat(beratInputForm2.value) || 0;

    if (manualWeight <= 0) {
        showNotification2('Masukkan berat yang valid (lebih dari 0)', 'warning');
        return;
    }

    // Update current weight tanpa mengganggu koneksi indikator
    currentWeight2 = manualWeight;
    lastWeightUpdate2 = Date.now();

    // Update hidden field langsung - INI YANG PENTING!
    beratInput2.value = manualWeight;

    // Set lock variables agar tidak di-override
    window.isWeightLocked2 = false; // Biarkan masih bisa diubah
    window.capturedWeight2 = manualWeight; // Simpan nilai manual

    // Update display dengan indikator manual
    if (weightDisplay2Large) {
        weightDisplay2Large.textContent = manualWeight.toLocaleString('id-ID') + ' KG';
        weightDisplay2Large.style.color = '#ffc107'; // Kuning untuk menandakan input manual
        weightDisplay2Large.style.textShadow = '0 0 15px rgba(255, 193, 7, 0.8)';
    }

    // Update status
    if (weightStatus2) {
        weightStatus2.textContent = 'Input Manual - Ready untuk capture';
        weightStatus2.style.color = '#ffc107';
    }

    // TRIGGER PERHITUNGAN OTOMATIS - INI YANG KURANG!
    hitungOtomatis();

    showNotification2(`Berat manual ${manualWeight.toLocaleString('id-ID')} KG digunakan!`, 'success');
});

// Event listener untuk input manual agar update otomatis saat mengetik
document.getElementById('beratInputForm2').addEventListener('input', function() {
    const beratInputForm2 = this;
    const beratInput2 = document.getElementById('beratInput2');
    const manualWeight = parseFloat(beratInputForm2.value) || 0;

    // Update hidden field secara real-time saat mengetik
    if (manualWeight > 0) {
        beratInput2.value = manualWeight;
        currentWeight2 = manualWeight;
        lastWeightUpdate2 = Date.now();

        // Update display warna
        const weightDisplay2Large = document.getElementById('weightDisplay2Large');
        if (weightDisplay2Large && !window.isWeightLocked2) {
            weightDisplay2Large.textContent = manualWeight.toLocaleString('id-ID') + ' KG';
            weightDisplay2Large.style.color = '#ffc107';
        }

        // Update status
        const weightStatus2 = document.getElementById('weightStatus2');
        if (weightStatus2 && !window.isWeightLocked2) {
            weightStatus2.textContent = 'Input Manual - Ketik untuk update';
            weightStatus2.style.color = '#ffc107';
        }

        // Trigger perhitungan otomatis
        hitungOtomatis();
    }
});

// Fungsi untuk reset warna display ketika menerima data dari indikator
function resetDisplayColor() {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');

    if (weightDisplay2Large && !window.isWeightLocked2) {
        weightDisplay2Large.style.color = '#dc3545'; // Kembali ke merah
        weightDisplay2Large.style.textShadow = '0 2px 4px rgba(0,0,0,0.3)';
    }

    if (weightStatus2 && !window.isWeightLocked2) {
        weightStatus2.style.color = '#fff';
        weightStatus2.textContent = 'Data diterima dari indikator';
    }
}

// Capture berat timbangan 2
document.getElementById('captureWeight2').addEventListener('click', function() {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const beratInput2 = document.getElementById('beratInput2');

    if (!weightDisplay2Large || !beratInput2) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Element tidak ditemukan. Silakan refresh halaman.'
        });
        return;
    }

    // Check if connected via Auto Serial Connector or Enhanced Web Serial OR using manual input
    let isConnected = false;
    let isManualInput = window.isWeightLocked2 && window.capturedWeight2 > 0;

    if (serialConnector2 && serialConnector2.isConnected) {
        isConnected = true;
    } else if (window.WeightIndicators) {
        isConnected = window.WeightIndicators.get('timbangan2') && window.WeightIndicators.get('timbangan2').isConnected;
    }

    // Allow capture if either connected OR using manual input
    if (!isConnected && !isManualInput) {
        showNotification2('Silakan hubungkan ke indikator atau gunakan input manual', 'warning');
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

    // Visual feedback - stop animation
    weightDisplay2Large.classList.add('captured');

    // Visual feedback button
    this.innerHTML = 'BERAT TERKUNCI!';
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
    showNotification2(`Berat ${weightValue.toLocaleString('id-ID')} KG berhasil di-capture dan dikunci!`, 'success');
});

// Hitung otomatis dengan formula yang benar
function hitungOtomatis() {
    const tiketSelector = document.getElementById('tiketSelector');
    const persenPotonganElement = document.getElementById('persenPotongan');
    const beratInput2Element = document.getElementById('beratInput2');
    const potongHutangInput = document.getElementById('potongHutangInput');

    if (!tiketSelector || !tiketSelector.value) return;

    const selectedOption = tiketSelector.options[tiketSelector.selectedIndex];
    const berat1 = parseInt(selectedOption.dataset.berat) || 0; // Bruto
    const harga = parseInt(selectedOption.dataset.harga) || 0;
    const persenPotongan = persenPotonganElement ? parseFloat(persenPotonganElement.value) || 0 : 0;
    const berat2 = beratInput2Element ? parseInt(beratInput2Element.value) || 0 : 0; // Tara
    const potongHutang = potongHutangInput ? parseRupiah(potongHutangInput.value) || 0 : 0;

    // Formula yang benar:
    // Timbangan 1 = BRUTO (Truck Penuh), Timbangan 2 = TARA (Truck Kosong)
    // 1. Bruto - Tara = Netto
    // 2. Netto x (Potongan % / 100) = Potongan (kg)
    // 3. Netto - Potongan (kg) = Netto Akhir
    // 4. Netto Akhir x Harga per kg = Total Harga
    // 5. Total Harga - Potong Hutang = Total Akhir 2

    const bruto = berat1; // Timbangan 1 = BRUTO (Truck Penuh)
    const tara = berat2;  // Timbangan 2 = TARA (Truck Kosong)
    const netto = bruto - tara; // Netto
    const potonganKg = (persenPotongan / 100) * netto; // Potongan dalam kg
    const nettoAkhir = netto - potonganKg; // Netto Akhir
    const totalHarga = nettoAkhir * harga; // Total Harga
    const totalHarga2 = Math.max(0, totalHarga - potongHutang); // Total Akhir 2 (tidak boleh negatif)

    // Update display dengan safe access dan format Rupiah yang lebih baik
    const elements = {
        hasilBruto: document.getElementById('hasilBruto'),
        hasilTara: document.getElementById('hasilTara'),
        hasilNettoBT: document.getElementById('hasilNettoBT'),
        hasilPotongan: document.getElementById('hasilPotongan'),
        hasilNettoAkhir: document.getElementById('hasilNettoAkhir'),
        hasilTotalHarga: document.getElementById('hasilTotalHarga'),
        hasilTotalHarga2: document.getElementById('hasilTotalHarga2'),
        potongHutangInfo: document.getElementById('potongHutangInfo')
    };

    if (elements.hasilBruto) elements.hasilBruto.textContent = bruto.toLocaleString('id-ID');
    if (elements.hasilTara) elements.hasilTara.textContent = tara.toLocaleString('id-ID');
    if (elements.hasilNettoBT) elements.hasilNettoBT.textContent = netto.toLocaleString('id-ID');
    if (elements.hasilPotongan) elements.hasilPotongan.textContent = potonganKg.toFixed(2).toLocaleString('id-ID');
    if (elements.hasilNettoAkhir) elements.hasilNettoAkhir.textContent = nettoAkhir.toFixed(2).toLocaleString('id-ID');
    if (elements.hasilTotalHarga) elements.hasilTotalHarga.textContent = formatRupiah(totalHarga);
    if (elements.hasilTotalHarga2) elements.hasilTotalHarga2.textContent = formatRupiah(totalHarga2);

    // Update hidden field untuk total akhir 2
    const totalAkhir2Hidden = document.getElementById('totalAkhir2Hidden');
    if (totalAkhir2Hidden) {
        totalAkhir2Hidden.value = totalHarga2;
    }

    // Tampilkan info potong hutang jika ada potongan
    if (elements.potongHutangInfo) {
        if (potongHutang > 0) {
            elements.potongHutangInfo.style.display = 'block';
        } else {
            elements.potongHutangInfo.style.display = 'none';
        }
    }
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

    if (elements.hasilBruto) elements.hasilBruto.textContent = '0';
    if (elements.hasilTara) elements.hasilTara.textContent = '0';
    if (elements.hasilNettoBT) elements.hasilNettoBT.textContent = '0';
    if (elements.hasilPotongan) elements.hasilPotongan.textContent = '0';
    if (elements.hasilNettoAkhir) elements.hasilNettoAkhir.textContent = '0';
    if (elements.hasilTotalHarga) elements.hasilTotalHarga.textContent = '0';
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
                text: 'Form element tidak ditemukan. Silakan refresh halaman.'
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
                text: 'Silakan pilih nomor tiket terlebih dahulu!'
            });
            return;
        }

        // Validasi berat 2 harus di-capture (tanpa minimal berat)
        if (berat2 === '0' || berat2 === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan capture berat timbangan 2 terlebih dahulu!'
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
                text: 'Persen potongan harus antara 0% - 100%!'
            });
            return;
        }

        // Validasi potong hutang
        const potongHutangInput = document.getElementById('potongHutangInput');

        // Get potong hutang value
        const potongHutang = (potongHutangInput && potongHutangInput.value)
            ? parseRupiah(potongHutangInput.value) || 0
            : 0;

        const totalHutang = currentSupplierData.total_hutang || 0;

        // Validasi hutang tidak boleh melebihi total hutang
        if (potongHutang > totalHutang) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Jumlah potong hutang melebihi total hutang supplier!'
            });
            return;
        }

        // Langsung submit tanpa konfirmasi
        // Form akan diproses oleh PHP dan langsung print struk
    });
}

// Refresh tiket data function
document.getElementById('refreshTiketBtn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;

    // Show loading state
    btn.innerHTML = 'REFRESHING...';
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

<script>
// Global variable untuk menyimpan data hutang supplier
let currentSupplierData = {
    supplier_id: null,
    nama_supplier: null,
    total_hutang: 0
};

// Load hutang supplier dari database
function loadSupplierHutang(supplierId) {
    if (!supplierId) return;

    // Show hutang section
    const hutangSection = document.getElementById('hutangSection');
    if (hutangSection) {
        hutangSection.style.display = 'block';
    }

    // Load summary hutang
    fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_supplier_hutang&supplier_id=' + supplierId
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' - ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            currentSupplierData = data.data;
            updateHutangDisplay();
            updatePotongHutangInput();
            loadHutangDetails(supplierId); // Load detail hutang
        } else {
            // Reset data jika gagal
            currentSupplierData = {
                supplier_id: supplierId,
                nama_supplier: 'Unknown',
                total_hutang: 0
            };
            updateHutangDisplay();
            updatePotongHutangInput();
            hideHutangSection();
        }
    })
    .catch(error => {
        console.error('Error loading hutang supplier:', error);
        // Reset data jika error
        currentSupplierData = {
            supplier_id: supplierId,
            nama_supplier: 'Unknown',
            total_hutang: 0
        };
        updateHutangDisplay();
        updatePotongHutangInput();
        hideHutangSection();
    });
}

// Load detail hutang supplier
function loadHutangDetails(supplierId) {
    // Load detail transaksi yang menunggu pembayaran
    fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_supplier_hutang_details&supplier_id=' + supplierId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            displayHutangTable(data.data);
        } else {
            displayEmptyHutangTable();
        }
    })
    .catch(error => {
        console.error('Error loading hutang details:', error);
        displayEmptyHutangTable();
    });
}

// Display hutang table
function displayHutangTable(hutangData) {
    const tbody = document.getElementById('hutangTableBody');
    const totalHutangTable = document.getElementById('totalHutangTable');

    if (!tbody) return;

    let html = '';
    let total = 0;

    hutangData.forEach(item => {
        total += parseFloat(item.total_harga || 0);
        const tanggal = new Date(item.tanggal).toLocaleDateString('id-ID');
        const status = item.status_bayar || 'Belum Bayar';
        const statusColor = status === 'Lunas' ? '#10b981' : '#ef4444';

        html += `
            <tr style="border-bottom: 1px solid #495057;">
                <td style="padding: 8px; font-size: 11px;">${item.no_tiket || '-'}</td>
                <td style="padding: 8px; font-size: 11px;">${tanggal}</td>
                <td style="padding: 8px; text-align: right; font-size: 11px;">${formatRupiah(item.total_harga || 0)}</td>
                <td style="padding: 8px; text-align: right; font-size: 11px; color: ${statusColor};">${status}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    if (totalHutangTable) {
        totalHutangTable.textContent = formatRupiah(total);
    }
}

// Display empty hutang table
function displayEmptyHutangTable() {
    const tbody = document.getElementById('hutangTableBody');
    const totalHutangTable = document.getElementById('totalHutangTable');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="4" style="padding: 20px; text-align: center; opacity: 0.7;">Tidak ada data hutang</td>
        </tr>
    `;

    if (totalHutangTable) {
        totalHutangTable.textContent = 'Rp 0';
    }
}

// Hide hutang section
function hideHutangSection() {
    const hutangSection = document.getElementById('hutangSection');
    if (hutangSection) {
        hutangSection.style.display = 'none';
    }
}

// Update display hutang
function updateHutangDisplay() {
    const totalHutangDisplay = document.getElementById('totalHutangDisplay');
    const sisaHutangDisplay = document.getElementById('sisaHutangDisplay');
    const sisaHutangHidden = document.getElementById('sisaHutangHidden');

    const totalHutang = parseFloat(currentSupplierData.total_hutang) || 0;

    // Update total hutang display dengan format baru
    if (totalHutangDisplay) {
        if (totalHutang > 0) {
            totalHutangDisplay.style.color = '#ef4444'; // Red untuk hutang
            totalHutangDisplay.value = formatRupiah(totalHutang);
        } else {
            totalHutangDisplay.style.color = '#10b981'; // Green untuk tidak ada hutang
            totalHutangDisplay.value = 'Rp 0';
        }
    }

    // Update sisa hutang (initially same as total)
    if (sisaHutangDisplay) {
        if (totalHutang > 0) {
            sisaHutangDisplay.style.color = '#f59e0b'; // Orange untuk sisa hutang
            sisaHutangDisplay.value = formatRupiah(totalHutang);
        } else {
            sisaHutangDisplay.style.color = '#10b981'; // Green untuk tidak ada hutang
            sisaHutangDisplay.value = 'Rp 0';
        }
    }

    if (sisaHutangHidden) {
        sisaHutangHidden.value = totalHutang;
    }

    }

// Update input potong hutang dengan validasi maksimal
function updatePotongHutangInput() {
    const potongHutangInput = document.getElementById('potongHutangInput');

    if (potongHutangInput) {
        // Set max value
        potongHutangInput.max = currentSupplierData.total_hutang || 0;
        // Set title dengan format benar
        potongHutangInput.title = currentSupplierData.total_hutang > 0
            ? `Maksimal: ${formatRupiah(currentSupplierData.total_hutang)}`
            : 'Supplier tidak memiliki hutang';

        // Disable jika tidak ada hutang
        potongHutangInput.disabled = currentSupplierData.total_hutang <= 0;
        if (currentSupplierData.total_hutang <= 0) {
            potongHutangInput.value = '0';
        }
    }
}

// Fungsi format Rupiah untuk display (1.000.000, 500.000, 100.000)
function formatRupiah(amount) {
    if (amount === 0 || !amount) return 'Rp 0';

    const num = parseFloat(amount);
    if (isNaN(num)) return 'Rp 0';

    // Format manual untuk memastikan titik sebagai pemisah ribuan
    // Test cases: 5000000 -> 5.000.000, 500000 -> 500.000, 100000 -> 100.000
    const formatted = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return 'Rp ' + formatted;
}

// Fungsi format Rupiah untuk input (tanpa "Rp ")
function formatRupiahInput(amount) {
    if (amount === 0 || !amount) return '0';

    const num = parseFloat(amount);
    if (isNaN(num)) return '0';

    // Format manual untuk memastikan titik sebagai pemisah ribuan
    const formatted = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    
    return formatted;
}

// Fungsi parse Rupiah dari input (hilangkan "Rp " dan titik)
function parseRupiah(rupiahString) {
    if (!rupiahString) return 0;

    // Hilangkan "Rp " dan titik, ganti dengan kosong
    const cleanString = rupiahString.toString().replace(/[^0-9]/g, '');
    return parseFloat(cleanString) || 0;
}

// Fungsi untuk format input saat user mengetik
function formatRupiahInputOnChange(input) {
    let value = input.value;

    // Parse current value
    let numValue = parseRupiah(value);

    // Format kembali
    if (numValue > 0) {
        input.value = formatRupiahInput(numValue);
        // Update hidden field
        const hiddenField = document.getElementById(input.id.replace('Input', 'Hidden'));
        if (hiddenField) {
            hiddenField.value = numValue;
        }
    } else {
        input.value = '';
        const hiddenField = document.getElementById(input.id.replace('Input', 'Hidden'));
        if (hiddenField) {
            hiddenField.value = 0;
        }
    }
}


// Event listener untuk input potong hutang (sederhana karena sudah ada global functions)
document.addEventListener('DOMContentLoaded', function() {
    const potongHutangInput = document.getElementById('potongHutangInput');

    if (potongHutangInput) {
        // Custom logic untuk validasi maksimal potong hutang dan update sisa hutang
        potongHutangInput.addEventListener('input', function() {
            // Get actual numeric value
            const potongHutang = parseRupiah(this.value) || 0;
            const totalHutang = currentSupplierData.total_hutang || 0;

            // Validasi maksimal potong hutang
            if (potongHutang > totalHutang) {
                // Format ke nilai maksimal
                this.value = formatRupiahInput(totalHutang);
                document.getElementById('potongHutangHidden').value = totalHutang;
                return;
            }

            const sisaHutang = Math.max(0, totalHutang - potongHutang);

            // Update sisa hutang display
            const sisaHutangDisplay = document.getElementById('sisaHutangDisplay');
            const sisaHutangHidden = document.getElementById('sisaHutangHidden');

            if (sisaHutangDisplay) {
                if (sisaHutang > 0) {
                    sisaHutangDisplay.style.color = '#f59e0b'; // Orange untuk sisa hutang
                    sisaHutangDisplay.value = formatRupiah(sisaHutang);
                } else if (sisaHutang === 0 && totalHutang > 0) {
                    sisaHutangDisplay.style.color = '#10b981'; // Green untuk lunas
                    sisaHutangDisplay.value = 'LUNAS';
                } else {
                    sisaHutangDisplay.style.color = '#10b981'; // Green untuk tidak ada hutang
                    sisaHutangDisplay.value = 'Rp 0';
                }
            }

            if (sisaHutangHidden) {
                sisaHutangHidden.value = sisaHutang;
            }

            // Trigger perhitungan otomatis untuk update Total Akhir 2
            hitungOtomatis();
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>  