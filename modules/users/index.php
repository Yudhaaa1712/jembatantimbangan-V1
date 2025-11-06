<?php
// modules/users/index.php
require_once '../../config/database.php';
check_role(['admin']);

$page_title = "Manajemen Pengguna - Sistem";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    switch($action) {
        case 'add_user':
            $username = clean_input($_POST['username']);
            $nama_lengkap = clean_input($_POST['nama_lengkap']);
            $role = clean_input($_POST['role']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $check_sql = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows > 0) {
                $error_message = "Username sudah ada!";
            } else {
                $sql = "INSERT INTO users (username, password, nama_lengkap, role, status)
                        VALUES (?, ?, ?, ?, 'active')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $username, $password, $nama_lengkap, $role);

                if ($stmt->execute()) {
                    $success_message = "User berhasil ditambahkan!";
                } else {
                    $error_message = "Gagal menambahkan user!";
                }
            }
            break;

        case 'edit_user':
            $user_id = (int)$_POST['user_id'];
            $nama_lengkap = clean_input($_POST['nama_lengkap']);
            $role = clean_input($_POST['role']);
            $status = clean_input($_POST['status']);

            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama_lengkap=?, role=?, status=?, password=?, updated_at=NOW()
                        WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $nama_lengkap, $role, $status, $password, $user_id);
            } else {
                $sql = "UPDATE users SET nama_lengkap=?, role=?, status=?, updated_at=NOW()
                        WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $nama_lengkap, $role, $status, $user_id);
            }

            if ($stmt->execute()) {
                $success_message = "User berhasil diupdate!";
            } else {
                $error_message = "Gagal mengupdate user!";
            }
            break;

        case 'delete_user':
            $user_id = (int)$_POST['user_id'];

            if ($user_id == $_SESSION['user_id']) {
                $error_message = "Tidak dapat menghapus akun sendiri!";
            } else {
                $sql = "UPDATE users SET status='inactive', updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $success_message = "User berhasil dinonaktifkan!";
                } else {
                    $error_message = "Gagal menonaktifkan user!";
                }
            }
            break;

        case 'toggle_status':
            $user_id = (int)$_POST['user_id'];
            $new_status = clean_input($_POST['new_status']);

            if ($user_id == $_SESSION['user_id']) {
                $error_message = "Tidak dapat mengubah status akun sendiri!";
            } else {
                $sql = "UPDATE users SET status=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_status, $user_id);

                if ($stmt->execute()) {
                    $success_message = "Status user berhasil diupdate!";
                } else {
                    $error_message = "Gagal mengupdate status user!";
                }
            }
            break;
    }
}

// Get all users
$users_sql = "SELECT id, username, nama_lengkap, role, status, last_login, created_at
              FROM users
              ORDER BY created_at DESC";
$users_result = mysqli_query($conn, $users_sql);

include '../../includes/header.php';
?>

<style>
/* Styles untuk Manajemen Pengguna - Compatible dengan Bootstrap 5 */
.main-title {
    color: #dc2626 !important;
    font-weight: 700;
    font-size: 1.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 0 2px 10px rgba(220, 38, 38, 0.3);
}

/* Alert Bootstrap Override */
.alert {
    border-radius: 10px !important;
    border: none !important;
    backdrop-filter: blur(10px);
}

.alert-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.1) 100%) !important;
    border: 1px solid rgba(34, 197, 94, 0.3) !important;
    color: #4ade80 !important;
    box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.1) 100%) !important;
    border: 1px solid rgba(239, 68, 68, 0.3) !important;
    color: #f87171 !important;
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.1);
}

/* Card Override */
.card {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.95) 0%, rgba(20, 20, 20, 0.9) 100%) !important;
    border: 2px solid rgba(220, 38, 38, 0.1) !important;
    border-radius: 15px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
    backdrop-filter: blur(20px);
}

/* Table Override */
.table {
    background: transparent !important;
    color: #fff !important;
}

.table thead th {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(220, 38, 38, 0.1) 100%) !important;
    color: #dc2626 !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.8px !important;
    font-size: 12px !important;
    border: none !important;
}

.table td {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    color: rgba(0, 0, 0, 0.8) !important;
}

.table tbody tr:hover {
    background: rgba(0, 0, 0, 0.05) !important;
}

.table tbody tr:hover td {
    color: #000000ff !important;
}

/* Badge Override */
.badge {
    padding: 6px 12px !important;
    border-radius: 20px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.badge.bg-danger, .badge.badge-admin {
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
}

.badge.bg-primary, .badge.badge-operator {
    background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
}

.badge.bg-secondary, .badge.badge-viewer {
    background: linear-gradient(135deg, #6b7280, #4b5563) !important;
}

.badge.bg-success, .badge.badge-active {
    background: linear-gradient(135deg, #22c55e, #16a34a) !important;
}

.badge.bg-warning, .badge.badge-inactive {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
}

/* Button Override */
.btn {
    border-radius: 8px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    transition: all 0.3s ease !important;
}

.btn-primary {
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3) !important;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4) !important;
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280, #4b5563) !important;
    border: none !important;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #9ca3af, #6b7280) !important;
    transform: translateY(-2px) !important;
}

.btn-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    border: none !important;
}

.btn-info:hover {
    background: linear-gradient(135deg, #60a5fa, #3b82f6) !important;
    transform: translateY(-2px) !important;
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    border: none !important;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
    transform: translateY(-2px) !important;
}

.btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a) !important;
    border: none !important;
}

.btn-success:hover {
    background: linear-gradient(135deg, #4ade80, #22c55e) !important;
    transform: translateY(-2px) !important;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    border: none !important;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #f87171, #ef4444) !important;
    transform: translateY(-2px) !important;
}

.btn-sm {
    font-size: 11px !important;
    padding: 6px 12px !important;
}

/* Modal Override */
.modal-content {
    background: linear-gradient(135deg, rgba(20, 20, 20, 0.98) 0%, rgba(10, 10, 10, 0.95) 100%) !important;
    border: 2px solid rgba(220, 38, 38, 0.2) !important;
    border-radius: 15px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
    backdrop-filter: blur(20px) !important;
    color: #fff !important;
}

.modal-header {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%) !important;
    border-bottom: 1px solid rgba(220, 38, 38, 0.2) !important;
    border-radius: 13px 13px 0 0 !important;
}

.modal-title {
    color: #dc2626 !important;
    font-weight: 700 !important;
}

.modal-footer {
    background: rgba(0, 0, 0, 0.1) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 0 0 13px 13px !important;
}

.btn-close {
    filter: brightness(0) invert(1) !important;
    opacity: 0.7 !important;
}

.btn-close:hover {
    opacity: 1 !important;
}

/* Form Override */
.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-size: 14px !important;
}

.form-control, .form-select {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 2px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 8px !important;
    color: #fff !important;
    transition: all 0.3s ease !important;
}

.form-control:focus, .form-select:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
    color: #fff !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4) !important;
}

.form-select option {
    background: #1a1a1a !important;
    color: #fff !important;
}

.form-text {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Custom Utility Classes */
.text-white {
    color: #fff !important;
}

.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column !important;
        gap: 6px !important;
    }

    .modal-footer {
        flex-direction: column !important;
    }

    .modal-footer .btn {
        width: 100% !important;
    }
}
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="main-title"><i class="fas fa-users"></i> Manajemen Pengguna</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Tambah User
                </button>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="text-white"><?= htmlspecialchars($user['username']) ?></td>
                                        <td class="text-white"><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                                        <td>
                                            <span class="badge <?= $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'operator' ? 'bg-primary' : 'bg-secondary') ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= $user['status'] == 'active' ? 'Aktif' : 'Tidak Aktif' ?>
                                            </span>
                                        </td>
                                        <td class="text-white"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-' ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-info edit-user"
                                                        data-user-id="<?= $user['id'] ?>"
                                                        data-username="<?= htmlspecialchars($user['username']) ?>"
                                                        data-nama="<?= htmlspecialchars($user['nama_lengkap']) ?>"
                                                        data-role="<?= $user['role'] ?>"
                                                        data-status="<?= $user['status'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-<?= $user['status'] == 'active' ? 'warning' : 'success' ?> toggle-status"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-new-status="<?= $user['status'] == 'active' ? 'inactive' : 'active' ?>">
                                                        <i class="fas fa-<?= $user['status'] == 'active' ? 'ban' : 'check' ?>"></i>
                                                    </button>

                                                    <button type="button" class="btn btn-sm btn-danger delete-user"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-username="<?= htmlspecialchars($user['username']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="Masukkan username">
                    </div>

                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required placeholder="Masukkan nama lengkap">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Minimal 6 karakter">
                        <div class="form-text">Minimal 6 karakter</div>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="operator">Operator (Timbang 1 & 2, Transaksi)</option>
                            <option value="viewer">Viewer (Hanya melihat)</option>
                            <option value="admin">Admin (Akses penuh)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                        <div class="form-text">Username tidak dapat diubah</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" id="edit_nama" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="password" id="edit_password" placeholder="Kosongkan jika tidak ingin mengubah password">
                        <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" name="role" id="edit_role" required>
                            <option value="operator">Operator (Timbang 1 & 2, Transaksi)</option>
                            <option value="viewer">Viewer (Hanya melihat)</option>
                            <option value="admin">Admin (Akses penuh)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete User -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">

                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Apakah Anda yakin ingin menonaktifkan user <strong id="delete_username"></strong>?
                        <hr>
                        <small class="mb-0">User akan dinonaktifkan, tidak akan bisa login tapi datanya tetap tersimpan.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Nonaktifkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Toggle Status -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-info">
                    <i class="fas fa-toggle-on"></i> Konfirmasi Ubah Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" id="toggle_user_id">
                    <input type="hidden" name="new_status" id="toggle_new_status">

                    <div class="alert alert-info">
                        <i class="fas fa-question-circle me-2"></i>
                        <p id="toggle_message" class="mb-0"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Ya
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Edit user
    $('.edit-user').click(function() {
        const data = $(this).data();
        $('#edit_user_id').val(data.userId);
        $('#edit_username').val(data.username);
        $('#edit_nama').val(data.nama);
        $('#edit_role').val(data.role);
        $('#edit_status').val(data.status);
        $('#editUserModal').modal('show');
    });

    // Delete user
    $('.delete-user').click(function() {
        const data = $(this).data();
        $('#delete_user_id').val(data.userId);
        $('#delete_username').text(data.username);
        $('#deleteUserModal').modal('show');
    });

    // Toggle status
    $('.toggle-status').click(function() {
        const data = $(this).data();
        const newStatusText = data.newStatus === 'active' ? 'mengaktifkan' : 'menonaktifkan';

        $('#toggle_user_id').val(data.userId);
        $('#toggle_new_status').val(data.newStatus);
        $('#toggle_message').html(`Apakah Anda yakin ingin <strong>${newStatusText}</strong> user ini?`);
        $('#toggleStatusModal').modal('show');
    });
});
</script>

</body>
</html>