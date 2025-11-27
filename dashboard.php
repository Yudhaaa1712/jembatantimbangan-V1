<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

include 'config/database.php';
$koneksi = $conn;

$page_title = "Dashboard - Jembatan Timbangan";
require_once 'includes/header.php';
?>

        <div class="container-fluid vh-100 py-2" style="max-height: 100vh; overflow: hidden;">
    <div class="row h-100">
        <div class="col-12">
            <div class="card border-0 bg-dark text-light shadow-lg h-100">
                <div class="card-header bg-gradient border-0 py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-0 text-white">
                               DASHBOARD
                            </h4>
                            <small class="text-light opacity-75">Sistem Jembatan Timbangan</small>
                        </div>
                        <div class="text-end">
                            <div class="text-white">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-3" style="overflow-y: auto; max-height: calc(100vh - 80px);">
                    <div class="row g-3">
                        <!-- Module Timbangan -->
                        <div class="col-lg-4 col-md-6">
                            <a href="modules/timbangan/timbangan1.php" class="text-decoration-none">
                                <div class="card bg-gradient border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-weight fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Timbangan 1</h5>
                                        <p class="card-text opacity-75">Input data awal timbangan (BRUTO)</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-plus me-2"></i>Mulai Timbang
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <a href="modules/timbangan/timbangan2.php" class="text-decoration-none">
                                <div class="card bg-gradient border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-balance-scale fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Timbangan 2</h5>
                                        <p class="card-text opacity-75">Proses akhir timbangan (TARA)</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-check me-2"></i>Selesaikan Timbang
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Module Transaksi -->
                        <div class="col-lg-4 col-md-6">
                            <a href="modules/transaksi/index.php" class="text-decoration-none">
                                <div class="card bg-gradient-success border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-list-alt fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Transaksi</h5>
                                        <p class="card-text opacity-75">Lihat semua data transaksi</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-eye me-2"></i>Lihat Data
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Module Master Data -->
                        <div class="col-lg-4 col-md-6">
                            <a href="modules/masterdata/index.php" class="text-decoration-none">
                                <div class="card bg-gradient-info border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-database fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Master Data</h5>
                                        <p class="card-text opacity-75">Kelola data master</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-cog me-2"></i>Kelola Data
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Module Laporan -->
                        <div class="col-lg-4 col-md-6">
                            <a href="modules/laporan/index.php" class="text-decoration-none">
                                <div class="card bg-gradient-warning border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-file-chart-line fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Laporan</h5>
                                        <p class="card-text opacity-75">Generate laporan</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-chart-bar me-2"></i>Buat Laporan
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Module Users -->
                        <div class="col-lg-4 col-md-6">
                            <a href="modules/users/index.php" class="text-decoration-none">
                                <div class="card bg-gradient-secondary border-0 text-white h-100 dashboard-card-hover">
                                    <div class="card-body p-4 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-users fa-3x"></i>
                                        </div>
                                        <h5 class="card-title">Users</h5>
                                        <p class="card-text opacity-75">Manajemen pengguna</p>
                                        <div class="mt-3">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <i class="fas fa-user-cog me-2"></i>Kelola User
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-12">
                            <div class="card bg-dark border-secondary">
                                <div class="card-header bg-secondary border-secondary">
                                    <h6 class="text-white mb-0">
                                        <i class="fas fa-rocket me-2"></i>Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-2">
                                        <div class="col-md-3 col-6">
                                            <a href="modules/timbangan/timbangan1.php" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-plus me-2"></i>Timbangan Baru
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <a href="modules/transaksi/index.php" class="btn btn-success btn-sm w-100">
                                                <i class="fas fa-eye me-2"></i>Lihat Transaksi
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <a href="modules/laporan/index.php" class="btn btn-warning btn-sm w-100">
                                                <i class="fas fa-chart-line me-2"></i>Laporan Hari Ini
                                            </a>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <a href="modules/auth/logout.php" class="btn btn-danger btn-sm w-100">
                                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.bg-gradient-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%) !important;
}

.bg-gradient-info {
    background: linear-gradient(135deg, #36b9cc 0%, #258391 100%) !important;
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%) !important;
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #858796 0%, #60616f 100%) !important;
}

.dashboard-card-hover {
    transition: all 0.3s ease;
    cursor: pointer;
}

.dashboard-card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3) !important;
}

.dashboard-card-hover .badge {
    transition: all 0.3s ease;
}

.dashboard-card-hover:hover .badge {
    background-color: #28a745 !important;
    transform: scale(1.05);
}

/* Compact badge styling */
.badge {
    font-size: 0.75rem;
    padding: 0.5em 1em;
    border-radius: 50px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container-fluid.vh-100 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .card-body {
        padding: 2rem 1.5rem !important;
    }

    .fa-3x {
        font-size: 2.5rem !important;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 1.5rem 1rem !important;
    }

    .fa-3x {
        font-size: 2rem !important;
    }

    .badge {
        font-size: 0.65rem;
        padding: 0.4em 0.8em;
    }
}

/* Ensure no scrollbars */
html, body {
    overflow: hidden;
}

.container-fluid.vh-100 {
    max-height: 100vh;
    overflow: hidden;
}
</style>

<?php require_once 'includes/footer.php'; ?>