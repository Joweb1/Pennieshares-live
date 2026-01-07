<?php
require_once __DIR__ . '/../../src/init.php';

header('Content-Type: application/json');

// Immediately start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for user authentication
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$loggedInUserId = $_SESSION['user']['id'];
$data = json_decode(file_get_contents('php://input'), true);
$pin = $data['pin'] ?? null;

if (empty($pin)) {
    echo json_encode(['success' => false, 'error' => 'PIN not provided']);
    exit;
}

if (verifyTransactionPin($loggedInUserId, $pin)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid PIN']);
}
?>
