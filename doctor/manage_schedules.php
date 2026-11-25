<?php
session_start();
require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/AppointmentManager.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'DOCTOR') {
    header("Location: ../auth/login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$apptManager = new AppointmentManager($conn);
$doctor_id = $apptManager->getDoctorIdByUserId($_SESSION['user_id']);

if (!$doctor_id) {
    die("Doctor profile not found. Please contact administrator.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_schedule') {
        $work_date  = trim($_POST['work_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time   = trim($_POST['end_time'] ?? '');

        $result = $apptManager->addDoctorSchedule($doctor_id, $work_date, $start_time, $end_time);
        $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];
    } elseif ($action === 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $result = $apptManager->deleteDoctorSchedule($doctor_id, $schedule_id);
        $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];
    }
    $redirectParams = array_filter([
        'work_date' => !empty($_POST['current_filter_date']) ? trim($_POST['current_filter_date']) : null,
        'page'      => !empty($_POST['current_page']) && (int)$_POST['current_page'] > 1 ? (int)$_POST['current_page'] : null,
    ]);
    $redirectUrl = "manage_schedules.php" . (!empty($redirectParams) ? "?" . http_build_query($redirectParams) : "");
    header("Location: " . $redirectUrl);
    exit();
}
$filterDate     = trim($_GET['work_date'] ?? '');
$limit          = 9; // 9 lịch mỗi trang (3 hàng x 3 cột)
$currentPage    = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$totalSchedules = $apptManager->countSchedulesByDoctor($doctor_id, $filterDate);
$totalPages     = max(1, (int)ceil($totalSchedules / $limit));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset    = ($currentPage - 1) * $limit;
$schedules = $apptManager->getPaginatedSchedulesByDoctor($doctor_id, $limit, $offset, $filterDate);

$doctorInfo = $apptManager->getDoctorById($doctor_id);
$doctorName = $doctorInfo['full_name'] ?? ($_SESSION['full_name'] ?? 'Doctor');
$specName   = $doctorInfo['specialization_name'] ?? 'Specialist';
function buildPageUrl(int $page, string $filterDate): string {
    $params = array_filter([
        'work_date' => $filterDate !== '' ? $filterDate : null,
        'page'      => $page > 1 ? $page : null,
    ]);
    return 'manage_schedules.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - MediConnect</title>
    <link href="https://googleapis.com" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/doctor_schedules.css">
</head>
<body>

    <header class="navbar">
        <a href="../index.php" class="brand">MediConnect</a>
        <nav class="nav-links">
            <a href="../index.php">Home</a>
            <a href="dashboard.php">Doctor Portal</a>
            <a href="manage_schedules.php" class="active">Manage Schedules</a>
            <a href="../chat.php">Consultation Chat</a>
            <a href="../articles/news.php">Articles</a>
            <a href="../reviews/index.php">Reviews</a>
        </nav>
        <div class="nav-user">
            <span class="btn-outline-sm">Hello, <?= htmlspecialchars($doctorName) ?> ▾</span>
            <a href="../auth/logout.php" class="btn-outline-sm">Logout</a>
        </div>
    </header>

    <section class="hero-banner">
        <div class="hero-container">
            <p class="hero-sub">Schedule Management</p>
            <h1 class="hero-title"><?= htmlspecialchars($doctorName) ?></h1>
            <span class="spec-badge"><?= htmlspecialchars($specName) ?></span>
            <p class="hero-desc">Set up your available time slots so patients can book medical consultations with you.</p>
        </div>
    </section>

    <main class="container">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="section-header">
            <div>
                <h2 class="section-title">Add New Available Slot</h2>
                <p class="section-subtitle">The system automatically prevents creating overlapping time slots on the same day.</p>
            </div>
        </div>

        <div class="card">
            <form method="POST" class="add-form-grid">
                <input type="hidden" name="action" value="add_schedule">
                <input type="hidden" name="current_filter_date" value="<?= htmlspecialchars($filterDate) ?>">
                <input type="hidden" name="current_page" value="<?= $currentPage ?>">

                <div class="form-group">
                    <label>Work Date</label>
                    <input type="date" name="work_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <select name="start_time" class="form-control" required>
                        <option value="">-- Select start --</option>
                        <?php for ($h = 7; $h <= 20; $h++): ?>
                            <?php foreach (['00', '30'] as $m): ?>
                                <?php $t = sprintf('%02d:%s:00', $h, $m); ?>
                                <option value="<?= $t ?>"><?= sprintf('%02d:%s', $h, $m) ?></option>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <select name="end_time" class="form-control" required>
                        <option value="">-- Select end --</option>
                        <?php for ($h = 7; $h <= 21; $h++): ?>
                            <?php foreach (['00', '30'] as $m): ?>
                                <?php $t = sprintf('%02d:%s:00', $h, $m); ?>
                                <option value="<?= $t ?>"><?= sprintf('%02d:%s', $h, $m) ?></option>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">+ Add Available Slot</button>
                </div>
            </form>
        </div>

        <div class="section-header section-header-spaced">
            <div>
                <h2 class="section-title">Your Schedules (<?= $totalSchedules ?>)</h2>
                <p class="section-subtitle">You can delete available slots that have not been booked by patients.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="work_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                <button type="submit" class="btn-primary btn-auto">Filter Date</button>
                <?php if ($filterDate): ?>
                    <a href="manage_schedules.php" class="btn-outline-sm btn-reset">All Dates</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($schedules)): ?>
            <div class="card card-empty">
                No upcoming schedules found. Add your available slots using the form above!
            </div>
        <?php else: ?>
            <div class="schedule-grid">
                <?php foreach ($schedules as $s): ?>
                    <?php
                        $isAvail = ((int)$s['is_available'] === 1);
                        $formattedDate = date('F d, Y', strtotime($s['work_date']));
                    ?>
                    <div class="slot-card">
                        <div>
                            <div class="slot-top">
                                <span class="slot-date"><?= $formattedDate ?></span>
                                <?php if ($isAvail): ?>
                                    <span class="badge-status badge-available">Available</span>
                                <?php else: ?>
                                    <span class="badge-status badge-booked">Booked</span>
                                <?php endif; ?>
                            </div>
                            <div class="slot-time">
                                Time Slot: <?= substr($s['start_time'], 0, 5) ?> - <?= substr($s['end_time'], 0, 5) ?>
                            </div>
                            <div class="slot-patient">
                                <?php if ($isAvail): ?>
                                    Status: Open for patient bookings
                                <?php else: ?>
                                    Patient: <strong><?= htmlspecialchars($s['patient_name'] ?? 'Booked') ?></strong> (<?= htmlspecialchars($s['appointment_status'] ?? 'PENDING') ?>)
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($isAvail): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this available slot?');">
                            <input type="hidden" name="action" value="delete_schedule">
                            <input type="hidden" name="schedule_id" value="<?= (int) $s['schedule_id'] ?>">
                            <input type="hidden" name="current_filter_date" value="<?= htmlspecialchars($filterDate) ?>">
                            <input type="hidden" name="current_page" value="<?= $currentPage ?>">
                            <button type="submit" class="btn-danger-outline">Delete Slot</button>
                        </form>
                        <?php else: ?>
                        <div class="slot-locked-note">Cannot delete (Already booked)</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
            </div>
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalSchedules ?> total slots)
                        </div>
                        <ul class="pagination">
                            <li>
                                <a href="<?= buildPageUrl(max(1, $currentPage - 1), $filterDate) ?>" 
                                   class="page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>">« Prev</a>
                            </li>

                            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                                <li>
                                    <a href="<?= buildPageUrl($p, $filterDate) ?>" 
                                       class="page-link <?= $p === $currentPage ? 'active' : '' ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <li>
                                <a href="<?= buildPageUrl(min($totalPages, $currentPage + 1), $filterDate) ?>" 
                                   class="page-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">Next »</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
    </main>

</body>
</html>