<?php
// modules/masterdata/pricing/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Harga Material - Master Data";

// Get materials with current price
$query = "SELECT mt.*,
                 (SELECT created_at FROM pricing_history WHERE material_type_id = mt.id ORDER BY created_at DESC LIMIT 1) as last_updated
          FROM material_types mt
          WHERE mt.status = 'active'
          ORDER BY mt.jenis_material ASC";
$result = mysqli_query($conn, $query);

// Get pricing history
$history_query = "SELECT ph.*, mt.jenis_material
                   FROM pricing_history ph
                   JOIN material_types mt ON ph.material_type_id = mt.id
                   ORDER BY ph.created_at DESC LIMIT 10";
$history_result = mysqli_query($conn, $history_query);

include '../../../includes/header.php';
?>

<style>
    .page-container {
        max-width: 1400px;
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

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 30px;
    }

    .pricing-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 25px;
    }

    .section-title {
        color: #dc2626;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .material-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .material-card {
        background: rgba(0, 0, 0, 0.3);
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
        height: 3px;
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
        align-items: center;
        margin-bottom: 15px;
    }

    .material-title {
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .material-description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 15px;
    }

    .price-info {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .current-price {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .price-label {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
    }

    .price-value {
        color: #22c55e;
        font-size: 18px;
        font-weight: 700;
    }

    .last-updated {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .btn-action {
        flex: 1;
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 10px 16px;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-action:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-secondary {
        background: transparent;
        border: 2px solid #666;
        color: #666;
    }

    .btn-secondary:hover {
        border-color: #fff;
        color: #fff;
        background: rgba(255, 255, 255, 0.1);
    }

    .history-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 25px;
        max-height: 600px;
        overflow-y: auto;
    }

    .history-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .history-item {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(220, 38, 38, 0.1);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .history-item:hover {
        border-color: rgba(220, 38, 38, 0.3);
        background: rgba(220, 38, 38, 0.05);
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .history-material {
        color: #dc2626;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
    }

    .history-date {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
    }

    .history-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
    }

    .price-change {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .old-price {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: line-through;
    }

    .new-price {
        color: #22c55e;
        font-weight: 600;
    }

    .price-arrow {
        color: #22c55e;
        font-size: 16px;
    }

    .price-decrease {
        color: #ef4444 !important;
    }

    .price-decrease .new-price {
        color: #ef4444;
    }

    .price-decrease .price-arrow {
        color: #ef4444;
    }

    .history-reason {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        font-style: italic;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
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

    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
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
            <i class="fas fa-dollar-sign"></i>
            Harga Material
        </h1>
    </div>

    <!-- Statistics Summary -->
    <div class="stats-summary">
        <?php
        // Get statistics
        $total_materials = mysqli_num_rows($result);
        $total_price = 0;
        $recent_changes = 0;

        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)) {
            $total_price += $row['harga_per_kg'] ?? 0;
        }

        // Count recent changes (last 7 days)
        $query_recent = "SELECT COUNT(*) as count FROM pricing_history WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $recent_result = mysqli_query($conn, $query_recent);
        $recent_data = mysqli_fetch_assoc($recent_result);
        $recent_changes = $recent_data['count'];
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
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></div>
            <div class="stat-label">Total Harga/Kg</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-value">Rp <?php echo number_format($total_materials > 0 ? $total_price / $total_materials : 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Rata-rata Harga</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-sync"></i>
            </div>
            <div class="stat-value"><?php echo number_format($recent_changes); ?></div>
            <div class="stat-label">Perubahan 7 Hari</div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Pricing Management -->
        <div class="pricing-section">
            <h3 class="section-title">
                <i class="fas fa-dollar-sign"></i>
                Manajemen Harga Material
            </h3>
            <div class="material-grid">
                <?php
                mysqli_data_seek($result, 0);
                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                ?>
                <div class="material-card">
                    <div class="material-title"><?php echo ucfirst($row['jenis_material']); ?></div>
                    <div class="material-description"><?php echo $row['deskripsi'] ?? 'Tidak ada deskripsi'; ?></div>

                    <div class="price-info">
                        <div class="current-price">
                            <span class="price-label">Harga Saat Ini</span>
                            <span class="price-value">Rp <?php echo number_format($row['harga_per_kg'], 0, ',', '.'); ?>/Kg</span>
                        </div>
                        <div class="last-updated">
                            <i class="fas fa-clock"></i>
                            <span>Update: <?php echo $row['last_updated'] ? date('d/m/Y H:i', strtotime($row['last_updated'])) : 'Belum ada'; ?></span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action" onclick="updatePrice(<?php echo $row['id']; ?>, '<?php echo $row['jenis_material']; ?>', <?php echo $row['harga_per_kg']; ?>)">
                            <i class="fas fa-edit"></i>
                            Update Harga
                        </button>
                        <button class="btn-action btn-secondary" onclick="viewHistory(<?php echo $row['id']; ?>)">
                            <i class="fas fa-history"></i>
                            Riwayat
                        </button>
                    </div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i><br>
                    <strong>Belum ada data material</strong><br>
                    <span>Tambah material terlebih dahulu</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent History -->
        <div class="history-section">
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Riwayat Perubahan Harga
            </h3>
            <ul class="history-list">
                <?php
                if (mysqli_num_rows($history_result) > 0):
                    while($history = mysqli_fetch_assoc($history_result)):
                        $is_increase = $history['new_price'] > $history['old_price'];
                ?>
                <li class="history-item">
                    <div class="history-header">
                        <div class="history-material"><?php echo ucfirst($history['jenis_material']); ?></div>
                        <div class="history-date"><?php echo date('d/m/Y H:i', strtotime($history['created_at'])); ?></div>
                    </div>
                    <div class="history-details">
                        <div class="price-change <?php echo $is_increase ? '' : 'price-decrease'; ?>">
                            <span class="old-price">Rp <?php echo number_format($history['old_price'], 0, ',', '.'); ?></span>
                            <span class="price-arrow"><?php echo $is_increase ? '↑' : '↓'; ?></span>
                            <span class="new-price">Rp <?php echo number_format($history['new_price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="history-reason">
                            <?php echo $history['reason'] ?: 'Tidak ada alasan'; ?>
                        </div>
                    </div>
                </li>
                <?php
                    endwhile;
                else:
                ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i><br>
                    <strong>Belum ada riwayat perubahan</strong><br>
                    <span>Perubahan harga akan tampil di sini</span>
                </div>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Update Price Modal -->
<div id="updatePriceModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Harga Material</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="updatePriceForm">
                <input type="hidden" id="materialId" name="material_id">
                <div class="form-group">
                    <label>Material</label>
                    <input type="text" id="materialName" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label>Harga Lama</label>
                    <input type="text" id="oldPrice" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label>Harga Baru (Rp/Kg) <span class="required">*</span></label>
                    <input type="number" id="newPrice" name="new_price" class="form-input" required min="0" step="100">
                </div>
                <div class="form-group">
                    <label>Alasan Perubahan</label>
                    <textarea id="reason" name="reason" class="form-textarea" placeholder="Alasan perubahan harga..." rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Tanggal Berlaku</label>
                    <input type="date" id="effectiveDate" name="effective_date" class="form-input" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid rgba(220, 38, 38, 0.2);
    }

    .modal-header h3 {
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        margin: 0;
    }

    .close {
        color: #666;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .close:hover {
        color: #fff;
    }

    .modal-body {
        padding: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-input, .form-textarea {
        width: 100%;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-input:focus, .form-textarea:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .form-textarea {
        resize: vertical;
    }

    .required {
        color: #ef4444;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .btn-primary, .btn-secondary {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-secondary {
        background: transparent;
        border: 2px solid #666;
        color: #666;
    }

    .btn-secondary:hover {
        border-color: #fff;
        color: #fff;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function updatePrice(id, name, currentPrice) {
    document.getElementById('materialId').value = id;
    document.getElementById('materialName').value = name;
    document.getElementById('oldPrice').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(currentPrice);
    document.getElementById('newPrice').value = currentPrice;
    document.getElementById('effectiveDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('updatePriceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('updatePriceModal').style.display = 'none';
}

function viewHistory(materialId) {
    // This would open a detailed history view
    // For now, just scroll to history section
    document.querySelector('.history-section').scrollIntoView({ behavior: 'smooth' });
}

// Handle form submission
document.getElementById('updatePriceForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    Swal.fire({
        title: 'Update Harga?',
        html: 'Apakah Anda yakin ingin mengupdate harga material ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#666',
        confirmButtonText: 'Ya, Update',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Here you would normally submit to server
            // For demo purposes, we'll just close the modal
            Swal.fire({
                title: 'Berhasil!',
                text: 'Harga material berhasil diperbarui',
                icon: 'success',
                confirmButtonColor: '#dc26226'
            }).then(() => {
                closeModal();
                // In real implementation, you would refresh the page
                location.reload();
            });
        }
    });
});

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
});
</script>

</body>
</html>