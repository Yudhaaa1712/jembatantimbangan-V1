class AutoSerialConnector {
    constructor(options = {}) {
        this.port = null;
        this.reader = null;
        this.writer = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
        this.reconnectInterval = options.reconnectInterval || 3000;
        this.autoReconnect = options.autoReconnect !== false;
        this.targetInputId = options.targetInputId || 'berat';
        this.callbacks = {
            onConnect: options.onConnect || (() => {}),
            onDisconnect: options.onDisconnect || (() => {}),
            onData: options.onData || (() => {}),
            onError: options.onError || (() => {})
        };

        this.serialConfig = {
            baudRate: 9600,
            dataBits: 8,
            stopBits: 1,
            parity: 'none',
            bufferSize: 1024
        };

        this.lastSavedPort = this.getSavedPort();
        this.readBuffer = '';
    }

    getSavedPort() {
        try {
            return localStorage.getItem('serialPortInfo');
        } catch {
            return null;
        }
    }

    savePort(portInfo) {
        try {
            if (portInfo) {
                localStorage.setItem('serialPortInfo', JSON.stringify(portInfo));
            }
        } catch {
            console.warn('Could not save port info to localStorage');
        }
    }

    async autoConnect() {
        if (this.lastSavedPort) {
            try {
                const ports = await navigator.serial.getPorts();
                const savedPortInfo = JSON.parse(this.lastSavedPort);

                for (const port of ports) {
                    if (port.getInfo().usbVendorId === savedPortInfo.usbVendorId &&
                        port.getInfo().usbProductId === savedPortInfo.usbProductId) {
                        await this.connect(port);
                        return true;
                    }
                }
            } catch (error) {
                console.warn('Auto-connect failed, will try manual connection:', error);
            }
        }

        return false;
    }

    async manualConnect() {
        try {
            this.port = await navigator.serial.requestPort();
            await this.connect(this.port);
            return true;
        } catch (error) {
            console.error('Manual connection failed:', error);
            this.callbacks.onError(error);
            return false;
        }
    }

    async connect(port) {
        try {
            if (!port) {
                throw new Error('No port provided');
            }

            // Cleanup existing connection if any
            await this.forceCleanup();

            this.port = port;
            await this.port.open(this.serialConfig);

            this.savePort(this.port.getInfo());
            this.isConnected = true;
            this.reconnectAttempts = 0;

            this.callbacks.onConnect();
            this.startReading();

            return true;
        } catch (error) {
            console.error('Connection failed:', error);
            // Special handling for "port already open" error
            if (error.message.includes('already open')) {
                console.log('Port already open, attempting cleanup...');
                await this.forceCleanup();
                // Retry connection after cleanup
                await new Promise(resolve => setTimeout(resolve, 500));
                return this.connect(port);
            }
            this.callbacks.onError(error);
            return false;
        }
    }

    startReading() {
        if (!this.port || !this.isConnected) return;

        const readLoop = async () => {
            try {
                while (this.port && this.port.readable && this.isConnected) {
                    this.reader = this.port.readable.getReader();

                    try {
                        while (true) {
                            const { value, done } = await this.reader.read();
                            if (done) break;

                            if (value) {
                                this.handleIncomingData(value);
                            }
                        }
                    } finally {
                        if (this.reader) {
                            try {
                                this.reader.releaseLock();
                            } catch (e) {
                                console.warn('Reader release lock error:', e);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Read error:', error);
                // Special handling for framing errors
                if (error.message.includes('FramingError')) {
                    console.log('Framing error detected, attempting to recover...');
                    // Don't immediately disconnect for framing errors, give it a chance to recover
                    setTimeout(() => {
                        if (this.isConnected) {
                            this.startReading(); // Restart reading
                        }
                    }, 1000);
                } else {
                    this.handleDisconnection();
                }
            }
        };

        readLoop();
    }

    handleIncomingData(uint8Array) {
        try {
            const text = new TextDecoder().decode(uint8Array);
            this.readBuffer += text;

            const lines = this.readBuffer.split(/[\r\n]+/);
            this.readBuffer = lines.pop() || '';

            // Prevent buffer from growing too large
            if (this.readBuffer.length > 1024) {
                this.readBuffer = this.readBuffer.slice(-512);
                console.warn('Buffer trimmed to prevent memory issues');
            }

            for (const line of lines) {
                if (line.trim()) {
                    try {
                        const weight = this.parseSonicA283Data(line.trim());
                        if (weight !== null && !isNaN(weight) && isFinite(weight)) {
                            this.updateWeightField(weight);
                            this.callbacks.onData(weight);
                        }
                    } catch (parseError) {
                        console.warn('Parse error for line:', line.trim(), parseError);
                    }
                }
            }
        } catch (error) {
            console.error('Data handling error:', error);
            // Reset buffer on serious errors
            this.readBuffer = '';
        }
    }

    parseSonicA283Data(data) {
        const cleanData = data.replace(/[^\d.\-+]/g, '');
        const weightMatch = cleanData.match(/[-+]?\d*\.?\d+/);

        if (weightMatch) {
            const weight = parseFloat(weightMatch[0]);
            return isNaN(weight) ? null : weight;
        }

        return null;
    }

    updateWeightField(weight) {
        const targetInput = document.getElementById(this.targetInputId);
        if (targetInput) {
            targetInput.value = weight.toFixed(2);
            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    handleDisconnection() {
        this.isConnected = false;
        this.callbacks.onDisconnect();

        if (this.autoReconnect && this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);

            setTimeout(async () => {
                // Force cleanup before reconnect attempt
                await this.forceCleanup();

                // Add extra delay for port to be fully released
                await new Promise(resolve => setTimeout(resolve, 1000));

                if (await this.autoConnect()) {
                    console.log('Reconnected successfully');
                } else {
                    this.handleDisconnection();
                }
            }, this.reconnectInterval);
        }
    }

    async disconnect() {
        this.autoReconnect = false;
        await this.forceCleanup();
    }

    async forceCleanup() {
        try {
            // Stop reading first
            this.isConnected = false;

            // Cancel reader with timeout
            if (this.reader) {
                try {
                    await Promise.race([
                        this.reader.cancel(),
                        new Promise((_, reject) =>
                            setTimeout(() => reject(new Error('Reader cancel timeout')), 2000)
                        )
                    ]);
                } catch (error) {
                    console.warn('Reader cancel error:', error.message);
                } finally {
                    try {
                        this.reader.releaseLock();
                    } catch (e) {
                        console.warn('Reader release lock error:', e);
                    }
                }
            }

            // Close writer
            if (this.writer) {
                try {
                    await this.writer.close();
                } catch (error) {
                    console.warn('Writer close error:', error.message);
                }
            }

            // Close port with timeout
            if (this.port) {
                try {
                    await Promise.race([
                        this.port.close(),
                        new Promise((_, reject) =>
                            setTimeout(() => reject(new Error('Port close timeout')), 3000)
                        )
                    ]);
                } catch (error) {
                    console.warn('Port close error:', error.message);
                }
            }

        } catch (error) {
            console.error('Cleanup error:', error);
        } finally {
            // Reset all variables
            this.port = null;
            this.reader = null;
            this.writer = null;
            this.readBuffer = '';
            this.isConnected = false;
        }
    }

    async sendCommand(command) {
        if (!this.port || !this.port.writable || !this.isConnected) {
            throw new Error('Port is not connected or not writable');
        }

        if (!this.writer) {
            this.writer = this.port.writable.getWriter();
        }

        try {
            const encoder = new TextEncoder();
            await this.writer.write(encoder.encode(command + '\r\n'));
        } finally {
            this.writer.releaseLock();
            this.writer = null;
        }
    }

    isSupported() {
        return 'serial' in navigator;
    }
}

window.AutoSerialConnector = AutoSerialConnector;