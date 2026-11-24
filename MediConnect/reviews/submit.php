<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only logged-in PATIENT accounts can submit reviews.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_name'])
) {
    header("Location: ../auth/login.php");
    exit();
}

if (strtoupper((string)$_SESSION['role_name']) !== 'PATIENT') {
    http_response_code(403);
    exit("Only patients can submit reviews.");
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

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Validate submitted data types.
if (
    !isset($_POST['appointment_id']) ||
    !is_string($_POST['appointment_id']) ||
    !isset($_POST['rating']) ||
    !is_string($_POST['rating']) ||
    !isset($_POST['comment']) ||
    !is_string($_POST['comment'])
) {
    http_response_code(400);
    exit("Invalid form data.");
}

// Validate CSRF token.
if (
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit("Invalid security token. Please try again.");
}

// Get and validate submitted data.
$appointment_id = filter_var(
    $_POST['appointment_id'],
    FILTER_VALIDATE_INT
);

$rating = filter_var(
    $_POST['rating'],
    FILTER_VALIDATE_INT
);

$comment = trim($_POST['comment']);

// Validate appointment ID.
if ($appointment_id === false || $appointment_id <= 0) {
    http_response_code(400);
    exit("Invalid appointment ID.");
}

// Validate rating.
if ($rating === false || $rating < 1 || $rating > 5) {
    http_response_code(400);
    exit("Rating must be between 1 and 5 stars.");
}

// Validate comment length.
if (mb_strlen($comment, 'UTF-8') > 2000) {
    http_response_code(400);
    exit("Your feedback must not exceed 2000 characters.");
}

try {

    $pdo->beginTransaction();

    // Check whether the appointment belongs to the logged-in patient
    // and is completed.
    $checkAppointment = $pdo->prepare("
        SELECT
            a.appointment_id,
            a.patient_id,
            a.doctor_id
        FROM appointments a
        JOIN patients p
            ON a.patient_id = p.patient_id
        WHERE a.appointment_id = :appointment_id
          AND a.status = 'COMPLETED'
          AND p.user_id = :user_id
        FOR UPDATE
    ");

    $checkAppointment->execute([
        ':appointment_id' => $appointment_id,
        ':user_id' => $userId
    ]);

    $appointment = $checkAppointment->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $pdo->rollBack();
        http_response_code(404);
        exit("Appointment not found, not completed, or you do not have permission to review it.");
    }

    // Check whether this appointment already has a review.
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
        $pdo->rollBack();
        exit("This appointment has already been reviewed.");
    }

    // Insert review into database.
    $insertReview = $pdo->prepare("
        INSERT INTO doctor_reviews (
            appointment_id,
            patient_id,
            doctor_id,
            rating,
            comment
        )
        VALUES (
            :appointment_id,
            :patient_id,
            :doctor_id,
            :rating,
            :comment
        )
    ");

    $insertReview->execute([
        ':appointment_id' => $appointment['appointment_id'],
        ':patient_id' => $appointment['patient_id'],
        ':doctor_id' => $appointment['doctor_id'],
        ':rating' => $rating,
        ':comment' => $comment
    ]);

    $pdo->commit();

    // Redirect after successful submission.
    header("Location: index.php?success=1");
    exit();

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle duplicate appointment review.
    if ($e->getCode() === '23000') {
        http_response_code(409);
        exit("This appointment has already been reviewed.");
    }

    error_log("Review submission error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to submit your review. Please try again later.");

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Unexpected review submission error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to submit your review. Please try again later.");
}