<?php
// modules/transaksi/export_excel_advanced.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

// Validate input parameters
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-d');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'selesai';

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $tanggal_awal) || !DateTime::createFromFormat('Y-m-d', $tanggal_akhir)) {
    die('Format tanggal tidak valid. Gunakan format YYYY-MM-DD.');
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment;filename="Laporan_Timbangan_Professional_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Excel header HTML template
$excel_header = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi Timbangan</title>
    <style>
        @page {
            margin: 0.5in;
            size: landscape;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Calibri, Arial, sans-serif;
            font-size: 11px;
        }

        .company-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .company-address {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }

        .company-phone {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }

        .report-period {
            font-size: 13px;
            color: #333;
            font-weight: bold;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .data-table th {
            background-color: #DC2626 !important;
            color: #FFFFFF !important;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            border: 1px solid #000;
            padding: 8px 6px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .data-table td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 10px;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background-color: #F9F9F9;
        }

        .data-table tr:hover {
            background-color: #F5F5F5;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-italic {
            font-style: italic;
        }

        .total-row {
            background-color: #DC2626 !important;
            color: #FFFFFF !important;
            font-weight: bold;
            font-size: 12px;
        }

        .total-row td {
            border: 1px solid #000;
            padding: 8px 6px;
        }

        .statistics-section {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #DC2626;
            background-color: #FFF5F5;
        }

        .statistics-title {
            font-size: 16px;
            font-weight: bold;
            color: #DC2626;
            margin-bottom: 10px;
            text-align: center;
        }

        .statistics-table {
            width: 100%;
            border-collapse: collapse;
        }

        .statistics-table td {
            border: 1px solid #CCC;
            padding: 6px;
            font-size: 11px;
        }

        .statistics-table td:first-child {
            font-weight: bold;
            background-color: #F5F5F5;
        }

        .footer-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #000;
            text-align: center;
        }

        .footer-text {
            font-size: 10px;
            color: #666;
            font-style: italic;
            margin-bottom: 5px;
        }

        .merge-cell {
            text-align: center;
            font-weight: bold;
        }

        .number-format {
            text-align: right;
        }

        .currency-format {
            text-align: right;
            white-space: nowrap;
        }

        @media print {
            @page {
                size: landscape;
                margin: 0.5in;
            }
        }
    </style>
</head>
<body>';

// Fetch data from database
$query = "SELECT tt.*,
                 s.nama_supplier,
                 u.nama_lengkap as operator_nama,
                 -- Perhitungan yang sama seperti di struk
                 (tt.berat_timbangan1 - tt.berat_timbangan2) as netto1_calc,
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         (tt.berat_timbangan1 - tt.berat_timbangan2) - ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))
                     WHEN tt.berat_netto > 0 THEN tt.berat_netto
                     ELSE (tt.berat_timbangan1 - tt.berat_timbangan2)
                 END as netto2_calc,
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))
                     ELSE 0
                 END as potongan_kg_calc,
                 CASE
                     WHEN (tt.berat_timbangan1 - tt.berat_timbangan2) > 0 AND tt.persen_potongan > 0 THEN
                         ((tt.berat_timbangan1 - tt.berat_timbangan2) - ((tt.persen_potongan / 100) * (tt.berat_timbangan1 - tt.berat_timbangan2))) * tt.harga_per_kg
                     WHEN tt.harga_per_kg > 0 AND (tt.berat_timbangan1 > 0 AND tt.berat_timbangan2 > 0) THEN (tt.berat_timbangan1 - tt.berat_timbangan2) * tt.harga_per_kg
                     WHEN tt.total_harga > 0 THEN tt.total_harga
                     ELSE 0
                 END as total_harga_calc,
                 s.total_hutang as sisa_hutang_supplier
          FROM transaksi_timbangan tt
          LEFT JOIN supplier s ON tt.id_supplier = s.id
          LEFT JOIN users u ON tt.operator_id = u.id
          WHERE DATE(tt.tanggal) BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
          AND tt.status = '$status' AND tt.timbang2_locked = 1
          ORDER BY tt.created_at DESC";

$result = mysqli_query($conn, $query);
$no = 1;
$grand_total_bruto = 0;
$grand_total_netto1 = 0;
$grand_total_netto2 = 0;
$grand_total_potongan = 0;
$grand_total_harga = 0;
$grand_total_potong_hutang = 0;
$grand_total_akhir = 0;

// Start building the HTML
echo $excel_header;
?>

<!-- Company Header -->
<div class="company-header">
    <div class="company-name">RAM JAWA SUMATRA</div>
    <div class="company-address">KAMPUNG TENGAH MAMAHAN JAYA PKL. GONDAL</div>
    <div class="company-phone">HP. 0822 8518 1010 / 0812 2706 1544</div>
    <div class="report-title">LAPORAN TRANSAKSI TIMBANGAN BARANG</div>
    <div class="report-period">Periode: <?php echo date('d/m/Y', strtotime($tanggal_awal)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir)); ?></div>
</div>

<!-- Data Table -->
<table class="data-table">
    <thead>
        <tr>
            <th rowspan="2">NO</th>
            <th rowspan="2">NO. TIKET</th>
            <th rowspan="2">TANGGAL</th>
            <th colspan="2">WAKTU</th>
            <th colspan="2">KENDARAAN</th>
            <th colspan="2">SUPPLIER</th>
            <th colspan="3">TIMBANGAN (KG)</th>
            <th colspan="2">POTONGAN</th>
            <th rowspan="2">NETTO 2 (KG)</th>
            <th colspan="2">HARGA</th>
            <th colspan="2">HUTANG</th>
            <th rowspan="2">TOTAL AKHIR</th>
            <th rowspan="2">KETERANGAN</th>
            <th rowspan="2">OPERATOR</th>
        </tr>
        <tr>
            <th>MASUK</th>
            <th>KELUAR</th>
            <th>NO. POLISI</th>
            <th>SUPIR</th>
            <th>NAMA</th>
            <th>MATERIAL</th>
            <th>BRUTO</th>
            <th>TARA</th>
            <th>NETTO 1</th>
            <th>%</th>
            <th>KG</th>
            <th>/KG</th>
            <th>TOTAL</th>
            <th>POTONG</th>
            <th>SISA</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while($data = mysqli_fetch_assoc($result)): ?>
                <?php
                // Hitung nilai-nilai seperti di struk
                $bruto = $data['berat_timbangan1'] ?? 0;
                $tara = $data['berat_timbangan2'] ?? 0;
                $netto1 = $bruto - $tara;
                $persen_potongan = $data['persen_potongan'] ?? 0;
                $kg_potongan = $data['potongan_kg_calc'] ?? 0;
                $netto2 = $data['netto2_calc'] ?? $data['berat_netto'] ?? 0;
                $harga_per_kg = $data['harga_per_kg'] ?? 0;
                $total_harga = $data['total_harga_calc'] ?? 0;
                $potong_hutang = $data['potong_hutang'] ?? 0;
                $sisa_hutang = $data['sisa_hutang_supplier'] ?? 0;
                $total_akhir = max(0, $total_harga - $potong_hutang);

                // Accumulate totals
                $grand_total_bruto += $bruto;
                $grand_total_netto1 += $netto1;
                $grand_total_netto2 += $netto2;
                $grand_total_potongan += $kg_potongan;
                $grand_total_harga += $total_harga;
                $grand_total_potong_hutang += $potong_hutang;
                $grand_total_akhir += $total_akhir;
                ?>
                <tr>
                    <td class="text-center"><?php echo $no; ?></td>
                    <td class="text-center font-bold"><?php echo $data['no_tiket']; ?></td>
                    <td class="text-center"><?php echo $data['tanggal'] ? date('d/m/Y', strtotime($data['tanggal'])) : '-'; ?></td>
                    <td class="text-center"><?php echo $data['waktu_timbangan1'] ? date('H:i:s', strtotime($data['waktu_timbangan1'])) : '-'; ?></td>
                    <td class="text-center"><?php echo $data['waktu_timbangan2'] ? date('H:i:s', strtotime($data['waktu_timbangan2'])) : '-'; ?></td>
                    <td class="text-center"><?php echo $data['no_polisi'] ?? '-'; ?></td>
                    <td><?php echo $data['nama_supir'] ?? '-'; ?></td>
                    <td><?php echo $data['nama_supplier'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo ucfirst($data['jenis_material'] ?? '-'); ?></td>
                    <td class="number-format"><?php echo number_format($bruto, 2, ',', '.'); ?></td>
                    <td class="number-format"><?php echo number_format($tara, 2, ',', '.'); ?></td>
                    <td class="number-format font-bold"><?php echo number_format($netto1, 2, ',', '.'); ?></td>
                    <td class="number-format"><?php echo number_format($persen_potongan, 1, ',', '.'); ?></td>
                    <td class="number-format font-bold" style="color: #F59E0B;"><?php echo number_format($kg_potongan, 2, ',', '.'); ?></td>
                    <td class="number-format font-bold"><?php echo number_format($netto2, 2, ',', '.'); ?></td>
                    <td class="currency-format"><?php echo $harga_per_kg > 0 ? 'Rp ' . number_format($harga_per_kg, 0, ',', '.') : '-'; ?></td>
                    <td class="currency-format font-bold" style="color: #22C55E;"><?php echo $total_harga > 0 ? 'Rp ' . number_format($total_harga, 0, ',', '.') : '-'; ?></td>
                    <td class="currency-format" style="color: #DC2626;"><?php echo $potong_hutang > 0 ? 'Rp ' . number_format($potong_hutang, 0, ',', '.') : '-'; ?></td>
                    <td class="currency-format" style="color: #DC2626;"><?php echo $sisa_hutang > 0 ? 'Rp ' . number_format($sisa_hutang, 0, ',', '.') : '-'; ?></td>
                    <td class="currency-format font-bold" style="color: #1F2937;"><?php echo $total_akhir > 0 ? 'Rp ' . number_format($total_akhir, 0, ',', '.') : '-'; ?></td>
                    <td style="max-width: 200px; word-wrap: break-word;"><?php echo $data['keterangan'] ?? '-'; ?></td>
                    <td><?php echo $data['operator_nama'] ?? '-'; ?></td>
                </tr>
                <?php $no++; endwhile; ?>

                <!-- Grand Total Row -->
                <tr class="total-row">
                    <td colspan="9" class="merge-cell">GRAND TOTAL</td>
                    <td class="number-format"><?php echo number_format($grand_total_bruto, 2, ',', '.'); ?></td>
                    <td></td>
                    <td class="number-format"><?php echo number_format($grand_total_netto1, 2, ',', '.'); ?></td>
                    <td></td>
                    <td class="number-format"><?php echo number_format($grand_total_potongan, 2, ',', '.'); ?></td>
                    <td class="number-format"><?php echo number_format($grand_total_netto2, 2, ',', '.'); ?></td>
                    <td></td>
                    <td class="currency-format"><?php echo 'Rp ' . number_format($grand_total_harga, 0, ',', '.'); ?></td>
                    <td class="currency-format"><?php echo 'Rp ' . number_format($grand_total_potong_hutang, 0, ',', '.'); ?></td>
                    <td></td>
                    <td class="currency-format"><?php echo 'Rp ' . number_format($grand_total_akhir, 0, ',', '.'); ?></td>
                    <td colspan="2"></td>
                </tr>
        <?php else: ?>
            <tr>
                <td colspan="21" class="text-center font-italic">Tidak ada data transaksi untuk periode ini</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($result && mysqli_num_rows($result) > 0): ?>

</div>
<?php endif; ?>

<!-- Footer Section -->
<div class="footer-section">
    <div class="footer-text">Laporan ini dihasilkan otomatis oleh Sistem Jembatan Timbangan RAM JAWA SUMATRA</div>
    <div class="footer-text">Dicetak tanggal: <?php echo date('d/m/Y H:i:s'); ?></div>
    <div class="footer-text">Data yang ditampilkan adalah transaksi dengan status: <?php echo strtoupper($status); ?></div>
</div>

</body>
</html>

<?php exit; ?>