<?php
session_start();
include '../config/db.php';

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['id'];


/* ============================================================
   Helper
   ============================================================ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function wa_link($no) {
    $no = trim($no ?? '');
    if ($no === '') return '';
    if ($no[0] === '+') $digits = '+' . preg_replace('/\D+/', '', substr($no,1));
    else $digits = preg_replace('/\D+/', '', $no);
    if (strlen(preg_replace('/\D+/', '', $digits)) < 8) return '';
    return 'https://wa.me/' . ltrim($digits, '+');
}
function status_badge($s){
    $m = [
        'baru'   => ['#10b981','Baru'],
        'tebus'  => ['#f59e0b','Perlu Tebus'],
        'selesai'=> ['#3b82f6','Selesai'],
    ];
    $x = $m[$s] ?? ['#6b7280',$s];
    return '<span class="badge-status" style="background:'.$x[0].'">'.$x[1].'</span>';
}

/* ============================================================
   Ambil daftar dokter untuk dropdown
   ============================================================ */
$doctors = [];
$resDoc = mysqli_query($conn, "SELECT id, nama, spesialis, no_hp FROM dokter ORDER BY nama ASC");
if ($resDoc) while ($r = mysqli_fetch_assoc($resDoc)) $doctors[] = $r;

/* ============================================================
   Handle: Tambah resep (milik user sendiri)
   ============================================================ */
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $dokter_id = (int)($_POST['dokter_id'] ?? 0);
    $tanggal   = trim($_POST['tanggal'] ?? '');
    $catatan   = trim($_POST['catatan'] ?? '');
    $status    = trim($_POST['status'] ?? 'baru');

    // Obat (array of rows)
    $nama_obat   = $_POST['nama_obat'] ?? [];
    $dosis       = $_POST['dosis'] ?? [];
    $aturan      = $_POST['aturan'] ?? [];

    if ($dokter_id <= 0 || $tanggal === '') {
        $msg_error = 'Dokter dan tanggal wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $msg_error = 'Format tanggal tidak valid.';
    } else {
        // Validasi dokter
        $cekDok = mysqli_prepare($conn, "SELECT COUNT(*) FROM dokter WHERE id=?");
        mysqli_stmt_bind_param($cekDok, "i", $dokter_id);
        mysqli_stmt_execute($cekDok);
        $r = mysqli_stmt_get_result($cekDok);
        $exists = ($r && mysqli_fetch_row($r)[0] > 0);
        mysqli_stmt_close($cekDok);

        if (!$exists) {
            $msg_error = 'Dokter tidak ditemukan.';
        } else {
            // Susun JSON obat
            $items = [];
            if (is_array($nama_obat)) {
                $n = count($nama_obat);
                for ($i=0; $i<$n; $i++) {
                    $nm = trim($nama_obat[$i] ?? '');
                    $ds = trim($dosis[$i] ?? '');
                    $at = trim($aturan[$i] ?? '');
                    if ($nm !== '') {
                        $items[] = ['nama'=>$nm, 'dosis'=>$ds, 'aturan'=>$at];
                    }
                }
            }
            $obatJson = json_encode($items, JSON_UNESCAPED_UNICODE);

            // Simpan
            $ins = mysqli_prepare($conn, "INSERT INTO resep (user_id, dokter_id, tanggal, obat, catatan, status) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, "iissss", $user_id, $dokter_id, $tanggal, $obatJson, $catatan, $status);
            if (mysqli_stmt_execute($ins)) {
                $msg_success = 'Resep berhasil ditambahkan.';
            } else {
                $msg_error = 'Gagal menyimpan resep. Coba lagi.';
            }
            mysqli_stmt_close($ins);
        }
    }
}

/* ============================================================
   Handle: Hapus resep (milik user sendiri)
   ============================================================ */
if (isset($_GET['hapus'])) {
    $rid = (int)$_GET['hapus'];
    $del = mysqli_prepare($conn, "DELETE FROM resep WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($del, "ii", $rid, $user_id);
    mysqli_stmt_execute($del);
    $affected = mysqli_stmt_affected_rows($del);
    mysqli_stmt_close($del);
    if ($affected > 0) {
        $msg_success = 'Resep berhasil dihapus.';
    } else {
        $msg_error = 'Gagal menghapus resep (data tidak ditemukan).';
    }
}

/* ============================================================
   Pagination daftar resep user
   ============================================================ */
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset   = ($page - 1) * $per_page;

$cnt = mysqli_prepare($conn, "SELECT COUNT(*) FROM resep WHERE user_id=?");
mysqli_stmt_bind_param($cnt, "i", $user_id);
mysqli_stmt_execute($cnt);
$resCnt = mysqli_stmt_get_result($cnt);
$total  = $resCnt ? (int)mysqli_fetch_row($resCnt)[0] : 0;
mysqli_stmt_close($cnt);

$total_pages = max(1, (int)ceil($total / $per_page));

$list = [];
$sql = "
SELECT r.id, r.tanggal, r.obat, r.catatan, r.status,
       d.nama AS dokter_nama, d.spesialis, d.no_hp
FROM resep r
JOIN dokter d ON d.id = r.dokter_id
WHERE r.user_id=?
ORDER BY r.tanggal DESC, r.id DESC
LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $per_page, $offset);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) while ($row = mysqli_fetch_assoc($res)) $list[] = $row;
mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resep Digital - Sistem Informasi Kesehatan</title>
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
        background-color: var(--light-bg);
        color: var(--text-dark);
        transition: var(--transition);
        min-height: 100vh;
        line-height: 1.6;
    }

    .navbar {
        background: var(--navbar-bg);
        border-bottom: 3px solid var(--primary-dark);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

    .main-content {
        padding: 40px 0;
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
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        border-left: 4px solid var(--primary-color);
        overflow: hidden;
    }

    .card-custom:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px;
        background: var(--card-light);
        color: var(--text-dark);
        transition: var(--transition);
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
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-success-custom:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .btn-danger-custom {
        background: #ef4444;
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 10px;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-danger-custom:hover {
        background: #dc2626;
        transform: translateY(-1px);
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

    .table-custom thead th {
        background: var(--primary-color);
        color: #fff;
        border: none;
        font-weight: 600;
        padding: 16px 12px;
    }

    .table-custom tbody td {
        vertical-align: middle;
        padding: 14px 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    .badge-status {
        display: inline-block;
        color: #fff;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 600;
    }

    .badge-sp {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        border-radius: 8px;
        padding: 4px 10px;
        font-weight: 600;
        font-size: 0.85rem;
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

    .fade-in-up {
        animation: fadeInUp .6s ease forwards;
    }

    .medicine-row {
        background: #f8fafc;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border: 1px solid #e2e8f0;
    }

    .medicine-row:hover {
        background: #f1f5f9;
    }

    .remove-row {
        transition: var(--transition);
    }

    .remove-row:hover {
        transform: scale(1.1);
    }

    .modal-header-custom {
        background: var(--primary-color);
        color: #fff;
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
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

    @media print {
        .no-print {
            display: none !important;
        }

        .print-area {
            padding: 20px;
        }

        body {
            background: #fff;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 20px 0;
        }

        .medicine-row .row {
            flex-direction: column;
            gap: 10px;
        }

        .medicine-row .col-md-1 {
            text-align: center;
        }

        .btn-primary-custom,
        .btn-outline-custom {
            width: 100%;
            justify-content: center;
        }

        .table-responsive {
            font-size: 0.9rem;
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg no-print">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat"></i> Rafflesia Sehat</a>
            <div class="d-flex align-items-center">
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
            <h1 class="page-title">Resep Digital</h1>

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

            <!-- Form Tambah Resep -->
            <div class="card-custom p-4 mb-4 fade-in-up no-print">
                <h5 class="mb-3"><i class="fas fa-prescription-bottle-alt me-2"></i> Tambah Resep</h5>
                <form method="post" id="formResep" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Dokter</label>
                        <select name="dokter_id" class="form-select" required>
                            <option value="">— Pilih Dokter —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int)$d['id']; ?>">
                                <?= h($d['nama']); ?> (<?= h($d['spesialis']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih dokter penerbit resep</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tanggal Resep</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="baru">Baru</option>
                            <option value="tebus">Perlu Tebus</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Daftar Obat</label>
                        <div id="obatList" class="mb-3"></div>
                        <button type="button" id="addRow" class="btn btn-success-custom">
                            <i class="fas fa-plus me-1"></i>Tambah Obat
                        </button>
                        <div class="form-text">Isi nama obat, dosis, dan aturan pakai</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Catatan (opsional)</label>
                        <textarea name="catatan" class="form-control" rows="3"
                            placeholder="Contoh: Alergi obat tertentu, minum setelah makan..."></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" name="tambah" class="btn btn-primary-custom">
                            <i class="fas fa-save me-1"></i> Simpan Resep
                        </button>
                        <a href="index.php" class="btn btn-outline-custom">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>

            <!-- Daftar Resep -->
            <div class="card-custom p-4 fade-in-up">
                <h5 class="mb-3"><i class="fas fa-file-medical me-2"></i> Daftar Resep Saya</h5>

                <?php if ($total === 0): ?>
                <div class="alert alert-info alert-custom">
                    <i class="fas fa-info-circle me-2"></i> Belum ada resep.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th style="width:46px;">#</th>
                                <th>Tanggal</th>
                                <th>Dokter</th>
                                <th>Status</th>
                                <th>Kontak</th>
                                <th>Obat (ringkas)</th>
                                <th style="width:160px;" class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                        $no = $offset + 1;
                        foreach ($list as $row):
                            $obat = [];
                            if (!empty($row['obat'])) {
                                $decoded = json_decode($row['obat'], true);
                                if (is_array($decoded)) $obat = $decoded;
                            }
                            $ringkas = [];
                            foreach ($obat as $it) {
                                if (!empty($it['nama'])) $ringkas[] = $it['nama'];
                                if (count($ringkas) >= 3) break;
                            }
                            $ringkas_text = $ringkas ? implode(', ', $ringkas) . (count($obat) > 3 ? '…' : '') : '-';
                            $wa = wa_link($row['no_hp']);
                        ?>
                            <tr>
                                <td class="fw-semibold"><?= $no++; ?></td>
                                <td><?= date('d M Y', strtotime($row['tanggal'])); ?></td>
                                <td>
                                    <div class="fw-semibold"><?= h($row['dokter_nama']); ?></div>
                                    <div class="badge-sp mt-1"><i
                                            class="fas fa-stethoscope me-1"></i><?= h($row['spesialis']); ?></div>
                                </td>
                                <td><?= status_badge($row['status']); ?></td>
                                <td>
                                    <?php if (!empty($row['no_hp'])): ?>
                                    <div class="small"><i class="fas fa-phone me-1"></i><?= h($row['no_hp']); ?></div>
                                    <?php if ($wa): ?>
                                    <a class="small action-link" href="<?= h($wa); ?>" target="_blank" rel="noopener"><i
                                            class="fab fa-whatsapp me-1"></i>WhatsApp</a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= h($ringkas_text); ?></td>
                                <td class="no-print">
                                    <div class="d-flex gap-1">
                                        <!-- Detail -->
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#detailModal"
                                            data-resep='<?= h(json_encode($row), ENT_QUOTES); ?>'>
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </button>
                                        <!-- Cetak -->
                                        <button class="btn btn-sm btn-outline-success"
                                            onclick='printResep(<?= json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>
                                            <i class="fas fa-print me-1"></i> Cetak
                                        </button>
                                        <!-- Hapus -->
                                        <a class="btn btn-sm btn-outline-danger" href="?hapus=<?= h($row['id']); ?>"
                                            onclick="return confirm('Hapus resep ini?');">
                                            <i class="fas fa-trash me-1"></i> Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4 d-flex justify-content-center no-print">
                    <ul class="pagination">
                        <?php
                    $build = function($p){ $qs = $_GET; $qs['page']=$p; return '?' . http_build_query($qs); };
                    $prev = max(1, $page-1); $next = min($total_pages, $page+1);
                    ?>
                        <li class="page-item <?= $page<=1?'disabled':''; ?>"><a class="page-link"
                                href="<?= $page<=1?'#':$build($prev); ?>">&laquo;</a></li>
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
                        <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>"><a class="page-link"
                                href="<?= $page>=$total_pages?'#':$build($next); ?>">&raquo;</a></li>
                    </ul>
                </nav>
                <p class="text-center small text-muted mt-2 no-print">
                    Menampilkan <?= min($per_page, max(0, $total - $offset)); ?> dari <?= $total; ?> resep.
                </p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-prescription me-2"></i>Detail Resep</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailBody"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary-custom" id="btnCetakModal">
                        <i class="fas fa-print me-1"></i>Cetak
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Area cetak tersembunyi -->
    <div id="printArea" class="print-area" style="display:none;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Dinamis: baris obat
    const list = document.getElementById('obatList');
    const addRowBtn = document.getElementById('addRow');

    function rowTpl() {
        const wrap = document.createElement('div');
        wrap.className = 'medicine-row';
        wrap.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="nama_obat[]" class="form-control" placeholder="Nama obat" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="dosis[]" class="form-control" placeholder="Dosis (misal: 500 mg)">
                </div>
                <div class="col-md-4">
                    <input type="text" name="aturan[]" class="form-control" placeholder="Aturan pakai (misal: 3x1)">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-danger-custom remove-row">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>`;
        return wrap;
    }

    function addRow() {
        list.appendChild(rowTpl());
    }
    addRowBtn?.addEventListener('click', addRow);
    list?.addEventListener('click', (e) => {
        if (e.target.closest('.remove-row')) {
            const r = e.target.closest('.medicine-row');
            if (r) r.remove();
        }
    });
    // Tambahkan 1 baris default
    addRow();

    // Modal detail
    const detailModal = document.getElementById('detailModal');
    const detailBody = document.getElementById('detailBody');
    const btnCetakModal = document.getElementById('btnCetakModal');
    let lastResep = null;

    detailModal?.addEventListener('show.bs.modal', (ev) => {
        const btn = ev.relatedTarget;
        try {
            const data = JSON.parse(btn.getAttribute('data-resep'));
            lastResep = data;
            // Render detail
            const obat = data.obat ? JSON.parse(data.obat) : [];
            let rows = '';
            if (Array.isArray(obat) && obat.length) {
                rows = obat.map((o, i) => `
                    <tr>
                        <td class="fw-semibold">${i+1}</td>
                        <td>${(o.nama||'')}</td>
                        <td>${(o.dosis||'')}</td>
                        <td>${(o.aturan||'')}</td>
                    </tr>
                `).join('');
            } else {
                rows = `<tr><td colspan="4" class="text-muted text-center">Tidak ada data obat.</td></tr>`;
            }
            detailBody.innerHTML = `
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Tanggal:</strong> ${new Date(data.tanggal).toLocaleDateString('id-ID')}
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> ${statusBadgeHtml(data.status)}
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Dokter:</strong> ${data.dokter_nama} 
                    <span class="badge-sp ms-2">${data.spesialis}</span>
                </div>
                ${data.catatan ? `
                <div class="mb-3">
                    <strong>Catatan:</strong> 
                    <div class="mt-1 p-2 bg-light rounded">${escapeHtml(data.catatan)}</div>
                </div>` : ''}
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width:46px">#</th>
                                <th>Nama Obat</th>
                                <th>Dosis</th>
                                <th>Aturan Pakai</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            detailBody.innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
        }
    });

    btnCetakModal?.addEventListener('click', () => {
        if (lastResep) printResep(lastResep);
    });

    // Cetak
    function printResep(data) {
        const area = document.getElementById('printArea');
        const obat = data.obat ? JSON.parse(data.obat) : [];
        const rows = (Array.isArray(obat) && obat.length) ?
            obat.map((o, i) =>
                `<tr><td>${i+1}</td><td>${escapeHtml(o.nama||'')}</td><td>${escapeHtml(o.dosis||'')}</td><td>${escapeHtml(o.aturan||'')}</td></tr>`
            ).join('') :
            `<tr><td colspan="4" class="text-muted text-center">Tidak ada data obat.</td></tr>`;

        area.innerHTML = `
            <div style="max-width:900px;margin:0 auto;font-family:Inter,Arial,sans-serif;">
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #10b981;padding-bottom:12px;margin-bottom:16px">
                    <div style="font-weight:700;font-size:24px;color:#065f46">Resep Digital</div>
                    <div style="color:#6b7280;font-size:12px">Dicetak: ${new Date().toLocaleString('id-ID')}</div>
                </div>
                <div style="margin-bottom:12px"><strong>Tanggal:</strong> ${new Date(data.tanggal).toLocaleDateString('id-ID')}</div>
                <div style="margin-bottom:12px"><strong>Dokter:</strong> ${escapeHtml(data.dokter_nama)} (${escapeHtml(data.spesialis)})</div>
                ${data.catatan ? `<div style="margin-bottom:12px"><strong>Catatan:</strong> ${escapeHtml(data.catatan)}</div>` : ''}
                <table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;margin-bottom:16px">
                    <thead>
                        <tr style="background:#d1fae5">
                            <th style="border:1px solid #e5e7eb;padding:12px;width:46px;text-align:left">#</th>
                            <th style="border:1px solid #e5e7eb;padding:12px;text-align:left">Nama Obat</th>
                            <th style="border:1px solid #e5e7eb;padding:12px;text-align:left">Dosis</th>
                            <th style="border:1px solid #e5e7eb;padding:12px;text-align:left">Aturan Pakai</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div style="margin-top:20px;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb;padding-top:8px">
                    *Dokumen ini bersifat informasi. Ikuti arahan dokter & baca etiket apotek.
                </div>
            </div>`;
        const w = window.open('', '_blank', 'width=900,height=700');
        w.document.write('<html><head><title>Cetak Resep</title></head><body>' + area.innerHTML + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
        setTimeout(() => w.close(), 300);
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [m]));
    }

    function statusBadgeHtml(s) {
        const map = {
            baru: '#10b981',
            tebus: '#f59e0b',
            selesai: '#3b82f6'
        };
        const label = {
            baru: 'Baru',
            tebus: 'Perlu Tebus',
            selesai: 'Selesai'
        };
        const color = map[s] || '#6b7280';
        const text = label[s] || s;
        return `<span style="display:inline-block;color:#fff;background:${color};padding:6px 12px;border-radius:20px;font-size:.85rem;font-weight:600">${text}</span>`;
    }
    </script>
</body>

</html>