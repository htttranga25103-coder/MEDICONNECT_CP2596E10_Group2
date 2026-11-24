
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$pageTitle = 'MediConnect - Location Management';

function locationSidebarActive($pages, $currentPage)
{
    return in_array($currentPage, $pages, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="../assets/css/admin_style.css?v=3">
</head>
<body>

<div class="admin-wrapper">

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
                <a href="../doctors/admin.php">
                    Doctor Management
                </a>
            </li>

            <li>
                <a href="../reviews/index.php">
                    Doctor Reviews
                </a>
            </li>

            <li>
                <a href="index.php"
                   class="<?= locationSidebarActive(['index.php', 'cities.php', 'districts.php', 'streets.php'], $currentPage) ?>">
                    Location Management
                </a>
            </li>

            <li>
                <a href="cities.php"
                   class="<?= locationSidebarActive(['cities.php'], $currentPage) ?>">
                    Cities
                </a>
            </li>

            <li>
                <a href="districts.php"
                   class="<?= locationSidebarActive(['districts.php'], $currentPage) ?>">
                    Districts
                </a>
            </li>

            <li>
                <a href="streets.php"
                   class="<?= locationSidebarActive(['streets.php'], $currentPage) ?>">
                    Streets
                </a>
            </li>

        </ul>

    </aside>

    <div class="main-area">

        <header class="topbar">

            <div class="topbar-user">

                <span>
                    Welcome,
                    <strong>
                        <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </span>

                <a href="../auth/logout.php"
                   class="btn-logout"
                   onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>

            </div>

        </header>

        <div class="content-container">
