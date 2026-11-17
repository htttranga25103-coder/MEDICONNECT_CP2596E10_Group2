<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";
//update profile
if($_SERVER['REQUEST_METHOD'] === 'POST' &&  isset($_POST['update_profile'])){
    $fulfName = trim($_POST['full_name']);
    $phoneNember = trim($_POST['phone_number']);
    $avatarUrl = trim($_POST['avatar_url']);

    $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone_number=?,avatar_url =? WHERE user_id = ?");
    $stmt->execute([$fullName, $phoneNember, $avatarUrl, $userId]);

    $_SESSION['full_name']=$fullName;
    $message = "Profile update successfully";
}
//update password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])){
    $oldPass = $_POST['old_password'];
    $newPass =$_POST['new_password'];

    $stmt = $pdo->prepare("SELECT password_hash FROM user WHERE user_id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if($user && password_verify($oldPass, $user['password_hash'])){
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $updateStmt->execute([$newHash, $userId]);
        $message = "Password changed successfully!";
    }else{
        $message="Password changed failed!";
    }
}

$stmtGet = $pdo->prepare("SELECT * FROM user WHERE usser_id=?");
$stmtGet->execute([$userId]);
$userInfo = $stmtGet->fetch();