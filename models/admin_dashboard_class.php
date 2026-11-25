<?php
require_once __DIR__ . '/../config/connection.php';

class AdminDashboard {
    // Đếm số dòng của một bảng (chỉ cho phép các bảng trong danh sách an toàn)
    private static function countTable($conn, $table) {
        $allowed = [
            'doctors', 'patients', 'articles', 'appointments',
            'article_comments', 'contact_messages', 'cities', 'specializations'
        ];
        if (!in_array($table, $allowed, true)) {
            return 0;
        }

        try {
            $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
            $row = $result ? $result->fetch_assoc() : null;
            return $row ? (int)$row['total'] : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    // 1. Các con số tổng quan cho thẻ thống kê và dải Quick overview
    public static function getOverviewCounts() {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $counts = [
            'doctors'          => self::countTable($conn, 'doctors'),
            'patients'         => self::countTable($conn, 'patients'),
            'articles'         => self::countTable($conn, 'articles'),
            'appointments'     => self::countTable($conn, 'appointments'),
            'comments'         => self::countTable($conn, 'article_comments'),
            'contact_messages' => self::countTable($conn, 'contact_messages'),
            'cities'           => self::countTable($conn, 'cities'),
            'specializations'  => self::countTable($conn, 'specializations'),
        ];

        $conn->close();
        return $counts;
    }

    // 2. Số lịch hẹn theo từng trạng thái
    public static function getAppointmentStatusCounts() {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $statuses = ['PENDING' => 0, 'CONFIRMED' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];

        try {
            $result = $conn->query("SELECT status, COUNT(*) AS total FROM appointments GROUP BY status");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $key = strtoupper(trim((string)$row['status']));
                    $statuses[$key] = ($statuses[$key] ?? 0) + (int)$row['total'];
                }
            }
        } catch (Throwable $e) {
            // giữ nguyên giá trị 0
        }

        $conn->close();
        return $statuses;
    }

    // 3. Các lịch hẹn mới nhất
    public static function getRecentAppointments($limit = 8) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $limit = max(1, (int)$limit);
        $sql = "
            SELECT 
                a.appointment_id,
                a.booking_time,
                a.status,
                u_pat.full_name AS patient_name,
                u_doc.full_name AS doctor_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u_pat ON p.user_id = u_pat.user_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            JOIN users u_doc ON d.user_id = u_doc.user_id
            ORDER BY a.booking_time DESC
            LIMIT $limit
        ";

        $rows = [];
        try {
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } catch (Throwable $e) {
            // trả về danh sách rỗng
        }

        $conn->close();
        return $rows;
    }

    // 4. Bác sĩ có nhiều lịch hẹn nhất
    public static function getTopDoctors($limit = 5) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $limit = max(1, (int)$limit);
        $sql = "
            SELECT 
                d.doctor_id,
                u.full_name,
                s.name AS specialty_name,
                COUNT(a.appointment_id) AS total_appointments
            FROM doctors d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
            LEFT JOIN appointments a ON a.doctor_id = d.doctor_id
            GROUP BY d.doctor_id, u.full_name, s.name
            ORDER BY total_appointments DESC, u.full_name ASC
            LIMIT $limit
        ";

        $rows = [];
        try {
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } catch (Throwable $e) {
            // trả về danh sách rỗng
        }

        $conn->close();
        return $rows;
    }
}
