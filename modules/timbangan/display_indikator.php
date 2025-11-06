<?php
// Include security and session
require_once __DIR__ . '/../../includes/security_functions.php';
require_once __DIR__ . '/../../includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Indikator Timbangan - Sistem Jembatan Timbangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .weight-display-main {
            font-size: 5rem;
            font-weight: 700;
            color: #2563eb;
            text-align: center;
            font-family: 'Courier New', monospace;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .status-online {
            color: #10b981;
            animation: blink 1s infinite;
        }

        .status-offline {
            color: #ef4444;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .data-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .terminal-log {
            background: #1f2937;
            color: #10b981;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            height: 300px;
            overflow-y: auto;
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid #374151;
        }

        .connection-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .btn-timbangan {
            background: linear-gradient(45deg, #10b981, #059669);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .btn-timbangan:hover {
            background: linear-gradient(45deg, #059669, #047857);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3);
        }

        .btn-disconnect {
            background: linear-gradient(45deg, #ef4444, #dc2626);
        }

        .btn-disconnect:hover {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);
        }

        .weight-card {
            background: linear-gradient(135deg, #f3f4f6, #ffffff);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .weight-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #3b82f6;
        }

        .history-item {
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border-left: 3px solid #10b981;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center text-white">
                    <h1 class="display-4 mb-2">
                        <i class="fas fa-weight-hanging"></i>
                        Display Indikator Timbangan
                    </h1>
                    <p class="lead">Sistem Jembatan Timbangan Digital</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Control Panel -->
            <div class="col-lg-3 mb-4">
                <div class="data-panel p-4">
                    <h5 class="mb-4">
                        <i class="fas fa-cogs text-primary"></i>
                        Kontrol Koneksi
                    </h5>

                    <div class="d-grid mb-4">
                        <button id="connectBtn" class="btn btn-timbangan">
                            <i class="fas fa-plug"></i>
                            Connect Indikator
                        </button>
                        <button id="disconnectBtn" class="btn btn-timbangan btn-disconnect" disabled>
                            <i class="fas fa-times"></i>
                            Disconnect
                        </button>
                    </div>

                    <!-- Auto-Reconnect Toggle -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoReconnectToggle" checked>
                            <label class="form-check-label" for="autoReconnectToggle">
                                <i class="fas fa-sync-alt"></i>
                                Auto-Reconnect
                            </label>
                        </div>
                        <small class="text-muted">Otomatis reconnect saat halaman refresh</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status Koneksi:</label>
                        <div id="connectionStatus" class="d-flex align-items-center">
                            <i class="fas fa-circle status-offline me-2"></i>
                            <span>Not Connected</span>
                        </div>
                    </div>

                    <!-- Reconnect Status -->
                    <div class="mb-3" id="reconnectStatus" style="display: none;">
                        <label class="form-label">Reconnect Status:</label>
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <span id="reconnectText">Menghubungkan ulang...</span>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div id="reconnectProgress" class="progress-bar bg-warning" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Method:</label>
                        <div>
                            <span id="connectionMethod" class="connection-badge bg-secondary">None</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Update:</label>
                        <div id="lastUpdate" class="text-muted small">No data</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Connection ID:</label>
                        <div id="connectionId" class="text-muted small">-</div>
                    </div>
                </div>

                <!-- System Info -->
                <div class="data-panel p-4 mt-3">
                    <h6 class="mb-3">
                        <i class="fas fa-info-circle text-info"></i>
                        Informasi Sistem
                    </h6>
                    <div class="info-grid">
                        <div class="info-item">
                            <small class="text-muted">Browser</small>
                            <div id="browserSupport" class="fw-bold">Checking...</div>
                        </div>
                        <div class="info-item">
                            <small class="text-muted">Bridge</small>
                            <div id="bridgeStatus" class="fw-bold">Not checked</div>
                        </div>
                        <div class="info-item">
                            <small class="text-muted">Port</small>
                            <div id="portType" class="fw-bold">Unknown</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weight Display -->
            <div class="col-lg-9 mb-4">
                <div class="weight-card">
                    <div class="text-center">
                        <h3 class="mb-4">
                            <i class="fas fa-tachometer-alt text-primary"></i>
                            Berat Saat Ini
                        </h3>

                        <div id="currentWeight" class="weight-display-main mb-4">
                            0.00 Kg
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                        <h6>Status Stabil</h6>
                                        <div id="stableStatus" class="h5 text-success">Stabil</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-weight text-primary fa-2x mb-2"></i>
                                        <h6>Satuan</h6>
                                        <div class="h5 text-primary">Kilogram</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-history text-info fa-2x mb-2"></i>
                                        <h6>Total Pembacaan</h6>
                                        <div id="totalReadings" class="h5 text-info">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Log and History -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="data-panel p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-terminal text-success"></i>
                                Data Log
                            </h5>
                            <div id="dataLog" class="terminal-log">
                                Menunggu koneksi ke indikator timbangan...
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="data-panel p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-history text-primary"></i>
                                Riwayat Pembacaan
                            </h5>
                            <div id="weightHistory" style="height: 300px; overflow-y: auto;">
                                <p class="text-muted text-center">Belum ada data pembacaan</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/web-serial-api.js"></script>
    <script src="../../assets/js/bridge-client.js"></script>

    <script>
        class DisplayIndikatorTimbangan {
            constructor() {
                this.weightHistory = [];
                this.maxHistory = 15;
                this.isConnected = false;
                this.connectionMethod = null;
                this.currentWeight = 0;
                this.totalReadings = 0;
                this.lastStableWeight = 0;
                this.stableCount = 0;
                this.stableThreshold = 3;
                this.autoReconnect = true;
                this.reconnectAttempts = 0;
                this.maxReconnectAttempts = 5;
                this.reconnectDelay = 2000;
                this.isReconnecting = false;
                this.connectionStateKey = 'timbangan_connection_state';
                this.reconnectTimer = null;

                this.init();
            }

            init() {
                this.checkSystemCompatibility();
                this.setupEventListeners();
                this.initializeConnections();
                this.startRealTimeClock();
                this.loadConnectionState();
                this.log('Sistem Display Indikator Timbangan siap', 'info');

                // Auto-reconnect if was previously connected
                if (this.shouldAutoReconnect()) {
                    this.log('🔄 Mendeteksi koneksi sebelumnya, mencoba reconnect otomatis...', 'info');
                    setTimeout(() => this.autoConnect(), 1000);
                }
            }

            checkSystemCompatibility() {
                // Check Web Serial API support
                const serialSupported = 'serial' in navigator;
                const browserInfo = navigator.userAgent;
                let browserName = 'Unknown';

                if (browserInfo.includes('Chrome')) browserName = 'Chrome';
                else if (browserInfo.includes('Edge')) browserName = 'Edge';
                else if (browserInfo.includes('Firefox')) browserName = 'Firefox';
                else if (browserInfo.includes('Safari')) browserName = 'Safari';

                document.getElementById('browserSupport').textContent = `${browserName} (${serialSupported ? 'Supported' : 'Not Supported'})`;
                document.getElementById('browserSupport').className = serialSupported ? 'text-success' : 'text-danger';

                // Check Bridge Server
                this.checkBridgeServer();

                // Detect port type
                this.detectPortType();

                if (!serialSupported) {
                    this.log('Web Serial API tidak didukung. Gunakan Chrome atau Edge untuk koneksi langsung.', 'warning');
                }
            }

            async checkBridgeServer() {
                const bridgeEl = document.getElementById('bridgeStatus');
                try {
                    const response = await fetch('http://localhost:5000/status');
                    if (response.ok) {
                        bridgeEl.textContent = 'Available';
                        bridgeEl.className = 'text-success';
                        this.log('Bridge server tersedia', 'success');
                    } else {
                        throw new Error('Bridge tidak merespon');
                    }
                } catch (error) {
                    bridgeEl.textContent = 'Not Available';
                    bridgeEl.className = 'text-danger';
                    this.log('Bridge server tidak tersedia', 'warning');
                }
            }

            detectPortType() {
                const portTypeEl = document.getElementById('portType');
                if ('serial' in navigator) {
                    portTypeEl.textContent = 'Web Serial';
                    portTypeEl.className = 'text-primary';
                } else {
                    portTypeEl.textContent = 'Bridge Only';
                    portTypeEl.className = 'text-warning';
                }
            }

            setupEventListeners() {
                document.getElementById('connectBtn').addEventListener('click', () => this.connectToIndicator());
                document.getElementById('disconnectBtn').addEventListener('click', () => this.disconnectFromIndicator());

                // Auto-reconnect toggle
                document.getElementById('autoReconnectToggle').addEventListener('change', (e) => {
                    this.autoReconnect = e.target.checked;
                    if (this.autoReconnect) {
                        this.log('✅ Auto-reconnect diaktifkan', 'success');
                        this.showNotification('Auto-reconnect diaktifkan', 'success');
                    } else {
                        this.log('❌ Auto-reconnect dinonaktifkan', 'warning');
                        this.showNotification('Auto-reconnect dinonaktifkan', 'warning');
                        this.clearConnectionState();
                    }
                });

                // Initialize connection ID
                this.generateConnectionId();
            }

            initializeConnections() {
                // Initialize Web Serial API
                if (window.WeightIndicator) {
                    window.WeightIndicator.onWeightUpdate((weight) => {
                        this.processWeightUpdate(weight, 'Web Serial API');
                    });

                    window.WeightIndicator.onConnectionChange((connected) => {
                        this.handleConnectionStatusChange(connected, 'Web Serial API');
                    });

                    window.WeightIndicator.onError((error) => {
                        this.log(`Error Web Serial: ${error}`, 'error');
                    });
                }

                // Initialize Bridge Client
                if (window.TimbanganBridgeClient) {
                    this.bridgeClient = new window.TimbanganBridgeClient();

                    this.bridgeClient.onWeightUpdate((indicatorId, weight, timestamp) => {
                        this.processWeightUpdate(weight, `Bridge (${indicatorId})`);
                    });

                    this.bridgeClient.onConnectionChange((connected) => {
                        this.handleConnectionStatusChange(connected, 'Bridge Client');
                    });

                    this.bridgeClient.onError((error) => {
                        this.log(`Error Bridge: ${error}`, 'error');
                    });
                }
            }

            async connectToIndicator() {
                this.log('Memulai proses koneksi...', 'info');
                this.reconnectAttempts = 0;

                // Prioritize Web Serial API
                if (window.WeightIndicator && window.WeightIndicator.isSupported()) {
                    this.log('Mencoba koneksi via Web Serial API...', 'info');
                    const success = await window.WeightIndicator.connect();
                    if (success) {
                        this.connectionMethod = 'Web Serial API';
                        this.saveConnectionState('Web Serial API');
                        this.log('✅ Berhasil terhubung via Web Serial API', 'success');
                        return;
                    }
                }

                // Fallback to Bridge Client
                if (this.bridgeClient) {
                    this.log('Mencoba koneksi via Bridge Client...', 'info');
                    const success = await this.bridgeClient.initialize();
                    if (success) {
                        this.connectionMethod = 'Bridge Client';
                        this.saveConnectionState('Bridge Client');
                        this.log('✅ Berhasil terhubung via Bridge Client', 'success');
                        return;
                    }
                }

                this.log('❌ Gagal terhubung ke indikator timbangan', 'error');
                this.showNotification('Gagal terhubung ke indikator. Periksa koneksi hardware.', 'danger');
                this.clearConnectionState();
            }

            // Auto-reconnect methods
            async autoConnect() {
                if (this.isReconnecting) return;

                this.isReconnecting = true;
                this.log('🔄 Memulai auto-reconnect...', 'info');

                try {
                    await this.connectToIndicator();
                    this.hideReconnectStatus();
                } catch (error) {
                    this.log(`Auto-reconnect gagal: ${error.message}`, 'error');
                    this.hideReconnectStatus();
                } finally {
                    this.isReconnecting = false;
                }
            }

            shouldAutoReconnect() {
                const savedState = this.getSavedConnectionState();
                return this.autoReconnect && savedState && savedState.connected;
            }

            saveConnectionState(method) {
                const state = {
                    connected: true,
                    method: method,
                    timestamp: Date.now(),
                    lastWeight: this.currentWeight,
                    totalReadings: this.totalReadings
                };
                localStorage.setItem(this.connectionStateKey, JSON.stringify(state));
            }

            loadConnectionState() {
                try {
                    const savedState = localStorage.getItem(this.connectionStateKey);
                    if (savedState) {
                        const state = JSON.parse(savedState);
                        this.log(`📂 Loaded connection state: ${state.method} (${new Date(state.timestamp).toLocaleString()})`, 'info');
                        return state;
                    }
                } catch (error) {
                    this.log('Gagal load connection state', 'warning');
                }
                return null;
            }

            getSavedConnectionState() {
                try {
                    const savedState = localStorage.getItem(this.connectionStateKey);
                    return savedState ? JSON.parse(savedState) : null;
                } catch (error) {
                    return null;
                }
            }

            clearConnectionState() {
                localStorage.removeItem(this.connectionStateKey);
            }

            scheduleReconnect() {
                if (!this.autoReconnect || this.reconnectAttempts >= this.maxReconnectAttempts) {
                    this.log('❌ Max reconnect attempts reached, stopping auto-reconnect', 'error');
                    this.clearConnectionState();
                    this.hideReconnectStatus();
                    return;
                }

                this.reconnectAttempts++;
                const delay = this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1); // Exponential backoff

                this.log(`🔄 Scheduling reconnect attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${delay/1000}s`, 'info');
                this.showReconnectStatus(delay);

                this.reconnectTimer = setTimeout(() => {
                    this.autoConnect();
                }, delay);
            }

            generateConnectionId() {
                this.connectionId = 'CONN-' + Date.now().toString(36).toUpperCase() + '-' + Math.random().toString(36).substr(2, 5).toUpperCase();
                document.getElementById('connectionId').textContent = this.connectionId;
            }

            showReconnectStatus(delay) {
                const statusEl = document.getElementById('reconnectStatus');
                const textEl = document.getElementById('reconnectText');
                const progressEl = document.getElementById('reconnectProgress');

                statusEl.style.display = 'block';
                textEl.textContent = `Reconnect attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${Math.ceil(delay/1000)}s...`;

                // Animate progress bar
                progressEl.style.width = '100%';
                progressEl.style.transition = `width ${delay}ms linear`;
                setTimeout(() => {
                    progressEl.style.width = '0%';
                }, 100);
            }

            hideReconnectStatus() {
                const statusEl = document.getElementById('reconnectStatus');
                const progressEl = document.getElementById('reconnectProgress');

                statusEl.style.display = 'none';
                progressEl.style.transition = 'none';
                progressEl.style.width = '0%';
            }

            async disconnectFromIndicator() {
                this.log('Memutuskan semua koneksi...', 'info');

                // Clear any pending reconnect
                if (this.reconnectTimer) {
                    clearTimeout(this.reconnectTimer);
                    this.reconnectTimer = null;
                }

                this.autoReconnect = false; // Disable auto-reconnect on manual disconnect
                document.getElementById('autoReconnectToggle').checked = false;

                // Disconnect Web Serial API
                if (window.WeightIndicator && window.WeightIndicator.isConnected) {
                    await window.WeightIndicator.disconnect();
                }

                // Disconnect Bridge Client
                if (this.bridgeClient) {
                    this.bridgeClient.disconnect();
                }

                this.isConnected = false;
                this.connectionMethod = null;
                this.clearConnectionState();
                this.hideReconnectStatus();
                this.updateUIComponents();
                this.log('🔌 Semua koneksi telah diputus', 'info');
                this.showNotification('Koneksi diputus secara manual', 'info');
            }

            handleConnectionStatusChange(connected, method) {
                const wasConnected = this.isConnected;
                this.isConnected = connected;

                if (connected) {
                    this.connectionMethod = method;
                    this.reconnectAttempts = 0; // Reset reconnect attempts on successful connection
                    this.saveConnectionState(method);
                    this.hideReconnectStatus();
                    this.log(`🔗 Terhubung via ${method}`, 'success');
                    this.showNotification(`Berhasil terhubung via ${method}`, 'success');
                } else {
                    this.log(`🔌 Terputus dari ${method}`, 'warning');
                    this.showNotification(`Koneksi terputus dari ${method}`, 'warning');

                    // Schedule auto-reconnect if enabled and this wasn't a manual disconnect
                    if (this.autoReconnect && wasConnected && !this.isReconnecting) {
                        this.log('🔄 Connection lost, scheduling auto-reconnect...', 'info');
                        this.scheduleReconnect();
                    }
                }

                this.updateUIComponents();
            }

            processWeightUpdate(weight, method) {
                const newWeight = parseFloat(weight) || 0;

                // Check for stability
                if (Math.abs(newWeight - this.lastStableWeight) < 0.1) {
                    this.stableCount++;
                    if (this.stableCount >= this.stableThreshold) {
                        this.lastStableWeight = newWeight;
                        this.updateStabilityIndicator(true);
                    }
                } else {
                    this.stableCount = 0;
                    this.lastStableWeight = newWeight;
                    this.updateStabilityIndicator(false);
                }

                // Update current weight if significant change
                if (Math.abs(newWeight - this.currentWeight) > 0.5) {
                    this.currentWeight = newWeight;
                    this.totalReadings++;
                    this.addToHistory(newWeight, method);

                    if (this.totalReadings % 5 === 0) {
                        this.log(`Pembacaan ke-${this.totalReadings}: ${this.formatWeight(newWeight)} via ${method}`, 'success');
                    }
                }

                this.updateWeightDisplay();
            }

            updateStabilityIndicator(isStable) {
                const statusEl = document.getElementById('stableStatus');
                if (isStable) {
                    statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Stabil';
                    statusEl.className = 'h5 text-success';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-circle"></i> Menstabil';
                    statusEl.className = 'h5 text-warning';
                }
            }

            addToHistory(weight, method) {
                const entry = {
                    weight: weight,
                    method: method,
                    timestamp: new Date(),
                    reading: this.totalReadings
                };

                this.weightHistory.unshift(entry);
                if (this.weightHistory.length > this.maxHistory) {
                    this.weightHistory.pop();
                }

                this.updateHistoryDisplay();
            }

            updateHistoryDisplay() {
                const historyEl = document.getElementById('weightHistory');

                if (this.weightHistory.length === 0) {
                    historyEl.innerHTML = '<p class="text-muted text-center">Belum ada data pembacaan</p>';
                    return;
                }

                let html = '';
                this.weightHistory.forEach((entry, index) => {
                    const opacity = 1 - (index * 0.05);
                    html += `
                        <div class="history-item" style="opacity: ${opacity}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-primary">#${entry.reading}</strong>
                                    <div class="h6 mb-0">${this.formatWeight(entry.weight)}</div>
                                    <small class="text-muted">${entry.method}</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">${this.formatTime(entry.timestamp)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });

                historyEl.innerHTML = html;
            }

            updateWeightDisplay() {
                document.getElementById('currentWeight').textContent = this.formatWeight(this.currentWeight);
                document.getElementById('totalReadings').textContent = this.totalReadings;
            }

            updateUIComponents() {
                const statusEl = document.getElementById('connectionStatus');
                const connectBtn = document.getElementById('connectBtn');
                const disconnectBtn = document.getElementById('disconnectBtn');
                const methodEl = document.getElementById('connectionMethod');

                if (this.isConnected) {
                    statusEl.innerHTML = '<i class="fas fa-circle status-online me-2"></i><span>Connected</span>';
                    connectBtn.disabled = true;
                    disconnectBtn.disabled = false;
                    methodEl.textContent = this.connectionMethod || 'Unknown';
                    methodEl.className = 'connection-badge bg-success';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-circle status-offline me-2"></i><span>Not Connected</span>';
                    connectBtn.disabled = false;
                    disconnectBtn.disabled = true;
                    methodEl.textContent = 'None';
                    methodEl.className = 'connection-badge bg-secondary';
                }
            }

            log(message, type = 'info') {
                const logEl = document.getElementById('dataLog');
                const timestamp = new Date().toLocaleTimeString('id-ID');
                const icons = {
                    info: 'ℹ️',
                    success: '✅',
                    warning: '⚠️',
                    error: '❌'
                };

                const logEntry = `[${timestamp}] ${icons[type] || 'ℹ️'} ${message}\n`;
                logEl.textContent += logEntry;

                // Maintain log size
                const lines = logEl.textContent.split('\n');
                if (lines.length > 100) {
                    logEl.textContent = lines.slice(-100).join('\n');
                }

                logEl.scrollTop = logEl.scrollHeight;
            }

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(notification);

                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }

            formatWeight(weight) {
                return `${weight.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Kg`;
            }

            formatTime(date) {
                return date.toLocaleTimeString('id-ID');
            }

            startRealTimeClock() {
                setInterval(() => {
                    if (this.isConnected) {
                        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('id-ID');
                    }
                }, 1000);
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            window.displayIndikator = new DisplayIndikatorTimbangan();
            console.log('Display Indikator Timbangan initialized');
        });
    </script>

    <?php include_once __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>