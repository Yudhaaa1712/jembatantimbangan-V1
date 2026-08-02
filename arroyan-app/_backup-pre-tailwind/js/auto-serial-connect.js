class AutoSerialConnector {
    constructor(options = {}) {
        console.log('🚀 AutoSerialConnector LOADED!');
        console.log('📋 Options received:', JSON.stringify(options));
        console.log('🔌 Serial API supported:', 'serial' in navigator);

        if (!('serial' in navigator)) {
            console.error('❌ FATAL: Web Serial API tidak tersedia!');
            console.error('💡 Pastikan menggunakan Chrome/Edge dan buka dengan HTTPS atau localhost');
        }

        this.port = null;
        this.reader = null;
        this.writer = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
        this.reconnectInterval = options.reconnectInterval || 3000;
        this.autoReconnect = options.autoReconnect !== false;
        this.targetInputId = options.targetInputId || 'berat';
        this.indicatorModel = options.indicatorModel || 'generic';
        this.customRegex = options.customRegex || '';
        this.callbacks = {
            onConnect: options.onConnect || (() => { }),
            onDisconnect: options.onDisconnect || (() => { }),
            onData: options.onData || (() => { }),
            onError: options.onError || (() => { })
        };

        // Load baud rate from localStorage first, then options, then default
        const savedBaudRate = this.getSavedBaudRate();

        this.serialConfig = {
            baudRate: savedBaudRate || options.baudRate || 9600,
            dataBits: 8,
            stopBits: 1,
            parity: 'none',
            flowControl: 'none',
            bufferSize: 1024
        };

        // Load saved port info for auto-reconnect
        this.lastSavedPort = this.getSavedPort();
        this.readBuffer = '';

        // Stabilization Filter
        this.weightHistory = [];
        this.historySize = 5; // Average over 5 frames to smooth data
        this.lastUpdateTime = 0; // For throttling UI updates
        this._lastWeight = 0;    // For tracking zero transition
    }

    getSavedPort() {
        try {
            return localStorage.getItem('serialPortInfo');
        } catch {
            return null;
        }
    }

    getSavedBaudRate() {
        try {
            const saved = localStorage.getItem('serialBaudRate');
            return saved ? parseInt(saved) : null;
        } catch {
            return null;
        }
    }

    savePort(portInfo) {
        try {
            if (portInfo) {
                localStorage.setItem('serialPortInfo', JSON.stringify(portInfo));
                this.lastSavedPort = JSON.stringify(portInfo);
            }
        } catch {
            console.warn('Could not save port info to localStorage');
        }
    }

    clearSavedPort() {
        try {
            localStorage.removeItem('serialPortInfo');
            this.lastSavedPort = null;
            console.log('🗑️ Saved port info cleared from localStorage');
        } catch {
            console.warn('Could not clear port info from localStorage');
        }
    }

    saveBaudRate(baudRate) {
        try {
            localStorage.setItem('serialBaudRate', baudRate.toString());
            this.serialConfig.baudRate = baudRate;
            console.log('✅ Baud rate saved:', baudRate);
        } catch {
            console.warn('Could not save baud rate to localStorage');
        }
    }

    // Update current baud rate without reconnecting
    updateBaudRate(baudRate) {
        this.serialConfig.baudRate = baudRate;
        this.saveBaudRate(baudRate);
    }

    async autoConnect(options = {}) {
        // GEMBOK: Jangan ganggu koneksi yang sudah aktif!
        if (this.isConnected && this.port) {
            console.log('🔒 Sudah terhubung, autoConnect dibatalkan.');
            return true;
        }

        const maxRetries = options.maxRetries || 5;
        const skipValidation = options.skipValidation || false;
        const forceScanAll = options.forceScanAll || false;
        const autoPrompt = options.autoPrompt !== false;

        console.log(`🚀 Auto-connect starting... (maxRetries: ${maxRetries}, skipValidation: ${skipValidation}, forceScanAll: ${forceScanAll}, autoPrompt: ${autoPrompt})`);

        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            console.log(`🔄 Auto-connect attempt ${attempt}/${maxRetries}...`);

            try {
                // Get previously authorized ports and filter out non-USB ports (like Bluetooth COM ports)
                // BUT allow physical COM ports (which have no usbVendorId) if they are explicitly authorized and saved,
                // or if there are no USB ports available at all.
                const allPorts = await navigator.serial.getPorts();
                
                let hasSavedPhysicalPort = false;
                try {
                    const saved = localStorage.getItem('serialPortInfo');
                    if (saved) {
                        const parsed = JSON.parse(saved);
                        if (parsed.usbVendorId === undefined || parsed.usbVendorId === null) {
                            hasSavedPhysicalPort = true;
                        }
                    }
                } catch (e) {}

                const ports = allPorts.filter(port => {
                    const info = port.getInfo();
                    const isUsb = info.usbVendorId !== undefined && info.usbVendorId !== null;
                    return isUsb || hasSavedPhysicalPort || !allPorts.some(p => {
                        const pInfo = p.getInfo();
                        return pInfo.usbVendorId !== undefined && pInfo.usbVendorId !== null;
                    });
                });

                if (ports.length === 0) {
                    console.log('📭 No previously authorized ports found');

                    // NEW: If autoPrompt is ON and this is last attempt, show port selection
                    if (autoPrompt && attempt === maxRetries) {
                        console.log('🔔 No ports found - will prompt user to select port...');
                        this.showNoPortNotification();
                    }

                    if (attempt < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        continue;
                    }
                    return false;
                }

                console.log(`🔍 Found ${ports.length} authorized port(s), trying to connect...`);

                // STRATEGY 1: If NOT forcing scan all, try saved port first
                if (!forceScanAll && this.lastSavedPort && attempt === 1) {
                    try {
                        const savedPortInfo = JSON.parse(this.lastSavedPort);

                        for (const port of ports) {
                            const portInfo = port.getInfo();
                            
                            // Check if both are physical/non-USB COM ports, or if their USB IDs match
                            const isPortPhysical = portInfo.usbVendorId === undefined || portInfo.usbVendorId === null;
                            const isSavedPhysical = savedPortInfo.usbVendorId === undefined || savedPortInfo.usbVendorId === null;
                            const isMatch = isPortPhysical && isSavedPhysical
                                ? true
                                : (portInfo.usbVendorId === savedPortInfo.usbVendorId && portInfo.usbProductId === savedPortInfo.usbProductId);

                            if (isMatch) {
                                console.log('✅ Found matching saved port, connecting...');

                                const success = skipValidation
                                    ? await this.tryConnectDirect(port)
                                    : await this.tryConnectWithValidation(port);

                                if (success) {
                                    console.log('🎉 Auto-connect successful via saved port!');
                                    return true;
                                }
                                console.log('⚠️ Saved port failed, clearing saved port info...');
                                // IMPORTANT: Clear saved port since it failed
                                this.clearSavedPort();
                            }
                        }
                    } catch (error) {
                        console.warn('Error matching saved port:', error);
                        this.clearSavedPort();
                    }
                }

                // STRATEGY 2: Try ALL available ports (for new cables/ports)
                console.log('🔄 Scanning ALL available ports for new connection...');
                for (let i = 0; i < ports.length; i++) {
                    const port = ports[i];
                    const portInfo = port.getInfo();
                    console.log(`📡 Trying port ${i + 1}/${ports.length} (VendorID: ${portInfo.usbVendorId || 'N/A'}, ProductID: ${portInfo.usbProductId || 'N/A'})...`);

                    // First cleanup any leftover state before trying each port
                    if (i > 0) {
                        await this.forceCleanup();
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }

                    const success = skipValidation
                        ? await this.tryConnectDirect(port)
                        : await this.tryConnectWithValidation(port);

                    if (success) {
                        console.log(`✅ Successfully connected to port ${i + 1}!`);
                        // Save the new working port
                        this.savePort(portInfo);
                        return true;
                    }

                    console.log(`❌ Port ${i + 1} failed, trying next...`);
                    await new Promise(resolve => setTimeout(resolve, 300));
                }

                console.log(`❌ Attempt ${attempt} failed, all ports failed to connect`);

            } catch (error) {
                console.warn(`Auto-connect attempt ${attempt} error:`, error);
            }

            // Wait before retry - with increasing delay
            if (attempt < maxRetries) {
                const waitTime = Math.min(1000 * attempt, 3000); // 1s, 2s, 3s...
                console.log(`⏳ Waiting ${waitTime}ms before retry...`);
                await new Promise(resolve => setTimeout(resolve, waitTime));
            }
        }

        console.log('❌ All auto-connect attempts failed');

        // Show notification to user that they need to select port
        if (autoPrompt) {
            this.showNoPortNotification();
        }
        return false;
    }

    // Direct connect without waiting for valid weight data (faster)
    async tryConnectDirect(port) {
        // GEMBOK: Jangan ganggu koneksi yang sudah aktif!
        if (this.isConnected && this.port) {
            console.log('🔒 Sudah terhubung, tryConnectDirect dibatalkan.');
            return true;
        }
        // Try up to 5 times for single port open (handling "busy" state)
        for (let i = 0; i < 5; i++) {
            try {
                // Ensure clean state
                if (i > 0) {
                    console.log(`⏳ Retry open port (attempt ${i + 1}/5)...`);
                    // Increasing delay for each retry
                    await new Promise(resolve => setTimeout(resolve, 500 + (i * 500)));
                }

                // AGGRESSIVE: Try to close this specific port first if it's open
                try {
                    if (port.readable || port.writable) {
                        console.log('🔒 Port appears to be open, trying to close first...');

                        // Cancel any active readers
                        if (port.readable) {
                            try {
                                const reader = port.readable.getReader();
                                await reader.cancel().catch(() => { });
                                reader.releaseLock();
                            } catch (e) { }
                        }

                        // Close the port
                        await port.close().catch(() => { });
                        console.log('✅ Port pre-closed');
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                } catch (closeError) {
                    // Ignore close errors, port might already be closed
                }

                // Force closing any previous connection instance from this class
                await this.forceCleanup();

                this.port = port;
                console.log('🔌 Opening port directly...', JSON.stringify(this.serialConfig));

                await this.port.open(this.serialConfig);

                console.log('✅ Port opened successfully!');
                this.savePort(this.port.getInfo());
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.callbacks.onConnect();
                this.startReading();
                return true;

            } catch (error) {
                console.warn(`Attempt ${i + 1} open failed:`, error.message);

                // If denied explicitly, stop retrying
                if (error.name === 'SecurityError' || error.name === 'NotFoundError') {
                    return false;
                }

                // If "already open" error, try more aggressive cleanup
                if (error.message && error.message.includes('already open')) {
                    console.log('🔧 Port already open - attempting aggressive cleanup...');
                    try {
                        // Try closing via the port object directly
                        await port.close().catch(() => { });
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    } catch (e) { }
                }

                // If busy/locked, we loop and retry
            }
        }
        return false;
    }

    // New method: Try to connect to a port and validate it receives weight data
    async tryConnectWithValidation(port) {
        try {
            // Cleanup any existing connection
            await this.forceCleanup();
            await new Promise(resolve => setTimeout(resolve, 300));

            this.port = port;

            console.log('🔌 Opening port with config:', JSON.stringify(this.serialConfig));

            try {
                await this.port.open(this.serialConfig);
            } catch (openError) {
                console.warn('Port open failed:', openError.message);
                return false;
            }

            console.log('✅ Port opened, waiting for data validation...');

            // Wait for valid weight data (timeout after 3 seconds)
            const receivedValidData = await this.waitForValidData(3000);

            if (receivedValidData) {
                // Port is working! Save it and set up properly
                this.savePort(this.port.getInfo());
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.callbacks.onConnect();
                // Start reading is already running from waitForValidData
                return true;
            } else {
                // Port opened but no valid data received
                console.log('⚠️ No valid weight data received from this port');
                await this.forceCleanup();
                return false;
            }

        } catch (error) {
            console.warn('tryConnectWithValidation error:', error.message);
            await this.forceCleanup();
            return false;
        }
    }

    // Wait for valid weight data from the port
    async waitForValidData(timeoutMs) {
        return new Promise(async (resolve) => {
            let hasValidData = false;
            let timeoutId;

            // Set timeout
            timeoutId = setTimeout(() => {
                if (!hasValidData) {
                    console.log('⏱️ Timeout waiting for valid data');
                    resolve(false);
                }
            }, timeoutMs);

            try {
                if (!this.port || !this.port.readable) {
                    clearTimeout(timeoutId);
                    resolve(false);
                    return;
                }

                this.reader = this.port.readable.getReader();
                let tempBuffer = '';

                // Read loop with timeout
                const readWithTimeout = async () => {
                    while (!hasValidData) {
                        try {
                            const { value, done } = await this.reader.read();
                            if (done) break;

                            if (value) {
                                // Strip 8th bit (parity) for 7-bit ASCII compatibility
                                for (let i = 0; i < value.length; i++) {
                                    value[i] = value[i] & 0x7F;
                                }
                                const text = new TextDecoder().decode(value);
                                
                                // Normalize STX, ETX, and '=' to newlines
                                let cleanText = text.replace(/[\x02\x03=]/g, '\n');
                                tempBuffer += cleanText;

                                // Check for newlines
                                const lines = tempBuffer.split(/[\r\n]+/);
                                tempBuffer = lines.pop() || '';

                                // If buffer grows without newlines, force parse
                                if (tempBuffer.length > 50) {
                                    lines.push(tempBuffer);
                                    tempBuffer = '';
                                }

                                for (const line of lines) {
                                    if (line.trim()) {
                                        const weight = this.parseSonicA283Data(line.trim());
                                        if (weight !== null && !isNaN(weight) && isFinite(weight)) {
                                            console.log('✅ Received valid weight data:', weight);
                                            hasValidData = true;
                                            clearTimeout(timeoutId);

                                            // Release reader and restart proper reading
                                            try {
                                                this.reader.releaseLock();
                                            } catch (e) { }

                                            // Start the real reading loop
                                            this.readBuffer = tempBuffer;
                                            this.startReading();

                                            resolve(true);
                                            return;
                                        }
                                    }
                                }
                            }
                        } catch (readError) {
                            console.warn('Read error during validation:', readError.message);
                            break;
                        }
                    }
                };

                await readWithTimeout();

            } catch (error) {
                console.warn('waitForValidData error:', error.message);
                clearTimeout(timeoutId);
                if (this.reader) {
                    try {
                        this.reader.releaseLock();
                    } catch (e) { }
                }
                resolve(false);
            }
        });
    }

    async manualConnect() {
        try {
            console.log('🔄 Meminta otorisasi port secara manual...');
            
            // Setup IPC listener if in electron
            if (window.electronAPI && window.electronAPI.onPortSelector && !this._portSelectorAttached) {
                this._portSelectorAttached = true;
                window.electronAPI.onPortSelector((portList) => {
                    if (typeof Swal !== 'undefined') {
                        let options = {};
                        portList.forEach(port => {
                            options[port.portId] = port.portName + (port.displayName ? ` (${port.displayName})` : '');
                        });
                        
                        Swal.fire({
                            title: 'Pilih Port Timbangan',
                            input: 'select',
                            inputOptions: options,
                            inputPlaceholder: 'Pilih Port COM',
                            showCancelButton: true,
                            confirmButtonText: 'Sambung',
                            cancelButtonText: 'Batal',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed && result.value) {
                                window.electronAPI.selectPort(result.value);
                            } else {
                                window.electronAPI.selectPort(''); // Cancel
                            }
                        });
                    } else {
                        let msg = 'Ketik ID port yang akan disambung:\\n';
                        portList.forEach(p => msg += `${p.portId}: ${p.portName}\\n`);
                        let pId = prompt(msg);
                        window.electronAPI.selectPort(pId || '');
                    }
                });
            }

            // GEMBOK: Kalau sudah terhubung, jangan konek ulang
            if (this.isConnected && this.port) {
                console.log('🔒 Sudah terhubung, tidak perlu konek ulang.');
                return true;
            }

            // Call requestPort without filters to allow choosing physical motherboard COM ports as well as USB ones.
            const selectedPort = await navigator.serial.requestPort();
            if (selectedPort) {
                this.clearSavedPort();
                const success = await this.connect(selectedPort);
                return success;
            }
            return false;
        } catch (error) {
            console.error('Manual connection failed:', error);
            this.callbacks.onError(error);
            return false;
        }
    }

    // DISABLED: Popup notification for new cable - was too intrusive
    // User can manually click "SAMBUNGKAN" button instead
    showNoPortNotification() {
        // Just log to console, don't show popup
        console.log('ℹ️ No port found - user can click SAMBUNGKAN button to connect manually');

        // Optionally highlight the connect button silently (no popup)
        const connectBtn = document.getElementById('toggleConnection') ||
            document.getElementById('toggleConnection2');
        if (connectBtn) {
            // Subtle highlight without annoying popup
            connectBtn.style.boxShadow = '0 0 15px rgba(34, 197, 94, 0.5)';
            setTimeout(() => {
                connectBtn.style.boxShadow = '';
            }, 3000);
        }
    }

    async connect(port) {
        // GEMBOK: Jangan ganggu koneksi yang sudah aktif!
        if (this.isConnected && this.port) {
            console.log('🔒 Sudah terhubung, connect() dibatalkan.');
            return true;
        }

        try {
            if (!port) {
                throw new Error('No port provided');
            }

            // Cleanup existing connection if any
            console.log('🧹 Cleaning up existing connections...');
            await this.forceCleanup();

            // Add delay to ensure port is fully released
            await new Promise(resolve => setTimeout(resolve, 500));

            this.port = port;

            // DEBUG: Log the actual config being used
            console.log('🔌 Opening port with config:', JSON.stringify(this.serialConfig));

            try {
                await this.port.open(this.serialConfig);
            } catch (openError) {
                console.error('❌ Port open error:', openError.message);

                // IMPORTANT: Clear saved port so next time it won't try this broken port
                this.clearSavedPort();
                console.log('🗑️ Cleared saved port - please select port again');

                // If port open fails, it might be in use
                if (openError.message.includes('Failed to open serial port')) {
                    console.error('❌ Port is busy or in use by another application!');
                    throw new Error('Port gagal dibuka. Kemungkinan: 1) Port digunakan aplikasi lain, 2) Port sudah tidak ada. Klik CONNECT untuk pilih ulang port.');
                }
                throw openError;
            }

            console.log('✅ Port opened successfully!');
            this.savePort(this.port.getInfo());
            this.isConnected = true;
            this.reconnectAttempts = 0;

            this.callbacks.onConnect();
            this.startReading();

            return true;
        } catch (error) {
            console.error('Connection failed:', error.message || error);
            this.callbacks.onError(error);
            return false;
        }
    }

    startReading() {
        if (!this.port || !this.isConnected) {
            console.warn('⚠️ startReading dibatalkan: port=', !!this.port, 'isConnected=', this.isConnected);
            return;
        }

        console.log('📖 startReading() dimulai... port.readable=', !!this.port.readable);

        const readLoop = async () => {
            try {
                while (this.port && this.port.readable && this.isConnected) {
                    this.reader = this.port.readable.getReader();
                    console.log('📖 Reader dibuat, menunggu data dari indikator...');

                    try {
                        while (true) {
                            const { value, done } = await this.reader.read();
                            if (done) {
                                console.log('📖 Reader done signal received');
                                break;
                            }

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
                console.error('Read error:', error.message || error);
                
                // Jangan langsung putus koneksi! Coba restart pembacaan dulu.
                // Hanya putus jika port benar-benar hilang/dicabut.
                const msg = (error.message || '').toLowerCase();
                const isFatalError = msg.includes('device has been lost') || 
                                     msg.includes('port is no longer') ||
                                     msg.includes('the device has been disconnected');
                
                if (isFatalError) {
                    console.log('💔 Port dicabut/hilang, memutuskan koneksi...');
                    this.handleDisconnection();
                } else {
                    // Error non-fatal (FramingError, BufferOverflow, dll) → restart baca
                    console.log('⚠️ Error non-fatal, restart pembacaan dalam 1 detik...');
                    setTimeout(() => {
                        if (this.isConnected && this.port) {
                            this.startReading();
                        }
                    }, 1000);
                }
            }
        };

        readLoop();
    }

    handleIncomingData(uint8Array) {
        try {
            // Strip 8th bit (parity) for 7-bit ASCII compatibility (fixes Even/Odd parity issues)
            for (let i = 0; i < uint8Array.length; i++) {
                uint8Array[i] = uint8Array[i] & 0x7F;
            }
            const text = new TextDecoder().decode(uint8Array);
            
            // Normalize STX, ETX, and '=' to newlines to force splitting
            let cleanText = text.replace(/[\x02\x03=]/g, '\n');
            this.readBuffer += cleanText;

            const lines = this.readBuffer.split(/[\r\n]+/);
            this.readBuffer = lines.pop() || '';

            // If buffer gets too long without a newline, treat it as a line
            if (this.readBuffer.length > 50) {
                lines.push(this.readBuffer);
                this.readBuffer = '';
            }

            // Prevent buffer from growing too large (fallback)
            if (this.readBuffer.length > 1024) {
                this.readBuffer = this.readBuffer.slice(-512);
                console.warn('Buffer trimmed to prevent memory issues');
            }

            for (const line of lines) {
                if (line.trim()) {
                    try {
                        const weight = this.parseSonicA283Data(line.trim());
                        if (weight !== null && !isNaN(weight) && isFinite(weight)) {
                            const now = Date.now();

                            if (weight <= 0) {
                                // ── Berat NOL: update display 1x saja ──
                                // Saat berat turun ke 0, kirim update sekali
                                // supaya display menampilkan "0 KG", lalu berhenti.
                                if (this._lastWeight > 0) {
                                    this._lastWeight = 0;
                                    this.updateWeightField(0);
                                    this.callbacks.onData(0);
                                }
                                // Kalau sudah 0 sebelumnya, skip (hemat resource)
                            } else {
                                // ── Ada berat: update normal (throttle 200ms) ──
                                this._lastWeight = weight;
                                if (now - this.lastUpdateTime > 200) {
                                    this.lastUpdateTime = now;
                                    this.updateWeightField(weight);
                                    this.callbacks.onData(weight);
                                }
                            }
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
        // Debug: log raw data
        console.log(`📨 [${this.indicatorModel}] Raw serial frame: "${data}"`);

        if (this.indicatorModel === 'gsc') {
            // GSC GST-9600 format: "ST,GS,+0012500kg\r\n" or similar
            const match = data.match(/GS,\s*([+-]?\d+)/i) || data.match(/([+-]?\d+)/);
            if (match && match[1]) {
                const weight = parseFloat(match[1]);
                if (!isNaN(weight) && weight >= -100000 && weight <= 100000) return weight;
            }
        } else if (this.indicatorModel === 'cas') {
            // CAS CI-2001 format: "ST,NT,+  1250.0  kg" or "ST,GS,+  1250.0  kg"
            const match = data.match(/[GN]T,\s*([+-]?\s*\d+\.?\d*)/i) || data.match(/([+-]?\s*\d+\.?\d*)/);
            if (match && match[1]) {
                const numStr = match[1].replace(/\s/g, '');
                const weight = parseFloat(numStr);
                if (!isNaN(weight) && weight >= -100000 && weight <= 100000) return weight;
            }
        } else if (this.indicatorModel === 'boston' || this.indicatorModel === 'genwin') {
            // Boston / Genwin continuous mode format: "+0012500" or raw number string
            const match = data.match(/([+-]?\d+)/);
            if (match && match[1]) {
                const weight = parseFloat(match[1]);
                if (!isNaN(weight) && weight >= -100000 && weight <= 100000) return weight;
            }
        } else if (this.indicatorModel === 'custom' && this.customRegex) {
            // Custom RegEx parser provided by user
            try {
                const rx = new RegExp(this.customRegex);
                const match = data.match(rx);
                if (match) {
                    const val = match[1] || match[0];
                    const numStr = val.replace(/[^\d.-]/g, ''); // strip non-numeric
                    const weight = parseFloat(numStr);
                    if (!isNaN(weight) && weight >= -100000 && weight <= 100000) return weight;
                }
            } catch (rxErr) {
                console.error('❌ Error custom regex parsing:', rxErr.message);
            }
        }

        // Default: generic/heuristics (patterns from original code)
        const patterns = [
            /([+-]?\s*\d+\.?\d*)\s*[Kk][Gg]/,  // with KG suffix
            /([+-]?\d+\.?\d*)\s*$/,             // number at end of line
            /[a-zA-Z,\s]+([+-]?\d+\.?\d*)/,     // after letters/commas (case-insensitive)
            /(?:=|\x02)?([+-]?\s*\d+\.?\d*)/,   // STX or = prefix
            /([+-]?\d{1,}\.?\d*)/               // any number fallback
        ];

        for (const pattern of patterns) {
            const match = data.match(pattern);
            if (match && match[1]) {
                const numStr = match[1].replace(/\s/g, '');
                const weight = parseFloat(numStr);

                if (!isNaN(weight) && isFinite(weight)) {
                    if (weight >= -100000 && weight <= 100000) {
                        return weight;
                    }
                }
            }
        }

        return null;
    }

    updateWeightField(weight) {
        // Jangan update input field jika berat sudah di-capture (terkunci)
        if (this.targetInputId === 'beratInputForm' && window.isWeightLocked) return;
        if (this.targetInputId === 'beratInput2' && window.isWeightLocked2) return;

        const targetInput = document.getElementById(this.targetInputId);
        if (targetInput) {
            const newWeight = Math.floor(weight);
            if (targetInput.value !== String(newWeight)) {
                targetInput.value = newWeight;
                // Only dispatch 'input' event to prevent double triggering
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
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
        console.log('🧹 Starting force cleanup...');

        try {
            // Stop reading first
            this.isConnected = false;

            // Cancel reader with timeout - MORE AGGRESSIVE
            if (this.reader) {
                console.log('🔄 Cancelling reader...');
                try {
                    // Try cancel first
                    const cancelPromise = this.reader.cancel().catch(() => { });
                    await Promise.race([
                        cancelPromise,
                        new Promise(resolve => setTimeout(resolve, 1000))
                    ]);
                } catch (error) {
                    // Ignore cancel errors
                }

                // Always try to release lock
                try {
                    this.reader.releaseLock();
                    console.log('✅ Reader lock released');
                } catch (e) {
                    // Ignore release errors
                }
                this.reader = null;
            }

            // Close writer
            if (this.writer) {
                console.log('🔄 Closing writer...');
                try {
                    this.writer.releaseLock();
                } catch (error) {
                    // Ignore
                }
                this.writer = null;
            }

            // Close port with timeout - MORE AGGRESSIVE
            if (this.port) {
                console.log('🔄 Closing port...');

                // First, try to abort any readable stream
                if (this.port.readable) {
                    try {
                        // This helps release the port
                        const reader = this.port.readable.getReader();
                        await reader.cancel().catch(() => { });
                        reader.releaseLock();
                    } catch (e) {
                        // Ignore
                    }
                }

                // Now close the port
                try {
                    await Promise.race([
                        this.port.close(),
                        new Promise(resolve => setTimeout(resolve, 2000))
                    ]);
                    console.log('✅ Port closed successfully');
                } catch (error) {
                    if (error.message && error.message.includes('already closed')) {
                        console.log('ℹ️ Port already closed');
                    } else {
                        console.warn('Port close warning:', error.message || error);
                    }
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
            console.log('✅ Cleanup complete');
        }
    }

    // Forget the port permission completely (useful when port is stuck)
    async forgetPort() {
        console.log('🗑️ Forgetting port...');

        await this.forceCleanup();

        try {
            // Get all ports and forget them
            const ports = await navigator.serial.getPorts();
            for (const port of ports) {
                try {
                    // Try to close if open
                    if (port.readable || port.writable) {
                        await port.close().catch(() => { });
                    }
                    // Forget the port permission
                    if (port.forget) {
                        await port.forget();
                        console.log('✅ Port forgotten');
                    }
                } catch (e) {
                    console.warn('Forget error:', e);
                }
            }

            // Clear saved port info
            this.clearSavedPort();

        } catch (error) {
            console.error('Forget port error:', error);
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

// =====================================================
// GLOBAL CLEANUP: Ensure port is closed when navigating away
// =====================================================
let globalSerialConnector = null;
let isCleaningUp = false;

// Register the active connector globally for cleanup
window.registerSerialConnector = function (connector) {
    globalSerialConnector = connector;
    console.log('📝 Serial connector registered for global cleanup');
};

// Synchronous cleanup for navigation (best effort)
function syncCleanup() {
    if (isCleaningUp) return;
    isCleaningUp = true;

    if (globalSerialConnector) {
        console.log('🧹 Sync cleanup triggered...');

        // Try to release reader lock immediately
        if (globalSerialConnector.reader) {
            try {
                globalSerialConnector.reader.releaseLock();
            } catch (e) { }
        }

        // Try to release writer lock immediately  
        if (globalSerialConnector.writer) {
            try {
                globalSerialConnector.writer.releaseLock();
            } catch (e) { }
        }

        // Try to close port (this may fail but we try)
        if (globalSerialConnector.port) {
            try {
                // Schedule close - it may or may not complete
                globalSerialConnector.port.close().catch(() => { });
            } catch (e) { }
        }

        globalSerialConnector.isConnected = false;
    }
}

// Async cleanup function
async function globalSerialCleanup() {
    if (isCleaningUp) return;

    if (globalSerialConnector && globalSerialConnector.isConnected) {
        console.log('🧹 Page navigation detected - closing serial port...');
        try {
            await globalSerialConnector.forceCleanup();
            console.log('✅ Serial port closed successfully');
        } catch (e) {
            console.warn('Cleanup error during navigation:', e);
        }
    }
}

// IMPORTANT: Use synchronous handlers for beforeunload/unload
// because async operations may not complete
window.addEventListener('beforeunload', function (e) {
    syncCleanup();
});

window.addEventListener('pagehide', function (e) {
    syncCleanup();
});

window.addEventListener('unload', function (e) {
    syncCleanup();
});

// Also cleanup when visibility changes (tab switch)
document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
        console.log('📋 Tab hidden - serial connection maintained');
    }
});

// Navigation cleanup is handled by beforeunload/pagehide events above.
// No need to intercept clicks - navigasi langsung tanpa jeda.

// Helper function to close all open ports (can be called manually)
window.closeAllSerialPorts = async function () {
    console.log('🔌 Closing all serial ports...');

    try {
        const ports = await navigator.serial.getPorts();
        for (const port of ports) {
            try {
                // First, cancel any active readers
                if (port.readable) {
                    try {
                        const reader = port.readable.getReader();
                        await reader.cancel().catch(() => { });
                        reader.releaseLock();
                        console.log('✅ Reader cancelled');
                    } catch (e) { }
                }

                // Then close the port
                if (port.readable || port.writable) {
                    await port.close();
                    console.log('✅ Port closed');
                }
            } catch (e) {
                if (!e.message?.includes('already closed')) {
                    console.warn('Close error:', e.message);
                }
            }
        }
    } catch (e) {
        console.error('Error closing ports:', e);
    }

    if (globalSerialConnector) {
        globalSerialConnector.port = null;
        globalSerialConnector.reader = null;
        globalSerialConnector.writer = null;
        globalSerialConnector.isConnected = false;
    }

    console.log('✅ All ports closed');
};

// =====================================================
// PORT CHANGE DETECTION - Auto-detect new USB cables
// =====================================================
let knownPortCount = 0;
let portWatcherInterval = null;

// Start watching for new ports
window.startPortWatcher = function () {
    if (portWatcherInterval) return; // Already running

    console.log('👁️ Starting port watcher for new USB devices...');

    // Initialize known port count (USB ports only)
    navigator.serial.getPorts().then(ports => {
        const usbPorts = ports.filter(port => {
            const info = port.getInfo();
            return info.usbVendorId !== undefined && info.usbVendorId !== null;
        });
        knownPortCount = usbPorts.length;
        console.log(`📊 Initial known USB ports: ${knownPortCount}`);
    });

    // Check for new ports every 2 seconds
    portWatcherInterval = setInterval(async () => {
        try {
            const allPorts = await navigator.serial.getPorts();
            const currentPorts = allPorts.filter(port => {
                const info = port.getInfo();
                return info.usbVendorId !== undefined && info.usbVendorId !== null;
            });

            // If there are MORE ports than before, a new device was connected
            if (currentPorts.length > knownPortCount) {
                console.log(`🔌 New USB device detected! (${knownPortCount} → ${currentPorts.length})`);
                knownPortCount = currentPorts.length;

                // If not connected, try to auto-connect with force scan
                if (globalSerialConnector && !globalSerialConnector.isConnected) {
                    console.log('🚀 Auto-connecting to new device...');

                    // First cleanup any stale connections
                    await globalSerialConnector.forceCleanup();
                    await new Promise(resolve => setTimeout(resolve, 500));

                    // Try to auto-connect, forcing a scan of all ports
                    const success = await globalSerialConnector.autoConnect({
                        skipValidation: true,
                        maxRetries: 3,
                        forceScanAll: true // Force scan all, don't rely on saved port
                    });

                    if (success) {
                        console.log('🎉 Auto-connected to new device!');

                        // Show notification
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Kabel baru terdeteksi, otomatis terhubung!',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    }
                }
            } else if (currentPorts.length < knownPortCount) {
                // A device was disconnected
                console.log(`⚠️ USB device disconnected! (${knownPortCount} → ${currentPorts.length})`);
                knownPortCount = currentPorts.length;
            }
        } catch (error) {
            console.warn('Port watcher error:', error);
        }
    }, 2000);
};

// Stop watching for new ports
window.stopPortWatcher = function () {
    if (portWatcherInterval) {
        clearInterval(portWatcherInterval);
        portWatcherInterval = null;
        console.log('🛑 Port watcher stopped');
    }
};

// Auto-start port watcher when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(window.startPortWatcher, 2000);
    });
} else {
    setTimeout(window.startPortWatcher, 2000);
}

// Also listen to navigator.serial connect/disconnect events (if supported)
if ('serial' in navigator) {
    navigator.serial.addEventListener('connect', async (event) => {
        const portInfo = event.port ? event.port.getInfo() : {};
        if (portInfo.usbVendorId === undefined || portInfo.usbVendorId === null) {
            console.log('📡 Ignored non-USB device connection (e.g. Bluetooth).');
            return;
        }
        console.log('🔌 USB Serial device connected (native event)!');

        // Auto-connect if possible
        if (globalSerialConnector && !globalSerialConnector.isConnected) {
            await new Promise(resolve => setTimeout(resolve, 1000)); // Wait for port to stabilize

            const success = await globalSerialConnector.autoConnect({
                skipValidation: true,
                maxRetries: 3,
                forceScanAll: true
            });

            if (success) {
                console.log('🎉 Auto-connected via native event!');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Indikator terhubung otomatis!',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            }
        }
    });

    navigator.serial.addEventListener('disconnect', (event) => {
        const portInfo = event.port ? event.port.getInfo() : {};
        if (portInfo.usbVendorId === undefined || portInfo.usbVendorId === null) {
            console.log('📡 Ignored non-USB device disconnection (e.g. Bluetooth).');
            return;
        }
        console.log('⚠️ USB Serial device disconnected (native event)!');

        if (globalSerialConnector && globalSerialConnector.isConnected) {
            console.log('🔄 Handling disconnection...');
            globalSerialConnector.isConnected = false;
            if (globalSerialConnector.callbacks && globalSerialConnector.callbacks.onDisconnect) {
                globalSerialConnector.callbacks.onDisconnect();
            }
        }
    });
}