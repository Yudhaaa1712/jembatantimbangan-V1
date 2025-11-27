<?php
/**
 * Konfigurasi Perangkat Keras Portabel - Sistem Jembatan Timbangan
 *
 * File ini berisi semua pengaturan perangkat keras yang dapat dengan mudah
 * disesuaikan saat dipindahkan ke komputer lain
 */

class HardwareConfig {
    // Konfigurasi Indikator Sonic A28E
    public static $sonic_a28e = [
        'default_com_port' => 'COM3',
        'baud_rate' => 9600,
        'timeout' => 1,
        'parity' => 'none',
        'stop_bits' => 1,
        'data_bits' => 8,
        'flow_control' => 'none'
    ];

    // Konfigurasi Server Bridge
    public static $bridge_server = [
        'host' => '127.0.0.1',
        'port' => 5001, // Kembali ke port 5001 (simple_bridge stabil)
        'polling_interval' => 2, // detik
        'retry_attempts' => 3,
        'retry_delay' => 1 // detik
    ];

    // Konfigurasi Validasi Timbangan
    public static $weight_validation = [
        'minimum_weight' => 0, // kg - diubah menjadi 0 agar tidak ada minimal
        'maximum_weight' => 100000, // kg
        'stability_threshold' => 50, // kg
        'capture_timeout' => 30 // detik
    ];

    // Konfigurasi Database (jika perlu diubah)
    public static $database = [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'jembatan_timbangan',
        'charset' => 'utf8mb4',
        'timezone' => 'Asia/Jakarta'
    ];

    /**
     * Fungsi untuk mendeteksi COM port yang tersedia secara otomatis
     */
    public static function detectAvailablePorts() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $ports = [];
            $output = shell_exec('wmic path Win32_SerialPort get DeviceID /format:list 2>nul');
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (strpos($line, 'DeviceID=') !== false) {
                        $port = str_replace('DeviceID=', '', trim($line));
                        $ports[] = $port;
                    }
                }
            }
            return $ports;
        } else {
            // Linux/Mac
            $ports = [];
            $output = shell_exec('ls /dev/tty* 2>/dev/null');
            if ($output) {
                $ports = explode("\n", trim($output));
            }
            return $ports;
        }
    }

    /**
     * Fungsi untuk mencari port yang kemungkinan digunakan oleh Sonic A28E
     */
    public static function findSonicA28EPort() {
        $available_ports = self::detectAvailablePorts();

        // Prioritaskan port yang umum digunakan
        $common_ports = ['COM3', 'COM1', 'COM2', 'COM4', 'COM5'];

        foreach ($common_ports as $port) {
            if (in_array($port, $available_ports)) {
                return $port;
            }
        }

        // Jika tidak ada port umum yang tersedia, kembalikan port pertama yang tersedia
        if (!empty($available_ports)) {
            return $available_ports[0];
        }

        return self::$sonic_a28e['default_com_port'];
    }

    /**
     * Fungsi untuk membuat file konfigurasi Python bridge
     */
    public static function generateBridgeConfig($com_port = null) {
        if ($com_port === null) {
            $com_port = self::findSonicA28EPort();  
        }

        $config = [
            'COM_PORT' => $com_port,
            'BAUD_RATE' => self::$sonic_a28e['baud_rate'],
            'TIMEOUT' => self::$sonic_a28e['timeout'],
            'SERVER_HOST' => self::$bridge_server['host'],
            'SERVER_PORT' => self::$bridge_server['port'],
            'POLLING_INTERVAL' => self::$bridge_server['polling_interval']
        ];

        return $config;
    }

    /**
     * Fungsi untuk menyimpan konfigurasi ke file
     */
    public static function saveConfigToFile($filename = 'hardware_settings.json') {
        $config = [
            'sonic_a28e' => self::$sonic_a28e,
            'bridge_server' => self::$bridge_server,
            'weight_validation' => self::$weight_validation,
            'database' => self::$database,
            'detected_ports' => self::detectAvailablePorts(),
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents(__DIR__ . '/' . $filename, json_encode($config, JSON_PRETTY_PRINT));
        return true;
    }

    /**
     * Fungsi untuk memuat konfigurasi dari file
     */
    public static function loadConfigFromFile($filename = 'hardware_settings.json') {
        $config_file = __DIR__ . '/' . $filename;
        if (file_exists($config_file)) {
            $config = json_decode(file_get_contents($config_file), true);

            if (isset($config['sonic_a28e'])) {
                self::$sonic_a28e = array_merge(self::$sonic_a28e, $config['sonic_a28e']);
            }
            if (isset($config['bridge_server'])) {
                self::$bridge_server = array_merge(self::$bridge_server, $config['bridge_server']);
            }
            if (isset($config['weight_validation'])) {
                self::$weight_validation = array_merge(self::$weight_validation, $config['weight_validation']);
            }
            if (isset($config['database'])) {
                self::$database = array_merge(self::$database, $config['database']);
            }

            return true;
        }
        return false;
    }

    /**
     * Fungsi untuk mengecek koneksi ke bridge server
     */
    public static function testBridgeConnection() {
        $url = 'http://' . self::$bridge_server['host'] . ':' . self::$bridge_server['port'] . '/status';

        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        return $response !== false;
    }
}

// Auto-load konfigurasi dari file jika ada
HardwareConfig::loadConfigFromFile();
?>