<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Helper function.
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$docNavRole = $_SESSION['role_name'] ?? '';

// Get search keyword safely.
$keyword = $_GET['keyword'] ?? '';

if (!is_string($keyword)) {
    $keyword = '';
}

$keyword = trim($keyword);

// Limit search keyword length.
if (mb_strlen($keyword, 'UTF-8') > 100) {
    $keyword = mb_substr($keyword, 0, 100, 'UTF-8');
}

$doctors = [];
$error = '';

try {

    // Only display approved doctors.
    $sql = "
        SELECT
            d.doctor_id,
            d.degree,
            d.experience_years,
            d.work_place,
            d.approval_status,
            u.full_name,
            u.email,
            s.name AS specialization_name,
            c.city_name
        FROM doctors d
        INNER JOIN users u
            ON d.user_id = u.user_id
        LEFT JOIN specializations s
            ON d.specialization_id = s.specialization_id
        LEFT JOIN cities c
            ON d.city_id = c.city_id
        WHERE d.approval_status = 'APPROVED'
        AND (
            u.full_name LIKE :name_keyword
            OR u.email LIKE :email_keyword
        )
        ORDER BY d.doctor_id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $searchKeyword = '%' . $keyword . '%';

    $stmt->execute([
        ':name_keyword' => $searchKeyword,
        ':email_keyword' => $searchKeyword
    ]);

    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log("Doctor list error: " . $exception->getMessage());

    $error = 'Unable to load doctor information. Please try again later.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Find a Doctor - MediConnect</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f7f8;
            color: #334155;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        /* HEADER */

        .site-header {
            background: #2f5d62;
            color: white;
            padding: 0 7%;
            min-height: 75px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: nowrap;
        }

        .brand {
            color: white;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: nowrap;
        }

        .header-nav a {
            color: white;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .header-nav a:hover {
            color: #bce5df;
        }

        /* HERO */

        .hero {
            background: linear-gradient(135deg, #2f5d62, #477f80);
            color: white;
            padding: 65px 20px 75px;
            text-align: center;
        }

        .hero-tag {
            display: inline-block;
            padding: 6px 15px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 30px;
            font-size: 12px;
            letter-spacing: 1px;
            margin-bottom: 18px;
            text-transform: uppercase;
        }

        .hero h1 {
            font-size: 38px;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .hero p {
            max-width: 620px;
            margin: auto;
            color: #e1eeee;
            font-size: 15px;
        }

        /* MAIN */

        .main-container {
            max-width: 1180px;
            margin: -30px auto 0;
            padding: 0 22px 55px;
            position: relative;
        }

        /* SEARCH */

        .search-panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(31, 67, 72, 0.09);
            margin-bottom: 35px;
        }

        .search-title {
            color: #2f5d62;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .search-form {
            display: flex;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            min-width: 0;
            padding: 13px 16px;
            border: 1px solid #dce5e8;
            border-radius: 7px;
            outline: none;
            font-size: 14px;
            color: #334155;
        }

        .search-input:focus {
            border-color: #477f80;
            box-shadow: 0 0 0 3px rgba(71,127,128,0.12);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 12px 20px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-search {
            background: #2f5d62;
            color: white;
        }

        .btn-search:hover {
            background: #24494d;
        }

        .btn-reset {
            background: #edf2f3;
            color: #475569;
        }

        .btn-reset:hover {
            background: #dfe8e9;
        }

        /* SECTION */

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            font-size: 23px;
            color: #2f5d62;
        }

        .result-count {
            color: #64748b;
            font-size: 13px;
        }

        /* DOCTOR CARDS */

        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .doctor-card {
            background: white;
            border: 1px solid #e7edef;
            border-radius: 12px;
            padding: 23px;
            box-shadow: 0 4px 15px rgba(31, 67, 72, 0.04);
            transition: 0.25s;
        }

        .doctor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(31, 67, 72, 0.09);
        }

        .doctor-top {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 18px;
            border-bottom: 1px solid #edf1f2;
        }

        .doctor-avatar {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #e0eeee;
            color: #2f5d62;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            font-weight: 800;
        }

        .doctor-name {
            color: #2f5d62;
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .doctor-specialization {
            color: #64748b;
            font-size: 13px;
        }

        .approved-label {
            display: inline-block;
            margin-left: auto;
            background: #e5f5ed;
            color: #198754;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .doctor-info {
            padding: 17px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px;
        }

        .info-label {
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }

        .info-value {
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .doctor-actions {
            border-top: 1px solid #edf1f2;
            padding-top: 17px;
        }

        .btn-profile {
            width: 100%;
            background: #2f5d62;
            color: white;
        }

        .btn-profile:hover {
            background: #24494d;
        }

        /* EMPTY AND ERROR */

        .empty-state,
        .error-state {
            background: white;
            border-radius: 12px;
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
            border: 1px solid #e7edef;
        }

        .empty-state h3 {
            color: #2f5d62;
            font-size: 19px;
            margin-bottom: 8px;
        }

        .error-state {
            color: #b42318;
            background: #fff5f5;
        }

        /* REGISTER */

        .register-panel {
            background: #e8f1f1;
            border-radius: 12px;
            margin-top: 40px;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .register-panel h3 {
            color: #2f5d62;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .register-panel p {
            color: #64748b;
            font-size: 14px;
        }

        .btn-register {
            background: #2f5d62;
            color: white;
        }

        .btn-register:hover {
            background: #24494d;
        }

        .back-link {
            display: inline-block;
            color: #477f80;
            font-size: 14px;
            font-weight: 700;
            margin-top: 28px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* FOOTER */

        .site-footer {
            background: #24494d;
            color: #d8e6e6;
            text-align: center;
            padding: 23px 15px;
            font-size: 13px;
        }

    </style>

</head>

<body>

<!-- HEADER -->
<header class="site-header">

    <a href="../index.php" class="brand">
        MediConnect
    </a>

    <nav class="header-nav">
        <a href="../index.php">Home</a>

        <?php if ($docNavRole === 'ADMIN'): ?>
            <a href="../admin/manage_patients.php">Manage Patients</a>
            <a href="admin.php">Manage Doctors</a>
            <a href="../reviews/index.php">Reviews Doctor</a>
            <a href="../articles/manage.php">Manage Articles</a>
            <a href="../articles/news.php">All Articles &amp; Comments</a>
            <a href="../locations/index.php">Manage Locations</a>
        <?php elseif ($docNavRole === 'DOCTOR'): ?>
            <a href="../doctor/dashboard.php">Doctor Portal</a>
            <a href="../chat.php">Consultation Chat</a>
            <a href="../articles/news.php">Articles</a>
            <a href="../reviews/index.php">Reviews</a>
        <?php elseif ($docNavRole === 'PATIENT'): ?>
            <a href="../patient/book_appointment.php">Book Appointment</a>
            <a href="../patient/my_appointments.php">My Appointments</a>
            <a href="../chat.php">Consultation Chat</a>
            <a href="../articles/news.php">Articles</a>
            <a href="../reviews/index.php">Reviews</a>
        <?php else: ?>
            <a href="../articles/news.php">All Articles</a>
            <a href="register.php">Doctor Register</a>
            <a href="../auth/login.php">Log in</a>
        <?php endif; ?>
    </nav>

</header>

<!-- HERO -->
<section class="hero">

    <span class="hero-tag">
        Your Health, Our Priority
    </span>

    <h1>Find Your Doctor</h1>

    <p>
        Discover qualified healthcare professionals and find the right
        specialist for your healthcare needs.
    </p>

</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- SEARCH -->
    <section class="search-panel">

        <h2 class="search-title">
            Search for a Doctor
        </h2>

        <form method="GET" action="index.php" class="search-form">

            <input
                type="text"
                name="keyword"
                class="search-input"
                maxlength="100"
                placeholder="Enter doctor name or email..."
                value="<?= e($keyword) ?>"
            >

            <button type="submit" class="btn btn-search">
                Search
            </button>

            <a href="index.php" class="btn btn-reset">
                Reset
            </a>

        </form>

    </section>

    <!-- DOCTOR LIST -->
    <section>

        <div class="section-heading">

            <h2>Our Doctors</h2>

            <span class="result-count">
                <?= count($doctors) ?> doctor(s) found
            </span>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error-state">
                <?= e($error) ?>
            </div>

        <?php elseif (!empty($doctors)): ?>

            <div class="doctor-grid">

                <?php foreach ($doctors as $doctor): ?>

                    <article class="doctor-card">

                        <div class="doctor-top">

                            <div class="doctor-avatar">
                                <?= e(function_exists('mb_substr')
                                    ? mb_strtoupper(mb_substr($doctor['full_name'], 0, 1, 'UTF-8'), 'UTF-8')
                                    : strtoupper(substr($doctor['full_name'], 0, 1))) ?>
                            </div>

                            <div>

                                <h3 class="doctor-name">
                                    <?= e($doctor['full_name']) ?>
                                </h3>

                                <div class="doctor-specialization">
                                    <?= e($doctor['specialization_name'] ?? 'General Medicine') ?>
                                </div>

                            </div>

                            <span class="approved-label">
                                APPROVED
                            </span>

                        </div>

                        <div class="doctor-info">
                            <div>
                                <div class="info-label">Degree</div>
                                <div class="info-value"><?= e($doctor['degree'] ?? 'N/A') ?></div>
                            </div>
                            <div>
                                <div class="info-label">Experience</div>
                                <div class="info-value">
                                    <?= $doctor['experience_years'] !== null
                                        ? (int)$doctor['experience_years'] . ' years'
                                        : 'N/A' ?>
                                </div>
                            </div>
                            <div>
                                <div class="info-label">Workplace</div>
                                <div class="info-value"><?= e($doctor['work_place'] ?? 'N/A') ?></div>
                            </div>
                            <div>
                                <div class="info-label">City</div>
                                <div class="info-value"><?= e($doctor['city_name'] ?? 'N/A') ?></div>
                            </div>
                        </div>

                        <div class="doctor-actions">
                            <a href="profile.php?id=<?= (int)$doctor['doctor_id'] ?>" class="btn btn-profile">
                                View Profile
                            </a>
                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <h3>No doctors found</h3>
                <p>Try adjusting your search keyword or reset the filter.</p>
            </div>

        <?php endif; ?>

    </section>

    <div class="register-panel">
        <div>
            <h3>Are you a licensed doctor?</h3>
            <p>Submit your credentials to join MediConnect and connect with patients.</p>
        </div>
        <a href="register.php" class="btn btn-register">Register as a Doctor</a>
    </div>

    <a href="../index.php" class="back-link">&larr; Back to Home</a>

</main>

<!-- FOOTER -->
<footer class="site-footer">
    © <?= date('Y') ?> MediConnect. All rights reserved.
</footer>

</body>
</html>