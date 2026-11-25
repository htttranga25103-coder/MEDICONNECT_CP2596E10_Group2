<?php
session_start();
require_once __DIR__ . '/../models/doctor_dashboard_class.php';
$doctor_id = $_SESSION['doctor_id'] ?? 1;
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
    return strtoupper(trim((string)($a['status'] ?? ''))) !== 'CANCELLED';
}));
$pendingAppointments = [];
foreach ($appointments as $item) {
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
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <img src="../images/icons/mediconnect.png" alt="MediConnect Logo">
                 <button style="color: white; background-color: green; width: 80px; height: 30px; border: none; border-radius: 6px; cursor: pointer;" onclick="location.href='home.php'">Logout</button>
            </div>
        </header>
        
        <main>
            <?php if ($flashMessage !== ''): ?>
                <div class="flash-box <?= $flashType === 'success' ? 'flash-success' : 'flash-error' ?>">
                    <?= htmlspecialchars($flashMessage) ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner Section -->
            <section class="welcome-banner">
                <div class="greeting">
                    <span>Welcome back</span>
                </div>
                <h2><?= htmlspecialchars($doctorInfo['full_name'] ?? 'Doctor') ?></h2>
                <p class="title"><?= htmlspecialchars($doctorInfo['specialty_name'] ?? 'Specialist') ?></p>
                <p class="description">Your workspace is ready to manage patients.</p>
            </section>

            <!-- Main Title Section -->
            <section class="appointments-header">
                <h2>Appointments & intake (<?= count($appointments) ?>)</h2>
                <p>Approve or decline patient appointment requests.</p>
            </section>
           
            <div class="appointments-grid">
                <?php if (!empty($appointments)): ?>
                    <?php foreach ($appointments as $app): ?>
                        <?php
                        $formattedTime = 'Undetermined';
                        if (!empty($app['scheduled_time'])) {
                        $formattedTime = date('d M Y, H:i', strtotime($app['scheduled_time']));
                        if (!empty($app['schedule_end_time'])) {
                        $formattedTime .= ' – ' . date('H:i', strtotime($app['schedule_end_time']));
                            }
                        }
                        ?>
                        <article class="appointment-card">
                            <div class="patient-info">
                                <span class="patient-name"><?= htmlspecialchars($app['patient_name'] ?? 'Patient') ?></span>
                                <button type="button" class="btn-start" onclick="location.href='medical_examination.php?appointment_id=<?= (int)$app['appointment_id'] ?>'">
                                    Start Consultation
                                </button>
                            </div>
                            <div class="phone-info">
                                <span>Phone: </span>
                                <strong><?= htmlspecialchars($app['patient_phone'] ?? 'Not yet updated') ?></strong>
                            </div>

                            <div class="appointment-time">
                                <div class="time-label">
                                    <span class="icon">🕒</span>
                                    <span class="label">Appointment (<?= htmlspecialchars($app['status'] ?? 'PENDING') ?>)</span>
                                </div>
                                <p class="date-time"><strong><?= $formattedTime ?></strong></p>
                            </div>                         

                            <!-- <button type="button" class="btn-cancel" onclick="if(confirm('Bạn có chắc muốn hủy lịch hẹn này?')) location.href='cancel_appointment.php?id=<?= (int)$app['appointment_id'] ?>'">
                                <span class="icon" style="display: inline-block; filter: brightness(0) invert(1)">🩺</span>
                                Cancel appointment
                            </button> -->
                            <?php if (in_array(strtoupper((string)$app['status']), ['PENDING', 'CONFIRMED'], true)): ?>
                                <form method="POST" style="margin-top: auto;" onsubmit="return confirm('Bạn có chắc muốn hủy lịch hẹn này?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="appointment_id" value="<?= (int)$app['appointment_id'] ?>">
                                <input type="hidden" name="status" value="CANCELLED">
                                <button type="submit" class="btn-cancel">
                                <span class="icon" style="display: inline-block; filter: brightness(0) invert(1)">🩺</span>
                                Cancel appointment
                                </button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; padding: 20px 0;">Hiện tại bác sĩ chưa có lịch hẹn nào.</p>
                <?php endif; ?>
            </div>

            <!-- PENDING APPOINTMENT REQUESTS-->
            <section>
                <table width="100%" border="1" cellpadding="12" cellspacing="0">
                    <tr>
                        <td style="background-color:white">
                            <h2>Pending Appointment Requests (<?= count($pendingAppointments) ?>)</h2>
                            <p>Review patient details and symptoms from database to approve or decline scheduled bookings.</p>
                        </td>
                    </tr>
                </table>
            </section>

            <br>

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
                    <fieldset class="pending-request-box">
                        <legend>
                            <h3>Request #<?= $reqId ?> — Scheduled Time: <?= htmlspecialchars($scheduledTime) ?></h3>
                        </legend>

                        <table width="100%" border="0" cellpadding="8" cellspacing="0">
                            <tr valign="top">
                                <!-- LEFT: PATIENT PERSONAL INFO (Lấy trực tiếp từ DB) -->
                                <td width="45%">
                                    <table width="100%" border="0" cellpadding="10" cellspacing="0">
                                        <tr>
                                            <td style="background-color:white">
                                                <h4>👤 Patient Information</h4>
                                                <br>
                                                <p style="margin-bottom: 6px;"><strong>Full Name:</strong> <?= htmlspecialchars($req['patient_name'] ?? 'Chưa cập nhật') ?></p>
                                                <p style="margin-bottom: 6px;"><strong>Phone Number:</strong> <?= htmlspecialchars($req['patient_phone'] ?? 'Chưa cập nhật') ?></p>
                                                <p style="margin-bottom: 6px;"><strong>Email:</strong> <?= htmlspecialchars($req['patient_email'] ?? 'Chưa cập nhật') ?></p>
                                                <p style="margin-bottom: 6px;"><strong>Date of Birth:</strong> <?= htmlspecialchars($req['date_of_birth'] ?? 'Chưa cập nhật') ?></p>
                                                <p style="margin-bottom: 6px;">
                                                    <strong>Gender:</strong>
                                                    <?= $genderVal !== '' ? htmlspecialchars($genderVal) : 'Not yet updated' ?>
                                                </p>
                                                <p><strong>Address:</strong> <?= htmlspecialchars($req['address'] ?? 'Chưa cập nhật') ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>

                                <!-- RIGHT: SYMPTOMS & BOOKING DETAILS -->
                                <td width="55%">
                                    <table width="100%" border="0" cellpadding="10" cellspacing="0">
                                        <tr>
                                            <td style="background-color:white">
                                                <h4>🩺 Symptoms & Booking Details</h4>
                                                
                                                <p style="margin-bottom: 6px;"><strong>Schedule ID:</strong> #<?= htmlspecialchars((string)($req['schedule_id'] ?? 'N/A')) ?></p>
                                                <p><strong>Status:</strong> <span style="color:#d97706; font-weight:bold;"><?= htmlspecialchars($req['status'] ?? 'PENDING') ?></span></p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 14px 0;">

                        <!-- ACTION BUTTONS -->
                        <div align="right">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Decline an appointment #<?= $reqId ?>?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="appointment_id" value="<?= $reqId ?>">
                                <input type="hidden" name="status" value="CANCELLED">
                                <button type="submit" class="btn-decline">Decline</button>
                            </form>
                            &nbsp;&nbsp;
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="appointment_id" value="<?= $reqId ?>">
                                <input type="hidden" name="status" value="CONFIRMED">
                                <button type="submit" class="btn-approve">Approve Appointment</button>
                            </form>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; padding: 16px 0;">There are currently no appointment requests awaiting approval (PENDING).</p>
            <?php endif; ?>
        </main>

        <footer>
            <div>
                <strong>MediConnect</strong>
                <p>Clinic management & appointment booking</p>
            </div>
            <small>© 2026 Mediconnect. All rights reserved.</small>
        </footer>
    </div>
</body>
</html>
