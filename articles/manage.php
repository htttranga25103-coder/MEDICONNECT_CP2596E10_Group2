<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

requireStaff();
$userId  = (int) $_SESSION['user_id'];
$role    = $_SESSION['role_name'] ?? '';
$isAdmin = $role === 'ADMIN';
$perPage = 10;

$message     = '';
$messageType = '';

if (isset($_SESSION['manage_flash'])) {
    $messageType = $_SESSION['manage_flash']['type'];
    $message     = $_SESSION['manage_flash']['text'];
    unset($_SESSION['manage_flash']);
}

function redirectWithFlash(string $type, string $text, string $url = 'manage.php'): void {
    $_SESSION['manage_flash'] = ['type' => $type, 'text' => $text];
    header("Location: " . $url);
    exit();
}

$form = ['title' => '', 'slug' => '', 'category_id' => '', 'summary' => '', 'content' => '', 'thumbnail_url' => ''];

try {
    $categories = $pdo->query("SELECT category_id, name FROM article_categories ORDER BY name")->fetchAll();
} catch (\PDOException $e) {
    error_log("Load categories error: " . $e->getMessage());
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_article'])) {
    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    $error = '';
    if (!csrfValid()) {
        $error = CSRF_ERROR_MESSAGE;
    } else {
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
    }
    $message     = $error;
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $deleteId = (int)($_POST['article_id'] ?? 0);
    $backPage = max(1, (int)($_POST['page'] ?? 1));
    $backUrl  = 'manage.php' . ($backPage > 1 ? '?page=' . $backPage : '');

    if (!csrfValid()) {
        redirectWithFlash('error', CSRF_ERROR_MESSAGE, $backUrl);
    }

    try {
        $stmt = $pdo->prepare("SELECT article_id, author_id FROM articles WHERE article_id = ?");
        $stmt->execute([$deleteId]);
        $target = $stmt->fetch();

        if (!$target) {
            redirectWithFlash('error', "The article does not exist.", $backUrl);
        }
        if (!canManageArticle($target)) {
            redirectWithFlash('error', "You do not have permission to delete this article.", $backUrl);
        }
        $stmt = $pdo->prepare("DELETE FROM articles WHERE article_id = ?");
        $stmt->execute([$deleteId]);
        redirectWithFlash('success', "Article deleted.", $backUrl);
    } catch (\PDOException $e) {
        error_log("Delete article error: " . $e->getMessage());
        $message     = "Could not delete the article. Please try again later.";
        $messageType = 'error';
    }
}

$articles      = [];
$totalArticles = 0;
$totalPages    = 1;
$page          = 1;
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
        SELECT a.article_id, a.title, a.author_id, a.thumbnail_url, a.created_at,
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Articles - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/article_style.css?v=2">
    <style>
        .topbar {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
        }
        .topbar-user span {
            color: #1e293b !important;
        }
        .btn-logout {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 7px 16px !important;
            font-weight: 700 !important;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php if ($isAdmin): ?>
            <!-- SHARED ADMIN SIDEBAR WITH ICONS -->
            <?php
                $adminActivePage = 'manage.php';
                require_once __DIR__ . '/../includes/admin_sidebar.php';
            ?>
        <?php else: ?>
            <!-- DOCTOR AUTHOR SIDEBAR -->
            <aside class="sidebar" style="background-color: #0f766e;">
                <a href="../index.php" class="sidebar-brand">MediConnect</a>
                <ul class="sidebar-menu">
                    <li><a href="../views/doctor_dashboard.php"><img src="../images/icons/overview.png" width="20" height="20" alt="" style="vertical-align:middle; margin-right:8px;">Doctor Portal</a></li>
                    <li><a href="../chat.php"><img src="../images/icons/comment.png" width="20" height="20" alt="" style="vertical-align:middle; margin-right:8px;">Consultation Chat</a></li>
                    <li><a href="news.php"><img src="../images/icons/new.png" width="20" height="20" alt="" style="vertical-align:middle; margin-right:8px;">Public Articles</a></li>
                    <li><a href="../reviews/index.php"><img src="../images/icons/review.png" width="20" height="20" alt="" style="vertical-align:middle; margin-right:8px;">Reviews</a></li>
                    <li><a href="manage.php" class="active"><img src="../images/icons/new.png" width="20" height="20" alt="" style="vertical-align:middle; margin-right:8px;">Manage Articles</a></li>
                </ul>
            </aside>
        <?php endif; ?>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-user" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 16px; font-weight: 700;">
                        Article Management — Welcome, <strong><?php echo e($_SESSION['full_name'] ?? 'Author'); ?></strong> (<?php echo e($_SESSION['role_name'] ?? ''); ?>)
                    </span>
                    <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                </div>
            </header>

            <main class="content-container">
                <?php if ($message !== ''): ?>
                    <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                        <span><?php echo e($message); ?></span>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="stat-summary-row">
                    <div class="stat-summary-card">
                        <div class="stat-summary-label"><?php echo $isAdmin ? 'Total System Articles' : 'My Published Articles'; ?></div>
                        <div class="stat-summary-value-row">
                            <span class="stat-summary-number"><?php echo $totalArticles; ?></span>
                            <span class="stat-summary-sub">articles</span>
                        </div>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title"><?php echo $isAdmin ? 'All Articles' : 'Your Articles'; ?></h2>
                        <a href="news.php" class="btn-mc btn-mc-outline-gray">View Public Page</a>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Thumbnail</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <?php if ($isAdmin): ?><th>Author</th><?php endif; ?>
                                    <th>Date</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($articles)): ?>
                                <?php foreach ($articles as $art): ?>
                                    <?php
                                        $thumb = $art['thumbnail_url'] ?? '';
                                        if (!empty($thumb) && !preg_match('/^https?:\/\//i', $thumb)) {
                                            $thumb = '../' . ltrim($thumb, '/');
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo e($art['article_id']); ?></td>
                                        <td>
                                            <?php if (!empty($thumb)): ?>
                                                <img src="<?php echo e($thumb); ?>" alt="" class="table-article-thumb">
                                            <?php else: ?>
                                                <span class="text-muted-italic">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="detail.php?id=<?php echo e($art['article_id']); ?>" class="article-title" style="font-size: 14.5px; margin-bottom: 0;">
                                                <?php echo e($art['title']); ?>
                                            </a>
                                        </td>
                                        <td><span class="article-category-tag" style="margin-bottom: 0;"><?php echo e($art['category_name'] ?? 'General'); ?></span></td>
                                        <?php if ($isAdmin): ?><td><?php echo e($art['author_name']); ?></td><?php endif; ?>
                                        <td><span class="text-sub"><?php echo e($art['created_at']); ?></span></td>
                                        <td><?php echo e($art['comment_count']); ?></td>
                                        <td class="action-cell">
                                            <?php if (canManageArticle($art)): ?>
                                                <a class="btn-mc btn-mc-teal" href="edit.php?id=<?php echo e($art['article_id']); ?>">Edit</a>
                                                <?php
                                                $confirmMsg = 'Delete "' . $art['title'] . '"? '
                                                    . ((int)$art['comment_count'] > 0 ? $art['comment_count'] . ' comment(s) on it will be deleted too. ' : '')
                                                    . 'This cannot be undone.';
                                                ?>
                                                <form action="manage.php" method="POST" style="display:inline;" onsubmit="return confirm(<?php echo e(json_encode($confirmMsg, JSON_UNESCAPED_UNICODE)); ?>);">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="article_id" value="<?php echo e($art['article_id']); ?>">
                                                    <input type="hidden" name="page" value="<?php echo e($page); ?>">
                                                    <button type="submit" name="delete_article" class="btn-mc btn-mc-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $isAdmin ? 8 : 7; ?>" class="empty-cell">
                                        <?php echo $isAdmin ? 'There are no articles yet.' : 'You have not posted any articles yet.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-wrapper">
                        <?php echo renderPagination($page, $totalPages, 'manage.php'); ?>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">Add a New Article</h2>
                    </div>
                    <?php include '../includes/article_form.php'; ?>
                </div>
            </main>

            <footer class="admin-footer">
                <span>&copy; 2026 MediConnect. All rights reserved.</span>
                <div class="footer-links">
                    <a href="news.php">Public News</a>
                    <a href="../index.php">Home</a>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>