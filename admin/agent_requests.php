<?php
$path_to_root = "../";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);

if (!$userRoleObj || strtolower($userRoleObj['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ====== AJAX: Process Actions ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    $adminId = $_SESSION['user']['id'];

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    try {
        if ($act === 'approve_partner') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $commission = (float)($_POST['commission_percentage'] ?? 10);
            $partnerLevel = trim($_POST['partner_level'] ?? 'Bronze Partner');
            $partnerNote = trim($_POST['partner_note'] ?? '');

            if ($requestId <= 0 || $targetUserId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid request or user ID.']);
                exit;
            }
            if ($commission < 0 || $commission > 100) {
                echo json_encode(['success' => false, 'message' => 'Commission must be between 0% and 100%.']);
                exit;
            }

            $validLevels = ['Bronze Partner', 'Silver Partner', 'Gold Partner', 'Platinum Partner'];
            if (!in_array($partnerLevel, $validLevels)) {
                echo json_encode(['success' => false, 'message' => 'Invalid partner level.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE agent_requests 
                SET status = 'approved', commission_percentage = ?, partner_level = ?, partner_note = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$commission, $partnerLevel, $partnerNote, $adminId, $requestId]);

            $stmt2 = $pdo->prepare("UPDATE users SET role = 'agent' WHERE id = ?");
            $stmt2->execute([$targetUserId]);

            $pdo->commit();

            add_notification($targetUserId, "Partner Request Approved!", "Congratulations! Your partner agent application has been approved as $partnerLevel with $commission% commission. Visit your referral portal to get started.", 'partner', '../user/referral_portal.php', $requestId);

            log_audit('partner_approval', 'approve', null, [
                'request_id' => $requestId, 'user_id' => $targetUserId,
                'commission' => $commission, 'level' => $partnerLevel, 'note' => $partnerNote
            ]);

            echo json_encode(['success' => true, 'message' => 'Partner approved successfully!', 'level' => $partnerLevel]);
            exit;

        } elseif ($act === 'reject_partner') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $rejectionReason = trim($_POST['rejection_reason'] ?? '');

            if ($requestId <= 0 || $targetUserId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid request or user ID.']);
                exit;
            }
            if (empty($rejectionReason)) {
                echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE agent_requests 
                SET status = 'rejected', rejection_reason = ?, rejected_by = ?, rejected_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$rejectionReason, $adminId, $requestId]);

            $stmt2 = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmt2->execute([$targetUserId]);

            $pdo->commit();

            add_notification($targetUserId, "Partner Request Declined", "Your partner agent application has been declined. Reason: $rejectionReason You may reapply in the future.", 'partner', '../user/upgrade_partner.php', $requestId);

            log_audit('partner_approval', 'reject', null, [
                'request_id' => $requestId, 'user_id' => $targetUserId, 'reason' => $rejectionReason
            ]);

            echo json_encode(['success' => true, 'message' => 'Application rejected.']);
            exit;

        } elseif ($act === 'get_details') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT ar.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.picture AS user_picture,
                       a.name AS approved_by_name, r.name AS rejected_by_name
                FROM agent_requests ar
                INNER JOIN users u ON u.id = ar.user_id
                LEFT JOIN users a ON a.id = ar.approved_by
                LEFT JOIN users r ON r.id = ar.rejected_by
                WHERE ar.id = ?
            ");
            $stmt->execute([$requestId]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($details) {
                // Get referral stats
                $refCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
                $refCountStmt->execute([$details['user_id']]);
                $details['total_referrals'] = (int)$refCountStmt->fetchColumn();

                $projStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM ongoing_projects op
                    INNER JOIN users u ON op.user_id = u.id
                    WHERE u.referred_by = ? AND op.status = 'completed'
                ");
                $projStmt->execute([$details['user_id']]);
                $details['total_projects'] = (int)$projStmt->fetchColumn();

                $earnStmt = $pdo->prepare("
                    SELECT IFNULL(SUM(op.budget * ?), 0)
                    FROM ongoing_projects op
                    INNER JOIN users u ON op.user_id = u.id
                    WHERE u.referred_by = ? AND op.status = 'completed'
                ");
                $rate = ($details['commission_percentage'] ?? 10) / 100;
                $earnStmt->execute([$rate, $details['user_id']]);
                $details['total_earnings'] = (float)$earnStmt->fetchColumn();

                echo json_encode(['success' => true, 'data' => $details]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Request not found.']);
            }
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$page_title = "Partner Upgrade Requests";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// ====== Fetch Requests ======
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : "";

$where = " WHERE 1=1 ";
$params = [];
if (!empty($search)) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR ar.application_id LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($statusFilter)) {
    $where .= " AND ar.status = ? ";
    $params[] = $statusFilter;
}

$sql = "
    SELECT ar.id, ar.user_id, ar.status, ar.commission_percentage, ar.partner_level, ar.partner_note,
           ar.approved_by, ar.approved_at, ar.rejection_reason, ar.rejected_by, ar.rejected_at,
           ar.created_at, ar.updated_at, ar.application_id, ar.partner_role,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.picture AS user_picture
    FROM agent_requests ar
    INNER JOIN users u ON u.id = ar.user_id
    $where
    ORDER BY 
        CASE ar.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 END,
        ar.updated_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function requestStatusBadge($status) {
    $status = strtolower($status);
    $cls = "bg-warning-subtle text-warning border border-warning-subtle";
    if ($status === 'approved') $cls = "bg-success-subtle text-success border border-success-subtle";
    if ($status === 'rejected') $cls = "bg-danger-subtle text-danger border border-danger-subtle";
    return '<span class="badge ' . $cls . ' px-2.5 py-1.5 fw-semibold">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

function partnerLevelBadge($level) {
    $colors = [
        'Bronze Partner' => 'background:#fef3c7;color:#92400e;',
        'Silver Partner' => 'background:#f3f4f6;color:#374151;',
        'Gold Partner' => 'background:#fef9c3;color:#854d0e;',
        'Platinum Partner' => 'background:#ede9fe;color:#6d28d9;',
    ];
    $style = $colors[$level] ?? 'background:#e2e8f0;color:#475569;';
    return '<span style="' . $style . 'padding:3px 10px;border-radius:50px;font-size:0.72rem;font-weight:600;">' . htmlspecialchars($level) . '</span>';
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.agent-req-card { background:white; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
.agent-req-header { padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; }
.agent-req-body { padding:1.5rem; }
.modal-header-custom { background:linear-gradient(135deg,#0A2D5E,#1e40af); color:white; border-radius:12px 12px 0 0; padding:1.25rem 1.5rem; }
.modal-header-custom .btn-close { filter:brightness(0) invert(1); }
.template-chip { display:inline-block; padding:6px 14px; border-radius:50px; font-size:0.78rem; font-weight:600; cursor:pointer; border:2px solid #e2e8f0; transition:all 0.2s; background:white; }
.template-chip:hover { border-color:#3b82f6; color:#3b82f6; }
.template-chip.active { background:#3b82f6; color:white; border-color:#3b82f6; }
.detail-label { font-size:0.78rem; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
.detail-value { font-size:0.95rem; color:#111827; font-weight:500; }
</style>

<div class="d-flex align-items-center mb-4 gap-3 flex-wrap">
    <div>
        <h3 style="margin:0;font-weight:800;color:#0A2D5E;">Partner Applications</h3>
        <p class="text-muted mb-0" style="font-size:0.88rem;">Review, approve, or reject partner agent applications</p>
    </div>
    <div class="ms-auto d-flex gap-2">
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fw-semibold">
            <i class="fas fa-clock me-1"></i> <?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')) ?> Pending
        </span>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold">
            <i class="fas fa-check me-1"></i> <?= count(array_filter($requests, fn($r) => $r['status'] === 'approved')) ?> Approved
        </span>
    </div>
</div>

<!-- FILTER BAR -->
<div class="agent-req-card mb-4">
    <div class="agent-req-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0"
                           placeholder="Search by name, email, phone, or Application ID..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($statusFilter == "pending") ? "selected" : "" ?>>Pending</option>
                    <option value="approved" <?= ($statusFilter == "approved") ? "selected" : "" ?>>Approved</option>
                    <option value="rejected" <?= ($statusFilter == "rejected") ? "selected" : "" ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-primary rounded-pill fw-semibold"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- REQUESTS TABLE -->
<div class="agent-req-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:0.92rem;">
            <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <tr>
                    <th class="ps-4 py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Applicant</th>
                    <th class="py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Application ID</th>
                    <th class="py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Applied</th>
                    <th class="py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Level</th>
                    <th class="py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Commission</th>
                    <th class="py-3 fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>
                    <th class="pe-4 py-3 text-end fw-semibold text-muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $row):
                        $pic = !empty($row['user_picture']) ? $row['user_picture'] : "https://cdn-icons-png.flaticon.com/512/149/149071.png";
                    ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= htmlspecialchars($pic) ?>" width="42" height="42" class="rounded-circle object-cover border" style="object-fit:cover;" alt="">
                                    <div>
                                        <div class="fw-bold text-body"><?= htmlspecialchars($row['user_name']) ?></div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($row['user_email']) ?>
                                            <?php if (!empty($row['user_phone']) && $row['user_phone'] !== 'N/A'): ?>
                                                <span class="ms-1">· <?= htmlspecialchars($row['user_phone']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3"><code style="font-size:0.78rem;color:#6d28d9;font-weight:600;"><?= htmlspecialchars($row['application_id'] ?? 'N/A') ?></code></td>
                            <td class="py-3 text-muted small"><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                            <td class="py-3">
                                <?php if ($row['partner_level']): ?>
                                    <?= partnerLevelBadge($row['partner_level']) ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if ($row['commission_percentage'] > 0): ?>
                                    <span class="fw-bold" style="color:#0A2D5E;"><?= $row['commission_percentage'] ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3"><?= requestStatusBadge($row['status']) ?></td>
                            <td class="pe-4 py-3 text-end">
                                <div class="d-inline-flex gap-1">
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success rounded-pill fw-semibold" onclick='openApproveModal(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill fw-semibold" onclick='openRejectModal(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill fw-semibold" onclick='viewDetails(<?= $row["id"] ?>)'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($row['status'] === 'approved'): ?>
                                        <a href="../user/user-agreement-form.php?agent_id=<?= $row['user_id'] ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill fw-semibold">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-clipboard-list fa-2x mb-3" style="opacity:0.3;"></i>
                            <h6 class="fw-semibold">No applications found</h6>
                            <p class="mb-0 small">Partner applications will appear here.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ====== APPROVAL MODAL ====== -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header-custom">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <div>
                        <h5 class="modal-title fw-bold mb-0"><i class="fas fa-user-check me-2"></i> Partner Approval</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="approve_request_id">
                <input type="hidden" id="approve_target_user_id">

                <!-- Applicant Info -->
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background:#f8fafc;">
                    <img id="approve_applicant_pic" src="" width="48" height="48" class="rounded-circle border" style="object-fit:cover;">
                    <div>
                        <div class="fw-bold" id="approve_applicant_name"></div>
                        <div class="text-muted small" id="approve_applicant_email"></div>
                    </div>
                </div>

                <!-- Quick Templates -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted small mb-2">Quick Templates</label>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="template-chip" data-level="Bronze Partner" data-pct="10" onclick="applyTemplate(this)">
                            <i class="fas fa-medal me-1" style="color:#d97706;"></i> Standard (10%)
                        </span>
                        <span class="template-chip" data-level="Silver Partner" data-pct="15" onclick="applyTemplate(this)">
                            <i class="fas fa-medal me-1" style="color:#6b7280;"></i> Premium (15%)
                        </span>
                        <span class="template-chip" data-level="Gold Partner" data-pct="20" onclick="applyTemplate(this)">
                            <i class="fas fa-medal me-1" style="color:#ca8a04;"></i> Gold (20%)
                        </span>
                        <span class="template-chip" data-level="Platinum Partner" data-pct="30" onclick="applyTemplate(this)">
                            <i class="fas fa-gem me-1" style="color:#7c3aed;"></i> Enterprise (30%)
                        </span>
                    </div>
                </div>

                <!-- Commission Percentage -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Commission Percentage <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="approve_commission" min="1" max="100" value="10" required style="font-size:1.05rem;font-weight:600;">
                        <span class="input-group-text fw-bold">%</span>
                    </div>
                </div>

                <!-- Partner Level -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Partner Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="approve_level">
                        <option value="Bronze Partner">Bronze Partner</option>
                        <option value="Silver Partner">Silver Partner</option>
                        <option value="Gold Partner">Gold Partner</option>
                        <option value="Platinum Partner">Platinum Partner</option>
                    </select>
                </div>

                <!-- Approval Note -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Approval Note</label>
                    <textarea class="form-control" id="approve_note" rows="3" placeholder="e.g. Approved for software and AI projects, eligible for enterprise clients"></textarea>
                </div>

                <!-- Status Badge -->
                <div class="d-flex align-items-center gap-2 p-3 rounded-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <i class="fas fa-check-circle text-success"></i>
                    <span class="fw-semibold" style="color:#166534;">Status: Approved</span>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success rounded-pill px-4 fw-semibold" onclick="submitApproval()">
                    <i class="fas fa-check me-1"></i> Approve Partner
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====== REJECTION MODAL ====== -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#991b1b,#dc2626);color:white;border-radius:12px 12px 0 0;padding:1.25rem 1.5rem;">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h5 class="modal-title fw-bold mb-0"><i class="fas fa-user-slash me-2"></i> Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:brightness(0) invert(1);"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="reject_request_id">
                <input type="hidden" id="reject_target_user_id">

                <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background:#fef2f2;">
                    <img id="reject_applicant_pic" src="" width="48" height="48" class="rounded-circle border" style="object-fit:cover;">
                    <div>
                        <div class="fw-bold" id="reject_applicant_name"></div>
                        <div class="text-muted small" id="reject_applicant_email"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reject_reason" rows="4" required placeholder="Please provide a reason for rejecting this application..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-semibold" onclick="submitRejection()">
                    <i class="fas fa-times me-1"></i> Reject Application
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====== DETAILS MODAL ====== -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header-custom">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h5 class="modal-title fw-bold mb-0"><i class="fas fa-user-circle me-2"></i> Partner Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:brightness(0) invert(1);"></button>
                </div>
            </div>
            <div class="modal-body p-4" id="detailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';

function openApproveModal(row) {
    document.getElementById('approve_request_id').value = row.id;
    document.getElementById('approve_target_user_id').value = row.user_id;
    document.getElementById('approve_applicant_pic').src = row.user_picture || 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    document.getElementById('approve_applicant_name').textContent = row.user_name;
    document.getElementById('approve_applicant_email').textContent = row.user_email;
    document.getElementById('approve_commission').value = row.commission_percentage || 10;
    document.getElementById('approve_level').value = row.partner_level || 'Bronze Partner';
    document.getElementById('approve_note').value = row.partner_note || '';
    document.querySelectorAll('.template-chip').forEach(c => c.classList.remove('active'));
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function openRejectModal(row) {
    document.getElementById('reject_request_id').value = row.id;
    document.getElementById('reject_target_user_id').value = row.user_id;
    document.getElementById('reject_applicant_pic').src = row.user_picture || 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    document.getElementById('reject_applicant_name').textContent = row.user_name;
    document.getElementById('reject_applicant_email').textContent = row.user_email;
    document.getElementById('reject_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function applyTemplate(el) {
    document.querySelectorAll('.template-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('approve_commission').value = el.dataset.pct;
    document.getElementById('approve_level').value = el.dataset.level;
    const notes = {
        'Bronze Partner': 'Standard partner — 10% commission on referred projects.',
        'Silver Partner': 'Premium partner — 15% commission, eligible for priority support.',
        'Gold Partner': 'Gold partner — 20% commission, eligible for enterprise projects.',
        'Platinum Partner': 'Enterprise partner — 30% commission, full strategic partnership.'
    };
    document.getElementById('approve_note').value = notes[el.dataset.level] || '';
}

function submitApproval() {
    const btn = event.target.closest('button');
    const pct = parseFloat(document.getElementById('approve_commission').value);
    if (pct < 0 || pct > 100) {
        Swal.fire('Invalid', 'Commission must be between 0% and 100%.', 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Approving...';

    const fd = new FormData();
    fd.append('ajax_action', 'approve_partner');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('request_id', document.getElementById('approve_request_id').value);
    fd.append('target_user_id', document.getElementById('approve_target_user_id').value);
    fd.append('commission_percentage', pct);
    fd.append('partner_level', document.getElementById('approve_level').value);
    fd.append('partner_note', document.getElementById('approve_note').value);

    fetch('agent_requests.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Approve Partner';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Partner Approved',
                    html: `<p class="text-muted">The applicant has been successfully upgraded to <strong>${data.level || 'Partner Agent'}</strong>.</p>`,
                    showDenyButton: true,
                    confirmButtonText: '<i class="fas fa-external-link-alt me-1"></i> View Partner',
                    denyButtonText: 'Close',
                    confirmButtonColor: '#0A2D5E',
                    denyButtonColor: '#6b7280',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(result => {
                    if (result.isConfirmed) {
                        window.location.href = 'payroll-partners.php';
                    } else {
                        location.reload();
                    }
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Approve Partner';
            Swal.fire('Error', 'Network error. Please try again.', 'error');
        });
}

function submitRejection() {
    const reason = document.getElementById('reject_reason').value.trim();
    if (!reason) {
        Swal.fire('Required', 'Please provide a rejection reason.', 'warning');
        return;
    }

    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

    const fd = new FormData();
    fd.append('ajax_action', 'reject_partner');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('request_id', document.getElementById('reject_request_id').value);
    fd.append('target_user_id', document.getElementById('reject_target_user_id').value);
    fd.append('rejection_reason', reason);

    fetch('agent_requests.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times me-1"></i> Reject Application';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Application Rejected',
                    text: data.message,
                    confirmButtonColor: '#dc2626',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times me-1"></i> Reject Application';
            Swal.fire('Error', 'Network error. Please try again.', 'error');
        });
}

function viewDetails(requestId) {
    const content = document.getElementById('detailsContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    new bootstrap.Modal(document.getElementById('detailsModal')).show();

    const fd = new FormData();
    fd.append('ajax_action', 'get_details');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('request_id', requestId);

    fetch('agent_requests.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { content.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>'; return; }
            const d = data.data;
            const pic = d.user_picture || 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
            const statusColors = { pending: 'warning', approved: 'success', rejected: 'danger' };
            const levelColors = {
                'Bronze Partner': { bg: '#fef3c7', fg: '#92400e' },
                'Silver Partner': { bg: '#f3f4f6', fg: '#374151' },
                'Gold Partner': { bg: '#fef9c3', fg: '#854d0e' },
                'Platinum Partner': { bg: '#ede9fe', fg: '#6d28d9' }
            };
            const lc = levelColors[d.partner_level] || { bg: '#e2e8f0', fg: '#475569' };

            content.innerHTML = `
                <div class="text-center mb-4">
                    <img src="${pic}" width="72" height="72" class="rounded-circle border mb-3" style="object-fit:cover;">
                    <h5 class="fw-bold mb-1">${escHtml(d.user_name)}</h5>
                    <div class="text-muted small">${escHtml(d.user_email)}</div>
                    <div class="mt-2">${statusBadge(d.status)} ${d.partner_level ? levelBadgeHtml(d.partner_level, lc) : ''}</div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6"><div class="detail-label">Phone</div><div class="detail-value">${escHtml(d.user_phone || 'N/A')}</div></div>
                    <div class="col-sm-6"><div class="detail-label">Commission</div><div class="detail-value" style="color:#0A2D5E;font-size:1.2rem;font-weight:700;">${d.commission_percentage || 0}%</div></div>
                    <div class="col-sm-6"><div class="detail-label">Total Referrals</div><div class="detail-value">${d.total_referrals}</div></div>
                    <div class="col-sm-6"><div class="detail-label">Completed Projects</div><div class="detail-value">${d.total_projects}</div></div>
                    <div class="col-sm-6"><div class="detail-label">Total Earnings (Commission)</div><div class="detail-value" style="color:#16a34a;font-weight:700;">₦${Number(d.total_earnings).toLocaleString('en-NG', {minimumFractionDigits:2})}</div></div>
                    <div class="col-sm-6"><div class="detail-label">Applied</div><div class="detail-value">${formatDate(d.created_at)}</div></div>
                    ${d.application_id ? `<div class="col-sm-6"><div class="detail-label">Application ID</div><div class="detail-value" style="font-family:monospace;font-weight:600;color:#6d28d9;">${escHtml(d.application_id)}</div></div>` : ''}
                    ${d.partner_role ? `<div class="col-12"><div class="detail-label">Role Description</div><div class="detail-value">${escHtml(d.partner_role)}</div></div>` : ''}
                    ${d.approved_at ? `<div class="col-sm-6"><div class="detail-label">Approved</div><div class="detail-value">${formatDate(d.approved_at)}<br><span class="text-muted small">by ${escHtml(d.approved_by_name || 'Admin')}</span></div></div>` : ''}
                    ${d.rejected_at ? `<div class="col-sm-6"><div class="detail-label">Rejected</div><div class="detail-value">${formatDate(d.rejected_at)}<br><span class="text-muted small">by ${escHtml(d.rejected_by_name || 'Admin')}</span></div></div>` : ''}
                </div>
                ${d.partner_note ? `<div class="p-3 rounded-3 mb-3" style="background:#f8fafc;"><div class="detail-label">Approval Note</div><div class="detail-value">${escHtml(d.partner_note)}</div></div>` : ''}
                ${d.rejection_reason ? `<div class="p-3 rounded-3" style="background:#fef2f2;"><div class="detail-label">Rejection Reason</div><div class="detail-value" style="color:#991b1b;">${escHtml(d.rejection_reason)}</div></div>` : ''}
                ${d.status === 'approved' ? `<div class="text-center mt-3"><a href="../user/user-agreement-form.php?agent_id=${d.user_id}" target="_blank" class="btn btn-outline-success rounded-pill fw-semibold"><i class="fas fa-print me-1"></i> Print Agreement</a></div>` : ''}
            `;
        })
        .catch(() => { content.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function formatDate(dt) { if (!dt) return '—'; const d = new Date(dt); return d.toLocaleDateString('en-NG', {day:'2-digit',month:'short',year:'numeric'}) + ' ' + d.toLocaleTimeString('en-NG', {hour:'2-digit',minute:'2-digit'}); }
function statusBadge(s) { const c = {pending:'warning',approved:'success',rejected:'danger'}; return `<span class="badge bg-${c[s]||'secondary'}-subtle text-${c[s]||'secondary'} border border-${c[s]||'secondary'}-subtle fw-semibold px-2 py-1">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`; }
function levelBadgeHtml(l, c) { return `<span style="background:${c.bg};color:${c.fg};padding:3px 10px;border-radius:50px;font-size:0.72rem;font-weight:600;margin-left:6px;">${escHtml(l)}</span>`; }
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
