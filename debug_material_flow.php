<?php
require_once 'config/database.php';

echo "<h2>DEBUG MATERIAL FLOW</h2>";

// Get latest transaction
$query = "SELECT no_tiket, jenis_material, harga_per_kg, no_polisi, nama_supir, created_at
          FROM transaksi_timbangan
          ORDER BY created_at DESC
          LIMIT 5";

$result = mysqli_query($conn, $query);

echo "<h3>5 Latest Transactions:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%'>";
echo "<tr style='background: #f0f0f0;'>
      <th>Tiket</th><th>Material</th><th>Harga</th><th>No Polisi</th><th>Supir</th><th>Created</th>
      </tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $material_color = empty($row['jenis_material']) ? '#ffcccc' : '#ccffcc';
    $harga_color = $row['harga_per_kg'] == 0 ? '#ffcccc' : '#ccffcc';

    echo "<tr>";
    echo "<td>" . $row['no_tiket'] . "</td>";
    echo "<td style='background: $material_color'>" .
         (empty($row['jenis_material']) ? '<strong>NULL</strong>' : $row['jenis_material']) . "</td>";
    echo "<td style='background: $harga_color'>" . number_format($row['harga_per_kg'], 2) . "</td>";
    echo "<td>" . $row['no_polisi'] . "</td>";
    echo "<td>" . $row['nama_supir'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check error logs
echo "<h3>Check Error Logs:</h3>";
echo "<p>Buka XAMPP Control Panel → Apache → Error Log untuk melihat debug output</p>";
echo "<p>Cari baris dengan: <code>PHP POST DEBUG</code>, <code>QUERY DEBUG</code>, atau <code>FORM SUBMISSION DEBUG</code></p>";

// Check form HTML structure
echo "<h3>Material Field in Form:</h3>";
echo "<p>Material dropdown name: <code>material</code></p>";
echo "<p>Material dropdown harus ada di form dengan atribut <code>name='material'</code></p>";

mysqli_close($conn);
?>

<hr>
<h3>Test Instructions:</h3>
<ol>
    <li>Buka <a href="modules/timbangan/timbangan1.php">Timbangan 1</a></li>
    <li>Buka Developer Console (F12)</li>
    <li>Isi form lengkap, pastikan material dipilih</li>
    <li>Submit form</li>
    <li>Lihat Console Output untuk JavaScript debug</li>
    <li>Lihat Error Log untuk PHP debug</li>
    <li>Refresh halaman ini untuk melihat hasil di database</li>
</ol>