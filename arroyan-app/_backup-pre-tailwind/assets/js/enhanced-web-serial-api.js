/**
 * Enhanced Web Serial API untuk Multiple Weight Indicators
 * Support multiple connections dengan unique instances
 * Author: Claude Code Assistant
 */

class EnhancedWeightIndicator {
    constructor(indicatorId = 'default') {
        this.indicatorId = indicatorId;
        this.port = null;
        this.reader = null;
        this.writer = null;
        this.isConnected = false;
        this.buffer = '';
        this.weightCallback = null;
        this.connectionCallback = null;
        this.errorCallback = null;
        this.readInterval = null;
        this.lastWeight = 0;
        this.lastUpdate = 0;
        this.dataHistory = [];
        this.maxHistorySize = 50;
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
        console.log(`=== Enhanced Web Serial API Connect Start - Indicator ${this.indicatorId} ===`);
        try {
            if (!this.isSupported()) {
                throw new Error('Web Serial API tidak didukung. Gunakan Chrome atau Edge.');
            }

            console.log(`Requesting serial port for indicator ${this.indicatorId}...`);

            // Request port dengan filter untuk COM port
            this.port = await navigator.serial.requestPort();
            console.log(`Port selected for indicator ${this.indicatorId}:`, this.port);

            console.log(`Opening port with settings: 9600,8,N,1 for indicator ${this.indicatorId}`);
            await this.port.open({
                baudRate: 9600,
                dataBits: 8,
                stopBits: 1,
                parity: 'none',
                flowControl: 'none'
            });
            console.log(`Port ${this.indicatorId} opened successfully!`);

            this.isConnected = true;

            // Start reading data
            console.log(`Starting to read data from indicator ${this.indicatorId}...`);
            this.startReading();

            if (this.connectionCallback) {
                this.connectionCallback(true);
            }

            console.log(`✅ Terhubung ke indikator timbangan ${this.indicatorId}`);
            return true;

        } catch (error) {
            console.error(`❌ Gagal menghubungkan ke indikator ${this.indicatorId}:`, error);
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

            console.log(`Terputus dari indikator timbangan ${this.indicatorId}`);
            return true;

        } catch (error) {
            console.error(`Gagal memutus koneksi dari indikator ${this.indicatorId}:`, error);
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
            console.error(`Error reading from serial port ${this.indicatorId}:`, error);
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
                    this.lastWeight = weight;
                    this.lastUpdate = Date.now();

                    // Add to history
                    this.addToHistory(weight, trimmedLine);

                    // Call callback
                    this.weightCallback(weight);
                }
            }
        }
    }

    /**
     * Add data to history
     */
    addToHistory(weight, rawData) {
        const entry = {
            weight: weight,
            rawData: rawData,
            timestamp: new Date(),
            indicatorId: this.indicatorId
        };

        this.dataHistory.push(entry);

        // Keep only last N entries
        if (this.dataHistory.length > this.maxHistorySize) {
            this.dataHistory.shift();
        }
    }

    /**
     * Parse data dari indikator timbangan
     * Support multiple formats: wn0000200kg, n001234kg, 1234kg, ST 1234, dll
     */
    parseWeightData(data) {
        try {
            console.log(`📨 [${this.indicatorId}] Received raw data:`, data);
            console.log(`📨 [${this.indicatorId}] Hex view:`, this.toHex(data));

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

            console.log(`🔍 [${this.indicatorId}] Parse results:`, results);

            // Choose the best result
            if (results.length > 0) {
                // Prioritize methods: digits_only > pattern_1 > others
                const priority = ['digits_only', 'pattern_1', 'pattern_2'];
                for (const method of priority) {
                    const result = results.find(r => r.method === method);
                    if (result) {
                        console.log(`✅ [${this.indicatorId}] Valid weight selected:`, result.weight, 'Method:', result.method);
                        return result.weight;
                    }
                }

                // If no priority method found, use first result
                const bestResult = results[0];
                console.log(`✅ [${this.indicatorId}] Valid weight (fallback):`, bestResult.weight, 'Method:', bestResult.method);
                return bestResult.weight;
            }

            console.log(`❌ [${this.indicatorId}] No valid weight found in data`);
            return null;

        } catch (error) {
            console.error(`❌ [${this.indicatorId}] Error parsing weight data:`, error);
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
            supported: this.isSupported(),
            indicatorId: this.indicatorId,
            lastWeight: this.lastWeight,
            lastUpdate: this.lastUpdate
        };
    }

    /**
     * Get data history
     */
    getDataHistory() {
        return [...this.dataHistory];
    }

    /**
     * Get last weight
     */
    getLastWeight() {
        return this.lastWeight;
    }

    /**
     * Get connection info
     */
    getConnectionInfo() {
        return {
            indicatorId: this.indicatorId,
            isConnected: this.isConnected,
            lastWeight: this.lastWeight,
            lastUpdate: this.lastUpdate,
            dataCount: this.dataHistory.length
        };
    }
}

/**
 * Multi Weight Manager - Manage multiple indicators
 */
class MultiWeightManager {
    constructor() {
        this.indicators = new Map();
        this.globalCallbacks = {
            weightUpdate: [],
            connectionChange: [],
            error: []
        };
    }

    /**
     * Create or get existing indicator
     */
    getIndicator(indicatorId) {
        if (!this.indicators.has(indicatorId)) {
            const indicator = new EnhancedWeightIndicator(indicatorId);

            // Set up callbacks that propagate to global callbacks
            indicator.onWeightUpdate((weight) => {
                this.globalCallbacks.weightUpdate.forEach(callback => {
                    callback(indicatorId, weight);
                });
            });

            indicator.onConnectionChange((connected) => {
                this.globalCallbacks.connectionChange.forEach(callback => {
                    callback(indicatorId, connected);
                });
            });

            indicator.onError((error) => {
                this.globalCallbacks.error.forEach(callback => {
                    callback(indicatorId, error);
                });
            });

            this.indicators.set(indicatorId, indicator);
        }

        return this.indicators.get(indicatorId);
    }

    /**
     * Connect specific indicator
     */
    async connect(indicatorId) {
        const indicator = this.getIndicator(indicatorId);
        return await indicator.connect();
    }

    /**
     * Disconnect specific indicator
     */
    async disconnect(indicatorId) {
        const indicator = this.indicators.get(indicatorId);
        if (indicator) {
            return await indicator.disconnect();
        }
        return true;
    }

    /**
     * Connect all indicators
     */
    async connectAll(indicatorIds) {
        const results = [];
        for (const id of indicatorIds) {
            const result = await this.connect(id);
            results.push({ indicatorId: id, success: result });

            // Add delay between connections to avoid conflicts
            if (indicatorIds.indexOf(id) < indicatorIds.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
        return results;
    }

    /**
     * Disconnect all indicators
     */
    async disconnectAll() {
        const results = [];
        for (const [indicatorId, indicator] of this.indicators) {
            const result = await indicator.disconnect();
            results.push({ indicatorId: indicatorId, success: result });
        }
        return results;
    }

    /**
     * Get all indicators status
     */
    getAllStatus() {
        const status = {};
        for (const [indicatorId, indicator] of this.indicators) {
            status[indicatorId] = indicator.getConnectionInfo();
        }
        return status;
    }

    /**
     * Get indicator
     */
    get(indicatorId) {
        return this.indicators.get(indicatorId);
    }

    /**
     * Register global callbacks
     */
    onWeightUpdate(callback) {
        this.globalCallbacks.weightUpdate.push(callback);
    }

    onConnectionChange(callback) {
        this.globalCallbacks.connectionChange.push(callback);
    }

    onError(callback) {
        this.globalCallbacks.error.push(callback);
    }
}

// Global instances
window.EnhancedWeightIndicator = EnhancedWeightIndicator;
window.MultiWeightManager = MultiWeightManager;
window.WeightIndicators = new MultiWeightManager();

// Backward compatibility - create default instance
window.WeightIndicator = new EnhancedWeightIndicator('default');

// Utility functions (maintain compatibility with existing code)
window.WeightIndicatorUtils = {
    formatWeight: function(weight) {
        return weight.toLocaleString('id-ID') + ' Kg';
    },

    isValidWeight: function(weight) {
        return !isNaN(weight) && weight >= 0 && weight <= 100000;
    },

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

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Enhanced Web Serial API loaded');
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

// Make utility functions globally accessible
window.showBrowserWarning = window.WeightIndicatorUtils.showBrowserWarning;