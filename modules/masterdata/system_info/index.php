<?php
// modules/masterdata/system_info/index.php
require_once '../../../config/database.php';
check_role(['admin']);

$page_title = "Informasi Sistem - Master Data";

include '../../../includes/header.php';
?>

<style>
    .page-container {
        max-width: 1200px;
        margin: 20px auto;
        background: #1a1a1a;
        border: 2px solid #dc2626;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 0 50px rgba(220, 38, 38, 0.3);
        text-align: center;
    }

    .coming-soon {
        padding: 60px 20px;
        color: rgba(255, 255, 255, 0.7);
    }

    .coming-soon i {
        font-size: 64px;
        color: #dc2626;
        margin-bottom: 20px;
    }

    .coming-soon h2 {
        color: #dc2626;
        font-size: 32px;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .coming-soon p {
        font-size: 16px;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .btn-back {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-back:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        color: #fff;
        text-decoration: none;
    }
</style>

<div class="page-container">
    <div class="coming-soon">
        <i class="fas fa-info-circle"></i>
        <h2>Informasi Sistem</h2>
        <p>Modul informasi sistem sedang dalam pengembangan.<br>
        Fitur ini akan segera tersedia untuk menampilkan status dan versi sistem.</p>
        <a href="../index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Master Data
        </a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</body>
</html>