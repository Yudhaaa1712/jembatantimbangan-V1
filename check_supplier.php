<?php
require_once 'config/database.php';

echo "<h3>Checking Supplier Data in Recent Transactions</h3>";

$query = "SELECT no_tiket, id_supplier, nama_supplier_manual,
          (SELECT nama_supplier FROM supplier WHERE id = transaksi_timbangan.id_supplier) as supplier_name
          FROM transaksi_timbangan
          WHERE status = 'timbang_1'
          ORDER BY created_at DESC
          LIMIT 5";

$result = mysqli_query($conn, $query);

echo "<table border='1'>";
echo "<tr><th>No Tiket</th><th>ID Supplier</th><th>Nama Supplier Manual</th><th>Supplier Name (from DB)</th></tr>";

while($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['no_tiket']}</td>";
    echo "<td>{$row['id_supplier']}</td>";
    echo "<td>{$row['nama_supplier_manual']}</td>";
    echo "<td>{$row['supplier_name']}</td>";
    echo "</tr>";
}

echo "</table>";

mysqli_close($conn);

// Delete debug file
unlink(__FILE__);
?>