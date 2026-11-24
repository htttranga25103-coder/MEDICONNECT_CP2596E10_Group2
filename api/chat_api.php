<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/MyConnection.php';
require_once __DIR__ . '/../models/ChatManager.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['PATIENT', 'DOCTOR'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = MyConnection::connectDatabase();
$chatManager = new ChatManager($conn);
$current_user_id = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message     = $_POST['message'] ?? '';
    $res = $chatManager->sendMessage($current_user_id, $receiver_id, $message);
    echo json_encode($res);
    exit();
}

if ($action === 'fetch') {
    $partner_id = (int)($_GET['partner_id'] ?? 0);
    if ($partner_id <= 0) {
        echo json_encode(['status' => false, 'messages' => []]);
        exit();
    }
    $messages = $chatManager->getConversation($current_user_id, $partner_id);
    echo json_encode([
        'status'          => true,
        'current_user_id' => $current_user_id,
        'messages'        => $messages
    ]);
    exit();
}

echo json_encode(['status' => false, 'message' => 'Invalid action']);