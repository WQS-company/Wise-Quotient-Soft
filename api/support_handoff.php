<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) { echo json_encode(['error' => 'Not logged in.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Generate ticket number
function generateTicketNumber($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ticket_number, 5) AS UNSIGNED)) FROM support_tickets WHERE ticket_number LIKE 'WQS-%'");
    $maxNum = (int)$stmt->fetchColumn();
    return 'WQS-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);
}

// Create a support ticket from the chatbot (FEATURE 1: Human Agent Escalation)
if ($action === 'create_ticket') {
    $subject = trim($_POST['subject'] ?? $_GET['subject'] ?? 'Support Request from Chat');
    $message = trim($_POST['message'] ?? $_GET['message'] ?? 'User requested human support via chatbot.');

    try {
        $pdo->beginTransaction();
        $ticketNum = generateTicketNumber($pdo);
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, category, priority, status, origin) VALUES (?, ?, ?, 'technical', 'medium', 'waiting', 'bot')");
        $stmt->execute([$userId, $ticketNum, $subject]);
        $ticketId = $pdo->lastInsertId();

        // Insert into ticket_messages (enterprise table)
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'user')")
            ->execute([$ticketId, $userId, $message]);
        // Mirror to support_ticket_replies for backward compat
        $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$ticketId, $userId, $message]);

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket created. A support representative will join shortly...']);

        // Log status
        try {
            $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, note) VALUES (?, ?, NULL, 'waiting', 'Ticket created via chatbot')")
                ->execute([$ticketId, $userId]);
        } catch (Exception $e) { /* table may not exist yet */ }

        // Notify admins/agents (FEATURE 2: Real-Time Agent Notification)
        $userName = $_SESSION['user']['name'] ?? 'A user';
        $adminStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','developer')");
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
        while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
            $notifStmt->execute([$admin['id'], 'New Support Ticket: ' . $ticketNum,
                "User: {$userName}\nSubject: {$subject}\nPriority: Medium\nClick to join this chat."]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'ticket_id' => $ticketId, 'ticket_number' => $ticketNum]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to create ticket.']);
    }
    exit;
}

// Get user's active ticket (unresolved, from bot origin)
if ($action === 'my_active_ticket') {
    // If Admin/Agent, they might be tracking a ticket they are assigned to.
    $stmt = $pdo->prepare("SELECT t.*, 
            a.name AS agent_name, a.picture AS agent_picture,
            u.name AS client_name, u.email AS client_email, u.picture AS client_picture, u.last_login AS client_last_login
        FROM support_tickets t
        LEFT JOIN users a ON t.assigned_to = a.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE (t.user_id = ? OR t.assigned_to = ?) 
          AND t.status NOT IN ('resolved','closed') 
          AND t.origin = 'bot'
        ORDER BY t.created_at DESC LIMIT 1");
    $stmt->execute([$userId, $userId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ticket) {
        echo json_encode(['success' => true, 'ticket' => $ticket]);
    } else {
        // Check if there's a recently resolved ticket (within last 30 min) to show resolution message
        $recentStmt = $pdo->prepare("SELECT t.*, a.name AS agent_name
            FROM support_tickets t
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.user_id = ? AND t.status IN ('resolved','closed') AND t.origin = 'bot'
            ORDER BY t.updated_at DESC LIMIT 1");
        $recentStmt->execute([$userId]);
        $recent = $recentStmt->fetch(PDO::FETCH_ASSOC);
        if ($recent && (time() - strtotime($recent['updated_at'])) < 1800) {
            echo json_encode(['success' => true, 'ticket' => null, 'resolved_ticket' => $recent]);
        } else {
            echo json_encode(['success' => false, 'ticket' => null]);
        }
    }
    exit;
}

// Get replies for a ticket (with ownership check) - uses ticket_messages
if ($action === 'get_replies') {
    $ticketId = (int)($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $chk = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ? AND (user_id = ? OR assigned_to = ? OR ? IN (SELECT id FROM users WHERE role IN ('admin','developer')))");
    $chk->execute([$ticketId, $userId, $userId, $userId]);
    $ticketRow = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$ticketRow) {
        echo json_encode(['error' => 'Unauthorized.']); exit;
    }

    // Use ticket_messages (enterprise) with fallback to support_ticket_replies
    try {
        $rStmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name, u.role AS sender_role, u.picture AS sender_pic
            FROM ticket_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.ticket_id = ?
            ORDER BY m.created_at ASC
        ");
        $rStmt->execute([$ticketId]);
        $messages = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($messages)) {
            echo json_encode(['success' => true, 'replies' => $messages, 'status' => $ticketRow['status']]);
        } else {
            // Fallback to support_ticket_replies
            $rStmt2 = $pdo->prepare("
                SELECT r.*, u.name AS sender_name, u.role AS sender_role, u.picture AS sender_pic
                FROM support_ticket_replies r
                JOIN users u ON r.user_id = u.id
                WHERE r.ticket_id = ?
                ORDER BY r.created_at ASC
            ");
            $rStmt2->execute([$ticketId]);
            echo json_encode(['success' => true, 'replies' => $rStmt2->fetchAll(PDO::FETCH_ASSOC), 'status' => $ticketRow['status']]);
        }
    } catch (Exception $e) {
        // Fallback to support_ticket_replies
        $rStmt = $pdo->prepare("
            SELECT r.*, u.name AS sender_name, u.role AS sender_role, u.picture AS sender_pic
            FROM support_ticket_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
        ");
        $rStmt->execute([$ticketId]);
        echo json_encode(['success' => true, 'replies' => $rStmt->fetchAll(PDO::FETCH_ASSOC), 'status' => $ticketRow['status']]);
    }
    exit;
}

// Send a reply to a ticket (user side)
if ($action === 'send_reply') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if (!$ticketId || !$message) { echo json_encode(['error' => 'Missing fields.']); exit; }

    $chk = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ? AND user_id = ?");
    $chk->execute([$ticketId, $userId]);
    $ticket = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { echo json_encode(['error' => 'Unauthorized.']); exit; }

    try {
        // Insert into ticket_messages
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'user')")
            ->execute([$ticketId, $userId, $message]);
        // Mirror to support_ticket_replies
        $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$ticketId, $userId, $message]);

        // Update status if it was resolved
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $oldStatus = $ticket['status'];
            $pdo->prepare("UPDATE support_tickets SET status = 'reopened', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            try {
                $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, note) VALUES (?, ?, ?, 'reopened', 'User sent new message')")
                    ->execute([$ticketId, $userId, $oldStatus]);
            } catch (Exception $e) {}
        }

        // Notify assigned agent
        if ($ticket['assigned_to']) {
            $tInfo = $pdo->prepare("SELECT subject FROM support_tickets WHERE id = ?");
            $tInfo->execute([$ticketId]);
            $subj = $tInfo->fetchColumn();
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$ticket['assigned_to'], 'New reply on ticket', 'User replied: ' . substr($message, 0, 100)]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to send message.']);
    }
    exit;
}

// Get agent info for user-side header (FEATURE 5: User Sees Real Human Name)
if ($action === 'agent_info') {
    $ticketId = (int)($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.picture, u.role, a.status AS availability
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

// Typing indicator
if ($action === 'typing') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $isTyping = (int)($_POST['is_typing'] ?? 0);
    $displayName = $_POST['display_name'] ?? null;
    $avatarUrl = $_POST['avatar_url'] ?? null;
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }
    try {
        $pdo->prepare("INSERT INTO typing_indicators (ticket_id, user_id, is_typing, display_name, avatar_url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), display_name = VALUES(display_name), avatar_url = VALUES(avatar_url), updated_at = NOW()")
            ->execute([$ticketId, $userId, $isTyping, $displayName, $avatarUrl]);
    } catch (Exception $e) { /* table may not exist */ }
    echo json_encode(['success' => true]);
    exit;
}

// Get typing status (multi-participant)
if ($action === 'get_typing') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }
    try {
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
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'typers' => []]);
    }
    exit;
}

// Admin/Agent: Get all open unassigned tickets (from bot)
if ($action === 'available_tickets') {
    $stmt = $pdo->prepare("SELECT t.*, u.name AS client_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.assigned_to IS NULL AND t.status NOT IN ('resolved','closed') ORDER BY t.created_at ASC");
    $stmt->execute();
    echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Admin/Agent: Get my assigned tickets
if ($action === 'my_tickets') {
    $stmt = $pdo->prepare("SELECT t.*, u.name AS client_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.assigned_to = ? AND t.status NOT IN ('resolved','closed') ORDER BY t.updated_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Admin/Agent: Claim a ticket (FEATURE 4: Agent Takes Ownership)
if ($action === 'claim_ticket') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $stmt = $pdo->prepare("SELECT id, assigned_to, status FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { echo json_encode(['error' => 'Ticket not found.']); exit; }
    if ($ticket['assigned_to'] && $ticket['assigned_to'] != $userId) {
        echo json_encode(['error' => 'Ticket already assigned.']); exit;
    }

    try {
        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'open', updated_at = NOW() WHERE id = ?")->execute([$userId, $ticketId]);

        // Log
        try {
            $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, note) VALUES (?, ?, ?, 'open', ?)")
                ->execute([$ticketId, $userId, $oldStatus, $_SESSION['user']['name'] . ' joined the chat']);
        } catch (Exception $e) {}

        // System message
        $agentName = $_SESSION['user']['name'] ?? 'An agent';
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, $agentName . ' joined the conversation.']);

        // Greeting
        $greeting = '👋 Hello! I\'m <strong>' . $agentName . '</strong>. I\'ll be assisting you. How can I help?';
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'agent')")
            ->execute([$ticketId, $userId, $greeting]);
        $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$ticketId, $userId, $greeting]);

        // Notify user
        $tInfo = $pdo->prepare("SELECT user_id, ticket_number FROM support_tickets WHERE id = ?");
        $tInfo->execute([$ticketId]);
        $tData = $tInfo->fetch();
        if ($tData) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$tData['user_id'], $agentName . ' is now helping you',
                    'Your ticket ' . $tData['ticket_number'] . ' has been picked up by ' . $agentName . '. Start chatting now!']);
        }

        // Update agent availability
        try {
            $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, 'busy') ON DUPLICATE KEY UPDATE status = 'busy'")
                ->execute([$userId]);
        } catch (Exception $e) {}

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to join chat.']);
    }
    exit;
}

// Admin/Agent: Resolve a ticket (FEATURE 10: Ticket Resolution)
if ($action === 'resolve_ticket') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) { echo json_encode(['error' => 'No ticket ID.']); exit; }

    $stmt = $pdo->prepare("SELECT id, user_id, ticket_number, subject, assigned_to, status FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { echo json_encode(['error' => 'Not found.']); exit; }

    try {
        $oldStatus = $ticket['status'];
        $pdo->prepare("UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);

        // Log
        try {
            $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, note) VALUES (?, ?, ?, 'resolved', ?)")
                ->execute([$ticketId, $userId, $oldStatus, $_SESSION['user']['name'] . ' resolved the ticket']);
        } catch (Exception $e) {}

        // System message
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")
            ->execute([$ticketId, $userId, 'Ticket marked as resolved.']);

        $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
            ->execute([$ticket['user_id'], 'Ticket Resolved: ' . $ticket['ticket_number'],
                'Your support ticket "' . $ticket['subject'] . '" has been resolved. Thanks for your patience!']);

        // Free up agent
        if ($ticket['assigned_to']) {
            try {
                $pdo->prepare("UPDATE agent_availability SET status = 'online' WHERE user_id = ?")->execute([$ticket['assigned_to']]);
            } catch (Exception $e) {}
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to resolve.']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action.']);
