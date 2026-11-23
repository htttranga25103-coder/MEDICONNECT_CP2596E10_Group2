<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: login.php");
    exit();
}
//hien loi roi quay lai form dang nhap
function failLogin(string $message): void{
    $_SESSION['error'] = $message;
    header("Location: login.php");
    exit();
}

if(!csrfValid()){
    failLogin(CSRF_ERROR_MESSAGE);
}

$email = trim($_POST['email']?? '');
$password = $_POST['password'] ?? '';
$selectedRole = strtoupper(trim($_POST['role'] ?? ''));

if($email === '' || $password === '' || !in_array($selectedRole, ['ADMIN', 'DOCTOR', 'PATIENT'], true)){
    failLogin("Please enter your email, password and role!");
}

try{
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ? AND u.is_active = 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
}catch (\PDOException $e) {
    error_log("Login error:" . $e->getMessage());
    failLogin("Login failed. Please try again later.");
}
//thong bao loi khong lam lo thong tin
if(!$user || !password_verify($password, $user['password_hash'])|| $user['role_name'] !== $selectedRole){
    failLogin("Incorrect email, password or role!");
}

//doi phien dang nhap de chong tan cong fixation
session_regenerate_id(true); 
unset($_SESSION['csrf_token']);   // doi ma CSRF cho phien dang nhap moi
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role_name'] = $user['role_name'];
    
    // if($selectedRole === 'ADMIN'){
    //     header("Location: ../admin/dashboard.php");
    // }elseif($selectedRole === 'DOCTOR'){
    //     header("Location: ../doctor/schedule.php");
    // }else{
    //     header("Location: ../index.php");
    // }
    // exit();
    // }else{
    //     $_SESSION['errer'] = "Incorrect email or password!";
    //     
        header("Location: ../index.php");
        exit();
   // }
//}