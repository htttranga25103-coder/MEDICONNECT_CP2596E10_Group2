<?php
session_start();
require_once __DIR__ . '/models/homeclass.php';

$roleName = strtoupper($_SESSION['role_name'] ?? '');
$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$isStaff = $isLoggedIn && in_array($roleName, ['ADMIN', 'DOCTOR'], true);

$doctors = Doctor::getFeaturedDoctors();
$articles = Doctor::getLatestArticles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - MediConnect</title>
    <link rel="stylesheet" href="assets/css/patient_booking.css?v=2">
    <link rel="stylesheet" href="assets/css/article_style.css?v=2">
    <link rel="stylesheet" href="assets/css/home_style.css?v=3">
</head>
<body>
    <!-- Thanh điều hướng phân quyền -->
    <nav class="patient-navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand-logo">MediConnect</a>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>

                <?php if ($isLoggedIn && $roleName === 'ADMIN'): ?>
                    <li><a href="views/admin_dashboard.php">Dashboard Overview</a></li>
                    <li><a href="admin/manage_patients.php">Manage Patients</a></li>

                    <li class="nav-dropdown">
                        <a href="doctors/admin.php">Manage Doctors ▾</a>
                        <ul class="nav-dropdown-menu">
                            <li><a href="doctors/admin.php">Doctors &amp; Approval</a></li>
                            <li><a href="doctors/index.php">Doctors List (Public)</a></li>
                            <li><a href="doctors/register.php">Doctor Register</a></li>
                            <li><a href="reviews/index.php">Reviews Doctor</a></li>
                        </ul>
                    </li>

                    <li class="nav-dropdown">
                        <a href="articles/manage.php">Manage Articles ▾</a>
                        <ul class="nav-dropdown-menu">
                            <li><a href="articles/manage.php">Add / Edit / Delete Articles</a></li>
                            <li><a href="articles/news.php">All Articles &amp; Comments</a></li>
                        </ul>
                    </li>

                    <li><a href="locations/index.php">Manage Locations</a></li>

                <?php elseif ($isLoggedIn && $roleName === 'DOCTOR'): ?>
                    <li><a href="views/doctor_dashboard.php">Doctor Portal</a></li>
                    <li><a href="chat.php">Consultation Chat</a></li>
                    <li><a href="articles/news.php">Articles</a></li>
                    <li><a href="reviews/index.php">Reviews</a></li>

                <?php elseif ($isLoggedIn && $roleName === 'PATIENT'): ?>
                    <li><a href="patient/book_appointment.php">Book Appointment</a></li>
                    <li><a href="patient/my_appointments.php">My Appointments</a></li>
                    <li><a href="chat.php">Consultation Chat</a></li>
                    <li><a href="articles/news.php">Articles</a></li>
                    <li><a href="reviews/index.php">Reviews</a></li>

                <?php else: ?>
                    <li><a href="doctors/index.php">Doctors</a></li>
                    <li><a href="articles/news.php">All Articles</a></li>
                    <li><a href="auth/register.php">Register as Patient</a></li>
                    <li><a href="doctors/register.php">Doctor Register</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-user">
                <?php if ($isLoggedIn): ?>
                    <div class="user-dropdown">
                        <a href="auth/profile.php" class="user-trigger">
                            <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong> ▾</span>
                        </a>
                        <ul class="user-dropdown-menu">
                            <li><a href="auth/profile.php">My Profile</a></li>
                        </ul>
                    </div>
                    <a href="auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-logout-patient btn-login-nav">Log in</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="home-hero">
        <div class="home-hero-inner">
            <div class="home-hero-text">
                <h1>Easy booking. Care that puts you first.</h1>
                <p>Connect with experienced specialists. Book on your terms, skip the wait, and access safe online care.</p>
                <div class="hero-actions">
                    <a href="<?= ($isLoggedIn && $roleName === 'PATIENT') ? 'patient/book_appointment.php' : 'auth/login.php' ?>"
                       class="btn-hero-primary">Book an appointment</a>
                    <?php if (!$isLoggedIn): ?>
                        <a href="auth/register.php" class="btn-hero-outline">Register as patient</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="home-hero-image">
                <img src="images/picture.png" alt="Medical care">
            </div>
        </div>
    </section>

    <main class="main-wrapper">
        <!-- FEATURED DOCTORS -->
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Featured doctors</h2>
                    <p class="section-subtitle">Leading specialists with verified credentials</p>
                </div>
                <a href="doctors/index.php" class="btn-view-all">View all doctors &rarr;</a>
            </div>

            <?php if (!empty($doctors)): ?>
                <div class="horizontal-grid">
                <?php foreach ($doctors as $doc): ?>
                <?php
                $doctorId   = (int)$doc['doctor_id'];
                $profileUrl = 'doctors/profile.php?id=' . $doctorId;
                $bookUrl    = ($isLoggedIn && $roleName === 'PATIENT')
                    ? 'patient/book_appointment.php?doctor_id=' . $doctorId . '#booking-section'
                    : 'auth/login.php';

                $rawAvatar  = trim((string)($doc['avatar_url'] ?? ''));
                $baseAvatar = basename($rawAvatar);

                if ($rawAvatar !== '' && preg_match('#^https?://#i', $rawAvatar) === 1) {
                    $avatarSrc = htmlspecialchars($rawAvatar, ENT_QUOTES, 'UTF-8');
                } elseif ($baseAvatar !== '' && is_file(__DIR__ . '/assets/images/' . $baseAvatar)) {
                    $avatarSrc = 'assets/images/' . htmlspecialchars($baseAvatar, ENT_QUOTES, 'UTF-8');
                } elseif ($baseAvatar !== '' && is_file(__DIR__ . '/uploads/avatars/' . $baseAvatar)) {
                    $avatarSrc = 'uploads/avatars/' . htmlspecialchars($baseAvatar, ENT_QUOTES, 'UTF-8');
                } else {
                    $avatarSrc = 'assets/images/default_avatar.png';
                }
                ?>
                <div class="doctor-card">
                    <a href="<?= $profileUrl ?>">
                        <img src="<?= $avatarSrc ?>" alt="<?= htmlspecialchars($doc['full_name']) ?>">
                    </a>
                    <h3>
                        <a href="<?= $profileUrl ?>" style="text-decoration: none; color: inherit;">
                            <?= htmlspecialchars($doc['full_name']) ?>
                        </a>
                    </h3>
                    <p><?= htmlspecialchars($doc['bio'] ?? '') ?> / <?= htmlspecialchars($doc['specialty_name'] ?? '') ?></p>
                    <div style="display: flex; gap: 8px; margin-top: auto;">
                        <a href="<?= $profileUrl ?>" class="btn-view-all" style="flex: 1; text-align: center;">Profile</a>
                        <button class="btn-teal" style="flex: 1; margin-top: 0;" onclick="location.href='<?= $bookUrl ?>'">Book now</button>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="article-card article-card-empty">No doctor data.</div>
            <?php endif; ?>
        </section>
        <!-- HEALTH INSIGHTS -->
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Health insights</h2>
                    <p class="section-subtitle">Latest medical news and health tips</p>
                </div>
                <a href="articles/news.php" class="btn-view-all">All articles &rarr;</a>
            </div>

            <?php if (!empty($articles)): ?>
                <div class="horizontal-grid">
                    <?php foreach ($articles as $art): ?>
                        <?php
                        $thumb = 'images/article1.png';
                        if (!empty($art['thumbnail_url'])) {
                            $thumb = str_starts_with($art['thumbnail_url'], 'http')
                                ? $art['thumbnail_url']
                                : ltrim($art['thumbnail_url'], '/');
                        }
                        $articleUrl = !empty($art['article_id'])
                            ? 'articles/detail.php?id=' . (int)$art['article_id']
                            : 'articles/news.php';
                        ?>
                        <article class="article-card insight-card" onclick="location.href='<?= $articleUrl ?>'">
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($art['title']) ?>">
                            <div class="insight-body">
                                <?php if (!empty($art['category_name'])): ?>
                                    <span class="article-category-tag"><?= htmlspecialchars($art['category_name']) ?></span>
                                <?php endif; ?>
                                <a href="<?= $articleUrl ?>" class="insight-title"><?= htmlspecialchars($art['title']) ?></a>
                                <p class="insight-summary"><?= htmlspecialchars($art['summary'] ?? '') ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="article-card article-card-empty">No articles yet.</div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <span>&copy; 2026 MediConnect. Clinic management &amp; appointment booking. All rights reserved.</span>
            <div>
                <a href="doctors/index.php">Doctors</a>
                <a href="articles/news.php">Medical News</a>
                <?php if ($isStaff): ?>
                    <a href="articles/manage.php">Author Portal</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</body>
</html>