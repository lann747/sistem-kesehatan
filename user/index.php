<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php'); exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$total_keluhan_data = 0;
$total_dokter_data  = 0;
$keluhan_selesai_data = 0; 

if ($res = $conn->query("SELECT COUNT(*) AS total FROM pasien")) {
    $total_keluhan_data = (int)($res->fetch_assoc()['total'] ?? 0);
    $res->close();
}
if ($res = $conn->query("SELECT COUNT(*) AS total FROM dokter")) {
    $total_dokter_data = (int)($res->fetch_assoc()['total'] ?? 0);
    $res->close();
}
$progress = $total_keluhan_data > 0 ? (int)round(($keluhan_selesai_data / $total_keluhan_data) * 100) : 0;

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
        --primary-color: #16a34a;
        --primary-light: #22c55e;
        --primary-dark: #15803d;
        --secondary-color: #10b981;
        --accent-color: #84cc16;
        --light-bg: #f0fdf4;
        --light-bg-secondary: #dcfce7;
        --dark-bg: #0f172a;
        --text-light: #f8fafc;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --card-light: #ffffff;
        --card-dark: #1e293b;
        --navbar-bg: #16a34a;
        --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-heavy: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        --gradient-secondary: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, var(--light-bg) 0%, var(--light-bg-secondary) 100%);
        color: var(--text-dark);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
        line-height: 1.6;
        overflow-x: hidden;
    }

    .navbar {
        background: var(--gradient-primary);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: var(--shadow-medium);
        padding: 1rem 0;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 1000;
    }

    .navbar::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        opacity: 0.1;
    }

    .navbar-brand {
        font-weight: 700;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.4rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .user-info {
        color: #fff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 16px;
        border-radius: 12px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .main-content {
        padding: 40px 0;
        min-height: calc(100vh - 120px);
        position: relative;
    }

    .page-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 15px;
        font-size: 2.2rem;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
    }

    .dashboard-card {
        background: var(--card-light);
        border: none;
        border-radius: 20px;
        box-shadow: var(--shadow-light);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(22, 163, 74, 0.1);
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.4s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-heavy);
    }

    .dashboard-card:hover::before {
        transform: scaleX(1);
    }

    .card-icon {
        font-size: 3rem;
        margin-bottom: 20px;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        filter: drop-shadow(0 4px 8px rgba(22, 163, 74, 0.2));
    }

    .card-title {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 12px;
        font-size: 1.2rem;
    }

    .card-text {
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 24px;
        line-height: 1.5;
    }

    .btn-custom {
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 12px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-custom::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-custom:hover::before {
        left: 100%;
    }

    .btn-primary-custom {
        background: var(--gradient-primary);
    }

    .btn-primary-custom:hover {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
    }

    .btn-success-custom {
        background: var(--gradient-secondary);
    }

    .btn-success-custom:hover {
        background: linear-gradient(135deg, var(--accent-color), var(--secondary-color));
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-warning-custom {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .btn-warning-custom:hover {
        background: linear-gradient(135deg, #d97706, #f59e0b);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        font-weight: 500;
        padding: 8px 18px;
        border-radius: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(10px);
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    footer {
        background: var(--gradient-primary);
        color: #fff;
        padding: 25px 0;
        text-align: center;
        margin-top: auto;
        position: relative;
        overflow: hidden;
    }

    footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        opacity: 0.1;
    }

    .stats-section {
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        box-shadow: var(--shadow-light);
        transition: all 0.3s ease;
        border: 1px solid rgba(22, 163, 74, 0.1);
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
        background: var(--gradient-primary);
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
        text-shadow: 0 2px 4px rgba(22, 163, 74, 0.2);
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .welcome-section {
        background: var(--gradient-primary);
        color: #fff;
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 40px;
        box-shadow: var(--shadow-heavy);
        position: relative;
        overflow: hidden;
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        opacity: 0.1;
    }

    .welcome-title {
        font-weight: 700;
        margin-bottom: 12px;
        font-size: 2rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .welcome-subtitle {
        opacity: 0.9;
        margin-bottom: 0;
        font-size: 1.1rem;
        line-height: 1.6;
    }

    .welcome-icon {
        font-size: 4rem;
        opacity: 0.8;
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
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

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .floating {
        animation: float 3s ease-in-out infinite;
    }

    .table-sm td {
        padding: 0.75rem 0.5rem;
    }

    .badge-custom {
        background: var(--gradient-primary);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 25px 0;
        }

        .page-title {
            font-size: 1.8rem;
        }

        .user-info {
            font-size: 0.9rem;
            padding: 6px 12px;
        }

        .navbar-brand {
            font-size: 1.2rem;
        }

        .welcome-section {
            padding: 25px;
            text-align: center;
        }

        .welcome-title {
            font-size: 1.6rem;
        }

        .welcome-subtitle {
            font-size: 1rem;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-number {
            font-size: 2rem;
        }

        .dashboard-card {
            margin-bottom: 20px;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand {
            font-size: 1.1rem;
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

        .welcome-section {
            padding: 20px;
        }

        .welcome-title {
            font-size: 1.4rem;
        }

        .card-icon {
            font-size: 2.5rem;
        }

        .btn-custom {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
    }

    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--light-bg);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
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
                    <i class="fas fa-user-circle"></i>
                    <span>Halo, <?= h($_SESSION['nama'] ?? 'User'); ?> (User)</span>
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
            <div class="welcome-section fade-in-up">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="welcome-title">Selamat Datang, <?= h($_SESSION['nama'] ?? 'User'); ?>!</h2>
                        <p class="welcome-subtitle">Kelola kesehatan Anda dengan mudah melalui sistem kami. Akses
                            layanan konsultasi, lihat riwayat, dan kelola profil Anda dengan aman dan nyaman.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-injured welcome-icon floating"></i>
                    </div>
                </div>
            </div>

            <h1 class="page-title fade-in-up">Dashboard Pengguna</h1>

            <div class="row stats-section fade-in-up">
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$total_keluhan_data; ?></div>
                        <div class="stat-label">Total Keluhan</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$keluhan_selesai_data; ?></div>
                        <div class="stat-label">Keluhan Selesai</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$total_dokter_data; ?></div>
                        <div class="stat-label">Dokter Tersedia</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= (int)$progress; ?>%</div>
                        <div class="stat-label">Progress Penyelesaian</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-circle"></i></div>
                        <h5 class="card-title">Profil Saya</h5>
                        <p class="card-text">Kelola informasi pribadi, data kontak, dan ubah password dengan mudah.</p>
                        <a href="profil.php" class="btn-custom btn-success-custom">
                            <i class="fas fa-user-edit me-2"></i>Kelola Profil
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-user-md"></i></div>
                        <h5 class="card-title">Konsultasi Dokter</h5>
                        <p class="card-text">Lihat daftar dokter spesialis & jadwal praktik untuk konsultasi.</p>
                        <a href="dokter.php" class="btn-custom btn-primary-custom">
                            <i class="fas fa-stethoscope me-2"></i>Lihat Dokter
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-file-medical-alt"></i></div>
                        <h5 class="card-title">Riwayat Keluhan</h5>
                        <p class="card-text">Catatan keluhan, status konsultasi, & riwayat pengobatan lengkap.</p>
                        <a href="keluhan.php" class="btn-custom btn-warning-custom">
                            <i class="fas fa-history me-2"></i>Lihat Riwayat
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-calendar-plus"></i></div>
                        <h5 class="card-title">Buat Janji Temu</h5>
                        <p class="card-text">Jadwalkan konsultasi sesuai kebutuhan dan ketersediaan dokter.</p>
                        <a href="janji_temu.php" class="btn-custom btn-primary-custom">
                            <i class="fas fa-calendar-check me-2"></i>Buat Janji
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-pills"></i></div>
                        <h5 class="card-title">Resep Digital</h5>
                        <p class="card-text">Akses resep obat digital & informasi penggunaan yang aman.</p>
                        <a href="resep.php" class="btn-custom btn-success-custom">
                            <i class="fas fa-file-prescription me-2"></i>Lihat Resep
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="dashboard-card p-4 text-center">
                        <div class="card-icon"><i class="fas fa-question-circle"></i></div>
                        <h5 class="card-title">Bantuan & Panduan</h5>
                        <p class="card-text">Panduan penggunaan lengkap & bantuan teknis 24/7.</p>
                        <a href="bantuan.php" class="btn-custom btn-warning-custom">
                            <i class="fas fa-life-ring me-2"></i>Dapatkan Bantuan
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-12 fade-in-up">
                    <div class="dashboard-card p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-clock me-2"></i>
                            Dokter Terbaru Tersedia
                        </h5>
                        <?php if ($recent_dokter): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recent_dokter as $d): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($d['nama']); ?></td>
                                        <td><span class="badge-custom"><?= h($d['spesialis']); ?></span></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="dokter.php">
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
        <div class="container position-relative">
            <p class="mb-0 fw-semibold">© <?= date('Y'); ?> Sistem Informasi Kesehatan | Universitas Bengkulu</p>
            <p class="mt-2 small opacity-90">Dashboard User - Memberikan yang terbaik untuk kesehatan Anda</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dashboard-card').forEach((card, i) => {
            card.style.animationDelay = `${i * 0.1}s`;
            card.classList.add('fade-in-up');
        });

        document.querySelectorAll('.stat-card').forEach((card, i) => {
            card.style.animationDelay = `${i * 0.15}s`;
            card.classList.add('fade-in-up');
        });

        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        document.querySelectorAll('.btn-custom').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.6);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                `;

                this.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
            });
        });

        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    });
    </script>
</body>

</html>