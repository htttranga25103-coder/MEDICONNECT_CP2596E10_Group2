<?php
session_start();
require_once '../includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create a Patient Account - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/auth_style.css?v=2">
</head>
<body>
    <div class="login-card" style="width: 480px; max-width: 95%; margin: 40px auto; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <a href="../index.php" style="color: #0f766e; font-size: 13.5px; font-weight: 700; text-decoration: none;">
                &larr; Back to Home
            </a>
            <a href="../doctors/register.php" style="color: #475569; font-size: 13px; text-decoration: none;">
                Register as Doctor &rarr;
            </a>
        </div>

        <div class="brand-header" style="margin-bottom: 20px;">
            <div class="brand-logo">+</div>
            <div>
                <p class="brand-title">MediConnect</p>
                <p class="brand-subtitle">Comprehensive healthcare</p>
            </div>
        </div>

        <p style="color: #0f766e; font-size: 13px; font-weight: bold; margin-bottom: 5px;">A HEALTHY START</p>
        <h2 style="font-size: 24px; margin-bottom: 8px;">Live healthier, with MediConnect.</h2>
        <p style="color: #666; font-size: 13px; margin-bottom: 20px;">Create an account to book visits and manage your health, all in one place.</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form action="register_process.php" method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>

            <!-- Avatar Upload & Preview -->
            <div class="form-group" style="display: flex; align-items: center; gap: 14px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <img id="patientAvatarPreview" src="../assets/uploads/avatars/default_avatar.png" onerror="this.src='../images/icons/mediconnect.png'" alt="Avatar Preview" style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #0f766e; background: #ffffff;">
                <div style="flex: 1;">
                    <label style="margin-bottom: 4px; display: block;">Profile Photo (Avatar)</label>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this, 'patientAvatarPreview')" style="font-size: 13px; border: none; padding: 0; background: transparent;">
                    <small style="color: #64748b; font-size: 11px; display: block; margin-top: 3px;">JPG, PNG or WEBP (max 2MB).</small>
                </div>
            </div>

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

        <p style="text-align: center; margin-top: 20px; font-size:14px">
            Already have an account? <a href="login.php" style="color: #0f766e; font-weight: bold; text-decoration: none;">Log in</a>
        </p>
    </div>

    <script>
        function previewAvatar(input, imgId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(imgId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>