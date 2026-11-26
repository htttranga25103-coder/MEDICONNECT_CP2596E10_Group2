<?php
session_start();
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_name'] ?? '') !== 'DOCTOR') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/doctor_dashboard_class.php';
$doctor_id = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctor_id <= 0) {
    $connTmp = MyConnection::connectDatabase();
    $stmtTmp = $connTmp->prepare("SELECT doctor_id FROM doctors WHERE user_id = ? LIMIT 1");
    $uid = (int)$_SESSION['user_id'];
    $stmtTmp->bind_param("i", $uid);
    $stmtTmp->execute();
    $rowTmp = $stmtTmp->get_result()->fetch_assoc();
    $doctor_id = (int)($rowTmp['doctor_id'] ?? 0);
    $_SESSION['doctor_id'] = $doctor_id;
}

$flashMessage = '';
$flashType = 'success';
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
    return strtoupper(trim((string)($a['status'] ?? ''))) === 'CONFIRMED';
}));

$pendingAppointments = [];
foreach ($allAppointments as $item) {
    if (strtoupper(trim((string)($item['status'] ?? ''))) === 'PENDING') {
        $pendingAppointments[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Doctor Portal - MediConnect</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=2">
    <link rel="stylesheet" href="../assets/css/article_style.css?v=2">
    <link rel="stylesheet" href="../assets/css/home_style.css?v=5">
=======
    <title>MediConnect - Doctor Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f6f8;
            color: #333;
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            min-height: 100vh;
            padding: 0 40px 50px 40px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }

        .flash-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: bold;
        }
        .flash-success {
            background-color: #e8f5e9;
            color: #146c43;
            border: 1px solid #badbcc;
        }
        .flash-error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border-radius: 16px;
            padding: 32px 40px;
            color: white;
            margin-bottom: 36px;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.2);
        }
        .greeting {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .welcome-banner h2 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .welcome-banner .title {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .welcome-banner .description {
            font-size: 15px;
            opacity: 0.95;
        }
        .appointments-header {
            background-color: transparent;
            padding-bottom: 14px;
            border-bottom: 1px solid #eee;
            margin-bottom: 24px;
        }
        .appointments-header h2 {
            font-size: 26px;
            color: #111;
            margin-bottom: 6px;
        }
        .appointments-header p {
            color: #666;
            font-size: 15px;
        }
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
            margin-bottom: 40px;
        }
        @media (max-width: 900px) {
            .appointments-grid {
            grid-template-columns: 1fr;
            }
        }
        .appointment-card {
            border: 1px solid #e0e0e0;
            border-radius: 14px;
            padding: 28px 32px;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            min-width: 0;
        }
        .patient-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .patient-name {
            font-weight: bold;
            font-size: 22px;
            color: #111;
        }
        .btn-start {
            font-size: 15px;
            font-weight: bold;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 11px 20px;
            cursor: pointer;
        }
        .btn-start:hover {
            background-color: #0b5ed7;
        }
        .phone-info {
            font-size: 16px;
            color: #555;
        }
        .appointment-time {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #e8f5e9;
            color: #198754;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 16px;
        }
        .appointment-time .time-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
        }
        .reason-section {
            border-radius: 10px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 16px 18px;
            flex: 1;
        }
        .reason-section .label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .reason-section p {
            font-size: 16px;
            color: #333;
            line-height: 1.5;
        }
        .btn-cancel {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background-color: #0d6efd;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .btn-cancel:hover {
            background-color: #0b5ed7;
        }
        .pending-request-box {
            border: 1px solid #dcdcdc;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
            background-color: #fff;
        }
        .pending-request-box legend {
            padding: 0 10px;
            color: #198754;
        }
        .btn-decline {
            background-color: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-approve {
            background-color: #198754;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        footer {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #666;
        }
        .logo-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 20px;
        }
    </style>
>>>>>>> origin/home-admindashboard-doctordashboard
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
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Appointments &amp; intake (<?= count($appointments) ?>)</h2>
                    <p class="section-subtitle">Approve or decline patient appointment requests, or start medical examination.</p>
                </div>
            </div>

            <?php if (!empty($appointments)): ?>
                <div class="appointments-grid">
                    <?php foreach ($appointments as $app): ?>
                        <?php
<<<<<<< HEAD
                        $formattedTime = !empty($app['booking_time'])
                            ? date('d M Y - H:i:s', strtotime($app['booking_time']))
                            : 'Undefined';
                        $patientUserId = (int)($app['patient_user_id'] ?? ($app['user_id'] ?? 0));
=======
                        $formattedTime = 'Undetermined';
                        if (!empty($app['scheduled_time'])) {
                        $formattedTime = date('d M Y, H:i', strtotime($app['scheduled_time']));
                        if (!empty($app['schedule_end_time'])) {
                        $formattedTime .= ' – ' . date('H:i', strtotime($app['schedule_end_time']));
                            }
                        }
>>>>>>> origin/home-admindashboard-doctordashboard
                        ?>
                        <article class="appointment-card">
                            <div class="patient-info">
                                <span class="patient-name"><?= htmlspecialchars($app['patient_name'] ?? 'Patient') ?></span>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <?php if ($patientUserId > 0): ?>
                                        <a href="../chat.php?partner_id=<?= $patientUserId ?>" class="btn-chat-link">💬 Chat</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-start" onclick="location.href='medical_examination.php?appointment_id=<?= (int)$app['appointment_id'] ?>'">
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
                                    <input type="hidden" name="appointment_id" value="<?= (int)$app['appointment_id'] ?>">
                                    <input type="hidden" name="status" value="CANCELLED">
                                    <button type="submit" class="btn-cancel">Cancel appointment</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="article-card article-card-empty">
                    Hiện tại bác sĩ chưa có lịch hẹn nào.
                </div>
            <?php endif; ?>
        </section>
        <section class="home-section">
            <div class="section-bar">
                <div>
                    <h2>Pending Appointment Requests (<?= count($pendingAppointments) ?>)</h2>
                    <p class="section-subtitle">Review patient details and symptoms from database to approve or decline scheduled bookings.</p>
                </div>
            </div>

            <?php if (!empty($pendingAppointments)): ?>
                <?php foreach ($pendingAppointments as $req): ?>
                    <?php
                    $reqId = (int)$req['appointment_id'];
                    $scheduledTime = 'Undetermined';
                    if (!empty($req['scheduled_time'])) {
                    $scheduledTime = date('d M Y, H:i', strtotime($req['scheduled_time']));
                    if (!empty($req['schedule_end_time'])) {
                    $scheduledTime .= ' – ' . date('H:i', strtotime($req['schedule_end_time']));
                        }
                    }
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