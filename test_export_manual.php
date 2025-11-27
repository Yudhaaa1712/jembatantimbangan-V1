<?php
// Test manual export Excel
include 'modules/transaksi/export_excel.php';

// Simulate GET parameters
$_GET['tanggal_awal'] = '2025-11-01';
$_GET['tanggal_akhir'] = '2025-11-27';
$_GET['status'] = 'selesai';

echo "Test export Excel - Manual included\n";
echo "Testing with parameters:\n";
echo "- tanggal_awal: " . $_GET['tanggal_awal'] . "\n";
echo "- tanggal_akhir: " . $_GET['tanggal_akhir'] . "\n";
echo "- status: " . $_GET['status'] . "\n";
?>