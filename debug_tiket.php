<?php
require_once 'config/database.php';
check_role(['admin', 'operator']);

echo "<h1>DEBUG HTML GENERATION</h1>";

// Get pending transactions for timbang 2
$query = "SELECT tt.no_tiket, tt.berat_timbangan1, tt.berat_bruto, tt.no_polisi, tt.nama_supir,
                 tt.jenis_material, tt.harga_per_kg, s.nama_supplier
          FROM transaksi_timbangan tt
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          WHERE tt.status = 'timbang_1'
          ORDER BY tt.created_at DESC";
$result = mysqli_query($conn, $query);

echo "<h2>Database Results:</h2>";
echo "<table border='1'>";
echo "<tr><th>Tiket</th><th>Supir</th><th>Polisi</th><th>Supplier</th><th>Material</th><th>Berat1</th><th>Harga</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['no_tiket'] . "</td>";
    echo "<td>" . $row['nama_supir'] . "</td>";
    echo "<td>" . $row['no_polisi'] . "</td>";
    echo "<td>" . ($row['nama_supplier'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['jenis_material'] . "</td>";
    echo "<td>" . $row['berat_timbangan1'] . "</td>";
    echo "<td>" . $row['harga_per_kg'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Reset result pointer
mysqli_data_seek($result, 0);

echo "<h2>Generated HTML Options:</h2>";
echo "<select id='no_tiket' onchange='debugSelect(this)'>";
echo "<option value=''>-- Pilih tiket dari Timbangan 1 --</option>";

while ($row = mysqli_fetch_assoc($result)):
    // Clean weight data - fix the decimal issue
    $berat_clean = $row['berat_timbangan1'] ?? $row['berat_bruto'] ?? 0;
    $berat_clean = floatval($berat_clean);

    echo "<option value='" . $row['no_tiket'] . "'";
    echo " data-bruto='" . $berat_clean . "'";
    echo " data-polisi='" . htmlspecialchars($row['no_polisi']) . "'";
    echo " data-supir='" . htmlspecialchars($row['nama_supir']) . "'";
    echo " data-kendaraan='" . htmlspecialchars($row['no_polisi']) . "'";
    echo " data-supplier='" . htmlspecialchars($row['nama_supplier'] ?: 'Unknown Supplier') . "'";
    echo " data-material='" . htmlspecialchars($row['jenis_material']) . "'";
    echo " data-harga='" . htmlspecialchars($row['harga_per_kg']) . "'>";
    echo $row['no_tiket'] . " - " . $row['no_polisi'] . " - " . ($row['nama_supplier'] ?? 'Unknown') . " - " . number_format($berat_clean, 0, ',', '.') . " Kg";
    echo "</option>";

    // Debug output
    echo "<br><small>DEBUG: Tiket " . $row['no_tiket'] . " -> data-bruto=" . $berat_clean . " | data-supplier=" . htmlspecialchars($row['nama_supplier'] ?: 'Unknown Supplier') . " | data-harga=" . htmlspecialchars($row['harga_per_kg']) . "</small>";

endwhile;
echo "</select>";

?>

<script>
function debugSelect(select) {
    console.log('=== DEBUG SELECT ===');
    console.log('Selected value:', select.value);
    console.log('Selected index:', select.selectedIndex);

    if (select.selectedIndex > 0) {
        const option = select.options[select.selectedIndex];
        console.log('Option element:', option);
        console.log('Option text:', option.text);

        console.log('Data attributes:');
        console.log('  data-bruto:', option.getAttribute('data-bruto'));
        console.log('  data-supir:', option.getAttribute('data-supir'));
        console.log('  data-polisi:', option.getAttribute('data-polisi'));
        console.log('  data-supplier:', option.getAttribute('data-supplier'));
        console.log('  data-material:', option.getAttribute('data-material'));
        console.log('  data-harga:', option.getAttribute('data-harga'));

        // Test filling form
        document.getElementById('debug_nama_supir').value = option.getAttribute('data-supir') || '';
        document.getElementById('debug_no_polisi').value = option.getAttribute('data-polisi') || '';
        document.getElementById('debug_supplier').value = option.getAttribute('data-supplier') || '';
        document.getElementById('debug_material').value = option.getAttribute('data-material') || '';
        document.getElementById('debug_harga').value = option.getAttribute('data-harga') || '';
        document.getElementById('debug_bruto').value = option.getAttribute('data-bruto') || '';
    }
}
</script>

<h2>Test Form Fields:</h2>
<table>
<tr><td>Supir:</td><td><input type="text" id="debug_nama_supir" readonly></td></tr>
<tr><td>Polisi:</td><td><input type="text" id="debug_no_polisi" readonly></td></tr>
<tr><td>Supplier:</td><td><input type="text" id="debug_supplier" readonly></td></tr>
<tr><td>Material:</td><td><input type="text" id="debug_material" readonly></td></tr>
<tr><td>Harga:</td><td><input type="text" id="debug_harga" readonly></td></tr>
<tr><td>Bruto:</td><td><input type="text" id="debug_bruto" readonly></td></tr>
</table>

<p><strong>Cara test:</strong> Pilih tiket dari dropdown di atas, lihat console browser (F12) untuk debug.</p>