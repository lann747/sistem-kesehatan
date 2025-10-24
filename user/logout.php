<?php
$_SESSION = [];

// Hancurkan sesi
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: ../login.php');
exit;