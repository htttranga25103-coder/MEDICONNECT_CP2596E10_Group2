<?php
session_start();
require_once '../config/database.php';
$articleId = $_GET['id'] ?? 0;

//  gửi bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    $commentText = trim($_POST['comment_text']);
    $userId = $_SESSION['user_id'];

    if (!empty($commentText)) {
        $stmtComment = $pdo->prepare("INSERT INTO article_comments (article_id, user_id, comment_text) VALUES (?, ?, ?)");
        $stmtComment->execute([$articleId, $userId, $commentText]);
        header("Location: detail.php?id=" . $articleId);
        exit();
    }
}

// thông tin chi tiết bài viết
$stmtArt = $pdo->prepare("SELECT a.*, u.full_name as author_name FROM articles a JOIN users u ON a.author_id = u.user_id WHERE a.article_id = ?");
$stmtArt->execute([$articleId]);
$article = $stmtArt->fetch();

if (!$article) {
    die("The article does not exist!");
}

// danh sách bình luận
$stmtCom = $pdo->prepare("SELECT ac.*, u.full_name FROM article_comments ac JOIN users u ON ac.user_id = u.user_id WHERE ac.article_id = ? ORDER BY ac.created_at DESC");
$stmtCom->execute([$articleId]);
$comments = $stmtCom->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($article['title']); ?> MediConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="main-container" style="max-width: 900px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px;">
        <a href="news.php">←Return to the news list </a>
        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
        <p style="color: #666; font-size: 13px;">Author: <strong><?php echo htmlspecialchars($article['author_name']); ?></strong> | Date submit: <?php echo $article['created_at']; ?></p>
        <hr style="margin: 20px 0;">
        
        <div class="content">
             <?php echo nl2br(htmlspecialchars($article['summary'])); ?>
            <div style="margin-top: 20px; line-height: 1.6;">
            <?php echo nl2br(htmlspecialchars($article['content'])); ?>
            </div>
        </div>

        <hr style="margin: 30px 0;">
        <h3>Comment</h3>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <form action="detail.php?id=<?php echo $articleId; ?>" method="POST" style="margin-bottom: 20px;">
                <textarea name="comment_text" rows="3" required placeholder="Please share your opinion..." style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
                <button type="submit" name="submit_comment" style="margin-top: 8px; background: #00b074; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Sent comment</button>
            </form>
        <?php else: ?>
            <p><a href="../auth/login.php">Log in</a> to participate in commenting on this article.</p>
        <?php endif; ?>

        <div class="comments-list">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $com): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                        <strong><?php echo htmlspecialchars($com['full_name']); ?></strong> 
                        <span style="font-size: 12px; color: #888;">(<?php echo $com['created_at']; ?>)</span>
                        <p style="margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($com['comment_text'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No comment yet</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>