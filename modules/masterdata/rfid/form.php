<?php
// modules/masterdata/rfid/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form RFID Tag - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$rfid = [
    'tag_uid' => '',
    'deskripsi' => '',
    'status' => 'active'
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM rfid_tags WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $rfid = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">RFID tag tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tag_uid = mysqli_real_escape_string($conn, $_POST['tag_uid']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Validasi
    if (empty($tag_uid)) {
        $msg = '<div class="alert alert-danger">Tag UID wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate tag_uid
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM rfid_tags WHERE tag_uid = '$tag_uid'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Tag UID sudah ada!</div>';
            } else {
                $query = "INSERT INTO rfid_tags (tag_uid, deskripsi, status)
                         VALUES ('$tag_uid', '$deskripsi', '$status')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">RFID tag berhasil ditambahkan!</div>';
                    // Reset form
                    $rfid = [
                        'tag_uid' => '',
                        'deskripsi' => '',
                        'status' => 'active'
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan RFID tag!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM rfid_tags WHERE tag_uid = '$tag_uid' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Tag UID sudah ada!</div>';
            } else {
                $query = "UPDATE rfid_tags SET
                         tag_uid = '$tag_uid',
                         deskripsi = '$deskripsi',
                         status = '$status'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">RFID tag berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM rfid_tags WHERE id = '$id'");
                    $rfid = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui RFID tag!</div>';
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

    .tag-uid-input {
        font-family: 'Courier New', monospace;
        text-transform: uppercase;
        letter-spacing: 1px;
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

    .btn-scan {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-scan:hover {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
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
        gap: 10px;
    }

    .input-group .form-input {
        flex: 1;
    }

    .rfid-info {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .rfid-info-title {
        color: #dc2626;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rfid-info-content {
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

        .input-group {
            flex-direction: column;
        }
    }
</style>

<div class="form-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-wifi"></i>
            <?php echo $action == 'add' ? 'Tambah RFID Tag' : 'Edit RFID Tag'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="rfidForm">
        <!-- Informasi Tag -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi RFID Tag
            </h3>

            <div class="rfid-info">
                <div class="rfid-info-title">
                    <i class="fas fa-info-circle"></i>
                    Petunjuk Pembacaan Tag
                </div>
                <div class="rfid-info-content">
                    Letakkan RFID tag dekat dengan pembaca untuk mendapatkan UID. Pastikan tag dalam jangkauan pembaca dan tidak ada halangan antara tag dan pembaca.
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">
                        Tag UID <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <input type="text" name="tag_uid" class="form-input tag-uid-input"
                               value="<?php echo $rfid['tag_uid']; ?>" required
                               maxlength="50" placeholder="Scan atau masukkan UID tag RFID"
                               pattern="[A-F0-9]+" title="Hanya karakter hexadecimal (A-F, 0-9)">
                        <button type="button" class="btn-scan" onclick="scanRFID()">
                            <i class="fas fa-wifi"></i>
                            Scan
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $rfid['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $rfid['status'] == 'inactive' ? 'checked' : ''; ?>>
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
                Deskripsi Tag
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Deskripsi atau catatan tentang RFID tag ini"><?php echo $rfid['deskripsi']; ?></textarea>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('rfidForm');
    const tagInput = form.querySelector('input[name="tag_uid"]');

    // Format Tag UID input to uppercase and remove invalid characters
    tagInput.addEventListener('input', function() {
        let value = this.value.toUpperCase();
        // Remove any characters that are not hexadecimal
        value = value.replace(/[^A-F0-9]/g, '');
        this.value = value;
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

function scanRFID() {
    Swal.fire({
        title: 'Scan RFID Tag',
        html: 'Menunggu pembacaan RFID tag...<br><small>Letakkan tag dekat pembaca</small>',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simulate RFID scanning
    setTimeout(() => {
        // Generate random RFID UID for demo purposes
        const randomUID = generateRandomRFID();

        Swal.fire({
            title: 'Tag Terdeteksi!',
            html: 'RFID UID: <strong>' + randomUID + '</strong>',
            icon: 'success',
            showConfirmButton: true,
            confirmButtonColor: '#22c55e',
            confirmButtonText: 'Gunakan Tag Ini'
        }).then((result) => {
            if (result.isConfirmed) {
                document.querySelector('input[name="tag_uid"]').value = randomUID;
                // Add a subtle animation to show the field was updated
                const input = document.querySelector('input[name="tag_uid"]');
                input.style.transition = 'background-color 0.5s ease';
                input.style.backgroundColor = 'rgba(34, 197, 94, 0.2)';
                setTimeout(() => {
                    input.style.backgroundColor = 'transparent';
                }, 1000);
            }
        });
    }, 2000);
}

function generateRandomRFID() {
    // Generate random RFID UID (typically 8-16 hexadecimal characters)
    const length = 8 + Math.floor(Math.random() * 8); // 8-16 characters
    let uid = '';
    const hexChars = '0123456789ABCDEF';

    for (let i = 0; i < length; i++) {
        uid += hexChars.charAt(Math.floor(Math.random() * hexChars.length));
    }

    return uid;
}

// Auto-format for manual entry
document.querySelector('input[name="tag_uid"]').addEventListener('blur', function() {
    if (this.value.length > 0) {
        // Add spaces every 4 characters for readability
        let formatted = this.value.match(/.{1,4}/g)?.join(' ') || this.value;
        this.value = formatted;
    }
});

document.querySelector('input[name="tag_uid"]').addEventListener('focus', function() {
    // Remove spaces when editing
    this.value = this.value.replace(/\s/g, '');
});
</script>

</body>
</html>