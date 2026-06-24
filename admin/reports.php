<?php
$path_to_root = "../";
$page_title = "Platform Reports";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';


// Fetch comprehensive platform stats
$stats = [];
try {
    // Users
    $r = $pdo->query("SELECT COUNT(*) FROM users WHERE role!='admin'"); $stats['total_users'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'"); $stats['total_clients'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'"); $stats['total_agents'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM users WHERE role='developer'"); $stats['total_devs'] = (int)$r->fetchColumn();

    // New users this month
    $r = $pdo->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND role!='admin'");
    $stats['new_users_month'] = (int)$r->fetchColumn();

    // Projects
    $r = $pdo->query("SELECT COUNT(*) FROM ongoing_projects"); $stats['total_projects'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM ongoing_projects WHERE status='completed'"); $stats['completed_projects'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM ongoing_projects WHERE status='ongoing'"); $stats['ongoing_projects'] = (int)$r->fetchColumn();

    // Revenue
    $r = $pdo->query("SELECT IFNULL(SUM(budget),0) FROM ongoing_projects WHERE status='completed'"); $stats['revenue_ngn'] = (float)$r->fetchColumn();
    $r = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM invoices WHERE status='paid'"); $stats['invoices_paid'] = (float)$r->fetchColumn();
    $r = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM invoices WHERE status!='paid'"); $stats['invoices_unpaid'] = (float)$r->fetchColumn();

    // Requests
    $r = $pdo->query("SELECT COUNT(*) FROM client_requests"); $stats['total_requests'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status='pending'"); $stats['pending_requests'] = (int)$r->fetchColumn();

    // Support
    $r = $pdo->query("SELECT COUNT(*) FROM support_tickets"); $stats['total_tickets'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='resolved'"); $stats['resolved_tickets'] = (int)$r->fetchColumn();

    // Payouts
    $r = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM payout_requests WHERE status='processed'"); $stats['total_payouts'] = (float)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM payout_requests WHERE status='pending'"); $stats['pending_payouts'] = (int)$r->fetchColumn();

    // Freelance
    $r = $pdo->query("SELECT COUNT(*) FROM freelance_jobs"); $stats['fl_jobs'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM freelance_jobs WHERE status='open'"); $stats['fl_open'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT COUNT(*) FROM freelance_contracts"); $stats['fl_contracts'] = (int)$r->fetchColumn();
    $r = $pdo->query("SELECT IFNULL(SUM(contract_amount),0) FROM freelance_contracts WHERE status='completed'"); $stats['fl_revenue'] = (float)$r->fetchColumn();

    // Platform commission from freelance (10%)
    $stats['fl_commission'] = $stats['fl_revenue'] * 0.10;

    // Meetings
    $r = $pdo->query("SELECT COUNT(*) FROM meeting_bookings WHERE status='pending'"); $stats['pending_meetings'] = (int)$r->fetchColumn();

} catch (Exception $e) {}

// Monthly growth: new users per month (last 6 months)
$monthlyUsers = [];
try {
    $r = $pdo->query("SELECT MONTH(created_at) as m, YEAR(created_at) as y, COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND role!='admin' GROUP BY y, m ORDER BY y, m");
    while ($row = $r->fetch()) {
        $monthlyUsers[date('M Y', mktime(0,0,0,$row['m'],1,$row['y']))] = $row['c'];
    }
} catch (Exception $e) {}

// Recent activities
$recentActs = [];
try {
    $r = $pdo->query("
        (SELECT 'project' AS type, title AS detail, status, created_at FROM ongoing_projects ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'request' AS type, project_name AS detail, status, created_at FROM client_requests ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'user' AS type, CONCAT(name,' (',role,')') AS detail, 'joined' AS status, created_at FROM users WHERE role!='admin' ORDER BY created_at DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 12
    ");
    $recentActs = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.reports-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.reports-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.report-metric-card { background:white; border-radius:16px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 16px rgba(0,0,0,0.04); padding:1.25rem; transition:all 0.2s; }
.report-metric-card:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,0,0,0.08); }
.metric-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; margin-bottom:0.75rem; }
.report-section-card { background:white; border-radius:18px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 20px rgba(0,0,0,0.04); overflow:hidden; }
.section-header-bar { padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.progress-mini { height:6px; border-radius:50px; background:#e2e8f0; overflow:hidden; }
.act-item { padding:0.75rem 1rem; border-radius:10px; background:var(--color-bg); border:1px solid #f1f5f9; margin-bottom:0.5rem; display:flex; gap:0.75rem; align-items:center; }
.act-type { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; }
</style>

<!-- Hero -->
<div class="reports-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-chart-bar me-1"></i>Admin Reports</span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Platform Analytics & Reports</h1>
        <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">Comprehensive overview of all platform activity and revenue metrics.</p>
    </div>
</div>

<!-- Revenue Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="report-metric-card">
            <div class="metric-icon" style="background:#dcfce7;"><i class="fas fa-coins" style="color:#15803d;"></i></div>
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#15803d;margin-bottom:0.25rem;">Total Revenue</div>
            <div style="font-size:1.6rem;font-weight:900;color:#14532d;line-height:1;">₦<?= number_format($stats['revenue_ngn'],0) ?></div>
            <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">From completed projects</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="report-metric-card">
            <div class="metric-icon" style="background:#eff6ff;"><i class="fas fa-file-invoice-dollar" style="color:#1d4ed8;"></i></div>
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#1d4ed8;margin-bottom:0.25rem;">Invoices Collected</div>
            <div style="font-size:1.6rem;font-weight:900;color:#1e40af;line-height:1;">₦<?= number_format($stats['invoices_paid'],0) ?></div>
            <div style="font-size:0.72rem;color:#dc2626;margin-top:0.2rem;">₦<?= number_format($stats['invoices_unpaid'],0) ?> outstanding</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="report-metric-card">
            <div class="metric-icon" style="background:#fdf4ff;"><i class="fas fa-percentage" style="color:#9333ea;"></i></div>
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#9333ea;margin-bottom:0.25rem;">Freelance Commission</div>
            <div style="font-size:1.6rem;font-weight:900;color:#6b21a8;line-height:1;">₦<?= number_format($stats['fl_commission'],0) ?></div>
            <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">10% of <?= number_format($stats['fl_revenue'],0) ?> fl. contracts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="report-metric-card">
            <div class="metric-icon" style="background:#fff7ed;"><i class="fas fa-money-check-alt" style="color:#ea580c;"></i></div>
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#ea580c;margin-bottom:0.25rem;">Total Payouts</div>
            <div style="font-size:1.6rem;font-weight:900;color:#c2410c;line-height:1;">₦<?= number_format($stats['total_payouts'],0) ?></div>
            <div style="font-size:0.72rem;color:#dc2626;margin-top:0.2rem;"><?= $stats['pending_payouts'] ?> pending approval</div>
        </div>
    </div>
</div>

<!-- User & Project Stats -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="report-section-card">
            <div class="section-header-bar">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-users me-2 text-primary"></i>User Base</h6>
            </div>
            <div class="p-4">
                <?php
                $uMetrics = [
                    ['Total Users', $stats['total_users'], $stats['total_users'],'#0A2D5E'],
                    ['Clients', $stats['total_clients'], $stats['total_users'],'#1d4ed8'],
                    ['Partners/Agents', $stats['total_agents'], $stats['total_users'],'#6d28d9'],
                    ['Developers', $stats['total_devs'], $stats['total_users'],'#0d9488'],
                ];
                foreach ($uMetrics as [$l,$v,$total,$c]): $pct = $total>0 ? round($v/$total*100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.82rem;font-weight:600;color:#374151;"><?=$l?></span>
                        <span style="font-size:0.82rem;font-weight:700;color:<?=$c?>;"><?=$v?></span>
                    </div>
                    <div class="progress-mini"><div style="width:<?=$pct?>%;height:100%;background:<?=$c?>;border-radius:50px;"></div></div>
                </div>
                <?php endforeach; ?>
                <div class="mt-3 p-2 rounded-3" style="background:#f0fdf4;border:1px solid #86efac;font-size:0.78rem;color:#15803d;font-weight:600;">
                    <i class="fas fa-arrow-up me-1"></i><?= $stats['new_users_month'] ?> new users this month
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="report-section-card">
            <div class="section-header-bar">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-briefcase me-2 text-success"></i>Projects</h6>
            </div>
            <div class="p-4">
                <?php
                $pTotal = $stats['total_projects'] ?: 1;
                $pMetrics = [
                    ['All Projects', $stats['total_projects'], $pTotal, '#0A2D5E'],
                    ['Ongoing', $stats['ongoing_projects'], $pTotal, '#1d4ed8'],
                    ['Completed', $stats['completed_projects'], $pTotal, '#15803d'],
                ];
                foreach ($pMetrics as [$l,$v,$t,$c]): $pct = round($v/$t*100); ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.82rem;font-weight:600;color:#374151;"><?=$l?></span>
                        <span style="font-size:0.82rem;font-weight:700;color:<?=$c?>;"><?=$v?></span>
                    </div>
                    <div class="progress-mini"><div style="width:<?=$pct?>%;height:100%;background:<?=$c?>;border-radius:50px;"></div></div>
                </div>
                <?php endforeach; ?>
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:0.82rem;color:#64748b;">Client Requests</span>
                        <span style="font-size:0.82rem;font-weight:700;color:#d97706;"><?= $stats['total_requests'] ?> total (<?= $stats['pending_requests'] ?> pending)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="report-section-card">
            <div class="section-header-bar">
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-briefcase me-2 text-purple" style="color:#9333ea;"></i>Freelance Marketplace</h6>
            </div>
            <div class="p-4">
                <?php
                $flMetrics = [
                    ['Total Jobs Posted', $stats['fl_jobs'], '#0A2D5E'],
                    ['Open Jobs', $stats['fl_open'], '#1d4ed8'],
                    ['Active Contracts', $stats['fl_contracts'], '#15803d'],
                ];
                foreach ($flMetrics as [$l,$v,$c]): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span style="font-size:0.82rem;color:#374151;"><?=$l?></span>
                    <span style="font-size:0.9rem;font-weight:700;color:<?=$c?>;"><?=$v?></span>
                </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#9333ea;margin-bottom:0.35rem;">Marketplace Revenue</div>
                    <div style="font-size:1.4rem;font-weight:900;color:#6b21a8;">₦<?= number_format($stats['fl_revenue'],0) ?></div>
                    <div style="font-size:0.75rem;color:#94a3b8;">Commission: ₦<?= number_format($stats['fl_commission'],0) ?> (10%)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Support & Activity -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="report-section-card">
            <div class="section-header-bar"><h6 class="fw-bold text-body mb-0"><i class="fas fa-headset me-2 text-purple" style="color:#9333ea;"></i>Support Overview</h6></div>
            <div class="p-4">
                <?php
                $sTotal = $stats['total_tickets'] ?: 1;
                $resolved_pct = round($stats['resolved_tickets']/$sTotal*100);
                ?>
                <div class="text-center mb-4">
                    <div style="font-size:3rem;font-weight:900;color:#0A2D5E;line-height:1;"><?= $stats['total_tickets'] ?></div>
                    <div style="font-size:0.78rem;color:#94a3b8;">Total Support Tickets</div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1"><span style="font-size:0.82rem;color:#374151;">Resolution Rate</span><span style="font-size:0.82rem;font-weight:700;color:#15803d;"><?= $resolved_pct ?>%</span></div>
                    <div class="progress-mini"><div style="width:<?= $resolved_pct ?>%;height:100%;background:#15803d;border-radius:50px;"></div></div>
                </div>
                <div class="d-flex justify-content-between py-2 border-top mt-3">
                    <span style="font-size:0.82rem;color:#374151;">Resolved</span>
                    <span style="font-size:0.82rem;font-weight:700;color:#15803d;"><?= $stats['resolved_tickets'] ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-top">
                    <span style="font-size:0.82rem;color:#374151;">Pending Meetings</span>
                    <span style="font-size:0.82rem;font-weight:700;color:#d97706;"><?= $stats['pending_meetings'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="report-section-card">
            <div class="section-header-bar"><h6 class="fw-bold text-body mb-0"><i class="fas fa-stream me-2 text-primary"></i>Recent Platform Activity</h6></div>
            <div class="p-4">
                <?php if (empty($recentActs)): ?>
                <div class="text-center py-4 text-muted"><p class="small">No recent activity found.</p></div>
                <?php else: ?>
                <?php foreach ($recentActs as $act):
                    $typeMap = ['project'=>['fas fa-briefcase','#eff6ff','#1d4ed8'],'request'=>['fas fa-lightbulb','#fef3c7','#d97706'],'user'=>['fas fa-user-plus','#dcfce7','#15803d']];
                    [$aIcon,$aBg,$aCl] = $typeMap[$act['type']] ?? ['fas fa-circle','#f1f5f9','#64748b'];
                ?>
                <div class="act-item">
                    <div class="act-type" style="background:<?=$aBg?>;"><i class="<?=$aIcon?>" style="color:<?=$aCl?>;font-size:0.82rem;"></i></div>
                    <div class="flex-grow-1">
                        <div style="font-size:0.85rem;font-weight:600;color:#0A2D5E;"><?= htmlspecialchars($act['detail']??'') ?></div>
                        <div style="font-size:0.72rem;color:#94a3b8;"><?= ucfirst($act['type']) ?> · <?= $act['status'] ?> · <?= date('M d, Y', strtotime($act['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="row g-3">
    <?php
    $qa = [
        ['invoice_management.php','fas fa-file-invoice-dollar','Manage Invoices','Create and manage all client invoices','#eff6ff','#1d4ed8'],
        ['payout_approvals.php','fas fa-money-check-alt','Payout Approvals','Review and approve pending payouts','#dcfce7','#15803d'],
        ['support_center.php','fas fa-headset','Support Center','Respond to open support tickets','#fdf4ff','#9333ea'],
        ['freelance_admin.php','fas fa-briefcase','Freelance Control','Review and manage job listings & bids','#fff7ed','#ea580c'],
        ['broadcast.php','fas fa-bullhorn','Broadcast','Send announcements to all users','#fef3c7','#d97706'],
        ['manage_users.php','fas fa-users','Manage Users','View and control platform users','#f0f9ff','#0284c7'],
    ];
    foreach ($qa as [$href,$icon,$title,$desc,$bg,$col]):
    ?>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?=$href?>" class="text-decoration-none">
            <div style="background:<?=$bg?>;border:1.5px solid <?=$col?>22;border-radius:14px;padding:1.1rem;text-align:center;transition:all 0.25s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="width:44px;height:44px;border-radius:12px;background:<?=$col?>;display:flex;align-items:center;justify-content:center;color:white;margin:0 auto 0.65rem;"><i class="<?=$icon?>"></i></div>
                <div style="font-size:0.82rem;font-weight:700;color:<?=$col?>;"><?=$title?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
