<?php
$path_to_root = "../";
$page_title = "Payout Approvals";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';



// AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action  = $_POST['ajax_action'];
    $payoutId = (int)$_POST['payout_id'];
    $notes   = trim($_POST['notes'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $newStatus = $action === 'approve' ? 'processed' : 'rejected';
        try {
            $pdo->prepare("UPDATE payout_requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $notes, $payoutId]);

            // Notify user
            $info = $pdo->prepare("SELECT pr.user_id, pr.amount, pr.currency, u.name FROM payout_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.id = ?");
            $info->execute([$payoutId]);
            $row = $info->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $title = $newStatus === 'processed' ? "Payout Approved: {$row['currency']}" . number_format($row['amount'], 2) : "Payout Request Rejected";
                $msg   = $newStatus === 'processed'
                    ? "Your payout of {$row['currency']}" . number_format($row['amount'], 2) . " has been approved and is being processed. Notes: " . ($notes ?: 'N/A')
                    : "Your payout request was rejected. Reason: " . ($notes ?: 'See support for details.');
                add_notification($row['user_id'], $title, $msg, 'payment', '../user/payout_requests.php', $payoutId);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    exit;
}

// Fetch all payout requests with user info
$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['all','pending','processed','rejected'])) $statusFilter = 'pending';
$whereSQL = $statusFilter !== 'all' ? "WHERE pr.status = ?" : "";
$params   = $statusFilter !== 'all' ? [$statusFilter] : [];

try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
        FROM payout_requests pr
        LEFT JOIN users u ON pr.user_id = u.id
        $whereSQL
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute($params);
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payouts = [];
}

// Stats
$statsCounts = ['total' => 0, 'pending' => 0, 'processed' => 0, 'rejected' => 0, 'total_amount' => 0, 'paid_amount' => 0];
try {
    $sc = $pdo->query("SELECT status, COUNT(*) AS cnt, SUM(amount) AS total FROM payout_requests GROUP BY status");
    while ($row = $sc->fetch()) {
        $statsCounts[$row['status']] = (int)$row['cnt'];
        $statsCounts['total_amount'] += (float)$row['total'];
        if ($row['status'] === 'processed') $statsCounts['paid_amount'] = (float)$row['total'];
        $statsCounts['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}
?>

<style>
.payouts-admin-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 20px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.payouts-admin-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.payout-card-admin {
    background: white; border-radius: 18px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
}
.padd-pending   { background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; }
.padd-processed { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.padd-rejected  { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
.payout-badge-admin {
    font-size:0.72rem; font-weight:700; padding:0.2rem 0.65rem;
    border-radius:50px; text-transform:uppercase; letter-spacing:0.04em; display:inline-flex; align-items:center; gap:4px;
}
.filter-pill-pa {
    padding: 0.38rem 0.9rem; border-radius: 50px; border: 1px solid #e2e8f0;
    background: white; color: #64748b; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.filter-pill-pa:hover { border-color: #0A2D5E; color: #0A2D5E; }
.filter-pill-pa.active { background: #0A2D5E; color: white; border-color: #0A2D5E; }
</style>

<!-- Hero -->
<div class="payouts-admin-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;">
                <i class="fas fa-money-check-alt me-1"></i> Finance Control
            </span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Payout Approvals</h1>
        <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">
            <?= $statsCounts['pending'] ?> pending · ₦<?= number_format($statsCounts['paid_amount'], 0) ?> paid out total
        </p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $paCards = [
        ['Total Requests','total','fas fa-list-alt','#eff6ff','#1d4ed8',$statsCounts['total']],
        ['Pending','pending','fas fa-hourglass-half','#fff7ed','#ea580c',$statsCounts['pending']],
        ['Processed','processed','fas fa-check-double','#dcfce7','#15803d',$statsCounts['processed']],
        ['Rejected','rejected','fas fa-times-circle','#fef2f2','#dc2626',$statsCounts['rejected']],
    ];
    foreach ($paCards as [$l,$k,$ic,$bg,$col,$cnt]):
    ?>
    <div class="col-6 col-md-3">
        <div style="background:<?= $bg ?>;border:1.5px solid <?= $col ?>22;border-radius:14px;padding:1.1rem 1.25rem;">
            <div style="width:42px;height:42px;border-radius:12px;background:<?= $col ?>;display:flex;align-items:center;justify-content:center;color:white;margin-bottom:0.75rem;">
                <i class="<?= $ic ?>"></i>
            </div>
            <div style="font-size:1.8rem;font-weight:900;color:<?= $col ?>;line-height:1;"><?= $cnt ?></div>
            <div style="font-size:0.8rem;font-weight:600;color:<?= $col ?>;margin-top:0.2rem;"><?= $l ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="payout-card-admin">
    <div class="p-4 border-bottom d-flex gap-2 flex-wrap">
        <a href="?status=pending"   class="filter-pill-pa <?= $statusFilter==='pending'?'active':'' ?>">⏳ Pending</a>
        <a href="?status=all"       class="filter-pill-pa <?= $statusFilter==='all'?'active':'' ?>">All Requests</a>
        <a href="?status=processed" class="filter-pill-pa <?= $statusFilter==='processed'?'active':'' ?>">✅ Processed</a>
        <a href="?status=rejected"  class="filter-pill-pa <?= $statusFilter==='rejected'?'active':'' ?>">❌ Rejected</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0" style="font-size:0.87rem;">
            <thead class="table-light text-muted fw-bold" style="font-size:0.8rem; border-bottom:2px solid rgba(0,0,0,0.05);">
                <tr>
                    <th class="ps-4 py-3">User</th>
                    <th class="py-3">Role</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Method</th>
                    <th class="py-3">Details</th>
                    <th class="py-3">Date</th>
                    <th class="py-3">Status</th>
                    <th class="pe-4 py-3 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payouts)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-money-check-alt d-block mb-3 text-secondary" style="font-size:2rem;"></i>
                        No payout requests found for this filter.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payouts as $p):
                    $statusKey = strtolower($p['status']);
                    $roleMap = ['user' => 'Client', 'agent' => 'Partner', 'developer' => 'Developer'];
                ?>
                <tr id="payout-row-<?= $p['id'] ?>">
                    <td class="ps-4 py-3">
                        <div class="fw-bold text-body"><?= htmlspecialchars($p['user_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($p['user_email'] ?? '') ?></div>
                    </td>
                    <td class="py-3">
                        <span style="font-size:0.75rem;font-weight:600;background:#f1f5f9;color:#475569;padding:0.2rem 0.6rem;border-radius:50px;">
                            <?= htmlspecialchars($roleMap[$p['user_role']] ?? ucfirst($p['user_role'])) ?>
                        </span>
                    </td>
                    <td class="py-3 fw-bold text-body"><?= htmlspecialchars($p['currency']) . number_format($p['amount'], 2) ?></td>
                    <td class="py-3 text-muted"><?= htmlspecialchars($p['payment_method']) ?></td>
                    <td class="py-3 text-muted" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <span title="<?= htmlspecialchars($p['payment_details']) ?>"><?= htmlspecialchars(substr($p['payment_details'], 0, 50)) ?><?= strlen($p['payment_details']) > 50 ? '...' : '' ?></span>
                    </td>
                    <td class="py-3 text-muted"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                    <td class="py-3">
                        <span class="payout-badge-admin padd-<?= $statusKey ?>">
                            <?php if ($statusKey === 'processed'): ?><i class="fas fa-check-circle"></i>Processed
                            <?php elseif ($statusKey === 'rejected'): ?><i class="fas fa-times-circle"></i>Rejected
                            <?php else: ?><i class="fas fa-clock"></i>Pending
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($p['admin_notes'])): ?>
                            <div class="text-muted mt-1" style="font-size:0.7rem;"><?= htmlspecialchars($p['admin_notes']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <?php if ($statusKey === 'pending'): ?>
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <button class="btn btn-sm btn-success rounded-pill px-3" style="font-size:0.75rem;"
                                onclick="actionPayout(<?= $p['id'] ?>, 'approve')">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:0.75rem;"
                                onclick="actionPayout(<?= $p['id'] ?>, 'reject')">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-top text-muted" style="font-size:0.8rem;">
        Showing <strong><?= count($payouts) ?></strong> payout request<?= count($payouts) != 1 ? 's' : '' ?>.
    </div>
</div>

<script>
function actionPayout(payoutId, action) {
    const isApprove = action === 'approve';
    Swal.fire({
        title: isApprove ? 'Approve Payout?' : 'Reject Payout?',
        html: `<div class="mb-3">${isApprove ? 'Confirm payment has been sent to the user.' : 'Provide reason for rejection:'}</div>
               <textarea id="admin-notes-input" class="swal2-textarea" style="width:90%;font-size:0.88rem;" placeholder="${isApprove ? 'Optional notes...' : 'Reason for rejection...'}"></textarea>`,
        icon: isApprove ? 'success' : 'warning',
        showCancelButton: true,
        confirmButtonText: isApprove ? 'Yes, Approve' : 'Yes, Reject',
        confirmButtonColor: isApprove ? '#15803d' : '#dc2626',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            return document.getElementById('admin-notes-input').value;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        const notes = result.value || '';
        fetch('payout_approvals.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=${action}&payout_id=${payoutId}&notes=${encodeURIComponent(notes)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({icon: 'success', title: 'Done!', text: `Payout has been ${isApprove ? 'approved' : 'rejected'}.`, confirmButtonColor:'#0A2D5E', timer:2500})
                    .then(() => location.reload());
            } else {
                Swal.fire({icon:'error', title:'Failed', text: data.message || 'Action failed.', confirmButtonColor:'#dc3545'});
            }
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
