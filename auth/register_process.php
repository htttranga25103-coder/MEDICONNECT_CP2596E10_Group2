<?php
session_start();
require_once '../config/database.php';
if($_SERVER['REQUEST_METHOD']=== 'POST'){
    $fullName = trim($_POST['full_name']) ?? '';
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $fullAddress = trim("$street, $ward, $province",",");
    $password = $_POST['password'] ?? '';

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)|| strpos($email, '@') === false || strpos($email, '.')===false){
        $_SESSION['error'] = "email format is incorrect!";
        header("Location: register.php");
        exit();
        }  

    $passwordHash = password_hash($password,PASSWORD_DEFAULT);
    $roleId = 3;
    try{
        $pdo->beginTransaction();
        $stmtUser = $pdo->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone_number, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmtUser -> execute([$roleId, $email, $passwordHash, $fullName, $phoneNumber]);
        $userId = $pdo->lastInsertId();
        $stmtPatient = $pdo->prepare(
           "INSERT INTO patients(user_id, address) VALUES(?, ?)" 
        );
        $stmtPatient->execute([$userId, $fullName]);
        $pdo->commit();

        $_SESSION['success']="Registration successful! You can now log in.";
        header("Location: login.php");
        exit();
    }catch(\PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error']="Registration error. Please check again:" . $e->getMessage();
        header("Location: register.php");
        exit();
    }
}