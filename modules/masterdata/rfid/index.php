<?php
// modules/masterdata/rfid/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "RFID Tags - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (!empty($id) && is_numeric($id)) {
        // Check if RFID tag is assigned to vehicle
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kendaraan WHERE rfid_tag = (SELECT tag_uid FROM rfid_tags WHERE id = '$id')");
        $result = mysqli_fetch_assoc($check);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Tag RFID tidak dapat dihapus karena masih digunakan oleh kendaraan!</div>';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM rfid_tags WHERE id = '$id'");
            if ($delete) {
                $msg = '<div class="alert alert-success">Tag RFID berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus tag RFID!</div>';
            }
        }
    }
}

// Get RFID tags
$query = "SELECT
            rt.*,
            k.no_polisi,
            k.merk,
            k.tipe,
            s.nama_supplier,
            rt.status as tag_status
          FROM rfid_tags rt
          LEFT JOIN kendaraan k ON rt.id = k.rfid_tag
          LEFT JOIN supplier s ON k.id_supplier = s.id
          ORDER BY rt.created_at DESC";
$result = mysqli_query($conn, $query);

include '../../../includes/header.php';
?>

<style>
    .page-container {
        max-width: 1400px;
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

    .btn-primary {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
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
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        color: #fff;
        text-decoration: none;
    }

    .search-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .search-form {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .search-group {
        flex: 1;
        min-width: 200px;
    }

    .search-label {
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: block;
    }

    .search-input {
        width: 100%;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.2);
    }

    .stat-icon {
        color: #dc2626;
        font-size: 28px;
        margin-bottom: 10px;
    }

    .stat-value {
        color: #fff;
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-container {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        padding: 20px;
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid #dc2626;
        color: #dc2626;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 13px;
    }

    .data-table td {
        border: 1px solid rgba(220, 38, 38, 0.2);
        color: #fff;
        padding: 12px;
        font-size: 14px;
    }

    .data-table tr:hover {
        background: rgba(220, 38, 38, 0.05);
    }

    .tag-uid {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #059669;
        background: rgba(5, 150, 105, 0.1);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
    }

    .vehicle-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .vehicle-plate {
        color: #dc2626;
        font-weight: 600;
        font-size: 14px;
    }

    .vehicle-type {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
    }

    .supplier-name {
        color: #f59e0b;
        font-size: 12px;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
        border: 1px solid #22c55e;
    }

    .status-inactive {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid #ef4444;
    }

    .status-assigned {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
        border: 1px solid #3b82f6;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-action {
        background: transparent;
        border: 1px solid #dc2626;
        border-radius: 6px;
        padding: 6px 10px;
        color: #dc2626;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-action:hover {
        background: #dc2626;
        color: #fff;
        text-decoration: none;
    }

    .btn-edit {
        border-color: #059669;
        color: #059669;
    }

    .btn-edit:hover {
        background: #059669;
    }

    .btn-delete {
        border-color: #dc2626;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #dc2626;
    }

    .btn-test {
        border-color: #f59e0b;
        color: #f59e0b;
    }

    .btn-test:hover {
        background: #f59e0b;
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

    .empty-state {
        text-align: center;
        padding: 40px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: rgba(220, 38, 38, 0.5);
    }

    @media (max-width: 768px) {
        .page-container {
            margin: 15px;
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .search-form {
            flex-direction: column;
        }

        .search-group {
            width: 100%;
        }

        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-wifi"></i>
            RFID Tags
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Tag
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_tags = mysqli_num_rows($result);
        $active_tags = 0;
        $assigned_tags = 0;
        $inactive_tags = 0;

        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)) {
            if ($row['tag_status'] == 'active') {
                $active_tags++;
            }
            if ($row['tag_status'] == 'assigned') {
                $assigned_tags++;
            }
            if ($row['tag_status'] == 'inactive') {
                $inactive_tags++;
            }
        }
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-wifi"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_tags); ?></div>
            <div class="stat-label">Total Tags</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_tags); ?></div>
            <div class="stat-label">Tags Aktif</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-link"></i>
            </div>
            <div class="stat-value"><?php echo number_format($assigned_tags); ?></div>
            <div class="stat-label">Tags Terpasang</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($inactive_tags); ?></div>
            <div class="stat-label">Tags Tidak Aktif</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-group">
                <label class="search-label">Cari Tag</label>
                <input type="text" name="search" class="search-input" placeholder="Tag UID atau deskripsi..." value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="search-group">
                <label class="search-label">Status</label>
                <select name="status" class="search-input">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="assigned" <?php echo ($_GET['status'] ?? '') == 'assigned' ? 'selected' : ''; ?>>Terpasang</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i>
                Cari
            </button>
        </form>
    </div>

    <!-- Table Section -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tag UID</th>
                    <th>Deskripsi</th>
                    <th>Kendaraan</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $search = $_GET['search'] ?? '';
                $status = $_GET['status'] ?? '';

                // Build query with search
                $query = "SELECT
                            rt.*,
                            k.no_polisi,
                            k.merk,
                            k.tipe,
                            s.nama_supplier,
                            rt.status as tag_status
                          FROM rfid_tags rt
                          LEFT JOIN kendaraan k ON rt.id = k.rfid_tag
                          LEFT JOIN supplier s ON k.id_supplier = s.id
                          WHERE 1=1";

                if (!empty($search)) {
                    $query .= " AND (rt.tag_uid LIKE '%$search%' OR rt.deskripsi LIKE '%$search%')";
                }
                if (!empty($status)) {
                    $query .= " AND rt.status = '$status'";
                }
                $query .= " ORDER BY rt.created_at DESC";

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td>
                        <span class="tag-uid"><?php echo $row['tag_uid']; ?></span>
                    </td>
                    <td><?php echo $row['deskripsi'] ?? '-'; ?></td>
                    <td>
                        <?php if ($row['no_polisi']): ?>
                            <div class="vehicle-info">
                                <div class="vehicle-plate"><?php echo $row['no_polisi']; ?></div>
                                <div class="vehicle-type"><?php echo $row['merk'] . ' ' . $row['tipe']; ?></div>
                            </div>
                        <?php else: ?>
                            <span style="color: rgba(255,255,255,0.5);">Belum terpasang</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['nama_supplier']): ?>
                            <div class="supplier-name"><?php echo $row['nama_supplier']; ?></div>
                        <?php else: ?>
                            <span style="color: rgba(255,255,255,0.5);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php
                            if ($row['tag_status'] == 'active') echo 'status-active';
                            elseif ($row['tag_status'] == 'assigned') echo 'status-assigned';
                            else echo 'status-inactive';
                        ?>">
                            <?php
                            if ($row['tag_status'] == 'active') echo 'Aktif';
                            elseif ($row['tag_status'] == 'assigned') echo 'Terpasang';
                            else echo 'Tidak Aktif';
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="javascript:void(0)" onclick="testTag('<?php echo $row['tag_uid']; ?>')" class="btn-action btn-test">
                                <i class="fas fa-wifi"></i>
                                Test
                            </a>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['tag_uid']; ?>')" class="btn-action btn-delete">
                                <i class="fas fa-trash"></i>
                                Hapus
                            </a>
                        </div>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-wifi"></i><br>
                        <strong>Belum ada data RFID tag</strong><br>
                        <span>Tambah tag RFID baru untuk memulai</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>

<script>
function confirmDelete(id, tagUid) {
    Swal.fire({
        title: 'Hapus RFID Tag?',
        html: 'Apakah Anda yakin ingin menghapus tag <strong>' + tagUid + '</strong>?',
        text: 'Tag yang terpasang pada kendaraan tidak dapat dihapus',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#666',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?action=delete&id=' + id;
        }
    });
}

function testTag(tagUid) {
    Swal.fire({
        title: 'Test RFID Tag',
        html: 'Mencoba membaca tag <strong>' + tagUid + '</strong>...',
        icon: 'info',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        didOpen: () => {
            Swal.showLoading();
        }
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.timer) {
            // Simulate test result
            const isSuccess = Math.random() > 0.3; // 70% success rate for demo

            if (isSuccess) {
                Swal.fire({
                    title: 'Test Berhasil!',
                    html: 'Tag <strong>' + tagUid + '</strong> terbaca dengan baik',
                    icon: 'success',
                    confirmButtonColor: '#22c55e'
                });
            } else {
                Swal.fire({
                    title: 'Test Gagal!',
                    html: 'Tag <strong>' + tagUid + '</strong> tidak terbaca',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
            }
        }
    });
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
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