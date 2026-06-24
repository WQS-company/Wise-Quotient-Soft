<?php
$path_to_root = "../";
$page_title = "Support Ticket Center";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$adminId = $headerUser['id'];

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'get_replies') {
        $ticketId = (int)$_POST['ticket_id'];
        try {
            $r = $pdo->prepare("
                SELECT r.*, u.name AS sender_name, u.role AS sender_role 
                FROM support_ticket_replies r
                JOIN users u ON r.user_id = u.id
                WHERE r.ticket_id = ? ORDER BY r.created_at ASC
            ");
            $r->execute([$ticketId]);
            echo json_encode($r->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reply') {
        $ticketId = (int)$_POST['ticket_id'];
        $message  = trim($_POST['message'] ?? '');
        if ($ticketId && $message) {
            try {
                $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'agent')")->execute([$ticketId, $adminId, $message]);
                $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")->execute([$ticketId, $adminId, $message]);
                $pdo->prepare("UPDATE support_tickets SET status = 'pending', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
                $pdo->prepare("UPDATE typing_indicators SET is_typing = 0 WHERE ticket_id = ? AND user_id = ?")->execute([$ticketId, $adminId]);
                $owner = $pdo->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ?");
                $owner->execute([$ticketId]);
                $t = $owner->fetch();
                if ($t) {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                        ->execute([$t['user_id'], 'Support Reply: ' . $t['subject'], 'An administrator has replied to your support ticket: ' . $t['subject']]);
                }
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Empty message.']);
        }
        exit;
    }

    if ($action === 'leave_chat') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if (!$ticketId) { echo json_encode(['success' => false, 'message' => 'No ticket ID.']); exit; }
        try {
            $pdo->prepare("UPDATE ticket_chat_participants SET status = 'left', left_at = NOW() WHERE ticket_id = ? AND user_id = ? AND status = 'active'")->execute([$ticketId, $adminId]);
            $pdo->prepare("UPDATE ticket_chat_presence SET is_present = 0 WHERE ticket_id = ? AND user_id = ?")->execute([$ticketId, $adminId]);
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'system')")->execute([$ticketId, $adminId, ($headerUser['name'] ?? 'Agent') . ' left the conversation.']);
            $pdo->prepare("INSERT INTO ticket_chat_activity_logs (ticket_id, user_id, action, description) VALUES (?, ?, 'leave', ?)")->execute([$ticketId, $adminId, ($headerUser['name'] ?? 'Agent') . ' left the chat']);
            $stmt = $pdo->prepare("SELECT assigned_to FROM support_tickets WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$ticketId, $adminId]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE support_tickets SET assigned_to = NULL, updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
                $pdo->prepare("UPDATE agent_availability SET status = 'online' WHERE user_id = ?")->execute([$adminId]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to leave chat.']);
        }
        exit;
    }

    if ($action === 'update_status') {
        $ticketId = (int)$_POST['ticket_id'];
        $newStatus = in_array($_POST['status'], ['waiting','open','pending','reopened','resolved','closed']) ? $_POST['status'] : null;
        if ($ticketId && $newStatus) {
            try {
                $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $ticketId]);
                if ($newStatus === 'resolved') {
                    $owner = $pdo->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ?");
                    $owner->execute([$ticketId]);
                    $t = $owner->fetch();
                    if ($t) {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                            ->execute([$t['user_id'], 'Ticket Resolved: ' . $t['subject'], 'Your support ticket "' . $t['subject'] . '" has been marked as resolved.']);
                    }
                }
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        exit;
    }

    if ($action === 'claim_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        if (!$ticketId) { echo json_encode(['success' => false, 'message' => 'No ticket ID.']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND assigned_to IS NULL");
            $stmt->execute([$ticketId]);
            if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Ticket already taken.']); exit; }
            $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'open', updated_at = NOW() WHERE id = ?")->execute([$adminId, $ticketId]);
            $tInfo = $pdo->prepare("SELECT user_id, ticket_number, subject FROM support_tickets WHERE id = ?");
            $tInfo->execute([$ticketId]);
            $tData = $tInfo->fetch();
            $agentName = $headerUser['name'] ?? 'An agent';
            if ($tData) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                    ->execute([$tData['user_id'], $agentName . ' is now helping you', 'Your ticket ' . ($tData['ticket_number']??'') . ' has been picked up by ' . $agentName . '.']);
                $greeting = 'Hello! I\'m <strong>' . $agentName . '</strong>. I\'ll be assisting you. How can I help?';
                $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, message_type) VALUES (?, ?, ?, 'agent')")->execute([$ticketId, $adminId, $greeting]);
                $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")->execute([$ticketId, $adminId, $greeting]);
                $pdo->prepare("INSERT INTO ticket_chat_participants (ticket_id, user_id, role, status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active', left_at = NULL")->execute([$ticketId, $adminId, 'admin']);
                $pdo->prepare("INSERT INTO ticket_chat_presence (ticket_id, user_id, is_present) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_present = 1, last_active = NOW()")->execute([$ticketId, $adminId]);
                $pdo->prepare("INSERT INTO ticket_chat_activity_logs (ticket_id, user_id, action, description) VALUES (?, ?, 'join', ?)")->execute([$ticketId, $adminId, $agentName . ' joined the chat']);
                $pdo->prepare("INSERT INTO agent_availability (user_id, status) VALUES (?, 'busy') ON DUPLICATE KEY UPDATE status = 'busy'")->execute([$adminId]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'unclaim_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        if (!$ticketId) { echo json_encode(['success' => false, 'message' => 'No ticket ID.']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$ticketId, $adminId]);
            if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Not your ticket.']); exit; }
            $pdo->prepare("UPDATE support_tickets SET assigned_to = NULL, status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'resolve_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        if (!$ticketId) { echo json_encode(['success' => false, 'message' => 'No ticket ID.']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT id, user_id, ticket_number, subject, assigned_to FROM support_tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) { echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }

            $pdo->prepare("UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);

            $agentName = $headerUser['name'] ?? 'Support Team';
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$ticket['user_id'], '✅ Ticket Resolved: ' . $ticket['ticket_number'], 'Your ticket "' . $ticket['subject'] . '" has been resolved by ' . $agentName . '. If you need further help, feel free to open a new chat.']);

            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
            if ($ticket['assigned_to'] && $ticket['assigned_to'] != $adminId) {
                $notifStmt->execute([$ticket['assigned_to'], 'Ticket ' . $ticket['ticket_number'] . ' resolved', 'Ticket "' . $ticket['subject'] . '" was resolved by ' . $agentName . '.']);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    exit;
}

// Fetch filters
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all','waiting','open','pending','resolved','closed','reopened'])) $statusFilter = 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
if (!in_array($priorityFilter, ['all','urgent','high','medium','low'])) $priorityFilter = 'all';
$searchQuery = $_GET['search'] ?? '';

$wheres = [];
$params = [];
if ($statusFilter !== 'all') { $wheres[] = "st.status = ?"; $params[] = $statusFilter; }
if ($priorityFilter !== 'all') { $wheres[] = "st.priority = ?"; $params[] = $priorityFilter; }
if ($searchQuery !== '') {
    $wheres[] = "(st.ticket_number LIKE ? OR st.subject LIKE ? OR u.name LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
$whereSQL = $wheres ? "WHERE " . implode(" AND ", $wheres) : "";

try {
    $stmt = $pdo->prepare("
        SELECT st.*, u.name AS client_name, u.email AS client_email, u.picture AS client_picture,
            a.name AS agent_name, a.picture AS agent_picture,
            (SELECT COUNT(*) FROM support_ticket_replies r WHERE r.ticket_id = st.id) AS reply_count
        FROM support_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN users a ON st.assigned_to = a.id
        $whereSQL
        ORDER BY 
            CASE WHEN st.status IN ('open', 'reopened') THEN 1
                 WHEN st.status IN ('waiting', 'pending') THEN 2
                 ELSE 3 END,
            FIELD(st.priority,'urgent','high','medium','low'), 
            st.updated_at DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tickets = [];
}

// Stats
$stats = ['total' => 0, 'waiting' => 0, 'open' => 0, 'pending' => 0, 'resolved' => 0, 'closed' => 0, 'reopened' => 0, 'unassigned' => 0];
try {
    $sr = $pdo->query("SELECT status, COUNT(*) AS cnt FROM support_tickets GROUP BY status");
    while ($row = $sr->fetch()) {
        $stats[$row['status']] = $row['cnt'];
        $stats['total'] += $row['cnt'];
    }
    $stats['unassigned'] = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE assigned_to IS NULL AND status NOT IN ('resolved','closed')")->fetchColumn();
} catch (Exception $e) {}

// Agent availability
$agentStatuses = [];
try {
    $agentStatuses = $pdo->query("SELECT a.user_id, a.status, u.name FROM agent_availability a JOIN users u ON a.user_id = u.id WHERE u.role IN ('admin','developer')")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
:root {
    --sc-primary: #0A2D5E;
    --sc-primary-light: #dbeafe;
    --sc-accent: #1e40af;
    --sc-success: #059669;
    --sc-agent-from: #0A2D5E;
    --sc-agent-to: #163f7a;
    --sc-client-bg: #f1f5f9;
    --sc-system-text: #94a3b8;
    --sc-border: #e2e8f0;
    --sc-bg: #f8fafc;
    --sc-card-bg: #fff;
    --sc-text: #1e293b;
    --sc-text-muted: #64748b;
    --sc-overlay: rgba(15,23,42,0.5);
}

body, .wrapper, .main-wrapper { overflow-x: hidden !important; }

/* ─── Ticket Card Grid ─── */
.ticket-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
}
@media (max-width: 1199.98px) { .ticket-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 991.98px) { .ticket-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px) { .ticket-grid { grid-template-columns: 1fr; } }

.ticket-card {
    background: var(--sc-card-bg);
    border: 1px solid var(--sc-border);
    border-radius: 14px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.ticket-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 14px 14px 0 0;
    background: var(--sc-border);
    transition: background 0.25s ease;
}
.ticket-card[data-priority="urgent"]::before { background: linear-gradient(90deg, #dc2626, #ef4444); }
.ticket-card[data-priority="high"]::before { background: linear-gradient(90deg, #ea580c, #f97316); }
.ticket-card[data-priority="medium"]::before { background: linear-gradient(90deg, #d97706, #eab308); }
.ticket-card[data-priority="low"]::before { background: linear-gradient(90deg, #059669, #10b981); }
.ticket-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
    border-color: var(--sc-primary-light);
}
.ticket-card.active {
    border-color: var(--sc-primary);
    box-shadow: 0 0 0 3px rgba(10, 45, 94, 0.12), 0 8px 24px rgba(0, 0, 0, 0.08);
}

/* ─── Filter Pills ─── */
.filter-pill {
    padding: 0.35rem 1rem; border-radius: 50px; border: 1px solid var(--sc-border);
    background: var(--sc-card-bg); color: var(--sc-text-muted); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all 0.15s;
    display: inline-flex; align-items: center; gap: 0.3rem;
}
.filter-pill:hover { border-color: var(--sc-primary); color: var(--sc-primary); background: #f8fafc; }
.filter-pill.active { background: var(--sc-primary); color: white; border-color: var(--sc-primary); }
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0 6px; border-radius: 50px; font-size: 0.68rem; font-weight: 700;
}
.filter-pill.active .pill-count { background: rgba(255,255,255,0.25); }
.filter-pill:not(.active) .pill-count { background: rgba(0,0,0,0.06); color: inherit; }
.filter-pill.prio-urgent { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }
.filter-pill.prio-urgent.active { background:#dc2626; color:white; border-color:#dc2626; }
.filter-pill.prio-high { background:#fff7ed; color:#ea580c; border-color:#fed7aa; }
.filter-pill.prio-high.active { background:#ea580c; color:white; border-color:#ea580c; }

/* ─── Chat Bubbles ─── */
.chat-bubble {
    max-width: 80%; margin-bottom: 0.85rem; font-size: 0.88rem; line-height: 1.45; word-wrap: break-word;
}
.chat-bubble.admin {
    background: linear-gradient(135deg, var(--sc-agent-from), var(--sc-agent-to));
    color: white; margin-left: auto; border-radius: 16px 16px 4px 16px;
    padding: 12px 16px;
}
.chat-bubble.client {
    background: var(--sc-client-bg); color: var(--sc-text); margin-right: auto; border-radius: 16px 16px 16px 4px;
    padding: 12px 16px;
}
.chat-bubble.system {
    background: transparent; color: var(--sc-system-text); text-align: center; font-style: italic;
    font-size: 0.78rem; max-width: 100%; padding: 8px 16px; border-radius: 0;
    border-left: none; border-right: none; margin-left: auto; margin-right: auto;
}
.chat-bubble .msg-header {
    display: flex; align-items: center; gap: 6px; margin-bottom: 6px;
}
.chat-bubble .msg-avatar {
    width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden; font-size: 0.65rem; font-weight: 700;
}
.chat-bubble.admin .msg-avatar { background: rgba(255,255,255,0.2); color: white; }
.chat-bubble.client .msg-avatar { background: var(--sc-primary-light); color: var(--sc-primary); }
.chat-bubble .msg-name { font-size: 0.72rem; font-weight: 700; opacity: 0.85; }
.chat-bubble .msg-role {
    font-size: 0.6rem; font-weight: 700; padding: 1px 6px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.3px;
}
.chat-bubble.admin .msg-role { background: rgba(255,255,255,0.2); color: white; }
.chat-bubble.client .msg-role { background: var(--sc-primary-light); color: var(--sc-primary); }
.chat-bubble .msg-time { font-size: 0.62rem; opacity: 0.55; margin-top: 6px; text-align: right; }

/* ─── Typing Indicators ─── */
.typing-indicator {
    display: flex; align-items: center; gap: 8px; padding: 8px 14px; margin: 6px 0;
    font-size: 0.78rem; color: var(--sc-system-text); background: rgba(148,163,184,0.08);
    border-radius: 16px; width: fit-content; animation: fadeIn 0.3s ease;
}
.typing-indicator .dots {
    display: flex; gap: 3px;
}
.typing-indicator .dots span {
    width: 6px; height: 6px; border-radius: 50%; background: var(--sc-primary);
    animation: typingBounce 1s infinite alternate;
}
.typing-indicator .dots span:nth-child(2) { animation-delay: 0.15s; }
.typing-indicator .dots span:nth-child(3) { animation-delay: 0.3s; }
.typing-indicator .typer-name { font-weight: 600; color: var(--sc-text); }
@keyframes typingBounce {
    from { transform: translateY(0); opacity: 0.3; }
    to { transform: translateY(-8px); opacity: 1; }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ─── Join Chat Button ─── */
.join-chat-btn, .join-chat-btn-header { transition: all 0.25s ease; cursor: pointer; }
.join-chat-btn:hover, .join-chat-btn-header:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(5,150,105,0.4) !important;
}
.join-chat-btn-header { animation: pulse-join 2s infinite; }
@keyframes pulse-join {
    0%, 100% { box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
    50% { box-shadow: 0 2px 20px rgba(5,150,105,0.5); }
}
@keyframes typingDot {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-5px); opacity: 1; }
}

/* ─── Resolve Button ─── */
.resolve-btn { transition: all 0.25s ease; cursor: pointer; }
.resolve-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(22,163,74,0.4) !important;
}

/* ─── Conversation Modal ─── */
.chat-modal-overlay {
    position: fixed; inset: 0; z-index: 1050;
    background: var(--sc-overlay); backdrop-filter: blur(4px);
    opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
}
.chat-modal-overlay.open { opacity: 1; pointer-events: all; }

.chat-modal {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 680px; max-width: 100vw; z-index: 1060;
    background: var(--sc-card-bg); box-shadow: -8px 0 40px rgba(0, 0, 0, 0.15);
    transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column;
}
.chat-modal.open { transform: translateX(0); }
@media (max-width: 575.98px) { .chat-modal { width: 100vw; } }

/* Modal Header */
.chat-modal-header {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--sc-border);
    display: flex; align-items: center; gap: 0.75rem;
    background: var(--sc-card-bg); flex-shrink: 0;
}
.chat-modal-header .header-info { flex: 1; min-width: 0; }
.chat-modal-header .header-info .ticket-label {
    font-size: 0.7rem; font-weight: 700; color: var(--sc-primary); letter-spacing: 0.3px;
}
.chat-modal-header .header-info .ticket-subject {
    font-size: 0.92rem; font-weight: 700; color: var(--sc-text); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.chat-modal-header .header-info .ticket-meta {
    font-size: 0.72rem; color: var(--sc-text-muted);
}
.header-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.header-actions .action-btn {
    width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--sc-border);
    background: var(--sc-card-bg); color: var(--sc-text-muted); display: flex;
    align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s;
    font-size: 0.75rem; position: relative;
}
.header-actions .action-btn:hover { background: var(--sc-bg); color: var(--sc-primary); border-color: var(--sc-primary); }
.header-actions .action-btn.active { background: var(--sc-primary); color: white; border-color: var(--sc-primary); }
.header-actions .action-btn .notif-dot {
    position: absolute; top: 2px; right: 2px; width: 7px; height: 7px;
    border-radius: 50%; background: #dc2626; border: 1.5px solid white;
}
.presence-dots { display: flex; gap: -4px; }
.presence-dot {
    width: 10px; height: 10px; border-radius: 50%; border: 2px solid white;
    margin-left: -4px; flex-shrink: 0;
}
.presence-dot.online { background: #22c55e; }
.presence-dot.away { background: #eab308; }
.presence-dot.offline { background: #94a3b8; }
.participant-count {
    font-size: 0.68rem; font-weight: 700; background: var(--sc-primary-light);
    color: var(--sc-primary); padding: 2px 8px; border-radius: 50px;
}

/* Modal Body */
.chat-modal-body {
    flex: 1; overflow-y: auto; padding: 1.25rem;
    background: var(--sc-bg); position: relative;
}

/* Modal Footer */
.chat-modal-footer {
    padding: 0.85rem 1.25rem; border-top: 1px solid var(--sc-border);
    background: var(--sc-card-bg); flex-shrink: 0;
}

/* ─── Participants Panel ─── */
.participants-panel {
    width: 0; overflow: hidden; transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
    border-left: 1px solid var(--sc-border); background: var(--sc-card-bg); flex-shrink: 0;
}
.participants-panel.open { width: 220px; }
@media (max-width: 767.98px) { .participants-panel.open { width: 180px; } }
.participants-panel-inner { width: 220px; padding: 1rem; overflow-y: auto; height: 100%; }
@media (max-width: 767.98px) { .participants-panel-inner { width: 180px; } }
.participants-panel-title {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--sc-text-muted); margin-bottom: 0.75rem;
}
.participant-item {
    display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px;
    margin-bottom: 4px; transition: background 0.15s;
}
.participant-item:hover { background: var(--sc-bg); }
.participant-avatar {
    width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; overflow: hidden; font-size: 0.65rem;
    font-weight: 700; position: relative;
}
.participant-avatar .online-indicator {
    position: absolute; bottom: -1px; right: -1px; width: 9px; height: 9px;
    border-radius: 50%; border: 2px solid white;
}
.participant-avatar .online-indicator.online { background: #22c55e; }
.participant-avatar .online-indicator.away { background: #eab308; }
.participant-avatar .online-indicator.offline { background: #94a3b8; }
.participant-info { min-width: 0; }
.participant-name { font-size: 0.78rem; font-weight: 600; color: var(--sc-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.participant-role { font-size: 0.62rem; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; }

/* ─── Activity Log Panel ─── */
.activity-panel {
    max-height: 0; overflow: hidden; transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1);
    border-top: 1px solid var(--sc-border); background: var(--sc-card-bg);
}
.activity-panel.open { max-height: 250px; }
.activity-panel-inner { padding: 1rem 1.25rem; overflow-y: auto; max-height: 220px; }
.activity-panel-title {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--sc-text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;
}
.activity-timeline { position: relative; padding-left: 20px; }
.activity-timeline::before {
    content: ''; position: absolute; left: 6px; top: 0; bottom: 0;
    width: 2px; background: var(--sc-border);
}
.activity-entry {
    position: relative; padding: 4px 0 12px 0; font-size: 0.75rem; color: var(--sc-text-muted);
}
.activity-entry::before {
    content: ''; position: absolute; left: -17px; top: 8px;
    width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--sc-border);
    background: var(--sc-card-bg);
}
.activity-entry.join::before { background: #22c55e; border-color: #22c55e; }
.activity-entry.leave::before { background: #94a3b8; border-color: #94a3b8; }
.activity-entry.message::before { background: var(--sc-accent); border-color: var(--sc-accent); }
.activity-entry.status::before { background: #eab308; border-color: #eab308; }
.activity-entry .entry-time { font-size: 0.65rem; color: var(--sc-text-muted); opacity: 0.7; }
.activity-entry .entry-text { color: var(--sc-text); font-weight: 500; }

/* ─── Stats Card ─── */
.stat-card {
    border-radius: 14px; padding: 1.15rem 1.25rem;
    display: flex; align-items: center; gap: 0.85rem;
    transition: all 0.2s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06); }
.stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; flex-shrink: 0; font-size: 1rem;
}

/* ─── Date Separator ─── */
.date-separator {
    display: flex; align-items: center; gap: 12px; margin: 16px 0; color: var(--sc-text-muted);
    font-size: 0.7rem; font-weight: 600;
}
.date-separator::before, .date-separator::after {
    content: ''; flex: 1; height: 1px; background: var(--sc-border);
}

/* ─── Empty State ─── */
.empty-chat-state {
    text-align: center; padding: 3rem 1rem; max-width: 320px; margin: 0 auto;
}
.empty-chat-state .empty-icon {
    width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 1rem;
    display: flex; align-items: center; justify-content: center;
}
.empty-chat-state h6 { font-weight: 700; margin-bottom: 0.5rem; }
.empty-chat-state p { color: var(--sc-text-muted); font-size: 0.82rem; line-height: 1.5; margin-bottom: 1.2rem; }
</style>

<!-- PAGE HEADER -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge" style="background:linear-gradient(135deg,#0f172a,#1e40af);color:white;font-size:0.65rem;font-weight:700;padding:0.25rem 0.75rem;border-radius:50px;text-transform:uppercase;letter-spacing:0.5px;">
        <i class="fas fa-headset me-1"></i> Support Center
      </span>
    </div>
    <h3 class="fw-bold text-body mb-0">Support Ticket Center</h3>
    <p class="text-muted mb-0 mt-1" style="font-size:0.88rem;">Manage, respond, and resolve client support tickets in real time.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-shrink-0">
    <span class="badge px-3 py-2 rounded-pill" style="background:linear-gradient(135deg,#0f172a,#1e40af);color:white;font-weight:700;">
      <i class="fas fa-ticket-alt me-1"></i> <?= $stats['total'] ?> Total
    </span>
  </div>
</div>

<!-- STATS ROW -->
<div class="row g-3 mb-4">
  <?php
  $statCards = [
    ['Total','fas fa-ticket-alt','#eff6ff','#1d4ed8',$stats['total']],
    ['Waiting','fas fa-hourglass-half','#fef3c7','#d97706',$stats['waiting']],
    ['Active','fas fa-comments-dots','#dbeafe','#1e40af',$stats['open']],
    ['Resolved','fas fa-check-circle','#f0fdf4','#15803d',$stats['resolved']],
  ];
  foreach ($statCards as [$label, $icon, $bg, $color, $count]):
  ?>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="background:<?= $bg ?>;border:1px solid <?= $color ?>15;">
      <div class="stat-icon" style="background:<?= $color ?>;">
        <i class="<?= $icon ?>"></i>
      </div>
      <div>
        <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;line-height:1;"><?= $count ?></div>
        <div style="font-size:0.75rem;font-weight:600;color:<?= $color ?>;opacity:0.8;"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- FILTER BAR -->
<div class="card-theme mb-4">
  <div class="card-theme-body p-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <span class="text-muted small fw-semibold me-1">Status:</span>
        <a href="?status=all" class="filter-pill <?= $statusFilter==='all'?'active':'' ?>">All <span class="pill-count"><?= $stats['total'] ?></span></a>
        <a href="?status=waiting" class="filter-pill <?= $statusFilter==='waiting'?'active':'' ?>">Waiting <span class="pill-count"><?= $stats['waiting'] ?></span></a>
        <a href="?status=open" class="filter-pill <?= $statusFilter==='open'?'active':'' ?>">Open <span class="pill-count"><?= $stats['open'] ?></span></a>
        <a href="?status=pending" class="filter-pill <?= $statusFilter==='pending'?'active':'' ?>">Pending <span class="pill-count"><?= $stats['pending'] ?></span></a>
        <a href="?status=reopened" class="filter-pill <?= $statusFilter==='reopened'?'active':'' ?>">Reopened <span class="pill-count"><?= $stats['reopened'] ?></span></a>
        <a href="?status=resolved" class="filter-pill <?= $statusFilter==='resolved'?'active':'' ?>">Resolved <span class="pill-count"><?= $stats['resolved'] ?></span></a>
        <a href="?status=closed" class="filter-pill <?= $statusFilter==='closed'?'active':'' ?>">Closed <span class="pill-count"><?= $stats['closed'] ?></span></a>
        <span class="text-muted small fw-semibold ms-3 me-1">Priority:</span>
        <a href="?status=<?= $statusFilter ?>&priority=all" class="filter-pill <?= $priorityFilter==='all'?'active':'' ?>">All</a>
        <a href="?status=<?= $statusFilter ?>&priority=urgent" class="filter-pill <?= $priorityFilter==='urgent'?'active':'' ?> prio-urgent">Urgent</a>
        <a href="?status=<?= $statusFilter ?>&priority=high" class="filter-pill <?= $priorityFilter==='high'?'active':'' ?> prio-high">High</a>
      </div>
      
      <!-- Search Form -->
      <form method="GET" class="d-flex align-items-center gap-2 m-0 flex-shrink-0">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="priority" value="<?= htmlspecialchars($priorityFilter) ?>">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search tickets..." value="<?= htmlspecialchars($searchQuery) ?>" style="width: 180px;">
        </div>
        <button type="submit" class="btn btn-sm px-3" style="background:var(--sc-primary);color:white;border:none;">Search</button>
        <?php if($searchQuery): ?>
        <a href="?status=<?= htmlspecialchars($statusFilter) ?>&priority=<?= htmlspecialchars($priorityFilter) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<!-- TICKET CARD GRID -->
<?php if (empty($tickets)): ?>
  <div class="card-theme">
    <div class="card-theme-body text-center py-5">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--sc-primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
        <i class="fas fa-inbox" style="font-size:1.5rem;color:var(--sc-primary);"></i>
      </div>
      <h6 class="fw-bold text-body">No tickets found</h6>
      <p class="text-muted small mb-0">No tickets match your current filters.</p>
    </div>
  </div>
<?php else: ?>
<div class="ticket-grid">
  <?php foreach ($tickets as $t):
    $hasAgent = !empty($t['agent_name']);
    $prioColor = match($t['priority']) { 'urgent'=>'#dc2626', 'high'=>'#ea580c', 'medium'=>'#d97706', default=>'#059669' };
    $statusBg = match($t['status']) { 'open'=>'#dbeafe', 'pending'=>'#fef3c7', 'waiting'=>'#fef3c7', 'resolved'=>'#dcfce7', 'closed'=>'#f1f5f9', 'reopened'=>'#fce7f3', default=>'#dbeafe' };
    $statusFg = match($t['status']) { 'open'=>'#1e40af', 'pending'=>'#92400e', 'waiting'=>'#92400e', 'resolved'=>'#15803d', 'closed'=>'#64748b', 'reopened'=>'#be185d', default=>'#1e40af' };
  ?>
  <div class="ticket-card" id="st-<?= $t['id'] ?>" data-priority="<?= $t['priority'] ?>" onclick="openTicketModal(<?= $t['id'] ?>,<?= json_encode($t['subject']) ?>,'<?= $t['status'] ?>','<?= $t['priority'] ?>',<?= json_encode($t['client_name']) ?>,<?= json_encode($t['client_email']??'') ?>,<?= json_encode($t['client_picture']??'') ?>,<?= json_encode($t['agent_name']??'') ?>,<?= json_encode($t['agent_picture']??'') ?>,<?= json_encode($t['ticket_number']??'') ?>)">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span style="font-size:0.68rem;font-weight:700;color:<?= $prioColor ?>;">#<?= htmlspecialchars($t['ticket_number']??'N/A') ?></span>
      <span class="badge" style="font-size:0.62rem;background:<?= $statusBg ?>;color:<?= $statusFg ?>;font-weight:600;padding:0.2rem 0.55rem;border-radius:50px;"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
    </div>
    <h6 class="fw-bold text-body mb-2" style="font-size:0.88rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($t['subject']) ?></h6>
    <div class="d-flex align-items-center gap-2 mb-2" style="font-size:0.75rem;color:#64748b;">
      <div style="width:22px;height:22px;border-radius:50%;background:var(--sc-primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
        <?php if (!empty($t['client_picture'])): ?>
          <img src="<?= htmlspecialchars($t['client_picture']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
        <?php else: ?>
          <span style="font-size:0.6rem;font-weight:700;color:var(--sc-primary);"><?= strtoupper(substr($t['client_name']??'C',0,1)) ?></span>
        <?php endif; ?>
      </div>
      <span class="text-truncate"><?= htmlspecialchars($t['client_name']??'N/A') ?></span>
    </div>
    <div class="mt-auto pt-2" style="border-top:1px solid var(--sc-border);">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span style="font-size:0.7rem;color:#94a3b8;">
          <?php if ($hasAgent): ?>
            <i class="fas fa-headset me-1" style="color:#059669;"></i><?= htmlspecialchars($t['agent_name']) ?>
          <?php else: ?>
            <i class="fas fa-clock me-1"></i>Unassigned
          <?php endif; ?>
        </span>
        <span style="font-size:0.65rem;color:#94a3b8;"><?= date('M d',strtotime($t['updated_at'])) ?></span>
      </div>
      <div class="d-flex gap-2">
        <?php if (!$hasAgent && $t['status'] !== 'resolved'): ?>
        <button class="btn flex-grow-1 rounded-pill join-chat-btn" onclick="event.stopPropagation();claimTicketById(<?= $t['id'] ?>)" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;font-size:0.72rem;font-weight:600;padding:0.35rem 0;box-shadow:0 2px 8px rgba(5,150,105,0.2);"><i class="fas fa-comments me-1"></i>Join</button>
        <?php endif; ?>
        
        <!-- Quick Status Changer on Card -->
        <select class="form-select form-select-sm rounded-pill flex-grow-1" style="font-size:0.72rem; border-color:var(--sc-border); cursor:pointer;" onclick="event.stopPropagation()" onchange="quickChangeStatus(<?= $t['id'] ?>, this.value)">
          <option value="waiting" <?= $t['status'] === 'waiting' ? 'selected' : '' ?>>Waiting</option>
          <option value="open" <?= $t['status'] === 'open' ? 'selected' : '' ?>>Open</option>
          <option value="pending" <?= $t['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="reopened" <?= $t['status'] === 'reopened' ? 'selected' : '' ?>>Reopened</option>
          <option value="resolved" <?= $t['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
          <option value="closed" <?= $t['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- CONVERSATION MODAL -->
<div class="chat-modal-overlay" id="chatOverlay" onclick="closeChatModal()"></div>
<div class="chat-modal" id="chatModal">
  <!-- Header -->
  <div class="chat-modal-header">
    <button class="btn btn-sm rounded-circle" style="width:36px;height:36px;background:var(--sc-bg);border:1px solid var(--sc-border);flex-shrink:0;" onclick="closeChatModal()">
      <i class="fas fa-arrow-left"></i>
    </button>
    <div id="modalAvatarWrap" style="width:40px;height:40px;border-radius:50%;background:var(--sc-primary-light);display:none;align-items:center;justify-content:center;font-weight:700;font-size:1rem;color:var(--sc-primary);flex-shrink:0;overflow:hidden;"></div>
    <div class="header-info">
      <div class="d-flex align-items-center gap-2 mb-0">
        <span class="ticket-label" id="modalTicketNumber" style="display:none;"></span>
        <span id="modalStatusBadge" style="display:none;font-size:0.62rem;" class="badge"></span>
      </div>
      <div class="ticket-subject" id="modalSubject"></div>
      <div class="ticket-meta d-flex align-items-center gap-2">
        <span id="modalClientName"></span>
        <span id="modalClientEmail" style="display:none;"> · <span id="modalEmailText"></span></span>
        <span id="modalClientPresence" class="presence-dot" style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;vertical-align:middle;margin-left:4px;" title="Offline"></span>
      </div>
    </div>
    <div class="d-flex align-items-center gap-1 me-1" id="modalPresenceDots"></div>
    <span class="participant-count" id="modalParticipantCount" style="display:none;">0</span>
    <div class="header-actions">
      <button class="action-btn" id="modalParticipantsBtn" title="Participants" onclick="toggleParticipantsPanel()" style="display:none;">
        <i class="fas fa-users"></i>
      </button>
      <button class="action-btn" id="modalActivityBtn" title="Activity Log" onclick="toggleActivityPanel()" style="display:none;">
        <i class="fas fa-clock"></i>
      </button>
      <button id="modalLeaveBtn" class="btn rounded-pill px-3" style="background:linear-gradient(135deg,#dc2626,#ef4444);color:white;border:none;font-size:0.75rem;font-weight:600;display:none;box-shadow:0 2px 8px rgba(220,38,38,0.3);" onclick="leaveChat()"><i class="fas fa-sign-out-alt me-1"></i>Leave</button>
      <button id="modalClaimBtn" class="btn rounded-pill px-3 join-chat-btn-header" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;font-size:0.75rem;font-weight:600;display:none;box-shadow:0 2px 8px rgba(5,150,105,0.3);" onclick="claimTicket()"><i class="fas fa-comments me-1"></i>Join</button>
      <span id="modalAssignedBadge" style="display:none;font-size:0.72rem;" class="badge bg-success py-1 px-2"><i class="fas fa-check-circle me-1"></i><span id="modalAssignedName"></span></span>
      <button id="modalResolveBtn" class="btn rounded-pill px-3 resolve-btn" style="background:linear-gradient(135deg,#15803d,#16a34a);color:white;border:none;font-size:0.75rem;font-weight:600;display:none;box-shadow:0 2px 8px rgba(22,163,74,0.3);" onclick="resolveTicket()"><i class="fas fa-check-double me-1"></i>Resolve</button>
      <button id="modalUnclaimBtn" class="btn btn-sm rounded-pill px-2" style="background:#64748b;color:white;border:none;font-size:0.7rem;display:none;" onclick="unclaimTicket()"><i class="fas fa-undo"></i></button>
      <select id="modalStatusChanger" class="form-select form-select-sm rounded-pill" style="width:auto;font-size:0.72rem;border-color:var(--sc-border);display:none;" onchange="changeStatus()">
        <option value="waiting">Waiting</option>
        <option value="open">Open</option>
        <option value="pending">Pending</option>
        <option value="reopened">Reopened</option>
        <option value="resolved">Resolved</option>
        <option value="closed">Closed</option>
      </select>
    </div>
  </div>

  <!-- Chat Area + Participants Sidebar -->
  <div style="display:flex;flex:1;overflow:hidden;">
    <!-- Messages -->
    <div class="chat-modal-body" id="modalChatArea" style="flex:1;min-width:0;">
      <div class="text-center py-5 text-muted">
        <i class="fas fa-headset d-block mb-3" style="font-size:2.5rem;color:#cbd5e1;"></i>
        <p class="small">Select a ticket to start the conversation.</p>
      </div>
    </div>
    <!-- Participants Panel -->
    <div class="participants-panel" id="participantsPanel">
      <div class="participants-panel-inner">
        <div class="participants-panel-title"><i class="fas fa-users me-1"></i> Participants</div>
        <div id="participantsList"></div>
      </div>
    </div>
  </div>

  <!-- Activity Log Panel -->
  <div class="activity-panel" id="activityPanel">
    <div class="activity-panel-inner">
      <div class="activity-panel-title"><i class="fas fa-clock me-1"></i> Activity Log</div>
      <div class="activity-timeline" id="activityTimeline"></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="chat-modal-footer" id="modalReplyFooter" style="display:none;">
    <div class="input-group">
      <input type="text" id="adminReplyInput" class="form-control" placeholder="Type your reply..." style="border-radius:50px 0 0 50px;border-color:var(--sc-border);font-size:0.88rem;">
      <button class="btn px-4" style="background:linear-gradient(135deg,var(--sc-primary),var(--sc-accent));color:white;border:none;border-radius:0 50px 50px 0;" onclick="sendAdminReply()"><i class="fas fa-paper-plane"></i></button>
    </div>
  </div>
</div>

<script>
var activeTicketId = null;
var activeTicketData = null;
var replyPollInterval = null;
var typingPollInterval = null;
var presencePollInterval = null;
var participantsPollInterval = null;
var activityPollInterval = null;
var agentTypingTimeout = null;
var typingElements = {};
var participantsPanelOpen = false;
var activityPanelOpen = false;

/* ─── Helper: build avatar HTML ─── */
function buildAvatar(pic, name, bgColor, txtColor, size) {
    size = size || 26;
    bgColor = bgColor || 'var(--sc-primary-light)';
    txtColor = txtColor || 'var(--sc-primary)';
    if (pic) {
        return '<div class="msg-avatar" style="width:'+size+'px;height:'+size+'px;background:'+bgColor+';"><img src="'+pic+'" style="width:100%;height:100%;object-fit:cover;"></div>';
    }
    return '<div class="msg-avatar" style="width:'+size+'px;height:'+size+'px;background:'+bgColor+';color:'+txtColor+';">'+((name||'?').charAt(0).toUpperCase())+'</div>';
}

/* ─── Helper: role badge ─── */
function roleBadge(role) {
    var label = role === 'admin' ? 'Admin' : role === 'developer' ? 'Agent' : role === 'client' ? 'Client' : role;
    var colors = { admin: ['#0A2D5E','#fff'], developer: ['#1e40af','#fff'], client: ['#64748b','#fff'] };
    var c = colors[role] || ['#64748b','#fff'];
    return '<span class="msg-role" style="background:'+c[0]+';color:'+c[1]+';">'+label+'</span>';
}

/* ─── Send typing status to server ─── */
function sendAgentTypingStatus(isTyping) {
    if (!activeTicketId) return;
    var agentName = (activeTicketData && activeTicketData.agentName) || 'Agent';
    var agentPic = (activeTicketData && activeTicketData.agentPicture) || '';
    var fd = new URLSearchParams();
    fd.append('action', 'typing');
    fd.append('ticket_id', activeTicketId);
    fd.append('is_typing', isTyping ? 1 : 0);
    fd.append('display_name', agentName);
    fd.append('avatar_url', agentPic);
    fetch('../api/ticket_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
    }).catch(function(){});
}

/* ─── Render multi-participant typing indicators ─── */
function renderTypingIndicators(typers) {
    var area = document.getElementById('modalChatArea');
    var existingContainer = document.getElementById('typing-indicators-container');

    if (!typers || typers.length === 0) {
        if (existingContainer) existingContainer.remove();
        return;
    }

    if (!existingContainer) {
        var container = document.createElement('div');
        container.id = 'typing-indicators-container';
        container.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 12px;margin:4px 0;animation:fadeIn 0.3s ease;';
        area.appendChild(container);
    }

    var container = document.getElementById('typing-indicators-container');
    var html = '';

    typers.forEach(function(typer) {
        var picHtml = typer.avatar_url
            ? '<img src="'+typer.avatar_url+'" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">'
            : '<span style="font-size:0.6rem;font-weight:700;">'+((typer.name||'?').charAt(0))+'</span>';
        html += '<div style="display:flex;align-items:center;gap:6px;background:rgba(0,0,0,0.04);border-radius:20px;padding:6px 12px;">' +
            '<div style="width:22px;height:22px;border-radius:50%;background:var(--sc-primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'+picHtml+'</div>' +
            '<span style="font-size:0.72rem;font-weight:600;color:var(--sc-text);">'+typer.name+'</span>' +
            '<div style="display:flex;gap:3px;padding:2px 0;">' +
                '<span style="width:5px;height:5px;border-radius:50%;background:var(--sc-primary);animation:typingDot 1.4s infinite;animation-delay:0s;"></span>' +
                '<span style="width:5px;height:5px;border-radius:50%;background:var(--sc-primary);animation:typingDot 1.4s infinite;animation-delay:0.2s;"></span>' +
                '<span style="width:5px;height:5px;border-radius:50%;background:var(--sc-primary);animation:typingDot 1.4s infinite;animation-delay:0.4s;"></span>' +
            '</div>' +
        '</div>';
    });

    container.innerHTML = html;
    setTimeout(function() { area.scrollTop = area.scrollHeight; }, 50);
}

/* ─── Presence rendering ─── */
function renderPresenceDots(participants) {
    var container = document.getElementById('modalPresenceDots');
    var countEl = document.getElementById('modalParticipantCount');
    var clientPresence = document.getElementById('modalClientPresence');
    if (!participants || participants.length === 0) {
        container.innerHTML = '';
        countEl.style.display = 'none';
        if (clientPresence) { clientPresence.style.background = '#94a3b8'; clientPresence.title = 'Offline'; }
        return;
    }
    var online = participants.filter(function(p){ return p.status === 'online'; });
    var html = '';
    var maxShow = Math.min(online.length, 4);
    for (var i = 0; i < maxShow; i++) {
        var p = online[i];
        var dotColor = p.status === 'online' ? '#22c55e' : p.status === 'away' ? '#eab308' : '#94a3b8';
        html += '<div class="presence-dot" style="background:'+dotColor+';" title="'+p.name+' ('+p.status+')"></div>';
    }
    container.innerHTML = html;
    countEl.textContent = participants.length + ' in chat';
    countEl.style.display = participants.length > 0 ? 'inline-block' : 'none';

    if (clientPresence && activeTicketData) {
        var clientP = participants.find(function(p){ return p.name === activeTicketData.clientName; });
        if (clientP) {
            var cColor = clientP.status === 'online' ? '#22c55e' : clientP.status === 'away' ? '#eab308' : '#94a3b8';
            clientPresence.style.background = cColor;
            clientPresence.title = clientP.name + ' - ' + clientP.status;
        }
    }
}

/* ─── Participants panel rendering ─── */
function renderParticipantsPanel(participants) {
    var list = document.getElementById('participantsList');
    if (!participants || participants.length === 0) {
        list.innerHTML = '<div style="font-size:0.75rem;color:var(--sc-text-muted);text-align:center;padding:1rem 0;">No participants</div>';
        return;
    }
    var html = '';
    participants.forEach(function(p) {
        var statusColor = p.status === 'online' ? '#22c55e' : p.status === 'away' ? '#eab308' : '#94a3b8';
        var avatarBg = (p.role === 'admin' || p.role === 'developer') ? 'var(--sc-primary)' : 'var(--sc-primary-light)';
        var avatarTxt = (p.role === 'admin' || p.role === 'developer') ? '#fff' : 'var(--sc-primary)';
        var avHtml = p.picture
            ? '<img src="'+p.picture+'" style="width:100%;height:100%;object-fit:cover;">'
            : '<span style="color:'+avatarTxt+';">'+((p.name||'?').charAt(0).toUpperCase())+'</span>';
        var roleLabel = p.role === 'admin' ? 'Admin' : p.role === 'developer' ? 'Agent' : 'Client';
        html += '<div class="participant-item">' +
            '<div class="participant-avatar" style="background:'+avatarBg+';color:'+avatarTxt+';">' +
                avHtml +
                '<div class="online-indicator" style="background:'+statusColor+';"></div>' +
            '</div>' +
            '<div class="participant-info">' +
                '<div class="participant-name">'+p.name+'</div>' +
                '<div class="participant-role">'+roleLabel+'</div>' +
            '</div>' +
        '</div>';
    });
    list.innerHTML = html;
}

/* ─── Activity log rendering ─── */
function renderActivityLog(events) {
    var timeline = document.getElementById('activityTimeline');
    if (!events || events.length === 0) {
        timeline.innerHTML = '<div style="font-size:0.75rem;color:var(--sc-text-muted);text-align:center;padding:0.5rem 0;">No activity yet</div>';
        return;
    }
    var html = '';
    events.forEach(function(ev) {
        var icon = ev.type === 'join' ? 'fa-sign-in-alt' : ev.type === 'leave' ? 'fa-sign-out-alt' : ev.type === 'message' ? 'fa-comment' : 'fa-info-circle';
        var time = new Date(ev.timestamp).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        html += '<div class="activity-entry ' + ev.type + '">' +
            '<div class="entry-time">'+time+'</div>' +
            '<div class="entry-text">'+ev.message+'</div>' +
        '</div>';
    });
    timeline.innerHTML = html;
}

/* ─── Toggle panels ─── */
function toggleParticipantsPanel() {
    participantsPanelOpen = !participantsPanelOpen;
    document.getElementById('participantsPanel').classList.toggle('open', participantsPanelOpen);
    document.getElementById('modalParticipantsBtn').classList.toggle('active', participantsPanelOpen);
}
function toggleActivityPanel() {
    activityPanelOpen = !activityPanelOpen;
    document.getElementById('activityPanel').classList.toggle('open', activityPanelOpen);
    document.getElementById('modalActivityBtn').classList.toggle('active', activityPanelOpen);
}

/* ─── Agent typing on input ─── */
document.addEventListener('DOMContentLoaded', function() {
    var replyInput = document.getElementById('adminReplyInput');
    if (replyInput) {
        replyInput.addEventListener('input', function() {
            sendAgentTypingStatus(true);
            clearTimeout(agentTypingTimeout);
            agentTypingTimeout = setTimeout(function() { sendAgentTypingStatus(false); }, 2000);
        });
        replyInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendAdminReply();
        });
    }
});

/* ─── ESC key to close modal ─── */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeChatModal();
});

/* ─── Open conversation modal ─── */
function openTicketModal(ticketId, subject, status, priority, clientName, clientEmail, clientPicture, agentName, agentPicture, ticketNumber) {
    activeTicketId = ticketId;
    activeTicketData = { ticketId: ticketId, subject: subject, status: status, priority: priority, clientName: clientName, clientEmail: clientEmail, clientPicture: clientPicture, agentName: agentName, agentPicture: agentPicture, ticketNumber: ticketNumber, adminName: '<?= addslashes($headerUser["name"] ?? "") ?>' };

    document.querySelectorAll('.ticket-card').forEach(function(c) { c.classList.remove('active'); });
    var card = document.getElementById('st-' + ticketId);
    if (card) card.classList.add('active');

    // Ticket number
    var numEl = document.getElementById('modalTicketNumber');
    if (ticketNumber) { numEl.style.display = 'inline'; numEl.textContent = '#' + ticketNumber; }
    else numEl.style.display = 'none';

    // Status badge
    var statusBadge = document.getElementById('modalStatusBadge');
    var statusColors = { open: {bg:'#dbeafe',fg:'#1e40af'}, pending: {bg:'#fef3c7',fg:'#92400e'}, waiting: {bg:'#fef3c7',fg:'#92400e'}, resolved: {bg:'#dcfce7',fg:'#15803d'}, closed: {bg:'#f1f5f9',fg:'#64748b'}, reopened: {bg:'#fce7f3',fg:'#be185d'} };
    var sc = statusColors[status] || statusColors.open;
    statusBadge.style.display = 'inline-block';
    statusBadge.style.background = sc.bg;
    statusBadge.style.color = sc.fg;
    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

    // Subject
    document.getElementById('modalSubject').textContent = subject;

    // Client avatar
    var avatarWrap = document.getElementById('modalAvatarWrap');
    if (clientPicture) {
        avatarWrap.style.display = 'flex';
        avatarWrap.innerHTML = '<img src="' + clientPicture + '" style="width:100%;height:100%;object-fit:cover;" alt="">';
    } else if (clientName) {
        avatarWrap.style.display = 'flex';
        avatarWrap.innerHTML = '';
        avatarWrap.textContent = clientName.charAt(0).toUpperCase();
    } else {
        avatarWrap.style.display = 'none';
    }

    // Client info
    document.getElementById('modalClientName').textContent = clientName || 'Unknown Client';
    var emailEl = document.getElementById('modalClientEmail');
    var emailText = document.getElementById('modalEmailText');
    if (clientEmail) { emailEl.style.display = 'inline'; emailText.textContent = clientEmail; }
    else { emailEl.style.display = 'none'; }

    // Show/hide action buttons
    document.getElementById('modalParticipantsBtn').style.display = 'inline-flex';
    document.getElementById('modalActivityBtn').style.display = 'inline-flex';

    var claimBtn = document.getElementById('modalClaimBtn');
    var unclaimBtn = document.getElementById('modalUnclaimBtn');
    var assignedBadge = document.getElementById('modalAssignedBadge');
    var assignedName = document.getElementById('modalAssignedName');
    var resolveBtn = document.getElementById('modalResolveBtn');
    var scSelect = document.getElementById('modalStatusChanger');
    var leaveBtn = document.getElementById('modalLeaveBtn');

    claimBtn.style.display = 'none';
    unclaimBtn.style.display = 'none';
    assignedBadge.style.display = 'none';
    resolveBtn.style.display = 'none';
    scSelect.style.display = 'none';
    leaveBtn.style.display = 'none';

    if (status === 'resolved') {
        scSelect.style.display = 'inline-block';
        scSelect.value = 'resolved';
        document.getElementById('modalReplyFooter').style.display = 'none';
    } else if (agentName) {
        assignedName.textContent = agentName;
        assignedBadge.style.display = 'inline-block';
        resolveBtn.style.display = 'inline-block';
        scSelect.style.display = 'inline-block';
        scSelect.value = status;
        document.getElementById('modalReplyFooter').style.display = 'block';
        if (agentName === (activeTicketData.adminName || '')) {
            leaveBtn.style.display = 'inline-block';
            unclaimBtn.style.display = 'none';
        } else {
            unclaimBtn.style.display = 'inline-block';
        }
    } else {
        claimBtn.style.display = 'inline-block';
        document.getElementById('modalReplyFooter').style.display = 'none';
    }

    // Open modal
    document.getElementById('chatOverlay').classList.add('open');
    document.getElementById('chatModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Load messages
    var area = document.getElementById('modalChatArea');
    area.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

    fetch('../api/ticket_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_messages&ticket_id=' + ticketId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        area.innerHTML = '';
        if (data.error) {
            area.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
            return;
        }
        if (data.status) {
            activeTicketData.status = data.status;
            var closedSt = ['resolved', 'closed'];
            document.getElementById('modalReplyFooter').style.display = closedSt.includes(data.status) ? 'none' : 'block';
            statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            var nsc = statusColors[data.status] || statusColors.open;
            statusBadge.style.background = nsc.bg;
            statusBadge.style.color = nsc.fg;
            scSelect.value = data.status;
        }
        var msgs = data.messages || data;
        if (!msgs.length) {
            if (!agentName && status !== 'resolved') {
                area.innerHTML = '<div class="empty-chat-state">' +
                    '<div class="empty-icon" style="background:rgba(5,150,105,0.1);">' +
                    '<i class="fas fa-comments" style="font-size:1.5rem;color:#059669;"></i></div>' +
                    '<h6>This ticket needs a person</h6>' +
                    '<p>' + (clientName || 'The client') + ' is waiting for a support agent.</p>' +
                    '<button class="btn rounded-pill px-4 join-chat-btn-header" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;font-size:0.85rem;font-weight:600;box-shadow:0 2px 8px rgba(5,150,105,0.3);" onclick="claimTicket()">' +
                    '<i class="fas fa-comments me-1"></i> Join Chat</button></div>';
            } else {
                area.innerHTML = '<p class="text-center text-muted py-5">No messages yet.</p>';
            }
            return;
        }
        var lastDate = '';
        msgs.forEach(function(msg) {
            var msgType = msg.message_type || (msg.sender_role === 'admin' || msg.sender_role === 'developer' ? 'agent' : 'user');
            // Date separator
            var msgDate = new Date(msg.created_at).toLocaleDateString([], {weekday:'short',month:'short',day:'numeric'});
            if (msgDate !== lastDate) {
                lastDate = msgDate;
                var sep = document.createElement('div');
                sep.className = 'date-separator';
                sep.textContent = msgDate;
                area.appendChild(sep);
            }
            if (msgType === 'system') {
                var sysDiv = document.createElement('div');
                sysDiv.className = 'chat-bubble system';
                sysDiv.innerHTML = '<span>' + msg.message + '</span>';
                area.appendChild(sysDiv);
                return;
            }
            var isAdmin = msgType === 'agent' || msg.sender_role === 'admin' || msg.sender_role === 'developer';
            var bubble = document.createElement('div');
            bubble.className = 'chat-bubble ' + (isAdmin ? 'admin' : 'client');
            var time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            var picHtml = msg.sender_pic
                ? '<img src="' + msg.sender_pic + '" style="width:100%;height:100%;object-fit:cover;">'
                : '<span style="font-size:0.6rem;font-weight:700;color:'+(isAdmin?'white':'var(--sc-primary)')+';">' + (msg.sender_name||'A').charAt(0) + '</span>';
            var avatarBg = isAdmin ? 'rgba(255,255,255,0.2)' : 'var(--sc-primary-light)';
            var avatarTxt = isAdmin ? 'white' : 'var(--sc-primary)';
            bubble.innerHTML =
                '<div class="msg-header">' +
                    '<div class="msg-avatar" style="background:'+avatarBg+';color:'+avatarTxt+';">' + picHtml + '</div>' +
                    '<span class="msg-name">' + msg.sender_name + '</span>' +
                    roleBadge(msg.sender_role) +
                '</div>' +
                '<div>' + msg.message + '</div>' +
                '<div class="msg-time">' + time + '</div>';
            area.appendChild(bubble);
        });
        setTimeout(function() { area.scrollTop = area.scrollHeight; }, 100);
    })
    .catch(function() {
        area.innerHTML = '<div class="alert alert-danger">Failed to load conversation.</div>';
    });

    startReplyPolling(ticketId);
}

/* ─── Close conversation modal ─── */
function closeChatModal() {
    document.getElementById('chatOverlay').classList.remove('open');
    document.getElementById('chatModal').classList.remove('open');
    document.body.style.overflow = '';
    if (replyPollInterval) { clearInterval(replyPollInterval); replyPollInterval = null; }
    if (typingPollInterval) { clearInterval(typingPollInterval); typingPollInterval = null; }
    if (presencePollInterval) { clearInterval(presencePollInterval); presencePollInterval = null; }
    if (participantsPollInterval) { clearInterval(participantsPollInterval); participantsPollInterval = null; }
    if (activityPollInterval) { clearInterval(activityPollInterval); activityPollInterval = null; }
    typingElements = {};
    participantsPanelOpen = false;
    activityPanelOpen = false;
    document.getElementById('participantsPanel').classList.remove('open');
    document.getElementById('activityPanel').classList.remove('open');
    document.getElementById('modalParticipantsBtn').classList.remove('active');
    document.getElementById('modalActivityBtn').classList.remove('active');
}

/* ─── Send admin reply ─── */
function sendAdminReply() {
    var msg = document.getElementById('adminReplyInput').value.trim();
    if (!msg || !activeTicketId) return;
    sendAgentTypingStatus(false);
    clearTimeout(agentTypingTimeout);
    fetch('../api/ticket_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=send_message&ticket_id=' + activeTicketId + '&message=' + encodeURIComponent(msg)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('adminReplyInput').value = '';
            var area = document.getElementById('modalChatArea');
            var bubble = document.createElement('div');
            bubble.className = 'chat-bubble admin';
            var time = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            var picHtml = activeTicketData.agentPicture
                ? '<img src="'+activeTicketData.agentPicture+'" style="width:100%;height:100%;object-fit:cover;">'
                : '<span style="font-size:0.6rem;font-weight:700;color:white;">'+((activeTicketData.agentName||'A').charAt(0))+'</span>';
            bubble.innerHTML =
                '<div class="msg-header">' +
                    '<div class="msg-avatar" style="background:rgba(255,255,255,0.2);color:white;">' + picHtml + '</div>' +
                    '<span class="msg-name">You</span>' +
                    roleBadge('admin') +
                '</div>' +
                '<div>' + msg + '</div>' +
                '<div class="msg-time">' + time + '</div>';
            area.appendChild(bubble);
            setTimeout(function() { area.scrollTop = area.scrollHeight; }, 100);
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed to send.', confirmButtonColor:'#dc3545'});
        }
    });
}

/* ─── Change ticket status ─── */
function changeStatus() {
    if (!activeTicketId) return;
    var newStatus = document.getElementById('modalStatusChanger').value;
    fetch('support_center.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=update_status&ticket_id=' + activeTicketId + '&status=' + newStatus
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            Swal.fire({
                icon: 'success', 
                title: 'Status Updated', 
                text: 'Ticket status changed to ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '.', 
                timer: 1500, 
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            var card = document.getElementById('st-' + activeTicketId);
            if (card) {
                var badge = card.querySelector('.badge');
                if (badge) {
                    var colors = { open: { bg: '#dbeafe', fg: '#1e40af' }, pending: { bg: '#fef3c7', fg: '#92400e' }, resolved: { bg: '#dcfce7', fg: '#15803d' }, waiting: { bg: '#fef3c7', fg: '#92400e' }, closed: { bg: '#f1f5f9', fg: '#64748b' }, reopened: { bg: '#fce7f3', fg: '#be185d' } };
                    badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    badge.style.background = colors[newStatus].bg;
                    badge.style.color = colors[newStatus].fg;
                }
            }
            var statusBadge = document.getElementById('modalStatusBadge');
            var statusColors = { open: {bg:'#dbeafe',fg:'#1e40af'}, pending: {bg:'#fef3c7',fg:'#92400e'}, waiting: {bg:'#fef3c7',fg:'#92400e'}, resolved: {bg:'#dcfce7',fg:'#15803d'}, closed: {bg:'#f1f5f9',fg:'#64748b'}, reopened: {bg:'#fce7f3',fg:'#be185d'} };
            var nsc = statusColors[newStatus] || statusColors.open;
            statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            statusBadge.style.background = nsc.bg;
            statusBadge.style.color = nsc.fg;

            document.getElementById('modalReplyFooter').style.display = newStatus === 'resolved' ? 'none' : 'block';
            document.getElementById('modalResolveBtn').style.display = newStatus === 'resolved' ? 'none' : 'inline-block';
            document.getElementById('modalUnclaimBtn').style.display = newStatus === 'resolved' ? 'none' : 'inline-block';
            if (activeTicketData) activeTicketData.status = newStatus;
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed.', confirmButtonColor:'#dc3545'});
        }
    });
}

/* ─── Quick Change ticket status (from card) ─── */
function quickChangeStatus(ticketId, newStatus) {
    if (!ticketId) return;
    fetch('support_center.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=update_status&ticket_id=' + ticketId + '&status=' + newStatus
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            Swal.fire({
                icon: 'success', 
                title: 'Status Updated', 
                text: 'Ticket status changed to ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '.', 
                timer: 2000, 
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            var card = document.getElementById('st-' + ticketId);
            if (card) {
                var badge = card.querySelector('.badge');
                if (badge) {
                    var colors = { open: { bg: '#dbeafe', fg: '#1e40af' }, pending: { bg: '#fef3c7', fg: '#92400e' }, resolved: { bg: '#dcfce7', fg: '#15803d' }, waiting: { bg: '#fef3c7', fg: '#92400e' }, closed: { bg: '#f1f5f9', fg: '#64748b' }, reopened: { bg: '#fce7f3', fg: '#be185d' } };
                    badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    badge.style.background = colors[newStatus].bg;
                    badge.style.color = colors[newStatus].fg;
                }
            }
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed.', confirmButtonColor:'#dc3545'});
        }
    });
}


/* ─── Claim ticket by ID (from card button) ─── */
function claimTicketById(ticketId) {
    fetch('../api/ticket_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=claim_ticket&ticket_id=' + ticketId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            Swal.fire({icon:'success', title:'Joined Chat', text:'Opening conversation...', timer:1200, showConfirmButton:false});
            setTimeout(function() { location.href = '?ticket_id=' + ticketId; }, 1200);
        } else {
            Swal.fire({icon:'error', title:'Failed', text: data.message || 'Could not join.', confirmButtonColor:'#dc3545'});
        }
    });
}

/* ─── Claim ticket (from modal) ─── */
function claimTicket() {
    if (!activeTicketId) return;
    var id = activeTicketId;
    fetch('../api/ticket_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=claim_ticket&ticket_id=' + id
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            Swal.fire({icon:'success', title:'Joined Chat', text:'Redirecting...', timer:1200, showConfirmButton:false});
            setTimeout(function() { location.href = '?ticket_id=' + id; }, 1200);
        } else {
            Swal.fire({icon:'error', title:'Failed', text: data.message || 'Could not join.', confirmButtonColor:'#dc3545'});
        }
    });
}

/* ─── Unclaim ticket (release to queue) ─── */
function unclaimTicket() {
    if (!activeTicketId) return;
    Swal.fire({
        title: 'Release ticket?',
        text: 'This will put the ticket back in the available queue.',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, release'
    }).then(function(res) {
        if (!res.isConfirmed) return;
        fetch('../api/ticket_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=leave_chat&ticket_id=' + activeTicketId
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({icon:'success', title:'Released', text:'Ticket returned to queue.', timer:1500, showConfirmButton:false});
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed.', confirmButtonColor:'#dc3545'});
            }
        });
    });
}

/* ─── Leave chat (quit from conversation) ─── */
function leaveChat() {
    if (!activeTicketId) return;
    Swal.fire({
        title: 'Leave this chat?',
        html: 'You will stop receiving messages from <strong>#' + (activeTicketData.ticketNumber || activeTicketId) + '</strong>. The ticket will be released back to the queue.',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, leave'
    }).then(function(res) {
        if (!res.isConfirmed) return;
        fetch('support_center.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ajax_action=leave_chat&ticket_id=' + activeTicketId
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({icon:'success', title:'Left Chat', text:'You have left the conversation.', timer:1500, showConfirmButton:false});
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed.', confirmButtonColor:'#dc3545'});
            }
        });
    });
}

/* ─── Resolve ticket ─── */
function resolveTicket() {
    if (!activeTicketId) return;
    Swal.fire({
        title: 'Resolve this ticket?',
        html: 'Mark <strong>' + (activeTicketData ? (activeTicketData.ticketNumber || '#' + activeTicketId) : '#' + activeTicketId) + '</strong> as resolved? The client will be notified.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#15803d',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-check-double me-1"></i> Yes, resolve it'
    }).then(function(res) {
        if (!res.isConfirmed) return;
        fetch('../api/ticket_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=resolve_ticket&ticket_id=' + activeTicketId
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Ticket Resolved', text: 'The client has been notified.', timer: 2000, showConfirmButton: false });
                document.getElementById('modalStatusChanger').value = 'resolved';
                var statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = 'Resolved';
                statusBadge.style.background = '#dcfce7';
                statusBadge.style.color = '#15803d';
                var card = document.getElementById('st-' + activeTicketId);
                if (card) {
                    var badge = card.querySelector('.badge');
                    if (badge) { badge.textContent = 'Resolved'; badge.style.background = '#dcfce7'; badge.style.color = '#15803d'; }
                }
                document.getElementById('modalReplyFooter').style.display = 'none';
                document.getElementById('modalResolveBtn').style.display = 'none';
                document.getElementById('modalUnclaimBtn').style.display = 'none';
                if (replyPollInterval) { clearInterval(replyPollInterval); replyPollInterval = null; }
                if (activeTicketData) activeTicketData.status = 'resolved';
            } else {
                Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed to resolve.', confirmButtonColor:'#dc3545'});
            }
        });
    });
}

/* ─── Reply polling ─── */
function startReplyPolling(ticketId) {
    if (replyPollInterval) clearInterval(replyPollInterval);
    replyPollInterval = setInterval(function() {
        if (!ticketId) return;
        fetch('../api/ticket_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_messages&ticket_id=' + ticketId
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && !data.error) {
                var area = document.getElementById('modalChatArea');
                var existing = area.querySelectorAll('.chat-bubble');
                var msgCount = (data.messages || data).length || 0;
                if (msgCount !== existing.length && msgCount > 0) {
                    var d = activeTicketData || {};
                    openTicketModal(ticketId, d.subject || 'Loading...', d.status || 'open', d.priority || 'medium', d.clientName || '', d.clientEmail || '', d.clientPicture || '', d.agentName || '', d.agentPicture || '', d.ticketNumber || '');
                }
            }
        })
        .catch(function(){});

        // Typing polling
        fetch('../api/ticket_api.php?action=get_typing&ticket_id=' + ticketId)
        .then(function(r) { return r.json(); })
        .then(function(tData) {
            if (tData.success && tData.typers && tData.typers.length > 0) {
                renderTypingIndicators(tData.typers);
            } else {
                renderTypingIndicators([]);
            }
        })
        .catch(function(){});

        // Presence polling
        fetch('../api/ticket_api.php?action=get_presence&ticket_id=' + ticketId)
        .then(function(r) { return r.json(); })
        .then(function(pData) {
            if (pData.success && pData.participants) {
                renderPresenceDots(pData.participants);
            }
        })
        .catch(function(){});

        // Participants polling
        fetch('../api/ticket_api.php?action=get_participants&ticket_id=' + ticketId)
        .then(function(r) { return r.json(); })
        .then(function(pData) {
            if (pData.success && pData.participants) {
                renderParticipantsPanel(pData.participants);
            }
        })
        .catch(function(){});

        // Activity log polling
        fetch('../api/ticket_api.php?action=get_activity_log&ticket_id=' + ticketId)
        .then(function(r) { return r.json(); })
        .then(function(aData) {
            if (aData.success && aData.events) {
                renderActivityLog(aData.events);
            }
        })
        .catch(function(){});
    }, 4000);
}

/* ─── Auto-load from URL param ─── */
document.addEventListener('DOMContentLoaded', function() {
    var params = new URLSearchParams(location.search);
    var tid = params.get('ticket_id');
    if (tid) {
        var card = document.getElementById('st-' + tid);
        if (card) card.click();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
