<?php
// modules/auth/login.php
require_once '../../config/database.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'modules/timbangan/timbangan1.php');
    exit;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Input validation
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        json_response(false, 'Username dan password harus diisi');
    }

    if (empty(trim($_POST['username'])) || empty(trim($_POST['password']))) {
        json_response(false, 'Username dan password tidak boleh kosong');
    }

    $username = clean_input($_POST['username']);
    $password_input = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify password using password_verify() for modern hashing
        $stored_password = $user['password'];

        if (password_verify($password_input, $stored_password)) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();

            // Log successful login
            error_log("User login successful: $username from " . $_SERVER['REMOTE_ADDR']);

            json_response(true, 'Login berhasil!', ['redirect' => BASE_URL . 'modules/timbangan/timbangan1.php']);
        } else {
            // Log failed login attempt
            error_log("Failed login attempt for username: $username from " . $_SERVER['REMOTE_ADDR']);
            json_response(false, 'Username atau password salah!');
        }
    } else {
        // Log failed login attempt
        error_log("Failed login attempt for username: $username from " . $_SERVER['REMOTE_ADDR']);
        json_response(false, 'Username atau password salah!');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jembatan Timbangan Sawit</title>

    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            overflow: hidden;
        }

        .login-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            overflow: hidden;
            background: #2a4d3a; /* Dark green as fallback */
        }

        /* Left side - Large arroyan image section */
        .image-section {
            flex: 1;
            position: relative;
            background-image: url('http://localhost/jembatantimbangan/assets/img/arroyan.png');
            background-size: 70% auto;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #1a1a2e;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg,
                rgba(26, 26, 46, 0.8) 0%,
                rgba(15, 15, 30, 0.6) 100%);
            z-index: 1;
        }

        .image-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            padding: 3rem;
            animation: float 8s ease-in-out infinite;
        }

        .image-content h2 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 12px rgba(0,0,0,0.5);
            letter-spacing: 2px;
            line-height: 1.2;
        }

        .image-content p {
            font-size: 1.4rem;
            opacity: 0.95;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
            font-weight: 500;
            line-height: 1.6;
        }

        /* Right side - Form login section */
        .form-section {
            flex: 1;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 50%, #16213e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements for form side */
        .form-section::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg,
                transparent 30%,
                rgba(102, 126, 234, 0.05) 50%,
                transparent 70%);
            animation: shimmer-bg 15s ease-in-out infinite;
            z-index: 1;
        }

        
        /* Transparent iOS-style login card */
        .login-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px) saturate(120%);
            -webkit-backdrop-filter: blur(10px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 20;
            box-shadow:
                0 8px 32px 0 rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            animation: slide-up 0.6s ease-out;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
            padding: 18px;
            transform: scale(1.1);
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-section h1 {
            color: white;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            letter-spacing: 1px;
        }

        .logo-section p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0;
            text-shadow: 0 1px 4px rgba(0,0,0,0.4);
        }

        /* Form styling */
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            color: white;
            font-size: 1rem;
            transition: all 0.2s ease;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
            color: white;
            outline: none;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .input-group .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
        }

        .input-group:focus-within .input-group-text {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* Checkbox styling */
        .form-check {
            margin-bottom: 1.5rem;
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            width: 20px;
            height: 20px;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: #007AFF;
            border-color: #007AFF;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='white' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
        }

        .form-check-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        /* Button styling */
        .btn-login {
            background: #007AFF;
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            color: white;
            transition: all 0.2s ease;
            width: 100%;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-login:hover {
            background: #0056CC;
            transform: translateY(-1px);
        }

        .btn-login:active {
            background: #004999;
            transform: translateY(0);
        }

        .btn-login:disabled {
            background: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            transform: none;
        }

        /* Alert styling */
        .alert {
            border-radius: 10px;
            display: none;
            margin-bottom: 1.5rem;
            border: none;
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: white;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            color: white;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .default-info {
            text-align: center;
            margin-top: 1.5rem;
        }

        .default-info small {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
        }

        /* Animations */
        @keyframes slide-up {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        @keyframes shimmer-bg {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .image-section {
                display: none;
            }

            .form-section {
                flex: 1;
            }

            .login-card {
                max-width: 450px;
                padding: 2.5rem;
            }

            .image-content h2 {
                font-size: 2.5rem;
            }

            .image-content p {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .form-section {
                padding: 1rem;
            }

            .login-card {
                padding: 2rem;
                border-radius: 18px;
            }

            .logo-section h1 {
                font-size: 1.6rem;
            }

            .logo-section p {
                font-size: 0.9rem;
            }

            .logo-icon {
                width: 80px;
                height: 80px;
                padding: 12px;
                margin-bottom: 1.5rem;
            }
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left side - Large image section -->
        <div class="image-section">
            <div class="image-content">
            </div>
        </div>

        <!-- Right side - Login form section -->
        <div class="form-section">
            <div class="login-card">
                <div class="logo-section">
                    <div class="logo-icon">
                        <img src="<?php echo BASE_URL; ?>assets/img/arroyan.png" alt="Arroyan Logo">
                    </div>
                    <h1>Selamat Datang</h1>
                    <p>Program Jembatan Timbang</p>
                </div>

                <div id="alert-message" class="alert"></div>

                <form id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                            </span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                            </span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Ingat Saya</label>
                    </div>

                    <button type="submit" class="btn btn-login" id="btnLogin">
                        <span id="btnText">Login</span>
                        <span id="btnSpinner" class="spinner" style="display: none;"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {
            // Test if background image loads
            var img = new Image();
            img.src = 'http://localhost/jembatantimbangan/assets/img/arroyan.png';
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();

                const btnText = $('#btnText');
                const btnSpinner = $('#btnSpinner');
                const btn = $('#btnLogin');
                const originalText = btnText.text();

                // Disable button and show loading
                btn.prop('disabled', true);
                btnText.text('Loading...');
                btnSpinner.show();

                $.ajax({
                    url: 'login.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        } else {
                            showAlert('danger', response.message);
                            btn.prop('disabled', false);
                            btnText.text(originalText);
                            btnSpinner.hide();
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Terjadi kesalahan sistem!');
                        btn.prop('disabled', false);
                        btnText.text(originalText);
                        btnSpinner.hide();
                    }
                });
            });

            function showAlert(type, message) {
                const alert = $('#alert-message');
                alert.removeClass('alert-success alert-danger')
                     .addClass('alert-' + type)
                     .html(message)
                     .fadeIn();

                if (type === 'success') {
                    setTimeout(() => {
                        alert.fadeOut();
                    }, 1500);
                }
            }

            // Add input animation effects
            $('.form-control').on('focus', function() {
                $(this).parent().find('.input-group-text').css({
                    'background-color': 'rgba(102, 126, 234, 0.1)',
                    'border-color': '#667eea'
                });
            });

            $('.form-control').on('blur', function() {
                $(this).parent().find('.input-group-text').css({
                    'background-color': 'rgba(255, 255, 255, 0.08)',
                    'border-color': 'rgba(255, 255, 255, 0.1)'
                });
            });

            // Auto-hide alert on click
            $(document).on('click', '.alert', function() {
                $(this).fadeOut();
            });
        });
    </script>
</body>
</html>