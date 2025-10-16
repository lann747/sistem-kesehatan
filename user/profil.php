<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

$id_user = $_SESSION['id'];

// Ambil data user dari database
$user = mysqli_query($conn, "SELECT * FROM users WHERE id='$id_user'");
$data = mysqli_fetch_assoc($user);

// Update data profil
if (isset($_POST['update'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email', no_hp='$no_hp', alamat='$alamat' WHERE id='$id_user'");
    $_SESSION['nama'] = $nama; // perbarui sesi
    header('Location: profil.php?success=1');
    exit;
}

// Update password
if (isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Verifikasi password lama
    if (md5($password_lama) === $data['password']) {
        if ($password_baru === $konfirmasi_password) {
            if (strlen($password_baru) >= 6) {
                $password_hash = md5($password_baru);
                mysqli_query($conn, "UPDATE users SET password='$password_hash' WHERE id='$id_user'");
                $success_password = "Password berhasil diubah!";
            } else {
                $error_password = "Password baru minimal 6 karakter!";
            }
        } else {
            $error_password = "Konfirmasi password tidak cocok!";
        }
    } else {
        $error_password = "Password lama salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Sistem Informasi Kesehatan</title>
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
        padding: 12px 15px;
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

    /* Alert Styles */
    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 12px 15px;
        font-weight: 500;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
    }

    .alert-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    body.dark-mode .alert-success {
        background: #064e3b;
        color: #a7f3d0;
    }

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca;
    }

    /* Profile Header */
    .profile-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    body.dark-mode .profile-header {
        background: linear-gradient(135deg, var(--primary-dark), #1e293b);
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 15px;
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

    /* Section Titles */
    .section-title {
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    body.dark-mode .section-title {
        border-bottom-color: #374151;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .profile-header {
            padding: 20px;
            text-align: center;
        }

        .profile-avatar {
            margin: 0 auto 15px;
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
                    <i class="fas fa-user"></i>
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
            <!-- Profile Header -->
            <div class="profile-header fade-in-up">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2 class="mb-2"><?= htmlspecialchars($data['nama']); ?></h2>
                        <p class="mb-1 opacity-75"><i
                                class="fas fa-envelope me-2"></i><?= htmlspecialchars($data['email']); ?></p>
                        <p class="mb-0 opacity-75"><i class="fas fa-user-tag me-2"></i>Pengguna</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-circle fa-4x opacity-75"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Edit Profil Section -->
                <div class="col-lg-8">
                    <div class="card-custom p-4 mb-4 fade-in-up">
                        <h4 class="section-title">
                            <i class="fas fa-user-edit"></i> Edit Profil
                        </h4>

                        <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-custom mb-4">
                            <i class="fas fa-check-circle me-2"></i>Profil berhasil diperbarui!
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" name="nama" class="form-control"
                                        value="<?= htmlspecialchars($data['nama']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($data['email']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. HP</label>
                                <input type="text" name="no_hp" class="form-control"
                                    value="<?= htmlspecialchars($data['no_hp']); ?>"
                                    placeholder="Masukkan nomor handphone">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="3"
                                    placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($data['alamat']); ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="update" class="btn-success-custom">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                                <a href="index.php" class="btn-secondary-custom">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Ubah Password Section -->
                <div class="col-lg-4">
                    <div class="card-custom p-4 fade-in-up">
                        <h4 class="section-title">
                            <i class="fas fa-lock"></i> Keamanan
                        </h4>

                        <?php if (isset($success_password)): ?>
                        <div class="alert alert-success alert-custom mb-4">
                            <i class="fas fa-check-circle me-2"></i><?= $success_password; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_password)): ?>
                        <div class="alert alert-danger alert-custom mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error_password; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Password Lama</label>
                                <input type="password" name="password_lama" class="form-control"
                                    placeholder="Masukkan password lama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="password_baru" class="form-control"
                                    placeholder="Minimal 6 karakter" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" name="konfirmasi_password" class="form-control"
                                    placeholder="Ulangi password baru" required>
                            </div>
                            <button type="submit" name="ubah_password" class="btn-primary-custom w-100">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </button>
                        </form>
                    </div>

                    <!-- Info Akun -->
                    <div class="card-custom p-4 mt-4 fade-in-up">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i> Info Akun
                        </h4>
                        <div class="mb-3">
                            <small class="text-muted">ID Pengguna</small>
                            <p class="mb-0 fw-semibold">#<?= $data['id']; ?></p>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Role</small>
                            <p class="mb-0 fw-semibold">
                                <span class="badge bg-primary">User</span>
                            </p>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Bergabung Sejak</small>
                            <p class="mb-0 fw-semibold">
                                <?= date('d M Y', strtotime($data['created_at'] ?? 'now')); ?>
                            </p>
                        </div>
                    </div>
                </div>
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

    // Validasi form
    document.addEventListener('DOMContentLoaded', function() {
        const passwordForm = document.querySelector('form[action*="ubah_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const passwordBaru = this.querySelector('input[name="password_baru"]').value;
                const konfirmasi = this.querySelector('input[name="konfirmasi_password"]').value;

                if (passwordBaru.length < 6) {
                    alert('Password baru minimal 6 karakter!');
                    e.preventDefault();
                } else if (passwordBaru !== konfirmasi) {
                    alert('Konfirmasi password tidak cocok!');
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>

</html>