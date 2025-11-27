<?php
// modules/timbangan/print_ticket.php
require_once '../../config/database.php';

// Validate and sanitize input - support both id and no_tiket
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$no_tiket = trim($_GET['no_tiket'] ?? '');

// Use no_tiket if provided, otherwise use id
if (!empty($no_tiket)) {
    $query = "SELECT tt.*, s.nama_supplier, s.total_hutang as sisa_hutang_supplier, u.nama_lengkap as operator_nama
              FROM transaksi_timbangan tt
              LEFT JOIN supplier s ON tt.id_supplier = s.id
              LEFT JOIN users u ON tt.operator_id = u.id
              WHERE tt.no_tiket = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $no_tiket);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif ($id > 0) {
    $query = "SELECT tt.*, s.nama_supplier, s.total_hutang as sisa_hutang_supplier, u.nama_lengkap as operator_nama
              FROM transaksi_timbangan tt
              LEFT JOIN supplier s ON tt.id_supplier = s.id
              LEFT JOIN users u ON tt.operator_id = u.id
              WHERE tt.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    http_response_code(400);
    die('Parameter tidak valid! Gunakan id atau no_tiket.');
}

if (!$result || mysqli_num_rows($result) == 0) {
    http_response_code(404);
    $identifier = !empty($no_tiket) ? "Tiket No: $no_tiket" : "ID: $id";
    die("Data transaksi tidak ditemukan untuk $identifier!");
}

$data = mysqli_fetch_assoc($result);

// Get company settings
$query_settings = "SELECT * FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone')";
$settings_result = mysqli_query($conn, $query_settings);
$settings = [];
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Gunakan data langsung dari database untuk konsistensi
$bruto = $data['berat_bruto'] ?? $data['berat_timbangan1'] ?? 0;
$tara = $data['berat_tara'] ?? $data['berat_timbangan2'] ?? 0;
$berat_timbangan1 = $data['berat_timbangan1'] ?? 0;
$berat_timbangan2 = $data['berat_timbangan2'] ?? 0;
$netto_awal = $bruto - $tara;
$persen_potongan = $data['persen_potongan'] ?? 0;
$harga_per_kg = $data['harga_per_kg'] ?? 0;
$kg_potongan = $data['kg_potongan'] ?? 0;
$berat_netto = $data['berat_netto'] ?? 0;
$total_harga = $data['total_harga'] ?? 0;
$potong_hutang = $data['potong_hutang'] ?? 0;
$sisa_hutang = $data['sisa_hutang_supplier'] ?? 0;
$total_akhir2 = max(0, $total_harga - $potong_hutang);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket Timbangan - <?php echo $data['no_tiket']; ?></title>
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 20mm;
            }
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
            color: #000;
        }

        .ticket-wrapper {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .company-address {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .company-phone {
            font-size: 11px;
            margin-bottom: 15px;
        }

        .slip-title {
            font-size: 14px;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 1px;
        }

        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #000;
        }

        .info-table td {
            padding: 4px 8px;
            font-size: 11px;
            border: 1px solid #000;
            vertical-align: middle;
        }

        .info-table .label {
            font-weight: bold;
            width: 22%;
            text-align: right;
            padding-right: 5px;
        }

        .info-table .colon {
            width: 3%;
            text-align: center;
            padding: 0;
        }

        .info-table .value {
            width: 25%;
        }

        /* Weight Table */
        .weight-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .weight-table td {
            padding: 5px 8px;
            font-size: 11px;
            border: 1px solid #000;
            vertical-align: middle;
        }

        .weight-table .label-cell {
            font-weight: bold;
            background-color: #e8e8e8;
            width: 14%;
        }

        .weight-table .value-cell {
            width: 11%;
        }

        /* Total Akhir Row */
        .total-row {
            width: 100%;
            border-collapse: collapse;
        }

        .total-row td {
            padding: 5px 8px;
            font-size: 11px;
            border: 1px solid #000;
            vertical-align: middle;
        }

        .total-row .label-cell {
            font-weight: bold;
        }

        /* Signature Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 0 50px;
        }

        .signature-box {
            text-align: center;
            width: 120px;
        }

        .signature-box .title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 60px;
        }

        .signature-box .line {
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .signature-box .name {
            font-size: 11px;
        }

        /* Print Button */
        .print-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .print-button:hover {
            background: #34495e;
        }

        @media print {
            .ticket-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            body {
                background: white;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">🖨️ CETAK STRUK</button>

    <div class="ticket-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="company-name">RAM JAWA SUMATRA</div>
            <div class="company-address">KAMPUNG TENGAH MAMAHAN JAYA PKL. GONDAL</div>
            <div class="company-phone">HP. 0822 8518 1010 / 0812 2706 1544 SLIP</div>
            <div class="slip-title">TIMBANGAN BARANG</div>
        </div>

        <!-- Info Table - 4 Rows -->
        <table class="info-table">
            <tr>
                <td class="label">NO. TIKET</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['no_tiket']; ?></td>
                <td class="label">KETERANGAN</td>
                <td class="colon">:</td>
                <td class="value"><?php echo !empty($data['keterangan']) ? $data['keterangan'] : ''; ?></td>
            </tr>
            <tr>
                <td class="label">SUPIR</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['nama_supir']; ?></td>
                <td class="label">WAKTU MASUK</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['waktu_timbangan1'] ? date('d/m/Y H:i', strtotime($data['waktu_timbangan1'])) : ''; ?></td>
            </tr>
            <tr>
                <td class="label">MATERIAL</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['jenis_material'] ?? ''; ?></td>
                <td class="label">NO. KENDARAAN</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['no_polisi']; ?></td>
            </tr>
            <tr>
                <td class="label">SUPPLIER</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['nama_supplier'] ?? ''; ?></td>
                <td class="label">WAKTU MASUK</td>
                <td class="colon">:</td>
                <td class="value"><?php echo $data['waktu_timbangan2'] ? date('d/m/Y H:i', strtotime($data['waktu_timbangan2'])) : ''; ?></td>
            </tr>
        </table>

        <!-- Weight Table - Row 1 -->
        <table class="weight-table">
            <tr>
                <td class="label-cell">BRUTO :</td>
                <td class="value-cell"><?php echo number_format($berat_timbangan1, 0, ',', '.'); ?></td>
                <td class="label-cell">TARA :</td>
                <td class="value-cell"><?php echo number_format($berat_timbangan2, 0, ',', '.'); ?></td>
                <td class="label-cell">NETTO 1 :</td>
                <td class="value-cell"><?php echo number_format($netto_awal, 0, ',', '.'); ?></td>
                <td class="label-cell">POTONGAN % :</td>
                <td class="value-cell"><?php echo $persen_potongan > 0 ? number_format($persen_potongan, 1) . '%' : ''; ?></td>
            </tr>
            <tr>
                <td class="label-cell">POTONGAN KG :</td>
                <td class="value-cell"><?php echo $kg_potongan > 0 ? number_format($kg_potongan, 1, ',', '.') : ''; ?></td>
                <td class="label-cell">HARGA :</td>
                <td class="value-cell"><?php echo $harga_per_kg > 0 ? number_format($harga_per_kg, 0, ',', '.') : ''; ?></td>
                <td class="label-cell">NETTO 2 :</td>
                <td class="value-cell"><?php echo number_format($berat_netto, 0, ',', '.'); ?></td>
                <td class="label-cell">TOTAL 1 :</td>
                <td class="value-cell"><?php echo $total_harga > 0 ? number_format($total_harga, 0, ',', '.') : ''; ?></td>
            </tr>
        </table>

        <!-- Total Akhir Row -->
        <table class="total-row">
            <tr>
                <td class="label-cell" style="width: 18%; font-size: 10px;">POTONGAN HUTANG :</td>
                <td class="value-cell" style="width: 15%;"><?php echo $potong_hutang > 0 ? number_format($potong_hutang, 0, ',', '.') : ''; ?></td>
                <td class="label-cell" style="width: 15%; font-size: 10px;">SISA HUTANG :</td>
                <td class="value-cell" style="width: 17%;"><?php echo $sisa_hutang > 0 ? number_format($sisa_hutang, 0, ',', '.') : ''; ?></td>
                <td class="label-cell" style="width: 15%; font-weight: bold;">TOTAL AKHIR: </td>
                <td class="value-cell" style="width: 20%; font-weight: bold;"><?php echo number_format($total_akhir2, 0, ',', '.'); ?></td>
            </tr>
        </table>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="title">SUPIR</div>
                <div class="line">
                    <div class="name">( <?php echo $data['nama_supir']; ?> )</div>
                </div>
            </div>
            <div class="signature-box">
                <div class="title">OPERATOR</div>
                <div class="line">
                    <div class="name">( <?php echo $data['operator_nama'] ?? ($_SESSION['nama_lengkap'] ?? 'Operator'); ?> )</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };

        window.addEventListener('afterprint', function() {
            setTimeout(() => {
                window.close();
            }, 1000);
        });
    </script>
</body>
</html>