<?php
// modules/timbangan/index.php
// Redirect ke timbangan1.php sesuai workflow baru
header('Location: timbangan1.php');
exit;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        body {
            background: #f0f2f5;
            overflow: hidden;
            height: 100vh;
        }
        
        .top-navbar {
            background: #2c3e50;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .nav-tabs .nav-link {
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            margin-right: 5px;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .nav-tabs .nav-link.active {
            background: #3498db;
        }
        
        .weight-display {
            background: #000;
            color: #ff0000;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 28px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            border: 2px solid #333;
            min-width: 150px;
            text-align: center;
        }
        
        .main-container {
            display: grid;
            grid-template-columns: 1fr 320px;
            height: calc(100vh - 60px);
        }
        
        .form-panel {
            background: white;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #ddd;
        }
        
        .side-panel {
            background: #2c3e50;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .camera-feed {
            background: #000;
            border-radius: 10px;
            height: 240px;
            position: relative;
            border: 2px solid #34495e;
        }
        
        .camera-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 10px;
        }
        
        .camera-label {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .info-panel {
            background: white;
            padding: 15px;
            border-radius: 10px;
            flex: 1;
            overflow-y: auto;
        }
        
        .mode-btn {
            border: 2px solid #3498db;
            background: white;
        }
        
        .mode-btn.active {
            background: #3498db;
            color: white;
        }
        
        .form-panel::-webkit-scrollbar,
        .info-panel::-webkit-scrollbar {
            width: 6px;
        }
        
        .form-panel::-webkit-scrollbar-thumb,
        .info-panel::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .stat-item {
            padding: 8px;
            background: #ecf0f1;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        
        .transaction-item {
            padding: 8px;
            border-radius: 5px;
            border-left: 3px solid;
            margin-bottom: 5px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-dark top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2">
                <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                    <i class="fas fa-balance-scale"></i> Jembatan Timbangan
                </a>
                
                <ul class="nav nav-tabs border-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-weight"></i> Timbang
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>modules/laporan/index.php">
                            <i class="fas fa-chart-line"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>modules/master/kendaraan.php">
                            <i class="fas fa-database"></i> Master Data
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <small class="text-white">
                    <i class="fas fa-plug"></i> COM3 | 9600
                </small>
                
                <div class="weight-display" id="currentWeight">0</div>
                
                <span class="badge bg-light text-dark">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['nama_lengkap']; ?>
                </span>
                
                <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Form Panel -->
        <div class="form-panel">
            <form id="formTimbang">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tiket No</label>
                        <input type="text" class="form-control form-control-sm" name="no_tiket" 
                               value="<?php echo $new_ticket; ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tanggal/Waktu</label>
                        <input type="text" class="form-control form-control-sm" 
                               value="<?php echo date('d/m/Y H:i'); ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Pengemudi *</label>
                        <input type="text" class="form-control form-control-sm" name="nama_supir" 
                               placeholder="Nama Supir" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">No. Kendaraan *</label>
                        <select class="form-select form-select-sm select2" name="id_kendaraan" required>
                            <option value="">-- Pilih Kendaraan --</option>
                            <?php while($row = mysqli_fetch_assoc($kendaraan_list)): ?>
                            <option value="<?php echo $row['id']; ?>" 
                                    data-supir="<?php echo $row['nama_supir']; ?>"
                                    data-tara="<?php echo $row['tara_avg']; ?>">
                                <?php echo $row['no_polisi']; ?> - <?php echo strtoupper(str_replace('_', ' ', $row['jenis_kendaraan'])); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold">Supplier/Petani *</label>
                        <select class="form-select form-select-sm select2" name="id_supplier" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php while($row = mysqli_fetch_assoc($supplier_list)): ?>
                            <option value="<?php echo $row['id']; ?>">
                                <?php echo $row['nama_supplier']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Timbang 1 (Bruto)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control" name="berat_bruto" 
                                   id="beratBruto" readonly>
                            <span class="input-group-text">Kg</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm w-100 mt-1" id="btnTimbang1">
                            <i class="fas fa-weight"></i> TIMBANG 1
                        </button>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Timbang 2 (Tara)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control" name="berat_tara" 
                                   id="beratTara" readonly>
                            <span class="input-group-text">Kg</span>
                        </div>
                        <button type="button" class="btn btn-success btn-sm w-100 mt-1" id="btnTimbang2" disabled>
                            <i class="fas fa-weight"></i> TIMBANG 2
                        </button>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Material *</label>
                        <select class="form-select form-select-sm" name="jenis_material" required>
                            <option value="">-- Pilih Material --</option>
                            <option value="tbs">TBS (Tandan Buah Segar)</option>
                            <option value="cpo">CPO</option>
                            <option value="kernel">Kernel</option>
                            <option value="brondolan">Brondolan</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Harga (Rp/Kg)</label>
                        <input type="number" class="form-control form-control-sm" name="harga_per_kg" 
                               placeholder="0" step="0.01">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold">Keterangan</label>
                        <input type="text" class="form-control form-control-sm" name="keterangan" 
                               placeholder="Catatan tambahan (opsional)">
                    </div>
                </div>

                <!-- Summary Box (Hidden by default) -->
                <div id="summaryBox" class="alert alert-info mt-3" style="display: none;">
                    <h6 class="mb-2"><i class="fas fa-calculator"></i> Ringkasan Timbangan</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">Bruto</small>
                            <div class="fs-5 fw-bold" id="summaryBruto">0</div>
                            <small>Kg</small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Tara</small>
                            <div class="fs-5 fw-bold" id="summaryTara">0</div>
                            <small>Kg</small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Netto</small>
                            <div class="fs-5 fw-bold text-success" id="summaryNetto">0</div>
                            <small>Kg</small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row g-2 mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success w-100" id="btnSimpan" disabled>
                            <i class="fas fa-save"></i> SIMPAN & CETAK TIKET
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-danger w-100" onclick="resetForm()">
                            <i class="fas fa-redo"></i> RESET
                        </button>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>modules/laporan/index.php" class="btn btn-secondary w-100">
                            <i class="fas fa-list"></i> LIHAT LAPORAN
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Side Panel -->
        <div class="side-panel">
            <!-- Camera Feed -->
            <div class="camera-feed">
                <div class="camera-label">CAMERA - TIMBANGAN</div>
                <div class="camera-placeholder">
                    <i class="fas fa-video fa-3x mb-2 opacity-50"></i>
                    <small>Live Camera Feed</small>
                </div>
            </div>

           

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        let currentStep = 0;

        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            // Auto-fill driver name when vehicle selected
            $('select[name="id_kendaraan"]').on('change', function() {
                const supir = $(this).find(':selected').data('supir');
                if (supir) {
                    $('input[name="nama_supir"]').val(supir);
                }
            });

            // Update weight every 2 seconds
            setInterval(updateWeight, 2000);
            updateWeight();
        });

        // Get current weight from scale
        function updateWeight() {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: { action: 'get_weight' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#currentWeight').text(formatNumber(response.data.weight));
                    }
                }
            });
        }

        // Timbang 1 (Bruto)
        $('#btnTimbang1').on('click', function() {
            const currentWeight = parseInt($('#currentWeight').text().replace(/\./g, ''));
            
            if (currentWeight < 1000) {
                Swal.fire('Error', 'Berat tidak valid!', 'error');
                return;
            }

            $('#beratBruto').val(currentWeight);
            $('#summaryBruto').text(formatNumber(currentWeight));
            $('#btnTimbang1').prop('disabled', true);
            $('#btnTimbang2').prop('disabled', false);
            currentStep = 1;

            Swal.fire({
                icon: 'success',
                title: 'Timbang 1 Berhasil!',
                text: 'Bruto: ' + formatNumber(currentWeight) + ' Kg',
                timer: 1500,
                showConfirmButton: false
            });
        });

        // Timbang 2 (Tara)
        $('#btnTimbang2').on('click', function() {
            const currentWeight = parseInt($('#currentWeight').text().replace(/\./g, ''));
            const bruto = parseInt($('#beratBruto').val());
            
            if (currentWeight < 1000 || currentWeight >= bruto) {
                Swal.fire('Error', 'Berat tara tidak valid!', 'error');
                return;
            }

            const netto = bruto - currentWeight;

            $('#beratTara').val(currentWeight);
            $('#summaryTara').text(formatNumber(currentWeight));
            $('#summaryNetto').text(formatNumber(netto));
            $('#summaryBox').slideDown();
            $('#btnTimbang2').prop('disabled', true);
            $('#btnSimpan').prop('disabled', false);
            currentStep = 2;

            Swal.fire({
                icon: 'success',
                title: 'Timbang 2 Berhasil!',
                html: 'Tara: ' + formatNumber(currentWeight) + ' Kg<br>Netto: <strong>' + formatNumber(netto) + ' Kg</strong>',
                timer: 2000,
                showConfirmButton: false
            });
        });

        // Submit Form
        $('#formTimbang').on('submit', function(e) {
            e.preventDefault();

            if (currentStep < 2) {
                Swal.fire('Error', 'Harap selesaikan proses timbangan!', 'error');
                return;
            }

            Swal.fire({
                title: 'Menyimpan data...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'proses.php',
                type: 'POST',
                data: $(this).serialize() + '&action=save',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '✅ Transaksi Berhasil Disimpan!',
                            html: '<div style="text-align: left;">' +
                                  '<p>' + response.message + '</p>' +
                                  '</div>',
                            showCancelButton: true,
                            confirmButtonColor: '#059669',
                            cancelButtonColor: '#666',
                            confirmButtonText: '🖨️ Cetak Struk',
                            cancelButtonText: 'OK',
                            width: 450,
                            showCloseButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            // Jika user klik Cetak Struk, langsung cetak
                            if (result.isConfirmed) {
                                // Langsung buka struk untuk print
                                window.open('print_ticket.php?id=' + response.data.id, '_blank');
                                resetForm();
                                location.reload();
                            } else {
                                // Jika user klik OK, reset form saja
                                resetForm();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Terjadi kesalahan sistem: ' + error, 'error');
                }
            });
        });

        // Reset Form
        function resetForm() {
            if (currentStep > 0) {
                Swal.fire({
                    title: 'Reset Form?',
                    text: 'Data yang sudah diinput akan hilang!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Reset!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#formTimbang')[0].reset();
                        $('#beratBruto, #beratTara').val('');
                        $('#summaryBox').slideUp();
                        $('#btnTimbang1').prop('disabled', false);
                        $('#btnTimbang2').prop('disabled', true);
                        $('#btnSimpan').prop('disabled', true);
                        $('.select2').val(null).trigger('change');
                        currentStep = 0;
                    }
                });
            } else {
                $('#formTimbang')[0].reset();
                $('.select2').val(null).trigger('change');
            }
        }

        // Format number
        function formatNumber(num) {
            return num.toString(); // Plain number without formatting
        }
    </script>
</body>
</html>