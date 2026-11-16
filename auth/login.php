<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login MediConnect</title>
    <link rel = "stylesheet" href = "../assets/css/auth_style.css">
</head>
<body>
    <div class="login-card">
        <div class="brand-header">
            <div class="brand-logo"></div>
            <div>
                <p class="brand-title">MediConnect</p>
                <p class="brand-subtitle">Comprehensive healthcare</p>
            </div>
        </div>

        <h2>LOG IN</h2>
        
        <!-- Hiển thị thông báo lỗi/thành công -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required placeholder="Enter your email address">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter a password">
            </div>

            <div class="form-group">
                <label>Role</label>
                <div class="role-container">
                    <label class="role-option">
                        <input type="radio" name="role" value="DOCTOR"> Doctor
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="PATIENT" checked> Patient
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="ADMIN"> Admin
                    </label>
                </div>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>