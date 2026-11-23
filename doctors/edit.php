
<?php

// Check ADMIN permission.
require_once __DIR__ . '/admin_guard.php';

// Connect to database.
require_once __DIR__ . '/../config/database.php';

// Escape output to prevent XSS.
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate doctor ID.
$doctor_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($doctor_id === false || $doctor_id === null || $doctor_id <= 0) {
    http_response_code(400);
    exit('Invalid doctor ID.');
}

// Initialize variables.
$doctor = null;
$specializations = [];
$cities = [];
$message = '';
$loadError = '';

// Get message safely.
$message = $_GET['message'] ?? '';

if (!is_string($message)) {
    $message = '';
}

try {

    // Get doctor information.
    $stmt = $pdo->prepare("
        SELECT
            d.doctor_id,
            d.user_id,
            d.specialization_id,
            d.city_id,
            d.degree,
            d.experience_years,
            d.work_place,
            d.bio,
            u.full_name,
            u.email,
            u.phone_number
        FROM doctors d
        INNER JOIN users u
            ON d.user_id = u.user_id
        WHERE d.doctor_id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $doctor_id
    ]);

    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        http_response_code(404);
        exit('Doctor not found.');
    }

    // Get specializations.
    $specializations = $pdo->query("
        SELECT
            specialization_id,
            name
        FROM specializations
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get cities.
    $cities = $pdo->query("
        SELECT
            city_id,
            city_name
        FROM cities
        ORDER BY city_name
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Edit doctor page error: " .
        $exception->getMessage()
    );

    http_response_code(500);

    $loadError = 'Unable to load doctor information. Please try again later.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MediConnect - Edit Doctor</title>

    <!-- Shared Admin stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=3">

    <style>

        .edit-form {
            padding: 22px;
        }

        .form-section-title {
            color: #2f5d62;
            font-size: 16px;
            font-weight: 700;
            margin: 8px 0 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5ebee;
        }

        .form-control:disabled {
            background: #eef2f5;
            color: #64748b;
            cursor: not-allowed;
        }

        .form-help {
            display: block;
            margin-top: -10px;
            margin-bottom: 15px;
            color: #64748b;
            font-size: 12px;
        }

        .edit-form textarea {
            min-height: 120px;
            resize: vertical;
        }

        .edit-form .form-actions {
            margin-top: 22px;
        }

        @media (max-width: 600px) {
            .edit-form {
                padding: 15px;
            }
        }

    </style>

</head>

<body>

<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <a href="../admin/manage_patients.php" class="sidebar-brand">
            MediConnect
        </a>

        <ul class="sidebar-menu">

            <li>
                <a href="../admin/manage_patients.php">
                    Patients
                </a>
            </li>

            <li>
                <a href="admin.php" class="active">
                    Doctor Management
                </a>
            </li>

            <li>
                <a href="../reviews/index.php">
                    Doctor Reviews
                </a>
            </li>

            <li>
                <a href="../locations/index.php">
                    Location Management
                </a>
            </li>

        </ul>

    </aside>

    <!-- MAIN AREA -->
    <div class="main-area">

        <!-- TOPBAR -->
        <header class="topbar">

            <div class="topbar-user">

                <span>
                    Welcome,
                    <strong>
                        <?= e($_SESSION['full_name'] ?? 'Admin') ?>
                    </strong>
                </span>

                <a
                    href="../auth/logout.php"
                    class="btn-logout"
                    onclick="return confirm('Are you sure you want to log out?');"
                >
                    Logout
                </a>

            </div>

        </header>

        <!-- CONTENT -->
        <div class="content-container">

            <main>

                <h2 class="page-title">
                    Edit Doctor Profile
                </h2>

                <p>
                    Update the doctor's personal and professional information.
                </p>

                <?php if ($message !== ''): ?>

                    <div class="alert-box alert-success">
                        <?= e($message) ?>
                    </div>

                <?php endif; ?>

                <?php if ($loadError !== ''): ?>

                    <div class="alert-box alert-error">
                        <?= e($loadError) ?>
                    </div>

                <?php elseif ($doctor !== null): ?>

                    <section class="panel-card">

                        <div class="panel-header">

                            <h3 class="panel-title">
                                Doctor Information
                                #<?= (int)$doctor['doctor_id'] ?>
                            </h3>

                        </div>

                        <form
                            action="actions/update_process.php"
                            method="POST"
                            class="edit-form"
                        >

                            <!-- CSRF protection -->
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <!-- Doctor ID -->
                            <input
                                type="hidden"
                                name="doctor_id"
                                value="<?= (int)$doctor['doctor_id'] ?>"
                            >

                            <h4 class="form-section-title">
                                Personal Information
                            </h4>

                            <div class="form-grid">

                                <div class="form-group">

                                    <label class="form-label" for="full_name">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        id="full_name"
                                        name="full_name"
                                        class="form-control"
                                        value="<?= e($doctor['full_name']) ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="email">
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        class="form-control"
                                        value="<?= e($doctor['email']) ?>"
                                        disabled
                                    >

                                    <small class="form-help">
                                        Email cannot be changed here.
                                    </small>

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="phone_number">
                                        Phone Number
                                    </label>

                                    <input
                                        type="text"
                                        id="phone_number"
                                        name="phone_number"
                                        class="form-control"
                                        value="<?= e($doctor['phone_number'] ?? '') ?>"
                                        maxlength="20"
                                    >

                                </div>

                            </div>

                            <h4 class="form-section-title">
                                Professional Information
                            </h4>

                            <div class="form-grid">

                                <div class="form-group">

                                    <label class="form-label" for="specialization_id">
                                        Specialization
                                    </label>

                                    <select
                                        id="specialization_id"
                                        name="specialization_id"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select specialization
                                        </option>

                                        <?php foreach ($specializations as $item): ?>

                                            <option
                                                value="<?= (int)$item['specialization_id'] ?>"
                                                <?= (int)$item['specialization_id'] === (int)$doctor['specialization_id'] ? 'selected' : '' ?>
                                            >
                                                <?= e($item['name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="degree">
                                        Degree
                                    </label>

                                    <input
                                        type="text"
                                        id="degree"
                                        name="degree"
                                        class="form-control"
                                        value="<?= e($doctor['degree'] ?? '') ?>"
                                        maxlength="150"
                                    >

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="experience_years">
                                        Experience Years
                                    </label>

                                    <input
                                        type="number"
                                        id="experience_years"
                                        name="experience_years"
                                        class="form-control"
                                        min="0"
                                        max="70"
                                        value="<?= e($doctor['experience_years'] ?? '') ?>"
                                    >

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="city_id">
                                        City
                                    </label>

                                    <select
                                        id="city_id"
                                        name="city_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Select city
                                        </option>

                                        <?php foreach ($cities as $city): ?>

                                            <option
                                                value="<?= (int)$city['city_id'] ?>"
                                                <?= (int)$city['city_id'] === (int)$doctor['city_id'] ? 'selected' : '' ?>
                                            >
                                                <?= e($city['city_name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="work_place">
                                        Work Place
                                    </label>

                                    <input
                                        type="text"
                                        id="work_place"
                                        name="work_place"
                                        class="form-control"
                                        value="<?= e($doctor['work_place'] ?? '') ?>"
                                        maxlength="255"
                                    >

                                </div>

                                <div class="form-group">

                                    <label class="form-label" for="bio">
                                        Introduction
                                    </label>

                                    <textarea
                                        id="bio"
                                        name="bio"
                                        class="form-control"
                                        rows="5"
                                        maxlength="5000"
                                    ><?= e($doctor['bio'] ?? '') ?></textarea>

                                </div>

                            </div>

                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="btn-mc btn-mc-teal"
                                >
                                    Save Changes
                                </button>

                                <a
                                    href="admin.php"
                                    class="btn-mc btn-mc-outline-gray"
                                >
                                    Cancel
                                </a>

                            </div>

                        </form>

                    </section>

                <?php endif; ?>

            </main>

        </div>

        <!-- FOOTER -->
        <footer class="admin-footer">

            <div>
                © <?= date('Y') ?> MediConnect. All rights reserved.
            </div>

            <div class="footer-links">
                <a href="#">Terms</a>
                <a href="#">Privacy</a>
                <a href="#">Support</a>
            </div>

        </footer>

    </div>

</div>

</body>
</html>
