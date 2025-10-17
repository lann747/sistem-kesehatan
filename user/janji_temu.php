<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['id'];

// --- Pastikan tabel janji_temu ada (migrasi ringan otomatis) ---
$createSql = "
CREATE TABLE IF NOT EXISTS janji_temu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dokter_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    keluhan TEXT NULL,
    status ENUM('menunggu','dikonfirmasi','dibatalkan','selesai') NOT NULL DEFAULT 'menunggu',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_dokter_waktu (dokter_id, tanggal, jam),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
mysqli_query($conn, $createSql);

// --- Ambil daftar dokter (untuk dropdown) ---
$doctors = [];
$resDoc = mysqli_query($conn, "SELECT id, nama, spesialis, no_hp FROM dokter ORDER BY nama ASC");
if ($resDoc) {
    while ($r = mysqli_fetch_assoc($resDoc)) $doctors[] = $r;
}

// --- Helpers ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function is_future_slot($dateStr, $timeStr) {
    $dateStr = trim($dateStr); $timeStr = trim($timeStr);
    if (!$dateStr || !$timeStr) return false;
    $slotTs = strtotime($dateStr . ' ' . $timeStr);
    if ($slotTs === false) return false;
    // Toleransi 59 detik agar tidak ada edge case server time skew
    return $slotTs >= (time() - 59);
}
function valid_time_range($timeStr) {
    // Izinkan slot antara 07:00 - 20:00
    return ($timeStr >= '07:00' && $timeStr <= '20:00');
}
function wa_link($no) {
    $no = trim($no ?? '');
    if ($no === '') return '';
    if ($no[0] === '+') $digits = '+' . preg_replace('/\D+/', '', substr($no,1));
    else $digits = preg_replace('/\D+/', '', $no);
    if (strlen(preg_replace('/\D+/', '', $digits)) < 8) return '';
    return 'https://wa.me/' . ltrim($digits, '+');
}

// --- Handle buat janji ---
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat'])) {
    $dokter_id = (int)($_POST['dokter_id'] ?? 0);
    $tanggal   = trim($_POST['tanggal'] ?? '');
    $jam       = trim($_POST['jam'] ?? '');
    $keluhan   = trim($_POST['keluhan'] ?? '');

    // Validasi dasar
    if ($dokter_id <= 0 || $tanggal === '' || $jam === '') {
        $msg_error = 'Semua field wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $msg_error = 'Format tanggal tidak valid.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $jam)) {
        $msg_error = 'Format jam tidak valid.';
    } elseif (!is_future_slot($tanggal, $jam)) {
        $msg_error = 'Tanggal/jam tidak boleh di masa lalu.';
    } elseif (!valid_time_range($jam)) {
        $msg_error = 'Jam konsultasi hanya antara 07:00 - 20:00.';
    } else {
        // Cek dokter ada
        $cekDok = mysqli_prepare($conn, "SELECT COUNT(*) FROM dokter WHERE id = ?");
        mysqli_stmt_bind_param($cekDok, "i", $dokter_id);
        mysqli_stmt_execute($cekDok);
        $r = mysqli_stmt_get_result($cekDok);
        $dokExists = ($r && mysqli_fetch_row($r)[0] > 0);
        mysqli_stmt_close($cekDok);

        if (!$dokExists) {
            $msg_error = 'Dokter tidak ditemukan.';
        } else {
            // Cek double-booking dokter di slot tsb (kecuali yang dibatalkan)
            $cek = mysqli_prepare($conn, "SELECT COUNT(*) FROM janji_temu WHERE dokter_id=? AND tanggal=? AND jam=? AND status <> 'dibatalkan'");
            mysqli_stmt_bind_param($cek, "iss", $dokter_id, $tanggal, $jam);
            mysqli_stmt_execute($cek);
            $rc = mysqli_stmt_get_result($cek);
            $isTaken = ($rc && mysqli_fetch_row($rc)[0] > 0);
            mysqli_stmt_close($cek);

            if ($isTaken) {
                $msg_error = 'Slot waktu tersebut sudah terisi. Silakan pilih jam lain.';
            } else {
                // Simpan janji
                $ins = mysqli_prepare($conn, "INSERT INTO janji_temu (user_id, dokter_id, tanggal, jam, keluhan, status) VALUES (?,?,?,?,?, 'menunggu')");
                mysqli_stmt_bind_param($ins, "iisss", $user_id, $dokter_id, $tanggal, $jam, $keluhan);
                if (mysqli_stmt_execute($ins)) {
                    $msg_success = 'Janji temu berhasil dibuat! Status saat ini: MENUNGGU.';
                } else {
                    $msg_error = 'Gagal membuat janji temu. Coba lagi.';
                }
                mysqli_stmt_close($ins);
            }
        }
    }
}

// --- Handle batalkan janji (milik user sendiri & masih mendatang) ---
if (isset($_GET['batal'])) {
    $jid = (int)$_GET['batal'];

    // Ambil data janji
    $q = mysqli_prepare($conn, "SELECT tanggal, jam, status FROM janji_temu WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($q, "ii", $jid, $user_id);
    mysqli_stmt_execute($q);
    $resJ = mysqli_stmt_get_result($q);
    $rowJ = $resJ ? mysqli_fetch_assoc($resJ) : null;
    mysqli_stmt_close($q);

    if ($rowJ) {
        $bolehBatal = in_array($rowJ['status'], ['menunggu','dikonfirmasi'], true) && is_future_slot($rowJ['tanggal'], $rowJ['jam']);
        if ($bolehBatal) {
            $u = mysqli_prepare($conn, "UPDATE janji_temu SET status='dibatalkan' WHERE id=? AND user_id=?");
            mysqli_stmt_bind_param($u, "ii", $jid, $user_id);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);
            $msg_success = 'Janji temu berhasil dibatalkan.';
        } else {
            $msg_error = 'Janji temu tidak bisa dibatalkan (sudah lewat/selesai/dibatalkan).';
        }
    } else {
        $msg_error = 'Data janji temu tidak ditemukan.';
    }
}

// --- Pagination daftar janji user ---
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset   = ($page - 1) * $per_page;

// Hitung total
$cnt = mysqli_prepare($conn, "SELECT COUNT(*) FROM janji_temu WHERE user_id=?");
mysqli_stmt_bind_param($cnt, "i", $user_id);
mysqli_stmt_execute($cnt);
$resCnt = mysqli_stmt_get_result($cnt);
$total  = $resCnt ? (int)mysqli_fetch_row($resCnt)[0] : 0;
mysqli_stmt_close($cnt);

$total_pages = max(1, (int)ceil($total / $per_page));

// Ambil data janji + join dokter
$list = [];
$sql = "
SELECT j.id, j.tanggal, j.jam, j.keluhan, j.status,
       d.nama AS dokter_nama, d.spesialis, d.no_hp
FROM janji_temu j
JOIN dokter d ON d.id = j.dokter_id
WHERE j.user_id = ?
ORDER BY j.tanggal DESC, j.jam DESC
LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $per_page, $offset);
mysqli_stmt_execute($stmt);
$resList = mysqli_stmt_get_result($stmt);
if ($resList) while ($r = mysqli_fetch_assoc($resList)) $list[] = $r;
mysqli_stmt_close($stmt);

// --- Badge status ---
function status_badge($s) {
    $map = [
        'menunggu'     => ['bg' => '#f59e0b', 'text' => 'Menunggu'],
        'dikonfirmasi' => ['bg' => '#3b82f6', 'text' => 'Dikonfirmasi'],
        'selesai'      => ['bg' => '#10b981', 'text' => 'Selesai'],
        'dibatalkan'   => ['bg' => '#ef4444', 'text' => 'Dibatalkan'],
    ];
    $x = $map[$s] ?? ['bg' => '#6b7280', 'text' => $s];
    return '<span class="badge-status" style="background:'.$x['bg'].'">'.$x['text'].'</span>';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Janji Temu - Sistem Informasi Kesehatan</title>
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
        transition: all .3s ease;
        min-height: 100vh;
    }

    body.dark-mode {
        background-color: var(--dark-bg);
        color: var(--text-light);
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 2px 15px rgba(0, 0, 0, .1);
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
        margin-right: 10px;
    }

    .theme-toggle:hover {
        background: rgba(255, 255, 255, .3);
        transform: rotate(20deg);
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
        gap: 5px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, .3);
        color: #fff;
        transform: translateY(-1px);
    }

    .main-content {
        padding: 30px 0;
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
        width: 110px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    .card-custom {
        background: var(--card-light);
        border: none;
        border-radius: 16px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        border-left: 4px solid var(--primary-color);
    }

    body.dark-mode .card-custom {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
    }

    .card-custom:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, .14);
    }

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 11px 12px;
        background: var(--card-light);
        color: var(--text-dark);
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background: var(--card-dark);
        border-color: #374151;
        color: var(--text-light);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 11px 16px;
        border-radius: 12px;
        transition: all .2s ease;
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .table-custom thead th {
        background: #3b82f6;
        color: #fff;
        border: none;
    }

    .table-custom tbody td {
        vertical-align: middle;
    }

    .badge-status {
        display: inline-block;
        color: #fff;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .8rem;
    }

    .badge-sp {
        background: #e0ecff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 3px 8px;
    }

    .action-link {
        text-decoration: none;
    }

    .action-link:hover {
        text-decoration: underline;
    }

    .pagination .page-link {
        border-radius: 8px;
        border: none;
        margin: 0 4px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, .06);
    }

    .fade-in-up {
        animation: fadeInUp .6s ease forwards;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(18px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
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
                    <i class="fas fa-user"></i>
                    <span><?= h($_SESSION['nama']); ?> (User)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <h1 class="page-title">Buat Janji Temu</h1>

            <?php if ($msg_success): ?>
            <div class="alert alert-success border-0"><?= h($msg_success); ?></div>
            <?php endif; ?>
            <?php if ($msg_error): ?>
            <div class="alert alert-danger border-0"><?= h($msg_error); ?></div>
            <?php endif; ?>

            <!-- Form Buat Janji -->
            <div class="card-custom p-4 mb-4 fade-in-up">
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Pilih Dokter</label>
                        <select name="dokter_id" class="form-select" required>
                            <option value="">— Pilih —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int)$d['id']; ?>">
                                <?= h($d['nama']); ?> (<?= h($d['spesialis']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pastikan spesialis sesuai keluhan ya.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required min="<?= date('Y-m-d'); ?>">
                        <div class="form-text">Tidak bisa pilih tanggal yang sudah lewat.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jam</label>
                        <input type="time" name="jam" class="form-control" required min="07:00" max="20:00" step="1800">
                        <div class="form-text">Jam praktik: 07:00–20:00 (kelipatan 30 menit disarankan).</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keluhan (opsional)</label>
                        <textarea name="keluhan" class="form-control" rows="3"
                            placeholder="Ceritakan singkat keluhan Anda..."></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" name="buat" class="btn btn-primary-custom">
                            <i class="fas fa-calendar-check me-1"></i> Buat Janji
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>

            <!-- Daftar Janji Saya -->
            <div class="card-custom p-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i> Janji Temu Saya</h5>

                <?php if ($total === 0): ?>
                <div class="alert alert-info border-0">
                    <i class="fas fa-info-circle me-2"></i> Belum ada janji temu.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th style="width: 46px;">#</th>
                                <th>Dokter</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Status</th>
                                <th>Kontak</th>
                                <th>Keluhan</th>
                                <th style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                        $no = $offset + 1;
                        foreach ($list as $row):
                            $future = is_future_slot($row['tanggal'], $row['jam']);
                            $canCancel = $future && in_array($row['status'], ['menunggu','dikonfirmasi'], true);
                            $wa = wa_link($row['no_hp']);
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td>
                                    <div class="fw-semibold"><?= h($row['dokter_nama']); ?></div>
                                    <div class="badge-sp mt-1"><i
                                            class="fas fa-stethoscope me-1"></i><?= h($row['spesialis']); ?></div>
                                </td>
                                <td><?= date('d M Y', strtotime($row['tanggal'])); ?></td>
                                <td><?= substr($row['jam'],0,5); ?></td>
                                <td><?= status_badge($row['status']); ?></td>
                                <td>
                                    <?php if (!empty($row['no_hp'])): ?>
                                    <div class="small"><i class="fas fa-phone me-1"></i><?= h($row['no_hp']); ?></div>
                                    <?php if ($wa): ?>
                                    <a class="action-link small" href="<?= h($wa); ?>" target="_blank" rel="noopener">
                                        <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= h($row['keluhan'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($canCancel): ?>
                                    <a class="btn btn-sm btn-outline-danger" href="?batal=<?= h($row['id']); ?>"
                                        onclick="return confirm('Batalkan janji temu ini?');">
                                        <i class="fas fa-times me-1"></i> Batal
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-3 d-flex justify-content-center">
                    <ul class="pagination">
                        <?php
                    $build = function($p){ $qs = $_GET; $qs['page']=$p; return '?' . http_build_query($qs); };
                    $prev = max(1, $page-1); $next = min($total_pages, $page+1);
                    ?>
                        <li class="page-item <?= $page<=1?'disabled':''; ?>">
                            <a class="page-link" href="<?= $page<=1?'#':$build($prev); ?>">&laquo;</a>
                        </li>
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
                        <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                            <a class="page-link" href="<?= $page>=$total_pages?'#':$build($next); ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
                <p class="text-center small text-muted mt-1">
                    Menampilkan <?= min($per_page, max(0, $total - $offset)); ?> dari <?= $total; ?> janji.
                </p>
                <?php endif; ?>
                <?php endif; ?>
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

    // Client-side guard: tanggal & jam
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.querySelector('input[name="tanggal"]');
        const timeInput = document.querySelector('input[name="jam"]');
        if (dateInput) {
            dateInput.min = new Date().toISOString().slice(0, 10);
        }
        if (timeInput) {
            timeInput.addEventListener('change', () => {
                const v = timeInput.value;
                if (v && (v < '07:00' || v > '20:00')) {
                    alert('Jam praktik 07:00–20:00');
                    timeInput.value = '';
                }
            });
        }
    });
    </script>
</body>

</html>