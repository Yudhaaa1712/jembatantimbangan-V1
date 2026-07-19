/**
 * Web Serial API Handler untuk Jembatan Timbangan
 * Menghubungkan browser langsung ke indikator timbangan via COM3
 * Author: Claude Code Assistant
 */

class WeightIndicatorSerial {
    constructor() {
        this.port = null;
        this.reader = null;
        this.writer = null;
        this.isConnected = false;
        this.buffer = '';
        this.weightCallback = null;
        this.connectionCallback = null;
        this.errorCallback = null;
        this.readInterval = null;
    }

    /**
     * Cek apakah Web Serial API didukung browser
     */
    isSupported() {
        return 'serial' in navigator;
    }

    /**
     * Meminta izin dan membuka koneksi ke port serial
     */
    async connect() {
        console.log('=== Web Serial API Connect Start ===');
        try {
            if (!this.isSupported()) {
                throw new Error('Web Serial API tidak didukung. Gunakan Chrome atau Edge.');
            }

            console.log('Requesting serial port...');
            // Request port dengan filter untuk COM port
            this.port = await navigator.serial.requestPort();
            console.log('Port selected:', this.port);

            console.log('Opening port with settings: 9600,8,N,1');
            // Configure port untuk indikator timbangan
            await this.port.open({
                baudRate: 9600,
                dataBits: 8,
                stopBits: 1,
                parity: 'none',
                flowControl: 'none'
            });
            console.log('Port opened successfully!');

            this.isConnected = true;

            // Start reading data
            console.log('Starting to read data...');
            this.startReading();

            if (this.connectionCallback) {
                this.connectionCallback(true);
            }

            console.log('✅ Terhubung ke indikator timbangan');
            return true;

        } catch (error) {
            console.error('❌ Gagal menghubungkan ke indikator:', error);
            console.error('Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });

            if (this.errorCallback) {
                this.errorCallback(error.message);
            }
            return false;
        }
    }

    /**
     * Memutus koneksi dari port serial
     */
    async disconnect() {
        try {
            this.stopReading();

            if (this.reader) {
                await this.reader.cancel();
                this.reader = null;
            }

            if (this.writer) {
                await this.writer.close();
                this.writer = null;
            }

            if (this.port) {
                await this.port.close();
                this.port = null;
            }

            this.isConnected = false;
            this.buffer = '';

            if (this.connectionCallback) {
                this.connectionCallback(false);
            }

            console.log('Terputus dari indikator timbangan');
            return true;

        } catch (error) {
            console.error('Gagal memutus koneksi:', error);
            if (this.errorCallback) {
                this.errorCallback(error.message);
            }
            return false;
        }
    }

    /**
     * Mulai membaca data dari port serial
     */
    async startReading() {
        if (!this.port || !this.isConnected) return;

        const decoder = new TextDecoderStream();
        const inputDone = this.port.readable.pipeTo(decoder.writable);
        const inputStream = decoder.readable;

        this.reader = inputStream.getReader();

        try {
            while (true) {
                const { value, done } = await this.reader.read();
                if (done) {
                    break;
                }

                if (value) {
                    this.processSerialData(value);
                }
            }
        } catch (error) {
            console.error('Error reading from serial port:', error);
            if (this.errorCallback) {
                this.errorCallback('Error membaca data: ' + error.message);
            }
        }
    }

    /**
     * Memproses data yang diterima dari serial port
     */
    processSerialData(data) {
        // Tambahkan data ke buffer
        this.buffer += data;

        // Process complete lines
        const lines = this.buffer.split('\n');
        this.buffer = lines.pop() || ''; // Keep incomplete line in buffer

        for (const line of lines) {
            const trimmedLine = line.trim();
            if (trimmedLine) {
                const weight = this.parseWeightData(trimmedLine);
                if (weight !== null && this.weightCallback) {
                    this.weightCallback(weight);
                }
            }
        }
    }

    /**
     * Parse data dari indikator timbangan
     * Support multiple formats: wn0000200kg, n001234kg, 1234kg, ST 1234, dll
     */
    parseWeightData(data) {
        try {
            console.log('📨 Received raw data:', data);
            console.log('📨 Hex view:', this.toHex(data));

            // Try multiple parsing patterns
            const results = [];

            // Pattern 1: Extract all digits (most common)
            const digitOnly = data.replace(/[^\d]/g, '');
            if (digitOnly) {
                const weight1 = parseInt(digitOnly);
                if (!isNaN(weight1) && weight1 >= 0 && weight1 <= 100000) {
                    results.push({ method: 'digits_only', weight: weight1, cleaned: digitOnly });
                }
            }

            // Pattern 2: Common weight indicator formats
            const patterns = [
                /w?n?0*(\d+)kg/i,           // "wn0001234kg" or "n001234kg"
                /(\d+)\s*kg/i,             // "1234 kg" or "1234KG"
                /st\s*(\d+)/i,             // "ST 1234"
                /gt\s*(\d+)/i,             // "GT 1234"
                /net\s*(\d+)/i,            // "NET 1234"
                /tara?\s*(\d+)/i,          // "TARA 1234" or "TARE 1234"
                /bruto\s*(\d+)/i,          // "BRUTO 1234"
                /(\d{3,6})/g               // Any 3-6 digit number
            ];

            patterns.forEach((pattern, index) => {
                const matches = data.match(pattern);
                if (matches) {
                    matches.forEach(match => {
                        const weight = parseInt(match.replace(/\D/g, ''));
                        if (!isNaN(weight) && weight >= 0 && weight <= 100000) {
                            results.push({
                                method: `pattern_${index + 1}`,
                                weight: weight,
                                match: match,
                                pattern: pattern.toString()
                            });
                        }
                    });
                }
            });

            // Pattern 3: Position-based extraction for fixed format
            if (data.length >= 4) {
                const positions = [
                    { start: 0, len: 4, name: "pos_0_4" },
                    { start: 2, len: 4, name: "pos_2_4" },
                    { start: 1, len: 5, name: "pos_1_5" },
                    { start: data.length - 4, len: 4, name: "pos_last4" }
                ];

                positions.forEach(pos => {
                    const substring = pos.start >= 0 ?
                        data.substring(pos.start, pos.start + pos.len) :
                        data.substring(data.length + pos.start);

                    const extracted = substring.replace(/[^\d]/g, '');
                    if (extracted.length >= 3) {
                        const weight = parseInt(extracted);
                        if (!isNaN(weight) && weight >= 0 && weight <= 100000) {
                            results.push({
                                method: pos.name,
                                weight: weight,
                                extracted: extracted,
                                position: pos
                            });
                        }
                    }
                });
            }

            console.log('🔍 Parse results:', results);

            // Choose the best result
            if (results.length > 0) {
                // Prioritize methods: digits_only > pattern_1 > others
                const priority = ['digits_only', 'pattern_1', 'pattern_2'];
                for (const method of priority) {
                    const result = results.find(r => r.method === method);
                    if (result) {
                        console.log('✅ Valid weight selected:', result.weight, 'Method:', result.method);
                        return result.weight;
                    }
                }

                // If no priority method found, use first result
                const bestResult = results[0];
                console.log('✅ Valid weight (fallback):', bestResult.weight, 'Method:', bestResult.method);
                return bestResult.weight;
            }

            console.log('❌ No valid weight found in data');
            return null;

        } catch (error) {
            console.error('❌ Error parsing weight data:', error);
            return null;
        }
    }

    /**
     * Convert string to hex for debugging
     */
    toHex(str) {
        let hex = '';
        for (let i = 0; i < str.length; i++) {
            const charCode = str.charCodeAt(i).toString(16).padStart(2, '0');
            hex += charCode + ' ';
        }
        return hex.trim();
    }

    /**
     * Stop reading data
     */
    stopReading() {
        if (this.readInterval) {
            clearInterval(this.readInterval);
            this.readInterval = null;
        }
    }

    /**
     * Set callback untuk update berat
     */
    onWeightUpdate(callback) {
        this.weightCallback = callback;
    }

    /**
     * Set callback untuk status koneksi
     */
    onConnectionChange(callback) {
        this.connectionCallback = callback;
    }

    /**
     * Set callback untuk error handling
     */
    onError(callback) {
        this.errorCallback = callback;
    }

    /**
     * Get status koneksi
     */
    getConnectionStatus() {
        return {
            connected: this.isConnected,
            supported: this.isSupported()
        };
    }
}

// Global instance
window.WeightIndicator = new WeightIndicatorSerial();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Weight Indicator Serial API loaded');
    console.log('Navigator serial available:', 'serial' in navigator);
    console.log('User agent:', navigator.userAgent);

    // Check browser support
    if (!window.WeightIndicator.isSupported()) {
        console.warn('Web Serial API not supported. Please use Chrome or Edge.');
        // Show browser compatibility warning
        if (window.showBrowserWarning) {
            window.showBrowserWarning();
        }

        // Fallback notification
        const warningDiv = document.createElement('div');
        warningDiv.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        warningDiv.style.cssText = 'top: 70px; right: 20px; z-index: 9999; max-width: 400px;';
        warningDiv.innerHTML = `
            <strong>⚠️ Web Serial API Tidak Didukung!</strong><br>
            Browser ini tidak mendukung Web Serial API. Gunakan Chrome atau Edge untuk koneksi langsung ke timbangan.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(warningDiv);
    } else {
        console.log('Web Serial API is supported!');

        // Show success notification
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
        successDiv.style.cssText = 'top: 70px; right: 20px; z-index: 9999; max-width: 400px;';
        successDiv.innerHTML = `
            <strong>✅ Web Serial API Ready!</strong><br>
            Browser mendukung Web Serial API. Klik "Connect Indicator" untuk mulai.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(successDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.parentNode.removeChild(successDiv);
            }
        }, 5000);
    }
});

// Utility functions
window.WeightIndicatorUtils = {
    /**
     * Format weight untuk display
     */
    formatWeight: function(weight) {
        return weight.toLocaleString('id-ID') + ' Kg';
    },

    /**
     * Validate weight value
     */
    isValidWeight: function(weight) {
        return !isNaN(weight) && weight >= 0 && weight <= 100000;
    },

    /**
     * Show browser compatibility warning
     */
    showBrowserWarning: function() {
        const warningDiv = document.createElement('div');
        warningDiv.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        warningDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        warningDiv.innerHTML = `
            <strong>⚠️ Peringatan Browser!</strong><br>
            Web Serial API hanya didukung di browser Chrome atau Edge.
            Untuk koneksi timbangan, silakan gunakan browser yang kompatibel.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(warningDiv);

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (warningDiv.parentNode) {
                warningDiv.parentNode.removeChild(warningDiv);
            }
        }, 10000);
    }
};

// Make utility functions globally accessible
window.showBrowserWarning = window.WeightIndicatorUtils.showBrowserWarning;