<?php
session_start();
require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/AppointmentManager.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'PATIENT') {
    header("Location: ../login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$apptManager = new AppointmentManager($conn);
$patient_id = $apptManager->getPatientIdByUserId($_SESSION['user_id']);

if (!$patient_id) {
    die("Patient profile not found. Please contact administrator.");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $result = $apptManager->cancelAppointment((int)$_POST['appointment_id'], $patient_id);
    $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];

    $statusParam = !empty($_POST['status_filter']) ? '?status=' . urlencode($_POST['status_filter']) : '';
    header("Location: my_appointments.php" . $statusParam);
    exit();
}
$statusFilter    = strtoupper(trim($_GET['status'] ?? 'ALL'));
$allAppointments = $apptManager->getPatientAppointments($patient_id);

if ($statusFilter !== 'ALL' && in_array($statusFilter, ['PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED'])) {
    $appointments = array_filter($allAppointments, fn($a) => $a['status'] === $statusFilter);
} else {
    $statusFilter = 'ALL';
    $appointments = $allAppointments;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - My Appointments</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=3">
    <style>
        .navbar-container { gap: 16px; }
        .nav-links { display: flex; align-items: center; flex-wrap: nowrap; gap: 12px; list-style: none; margin: 0; padding: 0; }
        .nav-links > li { position: relative; white-space: nowrap; }
        .nav-links a { white-space: nowrap; }
    </style>
</head>
<body>
<nav class="patient-navbar">
    <div class="navbar-container">
        <a href="../index.php" class="brand-logo">MediConnect</a>

        <ul class="nav-links">
            <li><a href="../index.php">Home</a></li>
            <li><a href="book_appointment.php">Book Appointment</a></li>
            <li><a href="my_appointments.php" class="active">My Appointments</a></li>
            <li><a href="../chat.php">Consultation Chat</a></li>
            <li><a href="../articles/news.php">Articles</a></li>
            <li><a href="../reviews/index.php">Reviews</a></li>
        </ul>

        <div class="nav-user">
            <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Patient') ?></strong></span>
            <a href="../auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">
                Logout
            </a>
        </div>
    </div>
</nav>

<section class="hero-banner">
    <div class="hero-inner">
        <h1>My Appointment History</h1>
        <p>Track your upcoming visits, review past consultations, or cancel pending appointments.</p>
    </div>
</section>

<main class="main-container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-box alert-success">
            <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">✕</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-box alert-error">
            <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">✕</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="panel-card">
        <div class="panel-header">
            <span class="panel-title"> Appointments (<?= count($appointments) ?>)</span>

            <form method="GET" action="my_appointments.php" class="search-bar">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>-- All Statuses --</option>
                    <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    <option value="CONFIRMED" <?= $statusFilter === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="COMPLETED" <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
                    <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <a href="book_appointment.php" class="btn-mc btn-mc-teal">+ Book New</a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Work Date & Shift</th>
                        <th>Workplace</th>
                        <th>Symptoms Note</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $appt): ?>
                            <?php
                                $statusClass = 'badge-pending';
                                if ($appt['status'] === 'CONFIRMED') $statusClass = 'badge-confirmed';
                                elseif ($appt['status'] === 'COMPLETED') $statusClass = 'badge-completed';
                                elseif ($appt['status'] === 'CANCELLED') $statusClass = 'badge-cancelled';
                            ?>
                            <tr>
                                <td><strong>#<?= $appt['appointment_id'] ?></strong></td>
                                <td>
                                    <strong>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></strong>
                                    <span class="text-sub"><?= htmlspecialchars($appt['degree'] ?? '') ?></span>
                                </td>
                                <td><?= htmlspecialchars($appt['specialization_name'] ?? 'General') ?></td>
                                <td>
                                    <div><?= date('Y-m-d', strtotime($appt['work_date'])) ?></div>
                                    <span class="text-sub"><?= date('H:i', strtotime($appt['start_time'])) ?> - <?= date('H:i', strtotime($appt['end_time'])) ?></span>
                                </td>
                                <td><?= htmlspecialchars($appt['work_place'] ?? '---') ?></td>
                                <td style="max-width: 220px;">
                                    <?= !empty($appt['symptoms_note']) ? htmlspecialchars($appt['symptoms_note']) : '<span class="text-muted-italic">No notes</span>' ?>
                                </td>
                                <td>
                                    <span class="badge-pill <?= $statusClass ?>">
                                        <?= htmlspecialchars($appt['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appt['status'] === 'PENDING'): ?>
                                        <form method="POST" action="my_appointments.php" style="display:inline;"
                                              onsubmit="return confirm('Are you sure you want to cancel appointment #<?= $appt['appointment_id'] ?>?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?= $appt['appointment_id'] ?>">
                                            <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                                            <button type="submit" class="btn-mc btn-mc-danger">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted-italic">---</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-cell">
                                No appointments found. <a href="book_appointment.php">Book an appointment now!</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<footer class="patient-footer">
    © 2026 MediConnect Healthcare Portal. All rights reserved.
</footer>
</body>
</html>