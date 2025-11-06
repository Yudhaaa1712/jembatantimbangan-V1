<?php
// modules/masterdata/categories/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Kategori Kendaraan - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$category = [
    'kode_kategori' => '',
    'nama_kategori' => '',
    'deskripsi' => '',
    'kapasitas_min' => '',
    'kapasitas_max' => '',
    'standar_berat' => '',
    'icon' => 'fas fa-truck',
    'status' => 'active'
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM kategori_kendaraan WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $category = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Kategori tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_kategori = mysqli_real_escape_string($conn, $_POST['kode_kategori']);
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $kapasitas_min = mysqli_real_escape_string($conn, str_replace(['.', ','], '', $_POST['kapasitas_min']));
    $kapasitas_max = mysqli_real_escape_string($conn, str_replace(['.', ','], '', $_POST['kapasitas_max']));
    $standar_berat = mysqli_real_escape_string($conn, str_replace(['.', ','], '', $_POST['standar_berat']));
    $icon = mysqli_real_escape_string($conn, $_POST['icon']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Validasi
    if (empty($kode_kategori) || empty($nama_kategori)) {
        $msg = '<div class="alert alert-danger">Kode kategori dan nama kategori wajib diisi!</div>';
    } elseif (!empty($kapasitas_min) && !empty($kapasitas_max) && $kapasitas_min > $kapasitas_max) {
        $msg = '<div class="alert alert-danger">Kapasitas minimum tidak boleh lebih besar dari kapasitas maksimum!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate kode_kategori
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kategori_kendaraan WHERE kode_kategori = '$kode_kategori'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode kategori sudah ada!</div>';
            } else {
                $query = "INSERT INTO kategori_kendaraan (kode_kategori, nama_kategori, deskripsi, kapasitas_min, kapasitas_max, standar_berat, icon, status)
                         VALUES ('$kode_kategori', '$nama_kategori', '$deskripsi', '$kapasitas_min', '$kapasitas_max', '$standar_berat', '$icon', '$status')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Kategori berhasil ditambahkan!</div>';
                    // Reset form
                    $category = [
                        'kode_kategori' => '',
                        'nama_kategori' => '',
                        'deskripsi' => '',
                        'kapasitas_min' => '',
                        'kapasitas_max' => '',
                        'standar_berat' => '',
                        'icon' => 'fas fa-truck',
                        'status' => 'active'
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan kategori!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kategori_kendaraan WHERE kode_kategori = '$kode_kategori' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode kategori sudah ada!</div>';
            } else {
                $query = "UPDATE kategori_kendaraan SET
                         kode_kategori = '$kode_kategori',
                         nama_kategori = '$nama_kategori',
                         deskripsi = '$deskripsi',
                         kapasitas_min = '$kapasitas_min',
                         kapasitas_max = '$kapasitas_max',
                         standar_berat = '$standar_berat',
                         icon = '$icon',
                         status = '$status'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Kategori berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM kategori_kendaraan WHERE id = '$id'");
                    $category = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui kategori!</div>';
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
        min-height: 80px;
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

    .icon-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .icon-option {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border: 2px solid #dc2626;
        border-radius: 8px;
        background: transparent;
        color: #dc2626;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .icon-option:hover {
        background: rgba(220, 38, 38, 0.1);
    }

    .icon-option.selected {
        background: #dc2626;
        color: #fff;
    }

    .icon-option input[type="radio"] {
        display: none;
    }

    .capacity-info {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .capacity-info-title {
        color: #dc2626;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .capacity-info-content {
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        line-height: 1.5;
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

        .icon-selector {
            grid-template-columns: repeat(6, 1fr);
        }
    }
</style>

<div class="form-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-tags"></i>
            <?php echo $action == 'add' ? 'Tambah Kategori' : 'Edit Kategori'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="categoryForm">
        <!-- Informasi Dasar -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi Dasar Kategori
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Kode Kategori <span class="required">*</span>
                    </label>
                    <input type="text" name="kode_kategori" class="form-input" value="<?php echo $category['kode_kategori']; ?>" required maxlength="10" placeholder="KAT001">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Nama Kategori <span class="required">*</span>
                    </label>
                    <input type="text" name="nama_kategori" class="form-input" value="<?php echo $category['nama_kategori']; ?>" required maxlength="50" placeholder="Nama kategori kendaraan">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $category['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $category['status'] == 'inactive' ? 'checked' : ''; ?>>
                            <label for="status_inactive">Tidak Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deskripsi -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-align-left"></i>
                Deskripsi Kategori
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Jelaskan jenis-jenis kendaraan yang termasuk dalam kategori ini"><?php echo $category['deskripsi']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Spesifikasi Kapasitas -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-weight"></i>
                Spesifikasi Kapasitas
            </h3>
            <div class="capacity-info">
                <div class="capacity-info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Kapasitas
                </div>
                <div class="capacity-info-content">
                    Tentukan range kapasitas muatan yang sesuai untuk kategori kendaraan ini. Kapasitas minimum dan maksimum digunakan untuk validasi saat transaksi.
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Kapasitas Minimum (kg)</label>
                    <input type="text" name="kapasitas_min" class="form-input" value="<?php echo number_format($category['kapasitas_min'] ?? 0, 0, ',', '.'); ?>" placeholder="1000">
                </div>
                <div class="form-group">
                    <label class="form-label">Kapasitas Maksimum (kg)</label>
                    <input type="text" name="kapasitas_max" class="form-input" value="<?php echo number_format($category['kapasitas_max'] ?? 0, 0, ',', '.'); ?>" placeholder="50000">
                </div>
                <div class="form-group">
                    <label class="form-label">Standar Berat Kosong (kg)</label>
                    <input type="text" name="standar_berat" class="form-input" value="<?php echo number_format($category['standar_berat'] ?? 0, 0, ',', '.'); ?>" placeholder="5000">
                </div>
            </div>
        </div>

        <!-- Icon -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-icons"></i>
                Pilih Icon
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <div class="icon-selector">
                        <?php
                        $icons = [
                            'fas fa-truck',
                            'fas fa-truck-pickup',
                            'fas fa-truck-loading',
                            'fas fa-shipping-fast',
                            'fas fa-bus',
                            'fas fa-van-shuttle',
                            'fas fa-tractor',
                            'fas fa-dumpster',
                            'fas fa-crate',
                            'fas fa-box-truck',
                            'fas fa-trailer',
                            'fas fa-car'
                        ];

                        foreach ($icons as $icon) {
                            $selected = ($category['icon'] ?? 'fas fa-truck') == $icon ? 'selected' : '';
                            echo '<label class="icon-option ' . $selected . '">';
                            echo '<input type="radio" name="icon" value="' . $icon . '" ' . ($selected ? 'checked' : '') . '>';
                            echo '<i class="' . $icon . '"></i>';
                            echo '</label>';
                        }
                        ?>
                    </div>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('categoryForm');

    // Auto-generate kode kategori
    const namaInput = form.querySelector('input[name="nama_kategori"]');
    const kodeInput = form.querySelector('input[name="kode_kategori"]');

    namaInput.addEventListener('input', function() {
        if (!form.querySelector('input[name="id"]')?.value) {
            // Generate kode from first 3 letters of name
            const name = this.value.toUpperCase().replace(/[^A-Z]/g, '');
            if (name.length >= 3) {
                const prefix = name.substring(0, 3);
                // Add random number
                const randomNum = Math.floor(Math.random() * 900) + 100;
                kodeInput.value = 'KAT' + randomNum;
            }
        }
    });

    // Format capacity inputs
    const capacityInputs = form.querySelectorAll('input[name="kapasitas_min"], input[name="kapasitas_max"], input[name="standar_berat"]');
    capacityInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString('id-ID');
            } else {
                this.value = '';
            }
        });
    });

    // Icon selection visual feedback
    const iconOptions = document.querySelectorAll('.icon-option');
    iconOptions.forEach(option => {
        option.addEventListener('click', function() {
            iconOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        });
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