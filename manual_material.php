<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material = $_POST['material_selected'];
    $no_tiket = 'MANUAL-' . date('YmdHis');
    $no_kendaraan = $_POST['no_kendaraan'];
    $nama_pengemudi = $_POST['nama_pengemudi'];
    $nama_suplier = $_POST['nama_suplier'];
    $harga = $_POST['harga'];
    $berat = $_POST['berat'];

    echo "<h2>MATERIAL MANUAL INPUT - DEBUG</h2>";
    echo "<strong>Data yang akan disimpan:</strong><br>";
    echo "Material: <span style='color: green; font-size: 20px;'>$material</span><br>";
    echo "Harga: <span style='color: blue;'>$harga</span><br>";
    echo "Berat: <span style='color: orange;'>$berat</span><br><br>";

    // Query dengan material manual
    $query = "INSERT INTO transaksi_timbangan
              (no_tiket, no_polisi, nama_supir, id_supplier, jenis_material, harga_per_kg, berat_bruto, berat_timbangan1, tanggal, created_at, status, timbang1_locked, waktu_timbangan1)
              VALUES (?, ?, ?, (SELECT id FROM supplier WHERE nama_supplier = ?), ?, ?, ?, ?, CURDATE(), NOW(), 'timbang_1', 1, NOW())";

    echo "<strong>Query:</strong><br><code>$query</code><br><br>";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssdsss", $no_tiket, $no_kendaraan, $nama_pengemudi, $nama_suplier, $material, $harga, $berat, $berat);

    if (mysqli_stmt_execute($stmt)) {
        echo "<h3 style='color: green;'>✅ BERHASIL DISIMPAN!</h3>";
        echo "Tiket: <strong>$no_tiket</strong><br>";

        // Verify data tersimpan
        $verify_query = "SELECT jenis_material, harga_per_kg, no_polisi FROM transaksi_timbangan WHERE no_tiket = ?";
        $verify_stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($verify_stmt, "s", $no_tiket);
        mysqli_stmt_execute($verify_stmt);
        $result = mysqli_stmt_get_result($verify_stmt);
        $row = mysqli_fetch_assoc($result);

        echo "<h4>VERIFICATION:</h4>";
        echo "Material di DB: <strong style='color: " . (empty($row['jenis_material']) ? 'red' : 'green') . "'>" . ($row['jenis_material'] ?? 'NULL') . "</strong><br>";
        echo "Harga di DB: <strong>" . $row['harga_per_kg'] . "</strong><br>";
        echo "No Polisi: <strong>" . $row['no_polisi'] . "</strong><br><br>";

        echo "<h4>NEXT STEPS:</h4>";
        echo "1. <a href='modules/timbangan/timbangan2.php' target='_blank'>Test di Timbangan 2</a><br>";
        echo "2. Pilih tiket: <strong>$no_tiket</strong><br>";
        echo "3. Material harus muncul: <strong>" . ($row['jenis_material'] ?? 'NULL') . "</strong><br>";

    } else {
        echo "<h3 style='color: red;'>❌ GAGAL DISIMPAN!</h3>";
        echo "Error: " . mysqli_error($conn) . "<br>";
    }

    mysqli_close($conn);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manual Material Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .form-container { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .material-highlight { background: yellow; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>🔧 MANUAL MATERIAL TEST</h1>
        <p style="color: red;">INI UNTUK TESTING MANUAL AGAR MATERIAL PASTI MASUK KE DATABASE!</p>

        <div class="material-highlight">
            <strong>⚠️ PERHATIAN:</strong> Pilih material manual di bawah ini untuk testing!
        </div>

        <form method="post">
            <div class="form-group">
                <label>📦 MATERIAL (PILIH MANUAL):</label>
                <select name="material_selected" required style="background: yellow; font-size: 18px; font-weight: bold;">
                    <option value="">-- PILIH MATERIAL --</option>
                    <option value="tbs">🍎 TBS (Tandan Buah Segar)</option>
                    <option value="cpo">🛢️ CPO (Crude Palm Oil)</option>
                    <option value="kernel">🌰 Kernel</option>
                    <option value="brondolan">🍂 Brondolan</option>
                    <option value="lainnya">📦 Lainnya</option>
                </select>
            </div>

            <div class="form-group">
                <label>🚚 No. Kendaraan:</label>
                <input type="text" name="no_kendaraan" value="TEST MANUAL" required>
            </div>

            <div class="form-group">
                <label>👨‍✈️ Nama Pengemudi:</label>
                <input type="text" name="nama_pengemudi" value="Test Driver" required>
            </div>

            <div class="form-group">
                <label>🏢 Supplier:</label>
                <input type="text" name="nama_suplier" value="Test Supplier" required>
            </div>

            <div class="form-group">
                <label>💰 Harga per Kg:</label>
                <input type="number" name="harga" value="4000" required>
            </div>

            <div class="form-group">
                <label>⚖️ Berat:</label>
                <input type="number" name="berat" value="1000" required>
            </div>

            <button type="submit">🚀 SIMPAN MANUAL & TEST</button>
        </form>

        <hr>
        <h3>📋 INSTRUKSI:</h3>
        <ol>
            <li>Pilih <strong>Material</strong> (WAJIB!)</li>
            <li>Klik tombol "SIMPAN MANUAL & TEST"</li>
            <li>Lihat hasil debugging di atas</li>
            <li>Jika berhasil, buka Timbangan 2 dan test</li>
        </ol>
    </div>
</body>
</html>