<?php
$path_to_root = "../";
$page_title = "Support Center";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: ../login.php"); exit; }

require_once dirname(__DIR__) . '/config.php';
$userId = $_SESSION['user']['id'];

// Fetch user's tickets
try {
    $tListStmt = $pdo->prepare("
        SELECT t.*, u.name AS agent_name, u.picture AS agent_picture,
            (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id AND m.is_read = 0 AND m.user_id != ?) AS unread_count
        FROM support_tickets t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.user_id = ?
        ORDER BY t.updated_at DESC
    ");
    $tListStmt->execute([$userId, $userId]);
    $tickets = $tListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tickets = [];
}

require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
:root {
    --cs-primary: #0A2D5E;
    --cs-primary-rgb: 10, 45, 94;
    --cs-accent: #1e40af;
    --cs-success: #059669;
    --cs-bg: #f8fafc;
    --cs-card-bg: #ffffff;
    --cs-border: #e2e8f0;
    --cs-text: #1e293b;
    --cs-text-muted: #94a3b8;
    --cs-user-msg-from: #0A2D5E;
    --cs-user-msg-to: #163f7a;
    --cs-agent-msg-bg: #f1f5f9;
    --cs-overlay: rgba(15,23,42,0.5);
}

body, .wrapper, .main-wrapper { overflow-x: hidden !important; }

/* ─── Ticket Card Grid ─── */
.ticket-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 1199.98px) { .ticket-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px) { .ticket-grid { grid-template-columns: 1fr; } }

.ticket-card {
    background: var(--cs-card-bg);
    border: 1px solid var(--cs-border);
    border-radius: 14px;
    padding: 1.15rem;
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
}
.ticket-card[data-priority="urgent"]::before { background: linear-gradient(90deg, #dc2626, #ef4444); }
.ticket-card[data-priority="high"]::before { background: linear-gradient(90deg, #ea580c, #f97316); }
.ticket-card[data-priority="medium"]::before { background: linear-gradient(90deg, #d97706, #eab308); }
.ticket-card[data-priority="low"]::before { background: linear-gradient(90deg, #059669, #10b981); }
.ticket-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.08);
    border-color: rgba(var(--cs-primary-rgb), 0.25);
}
.ticket-card.active {
    border-color: var(--cs-primary);
    box-shadow: 0 0 0 3px rgba(10,45,94,0.12), 0 8px 24px rgba(0,0,0,0.06);
}

/* ─── Status Badges ─── */
.status-badge { font-size: 0.62rem; font-weight: 700; padding: 0.18rem 0.55rem; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.3px; }
.status-waiting { background: #fef3c7; color: #92400e; }
.status-open { background: #dbeafe; color: #1e40af; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-resolved { background: #dcfce7; color: #15803d; }
.status-closed { background: #f1f5f9; color: #64748b; }
.status-reopened { background: #fce7f3; color: #be185d; }
.status-agent_joined { background: #d1fae5; color: #065f46; }

/* ─── Filter Pills ─── */
.filter-pill {
    padding: 0.3rem 0.85rem;
    border-radius: 50px;
    border: 1px solid var(--cs-border);
    background: var(--cs-card-bg);
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.filter-pill:hover { border-color: var(--cs-primary); color: var(--cs-primary); transform: translateY(-1px); }
.filter-pill.active { background: var(--cs-primary); color: white; border-color: var(--cs-primary); box-shadow: 0 2px 8px rgba(10,45,94,0.25); }

/* ─── Chat Modal Overlay ─── */
.chat-modal-overlay {
    position: fixed; inset: 0; z-index: 1050;
    background: var(--cs-overlay);
    backdrop-filter: blur(4px);
    opacity: 0; pointer-events: none;
    transition: opacity 0.3s ease;
}
.chat-modal-overlay.open { opacity: 1; pointer-events: all; }

/* ─── Chat Modal ─── */
.chat-modal {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 520px; max-width: 100vw;
    z-index: 1060;
    background: var(--cs-card-bg);
    box-shadow: -8px 0 40px rgba(0,0,0,0.15);
    transform: translateX(100%);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
    display: flex; flex-direction: column;
}
.chat-modal.open { transform: translateX(0); }
@media (max-width: 575.98px) { .chat-modal { width: 100vw; } }

/* ─── Chat Header ─── */
.chat-modal-header {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--cs-border);
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--cs-card-bg);
    flex-shrink: 0;
}
.chat-header-agent {
    display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0;
}
.chat-header-agent-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: rgba(var(--cs-primary-rgb), 0.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden; position: relative;
}
.chat-header-agent-avatar img { width: 100%; height: 100%; object-fit: cover; }
.presence-dot {
    position: absolute; bottom: 0; right: 0;
    width: 10px; height: 10px; border-radius: 50%;
    border: 2px solid var(--cs-card-bg);
}
.presence-dot.online { background: #22c55e; }
.presence-dot.busy { background: #eab308; }
.presence-dot.offline { background: #94a3b8; }

.chat-header-meta { min-width: 0; flex: 1; }
.chat-header-meta .ticket-num {
    font-size: 0.68rem; color: var(--cs-text-muted); font-weight: 600;
}
.chat-header-meta .ticket-subject {
    font-size: 0.88rem; font-weight: 700; color: var(--cs-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.chat-header-meta .agent-name {
    font-size: 0.72rem; color: var(--cs-text-muted);
}
.chat-header-meta .waiting-text {
    font-size: 0.72rem; color: #d97706;
}

.chat-header-actions {
    display: flex; align-items: center; gap: 0.3rem; flex-shrink: 0;
}
.chat-header-actions .btn-icon {
    width: 32px; height: 32px; border-radius: 8px;
    border: none; background: rgba(var(--cs-primary-rgb), 0.06);
    color: var(--cs-text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
    font-size: 0.78rem; position: relative;
}
.chat-header-actions .btn-icon:hover { background: rgba(var(--cs-primary-rgb), 0.12); color: var(--cs-primary); }
.chat-header-actions .btn-icon.active { background: var(--cs-primary); color: white; }
.chat-header-actions .btn-icon .notif-dot {
    position: absolute; top: 4px; right: 4px;
    width: 6px; height: 6px; border-radius: 50%;
    background: #dc2626;
}

/* ─── Chat Body Layout ─── */
.chat-body-wrapper {
    flex: 1; display: flex; overflow: hidden; position: relative;
}
.chat-modal-body {
    flex: 1; overflow-y: auto; padding: 1rem;
    background: var(--cs-bg);
}
.chat-sidebar {
    width: 0; overflow: hidden;
    border-left: 1px solid var(--cs-border);
    background: var(--cs-card-bg);
    transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
    flex-shrink: 0;
}
.chat-sidebar.open { width: 220px; }
@media (max-width: 575.98px) { .chat-sidebar.open { width: 180px; } }
.chat-sidebar-inner {
    width: 220px; padding: 0.75rem;
    height: 100%; overflow-y: auto;
}
@media (max-width: 575.98px) { .chat-sidebar-inner { width: 180px; } }
.chat-sidebar-title {
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: var(--cs-text-muted); margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.4rem;
}
.participant-item {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0; font-size: 0.78rem;
}
.participant-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(var(--cs-primary-rgb), 0.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden; position: relative;
    font-size: 0.6rem; font-weight: 700; color: var(--cs-primary);
}
.participant-avatar img { width: 100%; height: 100%; object-fit: cover; }
.participant-avatar .presence-dot { width: 8px; height: 8px; border-width: 1.5px; }
.participant-info { min-width: 0; }
.participant-name { font-weight: 600; color: var(--cs-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.participant-role {
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
    padding: 0.1rem 0.35rem; border-radius: 4px;
    display: inline-block;
}
.role-admin, .role-agent { background: rgba(var(--cs-primary-rgb), 0.1); color: var(--cs-primary); }
.role-user { background: #dbeafe; color: #1e40af; }

/* ─── Activity Log Panel ─── */
.activity-panel {
    border-top: 1px solid var(--cs-border);
    background: var(--cs-card-bg);
    max-height: 0; overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1);
}
.activity-panel.open { max-height: 200px; }
.activity-panel-inner {
    padding: 0.75rem 1rem;
    max-height: 180px; overflow-y: auto;
}
.activity-item {
    display: flex; align-items: flex-start; gap: 0.5rem;
    padding: 0.3rem 0; font-size: 0.72rem;
    color: var(--cs-text-muted);
}
.activity-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--cs-border); flex-shrink: 0; margin-top: 0.35rem;
}
.activity-dot.join { background: #22c55e; }
.activity-dot.leave { background: #ef4444; }
.activity-dot.message { background: var(--cs-accent); }
.activity-dot.system { background: #94a3b8; }

/* ─── Chat Footer ─── */
.chat-modal-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--cs-border);
    background: var(--cs-card-bg);
    flex-shrink: 0;
}

/* ─── Message Bubbles ─── */
.chat-msg {
    display: flex; gap: 0.5rem; margin-bottom: 0.75rem;
    animation: msgFadeIn 0.25s ease;
}
@keyframes msgFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.chat-msg.user-msg { flex-direction: row-reverse; }
.chat-msg.system-msg { justify-content: center; }

.msg-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: rgba(var(--cs-primary-rgb), 0.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden;
    font-size: 0.6rem; font-weight: 700; color: var(--cs-primary);
}
.msg-avatar img { width: 100%; height: 100%; object-fit: cover; }

.msg-content { max-width: 75%; min-width: 0; }
.msg-sender {
    display: flex; align-items: center; gap: 0.35rem;
    margin-bottom: 0.2rem;
}
.msg-sender-name { font-size: 0.7rem; font-weight: 700; color: var(--cs-text); }
.msg-sender-role {
    font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
    padding: 0.08rem 0.3rem; border-radius: 3px;
    background: rgba(var(--cs-primary-rgb), 0.1); color: var(--cs-primary);
}
.user-msg .msg-sender { flex-direction: row-reverse; }
.user-msg .msg-sender-role { background: rgba(255,255,255,0.2); color: white; }
.user-msg .msg-sender-name { color: white; }

.msg-bubble {
    padding: 0.65rem 0.9rem;
    border-radius: 16px;
    font-size: 0.85rem; line-height: 1.45;
    word-wrap: break-word;
}
.user-msg .msg-bubble {
    background: linear-gradient(135deg, var(--cs-user-msg-from), var(--cs-user-msg-to));
    color: white;
    border-bottom-right-radius: 4px;
}
.agent-msg .msg-bubble {
    background: var(--cs-agent-msg-bg);
    color: var(--cs-text);
    border-bottom-left-radius: 4px;
}
.system-msg .msg-bubble {
    background: transparent;
    color: var(--cs-text-muted);
    font-size: 0.75rem; font-style: italic;
    text-align: center; padding: 0.25rem 0.5rem;
}

.msg-time {
    font-size: 0.6rem; color: var(--cs-text-muted);
    margin-top: 0.15rem; opacity: 0.7;
}
.user-msg .msg-time { text-align: right; color: rgba(255,255,255,0.6); }

/* ─── Typing Indicator ─── */
.typing-indicator {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0; font-size: 0.72rem; color: var(--cs-text-muted);
}
.typing-dots {
    display: inline-flex; gap: 3px; padding: 2px 0;
}
.typing-dots span {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--cs-primary);
    animation: typingBounce 1.4s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-5px); opacity: 1; }
}

/* ─── Star Rating ─── */
.star-rating { display: flex; gap: 0.25rem; justify-content: center; }
.star-rating .star {
    font-size: 2rem; cursor: pointer; color: #d1d5db;
    transition: color 0.15s, transform 0.15s;
}
.star-rating .star:hover, .star-rating .star.active {
    color: #f59e0b; transform: scale(1.15);
}

/* ─── Reply Input ─── */
.reply-input-wrap {
    display: flex; align-items: center; gap: 0.5rem;
}
.reply-input-wrap input {
    flex: 1; border-radius: 50px; border: 1px solid var(--cs-border);
    padding: 0.55rem 1rem; font-size: 0.85rem;
    background: var(--cs-bg); color: var(--cs-text);
    outline: none; transition: border-color 0.15s;
}
.reply-input-wrap input:focus { border-color: var(--cs-primary); }
.reply-input-wrap input::placeholder { color: var(--cs-text-muted); }
.reply-send-btn {
    width: 40px; height: 40px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, var(--cs-primary), var(--cs-accent));
    color: white; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.15s, box-shadow 0.15s;
    flex-shrink: 0;
}
.reply-send-btn:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(10,45,94,0.3); }

/* ─── Modal Animations ─── */
.modal-backdrop-custom {
    position: fixed; inset: 0; z-index: 1070;
    background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.25s ease;
}
.modal-backdrop-custom.show { display: flex; opacity: 1; }
.modal-panel-custom {
    background: var(--cs-card-bg); border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    width: 100%; max-width: 420px; max-height: 90vh; overflow-y: auto;
    transform: scale(0.9); transition: transform 0.25s cubic-bezier(0.4,0,0.2,1);
}
.modal-backdrop-custom.show .modal-panel-custom { transform: scale(1); }

/* ─── Empty State ─── */
.empty-state-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: rgba(var(--cs-primary-rgb), 0.08);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem;
}
.empty-state-icon i { font-size: 1.5rem; color: var(--cs-primary); }

/* ─── Scrollbar ─── */
.chat-modal-body::-webkit-scrollbar,
.chat-sidebar-inner::-webkit-scrollbar,
.activity-panel-inner::-webkit-scrollbar { width: 4px; }
.chat-modal-body::-webkit-scrollbar-thumb,
.chat-sidebar-inner::-webkit-scrollbar-thumb,
.activity-panel-inner::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>

<!-- ─── PAGE HEADER ─── -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge" style="background:linear-gradient(135deg,#0f172a,#1e40af);color:white;font-size:0.65rem;font-weight:700;padding:0.25rem 0.75rem;border-radius:50px;text-transform:uppercase;letter-spacing:0.5px;">
        <i class="fas fa-headset me-1"></i> Support Center
      </span>
    </div>
    <h3 class="fw-bold text-body mb-0">My Support Tickets</h3>
    <p class="text-muted mb-0 mt-1" style="font-size:0.88rem;">Track, manage, and chat about your support requests.</p>
  </div>
  <button class="btn rounded-pill px-4" style="background:linear-gradient(135deg,#0A2D5E,#1e40af);color:white;font-weight:600;box-shadow:0 4px 12px rgba(10,45,94,0.2);" data-bs-toggle="modal" data-bs-target="#newTicketModal">
    <i class="fas fa-plus me-1"></i> New Ticket
  </button>
</div>

<!-- ─── FILTERS ─── -->
<div class="d-flex gap-2 flex-wrap align-items-center mb-4">
  <span class="text-muted small fw-semibold me-1">Filter:</span>
  <a href="#" class="filter-pill active" onclick="filterTickets('all', event)">All</a>
  <a href="#" class="filter-pill" onclick="filterTickets('waiting', event)">Waiting</a>
  <a href="#" class="filter-pill" onclick="filterTickets('open', event)">Open</a>
  <a href="#" class="filter-pill" onclick="filterTickets('resolved', event)">Resolved</a>
  <a href="#" class="filter-pill" onclick="filterTickets('closed', event)">Closed</a>
</div>

<!-- ─── TICKET CARD GRID ─── -->
<?php if (empty($tickets)): ?>
  <div style="background:var(--cs-card-bg);border:1px solid var(--cs-border);border-radius:14px;">
    <div class="text-center py-5">
      <div class="empty-state-icon">
        <i class="fas fa-ticket-alt"></i>
      </div>
      <h6 class="fw-bold text-body">No tickets yet</h6>
      <p class="text-muted small mb-3">Create a support ticket and our team will help you.</p>
      <button class="btn rounded-pill px-4" style="background:linear-gradient(135deg,#0A2D5E,#1e40af);color:white;" data-bs-toggle="modal" data-bs-target="#newTicketModal">
        <i class="fas fa-plus me-1"></i> Create First Ticket
      </button>
    </div>
  </div>
<?php else: ?>
<div class="ticket-grid" id="ticketGrid">
  <?php foreach ($tickets as $t):
    $prioColor = match($t['priority']) { 'urgent'=>'#dc2626', 'high'=>'#ea580c', 'medium'=>'#d97706', default=>'#059669' };
    $statusLabel = ucfirst(str_replace('_', ' ', $t['status']));
  ?>
  <div class="ticket-card" id="tc-<?= $t['id'] ?>" data-status="<?= $t['status'] ?>" data-priority="<?= $t['priority'] ?>" onclick="openTicketChat(<?= $t['id'] ?>)">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span style="font-size:0.68rem;font-weight:700;color:<?= $prioColor ?>;">#<?= htmlspecialchars($t['ticket_number']??'N/A') ?></span>
      <span class="status-badge status-<?= $t['status'] ?>"><?= $statusLabel ?></span>
    </div>
    <h6 class="fw-bold text-body mb-2" style="font-size:0.88rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($t['subject']) ?></h6>
    <div class="d-flex align-items-center gap-2 mb-2" style="font-size:0.72rem;color:#64748b;">
      <i class="fas fa-tag"></i>
      <span><?= ucfirst($t['category']) ?></span>
      <span>·</span>
      <span><?= ucfirst($t['priority']) ?></span>
    </div>
    <div class="mt-auto pt-2" style="border-top:1px solid var(--cs-border);">
      <div class="d-flex justify-content-between align-items-center">
        <?php if ($t['agent_name']): ?>
          <div class="d-flex align-items-center gap-1" style="font-size:0.72rem;">
            <div style="width:20px;height:20px;border-radius:50%;background:rgba(10,45,94,0.1);display:flex;align-items:center;justify-content:center;overflow:hidden;">
              <?php if (!empty($t['agent_picture'])): ?>
                <img src="<?= htmlspecialchars($t['agent_picture']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
              <?php else: ?>
                <span style="font-size:0.55rem;font-weight:700;color:var(--cs-primary);"><?= strtoupper(substr($t['agent_name'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <span style="color:#059669;font-weight:600;"><?= htmlspecialchars($t['agent_name']) ?></span>
          </div>
        <?php else: ?>
          <span style="font-size:0.72rem;color:#94a3b8;"><i class="fas fa-clock me-1"></i>Waiting for agent</span>
        <?php endif; ?>
        <span style="font-size:0.65rem;color:#94a3b8;"><?= date('M d', strtotime($t['updated_at'])) ?></span>
      </div>
    </div>
    <?php if ($t['unread_count'] > 0): ?>
    <div style="position:absolute;top:12px;right:12px;background:#dc2626;color:white;font-size:0.6rem;font-weight:700;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?= $t['unread_count'] ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ─── CHAT MODAL ─── -->
<div class="chat-modal-overlay" id="chatOverlay" onclick="closeChatModal()"></div>
<div class="chat-modal" id="chatModal">
  <!-- Header -->
  <div class="chat-modal-header">
    <button class="btn-icon" style="width:32px;height:32px;border-radius:8px;border:none;background:rgba(10,45,94,0.06);color:var(--cs-text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;" onclick="closeChatModal()">
      <i class="fas fa-arrow-left"></i>
    </button>
    <div class="chat-header-agent">
      <div class="chat-header-agent-avatar" id="modalAgentAvatar">
        <span id="modalAgentAvatarText" style="font-weight:700;color:var(--cs-primary);">?</span>
        <div class="presence-dot offline" id="modalAgentPresence"></div>
      </div>
      <div class="chat-header-meta">
        <div class="d-flex align-items-center gap-2">
          <span class="status-badge" id="modalStatusBadge" style="font-size:0.6rem;"></span>
          <span class="ticket-num" id="modalTicketNum"></span>
        </div>
        <div class="ticket-subject" id="modalSubject"></div>
        <div id="modalAgentInfoRow" style="display:none;">
          <span class="agent-name" id="modalAgentNameLabel"></span>
        </div>
        <div id="modalWaitingInfoRow" style="display:none;">
          <span class="waiting-text"><i class="fas fa-hourglass-half me-1"></i> Waiting for agent...</span>
        </div>
      </div>
    </div>
    <div id="modalTypingArea" style="display:none;">
      <div class="typing-indicator" style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:rgba(0,0,0,0.04);border-radius:20px;margin:4px 0;">
        <div id="typingAvatar" style="width:22px;height:22px;border-radius:50%;background:rgba(var(--cs-primary-rgb),0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas fa-user" style="font-size:0.55rem;color:var(--cs-primary);"></i>
        </div>
        <span id="typingNames" style="font-size:0.72rem;font-weight:600;color:var(--cs-text);"></span>
        <div class="typing-dots"><span></span><span></span><span></span></div>
      </div>
    </div>
    <div class="chat-header-actions">
      <button class="btn-icon" id="btnParticipants" title="Participants" onclick="toggleParticipants()">
        <i class="fas fa-users"></i>
        <span class="notif-dot" id="participantsDot" style="display:none;"></span>
      </button>
      <button class="btn-icon" id="btnActivity" title="Activity Log" onclick="toggleActivity()">
        <i class="fas fa-list-ul"></i>
      </button>
    </div>
  </div>

  <!-- Body -->
  <div class="chat-body-wrapper">
    <div class="chat-modal-body" id="modalChatArea">
      <div class="text-center py-5 text-muted">
        <i class="fas fa-headset d-block mb-3" style="font-size:2.5rem;color:#cbd5e1;"></i>
        <p class="small">Select a ticket to view the conversation.</p>
      </div>
    </div>

    <!-- Participants Sidebar -->
    <div class="chat-sidebar" id="participantsSidebar">
      <div class="chat-sidebar-inner">
        <div class="chat-sidebar-title"><i class="fas fa-users"></i> Participants</div>
        <div id="participantsList"></div>
      </div>
    </div>
  </div>

  <!-- Activity Panel -->
  <div class="activity-panel" id="activityPanel">
    <div class="activity-panel-inner">
      <div class="chat-sidebar-title"><i class="fas fa-list-ul"></i> Activity</div>
      <div id="activityLog"></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="chat-modal-footer" id="modalReplyFooter" style="display:none;">
    <div class="reply-input-wrap">
      <input type="text" id="userReplyInput" placeholder="Type your message..." autocomplete="off">
      <button class="reply-send-btn" onclick="sendUserReply()" title="Send">
        <i class="fas fa-paper-plane" style="font-size:0.85rem;"></i>
      </button>
    </div>
  </div>
</div>

<!-- ─── NEW TICKET MODAL ─── -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius:16px;overflow:hidden;">
      <div class="modal-header text-white" style="background:linear-gradient(135deg,#0A2D5E,#1e40af);border:none;">
        <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>New Support Ticket</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="newTicketForm" onsubmit="return submitNewTicket(event)">
          <div class="mb-3">
            <label class="form-label fw-semibold small text-muted">Subject</label>
            <input type="text" name="subject" class="form-control" placeholder="Brief description of your issue" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold small text-muted">Category</label>
              <select name="category" class="form-select">
                <option value="general">General</option>
                <option value="technical">Technical</option>
                <option value="billing">Billing</option>
                <option value="sales">Sales</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold small text-muted">Priority</label>
              <select name="priority" class="form-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold small text-muted">Message</label>
            <textarea name="message" class="form-control" rows="4" placeholder="Describe your issue in detail..." required></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn flex-grow-1" style="background:linear-gradient(135deg,#0A2D5E,#1e40af);color:white;font-weight:600;">Submit Ticket</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ─── RATING MODAL ─── -->
<div class="modal fade" id="ratingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow" style="border-radius:16px;overflow:hidden;">
      <div class="modal-body p-4 text-center">
        <h6 class="fw-bold text-body mb-2">Rate Your Experience</h6>
        <p class="text-muted small mb-3">How was your support experience?</p>
        <div class="star-rating mb-3" id="starRating">
          <i class="fas fa-star star" data-rating="1" onclick="setRating(1)"></i>
          <i class="fas fa-star star" data-rating="2" onclick="setRating(2)"></i>
          <i class="fas fa-star star" data-rating="3" onclick="setRating(3)"></i>
          <i class="fas fa-star star" data-rating="4" onclick="setRating(4)"></i>
          <i class="fas fa-star star" data-rating="5" onclick="setRating(5)"></i>
        </div>
        <textarea id="ratingFeedback" class="form-control mb-3" rows="2" placeholder="Optional feedback..." style="border-radius:12px;font-size:0.85rem;"></textarea>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
          <button class="btn flex-grow-1" onclick="submitRating()" style="background:#f59e0b;color:white;border-radius:10px;font-weight:600;">Submit</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API = '../api/ticket_api.php';
const CURRENT_USER_ID = <?= $userId ?>;
let activeTicketId = null;
let msgPollInterval = null;
let typingPollInterval = null;
let presencePollInterval = null;
let participantsPollInterval = null;
let activityPollInterval = null;
let typingTimeout = null;
let selectedRating = 0;
let participantsOpen = false;
let activityOpen = false;

/* ─── Submit New Ticket ─── */
function submitNewTicket(e) {
    e.preventDefault();
    const form = document.getElementById('newTicketForm');
    const fd = new FormData(form);
    fd.append('action', 'create_ticket');
    fetch(API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('newTicketModal')).hide();
            Swal.fire({icon:'success', title:'Ticket Created', text:'Your ticket ' + data.ticket_number + ' has been created. An agent will join shortly.', timer:2500, showConfirmButton:false});
            setTimeout(() => location.reload(), 2500);
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.error || 'Failed.', confirmButtonColor:'#dc3545'});
        }
    });
    return false;
}

/* ─── Open Ticket Chat Modal ─── */
function openTicketChat(ticketId) {
    activeTicketId = ticketId;
    document.querySelectorAll('.ticket-card').forEach(c => c.classList.remove('active'));
    const card = document.getElementById('tc-' + ticketId);
    if (card) card.classList.add('active');

    document.getElementById('chatOverlay').classList.add('open');
    document.getElementById('chatModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    loadMessages(ticketId);
    loadAgentInfo(ticketId);
    startMsgPolling(ticketId);
    startTypingPolling(ticketId);
    startPresencePolling(ticketId);
    startParticipantsPolling(ticketId);
    startActivityPolling(ticketId);
}

function closeChatModal() {
    document.getElementById('chatOverlay').classList.remove('open');
    document.getElementById('chatModal').classList.remove('open');
    document.body.style.overflow = '';
    clearAllPolling();
    closeParticipants();
    closeActivity();
}

function clearAllPolling() {
    if (msgPollInterval) { clearInterval(msgPollInterval); msgPollInterval = null; }
    if (typingPollInterval) { clearInterval(typingPollInterval); typingPollInterval = null; }
    if (presencePollInterval) { clearInterval(presencePollInterval); presencePollInterval = null; }
    if (participantsPollInterval) { clearInterval(participantsPollInterval); participantsPollInterval = null; }
    if (activityPollInterval) { clearInterval(activityPollInterval); activityPollInterval = null; }
}

/* ─── Load Messages ─── */
function loadMessages(ticketId) {
    const area = document.getElementById('modalChatArea');
    fetch(API + '?action=get_messages&ticket_id=' + ticketId)
    .then(r => r.json())
    .then(data => {
        if (!data.success) { area.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>'; return; }

        const statusBadge = document.getElementById('modalStatusBadge');
        statusBadge.className = 'status-badge status-' + data.status;
        statusBadge.textContent = data.status.replace('_',' ').toUpperCase();

        const card = document.getElementById('tc-' + ticketId);
        const ticketNumEl = card ? card.querySelector('[style*="font-weight:700"]') : null;
        document.getElementById('modalTicketNum').textContent = '#' + (ticketNumEl ? ticketNumEl.textContent.replace('#','') : 'N/A');
        document.getElementById('modalSubject').textContent = card ? card.querySelector('.fw-bold.text-body')?.textContent || '' : '';

        const replyFooter = document.getElementById('modalReplyFooter');
        const resolvedStatuses = ['resolved', 'closed'];
        replyFooter.style.display = resolvedStatuses.includes(data.status) ? 'none' : 'block';

        if (data.messages.length === 0) {
            area.innerHTML = '<p class="text-center text-muted py-5">No messages yet.</p>';
            return;
        }

        area.innerHTML = '';
        data.messages.forEach(msg => {
            area.appendChild(buildMessageEl(msg));
        });

        scrollToBottom();

        if (data.status === 'resolved') {
            const actionDiv = document.createElement('div');
            actionDiv.className = 'text-center py-3';
            actionDiv.innerHTML = '<p class="text-muted small mb-2">Was your issue resolved?</p>' +
                '<div class="d-flex gap-2 justify-content-center">' +
                '<button class="btn btn-sm rounded-pill px-3" style="background:#dcfce7;color:#15803d;border:none;font-weight:600;" onclick="openRatingModal()"><i class="fas fa-star me-1"></i>Satisfied</button>' +
                '<button class="btn btn-sm rounded-pill px-3" style="background:#fef3c7;color:#92400e;border:none;font-weight:600;" onclick="reopenTicket()"><i class="fas fa-redo me-1"></i>Need More Help</button>' +
                '</div>';
            area.appendChild(actionDiv);
        }
    })
    .catch(() => { area.innerHTML = '<div class="alert alert-danger">Failed to load messages.</div>'; });
}

function buildMessageEl(msg) {
    const isSystem = msg.message_type === 'system';
    const isUser = msg.user_id == CURRENT_USER_ID;
    const role = isSystem ? 'system' : (isUser ? 'user' : 'agent');
    const time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

    const wrapper = document.createElement('div');
    wrapper.className = 'chat-msg ' + role + '-msg';

    if (isSystem) {
        wrapper.innerHTML = '<div class="msg-bubble system-msg">' + escapeHtml(msg.message) + '</div>';
        return wrapper;
    }

    const picHtml = msg.sender_pic
        ? '<img src="' + escapeAttr(msg.sender_pic) + '">'
        : '<span>' + escapeHtml((msg.sender_name || '?').charAt(0)) + '</span>';

    const roleLabel = isUser ? 'You' : 'Agent';
    const roleBadgeClass = isUser ? 'role-user' : 'role-agent';

    wrapper.innerHTML =
        '<div class="msg-avatar">' + picHtml + '</div>' +
        '<div class="msg-content">' +
            '<div class="msg-sender">' +
                '<span class="msg-sender-name">' + escapeHtml(msg.sender_name || roleLabel) + '</span>' +
                '<span class="msg-sender-role ' + roleBadgeClass + '">' + roleLabel + '</span>' +
            '</div>' +
            '<div class="msg-bubble">' + escapeHtml(msg.message) + '</div>' +
            '<div class="msg-time">' + time + '</div>' +
        '</div>';

    return wrapper;
}

function scrollToBottom() {
    const area = document.getElementById('modalChatArea');
    setTimeout(() => { area.scrollTop = area.scrollHeight; }, 80);
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

function escapeAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ─── Load Agent Info ─── */
function loadAgentInfo(ticketId) {
    fetch(API + '?action=agent_info&ticket_id=' + ticketId)
    .then(r => r.json())
    .then(data => {
        const agentInfoRow = document.getElementById('modalAgentInfoRow');
        const waitingInfoRow = document.getElementById('modalWaitingInfoRow');
        const agentAvatarEl = document.getElementById('modalAgentAvatar');
        const avatarText = document.getElementById('modalAgentAvatarText');
        const presenceDot = document.getElementById('modalAgentPresence');

        if (data.agent) {
            agentInfoRow.style.display = 'block';
            waitingInfoRow.style.display = 'none';
            document.getElementById('modalAgentNameLabel').textContent = data.agent.name;

            const statusClass = data.agent.status === 'online' ? 'online' : (data.agent.status === 'busy' ? 'busy' : 'offline');
            presenceDot.className = 'presence-dot ' + statusClass;

            if (data.agent.picture) {
                avatarText.style.display = 'none';
                const img = document.createElement('img');
                img.src = data.agent.picture;
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                agentAvatarEl.querySelectorAll('img').forEach(i => i.remove());
                agentAvatarEl.appendChild(img);
            } else {
                avatarText.style.display = '';
                avatarText.textContent = data.agent.name.charAt(0);
                agentAvatarEl.querySelectorAll('img').forEach(i => i.remove());
            }
        } else {
            agentInfoRow.style.display = 'none';
            waitingInfoRow.style.display = 'block';
            presenceDot.className = 'presence-dot offline';
            avatarText.style.display = '';
            avatarText.textContent = '?';
            agentAvatarEl.querySelectorAll('img').forEach(i => i.remove());
        }
    });
}

/* ─── Send Reply ─── */
function sendUserReply() {
    const input = document.getElementById('userReplyInput');
    const msg = input.value.trim();
    if (!msg || !activeTicketId) return;
    const fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('ticket_id', activeTicketId);
    fd.append('message', msg);
    fetch(API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadMessages(activeTicketId);
            clearTyping();
        }
    });
}

function clearTyping() {
    const tfd = new FormData();
    tfd.append('action', 'typing');
    tfd.append('ticket_id', activeTicketId);
    tfd.append('is_typing', '0');
    fetch(API, { method: 'POST', body: tfd });
}

/* ─── Typing Indicator ─── */
function setupTypingInput() {
    document.getElementById('userReplyInput').addEventListener('input', function() {
        if (!activeTicketId) return;
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('ticket_id', activeTicketId);
        fd.append('is_typing', '1');
        fetch(API, { method: 'POST', body: fd });
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(clearTyping, 3000);
    });

    document.getElementById('userReplyInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendUserReply();
    });
}
setupTypingInput();

/* ─── Polling: Messages ─── */
function startMsgPolling(ticketId) {
    if (msgPollInterval) clearInterval(msgPollInterval);
    msgPollInterval = setInterval(() => { if (activeTicketId) loadMessages(activeTicketId); }, 4000);
}

/* ─── Polling: Typing ─── */
function startTypingPolling(ticketId) {
    if (typingPollInterval) clearInterval(typingPollInterval);
    typingPollInterval = setInterval(() => {
        if (!activeTicketId) return;
        fetch(API + '?action=get_typing&ticket_id=' + activeTicketId)
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('modalTypingArea');
            const namesEl = document.getElementById('typingNames');
            const avatarEl = document.getElementById('typingAvatar');
            if (data.typers && data.typers.length > 0) {
                const typer = data.typers[0];
                namesEl.textContent = typer.name + (data.typers.length === 1 ? ' is typing' : ' and ' + (data.typers.length - 1) + ' other' + (data.typers.length > 2 ? 's' : '') + ' are typing');
                if (typer.avatar_url) {
                    avatarEl.innerHTML = '<img src="' + typer.avatar_url + '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">';
                } else {
                    avatarEl.innerHTML = '<span style="font-size:0.55rem;font-weight:700;color:var(--cs-primary);">' + (typer.name || '?').charAt(0) + '</span>';
                }
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }).catch(() => {});
    }, 2000);
}

/* ─── Polling: Presence ─── */
function startPresencePolling(ticketId) {
    if (presencePollInterval) clearInterval(presencePollInterval);
    presencePollInterval = setInterval(() => {
        if (!activeTicketId) return;
        fetch(API + '?action=get_presence&ticket_id=' + activeTicketId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.viewers) {
                const viewerNames = data.viewers.filter(v => v.user_id != CURRENT_USER_ID).map(v => v.name);
                const dot = document.getElementById('participantsDot');
                if (viewerNames.length > 0) {
                    dot.style.display = 'block';
                    dot.title = 'Viewing: ' + viewerNames.join(', ');
                } else {
                    dot.style.display = 'none';
                }
            }
        }).catch(() => {});
    }, 5000);
}

/* ─── Polling: Participants ─── */
function startParticipantsPolling(ticketId) {
    if (participantsPollInterval) clearInterval(participantsPollInterval);
    participantsPollInterval = setInterval(() => {
        if (!activeTicketId) return;
        fetchParticipants();
    }, 6000);
}

function fetchParticipants() {
    if (!activeTicketId) return;
    fetch(API + '?action=get_participants&ticket_id=' + activeTicketId)
    .then(r => r.json())
    .then(data => {
        const list = document.getElementById('participantsList');
        if (!data.success || !data.participants || data.participants.length === 0) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.72rem;">No participants yet.</p>';
            return;
        }
        list.innerHTML = '';
        data.participants.forEach(p => {
            const isOnline = p.status === 'online';
            const dotClass = isOnline ? 'online' : (p.status === 'busy' ? 'busy' : 'offline');
            const roleClass = p.role === 'admin' || p.role === 'agent' ? 'role-admin' : 'role-user';
            const picHtml = p.picture
                ? '<img src="' + escapeAttr(p.picture) + '">'
                : escapeHtml((p.name || '?').charAt(0));

            const item = document.createElement('div');
            item.className = 'participant-item';
            item.innerHTML =
                '<div class="participant-avatar">' + picHtml +
                    '<div class="presence-dot ' + dotClass + '"></div>' +
                '</div>' +
                '<div class="participant-info">' +
                    '<div class="participant-name">' + escapeHtml(p.name) + '</div>' +
                    '<span class="participant-role ' + roleClass + '">' + escapeHtml(p.role || 'user') + '</span>' +
                '</div>';
            list.appendChild(item);
        });
    }).catch(() => {});
}

/* ─── Polling: Activity Log ─── */
function startActivityPolling(ticketId) {
    if (activityPollInterval) clearInterval(activityPollInterval);
    activityPollInterval = setInterval(() => {
        if (!activeTicketId) return;
        fetchActivityLog();
    }, 8000);
}

function fetchActivityLog() {
    if (!activeTicketId) return;
    fetch(API + '?action=get_activity_log&ticket_id=' + activeTicketId)
    .then(r => r.json())
    .then(data => {
        const log = document.getElementById('activityLog');
        if (!data.success || !data.activities || data.activities.length === 0) {
            log.innerHTML = '<p class="text-muted" style="font-size:0.72rem;">No activity yet.</p>';
            return;
        }
        log.innerHTML = '';
        data.activities.forEach(a => {
            const dotClass = a.type === 'join' ? 'join' : (a.type === 'leave' ? 'leave' : (a.type === 'message' ? 'message' : 'system'));
            const time = new Date(a.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            const item = document.createElement('div');
            item.className = 'activity-item';
            item.innerHTML =
                '<div class="activity-dot ' + dotClass + '"></div>' +
                '<div><span>' + escapeHtml(a.description) + '</span> <span style="opacity:0.6;">' + time + '</span></div>';
            log.appendChild(item);
        });
    }).catch(() => {});
}

/* ─── Participants Panel Toggle ─── */
function toggleParticipants() {
    participantsOpen = !participantsOpen;
    const sidebar = document.getElementById('participantsSidebar');
    const btn = document.getElementById('btnParticipants');
    if (participantsOpen) {
        sidebar.classList.add('open');
        btn.classList.add('active');
        fetchParticipants();
    } else {
        sidebar.classList.remove('open');
        btn.classList.remove('active');
    }
}

function closeParticipants() {
    participantsOpen = false;
    document.getElementById('participantsSidebar').classList.remove('open');
    document.getElementById('btnParticipants').classList.remove('active');
}

/* ─── Activity Panel Toggle ─── */
function toggleActivity() {
    activityOpen = !activityOpen;
    const panel = document.getElementById('activityPanel');
    const btn = document.getElementById('btnActivity');
    if (activityOpen) {
        panel.classList.add('open');
        btn.classList.add('active');
        fetchActivityLog();
    } else {
        panel.classList.remove('open');
        btn.classList.remove('active');
    }
}

function closeActivity() {
    activityOpen = false;
    document.getElementById('activityPanel').classList.remove('open');
    document.getElementById('btnActivity').classList.remove('active');
}

/* ─── Rating ─── */
function openRatingModal() { selectedRating = 0; updateStars(); new bootstrap.Modal(document.getElementById('ratingModal')).show(); }
function setRating(r) { selectedRating = r; updateStars(); }
function updateStars() {
    document.querySelectorAll('#starRating .star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.rating) <= selectedRating);
    });
}
function submitRating() {
    if (!selectedRating || !activeTicketId) return;
    const fd = new FormData();
    fd.append('action', 'rate_ticket');
    fd.append('ticket_id', activeTicketId);
    fd.append('rating', selectedRating);
    fd.append('feedback', document.getElementById('ratingFeedback').value);
    fetch(API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const cfd = new FormData();
            cfd.append('action', 'close_ticket');
            cfd.append('ticket_id', activeTicketId);
            fetch(API, { method: 'POST', body: cfd })
            .then(r => r.json())
            .then(() => {
                bootstrap.Modal.getInstance(document.getElementById('ratingModal')).hide();
                closeChatModal();
                Swal.fire({icon:'success', title:'Thank You!', text:'Your feedback has been recorded.', timer:2000, showConfirmButton:false});
                setTimeout(() => location.reload(), 2000);
            });
        }
    });
}

function reopenTicket() {
    if (!activeTicketId) return;
    const fd = new FormData();
    fd.append('action', 'reopen_ticket');
    fd.append('ticket_id', activeTicketId);
    fetch(API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages(activeTicketId);
            Swal.fire({icon:'info', title:'Ticket Reopened', text:'An agent will respond shortly.', timer:2000, showConfirmButton:false});
        }
    });
}

/* ─── Filter ─── */
function filterTickets(status, e) {
    if (e) e.preventDefault();
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    if (e) e.target.classList.add('active');
    document.querySelectorAll('.ticket-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

/* ─── ESC to close ─── */
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeChatModal(); });

/* ─── Auto-load from URL ─── */
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(location.search);
    const tid = params.get('ticket_id');
    if (tid) { const card = document.getElementById('tc-' + tid); if (card) card.click(); }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
