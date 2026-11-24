<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

$articleId = (int)($_GET['id'] ?? 0);
$maxCommentLength = 1000;
$detailUrl = 'detail.php?id=' . $articleId;
$flash = $_SESSION['comment_flash'] ?? null;
unset($_SESSION['comment_flash']);

function redirectWithCommentFlash(string $type, string $text, string $url): void {
    $_SESSION['comment_flash'] = ['type' => $type, 'text' => $text];
    header("Location: " . $url);
    exit();
}

try {
    $stmtArt = $pdo->prepare("SELECT a.*, u.full_name as author_name
    FROM articles a JOIN users u ON a.author_id = u.user_id WHERE a.article_id = ?");
    $stmtArt->execute([$articleId]);
    $article = $stmtArt->fetch();
} catch (\PDOException $e) {
    error_log("article load error: " . $e->getMessage());
    http_response_code(500);
    die("Unable to load the article. Please try again later.");
}

if (!$article) {
    http_response_code(404);
    die("The article does not exist!");
}

$commentError = '';
$commentDraft = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    $commentDraft = trim($_POST['comment_text'] ?? '');
    $userId = $_SESSION['user_id'];

    if (!csrfValid()) {
        $commentError = CSRF_ERROR_MESSAGE;
    } elseif ($commentDraft === '') {
        $commentError = "Please write something before sending.";
    } elseif (mb_strlen($commentDraft) > $maxCommentLength) {
        $commentError = "Your comment is too long (up to " . $maxCommentLength . " characters).";
    } else {
        try {
            $stmtComment = $pdo->prepare("INSERT INTO article_comments (article_id, user_id, comment_text) VALUES (?, ?, ?)");
            $stmtComment->execute([$articleId, $userId, $commentDraft]);
            redirectWithCommentFlash('success', "Your comment has been posted.", $detailUrl);
        } catch (\PDOException $e) {
            error_log("Post comment error: " . $e->getMessage());
            $commentError = "Could not post your comment. Please try again later.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
    if (!csrfValid()) {
        redirectWithCommentFlash('error', CSRF_ERROR_MESSAGE, $detailUrl);
    }

    $commentId = (int)($_POST['comment_id'] ?? 0);
    try {
        $stmtFind = $pdo->prepare("SELECT comment_id, user_id FROM article_comments WHERE comment_id = ? AND article_id = ?");
        $stmtFind->execute([$commentId, $articleId]);
        $target = $stmtFind->fetch();

        if (!$target) {
            redirectWithCommentFlash('error', "The comment does not exist.", $detailUrl);
        }

        if (!canDeleteComment($target)) {
            redirectWithCommentFlash('error', "You do not have permission to delete this comment.", $detailUrl);
        }

        $stmtDel = $pdo->prepare("DELETE FROM article_comments WHERE comment_id = ?");
        $stmtDel->execute([$commentId]);
        redirectWithCommentFlash('success', "Comment deleted.", $detailUrl);
    } catch (\PDOException $e) {
        error_log("Delete comment error: " . $e->getMessage());
        $commentError = "Could not delete the comment. Please try again later.";
    }
}

try {
    $stmtCom = $pdo->prepare("SELECT ac.*, u.full_name FROM article_comments ac JOIN users u ON ac.user_id = u.user_id WHERE ac.article_id = ? ORDER BY ac.created_at DESC");
    $stmtCom->execute([$articleId]);
    $comments = $stmtCom->fetchAll();
} catch (\PDOException $e) {
    error_log("Comments load error: " . $e->getMessage());
    $comments = [];
}

$role = $_SESSION['role_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($article['title']); ?> - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=3">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .navbar-container { gap: 16px; }
        .nav-links { display: flex; align-items: center; flex-wrap: nowrap; gap: 12px; list-style: none; margin: 0; padding: 0; }
        .nav-links > li { position: relative; white-space: nowrap; }
        .nav-links a { white-space: nowrap; }
        .nav-dropdown-menu {
            display: none; position: absolute; top: 100%; left: 0; min-width: 220px;
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); list-style: none;
            padding: 6px 0; margin-top: 6px; z-index: 1000;
        }
        .nav-dropdown:hover .nav-dropdown-menu { display: block; }
        .nav-dropdown-menu li a { display: block; padding: 8px 14px; color: #1e293b; text-decoration: none; font-size: 14px; }
        .nav-dropdown-menu li a:hover { background: #f1f5f9; }
        .msg-success { background: #e6f4ea; color: #137333; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .msg-error { background: #fce8e6; color: #c5221f; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .btn-del-comment { background: none; border: none; color: #d93025; cursor: pointer; font-size: 12px; padding: 0; }
    </style>
</head>
<body>
    <nav class="patient-navbar">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo">MediConnect</a>

            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>

                <?php if (isset($_SESSION['user_id']) && $role === 'ADMIN'): ?>
                    <li><a href="../admin/manage_patients.php">Manage Patients</a></li>
                    <li class="nav-dropdown">
                        <a href="../doctors/admin.php">Manage Doctors &#9662;</a>
                        <ul class="nav-dropdown-menu">
                            <li><a href="../doctors/admin.php">Doctors &amp; Approval</a></li>
                            <li><a href="../doctors/index.php">Doctors List (Public)</a></li>
                            <li><a href="../doctors/register.php">Doctor Register</a></li>
                            <li><a href="../reviews/index.php">Reviews Doctor</a></li>
                        </ul>
                    </li>
                    <li class="nav-dropdown">
                        <a href="manage.php" class="active">Manage Articles &#9662;</a>
                        <ul class="nav-dropdown-menu">
                            <li><a href="manage.php">Manage Articles</a></li>
                            <li><a href="news.php">All Articles &amp; Comments</a></li>
                        </ul>
                    </li>
                    <li><a href="../locations/index.php">Manage Locations</a></li>

                <?php elseif (isset($_SESSION['user_id']) && $role === 'DOCTOR'): ?>
                    <li><a href="../doctor/dashboard.php">Doctor Portal</a></li>
                    <li><a href="../chat.php">Consultation Chat</a></li>
                    <li><a href="news.php" class="active">Articles</a></li>
                    <li><a href="../reviews/index.php">Reviews</a></li>

                <?php elseif (isset($_SESSION['user_id']) && $role === 'PATIENT'): ?>
                    <li><a href="../patient/book_appointment.php">Book Appointment</a></li>
                    <li><a href="../patient/my_appointments.php">My Appointments</a></li>
                    <li><a href="../chat.php">Consultation Chat</a></li>
                    <li><a href="news.php" class="active">Articles</a></li>
                    <li><a href="../reviews/index.php">Reviews</a></li>

                <?php else: ?>
                    <li><a href="news.php" class="active">All Articles</a></li>
                    <li><a href="../doctors/register.php">Doctor Register</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-user">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong></span>
                    <a href="../auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn-logout-patient" style="background:#ffffff; color:#0f766e; border:none;">
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-container" style="max-width: 900px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px;">
        <a href="news.php">← Return to the news list</a>
        <h1><?php echo e($article['title']); ?></h1>
        <p style="color: #666; font-size: 13px;">Author: <strong><?php echo htmlspecialchars($article['author_name']); ?></strong> | Date submit: <?php echo $article['created_at']; ?></p>

        <?php
            $thumb = $article['thumbnail_url'] ?? '';
            if (!empty($thumb) && !preg_match('/^https?:\/\//i', $thumb)) {
                $thumb = '../' . ltrim($thumb, '/');
            }
        ?>
        <?php if (!empty($thumb)): ?>
            <div style="margin: 20px 0;">
                <img src="<?php echo htmlspecialchars($thumb); ?>"
                     alt="<?php echo htmlspecialchars($article['title']); ?>"
                     style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px;">
            </div>
        <?php endif; ?>

        <hr style="margin: 20px 0;">

        <div class="content">
            <strong><?php echo nl2br(e($article['summary'])); ?></strong>
            <div style="margin-top: 20px; line-height: 1.6;">
                <?php echo nl2br(e($article['content'])); ?>
            </div>
        </div>

        <hr style="margin: 30px 0;">
        <h3>Comment</h3>

        <?php if ($flash): ?>
            <div class="<?php echo $flash['type'] === 'success' ? 'msg-success' : 'msg-error'; ?>"><?php echo e($flash['text']); ?></div>
        <?php endif; ?>
        <?php if ($commentError !== ''): ?>
            <div class="msg-error"><?php echo e($commentError); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <form action="detail.php?id=<?php echo $articleId; ?>" method="POST" style="margin-bottom: 20px;">
                <?php echo csrfField(); ?>
                <textarea name="comment_text" rows="3" required maxlength="<?php echo $maxCommentLength; ?>" placeholder="Please share your opinion..." style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"><?php echo e($commentDraft); ?></textarea>
                <button type="submit" name="submit_comment" style="margin-top: 8px; background: #00b074; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Send comment</button>
                <span style="font-size: 12px; color: #888; margin-left: 8px;">Up to <?php echo $maxCommentLength; ?> characters.</span>
            </form>
        <?php else: ?>
            <p><a href="../auth/login.php">Log in</a> to participate in commenting on this article.</p>
        <?php endif; ?>

        <div class="comments-list">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $com): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                        <strong><?php echo e($com['full_name']); ?></strong>
                        <span style="font-size: 12px; color: #888;">(<?php echo e($com['created_at']); ?>)</span>
                        <?php if (canDeleteComment($com)): ?>
                            <form action="detail.php?id=<?php echo $articleId; ?>" method="POST" style="display: inline; float: right;" onsubmit="return confirm('Delete this comment? This cannot be undone.');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo e($com['comment_id']); ?>">
                                <button type="submit" name="delete_comment" class="btn-del-comment">Delete</button>
                            </form>
                        <?php endif; ?>
                        <p style="margin: 5px 0 0 0;"><?php echo nl2br(e($com['comment_text'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No comment yet</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>