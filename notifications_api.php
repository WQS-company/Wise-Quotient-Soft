<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
$userId = $_SESSION['user']['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper: format relative time
if (!function_exists('wqs_time_elapsed')) {
    function wqs_time_elapsed($datetime) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        $parts = [];
        foreach (['y'=>'year','m'=>'month','w'=>'week','d'=>'day','h'=>'hour','i'=>'minute','s'=>'second'] as $k => $v) {
            if ($diff->$k) { $parts[] = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); break; }
        }
        return $parts ? implode(', ', $parts) . ' ago' : 'just now';
    }
}

// Helper: get notification type icon
if (!function_exists('wqs_notif_icon')) {
    function wqs_notif_icon($type, $title) {
        $icons = [
            'project' => ['icon' => 'fa-folder-open', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
            'invoice' => ['icon' => 'fa-file-invoice-dollar', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
            'payment' => ['icon' => 'fa-naira-sign', 'color' => '#16a34a', 'bg' => '#f0fdf4'],
            'partner' => ['icon' => 'fa-handshake', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
            'meeting' => ['icon' => 'fa-calendar-check', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
            'message' => ['icon' => 'fa-comment-dots', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
            'announcement' => ['icon' => 'fa-bullhorn', 'color' => '#f97316', 'bg' => '#fff7ed'],
            'scholarship' => ['icon' => 'fa-graduation-cap', 'color' => '#6366f1', 'bg' => '#eef2ff'],
            'support' => ['icon' => 'fa-headset', 'color' => '#14b8a6', 'bg' => '#f0fdfa'],
            'welcome' => ['icon' => 'fa-door-open', 'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
            'success' => ['icon' => 'fa-circle-check', 'color' => '#16a34a', 'bg' => '#f0fdf4'],
            'warning' => ['icon' => 'fa-triangle-exclamation', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
            'danger' => ['icon' => 'fa-circle-xmark', 'color' => '#ef4444', 'bg' => '#fef2f2'],
            'info' => ['icon' => 'fa-bell', 'color' => '#6b7280', 'bg' => '#f9fafb'],
        ];
        // Try exact type match first
        if (isset($icons[$type])) return $icons[$type];
        // Fallback: keyword matching on title
        $t = strtolower($title);
        if (stripos($t, 'project') !== false || stripos($t, 'request') !== false) return $icons['project'];
        if (stripos($t, 'invoice') !== false) return $icons['invoice'];
        if (stripos($t, 'payment') !== false || stripos($t, 'payout') !== false || stripos($t, 'commission') !== false) return $icons['payment'];
        if (stripos($t, 'partner') !== false || stripos($t, 'referral') !== false) return $icons['partner'];
        if (stripos($t, 'meeting') !== false || stripos($t, 'book') !== false) return $icons['meeting'];
        if (stripos($t, 'message') !== false || stripos($t, 'chat') !== false || stripos($t, 'discussion') !== false) return $icons['message'];
        if (stripos($t, 'scholarship') !== false || stripos($t, 'application') !== false) return $icons['scholarship'];
        if (stripos($t, 'support') !== false || stripos($t, 'ticket') !== false || stripos($t, 'handoff') !== false) return $icons['support'];
        if (stripos($t, 'welcome') !== false) return $icons['welcome'];
        if (stripos($t, 'approve') !== false || stripos($t, 'success') !== false) return $icons['success'];
        if (stripos($t, 'reject') !== false || stripos($t, 'decline') !== false || stripos($t, 'fail') !== false) return $icons['danger'];
        if (stripos($t, 'broadcast') !== false || stripos($t, 'announcement') !== false) return $icons['announcement'];
        return $icons['info'];
    }
}

// Action: count
if ($action === 'count') {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'unread_count' => (int)$row['unread_count']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Action: fetch (for dropdown — lightweight)
if ($action === 'fetch') {
    try {
        $limit = (int) min((int)($_GET['limit'] ?? 20), 50);
        $stmt = $pdo->prepare("SELECT id, title, message, notification_type, target_url, target_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($rows as $row) {
            $icon = wqs_notif_icon($row['notification_type'] ?? 'general', $row['title']);
            $formatted[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['notification_type'] ?? 'general',
                'target_url' => $row['target_url'],
                'target_id' => $row['target_id'],
                'is_read' => (int)$row['is_read'],
                'created_at' => $row['created_at'],
                'time_ago' => wqs_time_elapsed($row['created_at']),
                'icon' => $icon,
            ];
        }

        echo json_encode(['success' => true, 'notifications' => $formatted]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Action: mark_read (all)
if ($action === 'mark_read') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Action: mark_single_read (one notification)
if ($action === 'mark_single_read') {
    $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$notifId) { echo json_encode(['success' => false, 'message' => 'Missing id']); exit; }
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Action: all (full page — paginated, filtered, searchable)
if ($action === 'all') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(20, max(5, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $filter = $_GET['filter'] ?? 'all';
    $search = trim($_GET['search'] ?? '');

    $where = " WHERE user_id = ? ";
    $params = [$userId];

    // Filter
    if ($filter === 'unread') {
        $where .= " AND is_read = 0 ";
    } elseif (in_array($filter, ['project','invoice','payment','partner','meeting','message','announcement','scholarship','support'])) {
        $where .= " AND notification_type = ? ";
        $params[] = $filter;
    }

    // Search
    if (!empty($search)) {
        $where .= " AND (title LIKE ? OR message LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    try {
        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        // Fetch — MariaDB doesn't support placeholders for LIMIT/OFFSET, cast to int and interpolate
        $stmt = $pdo->prepare("SELECT id, title, message, notification_type, target_url, target_id, is_read, created_at FROM notifications $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($rows as $row) {
            $icon = wqs_notif_icon($row['notification_type'] ?? 'general', $row['title']);
            $formatted[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['notification_type'] ?? 'general',
                'target_url' => $row['target_url'],
                'target_id' => $row['target_id'],
                'is_read' => (int)$row['is_read'],
                'created_at' => $row['created_at'],
                'time_ago' => wqs_time_elapsed($row['created_at']),
                'icon' => $icon,
            ];
        }

        echo json_encode([
            'success' => true,
            'notifications' => $formatted,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
