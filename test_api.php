<?php
// Simple test script for the serial API
echo "<h1>Testing Serial API</h1>";

// Test 1: Check API status
echo "<h2>1. API Status Check</h2>";
$status = file_get_contents('http://localhost/jembatantimbangan/api/serial.php');
echo "<pre>$status</pre>";

// Test 2: Check weight endpoint
echo "<h2>2. Weight Endpoint Check</h2>";
$weight = file_get_contents('http://localhost/jembatantimbangan/api/serial.php/weight');
echo "<pre>$weight</pre>";

// Test 3: Test connection endpoint
echo "<h2>3. Connection Test</h2>";
$ch = curl_init('http://localhost/jembatantimbangan/api/serial.php/connect');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['port' => 'COM3']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$conn_result = curl_exec($ch);
curl_close($ch);
echo "<pre>$conn_result</pre>";

echo "<h2>4. Test Complete</h2>";
echo "<p>If you can see this page, the PHP API is working. Check the results above.</p>";
echo "<p><a href='koneksi_indikator_php.html'>Go to Connection Page</a></p>";
?>