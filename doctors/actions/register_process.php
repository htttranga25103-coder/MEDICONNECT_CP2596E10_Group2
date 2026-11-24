
<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

// Redirect with flash message and safe old values.
function redirectWithMessage($message, $type = 'error', $old = [])
{
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];

    $_SESSION['old'] = $old;

    header('Location: ../register.php');
    exit;
}

// Count Unicode characters.
function textLength($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return preg_match_all('/./us', $value);
}

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

// Reject unexpected array input.
$fields = [
    'csrf_token',
    'full_name',
    'email',
    'phone_number',
    'password',
    'confirm_password',
    'specialization_id',
    'degree',
    'experience_years',
    'work_place',
    'bio',
    'city_id',
    'district_id',
    'street_id',
    'terms_agree'
];

foreach ($fields as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        redirectWithMessage('Invalid form data. Please try again.');
    }
}

// Validate CSRF.
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $submittedToken === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    redirectWithMessage(
        'Invalid or expired form session. Please reload the registration page.'
    );
}

// Read and normalize form values.
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');

$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$specializationInput = trim($_POST['specialization_id'] ?? '');
$degree = trim($_POST['degree'] ?? '');

$experienceInput = trim($_POST['experience_years'] ?? '0');
$work_place = trim($_POST['work_place'] ?? '');
$bio = trim($_POST['bio'] ?? '');

$cityInput = trim($_POST['city_id'] ?? '');
$districtInput = trim($_POST['district_id'] ?? '');
$streetInput = trim($_POST['street_id'] ?? '');

$agreeTerms = $_POST['terms_agree'] ?? '';

// Old values are restored after an error.
// Passwords are never stored in session.
$old = [
    'full_name' => $full_name,
    'email' => $email,
    'phone_number' => $phone_number,
    'specialization_id' => $specializationInput,
    'degree' => $degree,
    'experience_years' => $experienceInput,
    'work_place' => $work_place,
    'bio' => $bio,
    'city_id' => $cityInput,
    'district_id' => $districtInput,
    'street_id' => $streetInput,
    'terms_agree' => $agreeTerms
];

// Required fields.
if (
    $full_name === '' ||
    $email === '' ||
    $password === '' ||
    $confirm_password === '' ||
    $specializationInput === '' ||
    $cityInput === '' ||
    $districtInput === '' ||
    $streetInput === ''
) {
    redirectWithMessage(
        'Please complete all required fields.',
        'error',
        $old
    );
}

// Full name.
$nameLength = textLength($full_name);

if ($nameLength === false || $nameLength < 2 || $nameLength > 100) {
    redirectWithMessage(
        'Full name must contain between 2 and 100 characters.',
        'error',
        $old
    );
}

// Email.
$emailLength = textLength($email);

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $emailLength === false ||
    $emailLength > 150
) {
    redirectWithMessage(
        'Please enter a valid email address (maximum 150 characters).',
        'error',
        $old
    );
}

// Password length.
if (strlen($password) < 8 || strlen($password) > 72) {
    redirectWithMessage(
        'Password must contain between 8 and 72 bytes.',
        'error',
        $old
    );
}

// Password must include at least one letter and one number.
if (
    !preg_match('/[A-Za-z]/', $password) ||
    !preg_match('/[0-9]/', $password)
) {
    redirectWithMessage(
        'Password must include at least one letter and one number.',
        'error',
        $old
    );
}

// Confirm password.
if (!hash_equals($password, $confirm_password)) {
    redirectWithMessage(
        'Password and confirmation password do not match.',
        'error',
        $old
    );
}

// Terms agreement.
if ($agreeTerms !== '1') {
    redirectWithMessage(
        'You must agree to the Terms of Use and Privacy Policy.',
        'error',
        $old
    );
}

// Phone number.
$phoneLength = textLength($phone_number);

if ($phoneLength === false || $phoneLength > 20) {
    redirectWithMessage(
        'Phone number must not exceed 20 characters.',
        'error',
        $old
    );
}

// Specialization ID.
$specialization_id = filter_var(
    $specializationInput,
    FILTER_VALIDATE_INT
);

if ($specialization_id === false || $specialization_id <= 0) {
    redirectWithMessage(
        'Please select a valid medical specialty.',
        'error',
        $old
    );
}

// Degree.
$degreeLength = textLength($degree);

if ($degreeLength === false || $degreeLength > 150) {
    redirectWithMessage(
        'Medical qualifications must not exceed 150 characters.',
        'error',
        $old
    );
}

// Experience years.
if ($experienceInput === '') {
    $experience_years = 0;
} else {
    $experience_years = filter_var(
        $experienceInput,
        FILTER_VALIDATE_INT
    );
}

if (
    $experience_years === false ||
    $experience_years === null ||
    $experience_years < 0 ||
    $experience_years > 70
) {
    redirectWithMessage(
        'Years of experience must be a whole number between 0 and 70.',
        'error',
        $old
    );
}

// Workplace.
$workPlaceLength = textLength($work_place);

if ($workPlaceLength === false || $workPlaceLength > 255) {
    redirectWithMessage(
        'Current workplace must not exceed 255 characters.',
        'error',
        $old
    );
}

// Biography.
$bioLength = textLength($bio);

if ($bioLength === false || $bioLength > 5000) {
    redirectWithMessage(
        'Professional introduction must not exceed 5000 characters.',
        'error',
        $old
    );
}

// Validate city, district and street IDs.
$city_id = filter_var($cityInput, FILTER_VALIDATE_INT);
$district_id = filter_var($districtInput, FILTER_VALIDATE_INT);
$street_id = filter_var($streetInput, FILTER_VALIDATE_INT);

if (
    $city_id === false || $city_id <= 0 ||
    $district_id === false || $district_id <= 0 ||
    $street_id === false || $street_id <= 0
) {
    redirectWithMessage(
        'Please select a valid residential address.',
        'error',
        $old
    );
}

try {
    $pdo->beginTransaction();

    // Find DOCTOR role.
    $roleStmt = $pdo->prepare(
        "SELECT role_id
         FROM roles
         WHERE role_name = ?
         LIMIT 1"
    );

    $roleStmt->execute(['DOCTOR']);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new RuntimeException('DOCTOR role was not found.');
    }

    $doctorRoleId = (int) $role['role_id'];

    // Check duplicate email.
    $checkEmail = $pdo->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $checkEmail->execute([$email]);

    if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        redirectWithMessage(
            'This email is already registered. Please use another email.',
            'error',
            $old
        );
    }

    // Check specialization exists.
    $checkSpecialization = $pdo->prepare(
        "SELECT specialization_id
         FROM specializations
         WHERE specialization_id = ?
         LIMIT 1"
    );

    $checkSpecialization->execute([$specialization_id]);

    if (!$checkSpecialization->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        redirectWithMessage(
            'Selected medical specialty does not exist.',
            'error',
            $old
        );
    }

    // Check district belongs to selected city.
    $checkDistrict = $pdo->prepare(
        "SELECT district_id
         FROM districts
         WHERE district_id = ?
           AND city_id = ?
         LIMIT 1"
    );

    $checkDistrict->execute([
        $district_id,
        $city_id
    ]);

    if (!$checkDistrict->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        redirectWithMessage(
            'The selected district does not belong to the selected city.',
            'error',
            $old
        );
    }

    // Check street belongs to selected district.
    $checkStreet = $pdo->prepare(
        "SELECT street_id
         FROM streets
         WHERE street_id = ?
           AND district_id = ?
         LIMIT 1"
    );

    $checkStreet->execute([
        $street_id,
        $district_id
    ]);

    if (!$checkStreet->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        redirectWithMessage(
            'The selected street does not belong to the selected district.',
            'error',
            $old
        );
    }

    // Hash password.
    $password_hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($password_hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    // Create user account.
    $insertUser = $pdo->prepare(
        "INSERT INTO users
        (
            role_id,
            email,
            password_hash,
            full_name,
            phone_number,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $insertUser->execute([
        $doctorRoleId,
        $email,
        $password_hash,
        $full_name,
        $phone_number !== '' ? $phone_number : null,
        1
    ]);

    $user_id = (int) $pdo->lastInsertId();

    // Create doctor profile with residential address.
    $insertDoctor = $pdo->prepare(
        "INSERT INTO doctors
        (
            user_id,
            specialization_id,
            city_id,
            district_id,
            street_id,
            degree,
            experience_years,
            work_place,
            bio,
            approval_status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $insertDoctor->execute([
        $user_id,
        $specialization_id,
        $city_id,
        $district_id,
        $street_id,
        $degree !== '' ? $degree : null,
        $experience_years,
        $work_place !== '' ? $work_place : null,
        $bio !== '' ? $bio : null,
        'PENDING'
    ]);

    // Commit transaction.
    $pdo->commit();

    // Rotate CSRF token.
    unset($_SESSION['csrf_token']);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    redirectWithMessage(
        'Registration successful! Your doctor account is waiting for administrator approval.',
        'success'
    );

} catch (PDOException $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Doctor registration database error: ' . $exception->getMessage()
    );

    if ($exception->getCode() === '23000') {
        redirectWithMessage(
            'This email or account is already registered. Please check your information.',
            'error',
            $old
        );
    }

    redirectWithMessage(
        'Registration failed due to a database error. Please try again later.',
        'error',
        $old
    );

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Doctor registration error: ' . $exception->getMessage()
    );

    redirectWithMessage(
        'Registration failed. Please try again later or contact the administrator.',
        'error',
        $old
    );
}
