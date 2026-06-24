<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

require_once $path_to_root . 'config.php';
require_once $path_to_root . 'includes/sms_helper.php';
require_once $path_to_root . 'includes/fcm_helper.php';

// Handle Actions (Mark as Read, Replied, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['message_id'])) {
    $action = $_POST['action'];
    $messageId = (int)$_POST['message_id'];
    
    try {
        if ($action === 'mark_read') {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->execute([$messageId]);
            $_SESSION['success_message'] = "Message marked as read.";
        } elseif ($action === 'mark_replied') {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
            $stmt->execute([$messageId]);
            $_SESSION['success_message'] = "Message marked as replied.";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $_SESSION['success_message'] = "Message deleted successfully.";
        } elseif ($action === 'send_reply') {
            $replyMsg = trim($_POST['reply_message'] ?? '');
            $sendEmail = isset($_POST['send_email']);
            $sendSms = isset($_POST['send_sms']);
            $sendPush = isset($_POST['send_push']);
            
            // Get original message
            $mStmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
            $mStmt->execute([$messageId]);
            $msgRow = $mStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($msgRow && $replyMsg) {
                $channels = [];
                // 1. Send Email
                if ($sendEmail && !empty($msgRow['email'])) {
                    $subject = "Reply to your inquiry (Ref: " . $msgRow['ref_number'] . ")";
                    $body = "Hello " . $msgRow['name'] . ",\n\n" . $replyMsg . "\n\nBest regards,\nWise Quotient Soft";
                    send_smtp_email($msgRow['email'], $subject, nl2br($body), $pdo);
                    $channels[] = 'email';
                }
                
                // 2. Send SMS
                if ($sendSms && !empty($msgRow['phone'])) {
                    send_termii_sms($msgRow['phone'], $replyMsg, $pdo);
                    $channels[] = 'sms';
                }
                
                // 3. Send Push/Portal Note if user exists
                if ($sendPush && !empty($msgRow['email'])) {
                    $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $uStmt->execute([$msgRow['email']]);
                    $userId = $uStmt->fetchColumn();
                    if ($userId) {
                        add_notification($userId, "Support Reply", $replyMsg, 'info', '../user/dashboard.php');
                        FCMHelper::sendNotificationToUser($userId, "Reply from WQS Support", $replyMsg);
                        $channels[] = 'push';
                    }
                }
                
                $chStr = implode(',', $channels);
                
                // Update DB
                $upd = $pdo->prepare("UPDATE contact_messages SET reply_message = ?, reply_sent_at = NOW(), reply_channels = ?, status = 'replied' WHERE id = ?");
                $upd->execute([$replyMsg, $chStr, $messageId]);
                $_SESSION['success_message'] = "Reply sent successfully via " . strtoupper(str_replace(',', ', ', $chStr)) . ".";
            } else {
                $_SESSION['error_message'] = "Reply message cannot be empty.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }
    
    header("Location: contact_messages.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

$page_title = "Contact Messages";
$current_page = "contact_messages.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Pagination and Filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR service LIKE ? OR ref_number LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if (!empty($statusFilter)) {
    $where .= " AND status = ?";
    array_push($params, $statusFilter);
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM contact_messages $where");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch messages
$stmt = $pdo->prepare("SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusBadge($status) {
    if ($status === 'new') return '<span class="badge bg-danger rounded-pill px-2 py-1">New</span>';
    if ($status === 'read') return '<span class="badge bg-info text-dark rounded-pill px-2 py-1">Read</span>';
    if ($status === 'replied') return '<span class="badge bg-success rounded-pill px-2 py-1">Replied</span>';
    return '<span class="badge bg-secondary rounded-pill px-2 py-1">' . htmlspecialchars(ucfirst($status)) . '</span>';
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Contact Messages</h1>
            <p class="text-muted mb-0">View and manage messages from the public contact form.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Search name, email, service..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                        <option value="replied" <?= $statusFilter === 'replied' ? 'selected' : '' ?>>Replied</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Ref</th>
                            <th>Sender</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No messages found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($msg['ref_number']) ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($msg['name']) ?></div>
                                        <div class="small text-muted"><a href="mailto:<?= htmlspecialchars($msg['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($msg['email']) ?></a></div>
                                        <?php if (!empty($msg['phone'])): ?>
                                            <div class="small text-muted"><i class="fas fa-phone fa-sm"></i> <?= htmlspecialchars($msg['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($msg['service']) ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($msg['created_at'])) ?><br><small class="text-muted"><?= date('h:i A', strtotime($msg['created_at'])) ?></small></td>
                                    <td><?= statusBadge($msg['status']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#msgModal<?= $msg['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Render Modals Outside Table Stacking Context -->
<?php if (!empty($messages)): ?>
    <?php foreach ($messages as $msg): ?>
        <div class="modal fade" id="msgModal<?= $msg['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 rounded-4 shadow">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold">Message Details: <?= htmlspecialchars($msg['ref_number']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Name</p>
                                <p class="fw-bold"><?= htmlspecialchars($msg['name']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Email</p>
                                <p class="fw-bold"><a href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Company</p>
                                <p class="fw-bold"><?= htmlspecialchars($msg['company'] ?: 'N/A') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Phone</p>
                                <p class="fw-bold"><a href="tel:<?= htmlspecialchars($msg['phone']) ?>"><?= htmlspecialchars($msg['phone'] ?: 'N/A') ?></a></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Budget</p>
                                <p class="fw-bold"><?= htmlspecialchars($msg['budget'] ?: 'N/A') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">Timeline</p>
                                <p class="fw-bold"><?= htmlspecialchars($msg['timeline'] ?: 'N/A') ?></p>
                            </div>
                        </div>
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <h6 class="fw-bold text-dark">Original Message:</h6>
                            <p class="mb-0 text-break" style="white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></p>
                        </div>
                        
                        <?php if ($msg['status'] === 'replied' && !empty($msg['reply_message'])): ?>
                        <div class="p-3 rounded-3 mb-3 border border-success bg-success bg-opacity-10">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-success mb-0"><i class="fas fa-reply me-1"></i> Our Reply:</h6>
                                <small class="text-success fw-bold"><?= date('M d, Y h:i A', strtotime($msg['reply_sent_at'])) ?></small>
                            </div>
                            <p class="mb-2 text-break" style="white-space: pre-wrap;"><?= htmlspecialchars($msg['reply_message']) ?></p>
                            <small class="text-muted fw-bold">Sent via: <?= strtoupper(str_replace(',', ', ', $msg['reply_channels'])) ?></small>
                        </div>
                        <?php endif; ?>

                        <?php if ($msg['status'] !== 'replied'): ?>
                        <form method="POST" class="mt-4 border-top pt-3">
                            <input type="hidden" name="action" value="send_reply">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <h6 class="fw-bold text-body mb-2"><i class="fas fa-pen text-primary me-1"></i> Compose Reply</h6>
                            <textarea name="reply_message" class="form-control mb-3" rows="4" placeholder="Write your response here..." required></textarea>
                            
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_<?= $msg['id'] ?>" name="send_email" checked>
                                    <label class="form-check-label fw-bold text-muted small" for="email_<?= $msg['id'] ?>"><i class="fas fa-envelope text-primary me-1"></i> Email</label>
                                </div>
                                <?php if (!empty($msg['phone'])): ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sms_<?= $msg['id'] ?>" name="send_sms">
                                    <label class="form-check-label fw-bold text-muted small" for="sms_<?= $msg['id'] ?>"><i class="fas fa-sms text-success me-1"></i> SMS</label>
                                </div>
                                <?php endif; ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="push_<?= $msg['id'] ?>" name="send_push" checked>
                                    <label class="form-check-label fw-bold text-muted small" for="push_<?= $msg['id'] ?>"><i class="fas fa-bell text-warning me-1"></i> Firebase/Portal (if registered)</label>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill"><i class="fas fa-paper-plane me-2"></i> Send Reply</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <?php if ($msg['status'] !== 'read' && $msg['status'] !== 'replied'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <button type="submit" class="btn btn-outline-info"><i class="fas fa-check me-1"></i>Mark as Read Only</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this message?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
