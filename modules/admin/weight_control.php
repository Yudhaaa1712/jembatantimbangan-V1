<?php
// modules/admin/weight_control.php
require_once '../../config/database.php';
check_role(['admin']);

$page_title = "Weight Control - Jembatan Timbangan Sawit";

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'set_mode') {
        $mode = clean_input($_POST['mode']);
        set_weight_mode($mode);

        // Set manual weight jika disediakan
        if (!empty($_POST['manual_weight'])) {
            set_manual_weight((int)$_POST['manual_weight']);
        }

        header('Location: weight_control.php?success=1');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'manual_weight') {
        $weight = (int)$_POST['weight'];
        set_manual_weight($weight);

        header('Location: weight_control.php?success=2');
        exit;
    }
}

include '../../includes/header.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    .main-container {
        max-width: 1200px;
        margin: 20px;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .page-title {
        color: #dc2626;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 10px;
    }

    .control-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .control-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
    }

    .control-card:hover {
        border-color: rgba(220, 38, 38, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.2);
    }

    .card-title {
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .mode-button {
        width: 100%;
        padding: 12px;
        margin-bottom: 10px;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        color: #dc2626;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mode-button:hover {
        background: #dc2626;
        color: #fff;
        transform: translateY(-1px);
    }

    .mode-button.active {
        background: #dc2626;
        color: #fff;
    }

    .description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .manual-input {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }

    .form-control {
        flex: 1;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .btn-manual {
        background: #dc2626;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-manual:hover {
        background: #ef4444;
        transform: translateY(-1px);
    }

    .current-status {
        background: rgba(34, 197, 94, 0.1);
        border: 2px solid #22c55e;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin-bottom: 30px;
    }

    .weight-display {
        color: #22c55e;
        font-size: 48px;
        font-weight: bold;
        margin: 10px 0;
        font-family: 'Courier New', monospace;
    }

    .status-label {
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .connection-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
    }

    .info-title {
        color: #3b82f6;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-text {
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .main-container {
            margin: 15px;
            padding: 20px;
        }

        .control-grid {
            grid-template-columns: 1fr;
        }

        .weight-display {
            font-size: 36px;
        }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-weight"></i> WEIGHT CONTROL
        </h1>
    </div>

    <!-- Current Status -->
    <div class="current-status">
        <div class="status-label">Indicator Connection</div>
        <div style="margin: 10px 0;">
            <span id="indicatorStatus" style="color: #dc2626; font-weight: bold;">
                <?php echo is_indicator_connected() ? 'Connected' : 'Disconnected'; ?>
            </span>
        </div>
        <div style="margin-bottom: 20px;">
            <button type="button" id="toggleConnection" class="btn-manual" onclick="toggleIndicatorConnection()">
                <?php echo is_indicator_connected() ? 'Disconnect Indicator' : 'Connect Indicator'; ?>
            </button>
        </div>

        <div class="status-label">Current Weight</div>
        <div class="weight-display" id="currentWeightDisplay">
            <?php echo number_format(get_current_weight(), 0, '.', '.'); ?>
        </div>
        <div class="status-label">Kg</div>
    </div>

    <!-- Control Grid -->
    <div class="control-grid">
        <!-- Mode Control -->
        <div class="control-card">
            <h3 class="card-title">Simulation Mode</h3>
            <p class="description">
                Pilih mode simulasi indikator timbangan untuk testing.
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="set_mode">
                <input type="hidden" name="mode" value="stable">
                <button type="submit" class="mode-button" onclick="this.form.mode.value='stable'">
                    <i class="fas fa-lock"></i> Stable Mode
                </button>
            </form>

            <form method="POST">
                <input type="hidden" name="action" value="set_mode">
                <input type="hidden" name="mode" value="fluctuate">
                <button type="submit" class="mode-button" onclick="this.form.mode.value='fluctuate'">
                    <i class="fas fa-chart-line"></i> Fluctuate Mode
                </button>
            </form>

            <form method="POST">
                <input type="hidden" name="action" value="set_mode">
                <input type="hidden" name="mode" value="realistic">
                <button type="submit" class="mode-button" onclick="this.form.mode.value='realistic'">
                    <i class="fas fa-industry"></i> Realistic Mode
                </button>
            </form>
        </div>

        <!-- Manual Weight -->
        <div class="control-card">
            <h3 class="card-title">Manual Weight</h3>
            <p class="description">
                Set bobot manual untuk testing spesifik.
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="manual_weight">
                <div class="manual-input">
                    <input type="number" class="form-control" name="weight"
                           placeholder="Masukkan bobot (kg)" min="0" max="50000" step="100">
                    <button type="submit" class="btn-manual">
                        <i class="fas fa-set"></i> Set
                    </button>
                </div>
            </form>

            <div style="margin-top: 15px;">
                <button type="button" class="mode-button" onclick="setRandomWeight()">
                    <i class="fas fa-random"></i> Random Weight
                </button>
            </div>
        </div>

        <!-- Connection Info -->
        <div class="control-card">
            <h3 class="card-title">Connection Info</h3>
            <p class="description">
                Informasi untuk koneksi ke indikator timbangan real.
            </p>

            <div class="connection-info">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i> Status Saat Ini
                </div>
                <div class="info-text">
                    <strong>Mode:</strong> Dummy Simulation<br>
                    <strong>Port:</strong> Belum dikonfigurasi<br>
                    <strong>Baudrate:</strong> 9600 (Default)<br>
                    <strong>Protocol:</strong> Serial/RS232
                </div>
            </div>
        </div>
    </div>

    <!-- Real Connection Setup -->
    <div class="control-card" style="margin-top: 20px;">
        <h3 class="card-title">Real Connection Setup</h3>
        <div class="connection-info">
            <div class="info-title">
                <i class="fas fa-plug"></i> How to Connect Real Indicator
            </div>
            <div class="info-text">
                <strong>1. Hardware:</strong> RS232 to USB converter<br>
                <strong>2. Port:</strong> COM3, COM4, etc (check device manager)<br>
                <strong>3. PHP Extension:</strong> Install php_serial.dll<br>
                <strong>4. Configuration:</strong> Edit functions.php to use serial_read()<br>
                <strong>5. Testing:</strong> Use serial communication library
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>

<script>
    // Toggle indicator connection
    function toggleIndicatorConnection() {
        const currentStatus = $('#indicatorStatus').text();
        const newStatus = currentStatus === 'Connected' ? false : true;

        $.ajax({
            url: '../timbangan/ajax.php',
            type: 'POST',
            data: {
                action: 'toggle_indicator_connection',
                connect: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#indicatorStatus').text(newStatus ? 'Connected' : 'Disconnected');
                    $('#indicatorStatus').css('color', newStatus ? '#22c55e' : '#dc2626');
                    $('#toggleConnection').text(newStatus ? 'Disconnect Indicator' : 'Connect Indicator');

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Indicator ${newStatus ? 'connected' : 'disconnected'} successfully`,
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Update weight display
                    updateWeightDisplay();
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to toggle indicator connection',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    // Update weight display every 2 seconds
    function updateWeightDisplay() {
        $.ajax({
            url: '../timbangan/ajax.php',
            type: 'POST',
            data: { action: 'get_indicator_status' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update weight display
                    $('#currentWeightDisplay').text(response.data.weight.toLocaleString('id-ID'));

                    // Update connection status
                    $('#indicatorStatus').text(response.data.connected ? 'Connected' : 'Disconnected');
                    $('#indicatorStatus').css('color', response.data.connected ? '#22c55e' : '#dc2626');
                    $('#toggleConnection').text(response.data.connected ? 'Disconnect Indicator' : 'Connect Indicator');
                }
            }
        });
    }

    // Set random weight
    function setRandomWeight() {
        const weights = [2500, 5000, 7500, 10000, 15000, 20000, 25000, 30000, 35000, 40000];
        const randomWeight = weights[Math.floor(Math.random() * weights.length)];

        $.ajax({
            url: 'weight_control.php',
            type: 'POST',
            data: {
                action: 'manual_weight',
                weight: randomWeight
            },
            success: function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Weight Set!',
                    text: `Manual weight: ${randomWeight} kg`,
                    timer: 2000,
                    showConfirmButton: false
                });
                setTimeout(() => location.reload(), 1000);
            }
        });
    }

    // Auto update
    setInterval(updateWeightDisplay, 2000);

    // Success notification
    <?php if (isset($_GET['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Mode indikator telah diubah',
            timer: 2000,
            showConfirmButton: false
        });
        <?php endif; ?>
</script>

</body>
</html>