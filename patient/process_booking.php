<?php
session_start();
require_once '../config/MyConnection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['patient_id'])) {
    $conn = MyConnection::connectDatabase();

    $user_id = $_SESSION['user_id'];
    $patient_id = $_SESSION['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $schedule_id = (int)$_POST['schedule_id'];
    $symptoms_note = trim($_POST['symptoms_note']);

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn->begin_transaction();
        $stmtCheck = $conn->prepare("SELECT doctor_id, work_date, start_time, is_available FROM doctor_schedules WHERE schedule_id = ? FOR UPDATE");
        $stmtCheck->bind_param("i", $schedule_id);
        $stmtCheck->execute();
        $schedule = $stmtCheck->get_result()->fetch_assoc();

        if (!$schedule || $schedule['is_available'] == 0 || $schedule['doctor_id'] != $doctor_id) {
            $conn->rollback();
            $_SESSION['error'] = "This appointment slot has just been booked by someone else or is invalid!";
            header("Location: book_appointment.php");
            exit();
        }
        $stmtInsert = $conn->prepare("
            INSERT INTO appointments (patient_id, doctor_id, schedule_id, status, symptoms_note) 
            VALUES (?, ?, ?, 'PENDING', ?)
        ");
        $stmtInsert->bind_param("iiis", $patient_id, $doctor_id, $schedule_id, $symptoms_note);
        $stmtInsert->execute();
        $stmtUpdate = $conn->prepare("UPDATE doctor_schedules SET is_available = 0 WHERE schedule_id = ?");
        $stmtUpdate->bind_param("i", $schedule_id);
        $stmtUpdate->execute();
        $titlePat = "Appointment Booked Successfully";
        $msgPat = "You have booked an appointment on " . $schedule['work_date'] . " at " . $schedule['start_time'] . ". Waiting for doctor's confirmation.";
        $stmtNotifPat = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read) 
            VALUES (?, ?, ?, 'SYSTEM_ALERT', 0)
        ");
        $stmtNotifPat->bind_param("iss", $user_id, $titlePat, $msgPat);
        $stmtNotifPat->execute();
        $stmtDocUser = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
        $stmtDocUser->bind_param("i", $doctor_id);
        $stmtDocUser->execute();
        $docUser = $stmtDocUser->get_result()->fetch_assoc();

        if ($docUser) {
            $titleDoc = "New Appointment Request";
            $msgDoc = "A patient has just booked an appointment on " . $schedule['work_date'] . " at " . $schedule['start_time'] . ".";
            $stmtNotifDoc = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read) 
                VALUES (?, ?, ?, 'SYSTEM_ALERT', 0)
            ");
            $stmtNotifDoc->bind_param("iss", $docUser['user_id'], $titleDoc, $msgDoc);
            $stmtNotifDoc->execute();
        }
        $conn->commit();
        $conn->close();

        $_SESSION['success'] = "Appointment booked successfully!";
        header("Location: appointment_history.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        $_SESSION['error'] = "System error: " . $e->getMessage();
        header("Location: book_appointment.php");
        exit();
    }
}
?>