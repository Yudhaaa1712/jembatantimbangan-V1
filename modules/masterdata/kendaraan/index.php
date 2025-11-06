<?php
// modules/masterdata/kendaraan/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Data Kendaraan - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (!empty($id) && is_numeric($id)) {
        // Check if vehicle has transactions
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_kendaraan = '$id'");
        $result = mysqli_fetch_assoc($check);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Kendaraan tidak dapat dihapus karena memiliki transaksi!</div>';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM kendaraan WHERE id = '$id'");
            if ($delete) {
                $msg = '<div class="alert alert-success">Kendaraan berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus kendaraan!</div>';
            }
        }
    }
}

// Get vehicles with supplier info
$query = "SELECT k.*, s.nama_supplier FROM kendaraan k
          LEFT JOIN supplier s ON k.id_supplier = s.id
          ORDER BY k.no_polisi ASC";
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

    .status-maintenance {
        background: rgba(251, 191, 36, 0.2);
        color: #fbbf24;
        border: 1px solid #fbbf24;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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

    .vehicle-type-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
        border: 1px solid rgba(220, 38, 38, 0.3);
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

        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-truck"></i>
            Data Kendaraan
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Kendaraan
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-group">
                <label class="search-label">Cari Kendaraan</label>
                <input type="text" name="search" class="search-input" placeholder="No polisi atau nama supir..." value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="search-group">
                <label class="search-label">Jenis Kendaraan</label>
                <select name="jenis_kendaraan" class="search-input">
                    <option value="">Semua Jenis</option>
                    <option value="truk" <?php echo ($_GET['jenis_kendaraan'] ?? '') == 'truk' ? 'selected' : ''; ?>>Truk</option>
                    <option value="tronton" <?php echo ($_GET['jenis_kendaraan'] ?? '') == 'tronton' ? 'selected' : ''; ?>>Tronton</option>
                    <option value="container" <?php echo ($_GET['jenis_kendaraan'] ?? '') == 'container' ? 'selected' : ''; ?>>Container</option>
                    <option value="pickup" <?php echo ($_GET['jenis_kendaraan'] ?? '') == 'pickup' ? 'selected' : ''; ?>>Pickup</option>
                    <option value="lainnya" <?php echo ($_GET['jenis_kendaraan'] ?? '') == 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                </select>
            </div>
            <div class="search-group">
                <label class="search-label">Status</label>
                <select name="status" class="search-input">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                    <option value="maintenance" <?php echo ($_GET['status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
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
                    <th>No. Polisi</th>
                    <th>Jenis Kendaraan</th>
                    <th>Pemilik</th>
                    <th>Supir Default</th>
                    <th>Kapasitas Maks</th>
                    <th>Supplier</th>
                    <th>RFID Tag</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $search = $_GET['search'] ?? '';
                $jenis_kendaraan = $_GET['jenis_kendaraan'] ?? '';
                $status = $_GET['status'] ?? '';

                // Build query
                $query = "SELECT k.*, s.nama_supplier FROM kendaraan k
                          LEFT JOIN supplier s ON k.id_supplier = s.id WHERE 1=1";

                if (!empty($search)) {
                    $query .= " AND (k.no_polisi LIKE '%$search%' OR k.nama_supir LIKE '%$search%' OR k.pemilik LIKE '%$search%')";
                }
                if (!empty($jenis_kendaraan)) {
                    $query .= " AND k.jenis_kendaraan = '$jenis_kendaraan'";
                }
                if (!empty($status)) {
                    $query .= " AND k.status = '$status'";
                }
                $query .= " ORDER BY k.no_polisi ASC";

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo $row['no_polisi']; ?></strong></td>
                    <td>
                        <span class="vehicle-type-badge"><?php echo ucfirst($row['jenis_kendaraan']); ?></span>
                    </td>
                    <td><?php echo $row['pemilik'] ?? '-'; ?></td>
                    <td><?php echo $row['nama_supir'] ?? '-'; ?></td>
                    <td><?php echo number_format($row['kapasitas_maksimal'] ?? 0, 0, ',', '.'); ?> Kg</td>
                    <td><?php echo $row['nama_supplier'] ?? '-'; ?></td>
                    <td><?php echo $row['rfid_tag'] ?? '-'; ?></td>
                    <td>
                        <span class="status-badge
                            <?php
                            if ($row['status'] == 'active') echo 'status-active';
                            elseif ($row['status'] == 'maintenance') echo 'status-maintenance';
                            else echo 'status-inactive';
                            ?>">
                            <?php
                            if ($row['status'] == 'active') echo 'Aktif';
                            elseif ($row['status'] == 'maintenance') echo 'Maintenance';
                            else echo 'Tidak Aktif';
                            ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['no_polisi']; ?>')" class="btn-action btn-delete">
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
                    <td colspan="10" class="empty-state">
                        <i class="fas fa-truck"></i><br>
                        <strong>Belum ada data kendaraan</strong><br>
                        <span>Tambah kendaraan baru untuk memulai</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(id, noPolisi) {
    Swal.fire({
        title: 'Hapus Kendaraan?',
        html: 'Apakah Anda yakin ingin menghapus kendaraan <strong>' + noPolisi + '</strong>?',
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