<?php
require_once 'config/database.php';

// Tambahkan field nama_supplier ke tabel transaksi_timbangan
$alter_query = "ALTER TABLE transaksi_timbangan ADD COLUMN nama_supplier_manual VARCHAR(100) AFTER id_supplier";

if (mysqli_query($conn, $alter_query)) {
    echo "✅ Field 'nama_supplier_manual' berhasil ditambahkan ke tabel transaksi_timbangan<br>";
} else {
    echo "⚠️ Field mungkin sudah ada atau error: " . mysqli_error($conn) . "<br>";
}

// Cek struktur tabel transaksi_timbangan
echo "<h3>Struktur Tabel transaksi_timbangan:</h3>";
$result = mysqli_query($conn, "DESCRIBE transaksi_timbangan");

echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "</tr>";
}
echo "</table>";
?>