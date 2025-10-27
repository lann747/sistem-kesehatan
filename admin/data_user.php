<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_user'])) {
    $_SESSION['csrf_user'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_user'];

$flash = ['success'=>null,'error'=>null];

function count_admins($conn){
    $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='admin'");
    $r = mysqli_fetch_assoc($q);
    return (int)$r['c'];
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tambah'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $flash['error'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman.';
    } else {
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $no_hp    = trim($_POST['no_hp'] ?? '');
        $alamat   = trim($_POST['alamat'] ?? '');
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $password = $_POST['password'] ?? '';

        if ($nama==='' || $email==='' || $password==='') {
            $flash['error'] = 'Nama, email, dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash['error'] = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $flash['error'] = 'Password minimal 6 karakter.';
        } else {
            $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
            mysqli_stmt_bind_param($cek, "s", $email);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            if (mysqli_stmt_num_rows($cek) > 0) {
                $flash['error'] = 'Email sudah terdaftar.';
            } else {
                $hash = md5($password); 
                $stmt = mysqli_prepare($conn, "INSERT INTO users (nama, email, password, no_hp, alamat, role) VALUES (?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, "ssssss", $nama, $email, $hash, $no_hp, $alamat, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $flash['success'] = 'Pengguna berhasil ditambahkan.';
                } else {
                    $flash['error'] = 'Gagal menambahkan pengguna.';
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($cek);
        }
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $flash['error'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman.';
    } else {
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $no_hp    = trim($_POST['no_hp'] ?? '');
        $alamat   = trim($_POST['alamat'] ?? '');
        $role_in  = $_POST['role'] ?? 'user';
        $role     = ($role_in === 'admin') ? 'admin' : 'user';
        $newpass  = $_POST['password_baru'] ?? '';

        if ($id<=0 || $nama==='' || $email==='') {
            $flash['error'] = 'Nama dan email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash['error'] = 'Format email tidak valid.';
        } else {
            $cur = mysqli_prepare($conn, "SELECT role, email FROM users WHERE id=?");
            mysqli_stmt_bind_param($cur, "i", $id);
            mysqli_stmt_execute($cur);
            $res = mysqli_stmt_get_result($cur);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($cur);

            if (!$row) {
                $flash['error'] = 'Pengguna tidak ditemukan.';
            } else {
                $isTargetAdmin = $row['role']==='admin';
                if ($isTargetAdmin && $role !== 'admin' && count_admins($conn) <= 1) {
                    $flash['error'] = 'Tidak bisa menurunkan peran admin terakhir.';
                } else {
                    $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? AND id<>?");
                    mysqli_stmt_bind_param($cek, "si", $email, $id);
                    mysqli_stmt_execute($cek);
                    mysqli_stmt_store_result($cek);
                    if (mysqli_stmt_num_rows($cek) > 0) {
                        $flash['error'] = 'Email tersebut sudah digunakan pengguna lain.';
                    } else {
                        mysqli_stmt_close($cek);

                        if ($newpass !== '') {
                            if (strlen($newpass) < 6) {
                                $flash['error'] = 'Password baru minimal 6 karakter.';
                            } else {
                                $hash = md5($newpass);
                                $stmt = mysqli_prepare($conn, "UPDATE users SET nama=?, email=?, no_hp=?, alamat=?, role=?, password=? WHERE id=?");
                                mysqli_stmt_bind_param($stmt, "ssssssi", $nama, $email, $no_hp, $alamat, $role, $hash, $id);
                                $ok = mysqli_stmt_execute($stmt);
                                mysqli_stmt_close($stmt);
                                $flash[$ok ? 'success' : 'error'] = $ok ? 'Data pengguna dan password diperbarui.' : 'Gagal memperbarui pengguna.';
                            }
                        } else {
                            $stmt = mysqli_prepare($conn, "UPDATE users SET nama=?, email=?, no_hp=?, alamat=?, role=? WHERE id=?");
                            mysqli_stmt_bind_param($stmt, "sssssi", $nama, $email, $no_hp, $alamat, $role, $id);
                            $ok = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            $flash[$ok ? 'success' : 'error'] = $ok ? 'Data pengguna diperbarui.' : 'Gagal memperbarui pengguna.';
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    if ($id === (int)($_SESSION['id'] ?? 0)) {
        $flash['error'] = 'Anda tidak bisa menghapus akun Anda sendiri.';
    } else {
        $cur = mysqli_prepare($conn, "SELECT role FROM users WHERE id=?");
        mysqli_stmt_bind_param($cur, "i", $id);
        mysqli_stmt_execute($cur);
        $res = mysqli_stmt_get_result($cur);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($cur);

        if (!$row) {
            $flash['error'] = 'Pengguna tidak ditemukan.';
        } else {
            if ($row['role']==='admin' && count_admins($conn) <= 1) {
                $flash['error'] = 'Tidak bisa menghapus admin terakhir.';
            } else {
                $del = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
                mysqli_stmt_bind_param($del, "i", $id);
                $ok = mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
                $flash[$ok ? 'success' : 'error'] = $ok ? 'Pengguna berhasil dihapus.' : 'Gagal menghapus pengguna.';
            }
        }
    }
}

$total_users_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$total = (int)mysqli_fetch_assoc($total_users_q)['total'];

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna - Rafflesia Sehat</title>
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
        transition: all 1s ease;
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
        transition: transform 1s ease;
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-heavy);
    }

    .card-custom:hover::before {
        transform: scaleX(1);
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 1rem;
        transition: all 1s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 12px 24px;
        border-radius: 12px;
        transition: all 1s ease;
        box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
    }

    .btn-success-custom {
        background: var(--secondary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 12px 24px;
        border-radius: 12px;
        transition: all 1s ease;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }

    .btn-success-custom:hover {
        background: #0d9669;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-warning-custom {
        background: #f59e0b;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 10px;
        transition: all 1s ease;
        box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
    }

    .btn-danger-custom {
        background: #ef4444;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 10px;
        transition: all 1s ease;
        box-shadow: 0 3px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-danger-custom:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
    }

    .btn-secondary-custom {
        background: #6b7280;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 1s ease;
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
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table-custom tbody td {
        padding: 14px 12px;
        border-color: #f1f5f9;
        vertical-align: middle;
        color: var(--text-dark);
        transition: all 1s ease;
    }

    .table-custom tbody tr {
        transition: all 1s ease;
    }

    .table-custom tbody tr:hover {
        background: rgba(22, 163, 74, 0.05);
        transform: translateX(5px);
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

    .stat-card {
        background: var(--card-light);
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        box-shadow: var(--shadow-light);
        transition: all 1s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
        min-width: 160px;
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

    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 16px 20px;
        font-weight: 500;
        box-shadow: var(--shadow-light);
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #059669;
    }

    .alert-danger {
        background: #fee2e2;
        color: #dc2626;
        border-left: 4px solid #dc2626;
    }

    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: var(--shadow-heavy);
    }

    .modal-header {
        background: var(--primary-color);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-close-white {
        filter: invert(1);
        opacity: 0.8;
    }

    .btn-close-white:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 20px;
        border-radius: 0 0 16px 16px;
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

    .pagination {
        margin: 2rem 0 1rem 0;
        gap: 0.5rem;
    }

    .pagination .page-link {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        margin: 0 2px;
        color: var(--text-dark);
        font-weight: 600;
        padding: 0.75rem 1rem;
        min-width: 3rem;
        text-align: center;
        transition: all 1s ease;
        background: var(--card-light);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .pagination .page-item.active .page-link {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        transform: translateY(-2px);
    }

    .pagination .page-item:not(.active) .page-link:hover {
        background: var(--primary-light);
        border-color: var(--primary-light);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
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

        .btn-warning-custom,
        .btn-danger-custom {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .form-control {
            font-size: 0.95rem;
        }

        .badge-role {
            font-size: 0.8rem;
            padding: 6px 10px;
        }

        .stat-card {
            padding: 20px;
            min-width: 140px;
        }

        .stat-number {
            font-size: 2rem;
        }

        .pagination {
            gap: 0.25rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination .page-link {
            padding: 0.6rem 0.8rem;
            min-width: 2.5rem;
            font-size: 0.9rem;
            margin: 0 1px;
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

        .pagination .page-link {
            padding: 0.5rem 0.7rem;
            min-width: 2.2rem;
            font-size: 0.85rem;
        }

        .pagination {
            margin: 1.5rem 0 0.5rem 0;
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

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="page-title fade-in-up">
                        <i class="fas fa-users-cog"></i>
                        <span>Data Pengguna</span>
                    </h1>
                    <p class="page-subtitle">Kelola akun pengguna, peran (admin/user), dan informasi kontak.</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= $total; ?></div>
                    <div class="stat-label">Total Pengguna</div>
                </div>
            </div>

            <?php if ($flash['success']): ?>
            <div class="alert alert-success alert-custom fade-in-up" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= h($flash['success']); ?>
            </div>
            <?php endif; ?>
            <?php if ($flash['error']): ?>
            <div class="alert alert-danger alert-custom fade-in-up" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= h($flash['error']); ?>
            </div>
            <?php endif; ?>

            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3">
                    <i class="fas fa-user-plus me-2"></i>
                    <span>Tambah Pengguna Baru</span>
                </h5>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                    <div class="col-md-3">
                        <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" name="email" class="form-control" placeholder="email@contoh.com" required>
                    </div>
                    <div class="col-md-2">
                        <input type="password" name="password" class="form-control" placeholder="Password (≥6)"
                            required>
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" name="tambah" class="btn-success-custom">
                            <i class="fas fa-plus me-1"></i>
                            <span>Tambah</span>
                        </button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="no_hp" class="form-control mb-2" placeholder="No. HP (opsional)">
                        <input type="text" name="alamat" class="form-control" placeholder="Alamat (opsional)">
                    </div>
                </form>
            </div>

            <div class="card-custom p-4 fade-in-up">
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>No. HP</th>
                                    <th>Alamat</th>
                                    <th>Role</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; while($u=mysqli_fetch_assoc($users)): ?>
                                <tr class="fade-in-up">
                                    <td class="fw-semibold"><?= $no++; ?></td>
                                    <td class="fw-semibold"><?= h($u['nama']); ?></td>
                                    <td><?= h($u['email']); ?></td>
                                    <td><?= h($u['no_hp']); ?></td>
                                    <td><?= h($u['alamat']); ?></td>
                                    <td>
                                        <?php if ($u['role']==='admin'): ?>
                                        <span class="badge-role badge-admin">Admin</span>
                                        <?php else: ?>
                                        <span class="badge-role badge-user">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <button type="button" class="btn-warning-custom" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $u['id']; ?>">
                                                <i class="fas fa-edit me-1"></i>
                                                <span>Edit</span>
                                            </button>
                                            <a href="?hapus=<?= (int)$u['id']; ?>"
                                                onclick="return confirm('Yakin hapus pengguna <?= h($u['nama']); ?>?')"
                                                class="btn-danger-custom text-decoration-none">
                                                <i class="fas fa-trash me-1"></i>
                                                <span>Hapus</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4 fade-in-up">
                <a href="index.php" class="btn-secondary-custom">
                    <i class="fas fa-arrow-left me-2"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <?php mysqli_data_seek($users, 0); ?>
    <?php while($u=mysqli_fetch_assoc($users)): ?>
    <div class="modal fade" id="editModal<?= $u['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit me-2"></i>
                            <span>Edit Pengguna</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                        <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" name="nama" class="form-control" value="<?= h($u['nama']); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= h($u['email']); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. HP</label>
                                <input type="text" name="no_hp" class="form-control" value="<?= h($u['no_hp']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="user" <?= $u['role']==='user'?'selected':''; ?>>User</option>
                                    <option value="admin" <?= $u['role']==='admin'?'selected':''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <input type="text" name="alamat" class="form-control" value="<?= h($u['alamat']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Password Baru (opsional)</label>
                                <input type="password" name="password_baru" class="form-control"
                                    placeholder="Kosongkan jika tidak mengubah">
                                <small class="text-muted">Minimal 6 karakter. Jika diisi, password akan diganti.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update" class="btn-primary-custom">
                            <i class="fas fa-save me-1"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            <span>Batal</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('tbody tr').forEach((row, i) => {
            row.style.animationDelay = `${i * 0.05}s`;
        });

        document.querySelectorAll('.card-custom').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-bs-target');
                console.log('Modal target:', target);
            });
        });
    });
    </script>
</body>

</html>