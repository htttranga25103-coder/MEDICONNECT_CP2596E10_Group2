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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $result = null;

    if ($action === 'book') {
        $result = $apptManager->bookAppointment($patient_id, $_POST);
    } elseif ($action === 'cancel') {
        $result = $apptManager->cancelAppointment((int)$_POST['appointment_id'], $patient_id);
    }

    if ($result) {
        $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];
    }

    $redirectUrl = "book_appointment.php";
    if (!empty($_POST['doctor_id'])) {
        $redirectUrl .= "?doctor_id=" . (int)$_POST['doctor_id'];
    }
    header("Location: " . $redirectUrl);
    exit();
}
$filterSpec      = isset($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : 0;
$filterKeyword   = trim($_GET['keyword'] ?? '');
$specializations = $apptManager->getSpecializations();
$doctors         = $apptManager->getAvailableDoctors($filterSpec, $filterKeyword);
$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$filterDate       = trim($_GET['work_date'] ?? '');

$selectedDoctor = $selectedDoctorId > 0 ? $apptManager->getDoctorById($selectedDoctorId) : null;
$schedules      = $selectedDoctor ? $apptManager->getDoctorSchedules($selectedDoctorId, $filterDate) : [];
$appointments = $apptManager->getPatientAppointments($patient_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Book a Doctor Appointment</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=2">
</head>
<body>
<nav class="patient-navbar">
    <div class="navbar-container">
        <a href="book_appointment.php" class="brand-logo">MediConnect</a>

        <ul class="nav-links">
            <li><a href="book_appointment.php" class="active">Book Appointment</a></li>
            <li><a href="../chat.php">Consultation Chat</a></li>
            <li><a href="profile.php">My Profile</a></li>
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
        <h1>Book Your Medical Appointment</h1>
        <p>Browse certified specialists, view doctor profiles, and schedule your visit in just a few clicks.</p>
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
            <span class="panel-title"> Available Doctors (<?= count($doctors) ?>)</span>

            <form method="GET" action="book_appointment.php" class="search-bar">
                <?php if (!empty($specializations)): ?>
                    <select name="specialization_id" class="form-select">
                        <option value="0">-- All Specializations --</option>
                        <?php foreach ($specializations as $sp): ?>
                            <option value="<?= $sp['specialization_id'] ?>" <?= $filterSpec === (int)$sp['specialization_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sp['specialization_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <input type="text" name="keyword" class="form-control" placeholder="Search doctor or hospital..." value="<?= htmlspecialchars($filterKeyword) ?>">
                <button type="submit" class="btn-mc btn-mc-teal">Filter</button>
                <?php if ($filterSpec > 0 || !empty($filterKeyword)): ?>
                    <a href="book_appointment.php" class="btn-mc btn-mc-outline-gray">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($doctors) > 0): ?>
            <div class="doctor-grid">
                <?php foreach ($doctors as $doc): ?>
                    <div class="doctor-card <?= ($selectedDoctorId === (int)$doc['doctor_id']) ? 'selected' : '' ?>">
                        <div>
                            <div class="doc-name">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
                            <span class="doc-spec"><?= htmlspecialchars($doc['specialization_name'] ?? 'General Specialist') ?></span>
                            <div class="doc-meta"><strong>Degree:</strong> <?= htmlspecialchars($doc['degree'] ?? 'N/A') ?></div>
                            <div class="doc-meta"><strong>Experience:</strong> <?= (int)($doc['experience_years'] ?? 0) ?> years</div>
                            <div class="doc-meta"><strong>Workplace:</strong> <?= htmlspecialchars($doc['work_place'] ?? 'N/A') ?> <?= !empty($doc['city_name']) ? '(' . htmlspecialchars($doc['city_name']) . ')' : '' ?></div>
                        </div>
                        <div class="doc-card-footer">
                            <a href="book_appointment.php?doctor_id=<?= $doc['doctor_id'] ?>#booking-section" 
                               class="btn-mc btn-mc-teal w-100">
                                View Profile & Schedule
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-cell">No approved doctors found matching your criteria.</p>
        <?php endif; ?>
    </div>
    <?php if ($selectedDoctor): ?>
        <div class="panel-card" id="booking-section">
            <div class="panel-header">
                <span class="panel-title"> Doctor Profile & Available Schedule</span>
                <a href="book_appointment.php" class="btn-mc btn-mc-outline-gray">Close</a>
            </div>

            <div class="profile-box">
                <h3 class="profile-title">
                    Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?> — <?= htmlspecialchars($selectedDoctor['specialization_name'] ?? 'General Specialist') ?>
                </h3>
                <p class="profile-bio">
                    <?= !empty($selectedDoctor['bio']) ? nl2br(htmlspecialchars($selectedDoctor['bio'])) : 'Dedicated medical specialist committed to patient care.' ?>
                </p>
                <div class="profile-grid">
                    <div><strong>Qualifications:</strong> <?= htmlspecialchars($selectedDoctor['degree'] ?? 'N/A') ?></div>
                    <div><strong>Experience:</strong> <?= (int)($selectedDoctor['experience_years'] ?? 0) ?> years</div>
                    <div><strong>Workplace:</strong> <?= htmlspecialchars($selectedDoctor['work_place'] ?? 'N/A') ?></div>
                    <div><strong>Location:</strong> <?= htmlspecialchars($selectedDoctor['city_name'] ?? 'N/A') ?></div>
                </div>
            </div>
            <form method="GET" action="book_appointment.php#booking-section" class="date-filter-form">
                <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">
                <div class="form-group">
                    <label class="form-label">Filter Schedule by Date:</label>
                    <input type="date" name="work_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <button type="submit" class="btn-mc btn-mc-teal">Check Date</button>
                <?php if (!empty($filterDate)): ?>
                    <a href="book_appointment.php?doctor_id=<?= $selectedDoctor['doctor_id'] ?>#booking-section" class="btn-mc btn-mc-outline-gray">Show All Upcoming</a>
                <?php endif; ?>
            </form>
            <form method="POST" action="book_appointment.php" id="bookForm">
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">
                <input type="hidden" name="schedule_id" id="selected_schedule_id" value="">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">
                        Click to Select an Available Shift <span class="slot-hint">(Green = Open, Gray = Booked)</span> <span class="req">*</span>
                    </label>

                    <?php if (count($schedules) > 0): ?>
                        <div class="slot-grid">
                            <?php foreach ($schedules as $sch): ?>
                                <?php
                                    $dateLabel = date('D, M d, Y', strtotime($sch['work_date']));
                                    $timeLabel = date('H:i', strtotime($sch['start_time'])) . ' - ' . date('H:i', strtotime($sch['end_time']));
                                ?>
                                <?php if ((int)$sch['is_available'] === 1): ?>
                                    <button type="button" class="slot-label slot-btn" 
                                            data-id="<?= $sch['schedule_id'] ?>" 
                                            onclick="selectSlot(this, <?= $sch['schedule_id'] ?>)">
                                        <div><?= $dateLabel ?></div>
                                        <div class="slot-time"><?= $timeLabel ?></div>
                                    </button>
                                <?php else: ?>
                                    <span class="slot-booked">
                                        <div><?= $dateLabel ?></div>
                                        <div class="slot-time"><?= $timeLabel ?> (Booked)</div>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-cell empty-schedule-box">
                            No upcoming schedules available for this doctor.
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (count($schedules) > 0): ?>
                    <div class="form-group">
                        <label class="form-label">Symptoms Note</label>
                        <input type="text" name="symptoms_note" class="form-control" placeholder="Describe your symptoms or reason for visit...">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-mc btn-mc-teal">✔ Confirm Appointment Booking</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
    <div class="panel-card">
        <div class="panel-header">
            <span class="panel-title"> My Appointment History (<?= count($appointments) ?>)</span>
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
                                        <form method="POST" action="book_appointment.php" style="display:inline;" 
                                              onsubmit="return confirm('Are you sure you want to cancel appointment #<?= $appt['appointment_id'] ?>?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?= $appt['appointment_id'] ?>">
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
                                You have no appointment history yet. Select a doctor above to book!
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

<script>
    function selectSlot(btn, scheduleId) {
        document.querySelectorAll('.slot-btn').forEach(el => {
            el.style.backgroundColor = '#ffffff';
            el.style.color = '#168077';
        });
        btn.style.backgroundColor = '#168077';
        btn.style.color = '#ffffff';
        document.getElementById('selected_schedule_id').value = scheduleId;
    }
    const bookForm = document.getElementById('bookForm');
    if (bookForm) {
        bookForm.addEventListener('submit', function(e) {
            const scheduleId = document.getElementById('selected_schedule_id').value;
            if (!scheduleId) {
                e.preventDefault();
                alert('Please click on a green time slot first before confirming!');
            }
        });
    }
</script>
</body>
</html>