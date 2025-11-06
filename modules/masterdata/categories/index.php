<?php
// modules/masterdata/categories/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Kategori Kendaraan - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (!empty($id) && is_numeric($id)) {
        // Check if category has vehicles
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM kendaraan WHERE kategori_id = '$id'");
        $result = mysqli_fetch_assoc($check);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Kategori tidak dapat dihapus karena masih digunakan oleh kendaraan!</div>';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM kategori_kendaraan WHERE id = '$id'");
            if ($delete) {
                $msg = '<div class="alert alert-success">Kategori berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus kategori!</div>';
            }
        }
    }
}

// Get categories
$query = "SELECT
            kk.*,
            COUNT(k.id) as jumlah_kendaraan,
            MAX(k.updated_at) as last_update
          FROM kategori_kendaraan kk
          LEFT JOIN kendaraan k ON kk.id = k.kategori_id
          GROUP BY kk.id
          ORDER BY kk.nama_kategori ASC";
$result = mysqli_query($conn, $query);

include '../../../includes/header.php';
?>

<style>
    .page-container {
        max-width: 1200px;
        margin: 20px auto;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(220, 38, 38, 0.2);
    }

    .page-title {
        color: #dc2626;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        color: #fff;
        text-decoration: none;
    }

    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .category-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .category-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #dc2626, #ef4444);
    }

    .category-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
    }

    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
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
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .category-code {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        margin-bottom: 10px;
    }

    .category-description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        margin-bottom: 15px;
        line-height: 1.4;
        min-height: 40px;
    }

    .category-specs {
        margin-bottom: 15px;
    }

    .spec-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 12px;
    }

    .spec-label {
        color: rgba(255, 255, 255, 0.6);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .spec-value {
        color: #fff;
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
        border: 1px solid #22c55e;
    }

    .status-inactive {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid #ef4444;
    }

    .vehicle-count {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        margin-bottom: 15px;
    }

    .vehicle-count strong {
        color: #dc2626;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-action {
        background: transparent;
        border: 1px solid #dc2626;
        border-radius: 6px;
        padding: 6px 12px;
        color: #dc2626;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-action:hover {
        background: #dc2626;
        color: #fff;
        text-decoration: none;
    }

    .btn-edit {
        border-color: #059669;
        color: #059669;
    }

    .btn-edit:hover {
        background: #059669;
    }

    .btn-delete {
        border-color: #dc2626;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #dc2626;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        border-color: #22c55e;
        color: #22c55e;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #ef4444;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: rgba(220, 38, 38, 0.5);
    }

    @media (max-width: 768px) {
        .page-container {
            margin: 15px;
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .categories-grid {
            grid-template-columns: 1fr;
        }

        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-tags"></i>
            Kategori Kendaraan
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Kategori
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_categories = mysqli_num_rows($result);
        $active_categories = 0;
        $total_vehicles = 0;

        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)) {
            if ($row['status'] == 'active') {
                $active_categories++;
            }
            $total_vehicles += $row['jumlah_kendaraan'];
        }
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_categories); ?></div>
            <div class="stat-label">Total Kategori</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_categories); ?></div>
            <div class="stat-label">Kategori Aktif</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_vehicles); ?></div>
            <div class="stat-label">Total Kendaraan</div>
        </div>
    </div>

    <!-- Categories Grid -->
    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="categories-grid">
            <?php
            mysqli_data_seek($result, 0);
            while($row = mysqli_fetch_assoc($result)):
            ?>
            <div class="category-card">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="<?php echo $row['icon'] ?? 'fas fa-truck'; ?>"></i>
                    </div>
                    <div>
                        <span class="status-badge <?php echo $row['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $row['status'] == 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                    </div>
                </div>

                <div class="category-title"><?php echo $row['nama_kategori']; ?></div>
                <div class="category-code">Kode: <?php echo $row['kode_kategori']; ?></div>
                <div class="category-description"><?php echo $row['deskripsi'] ?? 'Tidak ada deskripsi'; ?></div>

                <div class="category-specs">
                    <div class="spec-item">
                        <span class="spec-label">Kapasitas Min</span>
                        <span class="spec-value"><?php echo number_format($row['kapasitas_min'] ?? 0, 0, ',', '.'); ?> kg</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Kapasitas Max</span>
                        <span class="spec-value"><?php echo number_format($row['kapasitas_max'] ?? 0, 0, ',', '.'); ?> kg</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Standar Berat</span>
                        <span class="spec-value"><?php echo number_format($row['standar_berat'] ?? 0, 0, ',', '.'); ?> kg</span>
                    </div>
                </div>

                <div class="vehicle-count">
                    <i class="fas fa-truck"></i> <strong><?php echo $row['jumlah_kendaraan']; ?></strong> kendaraan
                </div>

                <?php if ($row['last_update']): ?>
                <div style="color: rgba(255,255,255,0.4); font-size: 11px; margin-bottom: 10px;">
                    <i class="fas fa-clock"></i> Update: <?php echo date('d/m/Y H:i', strtotime($row['last_update'])); ?>
                </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit
                    </a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['nama_kategori']; ?>')" class="btn-action btn-delete">
                        <i class="fas fa-trash"></i>
                        Hapus
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i><br>
            <strong>Belum ada data kategori</strong><br>
            <span>Tambah kategori kendaraan baru untuk memulai</span>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Kategori?',
        html: 'Apakah Anda yakin ingin menghapus kategori <strong>' + name + '</strong>?',
        text: 'Kategori yang memiliki kendaraan tidak dapat dihapus',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#666',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?action=delete&id=' + id;
        }
    });
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.category-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

</body>
</html>