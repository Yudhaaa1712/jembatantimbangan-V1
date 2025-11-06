<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

include 'config/database.php';
$koneksi = $conn;

$page_title = "Dashboard - Jembatan Timbangan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .user-info {
            position: absolute;
            right: 30px;
            top: 30px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .main-content {
            padding: 40px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: #f8f9fc;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
        }

        .card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 10px;
        }

        .card-description {
            color: #858796;
            font-size: 0.95rem;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #4e73df;
            color: white;
        }

        .btn-success {
            background: #1cc88a;
            color: white;
        }

        .btn-warning {
            background: #f6c23e;
            color: white;
        }

        .btn-danger {
            background: #e74a3b;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            color: white;
        }

        .logout {
            background: rgba(231, 74, 59, 0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            margin-left: 15px;
        }

        .logout:hover {
            background: rgba(231, 74, 59, 0.3);
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .user-info {
                position: static;
                margin-top: 15px;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏭 Dashboard Jembatan Timbangan</h1>
            <p>Sistem Manajemen Timbangan Digital</p>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="modules/auth/logout.php" class="logout">🚪 Keluar</a>
            </div>
        </div>

        <div class="main-content">
            <div class="dashboard-grid">
                <a href="modules/timbangan/timbangan_kompak.php" class="dashboard-card">
                    <div class="card-icon">🎯</div>
                    <div class="card-title">Timbangan Kompak</div>
                    <div class="card-description">All-in-One - 2 timbangan dalam 1 layar tanpa scroll</div>
                </a>

                <a href="modules/timbangan/timbangan1.php" class="dashboard-card">
                    <div class="card-icon">⚖️</div>
                    <div class="card-title">Timbangan 1</div>
                    <div class="card-description">Timbangan Masuk - Input data awal kendaraan</div>
                </a>

                <a href="modules/timbangan/timbangan2.php" class="dashboard-card">
                    <div class="card-icon">🚛</div>
                    <div class="card-title">Timbangan 2</div>
                    <div class="card-description">Timbangan Keluar - Proses penimbangan akhir</div>
                </a>

                <a href="multi_display_timbangan.html" class="dashboard-card">
                    <div class="card-icon">🖥️</div>
                    <div class="card-title">Multi Display</div>
                    <div class="card-description">Monitor 3 timbangan secara real-time dalam satu layar</div>
                </a>

                <a href="modules/timbangan/view_data.php" class="dashboard-card">
                    <div class="card-icon">📊</div>
                    <div class="card-title">Lihat Data</div>
                    <div class="card-description">Lihat semua data timbangan</div>
                </a>

                <a href="modules/kendaraan/" class="dashboard-card">
                    <div class="card-icon">🚗</div>
                    <div class="card-title">Manajemen Kendaraan</div>
                    <div class="card-description">Kelola data kendaraan</div>
                </a>

                <a href="modules/supplier/" class="dashboard-card">
                    <div class="card-icon">🏢</div>
                    <div class="card-title">Manajemen Supplier</div>
                    <div class="card-description">Kelola data supplier</div>
                </a>

                <a href="modules/material/" class="dashboard-card">
                    <div class="card-icon">📦</div>
                    <div class="card-title">Manajemen Material</div>
                    <div class="card-description">Kelola data material</div>
                </a>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <p style="color: #858796;">
                    Sistem Jembatan Timbangan Digital v1.0<br>
                    © 2024 - All Rights Reserved
                </p>
            </div>
        </div>
    </div>
</body>
</html>