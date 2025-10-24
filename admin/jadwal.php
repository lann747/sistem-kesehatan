<?php
session_start();
include '../config/db.php';

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Helper ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- CSRF ---
if (empty($_SESSION['csrf_jadwal'])) {
    $_SESSION['csrf_jadwal'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_jadwal'];

$flash = ['success'=>null,'error'=>null];

// --- Pastikan tabel jadwal ada ---
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS jadwal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dokter_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    kuota INT DEFAULT 0,
    keterangan VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_jadwal_dokter FOREIGN KEY (dokter_id) REFERENCES dokter(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// --- Ambil daftar dokter untuk dropdown ---
$dokter_res = mysqli_query($conn, "SELECT id, nama, spesialis FROM dokter ORDER BY nama ASC");
$dokter_opts = [];
while ($r = mysqli_fetch_assoc($dokter_res)) { $dokter_opts[] = $r; }

// --- Tambah Jadwal ---
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tambah'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $flash['error'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman.';
    } else {
        $dokter_id   = (int)($_POST['dokter_id'] ?? 0);
        $tanggal     = $_POST['tanggal'] ?? '';
        $jam_mulai   = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        $kuota       = max(0, (int)($_POST['kuota'] ?? 0));
        $keterangan  = trim($_POST['keterangan'] ?? '');

        // Validasi
        $err = '';
        if ($dokter_id <= 0 || $tanggal === '' || $jam_mulai === '' || $jam_selesai === '') {
            $err = 'Lengkapi semua field wajib.';
        } elseif (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
            $err = 'Jam mulai harus lebih awal dari jam selesai.';
        }

        if ($err === '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO jadwal (dokter_id, tanggal, jam_mulai, jam_selesai, kuota, keterangan) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "isssis", $dokter_id, $tanggal, $jam_mulai, $jam_selesai, $kuota, $keterangan);
            if (mysqli_stmt_execute($stmt)) {
                $flash['success'] = 'Jadwal berhasil ditambahkan.';
            } else {
                $flash['error'] = 'Gagal menambahkan jadwal.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $flash['error'] = $err;
        }
    }
}

// --- Update Jadwal ---
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $flash['error'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman.';
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $dokter_id   = (int)($_POST['dokter_id'] ?? 0);
        $tanggal     = $_POST['tanggal'] ?? '';
        $jam_mulai   = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        $kuota       = max(0, (int)($_POST['kuota'] ?? 0));
        $keterangan  = trim($_POST['keterangan'] ?? '');

        $err = '';
        if ($id <= 0 || $dokter_id <= 0 || $tanggal === '' || $jam_mulai === '' || $jam_selesai === '') {
            $err = 'Lengkapi semua field wajib.';
        } elseif (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
            $err = 'Jam mulai harus lebih awal dari jam selesai.';
        }

        if ($err === '') {
            $stmt = mysqli_prepare($conn, "UPDATE jadwal SET dokter_id=?, tanggal=?, jam_mulai=?, jam_selesai=?, kuota=?, keterangan=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "isssisi", $dokter_id, $tanggal, $jam_mulai, $jam_selesai, $kuota, $keterangan, $id);
            if (mysqli_stmt_execute($stmt)) {
                $flash['success'] = 'Jadwal berhasil diperbarui.';
            } else {
                $flash['error'] = 'Gagal memperbarui jadwal.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $flash['error'] = $err;
        }
    }
}

// --- Hapus Jadwal ---
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    $del = mysqli_prepare($conn, "DELETE FROM jadwal WHERE id=?");
    mysqli_stmt_bind_param($del, "i", $id);
    $ok = mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    
    $flash[$ok ? 'success' : 'error'] = $ok ? 'Jadwal berhasil dihapus.' : 'Gagal menghapus jadwal.';
}

// --- Filter & Data Tabel ---
$filter_dokter = isset($_GET['dokter']) ? (int)$_GET['dokter'] : 0;
$filter_tanggal = trim($_GET['tanggal'] ?? '');

$where = [];
if ($filter_dokter > 0) $where[] = "j.dokter_id = $filter_dokter";
if ($filter_tanggal !== '') {
    $safe_tgl = mysqli_real_escape_string($conn, $filter_tanggal);
    $where[] = "j.tanggal = '$safe_tgl'";
}
$sql_where = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$q = "
SELECT j.*, d.nama AS nama_dokter, d.spesialis
FROM jadwal j
JOIN dokter d ON d.id = j.dokter_id
$sql_where
ORDER BY j.tanggal DESC, j.jam_mulai ASC
";
$jadwal_res = mysqli_query($conn, $q);

// Hitung total jadwal
$total_jadwal = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM jadwal"))['c'];

// Simpan data untuk modal
$jadwal_data = [];
while($j = mysqli_fetch_assoc($jadwal_res)) {
    $jadwal_data[] = $j;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Praktik - Rafflesia Sehat</title>
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

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 1rem;
        transition: all 1s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    .form-control:focus,
    .form-select:focus {
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

    .badge-spec {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge-kuota {
        background: #1e40af;
        color: #bfdbfe;
        border: 1px solid #1d4ed8;
        padding: 6px 10px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
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

        .form-control,
        .form-select {
            font-size: 0.95rem;
        }

        .badge-spec,
        .badge-kuota {
            font-size: 0.8rem;
            padding: 5px 10px;
        }

        .stat-card {
            padding: 20px;
            min-width: 140px;
        }

        .stat-number {
            font-size: 2rem;
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="page-title fade-in-up">
                        <i class="fas fa-calendar-check"></i>
                        <span>Jadwal Praktik</span>
                    </h1>
                    <p class="page-subtitle">Kelola jadwal praktik dokter dan kapasitas layanan.</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= $total_jadwal; ?></div>
                    <div class="stat-label">Total Jadwal</div>
                </div>
            </div>

            <!-- Flash -->
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

            <!-- Form Tambah -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span>Tambah Jadwal Baru</span>
                </h5>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                    <div class="col-md-3">
                        <label class="form-label">Dokter</label>
                        <select name="dokter_id" class="form-select" required>
                            <option value="">-- Pilih Dokter --</option>
                            <?php foreach($dokter_opts as $d): ?>
                            <option value="<?= (int)$d['id']; ?>"><?= h($d['nama']); ?> — <?= h($d['spesialis']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Kuota</label>
                        <input type="number" name="kuota" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="opsional">
                    </div>
                    <div class="col-md-12 d-grid d-md-flex justify-content-md-end mt-3">
                        <button type="submit" name="tambah" class="btn-success-custom">
                            <i class="fas fa-plus me-1"></i>
                            <span>Tambah Jadwal</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3">
                    <i class="fas fa-filter me-2"></i>
                    <span>Filter Data</span>
                </h5>
                <form class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter Dokter</label>
                        <select name="dokter" class="form-select" onchange="this.form.submit()">
                            <option value="0">Semua Dokter</option>
                            <?php foreach($dokter_opts as $d): ?>
                            <option value="<?= (int)$d['id']; ?>" <?= $filter_dokter===(int)$d['id']?'selected':''; ?>>
                                <?= h($d['nama']); ?> — <?= h($d['spesialis']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= h($filter_tanggal); ?>"
                            onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="jadwal.php" class="btn-secondary-custom">
                            <i class="fas fa-undo me-1"></i>
                            <span>Reset Filter</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabel Jadwal -->
            <div class="card-custom p-4 fade-in-up">
                <h5 class="mb-3">
                    <i class="fas fa-list me-2"></i>
                    <span>Daftar Jadwal</span>
                </h5>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Dokter</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Kuota</th>
                                    <th>Keterangan</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach($jadwal_data as $j): ?>
                                <tr class="fade-in-up">
                                    <td class="fw-semibold"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= h($j['nama_dokter']); ?></div>
                                        <span class="badge-spec"><?= h($j['spesialis']); ?></span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($j['tanggal'])); ?></td>
                                    <td><?= h(substr($j['jam_mulai'],0,5)); ?> -
                                        <?= h(substr($j['jam_selesai'],0,5)); ?></td>
                                    <td><span class="badge-kuota"><?= (int)$j['kuota']; ?></span></td>
                                    <td><?= h($j['keterangan']); ?></td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <button type="button" class="btn-warning-custom" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $j['id']; ?>">
                                                <i class="fas fa-edit me-1"></i>
                                                <span>Edit</span>
                                            </button>
                                            <a href="?hapus=<?= (int)$j['id']; ?>"
                                                onclick="return confirm('Hapus jadwal untuk <?= h($j['nama_dokter']); ?> pada <?= date('d M Y', strtotime($j['tanggal'])); ?>?')"
                                                class="btn-danger-custom text-decoration-none">
                                                <i class="fas fa-trash me-1"></i>
                                                <span>Hapus</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($jadwal_data)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                                        Belum ada jadwal.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Back -->
            <div class="mt-4 fade-in-up">
                <a href="index.php" class="btn-secondary-custom">
                    <i class="fas fa-arrow-left me-2"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Edit - Ditempatkan di luar tabel -->
    <?php foreach($jadwal_data as $j): ?>
    <div class="modal fade" id="editModal<?= $j['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>
                            <span>Edit Jadwal</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                        <input type="hidden" name="id" value="<?= (int)$j['id']; ?>">

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Dokter</label>
                                <select name="dokter_id" class="form-select" required>
                                    <?php foreach($dokter_opts as $d): ?>
                                    <option value="<?= (int)$d['id']; ?>"
                                        <?= ((int)$j['dokter_id']===(int)$d['id'])?'selected':''; ?>>
                                        <?= h($d['nama']); ?> — <?= h($d['spesialis']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= h($j['tanggal']); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kuota</label>
                                <input type="number" name="kuota" class="form-control" min="0"
                                    value="<?= (int)$j['kuota']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Mulai</label>
                                <input type="time" name="jam_mulai" class="form-control"
                                    value="<?= h(substr($j['jam_mulai'],0,5)); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Selesai</label>
                                <input type="time" name="jam_selesai" class="form-control"
                                    value="<?= h(substr($j['jam_selesai'],0,5)); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Keterangan</label>
                                <input type="text" name="keterangan" class="form-control"
                                    value="<?= h($j['keterangan']); ?>" placeholder="Tambahkan keterangan (opsional)">
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
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Validasi client-side sederhana
    document.addEventListener('DOMContentLoaded', function() {
        // Validasi untuk form tambah
        const formTambah = document.querySelector('form[method="POST"]');
        if (formTambah && !formTambah.querySelector('input[name="id"]')) {
            formTambah.addEventListener('submit', function(e) {
                const mulai = this.querySelector('input[name="jam_mulai"]')?.value;
                const selesai = this.querySelector('input[name="jam_selesai"]')?.value;
                if (mulai && selesai && mulai >= selesai) {
                    alert('Jam mulai harus lebih awal dari jam selesai.');
                    e.preventDefault();
                }
            });
        }

        // Validasi untuk modal edit
        const editModals = document.querySelectorAll('.modal');
        editModals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                const form = this.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const mulai = this.querySelector('input[name="jam_mulai"]')
                            ?.value;
                        const selesai = this.querySelector('input[name="jam_selesai"]')
                            ?.value;
                        if (mulai && selesai && mulai >= selesai) {
                            alert('Jam mulai harus lebih awal dari jam selesai.');
                            e.preventDefault();
                        }
                    });
                }
            });
        });

        // Animasi baris tabel
        document.querySelectorAll('tbody tr').forEach((row, i) => {
            row.style.animationDelay = `${i * 0.05}s`;
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
    });
    </script>
</body>

</html>