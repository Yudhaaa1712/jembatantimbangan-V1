/**
 * Main JavaScript for Jembatan Timbangan Sawit
 * Generated automatically
 */

// Global Variables
window.Timbangan = {
    settings: {
        baseUrl: '',
        weightUpdateInterval: 2000,
        timestampUpdateInterval: 1000,
        animationDuration: 300
    },
    state: {
        currentWeight: 0,
        capturedWeight: 0,
        isConnected: false,
        isCapturing: false
    }
};

// Utility Functions
window.Timbangan.utils = {
    // Format weight (whole numbers)
    formatWeight: function(num) {
        return num.toString(); // Plain number without formatting
    },

    // Format number with Indonesian style (for backward compatibility)
    formatNumber: function(num) {
        return this.formatWeight(num);
    },

    // Format currency
    formatCurrency: function(amount) {
        return 'Rp ' + this.formatWeight(amount);
    },

    // Show loading state
    showLoading: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.add('loading');
            element.disabled = true;
        }
    },

    // Hide loading state
    hideLoading: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.remove('loading');
            element.disabled = false;
        }
    },

    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Throttle function
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Validate form
    validateForm: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;

        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return isValid;
    },

    // Show notification
    showNotification: function(message, type = 'info', duration = 5000) {
        // Remove existing notifications
        const existing = document.querySelector('.notification-toast');
        if (existing) {
            existing.remove();
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification-toast alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    },

    // AJAX wrapper with error handling
    ajax: function(options) {
        const defaults = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: 30000
        };

        const settings = Object.assign({}, defaults, options);

        return fetch(settings.url, settings)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                this.showNotification('Terjadi kesalahan koneksi. Silakan coba lagi.', 'danger');
                throw error;
            });
    },

    // Animate number
    animateNumber: function(element, start, end, duration = 1000) {
        const self = this; // Store reference to this
        const range = end - start;
        const minTimer = 50;
        let stepTime = Math.abs(Math.floor(duration / range));
        stepTime = Math.max(stepTime, minTimer);
        const startTime = new Date().getTime();
        const endTime = startTime + duration;
        let timer;

        function run() {
            const now = new Date().getTime();
            const remaining = Math.max((endTime - now) / duration, 0);
            const value = Math.round(end - (remaining * range));

            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (element) {
                element.textContent = self.formatNumber(value); // Use self instead of this
            }

            if (value == end) {
                clearInterval(timer);
            }
        }

        timer = setInterval(run, stepTime);
        run();
    }
};

// Weight Management
window.Timbangan.weight = {
    // Update weight display
    updateWeight: function(callback) {
        this.ajax({
            url: Timbangan.settings.baseUrl + 'modules/timbangan/ajax.php',
            method: 'POST',
            body: JSON.stringify({
                action: 'get_weight'
            })
        })
        .then(response => {
            if (response.success) {
                const oldWeight = Timbangan.state.currentWeight;
                Timbangan.state.currentWeight = response.data.weight;

                // Animate weight change
                if (oldWeight !== response.data.weight) {
                    this.animateWeightChange(response.data.weight);
                }

                if (callback) callback(response.data.weight);
            }
        })
        .catch(error => {
            // Silently handle weight update errors
        });
    },

    // Animate weight change
    animateWeightChange: function(newWeight) {
        const display = document.getElementById('currentWeight');
        if (!display) return;

        display.style.opacity = '0.5';
        setTimeout(() => {
            display.textContent = Timbangan.utils.formatNumber(newWeight);
            display.style.opacity = '1';
        }, Timbangan.settings.animationDuration / 2);

        // Update status indicator
        this.updateStatus(newWeight);
    },

    // Update status indicator
    updateStatus: function(weight) {
        const status = document.getElementById('weightStatus');
        if (!status) return;

        if (weight > 0) {
            status.textContent = '� ACTIVE';
            status.style.cssText = `
                background: rgba(0,255,0,0.3);
                border: 1px solid #0f0;
                color: #0f0;
                padding: 5px 12px;
                border-radius: 5px;
                font-size: 11px;
                font-weight: 600;
            `;
        } else {
            status.textContent = '� STANDBY';
            status.style.cssText = `
                background: rgba(128,128,128,0.3);
                border: 1px solid #888;
                color: #888;
                padding: 5px 12px;
                border-radius: 5px;
                font-size: 11px;
                font-weight: 600;
            `;
        }
    },

    // Capture weight
    captureWeight: function() {
        if (Timbangan.state.isCapturing) {
            Timbangan.utils.showNotification('Sedang melakukan capture...', 'warning');
            return;
        }

        const weight = Timbangan.state.currentWeight;
        if (weight <= 0) {
            Timbangan.utils.showNotification('Berat tidak valid! Pastikan kendaraan berada di atas timbangan.', 'danger');
            return;
        }

        Timbangan.state.isCapturing = true;
        Timbangan.state.capturedWeight = weight;

        // Update capture display
        const displayElement = document.getElementById('display_weight');
        if (displayElement) {
            displayElement.value = Timbangan.utils.formatNumber(weight) + ' Kg';
        }

        // Update status
        this.updateStatusAfterCapture();

        // Show success notification
        Timbangan.utils.showNotification(`Berat ${Timbangan.utils.formatNumber(weight)} Kg berhasil di-capture!`, 'success');

        // Reset capture state
        setTimeout(() => {
            Timbangan.state.isCapturing = false;
        }, 2000);
    },

    // Update status after capture
    updateStatusAfterCapture: function() {
        const status = document.getElementById('weightStatus');
        if (!status) return;

        status.textContent = '� CAPTURED';
        status.style.cssText = `
            background: rgba(255,165,0,0.3);
            border: 1px solid #ff9800;
            color: #ff9800;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
        `;
    },

    // Start weight monitoring
    startMonitoring: function() {
        // Update weight immediately
        this.updateWeight();

        // Set interval for continuous updates
        setInterval(() => {
            this.updateWeight();
        }, Timbangan.settings.weightUpdateInterval);
    }
};

// Form Management
window.Timbangan.form = {
    // Reset form
    resetForm: function(formId, confirmMessage = 'Reset semua data?') {
        if (confirm(confirmMessage)) {
            const form = document.getElementById(formId);
            if (form) {
                form.reset();
                // Remove validation classes
                form.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });

                // Reset captured weight
                Timbangan.state.capturedWeight = 0;

                // Reset display elements
                const displayWeight = document.getElementById('display_weight');
                if (displayWeight) displayWeight.value = '';

                Timbangan.utils.showNotification('Form berhasil direset', 'info');
            }
        }
    },

    // Submit form with validation
    submitForm: function(formId, url, successCallback, errorCallback) {
        if (!Timbangan.utils.validateForm(formId)) {
            Timbangan.utils.showNotification('Mohon lengkapi semua field yang wajib diisi!', 'warning');
            return;
        }

        const form = document.getElementById(formId);
        const formData = new FormData(form);

        Timbangan.utils.showLoading(form);

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            Timbangan.utils.hideLoading(form);

            if (data.success) {
                Timbangan.utils.showNotification(data.message || 'Data berhasil disimpan!', 'success');
                if (successCallback) successCallback(data);
            } else {
                Timbangan.utils.showNotification(data.message || 'Gagal menyimpan data!', 'danger');
                if (errorCallback) errorCallback(data);
            }
        })
        .catch(error => {
            Timbangan.utils.hideLoading(form);
            Timbangan.utils.showNotification('Terjadi kesalahan. Silakan coba lagi.', 'danger');
            if (errorCallback) errorCallback(error);
        });
    }
};

// Timestamp Management
window.Timbangan.timestamp = {
    // Update timestamp display
    updateTimestamp: function() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID');
        const dateString = now.toLocaleDateString('id-ID');

        // Update time displays
        const timeElements = document.querySelectorAll('[id*="time"], .current-time');
        timeElements.forEach(el => {
            el.textContent = timeString;
        });

        // Update date displays
        const dateElements = document.querySelectorAll('.current-date');
        dateElements.forEach(el => {
            el.textContent = dateString;
        });
    },

    // Start timestamp updates
    startUpdating: function() {
        this.updateTimestamp();
        setInterval(() => {
            this.updateTimestamp();
        }, Timbangan.settings.timestampUpdateInterval);
    }
};

// Initialize application
window.Timbangan.init = function() {
    // Get base URL from current location
    const path = window.location.pathname;
    const pathParts = path.split('/');
    const baseUrl = window.location.origin + '/' + pathParts[1] + '/';
    Timbangan.settings.baseUrl = baseUrl;

    // Initialize components
    if (document.getElementById('currentWeight')) {
        Timbangan.weight.startMonitoring();
    }

    Timbangan.timestamp.startUpdating();

    // Add global error handler
    window.addEventListener('error', function(e) {
        Timbangan.utils.showNotification('Terjadi kesalahan pada sistem.', 'danger');
    });

    // Add unhandled promise rejection handler
    window.addEventListener('unhandledrejection', function(e) {
        Timbangan.utils.showNotification('Terjadi kesalahan pada sistem.', 'danger');
    });
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Timbangan.init);
} else {
    Timbangan.init();
}

// Export to global scope
window.TimbanganApp = Timbangan;