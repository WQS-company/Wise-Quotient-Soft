<?php
/**
 * Enterprise Support Ticket API
 * Unified endpoint for all support operations
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? '';
$userName = $_SESSION['user']['name'] ?? '';
if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not logged in.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper: generate ticket number
function generateTicketNumber($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ticket_number, 5) AS UNSIGNED)) FROM support_tickets WHERE ticket_number LIKE 'WQS-%'");
    $maxNum = (int)$stmt->fetchColumn();
    return 'WQS-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);
}

// Helper: log status change
function logStatusChange($pdo, $ticketId, $userId, $oldStatus, $newStatus, $note = null) {
    $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)")
        ->execute([$ticketId, $userId, $oldStatus, $newStatus, $note]);
}

// Helper: notify multiple users
function notifyUsers($pdo, $userIds, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
    foreach ($userIds as $uid) {
        $stmt->execute([$uid, $title, $message]);
    }
}

// Helper: get admin/agent IDs
function getStaffIds($pdo) {
    return $pdo->query("SELECT id FROM users WHERE role IN ('admin','developer')")->fetchAll(PDO::FETCH_COLUMN);
}

// Helper: get online staff IDs
function getOnlineStaffIds($pdo) {
    $stmt = $pdo->query("SELECT user_id FROM agent_availability WHERE status = 'online' AND user_id IN (SELECT id FROM users WHERE role IN ('admin','developer'))");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 1: Create Ticket (User / WiseBot escalation)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'create_ticket' && $method === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $priority = trim($_POST['priority'] ?? 'medium');
    $origin = trim($_POST['origin'] ?? 'portal');
    $message = trim($_POST['message'] ?? '');

    if (!$subject) { echo json_encode(['error' => 'Subject is required.']); exit; }

    try {
        $pdo->beginTransaction();
        $ticketNum = generateTicketNumber($pdo);
        $pdo->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, description, category, priority, status, origin) VALUES (?, ?, ?, ?, ?, ?, 'waiting', ?)")
            ->execute([$userId, $ticketNum, $subject, $description, $category, $priority, $origin]);
        $ticketId = $pdo->lastInsertId();

        // Insert initial message
        if ($message) {
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'user')")
                ->execute([$ticketId, $userId, $message]);
            // Also mirror to support_ticket_replies for backward compat
            $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
                ->execute([$ticketId, $userId, $message]);
        }

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket created. Waiting for a support agent...']);

        // Log
        logStatusChange($pdo, $ticketId, $userId, null, 'waiting', 'Ticket created');

        // Notify all staff
        $staffIds = getStaffIds($pdo);
        if (!empty($staffIds)) {
            $userName = $_SESSION['user']['name'] ?? 'A user';
            notifyUsers($pdo, $staffIds, 'New Support Ticket: ' . $ticketNum,
                "User: {$userName}\nSubject: {$subject}\nPriority: " . ucfirst($priority) . "\nClick to join this chat.");
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'ticket_id' => $ticketId, 'ticket_number' => $ticketNum]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to create ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 2: Get User's Tickets
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'my_tickets' && $method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, u.name AS agent_name, u.picture AS agent_picture,
                (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id AND m.is_read = 0 AND m.user_id != ?) AS unread_count,
                (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS message_count
            FROM support_tickets t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.user_id = ?
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch tickets.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 3: Get Ticket Messages
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'get_messages' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    // Ownership check
    $chk = $pdo->prepare("SELECT id, assigned_to, status, user_id FROM support_tickets WHERE id = ?");
    $chk->execute([$ticketId]);
    $ticket = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }
    if ($ticket['user_id'] != $userId && $ticket['assigned_to'] != $userId && !in_array($userRole, ['admin','developer'])) {
        echo json_encode(['error' => 'Unauthorized.']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name, u.role AS sender_role, u.picture AS sender_pic
            FROM ticket_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.ticket_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark as read
        $pdo->prepare("UPDATE ticket_messages SET is_read = 1, read_at = NOW() WHERE ticket_id = ? AND user_id != ? AND is_read = 0")
            ->execute([$ticketId, $userId]);

        echo json_encode(['success' => true, 'messages' => $messages, 'status' => $ticket['status']]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load messages.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 4: Send Message (User or Agent)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'send_message' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if (!$ticketId || !$message) { echo json_encode(['error' => 'Missing fields.']); exit; }

    $chk = $pdo->prepare("SELECT id, user_id, assigned_to, status FROM support_tickets WHERE id = ?");
    $chk->execute([$ticketId]);
    $ticket = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }
    if ($ticket['user_id'] != $userId && $ticket['assigned_to'] != $userId && !in_array($userRole, ['admin','developer'])) {
        echo json_encode(['error' => 'Unauthorized.']); exit;
    }

    try {
        $msgType = ($ticket['user_id'] == $userId) ? 'user' : 'agent';

        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, ?)")
            ->execute([$ticketId, $userId, $message, $msgType]);
        // Mirror to support_ticket_replies for backward compat
        $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$ticketId, $userId, $message]);

        // Update ticket status
        if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed') {
            $pdo->prepare("UPDATE support_tickets SET status = 'reopened', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            logStatusChange($pdo, $ticketId, $userId, $ticket['status'], 'reopened', 'User sent a new message');
        } elseif ($msgType === 'user' && $ticket['assigned_to']) {
            $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
        }

        // Clear typing indicator
        $pdo->prepare("UPDATE typing_indicators SET is_typing = 0 WHERE ticket_id = ? AND user_id = ?")->execute([$ticketId, $userId]);

        // Notify the other party
        $notifyUserId = ($msgType === 'user') ? $ticket['assigned_to'] : $ticket['user_id'];
        if ($notifyUserId) {
            $senderName = $userName;
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$notifyUserId, 'New message on ticket', $senderName . ' sent a message on ticket #' . $ticketId]);
        }

        $msgId = $pdo->lastInsertId();
        $senderInfo = $pdo->prepare("SELECT name, role, picture FROM users WHERE id = ?");
        $senderInfo->execute([$userId]);
        $sender = $senderInfo->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $msgId,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'message' => $message,
                'message_type' => $msgType,
                'sender_name' => $sender['name'],
                'sender_role' => $sender['role'],
                'sender_pic' => $sender['picture'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to send message.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 5: Agent Join Chat (Claim Ticket)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'claim_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }
        if ($ticket['assigned_to'] && $ticket['assigned_to'] != $userId) {
            echo json_encode(['error' => 'Ticket already assigned to another agent.']); exit;
        }

        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'open', updated_at = NOW() WHERE id = ?")
            ->execute([$userId, $ticketId]);

        logStatusChange($pdo, $ticketId, $userId, $oldStatus, 'open', $userName . ' joined the chat');

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, $userName . ' joined the conversation.']);

        // Greeting
        $greeting = '👋 Hello! I\'m <strong>' . $userName . '</strong>. I\'ll be assisting you. How can I help?';
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'agent')")
            ->execute([$ticketId, $userId, $greeting]);
        $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$ticketId, $userId, $greeting]);

        // Notify user
        $tInfo = $pdo->prepare("SELECT user_id, ticket_number FROM support_tickets WHERE id = ?");
        $tInfo->execute([$ticketId]);
        $tData = $tInfo->fetch(PDO::FETCH_ASSOC);
        if ($tData) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$tData['user_id'], $userName . ' is now helping you',
                    'Your ticket ' . $tData['ticket_number'] . ' has been picked up by ' . $userName . '.']);
        }

        // Update agent availability
        $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, 'busy') ON DUPLICATE KEY UPDATE status = 'busy'")
            ->execute([$userId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to join chat.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 6: Resolve Ticket
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'resolve_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("SELECT id, user_id, ticket_number, subject, assigned_to, status FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }

        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
        logStatusChange($pdo, $ticketId, $userId, $oldStatus, 'resolved', $userName . ' resolved the ticket');

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket marked as resolved by ' . $userName . '.']);

        // Notify user
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
            ->execute([$ticket['user_id'], 'Ticket Resolved: ' . $ticket['ticket_number'],
                'Your ticket "' . $ticket['subject'] . '" has been resolved by ' . $userName . '.']);

        // Free up agent
        if ($ticket['assigned_to']) {
            $pdo->prepare("UPDATE agent_availability SET status = 'online' WHERE user_id = ?")->execute([$ticket['assigned_to']]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to resolve ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 7: Transfer Ticket
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'transfer_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $transferTo = (int)($_POST['transfer_to'] ?? 0);
    if (!$ticketId || !$transferTo) { echo json_encode(['error' => 'Missing fields.']); exit; }

    try {
        $stmt = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }

        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$transferTo, $ticketId]);
        logStatusChange($pdo, $ticketId, $userId, $oldStatus, $oldStatus, $userName . ' transferred ticket');

        // System message
        $newAgent = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $newAgent->execute([$transferTo]);
        $newAgentName = $newAgent->fetchColumn();
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, $userName . ' transferred this ticket to ' . $newAgentName . '.']);

        // Notify new agent
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
            ->execute([$transferTo, 'Ticket transferred to you', 'You have been assigned ticket #' . $ticketId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to transfer ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 8: User Rate Ticket
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'rate_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    if (!$ticketId || $rating < 1 || $rating > 5) { echo json_encode(['error' => 'Invalid rating.']); exit; }

    try {
        $chk = $pdo->prepare("SELECT id, user_id, status FROM support_tickets WHERE id = ? AND user_id = ?");
        $chk->execute([$ticketId, $userId]);
        if (!$chk->fetch()) { echo json_encode(['error' => 'Unauthorized.']); exit; }

        $pdo->prepare("INSERT INTO ticket_ratings (ticket_id, user_id, rating, feedback) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), feedback = VALUES(feedback)")
            ->execute([$ticketId, $userId, $rating, $feedback]);
        $pdo->prepare("UPDATE support_tickets SET rating = ?, feedback = ? WHERE id = ?")
            ->execute([$rating, $feedback, $ticketId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to rate ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 9: User Reopen Ticket
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'reopen_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $chk = $pdo->prepare("SELECT id, user_id, status, assigned_to FROM support_tickets WHERE id = ? AND user_id = ?");
        $chk->execute([$ticketId, $userId]);
        $ticket = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Unauthorized.']); exit; }

        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET status = 'reopened', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
        logStatusChange($pdo, $ticketId, $userId, $oldStatus, 'reopened', 'User requested more help');

        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket reopened by user.']);

        // Notify assigned agent
        if ($ticket['assigned_to']) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$ticket['assigned_to'], 'Ticket Reopened', 'Ticket #' . $ticketId . ' has been reopened by the user.']);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to reopen ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 10: Close Ticket (after satisfaction)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'close_ticket' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $chk = $pdo->prepare("SELECT id, user_id, status FROM support_tickets WHERE id = ? AND user_id = ?");
        $chk->execute([$ticketId, $userId]);
        $ticket = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Unauthorized.']); exit; }

        $pdo->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
        logStatusChange($pdo, $ticketId, $userId, $ticket['status'], 'closed', 'User confirmed satisfaction');

        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket closed. Thank you for your feedback!']);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to close ticket.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 11: Typing Indicator
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'typing' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $isTyping = (int)($_POST['is_typing'] ?? 0);
    $displayName = $_POST['display_name'] ?? null;
    $avatarUrl = $_POST['avatar_url'] ?? null;
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $pdo->prepare("INSERT INTO typing_indicators (ticket_id, user_id, is_typing, display_name, avatar_url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), display_name = VALUES(display_name), avatar_url = VALUES(avatar_url), updated_at = NOW()")
        ->execute([$ticketId, $userId, $isTyping, $displayName, $avatarUrl]);
    echo json_encode(['success' => true]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 12: Get Typing Status (multi-participant)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'get_typing' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $stmt = $pdo->prepare("
        SELECT t.user_id, t.is_typing, u.name, u.role, u.picture,
               t.display_name, t.avatar_url
        FROM typing_indicators t
        JOIN users u ON t.user_id = u.id
        WHERE t.ticket_id = ? AND t.user_id != ? AND t.is_typing = 1
        AND t.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    $stmt->execute([$ticketId, $userId]);
    $typers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($typers as $t) {
        $result[] = [
            'user_id' => $t['user_id'],
            'name' => $t['display_name'] ?: $t['name'],
            'role' => $t['role'],
            'avatar' => $t['avatar_url'] ?: $t['picture'],
            'is_typing' => (int)$t['is_typing']
        ];
    }
    echo json_encode(['success' => true, 'typers' => $result]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 13: Agent Availability (set status)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'set_availability' && $method === 'POST') {
    $status = trim($_POST['status'] ?? 'offline');
    if (!in_array($status, ['online','offline','busy'])) { echo json_encode(['error' => 'Invalid status.']); exit; }

    $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)")
        ->execute([$userId, $status]);
    echo json_encode(['success' => true]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 14: Get Online Agents
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'online_agents' && $method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.picture, a.status, a.last_seen
        FROM agent_availability a
        JOIN users u ON a.user_id = u.id
        WHERE u.role IN ('admin','developer') AND a.status IN ('online','busy')
        ORDER BY a.status ASC, a.last_seen DESC
    ");
    $stmt->execute();
    echo json_encode(['success' => true, 'agents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 15: Get Agent Info (for user-side header)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'agent_info' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.picture, a.status
        FROM support_tickets t
        JOIN users u ON t.assigned_to = u.id
        LEFT JOIN agent_availability a ON a.user_id = u.id
        WHERE t.id = ? AND t.assigned_to IS NOT NULL
    ");
    $stmt->execute([$ticketId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'agent' => $agent ?: null]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 16: Unread Count (for badge)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'unread_count' && $method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM ticket_messages m
        JOIN support_tickets t ON m.ticket_id = t.id
        WHERE m.user_id != ? AND m.is_read = 0
        AND (t.user_id = ? OR t.assigned_to = ? OR ? IN (SELECT id FROM users WHERE role IN ('admin','developer')))
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'unread' => $count]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 17: Admin Dashboard Stats
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'admin_stats' && $method === 'GET') {
    if (!in_array($userRole, ['admin','developer'])) { echo json_encode(['error' => 'Unauthorized.']); exit; }

    try {
        $stats = [];
        $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn();
        $stats['waiting'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'waiting'")->fetchColumn();
        $stats['open'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn();
        $stats['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending'")->fetchColumn();
        $stats['resolved'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved'")->fetchColumn();
        $stats['closed'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'closed'")->fetchColumn();
        $stats['reopened'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'reopened'")->fetchColumn();
        $stats['unassigned'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE assigned_to IS NULL AND status NOT IN ('resolved','closed')")->fetchColumn();
        $stats['online_agents'] = (int)$pdo->query("SELECT COUNT(*) FROM agent_availability WHERE status = 'online'")->fetchColumn();

        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load stats.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 18: Admin/Agent - All Tickets
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'all_tickets' && $method === 'GET') {
    if (!in_array($userRole, ['admin','developer'])) { echo json_encode(['error' => 'Unauthorized.']); exit; }

    $status = $_GET['status'] ?? 'all';
    $priority = $_GET['priority'] ?? 'all';
    $wheres = [];
    $params = [];
    if ($status !== 'all') { $wheres[] = "t.status = ?"; $params[] = $status; }
    if ($priority !== 'all') { $wheres[] = "t.priority = ?"; $params[] = $priority; }
    $whereSQL = $wheres ? "WHERE " . implode(" AND ", $wheres) : "";

    try {
        $stmt = $pdo->prepare("
            SELECT t.*, u.name AS client_name, u.email AS client_email, u.picture AS client_picture,
                a.name AS agent_name, a.picture AS agent_picture,
                (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS message_count,
                (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id AND m.is_read = 0) AS unread_count
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            $whereSQL
            ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.updated_at DESC
        ");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load tickets.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 19: Agent Heartbeat (keep online)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'heartbeat' && $method === 'POST') {
    if (in_array($userRole, ['admin','developer'])) {
        $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, 'online') ON DUPLICATE KEY UPDATE status = 'online', last_seen = NOW()")
            ->execute([$userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 20: Get Ticket Status History
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'ticket_history' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.name AS user_name
            FROM ticket_status_history h
            JOIN users u ON h.user_id = u.id
            WHERE h.ticket_id = ?
            ORDER BY h.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        echo json_encode(['success' => true, 'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load history.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 21: Join Chat (multi-agent)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'join_chat' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }

        $oldStatus = $ticket['status'];

        // Assign if unassigned
        if (!$ticket['assigned_to']) {
            $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'open', updated_at = NOW() WHERE id = ?")
                ->execute([$userId, $ticketId]);
        }

        // Add as participant
        $pdo->prepare("INSERT INTO ticket_chat_participants (ticket_id, user_id, role, status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active', left_at = NULL")
            ->execute([$ticketId, $userId, $userRole]);

        // Mark as present
        $pdo->prepare("INSERT INTO ticket_chat_presence (ticket_id, user_id, is_present) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_present = 1, last_active = NOW()")
            ->execute([$ticketId, $userId]);

        logStatusChange($pdo, $ticketId, $userId, $oldStatus, $oldStatus === 'waiting' ? 'open' : $oldStatus, $userName . ' joined the chat');

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, $userName . ' joined the conversation.']);

        // Activity log
        $pdo->prepare("INSERT INTO ticket_chat_activity_logs (ticket_id, user_id, action, description) VALUES (?, ?, 'join', ?)")
            ->execute([$ticketId, $userId, $userName . ' joined the chat']);

        // Greeting if first agent
        if (!$ticket['assigned_to']) {
            $greeting = '👋 Hello! I\'m <strong>' . $userName . '</strong>. I\'ll be assisting you. How can I help?';
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'agent')")
                ->execute([$ticketId, $userId, $greeting]);
            $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
                ->execute([$ticketId, $userId, $greeting]);
        }

        // Notify user
        $tInfo = $pdo->prepare("SELECT user_id, ticket_number FROM support_tickets WHERE id = ?");
        $tInfo->execute([$ticketId]);
        $tData = $tInfo->fetch(PDO::FETCH_ASSOC);
        if ($tData) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$tData['user_id'], $userName . ' joined your chat',
                    'Your ticket ' . $tData['ticket_number'] . ' now has ' . $userName . ' helping you.']);
        }

        // Update agent availability
        $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, 'busy') ON DUPLICATE KEY UPDATE status = 'busy'")
            ->execute([$userId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to join chat.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 22: Leave Chat
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'leave_chat' && $method === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        // Mark as left in participants
        $pdo->prepare("UPDATE ticket_chat_participants SET status = 'left', left_at = NOW() WHERE ticket_id = ? AND user_id = ? AND status = 'active'")
            ->execute([$ticketId, $userId]);

        // Mark as not present
        $pdo->prepare("UPDATE ticket_chat_presence SET is_present = 0 WHERE ticket_id = ? AND user_id = ?")
            ->execute([$ticketId, $userId]);

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, $userName . ' left the conversation.']);

        // Activity log
        $pdo->prepare("INSERT INTO ticket_chat_activity_logs (ticket_id, user_id, action, description) VALUES (?, ?, 'leave', ?)")
            ->execute([$ticketId, $userId, $userName . ' left the chat']);

        // Free up agent if was assigned
        $stmt = $pdo->prepare("SELECT assigned_to FROM support_tickets WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$ticketId, $userId]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE support_tickets SET assigned_to = NULL, updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            $pdo->prepare("UPDATE agent_availability SET status = 'online' WHERE user_id = ?")->execute([$userId]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to leave chat.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 23: Get Participants
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'get_participants' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT tcp.user_id, tcp.role, tcp.status, tcp.joined_at, tcp.left_at,
                   u.name, u.picture, u.email,
                   p.is_present, p.last_active
            FROM ticket_chat_participants tcp
            JOIN users u ON tcp.user_id = u.id
            LEFT JOIN ticket_chat_presence p ON p.ticket_id = tcp.ticket_id AND p.user_id = tcp.user_id
            WHERE tcp.ticket_id = ?
            ORDER BY tcp.joined_at ASC
        ");
        $stmt->execute([$ticketId]);
        echo json_encode(['success' => true, 'participants' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load participants.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 24: Get Presence
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'get_presence' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        // Update own presence
        $pdo->prepare("INSERT INTO ticket_chat_presence (ticket_id, user_id, is_present) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_present = 1, last_active = NOW()")
            ->execute([$ticketId, $userId]);

        $stmt = $pdo->prepare("
            SELECT p.user_id, p.is_present, p.last_active, u.name, u.role, u.picture
            FROM ticket_chat_presence p
            JOIN users u ON p.user_id = u.id
            WHERE p.ticket_id = ? AND p.is_present = 1
            AND p.last_active > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        $stmt->execute([$ticketId]);
        echo json_encode(['success' => true, 'present' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load presence.']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FEATURE 25: Get Activity Log
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($action === 'get_activity_log' && $method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.name AS user_name, u.picture AS user_picture
            FROM ticket_chat_activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.ticket_id = ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$ticketId]);
        echo json_encode(['success' => true, 'activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load activity log.']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action.']);
