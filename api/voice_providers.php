<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $data['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// === GET: Fetch providers or settings ===
if ($method === 'GET' && ($action === '' || $action === 'list')) {
    try {
        $providers = $pdo->query("SELECT id, provider_name, provider_type, api_endpoint, api_key, default_model, default_voice, ws_endpoint, is_active, status, extra_config, created_at, updated_at FROM voice_providers ORDER BY is_active DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($providers as &$p) {
            $p['api_key_masked'] = strlen($p['api_key']) > 8 ? substr($p['api_key'], 0, 4) . '********' . substr($p['api_key'], -4) : '****';
            unset($p['api_key']); // Never expose raw API key
        }
        echo json_encode(['success' => true, 'providers' => $providers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === GET: Settings ===
if ($method === 'GET' && $action === 'settings') {
    try {
        $settings = [];
        $rows = $pdo->query("SELECT setting_key, setting_value, setting_type FROM voice_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $val = $r['setting_value'];
            if ($r['setting_type'] === 'boolean') $val = (bool)$val;
            elseif ($r['setting_type'] === 'number') $val = (float)$val;
            $settings[$r['setting_key']] = $val;
        }
        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === Auth check for POST actions ===
if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// === POST: Add provider ===
if ($method === 'POST' && $action === 'add') {

    $name = trim($data['provider_name'] ?? '');
    $type = trim($data['provider_type'] ?? 'stt_tts');
    $endpoint = trim($data['api_endpoint'] ?? '');
    $key = trim($data['api_key'] ?? '');
    $model = trim($data['default_model'] ?? '');
    $voice = trim($data['default_voice'] ?? '');
    $wsEndpoint = trim($data['ws_endpoint'] ?? '');

    if (!$name || !$endpoint || !$key) {
        echo json_encode(['success' => false, 'error' => 'Name, endpoint, and API key are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO voice_providers (provider_name, provider_type, api_endpoint, api_key, default_model, default_voice, ws_endpoint, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'untested')");
        $stmt->execute([$name, $type, $endpoint, $key, $model, $voice, $wsEndpoint]);
        echo json_encode(['success' => true, 'message' => 'Voice provider added successfully.', 'id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST: Edit provider ===
if ($method === 'POST' && $action === 'edit') {

    $id = (int)($data['id'] ?? 0);
    $name = trim($data['provider_name'] ?? '');
    $type = trim($data['provider_type'] ?? 'stt_tts');
    $endpoint = trim($data['api_endpoint'] ?? '');
    $key = trim($data['api_key'] ?? '');
    $model = trim($data['default_model'] ?? '');
    $voice = trim($data['default_voice'] ?? '');
    $wsEndpoint = trim($data['ws_endpoint'] ?? '');

    if (!$id || !$name || !$endpoint) {
        echo json_encode(['success' => false, 'error' => 'ID, name, and endpoint are required.']);
        exit;
    }

    try {
        if (!empty($key) && $key !== '********') {
            $stmt = $pdo->prepare("UPDATE voice_providers SET provider_name=?, provider_type=?, api_endpoint=?, api_key=?, default_model=?, default_voice=?, ws_endpoint=?, status='untested' WHERE id=?");
            $stmt->execute([$name, $type, $endpoint, $key, $model, $voice, $wsEndpoint, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE voice_providers SET provider_name=?, provider_type=?, api_endpoint=?, default_model=?, default_voice=?, ws_endpoint=?, status='untested' WHERE id=?");
            $stmt->execute([$name, $type, $endpoint, $model, $voice, $wsEndpoint, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'Voice provider updated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST: Set active ===
if ($method === 'POST' && $action === 'set_active') {

    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }

    try {
        $providerType = $pdo->prepare("SELECT provider_type FROM voice_providers WHERE id = ?");
        $providerType->execute([$id]);
        $pType = $providerType->fetchColumn() ?: 'stt_tts';

        if (in_array($pType, ['stt', 'stt_tts'])) {
            $pdo->exec("UPDATE voice_providers SET is_active = 0 WHERE provider_type IN ('stt','stt_tts')");
        }
        if (in_array($pType, ['tts', 'stt_tts'])) {
            $pdo->exec("UPDATE voice_providers SET is_active = 0 WHERE provider_type IN ('tts','stt_tts')");
        }
        if ($pType === 's2s') {
            $pdo->exec("UPDATE voice_providers SET is_active = 0 WHERE provider_type = 's2s'");
        }

        $pdo->prepare("UPDATE voice_providers SET is_active = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Active voice provider switched.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST: Set inactive ===
if ($method === 'POST' && $action === 'set_inactive') {

    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }

    try {
        $pdo->prepare("UPDATE voice_providers SET is_active = 0 WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Voice provider deactivated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST: Delete ===
if ($method === 'POST' && $action === 'delete') {

    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }

    try {
        $check = $pdo->prepare("SELECT is_active FROM voice_providers WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete the active provider. Switch first.']);
            exit;
        }
        $pdo->prepare("DELETE FROM voice_providers WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Voice provider deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST: Update settings ===
if ($method === 'POST' && $action === 'update_settings') {

    try {
        $updStmt = $pdo->prepare("UPDATE voice_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($data as $key => $val) {
            if (is_bool($val)) $val = $val ? '1' : '0';
            $updStmt->execute([$val, $key]);
        }
        echo json_encode(['success' => true, 'message' => 'Settings updated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>