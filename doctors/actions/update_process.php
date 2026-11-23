
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

// Redirect with a message.
function redirectWithMessage($message, $doctorId = null)
{
    if (
        $doctorId !== null &&
        is_int($doctorId) &&
        $doctorId > 0
    ) {
        $url = '../edit.php?id=' . $doctorId .
            '&message=' . urlencode($message);
    } else {
        $url = '../admin.php?message=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit;
}

// Validate CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($submittedToken) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    redirectWithMessage('Invalid or expired request. Please try again.');
}

// Validate submitted field types.
$textFields = [
    'full_name',
    'phone_number',
    'degree',
    'work_place',
    'bio',
    'city_id'
];

foreach ($textFields as $field) {
    if (
        !isset($_POST[$field]) ||
        !is_string($_POST[$field])
    ) {
        redirectWithMessage('Invalid form data.');
    }
}

$experienceInput = $_POST['experience_years'] ?? '';

if (!is_string($experienceInput)) {
    redirectWithMessage('Invalid experience value.');
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
    redirectWithMessage('Invalid doctor ID.');
}

// Validate specialization ID.
$specialization_id = filter_input(
    INPUT_POST,
    'specialization_id',
    FILTER_VALIDATE_INT
);

if (
    $specialization_id === false ||
    $specialization_id === null ||
    $specialization_id <= 0
) {
    redirectWithMessage(
        'Invalid specialization.',
        $doctor_id
    );
}

// Get submitted information.
$full_name = trim($_POST['full_name']);
$phone_number = trim($_POST['phone_number']);
$degree = trim($_POST['degree']);
$experienceInput = trim($experienceInput);
$work_place = trim($_POST['work_place']);
$bio = trim($_POST['bio']);
$city_input = trim($_POST['city_id']);

// Calculate text length.
function textLength($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

// Validate city.
$city_id = null;

if ($city_input !== '') {

    $validated_city_id = filter_var(
        $city_input,
        FILTER_VALIDATE_INT
    );

    if (
        $validated_city_id === false ||
        $validated_city_id <= 0
    ) {
        redirectWithMessage(
            'Invalid city selection.',
            $doctor_id
        );
    }

    $city_id = $validated_city_id;
}

// Validate experience.
if ($experienceInput === '') {
    $experience_years = 0;
} else {
    $experience_years = filter_var(
        $experienceInput,
        FILTER_VALIDATE_INT
    );
}

// Validate all information.
if (
    $full_name === '' ||
    textLength($full_name) > 100 ||
    textLength($phone_number) > 20 ||
    textLength($degree) > 150 ||
    textLength($work_place) > 255 ||
    textLength($bio) > 5000 ||
    $experience_years === false ||
    $experience_years < 0 ||
    $experience_years > 70
) {
    redirectWithMessage(
        'Invalid information. Please check the form.',
        $doctor_id
    );
}

try {

    // Check whether doctor exists.
    $check = $pdo->prepare("
        SELECT user_id
        FROM doctors
        WHERE doctor_id = :id
        LIMIT 1
    ");

    $check->execute([
        ':id' => $doctor_id
    ]);

    $doctor = $check->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        redirectWithMessage('Doctor not found.');
    }

    // Check whether specialization exists.
    $checkSpecialization = $pdo->prepare("
        SELECT specialization_id
        FROM specializations
        WHERE specialization_id = :id
    ");

    $checkSpecialization->execute([
        ':id' => $specialization_id
    ]);

    if (!$checkSpecialization->fetch()) {
        redirectWithMessage(
            'Invalid specialization.',
            $doctor_id
        );
    }

    // Check whether city exists.
    if ($city_id !== null) {

        $checkCity = $pdo->prepare("
            SELECT city_id
            FROM cities
            WHERE city_id = :id
        ");

        $checkCity->execute([
            ':id' => $city_id
        ]);

        if (!$checkCity->fetch()) {
            redirectWithMessage(
                'Invalid city.',
                $doctor_id
            );
        }
    }

    // Start transaction.
    $pdo->beginTransaction();

    // Update user information.
    $updateUser = $pdo->prepare("
        UPDATE users
        SET
            full_name = :full_name,
            phone_number = :phone_number
        WHERE user_id = :user_id
    ");

    $updateUser->execute([
        ':full_name' => $full_name,
        ':phone_number' => $phone_number !== ''
            ? $phone_number
            : null,
        ':user_id' => $doctor['user_id']
    ]);

    // Update doctor information.
    $updateDoctor = $pdo->prepare("
        UPDATE doctors
        SET
            specialization_id = :specialization_id,
            city_id = :city_id,
            degree = :degree,
            experience_years = :experience_years,
            work_place = :work_place,
            bio = :bio
        WHERE doctor_id = :doctor_id
    ");

    $updateDoctor->execute([
        ':specialization_id' => $specialization_id,
        ':city_id' => $city_id,
        ':degree' => $degree !== '' ? $degree : null,
        ':experience_years' => $experience_years,
        ':work_place' => $work_place !== ''
            ? $work_place
            : null,
        ':bio' => $bio !== '' ? $bio : null,
        ':doctor_id' => $doctor_id
    ]);

    // Commit transaction.
    $pdo->commit();

    // Rotate CSRF token after successful update.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Return to the same doctor's edit page.
    redirectWithMessage(
        'Doctor profile updated successfully!',
        $doctor_id
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    redirectWithMessage(
        'An error occurred while updating the doctor profile.',
        $doctor_id
    );
}
