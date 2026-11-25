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
    'CONFIRMED' => '#0284c7',
    'COMPLETED' => '#0f766e',
    'CANCELLED' => '#dc2626',
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
    body { font-family: Arial, Helvetica, sans-serif; font-size: 15px; background-color: #f8fafc; color: #1e293b; }

    .layout-container { display: flex; min-height: 100vh; }

    /* SHARED ADMIN SIDEBAR */
    .sidebar { width: 235px; background-color: #0f766e; flex-shrink: 0; padding-top: 18px; }
    .sidebar-logo { display: block; font-size: 24px; font-weight: 800; color: #ffffff; margin: 0 0 18px 20px; text-decoration: none; letter-spacing: -0.5px; }
    .nav-list { list-style: none; margin-top: 8px; }
    .nav-list li { margin: 2px 10px; border-radius: 8px; overflow: hidden; }
    .nav-list a { display: flex; align-items: center; gap: 11px; padding: 11px 14px; text-decoration: none; color: #ccfbf1; font-size: 14px; font-weight: 600; transition: background 0.15s ease; }
    .nav-list a:hover { background: rgba(255, 255, 255, 0.14); color: #ffffff; }
    .nav-list li.active { background: #ffffff; }
    .nav-list li.active a { color: #0f766e; font-weight: 700; }

    /* MAIN */
    .main-area { flex: 1; display: flex; flex-direction: column; padding: 20px 28px 0; min-width: 0; }
    .page-header {
      display: flex; justify-content: space-between; align-items: center;
      background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
      padding: 14px 20px; margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #0f172a;
    }

    /* STAT CARDS */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
    .stat-card, .stat-card1, .stat-card2 {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; color: white; border-radius: 12px;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }
    .stat-card  { background: linear-gradient(135deg, #0f766e, #115e59); }
    .stat-card1 { background: linear-gradient(135deg, #0284c7, #0369a1); }
    .stat-card2 { background: linear-gradient(135deg, #e11d48, #be123c); }
    .stat-left { display: flex; align-items: center; gap: 12px; font-weight: 600; }
    .stat-number { font-size: 26px; font-weight: 800; }

    /* PANELS */
    .panels-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 16px; margin-bottom: 20px; }
    .panel {
      background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
      padding: 18px; overflow: auto; max-height: 360px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    }
    .panel-title { font-size: 15.5px; font-weight: 700; color: #0f172a; margin-bottom: 14px; }

    /* TABLE */
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { border-bottom: 1px solid #f1f5f9; padding: 10px 8px; text-align: left; }
    th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b; }

    /* STATUS BADGE */
    .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #fff; }
    .badge-pending   { background: #d97706; }
    .badge-confirmed { background: #0284c7; }
    .badge-completed { background: #0f766e; }
    .badge-cancelled { background: #dc2626; }

    /* STATUS BARS */
    .bar-row { margin-bottom: 14px; }
    .bar-label { display: flex; justify-content: space-between; font-size: 13.5px; margin-bottom: 5px; }
    .bar-track { background: #e2e8f0; border-radius: 999px; height: 10px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 999px; }

    /* QUICK OVERVIEW */
    .quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .quick-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .quick-item .num { font-size: 22px; font-weight: 800; color: #0f766e; }
    .quick-item .lbl { font-size: 13px; color: #64748b; margin-top: 2px; }

    .empty { color: #64748b; font-size: 14px; padding: 10px 0; }

    footer {
      margin-top: auto; padding: 22px 0;
      border-top: 1px solid #e2e8f0;
      display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 13px;
    }

    @media (max-width: 960px) {
      .stats-grid, .panels-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="layout-container">

    <!-- SHARED SIDEBAR -->
    <?php
      $adminActivePage = 'admin_dashboard.php';
      require_once __DIR__ . '/../includes/admin_sidebar.php';
    ?>

    <!-- MAIN -->
    <main class="main-area">
      <div class="page-header">
        <span>Admin Dashboard — Welcome, <?= $e($_SESSION['full_name'] ?? 'Admin') ?></span>
      <a href="../auth/logout.php"
        class="btn-logout"
        onclick="return confirm('Are you sure you want to log out?');">
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
          <div class="panel-title">Appointments by status (<?= $totalAppointments ?>)</div>
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
          <div class="panel-title">Recent appointments</div>
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
          <div class="panel-title">Top doctors by appointments</div>
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
          <div class="panel-title">Quick overview</div>
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
        <small>&copy; 2026 MediConnect. All rights reserved.</small>
      </footer>
    </main>
  </div>
</body>
</html>