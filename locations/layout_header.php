<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$pageTitle = 'MediConnect - Location Management';

function locationTabActive(string $target, string $currentPage): string
{
    return $currentPage === $target ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=4">
    <style>
        .topbar {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
        }
        .topbar-user span {
            color: #1e293b !important;
        }
        .btn-logout {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 7px 16px !important;
            font-weight: 700 !important;
        }
        .location-subnav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .location-subnav a {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .location-subnav a:hover {
            border-color: #0f766e;
            color: #0f766e;
            background: #f0fdfa;
        }
        .location-subnav a.active {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">

    <!-- SHARED ADMIN SIDEBAR WITH ICONS -->
    <?php
        $adminActivePage = 'locations';
        require_once __DIR__ . '/../includes/admin_sidebar.php';
    ?>

    <div class="main-area">

        <header class="topbar">
            <div class="topbar-user" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 16px; font-weight: 700;">
                    Location Management — Welcome,
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
            <nav class="location-subnav" aria-label="Location sections">
                <a href="index.php" class="<?= locationTabActive('index.php', $currentPage) ?>">Locations Overview</a>
                <a href="cities.php" class="<?= locationTabActive('cities.php', $currentPage) ?>">Cities</a>
                <a href="districts.php" class="<?= locationTabActive('districts.php', $currentPage) ?>">Districts</a>
                <a href="streets.php" class="<?= locationTabActive('streets.php', $currentPage) ?>">Streets</a>
            </nav>