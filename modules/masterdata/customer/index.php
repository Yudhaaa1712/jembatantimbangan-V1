<?php
// modules/masterdata/customer/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Data Customer - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id > 0) {
        // Check if customer has transactions - using prepared statement
        $check_query = "SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_customer = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'i', $id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $result = mysqli_fetch_assoc($check_result);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Customer tidak dapat dihapus karena memiliki transaksi!</div>';
        } else {
            // Delete using prepared statement
            $delete_query = "DELETE FROM customer WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, 'i', $id);

            if (mysqli_stmt_execute($delete_stmt)) {
                $msg = '<div class="alert alert-success">Customer berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus customer: ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($delete_stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Get customers
$query = "SELECT * FROM customer ORDER BY nama_customer ASC";
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

    .status-blacklist {
        background: rgba(239, 68, 68, 0.3);
        color: #ef4444;
        border: 1px solid #ef4444;
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

    .credit-limit {
        font-weight: 600;
    }

    .credit-limit.positive {
        color: #22c55e;
    }

    .credit-limit.negative {
        color: #ef4444;
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
            <i class="fas fa-handshake"></i>
            Data Customer
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Customer
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-group">
                <label class="search-label">Cari Customer</label>
                <input type="text" name="search" class="search-input" placeholder="Nama customer atau kode..." value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="search-group">
                <label class="search-label">Status</label>
                <select name="status" class="search-input">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                    <option value="blacklist" <?php echo ($_GET['status'] ?? '') == 'blacklist' ? 'selected' : ''; ?>>Blacklist</option>
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
                    <th>Kode Customer</th>
                    <th>Nama Customer</th>
                    <th>Kontak Person</th>
                    <th>Telepon</th>
                    <th>Kredit Limit</th>
                    <th>Alamat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $search = $_GET['search'] ?? '';
                $status = $_GET['status'] ?? '';

                // Build query
                $query = "SELECT * FROM customer WHERE 1=1";
                if (!empty($search)) {
                    $query .= " AND (nama_customer LIKE '%$search%' OR kode_customer LIKE '%$search%')";
                }
                if (!empty($status)) {
                    $query .= " AND status = '$status'";
                }
                $query .= " ORDER BY nama_customer ASC";

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo $row['kode_customer']; ?></strong></td>
                    <td><?php echo $row['nama_customer']; ?></td>
                    <td><?php echo $row['kontak_person'] ?? '-'; ?></td>
                    <td><?php echo $row['no_telepon'] ?? '-'; ?></td>
                    <td>
                        <span class="credit-limit <?php echo $row['kredit_limit'] > 0 ? 'positive' : 'negative'; ?>">
                            Rp <?php echo number_format($row['kredit_limit'] ?? 0, 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td><?php echo substr($row['alamat'] ?? '-', 0, 30) . (strlen($row['alamat'] ?? '') > 30 ? '...' : ''); ?></td>
                    <td>
                        <span class="status-badge <?php
                            if ($row['status'] == 'active') echo 'status-active';
                            elseif ($row['status'] == 'blacklist') echo 'status-blacklist';
                            else echo 'status-inactive';
                        ?>">
                            <?php
                            if ($row['status'] == 'active') echo 'Aktif';
                            elseif ($row['status'] == 'blacklist') echo 'Blacklist';
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
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['nama_customer']; ?>')" class="btn-action btn-delete">
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
                    <td colspan="9" class="empty-state">
                        <i class="fas fa-inbox"></i><br>
                        <strong>Belum ada data customer</strong><br>
                        <span>Tambah customer baru untuk memulai</span>
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
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Customer?',
        html: 'Apakah Anda yakin ingin menghapus customer <strong>' + name + '</strong>?',
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