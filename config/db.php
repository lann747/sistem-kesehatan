<?php
declare(strict_types=1);

/**
 * Koneksi database yang aman untuk seluruh aplikasi.
 * - Baca kredensial dari environment variables (fallback ke default lokal).
 * - Aktifkan mysqli exceptions (lebih mudah ditangani try/catch).
 * - Set charset utf8mb4 (emoji & karakter multibahasa).
 * - Terapkan SQL strict mode untuk validasi data yang lebih ketat.
 * - Jangan bocorkan detail error ke user (log ke server saja).
 */

// ---- Konfigurasi dari ENV (atur di .env / panel hosting) ----
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'db_kesehatan';
$port = (int) (getenv('DB_PORT') ?: 3306);

// Aktifkan exception untuk semua error mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Opsi: persistent connection => prepend 'p:' ke host jika perlu
    // $host = 'p:' . $host;

    $conn = new mysqli($host, $user, $pass, $name, $port);

    // Charset & collation aman untuk Unicode penuh
    if (! $conn->set_charset('utf8mb4')) {
        throw new mysqli_sql_exception('Gagal set charset: ' . $conn->error);
    }

    // SQL mode ketat untuk mencegah data “setengah valid”
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (mysqli_sql_exception $e) {
    // Log detail ke server (bukan ke user)
    error_log('[DB] Koneksi/Init gagal: ' . $e->getMessage());

    // Tampilkan pesan umum saja ke user
    http_response_code(500);
    exit('Terjadi gangguan koneksi database. Coba beberapa saat lagi.');
}