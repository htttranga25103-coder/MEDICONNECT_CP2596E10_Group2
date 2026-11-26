<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Cấu hình thư mục lưu ảnh đại diện (tính từ vị trí file PHP hiện tại)
define('AVATAR_UPLOAD_DIR', __DIR__ . '/../uploads/avatars/');
define('AVATAR_WEB_PATH', '../uploads/avatars/');
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // Giới hạn 2MB

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

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

/**
 * Xử lý upload tệp ảnh đại diện an toàn
 * Trả về mảng ['filename' => string|null, 'error' => string]
 */
function handleAvatarUpload(array $file, int $userId): array {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['filename' => null, 'error' => ''];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['filename' => null, 'error' => 'File upload failed. Please try again.'];
    }

    if ($file['size'] > MAX_AVATAR_SIZE) {
        return ['filename' => null, 'error' => 'Avatar image must be smaller than 2MB.'];
    }

    // Kiểm tra định dạng MIME thực tế của tệp (tránh giả mạo đuôi file)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimes[$mimeType])) {
        return ['filename' => null, 'error' => 'Only JPG, PNG, GIF, and WEBP images are allowed.'];
    }

    // Tạo thư mục nếu chưa tồn tại
    if (!is_dir(AVATAR_UPLOAD_DIR) && !mkdir(AVATAR_UPLOAD_DIR, 0755, true)) {
        return ['filename' => null, 'error' => 'Server error: Cannot create upload directory.'];
    }

    // Tạo tên tệp ngẫu nhiên an toàn
    $newFilename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMimes[$mimeType];
    $destination = AVATAR_UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['filename' => null, 'error' => 'Could not save the uploaded image.'];
    }

    return ['filename' => $newFilename, 'error' => ''];
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

$hasPatient  = $userInfo['patient_id'] !== null;
$message     = '';
$messageType = '';

if (isset($_SESSION['profile_flash'])) {
    $messageType = $_SESSION['profile_flash']['type'];
    $message     = $_SESSION['profile_flash']['text'];
    unset($_SESSION['profile_flash']);
}

$form = [
    'full_name'    => $userInfo['full_name'],
    'phone_number' => $userInfo['phone_number'] ?? '',
    'address'      => $userInfo['address'] ?? '',
];

// Bắt lỗi khi tệp tải lên vượt quá giới hạn post_max_size của PHP (tránh báo sai lỗi CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $message     = "The uploaded file is too large. Please select an image under 2MB.";
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $form['full_name']    = trim($_POST['full_name'] ?? '');
    $form['phone_number'] = trim($_POST['phone_number'] ?? '');
    if ($hasPatient) {
        $form['address']  = trim($_POST['address'] ?? '');
    }

    $phoneClean = preg_replace('/[\s.\-]/', '', $form['phone_number']);
    $error = '';
    $uploadedAvatar = null;

    if (!csrfValid()) {
        $error = CSRF_ERROR_MESSAGE;
    } elseif ($form['full_name'] === '' || mb_strlen($form['full_name'], 'UTF-8') > 100) {
        $error = "Please enter your full name (up to 100 characters).";
    } elseif ($phoneClean !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
        $error = "Phone number is incorrect (9-15 digits).";
    } elseif ($hasPatient && mb_strlen($form['address'], 'UTF-8') > 255) {
        $error = "Address is too long (up to 255 characters).";
    } elseif (isset($_FILES['avatar_file'])) {
        $uploadResult = handleAvatarUpload($_FILES['avatar_file'], $userId);
        if ($uploadResult['error'] !== '') {
            $error = $uploadResult['error'];
        } else {
            $uploadedAvatar = $uploadResult['filename'];
        }
    }

    if ($error === '') {
        $oldAvatar = (string)($userInfo['avatar_url'] ?? '');
        // Giữ ảnh cũ nếu người dùng không chọn tệp mới
        $avatar = $uploadedAvatar ?? ($oldAvatar !== '' ? $oldAvatar : 'default_avatar.png');
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

            // Xóa tệp ảnh cũ trên server nếu có tải lên ảnh mới
            if ($uploadedAvatar !== null && $oldAvatar !== '' && $oldAvatar !== 'default_avatar.png') {
                $oldFilePath = AVATAR_UPLOAD_DIR . basename($oldAvatar);
                if (is_file($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }

            $_SESSION['full_name'] = $form['full_name'];
            $_SESSION['profile_flash'] = ['type' => 'success', 'text' => "Profile updated successfully!"];
            header("Location: profile.php");
            exit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Nếu lưu DB thất bại, xóa tệp vừa upload để tránh rác server
            if ($uploadedAvatar !== null && is_file(AVATAR_UPLOAD_DIR . $uploadedAvatar)) {
                @unlink(AVATAR_UPLOAD_DIR . $uploadedAvatar);
            }
            error_log("Profile update error: " . $e->getMessage());
            $error = "Could not update your profile. Please try again later.";
        }
    }
    $message     = $error;
    $messageType = 'error';
}

// ... (Giữ nguyên phần đổi mật khẩu change_password của bạn) ...

// Xác định đường dẫn hiển thị ảnh đại diện
$currentAvatar = (string)($userInfo['avatar_url'] ?? '');
$showAvatarImg = false;
$avatarSrc     = '';

if ($currentAvatar !== '' && $currentAvatar !== 'default_avatar.png') {
    if (preg_match('#^https://#i', $currentAvatar) === 1) {
        $showAvatarImg = true;
        $avatarSrc     = $currentAvatar;
    } elseif (is_file(AVATAR_UPLOAD_DIR . basename($currentAvatar))) {
        $showAvatarImg = true;
        $avatarSrc     = AVATAR_WEB_PATH . basename($currentAvatar);
    }
}

$initial = mb_strtoupper(mb_substr($userInfo['full_name'], 0, 1, 'UTF-8'), 'UTF-8');
$role    = strtoupper($userInfo['role_name'] ?? '');
$isStaff = in_array($role, ['ADMIN', 'DOCTOR'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/article_style.css">
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=5">
</head>
<body>
<?php if ($isStaff): ?>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <a href="../index.php" class="sidebar-brand">MediConnect</a>
            <ul class="sidebar-menu">
                <li><a href="../index.php">Home</a></li>
                <?php if ($role === 'ADMIN'): ?>
                    <li><a href="../views/admin_dashboard.php">Dashboard Overview</a></li>
                    <li><a href="../admin/manage_patients.php">Manage Patients</a></li>
                    <li><a href="../doctors/admin.php">Manage Doctors</a></li>
                    <li><a href="../locations/index.php">Manage Locations</a></li>
                <?php elseif ($role === 'DOCTOR'): ?>
                    <li><a href="../views/doctor_dashboard.php">Doctor Portal</a></li>
                    <li><a href="../chat.php">Consultation Chat</a></li>
                <?php endif; ?>
                <li><a href="../articles/news.php">Public News</a></li>
                <li><a href="../articles/manage.php">Manage Articles</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-user">
                    <span>Welcome, <strong><?php echo e($userInfo['full_name']); ?></strong> (<?php echo e($userInfo['role_name']); ?>)</span>
                    <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Log out</a>
                </div>
            </header>
            <main class="content-container">
<?php else: ?>
    <nav class="patient-navbar">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo">MediConnect</a>

            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>

                <?php if ($role === 'PATIENT'): ?>
                    <li><a href="../patient/book_appointment.php">Book Appointment</a></li>
                    <li><a href="../patient/my_appointments.php">My Appointments</a></li>
                    <li><a href="../chat.php">Consultation Chat</a></li>
                    <li><a href="../articles/news.php">Articles</a></li>
                    <li><a href="../reviews/index.php">Reviews</a></li>
                <?php endif; ?>

                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>

            <div class="nav-user">
                <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong></span>
                <a href="logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>
            </div>
        </div>
    </nav>
    <main class="article-container" style="max-width: 780px;">
<?php endif; ?>

        <h1 class="page-title">My Profile</h1>

        <?php if ($message !== ''): ?>
            <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <span><?php echo e($message); ?></span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <div class="panel-header">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div class="author-avatar" style="background-color: #168077; overflow: hidden;">
                        <?php if ($showAvatarImg): ?>
                            <img id="avatarPreview" src="<?= e($avatarSrc) ?>" alt="<?= e($initial) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block;">
                        <?php else: ?>
                            <img id="avatarPreview" src="" alt="<?= e($initial) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: none;">
                            <span id="avatarInitial"><?= e($initial) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="panel-title" style="margin-bottom: 2px;"><?php echo e($userInfo['full_name']); ?></h2>
                        <span class="article-category-tag" style="margin-bottom: 0;"><?php echo e($userInfo['role_name']); ?></span>
                    </div>
                </div>
            </div>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
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
                        <label class="form-label" for="avatar_file">Profile picture</label>
                        <input type="file" id="avatar_file" name="avatar_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" style="padding: 7px 10px;">
                        <span class="text-sub">JPG, PNG, GIF, or WEBP (Max 2MB). Leave empty to keep current.</span>
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