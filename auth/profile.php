<?php
session_start();
require_once '../config/database.php';

function e($value): string{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function isValidAvatar(string $avatar): bool{
    return strlen($avatar) <= 255 &&(
        preg_match('#^htpp://[^\s]+$#i', $avatar) === 1 ||
        preg_match('/^[\w\-]+\.(png|jpe?g|gif|webp)$/i', $avatar)===1
);
}

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";


// Lấy thông tin 
function loadUserInfo(PDO $pdo, int $userId) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email, u.full_name, u.phone_number, u.avatar_url,
               r.role_name, p.patient_id, p.address
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN patients p ON p.user_id = u.user_id
        WHERE u.user_id = ? AND u.is_active = 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

try {
    $userInfo = loadUserInfo($pdo, $userId);
} catch (\PDOException $e) {
    error_log("Profile load error: " . $e->getMessage());
    die("Unable to load your profile. Please try again later.");
}

// Tài khoản không còn tồn tại hoặc đã bị khóa: đăng xuất
if (!$userInfo) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit();
}

$hasPatient  = $userInfo['patient_id'] !== null;   // chỉ bệnh nhân mới có địa chỉ
$message     = '';
$messageType = '';

// Thông báo lưu 
if (isset($_SESSION['profile_flash'])) {
    $messageType = $_SESSION['profile_flash']['type'];
    $message     = $_SESSION['profile_flash']['text'];
    unset($_SESSION['profile_flash']);
}

// check ton tai trong database
$form = [
    'full_name'    => $userInfo['full_name'],
    'phone_number' => $userInfo['phone_number'] ?? '',
    'avatar_url'   => $userInfo['avatar_url'] ?? '',
    'address'      => $userInfo['address'] ?? '',
];


// ===== 1. CẬP NHẬT HỒ SƠ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $form['full_name']    = trim($_POST['full_name'] ?? '');
    $form['phone_number'] = trim($_POST['phone_number'] ?? '');
    $form['avatar_url']   = trim($_POST['avatar_url'] ?? '');
    if ($hasPatient) {
        $form['address']  = trim($_POST['address'] ?? '');
    }

    $phoneClean = preg_replace('/[\s.\-]/', '', $form['phone_number']);
    $error = '';

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 100) {
        $error = "Please enter your full name (up to 100 characters).";
    } elseif ($phoneClean !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
        $error = "Phone number is incorrect (9-15 digits).";
    } elseif ($hasPatient && mb_strlen($form['address']) > 255) {
        $error = "Address is too long (up to 255 characters).";
    } elseif ($form['avatar_url'] !== '' && !isValidAvatar($form['avatar_url'])) {
        $error = "Avatar must be an https:// link or an image file name.";
    }

    if ($error === '') {
        $avatar = $form['avatar_url'] === '' ? 'default_avatar.png' : $form['avatar_url'];
        $phone  = $phoneClean === '' ? null : $phoneClean;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ?, avatar_url = ? WHERE user_id = ?");
            $stmt->execute([$form['full_name'], $phone, $avatar, $userId]);
            if ($hasPatient) {
                $stmt = $pdo->prepare("UPDATE patients SET address = ? WHERE user_id = ?");
                $stmt->execute([$form['address'], $userId]);
            }
            $pdo->commit();

            $_SESSION['full_name'] = $form['full_name'];   // để lời chào hiện đúng tên mới
            $_SESSION['profile_flash'] = ['type' => 'success', 'text' => "Profile updated successfully!"];
            header("Location: profile.php");
            exit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Profile update error: " . $e->getMessage());
            $error = "Could not update your profile. Please try again later.";
        }
    }
    $message     = $error;
    $messageType = 'error';
}

// ===== 2. ĐỔI MẬT KHẨU =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $oldPass     = $_POST['old_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $error = '';

    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (\PDOException $e) {
        error_log("Password load error: " . $e->getMessage());
        $row = false;
        $error = "Could not change your password. Please try again later.";
    }

    if ($error === '') {
        if (!$row || !password_verify($oldPass, $row['password_hash'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($newPass) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif (strlen($newPass) > 72) {
            $error = "New password must be at most 72 characters.";
        } elseif (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $error = "New password must contain both letters and numbers.";
        } elseif ($newPass !== $confirmPass) {
            $error = "New password and confirmation do not match.";
        } elseif ($newPass === $oldPass) {
            $error = "New password must be different from the current one.";
        }
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);

            session_regenerate_id(true);   // đổi mã phiên sau khi đổi mật khẩu
            $_SESSION['profile_flash'] = ['type' => 'success', 'text' => "Password changed successfully!"];
            header("Location: profile.php");
            exit();
        } catch (\PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $error = "Could not change your password. Please try again later.";
        }
    }
    $message     = $error;
    $messageType = 'error';
}

// Avatar: chỉ hiện ảnh khi là link https, còn lại hiện chữ cái đầu của tên
$showAvatarImg = preg_match('#^https://#i', (string)$userInfo['avatar_url']) === 1;
$initial = mb_strtoupper(mb_substr($userInfo['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/auth_style.css">
    <style>
        .profile-card { width: 520px; max-width: 95%; margin: 40px auto; padding: 30px; box-sizing: border-box; }
        .profile-nav { text-align: right; font-size: 14px; margin-bottom: 10px; }
        .profile-nav a { color: #00b074; font-weight: bold; text-decoration: none; margin-left: 12px; }
        .profile-top { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .avatar { width: 64px; height: 64px; border-radius: 50%; background: #00b074; color: #fff; font-size: 28px; font-weight: bold; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-meta { font-size: 13px; color: #666; }
        .profile-meta strong { color: #333; }
        .section-title { font-size: 16px; color: #00b074; margin: 25px 0 10px; border-top: 1px solid #eee; padding-top: 20px; }
        .field-hint { color: #666; font-size: 11px; }
        input:disabled { background: #f1f3f4; color: #666; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="login-card profile-card">
        <div class="profile-nav">
            <a href="../index.php">Home</a>
            <a href="logout.php">Log out</a>
        </div>

        <h2>MY PROFILE</h2>

        <?php if ($message !== ''): ?>
            <div class="<?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <div class="profile-top">
            <div class="avatar">
                <?php if ($showAvatarImg): ?>
                    <img src="<?php echo e($userInfo['avatar_url']); ?>" alt="Avatar" referrerpolicy="no-referrer">
                <?php else: ?>
                    <?php echo e($initial); ?>
                <?php endif; ?>
            </div>
            <div class="profile-meta">
                <div><strong><?php echo e($userInfo['full_name']); ?></strong></div>
                <div>Role: <?php echo e($userInfo['role_name']); ?></div>
            </div>
        </div>

        <!-- Form cập nhật hồ sơ -->
        <form action="profile.php" method="POST">
            <div class="form-group">
                <label>Email address</label>
                <input type="email" value="<?php echo e($userInfo['email']); ?>" disabled>
                <small class="field-hint">Email is used to log in and cannot be changed.</small>
            </div>

            <div class="form-group">
                <label>Full name</label>
                <input type="text" name="full_name" required maxlength="100" value="<?php echo e($form['full_name']); ?>">
            </div>

            <div class="form-group">
                <label>Phone number</label>
                <input type="text" name="phone_number" maxlength="20" value="<?php echo e($form['phone_number']); ?>">
            </div>

            <?php if ($hasPatient): ?>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" maxlength="255" value="<?php echo e($form['address']); ?>" placeholder="Street, ward, province/city">
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Avatar</label>
                <input type="text" name="avatar_url" maxlength="255" value="<?php echo e($form['avatar_url']); ?>" placeholder="https://... or image file name">
                <small class="field-hint">Leave empty to use the default avatar.</small>
            </div>

            <button type="submit" name="update_profile">Save changes</button>
        </form>

        <!-- Form đổi mật khẩu -->
        <h3 class="section-title">Change password</h3>
        <form action="profile.php" method="POST">
            <div class="form-group">
                <label>Current password</label>
                <input type="password" name="old_password" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label>New password</label>
                <input type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
                <small class="field-hint">At least 8 characters, with letters and numbers.</small>
            </div>

            <div class="form-group">
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>

            <button type="submit" name="change_password">Change password</button>
        </form>
    </div>
</body>
</html>