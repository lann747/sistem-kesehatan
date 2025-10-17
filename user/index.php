<?php
// ---------- Bootstrap keamanan dasar ----------
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/../config/db.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Hanya user yang boleh masuk
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php'); exit;
}

// Helper escape
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Ambil statistik ringkas ----------
$total_keluhan_data = 0;
$total_dokter_data  = 0;
$keluhan_selesai_data = 0; // belum ada field status, set 0

if ($res = $conn->query("SELECT COUNT(*) AS total FROM pasien")) {
    $total_keluhan_data = (int)($res->fetch_assoc()['total'] ?? 0);
    $res->close();
}
if ($res = $conn->query("SELECT COUNT(*) AS total FROM dokter")) {
    $total_dokter_data = (int)($res->fetch_assoc()['total'] ?? 0);
    $res->close();
}
$progress = $total_keluhan_data > 0 ? (int)round(($keluhan_selesai_data / $total_keluhan_data) * 100) : 0;

// (Opsional) daftar dokter terbaru untuk quick access
$recent_dokter = [];
if ($res = $conn->query("SELECT id, nama, spesialis FROM dokter ORDER BY id DESC LIMIT 6")) {
    while ($row = $res->fetch_assoc()) { $recent_dokter[] = $row; }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Sistem Informasi Kesehatan</title>
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
        --navbar-bg: #3b82f6;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: all .3s ease;
        min-height: 100vh
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light)
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 2px 15px rgba(0, 0, 0, .1);
        padding: 1rem 0
    }

    body.dark-mode .navbar {
        background: var(--dark-bg);
        border-bottom-color: var(--primary-color)
    }

    .navbar-brand {
        font-weight: 700;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem
    }

    .user-info {
        color: #fff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px
    }

    .main-content {
        padding: 30px 0;
        min-height: calc(100vh - 120px)
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 15px
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px
    }

    .dashboard-card {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        height: 100%;
        border-left: 4px solid var(--primary-color)
    }

    body.dark-mode .dashboard-card {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2)
    }

    .dashboard-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, .15)
    }

    .card-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 15px
    }

    .card-title {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 10px
    }

    body.dark-mode .card-title {
        color: var(--text-light)
    }

    .card-text {
        color: #6b7280;
        font-size: .9rem;
        margin-bottom: 20px
    }

    body.dark-mode .card-text {
        color: #9ca3af
    }

    .btn-primary-custom,
    .btn-success-custom,
    .btn-warning-custom {
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all .3s ease;
        text-decoration: none;
        display: inline-block
    }

    .btn-primary-custom {
        background: var(--primary-color)
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-success-custom {
        background: var(--secondary-color)
    }

    .btn-success-custom:hover {
        background: #0d9669;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-warning-custom {
        background: #f59e0b
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-logout {
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .3);
        color: #fff;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all .3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, .3);
        color: #fff;
        transform: translateY(-1px)
    }

    .theme-toggle {
        background: rgba(255, 255, 255, .2);
        border: none;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        transition: all .3s ease;
        margin-right: 10px
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg)
    }

    footer {
        background: var(--primary-dark);
        color: #fff;
        padding: 20px 0;
        text-align: center;
        margin-top: auto
    }

    body.dark-mode footer {
        background: var(--dark-bg)
    }

    .stats-section {
        margin-bottom: 30px
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        transition: all .3s ease
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2)
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .12)
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 5px
    }

    .stat-label {
        color: #6b7280;
        font-size: .9rem;
        font-weight: 500
    }

    body.dark-mode .stat-label {
        color: #9ca3af
    }

    .welcome-section {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        color: #fff;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .1)
    }

    body.dark-mode .welcome-section {
        background: linear-gradient(135deg, var(--primary-dark), #1e293b)
    }

    .welcome-title {
        font-weight: 700;
        margin-bottom: 10px
    }

    .welcome-subtitle {
        opacity: .9;
        margin-bottom: 0
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    .fade-in-up {
        animation: fadeInUp .6s ease forwards
    }

    .table-sm td {
        padding: .45rem .5rem
    }

    @media (max-width:768px) {
        .main-content {
            padding: 20px 0
        }

        .page-title {
            font-size: 1.5rem
        }

        .user-info {
            font-size: .9rem
        }

        .navbar-brand {
            font-size: 1.1rem
        }

        .welcome-section {
            padding: 20px;
            text-align: center
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat"></i> Sistem Kesehatan</a>
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema"><i class="fas fa-moon"></i></button>
                <div class="user-info"><i class="fas fa-user"></i>
                    <span>Halo, <?= h($_SESSION['nama'] ?? 'User'); ?> (User)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section fade-in-up">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="welcome-title">Selamat Datang, <?= h($_SESSION['nama'] ?? 'User'); ?>!</h2>
                        <p class="welcome-subtitle">Kelola kesehatan Anda dengan mudah melalui sistem kami. Akses
                            layanan konsultasi, lihat riwayat, dan kelola profil Anda.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-injured fa-4x opacity-75"></i>
                    </div>
                </div>
            </div>

            <h1 class="page-title fade-in-up">Dashboard Pengguna</h1>

            <!-- Stats Section -->
            <div class="row stats-section fade-in-up">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$total_keluhan_data; ?></div>
                        <div class="stat-label">Total Keluhan</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$keluhan_selesai_data; ?></div>
                        <div class="stat-label">Keluhan Selesai</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$total_dokter_data; ?></div>
                        <div class="stat-label">Dokter Tersedia</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$progress; ?>%</div>
                        <div class="stat-label">Progress Penyelesaian</div>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="row g-4">
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-circle"></i></div>
                        <h5 class="card-title">Profil Saya</h5>
                        <p class="card-text">Kelola informasi pribadi, data kontak, dan ubah password.</p>
                        <a href="profil.php" class="btn-success-custom"><i class="fas fa-user-edit me-2"></i>Kelola
                            Profil</a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-md"></i></div>
                        <h5 class="card-title">Konsultasi Dokter</h5>
                        <p class="card-text">Lihat daftar dokter spesialis & jadwal praktik.</p>
                        <a href="dokter.php" class="btn-primary-custom"><i class="fas fa-stethoscope me-2"></i>Lihat
                            Dokter</a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-file-medical-alt"></i></div>
                        <h5 class="card-title">Riwayat Keluhan</h5>
                        <p class="card-text">Catatan keluhan, status konsultasi, & riwayat pengobatan.</p>
                        <a href="keluhan.php" class="btn-warning-custom"><i class="fas fa-history me-2"></i>Lihat
                            Riwayat</a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-calendar-plus"></i></div>
                        <h5 class="card-title">Buat Janji Temu</h5>
                        <p class="card-text">Jadwalkan konsultasi sesuai kebutuhan Anda.</p>
                        <a href="janji_temu.php" class="btn-primary-custom"><i
                                class="fas fa-calendar-check me-2"></i>Buat Janji</a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-pills"></i></div>
                        <h5 class="card-title">Resep Digital</h5>
                        <p class="card-text">Akses resep obat digital & info penggunaan.</p>
                        <a href="resep.php" class="btn-success-custom"><i
                                class="fas fa-file-prescription me-2"></i>Lihat Resep</a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-question-circle"></i></div>
                        <h5 class="card-title">Bantuan & Panduan</h5>
                        <p class="card-text">Panduan penggunaan & bantuan teknis.</p>
                        <a href="bantuan.php" class="btn-warning-custom"><i class="fas fa-life-ring me-2"></i>Dapatkan
                            Bantuan</a>
                    </div>
                </div>
            </div>

            <!-- Dokter terbaru (opsional) -->
            <div class="row g-4 mt-1">
                <div class="col-12 fade-in-up">
                    <div class="dashboard-card p-4">
                        <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Dokter Terbaru</h6>
                        <?php if ($recent_dokter): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recent_dokter as $d): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($d['nama']); ?></td>
                                        <td><span class="badge bg-info text-dark"><?= h($d['spesialis']); ?></span></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="dokter.php"><i
                                                    class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">Belum ada data dokter.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="mb-0">© <?= date('Y'); ?> Sistem Informasi Kesehatan | Universitas Bengkulu</p>
            <p class="mt-2 small opacity-75">Dashboard User</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
    const toggleBtn = document.getElementById('themeToggle'),
        body = document.body;
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

    // Animasi kartu
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dashboard-card').forEach((card, i) => {
            card.style.animationDelay = `${i*0.1}s`;
            card.classList.add('fade-in-up');
        });
    });
    </script>
</body>

</html>