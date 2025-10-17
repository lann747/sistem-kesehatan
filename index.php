<?php
// ---- Session & Security Headers ----
// Set cookie session yang lebih aman (panggil sebelum session_start)
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']), // kirim hanya lewat HTTPS
    'httponly' => true,                     // tidak bisa diakses JS
    'samesite' => 'Lax'
]);
session_start();

// Header keamanan ringan (tidak memutus kompatibilitas)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Jika sudah login, arahkan ke dashboard sesuai role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
        exit;
    } elseif ($_SESSION['role'] === 'user') {
        header('Location: user/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sistem Informasi Kesehatan</title>

    <!-- Preconnect untuk optimasi font -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <!-- Vendor CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font & Icons -->
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

    html {
        scroll-behavior: smooth
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: background-color .3s ease, color .3s ease;
        min-height: 100vh
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light)
    }

    /* Navbar */
    .navbar {
        background: rgba(255, 255, 255, .9);
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 15px rgba(0, 0, 0, .1);
        transition: all .3s ease
    }

    .navbar.navbar-dark {
        background: rgba(30, 41, 59, .9);
        box-shadow: 0 2px 15px rgba(0, 0, 0, .3)
    }

    .navbar-brand {
        font-weight: 700;
        color: var(--primary-color) !important;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .nav-link {
        font-weight: 500;
        color: var(--text-dark) !important;
        transition: color .3s ease
    }

    body.dark-mode .nav-link {
        color: var(--text-light) !important
    }

    .nav-link:hover {
        color: var(--primary-color) !important
    }

    /* Hero */
    .hero {
        background: var(--gradient);
        padding: 150px 0 100px;
        color: #fff;
        text-align: center;
        position: relative;
        overflow: hidden
    }

    body.dark-mode .hero {
        background: var(--gradient-dark)
    }

    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,213.3C672,192,768,128,864,128C960,128,1056,192,1152,192C1248,192,1344,128,1392,96L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') center/cover no-repeat
    }

    .hero-content {
        position: relative;
        z-index: 1
    }

    .hero h1 {
        font-weight: 700;
        margin-bottom: 20px;
        font-size: 2.8rem
    }

    .hero p {
        font-size: 1.2rem;
        max-width: 600px;
        margin: 0 auto 30px;
        opacity: .9
    }

    /* Buttons */
    .btn-primary-custom {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
        font-weight: 600;
        padding: 10px 25px;
        border-radius: 50px;
        transition: all .3s ease
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .1)
    }

    .btn-outline-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
        padding: 10px 25px;
        border-radius: 50px;
        transition: all .3s ease
    }

    .btn-outline-custom:hover {
        background: var(--primary-color);
        color: #fff;
        transform: translateY(-2px)
    }

    /* Sections */
    section {
        padding: 80px 0
    }

    .section-title {
        font-weight: 700;
        margin-bottom: 50px;
        color: var(--primary-color);
        position: relative;
        display: inline-block
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 50%;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px
    }

    /* Cards */
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        background: var(--card-light);
        height: 100%
    }

    body.dark-mode .card {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2)
    }

    .card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, .15)
    }

    .card-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 20px
    }

    .card h5 {
        font-weight: 600;
        margin-bottom: 15px
    }

    /* Footer */
    footer {
        background: var(--primary-dark);
        color: #fff;
        padding: 30px 0;
        text-align: center
    }

    body.dark-mode footer {
        background: #0f172a
    }

    /* Theme Toggle */
    #themeToggle {
        border: none;
        background: none;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
        transition: transform .3s ease;
        padding: 5px 10px;
        border-radius: 50%
    }

    #themeToggle:hover {
        transform: rotate(20deg);
        background: rgba(59, 130, 246, .1)
    }

    /* Animations */
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

    .fade-in-up {
        animation: fadeInUp .8s ease forwards
    }

    /* Responsive */
    @media (max-width:768px) {
        .hero h1 {
            font-size: 2rem
        }

        .hero p {
            font-size: 1rem
        }

        section {
            padding: 60px 0
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" role="navigation" aria-label="Navigasi utama">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="fas fa-heartbeat" aria-hidden="true"></i>
                <span>Sistem Kesehatan</span>
            </a>

            <!-- Toggler: pastikan ikon terlihat di Bootstrap -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tentang">Tentang</a></li>
                    <li class="nav-item"><a class="nav-link" href="#layanan">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary-custom ms-2 px-3" href="login.php" rel="nofollow">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-custom ms-2 px-3" href="register.php"
                            rel="nofollow">Daftar</a>
                    </li>
                    <li class="nav-item ms-2">
                        <button id="themeToggle" title="Ganti Tema" aria-label="Ganti tema">🌙</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section id="home" class="hero d-flex align-items-center justify-content-center">
        <div class="container hero-content">
            <div class="fade-in-up">
                <h1>Selamat Datang di Sistem Informasi Kesehatan</h1>
                <p>Kelola data pasien dan dokter dengan mudah, cepat, dan efisien dalam platform terintegrasi.</p>
                <a href="login.php" class="btn btn-light btn-lg px-4 py-2 rounded-pill fw-bold mt-3">
                    Mulai Sekarang <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Tentang -->
    <section id="tentang" class="py-5">
        <div class="container text-center">
            <h2 class="section-title">Tentang Sistem</h2>
            <p class="lead mb-5">Sistem Informasi Kesehatan ini dirancang untuk membantu pengelolaan data kesehatan
                seperti pasien, dokter, dan informasi layanan dengan efisiensi tinggi dan tampilan yang mudah digunakan.
            </p>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card p-4">
                        <div class="card-body">
                            <p>Sistem kami menyediakan solusi terintegrasi untuk manajemen data kesehatan, memungkinkan
                                akses yang cepat dan aman ke informasi medis penting. Dengan antarmuka yang intuitif,
                                staf medis dapat fokus pada perawatan pasien tanpa kesulitan teknis.</p>

                            <div class="mt-4">
                                <div class="row text-center">
                                    <div class="col-md-4 mb-3">
                                        <i class="fas fa-shield-alt fa-2x text-primary mb-2" aria-hidden="true"></i>
                                        <h5>Aman</h5>
                                        <p>Data pasien terlindungi dengan enkripsi tingkat tinggi</p>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <i class="fas fa-bolt fa-2x text-primary mb-2" aria-hidden="true"></i>
                                        <h5>Cepat</h5>
                                        <p>Akses informasi dalam hitungan detik</p>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <i class="fas fa-mobile-alt fa-2x text-primary mb-2" aria-hidden="true"></i>
                                        <h5>Responsif</h5>
                                        <p>Akses dari perangkat apa pun, kapan saja</p>
                                    </div>
                                </div>
                            </div>
                        </div> <!-- card-body -->
                    </div> <!-- card -->
                </div>
            </div>
        </div>
    </section>

    <!-- Layanan -->
    <section id="layanan" class="py-5 bg-light">
        <div class="container text-center">
            <h2 class="section-title">Layanan Kami</h2>
            <p class="lead mb-5">Berbagai fitur yang tersedia untuk mendukung pengelolaan data kesehatan</p>

            <div class="row justify-content-center">
                <div class="col-md-4 mb-4">
                    <div class="card p-4 h-100">
                        <div class="card-body">
                            <div class="card-icon"><i class="fas fa-user-injured" aria-hidden="true"></i></div>
                            <h5>Data Pasien</h5>
                            <p>Kelola data pasien secara mudah dan cepat dengan sistem pencarian yang canggih.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card p-4 h-100">
                        <div class="card-body">
                            <div class="card-icon"><i class="fas fa-user-md" aria-hidden="true"></i></div>
                            <h5>Data Dokter</h5>
                            <p>Informasi lengkap tentang dokter dan spesialisasi mereka dengan jadwal praktik.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card p-4 h-100">
                        <div class="card-body">
                            <div class="card-icon"><i class="fas fa-file-medical-alt" aria-hidden="true"></i></div>
                            <h5>Laporan Kesehatan</h5>
                            <p>Pantau laporan kesehatan pasien secara real-time dengan analitik yang komprehensif.</p>
                        </div>
                    </div>
                </div>
            </div> <!-- row -->
        </div>
    </section>

    <!-- Kontak -->
    <section id="kontak" class="py-5">
        <div class="container text-center">
            <h2 class="section-title">Kontak Kami</h2>
            <p class="lead mb-5">Hubungi kami untuk informasi lebih lanjut</p>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card p-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <i class="fas fa-envelope fa-2x text-primary mb-3" aria-hidden="true"></i>
                                    <h5>Email</h5>
                                    <p><a href="mailto:info@sistemkesehatan.id">info@sistemkesehatan.id</a></p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <i class="fas fa-map-marker-alt fa-2x text-primary mb-3" aria-hidden="true"></i>
                                    <h5>Lokasi</h5>
                                    <p>Universitas Bengkulu<br>Jl. WR. Supratman, Kandang Limun, Bengkulu</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="mailto:info@sistemkesehatan.id" class="btn btn-primary-custom">Hubungi Kami</a>
                            </div>
                        </div>
                    </div> <!-- card -->
                </div>
            </div> <!-- row -->
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="mb-0">© <?= date('Y'); ?> Sistem Informasi Kesehatan | Universitas Bengkulu</p>
            <p class="mt-2">Dibuat dengan <i class="fas fa-heart text-danger" aria-hidden="true"></i> untuk pelayanan
                kesehatan yang lebih baik</p>
        </div>
    </footer>

    <!-- Vendor JS (di akhir untuk performa) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

    <!-- Script Dark Mode, Animasi, dan UX -->
    <script>
    (function() {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const body = document.body;
        const toggleBtn = document.getElementById('themeToggle');

        // Set state awal dari localStorage atau prefers-color-scheme
        const saved = localStorage.getItem('theme');
        const startDark = saved ? (saved === 'dark') : prefersDark;
        if (startDark) {
            body.classList.add('dark-mode');
            document.querySelector('.navbar').classList.remove('navbar-light');
            document.querySelector('.navbar').classList.add('navbar-dark');
            toggleBtn.textContent = '☀️';
        }

        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const nav = document.querySelector('.navbar');
            const isDark = body.classList.contains('dark-mode');
            nav.classList.toggle('navbar-dark', isDark);
            nav.classList.toggle('navbar-light', !isDark);
            toggleBtn.textContent = isDark ? '☀️' : '🌙';
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Smooth scroll dengan offset navbar
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (!target) return;
                e.preventDefault();
                const y = target.getBoundingClientRect().top + window.pageYOffset - 70;
                window.scrollTo({
                    top: y,
                    behavior: 'smooth'
                });
            });
        });

        // Animasi muncul saat scroll (respect reduced motion)
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduce && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) entry.target.classList.add('fade-in-up');
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            document.querySelectorAll('.card, .section-title, .lead').forEach(el => observer.observe(el));
        }
    })();
    </script>

    <noscript>
        <div class="alert alert-warning text-center m-0" role="alert">
            Beberapa fitur (tema gelap & animasi) membutuhkan JavaScript agar berfungsi optimal.
        </div>
    </noscript>
</body>

</html>