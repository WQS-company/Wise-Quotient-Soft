<?php
$path_to_root = "../";
$page_title = "Bot Chat Logs";

// Action for fetching transcripts via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_transcript') {
    header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Access control: Ensure user is admin
    if (!isset($_SESSION['user']['role']) || strtolower($_SESSION['user']['role']) !== 'admin') {
        echo json_encode(['error' => 'Unauthorized access.']);
        exit;
    }

    require_once dirname(__DIR__) . '/config.php';
    $session_id = $_GET['session_id'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT role, message, is_critical, created_at FROM bot_chats WHERE session_id = ? ORDER BY created_at ASC");
        $stmt->execute([$session_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($messages);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Access check
if (strtolower($headerUser['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Handle filters
$filter = $_GET['filter'] ?? 'all'; // 'all' or 'critical'
$search = trim($_GET['search'] ?? '');

// Base query to fetch sessions
$sql = "
    SELECT 
        c.session_id,
        c.user_id,
        u.name as user_name,
        u.email as user_email,
        MAX(c.created_at) as last_active,
        COUNT(*) as msg_count,
        MAX(c.is_critical) as has_critical
    FROM bot_chats c
    LEFT JOIN users u ON c.user_id = u.id
";

$conditions = [];
$params = [];

if ($filter === 'critical') {
    $conditions[] = "c.session_id IN (SELECT DISTINCT session_id FROM bot_chats WHERE is_critical = 1)";
}

if ($search !== '') {
    $conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR c.session_id LIKE ? OR c.message LIKE ?)";
    $likeParam = "%" . $search . "%";
    $params[] = $likeParam;
    $params[] = $likeParam;
    $params[] = $likeParam;
    $params[] = $likeParam;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY c.session_id, c.user_id ORDER BY last_active DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sessions = [];
    $error_msg = "Database Error: " . $e->getMessage();
}
?>

<style>
/* ======= Bot Chat Logs Styles ======= */
.chat-logs-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 24px rgba(0,0,0,0.02);
    overflow: hidden;
}
.chat-logs-header {
    background: #fdfdfd;
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}
.filter-tabs {
    display: flex;
    gap: 0.5rem;
}
.filter-tab {
    padding: 0.5rem 1.2rem;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.25s ease;
    border: 1px solid transparent;
}
.filter-tab.active {
    background: #0A2D5E;
    color: #ffffff;
}
.filter-tab:not(.active) {
    background: #f1f5f9;
    color: #475569;
    border-color: #e2e8f0;
}
.filter-tab:not(.active):hover {
    background: #e2e8f0;
}
.critical-badge {
    background: #fecdd3;
    color: #9f1239;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.normal-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.6rem;
    border-radius: 50px;
}
.msg-count-badge {
    background: #e0f2fe;
    color: #0369a1;
    font-weight: 700;
    font-size: 0.8rem;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.transcript-bubble {
    padding: 10px 14px;
    border-radius: 14px;
    margin-bottom: 0.75rem;
    max-width: 85%;
    font-size: 0.9rem;
    line-height: 1.4;
    word-wrap: break-word;
    white-space: pre-wrap;
}
.transcript-bubble.user {
    background: #d9f2ff;
    color: #0369a1;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}
.transcript-bubble.bot {
    background: #f1f5f9;
    color: #1e293b;
    margin-right: auto;
    border-bottom-left-radius: 2px;
}
.transcript-bubble.critical {
    border: 1.5px solid #f43f5e;
    background: #fff1f2;
    color: #9f1239;
}
.critical-tag {
    font-size: 0.7rem;
    color: #e11d48;
    font-weight: 700;
    margin-bottom: 2px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.modal-chat-body {
    max-height: 450px;
    overflow-y: auto;
    padding: 1.5rem;
    background:var(--color-bg);
    border-radius: 8px;
}
.modal-chat-body::-webkit-scrollbar {
    width: 6px;
}
.modal-chat-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}
</style>

<div class="container-fluid py-4">
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="chat-logs-card mb-4">
        <div class="chat-logs-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="filter-tabs">
                <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All Discussions
                </a>
                <a href="?filter=critical&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter === 'critical' ? 'active' : '' ?>">
                    ⚠️ Critical Issues Only
                </a>
            </div>
            
            <form method="GET" class="d-flex gap-2 align-items-center" style="max-width: 400px; width: 100%;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="input-group">
                    <span class="input-group-text bg-body border-end-0 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name, message, keyword..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary px-3" style="background-color:#0A2D5E; border-color:#0A2D5E;">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light text-muted" style="font-size: 0.85rem; font-weight: 700;">
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Session ID</th>
                        <th class="text-center">Message Count</th>
                        <th>Last Message At</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="far fa-comments d-block mb-3" style="font-size: 2.5rem;"></i>
                                No matching discussions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $sess): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($sess['user_id']): ?>
                                        <div class="fw-bold text-body"><?= htmlspecialchars($sess['user_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($sess['user_email']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-user-secret me-1"></i> Guest User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size: 0.8rem;"><?= htmlspecialchars($sess['session_id']) ?></code>
                                </td>
                                <td class="text-center">
                                    <span class="msg-count-badge"><?= $sess['msg_count'] ?></span>
                                </td>
                                <td>
                                    <?= date('M j, Y \a\t g:i A', strtotime($sess['last_active'])) ?>
                                </td>
                                <td>
                                    <?php if ($sess['has_critical']): ?>
                                        <span class="critical-badge">
                                            <i class="fas fa-exclamation-triangle"></i> Critical Log
                                        </span>
                                    <?php else: ?>
                                        <span class="normal-badge">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="openTranscript('<?= htmlspecialchars($sess['session_id']) ?>', '<?= $sess['user_id'] ? htmlspecialchars($sess['user_name']) : 'Guest' ?>')">
                                        <i class="fas fa-eye me-1"></i> View History
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transcript Modal -->
<div class="modal fade" id="transcriptModal" tabindex="-1" aria-labelledby="transcriptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background-color: #0A2D5E; border-top-left-radius: 0.3rem; border-top-right-radius: 0.3rem;">
                <h5 class="modal-title" id="transcriptModalLabel">
                    <i class="fas fa-robot me-2"></i> WiseBot Dialogue Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <div class="mb-3 px-1 d-flex justify-content-between align-items-center">
                    <span class="text-muted" style="font-size: 0.85rem;">User: <strong id="modalUserName" class="text-body">Guest</strong></span>
                    <span class="text-muted" style="font-size: 0.85rem;">Session: <code id="modalSessionId">...</code></span>
                </div>
                <div class="modal-chat-body" id="modalChatContent">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Logs</button>
            </div>
        </div>
    </div>
</div>

<script>
let transcriptModal = null;

document.addEventListener("DOMContentLoaded", function() {
    transcriptModal = new bootstrap.Modal(document.getElementById('transcriptModal'));
});

function openTranscript(sessionId, userName) {
    document.getElementById('modalUserName').innerText = userName;
    document.getElementById('modalSessionId').innerText = sessionId;
    
    const contentArea = document.getElementById('modalChatContent');
    contentArea.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Retrieving dialogue history...</p>
        </div>
    `;
    
    transcriptModal.show();
    
    fetch(`?action=get_transcript&session_id=${sessionId}`)
        .then(response => response.json())
        .then(data => {
            contentArea.innerHTML = '';
            if (data.error) {
                contentArea.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            if (data.length === 0) {
                contentArea.innerHTML = `<p class="text-center text-muted my-4">No records found for this session.</p>`;
                return;
            }
            
            data.forEach(msg => {
                const bubble = document.createElement('div');
                const isUser = msg.role === 'user';
                const roleClass = isUser ? 'user' : 'bot';
                
                bubble.className = `transcript-bubble ${roleClass}`;
                if (isUser && parseInt(msg.is_critical) === 1) {
                    bubble.className += ' critical';
                }
                
                const timeStr = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                let contentHTML = '';
                if (isUser && parseInt(msg.is_critical) === 1) {
                    contentHTML += `<span class="critical-tag"><i class="fas fa-exclamation-circle me-1"></i> Critical Trigger</span>`;
                }
                
                contentHTML += `<div>${formatMessageText(msg.message)}</div>`;
                contentHTML += `<div class="text-end text-muted mt-1" style="font-size: 0.7rem; opacity: 0.85;">${timeStr}</div>`;
                
                bubble.innerHTML = contentHTML;
                contentArea.appendChild(bubble);
            });
            
            // Scroll to bottom
            setTimeout(() => {
                contentArea.scrollTop = contentArea.scrollHeight;
            }, 100);
        })
        .catch(err => {
            contentArea.innerHTML = `<div class="alert alert-danger">Failed to connect to backend server.</div>`;
        });
}

function formatMessageText(text) {
    let escaped = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
        
    // Parse ```code blocks```
    escaped = escaped.replace(/```([\s\S]*?)```/g, '<pre style="background:rgba(0,0,0,0.05); padding:8px; border-radius:6px; font-size:0.85rem; font-family:monospace; white-space:pre-wrap; margin:8px 0;"><code>$1</code></pre>');

    // Parse `inline code`
    escaped = escaped.replace(/`(.*?)`/g, '<code style="background:rgba(0,0,0,0.05); padding:2px 4px; border-radius:4px; font-family:monospace; font-size:0.9rem;">$1</code>');

    // Parse Markdown **bold** to <strong>bold</strong> (multiline support)
    escaped = escaped.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');
    
    // Parse sub-headings
    escaped = escaped.replace(/### (.*?)\n/g, '<h6 class="fw-bold my-2" style="color:#0A2D5E;">$1</h6>');
    escaped = escaped.replace(/## (.*?)\n/g, '<h5 class="fw-bold my-2" style="color:#0A2D5E;">$1</h5>');
    escaped = escaped.replace(/# (.*?)\n/g, '<h4 class="fw-bold my-2" style="color:#0A2D5E;">$1</h4>');

    // Parse [Attached Image: PATH]
    escaped = escaped.replace(
        /\[Attached Image:\s*(.*?)\]/g, 
        (match, p1) => {
            const fullPath = p1.startsWith('http') ? p1 : '../' + p1;
            return `<br><img src="${fullPath}" style="max-width:100%; max-height:150px; border-radius:8px; margin-top:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="Image Attachment" />`;
        }
    );
    
    // Parse [Attached File: NAME | PATH]
    escaped = escaped.replace(
        /\[Attached File:\s*(.*?)\s*\|\s*(.*?)\]/g, 
        (match, p1, p2) => {
            const fullPath = p2.startsWith('http') ? p2 : '../' + p2;
            return `<br><a href="${fullPath}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,102,0,0.1); color:#ff6600; padding:6px 12px; border-radius:8px; font-weight:600; text-decoration:none; margin-top:6px; border:1px solid rgba(255,102,0,0.25); font-size:0.85rem;"><i class="fas fa-file-pdf"></i> ${p1}</a>`;
        }
    );
    
    return escaped.replace(/\n/g, '<br>');
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
