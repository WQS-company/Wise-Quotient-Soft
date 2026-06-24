<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid provider ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM voice_providers WHERE id = ?");
    $stmt->execute([$id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$provider) { echo json_encode(['success' => false, 'error' => 'Provider not found']); exit; }

    $providerType = $provider['provider_type'];
    $apiKey = $provider['api_key'];
    $endpoint = $provider['api_endpoint'];
    $model = $provider['default_model'];
    $startTime = microtime(true);

    // Test based on provider type
    if ($providerType === 's2s' && stripos($provider['provider_name'], 'openai') !== false) {
        // OpenAI Realtime: verify API key via models endpoint
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'elevenlabs') !== false) {
        // ElevenLabs: verify API key
        $ch = curl_init('https://api.elevenlabs.io/v1/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['xi-api-key: ' . $apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'deepgram') !== false) {
        // Deepgram: verify API key
        $ch = curl_init('https://api.deepgram.com/v1/projects');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Token ' . $apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'assemblyai') !== false) {
        // AssemblyAI: verify API key
        $ch = curl_init('https://api.assemblyai.com/v2/transcript');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'retell') !== false) {
        // Retell AI: verify API key
        $ch = curl_init('https://api.retellai.com/list-agents');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'vapi') !== false) {
        // Vapi AI: verify API key
        $ch = curl_init('https://api.vapi.ai/assistant');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } elseif (stripos($provider['provider_name'], 'gemini') !== false || stripos($provider['provider_name'], 'google') !== false) {
        // Gemini Live: verify API key
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';

    } else {
        // Generic: send a minimal request to the endpoint
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => 'Hello']], 'max_tokens' => 5]),
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'healthy' : 'failing';
    }

    $duration = round((microtime(true) - $startTime) * 1000);

    // Update provider status
    $pdo->prepare("UPDATE voice_providers SET status = ? WHERE id = ?")->execute([$status, $id]);

    // Log the health check
    try {
        $logStmt = $pdo->prepare("INSERT INTO voice_logs (user_id, provider, request_type, request_data, response_data, status, duration_ms) VALUES (?, ?, 'health', ?, ?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user']['id'],
            $provider['provider_name'],
            json_encode(['endpoint' => $endpoint, 'model' => $model]),
            substr($response ?? '', 0, 2000),
            $status === 'healthy' ? 'success' : 'failed',
            $duration
        ]);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => $status === 'healthy'
            ? "✅ Connection successful! Response in {$duration}ms."
            : "❌ Connection failed (HTTP {$httpCode}). Check API key and endpoint.",
        'duration_ms' => $duration
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>