<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only logged-in PATIENT accounts can write reviews.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_name'])
) {
    header("Location: ../auth/login.php");
    exit();
}

if (strtoupper((string)$_SESSION['role_name']) !== 'PATIENT') {
    http_response_code(403);
    exit("Only patients can write reviews.");
}

// Validate logged-in user ID.
$userId = filter_var(
    $_SESSION['user_id'],
    FILTER_VALIDATE_INT
);

if ($userId === false || $userId <= 0) {
    header("Location: ../auth/login.php");
    exit();
}

// Escape output.
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Create CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate appointment ID.
$rawAppointmentId = $_GET['appointment_id'] ?? null;

if (!is_scalar($rawAppointmentId)) {
    http_response_code(400);
    exit("Invalid appointment ID.");
}

$appointment_id = filter_var(
    $rawAppointmentId,
    FILTER_VALIDATE_INT
);

if ($appointment_id === false || $appointment_id <= 0) {
    http_response_code(400);
    exit("Invalid appointment ID.");
}

try {
    // Get completed appointment belonging to the logged-in patient.
    $stmt = $pdo->prepare("
        SELECT
            a.appointment_id,
            a.patient_id,
            a.doctor_id,
            a.status,
            doctor_user.full_name AS doctor_name
        FROM appointments a
        JOIN patients p
            ON a.patient_id = p.patient_id
        JOIN doctors d
            ON a.doctor_id = d.doctor_id
        JOIN users doctor_user
            ON d.user_id = doctor_user.user_id
        WHERE a.appointment_id = :appointment_id
          AND a.status = 'COMPLETED'
          AND p.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':user_id' => $userId
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        exit("Appointment not found, not completed, or you do not have permission to review it.");
    }

    // Check whether this appointment has already been reviewed.
    $checkReview = $pdo->prepare("
        SELECT review_id
        FROM doctor_reviews
        WHERE appointment_id = :appointment_id
        LIMIT 1
    ");

    $checkReview->execute([
        ':appointment_id' => $appointment_id
    ]);

    if ($checkReview->fetch(PDO::FETCH_ASSOC)) {
        exit("This appointment has already been reviewed.");
    }

} catch (Throwable $e) {
    error_log("Review creation page error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load the review form. Please try again later.");
}
?>

<?php require_once __DIR__ . '/layout_header.php'; ?>

<main class="review-form-page">

    <h1>Write a Doctor Review</h1>

    <p>
        Share your consultation experience and help other patients.
    </p>

    <section class="review-doctor-information">

        <h2>Doctor Information</h2>

        <p>
            <strong>Doctor:</strong>
            Dr. <?= e($appointment['doctor_name']) ?>
        </p>

        <p>
            <strong>Appointment ID:</strong>
            <?= (int)$appointment['appointment_id'] ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= e($appointment['status']) ?>
        </p>

    </section>

    <form action="submit.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <input
            type="hidden"
            name="appointment_id"
            value="<?= (int)$appointment['appointment_id'] ?>"
        >

        <div class="form-group">

            <label for="rating">Your Rating</label>

            <select name="rating" id="rating" required>

                <option value="">Select your rating</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Very Good</option>
                <option value="3">3 - Good</option>
                <option value="2">2 - Fair</option>
                <option value="1">1 - Poor</option>

            </select>

        </div>

        <div class="form-group">

            <label for="comment">Your Feedback</label>

            <textarea
                name="comment"
                id="comment"
                rows="5"
                maxlength="2000"
                placeholder="Write your experience about this doctor..."
            ></textarea>

        </div>

        <button type="submit">
            Submit Review
        </button>

        <a href="index.php">
            Cancel
        </a>

    </form>

</main>

<?php require_once __DIR__ . '/layout_footer.php'; ?>