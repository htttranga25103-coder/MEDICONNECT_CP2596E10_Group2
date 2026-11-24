<?php
class ChatManager {
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
                if (in_array($candidate, $cols, true)) {
                    return $candidate;
                }
            }
        } catch (Exception $e) {
            return null;
        }
        return null;
    }

    public function getChatContacts($user_id, $role_name) {
        $user_id = (int)$user_id;

        if ($role_name === 'PATIENT') {
            $specCol    = $this->getSpecializationColName();
            $specSelect = $specCol ? "s.`$specCol` AS specialty" : "NULL AS specialty";
            $specJoin   = $specCol ? "LEFT JOIN specializations s ON d.specialization_id = s.specialization_id" : "";

            $sql = "SELECT u.user_id, u.full_name, u.avatar_url, d.degree, $specSelect,
                           (SELECT COUNT(*) FROM chat_messages cm
                            WHERE cm.sender_id = u.user_id AND cm.receiver_id = ? AND cm.is_read = 0) AS unread_count
                    FROM users u
                    LEFT JOIN doctors d ON d.user_id = u.user_id
                    $specJoin
                    WHERE u.is_active = 1
                      AND u.user_id != ?
                      AND (
                          EXISTS (
                              SELECT 1
                              FROM appointments a
                              JOIN patients p ON a.patient_id = p.patient_id
                              WHERE p.user_id = ? AND a.doctor_id = d.doctor_id
                          )
                          OR EXISTS (
                              SELECT 1
                              FROM chat_messages cm2
                              WHERE (cm2.sender_id = ? AND cm2.receiver_id = u.user_id)
                                 OR (cm2.sender_id = u.user_id AND cm2.receiver_id = ?)
                          )
                      )
                    ORDER BY u.full_name ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
        } else {
            $sql = "SELECT u.user_id, u.full_name, u.avatar_url, p.gender, p.date_of_birth,
                           (SELECT COUNT(*) FROM chat_messages cm
                            WHERE cm.sender_id = u.user_id AND cm.receiver_id = ? AND cm.is_read = 0) AS unread_count
                    FROM users u
                    LEFT JOIN patients p ON p.user_id = u.user_id
                    WHERE u.is_active = 1
                      AND u.user_id != ?
                      AND (
                          EXISTS (
                              SELECT 1
                              FROM appointments a
                              JOIN doctors d ON a.doctor_id = d.doctor_id
                              WHERE d.user_id = ? AND a.patient_id = p.patient_id
                          )
                          OR EXISTS (
                              SELECT 1
                              FROM chat_messages cm2
                              WHERE (cm2.sender_id = ? AND cm2.receiver_id = u.user_id)
                                 OR (cm2.sender_id = u.user_id AND cm2.receiver_id = ?)
                          )
                      )
                    ORDER BY u.full_name ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getPartnerInfo($partner_id) {
        $stmt = $this->conn->prepare("SELECT u.user_id, u.full_name, u.avatar_url, r.role_name
                                      FROM users u
                                      JOIN roles r ON u.role_id = r.role_id
                                      WHERE u.user_id = ? AND u.is_active = 1 LIMIT 1");
        $stmt->bind_param("i", $partner_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function sendMessage($sender_id, $receiver_id, $message) {
        $message = trim($message);
        if ($sender_id <= 0 || $receiver_id <= 0 || $message === '') {
            return ['status' => false, 'message' => 'Message cannot be empty.'];
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
            $stmt->execute();
            return ['status' => true, 'message_id' => $this->conn->insert_id];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Failed to send message: ' . $e->getMessage()];
        }
    }

    public function getConversation($user_id, $partner_id) {
        $stmtRead = $this->conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmtRead->bind_param("ii", $partner_id, $user_id);
        $stmtRead->execute();

        $sql = "SELECT message_id, sender_id, receiver_id, message, is_read, created_at
                FROM chat_messages
                WHERE (sender_id = ? AND receiver_id = ?)
                   OR (sender_id = ? AND receiver_id = ?)
                ORDER BY created_at ASC, message_id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}