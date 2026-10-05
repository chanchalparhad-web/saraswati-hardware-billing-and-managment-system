<?php
require_once __DIR__ . '/config/database.php';

// If user is already logged in, redirect based on role
if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'admin' || $user['role'] === 'staff') {
        header('Location: ' . base_url('billing/new_bill.php'));
    } else {
        header('Location: ' . base_url('customer/products.php'));
    }
    exit;
} else {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}
