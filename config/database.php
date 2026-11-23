<?php
$host = '127.0.0.1';
$db = 'mediconnect';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

if (!class_exists('MyConnection')) {
    class MyConnection {
        public static function connectDatabase() {
            global $host, $user, $pass, $db;
            $conn = new mysqli($host, $user, $pass, $db);
            if ($conn->connect_error) {
                die("Database connection error: " . $conn->connect_error);
            }
            $conn->set_charset("utf8mb4");
            return $conn;
        }
    }
}