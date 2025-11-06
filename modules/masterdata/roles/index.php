<?php
// modules/masterdata/roles/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Hak Akses - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (!empty($id) && is_numeric($id)) {
        // Check if role has users
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = (SELECT role_name FROM user_roles WHERE id = '$id')");
        $result = mysqli_fetch_assoc($check);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Role tidak dapat dihapus karena masih digunakan oleh pengguna!</div>';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM user_roles WHERE id = '$id'");
            if ($delete) {
                $msg = '<div class="alert alert-success">Role berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus role!</div>';
            }
        }
    }
}

// Get roles
$query = "SELECT * FROM user_roles ORDER BY display_name ASC";
$result = mysqli_query($conn, $query);

include '../../../includes/header.php';
?>

<style>
    .page-container {
        max-width: 1200px;
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

    .roles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .role-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 25px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .role-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #dc2626, #ef4444);
    }

    .role-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
    }

    .role-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .role-icon {
        width: 50px;
        height: 50px;
        background: rgba(220, 38, 38, 0.1);
        border: 2px solid #dc2626;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
        font-size: 24px;
    }

    .role-title {
        color: #dc2626;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .role-name {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 15px;
    }

    .role-description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .permissions-section {
        margin-bottom: 20px;
    }

    .permissions-title {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .permission-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .permission-tag {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 4px;
        padding: 4px 8px;
        color: #dc2626;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
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

    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-action {
        background: transparent;
        border: 1px solid #dc2626;
        border-radius: 6px;
        padding: 6px 12px;
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
        padding: 60px 20px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: rgba(220, 38, 38, 0.5);
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

        .roles-grid {
            grid-template-columns: 1fr;
        }

        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-user-shield"></i>
            Hak Akses
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Role
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_roles = mysqli_num_rows($result);
        $active_roles = 0;

        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)) {
            if ($row['status'] == 'active') {
                $active_roles++;
            }
        }

        // Count users per role
        $role_counts = [];
        $query_users = "SELECT role, COUNT(*) as user_count FROM users GROUP BY role";
        $result_users = mysqli_query($conn, $query_users);
        while($row = mysqli_fetch_assoc($result_users)) {
            $role_counts[$row['role']] = $row['user_count'];
        }
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_roles); ?></div>
            <div class="stat-label">Total Roles</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_roles); ?></div>
            <div class="stat-label">Roles Aktif</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format(array_sum($role_counts)); ?></div>
            <div class="stat-label">Total Pengguna</div>
        </div>
    </div>

    <!-- Roles Grid -->
    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="roles-grid">
            <?php
            mysqli_data_seek($result, 0);
            while($row = mysqli_fetch_assoc($result)):
            ?>
            <div class="role-card">
                <div class="role-header">
                    <div class="role-icon">
                        <?php
                        $icon = 'fa-user';
                        if ($row['role_name'] == 'admin') $icon = 'fa-user-cog';
                        elseif ($row['role_name'] == 'operator') $icon = 'fa-user-tie';
                        elseif ($row['role_name'] == 'viewer') $icon = 'fa-user-eye';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div>
                        <span class="status-badge <?php echo $row['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $row['status'] == 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                    </div>
                </div>

                <div class="role-title"><?php echo $row['display_name']; ?></div>
                <div class="role-name">Role: <?php echo $row['role_name']; ?></div>
                <div class="role-description"><?php echo $row['description'] ?? 'Tidak ada deskripsi'; ?></div>

                <?php
                // Parse permissions
                $permissions = [];
                if (!empty($row['permissions'])) {
                    $perm_data = json_decode($row['permissions'], true);
                    if ($perm_data && isset($perm_data['all'])) {
                        $permissions = $perm_data['all'];
                    }
                }
                ?>

                <?php if (!empty($permissions)): ?>
                <div class="permissions-section">
                    <div class="permissions-title">Permissions</div>
                    <div class="permission-tags">
                        <?php foreach ($permissions as $permission): ?>
                        <span class="permission-tag"><?php echo $permission; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div style="color: rgba(255,255,255,0.6); font-size: 12px; margin-bottom: 15px;">
                    <i class="fas fa-users"></i> <?php echo $role_counts[$row['role_name']] ?? 0; ?> pengguna
                </div>

                <div class="action-buttons">
                    <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit
                    </a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['display_name']; ?>')" class="btn-action btn-delete">
                        <i class="fas fa-trash"></i>
                        Hapus
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-shield"></i><br>
            <strong>Belum ada data role</strong><br>
            <span>Tambah role baru untuk memulai</span>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Role?',
        html: 'Apakah Anda yakin ingin menghapus role <strong>' + name + '</strong>?',
        text: 'Role yang memiliki pengguna tidak dapat dihapus',
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

    // Add hover effects to cards
    const cards = document.querySelectorAll('.role-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

</body>
</html>