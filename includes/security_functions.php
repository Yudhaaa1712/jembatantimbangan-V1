<?php
/**
 * Security Functions Library
 *
 * This file contains common security functions to:
 * - Prevent SQL injection
 * - Validate input data
 * - Generate CSRF tokens
 * - Handle authentication
 * - Sanitize output
 */

class SecurityUtils {
    private static $csrf_token = null;

    /**
     * Generate and store CSRF token
     */
    public static function generateCSRFToken() {
        if (self::$csrf_token === null) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_SESSION['csrf_token']) ||
                !isset($_SESSION['csrf_token_time']) ||
                time() - $_SESSION['csrf_token_time'] > 3600) {

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['csrf_token_time'] = time();
            }

            self::$csrf_token = $_SESSION['csrf_token'];
        }

        return self::$csrf_token;
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) ||
            !isset($_SESSION['csrf_token_time']) ||
            time() - $_SESSION['csrf_token_time'] > 3600) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $type = 'string') {
        if ($data === null) {
            return null;
        }

        switch ($type) {
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT);
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL);
            case 'boolean':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN);
            case 'string':
            default:
                return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
    }

    /**
     * Validate date format
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate Indonesian vehicle license plate format
     */
    public static function validateLicensePlate($plate) {
        // Basic Indonesian license plate format: B 1234 AB
        return preg_match('/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/', strtoupper(trim($plate)));
    }

    /**
     * Validate material types
     */
    public static function validateMaterialType($material) {
        $valid_materials = ['tbs', 'cpo', 'kernel', 'brondolan', 'lainnya'];
        return in_array(strtolower(trim($material)), $valid_materials);
    }

    /**
     * Validate user roles
     */
    public static function validateRole($role) {
        $valid_roles = ['admin', 'operator', 'viewer'];
        return in_array(strtolower(trim($role)), $valid_roles);
    }

    /**
     * Safe database query execution
     */
    public static function safeQuery($conn, $query, $params = [], $types = '') {
        if (empty($params)) {
            $result = mysqli_query($conn, $query);
            if (!$result) {
                throw new Exception("Database query failed: " . mysqli_error($conn));
            }
            return $result;
        }

        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
        }

        if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            throw new Exception("Failed to bind parameters: " . mysqli_stmt_error($stmt));
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute statement: " . mysqli_stmt_error($stmt));
        }

        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            throw new Exception("Failed to get result: " . mysqli_stmt_error($stmt));
        }

        return $result;
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent($conn, $event_type, $description, $user_id = null, $ip_address = null) {
        try {
            $query = "INSERT INTO security_logs (event_type, description, user_id, ip_address, user_agent, created_at)
                      VALUES (?, ?, ?, ?, ?, NOW())";

            $ip = $ip_address ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssss", $event_type, $description, $user_id, $ip, $user_agent);
            mysqli_stmt_execute($stmt);

        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }

    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity($conn, $ip_address, $time_window = 300, $max_attempts = 10) {
        try {
            $query = "SELECT COUNT(*) as attempts FROM security_logs
                      WHERE ip_address = ? AND event_type IN ('FAILED_LOGIN', 'SQL_INJECTION_ATTEMPT', 'CSRF_FAILURE')
                      AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "si", $ip_address, $time_window);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            return $row['attempts'] >= $max_attempts;

        } catch (Exception $e) {
            error_log("Failed to check suspicious activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate secure password hash
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Escape output for HTML
     */
    public static function escapeOutput($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
        $errors = [];

        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
        }

        if ($file['size'] > $max_size) {
            $errors[] = "File size exceeds maximum allowed size";
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = "File type not allowed";
        }

        // Check MIME type
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        ];

        if (isset($allowed_mimes[$file_extension])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($mime_type !== $allowed_mimes[$file_extension]) {
                $errors[] = "File MIME type does not match extension";
            }
        }

        return $errors;
    }

    /**
     * Rate limiting
     */
    public static function rateLimit($identifier, $limit = 60, $window = 3600) {
        $cache_file = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);

        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);

            if (time() - $data['start_time'] > $window) {
                // Reset window
                $data = ['count' => 1, 'start_time' => time()];
            } elseif ($data['count'] >= $limit) {
                return false; // Rate limit exceeded
            } else {
                $data['count']++;
            }
        } else {
            $data = ['count' => 1, 'start_time' => time()];
        }

        file_put_contents($cache_file, json_encode($data));
        return true;
    }

    /**
     * Input validation patterns
     */
    public static function getValidationPatterns() {
        return [
            'ticket_number' => '/^[A-Z0-9]{3}-\d{6}-\d{3}$/', // TKT-YYMMDD-XXX
            'phone_number' => '/^(\+62|62)?[\s-]?0?8[1-9][0-9]{6,9}$/', // Indonesian phone format
            'postal_code' => '/^\d{5}$/', // Indonesian postal code
            'npwp' => '/^\d{2}\.\d{3}\.\d{3}\.\d-\d{3}\.\d{3}$/', // Indonesian NPWP format
        ];
    }

    /**
     * Validate input against pattern
     */
    public static function validatePattern($input, $pattern_name) {
        $patterns = self::getValidationPatterns();

        if (!isset($patterns[$pattern_name])) {
            return false;
        }

        return preg_match($patterns[$pattern_name], $input);
    }
}

/**
 * Convenience functions for backward compatibility
 */
function sanitize_input($data) {
    return SecurityUtils::sanitizeInput($data);
}

function generate_csrf_token() {
    return SecurityUtils::generateCSRFToken();
}

function validate_csrf_token($token) {
    return SecurityUtils::validateCSRFToken($token);
}

function safe_query($conn, $query, $params = [], $types = '') {
    return SecurityUtils::safeQuery($conn, $query, $params, $types);
}
?>