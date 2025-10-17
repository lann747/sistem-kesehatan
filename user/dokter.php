<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

// --- Params pencarian & filter ---
$q        = trim($_GET['q'] ?? '');
$spes     = trim($_GET['spesialis'] ?? '');
$sort     = $_GET['sort'] ?? 'nama_asc'; // nama_asc|nama_desc|spes_asc|spes_desc
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9; // jumlah kartu per halaman
$offset   = ($page - 1) * $per_page;

// Sanitasi pilihan sort (whitelist)
$sort_map = [
    'nama_asc'  => 'nama ASC',
    'nama_desc' => 'nama DESC',
    'spes_asc'  => 'spesialis ASC, nama ASC',
    'spes_desc' => 'spesialis DESC, nama ASC',
];
$order_sql = $sort_map[$sort] ?? $sort_map['nama_asc'];

// Ambil daftar spesialis (untuk dropdown)
$spesialis_list = [];
$res_spes = mysqli_query($conn, "SELECT DISTINCT spesialis FROM dokter ORDER BY spesialis ASC");
if ($res_spes) {
    while ($row = mysqli_fetch_assoc($res_spes)) {
        if ($row['spesialis'] !== null && $row['spesialis'] !== '') {
            $spesialis_list[] = $row['spesialis'];
        }
    }
}

// Helper untuk membuat pola LIKE
function likePattern($s) {
    return '%' . str_replace(['%', '_'], ['\%','\_'], $s) . '%';
}

// --- Hitung total data (untuk pagination) ---
$where = "WHERE 1=1";
$params = [];
$types  = "";

if ($q !== '') {
    $where .= " AND (nama LIKE ? OR spesialis LIKE ? OR alamat LIKE ? OR no_hp LIKE ?)";
    $like = likePattern($q);
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "ssss";
}

if ($spes !== '') {
    $where .= " AND spesialis = ?";
    $params[] = $spes;
    $types   .= "s";
}

$sql_count = "SELECT COUNT(*) AS total FROM dokter $where";
$stmt_cnt = mysqli_prepare($conn, $sql_count);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
}
mysqli_stmt_execute($stmt_cnt);
$result_cnt = mysqli_stmt_get_result($stmt_cnt);
$total_rows = ($result_cnt && ($r = mysqli_fetch_assoc($result_cnt))) ? (int)$r['total'] : 0;
mysqli_stmt_close($stmt_cnt);

$total_pages = max(1, (int)ceil($total_rows / $per_page));

// --- Ambil data dokter (paged) ---
$sql_data = "SELECT id, nama, spesialis, no_hp, alamat
             FROM dokter
             $where
             ORDER BY $order_sql
             LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql_data);

// Bind param dinamis (+ limit & offset integer)
if ($types !== "") {
    $bind_types = $types . "ii";
    $params_with_limit = [...$params, $per_page, $offset];
    mysqli_stmt_bind_param($stmt, $bind_types, ...$params_with_limit);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $per_page, $offset);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Utility: buat link tel/wa secara aman
function tel_link($no) {
    $no = trim($no ?? '');
    if ($no === '') return '';
    $tel = preg_replace('/\s+/', '', $no);
    return "tel:" . htmlspecialchars($tel, ENT_QUOTES);
}
function wa_link($no) {
    $no = trim($no ?? '');
    if ($no === '') return '';
    // Hapus semua non-digit kecuali leading +
    if ($no[0] === '+') {
        $digits = '+' . preg_replace('/\D+/', '', substr($no,1));
    } else {
        $digits = preg_replace('/\D+/', '', $no);
    }
    if (strlen(preg_replace('/\D+/', '', $digits)) < 8) return ''; // minimal wajar
    // WhatsApp menganjurkan tanpa tanda plus di wa.me
    $wa = ltrim($digits, '+');
    return "https://wa.me/" . htmlspecialchars($wa, ENT_QUOTES);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Dokter - Sistem Informasi Kesehatan</title>
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
        width: 90px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px;
    }

    .card-doc {
        background: var(--card-light);
        border: none;
        border-radius: 16px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        transition: all .3s ease;
        height: 100%;
        border-left: 4px solid var(--primary-color);
    }

    body.dark-mode .card-doc {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
    }

    .card-doc:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, .15);
    }

    .doc-icon {
        font-size: 2.2rem;
        color: var(--primary-color);
    }

    .badge-sp {
        background: #e0ecff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    body.dark-mode .badge-sp {
        background: #1e3a8a;
        color: #bfdbfe;
        border-color: #1e40af;
    }

    .search-panel {
        background: var(--card-light);
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 5px 16px rgba(0, 0, 0, .06);
    }

    body.dark-mode .search-panel {
        background: var(--card-dark);
        box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
    }

    .btn-primary-custom {
        background: var(--primary-color);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 10px 16px;
        border-radius: 10px;
        transition: all .2s ease;
    }

    .btn-primary-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .btn-outline-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
        padding: 8px 14px;
        border-radius: 10px;
        transition: all .2s ease;
    }

    .btn-outline-custom:hover {
        background: var(--primary-color);
        color: #fff;
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
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat"></i> Sistem Kesehatan
            </a>
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="theme-toggle" title="Ganti Tema"><i class="fas fa-moon"></i></button>
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($_SESSION['nama']); ?> (User)</span>
                </div>
                <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <div class="main-content">
        <div class="container">
            <h1 class="page-title">Daftar Dokter</h1>

            <!-- Pencarian & Filter -->
            <div class="search-panel mb-4 fade-in-up">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label mb-1">Cari</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control"
                                placeholder="Nama, spesialis, alamat, no HP"
                                value="<?= htmlspecialchars($q, ENT_QUOTES); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Spesialis</label>
                        <select name="spesialis" class="form-select">
                            <option value="">Semua spesialis</option>
                            <?php foreach ($spesialis_list as $s): ?>
                            <option value="<?= htmlspecialchars($s, ENT_QUOTES); ?>"
                                <?= $spes === $s ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($s); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Urutkan</label>
                        <select name="sort" class="form-select">
                            <option value="nama_asc" <?= $sort==='nama_asc'?'selected':'';  ?>>Nama A → Z</option>
                            <option value="nama_desc" <?= $sort==='nama_desc'?'selected':''; ?>>Nama Z → A</option>
                            <option value="spes_asc" <?= $sort==='spes_asc'?'selected':'';  ?>>Spesialis A → Z</option>
                            <option value="spes_desc" <?= $sort==='spes_desc'?'selected':''; ?>>Spesialis Z → A</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary-custom w-100" type="submit">
                            <i class="fas fa-filter me-1"></i> Terapkan
                        </button>
                        <a class="btn btn-outline-custom w-100" href="dokter.php">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Grid Dokter -->
            <div class="row g-4">
                <?php if ($total_rows === 0): ?>
                <div class="col-12">
                    <div class="alert alert-info border-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Belum ada data dokter yang cocok dengan pencarian/filter.
                    </div>
                </div>
                <?php else: ?>
                <?php while ($d = mysqli_fetch_assoc($res)): ?>
                <div class="col-md-6 col-lg-4 fade-in-up">
                    <div class="card card-doc p-3 h-100">
                        <div class="d-flex align-items-start gap-3">
                            <div class="doc-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1 fw-semibold"><?= htmlspecialchars($d['nama']); ?></h5>
                                <div class="mb-2">
                                    <span class="badge badge-sp">
                                        <i class="fas fa-stethoscope me-1"></i>
                                        <?= htmlspecialchars($d['spesialis']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($d['no_hp'])): ?>
                                <div class="small text-muted mb-1">
                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($d['no_hp']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($d['alamat']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <?php if (!empty($d['no_hp'])): ?>
                            <a class="btn btn-sm btn-success flex-fill" href="<?= tel_link($d['no_hp']); ?>">
                                <i class="fas fa-phone-alt me-1"></i> Telpon
                            </a>
                            <?php $wa = wa_link($d['no_hp']); if ($wa): ?>
                            <a class="btn btn-sm btn-primary flex-fill" target="_blank" rel="noopener"
                                href="<?= $wa; ?>">
                                <i class="fab fa-whatsapp me-1"></i> WhatsApp
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4 d-flex justify-content-center">
                <ul class="pagination">
                    <?php
                        // Helper untuk mempertahankan query string lain saat pindah halaman
                        function build_qs($overrides = []) {
                            $base = $_GET;
                            foreach ($overrides as $k=>$v) { $base[$k] = $v; }
                            return '?' . http_build_query($base);
                        }
                        $prev = max(1, $page-1);
                        $next = min($total_pages, $page+1);
                        ?>
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="<?= $page<=1?'#':build_qs(['page'=>$prev]); ?>">&laquo;</a>
                    </li>
                    <?php
                        // Tampilkan range kecil yang ramah
                        $start = max(1, $page-2);
                        $end   = min($total_pages, $page+2);
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="'.build_qs(['page'=>1]).'">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        }
                        for ($i=$start; $i<=$end; $i++) {
                            $active = $i==$page ? 'active' : '';
                            echo '<li class="page-item '.$active.'"><a class="page-link" href="'.build_qs(['page'=>$i]).'">'.$i.'</a></li>';
                        }
                        if ($end < $total_pages) {
                            if ($end < $total_pages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="'.build_qs(['page'=>$total_pages]).'">'.$total_pages.'</a></li>';
                        }
                        ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link"
                            href="<?= $page>=$total_pages?'#':build_qs(['page'=>$next]); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
            <p class="text-center small text-muted mt-2">
                Menampilkan <?= min($per_page, max(0, $total_rows - $offset)); ?> dari <?= $total_rows; ?> dokter.
            </p>
            <?php endif; ?>

            <!-- Back -->
            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-custom">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
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
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
?>