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
            $_SESSION['error'] = "Suất khám này vừa có người đặt trước hoặc không hợp lệ!";
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
        $titlePat = "Đặt lịch khám thành công";
        $msgPat = "Bạn đã đặt lịch khám ngày " . $schedule['work_date'] . " lúc " . $schedule['start_time'] . ". Đang chờ bác sĩ xác nhận.";
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
            $titleDoc = "Có lịch hẹn khám mới";
            $msgDoc = "Bệnh nhân vừa đặt lịch khám vào ngày " . $schedule['work_date'] . " lúc " . $schedule['start_time'] . ".";
            $stmtNotifDoc = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read) 
                VALUES (?, ?, ?, 'SYSTEM_ALERT', 0)
            ");
            $stmtNotifDoc->bind_param("iss", $docUser['user_id'], $titleDoc, $msgDoc);
            $stmtNotifDoc->execute();
        }
        $conn->commit();
        $conn->close();

        $_SESSION['success'] = "Đặt lịch khám thành công!";
        header("Location: appointment_history.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        $_SESSION['error'] = "Lỗi hệ thống: " . $e->getMessage();
        header("Location: book_appointment.php");
        exit();
    }
}
?>