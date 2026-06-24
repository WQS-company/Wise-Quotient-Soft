<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Check if user_notification_settings table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'user_notification_settings'");
    if (!$checkTable->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tables not yet installed']);
        exit;
    }
    
    // Get current settings
    $checkStmt = $pdo->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
    $checkStmt->execute([$userId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing
        $updates = [];
        $params = [];
        if (isset($input['enable_ads'])) {
            $updates[] = "enable_ads = ?";
            $params[] = (int)$input['enable_ads'];
        }
        if (isset($input['enable_push'])) {
            $updates[] = "enable_push_notifications = ?";
            $params[] = (int)$input['enable_push'];
        }
        if (isset($input['enable_email'])) {
            $updates[] = "enable_email_notifications = ?";
            $params[] = (int)$input['enable_email'];
        }
        
        if (!empty($updates)) {
            $params[] = $userId;
            $stmt = $pdo->prepare("UPDATE user_notification_settings SET " . implode(', ', $updates) . " WHERE user_id = ?");
            $stmt->execute($params);
        }
    } else {
        // Create new
        $stmt = $pdo->prepare("INSERT INTO user_notification_settings (user_id, enable_ads, enable_push_notifications, enable_email_notifications) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $input['enable_ads'] ?? 1,
            $input['enable_push'] ?? 1,
            $input['enable_email'] ?? 1
        ]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'message' => 'Silent error']);
}
?>
