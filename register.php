<?php
// ---- Security headers & session (untuk CSRF) ----
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/config/db.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Jika sudah login, tidak perlu daftar lagi
if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php'); exit;
    } else {
        header('Location: user/index.php'); exit;
    }
}

// CSRF token
if (empty($_SESSION['csrf_register'])) {
    $_SESSION['csrf_register'] = bin2hex(random_bytes(32));
}

$err = '';
$success = '';
$nama = $email = '';

// (Opsional) rate limit sederhana: maksimal 5 submit / 10 menit
if (!isset($_SESSION['reg_attempts'])) {
    $_SESSION['reg_attempts'] = 0;
    $_SESSION['reg_last'] = 0;
}
$MAX_ATT = 5;
$WINDOW  = 600; // detik
$now = time();
if ($_SESSION['reg_attempts'] >= $MAX_ATT && ($now - $_SESSION['reg_last']) < $WINDOW) {
    $remaining = $WINDOW - ($now - $_SESSION['reg_last']);
    $err = "Terlalu sering mencoba. Coba lagi dalam " . ceil($remaining/60) . " menit.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($err) && isset($_POST['register'])) {
    // Verifikasi CSRF
    $post_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_register'], $post_csrf)) {
        $err = 'Sesi formulir kadaluarsa atau tidak valid. Muat ulang halaman.';
    } else {
        // Ambil input
        $nama     = trim((string)($_POST['nama'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $password2= (string)($_POST['password2'] ?? '');

        // Validasi
        if ($nama === '' || $email === '' || $password === '' || $password2 === '') {
            $err = 'Semua kolom wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $err = 'Password minimal 6 karakter.';
        } elseif ($password !== $password2) {
            $err = 'Konfirmasi password tidak cocok.';
        } else {
            // Cek email sudah terdaftar
            if ($stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1')) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $err = 'Email sudah terdaftar, silakan login.';
                } else {
                    // Hash modern (bcrypt). Login.php kamu sudah kompatibel (migrasi otomatis dari MD5),
                    // jadi menyimpan hash modern langsung lebih bagus untuk security.
                    $hash = password_hash($password, PASSWORD_BCRYPT);

                    if ($ins = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'user')")) {
                        $ins->bind_param('sss', $nama, $email, $hash);
                        if ($ins->execute()) {
                            $success = 'Pendaftaran berhasil! Silakan login.';
                            // Kosongkan field
                            $nama = $email = '';
                            // Reset rate limit
                            $_SESSION['reg_attempts'] = 0;
                        } else {
                            // Jika tabel punya UNIQUE KEY(email), error duplicate juga bisa masuk sini
                            if ($conn->errno === 1062) {
                                $err = 'Email sudah terdaftar, silakan login.';
                            } else {
                                $err = 'Gagal mendaftar. Coba lagi beberapa saat.';
                            }
                        }
                        $ins->close();
                    } else {
                        $err = 'Terjadi kesalahan koneksi. Coba lagi.';
                    }
                }

                $stmt->close();
            } else {
                $err = 'Terjadi kesalahan koneksi. Coba lagi.';
            }
        }
    }

    // Update rate limit counter
    $_SESSION['reg_attempts']++;
    $_SESSION['reg_last'] = time();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Daftar Akun - Sistem Informasi Kesehatan</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

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
        --gradient-dark: linear-gradient(135deg, #1e293b, #334155)
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--gradient);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
        transition: all .3s ease;
        padding: 20px
    }

    body.dark-mode {
        background: var(--gradient-dark)
    }

    .register-container {
        width: 100%;
        max-width: 500px;
        animation: fadeInUp .8s ease
    }

    .register-card {
        background: var(--card-light);
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, .1);
        overflow: hidden;
        transition: all .3s ease
    }

    body.dark-mode .register-card {
        background: var(--card-dark);
        box-shadow: 0 15px 35px rgba(0, 0, 0, .3)
    }

    .register-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, .15)
    }

    .card-header {
        background: var(--primary-color);
        color: #fff;
        text-align: center;
        padding: 30px 20px;
        border-bottom: none
    }

    .card-body {
        padding: 40px 30px;
        color: var(--text-dark)
    }

    body.dark-mode .card-body {
        color: var(--text-light)
    }

    .register-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px
    }

    body.dark-mode .form-label {
        color: var(--text-light)
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: .95rem;
        transition: all .3s ease;
        background: var(--card-light);
        color: var(--text-dark)
    }

    body.dark-mode .form-control {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light)
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .1)
    }

    .input-group-text {
        background: var(--card-light);
        border: 2px solid #e2e8f0;
        border-right: none;
        border-radius: 12px 0 0 12px
    }

    body.dark-mode .input-group-text {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light)
    }

    .input-group .form-control {
        border-left: none;
        border-radius: 0 12px 12px 0
    }

    .btn-register {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 12px;
        border-radius: 12px;
        transition: all .3s ease;
        width: 100%;
        font-size: 1rem
    }

    .btn-register:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-register:active {
        transform: translateY(0)
    }

    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 12px 15px;
        font-weight: 500
    }

    .alert-danger {
        background: #fee2e2;
        color: #dc2626
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46
    }

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca
    }

    body.dark-mode .alert-success {
        background: #064e3b;
        color: #a7f3d0
    }

    .links-section {
        margin-top: 25px;
        text-align: center
    }

    .link-text {
        color: var(--text-dark);
        font-size: .9rem
    }

    body.dark-mode .link-text {
        color: var(--text-light)
    }

    .link-primary {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
        transition: color .3s ease
    }

    .link-primary:hover {
        color: var(--primary-dark);
        text-decoration: underline
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #6b7280;
        transition: color .3s ease
    }

    body.dark-mode .back-link {
        color: #9ca3af
    }

    .back-link:hover {
        color: var(--primary-color)
    }

    .theme-toggle {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, .2);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        transition: all .3s ease;
        backdrop-filter: blur(10px)
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg)
    }

    .password-strength {
        margin-top: 5px;
        font-size: .8rem
    }

    .strength-weak {
        color: #dc2626
    }

    .strength-medium {
        color: #d97706
    }

    .strength-strong {
        color: #059669
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    @media (max-width:576px) {
        .card-body {
            padding: 30px 20px
        }

        .register-container {
            max-width: 100%
        }
    }
    </style>
</head>

<body>
    <!-- Theme Toggle -->
    <button id="themeToggle" class="theme-toggle" title="Ganti Tema" aria-label="Ganti tema">
        <i class="fas fa-moon" aria-hidden="true"></i>
    </button>

    <div class="register-container">
        <div class="register-card">
            <div class="card-header">
                <i class="fas fa-user-plus register-icon" aria-hidden="true"></i>
                <h2 class="fw-bold mb-2">Daftar Akun</h2>
                <p class="mb-0 opacity-75">Bergabung dengan sistem kesehatan kami</p>
            </div>

            <div class="card-body">
                <?php if ($err): ?>
                <div class="alert alert-danger alert-custom mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php elseif ($success): ?>
                <div class="alert alert-success alert-custom mb-4" role="status">
                    <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form method="post" novalidate autocomplete="off">
                    <input type="hidden" name="csrf"
                        value="<?= htmlspecialchars($_SESSION['csrf_register'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-4">
                        <label class="form-label" for="nama">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"
                                    aria-hidden="true"></i></span>
                            <input type="text" id="nama" name="nama" class="form-control" required
                                placeholder="Masukkan nama lengkap Anda"
                                value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-muted"
                                    aria-hidden="true"></i></span>
                            <input type="email" id="email" name="email" class="form-control" required
                                placeholder="email@contoh.com" inputmode="email"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"
                                    aria-hidden="true"></i></span>
                            <input type="password" id="password" name="password" class="form-control" required
                                placeholder="Minimal 6 karakter">
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password2">Konfirmasi Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"
                                    aria-hidden="true"></i></span>
                            <input type="password" id="password2" name="password2" class="form-control" required
                                placeholder="Ulangi password Anda">
                        </div>
                        <div class="password-match" id="passwordMatch"></div>
                    </div>

                    <button type="submit" name="register" class="btn btn-register mb-4">
                        <i class="fas fa-user-plus me-2" aria-hidden="true"></i>
                        Daftar Akun
                    </button>
                </form>

                <div class="links-section">
                    <p class="link-text mb-3">
                        Sudah punya akun?
                        <a href="login.php" class="link-primary" rel="nofollow">Login Sekarang</a>
                    </p>

                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>
                        Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

    <script>
    (function() {
        const body = document.body;
        const toggleBtn = document.getElementById('themeToggle');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('password2');
        const strengthEl = document.getElementById('passwordStrength');
        const matchEl = document.getElementById('passwordMatch');

        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const startDark = saved ? (saved === 'dark') : prefersDark;
        if (startDark) {
            body.classList.add('dark-mode');
            toggleBtn.innerHTML = '<i class="fas fa-sun" aria-hidden="true"></i>';
        }

        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            toggleBtn.innerHTML = isDark ? '<i class="fas fa-sun" aria-hidden="true"></i>' :
                '<i class="fas fa-moon" aria-hidden="true"></i>';
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Strength indikator sederhana
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            let text = '',
                cls = '';
            if (!val) {
                text = '';
                cls = '';
            } else if (val.length < 6) {
                text = 'Kekuatan: Lemah';
                cls = 'strength-weak';
            } else if (val.length < 8) {
                text = 'Kekuatan: Sedang';
                cls = 'strength-medium';
            } else {
                text = 'Kekuatan: Kuat';
                cls = 'strength-strong';
            }
            strengthEl.textContent = text;
            strengthEl.className = 'password-strength ' + cls;
        });

        // Cocok/tidak
        confirmPasswordInput.addEventListener('input', function() {
            const ok = this.value && (this.value === passwordInput.value);
            matchEl.innerHTML = ok ?
                '<span style="color:#059669;">✓ Password cocok</span>' :
                (this.value ? '<span style="color:#dc2626;">✗ Password tidak cocok</span>' : '');
        });

        // Animasi fokus
        document.querySelectorAll('.form-control').forEach(el => {
            el.addEventListener('focus', () => el.closest('.input-group').style.transform = 'scale(1.02)');
            el.addEventListener('blur', () => el.closest('.input-group').style.transform = 'scale(1)');
        });
    })();
    </script>
</body>

</html>