<?php
/**
 * API: WhatsApp Bridge (via Termii)
 * Continues bot conversation on WhatsApp
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

$action = $_POST['action'] ?? '';

if ($action === 'send_whatsapp') {
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Phone and message required']); exit;
    }

    // Normalize phone
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '234') !== 0 && strlen($digits) >= 10) {
        $digits = '234' . ltrim($digits, '0');
    }

    // Try Termii WhatsApp channel
    $termiiKey = $_ENV['TERMII_API_KEY'] ?? '';
    $termiiFrom = $_ENV['TERMII_SENDER_ID'] ?? 'WQS';

    if ($termiiKey) {
        $payload = json_encode([
            'to' => $digits,
            'from' => $termiiFrom,
            'sms' => $message,
            'type' => 'whatsapp',
            'api_key' => $termiiKey
        ]);

        $ch = curl_init('https://api.termii.com/api/messaging');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo json_encode(['success' => true, 'channel' => 'whatsapp', 'to' => $digits]);
        } else {
            // Fallback to SMS
            $smsPayload = json_encode([
                'to' => $digits,
                'from' => $termiiFrom,
                'sms' => $message,
                'type' => 'plain',
                'api_key' => $termiiKey
            ]);
            $ch2 = curl_init('https://api.termii.com/api/messaging');
            curl_setopt_array($ch2, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $smsPayload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            $smsResult = curl_exec($ch2);
            curl_close($ch2);
            echo json_encode(['success' => true, 'channel' => 'sms_fallback', 'to' => $digits]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'WhatsApp service not configured']);
    }
} elseif ($action === 'link_session') {
    // Link a chat session to a WhatsApp number for continuity
    $phone = trim($_POST['phone'] ?? '');
    $sessionId = trim($_POST['session_id'] ?? '');

    if (empty($phone) || empty($sessionId)) {
        echo json_encode(['success' => false, 'error' => 'Phone and session required']); exit;
    }

    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '234') !== 0 && strlen($digits) >= 10) {
        $digits = '234' . ltrim($digits, '0');
    }

    try {
        // Store the link in bot_chat_memory for continuity
        $existing = $pdo->prepare("SELECT id FROM bot_chat_memory WHERE session_id = ? AND memory_key = 'whatsapp_number'");
        $existing->execute([$sessionId]);
        if ($existing->fetch()) {
            $pdo->prepare("UPDATE bot_chat_memory SET memory_value = ? WHERE session_id = ? AND memory_key = 'whatsapp_number'")->execute([$digits, $sessionId]);
        } else {
            $pdo->prepare("INSERT INTO bot_chat_memory (user_id, session_id, memory_key, memory_value) VALUES (NULL, ?, 'whatsapp_number', ?)")->execute([$sessionId, $digits]);
        }
        echo json_encode(['success' => true, 'message' => 'WhatsApp session linked. You can now continue this conversation on WhatsApp.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
