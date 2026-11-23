<?php
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$perPage = 10;   // số bài mỗi trang

try {
    //u.user_id chính xác tuyệt đối
    $totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $totalPages    = max(1, (int)ceil($totalArticles / $perPage));
    $page          = currentPage($totalPages);

    // article_id DESC giúp thứ tự luôn cố định khi nhiều bài có cùng thời gian đăng
    $stmt = $pdo->prepare("
        SELECT a.*, ac.name as category_name, u.full_name as author_name
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
    die("SQL query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="main-container">
        <div class="header-nav">
            <div>
                <a href="../index.php" style="text-decoration: none; color: #00b074; font-weight: bold; font-size: 24px;">MediConnect</a>
            </div>
            <div>
                <a href="../index.php" style="margin-right: 15px; text-decoration: none; color: #00b074; font-weight: bold;">Home</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></strong>!</span>
                    <a href="../auth/logout.php" class="btn-auth" style="background: #00b074;">Log out</a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn-auth">Log in</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nằm ngoài header-nav để thêm test bố cục -->
       <h2 style="color: #333; margin-top: 0;">Featured Medical Article</h2>
        
    <?php if (!empty($articles)): ?>
        <?php foreach ($articles as $art): ?>
            <?php
                // Xử lý đường dẫn ảnh: nếu là link http thì giữ nguyên, nếu là file local thì thêm ../ vì file đang nằm trong thư mục articles/
                $thumb = $art['thumbnail_url'] ?? '';
                if (!empty($thumb) && !preg_match('/^https?:\/\//i', $thumb)) {
                    $thumb = '../' . ltrim($thumb, '/');
                }
            ?>
            <div class="article-card">
                <?php if (!empty($thumb)): ?>
                    <a href="detail.php?id=<?php echo $art['article_id']; ?>">
                        <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>" class="article-thumb">
                    </a>
                <?php endif; ?>

            <div class="article-info">
                <a href="detail.php?id=<?php echo $art['article_id']; ?>" class="article-title">
                    <?php echo htmlspecialchars($art['title']); ?>
                </a>
                <div class="meta-info">
                    Category: <strong><?php echo htmlspecialchars($art['category_name'] ?? 'General'); ?></strong> | 
                    Author: <?php echo htmlspecialchars($art['author_name']); ?> | 
                    Date Published: <?php echo $art['created_at']; ?>
                </div>
                <p style="color: #444; margin-top: 8px;"><?php echo htmlspecialchars($art['summary'] ?? ''); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">No articles found in the database</p>
    <?php endif; ?>

        <?php echo renderPagination($page, $totalPages, 'news.php'); ?>
    </div>
</body>
</html>