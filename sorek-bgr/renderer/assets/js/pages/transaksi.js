/**
 * Transaksi Index JS
 * Logic for exporting data and printing receipts
 */

function exportExcel() {
    // Get date filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const dateFilter = urlParams.get('date_filter') || 'today';
    const today = new Date();

    // Convert date filter to tanggal_awal and tanggal_akhir
    let tanggal_awal, tanggal_akhir;

    switch (dateFilter) {
        case 'today':
            tanggal_awal = tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            tanggal_awal = tanggal_akhir = yesterday.toISOString().split('T')[0];
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            tanggal_awal = weekAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'half_month':
            const halfMonthAgo = new Date(today);
            halfMonthAgo.setDate(today.getDate() - 15);
            tanggal_awal = halfMonthAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'month':
            tanggal_awal = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'half_year':
            const halfYearAgo = new Date(today);
            halfYearAgo.setMonth(today.getMonth() - 6);
            tanggal_awal = halfYearAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'year':
            tanggal_awal = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
        case 'custom_range':
            tanggal_awal = urlParams.get('start_date') || today.toISOString().split('T')[0];
            tanggal_akhir = urlParams.get('end_date') || today.toISOString().split('T')[0];
            break;
        default:
            tanggal_awal = tanggal_akhir = today.toISOString().split('T')[0];
    }

    window.open(`export_excel.php?tanggal_awal=${tanggal_awal}&tanggal_akhir=${tanggal_akhir}&status=selesai`, '_blank');
}

function exportPDF() {
    // Get date filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const dateFilter = urlParams.get('date_filter') || 'today';
    const customDate = urlParams.get('custom_date') || new Date().toISOString().split('T')[0];
    const year = urlParams.get('year') || new Date().getFullYear();
    const month = urlParams.get('month') || new Date().getMonth() + 1;

    // Convert date filter to tanggal_awal and tanggal_akhir
    let tanggal_awal, tanggal_akhir;

    switch (dateFilter) {
        case 'today':
            tanggal_awal = tanggal_akhir = new Date().toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            tanggal_awal = tanggal_akhir = yesterday.toISOString().split('T')[0];
            break;
        case 'week':
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            tanggal_awal = weekAgo.toISOString().split('T')[0];
            tanggal_akhir = new Date().toISOString().split('T')[0];
            break;
        case 'month':
            const thisMonth = new Date();
            tanggal_awal = new Date(thisMonth.getFullYear(), thisMonth.getMonth(), 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(thisMonth.getFullYear(), thisMonth.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'year':
            const thisYear = new Date();
            tanggal_awal = new Date(thisYear.getFullYear(), 0, 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(thisYear.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
        case 'custom':
            tanggal_awal = tanggal_akhir = customDate;
            break;
        case 'custom_month':
            const customYear = parseInt(year);
            const customMonth = parseInt(month);
            tanggal_awal = new Date(customYear, customMonth - 1, 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(customYear, customMonth, 0).toISOString().split('T')[0];
            break;
        default:
            tanggal_awal = tanggal_akhir = new Date().toISOString().split('T')[0];
    }

    window.open(`export_pdf.php?tanggal_awal=${tanggal_awal}&tanggal_akhir=${tanggal_akhir}&status=selesai`, '_blank');
}

function exportExcelAdvanced() {
    const urlParams = new URLSearchParams(window.location.search);
    const dateFilter = urlParams.get('date_filter') || 'today';
    const startInput = urlParams.get('start_date');
    const endInput = urlParams.get('end_date');

    let tanggal_awal, tanggal_akhir;
    const today = new Date();

    switch (dateFilter) {
        case 'today':
            tanggal_awal = tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            tanggal_awal = tanggal_akhir = yesterday.toISOString().split('T')[0];
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            tanggal_awal = weekAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'half_month':
            const halfMonthAgo = new Date(today);
            halfMonthAgo.setDate(today.getDate() - 15);
            tanggal_awal = halfMonthAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'month':
            tanggal_awal = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'half_year':
            const halfYearAgo = new Date(today);
            halfYearAgo.setMonth(today.getMonth() - 6);
            tanggal_awal = halfYearAgo.toISOString().split('T')[0];
            tanggal_akhir = today.toISOString().split('T')[0];
            break;
        case 'year':
            tanggal_awal = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            tanggal_akhir = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
        case 'custom_range':
            tanggal_awal = startInput || today.toISOString().split('T')[0];
            tanggal_akhir = endInput || today.toISOString().split('T')[0];
            break;
        default:
            tanggal_awal = tanggal_akhir = today.toISOString().split('T')[0];
    }

    window.open(`export_excel_advanced.php?tanggal_awal=${tanggal_awal}&tanggal_akhir=${tanggal_akhir}&status=selesai`, '_blank');
}

function printReport() {
    window.print();
}

function printReceipt(ticketNo) {
    Swal.fire({
        title: 'Cetak Struk',
        text: 'Cetak struk untuk tiket: ' + ticketNo,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#666',
        confirmButtonText: '🖨️ Cetak',
        cancelButtonText: 'Batal',
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Buka struk
            window.open('../timbangan/print_ticket.php?no_tiket=' + ticketNo, '_blank');
        }
    });
}

function cancelTransaction(id, ticketNo) {
    // Tampilkan dialog dengan 2 pilihan: Reset (timbang ulang) atau Batalkan Permanen
    Swal.fire({
        title: '⚠️ Penanganan Transaksi Salah',
        html: `
            <div style="text-align: left; font-size: 13px; line-height: 1.6;">
                <p>Tiket: <strong style="font-size:15px;">${ticketNo}</strong></p>
                <hr style="margin: 10px 0; border-color: rgba(255,255,255,0.15);">
                <p style="margin-bottom: 10px; color: #fbbf24;">Pilih tindakan yang ingin dilakukan:</p>

                <label style="display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; cursor:pointer; padding:10px; border-radius:8px; border:1px solid #22c55e; background:rgba(34,197,94,0.08);">
                    <input type="radio" name="cancelType" value="reset" checked style="margin-top:3px; accent-color:#22c55e;">
                    <div>
                        <strong style="color:#22c55e;">🔄 Reset & Timbang Ulang</strong><br>
                        <span style="color:#aaa;">Hapus data timbangan 2 yang salah. Tiket akan kembali ke antrian Timbangan 2 dan operator bisa memilih kendaraan yang <em>benar</em>.</span>
                    </div>
                </label>

                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:10px; border-radius:8px; border:1px solid #dc2626; background:rgba(220,38,38,0.08);">
                    <input type="radio" name="cancelType" value="permanent" style="margin-top:3px; accent-color:#dc2626;">
                    <div>
                        <strong style="color:#dc2626;">🚫 Batalkan Permanen</strong><br>
                        <span style="color:#aaa;">Tandai transaksi ini sebagai BATAL. Tiket tidak bisa diproses ulang. Gunakan jika transaksi memang tidak perlu diulang.</span>
                    </div>
                </label>

                <div style="margin-top:14px;">
                    <label style="font-size:12px; color:#aaa; margin-bottom:4px; display:block;">Alasan (wajib diisi):</label>
                    <textarea id="cancelReasonInput" class="swal2-textarea" placeholder="Contoh: Salah pilih kendaraan, harusnya Mobil A bukan Mobil B..." style="height:75px; font-size:13px; margin:0; width:100%;"></textarea>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#666',
        confirmButtonText: 'Lanjutkan',
        cancelButtonText: 'Tidak Jadi',
        showCloseButton: false,
        allowOutsideClick: false,
        preConfirm: () => {
            const reason = document.getElementById('cancelReasonInput').value.trim();
            const type = document.querySelector('input[name="cancelType"]:checked')?.value || 'permanent';
            if (!reason) {
                Swal.showValidationMessage('Alasan wajib diisi!');
                return false;
            }
            return { reason, type };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        const { reason, type } = result.value;
        const isReset = type === 'reset';

        // Konfirmasi akhir
        Swal.fire({
            title: isReset ? '🔄 Konfirmasi Reset' : '🚫 Konfirmasi Batalkan Permanen',
            html: isReset
                ? `<p>Tiket <strong>${ticketNo}</strong> akan di-reset.</p><p style="color:#22c55e; margin-top:8px;">Data timbangan 2 dihapus dan tiket kembali ke antrian untuk diproses ulang.</p>`
                : `<p>Tiket <strong>${ticketNo}</strong> akan dibatalkan <strong style="color:#dc2626;">permanen</strong>.</p><p style="color:#f59e0b; margin-top:8px;">Tindakan ini tidak bisa dibalik.</p>`,
            icon: isReset ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isReset ? '#22c55e' : '#dc2626',
            cancelButtonColor: '#666',
            confirmButtonText: isReset ? 'Ya, Reset Sekarang' : 'Ya, Batalkan Permanen',
            cancelButtonText: 'Kembali'
        }).then((confirm) => {
            if (!confirm.isConfirmed) return;

            const bodyParams = new URLSearchParams({
                action: 'cancel',
                id: id,
                cancel_reason: reason,
                reset_for_redo: isReset ? '1' : '0'
            });

            fetch('../timbangan/proses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyParams.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: isReset ? '✅ Berhasil Direset!' : '✅ Berhasil Dibatalkan!',
                        html: isReset
                            ? `<p>Tiket <strong>${ticketNo}</strong> sudah direset.</p><p style="margin-top:8px; color:#22c55e;">Operator dapat membuka halaman <strong>Timbangan 2</strong> dan memilih tiket ini kembali untuk menginput data kendaraan yang benar.</p>`
                            : `<p>Transaksi <strong>${ticketNo}</strong> berhasil dibatalkan.</p>`,
                        icon: 'success',
                        confirmButtonColor: '#059669'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message || 'Terjadi kesalahan. Mungkin batas waktu 30 menit sudah lewat, hubungi admin.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Tidak bisa terhubung ke server', 'error');
            });
        });
    });
}
