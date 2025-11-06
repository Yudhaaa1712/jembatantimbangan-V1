<?php
// modules/masterdata/logs/export.php
require_once '../../../config/database.php';
check_role(['admin']);

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

$query .= " ORDER BY al.created_at DESC LIMIT 10000";

$result = mysqli_query($conn, $query);

// Export format
$format = $_GET['format'] ?? 'excel';

if ($format == 'excel') {
    // Excel export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="activity_logs_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Waktu</th>';
    echo '<th>Pengguna</th>';
    echo '<th>Module</th>';
    echo '<th>Aksi</th>';
    echo '<th>Deskripsi</th>';
    echo '<th>IP Address</th>';
    echo '</tr>';

    $no = 1;
    while($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($row['created_at'])) . '</td>';
        echo '<td>' . date('H:i:s', strtotime($row['created_at'])) . '</td>';
        echo '<td>' . ($row['nama_lengkap'] ?? $row['username'] ?? 'System') . '</td>';
        echo '<td>' . $row['module'] . '</td>';
        echo '<td>' . $row['action'] . '</td>';
        echo '<td>' . htmlspecialchars($row['description']) . '</td>';
        echo '<td>' . ($row['ip_address'] ?? '-') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
} elseif ($format == 'pdf') {
    // PDF export (simplified version)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="activity_logs_' . date('Y-m-d') . '.pdf"');

    // Create simple PDF content
    $content = '<h1>Laporan Aktivitas Sistem</h1>';
    $content .= '<p>Periode: ' . ($date_from ?: 'Awal') . ' - ' . ($date_to ?: date('d/m/Y')) . '</p>';
    $content .= '<table border="1" width="100%">';
    $content .= '<tr>';
    $content .= '<th>No</th>';
    $content .= '<th>Tanggal</th>';
    $content .= '<th>Waktu</th>';
    $content .= '<th>Pengguna</th>';
    $content .= '<th>Module</th>';
    $content .= '<th>Aksi</th>';
    $content .= '<th>Deskripsi</th>';
    $content .= '</tr>';

    $no = 1;
    while($row = mysqli_fetch_assoc($result)) {
        $content .= '<tr>';
        $content .= '<td>' . $no++ . '</td>';
        $content .= '<td>' . date('d/m/Y', strtotime($row['created_at'])) . '</td>';
        $content .= '<td>' . date('H:i:s', strtotime($row['created_at'])) . '</td>';
        $content .= '<td>' . ($row['nama_lengkap'] ?? $row['username'] ?? 'System') . '</td>';
        $content .= '<td>' . $row['module'] . '</td>';
        $content .= '<td>' . $row['action'] . '</td>';
        $content .= '<td>' . htmlspecialchars($row['description']) . '</td>';
        $content .= '</tr>';
    }

    $content .= '</table>';

    // For a real PDF, you would use a library like TCPDF or FPDF
    // This is a simplified HTML to PDF conversion
    echo $content;
}

// Log this export activity
$ip_address = $_SERVER['REMOTE_ADDR'];
$log_description = "Export log aktivitas dengan filter: " .
                  (empty($date_from) && empty($date_to) && empty($user_filter) && empty($module_filter) && empty($action_filter)
                   ? "Semua data"
                   : "Date: $date_from - $date_to, User: $user_filter, Module: $module_filter, Action: $action_filter");

$log_query = "INSERT INTO activity_logs (user_id, module, action, description, ip_address, created_at)
              VALUES ('{$_SESSION['user_id']}', 'masterdata', 'export', '$log_description', '$ip_address', NOW())";
mysqli_query($conn, $log_query);
?>