<?php
session_start();
require_once '../config/database.php';
if($_SERVER['REQUEST_METHOD']=== 'POST'){
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)|| strpos($email, '@') === false || strpos($email, '.')===false){
        $_SESSION['error'] = "email format is incorrect!";
        }   
    $password = $_POST['password'];
    $phoneNumber = trim($_POST['phone_number']);

    $passwordHash = password_hash($password,PASSWORD_DEFAULT);
    $roleId = 3;
    try{
        $stmt = $pdo->prepare("INSERT INTO user (role_id, email, password_hash, full_name, phone_number) VALUES (?, ?, ?, ?, ?)");
        $stmt -> execute([$roleId, $email, $passwordHash, $fullName, $phoneNumber]);
        $_SESSION['success']="Registration successful! You can now log in.";
        header("Location: login.php");
        exit();
    }catch(\PDOException $e) {
        $_SESSION['error']="Registration error. Please check again:" . $e->getMessage();
        header("Location: register.php");
        exit();
    }
}