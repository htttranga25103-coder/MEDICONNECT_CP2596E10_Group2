<?php
session_start();
require_once __DIR__ . '/../config/MyConnection.php';
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_name'] ?? '') !== 'DOCTOR') {
    header("Location: ../auth/login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$user_id = (int)$_SESSION['user_id'];
$stmtDoc = $conn->prepare("SELECT d.doctor_id, d.degree, d.experience_years, s.name AS specialty
                           FROM doctors d
                           LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
                           WHERE d.user_id = ? LIMIT 1");
$stmtDoc->bind_param("i", $user_id);
$stmtDoc->execute();
$doctor = $stmtDoc->get_result()->fetch_assoc();
$appointments = [];
if ($doctor) {
    $doctor_id = (int)$doctor['doctor_id'];
    $sqlApp = "SELECT a.*, u.user_id AS patient_user_id, u.full_name AS patient_name, u.phone_number, u.email AS patient_email
               FROM appointments a
               JOIN patients p ON a.patient_id = p.patient_id
               JOIN users u ON p.user_id = u.user_id
               WHERE a.doctor_id = ?
               ORDER BY a.appointment_id DESC";
    $stmtApp = $conn->prepare($sqlApp);
    $stmtApp->bind_param("i", $doctor_id);
    $stmtApp->execute();
    $appointments = $stmtApp->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/patient_booking.css?v=2">
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
            <li><a href="dashboard.php" class="active">Doctor Portal</a></li>
            <li><a href="../chat.php">Consultation Chat</a></li>
            <li><a href="../articles/news.php">Articles</a></li>
            <li><a href="../reviews/index.php">Reviews</a></li>
        </ul>
        <div class="nav-user">
            <span>Dr. <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Doctor') ?></strong></span>
            <a href="../auth/logout.php" class="btn-logout-patient" onclick="return confirm('Log out?');">Logout</a>
        </div>
    </div>
</nav>

<main class="main-container">
    <div class="page-banner">
        <h2>Doctor Workspace</h2>
        <p>
            Specialty: <strong><?= htmlspecialchars($doctor['specialty'] ?? 'General Medicine') ?></strong>
            <?= !empty($doctor['degree']) ? ' (' . htmlspecialchars($doctor['degree']) . ')' : '' ?>
        </p>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span>My Patient Appointments (<?= count($appointments) ?>)</span>
            <a href="../chat.php" class="btn-primary-patient" style="width:auto; padding:8px 16px; text-decoration:none;">
                => Open Consultation Chat
            </a>
        </div>

        <div class="card-body">
            <?php if (count($appointments) > 0): ?>
                <div class="table-responsive">
                    <table class="booking-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient Name</th>
                                <th>Phone Number</th>
                                <th>Date / Schedule</th>
                                <th>Status</th>
                                <th>Consultation Chat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $app): ?>
                                <?php
                                    $dateVal = $app['appointment_date']
                                            ?? $app['scheduled_date']
                                            ?? $app['date']
                                            ?? $app['created_at']
                                            ?? 'N/A';
                                    $timeVal = $app['time_slot'] ?? $app['start_time'] ?? '';
                                ?>
                                <tr>
                                    <td>#<?= (int)$app['appointment_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($app['patient_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($app['phone_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(trim($dateVal . ' ' . $timeVal)) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($app['status'] ?? 'pending') ?>">
                                            <?= htmlspecialchars($app['status'] ?? 'PENDING') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../chat.php?partner_id=<?= $app['patient_user_id'] ?>"
                                           style="color:#168077; font-weight:700; text-decoration:none;">
                                             Chat with Patient →
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No appointments assigned to you yet.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>