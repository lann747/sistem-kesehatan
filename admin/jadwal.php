<?php
session_start();
include '../config/db.php';

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Helper ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

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
if (isset($_POST['tambah'])) {
    $dokter_id   = (int)($_POST['dokter_id'] ?? 0);
    $tanggal     = $_POST['tanggal'] ?? '';
    $jam_mulai   = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $kuota       = max(0, (int)($_POST['kuota'] ?? 0));
    $keterangan  = trim($_POST['keterangan'] ?? '');

    // Validasi sederhana
    $err = '';
    if ($dokter_id <= 0 || $tanggal === '' || $jam_mulai === '' || $jam_selesai === '') {
        $err = 'Lengkapi semua field wajib.';
    } elseif (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
        $err = 'Jam mulai harus lebih awal dari jam selesai.';
    }

    if ($err === '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO jadwal (dokter_id, tanggal, jam_mulai, jam_selesai, kuota, keterangan) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "isssis", $dokter_id, $tanggal, $jam_mulai, $jam_selesai, $kuota, $keterangan);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: jadwal.php?added=1');
        exit;
    } else {
        $form_error = $err;
    }
}

// --- Update Jadwal ---
if (isset($_POST['update'])) {
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
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: jadwal.php?updated=1');
        exit;
    } else {
        $form_error = $err;
    }
}

// --- Hapus Jadwal ---
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM jadwal WHERE id = $id");
    header('Location: jadwal.php?deleted=1');
    exit;
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
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Jadwal Praktik - Sistem Informasi Kesehatan</title>
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
        transition: .3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, .3);
        transform: translateY(-1px)
    }

    .main-content {
        padding: 30px 0
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
        margin-bottom: 20px
    }

    body.dark-mode .page-subtitle {
        color: #9ca3af
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: .3s;
        border-left: 4px solid var(--primary-color)
    }

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2)
    }

    .card-custom:hover {
        transform: translateY(-4px)
    }

    .table-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08)
    }

    .table-custom thead th {
        background: var(--table-header);
        color: #fff;
        border: none
    }

    .badge-spec {
        background: #eff6ff;
        color: #1e3a8a;
        border: 1px solid #bfdbfe;
        padding: 4px 8px;
        border-radius: 8px
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
        margin-right: 10px
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg)
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        border-left: 4px solid var(--secondary-color)
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .2)
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 4px
    }

    .stat-label {
        color: #6b7280;
        font-size: .9rem;
        font-weight: 500
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
                <div class="user-info"><i class="fas fa-user-shield"></i><span><?= h($_SESSION['nama']); ?></span></div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <!-- Header & Stats -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="page-title fade-in-up"><i class="fas fa-calendar-check"></i> Jadwal Praktik</h1>
                    <p class="page-subtitle">Kelola jadwal praktik dokter dan kapasitas layanan.</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= $total_jadwal; ?></div>
                    <div class="stat-label">Total Jadwal</div>
                </div>
            </div>

            <!-- Notif -->
            <?php if(isset($_GET['added'])): ?><div class="alert alert-success fade-in-up">Jadwal berhasil ditambahkan.
            </div><?php endif; ?>
            <?php if(isset($_GET['updated'])): ?><div class="alert alert-success fade-in-up">Jadwal berhasil diperbarui.
            </div><?php endif; ?>
            <?php if(isset($_GET['deleted'])): ?><div class="alert alert-success fade-in-up">Jadwal berhasil dihapus.
            </div><?php endif; ?>
            <?php if(!empty($form_error)): ?><div class="alert alert-danger fade-in-up"><?= h($form_error); ?></div>
            <?php endif; ?>

            <!-- Form Tambah -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Tambah Jadwal Baru</h5>
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Dokter</label>
                        <select name="dokter_id" class="form-control" required>
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
                    <div class="col-md-12 d-grid d-md-flex justify-content-md-end">
                        <button type="submit" name="tambah" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Tambah
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <form class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter Dokter</label>
                        <select name="dokter" class="form-control" onchange="this.form.submit()">
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
                        <a href="jadwal.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
                    </div>
                </form>
            </div>

            <!-- Tabel Jadwal -->
            <div class="card-custom p-4 fade-in-up">
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-custom table-striped mb-0">
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
                                <?php
              $no = 1;
              if (mysqli_num_rows($jadwal_res) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Belum ada jadwal.</td>
                                </tr>
                                <?php else:
                while($j = mysqli_fetch_assoc($jadwal_res)):
              ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= h($j['nama_dokter']); ?></div>
                                        <span class="badge-spec"><?= h($j['spesialis']); ?></span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($j['tanggal'])); ?></td>
                                    <td><?= h(substr($j['jam_mulai'],0,5)); ?> -
                                        <?= h(substr($j['jam_selesai'],0,5)); ?></td>
                                    <td><span class="badge bg-primary"><?= (int)$j['kuota']; ?></span></td>
                                    <td><?= h($j['keterangan']); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $j['id']; ?>">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </button>
                                            <a class="btn btn-danger btn-sm" href="?hapus=<?= (int)$j['id']; ?>"
                                                onclick="return confirm('Hapus jadwal untuk <?= h($j['nama_dokter']); ?> pada <?= date('d M Y', strtotime($j['tanggal'])); ?>?')">
                                                <i class="fas fa-trash me-1"></i> Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editModal<?= $j['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header" style="background:#3b82f6;color:#fff;">
                                                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Jadwal
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= (int)$j['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Dokter</label>
                                                        <select name="dokter_id" class="form-control" required>
                                                            <?php foreach($dokter_opts as $d): ?>
                                                            <option value="<?= (int)$d['id']; ?>"
                                                                <?= ((int)$j['dokter_id']===(int)$d['id'])?'selected':''; ?>>
                                                                <?= h($d['nama']); ?> — <?= h($d['spesialis']); ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Tanggal</label>
                                                            <input type="date" name="tanggal" class="form-control"
                                                                value="<?= h($j['tanggal']); ?>" required>
                                                        </div>
                                                        <div class="col-md-3 mb-3">
                                                            <label class="form-label">Mulai</label>
                                                            <input type="time" name="jam_mulai" class="form-control"
                                                                value="<?= h(substr($j['jam_mulai'],0,5)); ?>" required>
                                                        </div>
                                                        <div class="col-md-3 mb-3">
                                                            <label class="form-label">Selesai</label>
                                                            <input type="time" name="jam_selesai" class="form-control"
                                                                value="<?= h(substr($j['jam_selesai'],0,5)); ?>"
                                                                required>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Kuota</label>
                                                            <input type="number" name="kuota" class="form-control"
                                                                min="0" value="<?= (int)$j['kuota']; ?>">
                                                        </div>
                                                        <div class="col-md-8 mb-3">
                                                            <label class="form-label">Keterangan</label>
                                                            <input type="text" name="keterangan" class="form-control"
                                                                value="<?= h($j['keterangan']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update" class="btn btn-primary"><i
                                                            class="fas fa-save me-1"></i> Simpan</button>
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>
                                                        Batal</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Back -->
                <div class="mt-4">
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Kembali ke
                        Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

    // Validasi client-side sederhana
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const mulai = form.querySelector('input[name="jam_mulai"]')?.value;
                const selesai = form.querySelector('input[name="jam_selesai"]')?.value;
                if (mulai && selesai && mulai >= selesai) {
                    alert('Jam mulai harus lebih awal dari jam selesai.');
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>

</html>