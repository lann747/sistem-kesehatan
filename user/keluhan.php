<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['id'];

/* ============================================================
   MIGRASI RINGAN: tambah kolom ke tabel pasien bila belum ada
   (butuh MySQL 8+ untuk IF NOT EXISTS)
   ============================================================ */
@mysqli_query($conn, "ALTER TABLE pasien 
    ADD COLUMN IF NOT EXISTS user_id INT NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('baru','proses','selesai') NOT NULL DEFAULT 'baru',
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

/* ============================================================
   Helper
   ============================================================ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function badge_status($s){
    $map = [
        'baru'   => ['#10b981','Baru'],
        'proses' => ['#f59e0b','Proses'],
        'selesai'=> ['#3b82f6','Selesai'],
    ];
    $x = $map[$s] ?? ['#6b7280',$s];
    return '<span class="badge-status" style="background:'.$x[0].'">'.$x[1].'</span>';
}

/* ============================================================
   Handle: Tambah keluhan (milik user sendiri)
   ============================================================ */
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama   = trim($_POST['nama'] ?? '');
    $umur   = (int)($_POST['umur'] ?? 0);
    $alamat = trim($_POST['alamat'] ?? '');
    $keluhan= trim($_POST['keluhan'] ?? '');

    if ($nama === '' || $umur <= 0 || $alamat === '' || $keluhan === '') {
        $msg_error = 'Semua kolom wajib diisi dengan benar.';
    } elseif ($umur > 120) {
        $msg_error = 'Umur tidak valid (maksimal 120).';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO pasien (nama, umur, alamat, keluhan, user_id, status) VALUES (?,?,?,?,?, 'baru')");
        mysqli_stmt_bind_param($stmt, "sisss", $nama, $umur, $alamat, $keluhan, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $msg_success = 'Keluhan berhasil ditambahkan.';
        } else {
            $msg_error = 'Gagal menyimpan keluhan. Coba lagi.';
        }
        mysqli_stmt_close($stmt);
    }
}

/* ============================================================
   Handle: Update keluhan (milik user sendiri)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $umur   = (int)($_POST['umur'] ?? 0);
    $alamat = trim($_POST['alamat'] ?? '');
    $keluhan= trim($_POST['keluhan'] ?? '');
    $status = trim($_POST['status'] ?? 'baru');

    if ($id <= 0) {
        $msg_error = 'Data tidak valid.';
    } elseif ($nama === '' || $umur <= 0 || $alamat === '' || $keluhan === '') {
        $msg_error = 'Semua kolom wajib diisi dengan benar.';
    } elseif (!in_array($status, ['baru','proses','selesai'], true)) {
        $msg_error = 'Status tidak valid.';
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE pasien SET nama=?, umur=?, alamat=?, keluhan=?, status=? WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "sisssii", $nama, $umur, $alamat, $keluhan, $status, $id, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) >= 0) {
            $msg_success = 'Keluhan berhasil diperbarui.';
        } else {
            $msg_error = 'Gagal memperbarui data.';
        }
        mysqli_stmt_close($stmt);
    }
}

/* ============================================================
   Handle: Hapus keluhan (milik user sendiri)
   ============================================================ */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = mysqli_prepare($conn, "DELETE FROM pasien WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected > 0) {
        $msg_success = 'Keluhan berhasil dihapus.';
    } else {
        $msg_error = 'Gagal menghapus (data tidak ditemukan).';
    }
}

/* ============================================================
   Pencarian & Filter & Pagination
   ============================================================ */
$q       = trim($_GET['q'] ?? '');
$fstatus = trim($_GET['status'] ?? '');
$wheres  = ["user_id = ?"];
$params  = [$user_id];
$ptypes  = "i";

if ($q !== '') {
    $wheres[] = "(nama LIKE CONCAT('%',?,'%') OR keluhan LIKE CONCAT('%',?,'%') OR alamat LIKE CONCAT('%',?,'%'))";
    $params[] = $q; $params[] = $q; $params[] = $q;
    $ptypes  .= "sss";
}
if ($fstatus !== '' && in_array($fstatus, ['baru','proses','selesai'], true)) {
    $wheres[] = "status = ?";
    $params[] = $fstatus;
    $ptypes  .= "s";
}
$whereSql = implode(" AND ", $wheres);

// Pagination
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset   = ($page - 1) * $per_page;

// Hitung total
$sqlCnt = "SELECT COUNT(*) FROM pasien WHERE $whereSql";
$stmtCnt = mysqli_prepare($conn, $sqlCnt);
mysqli_stmt_bind_param($stmtCnt, $ptypes, ...$params);
mysqli_stmt_execute($stmtCnt);
$resCnt = mysqli_stmt_get_result($stmtCnt);
$total  = $resCnt ? (int)mysqli_fetch_row($resCnt)[0] : 0;
mysqli_stmt_close($stmtCnt);
$total_pages = max(1, (int)ceil($total / $per_page));

// Ambil data
$sqlList = "SELECT id, nama, umur, alamat, keluhan, status, created_at, updated_at 
            FROM pasien 
            WHERE $whereSql 
            ORDER BY id DESC 
            LIMIT ? OFFSET ?";
$paramsList = $params;
$ptypesList = $ptypes . "ii";
$paramsList[] = $per_page;
$paramsList[] = $offset;

$stmtList = mysqli_prepare($conn, $sqlList);
mysqli_stmt_bind_param($stmtList, $ptypesList, ...$paramsList);
mysqli_stmt_execute($stmtList);
$resList = mysqli_stmt_get_result($stmtList);
$rows = [];
if ($resList) while ($r = mysqli_fetch_assoc($resList)) $rows[] = $r;
mysqli_stmt_close($stmtList);

/* ============================================================
   Statistik status (untuk user)
   ============================================================ */
function count_status($conn, $user_id, $status=null){
    if ($status === null){
        $st = mysqli_prepare($conn, "SELECT COUNT(*) FROM pasien WHERE user_id=?");
        mysqli_stmt_bind_param($st, "i", $user_id);
    } else {
        $st = mysqli_prepare($conn, "SELECT COUNT(*) FROM pasien WHERE user_id=? AND status=?");
        mysqli_stmt_bind_param($st, "is", $user_id, $status);
    }
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $n = $r ? (int)mysqli_fetch_row($r)[0] : 0;
    mysqli_stmt_close($st);
    return $n;
}
$stat_total   = count_status($conn, $user_id, null);
$stat_baru    = count_status($conn, $user_id, 'baru');
$stat_proses  = count_status($conn, $user_id, 'proses');
$stat_selesai = count_status($conn, $user_id, 'selesai');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Keluhan - Sistem Informasi Kesehatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #10b981;
        --primary-light: #34d399;
        --primary-dark: #059669;
        --secondary-color: #3b82f6;
        --light-bg: #f0fdf4;
        --dark-bg: #0f172a;
        --text-light: #f8fafc;
        --text-dark: #1e293b;
        --card-light: #ffffff;
        --card-dark: #1e293b;
        --navbar-bg: #10b981;
        --table-header: #10b981;
        --success-light: #d1fae5;
        --success-dark: #065f46;
        --danger-light: #fee2e2;
        --danger-dark: #dc2626;
        --border-radius: 16px;
        --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: var(--transition);
        min-height: 100vh;
        line-height: 1.6;
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light);
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 1rem 0;
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
        padding: 8px 18px;
        border-radius: 8px;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, .3);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        transition: var(--transition);
        margin-right: 10px;
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg);
    }

    .main-content {
        padding: 40px 0;
        min-height: calc(100vh - 120px);
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 20px;
        position: relative;
        padding-bottom: 12px;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 140px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        border-left: 4px solid var(--primary-color);
        overflow: hidden;
    }

    .card-custom:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        background: var(--card-light);
        color: var(--text-dark);
        transition: var(--transition);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: 12px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: #fff;
    }

    .btn-success-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: 12px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-success-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: #fff;
    }

    .btn-warning-custom {
        background: #f59e0b;
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-1px);
    }

    .btn-danger-custom {
        background: #ef4444;
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-danger-custom:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .table-custom thead th {
        background: var(--table-header);
        color: #fff;
        border: none;
        font-weight: 600;
        padding: 16px 12px;
    }

    .table-custom tbody td {
        vertical-align: middle;
        padding: 14px 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    .badge-status {
        display: inline-block;
        color: #fff;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 600;
    }

    .badge-chip {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        border-radius: 8px;
        padding: 4px 10px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    body.dark-mode .badge-chip {
        background: #065f46;
        color: #a7f3d0;
        border-color: #10b981;
    }

    .stat-card {
        background: var(--card-light);
        border-radius: var(--border-radius);
        padding: 20px;
        text-align: center;
        box-shadow: var(--box-shadow);
        border-left: 4px solid var(--primary-color);
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6b7280;
        font-size: .9rem;
        font-weight: 500;
    }

    .alert-custom {
        border-radius: var(--border-radius);
        border: none;
        padding: 16px 20px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: var(--success-light);
        color: var(--success-dark);
        border-left: 4px solid var(--success-dark);
    }

    .alert-danger {
        background: var(--danger-light);
        color: var(--danger-dark);
        border-left: 4px solid var(--danger-dark);
    }

    .fade-in-up {
        animation: fadeInUp .6s ease forwards;
    }

    .modal-header-custom {
        background: var(--primary-color);
        color: #fff;
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(18px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

        .btn-warning-custom,
        .btn-danger-custom {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .d-flex.gap-2 {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat"></i>Rafflesia Sehat</a>
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?= h($_SESSION['nama']); ?> (User)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">

            <h1 class="page-title">Riwayat Keluhan</h1>

            <!-- Alerts -->
            <?php if ($msg_success): ?>
            <div class="alert alert-success alert-custom mb-4">
                <i class="fas fa-check-circle me-2"></i><?= h($msg_success); ?>
            </div>
            <?php endif; ?>
            <?php if ($msg_error): ?>
            <div class="alert alert-danger alert-custom mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?= h($msg_error); ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stat_total; ?></div>
                        <div class="stat-label">Total Keluhan</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:#10b981">
                        <div class="stat-number"><?= $stat_baru; ?></div>
                        <div class="stat-label">Status Baru</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:#f59e0b">
                        <div class="stat-number"><?= $stat_proses; ?></div>
                        <div class="stat-label">Sedang Proses</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:#3b82f6">
                        <div class="stat-number"><?= $stat_selesai; ?></div>
                        <div class="stat-label">Selesai</div>
                    </div>
                </div>
            </div>

            <!-- Form Tambah Keluhan -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i> Tambah Keluhan</h5>
                <form method="post" class="row g-3" novalidate>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control" required placeholder="Nama pasien">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Umur</label>
                        <input type="number" name="umur" class="form-control" required min="1" max="120"
                            placeholder="Umur">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Alamat</label>
                        <input type="text" name="alamat" class="form-control" required placeholder="Alamat lengkap">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Keluhan</label>
                        <input type="text" name="keluhan" class="form-control" required placeholder="Keluhan medis">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" name="tambah" class="btn btn-success-custom">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>

            <!-- Filter & Search -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <form class="row g-3" method="get">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cari Keluhan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0"
                                placeholder="Cari nama / alamat / keluhan" value="<?= h($q); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Filter Status</label>
                        <select name="status" class="form-select">
                            <option value="">— Semua Status —</option>
                            <option value="baru" <?= $fstatus==='baru'?'selected':''; ?>>Baru</option>
                            <option value="proses" <?= $fstatus==='proses'?'selected':''; ?>>Proses</option>
                            <option value="selesai" <?= $fstatus==='selesai'?'selected':''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary-custom w-100">
                            <i class="fas fa-filter me-1"></i> Terapkan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Keluhan -->
            <div class="card-custom p-4 fade-in-up">
                <?php if ($total === 0): ?>
                <div class="alert alert-info alert-custom">
                    <i class="fas fa-info-circle me-2"></i>Tidak ada data keluhan yang ditemukan.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th style="width:46px;">#</th>
                                <th>Nama</th>
                                <th>Umur</th>
                                <th>Alamat</th>
                                <th>Keluhan</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th style="width:170px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach ($rows as $d): ?>
                            <tr>
                                <td class="fw-semibold"><?= $no++; ?></td>
                                <td class="fw-semibold"><?= h($d['nama']); ?></td>
                                <td><span class="badge-chip"><?= (int)$d['umur']; ?> th</span></td>
                                <td><?= h($d['alamat']); ?></td>
                                <td><?= h($d['keluhan']); ?></td>
                                <td><?= badge_status($d['status']); ?></td>
                                <td class="small text-muted">
                                    <?= $d['created_at'] ? date('d M Y H:i', strtotime($d['created_at'])) : '-'; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-warning-custom btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#editModal"
                                            data-item='<?= h(json_encode($d), ENT_QUOTES); ?>'>
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <a class="btn btn-danger-custom btn-sm" href="?hapus=<?= (int)$d['id']; ?>"
                                            onclick="return confirm('Hapus keluhan ini?');">
                                            <i class="fas fa-trash me-1"></i>Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4 d-flex justify-content-center">
                    <ul class="pagination">
                        <?php
                    $build = function($p){
                        $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs);
                    };
                    $prev = max(1, $page-1); $next = min($total_pages, $page+1);
                    ?>
                        <li class="page-item <?= $page<=1?'disabled':''; ?>"><a class="page-link"
                                href="<?= $page<=1?'#':$build($prev); ?>">&laquo;</a></li>
                        <?php
                    $start = max(1, $page-2);
                    $end   = min($total_pages, $page+2);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.$build(1).'">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($i=$start; $i<=$end; $i++) {
                        $active = $i==$page ? 'active' : '';
                        echo '<li class="page-item '.$active.'"><a class="page-link" href="'.$build($i).'">'.$i.'</a></li>';
                    }
                    if ($end < $total_pages) {
                        if ($end < $total_pages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="'.$build($total_pages).'">'.$total_pages.'</a></li>';
                    }
                    ?>
                        <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>"><a class="page-link"
                                href="<?= $page>=$total_pages?'#':$build($next); ?>">&raquo;</a></li>
                    </ul>
                </nav>
                <p class="text-center small text-muted mt-2">
                    Menampilkan <?= min($per_page, max(0, $total - $offset)); ?> dari <?= $total; ?> data.
                </p>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header modal-header-custom">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Keluhan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="f_id">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="f_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Umur</label>
                            <input type="number" name="umur" id="f_umur" class="form-control" min="1" max="120"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Alamat</label>
                            <input type="text" name="alamat" id="f_alamat" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Keluhan</label>
                            <input type="text" name="keluhan" id="f_keluhan" class="form-control" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="f_status" class="form-select">
                                <option value="baru">Baru</option>
                                <option value="proses">Proses</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>
                        <small class="text-muted">Ubah status untuk menandai progress penanganan.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update" class="btn btn-primary-custom">
                            <i class="fas fa-save me-1"></i>Simpan
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Prefill modal edit
    const editModal = document.getElementById('editModal');
    editModal?.addEventListener('show.bs.modal', (ev) => {
        const btn = ev.relatedTarget;
        try {
            const item = JSON.parse(btn.getAttribute('data-item'));
            document.getElementById('f_id').value = item.id || '';
            document.getElementById('f_nama').value = item.nama || '';
            document.getElementById('f_umur').value = item.umur || '';
            document.getElementById('f_alamat').value = item.alamat || '';
            document.getElementById('f_keluhan').value = item.keluhan || '';
            document.getElementById('f_status').value = item.status || 'baru';
        } catch (e) {
            // noop
        }
    });

    // Validasi cepat untuk umur input di halaman
    document.addEventListener('input', function(e) {
        if (e.target && e.target.name === 'umur') {
            const v = parseInt(e.target.value || '0', 10);
            if (v < 1) e.target.value = 1;
            if (v > 120) e.target.value = 120;
        }
    });
    </script>
</body>

</html>