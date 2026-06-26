<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

$user_id = $_SESSION['user']['id'] ?? null;
$session_id = session_id();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';

function generateConversationId() {
    return 'conv_' . bin2hex(random_bytes(16));
}

function getGuestSessionId() {
    if (empty($_SESSION['wb_guest_id'])) {
        $_SESSION['wb_guest_id'] = 'guest_' . bin2hex(random_bytes(8));
    }
    return $_SESSION['wb_guest_id'];
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function verifyCSRF($input) {
    $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {

    case 'create_conversation':
        verifyCSRF($input);
        $topic = mb_strimwidth(sanitize($input['topic'] ?? 'New Conversation'), 0, 100, '...');
        $convId = generateConversationId();
        $guestId = $user_id ? null : getGuestSessionId();

        try {
            $stmt = $pdo->prepare("INSERT INTO bot_chat_sessions (session_id, conversation_id, user_id, topic) VALUES (?, ?, ?, ?)");
            $stmt->execute([$session_id, $convId, $user_id, $topic]);
            jsonResponse(['success' => true, 'conversation_id' => $convId, 'topic' => $topic]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to create conversation'], 500);
        }
        break;

    case 'list_conversations':
        verifyCSRF($input);
        try {
            if ($user_id) {
                $stmt = $pdo->prepare("
                    SELECT conversation_id, topic, created_at, updated_at 
                    FROM bot_chat_sessions 
                    WHERE user_id = ? AND is_deleted_by_user = 0 
                    ORDER BY updated_at DESC LIMIT 50
                ");
                $stmt->execute([$user_id]);
            } else {
                $guestId = getGuestSessionId();
                $stmt = $pdo->prepare("
                    SELECT conversation_id, topic, created_at, updated_at 
                    FROM bot_chat_sessions 
                    WHERE session_id = ? AND user_id IS NULL AND is_deleted_by_user = 0 
                    ORDER BY updated_at DESC LIMIT 50
                ");
                $stmt->execute([$session_id]);
            }
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'conversations' => $conversations]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to load conversations'], 500);
        }
        break;

    case 'get_messages':
        verifyCSRF($input);
        $convId = $input['conversation_id'] ?? '';
        if (empty($convId)) {
            jsonResponse(['success' => false, 'error' => 'Missing conversation_id'], 400);
        }

        try {
            // Verify conversation belongs to current user
            if ($user_id) {
                $check = $pdo->prepare("SELECT 1 FROM bot_chat_sessions WHERE conversation_id = ? AND user_id = ? AND is_deleted_by_user = 0");
                $check->execute([$convId, $user_id]);
            } else {
                $check = $pdo->prepare("SELECT 1 FROM bot_chat_sessions WHERE conversation_id = ? AND session_id = ? AND user_id IS NULL AND is_deleted_by_user = 0");
                $check->execute([$convId, $session_id]);
            }
            if (!$check->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Conversation not found'], 404);
            }

            $offset = max(0, intval($input['offset'] ?? 0));
            $limit = min(100, max(1, intval($input['limit'] ?? 50)));

            $stmt = $pdo->prepare("
                SELECT id, role, message, created_at 
                FROM bot_chats 
                WHERE conversation_id = ? AND is_deleted_by_user = 0 
                ORDER BY created_at ASC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$convId, $limit, $offset]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM bot_chats WHERE conversation_id = ? AND is_deleted_by_user = 0");
            $totalStmt->execute([$convId]);
            $total = $totalStmt->fetchColumn();

            jsonResponse(['success' => true, 'messages' => $messages, 'total' => $total, 'offset' => $offset, 'limit' => $limit]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to load messages'], 500);
        }
        break;

    case 'get_recent_messages':
        verifyCSRF($input);
        try {
            if ($user_id) {
                $stmt = $pdo->prepare("
                    SELECT id, role, message, created_at 
                    FROM bot_chats 
                    WHERE user_id = ? AND is_deleted_by_user = 0 
                    ORDER BY created_at DESC LIMIT 30
                ");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, role, message, created_at 
                    FROM bot_chats 
                    WHERE session_id = ? AND user_id IS NULL AND is_deleted_by_user = 0 
                    ORDER BY created_at DESC LIMIT 30
                ");
                $stmt->execute([$session_id]);
            }
            $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            jsonResponse(['success' => true, 'messages' => $messages]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to load messages'], 500);
        }
        break;

    case 'delete_conversation':
        verifyCSRF($input);
        $convId = $input['conversation_id'] ?? '';
        if (empty($convId)) {
            jsonResponse(['success' => false, 'error' => 'Missing conversation_id'], 400);
        }

        try {
            if ($user_id) {
                $pdo->prepare("UPDATE bot_chat_sessions SET is_deleted_by_user = 1 WHERE conversation_id = ? AND user_id = ?")->execute([$convId, $user_id]);
                $pdo->prepare("UPDATE bot_chats SET is_deleted_by_user = 1 WHERE conversation_id = ? AND user_id = ?")->execute([$convId, $user_id]);
            } else {
                $pdo->prepare("UPDATE bot_chat_sessions SET is_deleted_by_user = 1 WHERE conversation_id = ? AND session_id = ? AND user_id IS NULL")->execute([$convId, $session_id]);
                $pdo->prepare("UPDATE bot_chats SET is_deleted_by_user = 1 WHERE conversation_id = ? AND session_id = ? AND user_id IS NULL")->execute([$convId, $session_id]);
            }
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to delete'], 500);
        }
        break;

    case 'update_topic':
        verifyCSRF($input);
        $convId = $input['conversation_id'] ?? '';
        $topic = mb_strimwidth(sanitize($input['topic'] ?? 'New Conversation'), 0, 100, '...');
        if (empty($convId)) {
            jsonResponse(['success' => false, 'error' => 'Missing conversation_id'], 400);
        }

        try {
            if ($user_id) {
                $pdo->prepare("UPDATE bot_chat_sessions SET topic = ? WHERE conversation_id = ? AND user_id = ?")->execute([$topic, $convId, $user_id]);
            } else {
                $pdo->prepare("UPDATE bot_chat_sessions SET topic = ? WHERE conversation_id = ? AND session_id = ? AND user_id IS NULL")->execute([$topic, $convId, $session_id]);
            }
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to update topic'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
