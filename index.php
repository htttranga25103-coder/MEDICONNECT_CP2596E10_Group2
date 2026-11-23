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

$isStaff = isset($_SESSION['user_id']) && in_array($_SESSION['role_name'] ?? '', ['ADMIN', 'DOCTOR'], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - MediConnect</title>
    <link rel="stylesheet" href="assets/css/article_style.css">
</head>
<body>
    <!-- Thanh điều hướng đồng bộ màu -->
    <header class="public-navbar">
        <a href="index.php" class="public-brand">MediConnect</a>
        <nav class="public-nav-links">
            <a href="index.php" class="nav-item active">Home</a>
            <a href="articles/news.php" class="nav-item">All Articles</a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="auth/profile.php" class="nav-item">Profile</a>
                <?php if ($isStaff): ?>
                    <a href="articles/manage.php" class="nav-item">Manage Articles</a>
                    <a href="articles/crawl_articles.php" class="nav-item">Auto Update</a>
                <?php endif; ?>
                <span>Hi, <strong><?php echo e($_SESSION['full_name'] ?? ''); ?></strong></span>
                <a href="auth/logout.php" class="btn-logout">Log out</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-mc btn-mc-teal">Log in</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Nội dung chính -->
    <main class="article-container">
        <div class="panel-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 18px;">
            <h1 class="page-title" style="margin-bottom: 0;">Featured Medical Articles</h1>
            <a href="articles/news.php" class="btn-mc btn-mc-outline-gray">View All Articles &rarr;</a>
        </div>

        <?php if (!empty($articles)): ?>
            <div class="article-list">
                <?php foreach ($articles as $art): ?>
                    <?php
                        // Index nằm ở thư mục gốc nên giữ nguyên đường dẫn uploads/... hoặc https://...
                        $thumb = ltrim((string)($art['thumbnail_url'] ?? ''), '/');
                    ?>
                    <article class="article-card">
                        <?php if ($thumb !== ''): ?>
                            <a href="articles/detail.php?id=<?php echo (int)$art['article_id']; ?>" class="article-thumb-wrap">
                                <img src="<?php echo e($thumb); ?>" alt="<?php echo e($art['title']); ?>" class="article-thumb">
                            </a>
                        <?php endif; ?>

                        <div class="article-body">
                            <span class="article-category-tag">
                                <?php echo e($art['category_name'] ?? 'General'); ?>
                            </span>

                            <a href="articles/detail.php?id=<?php echo (int)$art['article_id']; ?>" class="article-title">
                                <?php echo e($art['title']); ?>
                            </a>

                            <div class="article-meta">
                                <span>Author: <strong><?php echo e($art['author_name']); ?></strong></span>
                                <span>&bull;</span>
                                <span>Published: <?php echo e($art['created_at']); ?></span>
                            </div>

                            <p class="article-summary">
                                <?php echo e($art['summary'] ?? ''); ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="panel-card">
                <p class="text-muted-italic">No articles found in the database.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="admin-footer">
        <span>&copy; 2026 MediConnect. All rights reserved.</span>
        <div class="footer-links">
            <a href="articles/news.php">Medical News</a>
            <?php if ($isStaff): ?>
                <a href="articles/manage.php">Author Portal</a>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>