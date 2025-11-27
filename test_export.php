<?php
// Test export script
$_GET['tanggal_awal'] = '2025-11-01';
$_GET['tanggal_akhir'] = '2025-11-27';
$_GET['status'] = 'selesai';

include 'modules/transaksi/export_excel.php';