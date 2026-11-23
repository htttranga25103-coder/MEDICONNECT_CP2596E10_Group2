<?php
error_reporting(E_ALL);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$perPage = 10;

try {
    $totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $totalPages    = max(1, (int)ceil($totalArticles / $perPage));
    $page          = currentPage($totalPages);

    $stmt = $pdo->prepare("
        SELECT a.*, ac.name AS category_name, u.full_name AS author_name
        FROM articles a
        LEFT JOIN article_categories ac ON a.category_id = ac.category_id
        JOIN users u ON a.author_id = u.user_id
        ORDER BY a.created_at DESC, a.article_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("News load error: " . $e->getMessage());
    die("Unable to load articles. Please try again later.");
}

$isStaff = isset($_SESSION['user_id']) && in_array($_SESSION['role_name'] ?? '', ['ADMIN', 'DOCTOR'], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Articles - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <header class="public-navbar">
        <a href="../index.php" class="public-brand">MediConnect</a>
        <nav class="public-nav-links">
            <a href="../index.php" class="nav-item">Home</a>
            <a href="news.php" class="nav-item active">All Articles</a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="../auth/profile.php" class="nav-item">Profile</a>
                <?php if ($isStaff): ?>
                    <a href="manage.php" class="nav-item">Manage Articles</a>
                <?php endif; ?>
                <span>Hi, <strong><?php echo e($_SESSION['full_name'] ?? ''); ?></strong></span>
                <a href="../auth/logout.php" class="btn-logout">Log out</a>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-mc btn-mc-teal">Log in</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="article-container">
        <div class="panel-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 18px;">
            <h1 class="page-title" style="margin-bottom: 0;">Medical Articles</h1>
            <a href="../index.php" class="btn-mc btn-mc-outline-gray">&larr; Back to Home</a>
        </div>

        <?php if (!empty($articles)): ?>
            <div class="article-list">
                <?php foreach ($articles as $art): ?>
                    <?php
                        $thumb = $art['thumbnail_url'] ?? '';
                        if (!empty($thumb) && !preg_match('/^https?:\/\//i', $thumb)) {
                            $thumb = '../' . ltrim($thumb, '/');
                        }
                    ?>
                    <article class="article-card">
                        <?php if (!empty($thumb)): ?>
                            <a href="detail.php?id=<?php echo (int)$art['article_id']; ?>" class="article-thumb-wrap">
                                <img src="<?php echo e($thumb); ?>" alt="<?php echo e($art['title']); ?>" class="article-thumb">
                            </a>
                        <?php endif; ?>

                        <div class="article-body">
                            <span class="article-category-tag">
                                <?php echo e($art['category_name'] ?? 'General'); ?>
                            </span>

                            <a href="detail.php?id=<?php echo (int)$art['article_id']; ?>" class="article-title">
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

            <div class="pagination-wrapper">
                <?php echo renderPagination($page, $totalPages, 'news.php'); ?>
            </div>
        <?php else: ?>
            <div class="panel-card">
                <p class="text-muted-italic">No articles found in the database.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="admin-footer">
        <span>&copy; 2026 MediConnect. All rights reserved.</span>
    </footer>
</body>
</html>