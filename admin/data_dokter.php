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

// Hanya admin yang boleh masuk
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

// Helper escape HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// CSRF token halaman admin
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
    $nama     = trim($_POST['nama'] ?? '');
    $spesialis= trim($_POST['spesialis'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    // Validasi sederhana
    if ($nama === '' || $spesialis === '' || $no_hp === '' || $alamat === '') {
        $flash_err = 'Semua kolom wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $no_hp)) {
        $flash_err = 'Format No. HP tidak valid.';
    } else {
        $stmt = $conn->prepare("INSERT INTO dokter (nama, spesialis, no_hp, alamat) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssss', $nama, $spesialis, $no_hp, $alamat);
            $stmt->execute();
            $stmt->close();
            header('Location: data_dokter.php'); exit;
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
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama'] ?? '');
    $spesialis= trim($_POST['spesialis'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    if ($id <= 0) {
        $flash_err = 'ID tidak valid.';
    } elseif ($nama === '' || $spesialis === '' || $no_hp === '' || $alamat === '') {
        $flash_err = 'Semua kolom wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $no_hp)) {
        $flash_err = 'Format No. HP tidak valid.';
    } else {
        $stmt = $conn->prepare("UPDATE dokter SET nama=?, spesialis=?, no_hp=?, alamat=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssi', $nama, $spesialis, $no_hp, $alamat, $id);
            $stmt->execute();
            $stmt->close();
            header('Location: data_dokter.php'); exit;
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
        $stmt = $conn->prepare("DELETE FROM dokter WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: data_dokter.php'); exit;
}

// ---------- Query ringkas: total & list ----------
$total = 0;
if ($res = $conn->query("SELECT COUNT(*) AS total FROM dokter")) {
    $row = $res->fetch_assoc();
    $total = (int)($row['total'] ?? 0);
    $res->close();
}

// (Opsional) pagination sederhana
$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$list = [];
$stmt = $conn->prepare("SELECT id, nama, spesialis, no_hp, alamat FROM dokter ORDER BY id DESC LIMIT ? OFFSET ?");
if ($stmt) {
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $list[] = $r; }
    $stmt->close();
}
$total_pages = (int)ceil(($total ?: 1)/$per_page);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Dokter - Sistem Informasi Kesehatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #3b82f6;
        --primary-dark: #1d4ed8;
        --secondary-color: #10b981;
        --light-bg: #f0f9ff;
        --dark-bg: #0f172a;
        --text-light: #f8fafc;
        --text-dark: #1e293b;
        --card-light: #ffffff;
        --card-dark: #1e293b;
        --navbar-bg: #3b82f6;
        --table-header: #3b82f6
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

    .main-content {
        padding: 30px 0;
        min-height: calc(100vh - 120px)
    }

    .page-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px
    }

    .page-subtitle {
        color: #6b7280;
        margin-bottom: 30px
    }

    body.dark-mode .page-subtitle {
        color: #9ca3af
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        border-left: 4px solid var(--primary-color)
    }

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2)
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, .15)
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 15px;
        font-size: .95rem;
        transition: all .3s ease;
        background: var(--card-light);
        color: var(--text-dark)
    }

    body.dark-mode .form-control {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light)
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .1)
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all .3s ease
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
    }

    .btn-warning-custom {
        background: #f59e0b;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all .3s ease
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2)
    }

    .btn-danger-custom {
        background: #ef4444;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all .3s ease
    }

    .btn-danger-custom:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2)
    }

    .btn-secondary-custom {
        background: #6b7280;
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 10px;
        transition: all .3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px
    }

    .btn-secondary-custom:hover {
        background: #4b5563;
        color: #fff;
        transform: translateY(-1px)
    }

    .table-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08)
    }

    .table-custom {
        margin: 0;
        background: var(--card-light)
    }

    body.dark-mode .table-custom {
        background: var(--card-dark)
    }

    .table-custom thead th {
        background: var(--table-header);
        color: #fff;
        font-weight: 600;
        padding: 15px 12px;
        border: none;
        font-size: .9rem
    }

    .table-custom tbody td {
        padding: 12px;
        border-color: #e5e7eb;
        vertical-align: middle;
        color: var(--text-dark)
    }

    body.dark-mode .table-custom tbody td {
        border-color: #374151;
        color: var(--text-light)
    }

    .table-custom tbody tr {
        transition: all .3s ease
    }

    .table-custom tbody tr:hover {
        background: rgba(59, 130, 246, .05)
    }

    body.dark-mode .table-custom tbody tr:hover {
        background: rgba(59, 130, 246, .1)
    }

    .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
        background: var(--card-light)
    }

    body.dark-mode .modal-content {
        background: var(--card-dark)
    }

    .modal-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 20px 25px;
        background: var(--primary-color);
        color: #fff;
        border-radius: 15px 15px 0 0
    }

    .modal-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        border-left: 4px solid var(--secondary-color)
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2)
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

    @media (max-width:768px) {
        .main-content {
            padding: 20px 0
        }

        .table-responsive {
            font-size: .85rem
        }

        .btn-warning-custom,
        .btn-danger-custom {
            padding: 5px 10px;
            font-size: .8rem
        }

        .form-control {
            font-size: .9rem
        }
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
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat"></i> Sistem Kesehatan</a>
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema"><i class="fas fa-moon"></i></button>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span><?= h($_SESSION['nama'] ?? 'Admin'); ?></span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title fade-in-up"><i class="fas fa-user-md"></i> Data Dokter</h1>
                    <p class="page-subtitle">Kelola data dokter dan spesialisasi</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= (int)$total; ?></div>
                    <div class="stat-label">Total Dokter</div>
                </div>
            </div>

            <?php if (!empty($flash_err)): ?>
            <div class="alert alert-danger border-0 rounded-3 shadow-sm fade-in-up" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= h($flash_err) ?>
            </div>
            <?php endif; ?>

            <!-- Form Tambah Dokter -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Tambah Dokter Baru</h5>
                <form method="POST" class="row g-3" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <div class="col-md-3">
                        <input type="text" name="nama" class="form-control" placeholder="Nama Dokter" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="spesialis" class="form-control" placeholder="Spesialis" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="no_hp" class="form-control" placeholder="No. HP" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="alamat" class="form-control" placeholder="Alamat" required>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" name="tambah" class="btn-primary-custom">
                            <i class="fas fa-plus me-1"></i>Tambah
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Dokter -->
            <div class="card-custom p-4 fade-in-up">
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Dokter</th>
                                    <th>Spesialis</th>
                                    <th>No. HP</th>
                                    <th>Alamat</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1 + $offset; foreach ($list as $d): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td class="fw-semibold"><?= h($d['nama']) ?></td>
                                    <td><span class="badge bg-primary"><?= h($d['spesialis']) ?></span></td>
                                    <td><?= h($d['no_hp']) ?></td>
                                    <td><?= h($d['alamat']) ?></td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button class="btn-warning-custom" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= (int)$d['id'] ?>">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>

                                            <!-- Hapus via POST + CSRF -->
                                            <form method="POST"
                                                onsubmit="return confirm('Yakin hapus data dokter <?= h($d['nama']) ?>?')"
                                                class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                <button type="submit" name="hapus" class="btn-danger-custom"><i
                                                        class="fas fa-trash me-1"></i>Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editModal<?= (int)$d['id'] ?>" tabindex="-1"
                                    aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" autocomplete="off">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Data
                                                        Dokter</h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Dokter</label>
                                                        <input type="text" name="nama" class="form-control"
                                                            value="<?= h($d['nama']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Spesialis</label>
                                                        <input type="text" name="spesialis" class="form-control"
                                                            value="<?= h($d['spesialis']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">No. HP</label>
                                                        <input type="text" name="no_hp" class="form-control"
                                                            value="<?= h($d['no_hp']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Alamat</label>
                                                        <input type="text" name="alamat" class="form-control"
                                                            value="<?= h($d['alamat']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update" class="btn-primary-custom"><i
                                                            class="fas fa-save me-1"></i>Simpan Perubahan</button>
                                                    <button type="button" class="btn-secondary-custom"
                                                        data-bs-dismiss="modal"><i
                                                            class="fas fa-times me-1"></i>Batal</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if (empty($list)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Belum ada data dokter.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination sederhana -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-3">
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
                <a href="index.php" class="btn-secondary-custom"><i class="fas fa-arrow-left me-2"></i>Kembali ke
                    Dashboard</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
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

    // Animasi untuk baris tabel
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('tbody tr').forEach((row, i) => {
            row.style.animationDelay = `${i*0.05}s`;
            row.classList.add('fade-in-up');
        });
    });
    </script>
</body>

</html>