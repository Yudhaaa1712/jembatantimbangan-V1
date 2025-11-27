<?php
// modules/masterdata/customer/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Customer - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$customer = [
    'kode_customer' => '',
    'nama_customer' => '',
    'alamat' => '',
    'no_telepon' => '',
    'email' => '',
    'npwp' => '',
    'kontak_person' => '',
    'kredit_limit' => '',
    'status' => 'active',
    'keterangan' => ''
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM customer WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $customer = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Customer tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_customer = mysqli_real_escape_string($conn, $_POST['kode_customer']);
    $nama_customer = mysqli_real_escape_string($conn, $_POST['nama_customer']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $npwp = mysqli_real_escape_string($conn, $_POST['npwp']);
    $kontak_person = mysqli_real_escape_string($conn, $_POST['kontak_person']);
    $kredit_limit = mysqli_real_escape_string($conn, str_replace(['.', ','], '', $_POST['kredit_limit']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    // Validasi
    if (empty($kode_customer) || empty($nama_customer)) {
        $msg = '<div class="alert alert-danger">Kode customer dan nama customer wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate kode_customer
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM customer WHERE kode_customer = '$kode_customer'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode customer sudah ada!</div>';
            } else {
                $query = "INSERT INTO customer (kode_customer, nama_customer, alamat, no_telepon, email, npwp, kontak_person, kredit_limit, status, keterangan)
                         VALUES ('$kode_customer', '$nama_customer', '$alamat', '$no_telepon', '$email', '$npwp', '$kontak_person', '$kredit_limit', '$status', '$keterangan')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Customer berhasil ditambahkan!</div>';
                    // Reset form
                    $customer = [
                        'kode_customer' => '',
                        'nama_customer' => '',
                        'alamat' => '',
                        'no_telepon' => '',
                        'email' => '',
                        'npwp' => '',
                        'kontak_person' => '',
                        'kredit_limit' => '',
                        'status' => 'active',
                        'keterangan' => ''
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan customer!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM customer WHERE kode_customer = '$kode_customer' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Kode customer sudah ada!</div>';
            } else {
                $query = "UPDATE customer SET
                         kode_customer = '$kode_customer',
                         nama_customer = '$nama_customer',
                         alamat = '$alamat',
                         no_telepon = '$no_telepon',
                         email = '$email',
                         npwp = '$npwp',
                         kontak_person = '$kontak_person',
                         kredit_limit = '$kredit_limit',
                         status = '$status',
                         keterangan = '$keterangan'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Customer berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM customer WHERE id = '$id'");
                    $customer = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui customer!</div>';
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

    .info-card {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .info-card-title {
        color: #dc2626;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card-content {
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
    }
</style>

<div class="form-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-handshake"></i>
            <?php echo $action == 'add' ? 'Tambah Customer' : 'Edit Customer'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="customerForm">
        <!-- Informasi Dasar -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi Dasar Customer
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Kode Customer <span class="required">*</span>
                    </label>
                    <input type="text" name="kode_customer" class="form-input" value="<?php echo $customer['kode_customer']; ?>" required maxlength="20" placeholder="CST001">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Nama Customer <span class="required">*</span>
                    </label>
                    <input type="text" name="nama_customer" class="form-input" value="<?php echo $customer['nama_customer']; ?>" required maxlength="100" placeholder="Nama perusahaan customer">
                </div>
                <div class="form-group">
                    <label class="form-label">Kontak Person</label>
                    <input type="text" name="kontak_person" class="form-input" value="<?php echo $customer['kontak_person']; ?>" maxlength="50" placeholder="Nama kontak person">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $customer['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $customer['status'] == 'inactive' ? 'checked' : ''; ?>>
                            <label for="status_inactive">Tidak Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_blacklist" name="status" value="blacklist" <?php echo $customer['status'] == 'blacklist' ? 'checked' : ''; ?>>
                            <label for="status_blacklist">Blacklist</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Kontak -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-address-book"></i>
                Informasi Kontak
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">No. Telepon</label>
                    <input type="tel" name="no_telepon" class="form-input" value="<?php echo $customer['no_telepon']; ?>" maxlength="20" placeholder="0812-3456-7890">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo $customer['email']; ?>" maxlength="100" placeholder="email@customer.com">
                </div>
                <div class="form-group">
                    <label class="form-label">NPWP</label>
                    <input type="text" name="npwp" class="form-input" value="<?php echo $customer['npwp']; ?>" maxlength="30" placeholder="Nomor NPWP">
                </div>
            </div>
        </div>

        <!-- Kredit Limit -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-dollar-sign"></i>
                Kredit Limit
            </h3>
            <div class="info-card">
                <div class="info-card-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Kredit
                </div>
                <div class="info-card-content">
                    Tentukan limit kredit yang diberikan kepada customer untuk pembelian sistem kredit. Nilai 0 berarti tidak ada limit kredit.
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Kredit Limit</label>
                    <div class="input-group">
                        <input type="text" name="kredit_limit" class="form-input" value="<?php echo number_format($customer['kredit_limit'] ?? 0, 0, ',', '.'); ?>" placeholder="10000000">
                        <span class="input-group-text">Rp</span>
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
                    <textarea name="alamat" class="form-textarea" placeholder="Masukkan alamat lengkap customer"><?php echo $customer['alamat']; ?></textarea>
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
                    <textarea name="keterangan" class="form-textarea" placeholder="Masukkan catatan atau keterangan tambahan" rows="3"><?php echo $customer['keterangan']; ?></textarea>
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
    const form = document.getElementById('customerForm');

    // Auto-generate kode customer
    const namaInput = form.querySelector('input[name="nama_customer"]');
    const kodeInput = form.querySelector('input[name="kode_customer"]');

    namaInput.addEventListener('input', function() {
        if (!form.querySelector('input[name="id"]')?.value) {
            // Generate kode from first 3 letters of name
            const name = this.value.toUpperCase().replace(/[^A-Z]/g, '');
            if (name.length >= 3) {
                const prefix = name.substring(0, 3);
                // Add random number
                const randomNum = Math.floor(Math.random() * 900) + 100;
                kodeInput.value = prefix + randomNum;
            }
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

    // Format kredit limit
    const kreditInput = form.querySelector('input[name="kredit_limit"]');
    kreditInput.addEventListener('input', function() {
        let value = this.value.replace(/[^\d]/g, '');
        if (value) {
            this.value = parseInt(value).toLocaleString('id-ID');
        } else {
            this.value = '';
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