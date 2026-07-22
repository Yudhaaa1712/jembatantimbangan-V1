/**
 * Timbangan 1 JS - Optimized
 * Logic for serial connection, weight capture, and form validation
 * Cleaned up version - removed excessive logging for better performance
 */

// ===================================================
// GLOBAL VARIABLES
// ===================================================
let serialConnector = null;
let currentWeight = 0;
let lastWeightUpdate = 0;
let weightInterval = null;
let isCaptured = false;

// ===================================================
// SERIAL CONNECTOR LOGIC
// ===================================================
async function initializeAutoSerialConnector() {
    if (!window.AutoSerialConnector) {
        console.warn('AutoSerialConnector not available');
        return false;
    }

    const targetEl = document.getElementById('beratInputForm');
    if (!targetEl) {
        console.warn('Target element beratInputForm not found');
        return false;
    }

    serialConnector = new AutoSerialConnector({
        targetInputId: 'beratInputForm',
        maxReconnectAttempts: 10,
        reconnectInterval: 3000,
        baudRate: 9600,
        onConnect: () => {
            updateConnectionUI(true);
            showNotification('Terhubung ke indikator', 'success');
        },
        onDisconnect: () => {
            updateConnectionUI(false);
        },
        onData: (weight) => {
            currentWeight = weight;
            lastWeightUpdate = Date.now();
            updateWeightDisplayAutoSerial(weight);
        },
        onError: (error) => {
            console.warn('Serial error:', error.message);
            updateConnectionUI(false);
        }
    });

    // Register for cleanup
    if (window.registerSerialConnector) {
        window.registerSerialConnector(serialConnector);
    }

    // Auto-connect attempt
    const autoConnected = await serialConnector.autoConnect({
        skipValidation: true,
        maxRetries: 3,
        forceScanAll: true,
        autoPrompt: false // Disabled popup
    });

    return autoConnected;
}

function updateWeightDisplayAutoSerial(weight) {
    const weightDisplay = document.getElementById('weightDisplay');
    const weightStatus = document.getElementById('weightStatus');

    if (weightDisplay) {
        if (window.isWeightLocked) {
            // Saat terkunci: tampilkan berat yang diambil (ukuran sama dengan status) + live kecil
            weightDisplay.innerHTML = `<span style="font-size: 16px; color: #ef4444; font-weight: bold;">Berat diambil: ${window.capturedWeight.toLocaleString('id-ID')} Kg</span><br><small style="color: #fbbf24; font-size: 14px;">(Live: ${Math.floor(weight).toLocaleString('id-ID')} Kg)</small>`;
        } else {
            weightDisplay.innerHTML = Math.floor(weight).toLocaleString('id-ID') + ' Kg';
            weightDisplay.style.color = '';
        }
    }

    if (weightStatus) {
        if (window.isWeightLocked) {
            weightStatus.textContent = 'Berat terkunci';
            weightStatus.className = 'display-status text-warning';
        } else if (weight > 0) {
            weightStatus.textContent = 'Data diterima';
            weightStatus.className = 'display-status text-success';
        } else {
            weightStatus.textContent = 'Terhubung';
            weightStatus.className = 'display-status';
        }
    }
}

function updateConnectionUI(connected) {
    const weightStatus = document.getElementById('weightStatus');
    const toggleBtn = document.getElementById('toggleConnection');
    const beratInputForm = document.getElementById('beratInputForm');

    if (connected) {
        if (weightStatus) {
            weightStatus.textContent = 'Terhubung ke Indikator';
            weightStatus.className = 'display-status text-success';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'PUTUSKAN';
            toggleBtn.className = 'btn btn-danger w-100 mt-3';
        }
        // Indikator terhubung = input manual tidak bisa
        if (beratInputForm) {
            beratInputForm.readOnly = true;
            beratInputForm.style.cursor = 'not-allowed';
            beratInputForm.placeholder = 'Dari indikator';
        }
    } else {
        if (weightStatus) {
            weightStatus.textContent = 'Tidak Terhubung';
            weightStatus.className = 'display-status text-danger';
        }
        if (toggleBtn) {
            toggleBtn.innerHTML = 'SAMBUNGKAN';
            toggleBtn.className = 'btn btn-info w-100 mt-3';
        }
        // Indikator terputus = bisa input manual
        if (beratInputForm && !isCaptured) {
            beratInputForm.readOnly = false;
            beratInputForm.style.cursor = 'text';
            beratInputForm.placeholder = 'Ketik manual';
        }
    }
}

function showNotification(message, type) {
    const icon = type === 'success' ? 'success' :
        type === 'error' ? 'error' :
            type === 'info' ? 'info' : 'warning';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: message,
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
    }
}

// ===================================================
// UTIL FUNCTIONS
// ===================================================
function initNoKendaraanPrefix() {
    const noKendaraanInput = document.getElementById('noKendaraanInput');

    if (!noKendaraanInput) return;

    // Auto uppercase saja, tanpa hardcode prefix
    noKendaraanInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
}

// ===================================================
// SERIAL SETTINGS UI HANDLERS
// ===================================================
function updateBaudRateDisplay() {
    const savedBaud = localStorage.getItem('serialBaudRate') || '9600';
    const currentBaudEl = document.getElementById('currentBaudRate');
    if (currentBaudEl) {
        currentBaudEl.textContent = savedBaud + ' baud';
    }
}

function updatePortInfoDisplay() {
    const portInfoEl = document.getElementById('portInfoDisplay');
    if (!portInfoEl) return;

    try {
        const savedPort = localStorage.getItem('serialPortInfo');
        if (savedPort) {
            const portInfo = JSON.parse(savedPort);
            const isUsb = portInfo.usbVendorId !== undefined && portInfo.usbVendorId !== null;
            portInfoEl.innerHTML = `
                <div class="text-success">
                    <strong>Port Terpilih</strong><br>
                    <small>${isUsb ? `VendorID: ${portInfo.usbVendorId || 'N/A'}, ProductID: ${portInfo.usbProductId || 'N/A'}` : 'Physical COM Port (Motherboard)'}</small>
                </div>
            `;
            return;
        }
    } catch (e) { }

    portInfoEl.innerHTML = '<span class="text-muted">Belum ada port dipilih</span>';
}

function applyPreset(presetType) {
    const baudSelect = document.getElementById('baudRateSelect');
    if (presetType === 'sonic') {
        baudSelect.value = '4800';
    } else if (presetType === 'standard') {
        baudSelect.value = '9600';
    }
    document.getElementById('currentBaudDisplay').textContent = baudSelect.value + ' baud';
}

// ===================================================
// DOCUMENT READY & EVENT LISTENERS
// ===================================================
document.addEventListener('DOMContentLoaded', async function () {
    // Init Auto Serial with delay
    setTimeout(async function () {
        try {
            await initializeAutoSerialConnector();
        } catch (error) {
            console.warn('Serial init failed:', error.message);
        }
    }, 1000);

    // Form Init
    const materialSelect = document.querySelector('select[name="material"]');
    if (materialSelect && !materialSelect.value) {
        materialSelect.value = 'tbs';
    }

    initNoKendaraanPrefix();

    const supplierInput = document.getElementById('supplierInput');
    if (supplierInput) {
        supplierInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    }

    updateBaudRateDisplay();

    // === EVENT LISTENERS FOR SERIAL SETTINGS ===

    // Select Port
    document.getElementById('selectPortBtn')?.addEventListener('click', async function () {
        const btn = this;
        const originalHTML = btn.innerHTML;
        try {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
            btn.disabled = true;
            const port = await navigator.serial.requestPort();
            const portInfo = port.getInfo();
            localStorage.setItem('serialPortInfo', JSON.stringify(portInfo));
            updatePortInfoDisplay();
            showNotification('Port dipilih! Klik Simpan & Reconnect', 'success');
        } catch (error) {
            if (error.name !== 'NotFoundError') {
                showNotification('Error: ' + error.message, 'error');
            }
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });

    // Refresh Port Info
    document.getElementById('refreshPortInfoBtn')?.addEventListener('click', function () {
        updatePortInfoDisplay();
    });

    // Open Settings Modal
    document.getElementById('openSerialSettings')?.addEventListener('click', function () {
        const modal = new bootstrap.Modal(document.getElementById('serialSettingsModal'));
        const savedBaud = localStorage.getItem('serialBaudRate') || '9600';
        document.getElementById('baudRateSelect').value = savedBaud;
        document.getElementById('currentBaudDisplay').textContent = savedBaud + ' baud';
        updatePortInfoDisplay();

        const connStatusEl = document.getElementById('currentConnStatus');
        if (serialConnector && serialConnector.isConnected) {
            connStatusEl.innerHTML = '<span class="text-success">✓ Terhubung</span>';
        } else {
            connStatusEl.innerHTML = '<span class="text-danger">✗ Terputus</span>';
        }
        modal.show();
    });

    // Save Serial Settings
    document.getElementById('saveSerialSettings')?.addEventListener('click', async function () {
        const selectedBaud = parseInt(document.getElementById('baudRateSelect').value);
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
        this.disabled = true;

        try {
            localStorage.setItem('serialBaudRate', selectedBaud.toString());
            if (serialConnector) {
                serialConnector.updateBaudRate(selectedBaud);
                if (serialConnector.isConnected) {
                    await serialConnector.disconnect();
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
                await serialConnector.autoConnect();
            }
            updateBaudRateDisplay();
            bootstrap.Modal.getInstance(document.getElementById('serialSettingsModal')).hide();
            showNotification('Setting disimpan!', 'success');
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        } finally {
            this.innerHTML = 'Simpan & Reconnect';
            this.disabled = false;
        }
    });

    // === HARD RESET BTN ===
    document.getElementById('hardResetSerial')?.addEventListener('click', async function () {
        if (confirm('Reset semua pengaturan serial?\nHalaman akan di-refresh.')) {
            try {
                localStorage.removeItem('serialPortInfo');
                localStorage.removeItem('serialBaudRate');
                if (serialConnector) {
                    await serialConnector.forceCleanup();
                }
            } catch (error) { }
            window.location.reload();
        }
    });

    // === INPUT HARGA RUPIAH ===
    const hargaInput = document.getElementById('hargaInput');
    if (hargaInput) {
        hargaInput.addEventListener('input', function (e) {
            let numValue = parseRupiah(e.target.value);
            document.getElementById('hargaHidden').value = numValue;
        });

        hargaInput.addEventListener('blur', function (e) {
            let numValue = parseRupiah(e.target.value);
            if (numValue > 0) {
                e.target.value = formatRupiahInput(numValue);
            } else {
                e.target.value = '';
            }
            document.getElementById('hargaHidden').value = numValue;
        });

        hargaInput.addEventListener('focus', function (e) {
            let numValue = parseRupiah(e.target.value);
            if (numValue > 0) {
                e.target.value = numValue;
            }
        });
    }

    // === CAPTURE WEIGHT ===
    document.getElementById('captureWeight')?.addEventListener('click', function () {
        const weightDisplay = document.getElementById('weightDisplay');
        const beratInputForm = document.getElementById('beratInputForm');

        if (!weightDisplay || !beratInputForm) return;

        let isConnected = serialConnector && serialConnector.isConnected;
        let weightToCapture = 0;

        if (isConnected) {
            // Mode indikator: ambil dari timbangan
            if ((Date.now() - lastWeightUpdate) > 5000) {
                showNotification('Data timbangan terlalu lama', 'warning');
                return;
            }
            if (currentWeight <= 0) {
                showNotification('Berat tidak valid', 'warning');
                return;
            }
            weightToCapture = Math.floor(currentWeight);
        } else {
            // Mode manual: ambil dari input form
            weightToCapture = parseInt(beratInputForm.value) || 0;
            if (weightToCapture <= 0) {
                showNotification('Masukkan berat manual terlebih dahulu', 'warning');
                return;
            }
        }

        // LOCK WEIGHT
        window.capturedWeight = weightToCapture;
        isCaptured = true;
        beratInputForm.value = weightToCapture;
        beratInputForm.readOnly = true;
        beratInputForm.style.cursor = 'not-allowed';

        this.innerHTML = 'TERKUNCI!';
        this.classList.remove('btn-warning');
        this.classList.add('btn-success');
        this.disabled = true;

        window.isWeightLocked = true;
        showNotification(`Berat ${weightToCapture.toLocaleString('id-ID')} Kg dikunci!`, 'success');
    });

    // === TOGGLE CONNECTION ===
    document.getElementById('toggleConnection')?.addEventListener('click', async function () {
        if (serialConnector) {
            if (serialConnector.isConnected) {
                await serialConnector.disconnect();
                updateConnectionUI(false);
                showNotification('Terputus dari indikator', 'info');
            } else {
                const success = await serialConnector.manualConnect();
                if (success) {
                    updateConnectionUI(true);
                    showNotification('Terhubung ke indikator', 'success');
                } else {
                    showNotification('Gagal menghubungkan', 'error');
                }
            }
        }
    });

    // === FORM SUBMIT ===
    document.getElementById('timbangan1Form')?.addEventListener('submit', function (e) {
        const beratInputForm = document.getElementById('beratInputForm');
        if (!beratInputForm) return;

        const berat = beratInputForm.value;
        if (berat === '0' || berat === '') {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Masukkan berat terlebih dahulu!' });
            return;
        }

        // Validate Material
        const formData = new FormData(this);
        const material = formData.get('material');
        if (!material) {
            const materialSelect = document.querySelector('select[name="material"]');
            if (materialSelect) materialSelect.value = 'tbs';
        }

        e.preventDefault();
        const noKendaraan = formData.get('no_kendaraan') || '-';
        const namaPengemudi = formData.get('nama_pengemudi') || '-';
        const namaSuplier = formData.get('nama_suplier') || '-';
        const keterangan = formData.get('keterangan') || '-';
        const hargaRaw = formData.get('harga') || 0;
        const harga = parseInt(hargaRaw) || 0;
        const beratFloat = parseFloat(berat);

        Swal.fire({
            title: 'Simpan Data Timbangan 1?',
            html: `
                <div style="text-align: left;">
                    <p><strong>No. Kendaraan:</strong> ${noKendaraan}</p>
                    <p><strong>Pengemudi:</strong> ${namaPengemudi}</p>
                    <p><strong>Suplier:</strong> ${namaSuplier}</p>
                    <p><strong>Material:</strong> ${material}</p>
                    ${harga > 0 ? `<p><strong>Harga/Kg:</strong> Rp ${harga.toLocaleString('id-ID')}</p>` : ''}
                    ${keterangan !== '-' ? `<p><strong>Keterangan:</strong> ${keterangan}</p>` : ''}
                    <hr>
                    <p><strong>Berat:</strong> <span style="color: #22c55e; font-size: 1.2em;">${beratFloat.toLocaleString('id-ID')} Kg</span></p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });


});

// Cleanup on page unload
window.addEventListener('beforeunload', async function () {
    if (serialConnector) {
        try {
            await serialConnector.forceCleanup();
        } catch (e) { }
    }
});
