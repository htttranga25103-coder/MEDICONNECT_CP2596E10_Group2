<?php
session_start();
require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/AppointmentManager.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'PATIENT') {
    header("Location: ../auth/login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$apptManager = new AppointmentManager($conn);
$patient_id = $apptManager->getPatientIdByUserId($_SESSION['user_id']);

if (!$patient_id) {
    die("Patient profile not found. Please contact administrator.");
}
function resolveDoctorAvatar(?string $avatarUrl): string {
    $defaultAvatar = '../assets/uploads/avatars/.png';

    if (empty($avatarUrl)) {
        return $defaultAvatar;
    }

    $possiblePaths = [
        $avatarUrl,
        'assets/uploads/avatars/' . $avatarUrl,
        'uploads/avatars/' . $avatarUrl,
        'assets/images/avatars/' . $avatarUrl,
        'uploads/' . $avatarUrl,
    ];

    foreach ($possiblePaths as $relPath) {
        $relPath = ltrim($relPath, '/');
        if (file_exists(__DIR__ . '/../' . $relPath)) {
            return '../' . $relPath;
        }
    }

    return $defaultAvatar;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $result = $apptManager->bookAppointment($patient_id, $_POST);
    $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];

    if ($result['status']) {
        header("Location: my_appointments.php");
        exit();
    }

    $redirectParams = array_filter([
        'specialization_id' => !empty($_POST['specialization_id']) ? (int)$_POST['specialization_id'] : null,
        'city_id'           => !empty($_POST['city_id']) ? (int)$_POST['city_id'] : null,
        'keyword'           => !empty($_POST['keyword']) ? trim($_POST['keyword']) : null,
        'page'              => !empty($_POST['page']) && (int)$_POST['page'] > 1 ? (int)$_POST['page'] : null,
        'doctor_id'         => !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null,
    ]);

    $redirectUrl = "book_appointment.php" . (!empty($redirectParams) ? "?" . http_build_query($redirectParams) : "");
    header("Location: " . $redirectUrl);
    exit();
}

$filterSpec      = isset($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : 0;
$filterCity      = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
$filterKeyword   = trim($_GET['keyword'] ?? '');

$specializations = $apptManager->getSpecializations();
$cities          = $apptManager->getCities();
$limit        = 6;
$currentPage  = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$totalDoctors = $apptManager->countAvailableDoctors($filterSpec, $filterKeyword, $filterCity);
$totalPages   = max(1, (int)ceil($totalDoctors / $limit));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset  = ($currentPage - 1) * $limit;
$doctors = $apptManager->getAvailableDoctors($filterSpec, $filterKeyword, $limit, $offset, $filterCity);

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$filterDate       = trim($_GET['work_date'] ?? '');

$selectedDoctor = $selectedDoctorId > 0 ? $apptManager->getDoctorById($selectedDoctorId) : null;
$schedules      = $selectedDoctor ? $apptManager->getDoctorSchedules($selectedDoctorId, $filterDate) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Book a Doctor Appointment</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=5">
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
            <li><a href="book_appointment.php" class="active">Book Appointment</a></li>
            <li><a href="my_appointments.php">My Appointments</a></li>
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
            <span class="panel-title"> Available Doctors (<?= $totalDoctors ?>)</span>

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

                <?php if (!empty($cities)): ?>
                    <select name="city_id" class="form-select">
                        <option value="0">-- All Cities --</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= $c['city_id'] ?>" <?= $filterCity === (int)$c['city_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['city_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <input type="text" name="keyword" class="form-control" placeholder="Search doctor or hospital..." value="<?= htmlspecialchars($filterKeyword) ?>">
                <button type="submit" class="btn-mc btn-mc-teal">Filter</button>

                <?php if ($filterSpec > 0 || $filterCity > 0 || !empty($filterKeyword)): ?>
                    <a href="book_appointment.php" class="btn-mc btn-mc-outline-gray">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($doctors) > 0): ?>
            <div class="doctor-grid">
                <?php foreach ($doctors as $doc): ?>
                    <?php
                        $docLinkParams = array_filter([
                            'specialization_id' => $filterSpec ?: null,
                            'city_id'           => $filterCity ?: null,
                            'keyword'           => $filterKeyword ?: null,
                            'page'              => $currentPage > 1 ? $currentPage : null,
                            'doctor_id'         => $doc['doctor_id'],
                        ]);
                        $docAvatarSrc = resolveDoctorAvatar($doc['avatar_url'] ?? null);
                    ?>
                    <div class="doctor-card <?= ($selectedDoctorId === (int)$doc['doctor_id']) ? 'selected' : '' ?>">
                        <div>
                            <div class="doc-card-header">
                                <img src="<?= htmlspecialchars($docAvatarSrc) ?>"
                                     alt="Dr. <?= htmlspecialchars($doc['full_name']) ?>"
                                     class="doc-avatar">
                                <div>
                                    <div class="doc-name">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
                                    <span class="doc-spec"><?= htmlspecialchars($doc['specialization_name'] ?? 'General Specialist') ?></span>
                                </div>
                            </div>
                            <div class="doc-meta"><strong>Degree:</strong> <?= htmlspecialchars($doc['degree'] ?? 'N/A') ?></div>
                            <div class="doc-meta"><strong>Experience:</strong> <?= (int)($doc['experience_years'] ?? 0) ?> years</div>
                            <div class="doc-meta"><strong>Workplace:</strong> <?= htmlspecialchars($doc['work_place'] ?? 'N/A') ?> <?= !empty($doc['city_name']) ? '(' . htmlspecialchars($doc['city_name']) . ')' : '' ?></div>
                        </div>
                        <div class="doc-card-footer">
                            <a href="book_appointment.php?<?= http_build_query($docLinkParams) ?>#booking-section"
                               class="btn-mc btn-mc-teal w-100">
                                View Profile & Schedule
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $buildPageUrl = function(int $page) use ($filterSpec, $filterCity, $filterKeyword): string {
                    $params = array_filter([
                        'specialization_id' => $filterSpec ?: null,
                        'city_id'           => $filterCity ?: null,
                        'keyword'           => $filterKeyword ?: null,
                        'page'              => $page > 1 ? $page : null,
                    ]);
                    return 'book_appointment.php' . (!empty($params) ? '?' . http_build_query($params) : '');
                };
                ?>
                <div class="pagination-wrapper">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $buildPageUrl($currentPage - 1) ?>" class="page-link">« Prev</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?= $buildPageUrl($i) ?>"
                           class="page-link <?= ($i === $currentPage) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $buildPageUrl($currentPage + 1) ?>" class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="empty-cell">No approved doctors found matching your criteria.</p>
        <?php endif; ?>
    </div>

    <?php if ($selectedDoctor): ?>
        <?php
            $closeParams = array_filter([
                'specialization_id' => $filterSpec ?: null,
                'city_id'           => $filterCity ?: null,
                'keyword'           => $filterKeyword ?: null,
                'page'              => $currentPage > 1 ? $currentPage : null,
            ]);
            $closeUrl = 'book_appointment.php' . (!empty($closeParams) ? '?' . http_build_query($closeParams) : '');
            $selectedAvatarSrc = resolveDoctorAvatar($selectedDoctor['avatar_url'] ?? null);
        ?>
        <div class="panel-card" id="booking-section">
            <div class="panel-header">
                <span class="panel-title"> Doctor Profile & Available Schedule</span>
                <a href="<?= $closeUrl ?>" class="btn-mc btn-mc-outline-gray">Close</a>
            </div>

            <div class="profile-box">
                <div class="profile-header-flex">
                    <img src="<?= htmlspecialchars($selectedAvatarSrc) ?>"
                         alt="Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?>"
                         class="profile-avatar-lg">
                    <div>
                        <h3 class="profile-title">
                            Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?> — <?= htmlspecialchars($selectedDoctor['specialization_name'] ?? 'General Specialist') ?>
                        </h3>
                        <p class="profile-bio">
                            <?= !empty($selectedDoctor['bio']) ? nl2br(htmlspecialchars($selectedDoctor['bio'])) : 'Dedicated medical specialist committed to patient care.' ?>
                        </p>
                    </div>
                </div>
                <div class="profile-grid">
                    <div><strong>Qualifications:</strong> <?= htmlspecialchars($selectedDoctor['degree'] ?? 'N/A') ?></div>
                    <div><strong>Experience:</strong> <?= (int)($selectedDoctor['experience_years'] ?? 0) ?> years</div>
                    <div><strong>Workplace:</strong> <?= htmlspecialchars($selectedDoctor['work_place'] ?? 'N/A') ?></div>
                    <div><strong>Location:</strong> <?= htmlspecialchars($selectedDoctor['city_name'] ?? 'N/A') ?></div>
                </div>
            </div>

            <form method="GET" action="book_appointment.php#booking-section" class="date-filter-form">
                <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">
                <?php if ($filterSpec > 0): ?><input type="hidden" name="specialization_id" value="<?= $filterSpec ?>"><?php endif; ?>
                <?php if ($filterCity > 0): ?><input type="hidden" name="city_id" value="<?= $filterCity ?>"><?php endif; ?>
                <?php if (!empty($filterKeyword)): ?><input type="hidden" name="keyword" value="<?= htmlspecialchars($filterKeyword) ?>"><?php endif; ?>
                <?php if ($currentPage > 1): ?><input type="hidden" name="page" value="<?= $currentPage ?>"><?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Filter Schedule by Date:</label>
                    <input type="date" name="work_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <button type="submit" class="btn-mc btn-mc-teal">Check Date</button>
                <?php if (!empty($filterDate)): ?>
                    <?php
                        $showAllParams = array_filter([
                            'specialization_id' => $filterSpec ?: null,
                            'city_id'           => $filterCity ?: null,
                            'keyword'           => $filterKeyword ?: null,
                            'page'              => $currentPage > 1 ? $currentPage : null,
                            'doctor_id'         => $selectedDoctor['doctor_id'],
                        ]);
                    ?>
                    <a href="book_appointment.php?<?= http_build_query($showAllParams) ?>#booking-section" class="btn-mc btn-mc-outline-gray">Show All Upcoming</a>
                <?php endif; ?>
            </form>

            <form method="POST" action="book_appointment.php" id="bookForm">
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">
                <input type="hidden" name="schedule_id" id="selected_schedule_id" value="">
                <input type="hidden" name="specialization_id" value="<?= $filterSpec ?>">
                <input type="hidden" name="city_id" value="<?= $filterCity ?>">
                <input type="hidden" name="keyword" value="<?= htmlspecialchars($filterKeyword) ?>">
                <input type="hidden" name="page" value="<?= $currentPage ?>">

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