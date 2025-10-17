<?php
// Pastikan halaman logout tidak bisa di-cache (mencegah akses via tombol Back)
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Mulai sesi bila belum aktif (dan seragamkan kebijakan cookie)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Regenerasi ID untuk menghindari reuse session id lama
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Bersihkan semua data sesi
$_SESSION = [];

// Hapus cookie sesi di browser (jika ada)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Hancurkan sesi
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: ../login.php');
exit;