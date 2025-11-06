<?php
// Database Connection Pool - Optimized for performance
class DatabasePool {
    private static $instance = null;
    private $connection = null;
    private $connected = false;
    private $last_activity = 0;
    private $max_idle_time = 300; // 5 minutes

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        global $hardware_config;

        $config = $hardware_config ?? [
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'jembatan_timbangan'
        ];

        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database']
            );

            // Optimize connection settings
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $this->connection->set_charset("utf8mb4");

            // Set session variables for performance
            $this->connection->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $this->connection->query("SET SESSION innodb_lock_wait_timeout = 5");
            $this->connection->query("SET SESSION query_cache_type = ON");

            $this->connected = true;
            $this->last_activity = time();

        } catch (mysqli_sql_exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->connected = false;
            throw $e;
        }
    }

    public function getConnection() {
        // Reconnect if connection is lost or too idle
        if (!$this->connected || !$this->connection || $this->connection->ping() === false) {
            $this->connect();
        }

        // Reset idle timer
        $this->last_activity = time();
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $conn = $this->getConnection();

        try {
            if (empty($params)) {
                $result = $conn->query($sql);
                return $result;
            } else {
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    throw new Exception("Failed to prepare query: " . $conn->error);
                }

                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                return $stmt->get_result();
            }
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("Query: " . $sql);
            throw $e;
        }
    }

    public function prepare($sql) {
        return $this->getConnection()->prepare($sql);
    }

    public function escape($string) {
        return $this->getConnection()->real_escape_string($string);
    }

    public function getLastInsertId() {
        return $this->getConnection()->insert_id;
    }

    public function beginTransaction() {
        $this->getConnection()->begin_transaction();
    }

    public function commit() {
        $this->getConnection()->commit();
    }

    public function rollback() {
        $this->getConnection()->rollback();
    }

    // Performance monitoring
    public function getConnectionStats() {
        if (!$this->connected) {
            return null;
        }

        $stats = $this->connection->query("SHOW STATUS LIKE 'Connections'")->fetch_assoc();
        $uptime = $this->connection->query("SHOW STATUS LIKE 'Uptime'")->fetch_assoc();

        return [
            'total_connections' => $stats['Value'] ?? 0,
            'uptime_seconds' => $uptime['Value'] ?? 0,
            'idle_time' => time() - $this->last_activity,
            'connected' => $this->connected
        ];
    }

    // Close connection when idle for too long
    public function checkIdleConnection() {
        if ($this->connected && (time() - $this->last_activity) > $this->max_idle_time) {
            $this->close();
        }
    }

    public function close() {
        if ($this->connection && $this->connected) {
            $this->connection->close();
            $this->connected = false;
        }
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        $this->connect();
    }

    // Destructor
    public function __destruct() {
        $this->close();
    }
}

// Backward compatibility functions
function get_db_connection() {
    return DatabasePool::getInstance()->getConnection();
}

function db_query($sql, $params = []) {
    return DatabasePool::getInstance()->query($sql, $params);
}

function db_prepare($sql) {
    return DatabasePool::getInstance()->prepare($sql);
}

function db_escape($string) {
    return DatabasePool::getInstance()->escape($string);
}

// Auto-cleanup idle connections
register_shutdown_function(function() {
    DatabasePool::getInstance()->checkIdleConnection();
});
?>