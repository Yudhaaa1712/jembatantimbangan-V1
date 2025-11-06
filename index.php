<?php
// index.php - Redirect langsung ke halaman timbangan
require_once 'config/database.php';

// Cek apakah user sudah login
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'modules/auth/login.php');
    exit;
}

// Redirect langsung ke halaman timbangan 1
header('Location: ' . BASE_URL . 'modules/timbangan/timbangan1.php');
exit;
?>