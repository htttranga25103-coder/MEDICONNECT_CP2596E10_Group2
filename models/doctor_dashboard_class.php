<?php
require_once __DIR__ . '/../config/connection.php';

class DoctorDashboard {   
    public static $lastError = '';    
    public static function getDoctorInfo($doctor_id) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $doctor_id = (int)$doctor_id;
        $sql = "
            SELECT 
                d.doctor_id,
                u.full_name,
                u.email,
                u.phone_number,
                s.name AS specialty_name
            FROM doctors d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
            WHERE d.doctor_id = $doctor_id
            LIMIT 1
        ";

        $result = $conn->query($sql);
        $doctor = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

        $conn->close();
        return $doctor;
    }

    // 2. Lấy danh sách lịch hẹn của bác sĩ kèm gender và thông tin bệnh nhân
    public static function getDoctorAppointments($doctor_id) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $doctor_id = (int)$doctor_id;
        $sql = "
            SELECT 
                a.appointment_id,
                a.patient_id,
                a.schedule_id,
                a.booking_time,
                COALESCE(TIMESTAMP(ds.work_date, ds.start_time), a.booking_time) AS scheduled_time,
                ds.end_time AS schedule_end_time,
                a.status,
                a.symptoms_note,
                p.date_of_birth,
                p.gender,
                
                p.address,
                u.full_name AS patient_name,
                u.phone_number AS patient_phone,
                u.email AS patient_email
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
            WHERE a.doctor_id = $doctor_id
            ORDER BY scheduled_time DESC
        ";

        $result = $conn->query($sql);
        $appointments = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
        }

        $conn->close();
        return $appointments;
    }

    // 3. Lấy chi tiết hồ sơ bệnh nhân và thông tin cuộc hẹn theo appointment_id
    public static function getPatientProfileByAppointment($appointment_id) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $appointment_id = (int)$appointment_id;
        $sql = "
            SELECT 
                a.appointment_id,
                a.patient_id,
                a.doctor_id,
                a.schedule_id,
                a.booking_time,
                COALESCE(TIMESTAMP(ds.work_date, ds.start_time), a.booking_time) AS scheduled_time,
                ds.end_time AS schedule_end_time,
                a.status,
                a.symptoms_note,
                p.address,
                p.date_of_birth,
                p.gender,
                COALESCE(NULLIF(TRIM(p.gender), ''), 'Not yet updated') AS gender_display,
                u.full_name,
                u.email,
                u.phone_number,
                u.avatar_url
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
            WHERE a.appointment_id = $appointment_id
            LIMIT 1
        ";

        $result = $conn->query($sql);
        $profile = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

        $conn->close();
        return $profile;
    }    
    public static function getPatientVisitHistory($patient_id, $exclude_appointment_id = 0) {
    $conn = Connection::Connectdb();
    $conn->set_charset("utf8mb4");

    $patient_id = (int)$patient_id;
    $exclude_appointment_id = (int)$exclude_appointment_id;

    $sql = "
        SELECT 
            mr.record_id,
            mr.appointment_id,
            mr.diagnosis,
            mr.treatment_plan,
            mr.doctor_notes,
            mr.created_at,
            a.booking_time,
            COALESCE(TIMESTAMP(ds.work_date, ds.start_time), a.booking_time) AS scheduled_time,
            ds.end_time AS schedule_end_time,
            a.status,
            a.symptoms_note,
            u_doc.full_name AS doctor_name
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.appointment_id
        LEFT JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
        JOIN doctors d ON mr.doctor_id = d.doctor_id
        JOIN users u_doc ON d.user_id = u_doc.user_id
        WHERE mr.patient_id = $patient_id
          AND mr.appointment_id != $exclude_appointment_id
        ORDER BY mr.created_at DESC, mr.record_id DESC
    ";

    $result = $conn->query($sql);
    $history = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    $conn->close();
    return $history;
}
   
    public static function updatePatientGender($patient_id, $gender) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $patient_id = (int)$patient_id;
        $allowedGenders = ['Male', 'Female', 'Other'];
        $cleanGender = trim((string)$gender);

        foreach ($allowedGenders as $valid) {
            if (strcasecmp($cleanGender, $valid) === 0) {
                $cleanGender = $valid;
                break;
            }
        }

        if ($patient_id <= 0 || !in_array($cleanGender, $allowedGenders, true)) {
            $conn->close();
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE patients 
            SET gender = ?, updated_at = NOW() 
            WHERE patient_id = ?
        ");
        if (!$stmt) {
            $conn->close();
            return false;
        }

        $stmt->bind_param("si", $cleanGender, $patient_id);
        $updated = $stmt->execute();

        $stmt->close();
        $conn->close();
        return $updated;
    }

    
    public static function updateAppointmentStatus($appointment_id, $doctor_id, $status) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $appointment_id = (int)$appointment_id;
        $doctor_id = (int)$doctor_id;
        $allowedStatuses = ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED'];
        $cleanStatus = strtoupper(trim((string)$status));

        if ($appointment_id <= 0 || $doctor_id <= 0 || !in_array($cleanStatus, $allowedStatuses, true)) {
            $conn->close();
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = ?, updated_at = NOW() 
            WHERE appointment_id = ? AND doctor_id = ?
        ");
        if (!$stmt) {
            $conn->close();
            return false;
        }

        $stmt->bind_param("sii", $cleanStatus, $appointment_id, $doctor_id);
        $updated = $stmt->execute();

        $stmt->close();
        $conn->close();
        return $updated;
    }

    
    public static function getMedicalRecordByAppointment($appointment_id) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $appointment_id = (int)$appointment_id;
        $sql = "
            SELECT record_id, appointment_id, patient_id, doctor_id,
                   diagnosis, treatment_plan, doctor_notes
            FROM medical_records
            WHERE appointment_id = $appointment_id
            LIMIT 1
        ";

        $result = $conn->query($sql);
        $record = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

        $conn->close();
        return $record;
    }    
    private static function ensureMedicalRecordsAllowMultiple($conn) {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $result = $conn->query("
                SELECT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'medical_records'
                  AND NON_UNIQUE = 0
                  AND INDEX_NAME <> 'PRIMARY'
                GROUP BY INDEX_NAME
                HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'appointment_id'
            ");

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $indexName = str_replace('`', '``', $row['INDEX_NAME']);
                    $conn->query("
                        ALTER TABLE medical_records
                            ADD INDEX idx_mr_appointment (appointment_id),
                            DROP INDEX `$indexName`
                    ");
                }
            }
        } catch (Throwable $e) {
            error_log('ensureMedicalRecordsAllowMultiple failed: ' . $e->getMessage());
        }
    }
    
    public static function saveMedicalRecord($appointment_id, $doctor_id, $diagnosis, $treatment_plan, $doctor_notes) {
        $appointment_id = (int)$appointment_id;
        $doctor_id = (int)$doctor_id;
        $diagnosis = trim((string)$diagnosis);
        $treatment_plan = trim((string)$treatment_plan);
        $doctor_notes = trim((string)$doctor_notes);

        if ($appointment_id <= 0 || $doctor_id <= 0 || $diagnosis === '') {
            return false;
        }

        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        self::ensureMedicalRecordsAllowMultiple($conn);

        try {
            $conn->begin_transaction();            
            $stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ? AND doctor_id = ? LIMIT 1");
            $stmt->bind_param("ii", $appointment_id, $doctor_id);
            $stmt->execute();
            $appt = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$appt) {
                throw new Exception('Appointment not found for this doctor');
            }
            $patient_id = (int)$appt['patient_id'];          
            $stmt = $conn->prepare("
                INSERT INTO medical_records
                    (appointment_id, patient_id, doctor_id, diagnosis, treatment_plan, doctor_notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("iiisss", $appointment_id, $patient_id, $doctor_id, $diagnosis, $treatment_plan, $doctor_notes);
            $stmt->execute();
            $stmt->close();           
            $stmt = $conn->prepare("
                UPDATE appointments
                SET status = 'COMPLETED', updated_at = NOW()
                WHERE appointment_id = ? AND doctor_id = ?
            ");
            $stmt->bind_param("ii", $appointment_id, $doctor_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $conn->close();
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('saveMedicalRecord failed: ' . $e->getMessage());
            $conn->rollback();
            $conn->close();
            return false;
        }
    }
}
