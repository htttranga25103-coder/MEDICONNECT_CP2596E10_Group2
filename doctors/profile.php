<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Get doctor ID safely.
$doctor_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$doctor_id || $doctor_id <= 0) {
    die("Invalid doctor ID.");
}

// Escape HTML output.
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? 'N/A'),
        ENT_QUOTES,
        'UTF-8'
    );
}

$docNavRole = $_SESSION['role_name'] ?? '';
$doctor = null;
$error = '';

try {

    // Only display approved doctors.
    $sql = "
        SELECT
            d.doctor_id,
            d.degree,
            d.experience_years,
            d.work_place,
            d.bio,
            d.approval_status,
            u.full_name,
            u.email,
            u.phone_number,
            s.name AS specialization_name,
            c.city_name
        FROM doctors d
        INNER JOIN users u
            ON d.user_id = u.user_id
        LEFT JOIN specializations s
            ON d.specialization_id = s.specialization_id
        LEFT JOIN cities c
            ON d.city_id = c.city_id
        WHERE d.doctor_id = :doctor_id
          AND d.approval_status = 'APPROVED'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':doctor_id' => $doctor_id
    ]);

    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        $error = 'Doctor not found or this profile is not available.';
    }

} catch (PDOException $exception) {

    error_log("Doctor profile error: " . $exception->getMessage());

    $error = 'Unable to load doctor profile. Please try again later.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Doctor Profile - MediConnect</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0 0 35px;
            font-family: Arial, sans-serif;
            background: #eef2f5;
            color: #333;
        }

        .doctor-module-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 12px;
            padding: 12px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            overflow: visible;
            margin-bottom: 28px;
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

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .profile-header {
            background: #2f5d62;
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
        }

        .brand {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 25px;
        }

        .profile-header h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .profile-header p {
            margin: 0;
            color: #e2eeee;
            font-size: 15px;
        }

        .profile-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
        }

        .section-title {
            color: #2f5d62;
            font-size: 19px;
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e8eeee;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .info-item {
            background: #f8fafb;
            border: 1px solid #e5ebee;
            border-radius: 8px;
            padding: 16px;
            min-width: 0;
        }

        .info-label {
            display: block;
            color: #64748b;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .info-value {
            color: #263b40;
            font-size: 15px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .introduction {
            line-height: 1.7;
            font-weight: normal;
            white-space: normal;
        }

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
            font-weight: bold;
        }

        .profile-actions {
            margin-top: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .back-button {
            display: inline-block;
            padding: 11px 20px;
            background: #2f5d62;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .back-button:hover {
            background: #24494d;
        }

        .error-message {
            padding: 18px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 7px;
            line-height: 1.6;
        }

        .profile-footer {
            text-align: center;
            margin-top: 25px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 600px) {

            .profile-container {
                padding: 0 10px;
            }

            .profile-header,
            .profile-content {
                padding: 20px;
            }

            .profile-header h1 {
                font-size: 23px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
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
                <a href="admin.php">Manage Doctors &#9662;</a>
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

        <?php elseif ($docNavRole === 'PATIENT'): ?>
            <li><a href="../patient/book_appointment.php">Book Appointment</a></li>
            <li><a href="../patient/my_appointments.php">My Appointments</a></li>
            <li><a href="../chat.php">Consultation Chat</a></li>
            <li><a href="../articles/news.php">Articles</a></li>
            <li><a href="../reviews/index.php">Reviews</a></li>

        <?php else: ?>
            <li><a href="index.php">Doctors</a></li>
            <li><a href="../articles/news.php">Articles</a></li>
            <li><a href="../reviews/index.php">Reviews</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="profile-container">

    <header class="profile-header">

        <div class="brand">
            MediConnect
        </div>

        <?php if ($error !== ''): ?>

            <h1>Doctor Profile</h1>

            <p>Doctor professional information</p>

        <?php else: ?>

            <h1><?= e($doctor['full_name']) ?></h1>

            <p>
                <?= e($doctor['specialization_name']) ?>
                &nbsp;|&nbsp;
                Professional Doctor Profile
            </p>

        <?php endif; ?>

    </header>

    <main class="profile-content">

        <?php if ($error !== ''): ?>

            <h2 class="section-title">
                Unable to Display Profile
            </h2>

            <div class="error-message">
                <?= e($error) ?>
            </div>

        <?php else: ?>

            <h2 class="section-title">
                Personal and Professional Information
            </h2>

            <div class="info-grid">

                <div class="info-item">

                    <span class="info-label">
                        Doctor ID
                    </span>

                    <div class="info-value">
                        #<?= (int)$doctor['doctor_id'] ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Full Name
                    </span>

                    <div class="info-value">
                        <?= e($doctor['full_name']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Email Address
                    </span>

                    <div class="info-value">
                        <?= e($doctor['email']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Phone Number
                    </span>

                    <div class="info-value">
                        <?= e($doctor['phone_number']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Specialization
                    </span>

                    <div class="info-value">
                        <?= e($doctor['specialization_name']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Degree
                    </span>

                    <div class="info-value">
                        <?= e($doctor['degree']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Experience
                    </span>

                    <div class="info-value">

                        <?= $doctor['experience_years'] !== null
                            ? (int)$doctor['experience_years'] . ' years'
                            : 'N/A' ?>

                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Work Place
                    </span>

                    <div class="info-value">
                        <?= e($doctor['work_place']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        City
                    </span>

                    <div class="info-value">
                        <?= e($doctor['city_name']) ?>
                    </div>

                </div>

                <div class="info-item">

                    <span class="info-label">
                        Approval Status
                    </span>

                    <div class="info-value">
                        <span class="status">
                            <?= e($doctor['approval_status']) ?>
                        </span>
                    </div>

                </div>

                <div class="info-item full-width">

                    <span class="info-label">
                        Professional Introduction
                    </span>

                    <div class="info-value introduction">
                        <?= nl2br(e($doctor['bio'])) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>

        <div class="profile-actions">

            <a href="index.php" class="back-button">
                &larr; Back to Doctor List
            </a>

        </div>

    </main>

    <footer class="profile-footer">

        © <?= date('Y') ?> MediConnect. All rights reserved.

    </footer>

</div>

</body>
</html>