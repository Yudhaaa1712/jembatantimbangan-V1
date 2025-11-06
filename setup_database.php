<?php
// setup_database.php - Script untuk memastikan struktur database benar
require_once 'config/database.php';

echo "<h3>Database Setup for Jembatan Timbangan</h3>";

try {
    // Cek struktur table supplier
    echo "<h4>Checking supplier table structure...</h4>";
    $query = "DESCRIBE supplier";
    $result = mysqli_query($conn, $query);

    $fields = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $fields[] = $row;
        echo "- {$row['Field']} ({$row['Type']}) - {$row['Null']} - {$row['Key']}<br>";
    }

    // Cek apakah kolom yang dibutuhkan ada
    $required_fields = ['id', 'kode_supplier', 'nama_supplier', 'status', 'created_at'];
    $existing_fields = array_column($fields, 'Field');
    $missing_fields = array_diff($required_fields, $existing_fields);

    if (!empty($missing_fields)) {
        echo "<h4>Adding missing fields...</h4>";
        foreach ($missing_fields as $field) {
            switch ($field) {
                case 'kode_supplier':
                    $query = "ALTER TABLE supplier ADD COLUMN kode_supplier VARCHAR(20) UNIQUE AFTER id";
                    echo "Adding kode_supplier column...<br>";
                    break;
                case 'nama_supplier':
                    $query = "ALTER TABLE supplier ADD COLUMN nama_supplier VARCHAR(100) NOT NULL AFTER kode_supplier";
                    echo "Adding nama_supplier column...<br>";
                    break;
                case 'status':
                    $query = "ALTER TABLE supplier ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER nama_supplier";
                    echo "Adding status column...<br>";
                    break;
                case 'created_at':
                    $query = "ALTER TABLE supplier ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status";
                    echo "Adding created_at column...<br>";
                    break;
            }
            mysqli_query($conn, $query);
        }
    }

    // Update existing records yang tidak punya kode_supplier
    echo "<h4>Updating existing records...</h4>";
    $query = "SELECT id, kode_supplier FROM supplier WHERE kode_supplier IS NULL OR kode_supplier = ''";
    $result = mysqli_query($conn, $query);
    $count = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $kode_supplier = 'SUP-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
        $update_query = "UPDATE supplier SET kode_supplier = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $kode_supplier, $row['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $count++;
    }

    if ($count > 0) {
        echo "Updated $count records with kode_supplier<br>";
    } else {
        echo "No records need updating<br>";
    }

    // Cek transaksi_timbangan table
    echo "<h4>Checking transaksi_timbangan table...</h4>";
    $query = "SHOW COLUMNS FROM transaksi_timbangan LIKE 'berat_timbangan1'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 0) {
        echo "Adding berat_timbangan1 column...<br>";
        $query = "ALTER TABLE transaksi_timbangan ADD COLUMN berat_timbangan1 DECIMAL(10,2) DEFAULT 0.00 AFTER berat_bruto";
        mysqli_query($conn, $query);
    }

    // Test supplier creation
    echo "<h4>Testing supplier creation...</h4>";
    $test_nama = "TEST_SUPPLIER_" . time();
    $kode_supplier = 'SUP-' . date('ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

    try {
        $query = "INSERT INTO supplier (kode_supplier, nama_supplier, status, created_at) VALUES (?, ?, 'active', NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $kode_supplier, $test_nama);
        mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Delete test record
        $delete_query = "DELETE FROM supplier WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $new_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        echo "✅ Supplier creation test PASSED<br>";
    } catch (Exception $e) {
        echo "❌ Supplier creation test FAILED: " . $e->getMessage() . "<br>";
    }

    echo "<h3>✅ Database setup completed!</h3>";
    echo "<p><a href='modules/timbangan/timbangan1.php'>Test Timbangan 1</a> |
          <a href='modules/timbangan/timbangan2.php'>Test Timbangan 2</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ ERROR: " . $e->getMessage() . "</h3>";
}

mysqli_close($conn);
?>

<!-- Auto delete setup file after 10 seconds -->
<script>
setTimeout(() => {
    if (confirm('Setup completed! Delete this setup file?')) {
        window.location.href = 'delete_setup.php';
    }
}, 10000);
</script>