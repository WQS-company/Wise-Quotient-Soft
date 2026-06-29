<?php
$path_to_root = "../";
$page_title = "Client Dashboard";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Format last login
$formatted_login = '';
if (!empty($user['last_login'])) {
    $timestamp = strtotime($user['last_login']);
    if ($timestamp !== false) {
        $formatted_login = date('F j, Y \a\t h:i A', $timestamp);
    } else {
        $formatted_login = htmlspecialchars($user['last_login']);
    }
}

// === Fetch My Projects Counts ===
$projectCounts = [
    'total' => 0,
    'ongoing' => 0,
    'completed' => 0
];

$stmt = $db->prepare("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status='ongoing' THEN 1 ELSE 0 END) AS ongoing,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
FROM ongoing_projects
WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $projectCounts = $row;
}

// === Fetch Project Requests Counts ===
$requestCounts = [
    'total' => 0,
    'approved' => 0,
    'pending' => 0
];

$stmt = $db->prepare("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending
FROM client_requests
WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $requestCounts = $row;
}
$stmt->close();

// === Fetch Invoice Billing Stats ===
$unpaidInvoiceCount = 0;
$unpaidInvoiceSum = 0.0;
$invStmt = $db->prepare("SELECT COUNT(*), IFNULL(SUM(amount), 0) FROM invoices WHERE user_id = ? AND status != 'paid'");
$invStmt->bind_param("i", $user_id);
$invStmt->execute();
$invStmt->bind_result($unpaidInvoiceCount, $unpaidInvoiceSum);
$invStmt->fetch();
$invStmt->close();

// === Fetch Support Ticket Stats ===
$activeTickets = [];
$activeTicketCount = 0;
$ticketStmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'resolved'");
$ticketStmt->bind_param("i", $user_id);
$ticketStmt->execute();
$ticketStmt->bind_result($activeTicketCount);
$ticketStmt->fetch();
$ticketStmt->close();

// Get latest 3 support tickets for preview
$tListStmt = $db->prepare("SELECT id, subject, status, updated_at FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3");
$tListStmt->bind_param("i", $user_id);
$tListStmt->execute();
$res = $tListStmt->get_result();
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $activeTickets[] = $r;
    }
}
$tListStmt->close();

// === Fetch Active Ongoing Projects for Dashboard Summary ===
$myOngoingProjects = [];
$stmt = $db->prepare("
    SELECT op.*, u.name AS manager_name, u.email AS manager_email 
    FROM ongoing_projects op 
    LEFT JOIN users u ON op.project_manager_id = u.id 
    WHERE op.user_id = ? 
    ORDER BY op.updated_at DESC 
    LIMIT 3
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $myOngoingProjects[] = $row;
    }
}
$stmt->close();

// === Fetch Scholarship Applications for Candidate ===
$myScholarshipApplications = [];
try {
    $schStmt = $pdo->prepare("
        SELECT sa.*, s.title AS scholarship_title, s.closing_date 
        FROM scholarship_applications sa 
        JOIN scholarships s ON sa.scholarship_id = s.id 
        WHERE sa.user_id = ? 
        ORDER BY sa.submitted_at DESC
    ");
    $schStmt->execute([$user_id]);
    $myScholarshipApplications = $schStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// === Fetch Referral Stats for Agents ===
$agentEarningsUSD = 0.0;
$agentEarningsNGN = 0.0;
$referredCount = 0;
$successfulProjectsCount = 0;
$recentReferrals = [];

if ($user_role === 'agent') {
    // Get configurable commission percentage
    $baseCommissionPct = 10;
    try {
        $partnerChk = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
        $partnerChk->execute([$user_id]);
        $partnerRow = $partnerChk->fetch(PDO::FETCH_ASSOC);
        if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
            $baseCommissionPct = (float)$partnerRow['default_commission_percent'];
        } else {
            $setChk = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
            $setRow = $setChk->fetch(PDO::FETCH_ASSOC);
            if ($setRow) $baseCommissionPct = (float)$setRow['setting_value'];
        }
    } catch (Exception $e) {}

    // Count total referrals
    $refCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $refCountStmt->bind_param("i", $user_id);
    $refCountStmt->execute();
    $refCountStmt->bind_result($referredCount);
    $refCountStmt->fetch();
    $refCountStmt->close();

    // Count successful completed projects
    $successCountStmt = $db->prepare("
        SELECT COUNT(op.id) 
        FROM ongoing_projects op 
        INNER JOIN users u ON op.user_id = u.id 
        WHERE u.referred_by = ? AND op.status = 'completed'
    ");
    $successCountStmt->bind_param("i", $user_id);
    $successCountStmt->execute();
    $successCountStmt->bind_result($successfulProjectsCount);
    $successCountStmt->fetch();
    $successCountStmt->close();

    // Determine current tier & commission rate (tiers are multiples of the admin-set base)
    $tierName = "Bronze";
    $commissionRate = $baseCommissionPct / 100;
    $nextTierName = "Silver";
    $projectsNeeded = 3 - $successfulProjectsCount;
    $tierColor = "#d97706";
    $tierBg = "#fef3c7";

    if ($successfulProjectsCount >= 6) {
        $tierName = "Gold";
        $commissionRate = ($baseCommissionPct * 1.5) / 100;
        $nextTierName = "";
        $projectsNeeded = 0;
        $tierColor = "#ca8a04";
        $tierBg = "#fef9c3";
    } elseif ($successfulProjectsCount >= 3) {
        $tierName = "Silver";
        $commissionRate = ($baseCommissionPct * 1.2) / 100;
        $nextTierName = "Gold";
        $projectsNeeded = 6 - $successfulProjectsCount;
        $tierColor = "#4b5563";
        $tierBg = "#f3f4f6";
    }

    // Get completed projects budgets sum
    $earningsStmt = $db->prepare("
        SELECT 
            SUM(op.final_budget) AS sum_usd,
            SUM(op.budget) AS sum_ngn
        FROM ongoing_projects op 
        INNER JOIN users u ON op.user_id = u.id 
        WHERE u.referred_by = ? AND op.status = 'completed'
    ");
    $earningsStmt->bind_param("i", $user_id);
    $earningsStmt->execute();
    $earningsResult = $earningsStmt->get_result();
    $sumUSD = 0.0;
    $sumNGN = 0.0;
    if ($earningsResult && $row = $earningsResult->fetch_assoc()) {
        $sumUSD = (float)($row['sum_usd'] ?? 0.0);
        $sumNGN = (float)($row['sum_ngn'] ?? 0.0);
    }
    $earningsStmt->close();

    // Multiply sum by commission rate
    $agentEarningsUSD = $sumUSD * $commissionRate;
    $agentEarningsNGN = $sumNGN * $commissionRate;

    // Get latest 3 referrals
    $refStmt = $db->prepare("SELECT id, name, email, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC LIMIT 3");
    $refStmt->bind_param("i", $user_id);
    $refStmt->execute();
    $refRes = $refStmt->get_result();
    if ($refRes) {
        while ($r = $refRes->fetch_assoc()) {
            $recentReferrals[] = $r;
        }
    }
    $refStmt->close();
}
// === Fetch Developer Stats ===
$devTaskCounts = ['total'=>0,'in_progress'=>0,'completed'=>0,'assigned'=>0,'review'=>0];
$devEarnings = 0.0; $devPending = 0.0;
$devRecentTasks = [];
if ($user_role === 'developer') {
    $dtRes = $db->query("SELECT status, SUM(hourly_rate*hours_worked) AS earned FROM developer_tasks WHERE developer_id=$user_id GROUP BY status");
    if ($dtRes) {
        while ($dr = $dtRes->fetch_assoc()) {
            $devTaskCounts['total']++;
            if (isset($devTaskCounts[$dr['status']])) $devTaskCounts[$dr['status']]++;
            if ($dr['status']==='completed') $devEarnings += (float)$dr['earned'];
            else $devPending += (float)$dr['earned'];
        }
    }
    $totalR = $db->query("SELECT COUNT(*) AS c FROM developer_tasks WHERE developer_id=$user_id")->fetch_assoc()['c'] ?? 0;
    $completedR = $db->query("SELECT COUNT(*) AS c FROM developer_tasks WHERE developer_id=$user_id AND status='completed'")->fetch_assoc()['c'] ?? 0;
    $inProgressR = $db->query("SELECT COUNT(*) AS c FROM developer_tasks WHERE developer_id=$user_id AND status='in_progress'")->fetch_assoc()['c'] ?? 0;
    $assignedR = $db->query("SELECT COUNT(*) AS c FROM developer_tasks WHERE developer_id=$user_id AND status='assigned'")->fetch_assoc()['c'] ?? 0;
    $devTaskCounts = ['total'=>$totalR,'in_progress'=>$inProgressR,'completed'=>$completedR,'assigned'=>$assignedR,'review'=>0];
    $devCompletion = $totalR > 0 ? round($completedR/$totalR*100) : 0;
    // Earnings
    $earRes = $db->query("SELECT SUM(hourly_rate*hours_worked) AS earned FROM developer_tasks WHERE developer_id=$user_id AND status='completed'");
    $devEarnings = $earRes ? (float)($earRes->fetch_assoc()['earned'] ?? 0) : 0;
    $pendRes = $db->query("SELECT SUM(hourly_rate*hours_worked) AS earned FROM developer_tasks WHERE developer_id=$user_id AND status IN('assigned','in_progress','review')");
    $devPending = $pendRes ? (float)($pendRes->fetch_assoc()['earned'] ?? 0) : 0;
    // Recent tasks
    $rtRes = $db->query("SELECT dt.*, op.title AS project_title FROM developer_tasks dt LEFT JOIN ongoing_projects op ON dt.project_id=op.id WHERE dt.developer_id=$user_id ORDER BY FIELD(dt.priority,'urgent','high','medium','low'), dt.due_date ASC LIMIT 4");
    if ($rtRes) while ($rt = $rtRes->fetch_assoc()) $devRecentTasks[] = $rt;
}
?>

<!-- ===== PREMIUM USER DASHBOARD STYLES ===== -->
<style>
.user-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 16px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.user-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.user-hero::after  { content:''; position:absolute; bottom:-60px; left:-40px; width:180px; height:180px; background:rgba(255,255,255,0.04); border-radius:50%; }
.stat-card-u {
    border-radius: 16px; padding: 1.35rem 1.5rem;
    transition: all 0.3s ease; border: 1px solid transparent;
    position: relative; overflow: hidden; text-decoration: none;
}
.stat-card-u:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(0,0,0,0.08); }
.stat-card-u .bg-dot { position:absolute; top:-20px; right:-20px; width:80px; height:80px; border-radius:50%; opacity:0.07; }
.stat-card-u .icon-w { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; margin-bottom:0.85rem; }
.stat-card-u .s-val  { font-size:1.8rem; font-weight:900; line-height:1; margin-bottom:0.2rem; }
.stat-card-u .s-lbl  { font-size:0.82rem; font-weight:600; margin-bottom:0.15rem; }
.stat-card-u .s-sub  { font-size:0.72rem; }
.sc-blue   { background:linear-gradient(135deg,#eff6ff,#dbeafe); border-color:#bfdbfe; }
.sc-blue   .icon-w { background:#2563eb; color:white; } .sc-blue .bg-dot { background:#1d4ed8; } .sc-blue .s-val { color:#1d4ed8; } .sc-blue .s-lbl { color:#1e40af; } .sc-blue .s-sub { color:#3b82f6; }
.sc-amber  { background:linear-gradient(135deg,#fff7ed,#fed7aa); border-color:#fdba74; }
.sc-amber  .icon-w { background:#ea580c; color:white; } .sc-amber .bg-dot { background:#ea580c; } .sc-amber .s-val { color:#c2410c; } .sc-amber .s-lbl { color:#9a3412; } .sc-amber .s-sub { color:#ea580c; }
.sc-green  { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-color:#86efac; }
.sc-green  .icon-w { background:#16a34a; color:white; } .sc-green .bg-dot { background:#16a34a; } .sc-green .s-val { color:#15803d; } .sc-green .s-lbl { color:#166534; } .sc-green .s-sub { color:#16a34a; }
.sc-sky    { background:linear-gradient(135deg,#f0f9ff,#e0f2fe); border-color:#7dd3fc; }
.sc-sky    .icon-w { background:#0ea5e9; color:white; } .sc-sky .bg-dot { background:#0ea5e9; } .sc-sky .s-val { color:#0284c7; } .sc-sky .s-lbl { color:#0369a1; } .sc-sky .s-sub { color:#0ea5e9; }
.sc-orange { background:linear-gradient(135deg,#fff7ed,#fef3c7); border-color:#fcd34d; }
.sc-orange .icon-w { background:#d97706; color:white; } .sc-orange .bg-dot { background:#d97706; } .sc-orange .s-val { color:#b45309; } .sc-orange .s-lbl { color:#92400e; } .sc-orange .s-sub { color:#d97706; }
</style>

<!-- Welcome Hero -->
<?php if ($user_role === 'developer'): ?>
<!-- ===== DEVELOPER HERO ===== -->
<div class="mb-4" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0f2027 100%);border-radius:16px;padding:1.75rem 2rem;color:white;position:relative;overflow:hidden;">
  <div style="position:absolute;top:-60px;right:-60px;width:220px;height:220px;background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%);border-radius:50%;"></div>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
    <div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span style="background:rgba(99,102,241,0.25);color:#a5b4fc;border:1px solid rgba(165,180,252,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;">
          <i class="fas fa-code me-1"></i> WQS Developer
        </span>
        <span style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.72rem;font-weight:600;">● Hired & Active</span>
      </div>
      <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.4rem;">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
      <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
        <?= $devTaskCounts['in_progress'] ?> task<?= $devTaskCounts['in_progress']!=1?'s':''?> in progress ·
        <?= $devTaskCounts['assigned'] ?> new ·
        <strong style="color:#86efac;">₦<?= number_format($devEarnings, 0) ?></strong> earned
      </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="developer_hub.php" class="btn" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:white;border-radius:8px;font-size:0.85rem;">
        <i class="fas fa-tasks me-1"></i> My Tasks
      </a>
      <a href="client-request.php" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.85rem;">
        <i class="fas fa-plus me-1"></i> New Request
      </a>
    </div>
  </div>
</div>
<?php elseif ($user_role === 'agent'): ?>
<!-- Calculate partner-specific variables -->
<?php
  // Referral link
  $dashRefCode = '';
  try {
      $codeChk = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
      $codeChk->execute([$user_id]);
      $codeRow = $codeChk->fetch(PDO::FETCH_ASSOC);
      if ($codeRow && !empty($codeRow['referral_code'])) $dashRefCode = $codeRow['referral_code'];
  } catch (Exception $e) {}

  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "https";
  $host = $_SERVER['HTTP_HOST'];
  $dashReferralLink = $protocol . '://' . $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/register.php?ref=' . urlencode($dashRefCode);

  // Tier progress calculations
  if ($tierName === 'Gold') {
      $tierProgress = 100;
      $tierProgressLabel = '🥇 Max Tier Reached';
  } elseif ($tierName === 'Silver') {
      $tierProgress = round((($successfulProjectsCount - 3) / 3) * 100);
      $tierProgressLabel = $projectsNeeded . ' more project' . ($projectsNeeded !== 1 ? 's' : '') . ' to Gold';
  } else {
      $tierProgress = round(($successfulProjectsCount / 3) * 100);
      $tierProgressLabel = $projectsNeeded . ' more project' . ($projectsNeeded !== 1 ? 's' : '') . ' to Silver';
  }

  // Pending earnings calculations
  $pendingStmt = $db->prepare("
      SELECT SUM(op.final_budget) AS sum_usd, SUM(op.budget) AS sum_ngn
      FROM ongoing_projects op INNER JOIN users u ON op.user_id = u.id
      WHERE u.referred_by = ? AND op.status IN ('ongoing','on-hold')
  ");
  $pendingStmt->bind_param('i', $user_id);
  $pendingStmt->execute();
  $pendRes = $pendingStmt->get_result()->fetch_assoc();
  $pendingUSD = (float)($pendRes['sum_usd'] ?? 0) * $commissionRate;
  $pendingNGN = (float)($pendRes['sum_ngn'] ?? 0) * $commissionRate;
  $pendingStmt->close();
?>

<!-- Welcome Hero -->
<div class="user-hero mb-4" style="background: linear-gradient(135deg, #0A2D5E 0%, #1e3a5f 100%);">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
    <div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <?php
          $tierIcon = ($tierName==='Gold') ? '🥇' : (($tierName==='Silver') ? '🥈' : '🥉');
          $tierBadgeStyle = ($tierName==='Gold') ? 'background:rgba(251,191,36,0.2);color:#fbbf24;border:1px solid rgba(251,191,36,0.3);' : (($tierName==='Silver') ? 'background:rgba(203,213,225,0.2);color:#cbd5e1;border:1px solid rgba(203,213,225,0.3);' : 'background:rgba(251,191,36,0.15);color:#fcd34d;border:1px solid rgba(252,211,77,0.3);');
        ?>
        <span style="<?= $tierBadgeStyle ?> padding: 0.25rem 0.75rem; border-radius:50px; font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;"><?= $tierIcon ?> <?= $tierName ?> Partner</span>
        <span style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.72rem;font-weight:600;">● Active Status</span>
      </div>
      <h1 style="font-size:1.6rem;font-weight:800;color:white;margin-bottom:0.4rem;">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
      <p style="color:rgba(255,255,255,0.65);font-size:0.88rem;margin:0;">
        Earning <strong style="color:#ffb380;"><?= round($commissionRate*100) ?>% commission</strong> · <?= $referredCount ?> referred client<?= $referredCount !== 1 ? 's' : '' ?> · <?= $successfulProjectsCount ?> successful project<?= $successfulProjectsCount !== 1 ? 's' : '' ?>
      </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="referral_portal.php" class="btn btn-theme-secondary text-white" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:8px;font-size:0.85rem;font-weight:600;"><i class="fas fa-chart-bar me-1"></i> Referral Portal</a>
      <a href="client-request.php" class="btn btn-theme" style="background:var(--color-accent);border:none;color:white;border-radius:8px;font-size:0.85rem;font-weight:600;"><i class="fas fa-plus-circle me-1"></i> Request Project</a>
    </div>
  </div>
</div>

<!-- Partner Command Center Row -->
<div class="row g-4 mb-4">
  <!-- PANEL 1: Tier & Progress -->
  <div class="col-12 col-lg-4 col-md-6">
    <div class="card-theme h-100 d-flex flex-column">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-trophy me-2 text-warning"></i>Your Partner Tier</h5>
        <a href="upgrade_partner.php" style="font-size:0.72rem;color:var(--color-text-light);text-decoration:none;"><i class="fas fa-info-circle me-1"></i>Tiers Info</a>
      </div>
      <div class="card-theme-body d-flex flex-column justify-content-between flex-grow-1" style="min-height: 250px;">
        <div class="text-center mb-3">
          <?php if($tierName==='Gold'): ?>
            <div style="font-size:3rem;line-height:1;margin-bottom:0.25rem;">🥇</div>
            <div class="fw-bold text-warning" style="font-size:1.15rem;">Gold Partner</div>
          <?php elseif($tierName==='Silver'): ?>
            <div style="font-size:3rem;line-height:1;margin-bottom:0.25rem;">🥈</div>
            <div class="fw-bold text-secondary" style="font-size:1.15rem;">Silver Partner</div>
          <?php else: ?>
            <div style="font-size:3rem;line-height:1;margin-bottom:0.25rem;">🥉</div>
            <div class="fw-bold" style="color:#d97706;font-size:1.15rem;">Bronze Partner</div>
          <?php endif; ?>
          <div class="mt-2" style="font-size:0.78rem;color:var(--color-text-light);">Commission Rate</div>
          <div style="font-size:2.2rem;font-weight:900;color:var(--color-primary);line-height:1.1;"><?= round($commissionRate*100) ?>%</div>
        </div>

        <div>
          <?php if ($tierName !== 'Gold'): ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-1" style="font-size:0.72rem;">
              <span class="text-muted"><?= $tierName ?></span>
              <span class="text-muted"><?= $nextTierName ?></span>
            </div>
            <div class="tier-progress-bar" style="height: 6px; background: var(--color-border); border-radius: 3px; overflow: hidden;">
              <div class="tier-progress-fill" style="width:<?= $tierProgress ?>%; height: 100%; background:<?= $tierName==='Silver' ? 'linear-gradient(90deg,#64748b,#94a3b8)' : 'linear-gradient(90deg,#d97706,#f59e0b)' ?>; border-radius: 3px;"></div>
            </div>
            <div class="text-muted mt-1 small" style="font-size:0.7rem;"><i class="fas fa-info-circle me-1"></i><?= $tierProgressLabel ?></div>
          </div>
          <?php else: ?>
          <div class="text-center mb-3 text-success small" style="font-size:0.75rem;"><i class="fas fa-check-circle me-1"></i>Maximum tier reached!</div>
          <?php endif; ?>

          <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:1px dashed var(--color-border);">
            <span class="text-muted small">Need an upgrade?</span>
            <a href="upgrade_partner_request.php" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-2" style="font-size: 0.72rem; font-weight: 600;"><i class="fas fa-arrow-circle-up me-1"></i>Upgrade Partnership</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PANEL 2: Referral Link -->
  <div class="col-12 col-lg-4 col-md-6">
    <div class="card-theme h-100 d-flex flex-column">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-link me-2 text-primary"></i>Referral Links</h5>
        <span class="badge" style="background:rgba(16,185,129,0.15);color:#10b981;font-size:0.7rem;">● Active</span>
      </div>
      <div class="card-theme-body d-flex flex-column justify-content-between flex-grow-1" style="min-height: 250px;">
        <div>
          <p class="text-muted mb-2 small" style="font-size:0.8rem; line-height: 1.4;">
            Share this link to earn commission. Referred clients get a <strong>5% discount</strong> on their first project!
          </p>

          <div class="p-2 mb-2 rounded-3 bg-light border d-flex align-items-center justify-content-between gap-2" style="font-family:monospace; font-size: 0.75rem; word-break: break-all;">
            <span id="dash-ref-url-text" class="text-truncate"><?= htmlspecialchars($dashReferralLink) ?></span>
            <input type="hidden" id="dash-ref-url" value="<?= htmlspecialchars($dashReferralLink) ?>">
            <button class="btn btn-xs btn-primary py-1 px-2 rounded-2 text-white" id="dash-copy-btn" onclick="copyDashReferralLink()" style="flex-shrink:0; font-size:0.7rem; background: var(--color-primary); border: none;">
              <i class="far fa-copy"></i>
            </button>
          </div>

          <div class="d-flex align-items-center justify-content-between mt-2 pt-2" style="border-top:1px dashed var(--color-border);">
            <span class="text-muted small">Referral Code:</span>
            <div class="d-flex align-items-center gap-2">
              <strong class="text-body" id="dash-ref-code-txt" style="font-family: monospace; background: var(--color-bg); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--color-border); font-size:0.8rem;"><?= htmlspecialchars($dashRefCode) ?></strong>
              <button class="btn btn-xs py-0 px-1 rounded text-primary" id="dash-code-copy-btn" onclick="copyDashReferralCode()" style="background:transparent; border:none; font-size:0.85rem;" title="Copy Code">
                <i class="far fa-copy"></i>
              </button>
            </div>
          </div>
        </div>

        <div>
          <!-- QR Code Display Toggler Inline -->
          <div id="qr-container-inline" class="text-center mb-2" style="display:none; transition: all 0.3s ease;">
            <div class="p-2 border rounded-3 bg-white d-inline-block shadow-sm">
              <img id="qr-img-inline" src="" width="110" height="110" alt="QR Code">
            </div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary w-50 py-1.5" onclick="toggleQRInline('<?= htmlspecialchars($dashReferralLink) ?>')" style="font-size:0.75rem; font-weight:600;">
              <i class="fas fa-qrcode me-1"></i> QR Code
            </button>
            <a href="https://wa.me/?text=<?= urlencode('Get custom premium software built by WQS. Sign up using my referral link for a 5% discount: ' . $dashReferralLink) ?>" target="_blank" class="btn btn-sm btn-outline-success w-50 py-1.5" style="font-size:0.75rem; font-weight:600;">
              <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PANEL 3: Earnings Ledger -->
  <div class="col-12 col-lg-4 col-md-12">
    <div class="card-theme h-100 d-flex flex-column">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-wallet me-2 text-success"></i>Earnings Summary</h5>
        <a href="referral_portal.php" style="font-size:0.72rem;color:var(--color-text-light);text-decoration:none;"><i class="fas fa-file-invoice-dollar me-1"></i>Portal</a>
      </div>
      <div class="card-theme-body d-flex flex-column justify-content-between flex-grow-1" style="min-height: 250px;">
        <div class="d-flex flex-column gap-2">
          <!-- Confirmed Earnings -->
          <div class="p-2.5 rounded-3 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; padding: 0.75rem 1rem;">
            <div>
              <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.04em; color:#15803d; font-weight:700; margin-bottom: 0.1rem;"><i class="fas fa-check-circle me-1"></i>Total Earned</div>
              <div style="font-size: 1.55rem; font-weight: 900; color: #166534; line-height: 1.1;">₦<?= number_format($agentEarningsNGN, 2) ?></div>
            </div>
            <div class="text-end text-success" style="font-size: 1.4rem;"><i class="fas fa-coins"></i></div>
          </div>

          <!-- Pending Earnings -->
          <div class="p-2.5 rounded-3 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fcd34d; padding: 0.75rem 1rem;">
            <div>
              <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.04em; color:#92400e; font-weight:700; margin-bottom: 0.1rem;"><i class="fas fa-hourglass-half me-1"></i>Pending Commission</div>
              <div style="font-size: 1.55rem; font-weight: 900; color: #78350f; line-height: 1.1;">₦<?= number_format($pendingNGN, 2) ?></div>
            </div>
            <div class="text-end text-warning" style="font-size: 1.4rem;"><i class="fas fa-clock"></i></div>
          </div>
        </div>

        <div class="tip-card mt-3" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fcd34d; border-radius: 8px; padding: 0.65rem 0.85rem; display: flex; gap: 0.5rem; align-items: flex-start;">
          <i class="fas fa-lightbulb" style="color:#f59e0b; margin-top:2px; flex-shrink:0; font-size:0.8rem;"></i>
          <div style="font-size:0.75rem; color:#78350f; line-height: 1.3;">
            <?php if ($tierName !== 'Gold'): ?>
              Reach <strong><?= $nextTierName ?> tier</strong> with <?= $projectsNeeded ?> more project<?= $projectsNeeded!==1?'s':''?> to earn <strong><?= $tierName==='Bronze' ? '12%' : '15%'?></strong> commission!
            <?php else: ?>
              🎉 You're at the highest tier! Keep referring to maximize your <?= round($commissionRate*100) ?>% commission.
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Inline QR script & styling -->
<script>
function toggleQRInline(link) {
  const container = document.getElementById('qr-container-inline');
  const img = document.getElementById('qr-img-inline');
  if (container.style.display === 'none') {
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(link);
    container.style.display = 'block';
  } else {
    container.style.display = 'none';
  }
}
function copyDashReferralLink() {
    const val = document.getElementById('dash-ref-url').value;
    const btn = document.getElementById('dash-copy-btn');
    navigator.clipboard.writeText(val).then(function() {
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = '<i class="far fa-copy"></i>';
            btn.classList.remove('copied');
        }, 2500);
    }).catch(function() {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = val; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => btn.innerHTML = '<i class="far fa-copy"></i>', 2500);
    });
}

function copyDashReferralCode() {
    const val = document.getElementById('dash-ref-code-txt').innerText;
    const btn = document.getElementById('dash-code-copy-btn');
    navigator.clipboard.writeText(val).then(function() {
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(function() {
            btn.innerHTML = '<i class="far fa-copy"></i>';
        }, 2500);
    }).catch(function() {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = val; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = '<i class="far fa-copy"></i>', 2500);
    });
}
</script>

<!-- Second Row: Tables & Personal Records -->
<div class="row g-4 mb-4">
  <!-- Left Panel: Recent Referred Clients -->
  <div class="col-12 col-lg-7">
    <div class="card-theme h-100">
      <div class="card-theme-header d-flex justify-content-between align-items-center">
        <h5 class="card-theme-title text-body"><i class="fas fa-users me-2 text-info"></i>Recent Referred Clients</h5>
        <a href="referral_portal.php" class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-2" style="font-size:0.75rem;"><i class="fas fa-list me-1"></i>View All</a>
      </div>
      <div class="card-theme-body p-0">
        <?php if (!empty($recentReferrals)): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
            <thead style="background:var(--color-bg); border-bottom:1px solid var(--color-border);">
              <tr>
                <th class="ps-4 py-2.5 text-muted fw-semibold" style="font-size:0.72rem; text-transform:uppercase;">Client</th>
                <th class="py-2.5 text-muted fw-semibold" style="font-size:0.72rem; text-transform:uppercase;">Joined</th>
                <th class="pe-4 py-2.5 text-muted fw-semibold text-end" style="font-size:0.72rem; text-transform:uppercase;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentReferrals as $ref): ?>
              <tr>
                <td class="ps-4 py-2.5">
                  <div class="d-flex align-items-center gap-2.5">
                    <div class="ref-avatar" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), #1a5db5); display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;"><?= strtoupper(substr($ref['name'], 0, 1)) ?></div>
                    <div>
                      <div class="fw-semibold text-body" style="font-size:0.85rem;"><?= htmlspecialchars($ref['name']) ?></div>
                      <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($ref['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="py-2.5 text-muted" style="font-size:0.8rem;"><?= date('M d, Y', strtotime($ref['created_at'])) ?></td>
                <td class="pe-4 py-2.5 text-end">
                  <span class="badge" style="background:#dcfce7; color:#15803d; font-size:0.7rem; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 50px;">● Registered</span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
          <div style="font-size:2.2rem; margin-bottom:0.5rem; opacity: 0.7;">🔗</div>
          <h6 class="fw-semibold text-body" style="font-size:0.9rem;">No referrals yet</h6>
          <p class="small mb-3 text-muted">Copy your referral link above and share it to start earning!</p>
          <button onclick="copyDashReferralLink()" class="btn btn-sm btn-primary" style="background:var(--color-primary); border:none;"><i class="fas fa-copy me-1"></i>Copy Link</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right Panel: Personal Project & Invoice Tabs -->
  <div class="col-12 col-lg-5">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <ul class="nav nav-tabs card-header-tabs border-bottom-0" id="partnerWidgetTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold border-0 px-3 py-1 text-primary" id="my-projects-tab" data-bs-toggle="tab" data-bs-target="#my-projects" type="button" role="tab" aria-controls="my-projects" aria-selected="true" style="font-size: 0.82rem; background: transparent;">
              <i class="fas fa-rocket me-1.5"></i>My Projects
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold border-0 px-3 py-1 text-muted" id="my-billing-tab" data-bs-toggle="tab" data-bs-target="#my-billing" type="button" role="tab" aria-controls="my-billing" aria-selected="false" style="font-size: 0.82rem; background: transparent;">
              <i class="fas fa-receipt me-1.5"></i>My Invoices
            </button>
          </li>
        </ul>
      </div>
      <div class="card-theme-body tab-content" id="partnerWidgetTabContent">
        <!-- Tab 1: My Projects -->
        <div class="tab-pane fade show active" id="my-projects" role="tabpanel" aria-labelledby="my-projects-tab">
          <?php if (!empty($myOngoingProjects)): ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($myOngoingProjects as $op): ?>
                <div class="pb-2 border-bottom last-border-0" style="border-bottom: 1px solid var(--color-border) !important;">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold text-body" style="font-size:0.85rem;"><?= htmlspecialchars($op['title']) ?></span>
                    <span class="badge bg-primary-subtle text-primary" style="font-size: 0.7rem; font-weight: 700;"><?= (int)$op['progress'] ?>%</span>
                  </div>
                  <div class="progress" style="height: 5px; border-radius: 3px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (int)$op['progress'] ?>%; border-radius: 3px;"></div>
                  </div>
                  <?php if (!empty($op['manager_name'])): ?>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <span class="text-muted" style="font-size:0.7rem;"><i class="fas fa-user-shield me-1"></i>PM: <?= htmlspecialchars($op['manager_name']) ?></span>
                      <a href="mailto:<?= htmlspecialchars($op['manager_email']) ?>" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-2" style="font-size: 0.65rem; font-weight: 600;"><i class="fas fa-envelope me-1"></i>Contact</a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4 text-muted">
              <i class="fas fa-folder-open fa-xl mb-2 text-secondary" style="opacity: 0.6;"></i>
              <p class="mb-0 small">No personal projects found.</p>
              <a href="client-request.php" class="btn btn-xs btn-primary mt-2" style="background:var(--color-primary); border:none; font-size:0.7rem;"><i class="fas fa-plus me-1"></i>Request Project</a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Tab 2: My Invoices -->
        <div class="tab-pane fade" id="my-billing" role="tabpanel" aria-labelledby="my-billing-tab">
          <?php
          $billingHistory = [];
          $bQuery = $db->prepare("SELECT invoice_number, amount, status, currency, created_at FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
          $bQuery->bind_param("i", $user_id);
          $bQuery->execute();
          $bRes = $bQuery->get_result();
          if ($bRes) {
              while ($br = $bRes->fetch_assoc()) {
                  $billingHistory[] = $br;
              }
          }
          $bQuery->close();
          
          if (!empty($billingHistory)):
          ?>
            <div class="d-flex flex-column gap-2.5">
              <?php foreach ($billingHistory as $bh): 
                $isPaid = strtolower($bh['status']) === 'paid';
              ?>
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom last-border-0" style="border-bottom: 1px solid var(--color-border) !important;">
                  <div>
                    <div class="fw-semibold text-body" style="font-size:0.82rem;"><?= htmlspecialchars($bh['invoice_number']) ?></div>
                    <div class="text-muted" style="font-size:0.7rem;"><?= date('M d, Y', strtotime($bh['created_at'])) ?></div>
                  </div>
                  <div class="text-end">
                    <div class="fw-bold <?= $isPaid ? 'text-success' : 'text-danger' ?>" style="font-size:0.85rem;">
                      <?= htmlspecialchars($bh['currency']) . number_format($bh['amount'], 2) ?>
                    </div>
                    <span class="badge rounded-pill" style="font-size: 0.62rem; font-weight: 700; background: <?= $isPaid ? '#dcfce7; color:#15803d;' : '#fee2e2; color:#991b1b;' ?>">
                      <?= strtoupper($bh['status']) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4 text-muted">
              <i class="fas fa-file-invoice fa-xl mb-2 text-secondary" style="opacity: 0.6;"></i>
              <p class="mb-0 small">No billing records found.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Style active tab link dynamically
document.querySelectorAll('#partnerWidgetTab button').forEach(button => {
  button.addEventListener('shown.bs.tab', event => {
    document.querySelectorAll('#partnerWidgetTab button').forEach(btn => {
      btn.classList.remove('text-primary');
      btn.classList.add('text-muted');
    });
    event.target.classList.remove('text-muted');
    event.target.classList.add('text-primary');
  });
});
</script>
<?php else: ?>
<?php
// Fetch notifications for client
$notifStmt = $db->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->bind_param("i", $user_id);
$notifStmt->execute();
$notifRes = $notifStmt->get_result();
$notifications = [];
if ($notifRes) { while ($nr = $notifRes->fetch_assoc()) $notifications[] = $nr; }
$notifStmt->close();
$unreadNotifs = 0;
foreach ($notifications as $n) { if (!$n['is_read']) $unreadNotifs++; }

// Fetch invoice breakdown
$paidInvoiceSum = 0; $paidInvoiceCount = 0;
$paidInvStmt = $db->prepare("SELECT COUNT(*), IFNULL(SUM(amount), 0) FROM invoices WHERE user_id = ? AND status = 'paid'");
$paidInvStmt->bind_param("i", $user_id);
$paidInvStmt->execute();
$paidInvStmt->bind_result($paidInvoiceCount, $paidInvoiceSum);
$paidInvStmt->fetch();
$paidInvStmt->close();

// Fetch latest 5 invoices
$recentInvoices = [];
$invListStmt = $db->prepare("SELECT invoice_number, amount, currency, status, due_date, created_at FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$invListStmt->bind_param("i", $user_id);
$invListStmt->execute();
$invListRes = $invListStmt->get_result();
if ($invListRes) { while ($ir = $invListRes->fetch_assoc()) $recentInvoices[] = $ir; }
$invListStmt->close();

// Fetch all ongoing projects (not just 3)
$allOngoingProjects = [];
$allProjStmt = $db->prepare("
    SELECT op.*, u.name AS manager_name, u.email AS manager_email, u.picture AS manager_picture
    FROM ongoing_projects op 
    LEFT JOIN users u ON op.project_manager_id = u.id 
    WHERE op.user_id = ? AND op.status IN ('ongoing', 'on-hold')
    ORDER BY op.updated_at DESC LIMIT 5
");
$allProjStmt->bind_param("i", $user_id);
$allProjStmt->execute();
$allProjRes = $allProjStmt->get_result();
if ($allProjRes) { while ($pr = $allProjRes->fetch_assoc()) $allOngoingProjects[] = $pr; }
$allProjStmt->close();

// Fetch completed projects count for potential referral display
$completedProjStmt = $db->prepare("SELECT COUNT(*) FROM ongoing_projects WHERE user_id = ? AND status = 'completed'");
$completedProjStmt->bind_param("i", $user_id);
$completedProjStmt->execute();
$completedProjCount = 0;
$completedProjStmt->bind_result($completedProjCount);
$completedProjStmt->fetch();

$completedProjStmt->close();
?>

<!-- ===== CLIENT DASHBOARD STYLES ===== -->
<style>
.client-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #1a4480 50%, #1e3a5f 100%);
    border-radius: 20px; padding: 2rem 2.25rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.client-hero::before { content:''; position:absolute; top:-80px; right:-80px; width:280px; height:280px; background:radial-gradient(circle, rgba(225,85,1,0.12) 0%, transparent 70%); border-radius:50%; }
.client-hero::after { content:''; position:absolute; bottom:-40px; left:30%; width:200px; height:200px; background:radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%); border-radius:50%; }
.quick-action-card {
    background: white; border-radius: 14px; border: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem; text-decoration: none; color: inherit;
    transition: all 0.25s ease; display: flex; align-items: center; gap: 0.85rem;
}
.quick-action-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: #93c5fd; }
.quick-action-card .qa-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.quick-action-card .qa-title { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.1rem; }
.quick-action-card .qa-sub { font-size: 0.75rem; color: #6b7280; }
.client-stat-card {
    background: white; border-radius: 16px; border: 1.5px solid #e2e8f0;
    padding: 1.25rem 1.5rem; position: relative; overflow: hidden;
    transition: all 0.25s ease;
}
.client-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
.client-stat-card .cs-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; margin-bottom: 0.75rem; }
.client-stat-card .cs-val { font-size: 1.65rem; font-weight: 900; line-height: 1.1; margin-bottom: 0.15rem; }
.client-stat-card .cs-lbl { font-size: 0.8rem; font-weight: 600; color: #6b7280; }
.client-stat-card .cs-sub { font-size: 0.72rem; color: #9ca3af; margin-top: 0.15rem; }
.project-card {
    background: white; border-radius: 14px; border: 1.5px solid #e2e8f0;
    padding: 1.25rem; transition: all 0.25s ease; position: relative; overflow: hidden;
}
.project-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
.project-card .proj-status {
    position: absolute; top: 1rem; right: 1rem;
    padding: 0.2rem 0.65rem; border-radius: 50px;
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
}
.invoice-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;
}
.invoice-row:last-child { border-bottom: none; }
.notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; position: absolute; top: -2px; right: -2px; }
</style>

<!-- ===== PERSONALIZED HERO ===== -->
<div class="client-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-3">
            <?php $userPic = !empty($user['picture']) ? $user['picture'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>
            <img src="<?= htmlspecialchars($userPic) ?>" width="56" height="56" class="rounded-circle border" style="border:3px solid rgba(255,255,255,0.3);object-fit:cover;" alt="">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.2rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:600;">● Account Active</span>
                </div>
                <h1 style="font-size:1.45rem;font-weight:800;color:white;margin:0;">Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
                <p style="color:rgba(255,255,255,0.55);font-size:0.85rem;margin:0.2rem 0 0;">
                    <?= intval($projectCounts['total']) ?> project<?= intval($projectCounts['total']) != 1 ? 's' : '' ?> · <?= intval($requestCounts['pending']) ?> pending request<?= intval($requestCounts['pending']) != 1 ? 's' : '' ?>
                    <?php if ($formatted_login): ?> · Last login: <?= date('M j, g:i A', strtotime($user['last_login'])) ?><?php endif; ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="client-request.php" class="btn" style="background:#E15501;border:none;color:white;border-radius:10px;font-size:0.85rem;font-weight:600;padding:0.55rem 1.25rem;">
                <i class="fas fa-plus-circle me-1"></i> New Request
            </a>
            <a href="client-project.php" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:10px;font-size:0.85rem;font-weight:600;padding:0.55rem 1.25rem;">
                <i class="fas fa-briefcase me-1"></i> My Projects
            </a>
        </div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="client-request.php" class="quick-action-card">
            <div class="qa-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-paper-plane"></i></div>
            <div>
                <div class="qa-title">New Request</div>
                <div class="qa-sub">Submit a project</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="client-project.php" class="quick-action-card">
            <div class="qa-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-folder-open"></i></div>
            <div>
                <div class="qa-title">My Projects</div>
                <div class="qa-sub"><?= intval($projectCounts['ongoing']) ?> active</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="client-invoices.php" class="quick-action-card">
            <div class="qa-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <div class="qa-title">Invoices</div>
                <div class="qa-sub"><?= $unpaidInvoiceCount ?> unpaid</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="client-support.php" class="quick-action-card">
            <div class="qa-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-headset"></i></div>
            <div>
                <div class="qa-title">Support</div>
                <div class="qa-sub"><?= $activeTicketCount ?> open ticket<?= $activeTicketCount != 1 ? 's' : '' ?></div>
            </div>
        </a>
    </div>
</div>

<!-- ===== STATS CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="client-stat-card">
            <div class="cs-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-briefcase"></i></div>
            <div class="cs-val" style="color:#1d4ed8;"><?= intval($projectCounts['total']) ?></div>
            <div class="cs-lbl">Total Projects</div>
            <div class="cs-sub"><?= intval($projectCounts['ongoing']) ?> ongoing · <?= intval($projectCounts['completed']) ?> completed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="client-stat-card">
            <div class="cs-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-file-signature"></i></div>
            <div class="cs-val" style="color:#b45309;"><?= intval($requestCounts['total']) ?></div>
            <div class="cs-lbl">Requests</div>
            <div class="cs-sub"><?= intval($requestCounts['approved']) ?> approved · <?= intval($requestCounts['pending']) ?> pending</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="client-stat-card">
            <div class="cs-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-wallet"></i></div>
            <div class="cs-val" style="color:#15803d;">₦<?= number_format($unpaidInvoiceSum, 0) ?></div>
            <div class="cs-lbl">Unpaid</div>
            <div class="cs-sub"><?= $unpaidInvoiceCount ?> invoice<?= $unpaidInvoiceCount != 1 ? 's' : '' ?> due</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="client-stat-card">
            <div class="cs-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-headset"></i></div>
            <div class="cs-val" style="color:#6d28d9;"><?= $activeTicketCount ?></div>
            <div class="cs-lbl">Support Tickets</div>
            <div class="cs-sub">Active</div>
        </div>
    </div>
</div>

<!-- ===== ACTIVE PROJECTS ===== -->
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold text-body mb-0" style="font-size:1.05rem;"><i class="fas fa-rocket me-2" style="color:#2563eb;"></i>Active Projects</h5>
        <a href="client-project.php" class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.78rem;"><i class="fas fa-arrow-right me-1"></i> View All</a>
    </div>
    <?php if (!empty($allOngoingProjects)): ?>
    <div class="row g-3">
        <?php foreach ($allOngoingProjects as $op):
            $statusColors = ['ongoing' => ['bg' => '#dbeafe', 'fg' => '#1d4ed8', 'label' => 'Ongoing'], 'on-hold' => ['bg' => '#fef3c7', 'fg' => '#b45309', 'label' => 'On Hold'], 'completed' => ['bg' => '#dcfce7', 'fg' => '#15803d', 'label' => 'Completed']];
            $sc = $statusColors[$op['status']] ?? $statusColors['ongoing'];
            $progressColor = $op['progress'] >= 75 ? '#16a34a' : ($op['progress'] >= 40 ? '#2563eb' : '#d97706');
        ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="project-card h-100">
                <span class="proj-status" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;"><?= $sc['label'] ?></span>
                <h6 class="fw-bold text-body mb-2" style="font-size:0.95rem;padding-right:80px;"><?= htmlspecialchars($op['title']) ?></h6>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.75rem;color:#6b7280;">Progress</span>
                        <span style="font-size:0.75rem;font-weight:700;color:<?= $progressColor ?>;"><?= (int)$op['progress'] ?>%</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:3px;">
                        <div class="progress-bar" role="progressbar" style="width:<?= (int)$op['progress'] ?>%;background:<?= $progressColor ?>;border-radius:3px;"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($op['manager_name'])): ?>
                            <?php $mgrPic = !empty($op['manager_picture']) ? $op['manager_picture'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>
                            <img src="<?= htmlspecialchars($mgrPic) ?>" width="24" height="24" class="rounded-circle" style="object-fit:cover;" alt="">
                            <span style="font-size:0.75rem;color:#6b7280;"><?= htmlspecialchars($op['manager_name']) ?></span>
                        <?php else: ?>
                            <span style="font-size:0.75rem;color:#9ca3af;">No PM assigned</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($op['manager_email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($op['manager_email']) ?>" style="font-size:0.72rem;color:#2563eb;text-decoration:none;font-weight:600;"><i class="fas fa-envelope me-1"></i>Contact</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($op['end_date'])): ?>
                <div class="mt-2 pt-2" style="border-top:1px solid #f1f5f9;font-size:0.72rem;color:#9ca3af;">
                    <i class="fas fa-calendar me-1"></i> Due: <?= date('M j, Y', strtotime($op['end_date'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5 rounded-4" style="background:#f8fafc;border:2px dashed #e2e8f0;">
        <div style="font-size:2.5rem;margin-bottom:0.5rem;">📋</div>
        <h6 class="fw-bold text-body">No active projects yet</h6>
        <p class="text-muted small mb-3">Submit your first project request to get started!</p>
        <a href="client-request.php" class="btn btn-sm btn-primary rounded-pill"><i class="fas fa-plus me-1"></i> Submit Request</a>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MY SCHOLARSHIPS ===== -->
<?php if (!empty($myScholarshipApplications)): ?>
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold text-body mb-0" style="font-size:1.05rem;"><i class="fas fa-graduation-cap me-2" style="color:#0d47a1;"></i>My Scholarship Applications</h5>
        <span class="badge rounded-pill bg-primary" style="font-size:0.75rem;"><?= count($myScholarshipApplications) ?> Applied</span>
    </div>
    <div class="row g-3">
        <?php foreach ($myScholarshipApplications as $app):
            $statusColors = [
                'submitted' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'label' => 'Submitted'],
                'under_review' => ['bg' => '#fef3c7', 'fg' => '#b45309', 'label' => 'Under Review'],
                'shortlisted' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'label' => 'Shortlisted'],
                'approved' => ['bg' => '#dcfce7', 'fg' => '#15803d', 'label' => 'Approved / Awarded'],
                'rejected' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Rejected']
            ];
            $sc = $statusColors[$app['status']] ?? $statusColors['submitted'];
        ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="project-card h-100" style="border-left: 4px solid <?= $sc['fg'] ?>;">
                <span class="proj-status" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;"><?= $sc['label'] ?></span>
                <h6 class="fw-bold text-body mb-2" style="font-size:0.95rem;padding-right:85px;"><?= htmlspecialchars($app['scholarship_title']) ?></h6>
                <div style="font-size:0.75rem;color:#6b7280;margin-bottom:0.5rem;">
                    <strong>Application Code:</strong> <?= htmlspecialchars($app['application_code']) ?>
                </div>
                <?php if (!empty($app['cgpa'])): ?>
                <div style="font-size:0.75rem;color:#6b7280;margin-bottom:0.5rem;">
                    <strong>Academic CGPA:</strong> <?= htmlspecialchars($app['cgpa']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($app['admin_notes'])): ?>
                <div class="mt-2 p-2 bg-light rounded" style="font-size:0.75rem;color:#4b5563;border-left:2px solid #cbd5e1;">
                    <i class="fas fa-comment-alt me-1 text-muted"></i><strong>WQS Update:</strong> <?= htmlspecialchars($app['admin_notes']) ?>
                </div>
                <?php endif; ?>
                <div class="mt-3 pt-2 d-flex justify-content-between align-items-center" style="border-top:1px solid #f1f5f9;font-size:0.72rem;color:#9ca3af;">
                    <span><i class="fas fa-calendar-alt me-1"></i>Applied: <?= date('M j, Y', strtotime($app['submitted_at'])) ?></span>
                    <?php if (!empty($app['closing_date'])): ?>
                    <span>Deadline: <?= date('M j', strtotime($app['closing_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== BILLING & INVOICES ===== -->
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-7">
        <div class="card-theme h-100">
            <div class="card-theme-header d-flex justify-content-between align-items-center">
                <h5 class="card-theme-title text-body" style="font-size:1rem;"><i class="fas fa-receipt me-2" style="color:#16a34a;"></i>Recent Invoices</h5>
                <a href="client-invoices.php" class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.75rem;">View All</a>
            </div>
            <div class="card-theme-body p-0 px-4 pb-3">
                <?php if (!empty($recentInvoices)): ?>
                    <?php foreach ($recentInvoices as $inv):
                        $isPaid = strtolower($inv['status']) === 'paid';
                        $isOverdue = !$isPaid && !empty($inv['due_date']) && strtotime($inv['due_date']) < time();
                    ?>
                    <div class="invoice-row">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:38px;height:38px;border-radius:10px;background:<?= $isPaid ? '#f0fdf4' : ($isOverdue ? '#fef2f2' : '#eff6ff') ?>;display:flex;align-items:center;justify-content:center;">
                                <i class="fas <?= $isPaid ? 'fa-check-circle' : ($isOverdue ? 'fa-exclamation-circle' : 'fa-clock') ?>" style="color:<?= $isPaid ? '#16a34a' : ($isOverdue ? '#dc2626' : '#2563eb') ?>;font-size:0.95rem;"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size:0.88rem;"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                                <div style="font-size:0.72rem;color:#9ca3af;"><?= date('M d, Y', strtotime($inv['created_at'])) ?><?php if (!empty($inv['due_date'])): ?> · Due <?= date('M d', strtotime($inv['due_date'])) ?><?php endif; ?></div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold" style="font-size:0.92rem;color:<?= $isPaid ? '#16a34a' : '#111827' ?>;">
                                <?= htmlspecialchars($inv['currency']) ?><?= number_format($inv['amount'], 2) ?>
                            </div>
                            <span style="font-size:0.65rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:50px;background:<?= $isPaid ? '#dcfce7;color:#15803d' : ($isOverdue ? '#fef2f2;color:#991b1b' : '#eff6ff;color:#1d4ed8') ?>;">
                                <?= $isPaid ? 'PAID' : ($isOverdue ? 'OVERDUE' : 'DUE') ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="fas fa-file-invoice fa-xl mb-2" style="opacity:0.3;"></i>
                        <p class="mb-0">No invoices yet. They'll appear here once your project is approved.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== SUPPORT & HELP ===== -->
    <div class="col-12 col-lg-5">
        <div class="card-theme h-100">
            <div class="card-theme-header d-flex justify-content-between align-items-center">
                <h5 class="card-theme-title text-body" style="font-size:1rem;"><i class="fas fa-headset me-2" style="color:#7c3aed;"></i>Support Center</h5>
                <a href="client-support.php" class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.75rem;">Open</a>
            </div>
            <div class="card-theme-body">
                <?php if (!empty($activeTickets)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($activeTickets as $ticket):
                            $badgeStyles = [
                                'open' => 'background:#dbeafe;color:#1d4ed8;',
                                'pending' => 'background:#fef3c7;color:#b45309;',
                                'waiting' => 'background:#fef3c7;color:#b45309;',
                                'resolved' => 'background:#dcfce7;color:#15803d;',
                            ];
                            $bs = $badgeStyles[strtolower($ticket['status'])] ?? 'background:#f3f4f6;color:#6b7280;';
                        ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background:#f8fafc;">
                            <div>
                                <div class="fw-semibold text-body" style="font-size:0.85rem;"><?= htmlspecialchars($ticket['subject']) ?></div>
                                <div style="font-size:0.72rem;color:#9ca3af;"><?= date('M d, Y', strtotime($ticket['updated_at'])) ?></div>
                            </div>
                            <span style="<?= $bs ?>font-size:0.65rem;font-weight:700;padding:0.2rem 0.55rem;border-radius:50px;text-transform:uppercase;"><?= htmlspecialchars($ticket['status']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <div style="font-size:2rem;margin-bottom:0.4rem;">🎧</div>
                        <p class="text-muted small mb-2">No open tickets</p>
                        <a href="client-support.php" class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.78rem;">Get Help</a>
                    </div>
                <?php endif; ?>

                <!-- Quick Help Links -->
                <div class="mt-3 pt-3" style="border-top:1px solid #f1f5f9;">
                    <div style="font-size:0.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Quick Help</div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="client-support.php" class="btn btn-sm rounded-pill" style="background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;font-size:0.75rem;"><i class="fas fa-comments me-1"></i> Chat Support</a>
                        <a href="client-contracts.php" class="btn btn-sm rounded-pill" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;font-size:0.75rem;"><i class="fas fa-file-contract me-1"></i> Contracts</a>
                        <a href="client-request.php" class="btn btn-sm rounded-pill" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:0.75rem;"><i class="fas fa-plus me-1"></i> New Request</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== REFERRAL CTA (for non-agents) ===== -->
<?php if ($user_role !== 'agent' && $user_role !== 'developer'): ?>
<?php
// Check for pending partner application
$partnerAppStatus = null;
$partnerAppId = null;
try {
    $paStmt = $pdo->prepare("SELECT status, application_id, created_at FROM agent_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $paStmt->execute([$user_id]);
    $partnerAppStatus = $paStmt->fetch(PDO::FETCH_ASSOC);
    if ($partnerAppStatus) $partnerAppId = $partnerAppStatus['application_id'];
} catch (Exception $e) {}
?>
<div class="mb-4">
    <div class="rounded-4 p-4" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;position:relative;overflow:hidden;">
        <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;background:radial-gradient(circle,rgba(37,99,235,0.08) 0%,transparent 70%);border-radius:50%;"></div>
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3" style="position:relative;z-index:1;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#2563eb,#3b82f6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-handshake" style="color:white;font-size:1.3rem;"></i>
            </div>
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1" style="color:#1e40af;font-size:1rem;">Earn While You Refer!</h6>
                <?php if ($partnerAppStatus && $partnerAppStatus['status'] === 'pending'): ?>
                    <p class="mb-1" style="font-size:0.85rem;color:#1e3a5f;">Your partner application is <strong style="color:#d97706;">Under Review</strong>. We'll notify you once a decision is made.</p>
                    <?php if ($partnerAppId): ?>
                        <p class="mb-0" style="font-size:0.78rem;color:#6b7280;">Application ID: <code style="background:white;padding:2px 8px;border-radius:6px;border:1px solid #e5e7eb;"><?= htmlspecialchars($partnerAppId) ?></code></p>
                    <?php endif; ?>
                <?php elseif ($partnerAppStatus && $partnerAppStatus['status'] === 'rejected'): ?>
                    <p class="mb-1" style="font-size:0.85rem;color:#991b1b;">Your application was not approved. You can reapply anytime.</p>
                    <a href="upgrade_partner.php" style="font-size:0.8rem;color:#2563eb;font-weight:600;">Re-apply →</a>
                <?php else: ?>
                    <p class="mb-1" style="font-size:0.85rem;color:#1e3a5f;">Join our Partner Program — earn <strong>10–30% commission</strong> for every client you refer, and they get a <strong>5% discount</strong> too!</p>
                    <?php if ($clientRefCode): ?>
                        <p class="mb-0" style="font-size:0.8rem;color:#3b82f6;">Your referral code: <strong style="font-family:monospace;background:white;padding:2px 8px;border-radius:6px;border:1px solid #93c5fd;"><?= htmlspecialchars($clientRefCode) ?></strong></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!$partnerAppStatus || $partnerAppStatus['status'] === 'rejected'): ?>
            <a href="upgrade_partner.php" class="btn rounded-pill fw-semibold" style="background:#2563eb;color:white;border:none;font-size:0.85rem;padding:0.55rem 1.5rem;white-space:nowrap;">
                <i class="fas fa-rocket me-1"></i> Become a Partner
            </a>
            <?php elseif ($partnerAppStatus['status'] === 'pending'): ?>
            <a href="upgrade_partner.php" class="btn rounded-pill fw-semibold" style="background:#d97706;color:white;border:none;font-size:0.85rem;padding:0.55rem 1.5rem;white-space:nowrap;">
                <i class="fas fa-clock me-1"></i> View Status
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== NOTIFICATIONS ===== -->
<?php if (!empty($notifications)): ?>
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold text-body mb-0" style="font-size:1.05rem;">
            <i class="fas fa-bell me-2" style="color:#d97706;"></i>Recent Notifications
            <?php if ($unreadNotifs > 0): ?>
                <span style="background:#ef4444;color:white;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:50px;margin-left:0.35rem;"><?= $unreadNotifs ?></span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($notifications as $notif):
            $isUnread = !$notif['is_read'];
            $timeAgo = '';
            $diff = time() - strtotime($notif['created_at']);
            if ($diff < 60) $timeAgo = 'Just now';
            elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
            elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
            else $timeAgo = date('M d', strtotime($notif['created_at']));
        ?>
        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background:<?= $isUnread ? '#eff6ff' : 'white' ?>;border:1px solid <?= $isUnread ? '#bfdbfe' : '#f1f5f9' ?>;">
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $isUnread ? '#dbeafe' : '#f3f4f6' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas <?= $isUnread ? 'fa-bell' : 'fa-check' ?>" style="color:<?= $isUnread ? '#2563eb' : '#9ca3af' ?>;font-size:0.85rem;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold text-body" style="font-size:0.88rem;"><?= htmlspecialchars($notif['title']) ?></div>
                <div style="font-size:0.8rem;color:#6b7280;line-height:1.4;"><?= htmlspecialchars($notif['message']) ?></div>
            </div>
            <div style="font-size:0.7rem;color:#9ca3af;white-space:nowrap;flex-shrink:0;"><?= $timeAgo ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; /* end developer vs client stats */ ?>

<?php if ($user_role !== 'agent'): ?>
<!-- Charts -->

<div class="row g-4 mb-4">
  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-tasks text-primary me-2"></i>Active Project Progress</h5>
      </div>
      <div class="card-theme-body p-3">
        <?php if (!empty($myOngoingProjects)): ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($myOngoingProjects as $op): ?>
              <div class="border-bottom pb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <a href="client-project.php" class="fw-semibold text-body text-decoration-none hover-primary"><?= htmlspecialchars($op['title']) ?></a>
                  <span class="badge bg-primary-subtle text-primary"><?= (int)$op['progress'] ?>%</span>
                </div>
                <div class="progress mb-2" style="height: 6px;">
                  <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (int)$op['progress'] ?>%"></div>
                </div>
                <?php if (!empty($op['manager_name'])): ?>
                  <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="text-muted small"><i class="fas fa-user-shield me-1"></i> PM: <?= htmlspecialchars($op['manager_name']) ?></span>
                    <a href="mailto:<?= htmlspecialchars($op['manager_email']) ?>" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-2" style="font-size: 0.7rem;"><i class="fas fa-envelope me-1"></i>Contact Manager</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-folder-open fa-2xl mb-3 text-secondary"></i>
            <p class="mb-0 small">No active ongoing projects found.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-receipt text-success me-2"></i> Recent Billing Logs</h5>
      </div>
      <div class="card-theme-body p-3">
        <?php
        $billingHistory = [];
        $bQuery = $db->prepare("SELECT invoice_number, amount, status, currency, created_at FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
        $bQuery->bind_param("i", $user_id);
        $bQuery->execute();
        $bRes = $bQuery->get_result();
        if ($bRes) {
            while ($br = $bRes->fetch_assoc()) {
                $billingHistory[] = $br;
            }
        }
        $bQuery->close();
        
        if (!empty($billingHistory)):
        ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($billingHistory as $bh): 
              $isPaid = strtolower($bh['status']) === 'paid';
            ?>
              <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                <div>
                  <div class="fw-semibold text-body" style="font-size:0.85rem;"><?= htmlspecialchars($bh['invoice_number']) ?></div>
                  <div class="text-muted" style="font-size:0.72rem;"><?= date('M d, Y', strtotime($bh['created_at'])) ?></div>
                </div>
                <div class="text-end">
                  <div class="fw-bold <?= $isPaid ? 'text-success' : 'text-danger' ?>" style="font-size:0.88rem;">
                    <?= htmlspecialchars($bh['currency']) . number_format($bh['amount'], 2) ?>
                  </div>
                  <span class="badge rounded-pill" style="font-size: 0.65rem; background: <?= $isPaid ? '#dcfce7; color:#15803d;' : '#fee2e2; color:#991b1b;' ?>">
                    <?= strtoupper($bh['status']) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4 text-muted small">
            No billing records found.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
