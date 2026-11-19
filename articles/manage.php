<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaff();   // chỉ ADMIN và DOCTOR được vào
$userId  = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role_name'] ?? '') === 'ADMIN';
$perPage = 10;

$message     = '';
$messageType = '';

// Thông báo lưu từ lần thao tác trước (hiện 1 lần rồi xóa)
if (isset($_SESSION['manage_flash'])) {
    $messageType = $_SESSION['manage_flash']['type'];
    $message     = $_SESSION['manage_flash']['text'];
    unset($_SESSION['manage_flash']);
}

// Chuyển hướng sau khi xử lý xong để tránh gửi lại form khi nhấn F5
function redirectWithFlash(string $type, string $text, string $url = 'manage.php'): void {
    $_SESSION['manage_flash'] = ['type' => $type, 'text' => $text];
    header("Location: " . $url);
    exit();
}

$form = ['title' => '', 'slug' => '', 'category_id' => '', 'summary' => '', 'content' => '', 'thumbnail_url' => ''];

// Danh sách danh mục cho ô chọn
try {
    $categories = $pdo->query("SELECT category_id, name FROM article_categories ORDER BY name")->fetchAll();
} catch (\PDOException $e) {
    error_log("Load categories error: " . $e->getMessage());
    $categories = [];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_article'])) {
    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');   // giữ lại nội dung đã gõ nếu có lỗi
    }
    $error = '';
    try {
        [$data, $error] = parseArticleForm($pdo, $_POST);
        if ($error === '') {
            $stmt = $pdo->prepare("INSERT INTO articles (category_id, author_id, title, slug, summary, content, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['category_id'], $userId, $data['title'], $data['slug'], $data['summary'], $data['content'], $data['thumbnail_url']]);
            redirectWithFlash('success', "Article added successfully!");
        }
    } catch (\PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            $error = "This slug is already used by another article.";
        } else {
            error_log("Create article error: " . $e->getMessage());
            $error = "Could not add the article. Please try again later.";
        }
    }
    $message     = $error;
    $messageType = 'error';
}

// xoa bai 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $deleteId = (int)($_POST['article_id'] ?? 0);
    $backPage = max(1, (int)($_POST['page'] ?? 1));
    $backUrl  = 'manage.php' . ($backPage > 1 ? '?page=' . $backPage : '');
    try {
        $stmt = $pdo->prepare("SELECT article_id, author_id FROM articles WHERE article_id = ?");
        $stmt->execute([$deleteId]);
        $target = $stmt->fetch();

        if (!$target) {
            redirectWithFlash('error', "The article does not exist.", $backUrl);
        }
        // Kiểm tra quyền ở phía server (không tin vào việc nút bị ẩn)
        if (!canManageArticle($target)) {
            redirectWithFlash('error', "You do not have permission to delete this article.", $backUrl);
        }
        $stmt = $pdo->prepare("DELETE FROM articles WHERE article_id = ?");
        $stmt->execute([$deleteId]);   // bình luận của bài tự động bị xóa theo (ON DELETE CASCADE)
        redirectWithFlash('success', "Article deleted.", $backUrl);
    } catch (\PDOException $e) {
        error_log("Delete article error: " . $e->getMessage());
        $message     = "Could not delete the article. Please try again later.";
        $messageType = 'error';
    }
}

// ===== DANH SÁCH BÀI VIẾT (có phân trang) =====
// Admin thấy tất cả bài; Doctor chỉ thấy bài của mình
$articles   = [];
$totalPages = 1;
$page       = 1;
try {
    $where = $isAdmin ? '' : 'WHERE a.author_id = :uid';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles a $where");
    if (!$isAdmin) {
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $totalArticles = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalArticles / $perPage));
    $page       = currentPage($totalPages);

    $stmt = $pdo->prepare("
        SELECT a.article_id, a.title, a.author_id, a.created_at,
               ac.name AS category_name, u.full_name AS author_name,
               (SELECT COUNT(*) FROM article_comments c WHERE c.article_id = a.article_id) AS comment_count
        FROM articles a
        LEFT JOIN article_categories ac ON a.category_id = ac.category_id
        JOIN users u ON a.author_id = u.user_id
        $where
        ORDER BY a.created_at DESC, a.article_id DESC
        LIMIT :limit OFFSET :offset
    ");
    if (!$isAdmin) {
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Load articles error: " . $e->getMessage());
    $message     = "Could not load the article list. Please try again later.";
    $messageType = 'error';
}

$formAction  = 'manage.php';
$submitName  = 'create_article';
$submitLabel = 'Post an article';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Articles - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/article_style.css">
    <style>
        .msg-success { background: #e6f4ea; color: #137333; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .msg-error { background: #fce8e6; color: #c5221f; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .article-table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        .article-table th, .article-table td { border-bottom: 1px solid #eee; padding: 8px 6px; text-align: left; vertical-align: top; }
        .article-table th { background: #f8f9fa; }
        .table-wrap { overflow-x: auto; }
        .act-link { color: #00b074; text-decoration: none; font-weight: bold; margin-right: 8px; }
        .act-delete { background: none; border: none; color: #d93025; font-weight: bold; cursor: pointer; padding: 0; font-size: 14px; }
        .inline-form { display: inline; }
        .pagination { margin-top: 15px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .pagination a, .pagination .current { padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #00b074; font-size: 14px; }
        .pagination .current { background: #00b074; color: #fff; border-color: #00b074; }
        .pagination .dots { color: #888; }
        .muted { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <div class="main-container" style="max-width: 960px;">
        <h2>Manage & add new article</h2>
        <a href="news.php">View articles</a> | <a href="../index.php">Return to home page</a>
        <hr style="margin: 20px 0;">

        <?php if ($message !== ''): ?>
            <div class="<?php echo $messageType === 'success' ? 'msg-success' : 'msg-error'; ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <h3><?php echo $isAdmin ? 'All articles' : 'Your articles'; ?></h3>
        <?php if (!empty($articles)): ?>
            <div class="table-wrap">
            <table class="article-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <?php if ($isAdmin): ?><th>Author</th><?php endif; ?>
                        <th>Date</th>
                        <th>Comments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $art): ?>
                    <tr>
                        <td><?php echo e($art['article_id']); ?></td>
                        <td><a href="detail.php?id=<?php echo e($art['article_id']); ?>" style="color:#333;"><?php echo e($art['title']); ?></a></td>
                        <td><?php echo e($art['category_name'] ?? '-'); ?></td>
                        <?php if ($isAdmin): ?><td><?php echo e($art['author_name']); ?></td><?php endif; ?>
                        <td><?php echo e($art['created_at']); ?></td>
                        <td><?php echo e($art['comment_count']); ?></td>
                        <td>
                            <?php if (canManageArticle($art)): ?>
                                <a class="act-link" href="edit.php?id=<?php echo e($art['article_id']); ?>">Edit</a>
                                <?php
                                $confirmMsg = 'Delete "' . $art['title'] . '"? '
                                    . ((int)$art['comment_count'] > 0 ? $art['comment_count'] . ' comment(s) on it will be deleted too. ' : '')
                                    . 'This cannot be undone.';
                                ?>
                                <form class="inline-form" action="manage.php" method="POST" onsubmit="return confirm(<?php echo e(json_encode($confirmMsg, JSON_UNESCAPED_UNICODE)); ?>);">
                                    <input type="hidden" name="article_id" value="<?php echo e($art['article_id']); ?>">
                                    <input type="hidden" name="page" value="<?php echo e($page); ?>">
                                    <button type="submit" name="delete_article" class="act-delete">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php echo renderPagination($page, $totalPages, 'manage.php'); ?>
        <?php else: ?>
            <p class="muted"><?php echo $isAdmin ? 'There are no articles yet.' : 'You have not posted any articles yet.'; ?></p>
        <?php endif; ?>

        <hr style="margin: 25px 0;">
        <h3>Add a new article</h3>
        <?php include '../includes/article_form.php'; ?>
    </div>
</body>
</html>
