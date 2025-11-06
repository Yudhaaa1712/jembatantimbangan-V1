<?php
require_once 'config/database.php';
$page_title = "Page Not Found - 404";
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-exclamation-triangle text-warning fa-4x"></i>
                </div>
                <h1 class="display-4 fw-bold">404</h1>
                <h3>Page Not Found</h3>
                <p class="text-muted">Halaman yang Anda cari tidak ditemukan atau telah dipindahkan.</p>

                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>modules/timbangan/index.php" class="btn btn-primary">
                        <i class="fas fa-weight"></i> Halaman Timbangan
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/laporan/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-line"></i> Laporan
                    </a>
                </div>

                <hr class="my-4">

                <div class="text-start">
                    <h5>Quick Links:</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>modules/timbangan/index.php">⚖️ Proses Timbangan</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/laporan/index.php">📈 Laporan</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/master/kendaraan.php">🚚 Master Kendaraan</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/master/supplier.php">🏢 Master Supplier</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/auth/login.php">🔐 Login</a></li>
                    </ul>
                </div>

                <?php if (is_logged_in()): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        Debug: You are logged in as <?php echo $_SESSION['nama_lengkap']; ?>
                        (<?php echo $_SESSION['user_role']; ?>)
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>