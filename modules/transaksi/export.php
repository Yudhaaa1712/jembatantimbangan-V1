<?php
// modules/transaksi/export.php
$database_path = dirname(__DIR__, 2) . '/config/database.php';
if (file_exists($database_path)) {
    require_once $database_path;
} else {
    // Try alternative path
    require_once '../../config/database.php';
}

// Check role if function exists
if (function_exists('check_role')) {
    check_role(['admin']);
}

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

// Get transactions - dengan perhitungan JavaScript yang sama
$query = "SELECT tt.*,
                 tt.no_polisi as no_polisi_display,
                 s.nama_supplier,
                 u.nama_lengkap as operator_nama,
                 -- Hitung dengan formula JavaScript yang sama seperti struk
                 (tt.berat_timbangan1 - tt.berat_timbangan2) as netto1_calc, -- Netto 1 (sebelum potongan)
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         (tt.berat_timbangan1 - tt.berat_timbangan2) - ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))
                     WHEN tt.berat_netto > 0 THEN tt.berat_netto
                     ELSE (tt.berat_timbangan1 - tt.berat_timbangan2)
                 END as netto2_calc, -- Netto 2 (setelah potongan)
                 CASE
                     WHEN tt.berat_timbangan1 > 0 AND tt.berat_timbangan2 > 0 THEN tt.berat_timbangan1 - tt.berat_timbangan2
                     WHEN tt.berat_netto > 0 THEN tt.berat_netto
                     ELSE 0
                 END as berat_netto_calc,
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         ((tt.berat_timbangan1 - tt.berat_timbangan2) - ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))) * tt.harga_per_kg
                     WHEN tt.harga_per_kg > 0 AND (tt.berat_timbangan1 > 0 AND tt.berat_timbangan2 > 0) THEN (tt.berat_timbangan1 - tt.berat_timbangan2) * tt.harga_per_kg
                     WHEN tt.total_harga > 0 THEN tt.total_harga
                     ELSE 0
                 END as total_harga_calc,
                 -- Hitung potongan dalam kg dengan formula JavaScript yang sama
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))
                     ELSE 0
                 END as potongan_kg_calc
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
    header('Content-Disposition: attachment; filename="Laporan_Transaksi_Jembatan_Timbangan_' . date('Y-m-d_H-i') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<table border="1">';

    // Header Company Info
    echo '<tr><th colspan="20" style="text-align: center; font-size: 18px; font-weight: bold; background-color: #2c3e50; color: white; padding: 10px;">LAPORAN DATA TRANSAKSI JEMBATAN TIMBANGAN KELAPA SAWIT</th></tr>';
    echo '<tr><th colspan="20" style="text-align: center; font-size: 14px; background-color: #34495e; color: white;">Periode: ' . $period_name . '</th></tr>';
    echo '<tr><th colspan="20" style="text-align: center; font-size: 12px; background-color: #34495e; color: white;">Tanggal Cetak: ' . date('d/m/Y H:i:s') . '</th></tr>';
    echo '<tr><td colspan="20" style="background-color: #ecf0f1; height: 10px;"></td></tr>';

    // Statistics Box - Better Layout
    echo '<tr>';
    echo '<td colspan="4" style="background-color: #3498db; color: white; font-weight: bold; text-align: center; padding: 8px;">TOTAL TRANSAKSI</td>';
    echo '<td colspan="4" style="background-color: #e74c3c; color: white; font-weight: bold; text-align: center; padding: 8px;">TOTAL BRUTO (Kg)</td>';
    echo '<td colspan="4" style="background-color: #27ae60; color: white; font-weight: bold; text-align: center; padding: 8px;">TOTAL NETTO (Kg)</td>';
    echo '<td colspan="4" style="background-color: #f39c12; color: white; font-weight: bold; text-align: center; padding: 8px;">TOTAL HARGA (Rp)</td>';
    echo '<td colspan="4" style="background-color: #9b59b6; color: white; font-weight: bold; text-align: center; padding: 8px;">RATA-RATA BRUTO (Kg)</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="4" style="text-align: center; font-size: 16px; font-weight: bold; background-color: #ecf0f1;">' . number_format($stats['total_transaksi']) . '</td>';
    echo '<td colspan="4" style="text-align: center; font-size: 16px; font-weight: bold; background-color: #ecf0f1;">' . number_format($stats['total_bruto'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td colspan="4" style="text-align: center; font-size: 16px; font-weight: bold; background-color: #ecf0f1;">' . number_format($stats['total_netto'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td colspan="4" style="text-align: center; font-size: 16px; font-weight: bold; background-color: #ecf0f1;">' . number_format($stats['total_harga'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td colspan="4" style="text-align: center; font-size: 16px; font-weight: bold; background-color: #ecf0f1;">' . number_format($stats['rata_bruto'] ?? 0, 0, ',', '.') . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="20" style="background-color: #bdc3c7; height: 5px;"></td></tr>';

    // Table Headers - Better Styling
    echo '<tr style="background-color: #2c3e50; color: white; font-weight: bold; text-align: center; font-size: 12px;">';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">No</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Tiket No</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Tanggal</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Waktu<br>Masuk</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Waktu<br>Keluar</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">No. Polisi</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Supplier</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Pengemudi</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Material</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Bruto<br>(Kg)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Tara<br>(Kg)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Netto 1<br>(Kg)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Potongan<br>(%)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Potongan<br>(Kg)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Netto 2<br>(Kg)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Harga/Kg<br>(Rp)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Total Harga<br>(Rp)</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Operator</th>';
    echo '<th style="padding: 8px; border: 1px solid #34495e;">Keterangan</th>';
    echo '</tr>';

    // Data
    $no = 1;
    $total_bruto = 0;
    $total_tara = 0;
    $total_netto1 = 0;
    $total_potongan_kg = 0;
    $total_netto2 = 0;
    $total_harga = 0;

    // Reset result pointer
    mysqli_data_seek($result, 0);

    while($row = mysqli_fetch_assoc($result)) {
        // Calculate values with proper logic
        $netto1 = $row['berat_timbangan1'] - $row['berat_timbangan2'];
        $potongan_kg = ($netto1 > 0 && $row['persen_potongan'] > 0) ? ($row['persen_potongan'] / 100) * $netto1 : 0;
        $netto2 = ($netto1 > 0 && $row['persen_potongan'] > 0) ? $netto1 - $potongan_kg : $netto1;
        $total_harga_row = ($row['harga_per_kg'] > 0) ? $netto2 * $row['harga_per_kg'] : $row['total_harga'];

        echo '<tr style="font-size: 11px;">';
        echo '<td align="center" style="padding: 5px; border: 1px solid #bdc3c7;">' . $no++ . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold;">' . $row['no_tiket'] . '</td>';
        echo '<td align="center" style="padding: 5px; border: 1px solid #bdc3c7;">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td align="center" style="padding: 5px; border: 1px solid #bdc3c7;">' . ($row['waktu_timbangan1'] ? date('H:i:s', strtotime($row['waktu_timbangan1'])) : '-') . '</td>';
        echo '<td align="center" style="padding: 5px; border: 1px solid #bdc3c7;">' . ($row['waktu_timbangan2'] ? date('H:i:s', strtotime($row['waktu_timbangan2'])) : '-') . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold;">' . ($row['no_polisi_display'] ?? $row['no_polisi'] ?? '-') . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7;">' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7;">' . ($row['nama_supir'] ?? '-') . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7; text-align: center;">' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; background-color: #e8f5e8;">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; background-color: #ffe8e8;">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold; background-color: #e6f7ff;">' . number_format($netto1, 0, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7;">' . number_format($row['persen_potongan'], 1, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold; color: #e67e22;">' . number_format($potongan_kg, 2, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold; background-color: #d5f4e6;">' . number_format($netto2, 2, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7;">' . number_format($row['harga_per_kg'], 0, ',', '.') . '</td>';
        echo '<td align="right" style="padding: 5px; border: 1px solid #bdc3c7; font-weight: bold; background-color: #fff3cd;">Rp ' . number_format($total_harga_row, 0, ',', '.') . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7; font-size: 10px;">' . (!empty($row['operator_nama']) ? substr($row['operator_nama'], 0, 15) : (!empty($row['operator_id']) ? 'ID: ' . $row['operator_id'] : '-')) . '</td>';
        echo '<td style="padding: 5px; border: 1px solid #bdc3c7; font-size: 10px;">' . ($row['status'] ?? '-') . '</td>';
        echo '</tr>';

        // Accumulate totals with proper calculations
        $total_bruto += $row['berat_timbangan1'];
        $total_tara += $row['berat_timbangan2'];
        $total_netto1 += $netto1;
        $total_potongan_kg += $potongan_kg;
        $total_netto2 += $netto2;
        $total_harga += $total_harga_row;
    }

    // Grand Total Row - Better Styling (Hanya Netto 1, Netto 2, dan Total Harga yang dijumlahkan)
    echo '<tr style="background-color: #2c3e50; color: white; font-weight: bold; font-size: 12px;">';
    echo '<td colspan="11" align="center" style="padding: 8px; border: 1px solid #34495e;">GRAND TOTAL</td>';
    echo '<td align="right" style="padding: 8px; border: 1px solid #34495e; font-size: 14px; background-color: #e6f7ff; color: #2c3e50;">' . number_format($total_netto1, 0, ',', '.') . '</td>';
    echo '<td align="center" style="padding: 8px; border: 1px solid #34495e;">-</td>';
    echo '<td align="center" style="padding: 8px; border: 1px solid #34495e;">-</td>';
    echo '<td align="right" style="padding: 8px; border: 1px solid #34495e; font-size: 14px; background-color: #d5f4e6; color: #2c3e50;">' . number_format($total_netto2, 2, ',', '.') . '</td>';
    echo '<td align="center" style="padding: 8px; border: 1px solid #34495e;">-</td>';
    echo '<td align="right" style="padding: 8px; border: 1px solid #34495e; font-size: 14px; background-color: #fff3cd; color: #2c3e50;">Rp ' . number_format($total_harga, 0, ',', '.') . '</td>';
    echo '<td colspan="3" align="center" style="padding: 8px; border: 1px solid #34495e;">-</td>';
    echo '</tr>';

    // Footer
    echo '<tr><td colspan="20" style="background-color: #bdc3c7; height: 10px;"></td></tr>';
    echo '<tr><td colspan="20" style="text-align: center; font-size: 10px; color: #7f8c8d; padding: 5px;">Generated by Jembatan Timbangan System - ' . date('Y') . '</td></tr>';

    echo '</table>';
    exit();
}

function exportPDF($result, $stats, $period_name) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="Laporan_Transaksi_Jembatan_Timbangan_' . date('Y-m-d_H-i') . '.pdf"');

    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi Jembatan Timbangan</title>
    <style>
        @page {
            margin: 15mm;
            size: A4 landscape;
        }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .subtitle {
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .date {
            font-size: 11px;
            opacity: 0.9;
        }
        .stats-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .stats-box {
            flex: 1;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stats-box.blue { border-color: #3498db; }
        .stats-box.red { border-color: #e74c3c; }
        .stats-box.green { border-color: #27ae60; }
        .stats-box.orange { border-color: #f39c12; }
        .stats-box.purple { border-color: #9b59b6; }

        .stats-label {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .stats-box.blue .stats-label { color: #3498db; }
        .stats-box.red .stats-label { color: #e74c3c; }
        .stats-box.green .stats-label { color: #27ae60; }
        .stats-box.orange .stats-label { color: #f39c12; }
        .stats-box.purple .stats-label { color: #9b59b6; }

        .stats-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 12px 6px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #4a5568;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 8px 6px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        tr:hover {
            background-color: #edf2f7;
        }

        .bruto { background-color: #e6fffa !important; }
        .tara { background-color: #fef5e7 !important; }
        .netto1 { background-color: #e6f3ff !important; font-weight: bold; }
        .potongan { color: #e67e22 !important; font-weight: bold; }
        .netto2 { background-color: #e8f8f5 !important; font-weight: bold; }
        .total-harga { background-color: #fff3cd !important; font-weight: bold; }

        .grand-total {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        .grand-total td {
            border-color: #2c3e50;
            padding: 12px 6px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #718096;
            border-top: 2px solid #e2e8f0;
            padding-top: 15px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            opacity: 0.05;
            font-weight: bold;
            pointer-events: none;
            z-index: -1;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 5mm; }
            .header { background: #667eea; }
            .stats-box {
                border: 2px solid #2c3e50;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">OFFICIAL</div>';

    echo '<div class="header">
        <div class="title">Laporan Data Transaksi Jembatan Timbangan Kelapa Sawit</div>
        <div class="subtitle">Periode: ' . $period_name . '</div>
        <div class="date">Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>
    </div>';

    echo '<div class="stats-container">
        <div class="stats-box blue">
            <div class="stats-label">Total Transaksi</div>
            <div class="stats-value">' . number_format($stats['total_transaksi']) . '</div>
        </div>
        <div class="stats-box red">
            <div class="stats-label">Total Bruto (Kg)</div>
            <div class="stats-value">' . number_format($stats['total_bruto'] ?? 0, 0, ',', '.') . '</div>
        </div>
        <div class="stats-box green">
            <div class="stats-label">Total Netto (Kg)</div>
            <div class="stats-value">' . number_format($stats['total_netto'] ?? 0, 0, ',', '.') . '</div>
        </div>
        <div class="stats-box orange">
            <div class="stats-label">Total Harga (Rp)</div>
            <div class="stats-value">' . number_format($stats['total_harga'] ?? 0, 0, ',', '.') . '</div>
        </div>
        <div class="stats-box purple">
            <div class="stats-label">Rata-rata Bruto (Kg)</div>
            <div class="stats-value">' . number_format($stats['rata_bruto'] ?? 0, 0, ',', '.') . '</div>
        </div>
    </div>';

    echo '<table>
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="7%">Tiket No</th>
                <th width="6%">Tanggal</th>
                <th width="6%">Waktu<br>Masuk</th>
                <th width="6%">Waktu<br>Keluar</th>
                <th width="6%">No. Polisi</th>
                <th width="8%">Supplier</th>
                <th width="7%">Pengemudi</th>
                <th width="5%">Material</th>
                <th width="5%" class="text-right">Bruto<br>(Kg)</th>
                <th width="5%" class="text-right">Tara<br>(Kg)</th>
                <th width="5%" class="text-right">Netto 1<br>(Kg)</th>
                <th width="4%" class="text-right">Potongan<br>(%)</th>
                <th width="5%" class="text-right">Potongan<br>(Kg)</th>
                <th width="5%" class="text-right">Netto 2<br>(Kg)</th>
                <th width="6%" class="text-right">Harga/Kg<br>(Rp)</th>
                <th width="7%" class="text-right">Total Harga<br>(Rp)</th>
                <th width="6%">Operator</th>
                <th width="5%">Status</th>
            </tr>
        </thead>
        <tbody>';

    $no = 1;
    $total_bruto = 0;
    $total_tara = 0;
    $total_netto1 = 0;
    $total_potongan_kg = 0;
    $total_netto2 = 0;
    $total_harga = 0;

    // Reset result pointer
    mysqli_data_seek($result, 0);

    while($row = mysqli_fetch_assoc($result)) {
        // Calculate values with proper logic
        $netto1 = $row['berat_timbangan1'] - $row['berat_timbangan2'];
        $potongan_kg = ($netto1 > 0 && $row['persen_potongan'] > 0) ? ($row['persen_potongan'] / 100) * $netto1 : 0;
        $netto2 = ($netto1 > 0 && $row['persen_potongan'] > 0) ? $netto1 - $potongan_kg : $netto1;
        $total_harga_row = ($row['harga_per_kg'] > 0) ? $netto2 * $row['harga_per_kg'] : $row['total_harga'];

        echo '<tr>';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td style="font-weight: bold;">' . $row['no_tiket'] . '</td>';
        echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td class="text-center">' . ($row['waktu_timbangan1'] ? date('H:i:s', strtotime($row['waktu_timbangan1'])) : '-') . '</td>';
        echo '<td class="text-center">' . ($row['waktu_timbangan2'] ? date('H:i:s', strtotime($row['waktu_timbangan2'])) : '-') . '</td>';
        echo '<td style="font-weight: bold;">' . ($row['no_polisi_display'] ?? $row['no_polisi'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td>' . ($row['nama_supir'] ?? '-') . '</td>';
        echo '<td class="text-center">' . ucfirst($row['jenis_material']) . '</td>';
        echo '<td class="text-right bruto">' . number_format($row['berat_timbangan1'], 0, ',', '.') . '</td>';
        echo '<td class="text-right tara">' . number_format($row['berat_timbangan2'], 0, ',', '.') . '</td>';
        echo '<td class="text-right font-bold netto1">' . number_format($netto1, 0, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['persen_potongan'], 1, ',', '.') . '</td>';
        echo '<td class="text-right font-bold potongan">' . number_format($potongan_kg, 2, ',', '.') . '</td>';
        echo '<td class="text-right font-bold netto2">' . number_format($netto2, 2, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format($row['harga_per_kg'], 0, ',', '.') . '</td>';
        echo '<td class="text-right font-bold total-harga">Rp ' . number_format($total_harga_row, 0, ',', '.') . '</td>';
        echo '<td style="font-size: 9px;">' . (!empty($row['operator_nama']) ? substr($row['operator_nama'], 0, 12) : (!empty($row['operator_id']) ? 'ID: ' . $row['operator_id'] : '-')) . '</td>';
        echo '<td class="text-center" style="font-size: 9px; font-weight: bold; color: ' . (($row['status'] == 'selesai') ? '#27ae60' : '#e74c3c') . ';">' . ucfirst($row['status'] ?? '-') . '</td>';
        echo '</tr>';

        // Accumulate totals with proper calculations
        $total_bruto += $row['berat_timbangan1'];
        $total_tara += $row['berat_timbangan2'];
        $total_netto1 += $netto1;
        $total_potongan_kg += $potongan_kg;
        $total_netto2 += $netto2;
        $total_harga += $total_harga_row;
    }

    echo '<tr class="grand-total">
        <td colspan="11" class="text-center">GRAND TOTAL</td>
        <td class="text-right" style="font-size: 13px; background-color: #e6f7ff; color: #2c3e50;">' . number_format($total_netto1, 0, ',', '.') . '</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-right" style="font-size: 13px; background-color: #d5f4e6; color: #2c3e50;">' . number_format($total_netto2, 2, ',', '.') . '</td>
        <td class="text-center">-</td>
        <td class="text-right" style="font-size: 13px; background-color: #fff3cd; color: #2c3e50;">Rp ' . number_format($total_harga, 0, ',', '.') . '</td>
        <td colspan="2" class="text-center">-</td>
    </tr>';

    echo '</tbody></table>';

    echo '<div class="footer">
        <div><strong>🔒 DOKUMEN RESMI - CONFIDENTIAL</strong></div>
        <div style="margin-top: 8px;">Laporan Resmi Jembatan Timbangan Kelapa Sawit</div>
        <div style="margin-top: 3px;">Generated by Timbangan System v' . date('Y.m.d') . ' • Dicetak: ' . date('d/m/Y H:i:s') . '</div>
        <div style="margin-top: 5px; font-style: italic;">This document contains confidential and proprietary information.</div>
    </div>';

    echo '</body></html>';
    exit();
}
?>