<?php
$path_to_root = "../";
$page_title = "My Bids & Contracts";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

if (!in_array($user_role, ['developer','admin'])) { header("Location: ../login.php"); exit; }
$userId = $headerUser['id'];
$isAdmin = ($user_role === 'admin');

// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if ($act === 'withdraw_bid') {
        $bidId = (int)$_POST['bid_id'];
        try {
            $pdo->prepare("UPDATE freelance_bids SET status='withdrawn' WHERE id=? AND developer_id=?")->execute([$bidId,$userId]);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    if ($act === 'add_milestone' && $isAdmin) {
        $cid   = (int)$_POST['contract_id'];
        $title = trim($_POST['title']??'');
        $desc  = trim($_POST['description']??'');
        $amt   = (float)$_POST['amount'];
        $due   = trim($_POST['due_date']??'');
        if (!$cid||!$title) { echo json_encode(['success'=>false,'message'=>'Required fields missing.']); exit; }
        try {
            $pdo->prepare("INSERT INTO freelance_milestones (contract_id,title,description,amount,due_date) VALUES (?,?,?,?,?)")->execute([$cid,$title,$desc,$amt,$due?:null]);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    if ($act === 'submit_milestone') {
        $mid = (int)$_POST['milestone_id'];
        $url = trim($_POST['deliverable_url']??'');
        try {
            $pdo->prepare("UPDATE freelance_milestones SET status='submitted', deliverable_url=?, updated_at=NOW() WHERE id=?")->execute([$url,$mid]);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    if ($act === 'approve_milestone' && $isAdmin) {
        $mid = (int)$_POST['milestone_id'];
        try {
            $pdo->prepare("UPDATE freelance_milestones SET status='approved', updated_at=NOW() WHERE id=?")->execute([$mid]);
            $ms = $pdo->prepare("SELECT fm.*, fc.developer_id, fj.title as job_title FROM freelance_milestones fm JOIN freelance_contracts fc ON fm.contract_id=fc.id JOIN freelance_jobs fj ON fc.job_id=fj.id WHERE fm.id=?");
            $ms->execute([$mid]); $m=$ms->fetch();
            if ($m) add_notification($m['developer_id'], "Milestone Approved: {$m['title']}", "Your milestone on '{$m['job_title']}' was approved. Payment will be processed.", 'payment', '../user/freelance_jobs.php', $mid);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// Fetch bids
try {
    $bids = $pdo->prepare("
        SELECT fb.*, fj.title AS job_title, fj.category, fj.budget_min, fj.budget_max, fj.currency, fj.status AS job_status, fj.deadline
        FROM freelance_bids fb JOIN freelance_jobs fj ON fb.job_id=fj.id
        WHERE fb.developer_id=?
        ORDER BY fb.created_at DESC
    ");
    $bids->execute([$userId]);
    $bids = $bids->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $bids=[]; }

// Fetch contracts
try {
    $contracts = $pdo->prepare("
        SELECT fc.*, fj.title AS job_title, fj.category, u.name AS client_name, u.email AS client_email,
            (SELECT COUNT(*) FROM freelance_milestones fm WHERE fm.contract_id=fc.id) AS milestone_count,
            (SELECT COUNT(*) FROM freelance_milestones fm WHERE fm.contract_id=fc.id AND fm.status='approved') AS milestones_done
        FROM freelance_contracts fc
        JOIN freelance_jobs fj ON fc.job_id=fj.id
        JOIN users u ON fc.client_id=u.id
        WHERE fc.developer_id=?
        ORDER BY fc.created_at DESC
    ");
    $contracts->execute([$userId]);
    $contracts = $contracts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $contracts=[]; }

$bidStats = ['total'=>count($bids),'pending'=>0,'accepted'=>0,'rejected'=>0];
foreach ($bids as $b) if (isset($bidStats[$b['status']])) $bidStats[$b['status']]++;
?>

<style>
.bids-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.bids-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.bid-card { background:white; border-radius:16px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 16px rgba(0,0,0,0.04); transition:all 0.2s; padding:1.25rem; margin-bottom:1rem; }
.bid-card:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.08); }
.bid-s-pending  { border-left:4px solid #f59e0b; }
.bid-s-accepted { border-left:4px solid #22c55e; }
.bid-s-rejected { border-left:4px solid #ef4444; }
.bid-s-withdrawn { border-left:4px solid #94a3b8; }
.bid-pill { font-size:0.7rem; font-weight:700; padding:0.2rem 0.65rem; border-radius:50px; text-transform:uppercase; }
.bp-pending  { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.bp-accepted { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.bp-rejected { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
.bp-withdrawn{ background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
.contract-card { background:white; border-radius:18px; border:1.5px solid rgba(10,45,94,0.12); box-shadow:0 4px 20px rgba(0,0,0,0.04); overflow:hidden; margin-bottom:1.25rem; }
.milestone-item { padding:0.75rem 1rem; border-radius:10px; border:1px solid #e2e8f0; background:var(--color-bg); margin-bottom:0.5rem; }
.section-tab { padding:0.6rem 1.5rem; border-radius:50px; border:1.5px solid #e2e8f0; background:white; color:#64748b; font-size:0.85rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
.section-tab.active { background:#0A2D5E; color:white; border-color:#0A2D5E; }
</style>

<!-- Hero -->
<div class="bids-hero">
    <div style="position:relative;z-index:1;" class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-gavel me-1"></i>Developer Activity</span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">My Bids & Contracts</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;"><?= $bidStats['total'] ?> total bids · <?= count($contracts) ?> active contract<?= count($contracts)!=1?'s':'' ?></p>
        </div>
        <a href="freelance_jobs.php" class="btn px-4 py-2 fw-bold" style="background:#E15501;border:none;color:white;border-radius:10px;"><i class="fas fa-search me-1"></i>Browse Jobs</a>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php $sc2=[['Total Bids','total','#eff6ff','#1d4ed8','fas fa-gavel'],['Pending','pending','#fef3c7','#d97706','fas fa-clock'],['Accepted','accepted','#dcfce7','#15803d','fas fa-check-circle'],['Rejected','rejected','#fef2f2','#dc2626','fas fa-times-circle']];
    foreach ($sc2 as [$l,$k,$bg,$c,$i]): ?>
    <div class="col-6 col-md-3">
        <div style="background:<?=$bg?>;border:1.5px solid <?=$c?>22;border-radius:14px;padding:1rem 1.25rem;">
            <div style="width:38px;height:38px;border-radius:10px;background:<?=$c?>;display:flex;align-items:center;justify-content:center;color:white;margin-bottom:0.65rem;"><i class="<?=$i?>"></i></div>
            <div style="font-size:1.7rem;font-weight:900;color:<?=$c?>;line-height:1;"><?=$bidStats[$k]?></div>
            <div style="font-size:0.78rem;font-weight:600;color:<?=$c?>;margin-top:0.2rem;"><?=$l?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="d-flex gap-2 mb-4">
    <button class="section-tab active" onclick="switchSection('bids-section',this)"><i class="fas fa-gavel me-1"></i>My Bids (<?=count($bids)?>)</button>
    <button class="section-tab" onclick="switchSection('contracts-section',this)"><i class="fas fa-file-contract me-1"></i>Contracts (<?=count($contracts)?>)</button>
</div>

<!-- Bids Section -->
<div id="bids-section">
    <?php if (empty($bids)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-gavel d-block mb-3 text-secondary" style="font-size:3rem;"></i>
        <h5>No bids yet</h5>
        <p>Browse the job board and submit your first proposal.</p>
        <a href="freelance_jobs.php" class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;">Browse Jobs</a>
    </div>
    <?php else: ?>
    <?php foreach ($bids as $b): ?>
    <div class="bid-card bid-s-<?= $b['status'] ?>">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="bid-pill bp-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                    <span style="font-size:0.75rem;color:#94a3b8;"><?= date('M d, Y', strtotime($b['created_at'])) ?></span>
                </div>
                <h6 class="fw-bold text-body mb-1"><?= htmlspecialchars($b['job_title']) ?></h6>
                <div class="text-muted" style="font-size:0.82rem;"><?= htmlspecialchars($b['category']) ?></div>
                <div class="mt-2 text-muted" style="font-size:0.82rem;line-height:1.5;"><?= nl2br(htmlspecialchars(substr($b['cover_letter'],0,200))) ?><?= strlen($b['cover_letter'])>200?'…':'' ?></div>
            </div>
            <div class="text-end">
                <div style="font-size:1.2rem;font-weight:900;color:#0A2D5E;">₦<?= number_format($b['bid_amount'],0) ?></div>
                <div style="font-size:0.75rem;color:#94a3b8;"><?= $b['delivery_days'] ?> day<?= $b['delivery_days']!=1?'s':'' ?> delivery</div>
                <?php if ($b['status']==='pending'): ?>
                <button class="btn btn-sm btn-outline-danger rounded-pill mt-2 px-3" style="font-size:0.75rem;" onclick="withdrawBid(<?=$b['id']?>)"><i class="fas fa-undo me-1"></i>Withdraw</button>
                <?php endif; ?>
                <?php if ($b['status']==='accepted'): ?>
                <span style="font-size:0.75rem;font-weight:700;color:#15803d;"><i class="fas fa-trophy me-1"></i>Contract Active</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Contracts Section -->
<div id="contracts-section" style="display:none;">
    <?php if (empty($contracts)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-file-contract d-block mb-3 text-secondary" style="font-size:3rem;"></i>
        <h5>No contracts yet</h5>
        <p>Win a bid to get your first contract.</p>
    </div>
    <?php else: ?>
    <?php foreach ($contracts as $c):
        $progress = $c['milestone_count'] > 0 ? round(($c['milestones_done']/$c['milestone_count'])*100) : 0;
    ?>
    <div class="contract-card">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h6 class="fw-bold text-body mb-1"><?= htmlspecialchars($c['job_title']) ?></h6>
                <div class="text-muted" style="font-size:0.82rem;"><i class="fas fa-user me-1"></i>Client: <?= htmlspecialchars($c['client_name']) ?></div>
            </div>
            <div class="text-end">
                <div style="font-size:1.3rem;font-weight:900;color:#0A2D5E;">₦<?= number_format($c['contract_amount'],0) ?></div>
                <div style="font-size:0.72rem;color:#94a3b8;"><?= $c['platform_fee_pct'] ?>% platform fee · Net: ₦<?= number_format($c['contract_amount']*(1-$c['platform_fee_pct']/100),0) ?></div>
                <span style="font-size:0.72rem;font-weight:700;background:<?= $c['status']==='active'?'#dcfce7':($c['status']==='completed'?'#e0e7ff':'#fef2f2') ?>;color:<?= $c['status']==='active'?'#15803d':($c['status']==='completed'?'#3730a3':'#dc2626') ?>;padding:0.2rem 0.65rem;border-radius:50px;"><?= ucfirst($c['status']) ?></span>
            </div>
        </div>
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small fw-semibold">Progress</span>
                <span class="fw-bold small text-body"><?= $c['milestones_done'] ?>/<?= $c['milestone_count'] ?> milestones · <?= $progress ?>%</span>
            </div>
            <div class="progress mb-3" style="height:8px;border-radius:50px;">
                <div class="progress-bar" style="width:<?= $progress ?>%;background:linear-gradient(90deg,#0A2D5E,#2563eb);border-radius:50px;"></div>
            </div>
            <?php if ($c['milestone_count'] > 0):
                $ms = $pdo->prepare("SELECT * FROM freelance_milestones WHERE contract_id=? ORDER BY id ASC");
                $ms->execute([$c['id']]); $milestones = $ms->fetchAll(PDO::FETCH_ASSOC);
                foreach ($milestones as $m):
                    $msColors = ['pending'=>['#f8fafc','#64748b'],'in_progress'=>['#eff6ff','#1d4ed8'],'submitted'=>['#fef3c7','#92400e'],'approved'=>['#dcfce7','#15803d'],'rejected'=>['#fef2f2','#dc2626']];
                    [$msBg,$msCl] = $msColors[$m['status']] ?? ['#f8fafc','#64748b'];
            ?>
            <div class="milestone-item d-flex justify-content-between align-items-center" style="background:<?=$msBg?>;border-color:<?=$msCl?>44;">
                <div>
                    <div class="fw-semibold" style="font-size:0.87rem;color:#0A2D5E;"><?= htmlspecialchars($m['title']) ?></div>
                    <?php if ($m['amount']>0): ?><div style="font-size:0.72rem;color:#64748b;">₦<?= number_format($m['amount'],0) ?></div><?php endif; ?>
                    <?php if ($m['deliverable_url']): ?><a href="<?= htmlspecialchars($m['deliverable_url']) ?>" target="_blank" style="font-size:0.72rem;color:#0A2D5E;"><i class="fas fa-external-link-alt me-1"></i>View Deliverable</a><?php endif; ?>
                </div>
                <div class="text-end">
                    <span style="font-size:0.68rem;font-weight:700;color:<?=$msCl?>;background:white;padding:0.15rem 0.5rem;border-radius:50px;border:1px solid <?=$msCl?>55;"><?= ucfirst($m['status']) ?></span>
                    <?php if ($m['status']==='in_progress'): ?>
                    <button class="btn btn-sm btn-primary rounded-pill d-block mt-1 px-2" style="font-size:0.7rem;background:#0A2D5E;border:none;" onclick="submitMilestone(<?=$m['id']?>)"><i class="fas fa-upload me-1"></i>Submit</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
            <?php if ($c['due_date']): ?><div class="text-muted small mt-2"><i class="fas fa-calendar me-1"></i>Due: <?= date('M d, Y', strtotime($c['due_date'])) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function switchSection(id, btn) {
    ['bids-section','contracts-section'].forEach(s=>{ const el=document.getElementById(s); if(el) el.style.display='none'; });
    document.querySelectorAll('.section-tab').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).style.display='block';
    btn.classList.add('active');
}

function withdrawBid(bidId) {
    Swal.fire({title:'Withdraw Bid?',text:'This will mark your bid as withdrawn.',icon:'warning',showCancelButton:true,confirmButtonText:'Withdraw',confirmButtonColor:'#dc2626'})
    .then(r=>{if(!r.isConfirmed)return;
        fetch('freelance_bid.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=withdraw_bid&bid_id=${bidId}`})
        .then(r=>r.json()).then(d=>{if(d.success)Swal.fire({icon:'success',title:'Withdrawn',confirmButtonColor:'#0A2D5E',timer:2000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});});
    });
}

function submitMilestone(mid) {
    Swal.fire({title:'Submit Milestone',html:`<input type="url" id="ms_url" class="swal2-input" placeholder="Deliverable URL (optional, e.g. https://github.com/...)">`,showCancelButton:true,confirmButtonText:'Submit for Review',confirmButtonColor:'#0A2D5E'})
    .then(r=>{if(!r.isConfirmed)return; const url=document.getElementById('ms_url')?.value||'';
        fetch('freelance_bid.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=submit_milestone&milestone_id=${mid}&deliverable_url=${encodeURIComponent(url)}`})
        .then(r=>r.json()).then(d=>{if(d.success)Swal.fire({icon:'success',title:'Submitted!',text:'Admin will review and approve your milestone.',confirmButtonColor:'#0A2D5E',timer:3000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});});
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
