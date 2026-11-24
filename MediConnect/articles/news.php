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

$role    = $_SESSION['role_name'] ?? '';
$isStaff = isset($_SESSION['user_id']) && in_array($role, ['ADMIN', 'DOCTOR'], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Articles - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=3">
    <link rel="stylesheet" href="../assets/css/article_style.css?v=2">
    <style>
        .navbar-container { gap: 16px; }
        .nav-links { display: flex; align-items: center; flex-wrap: nowrap; gap: 12px; }
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