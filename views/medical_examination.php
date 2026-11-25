<?php
session_start();
require_once __DIR__ . '/../models/doctor_dashboard_class.php';

$doctor_id = $_SESSION['doctor_id'] ?? 1;
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
$history = DoctorDashboard::getPatientVisitHistory((int)$profile['patient_id']);

$formDiagnosis = $_POST['diagnosis'] ?? '';
$formNotes = $_POST['symptoms'] ?? ($profile['symptoms_note'] ?? '');

$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

$patientCode = 'BN-' . date('Y', strtotime($profile['booking_time'] ?: 'now')) . '-' . str_pad((string)$profile['patient_id'], 4, '0', STR_PAD_LEFT);
$appointmentTime = !empty($profile['booking_time']) ? date('d M Y - H:i', strtotime($profile['booking_time'])) : 'Chưa xác định';
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
        body { background-color: lightgray; padding: 10px; font-size: 24px; }
        .name { background-color: white; }
        .reason-visit { background-color: #FEFCE8; }
        .personal, .address, .history, .note, .prescription {
            background-color: white; padding: 10px; border-radius: 20px;
        }
        input, select, textarea {
            padding: 10px 12px; font-size: 16px; border: 1px solid #ccc;
            border-radius: 4px; box-sizing: border-box;
        }
        input[readonly] { background-color: #f1f1f1; }
        .flash { padding: 12px 18px; border-radius: 10px; margin-bottom: 14px; font-size: 18px; font-weight: bold; }
        .flash-success { background: #e8f5e9; color: #146c43; border: 1px solid #badbcc; }
        .flash-error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }        
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
                    <td width="500" align="left">
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
                <td width="65%">

                    <!-- 1. PERSONAL INFO -->
                    <div class="personal">
                        <legend><h3>1. Personal & Contact Information</h3></legend>
                        <hr>
                        <table width="100%" border="0" cellpadding="6">
                            <tr>
                                <td width="50%">
                                    <label><small><strong>FULL NAME</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['full_name']) ?>" size="35" readonly>
                                </td>
                                <td width="50%">
                                    <label><small><strong>PHONE NUMBER</strong></small></label><br>
                                    <input type="tel" value="<?= $e($profile['phone_number']) ?>" size="35" readonly>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><small><strong>EMAIL</strong></small></label><br>
                                    <input type="email" value="<?= $e($profile['email']) ?>" size="35" readonly>
                                </td>
                                <td>
                                    <label><small><strong>GENDER</strong></small></label><br>
                                    <p><?= $e($profile['gender_display']) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><small><strong>DATE OF BIRTH</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['date_of_birth'] ?: 'Chưa cập nhật') ?>" size="35" readonly>
                                </td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                    <br>

                    <!-- 2. ADDRESS -->
                    <div class="address">
                        <legend><h3>2. Address Details</h3></legend>
                        <hr>
                        <table width="100%" border="0" cellpadding="6">
                            <tr>
                                <td>
                                    <label><small><strong>ADDRESS</strong></small></label><br>
                                    <input type="text" value="<?= $e($profile['address'] ?: 'Not yet updated') ?>" size="75" readonly>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <br>

                    <!-- 3. MEDICAL HISTORY -->
                    <div class="history">
                        <legend><h3>3. Medical History</h3></legend>
                        <br>
                        <?php if (!empty($history)): ?>
                            <?php foreach ($history as $h): ?>
                                <table width="100%" border="1" cellpadding="10" cellspacing="0">
                                    <tr>
                                        <td>
                                            <table width="100%" border="0">
                                                <tr>
                                                    <td align="left"><mark><?= $e(date('d M Y - H:i', strtotime($h['created_at']))) ?></mark></td>
                                                    <td align="right"><small><?= (int)$h['appointment_id'] === $appointment_id ? '<mark>Current visit</mark> ' : '' ?>Dr. <?= $e($h['doctor_name']) ?></small></td>
                                                </tr>
                                            </table>
                                            <p><strong>Diagnosis: <?= !empty($h['diagnosis']) ? $e($h['diagnosis']) : 'No diagnosis yet (' . $e($h['status']) . ')' ?></strong></p>
                                            <p><small><?= !empty($h['doctor_notes']) ? $e($h['doctor_notes']) : (!empty($h['symptoms_note']) ? $e($h['symptoms_note']) : 'No symptoms noted.') ?></small></p>
                                            <?php if (!empty($h['treatment_plan'])): ?>
                                                <table width="100%" border="1" cellpadding="5" cellspacing="0">
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
                        <?php else: ?>
                            <p style="color:#666;">The patient has no prior medical history.</p>
                        <?php endif; ?>
                    </div>
                </td>

                <td width="2%">&nbsp;</td>

                <!-- ================= RIGHT COLUMN ================= -->
                <td width="33%">
                    <form method="POST" id="consultForm">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">

                        <!-- CLINICAL NOTES & DIAGNOSIS -->
                        <div class="note">
                            <legend><h3>📋 Clinical Notes & Diagnosis</h3></legend>
                            <hr>
                            <p>
                                <label for="symptoms"><small><strong>CURRENT CLINICAL SYMPTOMS</strong></small></label><br>
                                <textarea id="symptoms" name="symptoms" rows="4" cols="38"><?= $e($formNotes) ?></textarea>
                            </p>
                            <p>
                                <label for="diagnosis"><small><strong>CONFIRMED DIAGNOSIS</strong></small></label><br>
                                <input type="text" id="diagnosis" name="diagnosis" size="38" value="<?= $e($formDiagnosis) ?>" placeholder="Enter diagnosis..." required>
                            </p>
                        </div>
                        <br>
                        <!-- PRESCRIPTION -->
                        <div class="prescription">
                            <div style="display:flex; justify-content: space-between">
                                <span>💊 Prescription</span>
                                <button type="button" style="background-color: green; border-radius:6px" onclick="window.print()">Print</button>
                            </div>
                            <br>
                            <div align="right">
                                <button type="button" style="background-color:green; border-radius:6px" onclick="addMedication()">+ Add Medication</button>
                            </div>
                            <br>
                            <div id="medications"></div>
                        </div>
                        <br>

                        <div align="center">
                            <button type="submit" onclick="return confirm('The examination has been completed and the record has been saved');">
                                <h3>✔️ Complete Consultation & Save Record</h3>
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>
    </main>

    <footer>
        <p>MediConnect</p>
        <p>Clinic management & appointment booking</p>
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
                            <td><input type="text" name="med_name[]" size="28" placeholder="Drug name, dosage"></td>
                            <td align="right"><button type="button" onclick="this.closest('div').remove()">✕</button></td>
                        </tr></table>
                        <br>
                        <input type="text" name="med_note[]" size="34" placeholder="User manual">
                    </td></tr>
                </table><br>`;
            wrap.appendChild(box);
        }
        addMedication();
    </script>
</body>
</html>
