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

// --- Tabel pengaturan: key-value ---
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pengaturan (
    k VARCHAR(64) PRIMARY KEY,
    v TEXT
) ENGINE=InnoDB");

// Inisialisasi default jika kosong
$defaults = [
    'site_name'         => 'Sistem Informasi Kesehatan',
    'kontak_email'      => 'info@sistemkesehatan.id',
    'kontak_phone'      => '',
    'alamat_instansi'   => 'Universitas Bengkulu',
    'maintenance_mode'  => '0', // 0=off, 1=on
    'allow_registration'=> '1'  // 1=on, 0=off
];

foreach ($defaults as $k => $v) {
    $k_esc = mysqli_real_escape_string($conn, $k);
    $check = mysqli_query($conn, "SELECT k FROM pengaturan WHERE k='$k_esc' LIMIT 1");
    if (mysqli_num_rows($check) === 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (k, v) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $k, $v);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Ambil semua pengaturan ke array
$settings = [];
$res = mysqli_query($conn, "SELECT k, v FROM pengaturan");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['k']] = $row['v'];
}

// --- CSRF token sederhana ---
if (empty($_SESSION['csrf_pengaturan'])) {
    $_SESSION['csrf_pengaturan'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_pengaturan'];

// --- Simpan pengaturan umum ---
if (isset($_POST['simpan_umum'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        $notif_error = "Sesi kadaluarsa. Muat ulang halaman.";
    } else {
        $site_name   = trim($_POST['site_name'] ?? '');
        $email       = trim($_POST['kontak_email'] ?? '');
        $phone       = trim($_POST['kontak_phone'] ?? '');
        $alamat      = trim($_POST['alamat_instansi'] ?? '');

        if ($site_name === '' || $email === '') {
            $notif_error = "Nama situs dan email kontak wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $notif_error = "Format email tidak valid.";
        } else {
            $pairs = [
                'site_name'       => $site_name,
                'kontak_email'    => $email,
                'kontak_phone'    => $phone,
                'alamat_instansi' => $alamat
            ];
            foreach ($pairs as $k => $v) {
                $stmt = mysqli_prepare($conn, "REPLACE INTO pengaturan (k, v) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ss", $k, $v);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $settings[$k] = $v;
            }
            $notif_success = "Pengaturan umum berhasil disimpan.";
        }
    }
}

// --- Simpan pengaturan fitur (maintenance & registrasi) ---
if (isset($_POST['simpan_fitur'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        $notif_error = "Sesi kadaluarsa. Muat ulang halaman.";
    } else {
        $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
        $allow_reg   = isset($_POST['allow_registration']) ? '1' : '0';

        $pairs = [
            'maintenance_mode'   => $maintenance,
            'allow_registration' => $allow_reg
        ];
        foreach ($pairs as $k => $v) {
            $stmt = mysqli_prepare($conn, "REPLACE INTO pengaturan (k, v) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $k, $v);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $settings[$k] = $v;
        }
        $notif_success = "Pengaturan fitur berhasil disimpan.";
    }
}

// --- Ubah password admin (user saat ini) ---
if (isset($_POST['ubah_password'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        $notif_error = "Sesi kadaluarsa. Muat ulang halaman.";
    } else {
        $uid = (int)($_SESSION['id'] ?? 0);
        $lama = $_POST['password_lama'] ?? '';
        $baru = $_POST['password_baru'] ?? '';
        $konf = $_POST['konfirmasi_password'] ?? '';

        if ($uid <= 0) {
            $notif_error = "Sesi tidak valid.";
        } elseif ($baru !== $konf) {
            $notif_error = "Konfirmasi password tidak cocok.";
        } elseif (strlen($baru) < 6) {
            $notif_error = "Password baru minimal 6 karakter.";
        } else {
            $r = mysqli_query($conn, "SELECT password FROM users WHERE id=$uid LIMIT 1");
            $now = mysqli_fetch_assoc($r);
            if (!$now || $now['password'] !== md5($lama)) {
                $notif_error = "Password lama salah.";
            } else {
                $hash = md5($baru);
                mysqli_query($conn, "UPDATE users SET password='$hash' WHERE id=$uid");
                $notif_success = "Password admin berhasil diubah.";
            }
        }
    }
}

// --- Ekspor CSV (users/dokter/pasien) ---
if (isset($_GET['backup'])) {
    $allowed = ['users','dokter','pasien'];
    $t = $_GET['backup'];
    if (in_array($t, $allowed, true)) {
        $filename = $t . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output','w');

        // Header kolom dinamis
        $res = mysqli_query($conn, "SELECT * FROM $t");
        if ($res) {
            $fields = [];
            $finfo = mysqli_fetch_fields($res);
            foreach ($finfo as $fi) { $fields[] = $fi->name; }
            fputcsv($out, $fields);

            while ($row = mysqli_fetch_assoc($res)) {
                // Hindari mengekspor hash password? Tetap ekspor sesuai tabel saat ini.
                fputcsv($out, array_values($row));
            }
        }
        fclose($out);
        exit;
    }
}

// Label tampilan maintenance
$maintenance_badge = ($settings['maintenance_mode'] ?? '0') === '1' ? '<span class="badge bg-danger">Aktif</span>' : '<span class="badge bg-success">Nonaktif</span>';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Pengaturan Sistem - Sistem Informasi Kesehatan</title>
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
        --success-light: #dcfce7;
        --success-border: #bbf7d0;
        --warning-light: #fef3c7;
        --warning-border: #fde68a;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: .3s;
        min-height: 100vh;
        line-height: 1.6;
    }

    .navbar {
        background: linear-gradient(135deg, var(--navbar-bg), var(--primary-dark));
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 4px 18px rgba(22, 163, 74, 0.2);
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
        font-size: 1.8rem;
    }

    .page-subtitle {
        color: #6b7280;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, .08);
        transition: .3s;
        border-left: 4px solid var(--primary-color);
        overflow: hidden;
        height: 100%;
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
    }

    .section-title {
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.2rem;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
    }

    .stat-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
        border-left: 4px solid var(--secondary-color);
        transition: .3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-number {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6b7280;
        font-size: .9rem;
        font-weight: 500;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: .3s;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(22, 163, 74, 0.3);
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: .3s;
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #d97706, #f59e0b);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: .3s;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(22, 163, 74, 0.3);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: .3s;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #4b5563, #6b7280);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 15px 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
    }

    .alert-success {
        background: var(--success-light);
        color: #166534;
        border-left: 4px solid var(--primary-color);
    }

    .alert-danger {
        background: #fef2f2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    .form-control,
    .form-select,
    .form-check-input {
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        padding: 10px 15px;
        transition: .3s;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .form-check-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
    }

    .form-check-label {
        font-weight: 500;
    }

    .badge {
        border-radius: 20px;
        padding: 6px 12px;
        font-weight: 500;
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
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in-up {
        animation: fadeInUp .6s ease forwards;
    }

    .feature-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        border-left: 4px solid var(--secondary-color);
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
        transition: .3s;
    }

    .feature-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .page-title {
            font-size: 1.5rem;
        }

        .stat-card {
            margin-top: 15px;
        }

        .card-custom {
            border-radius: 10px;
        }

        .btn {
            width: 100%;
            margin-bottom: 10px;
        }

        .d-flex.flex-wrap.gap-2 {
            flex-direction: column;
        }
    }

    @media (max-width: 576px) {
        .main-content {
            padding: 15px 0;
        }

        .card-custom {
            padding: 15px;
        }

        .section-title {
            font-size: 1.1rem;
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat"></i> Rafflesia Sehat</a>
            <div class="d-flex align-items-center">
                <div class="user-info"><i class="fas fa-user-shield"></i><span><?= h($_SESSION['nama']); ?></span></div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title fade-in-up"><i class="fas fa-sliders-h"></i> Pengaturan Sistem</h1>
                    <p class="page-subtitle">Konfigurasi identitas aplikasi, fitur, keamanan, dan ekspor data.</p>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number">Maintenance: <?= $maintenance_badge; ?></div>
                    <div class="small mt-1">Registrasi:
                        <?= ($settings['allow_registration'] ?? '1') === '1' ? '<span class="badge bg-success">Diizinkan</span>' : '<span class="badge bg-danger">Ditutup</span>'; ?>
                    </div>
                </div>
            </div>

            <!-- Notifikasi -->
            <?php if(!empty($notif_success)): ?>
            <div class="alert alert-success fade-in-up mb-4">
                <i class="fas fa-check-circle me-2"></i><?= h($notif_success); ?>
            </div>
            <?php endif; ?>
            <?php if(!empty($notif_error)): ?>
            <div class="alert alert-danger fade-in-up mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?= h($notif_error); ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Pengaturan Umum -->
                <div class="col-lg-6">
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="section-title"><i class="fas fa-wrench me-2"></i> Pengaturan Umum</h5>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Nama Aplikasi</label>
                                <input type="text" class="form-control" name="site_name"
                                    value="<?= h($settings['site_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Email Kontak</label>
                                <input type="email" class="form-control" name="kontak_email"
                                    value="<?= h($settings['kontak_email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">No. Telepon</label>
                                <input type="text" class="form-control" name="kontak_phone"
                                    value="<?= h($settings['kontak_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Alamat Instansi</label>
                                <textarea class="form-control" name="alamat_instansi"
                                    rows="3"><?= h($settings['alamat_instansi'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12 d-grid d-md-flex justify-content-md-end mt-3">
                                <button class="btn btn-primary" type="submit" name="simpan_umum">
                                    <i class="fas fa-save me-1"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Fitur -->
                <div class="col-lg-6">
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="section-title"><i class="fas fa-toggle-on me-2"></i> Pengaturan Fitur</h5>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                            <div class="col-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="maintenance"
                                        name="maintenance_mode"
                                        <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="maintenance">Aktifkan Maintenance
                                        Mode</label>
                                    <div class="form-text text-muted">
                                        Saat aktif, Anda bisa menampilkan banner/redirect maintenance (butuh
                                        implementasi pada index/login jika ingin memblokir non-admin).
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="allowReg"
                                        name="allow_registration"
                                        <?= ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="allowReg">Izinkan Pendaftaran
                                        Pengguna Baru</label>
                                </div>
                            </div>
                            <div class="col-12 d-grid d-md-flex justify-content-md-end mt-3">
                                <button class="btn btn-primary" type="submit" name="simpan_fitur">
                                    <i class="fas fa-save me-1"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Keamanan (Ubah Password Admin) -->
                <div class="col-lg-6">
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="section-title"><i class="fas fa-lock me-2"></i> Keamanan Akun</h5>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf" value="<?= h($csrf); ?>">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Password Lama</label>
                                <input type="password" class="form-control" name="password_lama" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Password Baru</label>
                                <input type="password" class="form-control" name="password_baru" minlength="6" required>
                                <div class="form-text">Minimal 6 karakter</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" name="konfirmasi_password" minlength="6"
                                    required>
                            </div>
                            <div class="col-12 d-grid d-md-flex justify-content-md-end mt-3">
                                <button class="btn btn-warning" type="submit" name="ubah_password">
                                    <i class="fas fa-key me-1"></i> Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Ekspor Data -->
                <div class="col-lg-6">
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="section-title"><i class="fas fa-download me-2"></i> Ekspor Data (CSV)</h5>
                        <p class="mb-3">Unduh salinan data dalam format CSV untuk keperluan backup/analisis cepat.</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a class="btn btn-success" href="?backup=users">
                                <i class="fas fa-users me-1"></i> Export Users
                            </a>
                            <a class="btn btn-success" href="?backup=dokter">
                                <i class="fas fa-user-md me-1"></i> Export Dokter
                            </a>
                            <a class="btn btn-success" href="?backup=pasien">
                                <i class="fas fa-user-injured me-1"></i> Export Pasien
                            </a>
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-info-circle me-1"></i> CSV akan diunduh langsung via browser Anda.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Catatan Peningkatan -->
            <div class="card-custom p-4 mt-4 fade-in-up">
                <h5 class="section-title"><i class="fas fa-shield-alt me-2"></i> Rekomendasi Keamanan</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="feature-card">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-key me-2 text-warning"></i> Password Hashing
                            </h6>
                            <p class="small mb-0">Pertimbangkan migrasi password ke <b>password_hash()</b> &
                                <b>password_verify()</b> (algoritma modern) menggantikan MD5.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-tools me-2 text-primary"></i> Maintenance Mode
                            </h6>
                            <p class="small mb-0">Tambahkan middleware <i>maintenance</i> agar non-admin mendapat
                                halaman/alert maintenance saat mode aktif.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-user-lock me-2 text-danger"></i> Akses Ekspor
                            </h6>
                            <p class="small mb-0">Batasi akses ekspor data agar hanya admin tertentu (opsional: role
                                granular).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back -->
            <div class="mt-4">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Validasi form password
    document.addEventListener('DOMContentLoaded', function() {
        const passwordForm = document.querySelector('form[name="ubah_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const passwordBaru = this.querySelector('input[name="password_baru"]').value;
                const konfirmasi = this.querySelector('input[name="konfirmasi_password"]').value;

                if (passwordBaru !== konfirmasi) {
                    alert('Konfirmasi password tidak cocok dengan password baru.');
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>

</html>