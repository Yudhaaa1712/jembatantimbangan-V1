<?php
// modules/masterdata/material/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Material - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$material = [
    'kode_material' => '',
    'jenis_material' => '',
    'deskripsi' => '',
    'kategori' => 'umum',
    'satuan' => 'Kg',
    'harga_per_kg' => '',
    'status' => 'active',
    'keterangan' => ''
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM material_types WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $material = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Material tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_material = mysqli_real_escape_string($conn, $_POST['kode_material']);
    $jenis_material = mysqli_real_escape_string($conn, strtolower($_POST['jenis_material']));
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $kategori = mysqli_real_escape_string($conn, strtolower($_POST['kategori']));
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $harga_per_kg = mysqli_real_escape_string($conn, $_POST['harga_per_kg']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    // Validasi
    if (empty($jenis_material)) {
        $msg = '<div class="alert alert-danger">Jenis material wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate jenis_material
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM material_types WHERE jenis_material = '$jenis_material'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Jenis material sudah ada!</div>';
            } else {
                $query = "INSERT INTO material_types (kode_material, jenis_material, deskripsi, kategori, satuan, harga_per_kg, status, keterangan)
                         VALUES ('$kode_material', '$jenis_material', '$deskripsi', '$kategori', '$satuan', '$harga_per_kg', '$status', '$keterangan')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Material berhasil ditambahkan!</div>';
                    // Reset form
                    $material = [
                        'kode_material' => '',
                        'jenis_material' => '',
                        'deskripsi' => '',
                        'kategori' => 'umum',
                        'satuan' => 'Kg',
                        'harga_per_kg' => '',
                        'status' => 'active',
                        'keterangan' => ''
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan material!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM material_types WHERE jenis_material = '$jenis_material' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Jenis material sudah ada!</div>';
            } else {
                $query = "UPDATE material_types SET
                         kode_material = '$kode_material',
                         jenis_material = '$jenis_material',
                         deskripsi = '$deskripsi',
                         kategori = '$kategori',
                         satuan = '$satuan',
                         harga_per_kg = '$harga_per_kg',
                         status = '$status',
                         keterangan = '$keterangan'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Material berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM material_types WHERE id = '$id'");
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
                        Jenis Material <span class="required">*</span>
                    </label>
                    <input type="text" name="jenis_material" class="form-input lowercase" value="<?php echo $material['jenis_material']; ?>" required maxlength="50" placeholder="tbs, cpo, kernel">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-select">
                        <option value="umum" <?php echo $material['kategori'] == 'umum' ? 'selected' : ''; ?>>Umum</option>
                        <option value="bahan_baku" <?php echo $material['kategori'] == 'bahan_baku' ? 'selected' : ''; ?>>Bahan Baku</option>
                        <option value="produksi" <?php echo $material['kategori'] == 'produksi' ? 'selected' : ''; ?>>Produksi</option>
                        <option value="limbah" <?php echo $material['kategori'] == 'limbah' ? 'selected' : ''; ?>>Limbah</option>
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

        <!-- Harga dan Satuan -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-dollar-sign"></i>
                Harga dan Satuan
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Harga per Kg</label>
                    <div class="input-group">
                        <input type="number" name="harga_per_kg" class="form-input" value="<?php echo $material['harga_per_kg']; ?>" min="0" step="100" placeholder="2000">
                        <span class="input-group-text">Rp/Kg</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <select name="satuan" class="form-select">
                        <option value="Kg" <?php echo $material['satuan'] == 'Kg' ? 'selected' : ''; ?>>Kilogram (Kg)</option>
                        <option value="Ton" <?php echo $material['satuan'] == 'Ton' ? 'selected' : ''; ?>>Ton</option>
                        <option value="Kwintal" <?php echo $material['satuan'] == 'Kwintal' ? 'selected' : ''; ?>>Kwintal</option>
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

        <!-- Keterangan -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-sticky-note"></i>
                Keterangan
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea name="keterangan" class="form-textarea" placeholder="Masukkan catatan atau keterangan tambahan" rows="3"><?php echo $material['keterangan']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="preview-card">
            <h4 class="preview-title">Preview Material</h4>
            <div class="material-icon-preview">
                <i class="fas fa-cube" id="previewIcon"></i>
            </div>
            <div class="preview-content">
                <strong id="previewName"><?php echo $material['jenis_material'] ? ucfirst($material['jenis_material']) : 'Nama Material'; ?></strong><br>
                <span id="previewDesc"><?php echo $material['deskripsi'] ?: 'Deskripsi material akan muncul di sini'; ?></span><br>
                <span style="color: #22c55e; font-weight: 700;" id="previewPrice">Rp <?php echo number_format($material['harga_per_kg'] ?? 0, 0, ',', '.'); ?>/Kg</span>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('materialForm');
    const jenisMaterialInput = form.querySelector('input[name="jenis_material"]');
    const deskripsiInput = form.querySelector('textarea[name="deskripsi"]');
    const hargaInput = form.querySelector('input[name="harga_per_kg"]');

    // Update preview in real-time
    function updatePreview() {
        const materialType = jenisMaterialInput.value.toLowerCase();
        const deskripsi = deskripsiInput.value;
        const harga = hargaInput.value;

        // Update name
        document.getElementById('previewName').textContent = materialType ? materialType.charAt(0).toUpperCase() + materialType.slice(1) : 'Nama Material';

        // Update description
        document.getElementById('previewDesc').textContent = deskripsi || 'Deskripsi material akan muncul di sini';

        // Update price
        const formattedPrice = new Intl.NumberFormat('id-ID').format(parseInt(harga) || 0);
        document.getElementById('previewPrice').textContent = 'Rp ' + formattedPrice + '/Kg';

        // Update icon based on material type
        const iconMap = {
            'tbs': 'fa-apple-alt',
            'cpo': 'fa-oil-can',
            'kernel': 'fa-seedling',
            'brondolan': 'fa-leaf',
            'limbah': 'fa-trash'
        };

        let iconClass = 'fa-cube'; // default
        for (const [key, value] of Object.entries(iconMap)) {
            if (materialType.includes(key)) {
                iconClass = value;
                break;
            }
        }

        const iconElement = document.getElementById('previewIcon');
        iconElement.className = 'fas ' + iconClass;
    }

    jenisMaterialInput.addEventListener('input', updatePreview);
    deskripsiInput.addEventListener('input', updatePreview);
    hargaInput.addEventListener('input', updatePreview);

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Initialize preview
    updatePreview();
});
</script>

</body>
</html>