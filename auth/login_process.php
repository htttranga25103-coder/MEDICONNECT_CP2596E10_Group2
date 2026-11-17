<?php
session_start();
require_once '../config/MyConnection.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
if (empty($email) || empty($password)) {
    $_SESSION['error'] = "Please enter both email and password!";
    header("Location: ../login.php");
    exit();
}

try {
    $conn = MyConnection::connectDatabase();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $sql = "SELECT u.user_id, u.email, u.password_hash, u.full_name, u.is_active, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['error'] = "Invalid email or password!";
        header("Location: ../login.php");
        exit();
    }
    if ((int)$user['is_active'] !== 1) {
        $_SESSION['error'] = "Your account has been locked. Please contact the administrator!";
        header("Location: ../login.php");
        exit();
    }
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_name'] = $user['role_name'];
    if ($user['role_name'] === 'ADMIN') {
        header("Location: ../admin/manage_patients.php");
    } elseif ($user['role_name'] === 'DOCTOR') {
        header("Location: ../doctor/dashboard.php");
    } elseif ($user['role_name'] === 'PATIENT') {
        header("Location: ../patient/book_appointment.php");
    } else {
        $_SESSION['error'] = "Your account does not have a valid role assigned!";
        header("Location: ../login.php");
    }
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = "System error: " . $e->getMessage();
    header("Location: ../login.php");
    exit();
}