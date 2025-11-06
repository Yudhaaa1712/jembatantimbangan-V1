<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kesalahan Server - Jembatan Timbangan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #e74c3c;
            margin-bottom: 20px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .error-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .error-message {
            font-size: 16px;
            color: #7f8c8d;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .error-details {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: left;
        }

        .error-details h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .error-details ul {
            list-style: none;
            color: #7f8c8d;
        }

        .error-details li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .error-details li:before {
            content: "•";
            color: #667eea;
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .timer {
            font-size: 14px;
            color: #95a5a6;
            margin-top: 20px;
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
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <div class="error-code">500</div>
        <h1 class="error-title">Kesalahan Server Internal</h1>
        <p class="error-message">
            Maaf, telah terjadi kesalahan pada server kami. Tim teknis sedang bekerja untuk memperbaiki masalah ini.
            Silakan coba kembali dalam beberapa saat.
        </p>

        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← Kembali ke Halaman Sebelumnya
            </a>
            <a href="../index.php" class="btn btn-primary">
                🏠 Halaman Utama
            </a>
        </div>

        <div class="error-details">
            <h3>🔧 Yang bisa Anda lakukan:</h3>
            <ul>
                <li>Refresh halaman ini</li>
                <li>Periksa koneksi internet Anda</li>
                <li>Coba kembali dalam beberapa menit</li>
                <li>Hubungi administrator jika masalah berlanjut</li>
            </ul>
        </div>

        <div class="timer" id="timer">
            Halaman akan otomatis refresh dalam <span id="countdown">30</span> detik
        </div>
    </div>

    <script>
        // Auto-refresh countdown
        let countdown = 30;
        const timerElement = document.getElementById('countdown');
        const timerInterval = setInterval(() => {
            countdown--;
            timerElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timerInterval);
                window.location.reload();
            }
        }, 1000);

        // Manual refresh button
        function refreshPage() {
            clearInterval(timerInterval);
            window.location.reload();
        }

        // Log error for debugging
        console.error('500 Internal Server Error occurred at:', new Date().toISOString());
    </script>
</body>
</html>