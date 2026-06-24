<?php
// API Endpoint: Register FCM Browser Push Tokens
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Retrieve the token details from the raw request payload or standard POST fields
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$token       = trim($data['token'] ?? '');
$device_type = trim($data['device_type'] ?? 'desktop');
$userId      = $_SESSION['user']['id'] ?? null;

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'FCM token is required.']);
    exit;
}

try {
    // Check if token already exists in database
    $stmt = $pdo->prepare("SELECT id, user_id FROM user_fcm_tokens WHERE fcm_token = ?");
    $stmt->execute([$token]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update user_id reference if it changes (e.g. guest logs in)
        $update = $pdo->prepare("UPDATE user_fcm_tokens SET user_id = ?, device_type = ?, updated_at = CURRENT_TIMESTAMP WHERE fcm_token = ?");
        $update->execute([$userId, $device_type, $token]);
    } else {
        // Insert new token association
        $insert = $pdo->prepare("INSERT INTO user_fcm_tokens (user_id, fcm_token, device_type) VALUES (?, ?, ?)");
        $insert->execute([$userId, $token, $device_type]);
    }

    echo json_encode(['success' => true, 'message' => 'FCM token registered successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
