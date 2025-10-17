<?php
// ---- Session & Security Headers ----
// Set cookie session yang lebih aman (panggil sebelum session_start)
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

// Header keamanan ringan
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Jika sudah login, arahkan langsung ke dashboard
if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php'); exit;
    } elseif ($_SESSION['role'] === 'user') {
        header('Location: user/index.php'); exit;
    }
}

// ---- Rate limiting sederhana ----
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = 0;
}
$LOCK_WINDOW_SEC = 300;   // 5 menit
$MAX_ATTEMPTS    = 5;

$now = time();
$locked_until = 0;
if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
    $locked_until = $_SESSION['login_last_attempt'] + $LOCK_WINDOW_SEC;
    if ($now < $locked_until) {
        $remaining = $locked_until - $now;
        $error = "Terlalu banyak percobaan. Coba lagi dalam " . ceil($remaining / 60) . " menit.";
    } else {
        // Reset setelah masa kunci
        $_SESSION['login_attempts'] = 0;
    }
}

$error = $error ?? '';

// ---- CSRF token ----
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $post_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_login'], $post_csrf)) {
        $error = "Sesi formulir kadaluarsa atau tidak valid. Muat ulang halaman.";
    } else {
        // Ambil & validasi input
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = "Email dan password harus diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } else {
            // ---- Query aman dengan prepared statement ----
            $stmt = $conn->prepare("SELECT id, nama, email, password, role FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res && $res->num_rows === 1) {
                    $user = $res->fetch_assoc();

                    $dbHash = $user['password'] ?? '';

                    // ---- Kompatibilitas MD5 lama + dukungan password_hash baru ----
                    $ok = false;
                    $isModernHash = str_starts_with($dbHash, '$2y$') || str_starts_with($dbHash, '$argon2');

                    if ($isModernHash) {
                        // Verifikasi password modern
                        $ok = password_verify($password, $dbHash);
                    } else {
                        // Verifikasi MD5 lama (sesuai struktur DB saat ini)
                        $ok = (md5($password) === $dbHash);
                    }

                    if ($ok) {
                        // Regenerate session ID untuk cegah session fixation
                        session_regenerate_id(true);

                        // Set session data minimal
                        $_SESSION['id']   = (int)$user['id'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['role'] = $user['role'];

                        // Reset rate limit counter
                        $_SESSION['login_attempts'] = 0;

                        // ---- (Opsional) Migrasi ke hash modern otomatis ----
                        // Jika masih MD5, upgrade ke password_hash (bcrypt)
                        if (!$isModernHash) {
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
                            if ($up) {
                                $up->bind_param('si', $newHash, $user['id']);
                                $up->execute();
                                $up->close();
                            }
                        }

                        // Arahkan sesuai role
                        if ($user['role'] === 'admin') {
                            header('Location: admin/index.php'); exit;
                        } else {
                            header('Location: user/index.php'); exit;
                        }
                    } else {
                        $error = "Email atau password salah.";
                        $_SESSION['login_attempts']++;
                        $_SESSION['login_last_attempt'] = time();
                    }
                } else {
                    $error = "Email tidak ditemukan.";
                    $_SESSION['login_attempts']++;
                    $_SESSION['login_last_attempt'] = time();
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
    <title>Login - Sistem Informasi Kesehatan</title>
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

    .login-container {
        width: 100%;
        max-width: 450px;
        animation: fadeInUp .8s ease
    }

    .login-card {
        background: var(--card-light);
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, .1);
        overflow: hidden;
        transition: all .3s ease
    }

    body.dark-mode .login-card {
        background: var(--card-dark);
        box-shadow: 0 15px 35px rgba(0, 0, 0, .3)
    }

    .login-card:hover {
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

    .login-icon {
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

    .btn-login {
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

    .btn-login:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-login:active {
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

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca
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

        .login-container {
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
                        <label class="form-label" for="email">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted" aria-hidden="true"></i>
                            </span>
                            <input type="email" id="email" name="email" class="form-control border-start-0"
                                placeholder="Masukkan email Anda" required inputmode="email" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
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
                        <a href="register.php" class="link-primary" rel="nofollow">Daftar Sekarang</a>
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
    // Dark mode toggle + simpan preferensi
    (function() {
        const body = document.body;
        const btn = document.getElementById('themeToggle');
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

        const startDark = saved ? (saved === 'dark') : prefersDark;
        if (startDark) {
            body.classList.add('dark-mode');
            btn.innerHTML = '<i class="fas fa-sun" aria-hidden="true"></i>';
        }

        btn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            btn.innerHTML = isDark ? '<i class="fas fa-sun" aria-hidden="true"></i>' :
                '<i class="fas fa-moon" aria-hidden="true"></i>';
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Animasi fokus input
        document.querySelectorAll('.form-control').forEach(el => {
            el.addEventListener('focus', () => el.closest('.input-group').style.transform = 'scale(1.02)');
            el.addEventListener('blur', () => el.closest('.input-group').style.transform = 'scale(1)');
        });
    })();
    </script>
</body>

</html>