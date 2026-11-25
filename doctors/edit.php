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

$docNavRole = $_SESSION['role_name'] ?? '';

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

        .doctor-module-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 12px;
            padding: 10px 20px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            overflow: visible;
        }

        .doctor-module-brand {
            font-weight: 700;
            color: #0f766e;
            text-decoration: none;
            white-space: nowrap;
        }

        .doctor-module-links {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .doctor-module-links > li {
            position: relative;
            white-space: nowrap;
        }

        .doctor-module-links a {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: #1e293b;
            font-size: 14px;
            white-space: nowrap;
        }

        .doctor-module-links a:hover,
        .doctor-module-links a.active {
            background: #0f766e;
            color: #ffffff;
        }

        .doctor-module-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 215px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            list-style: none;
            padding: 6px 0;
            margin-top: 4px;
            z-index: 1000;
        }

        .doctor-module-dropdown:hover .doctor-module-dropdown-menu {
            display: block;
        }

        .doctor-module-dropdown-menu li a {
            display: block;
            border-radius: 0;
            padding: 8px 14px;
            color: #1e293b;
        }

        .doctor-module-dropdown-menu li a:hover {
            background: #f1f5f9;
            color: #0f766e;
        }

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

<nav class="doctor-module-nav">
    <a href="../index.php" class="doctor-module-brand">MediConnect</a>

    <ul class="doctor-module-links">
        <li><a href="../index.php">Home</a></li>

        <?php if ($docNavRole === 'ADMIN'): ?>
            <li><a href="../admin/manage_patients.php">Manage Patients</a></li>

            <li class="doctor-module-dropdown">
                <a href="admin.php" class="active">Manage Doctors &#9662;</a>
                <ul class="doctor-module-dropdown-menu">
                    <li><a href="admin.php">Doctors &amp; Approval</a></li>
                    <li><a href="index.php">Doctors List (Public)</a></li>
                    <li><a href="register.php">Doctor Register</a></li>
                    <li><a href="../reviews/index.php">Reviews Doctor</a></li>
                </ul>
            </li>

            <li class="doctor-module-dropdown">
                <a href="../articles/manage.php">Manage Articles &#9662;</a>
                <ul class="doctor-module-dropdown-menu">
                    <li><a href="../articles/manage.php">Add / Edit / Delete Articles</a></li>
                    <li><a href="../articles/news.php">All Articles &amp; Comments</a></li>
                </ul>
            </li>

            <li><a href="../locations/index.php">Manage Locations</a></li>

        <?php elseif ($docNavRole === 'DOCTOR'): ?>
            <li><a href="../doctor/dashboard.php">Doctor Portal</a></li>
            <li><a href="../chat.php">Consultation Chat</a></li>
            <li><a href="../articles/news.php">Articles</a></li>
            <li><a href="../reviews/index.php">Reviews</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <a href="../index.php" class="sidebar-brand">
            MediConnect
        </a>

        <ul class="sidebar-menu">

            <li>
                <a href="../index.php">
                    Home
                </a>
            </li>

            <li>
                <a href="../admin/manage_patients.php">
                    Manage Patients
                </a>
            </li>

            <li>
                <a href="admin.php" class="active">
                    Manage Doctors
                </a>
            </li>

            <li>
                <a href="../reviews/index.php">
                    Reviews Doctor
                </a>
            </li>

            <li>
                <a href="../articles/manage.php">
                    Manage Articles
                </a>
            </li>

            <li>
                <a href="../articles/news.php">
                    All Articles &amp; Comments
                </a>
            </li>

            <li>
                <a href="../locations/index.php">
                    Manage Locations
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