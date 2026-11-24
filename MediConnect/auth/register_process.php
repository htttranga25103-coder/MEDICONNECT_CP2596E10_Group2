<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

//ep nhan du lieu tu POST 
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: register.php");
    exit();
}

//hien loi roi quay lai dang ky
function failRegister(string $message): void {
    $_SESSION['error']=$message;
    header("Location: register.php");
    exit();
}
if(!csrfValid()){
    failRegister(CSRF_ERROR_MESSAGE);
}


$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email']?? '');
$phoneNumber = trim($_POST['phone_number']?? '');
$province = trim($_POST['province']?? '');
$ward = trim($_POST['ward']?? '');
$street = trim($_POST['street']?? '');
$password = $_POST['password'] ?? '';   // khong trim: mat khau co the chua dau cach

//gom address theo database
$fullAddress = implode(', ', array_filter([$street, $ward, $province], 'strlen'));

//check dinh dang cua form dang ky
if($fullName === ''|| mb_strlen($fullName)>100){
    failRegister("Please enter your full name(upto 100 characters).");
}
if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>150){
    failRegister("Email  format is incorrect!");
}
$phoneClean = preg_replace('/[\s.\-]/','',$phoneNumber);
if(!preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
    failRegister("Phone number is incorrect (9-15 digits).");
}
if(mb_strlen($fullAddress)>255){
    failRegister("Address is too long (upto 255 characters).");
}
if(strlen($password)<8){
    failRegister("password must be at least 8 characters.");
}
if(strlen($password)>72){
    failRegister("Password must be at most 72 characters.");
}
if(!preg_match('/[A-Za-z]/', $password)|| !preg_match('/[0-9]/', $password)){
    failRegister("Password must contain both letters and numbers.");
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$roleId = 3; //dang test patient dang ky tai khoan

try{
    $pdo->beginTransaction();
    $stmtUser = $pdo->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone_number, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmtUser->execute([$roleId , $email, $passwordHash, $fullName, $phoneClean]);
    $userId = $pdo->lastInsertId();

    $stmtPatient = $pdo->prepare("INSERT INTO patients(user_id, address) VALUES (?, ?)");
    $stmtPatient->execute([$userId, $fullAddress]);
    $pdo->commit();
    $_SESSION['success'] = "Registration successfull! You can now log in.";
    header("Location: login.php");
    exit();
}catch(\PDOException $e){
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    //1 email duoc dang ky duy nhat 1 lan (1062 bao ma loi)
    if(($e->errorInfo[1] ?? 0) == 1062){
        failRegister("This email is already registered.");
    }

    //hien loi
    error_log("Register error:" . $e->getMessage());
    failRegister("Registration faild. Please try again later.");
}