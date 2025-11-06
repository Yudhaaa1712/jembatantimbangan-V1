<?php
/**
 * API untuk membaca data dari port COM3
 * Menggunakan PHP Serial Extension atau exec untuk komunikasi serial
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class SerialAPI {
    private $port = 'COM3';
    private $baudrate = 9600;
    private $timeout = 2;
    private $isConnected = false;
    private $currentWeight = 0;
    private $lastRawData = '';
    private $error = '';

    public function __construct() {
        // Load configuration
        $this->loadConfig();
    }

    private function loadConfig() {
        // Load dari file config jika ada
        $configFile = __DIR__ . '/serial_config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config) {
                $this->port = $config['port'] ?? 'COM3';
                $this->baudrate = $config['baudrate'] ?? 9600;
            }
        }
    }

    /**
     * Test koneksi ke port COM3
     */
    public function testConnection() {
        try {
            // Method 1: Cek port availability
            if ($this->isPortAvailable()) {
                $this->isConnected = true;
                return [
                    'success' => true,
                    'connected' => true,
                    'port' => $this->port,
                    'message' => 'Port COM3 tersedia'
                ];
            }

            return [
                'success' => false,
                'connected' => false,
                'message' => 'Port COM3 tidak tersedia atau sedang digunakan'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'connected' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cek apakah port tersedia
     */
    private function isPortAvailable() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $command = "mode {$this->port} 2>nul";
            exec($command, $output, $return_var);
            return $return_var === 0;
        } else {
            // Linux/Mac
            $command = "test -c {$this->port}";
            exec($command, $output, $return_var);
            return $return_var === 0;
        }
    }

    /**
     * Baca data dari serial port
     */
    public function readWeight() {
        try {
            // Method 1: Coba baca langsung dari port (jika ada PHP Serial Extension)
            if (extension_loaded('dio')) {
                return $this->readWithDio();
            }

            // Method 2: Gunakan exec dengan mode command
            return $this->readWithExec();

        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'weight' => 0,
                'raw_data' => ''
            ];
        }
    }

    /**
     * Baca dengan dio extension (jika available)
     */
    private function readWithDio() {
        try {
            $fd = dio_open($this->port, O_RDONLY | O_NONBLOCK);
            if (!$fd) {
                throw new Exception("Cannot open {$this->port}");
            }

            // Configure serial port
            dio_tcsetattr($fd, [
                'baud' => $this->baudrate,
                'bits' => 8,
                'stop' => 1,
                'parity' => 0
            ]);

            $data = dio_read($fd, 256);
            dio_close($fd);

            if ($data) {
                return $this->parseWeightData($data);
            }

            return $this->getSimulatedWeight();

        } catch (Exception $e) {
            throw new Exception("DIO Error: " . $e->getMessage());
        }
    }

    /**
     * Baca dengan exec command
     */
    private function readWithExec() {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: gunakan mode command untuk membaca port
                $tempFile = tempnam(sys_get_temp_dir(), 'serial_read');

                // Coba baca dari port COM3
                $command = "echo > {$this->port} 2>nul & timeout /t 1 >nul & type {$this->port} > {$tempFile} 2>nul";
                exec($command, $output, $return_var);

                if (file_exists($tempFile) && filesize($tempFile) > 0) {
                    $data = file_get_contents($tempFile);
                    unlink($tempFile);

                    if ($data) {
                        return $this->parseWeightData(trim($data));
                    }
                }

                // Cleanup temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

            // Fallback ke simulasi
            return $this->getSimulatedWeight();

        } catch (Exception $e) {
            throw new Exception("Exec Error: " . $e->getMessage());
        }
    }

    /**
     * Parse data dari indikator timbangan
     */
    private function parseWeightData($rawData) {
        $this->lastRawData = $rawData;

        // Simulasi parsing untuk berbagai format indikator
        $patterns = [
            // Format: ST,GS, +01234.5 KG
            '/^(ST|GS|NT|TARA|ZERO|NET|GROSS)\s*([+-]?\d+\.?\d*)\s*KG?$/i',
            // Format: 01234.5
            '/^([+-]?\d+\.?\d*)$/',
            // Format: [ST] 01234.5 KG
            '/^\[?(ST|GS|NT|TARA|ZERO|NET|GROSS)\]?\s*([+-]?\d+\.?\d*)\s*KG?$/i',
            // Format dengan karakter spesial: N 01234.5 KG
            '/^[A-Z]?\s*([+-]?\d+\.?\d*)\s*KG?$/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $rawData, $matches)) {
                $weight = floatval($matches[2] ?? $matches[1]);

                // Filter nilai yang wajar
                if ($weight >= -1000 && $weight <= 100000) { // -1 ton sampai 100 ton
                    $this->currentWeight = $weight;
                    return [
                        'success' => true,
                        'weight' => $weight,
                        'raw_data' => $rawData,
                        'timestamp' => time()
                    ];
                }
            }
        }

        // Jika tidak ada match, kembalikan simulasi
        return $this->getSimulatedWeight();
    }

    /**
     * Simulasi data (fallback)
     */
    private function getSimulatedWeight() {
        // Generate berat simulasi yang realistis
        $baseWeight = 5000; // 5 ton
        $variation = rand(-500, 500); // ±500kg

        $weight = $baseWeight + $variation;
        $this->currentWeight = $weight;

        $simulatedData = "ST " . number_format($weight, 1, ".", "") . " KG";

        return [
            'success' => true,
            'weight' => $weight,
            'raw_data' => $simulatedData,
            'timestamp' => time(),
            'simulated' => true
        ];
    }

    /**
     * Get status
     */
    public function getStatus() {
        return [
            'success' => true,
            'connected' => $this->isConnected,
            'port' => $this->port,
            'baudrate' => $this->baudrate,
            'current_weight' => $this->currentWeight,
            'last_raw_data' => $this->lastRawData,
            'error' => $this->error,
            'timestamp' => time()
        ];
    }

    /**
     * Disconnect dari port serial
     */
    public function disconnect() {
        $this->isConnected = false;
        $this->currentWeight = 0;
        $this->lastRawData = '';

        return [
            'success' => true,
            'message' => 'Disconnected from serial port'
        ];
    }

    /**
     * Set weight manual (untuk testing)
     */
    public function setWeight($weight) {
        $this->currentWeight = floatval($weight);
        $this->lastRawData = "MANUAL: " . number_format($weight, 1, ".", "") . " KG";

        return [
            'success' => true,
            'weight' => $this->currentWeight,
            'message' => 'Weight set manually'
        ];
    }
}

// Handle API requests
try {
    $serialAPI = new SerialAPI();

    // Handle URL routing
    $request = $_GET['request'] ?? '';
    $action = $_GET['action'] ?? 'status';

    // Parse URL-based requests
    if ($request) {
        if ($request === 'status' || $request === 'weight') {
            $action = 'status';
        } elseif ($request === 'connect') {
            $action = 'test';
        } elseif ($request === 'disconnect') {
            $action = 'disconnect';
        } elseif ($request === 'read') {
            $action = 'read';
        }
    }

    // Handle POST requests for connect/disconnect
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $postAction = $input['action'] ?? '';

        if ($postAction === 'connect') {
            $action = 'test';
        } elseif ($postAction === 'disconnect') {
            $action = 'disconnect';
        }
    }

    switch ($action) {
        case 'test':
            echo json_encode($serialAPI->testConnection());
            break;

        case 'read':
            echo json_encode($serialAPI->readWeight());
            break;

        case 'status':
            echo json_encode($serialAPI->getStatus());
            break;

        case 'disconnect':
            echo json_encode($serialAPI->disconnect());
            break;

        case 'set_weight':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $weight = $input['weight'] ?? 0;
                echo json_encode($serialAPI->setWeight($weight));
            } else {
                echo json_encode(['success' => false, 'message' => 'POST method required']);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Available: test, read, status, set_weight'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'Internal server error'
    ]);
}
?>