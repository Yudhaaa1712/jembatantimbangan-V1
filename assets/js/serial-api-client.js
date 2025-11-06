/**
 * Serial API Client untuk membaca data dari COM3 via PHP API
 * Author: AI Assistant
 * Version: 1.0
 */

class SerialAPIClient {
    constructor(options = {}) {
        this.apiUrl = options.apiUrl || '../api/serial.php';
        this.isConnected = false;
        this.currentWeight = 0;
        this.lastRawData = '';
        this.updateInterval = null;
        this.updateFrequency = options.updateFrequency || 1000; // 1 detik

        // Event callbacks
        this.onWeightUpdate = options.onWeightUpdate || (() => {});
        this.onConnectionChange = options.onConnectionChange || (() => {});
        this.onError = options.onError || (() => {});
        this.onRawData = options.onRawData || (() => {});

        // Auto-connect options
        this.autoConnect = options.autoConnect !== false;
        this.autoReconnect = options.autoReconnect !== false;
    }

    /**
     * Test koneksi ke port COM3
     */
    async testConnection() {
        try {
            const response = await fetch(`${this.apiUrl}?action=test`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            const data = await response.json();

            if (data.success && data.connected) {
                this.isConnected = true;
                this.onConnectionChange(true);
                console.log('✅ Port COM3 tersedia');
            } else {
                this.isConnected = false;
                this.onConnectionChange(false);
                console.log('❌ Port COM3 tidak tersedia:', data.message);
            }

            return data;
        } catch (error) {
            console.error('Error testing connection:', error);
            this.onError('Connection test error: ' + error.message);
            return { success: false, error: error.message };
        }
    }

    /**
     * Baca data dari indikator
     */
    async readWeight() {
        try {
            const response = await fetch(`${this.apiUrl}?action=read`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                const weightChanged = this.currentWeight !== data.weight;
                this.currentWeight = data.weight;
                this.lastRawData = data.raw_data || '';

                if (weightChanged) {
                    this.onWeightUpdate(data.weight);
                }

                if (data.raw_data) {
                    this.onRawData(data.raw_data);
                }

                return data;
            } else {
                throw new Error(data.error || 'Failed to read weight');
            }

        } catch (error) {
            console.error('Error reading weight:', error);
            this.onError('Weight read error: ' + error.message);
            return null;
        }
    }

    /**
     * Get status
     */
    async getStatus() {
        try {
            const response = await fetch(`${this.apiUrl}?action=status`);
            const data = await response.json();

            if (data.success) {
                const wasConnected = this.isConnected;
                this.isConnected = data.connected;
                this.currentWeight = data.current_weight || 0;
                this.lastRawData = data.last_raw_data || '';

                if (wasConnected !== data.connected) {
                    this.onConnectionChange(data.connected);
                }
            }

            return data;
        } catch (error) {
            console.error('Error getting status:', error);
            return null;
        }
    }

    /**
     * Set weight manual (untuk testing)
     */
    async setWeight(weight) {
        try {
            const response = await fetch(`${this.apiUrl}?action=set_weight`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ weight: weight })
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error setting weight:', error);
            return false;
        }
    }

    /**
     * Start auto-update
     */
    startPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }

        this.updateInterval = setInterval(async () => {
            await this.readWeight();
        }, this.updateFrequency);

        // Immediate read
        this.readWeight();
        console.log('📡 Started polling weight data');
    }

    /**
     * Stop auto-update
     */
    stopPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
        console.log('⏹️ Stopped polling weight data');
    }

    /**
     * Connect dan mulai membaca
     */
    async connect() {
        console.log('🔌 Connecting to COM3...');

        const testResult = await this.testConnection();
        if (testResult.success && testResult.connected) {
            this.startPolling();
            return true;
        } else {
            console.log('⚠️ Using simulation mode');
            this.startPolling();
            return true; // Masal jalan di mode simulasi
        }
    }

    /**
     * Disconnect
     */
    disconnect() {
        this.stopPolling();
        this.isConnected = false;
        this.onConnectionChange(false);
        console.log('🔌 Disconnected');
    }

    /**
     * Initialize
     */
    async initialize() {
        if (this.autoConnect) {
            await this.connect();
        }
    }

    /**
     * Format weight untuk display
     */
    static formatWeight(weight, unit = 'Kg') {
        const num = parseFloat(weight) || 0;
        return `${num.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} ${unit}`;
    }

    /**
     * Parse weight dari string
     */
    static parseWeight(weightString) {
        const match = weightString.match(/([+-]?\d+\.?\d*)/);
        return match ? parseFloat(match[1]) : 0;
    }
}

// Export untuk browser global
if (typeof window !== 'undefined') {
    window.SerialAPIClient = SerialAPIClient;
}

// Export untuk Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SerialAPIClient;
}