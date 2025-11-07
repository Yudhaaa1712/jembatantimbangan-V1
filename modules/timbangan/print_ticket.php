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

// Perhitungan yang benar
$berat_bruto = $data['berat_bruto'] ?? $data['berat_timbangan1'] ?? 0;
$berat_tara = $data['berat_tara'] ?? $data['berat_timbangan2'] ?? 0;
$persen_potongan = $data['persen_potongan'] ?? 0;
$harga_per_kg = $data['harga_per_kg'] ?? 0;

$bruto = $berat_bruto;
$tara = $berat_tara;
$netto = $bruto - $tara;
$persenPotongan = $persen_potongan;
$potonganKg = ($persenPotongan / 100) * $netto;
$nettoAkhir = $netto - $potonganKg;
$totalHarga = $nettoAkhir * $harga_per_kg;

$berat_netto = $nettoAkhir;
$kg_potongan = $potonganKg;
$total_harga = $totalHarga;
$netto_akhir = $nettoAkhir;
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
                margin: 0;
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
            width: 241mm;
            margin: 0 auto;
            padding: 8mm 10mm;
            background: white;
            color: #000;
        }
        
        .ticket-container {
            width: 100%;
            position: relative;
        }

        /* Header Section */
        .header-section {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 1mm;
        }

        .company-info {
            font-size: 10px;
            line-height: 1.4;
        }

        .separator {
            border-bottom: 1px solid #000;
            margin: 3mm 0;
        }

        /* Info Section - Two Column Layout */
        .info-section {
            margin: 3mm 0;
        }

        .info-row {
            display: flex;
            margin-bottom: 1.5mm;
            font-size: 11px;
        }

        .info-col {
            flex: 1;
            display: flex;
        }

        .info-label {
            min-width: 90px;
            font-weight: normal;
        }

        .info-value {
            flex: 1;
            font-weight: normal;
        }

        .info-separator {
            width: 10mm;
        }

        /* Material and Additional Info */
        .material-section {
            margin: 3mm 0;
            padding: 2mm 0;
        }

        /* Weight Section - Simple Table */
        .weight-section {
            margin: 4mm 0;
            border: 1px solid #000;
            padding: 2mm;
        }

        .weight-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 2mm;
            text-decoration: underline;
        }

        .weight-row {
            display: flex;
            justify-content: space-between;
            padding: 1mm 0;
            border-bottom: 1px dotted #666;
        }

        .weight-row:last-child {
            border-bottom: none;
            font-weight: bold;
            background: #f5f5f5;
            padding: 2mm;
            margin-top: 1mm;
        }

        .weight-label {
            font-weight: normal;
            width: 100px;
        }

        .weight-value {
            text-align: right;
            font-weight: bold;
            flex: 1;
        }

        .weight-unit {
            text-align: right;
            width: 30px;
            font-weight: normal;
        }

        /* Potongan Detail */
        .potongan-section {
            margin: 3mm 0;
            padding: 2mm;
            border: 1px solid #000;
            background: #fafafa;
        }

        .potongan-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 2mm;
            text-decoration: underline;
        }

        .potongan-row {
            display: flex;
            justify-content: space-between;
            padding: 1mm 0;
        }

        .potongan-label {
            font-weight: normal;
        }

        .potongan-value {
            text-align: right;
            font-weight: bold;
        }

        /* Price Section */
        .price-section {
            margin: 3mm 0;
            padding: 2mm;
            border: 2px solid #000;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 1mm 0;
        }

        .price-label {
            font-weight: normal;
        }

        .price-value {
            text-align: right;
            font-weight: bold;
        }

        .total-price {
            margin-top: 2mm;
            padding-top: 2mm;
            border-top: 1px solid #000;
            font-size: 13px;
        }

        /* Signature Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 10mm;
            padding: 0 10mm;
        }

        .signature-box {
            text-align: center;
            width: 80mm;
        }

        .signature-line {
            margin-top: 15mm;
            border-top: 1px solid #000;
            padding-top: 1mm;
        }

        .signature-role {
            margin-bottom: 2mm;
            font-weight: bold;
        }

        /* Footer */
        .footer-section {
            text-align: center;
            margin-top: 5mm;
            padding-top: 3mm;
            border-top: 1px solid #000;
            font-size: 10px;
        }

        .print-date {
            margin-top: 2mm;
            font-style: italic;
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

        /* Keterangan */
        .keterangan-section {
            margin: 3mm 0;
            padding: 2mm;
            border: 1px dashed #666;
        }

        .keterangan-label {
            font-weight: bold;
            margin-bottom: 1mm;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">
        🖨️ CETAK STRUK
    </button>

    <div class="ticket-container">
        <!-- Header -->
        <div class="header-section">
            <div class="company-name">PT. JEMBATAN TIMBANGAN SAWIT</div>
            <div class="company-info">
                Jl. Industri No. 123, Jakarta<br>
                Telp: 021-5551234
            </div>
        </div>

        <div class="separator"></div>

<!-- Info Section -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-col">
                    <span class="info-label">No. DO/TIKET</span>
                    <span class="info-value">: <?php echo $data['no_tiket']; ?></span>
                </div>
                <div class="info-separator"></div>
                <div class="info-col">
                    <span class="info-label">No. Polisi</span>
                    <span class="info-value">: <?php echo $data['no_polisi']; ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-col">
                    <span class="info-label">Nama Supir</span>
                    <span class="info-value">: <?php echo $data['nama_supir']; ?></span>
                </div>
                <div class="info-separator"></div>
                <div class="info-col">
                    <span class="info-label">Supplier</span>
                    <span class="info-value">: <?php echo $data['nama_supplier'] ?? '-'; ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-col">
                    <span class="info-label">Waktu Masuk</span>
                    <span class="info-value">: <?php echo $data['waktu_timbangan1'] ? date('d/m/Y H:i:s', strtotime($data['waktu_timbangan1'])) : '-'; ?></span>
                </div>
                <div class="info-separator"></div>
                <div class="info-col">
                    <span class="info-label">Waktu Keluar</span>
                    <span class="info-value">: <?php echo $data['waktu_timbangan2'] ? date('d/m/Y H:i:s', strtotime($data['waktu_timbangan2'])) : '-'; ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-col">
                    <span class="info-label">Keterangan</span>
                    <span class="info-value">: <?php echo $data['-'] ?? '-'; ?></span>
                </div>
                <div class="info-separator"></div>
                <div class="info-col">
                    <span class="info-label">Material</span>
                    <span class="info-value">: <?php echo $data['jenis_material'] ?? '-'; ?></span>
                </div>
            </div>
        </div>


        <div class="separator"></div>

        <!-- Weight Section -->
        <div class="weight-section">
            <div class="weight-title">SLIP TIMBANGAN BARANG</div>
            
            <div class="weight-row">
                <span class="weight-label">No. DO/Tiket</span>
                <span class="weight-value"><?php echo $data['no_tiket']; ?></span>
                <span class="weight-unit"></span>
            </div>


            <div class="weight-row">
                <span class="weight-label">BRUTO</span>
                <span class="weight-value"><?php echo number_format($bruto, 0, ',', '.'); ?></span>
                <span class="weight-unit">Kg</span>
            </div>

            <div class="weight-row">
                <span class="weight-label">TARA</span>
                <span class="weight-value"><?php echo number_format($tara, 0, ',', '.'); ?></span>
                <span class="weight-unit">Kg</span>
            </div>

            <div class="weight-row">
                <span class="weight-label">NETTO 1</span>
                <span class="weight-value"><?php echo number_format($netto, 0, ',', '.'); ?></span>
                <span class="weight-unit">Kg</span>
            </div>

            <?php if ($persen_potongan > 0 || $kg_potongan > 0): ?>
            <div class="weight-row">
                <span class="weight-label">Potongan (<?php echo number_format($persen_potongan, 2, ',', '.'); ?>%)</span>
                <span class="weight-value"><?php echo number_format($kg_potongan, 2, ',', '.'); ?></span>
                <span class="weight-unit">Kg</span>
            </div>

            <div class="weight-row">
                <span class="weight-label">NETTO 2</span>
                <span class="weight-value"><?php echo number_format($netto_akhir, 2, ',', '.'); ?></span>
                <span class="weight-unit">Kg</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($harga_per_kg > 0): ?>
        <!-- Price Section -->
        <div class="price-section">
            <div class="price-row">
                <span class="price-label">Harga</span>
                <span class="price-value">Rp <?php echo number_format($harga_per_kg, 0, ',', '.'); ?></span>
            </div>

            <div class="price-row">
                <span class="price-label">Jumlah (Rp.)</span>
                <span class="price-value"></span>
            </div>

            <div class="price-row total-price">
                <span class="price-label">TOTAL</span>
                <span class="price-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['keterangan'])): ?>
        <!-- Keterangan -->
        <div class="keterangan-section">
            <div class="keterangan-label">Keterangan:</div>
            <div><?php echo $data['keterangan']; ?></div>
        </div>
        <?php endif; ?>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-role">SUPIR</div>
                <div class="signature-line">
                    ( <?php echo $data['nama_supir']; ?> )
                </div>
            </div>

            <div class="signature-box">
                <div class="signature-role">OPERATOR</div>
                <div class="signature-line">
                    ( <?php echo $_SESSION['nama_lengkap'] ?? 'Operator'; ?> )
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div class="print-date">
                Dicetak: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
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