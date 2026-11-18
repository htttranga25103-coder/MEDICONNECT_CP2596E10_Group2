<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['ADMIN', 'DOCTOR'])) {
    header("Location: ../auth/login.php");
    exit();
}
$authorId = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_article'])) {
    $categoryId   = $_POST['category_id'];
    $title        = trim($_POST['title']);
    $slug         = trim($_POST['slug']);
    $summary      = trim($_POST['summary']);
    $content      = trim($_POST['content']);
    $thumbnailUrl = trim($_POST['thumbnail_url']);

    try {
        $stmt = $pdo->prepare("INSERT INTO articles (category_id, author_id, title, slug, summary, content, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoryId, $authorId, $title, $slug, $summary, $content, $thumbnailUrl]);
        $message = "Article added successfully!";
    } catch (\PDOException $e) {
        $message = "Lỗi: " . $e->getMessage();
    }
}
$categories = $pdo->query("SELECT * FROM article_categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Manage Articles - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="main-container" style="max-width: 800px;">
        <h2>Manage& add new article</h2>
        <a href="news.php">View articles</a> | <a href="../index.php">Về trang chủ</a>
        <hr style="margin: 20px 0;">

        <?php if (!empty($message)): ?>
            <div style="background: #e6f4ea; color: #137333; padding: 10px; border-radius: 6px; margin-bottom: 15px;"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="manage.php" method="POST">
            <div class="form-group-custom">
                <label>Article title:</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group-custom">
                <label>Slug:</label>
                <input type="text" name="slug" required>
            </div>
            <div class="form-group-custom">
                <label>Category Articles:</label>
                <select name="category_id">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-custom">
                <label>Short Summary:</label>
                <textarea name="summary" rows="3"></textarea>
            </div>
            <div class="form-group-custom">
                <label>Detailed Content:</label>
                <textarea name="content" rows="6"></textarea>
            </div>
            <div class="form-group-custom">
                <label>Thumbnail image path:</label>
                <input type="text" name="thumbnail_url">
            </div>
            <button type="submit" name="create_article" class="btn-submit">Đăng bài viết</button>
        </form>
    </div>
</body>
</html>