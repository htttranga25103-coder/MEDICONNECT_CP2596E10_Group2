
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

// Validate approval status.
$status = $_POST['status'] ?? '';

if (!is_string($status)) {
    $status = '';
}

if (
    $doctor_id === false ||
    $doctor_id === null ||
    $doctor_id <= 0 ||
    !in_array($status, ['APPROVED', 'REJECTED'], true)
) {
    header(
        'Location: ../admin.php?message=' .
        urlencode('Invalid doctor ID or approval status.')
    );
    exit;
}

try {

    // Check whether the doctor exists.
    $checkStmt = $pdo->prepare("
        SELECT doctor_id, approval_status
        FROM doctors
        WHERE doctor_id = :doctor_id
        LIMIT 1
    ");

    $checkStmt->execute([
        ':doctor_id' => $doctor_id
    ]);

    $doctor = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        header(
            'Location: ../admin.php?message=' .
            urlencode('Doctor not found.')
        );
        exit;
    }

    // Only pending doctors can be processed.
    if ($doctor['approval_status'] !== 'PENDING') {
        header(
            'Location: ../admin.php?message=' .
            urlencode('This doctor has already been processed.')
        );
        exit;
    }

    // Update approval status.
    $stmt = $pdo->prepare("
        UPDATE doctors
        SET approval_status = :status
        WHERE doctor_id = :doctor_id
        AND approval_status = 'PENDING'
    ");

    $stmt->execute([
        ':status' => $status,
        ':doctor_id' => $doctor_id
    ]);

    if ($stmt->rowCount() === 1) {

        $message = $status === 'APPROVED'
            ? 'Doctor approved successfully!'
            : 'Doctor rejected successfully!';

        // Rotate CSRF token after successful processing.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    } else {

        $message = 'No changes made. Please refresh and try again.';
    }

    header(
        'Location: ../admin.php?message=' .
        urlencode($message)
    );
    exit;

} catch (Throwable $e) {

    error_log($e->getMessage());

    header(
        'Location: ../admin.php?message=' .
        urlencode('An error occurred while processing the doctor.')
    );
    exit;
}
