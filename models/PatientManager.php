<?php
class PatientManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    public function addPatient($data) {
        $full_name = trim($data['full_name']);
        $email = trim($data['email']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $phone_number = trim($data['phone_number'] ?? '');
        $address = trim($data['address'] ?? '');
        $city_id = !empty($data['city_id']) ? (int)$data['city_id'] : null;
        $district_id = !empty($data['district_id']) ? (int)$data['district_id'] : null;
        $street_id = !empty($data['street_id']) ? (int)$data['street_id'] : null;
        $date_of_birth = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
        $gender = !empty($data['gender']) ? $data['gender'] : null;

        try {
            $this->conn->begin_transaction();
            $resRole = $this->conn->query("SELECT role_id FROM roles WHERE role_name = 'PATIENT' LIMIT 1");
            $roleRow = $resRole->fetch_assoc();
            if (!$roleRow) {
                throw new Exception("PATIENT role not found in the system.");
            }
            $role_id = $roleRow['role_id'];
            $stmtUser = $this->conn->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone_number, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmtUser->bind_param("issss", $role_id, $email, $password, $full_name, $phone_number);
            $stmtUser->execute();
            $new_user_id = $this->conn->insert_id;
            $stmtPat = $this->conn->prepare("INSERT INTO patients (user_id, address, city_id, district_id, street_id, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtPat->bind_param("isiiiss", $new_user_id, $address, $city_id, $district_id, $street_id, $date_of_birth, $gender);
            $stmtPat->execute();

            $this->conn->commit();
            return ['status' => true, 'message' => 'New patient added successfully!'];
        } catch (Exception $e) {
            $this->conn->rollback();
            $msg = ($e->getCode() == 1062) ? "This email already exists in the system!" : "Error adding patient: " . $e->getMessage();
            return ['status' => false, 'message' => $msg];
        }
    }
    public function updatePatient($data) {
        $patient_id = (int)$data['patient_id'];
        $full_name = trim($data['full_name']);
        $phone_number = trim($data['phone_number'] ?? '');
        $is_active = (int)$data['is_active'];
        $new_password = $data['password'] ?? '';
        $address = trim($data['address'] ?? '');
        $city_id = !empty($data['city_id']) ? (int)$data['city_id'] : null;
        $district_id = !empty($data['district_id']) ? (int)$data['district_id'] : null;
        $street_id = !empty($data['street_id']) ? (int)$data['street_id'] : null;
        $date_of_birth = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
        $gender = !empty($data['gender']) ? $data['gender'] : null;

        try {
            $this->conn->begin_transaction();
            $stmtGet = $this->conn->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $stmtGet->bind_param("i", $patient_id);
            $stmtGet->execute();
            $pat = $stmtGet->get_result()->fetch_assoc();
            if (!$pat) {
                throw new Exception("Patient record not found!");
            }
            $user_id = $pat['user_id'];

            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmtUser = $this->conn->prepare("UPDATE users SET full_name = ?, phone_number = ?, is_active = ?, password_hash = ? WHERE user_id = ?");
                $stmtUser->bind_param("ssisi", $full_name, $phone_number, $is_active, $hashed, $user_id);
            } else {
                $stmtUser = $this->conn->prepare("UPDATE users SET full_name = ?, phone_number = ?, is_active = ? WHERE user_id = ?");
                $stmtUser->bind_param("ssii", $full_name, $phone_number, $is_active, $user_id);
            }
            $stmtUser->execute();

            $stmtPat = $this->conn->prepare("UPDATE patients SET address = ?, city_id = ?, district_id = ?, street_id = ?, date_of_birth = ?, gender = ? WHERE patient_id = ?");
            $stmtPat->bind_param("siiissi", $address, $city_id, $district_id, $street_id, $date_of_birth, $gender, $patient_id);
            $stmtPat->execute();

            $this->conn->commit();
            return ['status' => true, 'message' => "Patient #$patient_id updated successfully!"];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => false, 'message' => "Error updating patient: " . $e->getMessage()];
        }
    }
    public function deletePatient($patient_id) {
        $patient_id = (int)$patient_id;

        try {
            $this->conn->begin_transaction();

            $stmtGet = $this->conn->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $stmtGet->bind_param("i", $patient_id);
            $stmtGet->execute();
            $pat = $stmtGet->get_result()->fetch_assoc();

            if (!$pat) {
                throw new Exception("Patient does not exist.");
            }

            $user_id = $pat['user_id'];
            // Kiểm tra lịch sử khám
            $stmtCheck = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
            $stmtCheck->bind_param("i", $patient_id);
            $stmtCheck->execute();
            $hasAppt = $stmtCheck->get_result()->fetch_assoc()['total'] > 0;

            if ($hasAppt) {
                $stmtSoft = $this->conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
                $stmtSoft->bind_param("i", $user_id);
                $stmtSoft->execute();
                $msg = "Patient has appointment history; account has been LOCKED to preserve medical records.";
            } else {
                $stmtDelPat = $this->conn->prepare("DELETE FROM patients WHERE patient_id = ?");
                $stmtDelPat->bind_param("i", $patient_id);
                $stmtDelPat->execute();

                $stmtDelUser = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmtDelUser->bind_param("i", $user_id);
                $stmtDelUser->execute();
                $msg = "Patient record permanently deleted from the system.";
            }

            $this->conn->commit();
            return ['status' => true, 'message' => $msg];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => false, 'message' => "Error deleting patient: " . $e->getMessage()];
        }
    }
    public function getPatients($keyword = '') {
        $searchParam = "%" . trim($keyword) . "%";
        $sql = "SELECT p.*, u.email, u.full_name, u.phone_number, u.is_active,
                       c.city_name, d.district_name, s.street_name
                FROM patients p
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN cities c ON p.city_id = c.city_id
                LEFT JOIN districts d ON p.district_id = d.district_id
                LEFT JOIN streets s ON p.street_id = s.street_id
                WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?
                ORDER BY p.patient_id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function getAddressOptions() {
        return [
            'cities' => $this->conn->query("SELECT city_id, city_name FROM cities ORDER BY city_name")->fetch_all(MYSQLI_ASSOC),
            'districts' => $this->conn->query("SELECT district_id, district_name FROM districts ORDER BY district_name")->fetch_all(MYSQLI_ASSOC),
            'streets' => $this->conn->query("SELECT street_id, street_name FROM streets ORDER BY street_name")->fetch_all(MYSQLI_ASSOC)
        ];
    }
}