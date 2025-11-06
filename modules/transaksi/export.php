<?php
// modules/transaksi/export.php
require_once '../../config/database.php';
check_role(['admin']);

// Handle date filtering (same as index.php)
$date_filter = $_GET['date_filter'] ?? 'today';
$custom_date = $_GET['custom_date'] ?? date('Y-m-d');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

switch($date_filter) {
    case 'today':
        $date_condition = "tanggal = CURDATE()";
        $period_name = "Hari Ini (" . date('d/m/Y') . ")";
        break;
    case 'yesterday':
        $date_condition = "tanggal = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $period_name = "Kemarin (" . date('d/m/Y', strtotime('-1 day')) . ")";
        break;
    case 'week':
        $date_condition = "tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $period_name = "7 Hari Terakhir";
        break;
    case 'month':
        $date_condition = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
        $period_name = "Bulan Ini (" . date('F Y') . ")";
        break;
    case 'year':
        $date_condition = "YEAR(tanggal) = YEAR(CURDATE())";
        $period_name = "Tahun " . date('Y');
        break;
    case 'custom':
        $date_condition = "tanggal = '$custom_date'";
        $period_name = date('d/m/Y', strtotime($custom_date));
        break;
    case 'custom_month':
        $date_condition = "MONTH(tanggal) = '$month' AND YEAR(tanggal) = '$year'";
        $period_name = date('F Y', mktime(0,0,0,$month,1,$year));
        break;
    default:
        $date_condition = "tanggal = CURDATE()";
        $period_name = "Hari Ini";
}

// Get transactions - sama seperti index.php
$query = "SELECT tt.*,
                 tt.no_polisi as no_polisi_display,
                 s.nama_supplier,
                 u.nama_lengkap as operator_nama,
                 CASE
                     WHEN tt.berat_timbangan1 > 0 AND tt.berat_timbangan2 > 0 THEN tt.berat_timbangan1 - tt.berat_timbangan2
                     WHEN tt.berat_netto > 0 THEN tt.berat_netto
                     ELSE 0
                 END as berat_netto_calc,
                 CASE
                     WHEN tt.harga_per_kg > 0 AND (tt.berat_timbangan1 > 0 AND tt.berat_timbangan2 > 0) THEN (tt.berat_timbangan1 - tt.berat_timbangan2) * tt.harga_per_kg
                     WHEN tt.total_harga > 0 THEN tt.total_harga
                     ELSE 0
                 END as total_harga_calc
          FROM transaksi_timbangan tt
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          LEFT JOIN users u ON tt.operator_id = u.id
          WHERE $date_condition AND tt.status = 'selesai' AND tt.timbang2_locked = 1
          ORDER BY tt.created_at DESC";

$result = mysqli_query($conn, $query);

// Get statistics - sama seperti index.php
$query_stats = "SELECT
                    COUNT(*) as total_transaksi,
                    COALESCE(SUM(berat_timbangan1), 0) as total_bruto,
                    COALESCE(SUM(CASE
                        WHEN berat_timbangan1 > 0 AND berat_timbangan2 > 0 THEN berat_timbangan1 - berat_timbangan2
                        WHEN berat_netto > 0 THEN berat_netto
                        ELSE 0
                    END), 0) as total_netto,
                    COALESCE(SUM(CASE
                        WHEN harga_per_kg > 0 AND berat_timbangan1 > 0 AND berat_timbangan2 > 0 THEN (berat_timbangan1 - berat_timbangan2) * harga_per_kg
                        WHEN total_harga > 0 THEN total_harga
                        ELSE 0
                    END), 0) as total_harga,
                    COALESCE(AVG(berat_timbangan1), 0) as rata_bruto
                FROM transaksi_timbangan
                WHERE $date_condition AND status = 'selesai' AND timbang2_locked = 1";

$stats = mysqli_fetch_assoc(mysqli_query($conn, $query_stats));

$export_type = $_GET['export'] ?? '';

if ($export_type == 'excel') {
    exportExcel($result, $stats, $period_name);
} elseif ($export_type == 'pdf') {
    exportPDF($result, $stats, $period_name);
}

function exportExcel($result, $stats, $period_name) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="transaksi_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<table border="1">';

    // Header
    echo '<tr><th colspan="18" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN DATA TRANSAKSI JEMBATAN TIMBANGAN</th></tr>';
    echo '<tr><th colspan="18" style="text-align: center;">Periode: ' . $period_name . '</th></tr>';
    echo '<tr><th colspan="18" style="text-align: center;">Tanggal Cetak: ' . date('d/m/Y H:i') . '</th></tr>';
    echo '<tr><td colspan="18">&nbsp;</td></tr>';

    // Statistics
    echo '<tr><th colspan="9" style="background-color: #f0f0f0;">RINGKASAN TRANSAKSI</th></tr>';
    echo '<tr>';
    echo '<td><strong>Total Transaksi</strong></td><td colspan="2">' . number_format($stats['total_transaksi']) . '</td>';
    echo '<td><strong>Total Bruto (Kg)</strong></td><td colspan="2">' . number_format($stats['total_bruto'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td><strong>Total Netto (Kg)</strong></td><td colspan="2">' . number_format($stats['total_netto'] ?? 0, 0, ',', '.') . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><strong>Total Harga</strong></td><td colspan="2">Rp ' . number_format($stats['total_harga'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td><strong>Rata-rata Bruto (Kg)</strong></td><td colspan="5">' . number_format($stats['rata_bruto'] ?? 0, 0, ',', '.') . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="18">&nbsp;</td></tr>';

    // Table Headers - sesuai dengan tabel di index.php
    echo '<tr style="background-color: #f0f0f0; font-weight: bold; text-align: center;">';
    echo '<th>No</th>';
    echo '<th>Tiket No</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Waktu Masuk</th>';
    echo '<th>Waktu Keluar</th>';
    echo '<th>No. Polisi</th>';
    echo '<th>Supplier</th>';
    echo '<th>Pengemudi</th>';
    echo '<th>Material</th>';
    echo '<th>Bruto (Kg)</th>';
    echo '<th>Tara (Kg)</th>';
    echo '<th>Netto (Kg)</th>';
    echo '<th>Potongan (%)</th>';
    echo '<th>Potongan (Kg)</th>';
    echo '<th>Harga/Kg</th>';
    echo '<th>Total Harga</th>';
    echo '<th>Operator</th>';
    echo '</tr>';

    // Data
    $no = 1;
    $total_bruto = 0;
    $total_tara = 0;
    $total_netto = 0;
    $total_harga = 0;

    // Reset result pointer
    mysqli_data_seek($result, 0);

    while($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td align="center">' . $no++ . '</td>';
        echo '<td>' . $row['no_tiket'] . '</td>';
        echo '<td align="center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td align="center">' . ($row['waktu_timbangan1'] ? date('H:i:s', strtotime($row['waktu_timbangan1'])) : '-') . '</td>';
        echo '<td align="center">' . ($row['waktu_timbangan2'] ? date('H:i:s', strtotime($row['waktu_timbangan2'])) : '-') . '</td>';
        echo '<td>' . ($row['no_polisi_display'] ?? $row['no_polisi'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supir'] ?? '-') . '</td>';
        echo '<td>' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td align="right">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td align="right">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td align="right" style="font-weight: bold;">' . number_format($row['berat_netto_calc'] ?? $row['berat_netto'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td align="right">' . number_format($row['persen_potongan'], 1, ',', '.') . '</td>';
        echo '<td align="right">' . number_format($row['kg_potongan'], 0, ',', '.') . '</td>';
        echo '<td align="right">Rp ' . number_format($row['harga_per_kg'], 0, ',', '.') . '</td>';
        echo '<td align="right" style="font-weight: bold;">Rp ' . number_format($row['total_harga_calc'] ?? $row['total_harga'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td>' . ($row['operator_nama'] ?? '-') . '</td>';
        echo '</tr>';

        // Accumulate totals
        $total_bruto += $row['berat_timbangan1'];
        $total_tara += $row['berat_timbangan2'];
        $total_netto += $row['berat_netto'];
        $total_harga += $row['total_harga'];
    }

    // Total row
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<td colspan="9" align="right"><strong>GRAND TOTAL</strong></td>';
    echo '<td align="right">' . number_format($total_bruto, 0, ',', '.') . '</td>';
    echo '<td align="right">' . number_format($total_tara, 0, ',', '.') . '</td>';
    echo '<td align="right">' . number_format($total_netto, 0, ',', '.') . '</td>';
    echo '<td colspan="2">-</td>';
    echo '<td align="right">Rp ' . number_format($total_harga, 0, ',', '.') . '</td>';
    echo '<td colspan="2">-</td>';
    echo '</tr>';

    echo '</table>';
    exit();
}

function exportPDF($result, $stats, $period_name) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="transaksi_' . date('Y-m-d') . '.pdf"');

    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi Jembatan Timbangan</title>
    <style>
        @page {
            margin: 20px;
            size: landscape;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 15px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .period {
            font-size: 12px;
            margin-bottom: 3px;
            font-weight: 600;
        }
        .date {
            font-size: 10px;
            color: #666;
        }
        .stats {
            margin-bottom: 15px;
            background: #f8f8f8;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .stats-item {
            flex: 1;
            text-align: center;
            border-right: 1px solid #ddd;
        }
        .stats-item:last-child {
            border-right: none;
        }
        .stats-label {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .stats-value {
            font-size: 11px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }
        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 9px;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .grand-total {
            background-color: #e0e0e0;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 10px; }
        }
    </style>
</head>
<body>';

    echo '<div class="header">
        <div class="title">Laporan Data Transaksi Jembatan Timbangan</div>
        <div class="period">Periode: ' . $period_name . '</div>
        <div class="date">Tanggal Cetak: ' . date('d/m/Y H:i') . '</div>
    </div>';

    echo '<div class="stats">
        <div class="stats-row">
            <div class="stats-item">
                <div class="stats-label">Total Transaksi</div>
                <div class="stats-value">' . number_format($stats['total_transaksi']) . '</div>
            </div>
            <div class="stats-item">
                <div class="stats-label">Total Bruto (Kg)</div>
                <div class="stats-value">' . number_format($stats['total_bruto'] ?? 0, 0, ',', '.') . '</div>
            </div>
            <div class="stats-item">
                <div class="stats-label">Total Netto (Kg)</div>
                <div class="stats-value">' . number_format($stats['total_netto'] ?? 0, 0, ',', '.') . '</div>
            </div>
            <div class="stats-item">
                <div class="stats-label">Total Harga</div>
                <div class="stats-value">Rp ' . number_format($stats['total_harga'] ?? 0, 0, ',', '.') . '</div>
            </div>
            <div class="stats-item">
                <div class="stats-label">Rata-rata Bruto (Kg)</div>
                <div class="stats-value">' . number_format($stats['rata_bruto'] ?? 0, 0, ',', '.') . '</div>
            </div>
        </div>
    </div>';

    echo '<table>
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="8%">Tiket No</th>
                <th width="7%">Tanggal</th>
                <th width="7%">Waktu<br>Masuk</th>
                <th width="7%">Waktu<br>Keluar</th>
                <th width="7%">No. Polisi</th>
                <th width="10%">Supplier</th>
                <th width="8%">Pengemudi</th>
                <th width="6%">Material</th>
                <th width="6%" class="text-right">Bruto<br>(Kg)</th>
                <th width="6%" class="text-right">Tara<br>(Kg)</th>
                <th width="6%" class="text-right">Netto<br>(Kg)</th>
                <th width="5%" class="text-right">Potongan<br>(%)</th>
                <th width="6%" class="text-right">Potongan<br>(Kg)</th>
                <th width="7%" class="text-right">Harga/Kg</th>
                <th width="8%" class="text-right">Total Harga</th>
                <th width="7%">Operator</th>
            </tr>
        </thead>
        <tbody>';

    $no = 1;
    $total_bruto = 0;
    $total_tara = 0;
    $total_netto = 0;
    $total_harga = 0;

    // Reset result pointer
    mysqli_data_seek($result, 0);

    while($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td>' . $row['no_tiket'] . '</td>';
        echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td class="text-center">' . ($row['waktu_timbangan1'] ? date('H:i:s', strtotime($row['waktu_timbangan1'])) : '-') . '</td>';
        echo '<td class="text-center">' . ($row['waktu_timbangan2'] ? date('H:i:s', strtotime($row['waktu_timbangan2'])) : '-') . '</td>';
        echo '<td>' . ($row['no_polisi_display'] ?? $row['no_polisi'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supir'] ?? '-') . '</td>';
        echo '<td>' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td class="text-right font-bold">' . number_format($row['berat_netto_calc'] ?? $row['berat_netto'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['persen_potongan'], 1, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['kg_potongan'], 0, ',', '.') . '</td>';
        echo '<td class="text-right">Rp ' . number_format($row['harga_per_kg'], 0, ',', '.') . '</td>';
        echo '<td class="text-right font-bold">Rp ' . number_format($row['total_harga_calc'] ?? $row['total_harga'] ?? 0, 0, ',', '.') . '</td>';
        echo '<td>' . ($row['operator_nama'] ?? '-') . '</td>';
        echo '</tr>';

        // Accumulate totals
        $total_bruto += $row['berat_timbangan1'];
        $total_tara += $row['berat_timbangan2'];
        $total_netto += $row['berat_netto'];
        $total_harga += $row['total_harga'];
    }

    echo '<tr class="grand-total">
        <td colspan="9" class="text-right">Grand Total</td>
        <td class="text-right">' . number_format($total_bruto, 0, ',', '.') . '</td>
        <td class="text-right">' . number_format($total_tara, 0, ',', '.') . '</td>
        <td class="text-right">' . number_format($total_netto, 0, ',', '.') . '</td>
        <td colspan="2" class="text-center">-</td>
        <td class="text-right">Rp ' . number_format($total_harga, 0, ',', '.') . '</td>
        <td class="text-center">-</td>
    </tr>';

    echo '</tbody></table>';

    echo '<div class="footer">
        <div><strong>Dicetak tanggal: ' . date('d/m/Y H:i:s') . '</strong></div>
        <div style="margin-top: 5px;">Laporan Resmi Jembatan Timbangan - Dokumen Confidentional</div>
    </div>';

    echo '</body></html>';
    exit();
}
?>