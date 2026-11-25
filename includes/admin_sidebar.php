<?php
$basePrefix = $adminBasePrefix ?? '../';
$activePage = $adminActivePage ?? basename($_SERVER['PHP_SELF']);

$adminMenuItems = [
    [
        'key'   => 'admin_dashboard.php',
        'label' => 'Overview',
        'href'  => $basePrefix . 'views/admin_dashboard.php',
        'icon'  => $basePrefix . 'images/icons/overview.png',
    ],
    [
        'key'   => 'home',
        'label' => 'Home Page',
        'href'  => $basePrefix . 'index.php',
        'icon'  => $basePrefix . 'images/icons/overview.png',
    ],
    [
        'key'   => 'manage_patients.php',
        'label' => 'Manage Patients',
        'href'  => $basePrefix . 'admin/manage_patients.php',
        'icon'  => $basePrefix . 'images/icons/patient.png',
    ],
    [
        'key'   => 'admin.php',
        'label' => 'Manage Doctors',
        'href'  => $basePrefix . 'doctors/admin.php',
        'icon'  => $basePrefix . 'images/icons/doctor.png',
    ],
    [
        'key'   => 'reviews',
        'label' => 'Reviews Doctor',
        'href'  => $basePrefix . 'reviews/index.php',
        'icon'  => $basePrefix . 'images/icons/review.png',
    ],
    [
        'key'   => 'manage.php',
        'label' => 'Manage Articles',
        'href'  => $basePrefix . 'articles/manage.php',
        'icon'  => $basePrefix . 'images/icons/new.png',
    ],
    [
        'key'   => 'news.php',
        'label' => 'Articles & Comments',
        'href'  => $basePrefix . 'articles/news.php',
        'icon'  => $basePrefix . 'images/icons/comment.png',
    ],
    [
        'key'   => 'locations',
        'label' => 'Manage Locations',
        'href'  => $basePrefix . 'locations/index.php',
        'icon'  => $basePrefix . 'images/icons/review.png',
    ],
];
?>
<style>
    .mc-admin-sidebar {
        width: 235px;
        background-color: #0f766e !important;
        flex-shrink: 0;
        padding-top: 18px;
        min-height: 100vh;
    }
    .mc-admin-sidebar .mc-sidebar-logo {
        display: block;
        font-size: 24px;
        font-weight: 800;
        color: #ffffff;
        margin: 0 0 18px 20px;
        text-decoration: none;
        letter-spacing: -0.5px;
    }
    .mc-admin-sidebar .mc-nav-list {
        list-style: none;
        margin: 8px 0 0;
        padding: 0;
    }
    .mc-admin-sidebar .mc-nav-list li {
        margin: 3px 10px;
        border-radius: 8px;
        overflow: hidden;
    }
    .mc-admin-sidebar .mc-nav-list a {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 14px;
        text-decoration: none;
        color: #ccfbf1;
        font-size: 14px;
        font-weight: 600;
        transition: background 0.15s ease;
    }
    .mc-admin-sidebar .mc-nav-list a:hover {
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
    }
    .mc-admin-sidebar .mc-nav-list li.active {
        background: #ffffff;
    }
    .mc-admin-sidebar .mc-nav-list li.active a {
        color: #0f766e;
        font-weight: 700;
    }
    .mc-admin-sidebar .mc-nav-list li.active img {
        filter: invert(32%) sepia(63%) saturate(835%) hue-rotate(138deg) brightness(91%) contrast(94%);
    }
    /* Đồng bộ nút Logout dịu mắt cho toàn bộ trang Admin */
    .btn-logout,
    .topbar .btn-logout,
    .topbar-user .btn-logout,
    .page-header .btn-logout {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background-color: #ffffff !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 7px 16px !important;
        font-size: 13.5px !important;
        font-weight: 600 !important;
        text-decoration: none !important;
        transition: all 0.15s ease !important;
    }

    .btn-logout:hover,
    .topbar .btn-logout:hover,
    .topbar-user .btn-logout:hover,
    .page-header .btn-logout:hover {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
        border-color: #fca5a5 !important;
    }
</style>
<aside class="sidebar mc-admin-sidebar">
    <a href="<?= htmlspecialchars($basePrefix . 'index.php') ?>" class="mc-sidebar-logo">MediConnect</a>
    <ul class="mc-nav-list">
        <?php foreach ($adminMenuItems as $item): ?>
            <?php $isActive = ($activePage === $item['key']); ?>
            <li class="<?= $isActive ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($item['href']) ?>">
                    <img src="<?= htmlspecialchars($item['icon']) ?>" width="22" height="22" alt="">
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>