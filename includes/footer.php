</div> <!-- Close main-container -->

    <!-- Critical JS - Load First -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js" defer></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js" defer></script>
    <script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js" defer></script>

    <!-- Non-Critical JS - Load Later -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/select2.min.js"></script>
    <!-- Chart.js - skip for now to avoid import errors -->
    <!-- <script src="<?php echo BASE_URL; ?>assets/js/chart.min.js"></script> -->
    
    <!-- Custom JS -->
    <script>
        // Base URL
        const BASE_URL = '<?php echo BASE_URL; ?>';
        
        // Initialize Select2 with DOM ready check
        function initializeSelect2() {
            if (typeof $ !== 'undefined' && $('.select2').length) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
        }

        // Optimized weight update with debouncing
        let weightUpdateInterval = null;
        function startWeightUpdates() {
            if (weightUpdateInterval) return;

            weightUpdateInterval = setInterval(function() {
                if ($('#currentWeight').length) {
                    // Simulate weight update with less frequency
                    const weight = Math.floor(Math.random() * 1000) + 1;
                    $('#currentWeight').text(weight.toString());
                }
            }, 5000); // Increased from 2s to 5s
        }

        // Combined initialization when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeSelect2();
            startWeightUpdates();
        });

        // Fallback for jQuery-dependent code
        $(document).ready(function() {
            initializeSelect2();
        });
        
        // Format weight (whole numbers)
        function formatWeight(num) {
            return num.toString(); // Plain number without formatting
        }

        // Format number Indonesian style (for backward compatibility)
        function formatNumber(num) {
            return formatWeight(num);
        }

        // Format Rupiah
        function formatRupiah(num) {
            return 'Rp ' + formatWeight(num);
        }
        
        // Show loading overlay
        function showLoading() {
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
        
        // Hide loading
        function hideLoading() {
            Swal.close();
        }
        
        // Success alert
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: message,
                timer: 2000,
                showConfirmButton: false
            });
        }
        
        // Error alert
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: message
            });
        }
        
        // Confirm dialog
        function confirmDialog(message, callback) {
            Swal.fire({
                title: 'Konfirmasi',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Lanjutkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    callback();
                }
            });
        }
        
        // Print ticket
        function printTicket(ticketId) {
            window.open(BASE_URL + 'modules/timbangan/print_ticket.php?id=' + ticketId, '_blank');
        }
        
        // Export to Excel
        function exportToExcel(url) {
            window.location.href = url;
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <!-- Global Rupiah Formatting Functions -->
    <script>
        // Fungsi format Rupiah untuk display (dengan "Rp ")
        function formatRupiah(amount) {
            if (amount === 0 || !amount) return 'Rp 0';

            const num = parseFloat(amount);
            if (isNaN(num)) return 'Rp 0';

            // Format manual untuk memastikan titik sebagai pemisah ribuan
            const formatted = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            return 'Rp ' + formatted;
        }

        // Fungsi format Rupiah untuk input (tanpa "Rp ")
        function formatRupiahInput(amount) {
            if (amount === 0 || !amount) return '0';

            const num = parseFloat(amount);
            if (isNaN(num)) return '0';

            // Format manual untuk memastikan titik sebagai pemisah ribuan
            const formatted = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            return formatted;
        }

        // Fungsi parse Rupiah dari input (hilangkan "Rp " dan titik)
        function parseRupiah(rupiahString) {
            if (!rupiahString) return 0;

            // Hilangkan "Rp " dan titik, ganti dengan kosong
            const cleanString = rupiahString.toString().replace(/[^0-9]/g, '');
            return parseFloat(cleanString) || 0;
        }

        // Fungsi untuk format input saat user mengetik
        function formatRupiahInputOnChange(input) {
            let value = input.value;

            // Parse current value
            let numValue = parseRupiah(value);

            // Format kembali
            if (numValue > 0) {
                input.value = formatRupiahInput(numValue);
                // Update hidden field jika ada
                const hiddenField = document.getElementById(input.id.replace('Input', 'Hidden'));
                if (hiddenField) {
                    hiddenField.value = numValue;
                }
            } else {
                input.value = '';
                const hiddenField = document.getElementById(input.id.replace('Input', 'Hidden'));
                if (hiddenField) {
                    hiddenField.value = 0;
                }
            }
        }

        // Fungsi untuk mengaplikasikan format Rupiah ke semua input dengan class 'rupiah-input'
        function applyRupiahFormat() {
            const rupiahInputs = document.querySelectorAll('.rupiah-input');
            rupiahInputs.forEach(input => {
                // Format initial value
                if (input.value) {
                    const numValue = parseRupiah(input.value);
                    if (numValue > 0) {
                        input.value = formatRupiahInput(numValue);
                    }
                }

                // Add event listeners
                input.addEventListener('input', function() {
                    formatRupiahInputOnChange(this);
                });

                input.addEventListener('blur', function() {
                    const numValue = parseRupiah(this.value);
                    if (numValue > 0) {
                        this.value = formatRupiahInput(numValue);
                    } else {
                        this.value = '';
                    }
                });

                input.addEventListener('focus', function() {
                    // Remove formatting on focus for easier editing
                    const numValue = parseRupiah(this.value);
                    if (numValue > 0) {
                        this.value = numValue;
                    }
                });
            });
        }

        // Apply format when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            applyRupiahFormat();
        });
    </script>
</body>
</html>