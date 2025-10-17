<?php
// Non-cache untuk mencegah page back ke halaman terlindungi
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Mulai sesi jika belum
if (session_status() !== PHP_SESSION_ACTIVE) {
    // (Opsional) samakan kebijakan cookie dengan halaman lain
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

// Regenerasi ID untuk menghindari reuse
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Bersihkan seluruh data sesi
$_SESSION = [];

// Hapus cookie sesi di browser (jika digunakan)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Set kedaluwarsa di masa lalu agar cookie terhapus
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Hancurkan sesi
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: ../login.php');
exit;