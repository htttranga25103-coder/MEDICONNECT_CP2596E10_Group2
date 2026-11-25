<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (($_SESSION['role_name'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Access denied. Admin permission required.');
}

$counts = [
    'patients' => 0,
    'doctors'  => 0,
    'articles' => 0,
    'comments' => 0,
    'reviews'  => 0,
    'cities'   => 0,
];

try {
    $counts['patients'] = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $counts['doctors']  = (int)$pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
    $counts['articles'] = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $counts['comments'] = (int)$pdo->query("SELECT COUNT(*) FROM article_comments")->fetchColumn();
    $counts['reviews']  = (int)$pdo->query("SELECT COUNT(*) FROM doctor_reviews")->fetchColumn();
    $counts['cities']   = (int)$pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
} catch (\PDOException $e) {
    error_log("Admin homepage count error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Homepage - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=3">
    <style>
        .admin-hub-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }
        .admin-hub-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
        }
        .admin-hub-card h3 {
            margin: 0 0 6px;
            font-size: 18px;
        }
        .admin-hub-card p {
            margin: 0;
            color: #64748b;
            font-size: 13.5px;
        }
        .admin-hub-count {
            font-size: 22px;
            font-weight: 700;
            color: #0f766e;
        }
        .admin-hub-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="sidebar">
        <a href="index.php" class="sidebar-brand">MediConnect</a>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active">Admin Homepage</a></li>
            <li><a href="manage_patients.php">Admin Patients</a></li>
            <li><a href="../doctors/admin.php">Admin Doctors</a></li>
            <li><a href="../articles/manage.php">Admin Articles</a></li>
            <li><a href="../articles/news.php">Admin Comment</a></li>
            <li><a href="../reviews/index.php">Admin Reviews</a></li>
            <li><a href="../locations/index.php">Locations Overview</a></li>
            <li><a href="../locations/cities.php">Cities</a></li>
            <li><a href="../locations/districts.php">Districts</a></li>
            <li><a href="../locations/streets.php">Streets</a></li>
            <li><a href="../index.php">Home Page</a></li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-user">
                <span>Welcome, <strong><?= e($_SESSION['full_name'] ?? 'Admin') ?></strong></span>
                <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </div>
        </header>

        <div class="content-container">
            <main>
                <h2 class="page-title">Admin Homepage</h2>
                <p>Trung tâm điều hướng các chức năng quản trị hiện có của hệ thống MediConnect.</p>

                <div class="admin-hub-grid">
                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Patients</h3>
                            <div class="admin-hub-count"><?= (int)$counts['patients'] ?></div>
                            <p>Quản lý thông tin bệnh nhân (thêm, sửa, xóa/khóa tài khoản).</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="manage_patients.php" class="btn-mc btn-mc-teal">Mở quản lý bệnh nhân</a>
                        </div>
                    </div>

                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Doctors</h3>
                            <div class="admin-hub-count"><?= (int)$counts['doctors'] ?></div>
                            <p>Duyệt/từ chối hồ sơ đăng ký, sửa thông tin và xóa hồ sơ bác sĩ.</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="../doctors/admin.php" class="btn-mc btn-mc-teal">Mở quản lý bác sĩ</a>
                            <a href="../doctors/register.php" class="btn-mc btn-mc-outline-gray">Form đăng ký BS</a>
                        </div>
                    </div>

                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Articles</h3>
                            <div class="admin-hub-count"><?= (int)$counts['articles'] ?></div>
                            <p>Quản lý bài viết kiến thức y khoa (thêm, sửa, xóa bài viết).</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="../articles/manage.php" class="btn-mc btn-mc-teal">Quản lý bài viết</a>
                            <a href="../articles/news.php" class="btn-mc btn-mc-outline-gray">Xem trang tin</a>
                        </div>
                    </div>

                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Comment</h3>
                            <div class="admin-hub-count"><?= (int)$counts['comments'] ?></div>
                            <p>Vào bài viết để xem và xóa bình luận theo quyền Admin.</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="../articles/news.php" class="btn-mc btn-mc-teal">Chọn bài viết xem bình luận</a>
                        </div>
                    </div>

                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Reviews</h3>
                            <div class="admin-hub-count"><?= (int)$counts['reviews'] ?></div>
                            <p>Xem thống kê đánh giá sao và phản hồi của bệnh nhân.</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="../reviews/index.php" class="btn-mc btn-mc-teal">Xem đánh giá bác sĩ</a>
                        </div>
                    </div>

                    <div class="admin-hub-card">
                        <div>
                            <h3>Admin Locations</h3>
                            <div class="admin-hub-count"><?= (int)$counts['cities'] ?> Cities</div>
                            <p>Thêm, sửa, xóa Thành phố, Quận/Huyện và Tên đường.</p>
                        </div>
                        <div class="admin-hub-actions">
                            <a href="../locations/index.php" class="btn-mc btn-mc-teal">Tổng quan địa danh</a>
                            <a href="../locations/cities.php" class="btn-mc btn-mc-outline-gray">Thành phố</a>
                            <a href="../locations/districts.php" class="btn-mc btn-mc-outline-gray">Quận/Huyện</a>
                            <a href="../locations/streets.php" class="btn-mc btn-mc-outline-gray">Tên đường</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
</body>
</html>