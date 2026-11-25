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
// Lấy doctor_id từ user_id đang đăng nhập
    public function getDoctorIdByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ? (int)$res['doctor_id'] : null;
    }

    // Lấy toàn bộ lịch làm việc của bác sĩ (kèm thông tin bệnh nhân nếu slot đã được đặt)
    public function getAllSchedulesByDoctor($doctor_id, $filter_date = '') {
        $sql = "SELECT ds.schedule_id, ds.work_date, ds.start_time, ds.end_time, ds.is_available,
                       a.appointment_id, a.status AS appointment_status, u.full_name AS patient_name
                FROM doctor_schedules ds
                LEFT JOIN appointments a 
                       ON ds.schedule_id = a.schedule_id 
                      AND a.status NOT IN ('CANCELLED')
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN users u ON p.user_id = u.user_id
                WHERE ds.doctor_id = ? AND ds.work_date >= CURDATE()";

        if (!empty($filter_date)) {
            $sql .= " AND ds.work_date = ? ORDER BY ds.work_date ASC, ds.start_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $doctor_id, $filter_date);
        } else {
            $sql .= " ORDER BY ds.work_date ASC, ds.start_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $doctor_id);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
// Đếm tổng số lịch của bác sĩ (để tính tổng số trang)
    public function countSchedulesByDoctor($doctor_id, $filter_date = '') {
        $sql = "SELECT COUNT(*) AS total
                FROM doctor_schedules
                WHERE doctor_id = ? AND work_date >= CURDATE()";

        if (!empty($filter_date)) {
            $sql .= " AND work_date = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $doctor_id, $filter_date);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $doctor_id);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    // Lấy danh sách lịch theo trang (LIMIT & OFFSET)
    public function getPaginatedSchedulesByDoctor($doctor_id, $limit = 9, $offset = 0, $filter_date = '') {
        $sql = "SELECT ds.schedule_id, ds.work_date, ds.start_time, ds.end_time, ds.is_available,
                       a.appointment_id, a.status AS appointment_status, u.full_name AS patient_name
                FROM doctor_schedules ds
                LEFT JOIN appointments a 
                       ON ds.schedule_id = a.schedule_id 
                      AND a.status NOT IN ('CANCELLED')
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN users u ON p.user_id = u.user_id
                WHERE ds.doctor_id = ? AND ds.work_date >= CURDATE()";

        if (!empty($filter_date)) {
            $sql .= " AND ds.work_date = ? ORDER BY ds.work_date ASC, ds.start_time ASC LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("isii", $doctor_id, $filter_date, $limit, $offset);
        } else {
            $sql .= " ORDER BY ds.work_date ASC, ds.start_time ASC LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iii", $doctor_id, $limit, $offset);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    // Thêm lịch trống mới cho bác sĩ (có kiểm tra giờ hợp lệ & chống trùng ca)
    public function addDoctorSchedule($doctor_id, $work_date, $start_time, $end_time) {
        if (empty($work_date) || empty($start_time) || empty($end_time)) {
            return ['status' => false, 'message' => 'Please fill in all date and time fields!'];
        }

        if ($work_date < date('Y-m-d')) {
            return ['status' => false, 'message' => 'Cannot create a schedule in the past!'];
        }

        if ($start_time >= $end_time) {
            return ['status' => false, 'message' => 'End time must be after start time!'];
        }

        // Kiểm tra bác sĩ đã có lịch nào bị giao nhau (overlap) trong cùng ngày chưa
        $stmtCheck = $this->conn->prepare("
            SELECT schedule_id 
            FROM doctor_schedules 
            WHERE doctor_id = ? 
              AND work_date = ? 
              AND (start_time < ? AND end_time > ?)
            LIMIT 1
        ");
        $stmtCheck->bind_param("isss", $doctor_id, $work_date, $end_time, $start_time);
        $stmtCheck->execute();

        if ($stmtCheck->get_result()->fetch_assoc()) {
            return ['status' => false, 'message' => 'This time slot overlaps with an existing schedule!'];
        }

        $stmtIns = $this->conn->prepare("
            INSERT INTO doctor_schedules (doctor_id, work_date, start_time, end_time, is_available) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtIns->bind_param("isss", $doctor_id, $work_date, $start_time, $end_time);

        if ($stmtIns->execute()) {
            return ['status' => true, 'message' => 'New available schedule added successfully!'];
        }
        return ['status' => false, 'message' => 'Failed to add schedule. Please try again.'];
    }

    // Xóa lịch trống (chỉ cho xóa khi is_available = 1 và chưa có bệnh nhân đặt)
    public function deleteDoctorSchedule($doctor_id, $schedule_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM doctor_schedules 
            WHERE schedule_id = ? AND doctor_id = ? AND is_available = 1
        ");
        $stmt->bind_param("ii", $schedule_id, $doctor_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            return ['status' => true, 'message' => 'Schedule slot deleted successfully!'];
        }
        return ['status' => false, 'message' => 'Cannot delete this slot (it may already be booked or does not exist).'];
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

            // 1. Khóa dòng của bệnh nhân để chống bấm đặt 2 tab cùng 1 giây (Race Condition)
            $stmtLockPatient = $this->conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ? FOR UPDATE");
            $stmtLockPatient->bind_param("i", $patient_id);
            $stmtLockPatient->execute();
            $stmtLockPatient->get_result();

            // 2. Khóa và lấy thông tin ca khám đang chọn
            $stmtCheck = $this->conn->prepare("
                SELECT is_available, work_date, start_time, end_time 
                FROM doctor_schedules 
                WHERE schedule_id = ? AND doctor_id = ? 
                FOR UPDATE
            ");
            $stmtCheck->bind_param("ii", $schedule_id, $doctor_id);
            $stmtCheck->execute();
            $slot = $stmtCheck->get_result()->fetch_assoc();

            if (!$slot || (int)$slot['is_available'] !== 1) {
                $this->conn->rollback();
                return ['status' => false, 'message' => 'Sorry, this schedule slot is no longer available!'];
            }

            // (Tùy chọn) Chặn đặt lịch của ngày đã qua
            if ($slot['work_date'] < date('Y-m-d')) {
                $this->conn->rollback();
                return ['status' => false, 'message' => 'Cannot book an appointment in the past!'];
            }

            // 3. Kiểm tra bệnh nhân có bị trùng lịch với bác sĩ khác (hoặc chính bác sĩ này) không
            // Dùng SELECT 1 để không phụ thuộc tên cột khóa chính của bảng appointments
            $stmtConflict = $this->conn->prepare("
                SELECT 1 
                FROM appointments a
                INNER JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
                WHERE a.patient_id = ?
                  AND a.status NOT IN ('CANCELLED', 'REJECTED')
                  AND ds.work_date = ?
                  AND (ds.start_time < ? AND ds.end_time > ?)
                LIMIT 1
            ");
            $stmtConflict->bind_param(
                "isss",
                $patient_id,
                $slot['work_date'],
                $slot['end_time'],
                $slot['start_time']
            );
            $stmtConflict->execute();

            if ($stmtConflict->get_result()->fetch_assoc()) {
                $this->conn->rollback();
                return [
                    'status' => false, 
                    'message' => 'You already have another appointment scheduled during this time slot!'
                ];
            }

            // 4. Lưu lịch hẹn và cập nhật trạng thái slot
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
            return ['status' => false, 'message' => 'Booking failed due to a system error. Please try again!'];
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