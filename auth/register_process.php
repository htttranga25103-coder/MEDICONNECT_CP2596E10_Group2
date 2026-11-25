<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

// Ep nhan du lieu tu POST 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

// Hien loi roi quay lai dang ky
function failRegister(string $message): void {
    $_SESSION['error'] = $message;
    header("Location: register.php");
    exit();
}

if (!csrfValid()) {
    failRegister(CSRF_ERROR_MESSAGE);
}

$fullName    = trim($_POST['full_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$province    = trim($_POST['province'] ?? '');
$ward        = trim($_POST['ward'] ?? '');
$street      = trim($_POST['street'] ?? '');
$password    = $_POST['password'] ?? ''; // khong trim: mat khau co the chua dau cach

// Gom address theo database
$fullAddress = implode(', ', array_filter([$street, $ward, $province], 'strlen'));

// Check dinh dang cua form dang ky
if ($fullName === '' || mb_strlen($fullName) > 100) {
    failRegister("Please enter your full name (up to 100 characters).");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    failRegister("Email format is incorrect!");
}
$phoneClean = preg_replace('/[\s.\-]/', '', $phoneNumber);
if (!preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
    failRegister("Phone number is incorrect (9-15 digits).");
}
if (mb_strlen($fullAddress) > 255) {
    failRegister("Address is too long (up to 255 characters).");
}
if (strlen($password) < 8) {
    failRegister("Password must be at least 8 characters.");
}
if (strlen($password) > 72) {
    failRegister("Password must be at most 72 characters.");
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    failRegister("Password must contain both letters and numbers.");
}

// Xu ly upload anh dai dien (neu benh nhan co chon anh)
$avatarUrl = 'default_avatar.png';
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        failRegister("Error uploading profile photo. Please try again.");
    }

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($_FILES['avatar']['size'] > $maxSize) {
        failRegister("Profile photo must be smaller than 2MB.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['avatar']['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedMimes[$mimeType])) {
        failRegister("Invalid image format. Only JPG, PNG, and WEBP are allowed.");
    }

    $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = $allowedMimes[$mimeType];
    $fileName = 'patient_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
        failRegister("Unable to save profile photo on server.");
    }

    // Luu ten file de tuong thich voi resolveUserAvatar() trong chat.php va profile
    $avatarUrl = $fileName;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$roleId = 3; // PATIENT

try {
    $pdo->beginTransaction();
    $stmtUser = $pdo->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone_number, avatar_url, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmtUser->execute([$roleId, $email, $passwordHash, $fullName, $phoneClean, $avatarUrl]);
    $userId = $pdo->lastInsertId();

    $stmtPatient = $pdo->prepare("INSERT INTO patients (user_id, address) VALUES (?, ?)");
    $stmtPatient->execute([$userId, $fullAddress]);
    $pdo->commit();

    $_SESSION['success'] = "Registration successful! You can now log in.";
    header("Location: login.php");
    exit();
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Xoa anh vua tai len neu insert DB that bai
    if ($avatarUrl !== 'default_avatar.png' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarUrl)) {
        @unlink(__DIR__ . '/../assets/uploads/avatars/' . $avatarUrl);
    }

    if (($e->errorInfo[1] ?? 0) == 1062) {
        failRegister("This email is already registered.");
    }

    error_log("Register error: " . $e->getMessage());
    failRegister("Registration failed. Please try again later.");
}