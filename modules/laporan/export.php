<?php
// modules/laporan/export.php
require_once '../../config/database.php';
check_role(['admin']);

$format = $_GET['format'] ?? 'excel';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$id_supplier = $_GET['id_supplier'] ?? '';
$jenis_material = $_GET['jenis_material'] ?? '';

// Build query
$where = "WHERE tanggal BETWEEN '$start_date' AND '$end_date' AND status = 'selesai'";

if (!empty($id_supplier)) {
    $where .= " AND id_supplier = '$id_supplier'";
}

if (!empty($jenis_material)) {
    $where .= " AND jenis_material = '$jenis_material'";
}

// Get data
$query = "SELECT tt.*, k.no_polisi, s.nama_supplier, u.nama_lengkap as nama_operator
          FROM transaksi_timbangan tt
          LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          LEFT JOIN users u ON tt.operator_id = u.id
          $where ORDER BY tt.tanggal ASC, tt.waktu_masuk ASC";
$result = mysqli_query($conn, $query);

// Get statistics
$query_stats = "SELECT
    COUNT(*) as total_transaksi,
    SUM(berat_timbangan1) as total_bruto,
    SUM(berat_timbangan2) as total_tara,
    SUM(berat_netto) as total_netto,
    SUM(total_harga) as total_nilai
    FROM transaksi_timbangan
    $where";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $query_stats));

if ($format == 'excel') {
    exportExcel($result, $stats, $start_date, $end_date);
} else {
    exportPDF($result, $stats, $start_date, $end_date);
}

function exportExcel($result, $stats, $start_date, $end_date) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Laporan_Timbangan_' . date('YmdHis') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 5px; }';
    echo 'th { background-color: #ddd; font-weight: bold; text-align: center; }';
    echo '.text-right { text-align: right; }';
    echo '.text-center { text-align: center; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2 style="text-align: center;">LAPORAN TIMBANGAN</h2>';
    echo '<p style="text-align: center;">Periode: ' . date('d/m/Y', strtotime($start_date)) . ' s/d ' . date('d/m/Y', strtotime($end_date)) . '</p>';
    
    // Summary
    echo '<h3>Ringkasan</h3>';
    echo '<table style="width: 50%;">';
    echo '<tr><td>Total Transaksi</td><td class="text-right"><strong>' . number_format($stats['total_transaksi']) . '</strong></td></tr>';
    echo '<tr><td>Total Bruto</td><td class="text-right"><strong>' . number_format($stats['total_bruto'], 0, ',', '.') . ' Kg</strong></td></tr>';
    echo '<tr><td>Total Tara</td><td class="text-right"><strong>' . number_format($stats['total_tara'], 0, ',', '.') . ' Kg</strong></td></tr>';
    echo '<tr><td>Total Netto</td><td class="text-right"><strong>' . number_format($stats['total_netto'], 0, ',', '.') . ' Kg</strong></td></tr>';
    echo '<tr><td>Total Nilai</td><td class="text-right"><strong>Rp ' . number_format($stats['total_nilai'], 0, ',', '.') . '</strong></td></tr>';
    echo '</table>';
    
    echo '<br><h3>Detail Transaksi</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>No. Tiket</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Waktu Masuk</th>';
    echo '<th>Waktu Keluar</th>';
    echo '<th>No. Polisi</th>';
    echo '<th>Pengemudi</th>';
    echo '<th>Supplier</th>';
    echo '<th>Material</th>';
    echo '<th>Bruto (Kg)</th>';
    echo '<th>Tara (Kg)</th>';
    echo '<th>Netto (Kg)</th>';
    echo '<th>Harga/Kg</th>';
    echo '<th>Total Harga</th>';
    echo '<th>Operator</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td>' . $row['no_tiket'] . '</td>';
        echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td class="text-center">' . date('H:i', strtotime($row['waktu_masuk'])) . '</td>';
        echo '<td class="text-center">' . ($row['waktu_timbangan2'] ? date('H:i', strtotime($row['waktu_timbangan2'])) : '-') . '</td>';
        echo '<td>' . ($row['no_polisi'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supir'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td class="text-center">' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_netto'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['harga_per_kg'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['total_harga'], 0, ',', '.') . '</td>';
        echo '<td>' . ($row['nama_operator'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '<tr style="background-color: #f0f0f0;">';
    echo '<td colspan="9" class="text-right"><strong>TOTAL</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_bruto'], 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_tara'], 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_netto'], 0, ',', '.') . '</strong></td>';
    echo '<td></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_nilai'], 0, ',', '.') . '</strong></td>';
    echo '<td></td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<br><p><em>Dicetak pada: ' . date('d/m/Y H:i:s') . '</em></p>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function exportPDF($result, $stats, $start_date, $end_date) {
    // Simple PDF export using HTML
    // For production, consider using TCPDF or DomPDF library
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Laporan Timbangan</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; font-size: 12px; }';
    echo 'table { border-collapse: collapse; width: 100%; margin: 20px 0; }';
    echo 'th, td { border: 1px solid #000; padding: 5px; }';
    echo 'th { background-color: #ddd; font-weight: bold; text-align: center; }';
    echo '.text-right { text-align: right; }';
    echo '.text-center { text-align: center; }';
    echo '.header { text-align: center; margin-bottom: 20px; }';
    echo '@media print { button { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<button onclick="window.print()" style="padding: 10px 20px; margin: 10px; cursor: pointer;">PRINT / SAVE AS PDF</button>';
    
    echo '<div class="header">';
    echo '<h2>LAPORAN TIMBANGAN</h2>';
    echo '<p>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' s/d ' . date('d/m/Y', strtotime($end_date)) . '</p>';
    echo '</div>';
    
    // Summary
    echo '<h3>Ringkasan</h3>';
    echo '<table style="width: 50%;">';
    echo '<tr><td>Total Transaksi</td><td class="text-right"><strong>' . number_format($stats['total_transaksi']) . '</strong></td></tr>';
    echo '<tr><td>Total Bruto</td><td class="text-right"><strong>' . number_format($stats['total_bruto']/1000, 2, ',', '.') . ' Ton</strong></td></tr>';
    echo '<tr><td>Total Tara</td><td class="text-right"><strong>' . number_format($stats['total_tara']/1000, 2, ',', '.') . ' Ton</strong></td></tr>';
    echo '<tr><td>Total Netto</td><td class="text-right"><strong>' . number_format($stats['total_netto']/1000, 2, ',', '.') . ' Ton</strong></td></tr>';
    echo '<tr><td>Total Nilai</td><td class="text-right"><strong>Rp ' . number_format($stats['total_nilai'], 0, ',', '.') . '</strong></td></tr>';
    echo '</table>';
    
    echo '<h3>Detail Transaksi</h3>';
    echo '<table style="font-size: 10px;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th width="20">No</th>';
    echo '<th>No. Tiket</th>';
    echo '<th>Tanggal</th>';
    echo '<th>No. Polisi</th>';
    echo '<th>Supplier</th>';
    echo '<th>Material</th>';
    echo '<th>Bruto (Kg)</th>';
    echo '<th>Tara (Kg)</th>';
    echo '<th>Netto (Kg)</th>';
    echo '<th>Total Harga</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td>' . $row['no_tiket'] . '</td>';
        echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . ($row['no_polisi'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td class="text-center">' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_netto'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['total_harga'], 0, ',', '.') . '</td>';
        echo '</tr>';
    }
    
    echo '<tr style="background-color: #f0f0f0;">';
    echo '<td colspan="6" class="text-right"><strong>TOTAL</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_bruto'], 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_tara'], 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_netto'], 0, ',', '.') . '</strong></td>';
    echo '<td class="text-right"><strong>' . number_format($stats['total_nilai'], 0, ',', '.') . '</strong></td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<p><em>Dicetak pada: ' . date('d/m/Y H:i:s') . '</em></p>';
    
    echo '</body>';
    echo '</html>';
    exit;
}
?>