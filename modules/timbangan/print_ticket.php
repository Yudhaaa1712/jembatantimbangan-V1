<?php
// modules/timbangan/print_ticket.php
require_once '../../config/database.php';

// Validate and sanitize input - support both id and no_tiket
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$no_tiket = trim($_GET['no_tiket'] ?? '');

// Use no_tiket if provided, otherwise use id
if (!empty($no_tiket)) {
    $query = "SELECT * FROM view_transaksi_lengkap WHERE no_tiket = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $no_tiket);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif ($id > 0) {
    $query = "SELECT * FROM view_transaksi_lengkap WHERE id = ?";
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
                size: 241mm auto;
                margin: 5mm 8mm;
                size: landscape;
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
            .break-after {
                page-break-after: always;
            }
            .no-break {
                page-break-inside: avoid;
            }
        }

        body {
            font-family: 'Courier New', 'Courier', monospace;
            font-size: 10px;
            line-height: 1.2;
            width: 241mm;
            margin: 0;
            padding: 5mm 8mm;
            background: white;
            box-sizing: border-box;
        }
        
        /* Continuous Form Styles */
        .ticket-container {
            width: 100%;
            max-width: 225mm;
            margin: 0 auto;
            padding: 10px 0;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .ticket-header h1 {
            margin: 3px 0;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .ticket-header h2 {
            margin: 2px 0;
            font-size: 12px;
            font-weight: bold;
        }

        .ticket-header p {
            margin: 1px 0;
            font-size: 9px;
        }

        .ticket-info {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .info-row {
            display: table-row;
        }

        .info-label, .info-value {
            display: table-cell;
            padding: 2px 5px;
            font-size: 10px;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            width: 25%;
        }

        .info-value {
            width: 25%;
            text-align: left;
        }

        .weight-section {
            border: 2px solid #000;
            padding: 8px;
            margin: 10px 0;
            text-align: center;
        }

        .weight-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .weight-table td {
            padding: 3px;
            border: 1px solid #000;
            text-align: center;
            font-weight: bold;
        }

        .weight-table .label {
            background: #f0f0f0;
            font-weight: normal;
            width: 30%;
        }

        .weight-value {
            font-size: 14px;
            font-weight: bold;
        }

        .weight-netto {
            font-size: 16px;
            font-weight: bold;
            background: #ffffcc;
        }

        .potongan-section {
            margin: 8px 0;
            border: 1px solid #ccc;
            padding: 5px;
        }

        .potongan-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .potongan-table td {
            padding: 2px;
            text-align: right;
        }

        .potongan-table .label {
            text-align: left;
            font-weight: bold;
        }

        .ticket-footer {
            border-top: 2px dashed #000;
            padding-top: 8px;
            margin-top: 10px;
            text-align: center;
            font-size: 9px;
        }

        .signature-section {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 20px 5px;
            margin-top: 15px;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #000;
            height: 20px;
            margin-top: 30px;
        }

        .signature-name {
            font-size: 9px;
            margin-top: 2px;
        }

        .barcode {
            text-align: center;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            letter-spacing: 1px;
            font-weight: bold;
        }

        .copy-label {
            text-align: center;
            font-weight: bold;
            margin: 5px 0;
            padding: 3px;
            border: 1px solid #000;
            background: #f9f9f9;
        }

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

        .double-copy {
            margin-bottom: 30px;
            border-bottom: 2px dashed #000;
            padding-bottom: 20px;
        }

        .copy-number {
            position: absolute;
            top: 5px;
            right: 8mm;
            font-size: 8px;
            font-weight: bold;
        }

        .carbon-copy-info {
            margin-top: 20px;
            padding: 10px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            text-align: center;
        }

        .carbon-copy-info p {
            margin: 3px 0;
            font-size: 9px;
            font-weight: 600;
            line-height: 1.3;
        }

        .carbon-copy-info p strong {
            font-size: 11px;
            color: #000;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">
        🖨️ CETAK STRUK
    </button>

    <!-- SINGLE STRUK WITH CARBON COPY INDICATION -->
    <div class="ticket-container no-break">
        <div class="ticket-header">
            <h1>PT. JEMBATAN TIMBANGAN SAWIT</h1>
            <h2>TIKET TIMBANGAN</h2>
            <p>Jl. Industri No. 123, Jakarta | Telp: 021-5551234</p>
        </div>

        <div class="ticket-info">
            <div class="info-row">
                <span class="info-label">No. Tiket:</span>
                <span class="info-value"><?php echo $data['no_tiket']; ?></span>
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?php echo $data['tanggal'] ? date('d/m/Y', strtotime($data['tanggal'])) : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">No. Polisi:</span>
                <span class="info-value"><?php echo $data['no_polisi']; ?></span>
                <span class="info-label">Supplier:</span>
                <span class="info-value"><?php echo $data['nama_supplier'] ?? '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Pengemudi:</span>
                <span class="info-value"><?php echo $data['nama_supir']; ?></span>
                <span class="info-label">Material:</span>
                <span class="info-value"><?php echo strtoupper($data['jenis_material'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Waktu Timbang 1:</span>
                <span class="info-value"><?php echo $data['waktu_timbangan1'] ? date('H:i:s', strtotime($data['waktu_timbangan1'])) : '-'; ?></span>
                <span class="info-label">Waktu Timbang 2:</span>
                <span class="info-value"><?php echo $data['waktu_timbangan2'] ? date('H:i:s', strtotime($data['waktu_timbangan2'])) : '-'; ?></span>
            </div>
        </div>

        <div class="weight-section">
            <table class="weight-table">
                <tr>
                    <td class="label">BERAT 1 (Bruto)</td>
                    <td class="weight-value"><?php echo number_format($data['berat_timbangan1'] ?? 0, 0, ',', '.'); ?></td>
                    <td class="label">Kg</td>
                </tr>
                <tr>
                    <td class="label">BERAT 2 (Tara)</td>
                    <td class="weight-value"><?php echo number_format($data['berat_timbangan2'] ?? 0, 0, ',', '.'); ?></td>
                    <td class="label">Kg</td>
                </tr>
                <tr>
                    <td class="label">BERAT BERSIH (Netto)</td>
                    <td class="weight-netto"><?php
                        $netto = ($data['berat_timbangan1'] ?? 0) - ($data['berat_timbangan2'] ?? 0);
                        if ($netto <= 0) $netto = $data['berat_netto'] ?? 0;
                        echo number_format($netto, 0, ',', '.');
                    ?></td>
                    <td class="label">Kg</td>
                </tr>
            </table>
        </div>

        <?php
        // Calculate netto and potongan
        $netto = ($data['berat_timbangan1'] ?? 0) - ($data['berat_timbangan2'] ?? 0);
        if ($netto <= 0) $netto = $data['berat_netto'] ?? 0;

        $persen_potongan = $data['persen_potongan'] ?? 0;
        $kg_potongan = $data['kg_potongan'] ?? 0;
        $potongan_persen = ($netto * $persen_potongan / 100);
        $total_potongan = $potongan_persen + $kg_potongan;
        $netto_akhir = $netto - $total_potongan;

        $harga_per_kg = $data['harga_per_kg'] ?? 0;
        $total_harga = $data['total_harga'] ?? ($netto_akhir * $harga_per_kg);

        if (($persen_potongan > 0) || ($kg_potongan > 0)):
        ?>
        <div class="potongan-section">
            <table class="potongan-table">
                <tr>
                    <td class="label">Potongan (%):</td>
                    <td><?php echo number_format($persen_potongan, 2, ',', '.'); ?> %</td>
                    <td class="label">Potongan (Kg):</td>
                    <td><?php echo number_format($kg_potongan, 0, ',', '.'); ?> Kg</td>
                </tr>
                <tr>
                    <td class="label">Netto Akhir:</td>
                    <td colspan="3"><?php echo number_format($netto_akhir, 0, ',', '.'); ?> Kg</td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($harga_per_kg > 0): ?>
        <div class="ticket-info">
            <div class="info-row">
                <span class="info-label">Harga per Kg:</span>
                <span class="info-value">Rp <?php echo number_format($harga_per_kg, 0, ',', '.'); ?></span>
                <span class="info-label">Total Harga:</span>
                <span class="info-value"><strong>Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></strong></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['keterangan'])): ?>
        <div class="ticket-info">
            <div class="info-row">
                <span class="info-label">Keterangan:</span>
                <span class="info-value" colspan="3"><?php echo $data['keterangan']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="barcode">
            [ <?php echo $data['no_tiket']; ?> ]
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div>Pengemudi</div>
                <div class="signature-line"></div>
                <div class="signature-name">(<?php echo $data['nama_supir']; ?>)</div>
            </div>
            <div class="signature-box">
                <div>Operator</div>
                <div class="signature-line"></div>
                <div class="signature-name">(<?php echo $_SESSION['nama_lengkap'] ?? 'Operator'; ?>)</div>
            </div>
        </div>

  
        <div class="ticket-footer">
            <p>----------------------------------</p>
            <p style="font-weight: bold;">*** DOKUMEN SAH ***</p>
            <p>Dicetak: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Auto print on load
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };

        // Prevent back button after print
        window.addEventListener('afterprint', function() {
            setTimeout(() => {
                window.close();
            }, 1000);
        });
    </script>
</body>
</html>