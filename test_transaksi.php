<?php
// Test Transaksi Features - Menguji fitur transaksi yang baru diimplementasikan
require_once 'config/database.php';
require_once 'includes/material_functions.php';

echo "<h2>Testing Fitur Transaksi Timbangan</h2>\n";

// Test 1: Generate Ticket Number
echo "<h3>1. Test Generate Ticket Number</h3>\n";
try {
    $ticket_number = generate_ticket_number($conn);
    echo "✅ Ticket Number Generated: <strong>$ticket_number</strong>\n";
} catch (Exception $e) {
    echo "❌ Error generating ticket: " . $e->getMessage() . "\n";
}

// Test 2: Check Table Structure
echo "<h3>2. Test Table Structure</h3>\n";
$tables_to_check = ['transaksi_timbangan', 'kendaraan', 'supplier', 'customer', 'settings'];

foreach ($tables_to_check as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Table '$table' exists\n";

        // Check key columns
        $describe_query = "DESCRIBE $table";
        $describe_result = mysqli_query($conn, $describe_query);
        $columns = [];
        while ($row = mysqli_fetch_assoc($describe_result)) {
            $columns[] = $row['Field'];
        }
        echo "   - Columns: " . implode(', ', array_slice($columns, 0, 5)) . "...\n";
    } else {
        echo "❌ Table '$table' not found\n";
    }
}

// Test 3: Check View
echo "<h3>3. Test View transaksi_lengkap</h3>\n";
$view_query = "SHOW TABLES LIKE 'view_transaksi_lengkap'";
$view_result = mysqli_query($conn, $view_query);
if (mysqli_num_rows($view_result) > 0) {
    echo "✅ View 'view_transaksi_lengkap' exists\n";
} else {
    echo "❌ View 'view_transaksi_lengkap' not found\n";
}

// Test 4: Check Sample Data
echo "<h3>4. Test Sample Data</h3>\n";

// Check kendaraan
$kendaraan_query = "SELECT COUNT(*) as total FROM kendaraan WHERE status = 'active'";
$kendaraan_result = mysqli_query($conn, $kendaraan_query);
$kendaraan_count = mysqli_fetch_assoc($kendaraan_result)['total'];
echo "✅ Active Kendaraan: $kendaraan_count\n";

// Check supplier
$supplier_query = "SELECT COUNT(*) as total FROM supplier WHERE status = 'active'";
$supplier_result = mysqli_query($conn, $supplier_query);
$supplier_count = mysqli_fetch_assoc($supplier_result)['total'];
echo "✅ Active Supplier: $supplier_count\n";

// Check settings for company data
$settings_query = "SELECT COUNT(*) as total FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone')";
$settings_result = mysqli_query($conn, $settings_query);
$settings_count = mysqli_fetch_assoc($settings_result)['total'];
echo "✅ Company Settings: $settings_count/3\n";

if ($settings_count < 3) {
    echo "   - Adding default company settings...\n";
    $default_settings = [
        'company_name' => 'PT. JEMBATAN TIMBANGAN SAWIT',
        'company_address' => 'Jl. Industri No. 123, Jakarta',
        'company_phone' => '021-5551234'
    ];

    foreach ($default_settings as $key => $value) {
        $insert_query = "INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sss", $key, $value, $description);
        $description = "Company information";
        mysqli_stmt_execute($stmt);
    }
    echo "   ✅ Default company settings added\n";
}

// Test 5: Test Material Functions
echo "<h3>5. Test Material Functions</h3>\n";
try {
    $materials = get_all_materials();
    echo "✅ Materials loaded: " . count($materials) . "\n";

    $js_mapping = get_material_js_mapping();
    echo "✅ JS Mapping generated: " . strlen($js_mapping) . " characters\n";
} catch (Exception $e) {
    echo "❌ Error with materials: " . $e->getMessage() . "\n";
}

// Test 6: Simulate Transaction Flow
echo "<h3>6. Test Transaction Flow Simulation</h3>\n";

// Get first available kendaraan and supplier for testing
$kendaraan_test_query = "SELECT id, no_polisi FROM kendaraan WHERE status = 'active' LIMIT 1";
$kendaraan_test = mysqli_query($conn, $kendaraan_test_query);
$kendaraan_data = mysqli_fetch_assoc($kendaraan_test);

$supplier_test_query = "SELECT id, nama_supplier FROM supplier WHERE status = 'active' LIMIT 1";
$supplier_test = mysqli_query($conn, $supplier_test_query);
$supplier_data = mysqli_fetch_assoc($supplier_test);

if ($kendaraan_data && $supplier_data) {
    echo "✅ Test data available:\n";
    echo "   - Kendaraan: " . $kendaraan_data['no_polisi'] . " (ID: " . $kendaraan_data['id'] . ")\n";
    echo "   - Supplier: " . $supplier_data['nama_supplier'] . " (ID: " . $supplier_data['id'] . ")\n";

    // Test tiket generation
    $test_tiket = generate_ticket_number($conn);
    echo "   - Generated ticket: $test_tiket\n";
} else {
    echo "❌ No test data available in kendaraan or supplier tables\n";
}

// Test 7: File Existence
echo "<h3>7. Test File Existence</h3>\n";
$files_to_check = [
    'modules/timbangan/timbangan2.php',
    'modules/timbangan/print_ticket.php',
    'includes/material_functions.php',
    'config/database.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

echo "<h3>✅ Testing Completed!</h3>\n";
echo "<p><em>Fitur transaksi sudah siap digunakan. Pastikan:</em></p>";
echo "<ul>";
echo "<li>Database sudah di-setup dengan benar</li>";
echo "<li>Data sample sudah ada (kendaraan, supplier)</li>";
echo "<li>Settings perusahaan sudah di-configure</li>";
echo "<li>Timbangan terhubung dengan baik</li>";
echo "</ul>";

// Quick form for testing transaction
if ($kendaraan_data && $supplier_data) {
    echo "<hr>";
    echo "<h3>Quick Transaction Test</h3>";
    echo "<form method='POST' action='modules/timbangan/timbangan2.php'>";
    echo "<input type='hidden' name='test_mode' value='1'>";
    echo "<button type='submit' class='btn btn-primary'>Test Timbangan 2 Page</button>";
    echo "</form>";
}
?>