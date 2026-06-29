<?php
/**
 * API: Email from Chat
 * Send branded emails from bot conversations
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user']['id'];

if ($action === 'send_chat_transcript') {
    // Send chat transcript to user's email
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'error' => 'User not found']); exit; }

        $sessionId = $_POST['session_id'] ?? session_id();
        $chatStmt = $pdo->prepare("SELECT role, message, created_at FROM bot_chats WHERE user_id = ? AND session_id = ? ORDER BY created_at ASC");
        $chatStmt->execute([$userId, $sessionId]);
        $messages = $chatStmt->fetchAll(PDO::FETCH_ASSOC);

        $html = "<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;}.header{background:linear-gradient(135deg,#0A2D5E,#1e3a5f);color:#fff;padding:20px;border-radius:12px 12px 0 0;}.msg{margin:12px 0;padding:10px 14px;border-radius:10px;font-size:14px;line-height:1.5;}.msg.bot{background:#f1f5f9;border-left:3px solid #3b82f6;}.msg.user{background:#eff6ff;border-left:3px solid #0A2D5E;margin-left:20px;}.role{font-weight:700;font-size:12px;text-transform:uppercase;margin-bottom:4px;}.time{font-size:11px;color:#94a3b8;}.footer{text-align:center;padding:16px;color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;}</style></head><body>";
        $html .= "<div class='header'><h2 style='margin:0;'>📋 Chat Transcript</h2><p style='margin:4px 0 0;opacity:0.8;'>Wise Quotient Soft</p></div>";
        $html .= "<p style='color:#64748b;font-size:13px;'>Hi " . htmlspecialchars($user['name']) . ", here is your chat transcript:</p>";

        foreach ($messages as $m) {
            $roleClass = $m['role'] === 'bot' ? 'bot' : 'user';
            $roleLabel = $m['role'] === 'bot' ? '🤖 WiseBot' : '👤 You';
            $html .= "<div class='msg {$roleClass}'><div class='role'>{$roleLabel}</div>" . nl2br(htmlspecialchars($m['message'])) . "<div class='time'>" . date('M d, Y g:i A', strtotime($m['created_at'])) . "</div></div>";
        }

        $html .= "<div class='footer'>Wise Quotient Soft &copy; " . date('Y') . " — This transcript was generated automatically.</div></body></html>";

        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Wise Quotient Soft <noreply@wisequotient.com>\r\n";
        $sent = @mail($user['email'], "Your WQS Chat Transcript — " . date('M d, Y'), $html, $headers);

        echo json_encode(['success' => $sent, 'message' => $sent ? 'Transcript emailed to ' . $user['email'] : 'Failed to send']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'send_custom_email') {
    $to = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (empty($to) || empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']); exit;
    }

    $html = "<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;}</style></head><body>";
    $html .= "<div style='background:linear-gradient(135deg,#0A2D5E,#1e3a5f);color:#fff;padding:20px;border-radius:12px 12px 0 0;'><h2 style='margin:0;'>Wise Quotient Soft</h2></div>";
    $html .= "<div style='padding:20px 0;'>" . nl2br(htmlspecialchars($body)) . "</div>";
    $html .= "<div style='text-align:center;padding:16px;color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;'>Sent via WQS Bot</div></body></html>";

    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Wise Quotient Soft <noreply@wisequotient.com>\r\n";
    $sent = @mail($to, $subject, $html, $headers);

    echo json_encode(['success' => $sent]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
