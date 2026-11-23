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
    <!-- file CSS chung từ assets -->
    <link rel="stylesheet" href="../assets/css/style.css">
   <!-- thêm để test truoc ghép vào nhóm có the xóa -->
   <style> 
        body { display: block; background-color: #f4f6f9; padding: 20px; }
        .main-container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header-nav { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #00b074; padding-bottom: 15px; margin-bottom: 20px; }
        .article-card { border-bottom: 1px solid #eee; padding: 15px 0; }
        .article-card:last-child { border-bottom: none; }
        .article-title { color: #00b074; text-decoration: none; font-size: 18px; font-weight: bold; }
        .article-title:hover { text-decoration: underline; }
        .meta-info { font-size: 13px; color: #666; margin-top: 5px; }
        .btn-auth { background: #00b074; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; margin-left: 10px; }
        .pagination { margin-top: 20px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .pagination a, .pagination .current { padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #00b074; font-size: 14px; }
        .pagination .current { background: #00b074; color: #fff; border-color: #00b074; }
        .pagination .dots { color: #888; }
    </style>
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