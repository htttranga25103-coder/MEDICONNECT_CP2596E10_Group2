<?php
session_start();
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_name'] ?? '') !== 'ADMIN') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../models/admin_dashboard_class.php';

$counts = AdminDashboard::getOverviewCounts();
$statusCounts = AdminDashboard::getAppointmentStatusCounts();
$recentAppointments = AdminDashboard::getRecentAppointments(8);
$topDoctors = AdminDashboard::getTopDoctors(5);

$totalAppointments = array_sum($statusCounts);
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$statusColors = [
    'PENDING'   => '#d97706',
    'CONFIRMED' => '#138BD1',
    'COMPLETED' => '#1D9E65',
    'CANCELLED' => '#DF2D3C',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediConnect - Admin Dashboard</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: sans-serif; font-size: 16px; background-color: rgb(209, 206, 206); }

    .layout-container { display: flex; min-height: 100vh; }

    /* SIDEBAR */
    .sidebar { width: 220px; background-color: #4D6D7B; flex-shrink: 0; position: relative; padding-top: 16px; }
    .sidebar-logo { display: block; font-size: 24px; font-weight: bold; color: white; margin: 0 0 14px 20px; text-decoration: none; }
    .nav-list { list-style: none; margin-top: 10px; }
    .nav-list a { display: flex; align-items: center; gap: 8px; padding: 12px 15px; text-decoration: none; color: white; font-size: 14px; }
    .nav-list a:hover { background: rgba(255,255,255,0.12); }
    .nav-list li.active { background: #eee; font-weight: bold; }
    .nav-list li.active a { color: #4D6D7B; }

    /* MAIN */
    .main-area { flex: 1; display: flex; flex-direction: column; padding: 15px 20px 0 30px; min-width: 0; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; font-size: 16px; font-weight: bold; }

    /* STAT CARDS */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .stat-card, .stat-card1, .stat-card2 {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 15px; color: white; border-radius: 8px;
    }
    .stat-card  { background-color: #1D9E65; }
    .stat-card1 { background-color: #138BD1; }
    .stat-card2 { background-color: #DF2D3C; }
    .stat-left { display: flex; align-items: center; gap: 10px; }
    .stat-number { font-size: 24px; font-weight: bold; }

    /* PANELS */
    .panels-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 15px; margin-bottom: 20px; }
    .panel {
      background: #fff; border: 1px solid #999; border-radius: 8px;
      padding: 14px; overflow: auto; max-height: 340px;
    }
    .panel-title { font-size: 15px; font-weight: bold; margin-bottom: 12px; }

    /* TABLE */
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border-bottom: 1px solid #ddd; padding: 8px 6px; text-align: left; }
    th { background: #f3f5f6; font-size: 12px; text-transform: uppercase; color: #555; }

    /* STATUS BADGE */
    .badge { display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; }
    .badge-pending   { background: #d97706; }
    .badge-confirmed { background: #138BD1; }
    .badge-completed { background: #1D9E65; }
    .badge-cancelled { background: #DF2D3C; }

    /* STATUS BARS */
    .bar-row { margin-bottom: 14px; }
    .bar-label { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px; }
    .bar-track { background: #e6e8ea; border-radius: 6px; height: 12px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 6px; }

    /* QUICK OVERVIEW */
    .quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .quick-item { background: #f3f5f6; border-radius: 8px; padding: 12px 14px; }
    .quick-item .num { font-size: 22px; font-weight: bold; color: #4D6D7B; }
    .quick-item .lbl { font-size: 13px; color: #555; }

    .empty { color: #777; font-size: 14px; padding: 10px 0; }

    footer {
      margin-top: auto; padding: 30px 0;
      border-top: 1px solid #bbb;
      display: flex; justify-content: space-between; align-items: center; color: #555;
    }
  </style>
</head>
<body>
  <div class="layout-container">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <a href="../index.php" class="sidebar-logo">MediConnect</a>
      <ul class="nav-list">
        <li class="active"><a href="admin_dashboard.php"><img src="../images/icons/overview.png" width="24" height="24" alt=""> Overview</a></li>
        <li><a href="../index.php"><img src="../images/icons/overview.png" width="24" height="24" alt=""> Home Page</a></li>
        <li><a href="../admin/manage_patients.php"><img src="../images/icons/patient.png" width="24" height="24" alt=""> Manage Patients</a></li>
        <li><a href="../doctors/admin.php"><img src="../images/icons/doctor.png" width="24" height="24" alt=""> Manage Doctors</a></li>
        <li><a href="../reviews/index.php"><img src="../images/icons/review.png" width="24" height="24" alt=""> Reviews Doctor</a></li>
        <li><a href="../articles/manage.php"><img src="../images/icons/new.png" width="24" height="24" alt=""> Manage Articles</a></li>
        <li><a href="../articles/news.php"><img src="../images/icons/comment.png" width="24" height="24" alt=""> Articles &amp; Comments</a></li>
        <li><a href="../locations/index.php"><img src="../images/icons/review.png" width="24" height="24" alt=""> Manage Locations</a></li>
      </ul>
    </aside>

    <!-- MAIN -->
    <main class="main-area">

      <div class="page-header">
        <span>Admin Dashboard — Welcome, <?= $e($_SESSION['full_name'] ?? 'Admin') ?></span>
        <a href="../auth/logout.php" onclick="return confirm('Are you sure you want to log out?');"
           style="display:inline-flex; align-items:center; justify-content:center; color: white; margin-right: 20px; background-color: #198754; padding: 7px 18px; text-decoration:none; border-radius: 6px; font-size:14px;">
           Logout
        </a>
      </div>

      <!-- 3 THẺ SỐ LIỆU TỪ DATABASE -->
      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-left">
            <span><img src="../images/icons/pending.png" width="30" height="30" alt=""></span>
            <span>Number of doctor accounts:</span>
          </div>
          <span class="stat-number"><?= number_format($counts['doctors']) ?></span>
        </div>

        <div class="stat-card1">
          <div class="stat-left">
            <span><img src="../images/icons/doctors.png" width="30" height="30" alt=""></span>
            <span>Number of patient accounts:</span>
          </div>
          <span class="stat-number"><?= number_format($counts['patients']) ?></span>
        </div>

        <div class="stat-card2">
          <div class="stat-left">
            <span><img src="../images/icons/patients.png" width="30" height="30" alt=""></span>
            <span>Number of articles:</span>
          </div>
          <span class="stat-number"><?= number_format($counts['articles']) ?></span>
        </div>
      </section>
      
      <section class="panels-grid">
        <div class="panel">
          <div class="panel-title">📊 Appointments by status (<?= $totalAppointments ?>)</div>
          <?php foreach ($statusCounts as $status => $total): ?>
            <?php $percent = $totalAppointments > 0 ? round($total / $totalAppointments * 100) : 0; ?>
            <div class="bar-row">
              <div class="bar-label">
                <span><?= $e(ucfirst(strtolower($status))) ?></span>
                <strong><?= $total ?> (<?= $percent ?>%)</strong>
              </div>
              <div class="bar-track">
                <div class="bar-fill" style="width: <?= $percent ?>%; background: <?= $statusColors[$status] ?? '#888' ?>;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="panel">
          <div class="panel-title">🕒 Recent appointments</div>
          <?php if (!empty($recentAppointments)): ?>
            <table>
              <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Time</th>
                <th>Status</th>
              </tr>
              <?php foreach ($recentAppointments as $a): ?>
                <tr>
                  <td>#<?= (int)$a['appointment_id'] ?></td>
                  <td><?= $e($a['patient_name']) ?></td>
                  <td><?= $e($a['doctor_name']) ?></td>
                  <td><?= $e(date('d M Y H:i', strtotime($a['booking_time']))) ?></td>
                  <td><span class="badge badge-<?= $e(strtolower($a['status'])) ?>"><?= $e($a['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php else: ?>
            <p class="empty">No appointments yet.</p>
          <?php endif; ?>
        </div>
      </section>
      
      <section class="panels-grid">
        <div class="panel">
          <div class="panel-title">🏆 Top doctors by appointments</div>
          <?php if (!empty($topDoctors)): ?>
            <table>
              <tr>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Appts</th>
              </tr>
              <?php foreach ($topDoctors as $d): ?>
                <tr>
                  <td><?= $e($d['full_name']) ?></td>
                  <td><?= $e($d['specialty_name'] ?: 'N/A') ?></td>
                  <td><strong><?= (int)$d['total_appointments'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php else: ?>
            <p class="empty">There are no doctors yet.</p>
          <?php endif; ?>
        </div>

        <div class="panel">
          <div class="panel-title">📌 Quick overview</div>
          <div class="quick-grid">
            <div class="quick-item"><div class="num"><?= number_format($counts['appointments']) ?></div><div class="lbl">Total appointments</div></div>
            <div class="quick-item"><div class="num"><?= number_format($counts['comments']) ?></div><div class="lbl">Article comments</div></div>
            <div class="quick-item"><div class="num"><?= number_format($counts['contact_messages']) ?></div><div class="lbl">Contact messages</div></div>
            <div class="quick-item"><div class="num"><?= number_format($counts['specializations']) ?></div><div class="lbl">Specializations</div></div>
            <div class="quick-item"><div class="num"><?= number_format($counts['cities']) ?></div><div class="lbl">Cities covered</div></div>
            <div class="quick-item"><div class="num"><?= number_format($statusCounts['PENDING']) ?></div><div class="lbl">Pending requests</div></div>
          </div>
        </div>
      </section>

      <!-- FOOTER -->
      <footer>
        <div>
          <strong>MediConnect</strong>
          <p>Clinic management &amp; appointment booking</p>
        </div>
        <small>© 2026 Mediconnect. All rights reserved.</small>
      </footer>
    </main>
  </div>
</body>
</html>