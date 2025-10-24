<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

// Konfigurasi kontak (opsional bisa diambil dari DB/pengaturan)
const ADMIN_EMAIL = 'admin@gmail.com';
const ADMIN_WHATSAPP = '6281234567890'; // ganti ke nomor WA admin (format internasional, tanpa +)

// CSRF token
if (empty($_SESSION['csrf_bantuan'])) {
    $_SESSION['csrf_bantuan'] = bin2hex(random_bytes(32));
}

$msg_success = '';
$msg_error   = '';

// Anti-spam sederhana: minimal jeda 20 detik antar submit
$cooldown_seconds = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lapor'])) {
    $token = $_POST['csrf'] ?? '';
    $now   = time();
    $last  = $_SESSION['last_submit_bantuan'] ?? 0;

    $nama   = trim($_POST['nama'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $topik  = trim($_POST['topik'] ?? 'Umum');
    $pesan  = trim($_POST['pesan'] ?? '');

    if (!hash_equals($_SESSION['csrf_bantuan'], $token)) {
        $msg_error = 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } elseif ($now - $last < $cooldown_seconds) {
        $msg_error = 'Terlalu cepat mengirim laporan. Mohon tunggu beberapa detik dan coba lagi.';
    } elseif ($nama === '' || $email === '' || $pesan === '') {
        $msg_error = 'Nama, email, dan pesan wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_error = 'Format email tidak valid.';
    } else {
        // Simulasikan sukses (tanpa penyimpanan/pengiriman)
        $_SESSION['last_submit_bantuan'] = $now;
        $msg_success = 'Terima kasih! Laporan Anda telah kami terima. Tim kami akan meninjau secepatnya.';

        // ====================================================================
        // TODO: kirim email / simpan ke DB tiket bantuan
        // Contoh (butuh konfigurasi mail server/SMTP):
        //
        // $subject = "[Bantuan] {$topik} - {$nama}";
        // $body    = "Pengirim: {$nama}\nEmail: {$email}\nTopik: {$topik}\n\nPesan:\n{$pesan}";
        // @mail(ADMIN_EMAIL, $subject, $body, "From: {$email}\r\nReply-To: {$email}");
        //
        // Atau simpan ke tabel `tiket_bantuan` jika tersedia.
        // ====================================================================
    }
}

// Helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bantuan & Panduan - Sistem Informasi Kesehatan</title>
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
        background: var(--light-bg);
        color: var(--text-dark);
        transition: var(--transition);
        min-height: 100vh;
        line-height: 1.6;
    }

    body.dark-mode {
        background: var(--dark-bg);
        color: var(--text-light);
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        width: 160px;
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

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
    }

    .card-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .form-control,
    .form-select,
    .input-group-text {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: var(--card-light);
        color: var(--text-dark);
        transition: var(--transition);
    }

    .input-group-text {
        border-right: none;
    }

    .form-control {
        border-left: none;
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .input-group-text {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
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

    .btn-outline-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-outline-custom:hover {
        background: var(--primary-color);
        color: #fff;
        transform: translateY(-1px);
    }

    .quick-contact .btn {
        border-radius: 12px;
    }

    .accordion-button {
        font-weight: 600;
        background: var(--card-light);
        color: var(--text-dark);
        border: 1px solid #e2e8f0;
        margin-bottom: 8px;
        border-radius: 12px !important;
        transition: var(--transition);
    }

    body.dark-mode .accordion-button {
        background: var(--card-dark);
        color: var(--text-light);
        border-color: #374151;
    }

    .accordion-button:not(.collapsed) {
        background: var(--primary-color);
        color: #fff;
        border-color: var(--primary-color);
    }

    .accordion-button:focus {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        border-color: var(--primary-color);
    }

    .accordion-body {
        background: var(--card-light);
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 12px 12px;
    }

    body.dark-mode .accordion-body {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
    }

    .badge-tip {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        border-radius: 8px;
        padding: 4px 10px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    body.dark-mode .badge-tip {
        background: #064e3b;
        color: #a7f3d0;
        border-color: #10b981;
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

    body.dark-mode .alert-success {
        background: #064e3b;
        color: #a7f3d0;
        border-left-color: #10b981;
    }

    body.dark-mode .alert-danger {
        background: #7f1d1d;
        color: #fecaca;
        border-left-color: #ef4444;
    }

    .small-muted {
        font-size: .9rem;
        opacity: .8;
    }

    .contact-info {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        color: #fff;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 20px;
    }

    .contact-info h6 {
        color: #fff;
        margin-bottom: 15px;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .contact-item i {
        width: 20px;
        text-align: center;
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

    .fade-in-up {
        animation: fadeInUp .6s ease forwards;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .quick-contact .btn {
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }

        .contact-info {
            text-align: center;
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
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema"><i class="fas fa-moon"></i></button>
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

            <h1 class="page-title">Bantuan & Panduan</h1>

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

            <div class="row g-4">
                <!-- Kolom kiri: FAQ & Panduan -->
                <div class="col-lg-7">
                    <!-- Info Kontak -->
                    <div class="contact-info fade-in-up">
                        <h6><i class="fas fa-life-ring me-2"></i>Butuh Bantuan Cepat?</h6>
                        <div class="contact-item">
                            <i class="fab fa-whatsapp"></i>
                            <span>WhatsApp: <?= h(ADMIN_WHATSAPP); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>Email: <?= h(ADMIN_EMAIL); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <span>Waktu Respons: 1-2 jam kerja</span>
                        </div>
                    </div>

                    <!-- Pencarian FAQ -->
                    <div class="card-custom p-4 mb-4 fade-in-up">
                        <h5 class="mb-3"><i class="fas fa-search me-2"></i>Cari Pertanyaan</h5>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-magnifying-glass text-muted"></i></span>
                            <input id="faqSearch" type="text" class="form-control"
                                placeholder="Ketik kata kunci, contoh: login, dokter, janji, resep, profil">
                        </div>
                        <p class="small-muted mt-2 mb-0"><span class="badge-tip">Tip</span> Gunakan kata kunci singkat
                            untuk hasil lebih akurat.</p>
                    </div>

                    <!-- FAQ -->
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="mb-3"><i class="fas fa-circle-question me-2"></i>Pertanyaan yang Sering Diajukan</h5>
                        <div class="accordion" id="faqAccordion">
                            <!-- Item 1 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h1">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c1" aria-expanded="true" aria-controls="c1">
                                        Bagaimana cara memperbarui profil dan mengubah password?
                                    </button>
                                </h2>
                                <div id="c1" class="accordion-collapse collapse show" aria-labelledby="h1"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Buka <strong>Profil Saya</strong> dari dashboard pengguna lalu edit data pribadi
                                        dan simpan.
                                        Untuk mengubah kata sandi, gunakan bagian <em>Keamanan</em>, isi password lama,
                                        password baru, dan konfirmasi.
                                    </div>
                                </div>
                            </div>
                            <!-- Item 2 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c2" aria-controls="c2">
                                        Cara melihat daftar dokter dan melakukan konsultasi/ janji temu?
                                    </button>
                                </h2>
                                <div id="c2" class="accordion-collapse collapse" aria-labelledby="h2"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Masuk ke menu <strong>Konsultasi Dokter</strong> untuk melihat daftar dokter &
                                        spesialis.
                                        Untuk penjadwalan, gunakan <strong>Buat Janji Temu</strong>, pilih dokter,
                                        tanggal, dan deskripsi singkat.
                                    </div>
                                </div>
                            </div>
                            <!-- Item 3 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c3" aria-controls="c3">
                                        Di mana saya bisa melihat resep obat digital?
                                    </button>
                                </h2>
                                <div id="c3" class="accordion-collapse collapse" aria-labelledby="h3"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Buka menu <strong>Resep Digital</strong>. Anda dapat mencari resep berdasarkan
                                        dokter atau tanggal serta melihat detail dan instruksi penggunaan.
                                    </div>
                                </div>
                            </div>
                            <!-- Item 4 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h4">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c4" aria-controls="c4">
                                        Bagaimana mengelola dan memantau keluhan saya?
                                    </button>
                                </h2>
                                <div id="c4" class="accordion-collapse collapse" aria-labelledby="h4"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Masuk ke <strong>Riwayat Keluhan</strong> untuk menambah, mengedit, atau
                                        menghapus keluhan.
                                        Gunakan filter status (<em>Baru/Proses/Selesai</em>) dan fitur pencarian untuk
                                        menemukan data dengan cepat.
                                    </div>
                                </div>
                            </div>
                            <!-- Item 5 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h5">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c5" aria-controls="c5">
                                        Kenapa saya tidak bisa login?
                                    </button>
                                </h2>
                                <div id="c5" class="accordion-collapse collapse" aria-labelledby="h5"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Pastikan email & password benar (case-sensitive). Hapus cache/cookies browser,
                                        lalu coba lagi.
                                        Jika masalah berlanjut, hubungi admin untuk reset akun.
                                    </div>
                                </div>
                            </div>
                            <!-- Item 6 -->
                            <div class="accordion-item" data-faq>
                                <h2 class="accordion-header" id="h6">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#c6" aria-controls="c6">
                                        Bagaimana jika data saya tidak muncul/terlihat tidak akurat?
                                    </button>
                                </h2>
                                <div id="c6" class="accordion-collapse collapse" aria-labelledby="h6"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Muat ulang halaman dan cek koneksi internet. Jika tetap bermasalah, kirim
                                        laporan melalui formulir di sebelah kanan dengan menyertakan tangkapan layar
                                        (jika ada).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panduan Singkat -->
                    <div class="card-custom p-4 mt-4 fade-in-up">
                        <h5 class="mb-3"><i class="fas fa-book-open me-2"></i>Panduan Singkat</h5>
                        <ol class="mb-3">
                            <li>Masuk ke sistem menggunakan akun terdaftar.</li>
                            <li>Perbarui <strong>Profil</strong> Anda terlebih dulu (email & nomor HP aktif).</li>
                            <li>Telusuri <strong>Dokter</strong>, lalu gunakan <strong>Janji Temu</strong> untuk
                                menjadwalkan konsultasi.</li>
                            <li>Cek <strong>Resep Digital</strong> setelah konsultasi untuk petunjuk penggunaan obat.
                            </li>
                            <li>Pantau <strong>Riwayat Keluhan</strong> untuk progres penanganan.</li>
                        </ol>
                        <div class="d-flex gap-2 flex-wrap quick-contact">
                            <a id="waBtn" class="btn btn-success-custom" href="#" target="_blank" rel="noopener">
                                <i class="fab fa-whatsapp me-2"></i>Hubungi via WhatsApp
                            </a>
                            <a class="btn btn-primary-custom"
                                href="mailto:<?= h(ADMIN_EMAIL); ?>?subject=Butuh%20Bantuan%20Sistem%20Informasi%20Kesehatan">
                                <i class="fas fa-envelope me-2"></i>Kirim Email ke Admin
                            </a>
                            <button id="copyEmail" class="btn btn-outline-custom">
                                <i class="fas fa-copy me-2"></i>Salin Email Admin
                            </button>
                        </div>
                        <p class="small-muted mt-2 mb-0">Email admin: <code><?= h(ADMIN_EMAIL); ?></code></p>
                    </div>
                </div>

                <!-- Kolom kanan: Form Laporan -->
                <div class="col-lg-5">
                    <div class="card-custom p-4 fade-in-up">
                        <h5 class="mb-3"><i class="fas fa-headset me-2"></i>Kirim Laporan Kendala</h5>
                        <p class="small-muted">Gunakan formulir ini untuk melaporkan masalah teknis atau pertanyaan.
                            Balasan akan dikirim ke email Anda.</p>
                        <form method="post" novalidate>
                            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_bantuan']); ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama</label>
                                <input type="text" name="nama" class="form-control" required
                                    value="<?= h($_SESSION['nama']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" required
                                    placeholder="email@contoh.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Topik</label>
                                <select name="topik" class="form-select">
                                    <option>Umum</option>
                                    <option>Login / Akun</option>
                                    <option>Profil</option>
                                    <option>Dokter / Konsultasi</option>
                                    <option>Janji Temu</option>
                                    <option>Resep</option>
                                    <option>Riwayat Keluhan</option>
                                    <option>Lainnya</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pesan</label>
                                <textarea name="pesan" rows="5" class="form-control" required
                                    placeholder="Jelaskan kendala Anda..."></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="lapor" class="btn btn-primary-custom">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Laporan
                                </button>
                            </div>
                            <p class="small text-muted mt-2 mb-0">Dengan mengirim, Anda setuju data ini digunakan untuk
                                dukungan teknis.</p>
                        </form>
                    </div>

                    <!-- Kotak tips -->
                    <div class="card-custom p-4 mt-4 fade-in-up">
                        <h6 class="mb-2"><i class="fas fa-lightbulb me-2"></i>Tips Pemecahan Masalah Cepat</h6>
                        <ul class="mb-0">
                            <li>Muat ulang halaman (Ctrl/⌘+R) dan pastikan koneksi internet stabil.</li>
                            <li>Jika ada kesalahan input, cek kembali format email/angka, lalu kirim ulang.</li>
                            <li>Keluar masuk (logout-login) kembali untuk menyegarkan sesi.</li>
                            <li>Gunakan mode penyamaran (incognito) untuk menghindari cache bermasalah.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Dark mode toggle
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

    // FAQ live search
    const searchInput = document.getElementById('faqSearch');
    const items = [...document.querySelectorAll('[data-faq]')];
    searchInput?.addEventListener('input', () => {
        const q = (searchInput.value || '').toLowerCase();
        items.forEach(it => {
            const txt = it.textContent.toLowerCase();
            it.style.display = txt.includes(q) ? '' : 'none';
        });
    });

    // Quick contact: WhatsApp
    const waBtn = document.getElementById('waBtn');
    if (waBtn) {
        const number = "<?= h(ADMIN_WHATSAPP); ?>";
        const msg = encodeURIComponent("Halo Admin, saya butuh bantuan terkait Sistem Informasi Kesehatan.");
        const url = `https://wa.me/${number}?text=${msg}`;
        waBtn.href = url;
    }

    // Copy email admin
    const copyBtn = document.getElementById('copyEmail');
    copyBtn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText("<?= h(ADMIN_EMAIL); ?>");
            copyBtn.innerHTML = '<i class="fas fa-check me-2"></i>Tersalin!';
            setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy me-2"></i>Salin Email Admin', 1500);
        } catch (e) {
            alert('Gagal menyalin. Salin manual: <?= h(ADMIN_EMAIL); ?>');
        }
    });
    </script>
</body>

</html>