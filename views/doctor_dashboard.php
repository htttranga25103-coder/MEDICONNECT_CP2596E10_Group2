
<?php
session_start();
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_name'] ?? '') !== 'DOCTOR') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../models/doctor_dashboard_class.php';

$doctor_id = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctor_id <= 0) {
    $connTmp = Connection::Connectdb();
    $stmtTmp = $connTmp->prepare("SELECT doctor_id FROM doctors WHERE user_id = ? LIMIT 1");
    $uid = (int)$_SESSION['user_id'];
    $stmtTmp->bind_param("i", $uid);
    $stmtTmp->execute();
    $rowTmp = $stmtTmp->get_result()->fetch_assoc();
    $doctor_id = (int)($rowTmp['doctor_id'] ?? 0);
    $_SESSION['doctor_id'] = $doctor_id;
    $connTmp->close();
}

$flashMessage = '';
$flashType = 'success';
$appointmentIdPost = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $appointmentIdPost = (int)($_POST['appointment_id'] ?? 0);
    $newStatusPost = strtoupper(trim((string)($_POST['status'] ?? '')));

    if (method_exists('DoctorDashboard', 'updateAppointmentStatus') && DoctorDashboard::updateAppointmentStatus($appointmentIdPost, $doctor_id, $newStatusPost)) {
        $flashMessage = "Đã cập nhật trạng thái lịch hẹn #{$appointmentIdPost} thành {$newStatusPost}.";
        $flashType = 'success';
    } else {
        $flashMessage = "Không thể cập nhật trạng thái lịch hẹn #{$appointmentIdPost}.";
        $flashType = 'error';
    }
}

$doctorInfo = DoctorDashboard::getDoctorInfo($doctor_id);
$allAppointments = DoctorDashboard::getDoctorAppointments($doctor_id) ?: [];
$appointments = array_values(array_filter($allAppointments, function ($a) {
    return strtoupper(trim((string)($a['status'] ?? ''))) !== 'CANCELLED';
}));

// Đưa lịch vừa cập nhật thành công lên đầu để dễ nhìn thấy ngay
if ($flashType === 'success' && $appointmentIdPost > 0) {
    usort($appointments, function ($a, $b) use ($appointmentIdPost) {
        $aMatch = ((int)($a['appointment_id'] ?? 0) === $appointmentIdPost) ? 0 : 1;
        $bMatch = ((int)($b['appointment_id'] ?? 0) === $appointmentIdPost) ? 0 : 1;
        return $aMatch <=> $bMatch;
    });
}

$pendingAppointments = [];
foreach ($appointments as $item) {
    if (strtoupper(trim((string)($item['status'] ?? ''))) === 'PENDING') {
        $pendingAppointments[] = $item;
    }
}
if (!function_exists('buildPageUrl')) {
    function buildPageUrl(array $overrides): string {
        $params = $_GET;
        foreach ($overrides as $k => $v) {
            $params[$k] = $v;
        }
        return '?' . http_build_query($params);
    }
}

// Phân trang cho Appointments & intake
$intakePerPage = 6;
$totalIntake = count($appointments);
$totalIntakePages = max(1, (int)ceil($totalIntake / $intakePerPage));
$intakePage = max(1, (int)($_GET['intake_page'] ?? 1));
if ($intakePage > $totalIntakePages) {
    $intakePage = $totalIntakePages;
}
$pagedAppointments = array_slice($appointments, ($intakePage - 1) * $intakePerPage, $intakePerPage);

// Phân trang cho Pending Appointment Requests
$pendingPerPage = 5;
$totalPending = count($pendingAppointments);
$totalPendingPages = max(1, (int)ceil($totalPending / $pendingPerPage));
$pendingPage = max(1, (int)($_GET['pending_page'] ?? 1));
if ($pendingPage > $totalPendingPages) {
    $pendingPage = $totalPendingPages;
}
$pagedPendingAppointments = array_slice($pendingAppointments, ($pendingPage - 1) * $pendingPerPage, $pendingPerPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Portal - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=2">
    <link rel="stylesheet" href="../assets/css/article_style.css?v=2">
    <link rel="stylesheet" href="../assets/css/home_style.css?v=5">
    <style>
        .pagination-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            font-size: 14px;
        }
        .pagination-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .pagination-btn {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            color: #1f2937;
            background: #ffffff;
            font-weight: 600;
        }
        .pagination-btn.active {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <nav class="patient-navbar">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo">MediConnect</a>

            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="doctor_dashboard.php" class="active">Doctor Portal</a></li>
                <li><a href="../doctor/manage_schedules.php" class="nav-link">Manage Schedules</a></li>
                <li><a href="../chat.php">Consultation Chat</a></li>
                <li><a href="../articles/news.php">Articles</a></li>
                <li><a href="../reviews/index.php">Reviews</a></li>
            </ul>

            <div class="nav-user">
                <div class="user-dropdown">
                    <a href="../auth/profile.php" class="user-trigger">
                        <span>Hello, <strong><?= htmlspecialchars($doctorInfo['full_name'] ?? ($_SESSION['full_name'] ?? 'Doctor')) ?></strong> &#9662;</span>
                    </a>
                    <ul class="user-dropdown-menu">
                        <li><a href="../auth/profile.php">My Profile</a></li>
                    </ul>
                </div>
                <a href="../auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </div>
        </div>
    </nav>

    <section class="home-hero">
        <div class="home-hero-inner">
            <div class="home-hero-text">
                <p style="margin-bottom: 6px; font-weight: 600;">Welcome back</p>
                <h1><?= htmlspecialchars($doctorInfo['full_name'] ?? ($_SESSION['full_name'] ?? 'Doctor')) ?></h1>
                <span class="specialty-pill"><?= htmlspecialchars($doctorInfo['specialty_name'] ?? 'Specialist') ?></span>
                <p style="margin-bottom: 0;">Your workspace is ready to manage patients and appointment requests.</p>
            </div>
        </div>
    </section>

    <main class="main-wrapper">
        <?php if ($flashMessage !== ''): ?>
            <div class="flash-box <?= $flashType === 'success' ? 'flash-success' : 'flash-error' ?>">
                <?= htmlspecialchars($flashMessage) ?>
            </div>
        <?php endif; ?>

        <!-- 1. APPOINTMENTS & INTAKE -->
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Appointments &amp; intake (<?= $totalIntake ?>)</h2>
                    <p class="section-subtitle">Approve or decline patient appointment requests, or start medical examination.</p>
                </div>
            </div>

            <?php if (!empty($pagedAppointments)): ?>
                <div class="appointments-grid">
                    <?php foreach ($pagedAppointments as $app): ?>
                        <?php
                        $appId = (int)$app['appointment_id'];
                        $timeSource = $app['scheduled_time'] ?? ($app['booking_time'] ?? '');
                        $formattedTime = !empty($timeSource)
                            ? date('d M Y - H:i:s', strtotime($timeSource))
                            : 'Undefined';
                        $patientUserId = (int)($app['patient_user_id'] ?? ($app['user_id'] ?? 0));
                        ?>
                        <article class="appointment-card">
                            <div class="patient-info">
                                <span class="patient-name">#<?= $appId ?> &mdash; <?= htmlspecialchars($app['patient_name'] ?? 'Patient') ?></span>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <?php if ($patientUserId > 0): ?>
                                        <a href="../chat.php?partner_id=<?= $patientUserId ?>" class="btn-chat-link">💬 Chat</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-start" onclick="location.href='medical_examination.php?appointment_id=<?= $appId ?>'">
                                        Start Consultation
                                    </button>
                                </div>
                            </div>

                            <div class="phone-info">
                                <span>Phone: </span>
                                <strong><?= htmlspecialchars($app['patient_phone'] ?? 'Not yet updated') ?></strong>
                            </div>

                            <div class="appointment-time">
                                <div class="time-label">
                                    <span>🕒 Appointment (<?= htmlspecialchars($app['status'] ?? 'PENDING') ?>)</span>
                                </div>
                                <p class="date-time"><strong><?= $formattedTime ?></strong></p>
                            </div>

                            <?php if (in_array(strtoupper((string)$app['status']), ['PENDING', 'CONFIRMED'], true)): ?>
                                <form method="POST" style="margin-top: auto;" onsubmit="return confirm('Bạn có chắc muốn hủy lịch hẹn này?');">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?= $appId ?>">
                                    <input type="hidden" name="status" value="CANCELLED">
                                    <button type="submit" class="btn-cancel">Cancel appointment</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalIntakePages > 1): ?>
                    <div class="pagination-bar">
                        <span>Trang <?= $intakePage ?> / <?= $totalIntakePages ?></span>
                        <div class="pagination-list">
                            <?php if ($intakePage > 1): ?>
                                <a class="pagination-btn" href="<?= htmlspecialchars(buildPageUrl(['intake_page' => $intakePage - 1])) ?>">←Back</a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalIntakePages; $p++): ?>
                                <a class="pagination-btn <?= $p === $intakePage ? 'active' : '' ?>"
                                   href="<?= htmlspecialchars(buildPageUrl(['intake_page' => $p])) ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($intakePage < $totalIntakePages): ?>
                                <a class="pagination-btn" href="<?= htmlspecialchars(buildPageUrl(['intake_page' => $intakePage + 1])) ?>">Next→</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="article-card article-card-empty">
                    Hiện tại bác sĩ chưa có lịch hẹn nào.
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. PENDING APPOINTMENT REQUESTS -->
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Pending Appointment Requests (<?= $totalPending ?>)</h2>
                    <p class="section-subtitle">Review patient details and symptoms from database to approve or decline scheduled bookings.</p>
                </div>
            </div>

            <?php if (!empty($pagedPendingAppointments)): ?>
                <?php foreach ($pagedPendingAppointments as $req): ?>
                    <?php
                    $reqId = (int)$req['appointment_id'];
                    $reqTimeSource = $req['scheduled_time'] ?? ($req['booking_time'] ?? '');
                    $scheduledTime = !empty($reqTimeSource)
                        ? date('d M Y - H:i:s', strtotime($reqTimeSource))
                        : 'Chưa xác định';
                    $genderVal = trim((string)($req['gender'] ?? ''));
                    ?>
                    <div class="pending-request-card">
                        <div class="pending-request-header">
                            <h3>Request #<?= $reqId ?> &mdash; Scheduled Time: <?= htmlspecialchars($scheduledTime) ?></h3>
                            <span class="article-category-tag">PENDING</span>
                        </div>

                        <div class="pending-grid">
                            <div class="pending-col">
                                <h4>👤 Patient Information</h4>
                                <p><strong>Full Name:</strong> <?= htmlspecialchars($req['patient_name'] ?? 'Chưa cập nhật') ?></p>
                                <p><strong>Phone Number:</strong> <?= htmlspecialchars($req['patient_phone'] ?? 'Chưa cập nhật') ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($req['patient_email'] ?? 'Chưa cập nhật') ?></p>
                                <p><strong>Date of Birth:</strong> <?= htmlspecialchars($req['date_of_birth'] ?? 'Chưa cập nhật') ?></p>
                                <p><strong>Gender:</strong> <?= $genderVal !== '' ? htmlspecialchars($genderVal) : 'Not yet updated' ?></p>
                                <p><strong>Address:</strong> <?= htmlspecialchars($req['address'] ?? 'Chưa cập nhật') ?></p>
                            </div>

                            <div class="pending-col">
                                <h4>🩺 Symptoms &amp; Booking Details</h4>
                                <p><strong>Schedule ID:</strong> #<?= htmlspecialchars((string)($req['schedule_id'] ?? 'N/A')) ?></p>
                                <?php if (!empty($req['symptoms_note'])): ?>
                                    <p><strong>Symptoms Note:</strong> <?= htmlspecialchars($req['symptoms_note']) ?></p>
                                <?php endif; ?>
                                <p><strong>Status:</strong> <span style="color:#d97706; font-weight:bold;"><?= htmlspecialchars($req['status'] ?? 'PENDING') ?></span></p>
                            </div>
                        </div>

                        <div class="pending-actions">
                            <form method="POST" onsubmit="return confirm('Decline an appointment #<?= $reqId ?>?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="appointment_id" value="<?= $reqId ?>">
                                <input type="hidden" name="status" value="CANCELLED">
                                <button type="submit" class="btn-decline">Decline</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="appointment_id" value="<?= $reqId ?>">
                                <input type="hidden" name="status" value="CONFIRMED">
                                <button type="submit" class="btn-approve">Approve Appointment</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalPendingPages > 1): ?>
                    <div class="pagination-bar">
                        <span>Trang <?= $pendingPage ?> / <?= $totalPendingPages ?></span>
                        <div class="pagination-list">
                            <?php if ($pendingPage > 1): ?>
                                <a class="pagination-btn" href="<?= htmlspecialchars(buildPageUrl(['pending_page' => $pendingPage - 1])) ?>">← Trước</a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPendingPages; $p++): ?>
                                <a class="pagination-btn <?= $p === $pendingPage ? 'active' : '' ?>"
                                   href="<?= htmlspecialchars(buildPageUrl(['pending_page' => $p])) ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($pendingPage < $totalPendingPages): ?>
                                <a class="pagination-btn" href="<?= htmlspecialchars(buildPageUrl(['pending_page' => $pendingPage + 1])) ?>">Sau →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="article-card article-card-empty">
                    There are currently no appointment requests awaiting approval (PENDING).
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <span>&copy; 2026 MediConnect. Clinic management &amp; appointment booking. All rights reserved.</span>
            <div>
                <a href="../index.php">Home</a>
                <a href="../articles/news.php">Medical News</a>
                <a href="../articles/manage.php">Author Portal</a>
            </div>
        </div>
    </footer>
</body>
</html>