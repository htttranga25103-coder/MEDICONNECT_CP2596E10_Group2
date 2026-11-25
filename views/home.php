<?php
session_start();
require_once __DIR__ . '/../models/homeclass.php';

$roleName = strtoupper($_SESSION['role_name'] ?? '');
$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

$doctors = Doctor::getFeaturedDoctors();
$articles = Doctor::getLatestArticles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Home</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f6f8;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 0 40px 40px 40px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        
        .btn-green {
            background-color: #198754;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-green:hover {
            background-color: #157347;
        }

        .btn-outline {
            background-color: transparent;
            color: #198754;
            border: 1px solid #198754;
            border-radius: 8px;
            padding: 11px 22px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            gap: 12px;
            flex-wrap: wrap;
        }
        .logo-btn {
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 60px 0;
        }
        .hero-text {
            flex: 1;
        }
        .hero-text h1 {
            font-size: 42px;
            line-height: 1.2;
            margin-bottom: 16px;
            color: #111;
        }
        .hero-text p {
            font-size: 18px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .hero-image {
            flex: 1;
            text-align: right;
        }
        .hero-image img {
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 50px;
            margin-bottom: 24px;
        }
        .section-header h2 {
            font-size: 28px;
            margin-bottom: 6px;
        }
        .section-header span,
        .section-header p {
            color: #666;
        }
       
        .featured-doctors {
            width: 100%;
            overflow: hidden;
        }
        .doctor-grid {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 24px;
            padding-bottom: 14px;
            scroll-behavior: smooth;
            width: 100%;
        }
        .card {
            flex: 0 0 calc(33.333% - 16px);
            min-width: 300px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #eee;
            border-radius: 8px;
        }
        .card button {
            margin-top: auto; 
        }
        
        .doctor-grid::-webkit-scrollbar {
            height: 8px;
        }
        .doctor-grid::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 4px;
        }
        .doctor-grid::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 4px;
        }
        
        .health-insights {
            width: 100%;
            overflow: hidden;
        }
        .article-grid {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 24px;
            padding-bottom: 14px;
            scroll-behavior: smooth;
            width: 100%;
        }
        article {
            flex: 0 0 calc(33.333% - 16px);
            min-width: 340px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
        }
        article img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .article-body {
            padding: 16px;
        }
        .article-body h3 {
            margin-bottom: 8px;
            font-size: 18px;
        }

        .article-grid::-webkit-scrollbar {
            height: 8px;
        }
        .article-grid::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 4px;
        }
        .article-grid::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 4px;
        }
       
        footer {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <a href="../index.php" class="logo-btn">
                <img src="../images/icons/mediconnect.png" alt="mediconnect image">
            </a>
            <div class="nav-buttons">
                <a href="../index.php" class="btn-outline">Main Portal</a>
                <a href="../articles/news.php" class="btn-outline">Articles</a>
                <?php if ($isLoggedIn): ?>
                    <?php if ($roleName === 'ADMIN'): ?>
                        <a href="admin_dashboard.php" class="btn-green">Admin Dashboard</a>
                    <?php elseif ($roleName === 'DOCTOR'): ?>
                        <a href="doctor_dashboard.php" class="btn-green">Doctor Workspace</a>
                    <?php elseif ($roleName === 'PATIENT'): ?>
                        <a href="../patient/book_appointment.php" class="btn-green">Book Appointment</a>
                    <?php endif; ?>
                    <a href="../auth/logout.php" class="btn-outline" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn-outline">Login</a>
                    <a href="../auth/register.php" class="btn-green">Register as patient</a>
                    <a href="../doctors/register.php" class="btn-green">Register as doctor</a>
                <?php endif; ?>
            </div>
        </header>
        
        <section class="hero">
            <div class="hero-text">
                <h1>Easy booking. Care that puts you first.</h1>
                <p>Connect with experienced specialists. Book on your terms, skip the wait, and access safe online care.</p>
                <a href="<?= ($isLoggedIn && $roleName === 'PATIENT') ? '../patient/book_appointment.php' : '../auth/login.php' ?>"
                   class="btn-green" style="padding: 15px 40px; font-size: 16px;">Book an appointment</a>
            </div>
            <div class="hero-image">
                <img src="../images/picture.png" alt="Medical care">
            </div>
        </section>

        <!-- FEATURED DOCTORS -->
        <section class="featured-doctors">
            <div class="section-header">
                <div>
                    <h2>Featured doctors</h2>
                    <p>Leading specialists with verified credentials</p>
                </div>
                <a href="../doctors/index.php" class="btn-outline">View all doctors →</a>
            </div>

            <div class="doctor-grid" id="doctor-grid">
                <?php if (!empty($doctors)): ?>
                    <?php foreach ($doctors as $doc): ?>
                        <?php
                        $bookUrl = ($isLoggedIn && $roleName === 'PATIENT')
                            ? '../patient/book_appointment.php?doctor_id=' . (int)$doc['doctor_id'] . '#booking-section'
                            : '../auth/login.php';
                        ?>
                        <div class="card doctor-item">
                            <img src="<?= !empty($doc['avatar_url']) ? '../assets/images/' . htmlspecialchars($doc['avatar_url']) : '../assets/images/default_avatar.png' ?>" 
                                 alt="<?= htmlspecialchars($doc['full_name']) ?>">
                            <h3><?= htmlspecialchars($doc['full_name']) ?></h3>
                            <p><?= htmlspecialchars($doc['bio'] ?? '') ?> / <?= htmlspecialchars($doc['specialty_name'] ?? '') ?></p>
                            <button class="btn-green" onclick="location.href='<?= $bookUrl ?>'">
                                Book now
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No doctor data.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- HEALTH INSIGHTS -->
        <section class="health-insights">
            <div class="section-header">
                <div>
                    <h2>Health insights</h2>
                    <span>Latest medical news and health tips</span>
                </div>
                <a href="../articles/news.php" class="btn-outline">All articles →</a>
            </div>

            <div class="article-grid" id="article-grid">
                <?php if (!empty($articles)): ?>
                    <?php foreach ($articles as $art): ?>
                        <?php                        
                        $thumb = '../images/article1.png';
                        if (!empty($art['thumbnail_url'])) {
                            $thumb = str_starts_with($art['thumbnail_url'], 'http') 
                                ? $art['thumbnail_url'] 
                                : '../' . ltrim($art['thumbnail_url'], '/');
                        }
                        $articleUrl = !empty($art['article_id'])
                            ? '../articles/detail.php?id=' . (int)$art['article_id']
                            : '../articles/news.php';
                        ?>
                        <article class="article-item" 
                                 onclick="location.href='<?= $articleUrl ?>'">
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($art['title']) ?>">
                            <div class="article-body">
                                <h3><?= htmlspecialchars($art['title']) ?></h3>
                                <p><?= htmlspecialchars($art['summary'] ?? '') ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No articles yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- FOOTER -->
        <footer>
            <div>
                <strong>MediConnect</strong>
                <p>Clinic management &amp; appointment booking</p>
            </div>
            <small>© 2026 Mediconnect. All rights reserved.</small>
        </footer>
    </div>
</body>
</html>