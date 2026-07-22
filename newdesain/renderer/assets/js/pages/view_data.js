/**
 * View Data Timbangan JS
 * Logic for capturing and displaying weighing data via AJAX
 * Kolom & perhitungan disamakan dengan struk (print_ticket.php)
 */

// Format numbers (1000 -> 1.000)
function formatNumber(num) {
    if (num === null || num === undefined || num === '' || isNaN(num)) return '0';
    return Math.round(Number(num)).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
}

// Format Rupiah
function formatRupiah(num) {
    if (num === null || num === undefined || num === '' || isNaN(num) || Number(num) === 0) return 'Rp 0';
    return 'Rp ' + formatNumber(num);
}

// Format berat
function formatBerat(num) {
    if (num === null || num === undefined || num === '' || isNaN(num) || Number(num) === 0) return '0';
    return formatNumber(num);
}

// Format waktu (dari datetime string)
function formatWaktu(datetime) {
    if (!datetime || datetime === '0000-00-00 00:00:00') return '-';
    try {
        if (typeof datetime === 'string' && !datetime.includes('-') && datetime.includes(':')) {
            return datetime.slice(0, 5);
        }
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return '-';
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = String(d.getFullYear()).slice(-2);
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}-${month}-${year} ${hours}:${minutes}`;
    } catch (e) {
        return '-';
    }
}

// Show error with SweetAlert (assuming loaded) or fallback
function showError(msg) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: msg
        });
    } else {
        alert(msg);
    }
}

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
        beforeSend: function () {
            $('#dataTableBody').html('<tr><td colspan="19" class="text-center">Memuat data...</td></tr>');
        },
        success: function (response) {
            if (response.success && response.data) {
                displayData(response.data);
                updateSummary(response.data.summary);
            } else {
                $('#dataTableBody').html('<tr><td colspan="19" class="text-center text-danger">Gagal memuat data</td></tr>');
            }
        },
        error: function () {
            $('#dataTableBody').html('<tr><td colspan="19" class="text-center text-danger">Terjadi kesalahan koneksi</td></tr>');
        }
    });
}

/**
 * Hitung data perhitungan sama seperti di struk (print_ticket.php)
 */
function hitungData(row) {
    const bruto = Number(row.berat_bruto || row.berat_timbangan1 || 0);
    const tara = Number(row.berat_tara || row.berat_timbangan2 || 0);
    const persen_potongan = Number(row.persen_potongan || 0);
    const harga_per_kg = Number(row.harga_per_kg || 0);

    const netto_awal = bruto - tara;
    const kg_potongan = Math.round((persen_potongan / 100) * netto_awal);
    const netto_akhir = Math.round(netto_awal - kg_potongan);
    const total_harga_bruto = netto_akhir * harga_per_kg; // Total Awal

    // Potongan Rupiah
    const potongan_pupuk_rp = Number(row.potongan_pupuk_rp || 0);
    const potongan_hutang_rp = Number(row.potongan_hutang_rp || 0);
    const potongan_jalan = Number(row.potongan_jalan || 0);
    const total_potongan_rp = potongan_pupuk_rp + potongan_hutang_rp + potongan_jalan;

    // Total Akhir = Total Awal - Potongan Rupiah
    const total_akhir = Math.max(0, total_harga_bruto - total_potongan_rp);

    return {
        bruto,
        tara,
        netto_awal,
        persen_potongan,
        kg_potongan,
        netto_akhir,
        harga_per_kg,
        total_harga_bruto,       // Total Awal
        total_potongan_rp,       // Potong Hutang
        total_akhir              // Total Akhir (Sisa Bayar)
    };
}

// Display data in table
function displayData(result) {
    let tbody = $('#dataTableBody');
    let data = result.data || result;
    tbody.empty();

    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="19" class="text-center text-muted">Tidak ada data</td></tr>');
        return;
    }

    data.forEach(function (row) {
        const statusBadge = getStatusBadge(row.status);
        const calc = hitungData(row);

        const tr = `
            <tr>
                <td><strong>${row.no_tiket || '-'}</strong></td>
                <td>${row.tanggal || '-'}</td>
                <td>${(row.no_polisi || '-').toUpperCase()}</td>
                <td>${(row.nama_supplier || '-').toUpperCase()}</td>
                <td>${(row.nama_supir || '-').toUpperCase()}</td>
                <td><span class="badge bg-primary">${row.jenis_material ? row.jenis_material.toUpperCase() : '-'}</span></td>
                <td class="text-end">${calc.bruto ? formatBerat(calc.bruto) : '-'}</td>
                <td class="text-end">${calc.tara ? formatBerat(calc.tara) : '-'}</td>
                <td class="text-end text-info"><strong>${calc.netto_awal ? formatBerat(calc.netto_awal) : '-'}</strong></td>
                <td class="text-center">${calc.persen_potongan > 0 ? calc.persen_potongan + '%' : '-'}</td>
                <td class="text-end text-warning"><strong>${calc.netto_akhir ? formatBerat(calc.netto_akhir) : '-'}</strong></td>
                <td class="text-end">${calc.harga_per_kg > 0 ? formatRupiah(calc.harga_per_kg) : '-'}</td>
                <td class="text-end">${calc.total_harga_bruto > 0 ? formatRupiah(calc.total_harga_bruto) : '-'}</td>
                <td class="text-end ${calc.total_potongan_rp > 0 ? 'text-danger' : ''}">${calc.total_potongan_rp > 0 ? '- ' + formatRupiah(calc.total_potongan_rp) : '-'}</td>
                <td class="text-end text-success"><strong>${calc.total_akhir > 0 ? formatRupiah(calc.total_akhir) : '-'}</strong></td>
                <td>${formatWaktu(row.waktu_timbangan1)}</td>
                <td>${formatWaktu(row.waktu_keluar)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-view btn-sm" onclick="viewDetail(${row.id})" title="Detail">
                        Detail
                    </button>
                    ${row.status === 'selesai' ? `<button class="btn btn-sm btn-outline-primary" onclick="cetakStruk('${row.no_tiket}')" title="Cetak Struk">Cetak</button>` : ''}
                </td>
            </tr>
        `;
        tbody.append(tr);
    });
}

// Cetak struk
function cetakStruk(noTiket) {
    window.open('print_ticket.php?no_tiket=' + encodeURIComponent(noTiket), '_blank');
}

// Get status badge
function getStatusBadge(status) {
    switch (status) {
        case 'timbang_1':
            return '<span class="badge-t1">T1 Selesai</span>';
        case 'timbang_2':
            return '<span class="badge-t2">T2 Selesai</span>';
        case 'selesai':
            return '<span class="badge-selesai">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge bg-danger">Dibatalkan</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Update summary
function updateSummary(summary) {
    if (!summary) return;
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
        success: function (response) {
            if (response.success) {
                showDetailModal(response.data);
            } else {
                showError('Gagal memuat detail');
            }
        },
        error: function () {
            showError('Terjadi kesalahan');
        }
    });
}

// Show detail modal - disamakan dengan struk
function showDetailModal(data) {
    const calc = hitungData(data);

    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-info">Data Identitas</h6>
                <table class="table table-sm table-dark">
                    <tr><td style="width:120px">Tanggal</td><td><strong>${data.tanggal || '-'}</strong></td></tr>
                    <tr><td>No. Tiket</td><td><strong>${data.no_tiket || '-'}</strong></td></tr>
                    <tr><td>No. Polisi</td><td><strong>${(data.no_polisi || '-').toUpperCase()}</strong></td></tr>
                    <tr><td>Supplier</td><td><strong>${(data.nama_supplier || '-').toUpperCase()}</strong></td></tr>
                    <tr><td>Nama Barang</td><td><span class="badge bg-primary">${data.jenis_material ? data.jenis_material.toUpperCase() : '-'}</span></td></tr>
                    <tr><td>Nama Supir</td><td><strong>${(data.nama_supir || '-').toUpperCase()}</strong></td></tr>
                    <tr><td>Keterangan</td><td>${data.keterangan || '-'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-info">Data Timbangan</h6>
                <table class="table table-sm table-dark">
                    <tr><td style="width:120px">Bruto</td><td class="text-end"><strong>${formatBerat(calc.bruto)} Kg</strong></td></tr>
                    <tr><td>Tara</td><td class="text-end"><strong>${formatBerat(calc.tara)} Kg</strong></td></tr>
                    <tr style="font-size:16px; font-weight:900"><td>Netto</td><td class="text-end text-info"><strong>${formatBerat(calc.netto_awal)} Kg</strong></td></tr>
                    <tr><td>Potongan ${calc.persen_potongan > 0 ? '(' + calc.persen_potongan + '%)' : ''}</td><td class="text-end">${formatBerat(calc.kg_potongan)} Kg</td></tr>
                    <tr><td>Netto Akhir</td><td class="text-end text-warning"><strong>${formatBerat(calc.netto_akhir)} Kg</strong></td></tr>
                    <tr><td>Harga / Kg</td><td class="text-end">${formatRupiah(calc.harga_per_kg)}</td></tr>
                    <tr><td>Waktu Masuk</td><td class="text-end">${formatWaktu(data.waktu_timbangan1)}</td></tr>
                    <tr><td>Waktu Keluar</td><td class="text-end">${formatWaktu(data.waktu_keluar)}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-info">Perhitungan Harga</h6>
                <div class="d-flex justify-content-between text-center" style="gap: 10px;">
                    <div class="flex-fill p-3 rounded" style="background: #1e3a8a; border: 2px solid #3b82f6;">
                        <div class="small text-uppercase" style="color: #60a5fa;">Total Awal</div>
                        <div class="fs-5 fw-bold" style="color: #60a5fa;">${formatRupiah(calc.total_harga_bruto)}</div>
                    </div>
                    <div class="flex-fill p-3 rounded" style="background: #78350f; border: 2px solid #f59e0b;">
                        <div class="small text-uppercase" style="color: #fbbf24;">Potong Hutang</div>
                        <div class="fs-5 fw-bold" style="color: #fbbf24;">${calc.total_potongan_rp > 0 ? '- ' + formatRupiah(calc.total_potongan_rp) : '-'}</div>
                    </div>
                    <div class="flex-fill p-3 rounded" style="background: #064e3b; border: 2px solid #10b981;">
                        <div class="small text-uppercase" style="color: #34d399;">Total Akhir</div>
                        <div class="fs-5 fw-bold" style="color: #34d399;">${formatRupiah(calc.total_akhir)}</div>
                    </div>
                </div>
            </div>
        </div>
        ${data.status === 'selesai' ? `
        <div class="row mt-3">
            <div class="col-12 text-center">
                <button class="btn btn-primary" onclick="cetakStruk('${data.no_tiket}')">
                    CETAK STRUK
                </button>
            </div>
        </div>` : ''}
    `;

    $('#detailContent').html(content);
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// Event handlers
$(document).ready(function () {
    // Initial load
    loadData();

    // Bind events
    $('#filterBtn').click(loadData);
    $('#resetBtn').click(function () {
        $('#filterDate').val(new Date().toISOString().split('T')[0]);
        $('#filterStatus, #filterMaterial, #filterSearch').val('');
        loadData();
    });

    $('#exportBtn').click(function () {
        const filterData = {
            action: 'export_data',
            date: $('#filterDate').val(),
            status: $('#filterStatus').val(),
            material: $('#filterMaterial').val(),
            search: $('#filterSearch').val()
        };

        window.open('ajax.php?' + $.param(filterData), '_blank');
    });

    // Auto refresh every 5 seconds
    setInterval(loadData, 5000);
});
