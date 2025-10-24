<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$name = 'db_kesehatan';
$port = 3306;

$conn = new mysqli($host, $user, $pass, $name, $port);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Terjadi gangguan koneksi database. Coba beberapa saat lagi.');
}
?>