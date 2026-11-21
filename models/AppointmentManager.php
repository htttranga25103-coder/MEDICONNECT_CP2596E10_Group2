<?php
class AppointmentManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    private function getSpecializationColName() {
        try {
            $res = $this->conn->query("SHOW COLUMNS FROM specializations");
            $cols = array_column($res->fetch_all(MYSQLI_ASSOC), 'Field');
            foreach (['name', 'specialization_name', 'specialty_name', 'title'] as $candidate) {
                if (in_array($candidate, $cols)) {
                    return $candidate;
                }
            }
        } catch (Exception $e) {
            return null;
        }
        return null;
    }

    public function getPatientIdByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT patient_id FROM patients WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ? (int)$res['patient_id'] : null;
    }

    public function countAvailableDoctors($specialization_id = 0, $keyword = '', $city_id = 0) {
        $sql = "SELECT COUNT(d.doctor_id) AS total
                FROM doctors d
                JOIN users u ON d.user_id = u.user_id
                WHERE u.is_active = 1 AND d.approval_status = 'APPROVED'";

        $params = [];
        $types = "";

        if ($specialization_id > 0) {
            $sql .= " AND d.specialization_id = ?";
            $params[] = $specialization_id;
            $types .= "i";
        }
        if ($city_id > 0) {
            $sql .= " AND d.city_id = ?";
            $params[] = $city_id;
            $types .= "i";
        }
        if (!empty($keyword)) {
            $sql .= " AND (u.full_name LIKE ? OR d.work_place LIKE ?)";
            $like = "%" . trim($keyword) . "%";
            $params[] = $like;
            $params[] = $like;
            $types .= "ss";
        }

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function getAvailableDoctors($specialization_id = 0, $keyword = '', $limit = 6, $offset = 0, $city_id = 0) {
        $specCol = $this->getSpecializationColName();
        $specSelect = $specCol ? "s.`$specCol` AS specialization_name" : "NULL AS specialization_name";
        $specJoin   = $specCol ? "LEFT JOIN specializations s ON d.specialization_id = s.specialization_id" : "";

        $sql = "SELECT d.doctor_id, u.full_name, u.email, u.phone_number, u.avatar_url,
                       d.specialization_id, d.degree, d.experience_years, d.work_place, d.bio,
                       c.city_name, $specSelect
                FROM doctors d
                JOIN users u ON d.user_id = u.user_id
                LEFT JOIN cities c ON d.city_id = c.city_id
                $specJoin
                WHERE u.is_active = 1 AND d.approval_status = 'APPROVED'";
        
        $params = [];
        $types = "";

        if ($specialization_id > 0) {
            $sql .= " AND d.specialization_id = ?";
            $params[] = $specialization_id;
            $types .= "i";
        }
        if ($city_id > 0) {
            $sql .= " AND d.city_id = ?";
            $params[] = $city_id;
            $types .= "i";
        }
        if (!empty($keyword)) {
            $sql .= " AND (u.full_name LIKE ? OR d.work_place LIKE ?)";
            $like = "%" . trim($keyword) . "%";
            $params[] = $like;
            $params[] = $like;
            $types .= "ss";
        }

        $sql .= " ORDER BY u.full_name ASC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $types .= "ii";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getSpecializations() {
        $specCol = $this->getSpecializationColName();
        if (!$specCol) {
            return [];
        }
        try {
            $res = $this->conn->query("SELECT specialization_id, `$specCol` AS specialization_name FROM specializations ORDER BY `$specCol` ASC");
            return $res->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getCities() {
        try {
            $res = $this->conn->query("SELECT city_id, city_name FROM cities ORDER BY city_name ASC");
            return $res->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getDoctorById($doctor_id) {
        $specCol = $this->getSpecializationColName();
        $specSelect = $specCol ? "s.`$specCol` AS specialization_name" : "NULL AS specialization_name";
        $specJoin   = $specCol ? "LEFT JOIN specializations s ON d.specialization_id = s.specialization_id" : "";

        $sql = "SELECT d.*, u.full_name, u.email, u.phone_number, u.avatar_url,
                       c.city_name, $specSelect
                FROM doctors d
                JOIN users u ON d.user_id = u.user_id
                LEFT JOIN cities c ON d.city_id = c.city_id
                $specJoin
                WHERE d.doctor_id = ? AND u.is_active = 1 AND d.approval_status = 'APPROVED'
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getDoctorSchedules($doctor_id, $work_date = '') {
        $sql = "SELECT schedule_id, work_date, start_time, end_time, is_available
                FROM doctor_schedules
                WHERE doctor_id = ? AND work_date >= CURDATE()";
        
        if (!empty($work_date)) {
            $sql .= " AND work_date = ? ORDER BY work_date ASC, start_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $doctor_id, $work_date);
        } else {
            $sql .= " ORDER BY work_date ASC, start_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $doctor_id);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function bookAppointment($patient_id, $data) {
        $doctor_id = (int)($data['doctor_id'] ?? 0);
        $schedule_id = (int)($data['schedule_id'] ?? 0);
        $symptoms_note = trim($data['symptoms_note'] ?? '');

        if ($doctor_id <= 0 || $schedule_id <= 0) {
            return ['status' => false, 'message' => 'Please select an available schedule slot!'];
        }

        try {
            $this->conn->begin_transaction();

            $stmtCheck = $this->conn->prepare("SELECT is_available, work_date FROM doctor_schedules WHERE schedule_id = ? AND doctor_id = ? FOR UPDATE");
            $stmtCheck->bind_param("ii", $schedule_id, $doctor_id);
            $stmtCheck->execute();
            $slot = $stmtCheck->get_result()->fetch_assoc();

            if (!$slot || (int)$slot['is_available'] !== 1) {
                $this->conn->rollback();
                return ['status' => false, 'message' => 'Sorry, this schedule slot is no longer available!'];
            }

            $stmtIns = $this->conn->prepare("INSERT INTO appointments (patient_id, doctor_id, schedule_id, status, symptoms_note) VALUES (?, ?, ?, 'PENDING', ?)");
            $stmtIns->bind_param("iiis", $patient_id, $doctor_id, $schedule_id, $symptoms_note);
            $stmtIns->execute();

            $stmtUpd = $this->conn->prepare("UPDATE doctor_schedules SET is_available = 0 WHERE schedule_id = ?");
            $stmtUpd->bind_param("i", $schedule_id);
            $stmtUpd->execute();

            $this->conn->commit();
            return ['status' => true, 'message' => 'Appointment booked successfully! Status is currently PENDING.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => false, 'message' => 'Booking error: ' . $e->getMessage()];
        }
    }

    public function cancelAppointment($appointment_id, $patient_id) {
        try {
            $this->conn->begin_transaction();

            $stmtGet = $this->conn->prepare("SELECT schedule_id, status FROM appointments WHERE appointment_id = ? AND patient_id = ? FOR UPDATE");
            $stmtGet->bind_param("ii", $appointment_id, $patient_id);
            $stmtGet->execute();
            $appt = $stmtGet->get_result()->fetch_assoc();

            if (!$appt) {
                $this->conn->rollback();
                return ['status' => false, 'message' => 'Appointment not found!'];
            }

            if ($appt['status'] !== 'PENDING') {
                $this->conn->rollback();
                return ['status' => false, 'message' => 'Only PENDING appointments can be cancelled!'];
            }

            $schedule_id = (int)$appt['schedule_id'];

            $stmtDel = $this->conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
            $stmtDel->bind_param("i", $appointment_id);
            $stmtDel->execute();

            $stmtOpen = $this->conn->prepare("UPDATE doctor_schedules SET is_available = 1 WHERE schedule_id = ?");
            $stmtOpen->bind_param("i", $schedule_id);
            $stmtOpen->execute();

            $this->conn->commit();
            return ['status' => true, 'message' => "Appointment #$appointment_id has been cancelled and the time slot is reopened."];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => false, 'message' => 'Error cancelling appointment: ' . $e->getMessage()];
        }
    }

     public function getPatientAppointments($patient_id) {
        $specCol = $this->getSpecializationColName();
        $specSelect = $specCol ? "s.`$specCol` AS specialization_name" : "NULL AS specialization_name";
        $specJoin   = $specCol ? "LEFT JOIN specializations s ON d.specialization_id = s.specialization_id" : "";

        $sql = "SELECT a.appointment_id, a.status, a.symptoms_note,
                       ds.work_date, ds.start_time, ds.end_time,
                       d.degree, d.work_place,
                       u.full_name AS doctor_name,
                       $specSelect
                FROM appointments a
                JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
                JOIN doctors d ON a.doctor_id = d.doctor_id
                JOIN users u ON d.user_id = u.user_id
                $specJoin
                WHERE a.patient_id = ?
                ORDER BY ds.work_date DESC, ds.start_time DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    }