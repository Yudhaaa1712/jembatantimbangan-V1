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
            padding: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .company-address {
            font-size: 10px;
            color: #666;
            margin-bottom: 2px;
        }

        .receipt-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }

        .receipt-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 12px;
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
            padding: 4px 0;
            font-size: 12px;
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
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 12px;
        }

        .weight-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .weight-label {
            color: #333;
        }

        .weight-value {
            font-weight: bold;
            font-size: 14px;
        }

        .total-weight {
            border-top: 2px solid #333;
            padding-top: 8px;
            margin-top: 8px;
        }

        .total-weight .weight-value {
            color: #dc2626;
            font-size: 16px;
        }

        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #333;
            padding-top: 12px;
            margin-top: 12px;
        }

        .footer-text {
            font-size: 10px;
            color: #666;
            margin-bottom: 4px;
        }

        .thank-you {
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
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
            @page {
                size: 11in 9.5in landscape;
                margin: 0.3in 0.4in;
            }

            body {
                background: white;
                padding: 0;
                font-family: 'Arial', sans-serif;
                font-size: 16px;
                line-height: 1.4;
                width: 10.2in;
                font-weight: bold;
            }

            .receipt-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                margin: 0 auto;
                padding: 0;
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
        <!-- Header -->
        <div style="text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 20px;">
            <div style="font-size: 22px; font-weight: bold; margin-bottom: 8px;">JEMBATAN TIMBANGAN SAWIT</div>
            <div style="font-size: 14px; margin-bottom: 5px;">Jl. Industri No. 123</div>
            <div style="font-size: 14px;">Tel: (021) 1234567</div>
        </div>

        <!-- Data Transaksi -->
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">No. Tiket:</span>
            <span style="font-weight: bold;"><?php echo $transaction['no_tiket']; ?></span>
        </div>
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">Tanggal:</span>
            <span style="font-weight: bold;"><?php echo $transaction['tanggal'] ? date('d/m/Y', strtotime($transaction['tanggal'])) : '-'; ?></span>
        </div>
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">No. Kendaraan:</span>
            <span style="font-weight: bold;"><?php echo $transaction['no_polisi'] ?? '-'; ?></span>
        </div>
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">Pengemudi:</span>
            <span style="font-weight: bold;"><?php echo $transaction['nama_supir'] ?? '-'; ?></span>
        </div>
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">Supplier:</span>
            <span style="font-weight: bold;"><?php echo $transaction['nama_supplier'] ?? '-'; ?></span>
        </div>
        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">Material:</span>
            <span style="font-weight: bold;"><?php echo ucfirst($transaction['jenis_material']); ?></span>
        </div>

        <?php if (!empty($transaction['keterangan'])): ?>
        <div style="margin: 15px 0; padding: 10px; border: 1px dashed #666; background: #f9f9f9;">
            <div style="font-weight: bold; margin-bottom: 5px; font-size: 14px; text-align: center;">KETERANGAN</div>
            <div style="font-size: 13px; line-height: 1.4; text-align: center;"><?php echo htmlspecialchars($transaction['keterangan']); ?></div>
        </div>
        <?php endif; ?>

        <div class="separator" style="text-align: center; margin: 20px 0; font-size: 18px; font-weight: bold;">*** DATA TIMBANGAN ***</div>

        <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
            <span style="font-weight: bold;">Berat Timbangan 1:</span>
            <span style="font-weight: bold;"><?php echo number_format($transaction['berat_timbangan1'] ?? $transaction['berat_bruto'], 0, ',', '.'); ?> Kg</span>
        </div>

        <?php if ($transaction['status'] == 'selesai'): ?>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
                <span style="font-weight: bold;">Berat Timbangan 2:</span>
                <span style="font-weight: bold;"><?php echo number_format($transaction['berat_tara'] ?? 0, 0, ',', '.'); ?> Kg</span>
            </div>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
                <span style="font-weight: bold;">Berat Netto:</span>
                <span style="font-weight: bold;"><?php echo number_format($berat_netto, 0, ',', '.'); ?> Kg</span>
            </div>
        <?php else: ?>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
                <span style="font-weight: bold; color: #dc2626;">Status:</span>
                <span style="font-weight: bold; color: #dc2626;">BELUM SELESAI</span>
            </div>
        <?php endif; ?>

        <?php if ($transaction['harga_per_kg'] > 0 && $transaction['status'] == 'complete'): ?>
            <div class="separator" style="text-align: center; margin: 20px 0; font-size: 18px; font-weight: bold;">*** HARGA ***</div>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
                <span style="font-weight: bold;">Harga/Kg:</span>
                <span style="font-weight: bold;">Rp <?php echo number_format($transaction['harga_per_kg'], 0, ',', '.'); ?></span>
            </div>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 20px; border-top: 2px solid #000; padding-top: 10px;">
                <span style="font-weight: bold;">TOTAL HARGA:</span>
                <span style="font-weight: bold;">Rp <?php echo number_format($berat_netto * $transaction['harga_per_kg'], 0, ',', '.'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (($transaction['potong_hutang'] ?? 0) > 0 || ($transaction['sisa_hutang'] ?? 0) > 0): ?>
            <div class="separator" style="text-align: center; margin: 20px 0; font-size: 18px; font-weight: bold;">*** INFORMASI HUTANG ***</div>
            <?php if (($transaction['potong_hutang'] ?? 0) > 0): ?>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px;">
                <span style="font-weight: bold; color: #dc2626;">Potong Hutang:</span>
                <span style="font-weight: bold; color: #dc2626;">Rp <?php echo number_format($transaction['potong_hutang'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <?php if (($transaction['sisa_hutang'] ?? 0) > 0): ?>
            <div class="data-row" style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px; border: 2px dashed #dc2626; padding: 8px; border-radius: 5px;">
                <span style="font-weight: bold; color: #dc2626;">SISA HUTANG:</span>
                <span style="font-weight: bold; color: #dc2626;">Rp <?php echo number_format($transaction['sisa_hutang'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="separator" style="text-align: center; margin: 20px 0; font-size: 18px; font-weight: bold;">***</div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; border-top: 3px double #000; padding-top: 15px; font-size: 14px;">
            <div>Terima kasih atas kepercayaan Anda</div>
            <div>Barang yang sudah ditimbang tidak bisa dikembalikan</div>
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