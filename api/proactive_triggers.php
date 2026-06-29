<?php
/**
 * API: Proactive Triggers
 * Returns active triggers for the current page
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

$page = trim($_GET['page'] ?? '');

if (empty($page)) {
    echo json_encode(['success' => true, 'triggers' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, trigger_page, message, delay_seconds, target_role FROM bot_proactive_triggers WHERE is_active = 1 AND trigger_page = ? ORDER BY sort_order ASC LIMIT 3");
    $stmt->execute([$page]);
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter by role if needed
    $userRole = $_SESSION['user']['role'] ?? 'guest';
    $filtered = [];
    foreach ($triggers as $t) {
        if ($t['target_role'] === 'all' || $t['target_role'] === $userRole) {
            $filtered[] = $t;
        }
    }

    echo json_encode(['success' => true, 'triggers' => $filtered]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'triggers' => []]);
}
