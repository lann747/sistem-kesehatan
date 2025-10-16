<?php
session_start();
include '../config/db.php';

// Pastikan hanya admin yang bisa masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Informasi Kesehatan</title>
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
        --sidebar-bg: #1e293b;
        --navbar-bg: #3b82f6;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: all 0.3s ease;
        min-height: 100vh;
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light);
    }

    /* Navbar */
    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        padding: 1rem 0;
    }

    body.dark-mode .navbar {
        background: var(--sidebar-bg);
        border-bottom-color: var(--primary-color);
    }

    .navbar-brand {
        font-weight: 700;
        color: white !important;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
    }

    .user-info {
        color: white;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Main Content */
    .main-content {
        padding: 30px 0;
        min-height: calc(100vh - 120px);
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 15px;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    /* Cards */
    .dashboard-card {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        height: 100%;
        border-left: 4px solid var(--primary-color);
    }

    body.dark-mode .dashboard-card {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .dashboard-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .card-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .card-title {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 10px;
    }

    body.dark-mode .card-title {
        color: var(--text-light);
    }

    .card-text {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    body.dark-mode .card-text {
        color: #9ca3af;
    }

    /* Buttons */
    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: white;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    .btn-success-custom {
        background: var(--secondary-color);
        border: none;
        color: white;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-success-custom:hover {
        background: #0d9669;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    .btn-warning-custom {
        background: #f59e0b;
        border: none;
        color: white;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-1px);
    }

    /* Theme Toggle */
    .theme-toggle {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-right: 10px;
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(20deg);
    }

    /* Footer */
    footer {
        background: var(--primary-dark);
        color: white;
        padding: 20px 0;
        text-align: center;
        margin-top: auto;
    }

    body.dark-mode footer {
        background: var(--sidebar-bg);
    }

    /* Stats Section */
    .stats-section {
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 500;
    }

    body.dark-mode .stat-label {
        color: #9ca3af;
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.6s ease forwards;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .user-info {
            font-size: 0.9rem;
        }

        .navbar-brand {
            font-size: 1.1rem;
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-heartbeat"></i> Sistem Kesehatan
            </a>
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span>Halo, <?= htmlspecialchars($_SESSION['nama']); ?> (Admin)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1 class="page-title fade-in-up">Dashboard Admin</h1>

            <!-- Stats Section -->
            <div class="row stats-section fade-in-up">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number">150</div>
                        <div class="stat-label">Total Pasien</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number">25</div>
                        <div class="stat-label">Total Dokter</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number">45</div>
                        <div class="stat-label">Pengguna Aktif</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number">89%</div>
                        <div class="stat-label">Kepuasan</div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="row g-4">
                <!-- Card 1 -->
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-user-injured"></i>
                        </div>
                        <h5 class="card-title">Data Pasien</h5>
                        <p class="card-text">Kelola data pasien, riwayat medis, dan keluhan mereka dengan sistem
                            terintegrasi.</p>
                        <a href="data_pasien.php" class="btn-success-custom">
                            <i class="fas fa-database me-2"></i>Kelola Data
                        </a>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h5 class="card-title">Data Dokter</h5>
                        <p class="card-text">Kelola daftar dokter, spesialisasi, jadwal praktik, dan informasi kontak.
                        </p>
                        <a href="data_dokter.php" class="btn-primary-custom">
                            <i class="fas fa-stethoscope me-2"></i>Kelola Data
                        </a>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h5 class="card-title">Manajemen Akun</h5>
                        <p class="card-text">Tambahkan, edit, atau hapus akun pengguna sistem dengan hak akses yang
                            sesuai.</p>
                        <a href="data_user.php" class="btn-warning-custom">
                            <i class="fas fa-cog me-2"></i>Kelola Akun
                        </a>
                    </div>
                </div>

                <!-- Additional Cards -->
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-file-medical-alt"></i>
                        </div>
                        <h5 class="card-title">Laporan Medis</h5>
                        <p class="card-text">Akses dan kelola laporan medis, rekam jejak pasien, dan statistik
                            kesehatan.</p>
                        <a href="laporan.php" class="btn-primary-custom">
                            <i class="fas fa-chart-bar me-2"></i>Lihat Laporan
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5 class="card-title">Jadwal Praktik</h5>
                        <p class="card-text">Atur jadwal praktik dokter dan sesi konsultasi untuk optimalisasi layanan.
                        </p>
                        <a href="jadwal.php" class="btn-success-custom">
                            <i class="fas fa-clock me-2"></i>Kelola Jadwal
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h5 class="card-title">Pengaturan Sistem</h5>
                        <p class="card-text">Konfigurasi sistem, backup data, dan pengaturan keamanan aplikasi.</p>
                        <a href="pengaturan.php" class="btn-warning-custom">
                            <i class="fas fa-sliders-h me-2"></i>Pengaturan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="mb-0">© <?= date('Y'); ?> Sistem Informasi Kesehatan | Universitas Bengkulu</p>
            <p class="mt-2 small opacity-75">Dashboard Admin - Versi 2.0</p>
        </div>
    </footer>

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

    // Animasi untuk cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.dashboard-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    });
    </script>
</body>

</html>