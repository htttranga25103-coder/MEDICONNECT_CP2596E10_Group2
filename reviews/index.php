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
     * ADMIN
     * View all approved doctors, all reviews and system statistics.
     */
    if ($roleName === 'ADMIN') {
        $selectedDoctor = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);

        if ($selectedDoctor === false || $selectedDoctor === null || $selectedDoctor < 0) {
            $selectedDoctor = 0;
        }

        // Doctor statistics.
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

        // Overall statistics.
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

        // Rating distribution.
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

        // All reviews, optionally filtered by doctor.
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
     * DOCTOR
     * A doctor can only see reviews belonging to their own account.
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
     * PATIENT
     * A patient can only see their own appointments and reviews.
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

        // Completed appointments and their review status.
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

        // Reviews written by this patient only.
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

<?php require_once __DIR__ . '/layout_header.php'; ?>

<style>
.reviews-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    color: #263746;
}

.reviews-heading {
    margin-bottom: 25px;
}

.reviews-heading h1 {
    margin: 0 0 8px;
    font-size: 27px;
    color: #173b50;
}

.reviews-heading p {
    margin: 0;
    color: #718096;
}

.review-alert {
    padding: 14px 17px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.review-alert.error {
    background: #fff0f0;
    color: #a52b2b;
    border: 1px solid #f2caca;
}

.review-alert.success {
    background: #eaf8f1;
    color: #176b45;
    border: 1px solid #bce5d0;
}

.review-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 17px;
    margin-bottom: 28px;
}

.review-stat-card,
.review-panel,
.review-doctor-card,
.review-appointment-card,
.review-item {
    background: #fff;
    border: 1px solid #e5ebef;
    border-radius: 11px;
    box-shadow: 0 3px 12px rgba(25, 55, 75, .045);
}

.review-stat-card {
    padding: 20px;
}

.review-stat-card span {
    display: block;
    color: #718096;
    font-size: 13px;
    margin-bottom: 9px;
}

.review-stat-card strong {
    font-size: 27px;
    color: #173b50;
}

.review-panel {
    padding: 22px;
    margin-bottom: 25px;
}

.review-panel h2 {
    font-size: 19px;
    margin: 0 0 18px;
    color: #173b50;
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
    margin: 0 0 10px;
    font-size: 16px;
    color: #173b50;
}

.review-stars {
    color: #e6a000;
    letter-spacing: 2px;
    font-size: 20px;
    margin: 7px 0;
}

.review-muted {
    color: #718096;
    font-size: 13px;
}

.review-button {
    display: inline-block;
    margin-top: 12px;
    padding: 9px 15px;
    background: #146d75;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.review-button:hover {
    background: #103c4b;
    color: #fff;
}

.review-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.review-status.done {
    color: #176b45;
    background: #e4f5eb;
}

.review-status.waiting {
    color: #946200;
    background: #fff3d6;
}

.review-list {
    display: grid;
    gap: 15px;
}

.review-item {
    padding: 19px;
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
    color: #173b50;
    font-size: 16px;
}

.review-comment {
    margin: 12px 0;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.review-meta {
    color: #718096;
    font-size: 12px;
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
    margin-bottom: 6px;
    color: #536575;
}

.review-filter select {
    min-width: 230px;
    padding: 10px;
    border: 1px solid #d7e0e5;
    border-radius: 6px;
    background: #fff;
}

.review-filter button {
    padding: 10px 17px;
    border: 0;
    border-radius: 6px;
    background: #146d75;
    color: #fff;
    cursor: pointer;
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
    border-radius: 10px;
    background: #edf1f3;
    overflow: hidden;
}

.rating-fill {
    height: 100%;
    background: #e6a000;
    border-radius: 10px;
}

.review-empty {
    padding: 20px;
    text-align: center;
    color: #718096;
    background: #f8fafb;
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

<main class="reviews-dashboard">

    <section class="reviews-heading">
        <h1><?= e($pageHeading) ?></h1>
        <p><?= e($pageDescription) ?></p>
    </section>

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
                <a class="review-button" href="index.php">Clear Filter</a>
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

<?php require_once __DIR__ . '/layout_footer.php'; ?>