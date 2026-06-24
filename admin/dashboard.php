<?php
$path_to_root = "../";
$page_title = "Admin Dashboard";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

$formatted_login = '';
if (!empty($user['last_login'])) {
    $timestamp = strtotime($user['last_login']);
    if ($timestamp !== false) {
        $formatted_login = date('F j, Y \a\t h:i A', $timestamp);
    }
}

// === Stats ===
$projectCount = 0;
$projResult = $db->query("SELECT COUNT(*) as total FROM projects");
if ($projResult && $row = $projResult->fetch_assoc()) $projectCount = $row['total'];

$requestTotal = 0; $requestPending = 0;
$reqResult = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM client_requests");
if ($reqResult && $row = $reqResult->fetch_assoc()) { $requestTotal = $row['total']; $requestPending = $row['pending']; }

$usersCount = 0; $agentCount = 0;
$usersResult = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN role='agent' THEN 1 ELSE 0 END) as agents FROM users WHERE role != 'admin'");
if ($usersResult && $row = $usersResult->fetch_assoc()) { $usersCount = $row['total']; $agentCount = $row['agents']; }

$pendingAgents = 0;
$arResult = $db->query("SELECT COUNT(*) as c FROM agent_requests WHERE status='pending'");
if ($arResult && $row = $arResult->fetch_assoc()) $pendingAgents = $row['c'];

$developerCount = 0;
$devResult = $db->query("SELECT COUNT(*) as c FROM users WHERE role='developer'");
if ($devResult && $row = $devResult->fetch_assoc()) $developerCount = $row['c'];

$pendingDevelopers = 0;
$pdrResult = $db->query("SELECT COUNT(*) as c FROM developer_requests WHERE status='pending'");
if ($pdrResult && $row = $pdrResult->fetch_assoc()) $pendingDevelopers = $row['c'];

// Total Revenue (sum of final_budget for completed)
$revenueRow = $db->query("SELECT SUM(budget) as ngn, SUM(final_budget) as usd FROM ongoing_projects WHERE status='completed'");
$revenueNGN = 0; $revenueUSD = 0;
if ($revenueRow && $rv = $revenueRow->fetch_assoc()) { $revenueNGN = (float)($rv['ngn']??0); $revenueUSD = (float)($rv['usd']??0); }

// Ongoing projects
$adminOngoingProjects = [];
$opResult = $db->query("SELECT op.*, u.name AS client_name, pm.name AS manager_name FROM ongoing_projects op LEFT JOIN users u ON op.user_id = u.id LEFT JOIN users pm ON op.project_manager_id = pm.id ORDER BY op.updated_at DESC LIMIT 5");
if ($opResult) { while ($row = $opResult->fetch_assoc()) $adminOngoingProjects[] = $row; }

// Recent client requests
$recentRequests = [];
$rrResult = $db->query("SELECT cr.*, u.name AS client_name FROM client_requests cr LEFT JOIN users u ON cr.user_id = u.id ORDER BY cr.created_at DESC LIMIT 5");
if ($rrResult) { while ($row = $rrResult->fetch_assoc()) $recentRequests[] = $row; }

// Open support tickets
$openTickets = 0;
$stRes = $db->query("SELECT COUNT(*) as c FROM support_tickets WHERE status != 'resolved'");
if ($stRes && $row = $stRes->fetch_assoc()) $openTickets = (int)$row['c'];

// Pending payouts
$pendingPayouts = 0;
$ppRes = $db->query("SELECT COUNT(*) as c FROM payout_requests WHERE status = 'pending'");
if ($ppRes && $row = $ppRes->fetch_assoc()) $pendingPayouts = (int)$row['c'];

// Unpaid invoices
$unpaidInvoices = 0; $unpaidInvoicesSum = 0;
$uiRes = $db->query("SELECT COUNT(*) as c, IFNULL(SUM(amount), 0) as s FROM invoices WHERE status != 'paid'");
if ($uiRes && $row = $uiRes->fetch_assoc()) { $unpaidInvoices = (int)$row['c']; $unpaidInvoicesSum = (float)$row['s']; }
?>

<style>
/* ======= ADMIN DASHBOARD PREMIUM STYLES ======= */
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

.admin-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 60%, #1a4a8a 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.admin-hero::before {
    content:''; position:absolute; top:-80px; right:-80px;
    width:280px; height:280px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.admin-hero::after {
    content:''; position:absolute; bottom:-60px; left:-40px;
    width:200px; height:200px; background:rgba(255,255,255,0.05); border-radius:50%;
}
.stat-card-premium {
    border-radius: 16px; padding: 1.5rem;
    border: 1px solid transparent; transition: all 0.3s ease;
    position: relative; overflow: hidden;
}
.stat-card-premium:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(0,0,0,0.08); }
.stat-card-premium .icon-wrap {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; margin-bottom: 1rem;
}
.stat-card-premium .stat-value {
    font-size: 2rem; font-weight: 900; line-height: 1; margin-bottom: 0.25rem;
}
.stat-card-premium .stat-label { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.2rem; }
.stat-card-premium .stat-sub { font-size: 0.75rem; }
.stat-card-premium .bg-pattern {
    position: absolute; top: -20px; right: -20px;
    width: 90px; height: 90px; border-radius: 50%;
    opacity: 0.07;
}

/* s1 = blue */
.stat-s1 { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-color: #bfdbfe; }
.stat-s1 .icon-wrap { background: #2563eb; color: white; }
.stat-s1 .stat-value { color: #1d4ed8; }
.stat-s1 .stat-label { color: #1e40af; }
.stat-s1 .bg-pattern { background: #1d4ed8; }
/* s2 = orange */
.stat-s2 { background: linear-gradient(135deg, #fff7ed, #fed7aa); border-color: #fdba74; }
.stat-s2 .icon-wrap { background: #ea580c; color: white; }
.stat-s2 .stat-value { color: #c2410c; }
.stat-s2 .stat-label { color: #9a3412; }
.stat-s2 .bg-pattern { background: #ea580c; }
/* s3 = green */
.stat-s3 { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-color: #86efac; }
.stat-s3 .icon-wrap { background: #16a34a; color: white; }
.stat-s3 .stat-value { color: #15803d; }
.stat-s3 .stat-label { color: #166534; }
.stat-s3 .bg-pattern { background: #16a34a; }
/* s4 = purple */
.stat-s4 { background: linear-gradient(135deg, #faf5ff, #ede9fe); border-color: #c4b5fd; }
.stat-s4 .icon-wrap { background: #7c3aed; color: white; }
.stat-s4 .stat-value { color: #6d28d9; }
.stat-s4 .stat-label { color: #5b21b6; }
.stat-s4 .bg-pattern { background: #7c3aed; }
/* s5 = teal */
.stat-s5 { background: linear-gradient(135deg, #f0fdfa, #ccfbf1); border-color: #99f6e4; }
.stat-s5 .icon-wrap { background: #0d9488; color: white; }
.stat-s5 .stat-value { color: #0f766e; }
.stat-s5 .stat-label { color: #115e59; }
.stat-s5 .bg-pattern { background: #0d9488; }
/* s6 = rose */
.stat-s6 { background: linear-gradient(135deg, #fff1f2, #ffe4e6); border-color: #fecdd3; }
.stat-s6 .icon-wrap { background: #e11d48; color: white; }
.stat-s6 .stat-value { color: #be123c; }
.stat-s6 .stat-label { color: #9f1239; }
.stat-s6 .bg-pattern { background: #e11d48; }

.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem;
}
.section-header h5 { font-size: 1rem; font-weight: 700; color: var(--color-text-body); margin: 0; }
.project-row { padding: 0.85rem 0; border-bottom: 1px solid var(--color-border); }
.project-row:last-child { border-bottom: none; }
.project-row .title { font-weight: 600; font-size: 0.9rem; color: var(--color-text-body); margin-bottom: 0.3rem; }
.project-row .meta { font-size: 0.75rem; color: var(--color-text-light); }
.custom-progress { height: 6px; border-radius: 3px; background: #e2e8f0; overflow: hidden; margin: 0.4rem 0; }
.custom-progress .fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #0A2D5E, #2563eb); }
.status-badge {
    font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.6rem;
    border-radius: 50px; text-transform: uppercase; letter-spacing: 0.05em;
}
.sb-pending  { background: #fef3c7; color: #92400e; }
.sb-reviewed { background: #dbeafe; color: #1e40af; }
.sb-approved { background: #dcfce7; color: #15803d; }
.sb-rejected { background: #fee2e2; color: #991b1b; }

/* ===== PREMIUM QUICK ACTION CARDS ===== */
.quick-action-card {
    position: relative;
    overflow: hidden;
    border-radius: 16px;
    padding: 1.4rem 1.25rem;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    border: 1px solid rgba(0,0,0,0.05);
    background: white;
}
.quick-action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #0A2D5E, #2563eb);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.quick-action-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15);
    border-color: rgba(10,45,94,0.15);
}
.quick-action-card:hover::before {
    transform: scaleX(1);
}
.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--qa-bg-1), var(--qa-bg-2));
    color: white;
    box-shadow: 0 10px 20px -8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}
.quick-action-card:hover .quick-action-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 30px -10px rgba(0,0,0,0.25);
}
.quick-action-title {
    font-weight: 800;
    font-size: 0.95rem;
    margin-bottom: 0.4rem;
    color: #0f172a;
    line-height: 1.2;
}
.quick-action-sub {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.3;
}
.qa-glow {
    position: absolute;
    bottom: -30px;
    right: -30px;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--qa-glow-color);
    opacity: 0.1;
    transition: all 0.4s ease;
}
.quick-action-card:hover .qa-glow {
    transform: scale(1.2);
    opacity: 0.18;
}
</style>

<!-- ===== HERO ===== -->
<div class="admin-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;">
                    <i class="fas fa-shield-alt me-1"></i> Administrator
                </span>
                <span style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.72rem;font-weight:600;">● All Systems Active</span>
            </div>
            <h1 style="font-size:1.6rem;font-weight:800;color:white;margin-bottom:0.4rem;">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
                <?php if ($requestPending > 0): ?><span style="color:#ffb380;font-weight:600;"><?= $requestPending ?> pending request<?= $requestPending>1?'s':''?></span> awaiting review · <?php endif; ?>
                <?php if ($openTickets > 0): ?><span style="color:#fcd34d;font-weight:600;"><?= $openTickets ?> open support ticket<?= $openTickets>1?'s':''?></span> · <?php endif; ?>
                <?php if ($pendingPayouts > 0): ?><span style="color:#86efac;font-weight:600;"><?= $pendingPayouts ?> payout<?= $pendingPayouts>1?'s':''?></span> pending approval · <?php endif; ?>
                <?= $usersCount ?> registered client<?= $usersCount>1?'s':''?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="client_requests.php" class="btn" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.85rem;"><i class="fas fa-inbox me-1"></i> Review Requests</a>
            <a href="create-portfolio.php" class="btn" style="background:#E15501;border:none;color:white;border-radius:8px;font-size:0.85rem;"><i class="fas fa-plus me-1"></i> Add Portfolio</a>
        </div>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="client_requests.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s1">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-briefcase"></i></div>
                <div class="stat-value"><?= intval($projectCount) ?></div>
                <div class="stat-label">Projects</div>
                <div class="stat-sub" style="color:#3b82f6;">Live on website</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="client_requests.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s2">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-file-signature"></i></div>
                <div class="stat-value"><?= intval($requestTotal) ?></div>
                <div class="stat-label">Client Requests</div>
                <div class="stat-sub" style="color:#ea580c;"><?= intval($requestPending) ?> pending</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="manage_users.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s3">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= intval($usersCount) ?></div>
                <div class="stat-label">Clients</div>
                <div class="stat-sub" style="color:#16a34a;"><?= intval($agentCount) ?> partner<?= $agentCount!=1?'s':''?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="agent_requests.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s4">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-handshake"></i></div>
                <div class="stat-value"><?= intval($pendingAgents) ?></div>
                <div class="stat-label">Partner Req.</div>
                <div class="stat-sub" style="color:#7c3aed;">Awaiting review</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="manage_developers.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s5">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-code"></i></div>
                <div class="stat-value"><?= intval($developerCount) ?></div>
                <div class="stat-label">Hired Devs</div>
                <div class="stat-sub" style="color:#0d9488;">Active in hub</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="developer_requests.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s6">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-user-clock"></i></div>
                <div class="stat-value"><?= intval($pendingDevelopers) ?></div>
                <div class="stat-label">Dev Requests</div>
                <div class="stat-sub" style="color:#e11d48;">Awaiting review</div>
            </div>
        </a>
    </div>
</div>

<!-- ===== EXTRA STAT CARDS ROW ===== -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-4">
        <a href="support_center.php" class="text-decoration-none">
            <div class="stat-card-premium" style="background:linear-gradient(135deg,#fdf4ff,#f3e8ff);border-color:#d8b4fe;">
                <div class="bg-pattern" style="background:#9333ea;"></div>
                <div class="icon-wrap" style="background:#9333ea;"><i class="fas fa-headset"></i></div>
                <div class="stat-value" style="color:#7e22ce;"><?= $openTickets ?></div>
                <div class="stat-label" style="color:#6b21a8;">Open Support Tickets</div>
                <div class="stat-sub" style="color:#9333ea;">Awaiting admin reply</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="payout_approvals.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s3">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-money-check-alt"></i></div>
                <div class="stat-value"><?= $pendingPayouts ?></div>
                <div class="stat-label">Pending Payouts</div>
                <div class="stat-sub" style="color:#16a34a;">Awaiting approval</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="invoice_management.php" class="text-decoration-none">
            <div class="stat-card-premium stat-s2">
                <div class="bg-pattern"></div>
                <div class="icon-wrap"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-value"><?= $unpaidInvoices ?></div>
                <div class="stat-label">Unpaid Invoices</div>
                <div class="stat-sub" style="color:#ea580c;">₦<?= number_format($unpaidInvoicesSum, 0) ?> outstanding</div>
            </div>
        </a>
    </div>
</div>

<!-- ===== REVENUE BANNER ===== -->
<div class="mb-4" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:16px;padding:1.5rem 2rem;">
    <div class="row align-items-center g-3">
        <div class="col-md-6">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.08em;color:#15803d;font-weight:700;margin-bottom:0.3rem;"><i class="fas fa-chart-line me-1"></i> Total Revenue from Completed Projects</div>
            <div style="font-size:2.2rem;font-weight:900;color:#14532d;line-height:1;">₦<?= number_format($revenueNGN, 0) ?></div>
            <div style="color:#16a34a;font-size:0.82rem;margin-top:0.25rem;">≈ $<?= number_format($revenueUSD, 0) ?> USD equivalent</div>
        </div>
        <div class="col-md-6">
            <div class="row g-3">
                <?php
                $ongoingCount = $db->query("SELECT COUNT(*) as c FROM ongoing_projects WHERE status='ongoing'")->fetch_assoc()['c'] ?? 0;
                $completedCount = $db->query("SELECT COUNT(*) as c FROM ongoing_projects WHERE status='completed'")->fetch_assoc()['c'] ?? 0;
                ?>
                <div class="col-6 text-center">
                    <div style="font-size:1.6rem;font-weight:900;color:#15803d;"><?= $ongoingCount ?></div>
                    <div style="font-size:0.78rem;color:#16a34a;font-weight:600;">Active Projects</div>
                </div>
                <div class="col-6 text-center">
                    <div style="font-size:1.6rem;font-weight:900;color:#15803d;"><?= $completedCount ?></div>
                    <div style="font-size:0.78rem;color:#16a34a;font-weight:600;">Completed Projects</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== ONGOING PROJECTS + RECENT REQUESTS ===== -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <div class="section-header">
                    <h5><i class="fas fa-rocket me-2 text-primary"></i>Active Ongoing Projects</h5>
                    <a href="client_requests.php" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem;">View All</a>
                </div>
            </div>
            <div class="card-theme-body p-0">
                <?php if (!empty($adminOngoingProjects)): ?>
                <div class="px-4">
                    <?php foreach ($adminOngoingProjects as $op): 
                        $statusColors = ['ongoing'=>'#2563eb','on-hold'=>'#f59e0b','completed'=>'#16a34a','cancelled'=>'#ef4444'];
                        $sc = $statusColors[$op['status']] ?? '#64748b';
                    ?>
                    <div class="project-row">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="title"><?= htmlspecialchars($op['title']) ?></div>
                            <span style="background:<?= $sc ?>22;color:<?= $sc ?>;border:1px solid <?= $sc ?>44;padding:0.15rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;text-transform:uppercase;white-space:nowrap;margin-left:0.5rem;"><?= ucfirst($op['status']) ?></span>
                        </div>
                        <div class="custom-progress">
                            <div class="fill" style="width:<?= intval($op['progress']) ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between meta">
                            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($op['client_name'] ?? 'N/A') ?></span>
                            <span class="fw-semibold" style="color:var(--color-text-body);"><?= intval($op['progress']) ?>%</span>
                            <span><i class="fas fa-user-shield me-1"></i><?= !empty($op['manager_name']) ? htmlspecialchars($op['manager_name']) : 'Unassigned' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <div style="font-size:2.5rem;margin-bottom:0.5rem;">📂</div>
                    <p class="small mb-0">No active ongoing projects found.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <div class="section-header">
                    <h5><i class="fas fa-bell me-2" style="color:#E15501;"></i>Recent Client Requests</h5>
                    <a href="client_requests.php" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem;">View All</a>
                </div>
            </div>
            <div class="card-theme-body p-0">
                <?php if (!empty($recentRequests)): ?>
                <div class="px-4">
                    <?php foreach ($recentRequests as $rr): ?>
                    <div class="project-row">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="title"><?= htmlspecialchars($rr['title']) ?></div>
                                <div class="meta"><i class="fas fa-user me-1"></i><?= htmlspecialchars($rr['client_name'] ?? 'N/A') ?> · <?= date('M d, Y', strtotime($rr['created_at'])) ?></div>
                            </div>
                            <span class="status-badge sb-<?= $rr['status'] ?>"><?= ucfirst($rr['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <div style="font-size:2.5rem;margin-bottom:0.5rem;">📋</div>
                    <p class="small mb-0">No client requests yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="card-theme">
    <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-bolt me-2" style="color:#E15501;"></i>Quick Actions</h5>
    </div>
    <div class="card-theme-body">
        <div class="row g-3">
            <?php
            $quickActions = [
                ['client_requests.php','fas fa-lightbulb','#2563eb','#1d4ed8','#dbeafe','Review Requests','Manage all client requests'],
                ['create-portfolio.php','fas fa-plus-circle','#ea580c','#c2410c','#fed7aa','Add Portfolio','Publish new portfolio item'],
                ['agent_requests.php','fas fa-handshake','#7c3aed','#6d28d9','#ddd6fe','Partner Requests','Review agent applications'],
                ['developer_requests.php','fas fa-code','#0d9488','#0f766e','#a7f3d0','Dev Requests','Review developer applications'],
                ['manage_developers.php','fas fa-users-cog','#e11d48','#be123c','#fecdd3','Manage Devs','Assign tasks & track dev payouts'],
                ['manage_users.php','fas fa-users','#0284c7','#0369a1','#bae6fd','Manage Users','View & control all platform users'],
                ['invoice_management.php','fas fa-file-invoice-dollar','#ca8a04','#a16207','#fef08a','Invoices','Create & manage client invoices'],
                ['support_center.php','fas fa-headset','#9333ea','#7e22ce','#e9d5ff','Support Center','Reply to support tickets'],
                ['payout_approvals.php','fas fa-money-check-alt','#16a34a','#15803d','#bbf7d0','Payout Approvals','Approve developer & partner payouts'],
                ['manage_blog.php','fas fa-blog','#8b5cf6','#7c3aed','#ddd6fe','Blog Management','Create, edit & manage blog posts'],
                ['payroll.php','fas fa-calculator','#059669','#047857','#a7f3d0','Payroll System','Manage payroll, employees & payouts'],
            ];
            foreach ($quickActions as [$href,$icon,$qaBg1,$qaBg2,$qaGlow,$title,$sub]):
            ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= $href ?>" class="text-decoration-none">
                    <div class="quick-action-card" style="--qa-bg-1: <?= $qaBg1 ?>; --qa-bg-2: <?= $qaBg2 ?>; --qa-glow-color: <?= $qaGlow ?>;">
                        <div class="qa-glow"></div>
                        <div class="quick-action-icon">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div class="quick-action-title"><?= $title ?></div>
                        <div class="quick-action-sub"><?= $sub ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
