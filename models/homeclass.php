<?php
require_once __DIR__ . '/../config/connection.php';

class Doctor {
    public static function getFeaturedDoctors($limit = null) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $sql = "
        SELECT 
            d.doctor_id,
            u.full_name,
            u.avatar_url,
            d.bio,
            s.name AS specialty_name
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $result = $conn->query($sql);
        $doctors = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $doctors[] = $row;
            }
        }

        $conn->close();
        return $doctors;
    }
       
    public static function getLatestArticles($limit = null) {
        $conn = Connection::Connectdb();
        $conn->set_charset("utf8mb4");

        $sql = "
            SELECT 
                article_id,
                title,
                slug,
                summary,
                thumbnail_url,
                created_at
            FROM articles
            ORDER BY created_at DESC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $result = $conn->query($sql);
        $articles = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
        }

        $conn->close();
        return $articles;
    }

}
