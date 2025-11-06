<?php
// modules/transaksi/receipt.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

$ticket_no = $_GET['ticket'] ?? '';

if (empty($ticket_no)) {
    die('Ticket number is required');
}

// Get transaction data
$query = "SELECT tt.*, k.no_polisi, s.nama_supplier
          FROM transaksi_timbangan tt
          LEFT JOIN kendaraan k ON tt.id_kendaraan = k.id
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          WHERE tt.no_tiket = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $ticket_no);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$transaction = mysqli_fetch_assoc($result);

if (!$transaction) {
    die('Transaction not found');
}

// Get net weight if complete
$berat_netto = 0;
if ($transaction['status'] == 'complete') {
    $query_netto = "SELECT berat_netto FROM transaksi_timbangan WHERE no_tiket = ?";
    $stmt = mysqli_prepare($conn, $query_netto);
    mysqli_stmt_bind_param($stmt, "s", $ticket_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $netto_data = mysqli_fetch_assoc($result);
    $berat_netto = $netto_data['berat_netto'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi - <?php echo $ticket_no; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .receipt-container {
            background: white;
            width: 380px;
            max-width: 100%;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-address {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }

        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .receipt-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            color: #666;
        }

        .info-value {
            font-weight: bold;
            text-align: right;
        }

        .receipt-details {
            margin-bottom: 20px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .detail-table td {
            padding: 5px 0;
            font-size: 14px;
            border-bottom: 1px dotted #ccc;
        }

        .detail-table .label {
            color: #666;
            width: 40%;
        }

        .detail-table .value {
            font-weight: bold;
            text-align: right;
            width: 60%;
        }

        .weight-info {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .weight-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .weight-label {
            color: #333;
        }

        .weight-value {
            font-weight: bold;
            font-size: 16px;
        }

        .total-weight {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }

        .total-weight .weight-value {
            color: #dc2626;
            font-size: 18px;
        }

        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #333;
            padding-top: 15px;
            margin-top: 15px;
        }

        .footer-text {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .thank-you {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .print-button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px auto;
            display: block;
            transition: background 0.3s;
        }

        .print-button:hover {
            background: #b91c1c;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                max-width: 380px;
                margin: 0 auto;
            }

            .print-button {
                display: none;
            }
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-complete {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-t1 {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="company-name">JEMBATAN TIMBANGAN SAWIT</div>
            <div class="company-address">Jl. Industri No. 123</div>
            <div class="company-address">Tel: (021) 1234567</div>
            <div class="receipt-title">STRUK TRANSAKSI</div>
        </div>

        <!-- Receipt Information -->
        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">No. Tiket:</span>
                <span class="info-value"><?php echo $transaction['no_tiket']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?php echo $transaction['tanggal'] ? date('d/m/Y', strtotime($transaction['tanggal'])) : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Waktu:</span>
                <span class="info-value"><?php echo $transaction['waktu_masuk'] ? date('H:i:s', strtotime($transaction['waktu_masuk'])) : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">
                    <span class="status-badge <?php echo $transaction['status'] == 'complete' ? 'status-complete' : 'status-t1'; ?>">
                        <?php echo $transaction['status'] == 'complete' ? 'SELESAI' : 'TIMBANG 1'; ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Transaction Details -->
        <div class="receipt-details">
            <table class="detail-table">
                <tr>
                    <td class="label">No. Polisi:</td>
                    <td class="value"><?php echo $transaction['no_polisi'] ?? '-'; ?></td>
                </tr>
                <tr>
                    <td class="label">Pengemudi:</td>
                    <td class="value"><?php echo $transaction['nama_supir'] ?? '-'; ?></td>
                </tr>
                <tr>
                    <td class="label">Supplier:</td>
                    <td class="value"><?php echo $transaction['nama_supplier'] ?? '-'; ?></td>
                </tr>
                <tr>
                    <td class="label">Material:</td>
                    <td class="value"><?php echo ucfirst($transaction['jenis_material']); ?></td>
                </tr>
                <?php if ($transaction['harga_per_kg'] > 0): ?>
                <tr>
                    <td class="label">Harga/Kg:</td>
                    <td class="value">Rp <?php echo number_format($transaction['harga_per_kg'], 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Weight Information -->
        <div class="weight-info">
            <div class="weight-row">
                <span class="weight-label">Berat Timbangan 1:</span>
                <span class="weight-value"><?php echo number_format($transaction['berat_timbangan1'] ?? $transaction['berat_bruto'], 0, ',', '.'); ?> Kg</span>
            </div>
            <?php if ($transaction['status'] == 'selesai'): ?>
                <div class="weight-row">
                    <span class="weight-label">Berat Timbangan 2:</span>
                    <span class="weight-value"><?php echo number_format($transaction['berat_tara'] ?? 0, 0, ',', '.'); ?> Kg</span>
                </div>
                <div class="weight-row">
                    <span class="weight-label">Potongan:</span>
                    <span class="weight-value"><?php echo number_format($transaction['potongan'] ?? 0, 0, ',', '.'); ?> Kg</span>
                </div>
            <?php endif; ?>

            <?php if ($transaction['status'] == 'selesai'): ?>
                <div class="total-weight weight-row">
                    <span class="weight-label">Berat Netto:</span>
                    <span class="weight-value"><?php echo number_format($berat_netto, 0, ',', '.'); ?> Kg</span>
                </div>
            <?php else: ?>
                <div class="weight-row">
                    <span class="weight-label" style="color: #dc2626;">Belum Selesai Timbang 2</span>
                    <span class="weight-value" style="color: #dc2626;">--- Kg</span>
                </div>
            <?php endif; ?>

            <?php if ($transaction['harga_per_kg'] > 0 && $transaction['status'] == 'complete'): ?>
                <div class="total-weight weight-row">
                    <span class="weight-label">Total Harga:</span>
                    <span class="weight-value">Rp <?php echo number_format($berat_netto * $transaction['harga_per_kg'], 0, ',', '.'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($transaction['keterangan'])): ?>
        <div style="font-size: 12px; color: #666; margin-bottom: 15px;">
            <strong>Keterangan:</strong><br>
            <?php echo $transaction['keterangan']; ?>
        </div>
        <?php endif; ?>

        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div class="footer-text">Terima kasih atas kepercayaan Anda</div>
            <div class="footer-text">Barang yang sudah ditimbang tidak bisa dikembalikan</div>
            <div class="thank-you">*** TERIMA KASIH ***</div>
        </div>
    </div>

    <!-- Print Button -->
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak Struk
    </button>

    <script>
        // Auto print after 1 second
        setTimeout(() => {
            window.print();
        }, 1000);

        // Close window after printing
        window.onafterprint = function() {
            window.close();
        };
    </script>
</body>
</html>