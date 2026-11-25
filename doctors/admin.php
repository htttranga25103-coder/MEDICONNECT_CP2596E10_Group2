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

// Get search keyword safely.
$keyword = $_GET['keyword'] ?? '';

if (!is_string($keyword)) {
    $keyword = '';
}

$keyword = trim($keyword);

if (mb_strlen($keyword, 'UTF-8') > 100) {
    $keyword = mb_substr($keyword, 0, 100, 'UTF-8');
}

// Get message safely.
$message = $_GET['message'] ?? '';

if (!is_string($message)) {
    $message = '';
}

// Initialize doctor list and error message.
$doctors = [];
$loadError = '';

try {

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
        WHERE (
            u.full_name LIKE :name_keyword
            OR u.email LIKE :email_keyword
        )
        ORDER BY d.doctor_id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $searchKeyword = '%' . $keyword . '%';

    $stmt->execute([
        ':name_keyword' => $searchKeyword,
        ':email_keyword' => $searchKeyword
    ]);

    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Doctor management list error: " .
        $exception->getMessage()
    );

    $loadError = 'Unable to load doctor records. Please try again later.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Doctor Management</title>
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
        .action-buttons-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">

    <!-- SHARED ADMIN SIDEBAR WITH ICONS -->
    <?php
        $adminActivePage = 'admin.php';
        require_once __DIR__ . '/../includes/admin_sidebar.php';
    ?>

    <!-- MAIN AREA -->
    <div class="main-area">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-user" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 16px; font-weight: 700;">
                    Doctor Management — Welcome, <strong><?= e($_SESSION['full_name'] ?? 'Admin') ?></strong>
                </span>

                <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>
            </div>
        </header>

        <!-- CONTENT -->
        <div class="content-container">
            <main>
                <p style="color: #64748b; margin-bottom: 16px;">
                    Manage doctor registration, profiles, and approval status.
                </p>

                <!-- SUCCESS MESSAGE -->
                <?php if ($message !== ''): ?>
                    <div class="alert-box alert-success">
                        <span><?= e($message) ?></span>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&#10005;</button>
                    </div>
                <?php endif; ?>

                <!-- ERROR MESSAGE -->
                <?php if ($loadError !== ''): ?>
                    <div class="alert-box alert-error">
                        <span><?= e($loadError) ?></span>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&#10005;</button>
                    </div>
                <?php endif; ?>

                <!-- DOCTOR MANAGEMENT PANEL -->
                <section class="panel-card">
                    <div class="panel-header">
                        <h3 class="panel-title">Doctor List (<?= count($doctors) ?>)</h3>

                        <!-- SEARCH FORM -->
                        <form method="GET" action="admin.php" class="search-bar">
                            <input
                                type="text"
                                name="keyword"
                                maxlength="100"
                                class="form-control"
                                placeholder="Search by doctor name or email..."
                                value="<?= e($keyword) ?>"
                            >
                            <button type="submit" class="btn-mc btn-mc-teal">Search</button>
                            <?php if ($keyword !== ''): ?>
                                <a href="admin.php" class="btn-mc btn-mc-outline-gray">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- DOCTOR TABLE -->
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Doctor</th>
                                    <th>Email / Phone</th>
                                    <th>Specialization</th>
                                    <th>Degree</th>
                                    <th>Experience</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($loadError === '' && !empty($doctors)): ?>
                                <?php foreach ($doctors as $doctor): ?>
                                    <?php
                                        $status = $doctor['approval_status'] ?? '';
                                        $statusClass = in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)
                                            ? strtolower($status)
                                            : 'unknown';
                                    ?>
                                    <tr>
                                        <td><strong>#<?= (int)$doctor['doctor_id'] ?></strong></td>
                                        <td><strong><?= e($doctor['full_name']) ?></strong></td>
                                        <td>
                                            <div><?= e($doctor['email']) ?></div>
                                            <span class="text-sub"><?= e($doctor['phone_number'] ?? '') ?></span>
                                        </td>
                                        <td><?= e($doctor['specialization_name'] ?? 'N/A') ?></td>
                                        <td><?= e($doctor['degree'] ?? 'N/A') ?></td>
                                        <td>
                                            <?= $doctor['experience_years'] !== null
                                                ? (int)$doctor['experience_years'] . ' years'
                                                : 'N/A' ?>
                                        </td>
                                        <td><?= e($doctor['city_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span
                                                class="badge-pill"
                                                style="<?=
                                                    $statusClass === 'approved'
                                                        ? 'background:#dcfce7;color:#166534;'
                                                        : ($statusClass === 'rejected'
                                                            ? 'background:#fee2e2;color:#991b1b;'
                                                            : ($statusClass === 'pending'
                                                                ? 'background:#fef3c7;color:#92400e;'
                                                                : 'background:#e2e8f0;color:#475569;'))
                                                ?>"
                                            >
                                                <?= e($status !== '' ? $status : 'UNKNOWN') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons-group">
                                                <!-- VIEW PROFILE -->
                                                <a class="btn-mc btn-mc-outline-gray" href="profile.php?id=<?= (int)$doctor['doctor_id'] ?>">
                                                    View
                                                </a>

                                                <!-- EDIT DOCTOR -->
                                                <a class="btn-mc btn-mc-teal" href="edit.php?id=<?= (int)$doctor['doctor_id'] ?>">
                                                    Edit
                                                </a>

                                                <!-- APPROVE / REJECT -->
                                                <?php if ($status === 'PENDING'): ?>
                                                    <form action="actions/approval_process.php" method="POST" style="display:inline-block; margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$doctor['doctor_id'] ?>">
                                                        <input type="hidden" name="status" value="APPROVED">
                                                        <button type="submit" class="btn-mc btn-mc-teal">Approve</button>
                                                    </form>

                                                    <form action="actions/approval_process.php" method="POST" style="display:inline-block; margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$doctor['doctor_id'] ?>">
                                                        <input type="hidden" name="status" value="REJECTED">
                                                        <button type="submit" class="btn-mc btn-mc-danger">Reject</button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- DELETE DOCTOR -->
                                                <form
                                                    action="actions/delete_process.php"
                                                    method="POST"
                                                    style="display:inline-block; margin:0;"
                                                    onsubmit="return confirm('Are you sure you want to delete this doctor?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="doctor_id" value="<?= (int)$doctor['doctor_id'] ?>">
                                                    <button type="submit" class="btn-mc btn-mc-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($loadError === ''): ?>
                                <tr>
                                    <td colspan="9" class="empty-cell">No doctors found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- BACK TO PUBLIC DOCTOR LIST -->
                <div style="margin-top: 20px;">
                    <a href="index.php" class="btn-mc btn-mc-outline-gray">
                        &larr; View Public Doctor List
                    </a>
                </div>
            </main>
        </div>

        <!-- FOOTER -->
        <footer class="admin-footer">
            <div>&copy; <?= date('Y') ?> MediConnect. All rights reserved.</div>
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