<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Access control
if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE id = ?");
    $stmt->execute([$id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        exit;
    }

    if (empty($provider['api_key'])) {
        $pdo->prepare("UPDATE ai_providers SET status = 'failing' WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => false, 'error' => 'API Key is missing']);
        exit;
    }

    $endpoint = $provider['api_endpoint'];
    $apiKey = $provider['api_key'];
    $model = $provider['default_model'];

    // Send a very small test prompt
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello']
        ],
        'max_tokens' => 5
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode >= 400 || !$resp) {
        $pdo->prepare("UPDATE ai_providers SET status = 'failing' WHERE id = ?")->execute([$id]);
        $errorMsg = $err ? $err : "HTTP $httpCode: $resp";
        echo json_encode(['success' => false, 'error' => "API Request Failed - $errorMsg"]);
        exit;
    }

    $dec = json_decode($resp, true);
    if (!isset($dec['choices'])) {
        $pdo->prepare("UPDATE ai_providers SET status = 'failing' WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => false, 'error' => "Invalid API response format (missing choices)"]);
        exit;
    }

    // Success
    $pdo->prepare("UPDATE ai_providers SET status = 'healthy' WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
