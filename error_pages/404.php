<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan - Jembatan Timbangan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-icon {
            font-size: 80px;
            color: #e17055;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }

        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #2d3436;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .error-title {
            font-size: 28px;
            color: #2d3436;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .error-message {
            font-size: 16px;
            color: #636e72;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(116, 185, 255, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(116, 185, 255, 0.5);
        }

        .btn-secondary {
            background: white;
            color: #74b9ff;
            border: 2px solid #74b9ff;
        }

        .btn-secondary:hover {
            background: #74b9ff;
            color: white;
            transform: translateY(-2px);
        }

        .search-box {
            margin: 30px 0;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #74b9ff;
            box-shadow: 0 0 15px rgba(116, 185, 255, 0.2);
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #74b9ff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #0984e3;
        }

        .quick-links {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .quick-link {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #2d3436;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .quick-link:hover {
            background: white;
            border-color: #74b9ff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .quick-link-icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .quick-link-text {
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 40px 20px;
            }

            .error-code {
                font-size: 56px;
            }

            .error-title {
                font-size: 24px;
            }

            .error-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
            }

            .quick-links {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔍</div>
        <div class="error-code">404</div>
        <h1 class="error-title">Halaman Tidak Ditemukan</h1>
        <p class="error-message">
            Halaman yang Anda cari tidak ditemukan atau telah dipindahkan.
            Mungkin ada kesalahan dalam URL atau halaman telah dihapus.
        </p>

        <div class="search-box">
            <form method="GET" action="../index.php">
                <input type="text" name="search" class="search-input" placeholder="Cari di situs ini...">
                <button type="submit" class="search-btn">
                    🔍
                </button>
            </form>
        </div>

        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← Kembali
            </a>
            <a href="../index.php" class="btn btn-primary">
                🏠 Beranda
            </a>
        </div>

        <div class="quick-links">
            <a href="../modules/timbangan/" class="quick-link">
                <span class="quick-link-icon">⚖️</span>
                <span class="quick-link-text">Timbangan</span>
            </a>
            <a href="../modules/laporan/" class="quick-link">
                <span class="quick-link-icon">📊</span>
                <span class="quick-link-text">Laporan</span>
            </a>
            <a href="../modules/masterdata/" class="quick-link">
                <span class="quick-link-icon">📋</span>
                <span class="quick-link-text">Master Data</span>
            </a>
            <a href="../modules/transaksi/" class="quick-link">
                <span class="quick-link-icon">💰</span>
                <span class="quick-link-text">Transaksi</span>
            </a>
        </div>
    </div>

    <script>
        // Log 404 error for analytics
        console.warn('404 Not Found:', {
            url: window.location.href,
            referrer: document.referrer,
            timestamp: new Date().toISOString()
        });

        // Focus on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        });

        // Suggest possible corrections
        function suggestCorrections() {
            const path = window.location.pathname.toLowerCase();
            const commonPages = [
                'index.php',
                'login.php',
                'dashboard.php',
                'timbangan.php',
                'laporan.php',
                'supplier.php',
                'kendaraan.php'
            ];

            for (const page of commonPages) {
                if (path.includes(page.substring(0, 4))) {
                    console.log(`Mungkin yang Anda maksud: ${page}`);
                    break;
                }
            }
        }

        suggestCorrections();
    </script>
</body>
</html>