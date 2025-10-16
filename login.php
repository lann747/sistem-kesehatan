<?php
session_start();
include 'config/db.php';

// Jika sudah login, arahkan langsung ke dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
        exit;
    } elseif ($_SESSION['role'] === 'user') {
        header('Location: user/index.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($email === '' || $password === '') {
        $error = "Email dan password harus diisi.";
    } else {
        // Cek user di database
        $query = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Cek password (MD5 sesuai struktur DB)
            if ($user['password'] === md5($password)) {
                $_SESSION['id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];

                // Arahkan sesuai role
                if ($user['role'] === 'admin') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: user/index.php');
                }
                exit;
            } else {
                $error = "Password salah.";
            }
        } else {
            $error = "Email tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Kesehatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #3b82f6;
        --primary-light: #60a5fa;
        --primary-dark: #1d4ed8;
        --secondary-color: #10b981;
        --light-bg: #f0f9ff;
        --dark-bg: #0f172a;
        --text-light: #f8fafc;
        --text-dark: #1e293b;
        --card-light: #ffffff;
        --card-dark: #1e293b;
        --gradient: linear-gradient(135deg, #3b82f6, #60a5fa);
        --gradient-dark: linear-gradient(135deg, #1e293b, #334155);
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
    }

    body.dark-mode {
        background: var(--gradient-dark);
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
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    body.dark-mode .login-card {
        background: var(--card-dark);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    .login-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background: var(--primary-color);
        color: white;
        text-align: center;
        padding: 30px 20px;
        border-bottom: none;
    }

    .card-body {
        padding: 40px 30px;
        color: var(--text-dark);
    }

    body.dark-mode .card-body {
        color: var(--text-light);
    }

    .login-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
    }

    body.dark-mode .form-label {
        color: var(--text-light);
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    body.dark-mode .form-control {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-login {
        background: var(--primary-color);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px;
        border-radius: 12px;
        transition: all 0.3s ease;
        width: 100%;
        font-size: 1rem;
    }

    .btn-login:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 12px 15px;
        font-weight: 500;
    }

    .alert-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca;
    }

    .links-section {
        margin-top: 25px;
        text-align: center;
    }

    .link-text {
        color: var(--text-dark);
        font-size: 0.9rem;
    }

    body.dark-mode .link-text {
        color: var(--text-light);
    }

    .link-primary {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .link-primary:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #6b7280;
        transition: color 0.3s ease;
    }

    body.dark-mode .back-link {
        color: #9ca3af;
    }

    .back-link:hover {
        color: var(--primary-color);
    }

    .theme-toggle {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(20deg);
    }

    /* Animations */
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

    /* Responsive */
    @media (max-width: 576px) {
        .card-body {
            padding: 30px 20px;
        }

        .login-container {
            max-width: 100%;
        }
    }
    </style>
</head>

<body>
    <!-- Theme Toggle -->
    <button id="themeToggle" class="theme-toggle" title="Ganti Tema">
        <i class="fas fa-moon"></i>
    </button>

    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <i class="fas fa-lock login-icon"></i>
                <h2 class="fw-bold mb-2">Login Sistem</h2>
                <p class="mb-0 opacity-75">Masuk untuk mengelola data kesehatan</p>
            </div>

            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-custom mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" name="email" class="form-control border-start-0"
                                placeholder="Masukkan email Anda" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-key text-muted"></i>
                            </span>
                            <input type="password" name="password" class="form-control border-start-0"
                                placeholder="Masukkan password Anda" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login mb-4">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Login
                    </button>
                </form>

                <div class="links-section">
                    <p class="link-text mb-3">
                        Belum punya akun?
                        <a href="register.php" class="link-primary">Daftar Sekarang</a>
                    </p>

                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left me-1"></i>
                        Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const toggleBtn = document.getElementById('themeToggle');
    const body = document.body;

    // Cek mode dari localStorage
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
    }

    toggleBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        toggleBtn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });

    // Animasi untuk form elements
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.parentElement.style.transform = 'scale(1)';
            });
        });
    });
    </script>
</body>

</html>