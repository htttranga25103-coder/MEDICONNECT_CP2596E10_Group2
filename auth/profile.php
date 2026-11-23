<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

function e($value): string{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function isValidAvatar(string $avatar): bool{
    return strlen($avatar) <= 255 &&(
        preg_match('#^https://[^\s]+$#i', $avatar) === 1 ||
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
    if(!csrfValid()) {
        $error=CSRF_ERROR_MESSAGE;
    }elseif($form['full_name'] === '' || mb_strlen($form['full_name']) > 100) {
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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $oldPass     = $_POST['old_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $error = '';
    if(!csrfValid()){
        $error=CSRF_ERROR_MESSAGE;
    }else{
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (\PDOException $e) {
        error_log("Password load error: " . $e->getMessage());
        $row = false;
        $error = "Could not change your password. Please try again later.";
    }
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
$isStaff = in_array($userInfo['role_name'] ?? '', ['ADMIN', 'DOCTOR'], true); // Thêm dòng này
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
<?php if ($isStaff): ?>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <a href="../index.php" class="sidebar-brand">MediConnect</a>
            <ul class="sidebar-menu">
                <li><a href="../index.php">Home</a></li>
                <li><a href="../articles/news.php">Public News</a></li>
                <li><a href="../articles/manage.php">Manage Articles</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-user">
                    <span>Welcome, <strong><?php echo e($userInfo['full_name']); ?></strong> (<?php echo e($userInfo['role_name']); ?>)</span>
                    <a href="logout.php" class="btn-logout">Log out</a>
                </div>
            </header>
            <main class="content-container">
<?php else: ?>
    <header class="public-navbar">
        <a href="../index.php" class="public-brand">MediConnect</a>
        <nav class="public-nav-links">
            <a href="../index.php" class="nav-item">Home</a>
            <a href="../articles/news.php" class="nav-item">All Articles</a>
            <a href="profile.php" class="nav-item active">My Profile</a>
            <a href="logout.php" class="btn-logout">Log out</a>
        </nav>
    </header>
    <main class="article-container" style="max-width: 780px;">
<?php endif; ?>

        <h1 class="page-title">My Profile</h1>

        <?php if ($message !== ''): ?>
            <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <span><?php echo e($message); ?></span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Thông tin tài khoản & Cập nhật hồ sơ -->
        <div class="panel-card">
            <div class="panel-header">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div class="author-avatar" style="background-color: #168077;">
                        <?php if ($showAvatarImg): ?>
                            <img src="<?php echo e($userInfo['avatar_url']); ?>" alt="Avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?php echo e($initial); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="panel-title" style="margin-bottom: 2px;"><?php echo e($userInfo['full_name']); ?></h2>
                        <span class="article-category-tag" style="margin-bottom: 0;"><?php echo e($userInfo['role_name']); ?></span>
                    </div>
                </div>
            </div>

            <form action="profile.php" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label">Email address</label>
                        <input type="email" class="form-control" value="<?php echo e($userInfo['email']); ?>" readonly>
                        <span class="text-sub">Email is used to log in and cannot be changed.</span>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Full name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" required maxlength="100" value="<?php echo e($form['full_name']); ?>">
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Phone number</label>
                        <input type="text" name="phone_number" class="form-control" maxlength="20" value="<?php echo e($form['phone_number']); ?>">
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Avatar URL / Filename</label>
                        <input type="text" name="avatar_url" class="form-control" maxlength="255" value="<?php echo e($form['avatar_url']); ?>" placeholder="https://... or image file name">
                        <span class="text-sub">Leave empty to use the default avatar.</span>
                    </div>

                    <?php if ($hasPatient): ?>
                    <div class="form-group col-span-4">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" maxlength="255" value="<?php echo e($form['address']); ?>" placeholder="Street, ward, province/city">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn-mc btn-mc-teal">Save changes</button>
                </div>
            </form>
        </div>

        <!-- Đổi mật khẩu -->
        <div class="panel-card">
            <div class="panel-header">
                <h2 class="panel-title">Change Password</h2>
            </div>

            <form action="profile.php" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group col-span-4">
                        <label class="form-label">Current password <span class="req">*</span></label>
                        <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">New password <span class="req">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password">
                        <span class="text-sub">At least 8 characters, with letters and numbers.</span>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Confirm new password <span class="req">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn-mc btn-mc-teal">Change password</button>
                </div>
            </form>
        </div>

<?php if ($isStaff): ?>
            </main>
            <footer class="admin-footer">
                <span>&copy; 2026 MediConnect. All rights reserved.</span>
            </footer>
        </div>
    </div>
<?php else: ?>
    </main>
    <footer class="admin-footer">
        <span>&copy; 2026 MediConnect. All rights reserved.</span>
    </footer>
<?php endif; ?>
</body>
</html>