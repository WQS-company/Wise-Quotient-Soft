<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();
session_start();

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/api_health_monitor.php';

try {
    $results = APIHealthMonitor::runAllChecks();
    $hasFailures = false;
    foreach ($results as $r) {
        if ($r['status'] === 'fail') { $hasFailures = true; break; }
    }
    echo json_encode([
        'success' => true,
        'has_failures' => $hasFailures,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
