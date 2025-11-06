<?php
// modules/timbangan/view_data.php - View all captured data
require_once '../../config/database.php';
check_role(['admin', 'operator']);

$page_title = "Data Timbangan - Jembatan Timbangan Sawit";

include '../../includes/header.php';
?>

<style>
body {
    background: #1a1a1a;
    color: #fff;
    font-family: 'Arial', sans-serif;
}

.form-control, .form-select {
    background: #2a2a2a;
    border: 1px solid #555;
    color: #fff;
    font-size: 14px;
}

.form-control:focus, .form-select:focus {
    background: #333;
    border-color: #007bff;
    color: #fff;
}

.card {
    background: #2a2a2a;
    border: 1px solid #555;
    border-radius: 10px;
}

.card-header {
    background: #333;
    border-bottom: 1px solid #555;
}

.table-dark {
    background: #2a2a2a;
}

.table-dark th,
.table-dark td {
    border-color: #444;
}

.badge-t1 {
    background: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.badge-t2 {
    background: #ffc107;
    color: #000;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.badge-selesai {
    background: #007bff;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.locked-indicator {
    color: #ff6b6b;
    font-size: 12px;
}

.weight-highlight {
    color: #51cf66;
    font-weight: bold;
}

.btn-view {
    background: #17a2b8;
    border: none;
    color: white;
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
}

.btn-view:hover {
    background: #138496;
}

.top-navbar {
    background: #1a1a1a !important;
    padding: 5px 0 !important;
}

.summary-card {
    background: #333;
    border: 1px solid #555;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.summary-number {
    font-size: 24px;
    font-weight: bold;
    color: #51cf66;
}
</style>

<div class="container-fluid py-3">
    <!-- Summary Cards -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-muted">Total Transaksi</div>
                <div class="summary-number" id="totalTransactions">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-muted">Timbang 1</div>
                <div class="summary-number" id="totalT1">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-muted">Timbang 2</div>
                <div class="summary-number" id="totalT2">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-muted">Selesai</div>
                <div class="summary-number" id="totalSelesai">0</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> FILTER DATA</h6>
                </div>
                <div class="card-body py-3">
                    <form id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="filterDate" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filterStatus">
                                    <option value="">Semua Status</option>
                                    <option value="timbang_1">Timbang 1</option>
                                    <option value="timbang_2">Timbang 2</option>
                                    <option value="selesai">Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Material</label>
                                <select class="form-select" id="filterMaterial">
                                    <option value="">Semua Material</option>
                                    <option value="tbs">TBS</option>
                                    <option value="cpo">CPO</option>
                                    <option value="kernel">KERNEL</option>
                                    <option value="brondolan">BRONDOLAN</option>
                                    <option value="lainnya">LAINNYA</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cari</label>
                                <input type="text" class="form-control" id="filterSearch" placeholder="No. Tiket / No. Pol">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary btn-sm" id="filterBtn">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="resetBtn">
                                    <i class="fas fa-refresh"></i> Reset
                                </button>
                                <button type="button" class="btn btn-success btn-sm" id="exportBtn">
                                    <i class="fas fa-download"></i> Export Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-database"></i> DATA CAPTURE TIMBANGAN</h6>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm" id="dataTable">
                            <thead>
                                <tr>
                                    <th>No. Tiket</th>
                                    <th>Tanggal</th>
                                    <th>No. Polisi</th>
                                    <th>Supir</th>
                                    <th>Supplier</th>
                                    <th>Customer</th>
                                    <th>Material</th>
                                    <th>T1 (Kg)</th>
                                    <th>T2 (Kg)</th>
                                    <th>Netto (Kg)</th>
                                    <th>Status</th>
                                    <th>Lock</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="dataTableBody">
                                <tr>
                                    <td colspan="13" class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title">Detail Transaksi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Will be filled with AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
// Load data function
function loadData() {
    const filterData = {
        action: 'get_all_transactions',
        date: $('#filterDate').val(),
        status: $('#filterStatus').val(),
        material: $('#filterMaterial').val(),
        search: $('#filterSearch').val()
    };

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: filterData,
        dataType: 'json',
        beforeSend: function() {
            $('#dataTableBody').html('<tr><td colspan="13" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>');
        },
        success: function(response) {
            if (response.success) {
                displayData(response.data);
                updateSummary(response.summary);
            } else {
                $('#dataTableBody').html('<tr><td colspan="13" class="text-center text-danger">Gagal memuat data</td></tr>');
            }
        },
        error: function() {
            $('#dataTableBody').html('<tr><td colspan="13" class="text-center text-danger">Terjadi kesalahan</td></tr>');
        }
    });
}

// Display data in table
function displayData(data) {
    let tbody = $('#dataTableBody');
    tbody.empty();

    if (data.length === 0) {
        tbody.html('<tr><td colspan="13" class="text-center text-muted">Tidak ada data</td></tr>');
        return;
    }

    data.forEach(function(row) {
        const statusBadge = getStatusBadge(row.status);
        const lockIndicator = getLockIndicator(row);

        const tr = `
            <tr>
                <td><strong>${row.no_tiket}</strong></td>
                <td>${row.tanggal}</td>
                <td>${row.no_polisi}</td>
                <td>${row.nama_supir || '-'}</td>
                <td>${row.nama_supplier || '-'}</td>
                <td>${row.nama_customer || '-'}</td>
                <td><span class="badge bg-primary">${row.jenis_material.toUpperCase()}</span></td>
                <td class="weight-highlight">${row.berat_timbangan1 ? formatNumber(row.berat_timbangan1) : '-'}</td>
                <td class="weight-highlight">${row.berat_timbangan2 ? formatNumber(row.berat_timbangan2) : '-'}</td>
                <td class="text-warning">${row.berat_netto ? formatNumber(row.berat_netto) : '-'}</td>
                <td>${statusBadge}</td>
                <td>${lockIndicator}</td>
                <td>
                    <button class="btn btn-view btn-sm" onclick="viewDetail(${row.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(tr);
    });
}

// Get status badge
function getStatusBadge(status) {
    switch(status) {
        case 'timbang_1':
            return '<span class="badge-t1">T1 Selesai</span>';
        case 'timbang_2':
            return '<span class="badge-t2">T2 Selesai</span>';
        case 'selesai':
            return '<span class="badge-selesai">Selesai</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Get lock indicator
function getLockIndicator(row) {
    let html = '';
    if (row.timbang1_locked) {
        html += '<div class="locked-indicator">🔒 T1</div>';
    }
    if (row.timbang2_locked) {
        html += '<div class="locked-indicator">🔒 T2</div>';
    }
    return html || '-';
}

// Update summary
function updateSummary(summary) {
    $('#totalTransactions').text(summary.total || 0);
    $('#totalT1').text(summary.t1_count || 0);
    $('#totalT2').text(summary.t2_count || 0);
    $('#totalSelesai').text(summary.selesai_count || 0);
}

// View detail
function viewDetail(id) {
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: { action: 'get_transaction_detail', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showDetailModal(response.data);
            } else {
                showError('Gagal memuat detail');
            }
        },
        error: function() {
            showError('Terjadi kesalahan');
        }
    });
}

// Show detail modal
function showDetailModal(data) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informasi Transaksi</h6>
                <table class="table table-sm table-dark">
                    <tr><td>No. Tiket</td><td><strong>${data.no_tiket}</strong></td></tr>
                    <tr><td>Tanggal</td><td>${data.tanggal}</td></tr>
                    <tr><td>No. Polisi</td><td>${data.no_polisi}</td></tr>
                    <tr><td>Nama Supir</td><td>${data.nama_supir || '-'}</td></tr>
                    <tr><td>No. DO</td><td>${data.no_do || '-'}</td></tr>
                    <tr><td>Operator</td><td>${data.nama_operator || '-'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Supplier & Customer</h6>
                <table class="table table-sm table-dark">
                    <tr><td>Supplier</td><td>${data.nama_supplier || '-'}</td></tr>
                    <tr><td>Customer</td><td>${data.nama_customer || '-'}</td></tr>
                    <tr><td>Material</td><td><span class="badge bg-primary">${data.jenis_material.toUpperCase()}</span></td></tr>
                    <tr><td>Status</td><td>${getStatusBadge(data.status)}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Data Timbangan</h6>
                <table class="table table-sm table-dark">
                    <tr>
                        <td>Timbangan 1</td>
                        <td class="weight-highlight">${data.berat_timbangan1 ? formatNumber(data.berat_timbangan1) + ' Kg' : '-'}</td>
                        <td>${data.waktu_timbangan1 || '-'}</td>
                        <td>${data.timbang1_locked ? '<span class="text-success">🔒 Locked</span>' : '-'}</td>
                    </tr>
                    <tr>
                        <td>Timbangan 2</td>
                        <td class="weight-highlight">${data.berat_timbangan2 ? formatNumber(data.berat_timbangan2) + ' Kg' : '-'}</td>
                        <td>${data.waktu_timbangan2 || '-'}</td>
                        <td>${data.timbang2_locked ? '<span class="text-success">🔒 Locked</span>' : '-'}</td>
                    </tr>
                    <tr class="border-top border-warning">
                        <td><strong>Netto</strong></td>
                        <td class="text-warning"><strong>${data.berat_netto ? formatNumber(data.berat_netto) + ' Kg' : '-'}</strong></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
        </div>
        ${data.harga_per_kg > 0 ? `
        <div class="row mt-3">
            <div class="col-12">
                <h6>Perhitungan Harga</h6>
                <table class="table table-sm table-dark">
                    <tr><td>Harga per Kg</td><td>Rp ${formatNumber(data.harga_per_kg)}</td></tr>
                    <tr><td>Potongan (%)</td><td>${data.persen_potongan || 0}%</td></tr>
                    <tr><td>Potongan (Kg)</td><td>${data.kg_potongan ? formatNumber(data.kg_potongan) + ' Kg' : '-'}</td></tr>
                    <tr class="border-top border-success">
                        <td><strong>Total Harga</strong></td>
                        <td class="text-success"><strong>Rp ${data.total_harga ? formatNumber(data.total_harga) : '-'}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
        ` : ''}
    `;

    $('#detailContent').html(content);
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// Event handlers
$('#filterBtn').click(loadData);
$('#resetBtn').click(function() {
    $('#filterDate').val('<?php echo date('Y-m-d'); ?>');
    $('#filterStatus, #filterMaterial, #filterSearch').val('');
    loadData();
});

$('#exportBtn').click(function() {
    const filterData = {
        action: 'export_data',
        date: $('#filterDate').val(),
        status: $('#filterStatus').val(),
        material: $('#filterMaterial').val(),
        search: $('#filterSearch').val()
    };

    window.open('ajax.php?' + $.param(filterData), '_blank');
});

// Auto refresh every 30 seconds
setInterval(loadData, 30000);

// Initial load
$(document).ready(function() {
    loadData();
});
</script>

<?php include '../../includes/footer.php'; ?>