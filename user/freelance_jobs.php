<?php
$path_to_root = "../";
$page_title = "Freelance Job Board";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$userId   = $headerUser['id'];
$isAdmin  = ($user_role === 'admin');
$isDev    = ($user_role === 'developer');
$isClient = in_array($user_role, ['user','agent']);

// ── AJAX Handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    // Post a new job (clients + admin)
    if ($act === 'post_job' && ($isClient || $isAdmin)) {
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $cat      = trim($_POST['category'] ?? 'General');
        $skills   = trim($_POST['skills'] ?? '');
        $btype    = in_array($_POST['budget_type'] ?? '', ['fixed','hourly']) ? $_POST['budget_type'] : 'fixed';
        $bmin     = (float)($_POST['budget_min'] ?? 0);
        $bmax     = (float)($_POST['budget_max'] ?? 0);
        $cur      = in_array($_POST['currency'] ?? '', ['₦','$','€']) ? $_POST['currency'] : '₦';
        $deadline = trim($_POST['deadline'] ?? '');
        $exp      = in_array($_POST['experience'] ?? '', ['entry','intermediate','expert']) ? $_POST['experience'] : 'intermediate';
        $remote   = isset($_POST['is_remote']) ? 1 : 0;

        if (!$title || !$desc) { echo json_encode(['success'=>false,'message'=>'Title and description required.']); exit; }
        $status = $isAdmin ? 'open' : 'pending_review';
        try {
            $pdo->prepare("INSERT INTO freelance_jobs (posted_by,title,description,category,skills_required,budget_type,budget_min,budget_max,currency,deadline,experience_level,status,is_remote) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$userId,$title,$desc,$cat,$skills,$btype,$bmin,$bmax,$cur,$deadline ?: null,$exp,$status,$remote]);
            $jid = $pdo->lastInsertId();
            add_notification($userId, "Job Posted: $title", $isAdmin ? "Your job listing is live." : "Your job listing is under review by admin.", 'project', '../user/freelance_jobs.php', $jid);

            // Broadcast to all developers when job goes live immediately
            if ($isAdmin) {
                require_once __DIR__ . '/../includes/fcm_helper.php';
                FCMHelper::sendNotificationToAll(
                    "New Freelance Job: " . $title,
                    "A new $cat job has been posted. Budget: $cur" . number_format($bmin, 0) . "–" . number_format($bmax, 0) . ". Apply now!",
                    ['click_action' => '/dashboard/wqs/user/freelance_bid.php?job_id=' . $jid]
                );
            }

            echo json_encode(['success'=>true,'job_id'=>$jid]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    // Submit a bid (developers only)
    if ($act === 'submit_bid' && $isDev) {
        $jid    = (int)$_POST['job_id'];
        $amount = (float)$_POST['bid_amount'];
        $days   = (int)$_POST['delivery_days'];
        $cover  = trim($_POST['cover_letter'] ?? '');
        if (!$jid || !$amount || !$cover) { echo json_encode(['success'=>false,'message'=>'All fields required.']); exit; }
        try {
            // Check if already bid
            $chk = $pdo->prepare("SELECT id FROM freelance_bids WHERE job_id=? AND developer_id=?");
            $chk->execute([$jid, $userId]);
            if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'You have already bid on this job.']); exit; }

            $pdo->prepare("INSERT INTO freelance_bids (job_id,developer_id,bid_amount,delivery_days,cover_letter) VALUES (?,?,?,?,?)")
                ->execute([$jid,$userId,$amount,$days,$cover]);
            // Notify job poster
            $job = $pdo->prepare("SELECT posted_by,title FROM freelance_jobs WHERE id=?"); $job->execute([$jid]); $j = $job->fetch();
            if ($j) add_notification($j['posted_by'], "New Bid on: {$j['title']}", "A developer has submitted a bid of ₦" . number_format($amount,2), 'message', '../user/freelance_jobs.php', $jid);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    // Accept a bid (client or admin)
    if ($act === 'accept_bid' && ($isClient || $isAdmin)) {
        $bidId = (int)$_POST['bid_id'];
        try {
            $bid = $pdo->prepare("SELECT fb.*, fj.posted_by, fj.title FROM freelance_bids fb JOIN freelance_jobs fj ON fb.job_id=fj.id WHERE fb.id=?");
            $bid->execute([$bidId]); $b = $bid->fetch();
            if (!$b) { echo json_encode(['success'=>false,'message'=>'Bid not found.']); exit; }

            $pdo->beginTransaction();
            // Reject all other bids on the same job
            $pdo->prepare("UPDATE freelance_bids SET status='rejected' WHERE job_id=? AND id!=?")->execute([$b['job_id'],$bidId]);
            // Accept this bid
            $pdo->prepare("UPDATE freelance_bids SET status='accepted' WHERE id=?")->execute([$bidId]);
            // Set job to in_progress
            $pdo->prepare("UPDATE freelance_jobs SET status='in_progress' WHERE id=?")->execute([$b['job_id']]);
            // Create contract
            $pdo->prepare("INSERT INTO freelance_contracts (job_id,bid_id,client_id,developer_id,contract_amount,currency,start_date,due_date) VALUES (?,?,?,?,?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? DAY))")
                ->execute([$b['job_id'],$bidId,$b['posted_by'],$b['developer_id'],$b['bid_amount'],$b['currency'],$b['delivery_days'] ?? 7]);
            $pdo->commit();

            add_notification($b['developer_id'], "🎉 Bid Accepted: {$b['title']}", "Congratulations! Your bid of ₦" . number_format($b['bid_amount'],2) . " was accepted. A contract has been created.", 'success', '../user/freelance_jobs.php', $b['job_id']);
            add_notification($b['posted_by'], "Contract Created: {$b['title']}", "You accepted a developer bid. Contract is now active.", 'project', '../user/freelance_jobs.php', $b['job_id']);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    // Admin: approve job listing
    if ($act === 'approve_job' && $isAdmin) {
        $jid = (int)$_POST['job_id'];
        try {
            $pdo->prepare("UPDATE freelance_jobs SET status='open' WHERE id=?")->execute([$jid]);
            $job = $pdo->prepare("SELECT posted_by,title FROM freelance_jobs WHERE id=?"); $job->execute([$jid]); $j=$job->fetch();
            if ($j) add_notification($j['posted_by'], "Job Listing Approved: {$j['title']}", "Your job is now live on the marketplace.", 'success', '../user/freelance_jobs.php', $jid);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    // Admin: reject job listing
    if ($act === 'reject_job' && $isAdmin) {
        $jid = (int)$_POST['job_id'];
        $reason = trim($_POST['reason'] ?? 'Does not meet platform standards.');
        try {
            $pdo->prepare("UPDATE freelance_jobs SET status='cancelled' WHERE id=?")->execute([$jid]);
            $job = $pdo->prepare("SELECT posted_by,title FROM freelance_jobs WHERE id=?"); $job->execute([$jid]); $j=$job->fetch();
            if ($j) add_notification($j['posted_by'], "Job Listing Rejected: {$j['title']}", "Reason: $reason", 'danger', '../user/freelance_jobs.php', $jid);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

// ── Fetch job data ─────────────────────────────────────────────────────────────
$catFilter  = $_GET['cat']    ?? 'all';
$expFilter  = $_GET['exp']    ?? 'all';
$typeFilter = $_GET['type']   ?? 'all';
$searchQ    = trim($_GET['q'] ?? '');

$allowCats = ['all','Web Development','Mobile App','AI & Automation','Cloud & DevOps','UI/UX Design','Fintech','E-Commerce','Data Science','Cybersecurity','General'];
$allowExps = ['all','entry','intermediate','expert'];
$allowTypes= ['all','fixed','hourly'];
if (!in_array($catFilter,$allowCats)) $catFilter='all';
if (!in_array($expFilter,$allowExps)) $expFilter='all';
if (!in_array($typeFilter,$allowTypes)) $typeFilter='all';

$wheres = [];
$params = [];

// Admins see all, clients see open+their own, developers see open jobs
if ($isAdmin) {
    // no filter
} elseif ($isDev) {
    $wheres[] = "fj.status = 'open'";
} else {
    $wheres[] = "(fj.status = 'open' OR fj.posted_by = ?)";
    $params[] = $userId;
}
if ($catFilter !== 'all')  { $wheres[] = "fj.category = ?"; $params[] = $catFilter; }
if ($expFilter !== 'all')  { $wheres[] = "fj.experience_level = ?"; $params[] = $expFilter; }
if ($typeFilter !== 'all') { $wheres[] = "fj.budget_type = ?"; $params[] = $typeFilter; }
if ($searchQ) { $wheres[] = "(fj.title LIKE ? OR fj.description LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }

$whereSql = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

try {
    $stmt = $pdo->prepare("
        SELECT fj.*, u.name AS poster_name, u.role AS poster_role,
            (SELECT COUNT(*) FROM freelance_bids fb WHERE fb.job_id=fj.id) AS bid_count,
            (SELECT COUNT(*) FROM freelance_bids fb WHERE fb.job_id=fj.id AND fb.developer_id=?) AS my_bid
        FROM freelance_jobs fj
        LEFT JOIN users u ON fj.posted_by=u.id
        $whereSql
        ORDER BY fj.created_at DESC
    ");
    $stmt->execute(array_merge([$userId], $params));
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $jobs = []; }

// Stats
$stats = ['total'=>0,'open'=>0,'in_progress'=>0,'pending_review'=>0];
try {
    if ($isAdmin) {
        $sr = $pdo->query("SELECT status, COUNT(*) as c FROM freelance_jobs GROUP BY status");
        while ($r=$sr->fetch()) { $stats[$r['status']] = $r['c']; $stats['total']+=$r['c']; }
    } else {
        $stats['total'] = count($jobs);
        foreach ($jobs as $j) { if (isset($stats[$j['status']])) $stats[$j['status']]++; }
    }
} catch (Exception $e) {}

// Fetch projects for client (for job posting)
$myProjects = [];
try {
    $pr = $pdo->prepare("SELECT id, title FROM ongoing_projects WHERE user_id=? AND status='ongoing'");
    $pr->execute([$userId]); $myProjects = $pr->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Dev: fetch my existing bids
$myBids = [];
if ($isDev) {
    try {
        $br = $pdo->prepare("SELECT job_id FROM freelance_bids WHERE developer_id=?");
        $br->execute([$userId]);
        $myBids = array_column($br->fetchAll(PDO::FETCH_ASSOC), 'job_id');
    } catch (Exception $e) {}
}

$categories = ['Web Development','Mobile App','AI & Automation','Cloud & DevOps','UI/UX Design','Fintech','E-Commerce','Data Science','Cybersecurity','General'];
?>

<style>
/* ===== FREELANCE JOB BOARD PREMIUM STYLES ===== */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

.fl-hero {
    background: linear-gradient(135deg, #0f2857 0%, #1a3f80 50%, #0f4c8f 100%);
    border-radius: 22px; padding: 2rem 2.5rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 2rem;
}
.fl-hero::before { content:''; position:absolute; top:-80px; right:-80px; width:300px; height:300px; background:rgba(225,85,1,0.12); border-radius:50%; }
.fl-hero::after  { content:''; position:absolute; bottom:-60px; left:-40px; width:200px; height:200px; background:rgba(255,255,255,0.04); border-radius:50%; }
.fl-hero-badge { background:rgba(225,85,1,0.25); color:#ffb380; border:1px solid rgba(225,85,1,0.4); padding:0.25rem 0.85rem; border-radius:50px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; }

.fl-stat-card {
    border-radius: 14px; padding: 1.1rem 1.4rem;
    border: 1.5px solid transparent; transition: all 0.25s;
    cursor: pointer; text-decoration: none; display: block;
}
.fl-stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }

.job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem; }
@media(max-width:768px) { .job-grid { grid-template-columns: 1fr; } }

.job-card {
    background: white; border-radius: 18px;
    border: 1.5px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    transition: all 0.25s; overflow: hidden; cursor: pointer;
}
.job-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.1); border-color: rgba(10,45,94,0.15); }
.job-card-header { padding: 1.25rem 1.4rem 0.8rem; }
.job-card-body { padding: 0.8rem 1.4rem 1.25rem; }
.job-card-footer { padding: 0.85rem 1.4rem; background:var(--color-bg); border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }

.category-chip {
    font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.65rem;
    border-radius: 50px; background: rgba(10,45,94,0.08); color: #0A2D5E;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.exp-chip { font-size: 0.68rem; font-weight: 600; padding: 0.15rem 0.6rem; border-radius: 50px; }
.exp-entry { background:#f0fdf4; color:#15803d; border:1px solid #86efac; }
.exp-intermediate { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.exp-expert { background:#faf5ff; color:#6d28d9; border:1px solid #c4b5fd; }

.job-status-pill {
    font-size: 0.68rem; font-weight: 700; padding: 0.2rem 0.65rem;
    border-radius: 50px; text-transform: uppercase; letter-spacing: 0.04em;
    display: inline-flex; align-items: center; gap: 4px;
}
.jst-open         { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.jst-in_progress  { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe; }
.jst-pending_review { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.jst-completed    { background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; }
.jst-cancelled    { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }

.budget-display { font-size: 1.1rem; font-weight: 800; color: #0A2D5E; }
.bid-count-tag { font-size: 0.75rem; color: #64748b; font-weight: 600; }

.skill-tag {
    font-size: 0.7rem; background: #f1f5f9; color: #475569;
    padding: 0.2rem 0.6rem; border-radius: 50px; display: inline-block;
    margin: 0.15rem; border: 1px solid #e2e8f0;
}
.fl-filter-bar { background: white; border-radius: 14px; border: 1px solid rgba(0,0,0,0.06); padding: 1.25rem 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
.fl-filter-pill { padding: 0.35rem 0.9rem; border-radius: 50px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
.fl-filter-pill:hover { border-color: #0A2D5E; color: #0A2D5E; }
.fl-filter-pill.active { background: #0A2D5E; color: white; border-color: #0A2D5E; }
.fl-empty-state { text-align: center; padding: 4rem 2rem; }

.bid-submit-section { background: linear-gradient(135deg, #f0f7ff, #e8f4ff); border: 1.5px solid #bfdbfe; border-radius: 14px; padding: 1.25rem; }

.star-rating { display: flex; gap: 4px; }
.star-rating i { color: #f59e0b; font-size: 0.9rem; }
.star-rating i.empty { color: #e2e8f0; }
</style>

<!-- Hero -->
<div class="fl-hero" style="position:relative;">
    <div style="position:relative;z-index:1;" class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="fl-hero-badge"><i class="fas fa-briefcase me-1"></i> Freelance Marketplace</span>
                <?php if ($isDev): ?><span style="background:rgba(52,211,153,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.7rem;font-weight:600;">● <?= count($jobs) ?> Open Jobs</span><?php endif; ?>
            </div>
            <h1 style="font-size:1.7rem;font-weight:900;color:white;margin-bottom:0.35rem;letter-spacing:-0.02em;">
                <?php if ($isDev): ?>Find Your Next Gig
                <?php elseif ($isClient||$isAdmin): ?>Hire Top Developers
                <?php endif; ?>
            </h1>
            <p style="color:rgba(255,255,255,0.65);font-size:0.88rem;margin:0;">
                <?php if ($isDev): ?>Browse verified tech projects, place smart bids, earn on your terms.
                <?php else: ?>Post a job, review developer bids, and hire the best talent — fast.
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <?php if (!$isDev): ?>
            <button class="btn px-4 py-2 fw-bold" style="background:#E15501;border:none;color:white;border-radius:11px;" data-bs-toggle="modal" data-bs-target="#postJobModal">
                <i class="fas fa-plus me-1"></i> Post a Job
            </button>
            <?php endif; ?>
            <?php if ($isDev): ?>
            <a href="freelance_bid.php" class="btn px-4 py-2 fw-bold" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:white;border-radius:11px;">
                <i class="fas fa-gavel me-1"></i> My Bids
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Jobs','total','fas fa-briefcase','#eff6ff','#1d4ed8'],
        ['Open','open','fas fa-door-open','#dcfce7','#15803d'],
        ['In Progress','in_progress','fas fa-spinner','#dbeafe','#1e40af'],
        ['Under Review','pending_review','fas fa-hourglass-half','#fef3c7','#d97706'],
    ];
    foreach ($statCards as [$label,$key,$icon,$bg,$col]):
    ?>
    <div class="col-6 col-md-3">
        <div class="fl-stat-card" style="background:<?= $bg ?>;border-color:<?= $col ?>22;">
            <div style="width:40px;height:40px;border-radius:10px;background:<?= $col ?>;display:flex;align-items:center;justify-content:center;color:white;margin-bottom:0.65rem;font-size:0.95rem;"><i class="<?= $icon ?>"></i></div>
            <div style="font-size:1.9rem;font-weight:900;color:<?= $col ?>;line-height:1;"><?= $stats[$key] ?? 0 ?></div>
            <div style="font-size:0.78rem;font-weight:600;color:<?= $col ?>;margin-top:0.2rem;"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="fl-filter-bar">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small fw-semibold me-1"><i class="fas fa-sliders-h me-1"></i>Filter:</span>
            <a href="?cat=all&exp=<?= $expFilter ?>&type=<?= $typeFilter ?>&q=<?= urlencode($searchQ) ?>" class="fl-filter-pill <?= $catFilter==='all'?'active':'' ?>">All</a>
            <?php foreach ($categories as $c): ?>
            <a href="?cat=<?= urlencode($c) ?>&exp=<?= $expFilter ?>&type=<?= $typeFilter ?>&q=<?= urlencode($searchQ) ?>" class="fl-filter-pill <?= $catFilter===$c?'active':'' ?>"><?= $c ?></a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($catFilter) ?>">
            <input type="hidden" name="exp" value="<?= htmlspecialchars($expFilter) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search jobs..." value="<?= htmlspecialchars($searchQ) ?>" style="min-width:200px;border-radius:50px;border-color:#e2e8f0;">
            <button class="btn btn-sm btn-primary rounded-pill px-3" style="background:#0A2D5E;border:none;"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="d-flex gap-2 mt-2 flex-wrap">
        <span class="text-muted small fw-semibold">Experience:</span>
        <a href="?cat=<?= $catFilter ?>&exp=all&type=<?= $typeFilter ?>" class="fl-filter-pill <?= $expFilter==='all'?'active':'' ?>">Any Level</a>
        <a href="?cat=<?= $catFilter ?>&exp=entry&type=<?= $typeFilter ?>" class="fl-filter-pill <?= $expFilter==='entry'?'active':'' ?>">Entry</a>
        <a href="?cat=<?= $catFilter ?>&exp=intermediate&type=<?= $typeFilter ?>" class="fl-filter-pill <?= $expFilter==='intermediate'?'active':'' ?>">Intermediate</a>
        <a href="?cat=<?= $catFilter ?>&exp=expert&type=<?= $typeFilter ?>" class="fl-filter-pill <?= $expFilter==='expert'?'active':'' ?>">Expert</a>
        <span class="text-muted small fw-semibold ms-3">Type:</span>
        <a href="?cat=<?= $catFilter ?>&exp=<?= $expFilter ?>&type=all" class="fl-filter-pill <?= $typeFilter==='all'?'active':'' ?>">Any</a>
        <a href="?cat=<?= $catFilter ?>&exp=<?= $expFilter ?>&type=fixed" class="fl-filter-pill <?= $typeFilter==='fixed'?'active':'' ?>">Fixed Price</a>
        <a href="?cat=<?= $catFilter ?>&exp=<?= $expFilter ?>&type=hourly" class="fl-filter-pill <?= $typeFilter==='hourly'?'active':'' ?>">Hourly</a>
    </div>
</div>

<!-- Job Grid -->
<?php if (empty($jobs)): ?>
<div class="fl-empty-state">
    <div style="font-size:4rem;margin-bottom:1rem;">💼</div>
    <h4 class="fw-bold text-body">No jobs found</h4>
    <p class="text-muted"><?= $isDev ? 'No open jobs match your filters. Check back soon!' : 'No jobs posted yet. Be the first to post a job!' ?></p>
    <?php if (!$isDev): ?>
    <button class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;" data-bs-toggle="modal" data-bs-target="#postJobModal"><i class="fas fa-plus me-2"></i>Post a Job</button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="job-grid">
    <?php foreach ($jobs as $job):
        $statusKey = str_replace(['_review','in_'], ['_review','in_'], $job['status']);
        $skillTags = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
        $alreadyBid = $isDev && in_array($job['id'], $myBids);
        $budgetStr = $job['budget_min'] > 0 ? $job['currency'] . number_format($job['budget_min'],0) . ($job['budget_max'] > $job['budget_min'] ? ' – ' . $job['currency'] . number_format($job['budget_max'],0) : '') : 'Negotiable';
    ?>
    <div class="job-card" onclick="openJobModal(<?= htmlspecialchars(json_encode($job), ENT_QUOTES) ?>)">
        <div class="job-card-header">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <span class="category-chip"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($job['category']) ?></span>
                <span class="job-status-pill jst-<?= $job['status'] ?>">
                    <?php if ($job['status']==='open'): ?><i class="fas fa-circle" style="font-size:0.4rem;"></i>Open
                    <?php elseif ($job['status']==='in_progress'): ?><i class="fas fa-spinner fa-spin" style="font-size:0.7rem;"></i>In Progress
                    <?php elseif ($job['status']==='pending_review'): ?><i class="fas fa-clock" style="font-size:0.7rem;"></i>Under Review
                    <?php elseif ($job['status']==='completed'): ?><i class="fas fa-check-circle" style="font-size:0.7rem;"></i>Completed
                    <?php else: ?><i class="fas fa-times-circle" style="font-size:0.7rem;"></i>Cancelled
                    <?php endif; ?>
                </span>
            </div>
            <h6 class="fw-bold text-body mb-1" style="font-size:0.97rem;line-height:1.4;"><?= htmlspecialchars($job['title']) ?></h6>
            <div class="d-flex align-items-center gap-2">
                <span class="exp-chip exp-<?= $job['experience_level'] ?>"><?= ucfirst($job['experience_level']) ?></span>
                <?php if ($job['is_remote']): ?><span style="font-size:0.68rem;color:#6d28d9;font-weight:600;"><i class="fas fa-globe me-1"></i>Remote</span><?php endif; ?>
                <?php if ($job['budget_type']==='hourly'): ?><span style="font-size:0.68rem;color:#0d9488;font-weight:600;"><i class="fas fa-clock me-1"></i>Hourly</span><?php endif; ?>
            </div>
        </div>
        <div class="job-card-body">
            <p class="text-muted mb-2" style="font-size:0.83rem;line-height:1.5;"><?= nl2br(htmlspecialchars(substr($job['description'],0,140))) ?><?= strlen($job['description'])>140?'…':'' ?></p>
            <?php if ($skillTags): ?>
            <div class="mb-1">
                <?php foreach (array_slice($skillTags,0,5) as $s): ?><span class="skill-tag"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
                <?php if (count($skillTags)>5): ?><span class="skill-tag">+<?= count($skillTags)-5 ?> more</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="job-card-footer">
            <div>
                <div class="budget-display"><?= $budgetStr ?></div>
                <div style="font-size:0.7rem;color:#94a3b8;"><?= ucfirst($job['budget_type']) ?> price</div>
            </div>
            <div class="text-end">
                <div class="bid-count-tag"><i class="fas fa-gavel me-1"></i><?= $job['bid_count'] ?> bid<?= $job['bid_count']!=1?'s':'' ?></div>
                <?php if ($job['deadline']): ?><div style="font-size:0.7rem;color:#94a3b8;"><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($job['deadline'])) ?></div><?php endif; ?>
            </div>
            <?php if ($alreadyBid): ?>
            <span style="background:#dcfce7;color:#15803d;border:1px solid #86efac;padding:0.25rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;"><i class="fas fa-check me-1"></i>Bid Submitted</span>
            <?php elseif ($isDev && $job['status']==='open'): ?>
            <button class="btn btn-sm btn-primary rounded-pill px-3" style="background:#0A2D5E;border:none;font-size:0.78rem;" onclick="event.stopPropagation();openBidModal(<?= $job['id'] ?>,<?= json_encode($job['title']) ?>)"><i class="fas fa-gavel me-1"></i>Place Bid</button>
            <?php elseif ($isAdmin && $job['status']==='pending_review'): ?>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-success rounded-pill px-2" style="font-size:0.75rem;" onclick="event.stopPropagation();approveJob(<?= $job['id'] ?>)"><i class="fas fa-check"></i></button>
                <button class="btn btn-sm btn-outline-danger rounded-pill px-2" style="font-size:0.75rem;" onclick="event.stopPropagation();rejectJob(<?= $job['id'] ?>)"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <div class="text-muted" style="font-size:0.7rem;">by <?= htmlspecialchars($job['poster_name'] ?? 'WQS') ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- POST JOB MODAL -->
<div class="modal fade" id="postJobModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0A2D5E,#163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-briefcase me-2"></i>Post a Freelance Job</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">Job Title *</label>
                        <input type="text" id="pj_title" class="form-control" placeholder="e.g. Build a React E-Commerce Dashboard">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">Category *</label>
                        <select id="pj_cat" class="form-select">
                            <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">Experience Level</label>
                        <select id="pj_exp" class="form-select">
                            <option value="entry">Entry Level</option>
                            <option value="intermediate" selected>Intermediate</option>
                            <option value="expert">Expert Only</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">Job Description *</label>
                        <textarea id="pj_desc" class="form-control" rows="4" placeholder="Describe the project scope, deliverables, technical requirements..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">Required Skills (comma-separated)</label>
                        <input type="text" id="pj_skills" class="form-control" placeholder="e.g. React, Node.js, MySQL, REST API">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted">Budget Type</label>
                        <select id="pj_btype" class="form-select">
                            <option value="fixed">Fixed Price</option>
                            <option value="hourly">Hourly Rate</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small text-muted">Currency</label>
                        <select id="pj_cur" class="form-select">
                            <option value="₦">₦ NGN</option>
                            <option value="$">$ USD</option>
                            <option value="€">€ EUR</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small text-muted">Min Budget</label>
                        <input type="number" id="pj_bmin" class="form-control" placeholder="e.g. 50000" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small text-muted">Max Budget</label>
                        <input type="number" id="pj_bmax" class="form-control" placeholder="e.g. 150000" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">Project Deadline</label>
                        <input type="date" id="pj_deadline" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="pj_remote" checked>
                            <label class="form-check-label fw-semibold" for="pj_remote">Remote Work Allowed</label>
                        </div>
                    </div>
                    <?php if (!empty($myProjects)): ?>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">Link to Existing Project (optional)</label>
                        <select id="pj_project" class="form-select">
                            <option value="">— No specific project —</option>
                            <?php foreach ($myProjects as $mp): ?><option value="<?= $mp['id'] ?>"><?= htmlspecialchars($mp['title']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn rounded-pill px-5 fw-bold" style="background:#E15501;border:none;color:white;" onclick="submitPostJob()"><i class="fas fa-paper-plane me-1"></i>Post Job</button>
            </div>
        </div>
    </div>
</div>

<!-- PLACE BID MODAL -->
<div class="modal fade" id="bidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0A2D5E,#163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-gavel me-2"></i>Place Your Bid</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 p-3 bg-body-tertiary rounded-3">
                    <div class="text-muted small">Bidding on:</div>
                    <div class="fw-bold text-body" id="bidJobTitle">—</div>
                </div>
                <input type="hidden" id="bidJobId">
                <div class="row g-3">
                    <div class="col-8">
                        <label class="form-label fw-semibold small text-muted">Your Bid Amount (₦) *</label>
                        <input type="number" id="bid_amount" class="form-control" placeholder="e.g. 75000" min="1000" step="500">
                    </div>
                    <div class="col-4">
                        <label class="form-label fw-semibold small text-muted">Delivery (Days) *</label>
                        <input type="number" id="bid_days" class="form-control" placeholder="7" min="1" max="365" value="7">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">Cover Letter / Proposal *</label>
                        <textarea id="bid_cover" class="form-control" rows="5" placeholder="Introduce yourself, explain your approach, highlight relevant experience..."></textarea>
                        <div class="text-muted mt-1" style="font-size:0.75rem;"><i class="fas fa-lightbulb me-1 text-warning"></i>Strong proposals mention your specific experience with similar projects.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn rounded-pill px-5 fw-bold" style="background:#0A2D5E;border:none;color:white;" onclick="submitBid()"><i class="fas fa-paper-plane me-1"></i>Submit Bid</button>
            </div>
        </div>
    </div>
</div>

<!-- JOB DETAIL MODAL -->
<div class="modal fade" id="jobDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:linear-gradient(135deg,#0A2D5E,#163f7a);color:white;">
                <h5 class="modal-title fw-bold" id="jdTitle">Job Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="jobDetailBody">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const bidModal = new bootstrap.Modal(document.getElementById('bidModal'));
const postJobModal = new bootstrap.Modal(document.getElementById('postJobModal'));
const jobDetailModal = new bootstrap.Modal(document.getElementById('jobDetailModal'));
const isDev = <?= $isDev ? 'true' : 'false' ?>;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

function openJobModal(job) {
    document.getElementById('jdTitle').textContent = job.title;
    const skills = (job.skills_required || '').split(',').filter(s=>s.trim()).map(s=>`<span class="skill-tag">${s.trim()}</span>`).join('');
    const budget = job.budget_min > 0
        ? `${job.currency}${parseInt(job.budget_min).toLocaleString()} ${job.budget_max > job.budget_min ? '– '+job.currency+parseInt(job.budget_max).toLocaleString() : ''}`
        : 'Negotiable';
    const expColors = {entry:'#15803d',intermediate:'#1d4ed8',expert:'#6d28d9'};
    const statusMap = {open:'🟢 Open',in_progress:'🔵 In Progress',pending_review:'🟡 Under Review',completed:'✅ Completed',cancelled:'❌ Cancelled'};
    
    document.getElementById('jobDetailBody').innerHTML = `
        <div class="row g-0">
            <div class="col-md-8 p-4 border-end">
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <span class="category-chip"><i class="fas fa-tag me-1"></i>${job.category}</span>
                    <span class="exp-chip exp-${job.experience_level}">${job.experience_level}</span>
                    ${job.is_remote == 1 ? '<span style="font-size:0.72rem;color:#6d28d9;font-weight:600;background:#faf5ff;padding:0.2rem 0.65rem;border-radius:50px;border:1px solid #c4b5fd;"><i class="fas fa-globe me-1"></i>Remote</span>' : ''}
                    ${job.budget_type === 'hourly' ? '<span style="font-size:0.72rem;color:#0d9488;font-weight:600;background:#f0fdfa;padding:0.2rem 0.65rem;border-radius:50px;border:1px solid #99f6e4;"><i class="fas fa-clock me-1"></i>Hourly</span>' : ''}
                </div>
                <h4 class="fw-bold text-body mb-3">${job.title}</h4>
                <div class="mb-4" style="font-size:0.9rem;line-height:1.7;color:#374151;">${(job.description || '').replace(/\n/g,'<br>')}</div>
                ${skills ? `<div class="mb-3"><div class="text-muted small fw-semibold mb-2">Required Skills</div>${skills}</div>` : ''}
                <div class="p-3 rounded-3" style="background:var(--color-bg);border:1px solid #e2e8f0;font-size:0.82rem;color:#64748b;">
                    <i class="fas fa-user me-1"></i>Posted by <strong>${job.poster_name || 'WQS'}</strong> · 
                    ${job.created_at ? new Date(job.created_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : ''} · 
                    <i class="fas fa-eye me-1 ms-2"></i>${job.views || 0} views · 
                    <i class="fas fa-gavel me-1 ms-2"></i>${job.bid_count || 0} bids
                </div>
            </div>
            <div class="col-md-4 p-4">
                <div class="mb-4 p-3 rounded-3" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #bfdbfe;">
                    <div class="text-muted small fw-semibold mb-1">Budget</div>
                    <div style="font-size:1.5rem;font-weight:900;color:#1d4ed8;">${budget}</div>
                    <div style="font-size:0.75rem;color:#3b82f6;">${job.budget_type === 'hourly' ? 'per hour' : 'fixed price'}</div>
                </div>
                ${job.deadline ? `<div class="mb-3 p-3 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa;"><div class="text-muted small fw-semibold mb-1"><i class="fas fa-calendar me-1"></i>Deadline</div><div class="fw-bold" style="color:#c2410c;">${new Date(job.deadline).toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'})}</div></div>` : ''}
                <div class="mb-3 p-3 rounded-3" style="background:#f0fdf4;border:1px solid #86efac;">
                    <div class="text-muted small fw-semibold mb-1">Status</div>
                    <div class="fw-bold" style="color:#15803d;">${statusMap[job.status] || job.status}</div>
                </div>
                ${isDev && job.status === 'open' ? `<button class="btn w-100 rounded-pill fw-bold py-2" style="background:#0A2D5E;border:none;color:white;" onclick="jobDetailModal.hide();setTimeout(()=>openBidModal(${job.id},${JSON.stringify(job.title)}),400)"><i class="fas fa-gavel me-1"></i>Place Bid Now</button>` : ''}
                ${isAdmin && job.status === 'pending_review' ? `<div class="d-flex gap-2"><button class="btn flex-grow-1 rounded-pill fw-bold" style="background:#15803d;border:none;color:white;" onclick="jobDetailModal.hide();approveJob(${job.id})"><i class="fas fa-check me-1"></i>Approve</button><button class="btn flex-grow-1 rounded-pill fw-bold" style="background:#dc2626;border:none;color:white;" onclick="jobDetailModal.hide();rejectJob(${job.id})"><i class="fas fa-times me-1"></i>Reject</button></div>` : ''}
            </div>
        </div>
    `;
    jobDetailModal.show();
}

function openBidModal(jobId, jobTitle) {
    document.getElementById('bidJobId').value = jobId;
    document.getElementById('bidJobTitle').textContent = jobTitle;
    document.getElementById('bid_amount').value = '';
    document.getElementById('bid_cover').value = '';
    bidModal.show();
}

function submitBid() {
    const jobId  = document.getElementById('bidJobId').value;
    const amount = document.getElementById('bid_amount').value;
    const days   = document.getElementById('bid_days').value;
    const cover  = document.getElementById('bid_cover').value.trim();
    if (!amount || !cover) { Swal.fire({icon:'warning',title:'Required',text:'Please fill in all bid fields.',confirmButtonColor:'#0A2D5E'}); return; }
    
    fetch('freelance_jobs.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `ajax_action=submit_bid&job_id=${jobId}&bid_amount=${amount}&delivery_days=${days}&cover_letter=${encodeURIComponent(cover)}`
    }).then(r=>r.json()).then(data => {
        if (data.success) {
            bidModal.hide();
            Swal.fire({icon:'success',title:'Bid Submitted!',text:'Your proposal has been sent to the client.',confirmButtonColor:'#0A2D5E',timer:3000}).then(()=>location.reload());
        } else Swal.fire({icon:'error',title:'Failed',text:data.message||'Could not submit bid.',confirmButtonColor:'#dc3545'});
    });
}

function submitPostJob() {
    const title  = document.getElementById('pj_title').value.trim();
    const desc   = document.getElementById('pj_desc').value.trim();
    const cat    = document.getElementById('pj_cat').value;
    const exp    = document.getElementById('pj_exp').value;
    const skills = document.getElementById('pj_skills').value;
    const btype  = document.getElementById('pj_btype').value;
    const cur    = document.getElementById('pj_cur').value;
    const bmin   = document.getElementById('pj_bmin').value || 0;
    const bmax   = document.getElementById('pj_bmax').value || 0;
    const dl     = document.getElementById('pj_deadline').value;
    const remote = document.getElementById('pj_remote').checked ? 'on' : '';
    if (!title||!desc) { Swal.fire({icon:'warning',title:'Required',text:'Title and description are required.',confirmButtonColor:'#0A2D5E'}); return; }
    fetch('freelance_jobs.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`ajax_action=post_job&title=${encodeURIComponent(title)}&description=${encodeURIComponent(desc)}&category=${encodeURIComponent(cat)}&experience=${exp}&skills=${encodeURIComponent(skills)}&budget_type=${btype}&currency=${encodeURIComponent(cur)}&budget_min=${bmin}&budget_max=${bmax}&deadline=${dl}&is_remote=${remote}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            postJobModal.hide();
            Swal.fire({icon:'success',title:'Job Posted!',text:<?= $isAdmin ? "'Your job is now live on the marketplace.'" : "'Your job is under review and will be published shortly.'" ?>,confirmButtonColor:'#0A2D5E',timer:3500}).then(()=>location.reload());
        } else Swal.fire({icon:'error',title:'Failed',text:data.message||'Could not post job.',confirmButtonColor:'#dc3545'});
    });
}

function approveJob(jid) {
    Swal.fire({title:'Approve Job Listing?',text:'This will make the job live on the marketplace.',icon:'question',showCancelButton:true,confirmButtonText:'Approve',confirmButtonColor:'#15803d'})
    .then(r=>{ if (!r.isConfirmed) return;
        fetch('freelance_jobs.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=approve_job&job_id=${jid}`})
        .then(r=>r.json()).then(d=>{if(d.success)Swal.fire({icon:'success',title:'Approved!',confirmButtonColor:'#0A2D5E',timer:2000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});});});
}

function rejectJob(jid) {
    Swal.fire({title:'Reject this Listing?',input:'textarea',inputPlaceholder:'Reason for rejection...',showCancelButton:true,confirmButtonText:'Reject',confirmButtonColor:'#dc2626'})
    .then(r=>{ if (!r.isConfirmed) return; const reason=r.value||'';
        fetch('freelance_jobs.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=reject_job&job_id=${jid}&reason=${encodeURIComponent(reason)}`})
        .then(r=>r.json()).then(d=>{if(d.success)Swal.fire({icon:'success',title:'Rejected',confirmButtonColor:'#0A2D5E',timer:2000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});});});
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
