<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetch_all($res){
    $rows = [];
    while($r = mysqli_fetch_assoc($res)){ $rows[] = $r; }
    return $rows;
}

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $filename = "export_" . $type . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");
    $output = fopen('php://output', 'w');

    if ($type === 'pasien') {
        fputcsv($output, ['id','nama','umur','alamat','keluhan']);
        $res = mysqli_query($conn, "SELECT * FROM pasien ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, [$row['id'],$row['nama'],$row['umur'],$row['alamat'],$row['keluhan']]);
        }
    } elseif ($type === 'dokter') {
        fputcsv($output, ['id','nama','spesialis','no_hp','alamat']);
        $res = mysqli_query($conn, "SELECT * FROM dokter ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, [$row['id'],$row['nama'],$row['spesialis'],$row['no_hp'],$row['alamat']]);
        }
    } elseif ($type === 'users') {
        fputcsv($output, ['id','nama','email','no_hp','alamat','role']);
        $res = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, [$row['id'],$row['nama'],$row['email'],$row['no_hp'],$row['alamat'],$row['role']]);
        }
    } else {
        fputcsv($output, ['pesan']);
        fputcsv($output, ['Tipe export tidak dikenali.']);
    }
    fclose($output);
    exit;
}

$total_pasien = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM pasien"))['c'];
$total_dokter = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM dokter"))['c'];
$total_users  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_admins = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='admin'"))['c'];

$age_groups = [
    '0-12'  => "umur BETWEEN 0 AND 12",
    '13-17' => "umur BETWEEN 13 AND 17",
    '18-35' => "umur BETWEEN 18 AND 35",
    '36-55' => "umur BETWEEN 36 AND 55",
    '56+'   => "umur >= 56"
];
$umur_labels = array_keys($age_groups);
$umur_counts = [];
foreach ($age_groups as $label=>$cond) {
    $umur_counts[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM pasien WHERE $cond"))['c'];
}

$spes = fetch_all(mysqli_query(
    $conn,
    "SELECT spesialis, COUNT(*) AS jumlah FROM dokter GROUP BY spesialis ORDER BY jumlah DESC, spesialis ASC"
));
$spes_labels = array_map(fn($r)=>$r['spesialis']!==''?$r['spesialis']:'(Tidak diisi)', $spes);
$spes_counts = array_map(fn($r)=>(int)$r['jumlah'], $spes);

$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $stmt = mysqli_prepare($conn, "SELECT keluhan, COUNT(*) AS jumlah FROM pasien WHERE keluhan LIKE CONCAT('%',?,'%') GROUP BY keluhan ORDER BY jumlah DESC, keluhan ASC LIMIT 10");
    mysqli_stmt_bind_param($stmt, "s", $keyword);
    mysqli_stmt_execute($stmt);
    $keluhan_res = mysqli_stmt_get_result($stmt);
    $keluhan = fetch_all($keluhan_res);
    mysqli_stmt_close($stmt);
} else {
    $keluhan = fetch_all(mysqli_query(
        $conn,
        "SELECT keluhan, COUNT(*) AS jumlah FROM pasien GROUP BY keluhan ORDER BY jumlah DESC, keluhan ASC LIMIT 10"
    ));
}
$keluhan_labels = array_map(fn($r)=>$r['keluhan']!==''?$r['keluhan']:'(Tidak diisi)', $keluhan);
$keluhan_counts = array_map(fn($r)=>(int)$r['jumlah'], $keluhan);

$roles = fetch_all(mysqli_query(
    $conn,
    "SELECT role, COUNT(*) AS jumlah FROM users GROUP BY role ORDER BY role"
));
$role_labels = array_map(fn($r)=>$r['role'], $roles);
$role_counts = array_map(fn($r)=>(int)$r['jumlah'], $roles);

$preview_pasien = fetch_all(mysqli_query($conn, "SELECT * FROM pasien ORDER BY id DESC LIMIT 8"));
$preview_dokter = fetch_all(mysqli_query($conn, "SELECT * FROM dokter ORDER BY id DESC LIMIT 8"));
$preview_users  = fetch_all(mysqli_query($conn, "SELECT id,nama,email,role,no_hp,alamat FROM users ORDER BY id DESC LIMIT 8"));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Rafflesia Sehat</title>
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
        --navbar-bg: #16a34a;
        --table-header: #16a34a;
        --shadow-light: 0 10px 25px rgba(0, 0, 0, 0.05);
        --shadow-medium: 0 15px 35px rgba(0, 0, 0, 0.1);
        --shadow-heavy: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: all 0.3s ease;
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
        transition: all 0.3s ease;
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
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 2rem;
    }

    .page-subtitle {
        color: #6b7280;
        margin-bottom: 30px;
        font-size: 1.1rem;
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 16px;
        box-shadow: var(--shadow-light);
        transition: all 0.3s ease;
        border-left: 5px solid var(--primary-color);
        overflow: hidden;
        position: relative;
    }

    .card-custom::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-heavy);
    }

    .card-custom:hover::before {
        transform: scaleX(1);
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        box-shadow: var(--shadow-light);
        transition: all 0.3s ease;
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
        background: var(--secondary-color);
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

    .table-container {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-light);
    }

    .table-custom {
        margin: 0;
        background: var(--card-light);
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-custom thead th {
        background: var(--table-header);
        color: #fff;
        font-weight: 600;
        padding: 16px 12px;
        border: none;
        font-size: 0.95rem;
    }

    .table-custom tbody td {
        padding: 14px 12px;
        border-color: #f1f5f9;
        vertical-align: middle;
        color: var(--text-dark);
        transition: all 0.3s ease;
    }

    .table-custom tbody tr {
        transition: all 0.3s ease;
    }

    .table-custom tbody tr:hover {
        background: rgba(22, 163, 74, 0.05);
    }

    .badge-role {
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 1px solid;
    }

    .badge-admin {
        background: #1e40af;
        color: #bfdbfe;
        border-color: #1d4ed8;
    }

    .badge-user {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .btn-export {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 3px 8px rgba(22, 163, 74, 0.3);
    }

    .btn-export:hover {
        background: var(--primary-dark);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(22, 163, 74, 0.4);
    }

    .btn-secondary-custom {
        background: #6b7280;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 3px 8px rgba(107, 114, 128, 0.3);
    }

    .btn-secondary-custom:hover {
        background: #4b5563;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(107, 114, 128, 0.4);
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
    }

    .chart-container {
        position: relative;
        height: 200px;
        width: 100%;
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

    .export-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 25px 0;
        }

        .page-title {
            font-size: 1.6rem;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-number {
            font-size: 2rem;
        }

        .export-buttons {
            flex-direction: column;
        }

        .btn-export {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand {
            font-size: 1.2rem;
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
            font-size: 1.4rem;
        }

        .main-content {
            padding: 20px 0;
        }

        .card-custom {
            padding: 20px !important;
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
                    <span><?= h($_SESSION['nama']); ?></span>
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
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h1 class="page-title fade-in-up">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan & Analitik</span>
                    </h1>
                    <p class="page-subtitle">Ringkasan statistik, rekap, dan visualisasi data kesehatan</p>
                </div>
                <div class="export-buttons fade-in-up">
                    <a class="btn-export" href="?export=pasien" title="Export CSV Pasien">
                        <i class="fas fa-file-export me-1"></i>Export Pasien
                    </a>
                    <a class="btn-export" href="?export=dokter" title="Export CSV Dokter">
                        <i class="fas fa-file-export me-1"></i>Export Dokter
                    </a>
                    <a class="btn-export" href="?export=users" title="Export CSV Users">
                        <i class="fas fa-file-export me-1"></i>Export Users
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4 fade-in-up">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_pasien; ?></div>
                        <div class="stat-label">Total Pasien</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_dokter; ?></div>
                        <div class="stat-label">Total Dokter</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_users; ?></div>
                        <div class="stat-label">Total Pengguna</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_admins; ?></div>
                        <div class="stat-label">Total Admin</div>
                    </div>
                </div>
            </div>

            <div class="card-custom p-4 mb-4 fade-in-up">
                <h6 class="mb-3">
                    <i class="fas fa-filter me-2"></i>
                    <span>Filter Data Keluhan</span>
                </h6>
                <form class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label">Cari Keluhan</label>
                        <input type="text" class="form-control" name="q" placeholder="mis. demam, batuk, sakit kepala"
                            value="<?= h($keyword); ?>">
                    </div>
                    <div class="col-md-6 col-lg-4 d-flex gap-2">
                        <button class="btn-primary-custom" type="submit">
                            <i class="fas fa-search me-1"></i> Terapkan
                        </button>
                        <a class="btn-secondary-custom" href="laporan.php">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <h6 class="mb-3">
                            <i class="fas fa-child me-2"></i>
                            <span>Distribusi Umur Pasien</span>
                        </h6>
                        <div class="chart-container">
                            <canvas id="chartUmur"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <h6 class="mb-3">
                            <i class="fas fa-stethoscope me-2"></i>
                            <span>Dokter per Spesialis</span>
                        </h6>
                        <div class="chart-container">
                            <canvas id="chartSpesialis"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <h6 class="mb-3">
                            <i class="fas fa-bell me-2"></i>
                            <span>Top Keluhan (10 Teratas<?= $keyword ? " - filter: ".h($keyword) : "";?>)</span>
                        </h6>
                        <div class="chart-container">
                            <canvas id="chartKeluhan"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <h6 class="mb-3">
                            <i class="fas fa-users me-2"></i>
                            <span>Distribusi Role Pengguna</span>
                        </h6>
                        <div class="chart-container">
                            <canvas id="chartRole"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-user-injured me-2"></i>
                                <span>Preview Pasien</span>
                            </h6>
                            <a class="btn-export" href="?export=pasien">
                                <i class="fas fa-download me-1"></i>CSV
                            </a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Umur</th>
                                            <th>Keluhan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$preview_pasien): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox me-2"></i>Belum ada data.
                                            </td>
                                        </tr>
                                        <?php else: foreach($preview_pasien as $p): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= (int)$p['id']; ?></td>
                                            <td class="fw-semibold"><?= h($p['nama']); ?></td>
                                            <td><span class="badge bg-primary"
                                                    style="background: var(--primary-color) !important;"><?= (int)$p['umur']; ?>
                                                    th</span></td>
                                            <td><?= h($p['keluhan']); ?></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-user-md me-2"></i>
                                <span>Preview Dokter</span>
                            </h6>
                            <a class="btn-export" href="?export=dokter">
                                <i class="fas fa-download me-1"></i>CSV
                            </a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Spesialis</th>
                                            <th>No. HP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$preview_dokter): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox me-2"></i>Belum ada data.
                                            </td>
                                        </tr>
                                        <?php else: foreach($preview_dokter as $d): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= (int)$d['id']; ?></td>
                                            <td class="fw-semibold"><?= h($d['nama']); ?></td>
                                            <td><span class="badge bg-info text-dark"><?= h($d['spesialis']); ?></span>
                                            </td>
                                            <td><?= h($d['no_hp']); ?></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 fade-in-up">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                <span>Preview Pengguna</span>
                            </h6>
                            <a class="btn-export" href="?export=users">
                                <i class="fas fa-download me-1"></i>CSV
                            </a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>No. HP</th>
                                            <th>Alamat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$preview_users): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox me-2"></i>Belum ada data.
                                            </td>
                                        </tr>
                                        <?php else: foreach($preview_users as $u): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= (int)$u['id']; ?></td>
                                            <td class="fw-semibold"><?= h($u['nama']); ?></td>
                                            <td><?= h($u['email']); ?></td>
                                            <td>
                                                <?php if ($u['role']==='admin'): ?>
                                                <span class="badge-role badge-admin">Admin</span>
                                                <?php else: ?>
                                                <span class="badge-role badge-user">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= h($u['no_hp']); ?></td>
                                            <td><?= h($u['alamat']); ?></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 fade-in-up">
                    <a href="index.php" class="btn-secondary-custom mt-2">
                        <i class="fas fa-arrow-left me-2"></i>
                        <span>Kembali ke Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    const umurLabels = <?= json_encode($umur_labels, JSON_UNESCAPED_UNICODE); ?>;
    const umurCounts = <?= json_encode($umur_counts, JSON_UNESCAPED_UNICODE); ?>;

    const spesLabels = <?= json_encode($spes_labels, JSON_UNESCAPED_UNICODE); ?>;
    const spesCounts = <?= json_encode($spes_counts, JSON_UNESCAPED_UNICODE); ?>;

    const keluhanLabels = <?= json_encode($keluhan_labels, JSON_UNESCAPED_UNICODE); ?>;
    const keluhanCounts = <?= json_encode($keluhan_counts, JSON_UNESCAPED_UNICODE); ?>;

    const roleLabels = <?= json_encode($role_labels, JSON_UNESCAPED_UNICODE); ?>;
    const roleCounts = <?= json_encode($role_counts, JSON_UNESCAPED_UNICODE); ?>;

    const chartColors = {
        primary: '#16a34a',
        secondary: '#10b981',
        accent: '#f59e0b',
        danger: '#ef4444',
        info: '#3b82f6'
    };

    new Chart(document.getElementById('chartUmur'), {
        type: 'bar',
        data: {
            labels: umurLabels,
            datasets: [{
                label: 'Jumlah Pasien',
                data: umurCounts,
                backgroundColor: chartColors.primary,
                borderColor: chartColors.primary,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('chartSpesialis'), {
        type: 'bar',
        data: {
            labels: spesLabels,
            datasets: [{
                label: 'Jumlah Dokter',
                data: spesCounts,
                backgroundColor: chartColors.secondary,
                borderColor: chartColors.secondary,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: spesLabels.length > 6 ? 'y' : 'x',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('chartKeluhan'), {
        type: 'bar',
        data: {
            labels: keluhanLabels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: keluhanCounts,
                backgroundColor: chartColors.accent,
                borderColor: chartColors.accent,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} kasus`
                    }
                }
            },
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('chartRole'), {
        type: 'pie',
        data: {
            labels: roleLabels,
            datasets: [{
                data: roleCounts,
                backgroundColor: [chartColors.primary, chartColors.info],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.card-custom').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
</body>

</html>