<?php
// modules/masterdata/material/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Jenis Material - Master Data";

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

if ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (!empty($id) && is_numeric($id)) {
        // Check if material has transactions
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM transaksi_timbangan WHERE jenis_material = (SELECT jenis_material FROM material_types WHERE id = '$id')");
        $result = mysqli_fetch_assoc($check);

        if ($result['count'] > 0) {
            $msg = '<div class="alert alert-danger">Jenis material tidak dapat dihapus karena memiliki transaksi!</div>';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM material_types WHERE id = '$id'");
            if ($delete) {
                $msg = '<div class="alert alert-success">Jenis material berhasil dihapus!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Gagal menghapus jenis material!</div>';
            }
        }
    }
}

// Get material types (using static data since no material_types table exists)
$materials = [
    ['jenis_material' => 'tbs', 'nama_material' => 'TBS (Tandan Buah Segar)', 'deskripsi' => 'Tandan Buah Segar dari kebun sawit'],
    ['jenis_material' => 'cpo', 'nama_material' => 'CPO (Crude Palm Oil)', 'deskripsi' => 'Minyak sawit mentah hasil pengolahan'],
    ['jenis_material' => 'kernel', 'nama_material' => 'Kernel', 'deskripsi' => 'Inti sawit hasil pemisahan'],
    ['jenis_material' => 'brondolan', 'nama_material' => 'Brondolan', 'deskripsi' => 'Buah sawit yang jatuh/terlepas'],
    ['jenis_material' => 'lainnya', 'nama_material' => 'Lainnya', 'deskripsi' => 'Jenis material lainnya']
];

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

    .material-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .material-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .material-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #dc2626, #ef4444);
    }

    .material-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.2);
    }

    .material-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .material-icon {
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

    .material-title {
        color: #dc2626;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .material-description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 15px;
    }

    .material-details {
        margin-bottom: 15px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .detail-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
    }

    .price-value {
        color: #22c55e;
        font-size: 16px;
        font-weight: 700;
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

    .stats-summary {
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

        .material-grid {
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
            <i class="fas fa-cube"></i>
            Jenis Material
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Material
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_materials = count($materials);
        $active_materials = $total_materials; // All materials are active
        $total_price = 0; // No price data in static array
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_materials); ?></div>
            <div class="stat-label">Total Material</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($active_materials); ?></div>
            <div class="stat-label">Material Aktif</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></div>
            <div class="stat-label">Total Harga/Kg</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">Rp <?php echo number_format($total_materials > 0 ? $total_price / $total_materials : 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Rata-rata Harga</div>
        </div>
    </div>

    <!-- Materials Grid -->
    <?php if (!empty($materials)): ?>
        <div class="material-grid">
            <?php foreach ($materials as $row): ?>
            <div class="material-card">
                <div class="material-header">
                    <div class="material-icon">
                        <?php
                        $icon = 'fa-cube';
                        if ($row['jenis_material'] == 'tbs') $icon = 'fa-apple-alt';
                        elseif ($row['jenis_material'] == 'cpo') $icon = 'fa-oil-can';
                        elseif ($row['jenis_material'] == 'kernel') $icon = 'fa-seedling';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div>
                        <span class="status-badge status-active">
                            Aktif
                        </span>
                    </div>
                </div>

                <div class="material-title"><?php echo $row['nama_material']; ?></div>
                <div class="material-description"><?php echo $row['deskripsi']; ?></div>

                <div class="material-details">
                    <div class="detail-row">
                        <span class="detail-label">Harga per Kg</span>
                        <span class="detail-value price-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Satuan</span>
                        <span class="detail-value">Kg</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Kategori</span>
                        <span class="detail-value"><?php echo ucfirst($row['kategori'] ?? 'Umum'); ?></span>
                    </div>
                    <?php if (!empty($row['kode_material'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Kode Material</span>
                        <span class="detail-value"><?php echo $row['kode_material']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit
                    </a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo ucfirst($row['jenis_material']); ?>')" class="btn-action btn-delete">
                        <i class="fas fa-trash"></i>
                        Hapus
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-cube"></i><br>
            <strong>Belum ada data material</strong><br>
            <span>Tambah material baru untuk memulai</span>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Material?',
        html: 'Apakah Anda yakin ingin menghapus material <strong>' + name + '</strong>?',
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
    const cards = document.querySelectorAll('.material-card');
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