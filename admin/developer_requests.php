<?php
$path_to_root = "../";
$page_title = "Developer Applications";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';



// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reqId     = (int)$_POST['request_id'];
    $action    = $_POST['action'];
    $adminNote = trim($_POST['admin_note'] ?? '');

    if (in_array($action, ['approved', 'rejected'])) {
        $pdo->prepare("UPDATE developer_requests SET status=?, admin_note=?, updated_at=NOW() WHERE id=?")
            ->execute([$action, $adminNote, $reqId]);

        // Get user_id from request for notification
        $uStmt = $pdo->prepare("SELECT user_id FROM developer_requests WHERE id=?");
        $uStmt->execute([$reqId]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $uid = (int)$uRow['user_id'];
            
            if ($action === 'approved') {
                $pdo->prepare("UPDATE users SET role='developer' WHERE id=?")->execute([$uid]);
                // Optionally insert their skills into developer_skills
                $skStmt = $pdo->prepare("SELECT skills FROM developer_requests WHERE id=?");
                $skStmt->execute([$reqId]);
                $skRow = $skStmt->fetch(PDO::FETCH_ASSOC);
                if ($skRow && !empty($skRow['skills'])) {
                    $skillArr = json_decode($skRow['skills'], true);
                    if (is_array($skillArr)) {
                        $insSk = $pdo->prepare("INSERT IGNORE INTO developer_skills (developer_id, skill_name) VALUES (?, ?)");
                        foreach ($skillArr as $sk) {
                            $insSk->execute([$uid, $sk]);
                        }
                    }
                }
                
                // Trigger approval notification
                add_notification($uid, "Developer Application Approved!", "Congratulations! Your developer application has been approved. You are now upgraded to WQS Developer status. Visit your Developer Hub to view task boards.", 'project', '../user/developer_hub.php', $uid);
            } else {
                $msg = "Your developer application was not approved.";
                if (!empty($adminNote)) {
                    $msg .= " Admin feedback: " . $adminNote;
                } else {
                    $msg .= " You can reapply or update your technical skills.";
                }
                
                // Trigger decline notification
                add_notification($uid, "Developer Application Declined", $msg, 'project', '../user/developer_hub.php', $uid);
            }
        }
        $_SESSION['success_message'] = "Developer application " . ucfirst($action) . " successfully.";
    }
    header("Location: developer_requests.php");
    exit;
}

// Fetch all applications
$applications = [];
$appRes = $pdo->query("
    SELECT dr.*, u.name, u.email, u.picture
    FROM developer_requests dr
    LEFT JOIN users u ON dr.user_id = u.id
    ORDER BY FIELD(dr.status,'pending','approved','rejected'), dr.created_at DESC
");
if ($appRes) { $applications = $appRes->fetchAll(PDO::FETCH_ASSOC); }

$counts = ['total'=>count($applications),'pending'=>0,'approved'=>0,'rejected'=>0];
foreach ($applications as $a) if (isset($counts[$a['status']])) $counts[$a['status']]++;
?>

<style>
.admin-dev-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.admin-dev-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%); border-radius:50%; }
.app-row { background:#fff; border:1px solid var(--color-border); border-radius:12px; padding:1.25rem; margin-bottom:0.75rem; transition:all 0.2s; }
.app-row:hover { box-shadow:0 4px 16px rgba(0,0,0,0.07); }
.app-avatar { width:46px; height:46px; border-radius:50%; object-fit:cover; border:2px solid var(--color-border); }
.app-avatar-placeholder { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:1rem; flex-shrink:0; }
.status-pill { padding:0.25rem 0.75rem; border-radius:50px; font-size:0.72rem; font-weight:700; text-transform:uppercase; }
.sp-pending  { background:#fef3c7;color:#92400e; }
.sp-approved { background:#dcfce7;color:#15803d; }
.sp-rejected { background:#fee2e2;color:#991b1b; }
.skill-mini { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; padding:0.18rem 0.55rem; border-radius:50px; font-size:0.72rem; font-weight:600; display:inline-block; margin:0.1rem; }
.filter-tab { padding:0.45rem 1rem; border-radius:50px; border:1.5px solid var(--color-border); background:white; font-size:0.82rem; font-weight:600; cursor:pointer; color:var(--color-text-light); transition:all 0.2s; }
.filter-tab.active { background:#0f172a; color:white; border-color:#0f172a; }
</style>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3 shadow-sm mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- Hero -->
<div class="admin-dev-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(99,102,241,0.25);color:#a5b4fc;border:1px solid rgba(165,180,252,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;text-transform:uppercase;">
                    <i class="fas fa-code me-1"></i> Developer Applications
                </span>
                <?php if ($counts['pending'] > 0): ?>
                <span style="background:#ef4444;color:white;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.72rem;font-weight:700;"><?= $counts['pending'] ?> pending</span>
                <?php endif; ?>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;color:white;margin-bottom:0.3rem;">Review Developer Requests</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
                <?= $counts['total'] ?> total · <?= $counts['approved'] ?> approved · <?= $counts['rejected'] ?> rejected
            </p>
        </div>
        <a href="manage_developers.php" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.85rem;">
            <i class="fas fa-users-cog me-1"></i> Manage Developers
        </a>
    </div>
</div>

<!-- Filters -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <button class="filter-tab active" onclick="filterApps('all',this)">All (<?= $counts['total'] ?>)</button>
    <button class="filter-tab" onclick="filterApps('pending',this)">Pending (<?= $counts['pending'] ?>)</button>
    <button class="filter-tab" onclick="filterApps('approved',this)">Approved (<?= $counts['approved'] ?>)</button>
    <button class="filter-tab" onclick="filterApps('rejected',this)">Rejected (<?= $counts['rejected'] ?>)</button>
</div>

<!-- Applications List -->
<div id="app-list">
<?php if (empty($applications)): ?>
<div class="card-theme p-5 text-center text-muted">
    <div style="font-size:3rem;margin-bottom:0.75rem;">💻</div>
    <h5 class="fw-bold text-body">No Applications Yet</h5>
    <p class="small mb-0">Developer applications will appear here when users apply.</p>
</div>
<?php else: ?>
<?php foreach ($applications as $app):
    $skillArr = json_decode($app['skills'] ?? '[]', true);
    if (!is_array($skillArr)) $skillArr = [];
?>
<div class="app-row" data-status="<?= $app['status'] ?>">
    <div class="d-flex gap-3 align-items-start flex-wrap">
        <!-- Avatar -->
        <div>
            <?php if (!empty($app['picture'])): ?>
            <img src="<?= htmlspecialchars($app['picture']) ?>" class="app-avatar" alt="">
            <?php else: ?>
            <img src="<?= $path_to_root ?>images/default-avatar.png" class="app-avatar" alt="">
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div style="flex:1;min-width:0;">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="fw-bold text-body"><?= htmlspecialchars($app['name'] ?? 'Unknown') ?></span>
                <span class="status-pill sp-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
            </div>
            <div class="text-muted small mb-2">
                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($app['email'] ?? '') ?> ·
                <?= $app['years_experience'] ?>yr<?= $app['years_experience']!=1?'s':''?> exp ·
                Expected ₦<?= number_format($app['hourly_rate_expected'], 0) ?>/hr ·
                Applied <?= date('M d, Y', strtotime($app['created_at'])) ?>
            </div>

            <!-- Skills -->
            <?php if (!empty($skillArr)): ?>
            <div class="mb-2">
                <?php foreach (array_slice($skillArr, 0, 10) as $sk): ?>
                <span class="skill-mini"><?= htmlspecialchars($sk) ?></span>
                <?php endforeach; ?>
                <?php if (count($skillArr) > 10): ?>
                <span class="skill-mini" style="background:#f1f5f9;color:#64748b;">+<?= count($skillArr)-10 ?> more</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Experience -->
            <?php if (!empty($app['experience'])): ?>
            <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                <?= htmlspecialchars($app['experience']) ?>
            </p>
            <?php endif; ?>

            <!-- Links + Admin note -->
            <div class="d-flex gap-3 flex-wrap">
                <?php if (!empty($app['portfolio_url'])): ?>
                <a href="<?= htmlspecialchars($app['portfolio_url']) ?>" target="_blank" class="text-primary small"><i class="fas fa-external-link-alt me-1"></i>Portfolio</a>
                <?php endif; ?>
                <?php if (!empty($app['github_url'])): ?>
                <a href="<?= htmlspecialchars($app['github_url']) ?>" target="_blank" class="text-muted small"><i class="fab fa-github me-1"></i>GitHub</a>
                <?php endif; ?>
                <?php if (!empty($app['admin_note'])): ?>
                <span class="text-muted small"><i class="fas fa-comment me-1"></i><?= htmlspecialchars($app['admin_note']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($app['status'] === 'pending'): ?>
        <div class="d-flex gap-2 ms-auto">
            <button class="btn btn-sm" style="background:#dcfce7;color:#15803d;border-radius:8px;border:1px solid #86efac;font-weight:600;" onclick="showActionModal(<?= $app['id'] ?>,'approved')">
                <i class="fas fa-user-check me-1"></i> Approve
            </button>
            <button class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border-radius:8px;border:1px solid #fca5a5;font-weight:600;" onclick="showActionModal(<?= $app['id'] ?>,'rejected')">
                <i class="fas fa-times me-1"></i> Reject
            </button>
        </div>
        <?php elseif ($app['status'] === 'approved'): ?>
        <a href="manage_developers.php" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:0.78rem;">
            <i class="fas fa-tasks me-1"></i> Assign Task
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px;">
            <form method="POST">
                <input type="hidden" name="request_id" id="modal-req-id">
                <input type="hidden" name="action" id="modal-action">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold text-body" id="modal-action-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-muted small" id="modal-action-desc"></p>
                    <label class="fw-semibold text-body" style="font-size:0.82rem;">Note to Applicant (optional)</label>
                    <textarea name="admin_note" class="form-control mt-1" rows="3" placeholder="Reason for decision, feedback, or next steps..."></textarea>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" id="modal-confirm-btn" style="border-radius:8px;font-weight:600;">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showActionModal(reqId, action) {
    document.getElementById('modal-req-id').value = reqId;
    document.getElementById('modal-action').value = action;
    const isApprove = action === 'approved';
    document.getElementById('modal-action-title').textContent = isApprove ? '✅ Approve Developer' : '❌ Reject Application';
    document.getElementById('modal-action-desc').textContent = isApprove
        ? 'This will upgrade the user\'s role to Developer and grant them access to the Developer Hub.'
        : 'This will decline the application. The user may re-apply with updated information.';
    const btn = document.getElementById('modal-confirm-btn');
    btn.textContent = isApprove ? 'Approve & Hire' : 'Reject';
    btn.style.background = isApprove ? 'linear-gradient(135deg,#10b981,#059669)' : '#ef4444';
    btn.style.color = 'white'; btn.style.border = 'none';
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
function filterApps(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.app-row').forEach(row => {
        row.style.display = (status==='all' || row.dataset.status===status) ? '' : 'none';
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
