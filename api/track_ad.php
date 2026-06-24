<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$adId = (int)($input['ad_id'] ?? 0);

if (!$adId) {
    echo json_encode(['success' => false, 'message' => 'Invalid ad ID']);
    exit;
}

$userId = $_SESSION['user']['id'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    // Check if ad_displays table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'ad_displays'");
    if (!$checkTable->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Tables not yet installed']);
        exit;
    }
    
    if ($action === 'view') {
        // Record view
        $stmt = $pdo->prepare("INSERT INTO ad_displays (ad_id, user_id, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$adId, $userId, $ip]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'click') {
        // Record click - find most recent view and update it
        $stmt = $pdo->prepare("UPDATE ad_displays SET clicked_at = NOW() WHERE ad_id = ? AND (user_id = ? OR ip_address = ?) AND clicked_at IS NULL ORDER BY viewed_at DESC LIMIT 1");
        $stmt->execute([$adId, $userId, $ip]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'close') {
        // Store in session to not show again this session
        if (!isset($_SESSION['closed_ads'])) {
            $_SESSION['closed_ads'] = [];
        }
        if (!in_array($adId, $_SESSION['closed_ads'])) {
            $_SESSION['closed_ads'][] = $adId;
        }
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => true, 'message' => 'Silent error']);
}
?>
