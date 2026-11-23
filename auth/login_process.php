<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

function failLogin(string $message): void {
    $_SESSION['error'] = $message;
    header("Location: login.php");
    exit();
}

if (!csrfValid()) {
    failLogin(CSRF_ERROR_MESSAGE);
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$selectedRole = strtoupper(trim($_POST['role'] ?? ''));

if ($email === '' || $password === '' || !in_array($selectedRole, ['ADMIN', 'DOCTOR', 'PATIENT'], true)) {
    failLogin("Please enter your email, password and role!");
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (\PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    failLogin("Login failed. Please try again later.");
}

if (!$user || !password_verify($password, $user['password_hash']) || $user['role_name'] !== $selectedRole) {
    failLogin("Incorrect email, password or role!");
}

if ((int)$user['is_active'] !== 1) {
    failLogin("Your account has been locked. Please contact the administrator!");
}

// Đổi phiên đăng nhập chống tấn công session fixation
session_regenerate_id(true);
unset($_SESSION['csrf_token']);

$_SESSION['user_id']   = $user['user_id'];
$_SESSION['email']     = $user['email'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role_name'] = $user['role_name'];

if ($user['role_name'] === 'ADMIN') {
    header("Location: ../index.php");
} elseif ($user['role_name'] === 'DOCTOR') {
    header("Location: ../doctor/dashboard.php");
} elseif ($user['role_name'] === 'PATIENT') {
    header("Location: ../patient/book_appointment.php");
} else {
    header("Location: ../index.php");
}
exit();