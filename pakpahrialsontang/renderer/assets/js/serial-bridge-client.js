/**
 * Serial Bridge Client untuk membaca data dari COM3 via Python Bridge Server
 * Author: AI Assistant
 * Version: 1.0
 */

class SerialBridgeClient {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || 'http://localhost:5000';
        this.isConnected = false;
        this.currentWeight = 0;
        this.lastReading = null;
        this.readingCount = 0;
        this.updateInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.updateFrequency = options.updateFrequency || 500; // ms

        // Event callbacks
        this.onWeightUpdate = options.onWeightUpdate || (() => {});
        this.onConnectionChange = options.onConnectionChange || (() => {});
        this.onError = options.onError || (() => {});
        this.onStatusUpdate = options.onStatusUpdate || (() => {});

        // Auto-connect options
        this.autoConnect = options.autoConnect !== false;
        this.autoReconnect = options.autoReconnect !== false;
    }

    /**
     * Connect ke bridge server
     */
    async connect(port = 'COM3') {
        try {
            const response = await fetch(`${this.baseUrl}/connect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ port: port })
            });

            const data = await response.json();

            if (data.success) {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.onConnectionChange(true);
                this.startPolling();
                console.log('✅ Connected to Serial Bridge:', data.message);
                return true;
            } else {
                this.onError('Failed to connect: ' + data.message);
                return false;
            }
        } catch (error) {
            this.onError('Connection error: ' + error.message);
            if (this.autoReconnect) {
                this.scheduleReconnect();
            }
            return false;
        }
    }

    /**
     * Disconnect dari bridge server
     */
    async disconnect() {
        this.stopPolling();

        try {
            const response = await fetch(`${this.baseUrl}/disconnect`, {
                method: 'POST'
            });
            const data = await response.json();

            this.isConnected = false;
            this.onConnectionChange(false);
            console.log('🔌 Disconnected:', data.message);
            return true;
        } catch (error) {
            console.error('Disconnect error:', error);
            return false;
        }
    }

    /**
     * Start polling untuk weight updates
     */
    startPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }

        this.updateInterval = setInterval(async () => {
            if (this.isConnected) {
                await this.updateWeight();
            }
        }, this.updateFrequency);

        // Immediate update
        this.updateWeight();
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    /**
     * Update weight data dari server
     */
    async updateWeight() {
        try {
            const response = await fetch(`${this.baseUrl}/get_weight`, {
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
                this.lastReading = data.last_reading;

                if (weightChanged) {
                    this.onWeightUpdate(data.weight);
                }

                // Update status periodically
                if (this.readingCount % 20 === 0) {
                    this.onStatusUpdate(data);
                }

                this.readingCount++;
            } else {
                throw new Error(data.message || 'Unknown error');
            }

        } catch (error) {
            console.error('Error updating weight:', error);
            this.onError('Weight update error: ' + error.message);

            // Try to reconnect if connection lost
            if (this.autoReconnect && this.isConnected) {
                this.isConnected = false;
                this.onConnectionChange(false);
                this.scheduleReconnect();
            }
        }
    }

    /**
     * Get connection status
     */
    async getStatus() {
        try {
            const response = await fetch(`${this.baseUrl}/status`);
            const data = await response.json();

            if (data.success) {
                const wasConnected = this.isConnected;
                this.isConnected = data.connected;
                this.currentWeight = data.current_weight;
                this.lastReading = data.last_reading;
                this.readingCount = data.readings_count;

                if (wasConnected !== data.connected) {
                    this.onConnectionChange(data.connected);
                }

                return data;
            }
        } catch (error) {
            console.error('Error getting status:', error);
            return null;
        }
    }

    /**
     * Get raw data for debugging
     */
    async getRawData() {
        try {
            const response = await fetch(`${this.baseUrl}/raw_data`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error getting raw data:', error);
            return null;
        }
    }

    /**
     * Set weight manually (for testing)
     */
    async setWeight(weight) {
        try {
            const response = await fetch(`${this.baseUrl}/set_weight`, {
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
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnect attempts reached');
            this.onError('Max reconnect attempts reached');
            return;
        }

        this.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000); // Exponential backoff, max 30s

        console.log(`Scheduling reconnect attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${delay/1000}s`);

        setTimeout(async () => {
            if (!this.isConnected) {
                console.log('Attempting to reconnect...');
                await this.connect();
            }
        }, delay);
    }

    /**
     * Initialize auto-connect
     */
    async initialize() {
        if (this.autoConnect) {
            console.log('Attempting auto-connect...');
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
     * Check if server is available
     */
    static async isServerAvailable(baseUrl = 'http://localhost:5000') {
        try {
            const response = await fetch(`${baseUrl}/`, {
                method: 'GET',
                timeout: 3000
            });
            return response.ok;
        } catch (error) {
            return false;
        }
    }
}

// Export untuk browser global
if (typeof window !== 'undefined') {
    window.SerialBridgeClient = SerialBridgeClient;
}

// Export untuk Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SerialBridgeClient;
}