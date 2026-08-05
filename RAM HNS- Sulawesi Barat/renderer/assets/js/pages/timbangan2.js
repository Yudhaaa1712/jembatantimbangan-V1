/**
 * Timbangan 2 JS - Optimized
 * Logic for serial connection, auto calculations, and ticket processing
 * Cleaned up version - removed excessive logging for better performance
 */

// ===================================================
// GLOBAL VARIABLES
// ===================================================
let serialConnector2 = null;
let currentWeight2 = 0;
let lastWeightUpdate2 = 0;
let isCaptured2 = false;

// ===================================================
// SERIAL CONNECTOR LOGIC
// ===================================================
async function initializeAutoSerialConnector2() {
    if (!window.AutoSerialConnector) {
        console.warn('AutoSerialConnector not available');
        return false;
    }

    if (!serialConnector2) {
        serialConnector2 = new AutoSerialConnector({
            targetInputId: 'beratInput2',
            maxReconnectAttempts: 10,
            reconnectInterval: 3000,
            baudRate: 9600,
            onConnect: () => {
                updateConnectionUI2(true);
                showNotification2('Terhubung ke indikator', 'success');
            },
            onDisconnect: () => {
                updateConnectionUI2(false);
            },
            onData: (weight) => {
                currentWeight2 = weight;
                lastWeightUpdate2 = Date.now();
                updateWeightDisplayAutoSerial2(weight);
            },
            onError: (error) => {
                console.warn('Serial Error (T2):', error.message);
                updateConnectionUI2(false);
            }
        });

        // Register for global cleanup
        if (window.registerSerialConnector) {
            window.registerSerialConnector(serialConnector2);
        }
    }

    // Auto-connect with disabled popup
    const autoConnected = await serialConnector2.autoConnect({
        skipValidation: true,
        maxRetries: 5,
        forceScanAll: true,
        autoPrompt: false // Disabled popup
    });

    return autoConnected;
}

function updateWeightDisplayAutoSerial2(weight) {
    const weightDisplay2Large = document.getElementById('weightDisplay2Large');
    const weightStatus2 = document.getElementById('weightStatus2');

    if (weightDisplay2Large) {
        if (window.isWeightLocked2) {
            // Saat terkunci: tampilkan berat yang diambil (ukuran sama dengan status) + live kecil
            weightDisplay2Large.innerHTML = `<span style="font-size: 16px; color: #ef4444; font-weight: bold;">Berat diambil: ${window.capturedWeight2.toLocaleString('id-ID')} Kg</span><br><small style="font-size: 14px; color: #ffc107;">(Live: ${Math.floor(weight).toLocaleString('id-ID')} Kg)</small>`;
        } else {
            weightDisplay2Large.innerHTML = Math.floor(weight).toLocaleString('id-ID') + ' KG';
            weightDisplay2Large.style.color = '';
        }
    }

    if (weightStatus2) {
        if (window.isWeightLocked2) {
            weightStatus2.textContent = 'Berat terkunci';
        } else if (weight > 0) {
            weightStatus2.textContent = 'Data diterima';
        } else {
            weightStatus2.textContent = 'Terhubung';
        }
    }
}

function updateConnectionUI2(connected) {
    const weightStatus2 = document.getElementById('weightStatus2');
    const toggleBtn2 = document.getElementById('toggleConnection2');
    const beratInputForm2 = document.getElementById('beratInputForm2');

    if (connected) {
        if (weightStatus2) weightStatus2.textContent = 'Terhubung ke Indikator';
        if (toggleBtn2) {
            toggleBtn2.innerHTML = '<i class="bi bi-plug-fill"></i> PUTUSKAN';
            toggleBtn2.className = 'terminal-btn';
            toggleBtn2.style.background = '#dc2626';
        }
        // Indikator terhubung = input manual tidak bisa
        if (beratInputForm2 && !isCaptured2) {
            beratInputForm2.readOnly = true;
            beratInputForm2.style.cursor = 'not-allowed';
            beratInputForm2.placeholder = 'Dari indikator';
        }
    } else {
        if (weightStatus2) weightStatus2.textContent = 'Menunggu...';
        if (toggleBtn2) {
            toggleBtn2.innerHTML = '<i class="bi bi-plug-fill"></i> SAMBUNGKAN';
            toggleBtn2.className = 'terminal-btn terminal-btn-info';
            toggleBtn2.style.background = '';
        }
        // Indikator terputus = bisa input manual
        if (beratInputForm2 && !isCaptured2) {
            beratInputForm2.readOnly = false;
            beratInputForm2.style.cursor = 'text';
            beratInputForm2.placeholder = 'Ketik manual';
        }
    }
}

function showNotification2(message, type) {
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
function formatRupiah(angka) {
    if (!angka && angka !== 0) return 'Rp 0';
    if (typeof angka === 'string') {
        if (angka.includes('Rp')) return angka;
        angka = parseFloat(angka);
    }
    const number = Math.floor(angka);
    return 'Rp ' + number.toLocaleString('id-ID');
}

function parseRupiah(rupiahString) {
    if (!rupiahString) return 0;
    const cleanString = rupiahString.toString().replace(/[^0-9]/g, '');
    return parseFloat(cleanString) || 0;
}

window.formatRupiahInput = function (elm) {
    let value = elm.value.replace(/[^0-9]/g, '');
    if (!value) {
        elm.value = '';
        hitungOtomatis();
        return;
    }
    let number = parseInt(value, 10);
    elm.value = 'Rp ' + number.toLocaleString('id-ID');
    hitungOtomatis();
};

function formatBerat(val) {
    return val.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function resetHasilPerhitungan() {
    const ids = [
        'hasilBruto', 'hasilTara', 'hasilNettoBT', 'hasilPotongan',
        'hasilNettoAkhir', 'hasilTotalHarga', 'hasilTotalHargaFinal', 'hasilSisaBayar'
    ];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '0';
    });
}

// ===================================================
// CALCULATION LOGIC
// ===================================================
function hitungOtomatis() {
    // Get values
    const bruto = parseFloat(document.getElementById('displayBerat1')?.value?.replace(/[^0-9]/g, '')) || 0;
    const tara = parseFloat(document.getElementById('beratInput2')?.value) || 0;
    const persenPotongan = parseFloat(document.getElementById('persenPotongan')?.value) || 0;

    // Check if harga input T2 is filled
    const hargaFromDisplayHarga = parseRupiah(document.getElementById('displayHarga')?.value || '0');
    const hargaPerKg = hargaFromDisplayHarga;

    // Get potongan hutang (Rp) - baca dari hidden input yang sudah angka bersih
    const potonganHutang = parseFloat(document.getElementById('potonganHutangHidden')?.value) || 0;
    const uangPanen = parseRupiah(document.getElementById('inputUangPanen')?.value || '0');
    const manual1 = parseRupiah(document.getElementById('inputManual1')?.value || '0');
    const manual2 = parseRupiah(document.getElementById('inputManual2')?.value || '0');

    // Calculate
    const nettoBT = bruto - tara;

    // Validasi: netto tidak boleh negatif
    if (bruto > 0 && tara > 0 && nettoBT < 0) {
        const warningEl = document.getElementById('hasilNettoBT');
        if (warningEl) {
            warningEl.textContent = 'TIDAK VALID!';
            warningEl.style.color = '#ef4444';
        }
        const saveBtn = document.getElementById('saveButton');
        if (saveBtn) saveBtn.disabled = true;
        return;
    }

    // Potongan kg dibulatkan ke kelipatan 10 terdekat (mis. 23 -> 20, 25 -> 30)
    const potonganKg = Math.round(nettoBT * (persenPotongan / 100) / 10) * 10;
    const nettoAkhir = Math.round(nettoBT - potonganKg); // Dibulatkan
    const totalHarga = nettoAkhir * hargaPerKg; // Dihitung dari netto yang sudah bulat
    const totalPotonganRp = uangPanen + manual1 + manual2 + potonganHutang;
    const sisaBayar = totalHarga - totalPotonganRp;

    // Re-enable save button if valid
    const tiketSelector = document.getElementById('tiketSelector');
    if (tiketSelector && tiketSelector.value) {
        const saveBtn = document.getElementById('saveButton');
        if (saveBtn) saveBtn.disabled = false;
    }

    // Update displays
    const elements = {
        hasilBruto: document.getElementById('hasilBruto'),
        hasilTara: document.getElementById('hasilTara'),
        hasilNettoBT: document.getElementById('hasilNettoBT'),
        hasilNettoAkhir: document.getElementById('hasilNettoAkhir'),
        hasilHargaPerKg: document.getElementById('hasilHargaPerKg'),
        hasilTotalPotongan: document.getElementById('hasilTotalPotongan'),
        hasilTotalHargaFinal: document.getElementById('hasilTotalHargaFinal')
    };

    if (elements.hasilBruto) elements.hasilBruto.textContent = formatBerat(bruto) + ' Kg';
    if (elements.hasilTara) elements.hasilTara.textContent = formatBerat(tara) + ' Kg';
    if (elements.hasilNettoBT) elements.hasilNettoBT.textContent = formatBerat(nettoBT) + ' Kg';

    const hasilPotonganPersen = document.getElementById('hasilPotonganPersen');
    const hasilPotonganKg = document.getElementById('hasilPotongan');

    if (hasilPotonganPersen) hasilPotonganPersen.textContent = persenPotongan + '%';
    if (hasilPotonganKg) hasilPotonganKg.textContent = '= ' + formatBerat(potonganKg) + ' Kg';
    if (elements.hasilNettoAkhir) elements.hasilNettoAkhir.textContent = formatBerat(nettoAkhir) + ' Kg';

    if (elements.hasilHargaPerKg) elements.hasilHargaPerKg.textContent = formatRupiah(hargaPerKg);
    if (elements.hasilTotalPotongan) elements.hasilTotalPotongan.textContent = formatRupiah(Math.round(totalPotonganRp));

    if (elements.hasilTotalHargaFinal) {
        elements.hasilTotalHargaFinal.textContent = formatRupiah(Math.round(sisaBayar));
        elements.hasilTotalHargaFinal.style.color = '#34d399';
    }
}

// ===================================================
// DOCUMENT READY & EVENT LISTENERS
// ===================================================
document.addEventListener('DOMContentLoaded', async function () {
    // Init Serial with delay
    setTimeout(async function () {
        try {
            await initializeAutoSerialConnector2();
        } catch (error) {
            console.warn('T2 Serial init failed:', error.message);
        }
    }, 1000);

    // === TIKET SELECTOR LOGIC ===
    const tiketSelector = document.getElementById('tiketSelector');
    if (tiketSelector) {
        tiketSelector.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];

            if (this.value) {
                const displayKendaraan = document.getElementById('displayKendaraan');
                const displayPengemudi = document.getElementById('displayPengemudi');
                const displaySuplier = document.getElementById('displaySuplier');
                const displayMaterial = document.getElementById('displayMaterial');
                const displayHarga = document.getElementById('displayHarga');
                const displayBerat1 = document.getElementById('displayBerat1');
                const saveButton = document.getElementById('saveButton');

                if (displayKendaraan) displayKendaraan.value = selectedOption.dataset.kendaraan || '';
                if (displayPengemudi) displayPengemudi.value = selectedOption.dataset.pengemudi || '';
                if (displaySuplier) displaySuplier.value = selectedOption.dataset.suplier || '';

                const keteranganInput = document.querySelector('input[name="keterangan"]');
                if (keteranganInput) {
                    keteranganInput.value = selectedOption.dataset.keterangan || '';
                }

                // Material Code Mapping
                const materialCode = selectedOption.dataset.material;
                const materialName = (typeof materialCodes !== 'undefined' && materialCodes[materialCode]) ? materialCodes[materialCode] : materialCode;
                if (displayMaterial) displayMaterial.value = materialName;

                const harga = parseInt(selectedOption.dataset.harga) || 0;
                const berat = parseInt(selectedOption.dataset.berat) || 0;

                if (displayHarga) {
                    displayHarga.value = 'Rp ' + harga.toLocaleString('id-ID');
                    // Update hidden input
                    const hargaHiddenT2 = document.getElementById('hargaHiddenT2');
                    if (hargaHiddenT2) hargaHiddenT2.value = harga;

                    if (harga === 0) {
                        displayHarga.style.backgroundColor = '#7f1d1d';
                        displayHarga.style.color = '#fca5a5';
                        displayHarga.style.border = '2px solid #dc2626';
                        showNotification2('⚠️ Harga belum diisi!', 'warning');
                    } else {
                        displayHarga.style.backgroundColor = '';
                        displayHarga.style.color = '';
                        displayHarga.style.border = '';
                    }
                }

                if (displayBerat1) displayBerat1.value = berat.toLocaleString('id-ID') + ' Kg';
                if (saveButton) saveButton.disabled = false;

                // Handle Berat Tara from DB
                const beratTaraFromDB = parseInt(selectedOption.dataset.beratTara) || 0;
                const beratInput2 = document.getElementById('beratInput2');
                const beratInputForm2 = document.getElementById('beratInputForm2');
                const captureBtn = document.getElementById('captureWeight2');
                const weightDisplay2Large = document.getElementById('weightDisplay2Large');

                isCaptured2 = false;

                if (beratTaraFromDB > 0) {
                    if (beratInput2) beratInput2.value = beratTaraFromDB;
                    if (beratInputForm2) beratInputForm2.value = beratTaraFromDB;
                    isCaptured2 = true;
                    window.isWeightLocked2 = true;
                    window.capturedWeight2 = beratTaraFromDB;

                    if (captureBtn) {
                        captureBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> BERAT TERISI';
                        captureBtn.disabled = true;
                    }
                    if (weightDisplay2Large) {
                        weightDisplay2Large.classList.add('captured');
                        weightDisplay2Large.innerHTML = beratTaraFromDB.toLocaleString('id-ID') + ' KG<br><small style="color: #10b981;">(Data Database)</small>';
                    }
                } else {
                    window.isWeightLocked2 = false;
                    window.capturedWeight2 = 0;
                    if (beratInput2) beratInput2.value = '0';
                    if (beratInputForm2) beratInputForm2.value = '0';
                    if (captureBtn) {
                        captureBtn.innerHTML = '<i class="bi bi-camera-fill"></i> CAPTURE';
                        captureBtn.disabled = false;
                    }
                    if (weightDisplay2Large) {
                        weightDisplay2Large.classList.remove('captured');
                        weightDisplay2Large.innerHTML = '0 KG';
                    }
                }

                hitungOtomatis();
            } else {
                // Clear selection
                const fields = ['displayKendaraan', 'displayPengemudi', 'displaySuplier', 'displayMaterial', 'displayHarga', 'displayBerat1'];
                fields.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                const saveButton = document.getElementById('saveButton');
                if (saveButton) saveButton.disabled = true;
                resetHasilPerhitungan();
            }
        });
    }

    // Refresh Btn
    document.getElementById('refreshTiketBtn')?.addEventListener('click', function () {
        showNotification2('Memuat ulang...', 'info');
        setTimeout(() => location.reload(), 500);
    });

    // Toggle Connection
    document.getElementById('toggleConnection2')?.addEventListener('click', async function () {
        if (serialConnector2) {
            if (serialConnector2.isConnected) {
                await serialConnector2.disconnect();
                updateConnectionUI2(false);
                showNotification2('Terputus dari indikator', 'info');
            } else {
                const success = await serialConnector2.manualConnect();
                if (success) {
                    updateConnectionUI2(true);
                    showNotification2('Terhubung ke indikator', 'success');
                } else {
                    showNotification2('Gagal menghubungkan', 'error');
                }
            }
        }
    });

    // Capture Weight
    document.getElementById('captureWeight2')?.addEventListener('click', function () {
        const weightDisplay2Large = document.getElementById('weightDisplay2Large');
        const beratInput2 = document.getElementById('beratInput2');
        const beratInputForm2 = document.getElementById('beratInputForm2');

        if (!weightDisplay2Large || !beratInput2) return;

        const displayText = weightDisplay2Large.textContent;
        // Remove thousand separator (.) and non-numeric except minus for negative
        // Indonesian format: 2.000 means 2000, so we remove dots first, then parse
        const cleanedText = displayText.replace(/[^\d.-]/g, '').replace(/\./g, '');
        const weight = parseFloat(cleanedText) || 0;

        if (weight <= 0) {
            showNotification2('Berat harus lebih dari 0 kg', 'warning');
            return;
        }

        beratInput2.value = weight;
        if (beratInputForm2) beratInputForm2.value = weight;

        window.isWeightLocked2 = true;
        window.capturedWeight2 = weight;

        const captureBtn = document.getElementById('captureWeight2');
        if (captureBtn) {
            captureBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> BERAT TERAMBIL';
            captureBtn.disabled = true;
            captureBtn.style.background = '#10b981';
        }

        hitungOtomatis();
        showNotification2('Berat berhasil di-capture', 'success');
    });

    // Manual Input Mode - hanya aktif saat indikator tidak terhubung
    const beratInputForm2El = document.getElementById('beratInputForm2');
    if (beratInputForm2El) {
        beratInputForm2El.addEventListener('input', function () {
            // Cek apakah indikator terhubung
            const isConnected = serialConnector2 && serialConnector2.isConnected;
            if (isConnected) {
                // Indikator terhubung, jangan izinkan input manual
                showNotification2('Input manual tidak tersedia saat indikator terhubung', 'warning');
                return;
            }

            const beratInput2 = document.getElementById('beratInput2');
            const manualWeight = parseFloat(this.value) || 0;

            if (beratInput2) beratInput2.value = manualWeight;
            currentWeight2 = manualWeight;
            lastWeightUpdate2 = Date.now();

            if (manualWeight > 0) {
                const weightDisplay2Large = document.getElementById('weightDisplay2Large');
                if (weightDisplay2Large) {
                    weightDisplay2Large.textContent = manualWeight.toLocaleString('id-ID') + ' KG';
                    weightDisplay2Large.style.color = '#ffc107';
                }
            }
            hitungOtomatis();
        });
    }

    // Auto Calc Triggers
    document.getElementById('persenPotongan')?.addEventListener('input', hitungOtomatis);

    // Event listener untuk input harga di T2
    const displayHarga = document.getElementById('displayHarga');
    if (displayHarga) {
        displayHarga.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                let number = parseInt(value, 10);
                this.value = 'Rp ' + number.toLocaleString('id-ID');
                // Update hidden input
                const hargaHiddenT2 = document.getElementById('hargaHiddenT2');
                if (hargaHiddenT2) hargaHiddenT2.value = number;
            } else {
                this.value = '';
                const hargaHiddenT2 = document.getElementById('hargaHiddenT2');
                if (hargaHiddenT2) hargaHiddenT2.value = 0;
            }
            hitungOtomatis();
        });
    }

    const hargaInputT2Display = document.getElementById('hargaInputT2Display');
    if (hargaInputT2Display) {
        hargaInputT2Display.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                value = parseInt(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                this.value = value;
            }
            document.getElementById('hargaInputT2Hidden').value = parseRupiah(this.value);
            hitungOtomatis();
        });
    }

    // === POTONGAN HUTANG INPUT ===
    const inputPotonganHutang = document.getElementById('inputPotonganHutang');
    if (inputPotonganHutang) {
        inputPotonganHutang.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                let number = parseInt(value, 10);
                this.value = 'Rp ' + number.toLocaleString('id-ID');
                document.getElementById('potonganHutangHidden').value = number;
            } else {
                this.value = '';
                document.getElementById('potonganHutangHidden').value = 0;
            }
            hitungOtomatis();
        });
    }

    const currencyInputs = document.querySelectorAll('.currency-input');
    currencyInputs.forEach(input => {
        input.addEventListener('input', hitungOtomatis);
        input.addEventListener('change', hitungOtomatis);
    });

    // Serial Settings UI
    document.getElementById('selectPortBtn2')?.addEventListener('click', async function () {
        const btn = this;
        const originalHTML = btn.innerHTML;
        try {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            btn.disabled = true;
            const port = await navigator.serial.requestPort();
            const portInfo = port.getInfo();
            localStorage.setItem('serialPortInfo', JSON.stringify(portInfo));
            document.getElementById('portInfoDisplay2').innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Port Terpilih</span>';
            showNotification2('Port dipilih!', 'success');
        } catch (error) {
            if (error.name !== 'NotFoundError') {
                showNotification2('Error: ' + error.message, 'error');
            }
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });

    document.getElementById('openSerialSettings2')?.addEventListener('click', function () {
        const modal = new bootstrap.Modal(document.getElementById('serialSettingsModal2'));
        const savedBaud = localStorage.getItem('serialBaudRate') || '9600';
        document.getElementById('baudRateSelect2').value = savedBaud;
        document.getElementById('currentBaudDisplay2').textContent = savedBaud + ' baud';

        const connStatusEl = document.getElementById('currentConnStatus2');
        if (serialConnector2 && serialConnector2.isConnected) {
            connStatusEl.innerHTML = '<span class="text-success">✓ Terhubung</span>';
        } else {
            connStatusEl.innerHTML = '<span class="text-danger">✗ Terputus</span>';
        }
        modal.show();
    });

    document.getElementById('saveSerialSettings2')?.addEventListener('click', async function () {
        const selectedBaud = parseInt(document.getElementById('baudRateSelect2').value);
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
        this.disabled = true;

        try {
            localStorage.setItem('serialBaudRate', selectedBaud.toString());
            if (serialConnector2) {
                serialConnector2.updateBaudRate(selectedBaud);
                if (serialConnector2.isConnected) {
                    await serialConnector2.disconnect();
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
                await serialConnector2.autoConnect();
            }
            document.getElementById('currentBaudDisplay2').textContent = selectedBaud + ' baud';
            bootstrap.Modal.getInstance(document.getElementById('serialSettingsModal2')).hide();
            showNotification2('Setting disimpan!', 'success');
        } catch (error) {
            showNotification2('Error: ' + error.message, 'error');
        } finally {
            this.innerHTML = '<i class="bi bi-save-fill"></i> Simpan & Reconnect';
            this.disabled = false;
        }
    });

    if (document.getElementById('currentBaudRate2')) {
        document.getElementById('currentBaudRate2').textContent = (localStorage.getItem('serialBaudRate') || '9600') + ' baud';
    }

    // === CONFIRMATION DIALOG BEFORE SUBMIT ===
    const timbangan2Form = document.getElementById('timbangan2Form');
    if (timbangan2Form) {
        timbangan2Form.addEventListener('submit', function (e) {
            e.preventDefault();

            const bruto = document.getElementById('hasilBruto')?.textContent || '-';
            const tara = document.getElementById('hasilTara')?.textContent || '-';
            const netto = document.getElementById('hasilNettoAkhir')?.textContent || '-';
            const sisa = document.getElementById('hasilTotalHargaFinal')?.textContent || '-';
            const suplier = document.getElementById('displaySuplier')?.value || '-';

            Swal.fire({
                title: 'KONFIRMASI SIMPAN',
                html: `
                    <div style="text-align: left; font-size: 14px; line-height: 1.5;">
                        <p>Pastikan data benar sebelum menyimpan:</p>
                        <ul style="margin-bottom: 10px;">
                            <li>Supplier: <b>${suplier}</b></li>
                            <li>Bruto: <b>${bruto}</b></li>
                            <li>Tara: <b>${tara}</b></li>
                            <li>Netto Akhir: <b>${netto}</b></li>
                        </ul>
                        <hr>
                        <p style="text-align: center; font-size: 1.1em; margin-bottom: 5px;">TOTAL BAYAR</p>
                        <h3 style="text-align: center; color: #34d399; margin: 0;">${sisa}</h3>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#d33',
                confirmButtonText: 'YA, SIMPAN',
                cancelButtonText: 'BATAL',
                focusConfirm: true
            }).then((result) => {
                if (result.isConfirmed) {
                    timbangan2Form.submit();
                }
            });
        });
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', async function () {
    if (serialConnector2) {
        try {
            await serialConnector2.forceCleanup();
        } catch (e) { }
    }
});
