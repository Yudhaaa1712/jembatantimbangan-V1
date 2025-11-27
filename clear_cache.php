<?php
// Clear cache script
require_once 'includes/cache_manager.php';
require_once 'config/database.php';

// Clear all supplier cache keys
for ($i = 0; $i < 24; $i++) {
    $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
    $cache_key = 'supplier_list_' . date('Y-m-d') . '-' . $hour;
    cache_delete($cache_key);
}

// Clear hourly cache for today
for ($i = 0; $i < 24; $i++) {
    $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
    $cache_key = 'supplier_list_' . date('Y-m-d-H');
    cache_delete($cache_key);
}

echo "Cache cleared successfully!<br>";

// Test supplier count
$query = "SELECT COUNT(*) as total FROM supplier";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "Total suppliers in database: " . $row['total'] . "<br>";

// Test active supplier count
$query = "SELECT COUNT(*) as total FROM supplier WHERE status = 'active'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "Active suppliers: " . $row['total'] . "<br>";

// Test inactive supplier count
$query = "SELECT COUNT(*) as total FROM supplier WHERE status = 'inactive'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "Inactive suppliers: " . $row['total'] . "<br>";

// Show all suppliers
echo "<br>All suppliers:<br>";
$query = "SELECT id, nama_supplier, status FROM supplier ORDER BY nama_supplier";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    echo "- {$row['nama_supplier']} (Status: {$row['status']})<br>";
}
?>