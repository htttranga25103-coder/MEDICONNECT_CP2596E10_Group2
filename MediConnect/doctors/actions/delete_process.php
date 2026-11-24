
<?php

// Check ADMIN permission.
require_once __DIR__ . '/../admin_guard.php';
// Connect to database.
require_once __DIR__ . '/../../config/database.php';

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit;
}

// Validate CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($submittedToken) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    header(
        'Location: ../admin.php?message=' .
        urlencode('Invalid or expired request. Please try again.')
    );
    exit;
}

// Validate doctor ID.
$doctor_id = filter_input(
    INPUT_POST,
    'doctor_id',
    FILTER_VALIDATE_INT
);

if (
    $doctor_id === false ||
    $doctor_id === null ||
    $doctor_id <= 0
) {
    header(
        'Location: ../admin.php?message=' .
        urlencode('Invalid doctor ID.')
    );
    exit;
}

try {

    // Start transaction.
    $pdo->beginTransaction();

    // Check whether the doctor exists.
    $stmt = $pdo->prepare("
        SELECT doctor_id, user_id
        FROM doctors
        WHERE doctor_id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $doctor_id
    ]);

    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        $pdo->rollBack();

        header(
            'Location: ../admin.php?message=' .
            urlencode('Doctor not found.')
        );
        exit;
    }

    // Prevent deletion if appointment records exist.
    $checkAppointments = $pdo->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE doctor_id = :id
    ");

    $checkAppointments->execute([
        ':id' => $doctor_id
    ]);

    if ((int) $checkAppointments->fetchColumn() > 0) {
        $pdo->rollBack();

        header(
            'Location: ../admin.php?message=' .
            urlencode(
                'Cannot delete this doctor because appointment records exist.'
            )
        );
        exit;
    }

    // Prevent deletion if review records exist.
    $checkReviews = $pdo->prepare("
        SELECT COUNT(*)
        FROM doctor_reviews
        WHERE doctor_id = :id
    ");

    $checkReviews->execute([
        ':id' => $doctor_id
    ]);

    if ((int) $checkReviews->fetchColumn() > 0) {
        $pdo->rollBack();

        header(
            'Location: ../admin.php?message=' .
            urlencode(
                'Cannot delete this doctor because review records exist.'
            )
        );
        exit;
    }

    // Delete doctor profile only.
    $deleteStmt = $pdo->prepare("
        DELETE FROM doctors
        WHERE doctor_id = :id
    ");

    $deleteStmt->execute([
        ':id' => $doctor_id
    ]);

    if ($deleteStmt->rowCount() !== 1) {
        $pdo->rollBack();

        header(
            'Location: ../admin.php?message=' .
            urlencode('Doctor profile could not be deleted.')
        );
        exit;
    }

    // Keep the associated user account.
    $pdo->commit();

    // Rotate CSRF token after successful deletion.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header(
        'Location: ../admin.php?message=' .
        urlencode('Doctor profile deleted successfully.')
    );
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    header(
        'Location: ../admin.php?message=' .
        urlencode(
            'Could not delete doctor. This record may be used by other data.'
        )
    );
    exit;
}
