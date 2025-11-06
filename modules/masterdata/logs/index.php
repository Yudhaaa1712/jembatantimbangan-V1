<?php
// modules/masterdata/logs/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Log Aktivitas - Master Data";

// Handle filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user'] ?? '';
$module_filter = $_GET['module'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Build query
$query = "SELECT al.*, u.nama_lengkap, u.username
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE 1=1";

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= '$date_from'";
}
if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= '$date_to'";
}
if (!empty($user_filter)) {
    $query .= " AND al.user_id = '$user_filter'";
}
if (!empty($module_filter)) {
    $query .= " AND al.module = '$module_filter'";
}
if (!empty($action_filter)) {
    $query .= " AND al.action = '$action_filter'";
}

$query .= " ORDER BY al.created_at DESC LIMIT 1000";

$result = mysqli_query($conn, $query);

// Get distinct users for filter
$users_query = "SELECT DISTINCT u.id, u.nama_lengkap, u.username
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.user_id IS NOT NULL
                ORDER BY u.nama_lengkap";
$users_result = mysqli_query($conn, $users_query);

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

    .btn-export {
        background: linear-gradient(135deg, #059669, #047857);
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

    .btn-export:hover {
        background: linear-gradient(135deg, #10b981, #059669);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        color: #fff;
        text-decoration: none;
    }

    .filter-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-label {
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input {
        width: 100%;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .filter-input:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .btn-filter {
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-filter:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-reset {
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-reset:hover {
        border-color: #fff;
        color: #fff;
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
        max-height: 600px;
        overflow-y: auto;
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
        position: sticky;
        top: 0;
        z-index: 10;
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

    .log-time {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
    }

    .log-date {
        color: #dc2626;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .log-user {
        color: #059669;
        font-weight: 600;
    }

    .log-module {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        margin-right: 5px;
    }

    .module-transaksi { background: rgba(220, 38, 38, 0.2); color: #dc2626; }
    .module-masterdata { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .module-user { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    .module-system { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }

    .log-action {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-create { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
    .action-update { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    .action-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    .action-login { background: rgba(168, 85, 247, 0.2); color: #a855f7; }
    .action-logout { background: rgba(107, 114, 128, 0.2); color: #6b7280; }

    .log-description {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        max-width: 300px;
        word-wrap: break-word;
    }

    .log-ip {
        color: rgba(255, 255, 255, 0.5);
        font-size: 12px;
        font-family: monospace;
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

        .filter-form {
            grid-template-columns: 1fr;
        }

        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .log-description {
            max-width: 200px;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-history"></i>
            Log Aktivitas
        </h1>
        <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn-export">
            <i class="fas fa-download"></i>
            Export
        </a>
    </div>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_logs = mysqli_num_rows($result);

        // Today's logs
        $today_query = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()";
        $today_result = mysqli_query($conn, $today_query);
        $today_logs = mysqli_fetch_assoc($today_result)['count'];

        // This week logs
        $week_query = "SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $week_result = mysqli_query($conn, $week_query);
        $week_logs = mysqli_fetch_assoc($week_result)['count'];

        // Active users today
        $active_users_query = "SELECT COUNT(DISTINCT user_id) as count FROM activity_logs WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL";
        $active_users_result = mysqli_query($conn, $active_users_query);
        $active_users = mysqli_fetch_assoc($active_users_result)['count'];
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_logs); ?></div>
            <div class="stat-label">Total Log</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($today_logs); ?></div>
            <div class="stat-label">Hari Ini</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-value"><?php echo number_format($week_logs); ?></div>
            <div class="stat-label">Minggu Ini</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_users); ?></div>
            <div class="stat-label">User Aktif Hari Ini</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label class="filter-label">Dari Tanggal</label>
                <input type="date" name="date_from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Sampai Tanggal</label>
                <input type="date" name="date_to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Pengguna</label>
                <select name="user" class="filter-input">
                    <option value="">Semua Pengguna</option>
                    <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo $user['nama_lengkap'] ?? $user['username']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Module</label>
                <select name="module" class="filter-input">
                    <option value="">Semua Module</option>
                    <option value="transaksi" <?php echo $module_filter == 'transaksi' ? 'selected' : ''; ?>>Transaksi</option>
                    <option value="masterdata" <?php echo $module_filter == 'masterdata' ? 'selected' : ''; ?>>Master Data</option>
                    <option value="user" <?php echo $module_filter == 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="system" <?php echo $module_filter == 'system' ? 'selected' : ''; ?>>System</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Aksi</label>
                <select name="action" class="filter-input">
                    <option value="">Semua Aksi</option>
                    <option value="create" <?php echo $action_filter == 'create' ? 'selected' : ''; ?>>Create</option>
                    <option value="update" <?php echo $action_filter == 'update' ? 'selected' : ''; ?>>Update</option>
                    <option value="delete" <?php echo $action_filter == 'delete' ? 'selected' : ''; ?>>Delete</option>
                    <option value="login" <?php echo $action_filter == 'login' ? 'selected' : ''; ?>>Login</option>
                    <option value="logout" <?php echo $action_filter == 'logout' ? 'selected' : ''; ?>>Logout</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i>
                    Filter
                </button>
            </div>
            <div class="filter-group">
                <a href="index.php" class="btn-reset">
                    <i class="fas fa-redo"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table Section -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Module</th>
                    <th>Aksi</th>
                    <th>Deskripsi</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <div class="log-date"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                <div class="log-time"><?php echo date('H:i:s', strtotime($row['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="log-user"><?php echo $row['nama_lengkap'] ?? $row['username'] ?? 'System'; ?></div>
                            </td>
                            <td>
                                <span class="log-module module-<?php echo $row['module']; ?>">
                                    <?php echo $row['module']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-action action-<?php echo $row['action']; ?>">
                                    <?php echo $row['action']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="log-description"><?php echo $row['description']; ?></div>
                            </td>
                            <td>
                                <div class="log-ip"><?php echo $row['ip_address'] ?? '-'; ?></div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i><br>
                            <strong>Belum ada data log</strong><br>
                            <span>Aktivitas sistem akan tampil di sini</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh logs every 30 seconds
    setInterval(function() {
        // Only auto-refresh if not filtering
        const hasFilters = <?php echo (empty($date_from) && empty($date_to) && empty($user_filter) && empty($module_filter) && empty($action_filter)) ? 'false' : 'true'; ?>;
        if (!hasFilters) {
            window.location.reload();
        }
    }, 30000);

    // Smooth scroll for table
    const tableContainer = document.querySelector('.table-container');
    if (tableContainer) {
        tableContainer.scrollTop = tableContainer.scrollHeight;
    }
});
</script>

</body>
</html>