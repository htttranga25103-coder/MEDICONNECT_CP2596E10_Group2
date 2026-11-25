<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only logged-in users with a supported role can access Reviews.
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$roleName = strtoupper((string)($_SESSION['role_name'] ?? ''));

if (!$userId || $userId <= 0 || !in_array($roleName, ['PATIENT', 'DOCTOR', 'ADMIN'], true)) {
    header("Location: ../auth/login.php");
    exit();
}

// Escape output.
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Render rating stars.
function reviewStars($rating)
{
    $rating = max(0, min(5, (int)round((float)$rating)));

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

$doctors = [];
$reviews = [];
$appointments = [];
$patientReviews = [];
$patientId = 0;
$doctorId = 0;
$selectedDoctor = 0;
$statistics = [
    'total_reviews' => 0,
    'average_rating' => 0,
    'total_doctors' => 0
];
$ratingDistribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$error = '';

try {
    /*
     * ADMIN: View all approved doctors, all reviews and system statistics.
     */
    if ($roleName === 'ADMIN') {
        $selectedDoctor = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);

        if ($selectedDoctor === false || $selectedDoctor === null || $selectedDoctor < 0) {
            $selectedDoctor = 0;
        }

        $doctorSql = "
            SELECT
                d.doctor_id,
                u.full_name AS doctor_name,
                COALESCE(ROUND(AVG(r.rating), 1), 0) AS average_rating,
                COUNT(r.review_id) AS total_reviews
            FROM doctors d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN doctor_reviews r ON d.doctor_id = r.doctor_id
            WHERE d.approval_status = 'APPROVED'
            GROUP BY d.doctor_id, u.full_name
            ORDER BY average_rating DESC, total_reviews DESC
        ";
        $doctors = $pdo->query($doctorSql)->fetchAll(PDO::FETCH_ASSOC);

        $statsSql = "
            SELECT
                COUNT(r.review_id) AS total_reviews,
                COALESCE(ROUND(AVG(r.rating), 1), 0) AS average_rating,
                COUNT(DISTINCT r.doctor_id) AS total_doctors
            FROM doctor_reviews r
            JOIN doctors d ON r.doctor_id = d.doctor_id
            WHERE d.approval_status = 'APPROVED'
        ";
        $statistics = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC);

        $distributionSql = "
            SELECT r.rating, COUNT(*) AS total
            FROM doctor_reviews r
            JOIN doctors d ON r.doctor_id = d.doctor_id
            WHERE d.approval_status = 'APPROVED'
            GROUP BY r.rating
        ";
        foreach ($pdo->query($distributionSql)->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $ratingDistribution[(int)$item['rating']] = (int)$item['total'];
        }

        $reviewSql = "
            SELECT
                r.review_id,
                r.rating,
                r.comment,
                r.created_at,
                r.appointment_id,
                patient_user.full_name AS patient_name,
                d.doctor_id,
                doctor_user.full_name AS doctor_name
            FROM doctor_reviews r
            JOIN patients p ON r.patient_id = p.patient_id
            JOIN users patient_user ON p.user_id = patient_user.user_id
            JOIN doctors d ON r.doctor_id = d.doctor_id
            JOIN users doctor_user ON d.user_id = doctor_user.user_id
            WHERE d.approval_status = 'APPROVED'
        ";

        if ($selectedDoctor > 0) {
            $reviewSql .= " AND r.doctor_id = :doctor_id";
        }
        $reviewSql .= " ORDER BY r.created_at DESC";

        $reviewStmt = $pdo->prepare($reviewSql);
        if ($selectedDoctor > 0) {
            $reviewStmt->execute([':doctor_id' => $selectedDoctor]);
        } else {
            $reviewStmt->execute();
        }
        $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * DOCTOR: A doctor can only see reviews belonging to their own account.
     */
    elseif ($roleName === 'DOCTOR') {
        $doctorStmt = $pdo->prepare("
            SELECT doctor_id, approval_status
            FROM doctors
            WHERE user_id = :user_id
            LIMIT 1
        ");
        $doctorStmt->execute([':user_id' => $userId]);
        $doctorProfile = $doctorStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doctorProfile) {
            throw new RuntimeException('Doctor profile not found.');
        }

        $doctorId = (int)$doctorProfile['doctor_id'];

        $doctorStatsStmt = $pdo->prepare("
            SELECT
                COUNT(review_id) AS total_reviews,
                COALESCE(ROUND(AVG(rating), 1), 0) AS average_rating
            FROM doctor_reviews
            WHERE doctor_id = :doctor_id
        ");
        $doctorStatsStmt->execute([':doctor_id' => $doctorId]);
        $statistics = $doctorStatsStmt->fetch(PDO::FETCH_ASSOC);

        $distributionStmt = $pdo->prepare("
            SELECT rating, COUNT(*) AS total
            FROM doctor_reviews
            WHERE doctor_id = :doctor_id
            GROUP BY rating
        ");
        $distributionStmt->execute([':doctor_id' => $doctorId]);

        foreach ($distributionStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $ratingDistribution[(int)$item['rating']] = (int)$item['total'];
        }

        $reviewStmt = $pdo->prepare("
            SELECT
                r.review_id,
                r.rating,
                r.comment,
                r.created_at,
                r.appointment_id,
                patient_user.full_name AS patient_name,
                doctor_user.full_name AS doctor_name
            FROM doctor_reviews r
            JOIN patients p ON r.patient_id = p.patient_id
            JOIN users patient_user ON p.user_id = patient_user.user_id
            JOIN doctors d ON r.doctor_id = d.doctor_id
            JOIN users doctor_user ON d.user_id = doctor_user.user_id
            WHERE r.doctor_id = :doctor_id
            ORDER BY r.created_at DESC
        ");
        $reviewStmt->execute([':doctor_id' => $doctorId]);
        $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * PATIENT: A patient can only see their own appointments and reviews.
     */
    elseif ($roleName === 'PATIENT') {
        $patientStmt = $pdo->prepare("
            SELECT patient_id
            FROM patients
            WHERE user_id = :user_id
            LIMIT 1
        ");
        $patientStmt->execute([':user_id' => $userId]);
        $patientId = (int)$patientStmt->fetchColumn();

        if ($patientId <= 0) {
            throw new RuntimeException('Patient profile not found.');
        }

        $appointmentStmt = $pdo->prepare("
            SELECT
                a.appointment_id,
                a.status,
                d.doctor_id,
                doctor_user.full_name AS doctor_name,
                r.review_id,
                r.rating,
                r.comment,
                r.created_at AS review_date
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            JOIN users doctor_user ON d.user_id = doctor_user.user_id
            LEFT JOIN doctor_reviews r ON a.appointment_id = r.appointment_id
            WHERE a.patient_id = :patient_id
              AND a.status = 'COMPLETED'
              AND d.approval_status = 'APPROVED'
            ORDER BY a.appointment_id DESC
        ");
        $appointmentStmt->execute([':patient_id' => $patientId]);
        $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

        $patientReviewStmt = $pdo->prepare("
            SELECT
                r.review_id,
                r.rating,
                r.comment,
                r.created_at,
                r.appointment_id,
                doctor_user.full_name AS doctor_name
            FROM doctor_reviews r
            JOIN doctors d ON r.doctor_id = d.doctor_id
            JOIN users doctor_user ON d.user_id = doctor_user.user_id
            WHERE r.patient_id = :patient_id
            ORDER BY r.created_at DESC
        ");
        $patientReviewStmt->execute([':patient_id' => $patientId]);
        $patientReviews = $patientReviewStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $exception) {
    error_log('Reviews page error: ' . $exception->getMessage());
    $error = 'Unable to load review information. Please try again later.';
}

$pageHeading = [
    'ADMIN' => 'Doctor Reviews Management',
    'DOCTOR' => 'My Patient Reviews',
    'PATIENT' => 'My Doctor Reviews'
][$roleName];

$pageDescription = [
    'ADMIN' => 'Monitor patient feedback and ratings across MediConnect.',
    'DOCTOR' => 'View feedback and ratings submitted by your patients.',
    'PATIENT' => 'Review your completed consultations and share your experience.'
][$roleName];
?>

<?php if ($roleName === 'ADMIN'): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - <?= e($pageHeading) ?></title>
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=4">
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
<?php else: ?>
    <?php require_once __DIR__ . '/layout_header.php'; ?>
<?php endif; ?>

<style>
.reviews-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    color: #1e293b;
}

.reviews-heading {
    margin-bottom: 24px;
}

.reviews-heading h1 {
    margin: 0 0 6px;
    font-size: 26px;
    color: #0f172a;
}

.reviews-heading p {
    margin: 0;
    color: #64748b;
}

.review-alert {
    padding: 14px 17px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.review-alert.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.review-alert.success {
    background: #ccfbf1;
    color: #0f766e;
    border: 1px solid #99f6e4;
}

.review-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.review-stat-card,
.review-panel,
.review-doctor-card,
.review-appointment-card,
.review-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.review-stat-card {
    padding: 18px 20px;
}

.review-stat-card span {
    display: block;
    color: #64748b;
    font-size: 13px;
    margin-bottom: 8px;
}

.review-stat-card strong {
    font-size: 26px;
    color: #0f766e;
}

.review-panel {
    padding: 22px;
    margin-bottom: 24px;
}

.review-panel h2 {
    font-size: 18px;
    margin: 0 0 18px;
    color: #0f172a;
}

.review-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}

.review-doctor-card,
.review-appointment-card {
    padding: 18px;
}

.review-doctor-card h3,
.review-appointment-card h3 {
    margin: 0 0 8px;
    font-size: 16px;
    color: #0f172a;
}

.review-stars {
    color: #f59e0b;
    letter-spacing: 2px;
    font-size: 19px;
    margin: 6px 0;
}

.review-muted {
    color: #64748b;
    font-size: 13px;
}

.review-button {
    display: inline-block;
    margin-top: 12px;
    padding: 9px 15px;
    background: #0f766e;
    color: #ffffff;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.15s ease;
}

.review-button:hover {
    background: #115e59;
    color: #ffffff;
}

.review-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.review-status.done {
    color: #0f766e;
    background: #ccfbf1;
}

.review-status.waiting {
    color: #92400e;
    background: #fef3c7;
}

.review-list {
    display: grid;
    gap: 14px;
}

.review-item {
    padding: 18px;
}

.review-item-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-start;
}

.review-item h3 {
    margin: 0;
    color: #0f172a;
    font-size: 16px;
}

.review-comment {
    margin: 10px 0;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.review-meta {
    color: #64748b;
    font-size: 12.5px;
}

.review-filter {
    display: flex;
    align-items: end;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.review-filter label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    color: #475569;
}

.review-filter select {
    min-width: 230px;
    padding: 9px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #ffffff;
}

.review-filter button {
    padding: 9px 17px;
    border: 0;
    border-radius: 7px;
    background: #0f766e;
    color: #ffffff;
    font-weight: 600;
    cursor: pointer;
}

.review-filter button:hover {
    background: #115e59;
}

.rating-distribution {
    display: grid;
    gap: 10px;
}

.rating-row {
    display: grid;
    grid-template-columns: 45px minmax(80px, 1fr) 35px;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}

.rating-track {
    height: 9px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.rating-fill {
    height: 100%;
    background: #f59e0b;
    border-radius: 999px;
}

.review-empty {
    padding: 20px;
    text-align: center;
    color: #64748b;
    background: #f8fafc;
    border-radius: 8px;
}

@media (max-width: 600px) {
    .review-panel {
        padding: 15px;
    }

    .reviews-heading h1 {
        font-size: 22px;
    }
}
</style>

<?php if ($roleName === 'ADMIN'): ?>
</head>
<body>
<div class="admin-wrapper">
    <!-- SHARED ADMIN SIDEBAR WITH ICONS -->
    <?php
        $adminActivePage = 'reviews';
        require_once __DIR__ . '/../includes/admin_sidebar.php';
    ?>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-user" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 16px; font-weight: 700;">
                    <?= e($pageHeading) ?> — Welcome, <strong><?= e($_SESSION['full_name'] ?? 'Admin') ?></strong>
                </span>
                <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </div>
        </header>

        <div class="content-container">
<?php endif; ?>

<main class="reviews-dashboard">

    <?php if ($roleName !== 'ADMIN'): ?>
        <section class="reviews-heading">
            <h1><?= e($pageHeading) ?></h1>
            <p><?= e($pageDescription) ?></p>
        </section>
    <?php else: ?>
        <p style="color: #64748b; margin-bottom: 18px;"><?= e($pageDescription) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="review-alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($roleName === 'PATIENT' && isset($_GET['success']) && $_GET['success'] === '1'): ?>
        <div class="review-alert success">
            <strong>Review submitted successfully!</strong>
            Thank you for sharing your feedback.
        </div>
    <?php endif; ?>

    <?php if ($roleName === 'ADMIN'): ?>

        <section class="review-stats">
            <div class="review-stat-card">
                <span>Total Reviews</span>
                <strong><?= (int)$statistics['total_reviews'] ?></strong>
            </div>

            <div class="review-stat-card">
                <span>Average Rating</span>
                <strong><?= e($statistics['average_rating']) ?>/5</strong>
            </div>

            <div class="review-stat-card">
                <span>Doctors Reviewed</span>
                <strong><?= (int)$statistics['total_doctors'] ?></strong>
            </div>

            <div class="review-stat-card">
                <span>Approved Doctors</span>
                <strong><?= count($doctors) ?></strong>
            </div>
        </section>

        <section class="review-panel">
            <h2>Doctor Rating Statistics</h2>

            <?php if ($doctors): ?>
                <div class="review-grid">
                    <?php foreach ($doctors as $doctor): ?>
                        <article class="review-doctor-card">
                            <h3><?= e($doctor['doctor_name']) ?></h3>

                            <div class="review-stars">
                                <?= e(reviewStars($doctor['average_rating'])) ?>
                            </div>

                            <p>
                                <strong><?= e($doctor['average_rating']) ?>/5</strong>
                                <span class="review-muted">Average Rating</span>
                            </p>

                            <p class="review-muted">
                                Total Reviews: <?= (int)$doctor['total_reviews'] ?>
                            </p>

                            <a class="review-button"
                               href="index.php?doctor_id=<?= (int)$doctor['doctor_id'] ?>">
                                View Reviews
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-empty">No approved doctors found.</div>
            <?php endif; ?>
        </section>

        <section class="review-panel">
            <h2>All Patient Reviews</h2>

            <form class="review-filter" method="GET" action="index.php">
                <div>
                    <label for="doctor_id">Filter by Doctor</label>
                    <select name="doctor_id" id="doctor_id">
                        <option value="0">All Doctors</option>

                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= (int)$doctor['doctor_id'] ?>"
                                <?= (int)$selectedDoctor === (int)$doctor['doctor_id'] ? 'selected' : '' ?>>
                                <?= e($doctor['doctor_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Search</button>
                <a class="review-button" style="margin-top:0; background:#64748b;" href="index.php">Clear Filter</a>
            </form>

            <?php if ($reviews): ?>
                <div class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <article class="review-item">
                            <div class="review-item-header">
                                <div>
                                    <h3>Dr. <?= e($review['doctor_name']) ?></h3>
                                    <div class="review-stars">
                                        <?= e(reviewStars($review['rating'])) ?>
                                    </div>
                                </div>

                                <span class="review-meta">
                                    <?= e($review['created_at']) ?>
                                </span>
                            </div>

                            <p class="review-comment">
                                <?= nl2br(e($review['comment'] ?: 'No comment provided.')) ?>
                            </p>

                            <div class="review-meta">
                                Patient: <?= e($review['patient_name']) ?>
                                | Appointment #<?= (int)$review['appointment_id'] ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-empty">No reviews found for this selection.</div>
            <?php endif; ?>
        </section>

    <?php elseif ($roleName === 'DOCTOR'): ?>

        <section class="review-stats">
            <div class="review-stat-card">
                <span>Average Rating</span>
                <strong><?= e($statistics['average_rating']) ?>/5</strong>
            </div>

            <div class="review-stat-card">
                <span>Total Patient Reviews</span>
                <strong><?= (int)$statistics['total_reviews'] ?></strong>
            </div>
        </section>

        <section class="review-panel">
            <h2>Rating Distribution</h2>

            <div class="rating-distribution">
                <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                    <?php
                    $count = $ratingDistribution[$star];
                    $total = (int)$statistics['total_reviews'];
                    $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                    ?>
                    <div class="rating-row">
                        <span><?= $star ?> ★</span>
                        <div class="rating-track">
                            <div class="rating-fill" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <span><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="review-panel">
            <h2>Reviews From My Patients</h2>

            <?php if ($reviews): ?>
                <div class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <article class="review-item">
                            <div class="review-item-header">
                                <div>
                                    <h3><?= e($review['patient_name']) ?></h3>
                                    <div class="review-stars">
                                        <?= e(reviewStars($review['rating'])) ?>
                                    </div>
                                </div>

                                <span class="review-meta">
                                    <?= e($review['created_at']) ?>
                                </span>
                            </div>

                            <p class="review-comment">
                                <?= nl2br(e($review['comment'] ?: 'No comment provided.')) ?>
                            </p>

                            <div class="review-meta">
                                Appointment #<?= (int)$review['appointment_id'] ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-empty">
                    You have not received any patient reviews yet.
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($roleName === 'PATIENT'): ?>

        <section class="review-panel">
            <h2>My Completed Appointments</h2>

            <?php if ($appointments): ?>
                <div class="review-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <article class="review-appointment-card">
                            <h3>Dr. <?= e($appointment['doctor_name']) ?></h3>

                            <p class="review-muted">
                                Appointment #<?= (int)$appointment['appointment_id'] ?>
                            </p>

                            <?php if (!empty($appointment['review_id'])): ?>
                                <span class="review-status done">Reviewed</span>

                                <div class="review-stars">
                                    <?= e(reviewStars($appointment['rating'])) ?>
                                </div>

                                <p class="review-muted">
                                    Submitted: <?= e($appointment['review_date']) ?>
                                </p>
                            <?php else: ?>
                                <span class="review-status waiting">Waiting for review</span>

                                <p class="review-muted">
                                    Your consultation is complete. You can now rate this doctor.
                                </p>

                                <a class="review-button"
                                   href="create.php?appointment_id=<?= (int)$appointment['appointment_id'] ?>">
                                    Write a Review
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-empty">
                    You do not have any completed appointments available for review.
                </div>
            <?php endif; ?>
        </section>

        <section class="review-panel">
            <h2>My Submitted Reviews</h2>

            <?php if ($patientReviews): ?>
                <div class="review-list">
                    <?php foreach ($patientReviews as $review): ?>
                        <article class="review-item">
                            <div class="review-item-header">
                                <div>
                                    <h3>Dr. <?= e($review['doctor_name']) ?></h3>
                                    <div class="review-stars">
                                        <?= e(reviewStars($review['rating'])) ?>
                                    </div>
                                </div>

                                <span class="review-meta">
                                    <?= e($review['created_at']) ?>
                                </span>
                            </div>

                            <p class="review-comment">
                                <?= nl2br(e($review['comment'] ?: 'No comment provided.')) ?>
                            </p>

                            <div class="review-meta">
                                Appointment #<?= (int)$review['appointment_id'] ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-empty">
                    You have not submitted any reviews yet.
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<?php if ($roleName === 'ADMIN'): ?>
        </div><!-- /.content-container -->

        <footer class="admin-footer">
            <div>&copy; <?= date('Y') ?> MediConnect. All rights reserved.</div>
            <div class="footer-links">
                <a href="#">Terms</a>
                <a href="#">Privacy</a>
                <a href="#">Support</a>
            </div>
        </footer>
    </div><!-- /.main-area -->
</div><!-- /.admin-wrapper -->
</body>
</html>
<?php else: ?>
    <?php require_once __DIR__ . '/layout_footer.php'; ?>
<?php endif; ?>