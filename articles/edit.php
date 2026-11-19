<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaff();   // chỉ ADMIN và DOCTOR được vào

// Hiện thông báo đơn giản rồi dừng (dùng cho lỗi không tìm thấy / không có quyền)
function stopWithMessage(int $code, string $text): void {
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>MediConnect</title></head><body>'
       . '<div style="max-width:600px;margin:50px auto;font-family:Arial,sans-serif;">'
       . '<p>' . e($text) . '</p><a href="manage.php">&larr; Back to manage articles</a></div></body></html>';
    exit();
}

$articleId = (int)($_GET['id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE article_id = ?");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch();

    $categories = $pdo->query("SELECT category_id, name FROM article_categories ORDER BY name")->fetchAll();
} catch (\PDOException $e) {
    error_log("Edit load error: " . $e->getMessage());
    stopWithMessage(500, "Could not load the article. Please try again later.");
}

if (!$article) {
    stopWithMessage(404, "The article does not exist.");
}
// Kiểm tra quyền ở phía server: Admin sửa mọi bài, Doctor chỉ sửa bài của mình
if (!canManageArticle($article)) {
    stopWithMessage(403, "You do not have permission to edit this article.");
}

$message     = '';
$messageType = '';

// Giá trị hiển thị trong form (mặc định lấy từ database)
$form = [
    'title'         => $article['title'],
    'slug'          => $article['slug'],
    'category_id'   => $article['category_id'] ?? '',
    'summary'       => $article['summary'] ?? '',
    'content'       => $article['content'],
    'thumbnail_url' => $article['thumbnail_url'] ?? '',
];

// ===== LƯU THAY ĐỔI =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_article'])) {
    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');   // giữ lại nội dung đã gõ nếu có lỗi
    }
    $error = '';
    try {
        [$data, $error] = parseArticleForm($pdo, $_POST, $articleId);
        if ($error === '') {
            $stmt = $pdo->prepare("UPDATE articles SET category_id = ?, title = ?, slug = ?, summary = ?, content = ?, thumbnail_url = ? WHERE article_id = ?");
            $stmt->execute([$data['category_id'], $data['title'], $data['slug'], $data['summary'], $data['content'], $data['thumbnail_url'], $articleId]);

            $_SESSION['manage_flash'] = ['type' => 'success', 'text' => "Article updated successfully!"];
            header("Location: manage.php");
            exit();
        }
    } catch (\PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            $error = "This slug is already used by another article.";
        } else {
            error_log("Update article error: " . $e->getMessage());
            $error = "Could not update the article. Please try again later.";
        }
    }
    $message     = $error;
    $messageType = 'error';
}

$formAction  = 'edit.php?id=' . $articleId;
$submitName  = 'update_article';
$submitLabel = 'Save changes';
$cancelUrl   = 'manage.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Article - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/article_style.css">
    <style>
        .msg-error { background: #fce8e6; color: #c5221f; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="main-container" style="max-width: 800px;">
        <h2>Edit article</h2>
        <a href="manage.php">&larr; Back to manage articles</a> | <a href="detail.php?id=<?php echo e($articleId); ?>">View article</a>
        <hr style="margin: 20px 0;">

        <?php if ($message !== ''): ?>
            <div class="msg-error"><?php echo e($message); ?></div>
        <?php endif; ?>

        <?php include '../includes/article_form.php'; ?>
    </div>
</body>
</html>