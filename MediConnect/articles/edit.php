<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

requireStaff();
$role    = $_SESSION['role_name'] ?? '';
$isAdmin = $role === 'ADMIN';

function stopWithMessage(int $code, string $text): void {
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>MediConnect</title>'
       . '<link rel="stylesheet" href="../assets/css/article_style.css"></head><body>'
       . '<div class="article-container"><div class="panel-card">'
       . '<div class="alert-box alert-error"><span>' . e($text) . '</span></div>'
       . '<a href="manage.php" class="btn-mc btn-mc-outline-gray">&larr; Back to manage articles</a>'
       . '</div></div></body></html>';
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
if (!canManageArticle($article)) {
    stopWithMessage(403, "You do not have permission to edit this article.");
}

$message     = '';
$messageType = '';

$form = [
    'title'         => $article['title'],
    'slug'          => $article['slug'],
    'category_id'   => $article['category_id'] ?? '',
    'summary'       => $article['summary'] ?? '',
    'content'       => $article['content'],
    'thumbnail_url' => $article['thumbnail_url'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_article'])) {
    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');
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
    <link rel="stylesheet" href="../assets/css/article_style.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <a href="../index.php" class="sidebar-brand">MediConnect</a>
            <ul class="sidebar-menu">
                <li><a href="../index.php">Home</a></li>
                <?php if ($isAdmin): ?>
                    <li><a href="../admin/manage_patients.php">Manage Patients</a></li>
                    <li><a href="../doctors/admin.php">Manage Doctors</a></li>
                    <li><a href="../reviews/index.php">Reviews Doctor</a></li>
                    <li><a href="manage.php" class="active">Manage Articles</a></li>
                    <li><a href="news.php">All Articles &amp; Comments</a></li>
                    <li><a href="../locations/index.php">Manage Locations</a></li>
                <?php else: ?>
                    <li><a href="../doctor/dashboard.php">Doctor Portal</a></li>
                    <li><a href="../chat.php">Consultation Chat</a></li>
                    <li><a href="news.php">Articles</a></li>
                    <li><a href="../reviews/index.php">Reviews</a></li>
                    <li><a href="manage.php" class="active">Manage Articles</a></li>
                <?php endif; ?>
            </ul>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-user">
                    <span>Welcome, <strong><?php echo e($_SESSION['full_name'] ?? 'Author'); ?></strong> (<?php echo e($_SESSION['role_name'] ?? ''); ?>)</span>
                    <a href="../auth/logout.php" class="btn-logout">Log out</a>
                </div>
            </header>

            <main class="content-container">
                <h1 class="page-title">Edit Article</h1>

                <?php if ($message !== ''): ?>
                    <div class="alert-box alert-error">
                        <span><?php echo e($message); ?></span>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Editing: <?php echo e($article['title']); ?></h2>
                        <div style="display: flex; gap: 8px;">
                            <a href="detail.php?id=<?php echo e($articleId); ?>" class="btn-mc btn-mc-outline-gray">View Article</a>
                            <a href="manage.php" class="btn-mc btn-mc-outline-gray">&larr; Back to List</a>
                        </div>
                    </div>

                    <?php include '../includes/article_form.php'; ?>
                </div>
            </main>

            <footer class="admin-footer">
                <span>&copy; 2026 MediConnect. All rights reserved.</span>
            </footer>
        </div>
    </div>
</body>
</html>