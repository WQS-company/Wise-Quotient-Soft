<?php
$path_to_root = "../";
$page_title = "Freelance Admin Control";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';


// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    // Job Approval
    if ($act === 'approve_job') {
        $jid = (int)$_POST['job_id'];
        try {
            $pdo->prepare("UPDATE freelance_jobs SET status='open', updated_at=NOW() WHERE id=? AND status='pending_review'")->execute([$jid]);
            
            $jStmt = $pdo->prepare("SELECT client_id, title, category, budget_min, budget_max, currency FROM freelance_jobs WHERE id=?");
            $jStmt->execute([$jid]); $job = $jStmt->fetch();
            if ($job) {
                add_notification($job['client_id'], "Job Approved", "Your job post '{$job['title']}' has been approved and is now live.", 'project', '../user/freelance_jobs.php', $jid);

                // Broadcast to all developers
                require_once __DIR__ . '/../includes/fcm_helper.php';
                $cur = $job['currency'] ?? '₦';
                FCMHelper::sendNotificationToAll(
                    "New Freelance Job: " . $job['title'],
                    "A new {$job['category']} job is now open. Budget: $cur" . number_format($job['budget_min'], 0) . "–" . number_format($job['budget_max'], 0),
                    ['click_action' => '/dashboard/wqs/user/freelance_bid.php?job_id=' . $jid]
                );
            }
            
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }

    if ($act === 'reject_job') {
        $jid = (int)$_POST['job_id'];
        try {
            $pdo->prepare("UPDATE freelance_jobs SET status='cancelled', updated_at=NOW() WHERE id=? AND status='pending_review'")->execute([$jid]);
            
            $jStmt = $pdo->prepare("SELECT client_id, title FROM freelance_jobs WHERE id=?");
            $jStmt->execute([$jid]); $job = $jStmt->fetch();
            if ($job) add_notification($job['client_id'], "Job Rejected", "Your job post '{$job['title']}' was rejected. Please review our guidelines.", 'danger', '../user/freelance_jobs.php', $jid);
            
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }

    // Milestone Payment / Escrow Release
    if ($act === 'process_milestone_payment') {
        $mid = (int)$_POST['milestone_id'];
        try {
            $pdo->beginTransaction();
            // Get milestone and contract info
            $ms = $pdo->prepare("SELECT fm.*, fc.developer_id, fc.job_id, fj.title as job_title FROM freelance_milestones fm JOIN freelance_contracts fc ON fm.contract_id=fc.id JOIN freelance_jobs fj ON fc.job_id=fj.id WHERE fm.id=? FOR UPDATE");
            $ms->execute([$mid]); $m = $ms->fetch();
            
            if (!$m || $m['status'] !== 'approved') throw new Exception("Milestone must be approved by client first.");
            
            // Mark as paid
            $pdo->prepare("UPDATE freelance_milestones SET status='paid', updated_at=NOW() WHERE id=?")->execute([$mid]);
            
            // In a real system: transfer NGN from Client Escrow to Developer Wallet here
            // We just send notification
            add_notification($m['developer_id'], "Payment Received", "₦".number_format($m['amount'])." has been credited for milestone '{$m['title']}' on '{$m['job_title']}'.", 'payment', '../user/freelance_jobs.php', $mid);
            
            $pdo->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// Fetch pending jobs
try {
    $pendingJobs = $pdo->query("SELECT fj.*, u.name as client_name, u.email as client_email FROM freelance_jobs fj JOIN users u ON fj.client_id=u.id WHERE fj.status='pending_review' ORDER BY fj.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingJobs = []; }

// Fetch active contracts
try {
    $activeContracts = $pdo->query("
        SELECT fc.*, fj.title as job_title, uc.name as client_name, ud.name as developer_name,
            (SELECT COUNT(*) FROM freelance_milestones WHERE contract_id=fc.id) as total_milestones,
            (SELECT COUNT(*) FROM freelance_milestones WHERE contract_id=fc.id AND status IN ('approved','paid')) as completed_milestones
        FROM freelance_contracts fc
        JOIN freelance_jobs fj ON fc.job_id=fj.id
        JOIN users uc ON fc.client_id=uc.id
        JOIN users ud ON fc.developer_id=ud.id
        WHERE fc.status='active'
        ORDER BY fc.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $activeContracts = []; }

// Fetch pending milestone payments (approved by client but not disbursed)
try {
    $pendingPayments = $pdo->query("
        SELECT fm.*, fc.developer_id, ud.name as developer_name, fj.title as job_title, uc.name as client_name
        FROM freelance_milestones fm
        JOIN freelance_contracts fc ON fm.contract_id=fc.id
        JOIN freelance_jobs fj ON fc.job_id=fj.id
        JOIN users ud ON fc.developer_id=ud.id
        JOIN users uc ON fc.client_id=uc.id
        WHERE fm.status='approved'
        ORDER BY fm.updated_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingPayments = []; }
?>

<style>
.fa-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.fa-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.admin-card { background:white; border-radius:16px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 16px rgba(0,0,0,0.04); margin-bottom:1.25rem; }
.admin-card-header { padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
.admin-card-body { padding:1.5rem; }
.list-item-row { padding:1rem; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:0.75rem; transition:all 0.2s; }
.list-item-row:hover { background:var(--color-bg); border-color:#cbd5e1; }
</style>

<div class="fa-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-shield-alt me-1"></i>Moderation</span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Freelance Admin Control</h1>
        <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">Approve jobs, monitor contracts, and process escrow disbursements.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Pending Jobs -->
    <div class="col-lg-12">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-search me-2 text-primary"></i>Pending Job Approvals (<?= count($pendingJobs) ?>)</h6>
            </div>
            <div class="admin-card-body">
                <?php if (empty($pendingJobs)): ?>
                <div class="text-center py-4 text-muted"><i class="fas fa-check-circle d-block mb-2" style="font-size:2rem;color:#15803d;"></i><p class="mb-0">No jobs pending review.</p></div>
                <?php else: ?>
                <?php foreach ($pendingJobs as $j): ?>
                <div class="list-item-row d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="fw-bold" style="color:#0A2D5E;font-size:1.05rem;"><?= htmlspecialchars($j['title']) ?></div>
                        <div class="text-muted" style="font-size:0.8rem;"><i class="fas fa-user me-1"></i><?= htmlspecialchars($j['client_name']) ?> (<?= htmlspecialchars($j['client_email']) ?>)</div>
                        <div class="mt-2 text-muted" style="font-size:0.85rem;line-height:1.4;"><?= nl2br(htmlspecialchars(substr($j['description'],0,150))) ?>...</div>
                        <div class="mt-2">
                            <span class="badge bg-body-tertiary text-body border"><?= htmlspecialchars($j['category']) ?></span>
                            <span class="badge bg-body-tertiary text-body border ms-1">₦<?= number_format($j['budget_min']) ?> - ₦<?= number_format($j['budget_max']) ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger px-3 py-2 fw-bold" onclick="rejectJob(<?=$j['id']?>)"><i class="fas fa-times me-1"></i>Reject</button>
                        <button class="btn px-4 py-2 fw-bold text-white" style="background:#15803d;border:none;" onclick="approveJob(<?=$j['id']?>)"><i class="fas fa-check me-1"></i>Approve</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Escrow Disbursements -->
    <div class="col-lg-12">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-money-bill-wave me-2 text-success"></i>Pending Escrow Disbursements (<?= count($pendingPayments) ?>)</h6>
            </div>
            <div class="admin-card-body">
                <div class="alert alert-info py-2" style="font-size:0.85rem;"><i class="fas fa-info-circle me-1"></i>These milestones have been approved by the client. Funds should be released from WQS escrow to the developer.</div>
                <?php if (empty($pendingPayments)): ?>
                <div class="text-center py-4 text-muted"><p class="mb-0">No pending disbursements.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Milestone</th><th>Job & Client</th><th>Developer</th><th>Amount</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($pendingPayments as $p): ?>
                            <tr>
                                <td><div class="fw-bold"><?= htmlspecialchars($p['title']) ?></div><div class="text-muted small">Updated: <?= date('M d, H:i', strtotime($p['updated_at'])) ?></div></td>
                                <td><div><?= htmlspecialchars($p['job_title']) ?></div><div class="text-muted small">Client: <?= htmlspecialchars($p['client_name']) ?></div></td>
                                <td><span class="badge bg-primary rounded-pill"><?= htmlspecialchars($p['developer_name']) ?></span></td>
                                <td class="fw-bold text-success">₦<?= number_format($p['amount']) ?></td>
                                <td><button class="btn btn-sm text-white" style="background:#0A2D5E;" onclick="processPayment(<?=$p['id']?>)"><i class="fas fa-wallet me-1"></i>Disburse</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Contracts Overview -->
    <div class="col-lg-12">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-file-contract me-2 text-warning"></i>Active Contracts Monitor</h6>
            </div>
            <div class="admin-card-body">
                <?php if (empty($activeContracts)): ?>
                <div class="text-center py-4 text-muted"><p class="mb-0">No active contracts right now.</p></div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($activeContracts as $c): $prog = $c['total_milestones']>0 ? round(($c['completed_milestones']/$c['total_milestones'])*100):0; ?>
                    <div class="col-md-6">
                        <div class="p-3 border rounded" style="background:var(--color-bg);">
                            <div class="d-flex justify-content-between mb-2">
                                <div class="fw-bold text-body" style="font-size:0.95rem;"><?= htmlspecialchars($c['job_title']) ?></div>
                                <div class="fw-bold" style="color:#0A2D5E;">₦<?= number_format($c['contract_amount']) ?></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted mb-2" style="font-size:0.8rem;">
                                <span><i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($c['client_name']) ?></span>
                                <span><i class="fas fa-code me-1"></i><?= htmlspecialchars($c['developer_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-muted mb-1" style="font-size:0.75rem;">
                                <span>Progress: <?= $c['completed_milestones'] ?>/<?= $c['total_milestones'] ?> Milestones</span>
                                <span><?= $prog ?>%</span>
                            </div>
                            <div class="progress" style="height:6px;"><div class="progress-bar bg-success" style="width:<?=$prog?>%;"></div></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function approveJob(id) {
    Swal.fire({title:'Approve Job?',text:'This job will be visible to all developers.',icon:'question',showCancelButton:true,confirmButtonText:'Approve',confirmButtonColor:'#15803d'})
    .then(r=>{if(!r.isConfirmed)return;
        fetch('freelance_admin.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=approve_job&job_id=${id}`})
        .then(r=>r.json()).then(d=>{if(d.success)location.reload(); else Swal.fire('Error',d.message,'error');});
    });
}
function rejectJob(id) {
    Swal.fire({title:'Reject Job?',text:'This will cancel the job post.',icon:'warning',showCancelButton:true,confirmButtonText:'Reject',confirmButtonColor:'#dc2626'})
    .then(r=>{if(!r.isConfirmed)return;
        fetch('freelance_admin.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=reject_job&job_id=${id}`})
        .then(r=>r.json()).then(d=>{if(d.success)location.reload(); else Swal.fire('Error',d.message,'error');});
    });
}
function processPayment(id) {
    Swal.fire({title:'Disburse Funds?',text:'This confirms escrow funds have been released to the developer wallet.',icon:'warning',showCancelButton:true,confirmButtonText:'Confirm Disbursement',confirmButtonColor:'#0A2D5E'})
    .then(r=>{if(!r.isConfirmed)return;
        fetch('freelance_admin.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=process_milestone_payment&milestone_id=${id}`})
        .then(r=>r.json()).then(d=>{if(d.success) Swal.fire({icon:'success',title:'Disbursed!',timer:2000}).then(()=>location.reload()); else Swal.fire('Error',d.message,'error');});
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
