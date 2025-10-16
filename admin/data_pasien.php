<?php
session_start();
include '../config/db.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Tambah Data Pasien ---
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $umur = (int)$_POST['umur'];
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $keluhan = mysqli_real_escape_string($conn, $_POST['keluhan']);

    mysqli_query($conn, "INSERT INTO pasien (nama, umur, alamat, keluhan) VALUES ('$nama', '$umur', '$alamat', '$keluhan')");
    header('Location: data_pasien.php');
    exit;
}

// --- Hapus Data Pasien ---
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM pasien WHERE id='$id'");
    header('Location: data_pasien.php');
    exit;
}

// --- Update Data Pasien ---
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $umur = (int)$_POST['umur'];
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $keluhan = mysqli_real_escape_string($conn, $_POST['keluhan']);

    mysqli_query($conn, "UPDATE pasien SET nama='$nama', umur='$umur', alamat='$alamat', keluhan='$keluhan' WHERE id='$id'");
    header('Location: data_pasien.php');
    exit;
}

// Hitung total pasien
$total_pasien = mysqli_query($conn, "SELECT COUNT(*) as total FROM pasien");
$total = mysqli_fetch_assoc($total_pasien)['total'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pasien - Sistem Informasi Kesehatan</title>
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
        transition: all 0.3s ease;
        min-height: 100vh;
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light);
    }

    /* Navbar */
    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        padding: 1rem 0;
    }

    body.dark-mode .navbar {
        background: var(--dark-bg);
        border-bottom-color: var(--primary-color);
    }

    .navbar-brand {
        font-weight: 700;
        color: white !important;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
    }

    .user-info {
        color: white;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-1px);
    }

    /* Main Content */
    .main-content {
        padding: 30px 0;
        min-height: calc(100vh - 120px);
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
        margin-bottom: 30px;
    }

    body.dark-mode .page-subtitle {
        color: #9ca3af;
    }

    /* Cards */
    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border-left: 4px solid var(--primary-color);
    }

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    /* Form Styles */
    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--card-light);
        color: var(--text-dark);
    }

    body.dark-mode .form-control {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
    }

    body.dark-mode .form-label {
        color: var(--text-light);
    }

    /* Buttons */
    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: white;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-success-custom {
        background: var(--secondary-color);
        border: none;
        color: white;
        font-weight: 500;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn-success-custom:hover {
        background: #0d9669;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-warning-custom {
        background: #f59e0b;
        border: none;
        color: white;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-warning-custom:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .btn-danger-custom {
        background: #ef4444;
        border: none;
        color: white;
        font-weight: 500;
        padding: 6px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-danger-custom:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .btn-secondary-custom {
        background: #6b7280;
        border: none;
        color: white;
        font-weight: 500;
        padding: 8px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-secondary-custom:hover {
        background: #4b5563;
        color: white;
        transform: translateY(-1px);
    }

    /* Table Styles */
    .table-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .table-custom {
        margin: 0;
        background: var(--card-light);
    }

    body.dark-mode .table-custom {
        background: var(--card-dark);
    }

    .table-custom thead th {
        background: var(--table-header);
        color: white;
        font-weight: 600;
        padding: 15px 12px;
        border: none;
        font-size: 0.9rem;
    }

    .table-custom tbody td {
        padding: 12px;
        border-color: #e5e7eb;
        vertical-align: middle;
        color: var(--text-dark);
    }

    body.dark-mode .table-custom tbody td {
        border-color: #374151;
        color: var(--text-light);
    }

    .table-custom tbody tr {
        transition: all 0.3s ease;
    }

    .table-custom tbody tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    body.dark-mode .table-custom tbody tr:hover {
        background: rgba(59, 130, 246, 0.1);
    }

    /* Keluhan Badge */
    .keluhan-badge {
        background: #f0f9ff;
        color: #0369a1;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        border: 1px solid #bae6fd;
    }

    body.dark-mode .keluhan-badge {
        background: #1e3a8a;
        color: #bfdbfe;
        border-color: #1e40af;
    }

    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        background: var(--card-light);
    }

    body.dark-mode .modal-content {
        background: var(--card-dark);
    }

    .modal-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 20px 25px;
        background: var(--primary-color);
        color: white;
        border-radius: 15px 15px 0 0;
    }

    body.dark-mode .modal-header {
        border-bottom-color: #374151;
    }

    .modal-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 20px 25px;
    }

    body.dark-mode .modal-footer {
        border-top-color: #374151;
    }

    /* Stats Card */
    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border-left: 4px solid var(--secondary-color);
    }

    body.dark-mode .stat-card {
        background: var(--card-dark);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 500;
    }

    body.dark-mode .stat-label {
        color: #9ca3af;
    }

    /* Theme Toggle */
    .theme-toggle {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-right: 10px;
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(20deg);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .btn-warning-custom,
        .btn-danger-custom {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .form-control {
            font-size: 0.9rem;
        }

        .keluhan-badge {
            font-size: 0.75rem;
            padding: 3px 6px;
        }
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.6s ease forwards;
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat"></i> Sistem Kesehatan
            </a>
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($_SESSION['nama']); ?></span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title fade-in-up">
                        <i class="fas fa-user-injured"></i> Data Pasien
                    </h1>
                    <p class="page-subtitle">Kelola data pasien dan keluhan medis</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number"><?= $total; ?></div>
                    <div class="stat-label">Total Pasien</div>
                </div>
            </div>

            <!-- Form Tambah Pasien -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Tambah Pasien Baru</h5>
                <form method="POST" class="row g-3">
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
                            <i class="fas fa-plus me-1"></i>Tambah
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
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $data = mysqli_query($conn, "SELECT * FROM pasien ORDER BY id DESC");
                                while ($d = mysqli_fetch_assoc($data)) :
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($d['nama']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($d['umur']); ?> tahun</span>
                                    </td>
                                    <td><?= htmlspecialchars($d['alamat']); ?></td>
                                    <td>
                                        <span class="keluhan-badge"><?= htmlspecialchars($d['keluhan']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button class="btn-warning-custom" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $d['id']; ?>">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <a href="?hapus=<?= $d['id']; ?>"
                                                onclick="return confirm('Yakin hapus data pasien <?= htmlspecialchars($d['nama']); ?>?')"
                                                class="btn-danger-custom text-decoration-none">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editModal<?= $d['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-edit me-2"></i>Edit Data Pasien
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= $d['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Pasien</label>
                                                        <input type="text" name="nama" class="form-control"
                                                            value="<?= htmlspecialchars($d['nama']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Umur</label>
                                                        <input type="number" name="umur" class="form-control"
                                                            value="<?= htmlspecialchars($d['umur']); ?>" min="1"
                                                            max="120" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Alamat</label>
                                                        <input type="text" name="alamat" class="form-control"
                                                            value="<?= htmlspecialchars($d['alamat']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Keluhan</label>
                                                        <input type="text" name="keluhan" class="form-control"
                                                            value="<?= htmlspecialchars($d['keluhan']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update" class="btn-primary-custom">
                                                        <i class="fas fa-save me-1"></i>Simpan Perubahan
                                                    </button>
                                                    <button type="button" class="btn-secondary-custom"
                                                        data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i>Batal
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-4 fade-in-up">
                <a href="index.php" class="btn-secondary-custom">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const toggleBtn = document.getElementById('themeToggle');
    const body = document.body;

    // Cek mode dari localStorage
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

    // Animasi untuk tabel rows
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            row.classList.add('fade-in-up');
        });
    });

    // Validasi form umur
    document.addEventListener('DOMContentLoaded', function() {
        const umurInputs = document.querySelectorAll('input[name="umur"]');
        umurInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value < 1) this.value = 1;
                if (this.value > 120) this.value = 120;
            });
        });
    });
    </script>
</body>

</html>