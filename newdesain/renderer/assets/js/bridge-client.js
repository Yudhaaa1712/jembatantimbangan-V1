/**
 * Bridge Client untuk Timbangan Indonesia
 * Menghubungkan web application ke Python Serial Bridge
 * Author: Claude Code Assistant
 */

class TimbanganBridgeClient {
    constructor(options = {}) {
        this.bridgeUrl = options.bridgeUrl || 'http://localhost:5000';
        this.indicators = {};
        this.callbacks = {
            weightUpdate: [],
            connectionChange: [],
            error: []
        };
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 2000;
        this.isConnected = false;
        this.socket = null;
        this.weightUpdateInterval = null;
        this.bridgeType = 'standard'; // 'standard' or 'sonic'
        this.lastWeight = 0;
        this.pollingInterval = null;
    }

    /**
     * Initialize bridge connection
     */
    async initialize() {
        try {
            console.log('Initializing Timbangan Bridge Client...');

            // Test bridge availability - try both endpoints
            let response;
            try {
                response = await fetch(`${this.bridgeUrl}/api/status`);
            } catch (e) {
                // Try Alternative Sonic A28E Bridge endpoint
                response = await fetch(`${this.bridgeUrl}/status`);
            }

            if (!response.ok) {
                throw new Error('Bridge server not responding');
            }

            const data = await response.json();
            console.log('Bridge data received:', data);

            // For Alternative Sonic A28E Bridge, use HTTP polling instead of WebSocket
            if (data.server && data.server.includes('Alternative Sonic')) {
                console.log('Detected Alternative Sonic A28E Bridge, using HTTP polling');
                this.bridgeType = 'sonic';
                this.startPolling();
                return true;
            }

            // Initialize WebSocket connection for standard bridge
            await this.initWebSocket();

            console.log('Bridge client initialized successfully');
            return true;

        } catch (error) {
            console.error('Failed to initialize bridge client:', error);
            this.handleError('Bridge initialization failed: ' + error.message);
            return false;
        }
    }

    /**
     * Initialize WebSocket connection
     */
    async initWebSocket() {
        try {
            // Load socket.io if not available
            if (typeof io === 'undefined') {
                await this.loadSocketIO();
            }

            this.socket = io(this.bridgeUrl);

            this.socket.on('connect', () => {
                console.log('🔗 Connected to bridge server');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.handleConnectionChange(true);
            });

            this.socket.on('disconnect', () => {
                console.log('🔗 Disconnected from bridge server');
                this.isConnected = false;
                this.handleConnectionChange(false);
                this.attemptReconnect();
            });

            this.socket.on('weight_update', (data) => {
                console.log('📈 Weight update received:', data);
                this.handleWeightUpdate(data);
            });

            this.socket.on('status', (status) => {
                console.log('📊 Status update received:', status);
                this.updateIndicatorsStatus(status);
            });

        } catch (error) {
            console.error('❌ WebSocket initialization failed:', error);
            throw error;
        }
    }

    /**
     * Load socket.io library dynamically
     */
    loadSocketIO() {
        return new Promise((resolve, reject) => {
            if (typeof io !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.0.1/socket.io.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Get available serial ports
     */
    async getAvailablePorts() {
        try {
            const response = await fetch(`${this.bridgeUrl}/api/ports`);
            const data = await response.json();
            return data.ports || [];
        } catch (error) {
            console.error('❌ Failed to get available ports:', error);
            return [];
        }
    }

    /**
     * Connect to specific indicator
     */
    async connectIndicator(indicatorId, port, baudRate = 9600) {
        try {
            const response = await fetch(`${this.bridgeUrl}/api/connect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    indicator_id: indicatorId,
                    port: port,
                    baud_rate: baudRate
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log(`✅ Connected to ${indicatorId} on ${port}`);
                this.indicators[indicatorId] = {
                    connected: true,
                    port: port,
                    weight: 0
                };
            } else {
                throw new Error(data.error || 'Connection failed');
            }

            return data.success;

        } catch (error) {
            console.error(`❌ Failed to connect to ${indicatorId}:`, error);
            this.handleError(`Connection error ${indicatorId}: ${error.message}`);
            return false;
        }
    }

    /**
     * Disconnect from specific indicator
     */
    async disconnectIndicator(indicatorId) {
        try {
            const response = await fetch(`${this.bridgeUrl}/api/disconnect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    indicator_id: indicatorId
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log(`✅ Disconnected from ${indicatorId}`);
                if (this.indicators[indicatorId]) {
                    this.indicators[indicatorId].connected = false;
                }
            }

            return data.success;

        } catch (error) {
            console.error(`❌ Failed to disconnect from ${indicatorId}:`, error);
            return false;
        }
    }

    /**
     * Get current weight for specific indicator
     */
    async getWeight(indicatorId) {
        try {
            const response = await fetch(`${this.bridgeUrl}/api/weight/${indicatorId}`);
            if (!response.ok) {
                throw new Error('Weight request failed');
            }

            const data = await response.json();
            return data.weight || 0;

        } catch (error) {
            console.error(`❌ Failed to get weight for ${indicatorId}:`, error);
            return 0;
        }
    }

    /**
     * Get connection status for all indicators
     */
    async getStatus() {
        try {
            const response = await fetch(`${this.bridgeUrl}/api/status`);
            const data = await response.json();
            return data.status || {};
        } catch (error) {
            console.error('❌ Failed to get status:', error);
            return {};
        }
    }

    /**
     * Register callback for weight updates
     */
    onWeightUpdate(callback) {
        this.callbacks.weightUpdate.push(callback);
    }

    /**
     * Register callback for connection changes
     */
    onConnectionChange(callback) {
        this.callbacks.connectionChange.push(callback);
    }

    /**
     * Register callback for errors
     */
    onError(callback) {
        this.callbacks.error.push(callback);
    }

    /**
     * Handle weight update from bridge
     */
    handleWeightUpdate(data) {
        // Update internal state
        if (this.indicators[data.indicator_id]) {
            this.indicators[data.indicator_id].weight = data.weight;
            this.indicators[data.indicator_id].last_update = data.timestamp;
        }

        // Call registered callbacks
        this.callbacks.weightUpdate.forEach(callback => {
            try {
                callback(data.indicator_id, data.weight, data.timestamp);
            } catch (error) {
                console.error('❌ Error in weight update callback:', error);
            }
        });
    }

    /**
     * Handle connection change
     */
    handleConnectionChange(connected) {
        this.callbacks.connectionChange.forEach(callback => {
            try {
                callback(connected);
            } catch (error) {
                console.error('❌ Error in connection change callback:', error);
            }
        });
    }

    /**
     * Handle errors
     */
    handleError(message) {
        this.callbacks.error.forEach(callback => {
            try {
                callback(message);
            } catch (error) {
                console.error('❌ Error in error callback:', error);
            }
        });
    }

    /**
     * Update indicators status
     */
    updateIndicatorsStatus(status) {
        Object.keys(status).forEach(indicatorId => {
            const indicatorStatus = status[indicatorId];
            this.indicators[indicatorId] = {
                ...this.indicators[indicatorId],
                connected: indicatorStatus.connected,
                weight: indicatorStatus.weight,
                last_update: indicatorStatus.last_update,
                time_since_update: indicatorStatus.time_since_update
            };
        });
    }

    /**
     * Start HTTP polling for Sonic bridge
     */
    startPolling() {
        this.isConnected = true;
        this.handleConnectionChange(true);

        this.pollingInterval = setInterval(async () => {
            try {
                const response = await fetch(`${this.bridgeUrl}/status`);
                if (response.ok) {
                    const data = await response.json();

                    if (data.connected && data.current_weight !== undefined) {
                        // Check if weight changed
                        if (Math.abs(data.current_weight - this.lastWeight) > 0.1) {
                            this.lastWeight = data.current_weight;
                            this.handleWeightUpdate('timbangan1', data.current_weight, Date.now() / 1000);
                        }
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 1000); // Poll every second
    }

    /**
     * Attempt to reconnect to bridge
     */
    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

            setTimeout(() => {
                this.initWebSocket();
            }, this.reconnectDelay);
        } else {
            console.error('Max reconnection attempts reached');
            this.handleError('Bridge connection lost - max reconnection attempts reached');
        }
    }

    /**
     * Start periodic weight polling (fallback)
     */
    startWeightPolling(indicatorId, interval = 2000) {
        if (this.weightUpdateInterval) {
            clearInterval(this.weightUpdateInterval);
        }

        this.weightUpdateInterval = setInterval(async () => {
            if (this.indicators[indicatorId] && this.indicators[indicatorId].connected) {
                const weight = await this.getWeight(indicatorId);
                if (weight !== this.indicators[indicatorId].weight) {
                    this.handleWeightUpdate({
                        indicator_id: indicatorId,
                        weight: weight,
                        timestamp: Date.now() / 1000
                    });
                }
            }
        }, interval);
    }

    /**
     * Stop weight polling
     */
    stopWeightPolling() {
        if (this.weightUpdateInterval) {
            clearInterval(this.weightUpdateInterval);
            this.weightUpdateInterval = null;
        }
    }

    /**
     * Check if indicator is connected
     */
    isIndicatorConnected(indicatorId) {
        if (this.bridgeType === 'sonic') {
            // For Sonic bridge, check if bridge is connected
            return this.isConnected;
        }
        return this.indicators[indicatorId] && this.indicators[indicatorId].connected;
    }

    /**
     * Get current weight of indicator
     */
    getIndicatorWeight(indicatorId) {
        if (this.bridgeType === 'sonic') {
            // For Sonic bridge, return last known weight
            return this.lastWeight;
        }
        return this.indicators[indicatorId] ? this.indicators[indicatorId].weight : 0;
    }

    /**
     * Disconnect from bridge
     */
    disconnect() {
        this.stopWeightPolling();

        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }

        if (this.socket) {
            this.socket.disconnect();
            this.socket = null;
        }

        this.isConnected = false;
        this.handleConnectionChange(false);
        console.log('Disconnected from bridge');
    }
}

// Global instance
window.TimbanganBridgeClient = TimbanganBridgeClient;