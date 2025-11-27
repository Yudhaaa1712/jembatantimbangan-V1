<?php
/**
 * Master Data Handler - DRY Implementation
 *
 * This class provides common CRUD operations for master data modules
 * to reduce code duplication across the application.
 */

class MasterDataHandler {
    private $conn;
    private $table_name;
    private $primary_key = 'id';
    private $columns = [];
    private $required_fields = [];
    private $searchable_fields = [];
    private $validation_rules = [];

    public function __construct($conn, $config) {
        $this->conn = $conn;
        $this->table_name = $config['table_name'];
        $this->primary_key = $config['primary_key'] ?? 'id';
        $this->columns = $config['columns'] ?? [];
        $this->required_fields = $config['required_fields'] ?? [];
        $this->searchable_fields = $config['searchable_fields'] ?? [];
        $this->validation_rules = $config['validation_rules'] ?? [];
    }

    /**
     * Get all records with optional filtering and pagination
     */
    public function getAll($page = 1, $limit = 50, $search = '', $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];
        $types = '';

        // Build WHERE conditions
        if (!empty($search)) {
            $search_conditions = [];
            foreach ($this->searchable_fields as $field) {
                $search_conditions[] = "$field LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            }
            $where_conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
        }

        foreach ($filters as $field => $value) {
            if (in_array($field, $this->columns) && !empty($value)) {
                $where_conditions[] = "$field = ?";
                $params[] = $value;
                $types .= is_numeric($value) ? 'i' : 's';
            }
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM {$this->table_name} $where_clause";
        $count_stmt = mysqli_prepare($this->conn, $count_query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $total = mysqli_fetch_assoc($count_result)['total'];

        // Get data
        $query = "SELECT * FROM {$this->table_name} $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $data_params = array_merge($params, [$limit, $offset]);
        $data_types = $types . 'ii';

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $data_types, ...$data_params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return [
            'data' => $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get single record by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return $result ? mysqli_fetch_assoc($result) : null;
    }

    /**
     * Create new record
     */
    public function create($data) {
        // Validate required fields
        $missing_fields = array_diff($this->required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
        }

        // Validate data
        $this->validateData($data);

        // Build query
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $query = "INSERT INTO {$this->table_name} (" . implode(', ', $columns) . ")
                  VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = mysqli_prepare($this->conn, $query);
        $types = $this->getParamTypes($values);
        mysqli_stmt_bind_param($stmt, $types, ...$values);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create record: " . mysqli_stmt_error($stmt));
        }

        return mysqli_insert_id($this->conn);
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        // Validate data
        $this->validateData($data);

        // Build SET clause
        $set_clauses = [];
        $values = [];
        $types = '';

        foreach ($data as $column => $value) {
            if (in_array($column, $this->columns)) {
                $set_clauses[] = "$column = ?";
                $values[] = $value;
                $types .= is_numeric($value) ? (is_int($value) ? 'i' : 'd') : 's';
            }
        }

        if (empty($set_clauses)) {
            throw new Exception("No valid columns to update");
        }

        $query = "UPDATE {$this->table_name} SET " . implode(', ', $set_clauses) . "
                  WHERE {$this->primary_key} = ?";

        $values[] = $id;
        $types .= 'i';

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$values);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update record: " . mysqli_stmt_error($stmt));
        }

        return mysqli_stmt_affected_rows($stmt) > 0;
    }

    /**
     * Delete record
     */
    public function delete($id) {
        // Check for dependencies
        $this->checkDependencies($id);

        $query = "DELETE FROM {$this->table_name} WHERE {$this->primary_key} = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to delete record: " . mysqli_stmt_error($stmt));
        }

        return mysqli_stmt_affected_rows($stmt) > 0;
    }

    /**
     * Check if record exists
     */
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM {$this->table_name} WHERE {$this->primary_key} = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row['count'] > 0;
    }

    /**
     * Validate data against rules
     */
    private function validateData($data) {
        foreach ($this->validation_rules as $field => $rules) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = $data[$field];

            foreach ($rules as $rule => $rule_value) {
                switch ($rule) {
                    case 'required':
                        if ($rule_value && empty($value)) {
                            throw new Exception("$field is required");
                        }
                        break;

                    case 'max_length':
                        if (strlen($value) > $rule_value) {
                            throw new Exception("$field exceeds maximum length of $rule_value");
                        }
                        break;

                    case 'min_length':
                        if (strlen($value) < $rule_value) {
                            throw new Exception("$field must be at least $rule_value characters");
                        }
                        break;

                    case 'pattern':
                        if (!preg_match($rule_value, $value)) {
                            throw new Exception("$field format is invalid");
                        }
                        break;

                    case 'in':
                        if (!in_array($value, $rule_value)) {
                            throw new Exception("$field must be one of: " . implode(', ', $rule_value));
                        }
                        break;

                    case 'numeric':
                        if ($rule_value && !is_numeric($value)) {
                            throw new Exception("$field must be numeric");
                        }
                        break;

                    case 'email':
                        if ($rule_value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("$field must be a valid email address");
                        }
                        break;
                }
            }
        }
    }

    /**
     * Check for record dependencies
     */
    private function checkDependencies($id) {
        // Override in child classes if needed
        return true;
    }

    /**
     * Get parameter types for prepared statements
     */
    private function getParamTypes($values) {
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Get table statistics
     */
    public function getStatistics() {
        $query = "SELECT
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_records,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_records,
                    MAX(created_at) as last_created,
                    MAX(updated_at) as last_updated
                  FROM {$this->table_name}";

        $result = mysqli_query($this->conn, $query);
        return $result ? mysqli_fetch_assoc($result) : null;
    }
}

/**
 * Specific handlers for different master data types
 */
class SupplierHandler extends MasterDataHandler {
    public function __construct($conn) {
        parent::__construct($conn, [
            'table_name' => 'supplier',
            'columns' => ['kode_supplier', 'nama_supplier', 'alamat', 'no_telepon', 'email', 'npwp', 'kontak_person', 'total_hutang', 'status'],
            'required_fields' => ['kode_supplier', 'nama_supplier'],
            'searchable_fields' => ['kode_supplier', 'nama_supplier', 'alamat', 'no_telepon', 'email'],
            'validation_rules' => [
                'kode_supplier' => ['required' => true, 'max_length' => 20],
                'nama_supplier' => ['required' => true, 'max_length' => 100],
                'no_telepon' => ['max_length' => 20],
                'email' => ['email' => true, 'max_length' => 100],
                'total_hutang' => ['numeric' => true, 'min' => 0],
                'status' => ['in' => ['active', 'inactive', 'blacklist']]
            ]
        ]);
    }

    protected function checkDependencies($id) {
        // Check if supplier has transactions
        $query = "SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_supplier = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];

        if ($count > 0) {
            throw new Exception("Cannot delete supplier: has $count related transactions");
        }
    }

    /**
     * Update hutang supplier
     */
    public function updateHutang($supplier_id, $jumlah_bayar, $keterangan = '') {
        // Get current supplier data
        $supplier = $this->getById($supplier_id);
        if (!$supplier) {
            throw new Exception("Supplier not found");
        }

        $current_hutang = floatval($supplier['total_hutang'] ?? 0);
        $jumlah_bayar = floatval($jumlah_bayar);

        if ($jumlah_bayar > $current_hutang) {
            throw new Exception("Jumlah bayar (Rp " . number_format($jumlah_bayar) . ") melebihi total hutang (Rp " . number_format($current_hutang) . ")");
        }

        $new_hutang = $current_hutang - $jumlah_bayar;

        // Update hutang in database
        $query = "UPDATE {$this->table_name}
                  SET total_hutang = ?, hutang_terakhir_update = NOW()
                  WHERE {$this->primary_key} = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'di', $new_hutang, $supplier_id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update hutang: " . mysqli_error($this->conn));
        }

        // Log hutang transaction (if needed)
        $this->logHutangTransaction($supplier_id, $current_hutang, $jumlah_bayar, $new_hutang, $keterangan);

        return [
            'success' => true,
            'previous_hutang' => $current_hutang,
            'jumlah_bayar' => $jumlah_bayar,
            'new_hutang' => $new_hutang
        ];
    }

    /**
     * Add hutang to supplier
     */
    public function addHutang($supplier_id, $jumlah_hutang, $keterangan = '') {
        // Get current supplier data
        $supplier = $this->getById($supplier_id);
        if (!$supplier) {
            throw new Exception("Supplier not found");
        }

        $current_hutang = floatval($supplier['total_hutang'] ?? 0);
        $jumlah_hutang = floatval($jumlah_hutang);

        if ($jumlah_hutang <= 0) {
            throw new Exception("Jumlah hutang harus lebih dari 0");
        }

        $new_hutang = $current_hutang + $jumlah_hutang;

        // Update hutang in database
        $query = "UPDATE {$this->table_name}
                  SET total_hutang = ?, hutang_terakhir_update = NOW()
                  WHERE {$this->primary_key} = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'di', $new_hutang, $supplier_id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update hutang: " . mysqli_error($this->conn));
        }

        // Log hutang transaction (if needed)
        $this->logHutangTransaction($supplier_id, $current_hutang, -$jumlah_hutang, $new_hutang, $keterangan);

        return [
            'success' => true,
            'previous_hutang' => $current_hutang,
            'jumlah_hutang' => $jumlah_hutang,
            'new_hutang' => $new_hutang
        ];
    }

    /**
     * Log hutang transaction (placeholder for future implementation)
     */
    private function logHutangTransaction($supplier_id, $previous_hutang, $perubahan, $new_hutang, $keterangan) {
        // TODO: Implement logging to hutang_transactions table
        // For now, we could log to general activity logs
        error_log("HUTANG UPDATE: Supplier ID: $supplier_id, From: $previous_hutang, Change: $perubahan, To: $new_hutang, Note: $keterangan");
    }
}

class KendaraanHandler extends MasterDataHandler {
    public function __construct($conn) {
        parent::__construct($conn, [
            'table_name' => 'kendaraan',
            'columns' => ['no_polisi', 'jenis_kendaraan', 'kapasitas_maksimal', 'pemilik', 'no_telepon', 'alamat', 'status'],
            'required_fields' => ['no_polisi'],
            'searchable_fields' => ['no_polisi', 'pemilik', 'no_telepon'],
            'validation_rules' => [
                'no_polisi' => ['required' => true, 'max_length' => 20, 'pattern' => '/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/'],
                'jenis_kendaraan' => ['in' => ['truk', 'tronton', 'container', 'pickup', 'lainnya']],
                'kapasitas_maksimal' => ['numeric' => true],
                'status' => ['in' => ['active', 'inactive', 'maintenance']]
            ]
        ]);
    }

    protected function checkDependencies($id) {
        // Check if vehicle has transactions
        $query = "SELECT COUNT(*) as count FROM transaksi_timbangan WHERE id_kendaraan = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];

        if ($count > 0) {
            throw new Exception("Cannot delete vehicle: has $count related transactions");
        }
    }
}
?>