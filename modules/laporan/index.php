<?php
// modules/laporan/index.php
require_once '../../config/database.php';
check_role(['admin', 'operator', 'viewer']);

$page_title = "Laporan Timbangan - Jembatan Timbangan Sawit";

// Validate and sanitize input
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$id_supplier = filter_var($_GET['id_supplier'] ?? 0, FILTER_VALIDATE_INT);
$jenis_material = trim($_GET['jenis_material'] ?? '');

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!DateTime::createFromFormat('Y-m-d', $end_date)) {
    $end_date = date('Y-m-d');
}

// Validate material type
$valid_materials = ['tbs', 'cpo', 'kernel', 'brondolan', 'lainnya'];
if (!empty($jenis_material) && !in_array($jenis_material, $valid_materials)) {
    $jenis_material = '';
}

// Get statistics using prepared statement
$query_stats = "SELECT
    COUNT(*) as total_transaksi,
    SUM(berat_bruto) as total_bruto,
    SUM(berat_tara) as total_tara,
    SUM(berat_netto) as total_netto,
    SUM(total_harga) as total_nilai
    FROM transaksi_timbangan
    WHERE tanggal BETWEEN ? AND ?";

$params = [$start_date, $end_date];
$types = "ss";

if ($id_supplier) {
    $query_stats .= " AND id_supplier = ?";
    $params[] = $id_supplier;
    $types .= "i";
}

if (!empty($jenis_material)) {
    $query_stats .= " AND jenis_material = ?";
    $params[] = $jenis_material;
    $types .= "s";
}

$query_stats .= " AND status = 'selesai'";

$stmt = mysqli_prepare($conn, $query_stats);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get transactions using prepared statement
$query = "SELECT * FROM view_transaksi_lengkap WHERE tanggal BETWEEN ? AND ?";
$trans_params = [$start_date, $end_date];
$trans_types = "ss";

if ($id_supplier) {
    $query .= " AND id_supplier = ?";
    $trans_params[] = $id_supplier;
    $trans_types .= "i";
}

if (!empty($jenis_material)) {
    $query .= " AND jenis_material = ?";
    $trans_params[] = $jenis_material;
    $trans_types .= "s";
}

$query .= " ORDER BY tanggal DESC, waktu_masuk DESC";

$trans_stmt = mysqli_prepare($conn, $query);
if (!empty($trans_params)) {
    mysqli_stmt_bind_param($trans_stmt, $trans_types, ...$trans_params);
}
mysqli_stmt_execute($trans_stmt);
$transactions = mysqli_stmt_get_result($trans_stmt);

// Get supplier list for filter
$supplier_list = mysqli_query($conn, "SELECT * FROM supplier WHERE status = 'active' ORDER BY nama_supplier");

include '../../includes/header.php';
?>

<div class="container-fluid">
    <h4 class="mb-4"><i class="fas fa-chart-line"></i> Laporan Timbangan</h4>

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-filter"></i> Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Tanggal Dari</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tanggal Sampai</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Supplier</label>
                        <select class="form-select" name="id_supplier">
                            <option value="">Semua Supplier</option>
                            <?php while($sup = mysqli_fetch_assoc($supplier_list)): ?>
                            <option value="<?php echo $sup['id']; ?>" <?php echo ($id_supplier == $sup['id']) ? 'selected' : ''; ?>>
                                <?php echo $sup['nama_supplier']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Jenis Material</label>
                        <select class="form-select" name="jenis_material">
                            <option value="">Semua Material</option>
                            <option value="tbs" <?php echo ($jenis_material == 'tbs') ? 'selected' : ''; ?>>TBS</option>
                            <option value="cpo" <?php echo ($jenis_material == 'cpo') ? 'selected' : ''; ?>>CPO</option>
                            <option value="kernel" <?php echo ($jenis_material == 'kernel') ? 'selected' : ''; ?>>Kernel</option>
                            <option value="brondolan" <?php echo ($jenis_material == 'brondolan') ? 'selected' : ''; ?>>Brondolan</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                        <button type="button" class="btn btn-success" onclick="exportExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                       
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Transaksi</h6>
                    <h2 class="text-primary"><?php echo number_format($stats['total_transaksi'] ?? 0); ?></h2>
                    <small>Transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Bruto</h6>
                    <h2 class="text-success"><?php echo number_format(($stats['total_bruto'] ?? 0)/1000, 1); ?></h2>
                    <small>Ton</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Tara</h6>
                    <h2 class="text-warning"><?php echo number_format(($stats['total_tara'] ?? 0)/1000, 1); ?></h2>
                    <small>Ton</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Netto</h6>
                    <h2 class="text-info"><?php echo number_format(($stats['total_netto'] ?? 0)/1000, 1); ?></h2>
                    <small>Ton</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-table"></i> Data Transaksi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableLaporan" class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>No. Tiket</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>No. Polisi</th>
                            <th>Supplier</th>
                            <th>Material</th>
                            <th>Bruto (Kg)</th>
                            <th>Tara (Kg)</th>
                            <th>Netto (Kg)</th>
                            <th>Harga/Kg</th>
                            <th>Total Harga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($transactions)): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo $row['no_tiket']; ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                            <td><?php echo date('H:i', strtotime($row['waktu_masuk'])); ?></td>
                            <td><?php echo $row['no_polisi']; ?></td>
                            <td><?php echo $row['nama_supplier'] ?? '-'; ?></td>
                            <td><span class="badge bg-info"><?php echo strtoupper($row['jenis_material']); ?></span></td>
                            <td class="text-end"><?php echo number_format($row['berat_bruto'], 0, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($row['berat_tara'], 0, ',', '.'); ?></td>
                            <td class="text-end"><strong><?php echo number_format($row['berat_netto'], 0, ',', '.'); ?></strong></td>
                            <td class="text-end"><?php echo number_format($row['harga_per_kg'], 0, ',', '.'); ?></td>
                            <td class="text-end"><strong><?php echo number_format($row['total_harga'], 0, ',', '.'); ?></strong></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="printTicket(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="7" class="text-end">TOTAL:</th>
                            <th class="text-end"><?php echo number_format($stats['total_bruto'] ?? 0, 0, ',', '.'); ?></th>
                            <th class="text-end"><?php echo number_format($stats['total_tara'] ?? 0, 0, ',', '.'); ?></th>
                            <th class="text-end"><?php echo number_format($stats['total_netto'] ?? 0, 0, ',', '.'); ?></th>
                            <th></th>
                            <th class="text-end"><?php echo number_format($stats['total_nilai'] ?? 0, 0, ',', '.'); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#tableLaporan').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>assets/id.json'
            },
            pageLength: 50,
            order: [[1, 'desc']],
            dom: 'Bfrtip',
            buttons: []
        });
    });

    function exportExcel() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'export.php?format=excel&' + params.toString();
    }

    function exportPDF() {
        const params = new URLSearchParams(window.location.search);
        window.open('export.php?format=pdf&' + params.toString(), '_blank');
    }
</script>

<?php include '../../includes/footer.php'; ?>