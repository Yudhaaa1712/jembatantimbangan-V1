<?php
// modules/master/supplier.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

$page_title = "Master Supplier - Jembatan Timbangan Sawit";

// Get all supplier
$query = "SELECT * FROM supplier ORDER BY nama_supplier ASC";
$supplier_list = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h4><i class="fas fa-building"></i> Master Supplier</h4>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Tambah Supplier
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableSupplier" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>Kode</th>
                            <th>Nama Supplier</th>
                            <th>Alamat</th>
                            <th>Telepon</th>
                            <th>Kontak Person</th>
                            <th>Status</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($supplier_list)): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo $row['kode_supplier']; ?></strong></td>
                            <td><?php echo $row['nama_supplier']; ?></td>
                            <td><?php echo $row['alamat'] ?? '-'; ?></td>
                            <td><?php echo $row['telepon'] ?? '-'; ?></td>
                            <td><?php echo $row['kontak_person'] ?? '-'; ?></td>
                            <td>
                                <?php if($row['status'] == 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editData(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteData(<?php echo $row['id']; ?>, '<?php echo $row['nama_supplier']; ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="modalSupplier" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSupplier">
                <input type="hidden" name="id" id="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode Supplier *</label>
                            <input type="text" class="form-control" name="kode_supplier" id="kode_supplier" 
                                   placeholder="SUP001" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Supplier *</label>
                            <input type="text" class="form-control" name="nama_supplier" id="nama_supplier" 
                                   placeholder="PT. Sawit Jaya" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" id="alamat" rows="2" 
                                      placeholder="Alamat lengkap supplier"></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telepon</label>
                            <input type="text" class="form-control" name="telepon" id="telepon" 
                                   placeholder="0761-123456">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kontak Person</label>
                            <input type="text" class="form-control" name="kontak_person" id="kontak_person" 
                                   placeholder="Nama kontak person">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let table;
    const modal = new bootstrap.Modal(document.getElementById('modalSupplier'));

    $(document).ready(function() {
        table = $('#tableSupplier').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            },
            pageLength: 25,
            order: [[2, 'asc']]
        });
    });

    function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Supplier';
        document.getElementById('formSupplier').reset();
        document.getElementById('id').value = '';
        modal.show();
    }

    function editData(id) {
        showLoading();
        
        $.ajax({
            url: 'ajax_master.php',
            type: 'POST',
            data: { action: 'get_supplier', id: id },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Supplier';
                    document.getElementById('id').value = response.data.id;
                    document.getElementById('kode_supplier').value = response.data.kode_supplier;
                    document.getElementById('nama_supplier').value = response.data.nama_supplier;
                    document.getElementById('alamat').value = response.data.alamat;
                    document.getElementById('telepon').value = response.data.telepon;
                    document.getElementById('kontak_person').value = response.data.kontak_person;
                    document.getElementById('status').value = response.data.status;
                    modal.show();
                } else {
                    showError(response.message);
                }
            },
            error: function() {
                hideLoading();
                showError('Terjadi kesalahan sistem!');
            }
        });
    }

    function deleteData(id, nama) {
        confirmDialog('Apakah Anda yakin ingin menghapus supplier ' + nama + '?', function() {
            showLoading();
            
            $.ajax({
                url: 'ajax_master.php',
                type: 'POST',
                data: { action: 'delete_supplier', id: id },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        showSuccess(response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(response.message);
                    }
                },
                error: function() {
                    hideLoading();
                    showError('Terjadi kesalahan sistem!');
                }
            });
        });
    }

    $('#formSupplier').on('submit', function(e) {
        e.preventDefault();
        
        const id = $('#id').val();
        const action = id ? 'update_supplier' : 'save_supplier';
        
        showLoading();
        
        $.ajax({
            url: 'ajax_master.php',
            type: 'POST',
            data: $(this).serialize() + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess(response.message);
                    modal.hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(response.message);
                }
            },
            error: function() {
                hideLoading();
                showError('Terjadi kesalahan sistem!');
            }
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>