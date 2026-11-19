<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create an account</title>
    <link rel = "stylesheet" href = "../assets/css/auth_style.css">
</head>
<body>
    <div class="login-card" style="width: 480px; margin: 40px auto; padding: 30px;">
        <div class="brand-header" style="margin-bottom: 20px;">
            <div class="brand-logo">+</div>
            <div>
                <p class="brand-title">MediConnect</p>
                <p class="brand-subtitle">Comprehensive healthcare</p>
            </div>
        </div>

        <p style="color: #00b074; font-size: 13px; font-weight: bold; margin-bottom: 5px;">A HEALTHY START</p>
        <h2 style="font-size: 24px; margin-bottom: 8px;">Live healthier, with MediConnect.</h2>
        <p style="color: #666; font-size: 13px; margin-bottom: 20px;">Create an account to book visits and manage your health, all in one place.</p>

        <!-- Hiển thị thông báo lỗi nếu có -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        
        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label>Full name</label>
                <input type="text" name="full_name" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label>Phone number</label>
                <input type="text" name="phone_number" required placeholder="Enter your number">
            </div>

            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label>Province/City</label>
                <input type="text" name="province" placeholder="Enter province/city">
            </div>

            <div class="form-group">
                <label>Ward/Commune</label>
                <input type="text" name="ward" placeholder="Enter district/ward">
            </div>

            <div class="form-group">
                <label>Street/Hamlet</label>
                <input type="text" name="street" placeholder="Enter street/hamlet">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8" placeholder="Create a password">
                <small style="color: #666; font-size: 11px;">At least 8 characters, with letters and numbers.</small>
            </div>

            <button type="submit" name="register_btn" style="margin-top: 15px;">Create account</button>
        </form>
    <p style = "text-align: center; margin-top: 20px; font-size:14px">
        Already have an account? <a href="login.php" style="color: #00b074; font-weight: bold; text-decoration: none;">Log in </a>
    </p>
    </div>
</body>
</html>