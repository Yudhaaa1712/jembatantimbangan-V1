<?php
// modules/masterdata/settings/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Pengaturan Sistem - Master Data";

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update settings
    $settings = [
        'company_name' => $_POST['company_name'] ?? '',
        'company_address' => $_POST['company_address'] ?? '',
        'company_phone' => $_POST['company_phone'] ?? '',
        'company_email' => $_POST['company_email'] ?? '',
        'ticket_prefix' => $_POST['ticket_prefix'] ?? '',
        'currency' => $_POST['currency'] ?? 'IDR',
        'decimal_places' => $_POST['decimal_places'] ?? '0',
        'working_hours_start' => $_POST['working_hours_start'] ?? '06:00',
        'working_hours_end' => $_POST['working_hours_end'] ?? '22:00',
        'auto_refresh_interval' => $_POST['auto_refresh_interval'] ?? '30',
        'timezone' => $_POST['timezone'] ?? 'Asia/Jakarta',
        'date_format' => $_POST['date_format'] ?? 'd/m/Y',
        'time_format' => $_POST['time_format'] ?? '24',
        'language' => $_POST['language'] ?? 'id',
        'backup_schedule' => $_POST['backup_schedule'] ?? 'daily',
        'max_file_upload' => $_POST['max_file_upload'] ?? '10',
        'enable_rfid' => isset($_POST['enable_rfid']) ? '1' : '0',
        'enable_auto_print' => isset($_POST['enable_auto_print']) ? '1' : '0',
        'enable_notifications' => isset($_POST['enable_notifications']) ? '1' : '0',
        'enable_audit_log' => isset($_POST['enable_audit_log']) ? '1' : '0'
    ];

    foreach ($settings as $key => $value) {
        // Check if setting exists
        $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key = '$key'");

        if (mysqli_num_rows($check) > 0) {
            // Update existing
            mysqli_query($conn, "UPDATE settings SET setting_value = '$value', updated_at = NOW() WHERE setting_key = '$key'");
        } else {
            // Insert new
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value, created_at) VALUES ('$key', '$value', NOW())");
        }
    }

    $msg = '<div class="alert alert-success">Pengaturan sistem berhasil disimpan!</div>';
}

// Get current settings
$settings = [];
$query = "SELECT setting_key, setting_value FROM settings";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not exists
$defaults = [
    'company_name' => 'PT. Jembatan Timbangan',
    'company_address' => 'Jl. Industri No. 123, Jakarta',
    'company_phone' => '021-12345678',
    'company_email' => 'info@timbangan.com',
    'ticket_prefix' => 'TKT',
    'currency' => 'IDR',
    'decimal_places' => '0',
    'working_hours_start' => '06:00',
    'working_hours_end' => '22:00',
    'auto_refresh_interval' => '30',
    'timezone' => 'Asia/Jakarta',
    'date_format' => 'd/m/Y',
    'time_format' => '24',
    'language' => 'id',
    'backup_schedule' => 'daily',
    'max_file_upload' => '10',
    'enable_rfid' => '0',
    'enable_auto_print' => '1',
    'enable_notifications' => '1',
    'enable_audit_log' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

include '../../../includes/header.php';
?>

<style>
    .settings-container {
        max-width: 1200px;
        margin: 20px auto;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(220, 38, 38, 0.2);
    }

    .page-title {
        color: #dc2626;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .settings-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid rgba(220, 38, 38, 0.2);
        flex-wrap: wrap;
    }

    .tab-button {
        background: transparent;
        border: none;
        padding: 12px 20px;
        color: rgba(255, 255, 255, 0.6);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 3px solid transparent;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-button.active {
        color: #dc2626;
        border-bottom-color: #dc2626;
    }

    .tab-button:hover {
        color: #dc2626;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .settings-section {
        margin-bottom: 40px;
    }

    .section-title {
        color: #dc2626;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .setting-group {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-radius: 12px;
        padding: 20px;
    }

    .group-title {
        color: #dc2626;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        display: block;
    }

    .form-input, .form-select {
        width: 100%;
        background: transparent;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-input:focus, .form-select:focus {
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(220, 38, 38, 0.3);
        outline: none;
    }

    .form-textarea {
        width: 100%;
        min-height: 100px;
        resize: vertical;
    }

    .switch-group {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .switch-group:last-child {
        border-bottom: none;
    }

    .switch-label {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
    }

    .switch-description {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        margin-top: 4px;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #666;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #dc2626;
    }

    input:checked + .slider:before {
        transform: translateX(26px);
    }

    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 2px solid rgba(220, 38, 38, 0.2);
    }

    .btn-primary {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-secondary {
        background: transparent;
        border: 2px solid #666;
        border-radius: 8px;
        padding: 12px 24px;
        color: #666;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .btn-secondary:hover {
        border-color: #fff;
        color: #fff;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        border-color: #22c55e;
        color: #22c55e;
    }

    .info-card {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .info-card-title {
        color: #dc2626;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card-content {
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .settings-container {
            margin: 15px;
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .settings-tabs {
            overflow-x: auto;
        }

        .settings-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="settings-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-cog"></i>
            Pengaturan Sistem
        </h1>
    </div>

    <?php echo $msg; ?>

    <!-- Tabs Navigation -->
    <div class="settings-tabs">
        <button class="tab-button active" onclick="switchTab('general')">
            <i class="fas fa-cog"></i>
            Umum
        </button>
        <button class="tab-button" onclick="switchTab('company')">
            <i class="fas fa-building"></i>
            Perusahaan
        </button>
        <button class="tab-button" onclick="switchTab('ticket')">
            <i class="fas fa-ticket-alt"></i>
            Tiket
        </button>
        <button class="tab-button" onclick="switchTab('display')">
            <i class="fas fa-desktop"></i>
            Tampilan
        </button>
        <button class="tab-button" onclick="switchTab('features')">
            <i class="fas fa-toggle-on"></i>
            Fitur
        </button>
    </div>

    <form method="POST" id="settingsForm">
        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-cog"></i>
                    Pengaturan Umum
                </h3>

                <div class="settings-grid">
                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-clock"></i>
                            Waktu & Tanggal
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-select">
                                <option value="Asia/Jakarta" <?php echo $settings['timezone'] == 'Asia/Jakarta' ? 'selected' : ''; ?>>WIB (UTC+7)</option>
                                <option value="Asia/Makassar" <?php echo $settings['timezone'] == 'Asia/Makassar' ? 'selected' : ''; ?>>WITA (UTC+8)</option>
                                <option value="Asia/Jayapura" <?php echo $settings['timezone'] == 'Asia/Jayapura' ? 'selected' : ''; ?>>WIT (UTC+9)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Format Tanggal</label>
                            <select name="date_format" class="form-select">
                                <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d M Y" <?php echo $settings['date_format'] == 'd M Y' ? 'selected' : ''; ?>>DD Month YYYY</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Format Waktu</label>
                            <select name="time_format" class="form-select">
                                <option value="24" <?php echo $settings['time_format'] == '24' ? 'selected' : ''; ?>>24 Jam (14:30)</option>
                                <option value="12" <?php echo $settings['time_format'] == '12' ? 'selected' : ''; ?>>12 Jam (2:30 PM)</option>
                            </select>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-briefcase"></i>
                            Jam Operasional
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Jam Mulai</label>
                            <input type="time" name="working_hours_start" class="form-input" value="<?php echo $settings['working_hours_start']; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" name="working_hours_end" class="form-input" value="<?php echo $settings['working_hours_end']; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Auto Refresh (detik)</label>
                            <input type="number" name="auto_refresh_interval" class="form-input" value="<?php echo $settings['auto_refresh_interval']; ?>" min="10" max="300" step="10">
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-globe"></i>
                            Lokalisasi
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Bahasa</label>
                            <select name="language" class="form-select">
                                <option value="id" <?php echo $settings['language'] == 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
                                <option value="en" <?php echo $settings['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mata Uang</label>
                            <select name="currency" class="form-select">
                                <option value="IDR" <?php echo $settings['currency'] == 'IDR' ? 'selected' : ''; ?>>Rupiah (Rp)</option>
                                <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Jumlah Desimal</label>
                            <input type="number" name="decimal_places" class="form-input" value="<?php echo $settings['decimal_places']; ?>" min="0" max="4">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Settings Tab -->
        <div id="company" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-building"></i>
                    Informasi Perusahaan
                </h3>

                <div class="info-card">
                    <div class="info-card-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Penting
                    </div>
                    <div class="info-card-content">
                        Informasi perusahaan akan ditampilkan pada struk, laporan, dan dokumen resmi lainnya.
                    </div>
                </div>

                <div class="settings-grid">
                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-building"></i>
                            Data Perusahaan
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Nama Perusahaan</label>
                            <input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat Perusahaan</label>
                            <textarea name="company_address" class="form-input form-textarea" rows="3"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. Telepon</label>
                            <input type="tel" name="company_phone" class="form-input" value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Perusahaan</label>
                            <input type="email" name="company_email" class="form-input" value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Settings Tab -->
        <div id="ticket" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-ticket-alt"></i>
                    Pengaturan Tiket
                </h3>

                <div class="settings-grid">
                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-barcode"></i>
                            Nomor Tiket
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Prefix Tiket</label>
                            <input type="text" name="ticket_prefix" class="form-input" value="<?php echo htmlspecialchars($settings['ticket_prefix']); ?>" maxlength="10" placeholder="TKT">
                        </div>

                        <div class="info-card">
                            <div class="info-card-title">
                                <i class="fas fa-lightbulb"></i>
                                Contoh Format
                            </div>
                            <div class="info-card-content">
                                Dengan prefix "TKT", nomor tiket akan menjadi: TKT202411001
                            </div>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-print"></i>
                            Cetak Struk
                        </h4>

                        <div class="switch-group">
                            <div>
                                <div class="switch-label">Auto Print</div>
                                <div class="switch-description">Cetak struk otomatis setelah transaksi selesai</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_auto_print" <?php echo $settings['enable_auto_print'] == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Settings Tab -->
        <div id="display" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-desktop"></i>
                    Pengaturan Tampilan
                </h3>

                <div class="settings-grid">
                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-sync"></i>
                            Refresh Otomatis
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Interval Refresh (detik)</label>
                            <input type="number" name="auto_refresh_interval" class="form-input" value="<?php echo $settings['auto_refresh_interval']; ?>" min="10" max="300" step="10">
                            <small style="color: rgba(255,255,255,0.5); font-size: 12px;">Berlaku untuk halaman transaksi dan dashboard</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Settings Tab -->
        <div id="features" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-toggle-on"></i>
                    Pengaturan Fitur
                </h3>

                <div class="settings-grid">
                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-wifi"></i>
                            Fitur RFID
                        </h4>

                        <div class="switch-group">
                            <div>
                                <div class="switch-label">Enable RFID</div>
                                <div class="switch-description">Aktifkan sistem identifikasi kendaraan otomatis dengan RFID</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_rfid" <?php echo $settings['enable_rfid'] == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-bell"></i>
                            Notifikasi
                        </h4>

                        <div class="switch-group">
                            <div>
                                <div class="switch-label">Enable Notifications</div>
                                <div class="switch-description">Aktifkan notifikasi sistem untuk berbagai event</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_notifications" <?php echo $settings['enable_notifications'] == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-history"></i>
                            Audit Log
                        </h4>

                        <div class="switch-group">
                            <div>
                                <div class="switch-label">Enable Audit Log</div>
                                <div class="switch-description">Catat semua aktivitas sistem untuk keperluan audit</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_audit_log" <?php echo $settings['enable_audit_log'] == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4 class="group-title">
                            <i class="fas fa-database"></i>
                            Backup
                        </h4>

                        <div class="form-group">
                            <label class="form-label">Jadwal Backup</label>
                            <select name="backup_schedule" class="form-select">
                                <option value="daily" <?php echo $settings['backup_schedule'] == 'daily' ? 'selected' : ''; ?>>Harian</option>
                                <option value="weekly" <?php echo $settings['backup_schedule'] == 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                                <option value="monthly" <?php echo $settings['backup_schedule'] == 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                                <option value="manual" <?php echo $settings['backup_schedule'] == 'manual' ? 'selected' : ''; ?>>Manual</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max File Upload (MB)</label>
                            <input type="number" name="max_file_upload" class="form-input" value="<?php echo $settings['max_file_upload']; ?>" min="1" max="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="resetForm()">
                <i class="fas fa-undo"></i>
                Reset
            </button>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i>
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.min.js"></script>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName).classList.add('active');

    // Add active class to clicked button
    event.target.closest('.tab-button').classList.add('active');
}

function resetForm() {
    Swal.fire({
        title: 'Reset Pengaturan?',
        text: 'Apakah Anda yakin ingin mereset semua pengaturan ke nilai default?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#666',
        confirmButtonText: 'Ya, Reset',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            location.reload();
        }
    });
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Save current tab to localStorage
    const activeTab = localStorage.getItem('activeSettingsTab') || 'general';
    if (activeTab !== 'general') {
        document.getElementById(activeTab).classList.add('active');
        document.getElementById('general').classList.remove('active');

        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
            if (button.textContent.toLowerCase().includes(activeTab)) {
                button.classList.add('active');
            }
        });
    }

    // Store tab changes
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('onclick').match(/switchTab\('(.+?)'\)/)[1];
            localStorage.setItem('activeSettingsTab', tabName);
        });
    });
});
</script>

</body>
</html>