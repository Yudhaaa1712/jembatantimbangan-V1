<?php
// modules/transaksi/index.php
require_once '../../config/database.php';
check_role(['admin', 'operator', 'viewer']);

$page_title = "Transaksi - Jembatan Timbangan Sawit";

// Handle date filtering
$date_filter = $_GET['date_filter'] ?? 'today';
$custom_date = $_GET['custom_date'] ?? date('Y-m-d');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Build date condition
switch($date_filter) {
    case 'today':
        $date_condition = "tanggal = CURDATE()";
        break;
    case 'yesterday':
        $date_condition = "tanggal = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'week':
        $date_condition = "tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
        break;
    case 'year':
        $date_condition = "YEAR(tanggal) = YEAR(CURDATE())";
        break;
    case 'custom':
        $date_condition = "tanggal = '$custom_date'";
        break;
    case 'custom_month':
        $date_condition = "MONTH(tanggal) = '$month' AND YEAR(tanggal) = '$year'";
        break;
    default:
        $date_condition = "tanggal = CURDATE()";
}

// Get transactions - hanya yang sudah selesai dari timbangan 2 dengan perhitungan JavaScript
$query = "SELECT tt.*,
                 tt.no_polisi as no_polisi_display, -- Use no_polisi directly from transaksi_timbangan
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

// Check for query errors
if (!$result) {
    error_log("Error in transaction query: " . mysqli_error($conn));
    $result = mysqli_query($conn, "SELECT * FROM transaksi_timbangan WHERE $date_condition ORDER BY created_at DESC");
}

// Get statistics - dengan perhitungan JavaScript yang sama
$query_stats = "SELECT
                    COUNT(*) as total_transaksi,
                    COALESCE(SUM(berat_timbangan1), 0) as total_bruto,
                    COALESCE(SUM(CASE
                        WHEN (berat_timbangan1 - berat_timbangan2) > 0 AND persen_potongan > 0 THEN
                            (berat_timbangan1 - berat_timbangan2) - ((persen_potongan / 100) * (berat_timbangan1 - berat_timbangan2))
                        WHEN berat_timbangan1 > 0 AND berat_timbangan2 > 0 THEN berat_timbangan1 - berat_timbangan2
                        WHEN berat_netto > 0 THEN berat_netto
                        ELSE 0
                    END), 0) as total_netto,
                    COALESCE(SUM(CASE
                        WHEN (berat_timbangan1 - berat_timbangan2) > 0 AND persen_potongan > 0 THEN
                            ((berat_timbangan1 - berat_timbangan2) - ((persen_potongan / 100) * (berat_timbangan1 - berat_timbangan2))) * harga_per_kg
                        WHEN harga_per_kg > 0 AND berat_timbangan1 > 0 AND berat_timbangan2 > 0 THEN (berat_timbangan1 - berat_timbangan2) * harga_per_kg
                        WHEN total_harga > 0 THEN total_harga
                        ELSE 0
                    END), 0) as total_harga,
                    COALESCE(AVG(berat_timbangan1), 0) as rata_bruto
                FROM transaksi_timbangan
                WHERE $date_condition AND status = 'selesai' AND timbang2_locked = 1";

$stats_result = mysqli_query($conn, $query_stats);
if (!$stats_result) {
    error_log("Error in stats query: " . mysqli_error($conn));
    $stats = [
        'total_transaksi' => 0,
        'total_bruto' => 0,
        'total_netto' => 0,
        'total_harga' => 0,
        'rata_bruto' => 0
    ];
} else {
    $stats = mysqli_fetch_assoc($stats_result);
}

include '../../includes/header.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    .main-container {
        max-width: 1600px;
        margin: 20px;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        margin-bottom: 30px;
        text-align: center;
    }

    .page-title {
        color: #dc2626;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 10px;
    }

    .filter-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .filter-row {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-group label {
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-control {
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
        min-width: 150px;
    }

    .filter-control:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .btn-filter {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 10px 24px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .btn-filter:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .stats-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.2);
    }

    .stat-icon {
        color: #dc2626;
        font-size: 32px;
        margin-bottom: 10px;
    }

    .stat-value {
        color: #fff;
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .action-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-action {
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 8px 16px;
        color: #dc2626;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-action:hover {
        background: #dc2626;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    .table-container {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        padding: 20px;
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid #dc2626;
        color: #dc2626;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 13px;
    }

    .data-table td {
        border: 1px solid rgba(220, 38, 38, 0.2);
        color: #fff;
        padding: 12px;
        font-size: 14px;
    }

    .data-table tr:hover {
        background: rgba(220, 38, 38, 0.05);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-t1 {
        background: rgba(220, 38, 38, 0.2);
        color: #dc2626;
        border: 1px solid #dc2626;
    }

    .status-complete {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
        border: 1px solid #22c55e;
    }

    .btn-receipt {
        background: linear-gradient(135deg, #059669, #047857);
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }

    .btn-receipt:hover {
        background: linear-gradient(135deg, #047857, #065f46);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(5, 150, 105, 0.5);
    }

    .btn-receipt:active {
        transform: translateY(0);
    }

    /* Icon styling */
    .btn-receipt i {
        margin-right: 4px;
    }

    .table-container {
        overflow-x: auto;
    }

    @media (max-width: 768px) {
        .main-container {
            margin: 15px;
            padding: 20px;
        }

        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-control {
            min-width: auto;
        }

        .stats-section {
            grid-template-columns: 1fr;
        }

        .action-section {
            flex-direction: column;
            align-items: stretch;
        }

        .table-container {
            padding: 10px;
        }

        .data-table {
            font-size: 12px;
        }

        .data-table th,
        .data-table td {
            padding: 8px;
        }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-exchange-alt"></i> Data Transaksi
        </h1>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <div class="filter-group">
                <label>Filter Tanggal</label>
                <select name="date_filter" class="filter-control" onchange="this.form.submit()">
                    <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="yesterday" <?php echo $date_filter == 'yesterday' ? 'selected' : ''; ?>>Kemarin</option>
                    <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="year" <?php echo $date_filter == 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                    <option value="custom" <?php echo $date_filter == 'custom' ? 'selected' : ''; ?>>Tanggal Spesifik</option>
                    <option value="custom_month" <?php echo $date_filter == 'custom_month' ? 'selected' : ''; ?>>Bulan Spesifik</option>
                </select>
            </div>

            <?php if ($date_filter == 'custom'): ?>
            <div class="filter-group">
                <label>Tanggal</label>
                <input type="date" name="custom_date" class="filter-control" value="<?php echo $custom_date; ?>">
            </div>
            <?php endif; ?>

            <?php if ($date_filter == 'custom_month'): ?>
            <div class="filter-group">
                <label>Bulan</label>
                <select name="month" class="filter-control">
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Tahun</label>
                <input type="number" name="year" class="filter-control" value="<?php echo $year; ?>" min="2020" max="2030">
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>

    <!-- Statistics Section -->
    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total_transaksi']); ?></div>
            <div class="stat-label">Total Transaksi</div>
        </div>
       
       
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value"><?php echo 'Rp ' . number_format($stats['total_harga'] ?? 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Total Harga</div>
        </div>
    </div>

    <!-- Action Section -->
    <div class="action-section">
        <div class="action-buttons">
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <button class="btn-action" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn-action" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <?php endif; ?>
            <button class="btn-action" onclick="printReport()">
                <i class="fas fa-print"></i> Cetak
            </button>
        </div>
        <div class="total-records">
            <span style="color: rgba(255,255,255,0.7);">Total Records: <?php echo mysqli_num_rows($result); ?></span>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-container">
        <table class="data-table" id="transaksiTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tiket No</th>
                    <th>Tanggal</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>No. Polisi</th>
                    <th>Supplier</th>
                    <th>Pengemudi</th>
                    <th>Material</th>
                    <th>Bruto (Kg)</th>
                    <th>Tara (Kg)</th>
                    <th>Netto 1 (Kg)</th>
                    <th>Potongan (%)</th>
                    <th>Potongan (Kg)</th>
                    <th>Netto 2 (Kg)</th>
                    <th>Harga/Kg</th>
                    <th>Total Harga</th>
                    <th>Operator</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                while($row = mysqli_fetch_assoc($result)):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo $row['no_tiket']; ?></strong></td>
                    <td><?php echo $row['tanggal'] ? date('d/m/Y', strtotime($row['tanggal'])) : '-'; ?></td>
                    <td><?php echo $row['waktu_timbangan1'] ? date('H:i:s', strtotime($row['waktu_timbangan1'])) : '-'; ?></td>
                    <td><?php echo $row['waktu_timbangan2'] ? date('H:i:s', strtotime($row['waktu_timbangan2'])) : '-'; ?></td>
                    <td><?php echo $row['no_polisi_display'] ?? $row['no_polisi'] ?? '-'; ?></td>
                    <td><?php echo $row['nama_supplier'] ?? '-'; ?></td>
                    <td><?php echo $row['nama_supir'] ?? '-'; ?></td>
                    <td><?php echo ucfirst($row['jenis_material']); ?></td>
                    <td style="text-align: right;"><?php echo number_format($row['berat_timbangan1'], 0, ',', '.'); ?></td>
                    <td style="text-align: right;"><?php echo number_format($row['berat_timbangan2'], 0, ',', '.'); ?></td>
                    <td style="text-align: right; font-weight: bold; background: #00000;">
                        <?php echo number_format($row['netto1_calc'] ?? 0, 0, ',', '.'); ?>
                    </td>
                    <td style="text-align: right;"><?php echo number_format($row['persen_potongan'] ?? 0, 1, ',', '.'); ?></td>
                    <td style="text-align: right; font-weight: bold; color: #f59e0b;">
                        <?php echo number_format($row['potongan_kg_calc'] ?? 0, 2, ',', '.'); ?>
                    </td>
                    <td style="text-align: right; font-weight: bold; background: #00000;">
                        <?php echo number_format($row['netto2_calc'] ?? 0, 2, ',', '.'); ?>
                    </td>
                    <td style="text-align: right;"><?php echo 'Rp ' . number_format($row['harga_per_kg'] ?? 0, 0, ',', '.'); ?></td>
                    <td style="text-align: right; font-weight: bold; color: #22c55e;">
                        <?php echo 'Rp ' . number_format($row['total_harga_calc'] ?? $row['total_harga'] ?? 0, 0, ',', '.'); ?>
                    </td>
                    <td>
                    <?php
                    if (!empty($row['operator_nama'])) {
                        echo $row['operator_nama'];
                    } elseif (!empty($row['operator_id'])) {
                        echo 'Operator ID: ' . $row['operator_id'];
                    } else {
                        echo '<span style="color: #6b7280;">-</span>';
                    }
                    ?>
                </td>
                    <td>
                        <button class="btn-receipt" onclick="printReceipt('<?php echo $row['no_tiket']; ?>')" title="Cetak Struk Continuous Form">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if(mysqli_num_rows($result) == 0): ?>
                <tr>
                    <td colspan="18" style="text-align: center; color: rgba(255,255,255,0.5);">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i><br>
                        Tidak ada data transaksi yang selesai untuk periode ini
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.open('export.php?' + params.toString(), '_blank');
}

function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open('export.php?' + params.toString(), '_blank');
}

function printReport() {
    window.print();
}

function printReceipt(ticketNo) {
    Swal.fire({
        title: 'Cetak Struk',
        text: 'Cetak struk untuk tiket: ' + ticketNo,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#666',
        confirmButtonText: '🖨️ Cetak',
        cancelButtonText: 'Batal',
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Buka struk
            window.open('../timbangan/print_ticket.php?no_tiket=' + ticketNo, '_blank');
        }
    });
}

// Auto refresh setiap 30 detik
setInterval(() => {
    window.location.reload();
}, 30000);
</script>

</body>
</html>