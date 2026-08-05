/**
 * Pengiriman Pabrik JS
 * Logic for calculating netto, handling modals, and validation
 */

// Global variable for calculating shrinkage in modal
let currentNettoRam = 0;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 Pengiriman Pabrik JS Loaded');

    // === HITUNG NETTO OTOMATIS (Form Input) ===
    const beratBrutoEl = document.getElementById('beratBruto');
    const beratTaraEl = document.getElementById('beratTara');
    const nettoDisplayEl = document.getElementById('nettoDisplay');

    function hitungNetto() {
        const bruto = parseFloat(beratBrutoEl.value) || 0;
        const tara = parseFloat(beratTaraEl.value) || 0;
        const netto = bruto - tara;

        if (nettoDisplayEl) {
            nettoDisplayEl.textContent = netto.toLocaleString('id-ID') + ' Kg';
            if (netto < 0) {
                nettoDisplayEl.style.color = '#dc2626'; // Merah jika minus
            } else {
                nettoDisplayEl.style.color = '#22c55e'; // Hijau jika valid
            }
        }
    }

    if (beratBrutoEl && beratTaraEl) {
        beratBrutoEl.addEventListener('input', hitungNetto);
        beratTaraEl.addEventListener('input', hitungNetto);
    }

    // === MODAL HITUNG SUSUT (Input Berat Pabrik) ===
    const modalNettoPabrikEl = document.getElementById('modalNettoPabrik');
    const modalSusutEl = document.getElementById('modalSusut');

    if (modalNettoPabrikEl && modalSusutEl) {
        modalNettoPabrikEl.addEventListener('input', function () {
            const nettoPabrik = parseFloat(this.value) || 0;
            const susut = currentNettoRam - nettoPabrik;
            const persen = currentNettoRam > 0 ? (susut / currentNettoRam * 100) : 0;

            modalSusutEl.textContent = susut.toLocaleString('id-ID') + ' Kg (' + persen.toFixed(2) + '%)';

            // Visual indicator
            if (susut > 0) {
                modalSusutEl.style.color = '#f59e0b'; // Susut wajar (Kuning)
            } else if (susut < 0) {
                modalSusutEl.style.color = '#22c55e'; // Gain/Surplus (Hijau)
            } else {
                modalSusutEl.style.color = '#fff';
            }
        });
    }

    // === AUTO UPPERCASE NO POLISI ===
    // Mencari semua input dengan name="no_polisi"
    const noPolisiInputs = document.querySelectorAll('input[name="no_polisi"]');
    noPolisiInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });
});

// === GLOBAL FUNCTION EXPORT (Dipanggil dari HTML onclick) ===
window.inputBeratPabrik = function (id, noSJ, nettoRam) {
    currentNettoRam = nettoRam;

    // Set values to modal
    const modalId = document.getElementById('modalId');
    const modalNoSJ = document.getElementById('modalNoSJ');
    const modalNettoRam = document.getElementById('modalNettoRam');
    const modalNettoPabrik = document.getElementById('modalNettoPabrik');
    const modalSusut = document.getElementById('modalSusut');

    if (modalId) modalId.value = id;
    if (modalNoSJ) modalNoSJ.value = noSJ;
    if (modalNettoRam) modalNettoRam.value = nettoRam.toLocaleString('id-ID') + ' Kg';
    if (modalNettoPabrik) modalNettoPabrik.value = '';
    if (modalSusut) {
        modalSusut.textContent = '0 Kg (0%)';
        modalSusut.style.color = '#fff';
    }

    // Show modal using Bootstrap API
    const modalEl = document.getElementById('modalBeratPabrik');
    if (modalEl) {
        // Check if bootstrap is available
        if (typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            // Fallback for older bootstrap or jquery
            $(modalEl).modal('show');
        }
    }
};
