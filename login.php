<?php
session_start();
if (isset($_SESSION['role_name'])) {
    if ($_SESSION['role_name'] === 'ADMIN') {
        header("Location: admin/manage_patients.php");
        exit();
    } elseif ($_SESSION['role_name'] === 'DOCTOR') {
        header("Location: doctor/dashboard.php");
        exit();
    } elseif ($_SESSION['role_name'] === 'PATIENT') {
        header("Location: patient/book_appointment.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediConnect</title>
    <link rel="stylesheet" href="assets/css/auth_style.css">
</head>
<body>

<div class="login-card">
    <div class="card-body">
        <div class="auth-header">
            <h3 class="auth-title">MediConnect</h3>
            <p class="auth-subtitle">Healthcare Management & Appointment System</p>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button type="button" class="btn-close-alert" onclick="this.parentElement.remove()">✕</button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button type="button" class="btn-close-alert" onclick="this.parentElement.remove()">✕</button>
            </div>
        <?php endif; ?>
        <form action="auth/login_process.php" method="POST">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="enteremail@example.com" required autofocus>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <label for="password" class="form-label">Password</label>
                    <a href="forgot_password.php" class="link-mc">Forgot password?</a>
                </div>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-mc">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have a patient account? 
            <a href="register.php" class="link-mc fw-semibold">Register now</a>
        </div>
    </div>
</div>

</body>
</html>