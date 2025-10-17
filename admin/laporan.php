<?php
session_start();
include '../config/db.php';

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function fetch_all($res){
    $rows = [];
    while($r = mysqli_fetch_assoc($res)){ $rows[] = $r; }
    return $rows;
}

// -------------------------------
// EXPORT CSV ?export=pasien|dokter|users
// -------------------------------
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

// -------------------------------
// Data Ringkasan
// -------------------------------
$total_pasien = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM pasien"))['c'];
$total_dokter = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM dokter"))['c'];
$total_users  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_admins = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='admin'"))['c'];

// -------------------------------
// Agregasi Kelompok Umur Pasien
// -------------------------------
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

// -------------------------------
// Agregasi Spesialis Dokter
// -------------------------------
$spes = fetch_all(mysqli_query(
    $conn,
    "SELECT spesialis, COUNT(*) AS jumlah FROM dokter GROUP BY spesialis ORDER BY jumlah DESC, spesialis ASC"
));
$spes_labels = array_map(fn($r)=>$r['spesialis']!==''?$r['spesialis']:'(Tidak diisi)', $spes);
$spes_counts = array_map(fn($r)=>(int)$r['jumlah'], $spes);

// -------------------------------
// Keluhan Teratas (Top 10)
// -------------------------------
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

// -------------------------------
// Distribusi Role User
// -------------------------------
$roles = fetch_all(mysqli_query(
    $conn,
    "SELECT role, COUNT(*) AS jumlah FROM users GROUP BY role ORDER BY role"
));
$role_labels = array_map(fn($r)=>$r['role'], $roles);
$role_counts = array_map(fn($r)=>(int)$r['jumlah'], $roles);

// -------------------------------
// Tabel ringkas (preview)
// -------------------------------
$preview_pasien = fetch_all(mysqli_query($conn, "SELECT * FROM pasien ORDER BY id DESC LIMIT 8"));
$preview_dokter = fetch_all(mysqli_query($conn, "SELECT * FROM dokter ORDER BY id DESC LIMIT 8"));
$preview_users  = fetch_all(mysqli_query($conn, "SELECT id,nama,email,role,no_hp,alamat FROM users ORDER BY id DESC LIMIT 8"));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Sistem Informasi Kesehatan</title>
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
        --table-header: #3b82f6;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: .3s;
        min-height: 100vh;
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light);
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 2px 15px rgba(0, 0, 0, .1);
        padding: 1rem 0;
    }

    body.dark-mode .navbar {
        background: var(--dark-bg);
        border-bottom-color: var(--primary-color);
    }

    .navbar-brand {
        font-weight: 700;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
    }

    .user-info {
        color: #fff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-logout {
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .3);
        color: #fff;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: .3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, .3);
        transform: translateY(-1px);
    }

    .main-content {
        padding: 30px 0;
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-subtitle {
        color: #6b7280;
        margin-bottom: 20px;
    }

    body.dark-mode .page-subtitle {
        color: #9ca3af;
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: .3s;
        border-left: 4px solid var(--primary-color);
    }

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
    }

    .card-custom:hover {
        transform: translateY(-4px);
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        border-left: 4px solid var(--secondary-color);
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2);
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 4px;
    }

    .stat-label {
        color: #6b7280;
        font-size: .9rem;
        font-weight: 500;
    }

    .table-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
    }

    .table-custom thead th {
        background: var(--table-header);
        color: #fff;
        border: none;
    }

    .badge-role {
        padding: 4px 8px;
        border-radius: 8px;
        font-weight: 600;
    }

    .badge-admin {
        background: #1e40af;
        color: #bfdbfe;
        border: 1px solid #1d4ed8;
    }

    .badge-user {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .btn-chip {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 9999px;
        padding: 6px 12px;
        font-weight: 600;
    }

    body.dark-mode .btn-chip {
        border-color: #334155;
        background: #0b1220;
        color: #93c5fd;
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
        transition: .3s;
        margin-right: 10px;
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg);
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
        animation: fadeInUp .6s ease forwards;
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
                <div class="user-info"><i class="fas fa-user-shield"></i><span><?= h($_SESSION['nama']); ?></span></div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="page-title fade-in-up"><i class="fas fa-chart-bar"></i> Laporan</h1>
                    <p class="page-subtitle">Ringkasan statistik, rekap, dan visualisasi data.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-chip" href="?export=pasien" title="Export CSV Pasien"><i
                            class="fas fa-file-export me-2"></i>Export Pasien</a>
                    <a class="btn btn-chip" href="?export=dokter" title="Export CSV Dokter"><i
                            class="fas fa-file-export me-2"></i>Export Dokter</a>
                    <a class="btn btn-chip" href="?export=users" title="Export CSV Users"><i
                            class="fas fa-file-export me-2"></i>Export Users</a>
                </div>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4 fade-in-up">
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

            <!-- Filter Keluhan -->
            <div class="card-custom p-3 mb-4 fade-in-up">
                <form class="row g-2 align-items-center">
                    <div class="col-sm-6 col-md-4">
                        <label class="form-label mb-1">Cari Keluhan</label>
                        <input type="text" class="form-control" name="q" placeholder="mis. demam, batuk"
                            value="<?= h($keyword); ?>">
                    </div>
                    <div class="col-sm-6 col-md-4 d-flex align-items-end gap-2">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>
                            Terapkan</button>
                        <a class="btn btn-secondary" href="laporan.php"><i class="fas fa-undo me-1"></i> Reset</a>
                    </div>
                    <!-- TODO: filter tanggal jika field ada (created_at) -->
                </form>
            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-3">
                        <h6 class="mb-3"><i class="fas fa-child me-2"></i>Distribusi Umur Pasien</h6>
                        <canvas id="chartUmur" height="160"></canvas>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-3">
                        <h6 class="mb-3"><i class="fas fa-stethoscope me-2"></i>Dokter per Spesialis</h6>
                        <canvas id="chartSpesialis" height="160"></canvas>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-3">
                        <h6 class="mb-3"><i class="fas fa-bell me-2"></i>Top Keluhan (10
                            Teratas<?= $keyword ? " - filter: ".h($keyword) : "";?>)</h6>
                        <canvas id="chartKeluhan" height="160"></canvas>
                    </div>
                </div>
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-3">
                        <h6 class="mb-3"><i class="fas fa-users me-2"></i>Distribusi Role Pengguna</h6>
                        <canvas id="chartRole" height="160"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabel Preview -->
            <div class="row g-4">
                <div class="col-lg-6 fade-in-up">
                    <div class="card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-user-injured me-2"></i>Preview Pasien</h6>
                            <a class="btn btn-sm btn-chip" href="?export=pasien"><i
                                    class="fas fa-download me-1"></i>CSV</a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-striped table-custom mb-0">
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
                                            <td colspan="4" class="text-center text-muted">Belum ada data.</td>
                                        </tr>
                                        <?php else: foreach($preview_pasien as $p): ?>
                                        <tr>
                                            <td><?= (int)$p['id']; ?></td>
                                            <td class="fw-semibold"><?= h($p['nama']); ?></td>
                                            <td><span class="badge bg-primary"><?= (int)$p['umur']; ?> th</span></td>
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
                    <div class="card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>Preview Dokter</h6>
                            <a class="btn btn-sm btn-chip" href="?export=dokter"><i
                                    class="fas fa-download me-1"></i>CSV</a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-striped table-custom mb-0">
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
                                            <td colspan="4" class="text-center text-muted">Belum ada data.</td>
                                        </tr>
                                        <?php else: foreach($preview_dokter as $d): ?>
                                        <tr>
                                            <td><?= (int)$d['id']; ?></td>
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
                    <div class="card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Preview Pengguna</h6>
                            <a class="btn btn-sm btn-chip" href="?export=users"><i
                                    class="fas fa-download me-1"></i>CSV</a>
                        </div>
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-striped table-custom mb-0">
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
                                            <td colspan="6" class="text-center text-muted">Belum ada data.</td>
                                        </tr>
                                        <?php else: foreach($preview_users as $u): ?>
                                        <tr>
                                            <td><?= (int)$u['id']; ?></td>
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

                <!-- Back -->
                <div class="col-12 fade-in-up">
                    <a href="index.php" class="btn btn-secondary mt-2"><i class="fas fa-arrow-left me-2"></i>Kembali ke
                        Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    // Theme toggle
    const toggleBtn = document.getElementById('themeToggle');
    const body = document.body;
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

    // Data dari PHP
    const umurLabels = <?= json_encode($umur_labels, JSON_UNESCAPED_UNICODE); ?>;
    const umurCounts = <?= json_encode($umur_counts, JSON_UNESCAPED_UNICODE); ?>;

    const spesLabels = <?= json_encode($spes_labels, JSON_UNESCAPED_UNICODE); ?>;
    const spesCounts = <?= json_encode($spes_counts, JSON_UNESCAPED_UNICODE); ?>;

    const keluhanLabels = <?= json_encode($keluhan_labels, JSON_UNESCAPED_UNICODE); ?>;
    const keluhanCounts = <?= json_encode($keluhan_counts, JSON_UNESCAPED_UNICODE); ?>;

    const roleLabels = <?= json_encode($role_labels, JSON_UNESCAPED_UNICODE); ?>;
    const roleCounts = <?= json_encode($role_counts, JSON_UNESCAPED_UNICODE); ?>;

    // Chart Umur (Bar)
    new Chart(document.getElementById('chartUmur'), {
        type: 'bar',
        data: {
            labels: umurLabels,
            datasets: [{
                label: 'Jumlah Pasien',
                data: umurCounts
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Chart Spesialis (Bar Horizontal bila label banyak)
    new Chart(document.getElementById('chartSpesialis'), {
        type: 'bar',
        data: {
            labels: spesLabels,
            datasets: [{
                label: 'Jumlah Dokter',
                data: spesCounts
            }]
        },
        options: {
            responsive: true,
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

    // Chart Keluhan (Bar)
    new Chart(document.getElementById('chartKeluhan'), {
        type: 'bar',
        data: {
            labels: keluhanLabels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: keluhanCounts
            }]
        },
        options: {
            responsive: true,
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

    // Chart Role (Pie)
    new Chart(document.getElementById('chartRole'), {
        type: 'pie',
        data: {
            labels: roleLabels,
            datasets: [{
                data: roleCounts
            }]
        },
        options: {
            responsive: true
        }
    });
    </script>
</body>

</html>