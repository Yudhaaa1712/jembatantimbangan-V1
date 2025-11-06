<?php
// reset_database.php - Fresh database setup
require_once 'config/database.php';

echo "<h2>🔄 DATABASE RESET - FRESH INSTALL</h2>";

try {
    // Connect ke MySQL
    $pdo = new PDO("mysql:host={$db_config['host']}", $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop dan create database baru
    $dbname = $db_config['dbname'];

    echo "<h3>Step 1: Dropping old database...</h3>";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    echo "✅ Old database dropped<br>";

    echo "<h3>Step 2: Creating new database...</h3>";
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ New database created<br>";

    echo "<h3>Step 3: Importing fresh schema...</h3>";
    $pdo->exec("USE `$dbname`");

    // Read and execute setup SQL
    $sql = file_get_contents('setup_database.sql');

    // Remove comments dan split statements
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                echo "⚠️ Warning: " . $e->getMessage() . "<br>";
            }
        }
    }
    echo "✅ Database schema imported<br>";

    echo "<h3>Step 4: Verifying setup...</h3>";

    // Test connection ke new database
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $dbname);

    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }

    // Check tables
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    echo "✅ Tables created: " . implode(', ', $tables) . "<br>";

    // Check sample data
    $queries = [
        'users' => 'SELECT COUNT(*) as count FROM users',
        'kendaraan' => 'SELECT COUNT(*) as count FROM kendaraan',
        'supplier' => 'SELECT COUNT(*) as count FROM supplier',
        'transaksi_timbangan' => 'SELECT COUNT(*) as count FROM transaksi_timbangan'
    ];

    foreach ($queries as $table => $query) {
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        echo "✅ $table: {$row['count']} records<br>";
    }

    // Test login
    $result = $conn->query("SELECT username, nama_lengkap, role FROM users WHERE username = 'admin'");
    if ($row = $result->fetch_assoc()) {
        echo "✅ Admin user ready: {$row['username']} ({$row['nama_lengkap']}) - {$row['role']}<br>";
    }

    echo "<h3>🎉 SUCCESS! Database is now fresh and ready!</h3>";
    echo "<p><strong>Default Login:</strong> admin / password</p>";
    echo "<p><a href='modules/timbangan/timbangan1.php'>🚀 Go to Timbangan 1</a> | ";
    echo "<a href='modules/timbangan/timbangan2.php'>🚀 Go to Timbangan 2</a></p>";

    $conn->close();

    // Delete reset file
    echo "<script>
        setTimeout(() => {
            if (confirm('Database reset successful! Delete this reset file?')) {
                fetch('delete_reset.php');
            }
        }, 3000);
    </script>";

} catch (Exception $e) {
    echo "<h3>❌ ERROR: " . $e->getMessage() . "</h3>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}
?>