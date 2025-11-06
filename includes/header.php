<?php
// includes/header.php
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'modules/auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Jembatan Timbangan Sawit'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Critical CSS - Optimized -->
    <style>
        /* Critical CSS for above-the-fold content */
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Professional Header */
        .top-navbar {
            background: linear-gradient(180deg, rgba(20, 20, 20, 0.98) 0%, rgba(15, 15, 15, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 3px solid #dc2626;
            padding: 1rem 0;
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #2a2a2a 25%, #3a3a3a 50%, #2a2a2a 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Critical button styles */
        .btn {
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            color: white;
        }

        /* Form styles */
        .form-control {
            border-radius: 8px;
            border-width: 2px;
            transition: all 0.3s ease;
            background-color: #2a2a2a;
            color: #fff;
            border-color: #495057;
        }

        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
    </style>

    <!-- Non-critical CSS - Load asynchronously -->
    <link rel="preload" href="<?php echo BASE_URL; ?>assets/css/non-critical.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/non-critical.css"></noscript>

    <!-- Performance Optimizer -->
    <script src="<?php echo BASE_URL; ?>assets/js/performance-optimizer.js" defer></script>

    <!-- Original Custom CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            overflow-x: hidden;
        }

        /* Professional Header */
        .top-navbar {
            background: linear-gradient(180deg, rgba(20, 20, 20, 0.98) 0%, rgba(15, 15, 15, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 3px solid #dc2626;
            box-shadow:
                0 4px 20px rgba(220, 38, 38, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            color: #dc2626 !important;
            font-size: 22px;
            font-weight: 800;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .navbar-brand::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #dc2626, transparent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover::before {
            transform: scaleX(1);
        }

        .navbar-brand:hover {
            color: #ef4444 !important;
            transform: translateY(-1px);
            text-shadow: 0 2px 10px rgba(220, 38, 38, 0.3);
        }

        .navbar-brand i {
            font-size: 24px;
            filter: drop-shadow(0 2px 4px rgba(220, 38, 38, 0.3));
        }

        /* Professional Navigation */
        .nav-group {
            display: flex;
            gap: 6px;
            background: rgba(255, 255, 255, 0.02);
            padding: 4px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
        }

        .nav-group .nav-link {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            margin: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 8px 14px;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }

        .nav-group .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-group .nav-link:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .nav-group .nav-link:hover::before {
            opacity: 0.1;
        }

        .nav-group .nav-link.active {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow:
                0 4px 15px rgba(220, 38, 38, 0.3),
                0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-group .nav-link i {
            margin-right: 6px;
            font-size: 14px;
        }

        /* Professional User Section */
        .user-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-badge {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.05));
            border: 1.5px solid rgba(220, 38, 38, 0.3);
            color: #ef4444;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .user-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .user-badge:hover::before {
            left: 100%;
        }

        .user-badge:hover {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.15), rgba(185, 28, 28, 0.1));
            border-color: rgba(220, 38, 38, 0.5);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
        }

        .user-badge i {
            font-size: 16px;
            opacity: 0.8;
        }

        .btn-logout {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            color: #fff;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 10px 18px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn-logout::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }

        .btn-logout:hover::before {
            left: 100%;
        }

        .btn-logout:active {
            transform: translateY(0);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .nav-group .nav-link {
                padding: 6px 12px;
                font-size: 11px;
                letter-spacing: 0.5px;
            }
        }

        @media (max-width: 992px) {
            .navbar-brand {
                font-size: 18px;
                letter-spacing: 1px;
            }

            .nav-group {
                gap: 4px;
            }

            .nav-group .nav-link {
                padding: 6px 10px;
                font-size: 10px;
                letter-spacing: 0.4px;
            }

            .user-badge {
                padding: 8px 16px;
                font-size: 12px;
            }

            .btn-logout {
                padding: 8px 14px;
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 8px 0;
            }

            .navbar-brand {
                font-size: 16px;
                gap: 8px;
            }

            .navbar-brand i {
                font-size: 20px;
            }

            .container-fluid {
                padding: 0 12px;
            }

            .nav-group {
                order: 3;
                width: 100%;
                margin-top: 12px;
                justify-content: center;
                gap: 6px;
            }

            .user-section {
                order: 2;
                gap: 12px;
            }

            .user-badge {
                padding: 6px 12px;
                font-size: 11px;
                letter-spacing: 0.4px;
            }

            .btn-logout {
                padding: 6px 12px;
                font-size: 11px;
            }

            .nav-group .nav-link {
                padding: 6px 12px;
                font-size: 11px;
            }

            .nav-group .nav-link i {
                margin-right: 4px;
                font-size: 12px;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand span {
                display: none;
            }

            .user-badge span {
                display: none;
            }

            .nav-group .nav-link span {
                display: none;
            }

            .btn-logout span {
                display: none;
            }
        }

        /* Smooth scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0a0a0a;
        }

        ::-webkit-scrollbar-thumb {
            background: #dc2626;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-dark top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <a class="navbar-brand" href="<?php echo BASE_URL; ?>modules/timbangan/timbangan1.php">
                    <i class="fas fa-weight"></i> TIMBANGAN 1
                </a>

                <div class="d-flex align-items-center gap-3">
                    <!-- Quick Navigation -->
                    <div class="nav-group">
                        <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'timbangan1') !== false) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL; ?>modules/timbangan/timbangan1.php">
                            <i class="fas fa-tachometer-alt"></i> <span>Timbangan 1</span>
                        </a>
                        <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'timbangan2') !== false) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL; ?>modules/timbangan/timbangan2.php">
                            <i class="fas fa-weight"></i> <span>Timbangan 2</span>
                        </a>
                        <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'transaksi') !== false) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL; ?>modules/transaksi/index.php">
                            <i class="fas fa-exchange-alt"></i> <span>Transaksi</span>
                        </a>
                        <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'masterdata') !== false) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL; ?>modules/masterdata/index.php">
                            <i class="fas fa-database"></i> <span>Master Data</span>
                        </a>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                        <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'users') !== false) ? 'active' : ''; ?>"
                           href="<?php echo BASE_URL; ?>modules/users/index.php">
                            <i class="fas fa-users"></i> <span>Manajemen Pengguna</span>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- User Section -->
                    <div class="user-section">
                        <div class="user-badge">
                            <i class="fas fa-user-circle"></i> <span><?php echo $_SESSION['nama_lengkap']; ?></span>
                        </div>
                        <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>