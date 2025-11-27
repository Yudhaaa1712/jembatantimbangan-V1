<?php
// modules/transaksi/export_excel.php
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

// Set headers for CSV Excel download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment;filename="Laporan_Timbangan_' . date('Y-m-d') . '.csv"');
header('Cache-Control: max-age=0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 support
fwrite($output, "\xEF\xBB\xBF");

// COMPANY HEADER
fputcsv($output, ['RAM JAWA SUMATRA']);
fputcsv($output, ['KAMPUNG TENGAH MAMAHAN JAYA PKL. GONDAL']);
fputcsv($output, ['HP. 0822 8518 1010 / 0812 2706 1544']);
fputcsv($output, ['LAPORAN TRANSAKSI TIMBANGAN BARANG']);
fputcsv($output, ['Periode: ' . date('d/m/Y', strtotime($tanggal_awal)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir))]);
fputcsv($output, []); // Empty row

// TABLE HEADERS
$headers = [
    'NO',
    'NO. TIKET',
    'TANGGAL',
    'WAKTU MASUK',
    'WAKTU KELUAR',
    'NO. POLISI',
    'SUPIR',
    'SUPPLIER',
    'MATERIAL',
    'BRUTO (KG)',
    'TARA (KG)',
    'NETTO 1 (KG)',
    'POTONGAN (%)',
    'POTONGAN (KG)',
    'NETTO 2 (KG)',
    'HARGA/KG',
    'TOTAL HARGA',
    'POTONG HUTANG',
    'SISA HUTANG',
    'TOTAL AKHIR',
    'KETERANGAN',
    'OPERATOR'
];

fputcsv($output, $headers);

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

if ($result && mysqli_num_rows($result) > 0) {
    while($data = mysqli_fetch_assoc($result)) {
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

        // Prepare row data
        $rowData = [
            $no,
            $data['no_tiket'],
            $data['tanggal'] ? date('d/m/Y', strtotime($data['tanggal'])) : '',
            $data['waktu_timbangan1'] ? date('H:i:s', strtotime($data['waktu_timbangan1'])) : '',
            $data['waktu_timbangan2'] ? date('H:i:s', strtotime($data['waktu_timbangan2'])) : '',
            $data['no_polisi'] ?? '',
            $data['nama_supir'] ?? '',
            $data['nama_supplier'] ?? '',
            ucfirst($data['jenis_material'] ?? ''),
            number_format($bruto, 2, ',', '.'),
            number_format($tara, 2, ',', '.'),
            number_format($netto1, 2, ',', '.'),
            number_format($persen_potongan, 1, ',', '.'),
            number_format($kg_potongan, 2, ',', '.'),
            number_format($netto2, 2, ',', '.'),
            $harga_per_kg > 0 ? 'Rp ' . number_format($harga_per_kg, 0, ',', '.') : '',
            $total_harga > 0 ? 'Rp ' . number_format($total_harga, 0, ',', '.') : '',
            $potong_hutang > 0 ? 'Rp ' . number_format($potong_hutang, 0, ',', '.') : '',
            $sisa_hutang > 0 ? 'Rp ' . number_format($sisa_hutang, 0, ',', '.') : '',
            $total_akhir > 0 ? 'Rp ' . number_format($total_akhir, 0, ',', '.') : '',
            $data['keterangan'] ?? '',
            $data['operator_nama'] ?? ''
        ];

        fputcsv($output, $rowData);
        $no++;
    }

    // Empty row before totals
    fputcsv($output, []);

    // GRAND TOTAL ROW
    $totals = [
        'GRAND TOTAL',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        number_format($grand_total_bruto, 2, ',', '.'),
        '',
        number_format($grand_total_netto1, 2, ',', '.'),
        '',
        number_format($grand_total_potongan, 2, ',', '.'),
        number_format($grand_total_netto2, 2, ',', '.'),
        '',
        'Rp ' . number_format($grand_total_harga, 0, ',', '.'),
        'Rp ' . number_format($grand_total_potong_hutang, 0, ',', '.'),
        '',
        'Rp ' . number_format($grand_total_akhir, 0, ',', '.'),
        '',
        ''
    ];

    fputcsv($output, $totals);

    // Empty row before statistics
    fputcsv($output, []);

    // STATISTICS SECTION
    $total_transaksi = mysqli_num_rows($result);
    $avg_bruto = $total_transaksi > 0 ? $grand_total_bruto / $total_transaksi : 0;
    $avg_netto = $total_transaksi > 0 ? $grand_total_netto2 / $total_transaksi : 0;

    fputcsv($output, ['RINGKASAN STATISTIK']);
    fputcsv($output, ['Total Transaksi', $total_transaksi]);
    fputcsv($output, ['Rata-rata Bruto', number_format($avg_bruto, 2, ',', '.') . ' kg']);
    fputcsv($output, ['Rata-rata Netto', number_format($avg_netto, 2, ',', '.') . ' kg']);
    fputcsv($output, ['Total Potongan', number_format($grand_total_potongan, 2, ',', '.') . ' kg']);
    fputcsv($output, ['Total Harga', 'Rp ' . number_format($grand_total_harga, 0, ',', '.')]);

} else {
    fputcsv($output, ['Tidak ada data transaksi untuk periode ini']);
}

// Empty rows before footer
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, []);

// Footer information
fputcsv($output, ['Laporan ini dihasilkan otomatis oleh Sistem Jembatan Timbangan RAM JAWA SUMATRA']);
fputcsv($output, ['Dicetak tanggal: ' . date('d/m/Y H:i:s')]);
fputcsv($output, ['Data yang ditampilkan adalah transaksi dengan status: ' . strtoupper($status)]);

// Close output stream
fclose($output);
exit;
?>