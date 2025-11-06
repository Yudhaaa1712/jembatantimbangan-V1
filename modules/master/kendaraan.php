    <?php
// modules/master/kendaraan.php
require_once '../../config/database.php';
check_role(['admin', 'operator']);

$page_title = "Master Kendaraan - Jembatan Timbangan Sawit";

// Get all kendaraan
$query = "SELECT * FROM kendaraan ORDER BY no_polisi ASC";
$kendaraan_list = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h4><i class="fas fa-truck"></i> Master Kendaraan</h4>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Tambah Kendaraan
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableKendaraan" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>No. Polisi</th>
                            <th>Jenis Kendaraan</th>
                            <th>Nama Supir Default</th>
                            <th>Tara Rata-rata (Kg)</th>
                            <th>RFID Tag</th>
                            <th>Status</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($kendaraan_list)): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo $row['no_polisi']; ?></strong></td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo strtoupper(str_replace('_', ' ', $row['jenis_kendaraan'])); ?>
                                </span>
                            </td>
                            <td><?php echo $row['nama_supir'] ?? '-'; ?></td>
                            <td><?php echo number_format($row['tara_avg'], 0, ',', '.'); ?></td>
                            <td><?php echo $row['rfid_tag'] ?? '-'; ?></td>
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
                                <button class="btn btn-sm btn-danger" onclick="deleteData(<?php echo $row['id']; ?>, '<?php echo $row['no_polisi']; ?>')">
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
<div class="modal fade" id="modalKendaraan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kendaraan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formKendaraan">
                <input type="hidden" name="id" id="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">No. Polisi *</label>
                        <input type="text" class="form-control" name="no_polisi" id="no_polisi" 
                               placeholder="Contoh: BM 1234 AB" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Kendaraan *</label>
                        <select class="form-select" name="jenis_kendaraan" id="jenis_kendaraan" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="truk_besar">Truk Besar</option>
                            <option value="pickup">Pick Up</option>
                            <option value="trailer">Trailer</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Supir Default</label>
                        <input type="text" class="form-control" name="nama_supir" id="nama_supir" 
                               placeholder="Nama supir (opsional)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tara Rata-rata (Kg)</label>
                        <input type="number" class="form-control" name="tara_avg" id="tara_avg" 
                               placeholder="8200" step="0.01">
                        <small class="text-muted">Akan otomatis terisi dari data timbangan</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">RFID Tag</label>
                        <input type="text" class="form-control" name="rfid_tag" id="rfid_tag" 
                               placeholder="RFID-001234">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" id="status" required>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
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
    const modal = new bootstrap.Modal(document.getElementById('modalKendaraan'));

    $(document).ready(function() {
        // Initialize DataTable
        table = $('#tableKendaraan').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            },
            pageLength: 25,
            order: [[1, 'asc']]
        });
    });

    function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Kendaraan';
        document.getElementById('formKendaraan').reset();
        document.getElementById('id').value = '';
        modal.show();
    }

    function editData(id) {
        showLoading();
        
        $.ajax({
            url: 'ajax_master.php',
            type: 'POST',
            data: {
                action: 'get_kendaraan',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Kendaraan';
                    document.getElementById('id').value = response.data.id;
                    document.getElementById('no_polisi').value = response.data.no_polisi;
                    document.getElementById('jenis_kendaraan').value = response.data.jenis_kendaraan;
                    document.getElementById('nama_supir').value = response.data.nama_supir;
                    document.getElementById('tara_avg').value = response.data.tara_avg;
                    document.getElementById('rfid_tag').value = response.data.rfid_tag;
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

    function deleteData(id, noPolisi) {
        confirmDialog('Apakah Anda yakin ingin menghapus kendaraan ' + noPolisi + '?', function() {
            showLoading();
            
            $.ajax({
                url: 'ajax_master.php',
                type: 'POST',
                data: {
                    action: 'delete_kendaraan',
                    id: id
                },
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

    $('#formKendaraan').on('submit', function(e) {
        e.preventDefault();
        
        const id = $('#id').val();
        const action = id ? 'update_kendaraan' : 'save_kendaraan';
        
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