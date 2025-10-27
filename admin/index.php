<?php

session_start();

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); 
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$stat = ['pasien'=>0, 'dokter'=>0, 'user'=>0];
if ($r = $conn->query("SELECT COUNT(*) c FROM pasien")) { 
    $stat['pasien'] = (int)$r->fetch_assoc()['c']; 
    $r->close(); 
}
if ($r = $conn->query("SELECT COUNT(*) c FROM dokter")) { 
    $stat['dokter'] = (int)$r->fetch_assoc()['c']; 
    $r->close(); 
}
if ($r = $conn->query("SELECT COUNT(*) c FROM users"))  { 
    $stat['user']   = (int)$r->fetch_assoc()['c']; 
    $r->close(); 
}

$recent_pasien = [];
if ($res = $conn->query("SELECT id, nama, umur, alamat FROM pasien ORDER BY id DESC LIMIT 5")) {
    while ($row = $res->fetch_assoc()) { $recent_pasien[] = $row; }
    $res->close();
}
$recent_dokter = [];
if ($res = $conn->query("SELECT id, nama, spesialis FROM dokter ORDER BY id DESC LIMIT 5")) {
    while ($row = $res->fetch_assoc()) { $recent_dokter[] = $row; }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Rafflesia Sehat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #16a34a;
        --primary-light: #22c55e;
        --primary-dark: #15803d;
        --secondary-color: #10b981;
        --light-bg: #f0fdf4;
        --dark-bg: #0f172a;
        --text-light: #f8fafc;
        --text-dark: #1e293b;
        --card-light: #ffffff;
        --card-dark: #1e293b;
        --sidebar-bg: #1e293b;
        --navbar-bg: #16a34a;
        --shadow-light: 0 10px 25px rgba(0, 0, 0, 0.05);
        --shadow-medium: 0 15px 35px rgba(0, 0, 0, 0.1);
        --shadow-heavy: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: all 1s ease;
        min-height: 100vh;
        line-height: 1.6;
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: var(--shadow-medium);
        padding: 1rem 0;
        backdrop-filter: blur(10px);
    }

    .navbar-brand {
        font-weight: 700;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.4rem;
    }

    .user-info {
        color: #fff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 15px;
        border-radius: 10px;
        backdrop-filter: blur(5px);
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        font-weight: 500;
        padding: 8px 18px;
        border-radius: 10px;
        transition: all 1s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .main-content {
        padding: 40px 0;
        min-height: calc(100vh - 120px);
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 35px;
        position: relative;
        padding-bottom: 15px;
        font-size: 2.2rem;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100px;
        height: 4px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    .dashboard-card {
        background: var(--card-light);
        border: none;
        border-radius: 16px;
        box-shadow: var(--shadow-light);
        transition: all 1s ease;
        height: 100%;
        border-left: 5px solid var(--primary-color);
        overflow: hidden;
        position: relative;
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
        transform: scaleX(0);
        transition: transform 1s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-heavy);
    }

    .dashboard-card:hover::before {
        transform: scaleX(1);
    }

    .card-icon {
        font-size: 2.8rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        transition: transform 1s ease;
    }

    .dashboard-card:hover .card-icon {
        transform: scale(1.1);
    }

    .card-title {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 12px;
        font-size: 1.2rem;
    }

    .card-text {
        color: #6b7280;
        font-size: 0.95rem;
        margin-bottom: 25px;
        line-height: 1.5;
    }

    .btn-primary-custom,
    .btn-success-custom,
    .btn-warning-custom {
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 24px;
        border-radius: 10px;
        transition: all 1s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .btn-primary-custom {
        background: var(--primary-color);
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
    }

    .btn-success-custom {
        background: var(--secondary-color);
    }

    .btn-success-custom:hover {
        background: #0d9669;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-warning-custom {
        background: #f59e0b;
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
    }

    footer {
        background: var(--primary-dark);
        color: #fff;
        padding: 25px 0;
        text-align: center;
        margin-top: auto;
    }

    .stats-section {
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 16px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: var(--shadow-light);
        transition: all 1s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 8px;
        display: block;
    }

    .stat-label {
        color: #6b7280;
        font-size: 0.95rem;
        font-weight: 500;
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
        animation: fadeInUp 0.6s ease forwards;
    }

    .table-sm tr td {
        padding: 0.6rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }

    .recent-section {
        margin-top: 40px;
    }

    .recent-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 20px;
    }

    .recent-title {
        font-weight: 600;
        color: var(--text-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-view-all {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 8px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 1s ease;
    }

    .btn-view-all:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 25px 0;
        }

        .page-title {
            font-size: 1.8rem;
        }

        .user-info,
        .navbar-brand {
            font-size: 1rem;
        }

        .stat-number {
            font-size: 2rem;
        }

        .card-icon {
            font-size: 2.2rem;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand {
            font-size: 1.1rem;
        }

        .user-info {
            padding: 6px 12px;
            font-size: 0.9rem;
        }

        .btn-logout {
            padding: 6px 12px;
            font-size: 0.9rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .main-content {
            padding: 20px 0;
        }
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat"></i>
                <span>Rafflesia Sehat</span>
            </a>
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span>Halo, <?= h($_SESSION['nama'] ?? 'Admin'); ?> (Admin)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <h1 class="page-title fade-in-up">Dashboard Admin</h1>

            <div class="row stats-section fade-in-up">
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$stat['pasien']; ?></div>
                        <div class="stat-label">Total Pasien</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$stat['dokter']; ?></div>
                        <div class="stat-label">Total Dokter</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$stat['user']; ?></div>
                        <div class="stat-label">Pengguna Terdaftar</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $ratio = $stat['dokter'] ? round($stat['pasien'] / $stat['dokter'], 1) : 0;
                            echo h($ratio).'x';
                            ?>
                        </div>
                        <div class="stat-label">Rasio Pasien/Dokter</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-injured"></i></div>
                        <h5 class="card-title">Data Pasien</h5>
                        <p class="card-text">Kelola data pasien & keluhan secara terintegrasi.</p>
                        <a href="data_pasien.php" class="btn-success-custom">
                            <i class="fas fa-database me-2"></i>Kelola Data
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-md"></i></div>
                        <h5 class="card-title">Data Dokter</h5>
                        <p class="card-text">Kelola daftar dokter & spesialisasi.</p>
                        <a href="data_dokter.php" class="btn-primary-custom">
                            <i class="fas fa-stethoscope me-2"></i>Kelola Data
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-users-cog"></i></div>
                        <h5 class="card-title">Manajemen Akun</h5>
                        <p class="card-text">Kelola akun pengguna & hak akses.</p>
                        <a href="data_user.php" class="btn-warning-custom">
                            <i class="fas fa-cog me-2"></i>Kelola Akun
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-file-medical-alt"></i></div>
                        <h5 class="card-title">Laporan Medis</h5>
                        <p class="card-text">Akses laporan & statistik kesehatan.</p>
                        <a href="laporan.php" class="btn-primary-custom">
                            <i class="fas fa-chart-bar me-2"></i>Lihat Laporan
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-calendar-check"></i></div>
                        <h5 class="card-title">Jadwal Praktik</h5>
                        <p class="card-text">Atur jadwal praktik & konsultasi.</p>
                        <a href="jadwal.php" class="btn-success-custom">
                            <i class="fas fa-clock me-2"></i>Kelola Jadwal
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-cogs"></i></div>
                        <h5 class="card-title">Pengaturan Sistem</h5>
                        <p class="card-text">Konfigurasi, backup, & keamanan.</p>
                        <a href="pengaturan.php" class="btn-warning-custom">
                            <i class="fas fa-sliders-h me-2"></i>Pengaturan
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-4 recent-section">
                <div class="col-lg-6 fade-in-up">
                    <div class="dashboard-card p-4">
                        <div class="recent-header">
                            <h6 class="recent-title">
                                <i class="fas fa-clock me-2"></i>Pasien Terbaru
                            </h6>
                            <a href="data_pasien.php" class="btn-view-all m-2">Lihat Semua</a>
                        </div>
                        <?php if ($recent_pasien): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recent_pasien as $p): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($p['nama']) ?></td>
                                        <td><span class="badge bg-primary"><?= (int)$p['umur'] ?> th</span></td>
                                        <td class="text-muted small"><?= h($p['alamat']) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="data_pasien.php">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">Belum ada data pasien.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="dashboard-card p-4">
                        <div class="recent-header">
                            <h6 class="recent-title">
                                <i class="fas fa-clock me-2"></i>Dokter Terbaru
                            </h6>
                            <a href="data_dokter.php" class="btn-view-all m-2">Lihat Semua</a>
                        </div>
                        <?php if ($recent_dokter): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recent_dokter as $d): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($d['nama']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= h($d['spesialis']) ?></span></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="data_dokter.php">
                                                <i class="fas fa-eye"></i>
                                            </a>
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

    <footer>
        <div class="container">
            <p class="mb-0">© <?= date('Y'); ?> Rafflesia Sehat | Universitas Bengkulu</p>
            <p class="mt-2 small opacity-75">Dashboard Admin - Sistem Manajemen Kesehatan</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.fade-in-up').forEach((element, index) => {
            element.style.animationDelay = `${index * 0.1}s`;
        });

        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
</body>

</html>