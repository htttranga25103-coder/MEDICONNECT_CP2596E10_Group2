<?php
session_start();
require_once '../config/database.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $selectedRole = strtoupper(trim($_POST['role']));

    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ? AND u.is_active = 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['password_hash'])){
        if($user['role_name'] !== $selectedRole){
            $_SESSION['error'] = "The login account does not match the data!";
            header("Location: login.php");
            exit();
        }
        
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_name'] = $user['role_name'];
    
    if($selectedRole === 'ADMIN'){
        header("Location: ../admin/dashboard.php");
    }elseif($selectedRole === 'DOCTOR'){
        header("Location: ../doctor/schedule.php");
    }else{
        header("Location: ../index.php");
    }
    exit();
    }else{
        $_SESSION['errer'] = "Incorrect email or password!";
        header("Location: login.php");
        exit();
    }
}
