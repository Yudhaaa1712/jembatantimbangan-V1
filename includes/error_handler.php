<?php
/**
 * Comprehensive Error Handling and Logging System
 *
 * This file provides centralized error handling, logging, and debugging
 * functionality for the Jembatan Timbangan application.
 */

class ErrorHandler {
    private static $instance = null;
    private $log_file = null;
    private $error_log_file = null;
    private $security_log_file = null;
    private $debug_mode = false;

    private function __construct() {
        $this->log_file = __DIR__ . '/../logs/application.log';
        $this->error_log_file = __DIR__ . '/../logs/errors.log';
        $this->security_log_file = __DIR__ . '/../logs/security.log';
        $this->debug_mode = defined('DEBUG_MODE') && DEBUG_MODE;

        // Create log directories if they don't exist
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // Set custom error handler
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line) {
        // Don't handle suppressed errors
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $error_types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];

        $error_type = $error_types[$severity] ?? 'UNKNOWN';
        $this->logError($error_type, $message, $file, $line);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $this->logError('FATAL', $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString());

        if ($this->debug_mode) {
            echo "<div style='background: #ff6b6b; color: white; padding: 20px; border-radius: 5px; margin: 20px;'>";
            echo "<h3>Fatal Error</h3>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<pre style='background: rgba(0,0,0,0.2); padding: 10px; border-radius: 3px; overflow: auto;'>";
            echo htmlspecialchars($exception->getTraceAsString());
            echo "</pre>";
            echo "</div>";
        } else {
            // Show user-friendly error page in production
            http_response_code(500);
            include __DIR__ . '/../error_pages/500.php';
        }

        exit;
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $this->logError('FATAL', $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Log errors to file
     */
    private function logError($type, $message, $file, $line, $trace = '') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $type: $message in $file on line $line\n";

        if (!empty($trace)) {
            $log_entry .= "Stack trace:\n$trace\n";
        }

        $log_entry .= "----------------------------------------\n";

        file_put_contents($this->error_log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Send email notification for critical errors
        if (in_array($type, ['ERROR', 'FATAL', 'CORE_ERROR']) && defined('ADMIN_EMAIL')) {
            $this->sendErrorNotification($type, $message, $file, $line);
        }
    }

    /**
     * Log application events
     */
    public function logEvent($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[$timestamp] [$level] User:$user_id IP:$ip_address - $message$context_str\n";

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log security events
     */
    public function logSecurityEvent($event_type, $description, $user_id = null, $ip_address = null, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $user = $user_id ?? $_SESSION['user_id'] ?? 'anonymous';
        $ip = $ip_address ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[$timestamp] SECURITY:$event_type User:$user IP:$ip - $description$context_str\n";

        file_put_contents($this->security_log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Send immediate notification for critical security events
        if (in_array($event_type, ['SQL_INJECTION_ATTEMPT', 'BRUTE_FORCE_ATTACK', 'PRIVILEGE_ESCALATION']) && defined('SECURITY_EMAIL')) {
            $this->sendSecurityNotification($event_type, $description, $user, $ip);
        }
    }

    /**
     * Send error notification to admin
     */
    private function sendErrorNotification($type, $message, $file, $line) {
        if (!defined('ADMIN_EMAIL') || !ADMIN_EMAIL) {
            return;
        }

        $subject = "Error Notification - Jembatan Timbangan";
        $body = "An error occurred on your website:\n\n";
        $body .= "Type: $type\n";
        $body .= "Message: $message\n";
        $body .= "File: $file\n";
        $body .= "Line: $line\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

        $headers = "From: noreply@jembatantimbangan.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail(ADMIN_EMAIL, $subject, $body, $headers);
    }

    /**
     * Send security notification to admin
     */
    private function sendSecurityNotification($event_type, $description, $user, $ip) {
        if (!defined('SECURITY_EMAIL') || !SECURITY_EMAIL) {
            return;
        }

        $subject = "Security Alert - Jembatan Timbangan";
        $body = "A security event was detected:\n\n";
        $body .= "Event Type: $event_type\n";
        $body .= "Description: $description\n";
        $body .= "User: $user\n";
        $body .= "IP Address: $ip\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

        $headers = "From: noreply@jembatantimbangan.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail(SECURITY_EMAIL, $subject, $body, $headers);
    }

    /**
     * Check for suspicious activity patterns
     */
    public function checkSuspiciousActivity($ip_address, $time_window = 300, $max_attempts = 10) {
        $log_content = file_get_contents($this->security_log_file);
        $time_threshold = time() - $time_window;

        $recent_entries = [];
        $lines = explode("\n", $log_content);

        foreach ($lines as $line) {
            if (strpos($line, $ip_address) !== false) {
                preg_match('/\[([\d-]+\s[\d:]+)\]/', $line, $matches);
                if (isset($matches[1])) {
                    $timestamp = strtotime($matches[1]);
                    if ($timestamp > $time_threshold) {
                        $recent_entries[] = $line;
                    }
                }
            }
        }

        return count($recent_entries) >= $max_attempts;
    }

    /**
     * Get system health report
     */
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Check database connection
        global $conn;
        if ($conn && mysqli_ping($conn)) {
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connection healthy'];
        } else {
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $health['status'] = 'unhealthy';
        }

        // Check log file sizes
        $error_log_size = file_exists($this->error_log_file) ? filesize($this->error_log_file) : 0;
        if ($error_log_size > 10 * 1024 * 1024) { // 10MB
            $health['checks']['error_log'] = ['status' => 'warning', 'message' => 'Error log file is large'];
        } else {
            $health['checks']['error_log'] = ['status' => 'ok', 'message' => 'Error log size normal'];
        }

        // Check disk space
        $free_space = disk_free_space(__DIR__ . '/..');
        $total_space = disk_total_space(__DIR__ . '/..');
        $space_usage = $total_space > 0 ? ($total_space - $free_space) / $total_space * 100 : 0;

        if ($space_usage > 90) {
            $health['checks']['disk_space'] = ['status' => 'error', 'message' => 'Disk space critically low'];
            $health['status'] = 'unhealthy';
        } elseif ($space_usage > 80) {
            $health['checks']['disk_space'] = ['status' => 'warning', 'message' => 'Disk space getting low'];
        } else {
            $health['checks']['disk_space'] = ['status' => 'ok', 'message' => 'Disk space adequate'];
        }

        return $health;
    }

    /**
     * Clean up old log files
     */
    public function cleanupLogs($retention_days = 30) {
        $cutoff_time = time() - ($retention_days * 24 * 60 * 60);

        $log_files = [
            $this->log_file,
            $this->error_log_file,
            $this->security_log_file
        ];

        foreach ($log_files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                // Archive old logs instead of deleting
                $archive_file = $file . '.' . date('Y-m-d', filemtime($file)) . '.gz';
                $log_content = file_get_contents($file);
                file_put_contents($archive_file, gzencode($log_content));
                file_put_contents($file, ''); // Clear current log
            }
        }
    }
}

// Initialize error handler
$error_handler = ErrorHandler::getInstance();

/**
 * Convenience functions
 */
function log_info($message, $context = []) {
    ErrorHandler::getInstance()->logEvent('INFO', $message, $context);
}

function log_warning($message, $context = []) {
    ErrorHandler::getInstance()->logEvent('WARNING', $message, $context);
}

function log_error($message, $context = []) {
    ErrorHandler::getInstance()->logEvent('ERROR', $message, $context);
}

function log_security($event_type, $description, $user_id = null, $ip_address = null) {
    ErrorHandler::getInstance()->logSecurityEvent($event_type, $description, $user_id, $ip_address);
}
?>