<?php

session_start();

$_SESSION = [];

session_destroy();

header('Location: login.php?logged_out=1', true, 303);
exit;