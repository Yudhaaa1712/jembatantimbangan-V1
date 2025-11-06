<?php
// modules/masterdata/index.php
require_once '../../config/database.php';
check_role(['admin']);

$page_title = "Master Data - Jembatan Timbangan Sawit";

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
        max-width: 1400px;
        margin: 20px auto;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        margin-bottom: 40px;
        text-align: center;
    }

    .page-title {
        color: #dc2626;
        font-size: 32px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.7);
        font-size: 16px;
        margin-bottom: 20px;
    }

    .masterdata-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .masterdata-category {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 25px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .masterdata-category::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #dc2626, #ef4444);
    }

    .masterdata-category:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
    }

    .category-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .category-icon {
        width: 50px;
        height: 50px;
        background: rgba(220, 38, 38, 0.1);
        border: 2px solid #dc2626;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
        font-size: 24px;
    }

    .category-title {
        color: #dc2626;
        font-size: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .category-description {
        color: rgba(255, 255, 255, 0.6);
        font-size: 14px;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .masterdata-list {
        list-style: none;
    }

    .masterdata-item {
        margin-bottom: 12px;
    }

    .masterdata-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.1);
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        transition: all 0.3s ease;
        gap: 12px;
    }

    .masterdata-link:hover {
        background: rgba(220, 38, 38, 0.1);
        border-color: rgba(220, 38, 38, 0.3);
        transform: translateX(5px);
    }

    .masterdata-link-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .item-icon {
        color: #dc2626;
        font-size: 16px;
        width: 20px;
        text-align: center;
    }

    .item-text {
        flex: 1;
    }

    .item-title {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .item-description {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.5);
    }

    .item-arrow {
        color: rgba(220, 38, 38, 0.6);
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .masterdata-link:hover .item-arrow {
        transform: translateX(3px);
        color: #dc2626;
    }

    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        font-size: 28px;
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
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-title {
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .main-container {
            margin: 15px;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
        }

        .masterdata-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-database"></i>
            Master Data
        </h1>
        <p class="page-subtitle">Kelola data referensi dan konfigurasi sistem jembatan timbangan</p>
    </div>

    <!-- Quick Statistics -->
    <div class="quick-stats">
        <?php
        // Get quick stats
        $stats = [];

        // Count suppliers
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM supplier");
        $stats['suppliers'] = mysqli_fetch_assoc($result)['count'];

        // Count vehicles
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM kendaraan");
        $stats['vehicles'] = mysqli_fetch_assoc($result)['count'];

        // Count users
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $stats['users'] = mysqli_fetch_assoc($result)['count'];

        // Count material types
        $stats['materials'] = 5; // Fixed for now, can be made dynamic
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['suppliers']); ?></div>
            <div class="stat-label">Supplier</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['vehicles']); ?></div>
            <div class="stat-label">Kendaraan</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
            <div class="stat-label">Pengguna</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-cube"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['materials']); ?></div>
            <div class="stat-label">Jenis Material</div>
        </div>
    </div>

    <!-- Master Data Categories -->
    <div class="masterdata-grid">
        <!-- Data Master Utama -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="category-title">Data Master Utama</div>
            </div>
            <p class="category-description">
                Kelola data utama sistem jembatan timbangan yang menjadi dasar dari seluruh transaksi.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="supplier/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-truck-loading"></i></span>
                            <div class="item-text">
                                <div class="item-title">Supplier</div>
                                <div class="item-description">Data pemasok material</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="kendaraan/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-truck"></i></span>
                            <div class="item-text">
                                <div class="item-title">Kendaraan</div>
                                <div class="item-description">Data kendaraan pengangkut</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="customer/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-handshake"></i></span>
                            <div class="item-text">
                                <div class="item-title">Customer</div>
                                <div class="item-description">Data pembeli produk</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Data Referensi -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="category-title">Data Referensi</div>
            </div>
            <p class="category-description">
                Kelola data referensi dan konfigurasi yang digunakan dalam transaksi harian.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="material/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-cube"></i></span>
                            <div class="item-text">
                                <div class="item-title">Jenis Material</div>
                                <div class="item-description">Tipe material & harga</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="jenis_kendaraan/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-tags"></i></span>
                            <div class="item-text">
                                <div class="item-title">Jenis Kendaraan</div>
                                <div class="item-description">Kategori kendaraan</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="settings/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-cog"></i></span>
                            <div class="item-text">
                                <div class="item-title">Pengaturan Sistem</div>
                                <div class="item-description">Konfigurasi global</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Manajemen Pengguna -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="category-title">Manajemen Pengguna</div>
            </div>
            <p class="category-description">
                Kelola akun pengguna dan hak akses sistem.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="../users/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-user"></i></span>
                            <div class="item-text">
                                <div class="item-title">Data Pengguna</div>
                                <div class="item-description">Kelola akun pengguna</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="roles/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-user-shield"></i></span>
                            <div class="item-text">
                                <div class="item-title">Hak Akses</div>
                                <div class="item-description">Role & permissions</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Data Operasional -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="category-title">Data Operasional</div>
            </div>
            <p class="category-description">
                Kelola data pendukung operasional timbangan.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="pricing/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-dollar-sign"></i></span>
                            <div class="item-text">
                                <div class="item-title">Harga Material</div>
                                <div class="item-description">Manajemen harga</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="kalibrasi/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-balance-scale"></i></span>
                            <div class="item-text">
                                <div class="item-title">Kalibrasi Timbangan</div>
                                <div class="item-description">Record kalibrasi</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="rfid/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-wifi"></i></span>
                            <div class="item-text">
                                <div class="item-title">RFID Tags</div>
                                <div class="item-description">Manajemen tag RFID</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Data Audit & Laporan -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="category-title">Audit & Laporan</div>
            </div>
            <p class="category-description">
                Pantau aktivitas sistem dan histori perubahan data.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="activity_logs/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-history"></i></span>
                            <div class="item-text">
                                <div class="item-title">Log Aktivitas</div>
                                <div class="item-description">Histori sistem</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="backup/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-database"></i></span>
                            <div class="item-text">
                                <div class="item-title">Backup Data</div>
                                <div class="item-description">Cadangkan data</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Data Maintenance -->
        <div class="masterdata-category">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="category-title">Maintenance</div>
            </div>
            <p class="category-description">
                Kelola perawatan dan maintenance sistem.
            </p>
            <ul class="masterdata-list">
                <li class="masterdata-item">
                    <a href="maintenance/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-wrench"></i></span>
                            <div class="item-text">
                                <div class="item-title">Jadwal Maintenance</div>
                                <div class="item-description">Perawatan sistem</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
                <li class="masterdata-item">
                    <a href="system_info/" class="masterdata-link">
                        <div class="masterdata-link-content">
                            <span class="item-icon"><i class="fas fa-info-circle"></i></span>
                            <div class="item-text">
                                <div class="item-title">Informasi Sistem</div>
                                <div class="item-description">Status & versi</div>
                            </div>
                        </div>
                        <span class="item-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth hover effects
    const cards = document.querySelectorAll('.masterdata-category');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Add click animation to links
    const links = document.querySelectorAll('.masterdata-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.style.position = 'absolute';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.background = 'rgba(220, 38, 38, 0.5)';
            ripple.style.borderRadius = '50%';
            ripple.style.transform = 'translate(-50%, -50%)';
            ripple.style.pointerEvents = 'none';
            ripple.style.animation = 'ripple 0.6s ease-out';

            const rect = this.getBoundingClientRect();
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });
});

// Add ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            width: 200px;
            height: 200px;
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>