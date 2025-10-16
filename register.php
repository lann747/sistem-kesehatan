<?php
include 'config/db.php'; // pastikan file koneksi kamu bernama config.php

$err = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($nama) || empty($email) || empty($password) || empty($password2)) {
        $err = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $err = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $err = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek apakah email sudah terdaftar
        $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($cek, "s", $email);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $err = 'Email sudah terdaftar, silakan login.';
        } else {
            // Hash password pakai MD5 (biar sesuai login.php)
            $hash = md5($password);

            // Simpan data user baru
            $stmt = mysqli_prepare($conn, "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'user')");
            mysqli_stmt_bind_param($stmt, "sss", $nama, $email, $hash);

            if (mysqli_stmt_execute($stmt)) {
                $success = 'Pendaftaran berhasil! Silakan login.';
                $nama = $email = '';
            } else {
                $err = 'Gagal mendaftar. Coba lagi.';
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun - Sistem Informasi Kesehatan</title>
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

    .register-container {
        width: 100%;
        max-width: 500px;
        animation: fadeInUp 0.8s ease;
    }

    .register-card {
        background: var(--card-light);
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    body.dark-mode .register-card {
        background: var(--card-dark);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    .register-card:hover {
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

    .register-icon {
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

    .input-group-text {
        background: var(--card-light);
        border: 2px solid #e2e8f0;
        border-right: none;
        border-radius: 12px 0 0 12px;
    }

    body.dark-mode .input-group-text {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
    }

    .input-group .form-control {
        border-left: none;
        border-radius: 0 12px 12px 0;
    }

    .btn-register {
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

    .btn-register:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-register:active {
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

    .alert-success {
        background: #d1fae5;
        color: #065f46;
    }

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca;
    }

    body.dark-mode .alert-success {
        background: #064e3b;
        color: #a7f3d0;
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

    .password-strength {
        margin-top: 5px;
        font-size: 0.8rem;
    }

    .strength-weak {
        color: #dc2626;
    }

    .strength-medium {
        color: #d97706;
    }

    .strength-strong {
        color: #059669;
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

        .register-container {
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

    <div class="register-container">
        <div class="register-card">
            <div class="card-header">
                <i class="fas fa-user-plus register-icon"></i>
                <h2 class="fw-bold mb-2">Daftar Akun</h2>
                <p class="mb-0 opacity-75">Bergabung dengan sistem kesehatan kami</p>
            </div>

            <div class="card-body">
                <?php if ($err): ?>
                <div class="alert alert-danger alert-custom mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($err, ENT_QUOTES) ?>
                </div>
                <?php elseif ($success): ?>
                <div class="alert alert-success alert-custom mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success, ENT_QUOTES) ?>
                </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-4">
                        <label class="form-label">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" name="nama" class="form-control" required
                                placeholder="Masukkan nama lengkap Anda"
                                value="<?= htmlspecialchars($nama ?? '', ENT_QUOTES) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" name="email" class="form-control" required
                                placeholder="email@contoh.com"
                                value="<?= htmlspecialchars($email ?? '', ENT_QUOTES) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="password" class="form-control" required
                                placeholder="Minimal 6 karakter" id="password">
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Konfirmasi Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="password2" class="form-control" required
                                placeholder="Ulangi password Anda" id="confirmPassword">
                        </div>
                        <div class="password-match" id="passwordMatch"></div>
                    </div>

                    <button type="submit" name="register" class="btn btn-register mb-4">
                        <i class="fas fa-user-plus me-2"></i>
                        Daftar Akun
                    </button>
                </form>

                <div class="links-section">
                    <p class="link-text mb-3">
                        Sudah punya akun?
                        <a href="login.php" class="link-primary">Login Sekarang</a>
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
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordMatch = document.getElementById('passwordMatch');

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

    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = '';
        let strengthClass = '';

        if (password.length === 0) {
            strength = '';
        } else if (password.length < 6) {
            strength = 'Kekuatan: Lemah';
            strengthClass = 'strength-weak';
        } else if (password.length < 8) {
            strength = 'Kekuatan: Sedang';
            strengthClass = 'strength-medium';
        } else {
            strength = 'Kekuatan: Kuat';
            strengthClass = 'strength-strong';
        }

        passwordStrength.innerHTML = strength;
        passwordStrength.className = 'password-strength ' + strengthClass;
    });

    // Password match checker
    confirmPasswordInput.addEventListener('input', function() {
        const password = passwordInput.value;
        const confirmPassword = this.value;

        if (confirmPassword.length === 0) {
            passwordMatch.innerHTML = '';
        } else if (password === confirmPassword) {
            passwordMatch.innerHTML = '<span style="color: #059669;">✓ Password cocok</span>';
        } else {
            passwordMatch.innerHTML = '<span style="color: #dc2626;">✗ Password tidak cocok</span>';
        }
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