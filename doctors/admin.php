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

    <!-- Use the group's shared Admin CSS -->
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
    </style>

</head>

<body>

<?php $docNavRole = $_SESSION['role_name'] ?? ''; ?>
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
                    Doctor Management
                </h2>

                <p>
                    Manage doctor registration, profiles and approval.
                </p>

                <!-- SUCCESS MESSAGE -->
                <?php if ($message !== ''): ?>

                    <div class="alert-box alert-success">
                        <?= e($message) ?>
                    </div>

                <?php endif; ?>

                <!-- ERROR MESSAGE -->
                <?php if ($loadError !== ''): ?>

                    <div class="alert-box alert-error">
                        <?= e($loadError) ?>
                    </div>

                <?php endif; ?>

                <!-- DOCTOR MANAGEMENT PANEL -->
                <section class="panel-card">

                    <div class="panel-header">

                        <h3 class="panel-title">
                            Doctor List
                        </h3>

                    </div>

                    <!-- SEARCH FORM -->
                    <form
                        method="GET"
                        action="admin.php"
                        class="search-bar"
                    >

                        <input
                            type="text"
                            name="keyword"
                            maxlength="100"
                            class="form-control"
                            placeholder="Search by doctor name or email"
                            value="<?= e($keyword) ?>"
                        >

                        <button
                            type="submit"
                            class="btn-mc btn-mc-teal"
                        >
                            Search
                        </button>

                        <a
                            href="admin.php"
                            class="btn-mc btn-mc-outline-gray"
                        >
                            Reset
                        </a>

                    </form>

                    <!-- DOCTOR TABLE -->
                    <div class="table-responsive">

                        <table class="custom-table">

                            <thead>

                                <tr>
                                    <th>ID</th>
                                    <th>Doctor</th>
                                    <th>Email</th>
                                    <th>Phone</th>
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

                                    $statusClass = in_array(
                                        $status,
                                        ['PENDING', 'APPROVED', 'REJECTED'],
                                        true
                                    ) ? strtolower($status) : 'unknown';

                                    ?>

                                    <tr>

                                        <td>
                                            <?= (int)$doctor['doctor_id'] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($doctor['full_name']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($doctor['email']) ?>
                                        </td>

                                        <td>
                                            <?= e($doctor['phone_number']) ?>
                                        </td>

                                        <td>
                                            <?= e($doctor['specialization_name'] ?? 'N/A') ?>
                                        </td>

                                        <td>
                                            <?= e($doctor['degree'] ?? 'N/A') ?>
                                        </td>

                                        <td>
                                            <?= $doctor['experience_years'] !== null
                                                ? (int)$doctor['experience_years'] . ' years'
                                                : 'N/A' ?>
                                        </td>

                                        <td>
                                            <?= e($doctor['city_name'] ?? 'N/A') ?>
                                        </td>

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

                                            <!-- VIEW PROFILE -->
                                            <a
                                                class="btn-mc btn-mc-outline-gray"
                                                href="profile.php?id=<?= (int)$doctor['doctor_id'] ?>"
                                            >
                                                View
                                            </a>

                                            <!-- EDIT DOCTOR -->
                                            <a
                                                class="btn-mc btn-mc-teal"
                                                href="edit.php?id=<?= (int)$doctor['doctor_id'] ?>"
                                            >
                                                Edit
                                            </a>

                                            <!-- APPROVE / REJECT -->
                                            <?php if ($status === 'PENDING'): ?>

                                                <form
                                                    action="actions/approval_process.php"
                                                    method="POST"
                                                    style="display:inline-block;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="doctor_id"
                                                        value="<?= (int)$doctor['doctor_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="APPROVED"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn-mc btn-mc-teal"
                                                    >
                                                        Approve
                                                    </button>

                                                </form>

                                                <form
                                                    action="actions/approval_process.php"
                                                    method="POST"
                                                    style="display:inline-block;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="doctor_id"
                                                        value="<?= (int)$doctor['doctor_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="REJECTED"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn-mc btn-mc-danger"
                                                    >
                                                        Reject
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <!-- DELETE DOCTOR -->
                                            <form
                                                action="actions/delete_process.php"
                                                method="POST"
                                                style="display:inline-block;"
                                                onsubmit="return confirm('Are you sure you want to delete this doctor?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="doctor_id"
                                                    value="<?= (int)$doctor['doctor_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-mc btn-mc-danger"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php elseif ($loadError === ''): ?>

                                <tr>
                                    <td colspan="10">
                                        No doctors found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>

                <!-- BACK TO PUBLIC DOCTOR LIST -->
                <div style="margin-top:20px;">

                    <a
                        href="index.php"
                        class="btn-mc btn-mc-outline-gray"
                    >
                        &larr; Back to Public Doctor List
                    </a>

                </div>

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