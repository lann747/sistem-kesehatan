<?php

session_start();

require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php'); 
        exit;
    } elseif ($_SESSION['role'] === 'user') {
        header('Location: user/index.php'); 
        exit;
    }
}

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $post_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_login'], $post_csrf)) {
        $error = "Sesi formulir kadaluarsa atau tidak valid. Muat ulang halaman.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = "Email dan password harus diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } else {
            $stmt = $conn->prepare("SELECT id, nama, email, password, role FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res && $res->num_rows === 1) {
                    $user = $res->fetch_assoc();

                    $dbHash = $user['password'] ?? '';

                    $ok = false;
                    $isModernHash = str_starts_with($dbHash, '$2y$') || str_starts_with($dbHash, '$argon2');

                    if ($isModernHash) {
                        $ok = password_verify($password, $dbHash);
                    } else {
                        $ok = (md5($password) === $dbHash);
                    }

                    if ($ok) {
                        session_regenerate_id(true);
                        $_SESSION['id']   = (int)$user['id'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['role'] = $user['role'];

                        $_SESSION['login_attempts'] = 0;

                        if (!$isModernHash) {
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
                            if ($up) {
                                $up->bind_param('si', $newHash, $user['id']);
                                $up->execute();
                                $up->close();
                            }
                        }
                        
                        if ($user['role'] === 'admin') {
                            header('Location: admin/index.php'); 
                            exit;
                        } else {
                            header('Location: user/index.php'); 
                            exit;
                        }
                    } else {
                        $error = "Email atau password salah.";
                    }
                } else {
                    $error = "Email tidak ditemukan.";
                }
                $stmt->close();
            } else {
                $error = "Terjadi kesalahan koneksi. Coba beberapa saat lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Rafflesia Sehat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
    :root {
        --primary-color: #16a34a;
        --primary-light: #22c55e;
        --primary-dark: #15803d;
        --secondary-color: #10b981;
        --light-bg: #f0fdf4;
        --text-light: #f8fafc;
        --card-light: #ffffff;
        --gradient: linear-gradient(135deg, #16a34a, #22c55e);
        --dark-bg: #0f172a;
        --text-dark: #1e293b;
        --card-dark: #1e293b;
        --sidebar-bg: #1e293b;
        --navbar-bg: #16a34a;
        --shadow-light: 0 10px 25px rgba(0, 0, 0, 0.05);
        --shadow-medium: 0 15px 35px rgba(0, 0, 0, 0.1);
        --shadow-heavy: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--gradient);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
        transition: all 0.3s ease;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }

    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,213.3C672,192,768,128,864,128C960,128,1056,192,1152,192C1248,192,1344,128,1392,96L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') center/cover no-repeat;
        z-index: -1;
    }

    .login-container {
        width: 100%;
        max-width: 450px;
        animation: fadeInUp 0.8s ease;
    }

    .login-card {
        background: var(--card-light);
        border: none;
        border-radius: 20px;
        box-shadow: var(--shadow-heavy);
        overflow: hidden;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .login-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .card-header {
        background: var(--primary-color);
        color: #fff;
        text-align: center;
        padding: 40px 20px;
        border-bottom: none;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        z-index: 0;
    }

    .card-header>* {
        position: relative;
        z-index: 1;
    }

    .login-icon {
        font-size: 3.5rem;
        margin-bottom: 15px;
        display: block;
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
    }

    .card-body {
        padding: 40px 30px;
        color: var(--text-dark);
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .input-group {
        transition: transform 0.3s ease;
    }

    .input-group:focus-within {
        transform: translateY(-2px);
    }

    .input-group-text {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-right: none;
        transition: all 0.3s ease;
    }

    .input-group:focus-within .input-group-text {
        border-color: var(--primary-color);
        background: rgba(22, 163, 74, 0.05);
        color: var(--primary-color);
    }

    .form-control.border-start-0 {
        border-left: none;
    }

    .btn-login {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 14px;
        border-radius: 12px;
        transition: all 0.3s ease;
        width: 100%;
        font-size: 1rem;
        box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
        position: relative;
        overflow: hidden;
    }

    .btn-login:hover {
        background: var(--primary-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
    }

    .btn-login:active {
        transform: translateY(-1px);
    }

    .btn-login::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 5px;
        height: 5px;
        background: rgba(255, 255, 255, 0.5);
        opacity: 0;
        border-radius: 100%;
        transform: scale(1, 1) translate(-50%);
        transform-origin: 50% 50%;
    }

    .btn-login:focus:not(:active)::after {
        animation: ripple 1s ease-out;
    }

    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 14px 16px;
        font-weight: 500;
        box-shadow: var(--shadow-light);
    }

    .alert-danger {
        background: #fee2e2;
        color: #dc2626;
        border-left: 4px solid #dc2626;
    }

    .links-section {
        margin-top: 25px;
        text-align: center;
    }

    .link-text {
        color: var(--text-dark);
        font-size: 0.9rem;
    }

    .link-primary {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.3s ease;
        position: relative;
    }

    .link-primary::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--primary-color);
        transition: width 0.3s ease;
    }

    .link-primary:hover {
        color: var(--primary-dark);
        text-decoration: none;
    }

    .link-primary:hover::after {
        width: 100%;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #6b7280;
        transition: color 0.3s ease;
        text-decoration: none;
    }

    .back-link:hover {
        color: var(--primary-color);
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

    @keyframes ripple {
        0% {
            transform: scale(0, 0);
            opacity: 0.5;
        }

        100% {
            transform: scale(20, 20);
            opacity: 0;
        }
    }

    @media (max-width: 576px) {
        .card-body {
            padding: 30px 20px;
        }

        .login-container {
            max-width: 100%;
        }

        .card-header {
            padding: 30px 20px;
        }

        .login-icon {
            font-size: 3rem;
        }

        body {
            padding: 15px;
        }
    }

    @media (max-width: 400px) {
        .card-body {
            padding: 25px 15px;
        }

        .card-header {
            padding: 25px 15px;
        }

        .form-control {
            padding: 12px 15px;
        }
    }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <i class="fas fa-lock login-icon" aria-hidden="true"></i>
                <h2 class="fw-bold mb-2">Login Sistem</h2>
                <p class="mb-0 opacity-75">Masuk untuk mengelola data kesehatan</p>
            </div>

            <div class="card-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf"
                        value="<?= htmlspecialchars($_SESSION['csrf_login'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-4">
                        <label class="form-label text-dark" for="email">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted" aria-hidden="true"></i>
                            </span>
                            <input type="email" id="email" name="email" class="form-control border-start-0"
                                placeholder="Masukkan email Anda" required inputmode="email"
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-dark" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-key text-muted" aria-hidden="true"></i>
                            </span>
                            <input type="password" id="password" name="password" class="form-control border-start-0"
                                placeholder="Masukkan password Anda" required minlength="6" />
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login mb-4">
                        <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>
                        Login
                    </button>
                </form>

                <div class="links-section">
                    <p class="link-text mb-3">
                        Belum punya akun?
                        <a href="register.php" class="link-primary ms-1" rel="nofollow">Daftar Sekarang</a>
                    </p>

                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>
                        Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

    <script>
    (function() {
        // Efek interaktif pada input fields
        document.querySelectorAll('.form-control').forEach(el => {
            el.addEventListener('focus', function() {
                this.closest('.input-group').style.transform = 'translateY(-2px)';
            });

            el.addEventListener('blur', function() {
                this.closest('.input-group').style.transform = 'translateY(0)';
            });
        });

        // Efek ripple pada tombol login
        document.querySelector('.btn-login').addEventListener('click', function(e) {
            if (this.form.checkValidity()) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.5)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.width = '5px';
                ripple.style.height = '5px';

                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            }
        });

        // Validasi form real-time
        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        function validateForm() {
            let isValid = true;

            // Validasi email
            if (!emailInput.value || !emailInput.validity.valid) {
                isValid = false;
            }

            // Validasi password
            if (!passwordInput.value || passwordInput.value.length < 6) {
                isValid = false;
            }

            return isValid;
        }

        // Update status tombol berdasarkan validasi
        function updateButtonState() {
            const button = document.querySelector('.btn-login');
            if (validateForm()) {
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
            } else {
                button.style.opacity = '0.7';
                button.style.cursor = 'not-allowed';
            }
        }

        emailInput.addEventListener('input', updateButtonState);
        passwordInput.addEventListener('input', updateButtonState);

        // Inisialisasi status tombol
        updateButtonState();
    })();
    </script>
</body>

</html>