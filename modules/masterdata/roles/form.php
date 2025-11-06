<?php
// modules/masterdata/roles/form.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Form Hak Akses - Master Data";

$action = $_GET['action'] ?? 'add';
$id = $_GET['id'] ?? '';

$role = [
    'role_name' => '',
    'display_name' => '',
    'description' => '',
    'permissions' => json_encode(['all' => ['create', 'read', 'update', 'delete']]),
    'status' => 'active'
];

$msg = '';

if ($action == 'edit' && !empty($id)) {
    $query = "SELECT * FROM user_roles WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $role = mysqli_fetch_assoc($result);
    } else {
        $msg = '<div class="alert alert-danger">Role tidak ditemukan!</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role_name = mysqli_real_escape_string($conn, strtolower($_POST['role_name']));
    $display_name = mysqli_real_escape_string($conn, $_POST['display_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Process permissions
    $permissions = [];
    $selected_permissions = $_POST['permissions'] ?? [];
    $all_permissions = $_POST['all_permissions'] ?? [];

    if (!empty($all_permissions)) {
        $permissions['all'] = $all_permissions;
    } else {
        foreach ($selected_permissions as $module => $actions) {
            if (is_array($actions)) {
                $permissions[$module] = array_values($actions);
            }
        }
    }

    $permissions_json = json_encode($permissions);

    // Validasi
    if (empty($role_name) || empty($display_name)) {
        $msg = '<div class="alert alert-danger">Role name dan display name wajib diisi!</div>';
    } else {
        if ($action == 'add') {
            // Check duplicate role_name
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_roles WHERE role_name = '$role_name'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Role name sudah ada!</div>';
            } else {
                $query = "INSERT INTO user_roles (role_name, display_name, description, permissions, status)
                         VALUES ('$role_name', '$display_name', '$description', '$permissions_json', '$status')";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Role berhasil ditambahkan!</div>';
                    // Reset form
                    $role = [
                        'role_name' => '',
                        'display_name' => '',
                        'description' => '',
                        'permissions' => json_encode(['all' => ['create', 'read', 'update', 'delete']]),
                        'status' => 'active'
                    ];
                } else {
                    $msg = '<div class="alert alert-danger">Gagal menambahkan role!</div>';
                }
            }
        } else {
            // Update
            $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_roles WHERE role_name = '$role_name' AND id != '$id'");
            $result = mysqli_fetch_assoc($check);

            if ($result['count'] > 0) {
                $msg = '<div class="alert alert-danger">Role name sudah ada!</div>';
            } else {
                $query = "UPDATE user_roles SET
                         role_name = '$role_name',
                         display_name = '$display_name',
                         description = '$description',
                         permissions = '$permissions_json',
                         status = '$status'
                         WHERE id = '$id'";

                if (mysqli_query($conn, $query)) {
                    $msg = '<div class="alert alert-success">Role berhasil diperbarui!</div>';
                    // Refresh data
                    $result = mysqli_query($conn, "SELECT * FROM user_roles WHERE id = '$id'");
                    $role = mysqli_fetch_assoc($result);
                } else {
                    $msg = '<div class="alert alert-danger">Gagal memperbarui role!</div>';
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

    .permissions-container {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .permission-type {
        margin-bottom: 20px;
    }

    .permission-type:last-child {
        margin-bottom: 0;
    }

    .permission-title {
        color: #dc2626;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .permission-modules {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .permission-module {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(220, 38, 38, 0.1);
        border-radius: 8px;
        padding: 15px;
    }

    .module-title {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
        text-transform: capitalize;
    }

    .permission-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .checkbox-group input[type="checkbox"] {
        accent-color: #dc2626;
    }

    .checkbox-group label {
        color: rgba(255, 255, 255, 0.8);
        font-size: 12px;
        cursor: pointer;
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

        .permission-modules {
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
            <i class="fas fa-user-shield"></i>
            <?php echo $action == 'add' ? 'Tambah Hak Akses' : 'Edit Hak Akses'; ?>
        </h1>
        <a href="index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <?php echo $msg; ?>

    <form method="POST" id="roleForm">
        <!-- Informasi Dasar -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informasi Role
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Role Name <span class="required">*</span>
                    </label>
                    <input type="text" name="role_name" class="form-input lowercase" value="<?php echo $role['role_name']; ?>" required maxlength="50" placeholder="manager">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Display Name <span class="required">*</span>
                    </label>
                    <input type="text" name="display_name" class="form-input" value="<?php echo $role['display_name']; ?>" required maxlength="100" placeholder="Manager">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="status_active" name="status" value="active" <?php echo $role['status'] == 'active' ? 'checked' : ''; ?>>
                            <label for="status_active">Aktif</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="status_inactive" name="status" value="inactive" <?php echo $role['status'] == 'inactive' ? 'checked' : ''; ?>>
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
                Deskripsi Role
            </h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-textarea" placeholder="Jelaskan tugas dan tanggung jawab role ini"><?php echo $role['description']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-key"></i>
                Permissions
            </h3>

            <div class="info-card">
                <div class="info-card-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Permissions
                </div>
                <div class="info-card-content">
                    Pilih permissions yang akan diberikan kepada role ini. permissions menentukan apa saja yang bisa dilakukan oleh pengguna dengan role ini di setiap module sistem.
                </div>
            </div>

            <div class="permissions-container">
                <!-- Global Permissions -->
                <div class="permission-type">
                    <div class="permission-title">
                        <i class="fas fa-globe"></i>
                        Global Permissions
                    </div>
                    <div class="permission-modules">
                        <div class="permission-module">
                            <div class="module-title">Semua Module</div>
                            <div class="permission-actions">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="all_permissions[]" value="create" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['all']) && in_array('create', $perm_data['all'])) echo 'checked';
                                    ?>>
                                    <label>Create</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="all_permissions[]" value="read" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['all']) && in_array('read', $perm_data['all'])) echo 'checked';
                                    ?>>
                                    <label>Read</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="all_permissions[]" value="update" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['all']) && in_array('update', $perm_data['all'])) echo 'checked';
                                    ?>>
                                    <label>Update</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="all_permissions[]" value="delete" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['all']) && in_array('delete', $perm_data['all'])) echo 'checked';
                                    ?>>
                                    <label>Delete</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Module-specific Permissions -->
                <div class="permission-type">
                    <div class="permission-title">
                        <i class="fas fa-cogs"></i>
                        Module-specific Permissions
                    </div>
                    <div class="permission-modules">
                        <div class="permission-module">
                            <div class="module-title">Transaksi</div>
                            <div class="permission-actions">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[transaksi][]" value="create" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['transaksi']) && in_array('create', $perm_data['transaksi'])) echo 'checked';
                                    ?>>
                                    <label>Create</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[transaksi][]" value="read" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['transaksi']) && in_array('read', $perm_data['transaksi'])) echo 'checked';
                                    ?>>
                                    <label>Read</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[transaksi][]" value="update" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['transaksi']) && in_array('update', $perm_data['transaksi'])) echo 'checked';
                                    ?>>
                                    <label>Update</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[transaksi][]" value="delete" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['transaksi']) && in_array('delete', $perm_data['transaksi'])) echo 'checked';
                                    ?>>
                                    <label>Delete</label>
                                </div>
                            </div>
                        </div>

                        <div class="permission-module">
                            <div class="module-title">Master Data</div>
                            <div class="permission-actions">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[masterdata][]" value="create" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['masterdata']) && in_array('create', $perm_data['masterdata'])) echo 'checked';
                                    ?>>
                                    <label>Create</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[masterdata][]" value="read" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['masterdata']) && in_array('read', $perm_data['masterdata'])) echo 'checked';
                                    ?>>
                                    <label>Read</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[masterdata][]" value="update" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['masterdata']) && in_array('update', $perm_data['masterdata'])) echo 'checked';
                                    ?>>
                                    <label>Update</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[masterdata][]" value="delete" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['masterdata']) && in_array('delete', $perm_data['masterdata'])) echo 'checked';
                                    ?>>
                                    <label>Delete</label>
                                </div>
                            </div>
                        </div>

                        <div class="permission-module">
                            <div class="module-title">Laporan</div>
                            <div class="permission-actions">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[laporan][]" value="create" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['laporan']) && in_array('create', $perm_data['laporan'])) echo 'checked';
                                    ?>>
                                    <label>Create</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[laporan][]" value="read" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['laporan']) && in_array('read', $perm_data['laporan'])) echo 'checked';
                                    ?>>
                                    <label>Read</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[laporan][]" value="update" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['laporan']) && in_array('update', $perm_data['laporan'])) echo 'checked';
                                    ?>>
                                    <label>Update</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[laporan][]" value="delete" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['laporan']) && in_array('delete', $perm_data['laporan'])) echo 'checked';
                                    ?>>
                                    <label>Delete</label>
                                </div>
                            </div>
                        </div>

                        <div class="permission-module">
                            <div class="module-title">Users</div>
                            <div class="permission-actions">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[users][]" value="create" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['users']) && in_array('create', $perm_data['users'])) echo 'checked';
                                    ?>>
                                    <label>Create</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[users][]" value="read" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['users']) && in_array('read', $perm_data['users'])) echo 'checked';
                                    ?>>
                                    <label>Read</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[users][]" value="update" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['users']) && in_array('update', $perm_data['users'])) echo 'checked';
                                    ?>>
                                    <label>Update</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[users][]" value="delete" <?php
                                        $perm_data = json_decode($role['permissions'], true);
                                        if ($perm_data && isset($perm_data['users']) && in_array('delete', $perm_data['users'])) echo 'checked';
                                    ?>>
                                    <label>Delete</label>
                                </div>
                            </div>
                        </div>
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
    const form = document.getElementById('roleForm');

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