
<?php

// Start session if it has not been started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine the project base URL from the current Doctors script.
$scriptName = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? ''
);

$modulePosition = strpos($scriptName, '/doctors/');

$baseUrl = '';

if ($modulePosition !== false) {
    $baseUrl = substr($scriptName, 0, $modulePosition);
}

// Check whether the user is logged in.
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit();
}

// Check ADMIN permission.
if (
    !isset($_SESSION['role_name']) ||
    $_SESSION['role_name'] !== 'ADMIN'
) {
    http_response_code(403);
    exit('Access denied. Admin permission required.');
}
