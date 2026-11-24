<?php
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    // Lấy 5 bài viết mới nhất kèm danh mục và tác giả
    $stmt = $pdo->query("
        SELECT a.*, ac.name AS category_name, u.full_name AS author_name
        FROM articles a
        LEFT JOIN article_categories ac ON a.category_id = ac.category_id
        JOIN users u ON a.author_id = u.user_id
        ORDER BY a.created_at DESC, a.article_id DESC
        LIMIT 5
    ");
    $articles = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Home load articles error: " . $e->getMessage());
    die("Unable to load featured articles. Please try again later.");
}

$role = $_SESSION['role_name'] ?? '';
$isStaff = isset($_SESSION['user_id']) && in_array($role, ['ADMIN', 'DOCTOR'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - MediConnect</title>
    <link rel="stylesheet" href="assets/css/patient_booking.css?v=2">
    <link rel="stylesheet" href="assets/css/article_style.css?v=2">
    <link rel="stylesheet" href="assets/css/home_style.css?v=1">
</head>
<body>
    <!-- Thanh điều hướng đồng bộ -->
    <nav class="patient-navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand-logo">MediConnect</a>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="articles/news.php">All Articles</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($role === 'ADMIN'): ?>
                        <li><a href="admin/manage_patients.php">Manage Users</a></li>
                    <?php elseif ($role === 'DOCTOR'): ?>
                        <li><a href="doctor/dashboard.php">Doctor Portal</a></li>
                    <?php elseif ($role === 'PATIENT'): ?>
                        <li><a href="patient/book_appointment.php">Book Appointment</a></li>
                        <li><a href="chat.php">Consultation Chat</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <div class="nav-user">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong></span>
                    <a href="auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-logout-patient btn-login-nav">
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Danh sách bài viết nổi bật -->
    <main class="main-wrapper">
        <div class="section-bar">
            <h2>Featured Medical Articles</h2>
            <a href="articles/news.php" class="btn-view-all">View All Articles &rarr;</a>
        </div>

        <?php if (!empty($articles)): ?>
            <div class="article-list">
                <?php foreach ($articles as $art): ?>
                    <?php
                        $thumb = ltrim((string)($art['thumbnail_url'] ?? ''), '/');
                    ?>
                    <article class="article-card">
                        <?php if ($thumb !== ''): ?>
                            <a href="articles/detail.php?id=<?= (int)$art['article_id'] ?>" class="article-thumb-wrap">
                                <img src="<?= e($thumb) ?>" alt="<?= e($art['title']) ?>" class="article-thumb">
                            </a>
                        <?php endif; ?>

                        <div class="article-body">
                            <span class="article-category-tag">
                                <?= e($art['category_name'] ?? 'General') ?>
                            </span>

                            <a href="articles/detail.php?id=<?= (int)$art['article_id'] ?>" class="article-title">
                                <?= e($art['title']) ?>
                            </a>

                            <div class="article-meta">
                                <span>Author: <strong><?= e($art['author_name']) ?></strong></span>
                                <span>&bull;</span>
                                <span>Published: <?= e($art['created_at']) ?></span>
                            </div>

                            <p class="article-summary">
                                <?= e($art['summary'] ?? '') ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="article-card article-card-empty">
                No articles found in the database.
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <span>&copy; 2026 MediConnect. All rights reserved.</span>
            <div>
                <a href="articles/news.php">Medical News</a>
                <?php if ($isStaff): ?>
                    <a href="articles/manage.php">Author Portal</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</body>
</html>