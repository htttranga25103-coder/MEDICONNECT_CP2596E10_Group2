<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (
    !isset($_SESSION['role_name']) ||
    $_SESSION['role_name'] !== 'ADMIN'
) {
    http_response_code(403);
    exit('Access denied. Admin permission required.');
}