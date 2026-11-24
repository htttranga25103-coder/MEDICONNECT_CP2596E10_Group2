
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$roleName = $_SESSION['role_name'] ?? '';
$fullName = $_SESSION['full_name'] ?? 'User';

$pageTitle = 'MediConnect - Doctor Reviews';

function reviewsSidebarActive($pages, $currentPage)
{
    return in_array($currentPage, $pages, true) ? 'active' : '';
}

$roleLabel = [
    'ADMIN' => 'Administrator',
    'DOCTOR' => 'Doctor',
    'PATIENT' => 'Patient'
][$roleName] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="../assets/css/admin_style.css?v=3">

    <style>
        .reviews-sidebar-subtitle {
            padding: 15px 20px 5px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .reviews-role-label {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            margin-top: 3px;
        }

        .reviews-sidebar .sidebar-menu a.active {
            font-weight: 700;
        }

        .reviews-sidebar .sidebar-menu a {
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">

    <aside class="sidebar reviews-sidebar">

        <?php if ($roleName === 'ADMIN'): ?>

            <a href="../admin/manage_patients.php" class="sidebar-brand">
                MediConnect
            </a>

            <div class="reviews-sidebar-subtitle">Administration</div>

            <ul class="sidebar-menu">

                <li>
                    <a href="../admin/manage_patients.php">
                        Patients
                    </a>
                </li>

                <li>
                    <a href="../doctors/admin.php">
                        Doctor Management
                    </a>
                </li>

                <li>
                    <a href="index.php"
                       class="<?= reviewsSidebarActive(['index.php'], $currentPage) ?>">
                        Doctor Reviews
                    </a>
                </li>

                <li>
                    <a href="../locations/index.php">
                        Location Management
                    </a>
                </li>

            </ul>

        <?php elseif ($roleName === 'DOCTOR'): ?>

            <a href="../doctor/dashboard.php" class="sidebar-brand">
                MediConnect
            </a>

            <div class="reviews-sidebar-subtitle">Doctor Portal</div>

            <ul class="sidebar-menu">

                <li>
                    <a href="../doctor/dashboard.php">
                        Doctor Dashboard
                    </a>
                </li>

                <li>
                    <a href="index.php"
                       class="<?= reviewsSidebarActive(['index.php'], $currentPage) ?>">
                        My Patient Reviews
                    </a>
                </li>

            </ul>

        <?php elseif ($roleName === 'PATIENT'): ?>

            <a href="../patient/book_appointment.php" class="sidebar-brand">
                MediConnect
            </a>

            <div class="reviews-sidebar-subtitle">Patient Portal</div>

            <ul class="sidebar-menu">

                <li>
                    <a href="../patient/book_appointment.php">
                        Book Appointment
                    </a>
                </li>

                <li>
                    <a href="../patient/my_appointments.php">
                        My Appointments
                    </a>
                </li>

                <li>
                    <a href="index.php"
                       class="<?= reviewsSidebarActive(['index.php'], $currentPage) ?>">
                        My Doctor Reviews
                    </a>
                </li>

            </ul>

        <?php endif; ?>

    </aside>

    <div class="main-area">

        <header class="topbar">

            <div class="topbar-user">

                <span>
                    Welcome,
                    <strong>
                        <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                    </strong>

                    <span class="reviews-role-label">
                        <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>

                <a href="../auth/logout.php"
                   class="btn-logout"
                   onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>

            </div>

        </header>

        <div class="content-container">
