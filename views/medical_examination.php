<?php
session_start();
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_name'] ?? '') !== 'DOCTOR') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/doctor_dashboard_class.php';

// Lấy đúng doctor_id theo user_id đang đăng nhập
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

$appointment_id = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

$profile = $appointment_id > 0 ? DoctorDashboard::getPatientProfileByAppointment($appointment_id) : null;

if (!$profile || (int)$profile['doctor_id'] !== (int)$doctor_id) {
    header('Location: doctor_dashboard.php');
    exit;
}

$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Hoàn tất khám
    if ($action === 'complete') {
        $diagnosisPost = trim((string)($_POST['diagnosis'] ?? ''));
        $notesPost = trim((string)($_POST['symptoms'] ?? ''));

        $medLines = [];
        $medNames = $_POST['med_name'] ?? [];
        $medNotes = $_POST['med_note'] ?? [];
        foreach ((array)$medNames as $i => $medName) {
            $medName = trim((string)$medName);
            $medNote = trim((string)($medNotes[$i] ?? ''));
            if ($medName === '') {
                continue;
            }
            $medLines[] = $medNote !== '' ? "$medName — $medNote" : $medName;
        }
        $treatmentPlanPost = implode("\n", $medLines);

        if ($diagnosisPost === '') {
            $flashMessage = 'Please enter a diagnosis before completing.';
            $flashType = 'error';
        } elseif (DoctorDashboard::saveMedicalRecord($appointment_id, $doctor_id, $diagnosisPost, $treatmentPlanPost, $notesPost)) {
            header('Location: doctor_dashboard.php');
            exit;
        } else {
            $flashMessage = 'Cannot be saved to the medical record.';
            $flashType = 'error';
        }
    }

    $profile = DoctorDashboard::getPatientProfileByAppointment($appointment_id);
}

$doctorInfo = DoctorDashboard::getDoctorInfo($doctor_id);
$history = DoctorDashboard::getPatientVisitHistory((int)$profile['patient_id']) ?: [];

// Phân trang cho Medical History
$perPage = 5;
$totalHistory = count($history);
$totalPages = max(1, (int)ceil($totalHistory / $perPage));
$currentPage = max(1, (int)($_GET['history_page'] ?? 1));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;
$pagedHistory = array_slice($history, $offset, $perPage);

$formDiagnosis = $_POST['diagnosis'] ?? '';
$formNotes = $_POST['symptoms'] ?? ($profile['symptoms_note'] ?? '');

$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

$patientCode = 'BN-' . date('Y', strtotime($profile['booking_time'] ?: 'now')) . '-' . str_pad((string)$profile['patient_id'], 4, '0', STR_PAD_LEFT);
$appointmentTime = !empty($profile['booking_time']) ? date('d M Y - H:i', strtotime($profile['booking_time'])) : 'Undetermined';
$otherVisits = array_filter($history, fn($h) => (int)$h['appointment_id'] !== $appointment_id);
$isNewVisit = empty($otherVisits);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Consultation Details</title>
    <style>
        body { background-color: lightgray; padding: 10px; font-size: 18px; font-family: Arial, sans-serif; }
        .name { background-color: white; }
        .reason-visit { background-color: #FEFCE8; }
        .personal, .address, .history, .note, .prescription {
            background-color: white; padding: 16px; border-radius: 14px;
        }
        input, select, textarea {
            padding: 10px 12px; font-size: 15px; border: 1px solid #ccc;
            border-radius: 4px; box-sizing: border-box; width: 100%;
        }
        input[readonly] { background-color: #f1f1f1; }
        .flash { padding: 12px 18px; border-radius: 10px; margin-bottom: 14px; font-size: 16px; font-weight: bold; }
        .flash-success { background: #e8f5e9; color: #146c43; border: 1px solid #badbcc; }
        .flash-error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .btn-back {
            display: inline-block; padding: 8px 14px; background: #4D6D7B; color: #fff;
            text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold; margin-right: 12px;
        }
        .pagination {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 10px; margin-top: 12px; font-size: 14px;
        }
        .pagination-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-link {
            display: inline-block; padding: 6px 11px; border: 1px solid #ccc; border-radius: 5px;
            text-decoration: none; color: #333; background: #fff; font-weight: bold;
        }
        .page-link.active {
            background: #198754; color: #fff; border-color: #198754;
        }
    </style>
</head>
<body>

    <header style="background-color: white">
        <table width="100%" border="1" cellpadding="10" cellspacing="0">
            <tr>
                <td align="left">
                    <a href="doctor_dashboard.php"><img src="../images/icons/mediconnect.png" alt="MediConnect"></a>
                </td>
                <td align="right">
                    <a href="doctor_dashboard.php" class="btn-back">← Back to Dashboard</a>
                    <span>● <strong>Dr. <?= $e($doctorInfo['full_name'] ?? 'Doctor') ?></strong> (<?= $e($doctorInfo['specialty_name'] ?? 'Specialist') ?>)</span>
                </td>
            </tr>
        </table>
    </header>

    <main>
        <?php if ($flashMessage !== ''): ?>
            <br>
            <div class="flash <?= $flashType === 'success' ? 'flash-success' : 'flash-error' ?>"><?= $e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="name">
            <table width="100%" border="1" cellpadding="15" cellspacing="0">
                <tr>
                    <td align="left">
                        <h2><?= $e($profile['full_name']) ?></h2>
                        <p>📞 <?= $e($profile['phone_number'] ?: 'Not yet updated') ?> | ✉️ <?= $e($profile['email'] ?: 'Not yet updated') ?></p>
                    </td>
                    <td width="420" align="left">
                        <table width="100%" border="0" cellpadding="3">
                            <tr>
                                <td>Patient ID:</td>
                                <td align="right"><strong><?= $e($patientCode) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Appointment Time:</td>
                                <td align="right"><strong><?= $e($appointmentTime) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Status:</td>
                                <td align="right"><strong><?= $e($profile['status']) ?></strong></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </section>
        <br/>

        <table width="100%" border="0" cellpadding="0" cellspacing="0">
            <tr valign="top">
                <!-- ================= LEFT COLUMN ================= -->
                <td width="63%">

                    <!-- 1. PERSONAL INFO -->
                    <div class="personal">
                        <h3>1. Personal &amp; Contact Information</h3>
                        <hr style="margin: 10px 0;">
                        <table width="100%" border="0" cellpadding="6">
                            <tr>
                                <td width="50%">
                                    <label><small><strong>FULL NAME</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['full_name']) ?>" readonly>
                                </td>
                                <td width="50%">
                                    <label><small><strong>PHONE NUMBER</strong></small></label><br>
                                    <input type="tel" value="<?= $e($profile['phone_number']) ?>" readonly>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><small><strong>EMAIL</strong></small></label><br>
                                    <input type="email" value="<?= $e($profile['email']) ?>" readonly>
                                </td>
                                <td>
                                    <label><small><strong>GENDER</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['gender_display']) ?>" readonly>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><small><strong>DATE OF BIRTH</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['date_of_birth'] ?: 'Not yet updated') ?>" readonly>
                                </td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                    <br>

                    <!-- 2. ADDRESS -->
                    <div class="address">
                        <h3>2. Address Details</h3>
                        <hr style="margin: 10px 0;">
                        <table width="100%" border="0" cellpadding="6">
                            <tr>
                                <td>
                                    <label><small><strong>ADDRESS</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['address'] ?: 'Not yet updated') ?>" readonly>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <br>

                    <!-- 3. MEDICAL HISTORY -->
                    <div class="history">
                        <h3>3. Medical History (<?= $totalHistory ?>)</h3>
                        <br>
                        <?php if (!empty($pagedHistory)): ?>
                            <?php foreach ($pagedHistory as $h): ?>
                                <table width="100%" border="1" cellpadding="10" cellspacing="0">
                                    <tr>
                                        <td>
                                            <table width="100%" border="0">
                                                <tr>
                                                    <td align="left"><mark><?= $e(date('d M Y - H:i', strtotime($h['created_at']))) ?></mark></td>
                                                    <td align="right"><small><?= (int)$h['appointment_id'] === $appointment_id ? '<mark>Current visit</mark> ' : '' ?>Dr. <?= $e($h['doctor_name']) ?></small></td>
                                                </tr>
                                            </table>
                                            <p style="margin-top:6px;"><strong>Diagnosis: <?= !empty($h['diagnosis']) ? $e($h['diagnosis']) : 'No diagnosis yet (' . $e($h['status']) . ')' ?></strong></p>
                                            <p><small><?= !empty($h['doctor_notes']) ? $e($h['doctor_notes']) : (!empty($h['symptoms_note']) ? $e($h['symptoms_note']) : 'No symptoms noted.') ?></small></p>
                                            <?php if (!empty($h['treatment_plan'])): ?>
                                                <table width="100%" border="1" cellpadding="5" cellspacing="0" style="margin-top:6px;">
                                                    <tr>
                                                        <td><small><strong>Prescribed Medication:</strong><br><?= nl2br($e($h['treatment_plan'])) ?></small></td>
                                                    </tr>
                                                </table>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                            <?php endforeach; ?>

                            <?php if ($totalPages > 1): ?>
                                <div class="pagination">
                                    <span>Page <?= $currentPage ?> / <?= $totalPages ?></span>
                                    <div class="pagination-links">
                                        <?php if ($currentPage > 1): ?>
                                            <a class="page-link" href="?appointment_id=<?= $appointment_id ?>&amp;history_page=<?= $currentPage - 1 ?>">← Prev</a>
                                        <?php endif; ?>

                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <a class="page-link <?= $p === $currentPage ? 'active' : '' ?>"
                                               href="?appointment_id=<?= $appointment_id ?>&amp;history_page=<?= $p ?>">
                                                <?= $p ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <a class="page-link" href="?appointment_id=<?= $appointment_id ?>&amp;history_page=<?= $currentPage + 1 ?>">Next →</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="color:#666;">The patient has no prior medical history.</p>
                        <?php endif; ?>
                    </div>
                </td>

                <td width="2%">&nbsp;</td>

                <!-- ================= RIGHT COLUMN ================= -->
                <td width="35%">
                    <form method="POST" id="consultForm">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">

                        <!-- CLINICAL NOTES & DIAGNOSIS -->
                        <div class="note">
                            <h3>📋 Clinical Notes &amp; Diagnosis</h3>
                            <hr style="margin: 10px 0;">
                            <p style="margin-bottom: 12px;">
                                <label for="symptoms"><small><strong>CURRENT CLINICAL SYMPTOMS</strong></small></label><br>
                                <textarea id="symptoms" name="symptoms" rows="4"><?= $e($formNotes) ?></textarea>
                            </p>
                            <p>
                                <label for="diagnosis"><small><strong>CONFIRMED DIAGNOSIS</strong></small></label><br>
                                <input type="text" id="diagnosis" name="diagnosis" value="<?= $e($formDiagnosis) ?>" placeholder="Enter diagnosis..." required>
                            </p>
                        </div>
                        <br>
                        <!-- PRESCRIPTION -->
                        <div class="prescription">
                            <div style="display:flex; justify-content: space-between; align-items:center;">
                                <strong>💊 Prescription</strong>
                                <button type="button" style="background-color: #198754; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer;" onclick="window.print()">Print</button>
                            </div>
                            <br>
                            <div align="right">
                                <button type="button" style="background-color:#198754; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer;" onclick="addMedication()">+ Add Medication</button>
                            </div>
                            <br>
                            <div id="medications"></div>
                        </div>
                        <br>

                        <div align="center">
                            <button type="submit" style="width:100%; background:#198754; color:#fff; border:none; padding:14px; border-radius:10px; font-size:16px; font-weight:bold; cursor:pointer;"
                                    onclick="return confirm('Confirm consultation completion and save medical record?');">
                                ✔️ Complete Consultation &amp; Save Record
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>
    </main>

    <footer style="margin-top:30px; padding:16px 0; border-top:1px solid #bbb; display:flex; justify-content:space-between; color:#555;">
        <p><strong>MediConnect</strong> — Clinic management &amp; appointment booking</p>
        <small>© 2026 Mediconnect. All rights reserved.</small>
    </footer>

    <script>
        function addMedication() {
            const wrap = document.getElementById('medications');
            const box = document.createElement('div');
            box.innerHTML = `
                <table width="100%" border="1" cellpadding="8" cellspacing="0">
                    <tr><td>
                        <table width="100%" border="0"><tr>
                            <td><input type="text" name="med_name[]" placeholder="Drug name, dosage"></td>
                            <td width="40" align="right"><button type="button" onclick="this.closest('div').remove()">✕</button></td>
                        </tr></table>
                        <br>
                        <input type="text" name="med_note[]" placeholder="User manual">
                    </td></tr>
                </table><br>`;
            wrap.appendChild(box);
        }
        addMedication();
    </script>
</body>
</html>