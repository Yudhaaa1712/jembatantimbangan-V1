<?php
// modules/masterdata/material/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Material - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$material = [
    'kode_material' => '',
    'nama_material' => '',
    'deskripsi' => '',
    'icon' => 'fa-cube',
    'satuan' => 'Kg',
    'status' => 'active'
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM materials WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $material = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Material tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_material = mysqli_real_escape_string($conn, $_POST['kode_material']);
    $nama_material = mysqli_real_escape_string($conn, $_POST['nama_material']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $icon = mysqli_real_escape_string($conn, $_POST['icon']);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Validasi
    if (empty($nama_material)) {
        $msg = '<div class="alert alert-danger">Nama material wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate kode_material
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM materials WHERE kode_material = '$kode_material'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode material sudah ada!</div>';
            } else {
                $query = "INSERT INTO materials (kode_material, nama_material, deskripsi, icon, satuan, status)
                         VALUES ('$kode_material', '$nama_material', '$deskripsi', '$icon', '$satuan', '$status')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Material berhasil ditambahkan!</div>';

                    // Clear cache untuk material list
                    require_once '../../../includes/cache_manager.php';
                    // Clear all material cache keys
                    for ($i = 0; $i < 24; $i++) {
                        $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $cache_key = 'material_list_' . date('Y-m-d') . '-' . $hour;
                        cache_delete($cache_key);
                    }

                    // Reset form
                    $material = [
                        'kode_material' => '',
                        'nama_material' => '',
                        'deskripsi' => '',
                        'icon' => 'fa-cube',
                        'satuan' => 'Kg',
                        'status' => 'active'
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan material!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM materials WHERE kode_material = '$kode_material' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode material sudah ada!</div>';
            } else {
                $query = "UPDATE materials SET
                         kode_material = '$kode_material',
                         nama_material = '$nama_material',
                         deskripsi = '$deskripsi',
                         icon = '$icon',
                         satuan = '$satuan',
                         status = '$status'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Material berhasil diperbarui!</div>';

                    // Clear cache untuk material list
                    require_once '../../../includes/cache_manager.php';
                    // Clear all material cache keys
                    for ($i = 0; $i < 24; $i++) {
                        $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $cache_key = 'material_list_' . date('Y-m-d') . '-' . $hour;
                        cache_delete($cache_key);
                    }

                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM materials WHERE id = '$id'");
                    $material = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui material!</div>';
                }
            }
        }
    }
}

include '../../../includes/header.php';
?>

<style>
    .form-container {
        max-width: 800px;
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

    .form-input.lowercase {
        text-transform: lowercase;
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

    .preview-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
    }

    .preview-title {
        color: #dc2626;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .preview-content {
        color: #fff;
        font-size: 14px;
        line-height: 1.6;
    }

    .material-icon-preview {
        width: 60px;
        height: 60px;
        background: rgba(220, 38, 38, 0.1);
        border: 2px solid #dc2626;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
        font-size: 28px;
        margin-bottom: 15px;
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
            <i class="fas fa-cube"></i>
            <?php echo $action == 'add' ? 'Tambah Material' : 'Edit Material'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="materialForm">
        <!-- Informasi Dasar -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi Dasar Material
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Kode Material</label>
                    <input type="text" name="kode_material" class="form-input" value="<?php echo $material['kode_material']; ?>" maxlength="20" placeholder="MTL001">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Nama Material <span class="required">*</span>
                    </label>
                    <input type="text" name="nama_material" class="form-input" value="<?php echo $material['nama_material']; ?>" required maxlength="100" placeholder="Tandan Buah Segar">
                </div>
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <select name="icon" class="form-select">
                        <option value="fa-cube" <?php echo $material['icon'] == 'fa-cube' ? 'selected' : ''; ?>>Kotak</option>
                        <option value="fa-apple-alt" <?php echo $material['icon'] == 'fa-apple-alt' ? 'selected' : ''; ?>>Apel</option>
                        <option value="fa-oil-can" <?php echo $material['icon'] == 'fa-oil-can' ? 'selected' : ''; ?>>Minyak</option>
                        <option value="fa-seedling" <?php echo $material['icon'] == 'fa-seedling' ? 'selected' : ''; ?>>Tanaman</option>
                        <option value="fa-leaf" <?php echo $material['icon'] == 'fa-leaf' ? 'selected' : ''; ?>>Daun</option>
                        <option value="fa-box" <?php echo $material['icon'] == 'fa-box' ? 'selected' : ''; ?>>Kardus</option>
                        <option value="fa-truck" <?php echo $material['icon'] == 'fa-truck' ? 'selected' : ''; ?>>Truk</option>
                        <option value="fa-trash" <?php echo $material['icon'] == 'fa-trash' ? 'selected' : ''; ?>>Sampah</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $material['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $material['status'] == 'inactive' ? 'checked' : ''; ?>>
                            <label for="status_inactive">Tidak Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Satuan -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-balance-scale"></i>
                Satuan
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <select name="satuan" class="form-select">
                        <option value="Kg" <?php echo $material['satuan'] == 'Kg' ? 'selected' : ''; ?>>Kilogram (Kg)</option>
                        <option value="Ton" <?php echo $material['satuan'] == 'Ton' ? 'selected' : ''; ?>>Ton</option>
                        <option value="Kwintal" <?php echo $material['satuan'] == 'Kwintal' ? 'selected' : ''; ?>>Kwintal</option>
                        <option value="Liter" <?php echo $material['satuan'] == 'Liter' ? 'selected' : ''; ?>>Liter</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Deskripsi -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-align-left"></i>
                Deskripsi Material
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Jelaskan jenis material ini secara detail"><?php echo $material['deskripsi']; ?></textarea>
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
    const form = document.getElementById('materialForm');
    const namaMaterialInput = form.querySelector('input[name="nama_material"]');
    const deskripsiInput = form.querySelector('textarea[name="deskripsi"]');

    // Auto-generate kode material from nama material
    namaMaterialInput.addEventListener('input', function() {
        const kodeInput = form.querySelector('input[name="kode_material"]');
        if (!kodeInput.value) {
            const name = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (name.length >= 3) {
                const prefix = name.substring(0, 3);
                const randomNum = Math.floor(Math.random() * 900) + 100;
                kodeInput.value = prefix + randomNum;
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