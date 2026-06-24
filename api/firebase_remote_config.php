<?php
// Firebase Remote Config + A/B Testing API
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$action = $_REQUEST['action'] ?? '';

// Public endpoint: fetch all active config params + A/B test assignments
if ($action === 'get_config') {
    $params = [];
    try {
        $rows = $pdo->query("SELECT param_key, param_value, param_type FROM remote_config_params WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $val = $r['param_value'];
            switch ($r['param_type']) {
                case 'boolean': $val = (bool)$val; break;
                case 'number':  $val = (float)$val; break;
                case 'json':    $val = json_decode($val, true); break;
            }
            $params[$r['param_key']] = $val;
        }
    } catch (Exception $e) {}

    // Get A/B test assignments for current session
    $sessionId = session_id();
    $userId = $_SESSION['user']['id'] ?? null;
    $abAssignments = [];
    try {
        $abTests = $pdo->query("SELECT id, param_key, variants, traffic_pct FROM ab_tests WHERE status = 'running'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($abTests as $test) {
            // Check if already assigned
            $stmt = $pdo->prepare("SELECT variant FROM ab_test_assignments WHERE test_id = ? AND (session_id = ? OR user_id = ?)");
            $stmt->execute([$test['id'], $sessionId, $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $abAssignments[$test['param_key']] = $existing['variant'];
            } else {
                // Check traffic allocation
                if (mt_rand(1, 100) > $test['traffic_pct']) continue;

                // Assign randomly to a variant
                $variants = json_decode($test['variants'], true);
                if (empty($variants)) continue;
                $variantKeys = array_keys($variants);
                $chosen = $variantKeys[array_rand($variantKeys)];

                // Store assignment
                $ins = $pdo->prepare("INSERT IGNORE INTO ab_test_assignments (test_id, session_id, user_id, variant) VALUES (?, ?, ?, ?)");
                $ins->execute([$test['id'], $sessionId, $userId, $chosen]);

                $abAssignments[$test['param_key']] = $chosen;

                // Override config param with variant value
                if (isset($variants[$chosen])) {
                    $params[$test['param_key']] = $variants[$chosen];
                }
            }
        }
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'config' => $params, 'ab_tests' => $abAssignments]);
    exit;
}

// Public endpoint: log A/B test conversion
if ($action === 'convert') {
    $testId = (int)($_POST['test_id'] ?? 0);
    $sessionId = session_id();
    $userId = $_SESSION['user']['id'] ?? null;

    if (!$testId) {
        echo json_encode(['success' => false, 'message' => 'Missing test_id']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE ab_test_assignments SET converted = 1, converted_at = NOW() WHERE test_id = ? AND (session_id = ? OR user_id = ?) AND converted = 0");
        $stmt->execute([$testId, $sessionId, $userId]);
        echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Public endpoint: log A/B test conversion by param_key (client-friendly)
if ($action === 'convert_test') {
    $paramKey = trim($_POST['test_param_key'] ?? '');
    $sessionId = session_id();
    $userId = $_SESSION['user']['id'] ?? null;

    if (!$paramKey) {
        echo json_encode(['success' => false, 'message' => 'Missing test_param_key']);
        exit;
    }

    try {
        // Find the test by param_key
        $stmt = $pdo->prepare("SELECT id FROM ab_tests WHERE param_key = ?");
        $stmt->execute([$paramKey]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$test) {
            echo json_encode(['success' => false, 'message' => 'Test not found']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE ab_test_assignments SET converted = 1, converted_at = NOW() WHERE test_id = ? AND (session_id = ? OR user_id = ?) AND converted = 0");
        $stmt->execute([$test['id'], $sessionId, $userId]);
        echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin endpoints require authentication
if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Admin: list all config params
if ($action === 'list_params') {
    try {
        $rows = $pdo->query("SELECT * FROM remote_config_params ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: create/update config param
if ($action === 'save_param') {
    $id = (int)($_POST['id'] ?? 0);
    $key = trim($_POST['param_key'] ?? '');
    $value = $_POST['param_value'] ?? '';
    $type = $_POST['param_type'] ?? 'string';
    $desc = trim($_POST['description'] ?? '');
    $default = $_POST['default_value'] ?? '';
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (!$key) {
        echo json_encode(['success' => false, 'message' => 'Param key required']);
        exit;
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE remote_config_params SET param_key=?, param_value=?, param_type=?, description=?, default_value=?, is_active=? WHERE id=?");
            $stmt->execute([$key, $value, $type, $desc, $default, $active, $id]);
        } else {
            // Check unique key
            $chk = $pdo->prepare("SELECT COUNT(*) FROM remote_config_params WHERE param_key = ?");
            $chk->execute([$key]);
            if ($chk->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Param key already exists']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO remote_config_params (param_key, param_value, param_type, description, default_value, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$key, $value, $type, $desc, $default, $active]);
        }
        echo json_encode(['success' => true, 'id' => $id > 0 ? $id : $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: delete config param
if ($action === 'delete_param') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM remote_config_params WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: list all A/B tests
if ($action === 'list_tests') {
    try {
        $rows = $pdo->query("SELECT t.*, 
            (SELECT COUNT(*) FROM ab_test_assignments WHERE test_id = t.id) as total_assigned,
            (SELECT COUNT(*) FROM ab_test_assignments WHERE test_id = t.id AND converted = 1) as total_converted
            FROM ab_tests t ORDER BY t.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: create/update A/B test
if ($action === 'save_test') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $paramKey = trim($_POST['param_key'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $traffic = (int)($_POST['traffic_pct'] ?? 100);
    $variants = $_POST['variants'] ?? '{}';

    if (!$name || !$paramKey) {
        echo json_encode(['success' => false, 'message' => 'Name and param key required']);
        exit;
    }

    if (is_string($variants)) $variants = json_decode($variants, true);
    $variantsJson = json_encode($variants);

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ab_tests SET name=?, description=?, param_key=?, status=?, traffic_pct=?, variants=? WHERE id=?");
            $stmt->execute([$name, $desc, $paramKey, $status, $traffic, $variantsJson, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ab_tests (name, description, param_key, status, traffic_pct, variants) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $paramKey, $status, $traffic, $variantsJson]);
            $id = $pdo->lastInsertId();
        }
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: delete A/B test
if ($action === 'delete_test') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM ab_test_assignments WHERE test_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ab_tests WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Admin: get A/B test detail with variant breakdown
if ($action === 'get_test_detail') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $test = $pdo->prepare("SELECT * FROM ab_tests WHERE id = ?");
        $test->execute([$id]);
        $test = $test->fetch(PDO::FETCH_ASSOC);
        if (!$test) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        $variants = json_decode($test['variants'], true);
        $variantStats = [];
        foreach (array_keys($variants) as $v) {
            $assigned = $pdo->prepare("SELECT COUNT(*) FROM ab_test_assignments WHERE test_id = ? AND variant = ?");
            $assigned->execute([$id, $v]);
            $total = $assigned->fetchColumn();

            $converted = $pdo->prepare("SELECT COUNT(*) FROM ab_test_assignments WHERE test_id = ? AND variant = ? AND converted = 1");
            $converted->execute([$id, $v]);
            $conv = $converted->fetchColumn();

            $variantStats[$v] = [
                'assigned' => $total,
                'converted' => $conv,
                'rate' => $total > 0 ? round(($conv / $total) * 100, 1) : 0,
            ];
        }

        echo json_encode(['success' => true, 'data' => $test, 'variant_stats' => $variantStats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
