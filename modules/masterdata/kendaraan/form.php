<?php
// modules/masterdata/kendaraan/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Kendaraan - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$vehicle = [
    'no_polisi' => '',
    'jenis_kendaraan' => 'truk',
    'pemilik' => '',
    'no_telepon' => '',
    'alamat' => '',
    'nama_supir' => '',
    'kapasitas_maksimal' => '',
    'id_supplier' => '',
    'rfid_tag' => '',
    'status' => 'active',
    'keterangan' => ''
];

$msg = '';

// Get suppliers for dropdown
$suppliers = [];
$query = "SELECT id, nama_supplier FROM supplier WHERE status = 'active' ORDER BY nama_supplier ASC";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $suppliers[] = $row;
}

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM kendaraan WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $vehicle = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Kendaraan tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_polisi = mysqli_real_escape_string($conn, strtoupper($_POST['no_polisi']));
    $jenis_kendaraan = mysqli_real_escape_string($conn, $_POST['jenis_kendaraan']);
    $pemilik = mysqli_real_escape_string($conn, $_POST['pemilik']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $nama_supir = mysqli_real_escape_string($conn, $_POST['nama_supir']);
    $kapasitas_maksimal = mysqli_real_escape_string($conn, $_POST['kapasitas_maksimal']);
    $id_supplier = mysqli_real_escape_string($conn, $_POST['id_supplier']);
    $rfid_tag = mysqli_real_escape_string($conn, $_POST['rfid_tag']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    // Validasi
    if (empty($no_polisi)) {
        $msg = '<div class="alert alert-danger">No. polisi wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate no_polisi
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kendaraan WHERE no_polisi = '$no_polisi'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">No. polisi sudah ada!</div>';
            } else {
                $query = "INSERT INTO kendaraan (no_polisi, jenis_kendaraan, pemilik, no_telepon, alamat, nama_supir, kapasitas_maksimal, id_supplier, rfid_tag, status, keterangan)
                         VALUES ('$no_polisi', '$jenis_kendaraan', '$pemilik', '$no_telepon', '$alamat', '$nama_supir', '$kapasitas_maksimal', '$id_supplier', '$rfid_tag', '$status', '$keterangan')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Kendaraan berhasil ditambahkan!</div>';
                    // Reset form
                    $vehicle = [
                        'no_polisi' => '',
                        'jenis_kendaraan' => 'truk',
                        'pemilik' => '',
                        'no_telepon' => '',
                        'alamat' => '',
                        'nama_supir' => '',
                        'kapasitas_maksimal' => '',
                        'id_supplier' => '',
                        'rfid_tag' => '',
                        'status' => 'active',
                        'keterangan' => ''
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan kendaraan!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kendaraan WHERE no_polisi = '$no_polisi' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">No. polisi sudah ada!</div>';
            } else {
                $query = "UPDATE kendaraan SET
                         no_polisi = '$no_polisi',
                         jenis_kendaraan = '$jenis_kendaraan',
                         pemilik = '$pemilik',
                         no_telepon = '$no_telepon',
                         alamat = '$alamat',
                         nama_supir = '$nama_supir',
                         kapasitas_maksimal = '$kapasitas_maksimal',
                         id_supplier = '$id_supplier',
                         rfid_tag = '$rfid_tag',
                         status = '$status',
                         keterangan = '$keterangan'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Kendaraan berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM kendaraan WHERE id = '$id'");
                    $vehicle = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui kendaraan!</div>';
                }
            }
        }
    }
}

include '../../../includes/header.php';
?>

<style>
    .form-container {
        max-width: 900px;
        margin: 20px auto;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(220, 38, 38, 0.2);
    }

    .page-title {
        color: #dc2626;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .btn-secondary {
        background: transparent;
        border: 2px solid #666;
        border-radius: 8px;
        padding: 10px 20px;
        color: #666;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-secondary:hover {
        border-color: #fff;
        color: #fff;
        text-decoration: none;
    }

    .form-section {
        margin-bottom: 30px;
    }

    .section-title {
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input, .form-select, .form-textarea {
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-input.uppercase {
        text-transform: uppercase;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid rgba(220, 38, 38, 0.2);
    }

    .btn-primary {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        border-color: #22c55e;
        color: #22c55e;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #ef4444;
    }

    .radio-group {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .radio-option input[type="radio"] {
        accent-color: #dc2626;
    }

    .radio-option label {
        color: #fff;
        font-size: 14px;
        cursor: pointer;
    }

    .input-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .input-group-text {
        background: rgba(220, 38, 38, 0.1);
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .form-container {
            margin: 15px;
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }
    }
</style>

<div class="form-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-truck"></i>
            <?php echo $action == 'add' ? 'Tambah Kendaraan' : 'Edit Kendaraan'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="vehicleForm">
        <!-- Informasi Dasar -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi Dasar Kendaraan
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        No. Polisi <span class="required">*</span>
                    </label>
                    <input type="text" name="no_polisi" class="form-input uppercase" value="<?php echo $vehicle['no_polisi']; ?>" required maxlength="15" placeholder="B 1234 ABC">
                </div>
                <div class="form-group">
                    <label class="form-label">Jenis Kendaraan</label>
                    <select name="jenis_kendaraan" class="form-select">
                        <option value="truk" <?php echo $vehicle['jenis_kendaraan'] == 'truk' ? 'selected' : ''; ?>>Truk</option>
                        <option value="tronton" <?php echo $vehicle['jenis_kendaraan'] == 'tronton' ? 'selected' : ''; ?>>Tronton</option>
                        <option value="container" <?php echo $vehicle['jenis_kendaraan'] == 'container' ? 'selected' : ''; ?>>Container</option>
                        <option value="pickup" <?php echo $vehicle['jenis_kendaraan'] == 'pickup' ? 'selected' : ''; ?>>Pickup</option>
                        <option value="lainnya" <?php echo $vehicle['jenis_kendaraan'] == 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $vehicle['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_maintenance" name="status" value="maintenance" <?php echo $vehicle['status'] == 'maintenance' ? 'checked' : ''; ?>>
                            <label for="status_maintenance">Maintenance</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $vehicle['status'] == 'inactive' ? 'checked' : ''; ?>>
                            <label for="status_inactive">Tidak Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Pemilik -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-user"></i>
                Informasi Pemilik
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Pemilik</label>
                    <input type="text" name="pemilik" class="form-input" value="<?php echo $vehicle['pemilik']; ?>" maxlength="100" placeholder="Nama perusahaan atau pemilik">
                </div>
                <div class="form-group">
                    <label class="form-label">No. Telepon</label>
                    <input type="tel" name="no_telepon" class="form-input" value="<?php echo $vehicle['no_telepon']; ?>" maxlength="20" placeholder="0812-3456-7890">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier (opsional)</label>
                    <select name="id_supplier" class="form-select">
                        <option value="">-- Pilih Supplier --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>" <?php echo $vehicle['id_supplier'] == $supplier['id'] ? 'selected' : ''; ?>>
                            <?php echo $supplier['nama_supplier']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Informasi Supir -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-id-card"></i>
                Informasi Supir Default
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Supir</label>
                    <input type="text" name="nama_supir" class="form-input" value="<?php echo $vehicle['nama_supir']; ?>" maxlength="50" placeholder="Nama supir default">
                </div>
                <div class="form-group">
                    <label class="form-label">RFID Tag</label>
                    <input type="text" name="rfid_tag" class="form-input" value="<?php echo $vehicle['rfid_tag']; ?>" maxlength="50" placeholder="RFID identifier">
                </div>
                <div class="form-group">
                    <label class="form-label">Kapasitas Maksimal</label>
                    <div class="input-group">
                        <input type="number" name="kapasitas_maksimal" class="form-input" value="<?php echo $vehicle['kapasitas_maksimal']; ?>" min="0" step="100" placeholder="50000">
                        <span class="input-group-text">Kg</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alamat -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Alamat
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="alamat" class="form-textarea" placeholder="Masukkan alamat lengkap pemilik kendaraan"><?php echo $vehicle['alamat']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Keterangan -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-sticky-note"></i>
                Keterangan
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea name="keterangan" class="form-textarea" placeholder="Masukkan catatan atau keterangan tambahan" rows="3"><?php echo $vehicle['keterangan']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-times"></i>
                Batal
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i>
                <?php echo $action == 'add' ? 'Simpan' : 'Perbarui'; ?>
            </button>
        </div>
    </form>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('vehicleForm');
    const noPolisiInput = form.querySelector('input[name="no_polisi"]');

    // Auto-format no polisi
    noPolisiInput.addEventListener('input', function() {
        let value = this.value.toUpperCase().replace(/[^A-Z0-9\s]/g, '');

        // Format: B 1234 ABC
        if (value.length > 0) {
            // Find first letter(s)
            const letters = value.match(/^[A-Z]+/);
            // Find numbers
            const numbers = value.match(/\d+/);
            // Find last letters
            const lastLetters = value.match(/[A-Z]+$/);

            if (letters && numbers) {
                let formatted = letters[0];
                const numberPart = value.substring(letters[0].length);
                const numberMatch = numberPart.match(/\d+/);

                if (numberMatch) {
                    formatted += ' ' + numberMatch[0];
                    const remaining = numberPart.substring(numberMatch[0].length).trim();
                    if (remaining) {
                        formatted += ' ' + remaining;
                    }
                }

                this.value = formatted;
            } else {
                this.value = value;
            }
        } else {
            this.value = value;
        }
    });

    // Format phone number
    const phoneInput = form.querySelector('input[name="no_telepon"]');
    phoneInput.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            if (value.length <= 4) {
                this.value = value;
            } else if (value.length <= 8) {
                this.value = value.substring(0, 4) + '-' + value.substring(4);
            } else {
                this.value = value.substring(0, 4) + '-' + value.substring(4, 8) + '-' + value.substring(8, 12);
            }
        }
    });

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>

</body>
</html>