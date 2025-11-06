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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .logo-section h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .logo-section p {
            color: #666;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <h1>Jembatan Timbangan</h1>
            <p>Sistem Timbang Kelapa Sawit</p>
        </div>

        <div id="alert-message" class="alert"></div>

        <form id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Ingat Saya</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100" id="btnLogin">
                <i class="fas fa-sign-in-alt"></i> LOGIN
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">Default: admin / admin123</small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                const btn = $('#btnLogin');
                const originalText = btn.html();
                
                // Disable button and show loading
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
                
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
                            }, 1000);
                        } else {
                            showAlert('danger', response.message);
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Terjadi kesalahan sistem!');
                        btn.prop('disabled', false).html(originalText);
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
                    setTimeout(() => alert.fadeOut(), 3000);
                }
            }
        });
    </script>
</body>
</html>