<?php
require_once __DIR__ . '/../config/database.php';

$_SESSION = [];
if (session_id() !== '') {
    session_destroy();
}
clear_auth_cookie();

header('Location: ' . base_url('auth/login.php'));
exit;
