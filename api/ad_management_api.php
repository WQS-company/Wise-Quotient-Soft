<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user']['id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }

// ─── Admin check ───
$roleQ = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleQ->execute([$userId]);
$roleRow = $roleQ->fetch(PDO::FETCH_ASSOC);
if (!$roleRow || $roleRow['role'] !== 'admin') { echo json_encode(['error' => 'Unauthorized.']); exit; }

// ━━━ STATS ━━━
if ($action === 'stats' && $method === 'GET') {
    try {
        $ads = $pdo->query("SELECT id, is_active, run_status, featured, start_date, end_date, max_views, total_views, total_clicks, created_at FROM ads")->fetchAll(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');
        $stats = ['total'=>0,'running'=>0,'scheduled'=>0,'expired'=>0,'paused'=>0,'disabled'=>0,'total_views'=>0,'total_clicks'=>0];
        foreach ($ads as $a) {
            $stats['total']++;
            $stats['total_views'] += (int)$a['total_views'];
            $stats['total_clicks'] += (int)$a['total_clicks'];
            $status = calcAdStatus($a, $now);
            if (isset($stats[$status])) $stats[$status]++;
        }
        $stats['avg_ctr'] = $stats['total_views'] > 0 ? round(($stats['total_clicks'] / $stats['total_views']) * 100, 1) : 0;
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load stats.']);
    }
    exit;
}

// ━━━ TOGGLE RUN STATUS ━━━
if ($action === 'toggle_run' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    try {
        $pdo->prepare("UPDATE ads SET run_status = NOT run_status WHERE id = ?")->execute([$id]);
        $r = $pdo->prepare("SELECT run_status FROM ads WHERE id = ?");
        $r->execute([$id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'run_status' => $row['run_status']]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ TOGGLE FEATURED ━━━
if ($action === 'toggle_featured' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    try {
        $pdo->prepare("UPDATE ads SET featured = NOT featured WHERE id = ?")->execute([$id]);
        $r = $pdo->prepare("SELECT featured FROM ads WHERE id = ?");
        $r->execute([$id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'featured' => $row['featured']]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ UPDATE PRIORITY ━━━
if ($action === 'update_priority' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $priority = (int)($_POST['priority'] ?? 3);
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    $priority = max(1, min(5, $priority));
    try {
        $pdo->prepare("UPDATE ads SET priority = ? WHERE id = ?")->execute([$priority, $id]);
        echo json_encode(['success' => true, 'priority' => $priority]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ UPDATE PLACEMENT ━━━
if ($action === 'update_placement' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $placement = $_POST['placement'] ?? 'all_pages';
    $allowedPlacements = ['all_pages','homepage_hero','homepage_top','homepage_middle','homepage_bottom','sidebar_top','sidebar_middle','sidebar_bottom','dashboard_top','dashboard_middle','dashboard_bottom','partner_page','developers_hub','services_page','portfolio_page','mobile_top','mobile_bottom','popup_ad','floating_ad'];
    if (!in_array($placement, $allowedPlacements)) $placement = 'all_pages';
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    try {
        $pdo->prepare("UPDATE ads SET placement = ? WHERE id = ?")->execute([$placement, $id]);
        echo json_encode(['success' => true, 'placement' => $placement]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ TOGGLE ACTIVE ━━━
if ($action === 'toggle_active' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    try {
        $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $r = $pdo->prepare("SELECT is_active FROM ads WHERE id = ?");
        $r->execute([$id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'is_active' => $row['is_active']]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ DELETE AD ━━━
if ($action === 'delete_ad' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'No ID.']); exit; }
    try {
        $pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ BULK ACTIONS ━━━
if ($action === 'bulk_action' && $method === 'POST') {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $bulk = $_POST['bulk_action'] ?? '';
    if (empty($ids) || !is_array($ids)) { echo json_encode(['error' => 'No ads selected.']); exit; }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        if ($bulk === 'enable') {
            $pdo->prepare("UPDATE ads SET run_status = 1 WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($bulk === 'disable') {
            $pdo->prepare("UPDATE ads SET run_status = 0 WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($bulk === 'delete') {
            $pdo->prepare("DELETE FROM ads WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($bulk === 'feature') {
            $pdo->prepare("UPDATE ads SET featured = 1 WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($bulk === 'unfeature') {
            $pdo->prepare("UPDATE ads SET featured = 0 WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($bulk === 'move_placement') {
            $placement = $_POST['placement'] ?? 'all_pages';
            $pdo->prepare("UPDATE ads SET placement = ? WHERE id IN ($placeholders)")->execute(array_merge([$placement], $ids));
        } elseif ($bulk === 'set_priority') {
            $priority = max(1, min(5, (int)($_POST['priority'] ?? 3)));
            $pdo->prepare("UPDATE ads SET priority = ? WHERE id IN ($placeholders)")->execute(array_merge([$priority], $ids));
        }
        echo json_encode(['success' => true, 'affected' => count($ids)]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ TRACK IMPRESSION ━━━
if ($action === 'track' && $method === 'POST') {
    $adId = (int)($_POST['ad_id'] ?? 0);
    $eventType = $_POST['event_type'] ?? 'view';
    $placement = $_POST['placement'] ?? '';
    if (!$adId || !in_array($eventType, ['view','click'])) { echo json_encode(['error' => 'Invalid.']); exit; }
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pdo->prepare("INSERT INTO ad_impressions (ad_id, user_id, ip_address, user_agent, placement, event_type) VALUES (?,?,?,?,?,?)")
            ->execute([$adId, $userId, $ip, $ua, $placement, $eventType]);

        $col = $eventType === 'view' ? 'total_views' : 'total_clicks';
        $pdo->prepare("UPDATE ads SET $col = $col + 1 WHERE id = ?")->execute([$adId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => 'Failed.']); }
    exit;
}

// ━━━ GET ADS FOR PUBLIC DISPLAY ━━━
if ($action === 'get_ads' && $method === 'GET') {
    $placement = $_GET['placement'] ?? '';
    $device = $_GET['device'] ?? 'all';
    $userRole = $_GET['role'] ?? 'guest';
    $now = date('Y-m-d H:i:s');

    try {
        $sql = "SELECT * FROM ads WHERE run_status = 1 AND is_active = 1
                AND (start_date IS NULL OR start_date <= ?)
                AND (end_date IS NULL OR end_date >= ?)
                AND (max_views IS NULL OR total_views < max_views)";

        $params = [$now, $now];

        if ($placement) {
            $sql .= " AND (placement = ? OR placement = 'all_pages')";
            $params[] = $placement;
        }

        $targetMap = [
            'guest' => ['all','guests'],
            'user' => ['all','users'],
            'developer' => ['all','developers'],
            'partner' => ['all','partners'],
            'agent' => ['all','agents'],
            'admin' => ['all','admins'],
        ];
        $targets = $targetMap[$userRole] ?? ['all'];
        $tPlaceholders = implode(',', array_fill(0, count($targets), '?'));
        $sql .= " AND target_audience IN ($tPlaceholders)";
        $params = array_merge($params, $targets);

        if ($device && $device !== 'all') {
            $sql .= " AND (device_target = 'all' OR device_target = ?)";
            $params[] = $device;
        }

        $sql .= " ORDER BY featured DESC, priority ASC, created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'ads' => $ads]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'ads' => []]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action.']);

// ─── Helper: Calculate ad status ───
function calcAdStatus($ad, $now) {
    if ($ad['run_status'] == 0) return 'disabled';
    if ($ad['is_active'] == 0) return 'paused';
    if (!empty($ad['start_date']) && $ad['start_date'] > $now) return 'scheduled';
    if (!empty($ad['end_date']) && $ad['end_date'] < $now) return 'expired';
    if (!empty($ad['max_views']) && $ad['total_views'] >= $ad['max_views']) return 'expired';
    return 'running';
}
