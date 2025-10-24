<?php
session_start();

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
    <title>Rafflesia Sehat</title>
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
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: background-color 1s ease, color 1s ease;
        min-height: 100vh;
        line-height: 1.6;
    }

    .navbar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        box-shadow: var(--shadow-light);
        transition: all 1s ease;
        padding: 1rem 0;
    }

    .navbar-brand {
        font-weight: 700;
        color: var(--primary-color) !important;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.5rem;
    }

    .nav-link {
        font-weight: 500;
        color: var(--text-dark) !important;
        transition: color 1s ease;
        position: relative;
    }

    .nav-link:hover {
        color: var(--primary-color) !important;
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 0;
        height: 2px;
        background-color: var(--primary-color);
        transition: width 1s ease;
    }

    .nav-link:hover::after {
        width: 100%;
    }

    .hero {
        background: var(--gradient);
        padding: 180px 0 120px;
        color: #fff;
        text-align: center;
        position: relative;
        overflow: hidden;
        /* clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%); */
    }

    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,213.3C672,192,768,128,864,128C960,128,1056,192,1152,192C1248,192,1344,128,1392,96L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') center/cover no-repeat;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .hero h1 {
        font-weight: 700;
        margin-bottom: 20px;
        font-size: 3.2rem;
        line-height: 1.2;
    }

    .hero p {
        font-size: 1.25rem;
        max-width: 600px;
        margin: 0 auto 30px;
        opacity: 0.9;
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 50px;
        transition: all 1s ease;
        box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
    }

    .btn-outline-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 50px;
        transition: all 1s ease;
    }

    .btn-outline-custom:hover {
        background: var(--primary-color);
        color: #fff;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
    }

    section {
        padding: 100px 0;
    }

    .section-title {
        font-weight: 700;
        margin-bottom: 50px;
        color: var(--primary-color);
        position: relative;
        display: inline-block;
        font-size: 2.5rem;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    .card {
        border: none;
        border-radius: 16px;
        box-shadow: var(--shadow-light);
        transition: all 1s ease;
        background: var(--card-light);
        height: 100%;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-medium);
    }

    .card-icon {
        font-size: 2.8rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        transition: transform 1s ease;
    }

    .card:hover .card-icon {
        transform: scale(1.1);
    }

    .card h5 {
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1.3rem;
    }

    footer {
        background: var(--primary-dark);
        color: #fff;
        padding: 40px 0 20px;
        text-align: center;
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

    .fade-in-up {
        animation: fadeInUp 0.8s ease forwards;
    }

    .feature-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(22, 163, 74, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .feature-icon i {
        font-size: 2rem;
        color: var(--primary-color);
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
    }

    .contact-info i {
        background: rgba(22, 163, 74, 0.1);
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
    }

    .stats-container {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 40px;
    }

    .stat-item {
        text-align: center;
        padding: 20px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-color);
        display: block;
    }

    .stat-label {
        font-size: 1rem;
        color: var(--text-dark);
        margin-top: 5px;
    }

    @media (max-width: 768px) {
        .hero {
            padding: 150px 0 80px;
            /* clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%); */
        }

        .hero h1 {
            font-size: 2.2rem;
        }

        .hero p {
            font-size: 1.1rem;
        }

        section {
            padding: 70px 0;
        }

        .section-title {
            font-size: 2rem;
        }

        .stats-container {
            flex-direction: column;
            gap: 15px;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand {
            font-size: 1.3rem;
        }

        .hero h1 {
            font-size: 1.8rem;
        }

        .btn-primary-custom,
        .btn-outline-custom {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" role="navigation" aria-label="Navigasi utama">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="fas fa-heartbeat" aria-hidden="true"></i>
                <span>Rafflesia Sehat</span>
            </a>

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
                </ul>
            </div>
        </div>
    </nav>

    <section id="home" class="hero d-flex align-items-center justify-content-center">
        <div class="container hero-content">
            <div class="fade-in-up">
                <h1>Selamat Datang di Rafflesia Sehat</h1>
                <p>Kelola data pasien dan dokter dengan mudah, cepat, dan efisien dalam platform terintegrasi.</p>
                <a href="login.php" class="btn btn-light btn-lg px-4 py-2 rounded-pill fw-bold mt-3">
                    Mulai Sekarang <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="tentang" class="py-5">
        <div class="container text-center">
            <h2 class="section-title">Tentang Sistem</h2>
            <p class="lead mb-5">Rafflesia Sehat ini dirancang untuk membantu pengelolaan data kesehatan
                seperti pasien, dokter, dan informasi layanan dengan efisiensi tinggi dan tampilan yang mudah digunakan.
            </p>

            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card p-4">
                        <div class="card-body">
                            <p class="mb-4">Disini kami menyediakan solusi terintegrasi untuk manajemen data kesehatan,
                                memungkinkan
                                akses yang cepat dan aman ke informasi medis penting. Dengan antarmuka yang intuitif,
                                staf medis dapat fokus pada perawatan pasien tanpa kesulitan teknis.</p>

                            <div class="stats-container">
                                <div class="stat-item">
                                    <span class="stat-number">500+</span>
                                    <span class="stat-label">Pengguna Aktif</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">10K+</span>
                                    <span class="stat-label">Data Tersimpan</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">99.9%</span>
                                    <span class="stat-label">Uptime</span>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div class="row text-center">
                                    <div class="col-md-4 mb-4">
                                        <div class="feature-icon">
                                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                                        </div>
                                        <h5>Aman</h5>
                                        <p>Data pasien terlindungi dengan enkripsi tingkat tinggi</p>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="feature-icon">
                                            <i class="fas fa-bolt" aria-hidden="true"></i>
                                        </div>
                                        <h5>Cepat</h5>
                                        <p>Akses informasi dalam hitungan detik</p>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="feature-icon">
                                            <i class="fas fa-mobile-alt" aria-hidden="true"></i>
                                        </div>
                                        <h5>Responsif</h5>
                                        <p>Akses dari perangkat apa pun, kapan saja</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
            </div>
        </div>
    </section>

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
                                    <div class="contact-info">
                                        <i class="fas fa-envelope fa-2x card-icon"></i>
                                        <h5>Email</h5>
                                        <p><a href="mailto:info@rafflesiasehat.id">info@rafflesiasehat.id</a>
                                        </p>
                                    </div>
                                </div>
                                <div class=" col-md-6 mb-4">
                                    <div class="contact-info">
                                        <i class="fas fa-map-marker-alt fa-2x card-icon"></i>
                                        <h5>Lokasi</h5>
                                        <p>Universitas Bengkulu<br>Jl. WR. Supratman, Kandang Limun,
                                            Bengkulu</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="mailto:info@rafflesiasehat.id" class="btn btn-primary-custom">Hubungi
                                    Kami</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p class="mb-0">© <?= date('Y'); ?> Rafflesia Sehat | Universitas Bengkulu</p>
            <p class="mt-2">Dibuat oleh kelompok 3 untuk pelayanan kesehatan yang lebih baik</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
    (function() {
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
</body>

</html>