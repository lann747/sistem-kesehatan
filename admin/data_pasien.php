<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Hanya admin yang boleh mengakses
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); 
    exit;
}

// Helper escape HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// CSRF token
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_admin'];

// ---------- Handler: Tambah ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { 
        http_response_code(400); 
        die('Invalid CSRF token.'); 
    }

    $nama   = trim($_POST['nama'] ?? '');
    $umur   = (int)($_POST['umur'] ?? 0);
    $alamat = trim($_POST['alamat'] ?? '');
    $keluhan= trim($_POST['keluhan'] ?? '');

    if ($nama === '' || $alamat === '' || $keluhan === '') {
        $flash_err = 'Semua kolom wajib diisi.';
    } elseif ($umur < 1 || $umur > 120) {
        $flash_err = 'Umur harus antara 1–120.';
    } else {
        $stmt = $conn->prepare("INSERT INTO pasien (nama, umur, alamat, keluhan) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('siss', $nama, $umur, $alamat, $keluhan);
            $stmt->execute(); 
            $stmt->close();
            header('Location: data_pasien.php'); 
            exit;
        } else { 
            $flash_err = 'Gagal menambah data.'; 
        }
    }
}

// ---------- Handler: Update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { 
        http_response_code(400); 
        die('Invalid CSRF token.'); 
    }

    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $umur   = (int)($_POST['umur'] ?? 0);
    $alamat = trim($_POST['alamat'] ?? '');
    $keluhan= trim($_POST['keluhan'] ?? '');

    if ($id <= 0) {
        $flash_err = 'ID tidak valid.';
    } elseif ($nama === '' || $alamat === '' || $keluhan === '') {
        $flash_err = 'Semua kolom wajib diisi.';
    } elseif ($umur < 1 || $umur > 120) {
        $flash_err = 'Umur harus antara 1–120.';
    } else {
        $stmt = $conn->prepare("UPDATE pasien SET nama=?, umur=?, alamat=?, keluhan=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('sissi', $nama, $umur, $alamat, $keluhan, $id);
            $stmt->execute(); 
            $stmt->close();
            header('Location: data_pasien.php'); 
            exit;
        } else { 
            $flash_err = 'Gagal memperbarui data.'; 
        }
    }
}

// ---------- Handler: Hapus (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { 
        http_response_code(400); 
        die('Invalid CSRF token.'); 
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM pasien WHERE id=?");
        if ($stmt) { 
            $stmt->bind_param('i', $id); 
            $stmt->execute(); 
            $stmt->close(); 
        }
    }
    header('Location: data_pasien.php'); 
    exit;
}

// ---------- Statistik & List (dengan pagination) ----------
$total = 0;
if ($res = $conn->query("SELECT COUNT(*) AS total FROM pasien")) {
    $total = (int)($res->fetch_assoc()['total'] ?? 0);
    $res->close();
}

$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$list = [];

$stmt = $conn->prepare("SELECT id, nama, umur, alamat, keluhan FROM pasien ORDER BY id DESC LIMIT ? OFFSET ?");
if ($stmt) {
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $list[] = $r; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil(($total ?: 1)/$per_page));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pasien - Rafflesia Sehat</title>
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
        padding: 12px 24px;
        border-radius: 12px;
        transition: all 0.3s ease;
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
        transition: all 0.3s ease;
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
        transition: all 0.3s ease;
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
        transition: all 0.3s ease;
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
        transition: all 0.3s ease;
    }

    .table-custom tbody tr {
        transition: all 0.3s ease;
    }

    .table-custom tbody tr:hover {
        background: rgba(22, 163, 74, 0.05);
        transform: translateX(5px);
    }

    .keluhan-badge {
        background: #f0fdf4;
        color: #15803d;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        border: 1px solid #bbf7d0;
        font-weight: 500;
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
        border-left: 4px solid #dc2626;
    }

    /* Modal Styles */
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
        transition: all 0.3s ease;
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

        .keluhan-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
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
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat"></i>
                <span>Rafflesia Sehat</span>
            </a>
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span><?= h($_SESSION['nama'] ?? 'Admin'); ?></span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="page-title fade-in-up">
                        <i class="fas fa-user-injured"></i>
                        <span>Data Pasien</span>
                    </h1>
                    <p class="page-subtitle">Kelola data pasien dan keluhan medis</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= (int)$total; ?></div>
                    <div class="stat-label">Total Pasien</div>
                </div>
            </div>

            <?php if (!empty($flash_err)): ?>
            <div class="alert alert-danger alert-custom fade-in-up" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= h($flash_err) ?>
            </div>
            <?php endif; ?>

            <!-- Form Tambah Pasien -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span>Tambah Pasien Baru</span>
                </h5>
                <form method="POST" class="row g-3" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <div class="col-md-3">
                        <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="umur" class="form-control" placeholder="Umur" min="1" max="120"
                            required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="alamat" class="form-control" placeholder="Alamat" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="keluhan" class="form-control" placeholder="Keluhan Medis" required>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" name="tambah" class="btn-success-custom">
                            <i class="fas fa-plus me-1"></i>
                            <span class="d-none d-md-inline">Tambah</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Data Pasien -->
            <div class="card-custom p-4 fade-in-up">
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pasien</th>
                                    <th>Umur</th>
                                    <th>Alamat</th>
                                    <th>Keluhan</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1 + $offset; foreach ($list as $d): ?>
                                <tr class="fade-in-up">
                                    <td class="fw-semibold"><?= $no++; ?></td>
                                    <td class="fw-semibold"><?= h($d['nama']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"
                                            style="background: var(--primary-color) !important;">
                                            <?= (int)$d['umur']; ?> tahun
                                        </span>
                                    </td>
                                    <td><?= h($d['alamat']); ?></td>
                                    <td><span class="keluhan-badge"><?= h($d['keluhan']); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <!-- Tombol Edit -->
                                            <button type="button" class="btn-warning-custom" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= (int)$d['id']; ?>">
                                                <i class="fas fa-edit me-1"></i>
                                                <span>Edit</span>
                                            </button>

                                            <!-- Form Hapus -->
                                            <form method="POST"
                                                onsubmit="return confirm('Yakin hapus data pasien <?= h($d['nama']); ?>?')"
                                                class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$d['id']; ?>">
                                                <button type="submit" name="hapus" class="btn-danger-custom">
                                                    <i class="fas fa-trash me-1"></i>
                                                    <span>Hapus</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($list)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox me-2"></i>
                                        Belum ada data pasien.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($p=1; $p<=$total_pages; $p++): ?>
                        <li class="page-item <?= $p===$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

            <!-- Back Button -->
            <div class="mt-4 fade-in-up">
                <a href="index.php" class="btn-secondary-custom">
                    <i class="fas fa-arrow-left me-2"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Edit - Ditempatkan di luar tabel -->
    <?php foreach ($list as $d): ?>
    <div class="modal fade" id="editModal<?= (int)$d['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>
                            <span>Edit Data Pasien</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)$d['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Pasien</label>
                            <input type="text" name="nama" class="form-control" value="<?= h($d['nama']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Umur</label>
                            <input type="number" name="umur" class="form-control" value="<?= (int)$d['umur']; ?>"
                                min="1" max="120" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <input type="text" name="alamat" class="form-control" value="<?= h($d['alamat']); ?>"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keluhan</label>
                            <input type="text" name="keluhan" class="form-control" value="<?= h($d['keluhan']); ?>"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            Batal
                        </button>
                        <button type="submit" name="update" class="btn-primary-custom">
                            <i class="fas fa-save me-1"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Vendor JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Animasi baris tabel
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('tbody tr').forEach((row, i) => {
            row.style.animationDelay = `${i * 0.05}s`;
        });

        // Validasi input umur
        document.querySelectorAll('input[name="umur"]').forEach(inp => {
            inp.addEventListener('input', function() {
                if (this.value) {
                    if (this.value < 1) this.value = 1;
                    if (this.value > 120) this.value = 120;
                }
            });
        });

        // Efek hover pada kartu
        document.querySelectorAll('.card-custom').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Debug modal
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