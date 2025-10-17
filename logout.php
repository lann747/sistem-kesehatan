<?php
// ---- Aman: matikan cache agar halaman terlindungi saat back button ----
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// (Opsional) Minta browser menghapus data sisi-klien yang umum
header('Clear-Site-Data: "cache","cookies","storage"');

// ---- Mulai sesi untuk bisa menghapusnya ----
session_start();

// Hapus semua variabel sesi
$_SESSION = [];

// Hapus cookie sesi di browser (jika ada)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // atur kedaluwarsa mundur supaya dihapus browser
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// Hancurkan sesi di server
session_destroy();

// (Opsional) putuskan ID sesi lama (defensif)
if (function_exists('session_create_id')) {
    session_id('');
}

// Redirect ke halaman login dengan indikator sukses logout
header('Location: login.php?logged_out=1', true, 303);
exit;