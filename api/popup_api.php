<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user']['id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─── PUBLIC: Get active popups (no auth required) ───
if ($action === 'active_popups' && $method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM floating_popups WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode(['success' => true, 'popups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'popups' => []]);
    }
    exit;
}

// ─── PUBLIC: Track event ───
if ($action === 'track' && $method === 'POST') {
    $popupId = (int)($_POST['popup_id'] ?? 0);
    $eventType = $_POST['event_type'] ?? '';
    $sessionId = $_POST['session_id'] ?? '';
    if (!$popupId || !in_array($eventType, ['view','click','close'])) {
        echo json_encode(['error' => 'Invalid data.']); exit;
    }
    try {
        $pdo->prepare("INSERT INTO popup_analytics (popup_id, event_type, user_id, session_id) VALUES (?, ?, ?, ?)")
            ->execute([$popupId, $eventType, $userId, $sessionId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to track.']);
    }
    exit;
}

// ─── ADMIN: Create popup ───
if ($action === 'create_popup' && $method === 'POST') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $buttonText = trim($_POST['button_text'] ?? 'Learn More');
    $buttonUrl = trim($_POST['button_url'] ?? '#');
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $timerDuration = (int)($_POST['timer_duration'] ?? 10);
    $position = $_POST['position'] ?? 'center';
    $size = $_POST['size'] ?? 'md';
    $trigger = $_POST['trigger'] ?? 'immediate';
    $triggerDelay = (int)($_POST['trigger_delay'] ?? 3);
    $isActive = (int)($_POST['is_active'] ?? 1);

    if (!$title) { echo json_encode(['error' => 'Title required.']); exit; }

    try {
        $pdo->prepare("INSERT INTO floating_popups (title, description, image_url, button_text, button_url, start_date, end_date, timer_duration, position, size, `trigger`, trigger_delay, is_active, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$title, $description, $imageUrl, $buttonText, $buttonUrl, $startDate ?: null, $endDate ?: null, $timerDuration, $position, $size, $trigger, $triggerDelay, $isActive, $userId]);
        echo json_encode(['success' => true, 'popup_id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to create popup.']);
    }
    exit;
}

// ─── ADMIN: Update popup ───
if ($action === 'update_popup' && $method === 'POST') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    $popupId = (int)($_POST['popup_id'] ?? 0);
    if (!$popupId) { echo json_encode(['error' => 'No popup ID.']); exit; }

    $fields = [];
    $params = [];
    $allowed = ['title','description','image_url','button_text','button_url','start_date','end_date','timer_duration','position','size','trigger','trigger_delay','is_active'];
    foreach ($allowed as $f) {
        if (isset($_POST[$f])) {
            $fields[] = "`$f` = ?";
            $params[] = $f === 'timer_duration' || $f === 'trigger_delay' ? (int)$_POST[$f] : ($f === 'is_active' ? (int)$_POST[$f] : $_POST[$f]);
        }
    }
    if (empty($fields)) { echo json_encode(['error' => 'Nothing to update.']); exit; }
    $params[] = $popupId;
    try {
        $pdo->prepare("UPDATE floating_popups SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to update.']);
    }
    exit;
}

// ─── ADMIN: Delete popup ───
if ($action === 'delete_popup' && $method === 'POST') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    $popupId = (int)($_POST['popup_id'] ?? 0);
    if (!$popupId) { echo json_encode(['error' => 'No popup ID.']); exit; }
    try {
        $pdo->prepare("DELETE FROM floating_popups WHERE id = ?")->execute([$popupId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to delete.']);
    }
    exit;
}

// ─── ADMIN: Toggle active status ───
if ($action === 'toggle_popup' && $method === 'POST') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    $popupId = (int)($_POST['popup_id'] ?? 0);
    if (!$popupId) { echo json_encode(['error' => 'No popup ID.']); exit; }
    try {
        $pdo->prepare("UPDATE floating_popups SET is_active = NOT is_active WHERE id = ?")->execute([$popupId]);
        $newStatus = $pdo->prepare("SELECT is_active FROM floating_popups WHERE id = ?");
        $newStatus->execute([$popupId]);
        $row = $newStatus->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'is_active' => $row['is_active']]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to toggle.']);
    }
    exit;
}

// ─── ADMIN: Get all popups ───
if ($action === 'list_popups' && $method === 'GET') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    try {
        $stmt = $pdo->query("SELECT p.*, u.name AS created_by_name,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'view') AS views,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'click') AS clicks,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'close') AS closes
            FROM floating_popups p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC");
        echo json_encode(['success' => true, 'popups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load popups.']);
    }
    exit;
}

// ─── ADMIN: Get single popup ───
if ($action === 'get_popup' && $method === 'GET') {
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    $popupId = (int)($_GET['popup_id'] ?? 0);
    if (!$popupId) { echo json_encode(['error' => 'No popup ID.']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT p.*, u.name AS created_by_name,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'view') AS views,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'click') AS clicks,
            (SELECT COUNT(*) FROM popup_analytics a WHERE a.popup_id = p.id AND a.event_type = 'close') AS closes
            FROM floating_popups p LEFT JOIN users u ON p.created_by = u.id WHERE p.id = ?");
        $stmt->execute([$popupId]);
        $popup = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($popup ? ['success' => true, 'popup' => $popup] : ['error' => 'Not found.']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed.']);
    }
    exit;
}

// ─── PUBLIC: Dismiss ad/popup (don't show again) ───
if ($action === 'dismiss_ad' && $method === 'POST') {
    $adId = (int)($_POST['ad_id'] ?? 0);
    $popupId = (int)($_POST['popup_id'] ?? 0);
    $adType = $_POST['ad_type'] ?? 'modal';
    if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }
    if (!$adId && !$popupId) { echo json_encode(['error' => 'No ad or popup ID.']); exit; }

    try {
        if ($adId) {
            $pdo->prepare("INSERT IGNORE INTO user_ad_dismissals (user_id, ad_id, ad_type) VALUES (?, ?, ?)")
                ->execute([$userId, $adId, $adType]);
        }
        if ($popupId) {
            $pdo->prepare("INSERT IGNORE INTO user_ad_dismissals (user_id, popup_id, ad_type) VALUES (?, ?, ?)")
                ->execute([$userId, $popupId, $adType]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to dismiss.']);
    }
    exit;
}

// ─── PUBLIC: Check if ad/popup is dismissed ───
if ($action === 'check_dismissed' && $method === 'GET') {
    $adId = (int)($_GET['ad_id'] ?? 0);
    $popupId = (int)($_GET['popup_id'] ?? 0);
    if (!$userId) { echo json_encode(['success' => true, 'dismissed' => false]); exit; }

    try {
        $dismissed = false;
        if ($adId) {
            $stmt = $pdo->prepare("SELECT id FROM user_ad_dismissals WHERE user_id = ? AND ad_id = ?");
            $stmt->execute([$userId, $adId]);
            $dismissed = (bool)$stmt->fetch();
        }
        if (!$dismissed && $popupId) {
            $stmt = $pdo->prepare("SELECT id FROM user_ad_dismissals WHERE user_id = ? AND popup_id = ?");
            $stmt->execute([$userId, $popupId]);
            $dismissed = (bool)$stmt->fetch();
        }
        echo json_encode(['success' => true, 'dismissed' => $dismissed]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'dismissed' => false]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action.']);
