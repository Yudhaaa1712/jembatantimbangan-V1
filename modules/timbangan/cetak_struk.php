<?php
// modules/timbangan/cetak_struk.php
require_once '../../config/database.php';

if (!isset($_GET['no_tiket'])) {
    header('Location: timbangan2.php');
    exit;
}

$no_tiket = clean_input($_GET['no_tiket']);

// Get data transaksi timbangan yang sudah selesai
$query = "SELECT tt.*, s.nama_supplier FROM transaksi_timbangan tt LEFT JOIN supplier s ON tt.id_supplier = s.id WHERE tt.no_tiket = ? AND tt.status = 'selesai'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $no_tiket);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.close();</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Timbangan - <?= $no_tiket ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 15px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.4;
            }

            .no-print {
                display: none !important;
            }

            .struk-container {
                width: 100%;
                max-width: 300px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border: 1px solid #ddd;
            }

            .header {
                text-align: center;
                border-bottom: 2px dashed #000;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .company-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .company-address {
                font-size: 10px;
                margin-bottom: 5px;
            }

            .section {
                margin-bottom: 15px;
            }

            .section-title {
                font-weight: bold;
                text-decoration: underline;
                margin-bottom: 8px;
                font-size: 14px;
            }

            .data-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 11px;
            }

            .data-label {
                font-weight: bold;
            }

            .data-value {
                text-align: right;
            }

            .total-section {
                border-top: 2px dashed #000;
                padding-top: 10px;
                margin-top: 15px;
            }

            .total-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-weight: bold;
            }

            .grand-total {
                font-size: 14px;
                border-top: 1px solid #000;
                padding-top: 5px;
                margin-top: 10px;
            }

            .footer {
                text-align: center;
                margin-top: 20px;
                border-top: 2px dashed #000;
                padding-top: 10px;
                font-size: 10px;
            }

            .barcode {
                text-align: center;
                margin: 15px 0;
            }

            .barcode-number {
                font-family: 'Courier New', monospace;
                font-size: 14px;
                letter-spacing: 2px;
            }

            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 72px;
                color: rgba(0, 0, 0, 0.05);
                font-weight: bold;
                z-index: -1;
                pointer-events: none;
            }
        }

        @media screen {
            body {
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }

            .struk-container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                max-width: 350px;
                width: 100%;
            }

            .print-button {
                background: #dc2626;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: bold;
                margin-top: 20px;
                width: 100%;
                font-size: 16px;
                transition: all 0.3s ease;
            }

            .print-button:hover {
                background: #b91c1c;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
            }

            .close-button {
                background: #6c757d;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: bold;
                margin-top: 10px;
                width: 100%;
                font-size: 16px;
                transition: all 0.3s ease;
            }

            .close-button:hover {
                background: #5a6268;
                transform: translateY(-2px);
            }
        }
    </style>
</head>
<body>
    <div class="watermark">SAWIT</div>

    <div class="struk-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">JEMBATAN TIMBANGAN SAWIT</div>
            <div class="company-address">
                Jl. Industri Sawit No. 123<br>
                Telepon: (021) 1234-5678
            </div>
            <div class="company-address">
                =================================
            </div>
        </div>

        <!-- Informasi Transaksi -->
        <div class="section">
            <div class="section-title">INFORMASI TRANSAKSI</div>
            <div class="data-row">
                <span class="data-label">No. Tiket:</span>
                <span class="data-value"><?= $data['no_tiket'] ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Tanggal:</span>
                <span class="data-value"><?= $data['tanggal'] . ' ' . ($data['waktu_keluar'] ?? '') ?></span>
            </div>
        </div>

        <!-- Data Kendaraan -->
        <div class="section">
            <div class="section-title">DATA KENDARAAN</div>
            <div class="data-row">
                <span class="data-label">No. Kendaraan:</span>
                <span class="data-value"><?= $data['no_polisi'] ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Pengemudi:</span>
                <span class="data-value"><?= $data['nama_supir'] ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Suplier:</span>
                <span class="data-value"><?= $data['nama_supplier'] ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Material:</span>
                <span class="data-value"><?= strtoupper($data['jenis_material']) ?></span>
            </div>
        </div>

        <!-- Data Timbangan -->
        <div class="section">
            <div class="section-title">DATA TIMBANGAN</div>
            <div class="data-row">
                <span class="data-label">Berat 1 (Bruto):</span>
                <span class="data-value"><?= number_format($data['berat_timbangan1'], 0, ',', '.') ?> Kg</span>
            </div>
            <div class="data-row">
                <span class="data-label">Berat 2 (Tara):</span>
                <span class="data-value"><?= number_format($data['berat_timbangan2'], 0, ',', '.') ?> Kg</span>
            </div>
            <div class="data-row">
                <span class="data-label">Harga/Kg:</span>
                <span class="data-value">Rp <?= number_format($data['harga_per_kg'], 0, ',', '.') ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">% Potongan:</span>
                <span class="data-value"><?= number_format($data['persen_potongan'], 2, ',', '.') ?> %</span>
            </div>
        </div>

        <?php
        // Hitung ulang untuk struk
        $bruto = $data['berat_timbangan1'];
        $tara = $data['berat_timbangan2'];
        $netto_bt = $bruto - $tara;
        $total_potongan = ($data['persen_potongan'] / 100) * $netto_bt;
        $netto_akhir = $netto_bt - $total_potongan;
        $total_harga = $netto_akhir * $data['harga_per_kg'];
        ?>

        <!-- Perhitungan -->
        <div class="total-section">
            <div class="section-title">HASIL PERHITUNGAN</div>
            <div class="total-row">
                <span>Bruto:</span>
                <span><?= number_format($bruto, 0, ',', '.') ?> Kg</span>
            </div>
            <div class="total-row">
                <span>Tara:</span>
                <span><?= number_format($tara, 0, ',', '.') ?> Kg</span>
            </div>
            <div class="total-row">
                <span>Netto (B-T):</span>
                <span><?= number_format($netto_bt, 0, ',', '.') ?> Kg</span>
            </div>
            <div class="total-row">
                <span>Total Potongan:</span>
                <span><?= number_format($total_potongan, 0, ',', '.') ?> Kg</span>
            </div>
            <div class="total-row grand-total">
                <span>Netto Akhir:</span>
                <span><?= number_format($netto_akhir, 0, ',', '.') ?> Kg</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL HARGA:</span>
                <span>Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Barcode -->
        <div class="barcode">
            <div class="barcode-number"><?= $data['no_tiket'] ?></div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>=================================</div>
            <div>Terima Kasih Atas Kunjungan Anda</div>
            <div>Barang yang sudah ditimbang tidak dapat dikembalikan</div>
            <div>&nbsp;</div>
            <div><strong>Admin:</strong> ________________</div>
        </div>

        <!-- Buttons untuk view screen -->
        <div class="no-print">
            <button class="print-button" onclick="window.print()">
                <i class="fas fa-print"></i> CETAK STRUK
            </button>
            <button class="close-button" onclick="window.close()">
                <i class="fas fa-times"></i> TUTUP
            </button>
        </div>
    </div>

    <script>
        // Auto close setelah print
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };

        // Auto print saat halaman dimuat (opsional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
    </script>
</body>
</html>