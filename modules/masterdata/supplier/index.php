<?php
// modules/masterdata/supplier/index.php - Secure DRY Version
require_once '../../../config/database.php';
require_once '../../../includes/security_functions.php';
require_once '../../../includes/masterdata_handler.php';
check_role(['admin']);

$page_title = "Data Supplier - Master Data";

try {
    // Initialize secure handler
    $supplier_handler = new SupplierHandler($conn);

    // Handle actions
    $action = $_GET['action'] ?? '';
    $msg = '';

    if ($action == 'delete') {
        $id = SecurityUtils::sanitizeInput($_GET['id'] ?? 0, 'int');

        if ($id > 0) {
            try {
                if ($supplier_handler->delete($id)) {
                    // Log security event
                    SecurityUtils::logSecurityEvent($conn, 'SUPPLIER_DELETED', "Supplier ID $id deleted by user {$_SESSION['user_id']}", $_SESSION['user_id']);

                    $msg = '<div class="alert alert-success">Supplier berhasil dihapus!</div>';
                }
            } catch (Exception $e) {
                $msg = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
                SecurityUtils::logSecurityEvent($conn, 'SUPPLIER_DELETE_FAILED', $e->getMessage(), $_SESSION['user_id']);
            }
        }
    }

    // Get search and filter parameters
    $search = SecurityUtils::sanitizeInput($_GET['search'] ?? '');
    $status = SecurityUtils::sanitizeInput($_GET['status'] ?? '');
    $page = SecurityUtils::sanitizeInput($_GET['page'] ?? 1, 'int');
    $limit = 50;

    // Build filters
    $filters = [];
    if (!empty($status)) {
        $filters['status'] = $status;
    }

    // Get suppliers using secure method
    $suppliers_data = $supplier_handler->getAll($page, $limit, $search, $filters);
    $suppliers = $suppliers_data['data'];
    $total_pages = $suppliers_data['total_pages'];
    $current_page = $suppliers_data['page'];

} catch (Exception $e) {
    error_log("Error in supplier index: " . $e->getMessage());
    $suppliers = [];
    $total_pages = 1;
    $current_page = 1;
    $msg = '<div class="alert alert-danger">Terjadi kesalahan sistem. Silakan coba lagi.</div>';
}

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

    .search-section {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .search-form {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .search-group {
        flex: 1;
        min-width: 200px;
    }

    .search-label {
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: block;
    }

    .search-input {
        width: 100%;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
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
    }

    .btn-action {
        background: transparent;
        border: 1px solid #dc2626;
        border-radius: 6px;
        padding: 6px 10px;
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
        padding: 40px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
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

        .search-form {
            flex-direction: column;
        }

        .search-group {
            width: 100%;
        }

        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
           
            Data Supplier
        </h1>
        <a href="form.php?action=add" class="btn-primary">
            <i class="fas fa-plus"></i>
            Tambah Supplier
        </a>
    </div>

    <?php echo $msg; ?>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-group">
                <label class="search-label">Cari Supplier</label>
                <input type="text" name="search" class="search-input" placeholder="Nama supplier atau kode..." value="<?php echo SecurityUtils::escapeOutput($search); ?>">
            </div>
            <div class="search-group">
                <label class="search-label">Status</label>
                <select name="status" class="search-input">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">
               
                Cari
            </button>
        </form>
    </div>

    <!-- Table Section -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode Supplier</th>
                    <th>Nama Supplier</th>
                    <th>Kontak Person</th>
                    <th>Telepon</th>
                    <th>Total Hutang</th>
                    <th>Alamat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1 + (($current_page - 1) * $limit);

                if (!empty($suppliers)):
                    foreach($suppliers as $row):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo SecurityUtils::escapeOutput($row['kode_supplier']); ?></strong></td>
                    <td><?php echo SecurityUtils::escapeOutput($row['nama_supplier']); ?></td>
                    <td><?php echo SecurityUtils::escapeOutput($row['kontak_person'] ?? '-'); ?></td>
                    <td><?php echo SecurityUtils::escapeOutput($row['no_telepon'] ?? '-'); ?></td>
                    <td>
                        <?php
                        $hutang = floatval($row['total_hutang'] ?? 0);
                        if ($hutang > 0) {
                            echo '<span style="color: #ef4444; font-weight: bold;">Rp ' . number_format($hutang, 0, ',', '.') . '</span>';
                        } else {
                            echo '<span style="color: #10b981;">Rp 0</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo SecurityUtils::escapeOutput(substr($row['alamat'] ?? '-', 0, 30) . (strlen($row['alamat'] ?? '') > 30 ? '...' : '')); ?></td>
                    <td>
                        <span class="status-badge <?php echo $row['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $row['status'] == 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="form.php?action=edit&id=<?php echo $row['id']; ?>" class="btn-action btn-edit">
                                
                                Edit
                            </a>
                            <?php if ($hutang > 0): ?>
                            <a href="javascript:void(0)" onclick="kelolaHutang(<?php echo $row['id']; ?>, '<?php echo SecurityUtils::escapeOutput($row['nama_supplier']); ?>', <?php echo $hutang; ?>)" class="btn-action btn-warning" style="background: #f59e0b; color: #fff;">
                               
                               Edit Hutang
                            </a>
                            <?php endif; ?>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo SecurityUtils::escapeOutput($row['nama_supplier']); ?>')" class="btn-action btn-delete">
                                
                                Hapus
                            </a>
                        </div>
                    </td>
                </tr>
                <?php
                    endforeach;
                else:
                ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-inbox"></i><br>
                        <strong>Belum ada data supplier</strong><br>
                        <span>Tambah supplier baru untuk memulai</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container" style="margin-top: 20px; text-align: center;">
            <nav>
                <ul class="pagination" style="display: inline-flex; list-style: none; padding: 0; margin: 0; gap: 5px;">
                    <?php if ($current_page > 1): ?>
                        <li><a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="btn-action">« Prev</a></li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                        <li>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"
                               class="btn-action <?php echo $i == $current_page ? 'btn-primary' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li><a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="btn-action">Next »</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Supplier?',
        html: 'Apakah Anda yakin ingin menghapus supplier <strong>' + name + '</strong>?',
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
});

// Function untuk kelola hutang
function kelolaHutang(supplierId, supplierName, currentHutang) {
    Swal.fire({
        title: 'Kelola Hutang Supplier',
        html: `
            <div style="text-align: left;">
                <p><strong>Supplier:</strong> ${supplierName}</p>
                <p><strong>Hutang Saat Ini:</strong> Rp ${currentHutang.toLocaleString('id-ID')}</p>
                <div class="form-group mt-3">
                    <label for="jumlahBayar">Jumlah Pembayaran:</label>
                    <input type="text" id="jumlahBayar" class="swal2-input" placeholder="0" style="text-align: right;">
                    <small style="color: #666;">Format: 1.000.000 (gunakan titik sebagai pemisah ribuan)</small>
                </div>
                <div class="form-group mt-3">
                    <label for="keteranganBayar">Keterangan:</label>
                    <textarea id="keteranganBayar" class="swal2-textarea" placeholder="Opsional"></textarea>
                </div>
                <div id="errorBayar" style="color: #dc3545; font-size: 14px; margin-top: 10px; display: none;"></div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Bayar Hutang',
        cancelButtonText: 'Batal',
        width: '500px',
        preConfirm: () => {
            const bayarText = document.getElementById('jumlahBayar').value;
            const keterangan = document.getElementById('keteranganBayar').value;
            const errorDiv = document.getElementById('errorBayar');

            // Parse pembayaran
            const jumlahBayar = parseFloat(bayarText.replace(/[^\d]/g, '')) || 0;

            // Validasi
            if (jumlahBayar <= 0) {
                errorDiv.textContent = 'Jumlah pembayaran harus lebih dari 0!';
                errorDiv.style.display = 'block';
                return false;
            }

            if (jumlahBayar > currentHutang) {
                errorDiv.textContent = `Pembayaran (Rp ${jumlahBayar.toLocaleString('id-ID')}) melebihi hutang (Rp ${currentHutang.toLocaleString('id-ID')})!`;
                errorDiv.style.display = 'block';
                return false;
            }

            errorDiv.style.display = 'none';

            return {
                supplier_id: supplierId,
                jumlah_bayar: jumlahBayar,
                keterangan: keterangan
            };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                html: 'Sedang memperbarui data hutang...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // AJAX request to update hutang
            $.ajax({
                url: 'hutang_ajax.php',
                type: 'POST',
                data: {
                    action: 'update_hutang',
                    supplier_id: result.value.supplier_id,
                    jumlah_bayar: result.value.jumlah_bayar,
                    keterangan: result.value.keterangan
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: `
                                <div style="text-align: left;">
                                    <p>Hutang berhasil diperbarui!</p>
                                    <hr>
                                    <p><strong>Hutang Sebelumnya:</strong> Rp ${response.data.previous_hutang.toLocaleString('id-ID')}</p>
                                    <p><strong>Jumlah Bayar:</strong> Rp ${response.data.jumlah_bayar.toLocaleString('id-ID')}</p>
                                    <p><strong>Sisa Hutang:</strong> Rp ${response.data.new_hutang.toLocaleString('id-ID')}</p>
                                </div>
                            `,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reload page untuk update data
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat menghubungi server. Silakan coba lagi.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });

    // Format currency input
    setTimeout(() => {
        const bayarInput = document.getElementById('jumlahBayar');
        if (bayarInput) {
            bayarInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d]/g, '');
                if (value) {
                    value = parseInt(value).toLocaleString('id-ID');
                    e.target.value = value;
                }
            });

            bayarInput.addEventListener('focus', function(e) {
                e.target.select();
            });
        }
    }, 100);
}
</script>

</body>
</html>