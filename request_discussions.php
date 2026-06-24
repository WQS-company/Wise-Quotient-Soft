<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'fetch') {
    $requestId = (int)($_GET['request_id'] ?? 0);
    if (!$requestId) { echo json_encode(['error' => 'Missing request_id.']); exit; }

    // Verify user owns the request or is admin
    $check = $pdo->prepare("SELECT user_id FROM client_requests WHERE id = ?");
    $check->execute([$requestId]);
    $req = $check->fetch(PDO::FETCH_ASSOC);
    $roleCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleCheck->execute([$userId]);
    $user = $roleCheck->fetch(PDO::FETCH_ASSOC);
    if (!$req || !$user || ($req['user_id'] != $userId && $user['role'] !== 'admin' && $user['role'] !== 'agent')) {
        echo json_encode(['error' => 'Access denied.']); exit;
    }

    $stmt = $pdo->prepare("SELECT rd.*, u.name AS user_name, u.picture AS user_picture
        FROM request_discussions rd
        LEFT JOIN users u ON u.id = rd.user_id
        WHERE rd.request_id = ?
        ORDER BY rd.created_at ASC");
    $stmt->execute([$requestId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($action === 'add') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if (!$requestId || !$message) { echo json_encode(['error' => 'Missing fields.']); exit; }

    // Verify access
    $check = $pdo->prepare("SELECT user_id FROM client_requests WHERE id = ?");
    $check->execute([$requestId]);
    $req = $check->fetch(PDO::FETCH_ASSOC);
    $roleCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleCheck->execute([$userId]);
    $user = $roleCheck->fetch(PDO::FETCH_ASSOC);
    if (!$req || !$user || ($req['user_id'] != $userId && $user['role'] !== 'admin' && $user['role'] !== 'agent')) {
        echo json_encode(['error' => 'Access denied.']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO request_discussions (request_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$requestId, $userId, $message]);

    // Notify the other party
    $otherUserId = ($req['user_id'] == $userId) ? null : $req['user_id'];
    if (!$otherUserId) {
        // Notify admins
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND id != ?");
        $adminStmt->execute([$userId]);
        while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
            add_notification($admin['id'], "New discussion on project request #{$requestId}",
                "A new message was added to the project request discussion.", 'message', '../admin/client_requests.php', $requestId);
        }
    } else {
        add_notification($otherUserId, "New message on your project request #{$requestId}",
            "Someone replied to the discussion on your project request.", 'message', '../user/my_requests.php', $requestId);
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action.']);
