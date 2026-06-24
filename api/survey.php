<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check if user has completed survey
if ($action === 'status') {
    $stmt = $pdo->prepare("SELECT survey_completed FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $pref = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['completed' => $pref ? (bool)$pref['survey_completed'] : false]);
    exit;
}

// Get all active survey questions
if ($action === 'questions') {
    // Fetch logged-in user's info
    $stmtUser = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, question, type, options, placeholder, is_required, section, sort_order FROM survey_questions WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($questions as &$q) {
        $q['options'] = $q['options'] ? json_decode($q['options'], true) : null;
    }
    // Filter out personal info questions (name, email, phone) since user is already logged in
    $questions = array_values(array_filter($questions, function($q) {
        return !in_array(strtolower($q['type']), ['email', 'phone']) && stripos($q['question'], 'full name') === false;
    }));
    echo json_encode(['success' => true, 'questions' => $questions, 'user_info' => $userInfo]);
    exit;
}

// Submit survey responses
if ($action === 'submit') {
    $responses = $_POST['responses'] ?? null;
    if (!$responses) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $responses = $data['responses'] ?? null;
    }
    if (!$responses || !is_array($responses)) {
        echo json_encode(['error' => 'No responses provided.']); exit;
    }

    try {
        $pdo->beginTransaction();

        // Auto-capture name, email, phone from logged-in user
        $stmtUser = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Build preferences JSON
        $prefs = [];
        $insertStmt = $pdo->prepare("INSERT INTO survey_responses (user_id, question_id, response) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE response = VALUES(response)");

        // Save auto-filled personal info for questions 1-3 (name, email, phone)
        $autoFields = [
            1 => $userInfo['name'] ?? '',
            2 => $userInfo['email'] ?? '',
            3 => $userInfo['phone'] ?? ''
        ];
        foreach ($autoFields as $qId => $val) {
            $insertStmt->execute([$userId, $qId, $val]);
            $qStmt = $pdo->prepare("SELECT question FROM survey_questions WHERE id = ?");
            $qStmt->execute([$qId]);
            $qText = $qStmt->fetchColumn();
            if ($qText) {
                $key = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($qText)), 0, 50);
                $prefs[$key] = $val;
            }
        }

        foreach ($responses as $r) {
            $qId = (int)($r['question_id'] ?? 0);
            $val = trim($r['response'] ?? '');
            if (!$qId) continue;
            $insertStmt->execute([$userId, $qId, $val]);

            // Get question text for preferences key
            $qStmt = $pdo->prepare("SELECT question FROM survey_questions WHERE id = ?");
            $qStmt->execute([$qId]);
            $qText = $qStmt->fetchColumn();
            if ($qText) {
                $key = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($qText)), 0, 50);
                $prefs[$key] = $val;
            }
        }

        // Upsert into user_preferences
        $prefJson = json_encode($prefs);
        $upsertStmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preferences_json, survey_completed, survey_completed_at) VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE preferences_json = VALUES(preferences_json), survey_completed = 1, survey_completed_at = NOW()");
        $upsertStmt->execute([$userId, $prefJson]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Survey completed.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to save: ' . $e->getMessage()]);
    }
    exit;
}

// Admin: Get all user preferences (for monitoring)
if ($action === 'all_preferences' && isset($_SESSION['user']['id'])) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$_SESSION['user']['id']]);
    if (strtolower($roleStmt->fetchColumn()) !== 'admin') {
        echo json_encode(['error' => 'Access denied.']); exit;
    }
    $stmt = $pdo->prepare("SELECT up.*, u.name, u.email FROM user_preferences up JOIN users u ON u.id = up.user_id ORDER BY up.created_at DESC");
    $stmt->execute();
    echo json_encode(['success' => true, 'preferences' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Get user's own preferences
if ($action === 'my_preferences') {
    $stmt = $pdo->prepare("SELECT preferences_json, survey_completed, survey_completed_at FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $pref = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pref) {
        $pref['preferences_json'] = $pref['preferences_json'] ? json_decode($pref['preferences_json'], true) : null;
    }
    echo json_encode(['success' => true, 'preferences' => $pref]);
    exit;
}

echo json_encode(['error' => 'Invalid action.']);
