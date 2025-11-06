</div> <!-- Close main-container -->

    <!-- Critical JS - Load First -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-3.7.1.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

    <!-- Non-Critical JS - Load Later -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" async></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js" async></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" async></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" async></script>
    
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
</body>
</html>