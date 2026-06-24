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

$userId = $_SESSION['user']['id'];

$roleStmt = $pdo->prepare("SELECT role, name, email, picture FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userObj = $roleStmt->fetch(PDO::FETCH_ASSOC);
$user_role = $userObj ? strtolower($userObj['role']) : 'user';

if ($user_role !== 'agent') {
    header("Location: upgrade_partner.php");
    exit;
}

$page_title = "Partner Portal";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// ====== Commission Rate ======
$partnerCommissionPct = 10;
try {
    $partnerStmt = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
    $partnerStmt->execute([$userId]);
    $partnerRow = $partnerStmt->fetch(PDO::FETCH_ASSOC);
    if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
        $partnerCommissionPct = (float)$partnerRow['default_commission_percent'];
    } else {
        $settingStmt = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
        $settingRow = $settingStmt->fetch(PDO::FETCH_ASSOC);
        if ($settingRow) $partnerCommissionPct = (float)$settingRow['setting_value'];
    }
} catch (Exception $e) {}
$commissionRate = $partnerCommissionPct / 100;

// ====== Stats ======
// Total referrals
$refCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$refCountStmt->execute([$userId]);
$totalReferrals = $refCountStmt->fetchColumn();

// Completed projects
$completedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM ongoing_projects op 
    INNER JOIN users u ON op.user_id = u.id 
    WHERE u.referred_by = ? AND op.status = 'completed'
");
$completedStmt->execute([$userId]);
$successfulProjects = $completedStmt->fetchColumn();

// Real earnings (completed projects only, ₦ only)
$earningsStmt = $pdo->prepare("
    SELECT SUM(op.budget * ?) AS earnings_ngn
    FROM ongoing_projects op 
    INNER JOIN users u ON op.user_id = u.id 
    WHERE u.referred_by = ? AND op.status = 'completed'
");
$earningsStmt->execute([$commissionRate, $userId]);
$earningsRow = $earningsStmt->fetch(PDO::FETCH_ASSOC);
$earningsNGN = (float)($earningsRow['earnings_ngn'] ?? 0.0);

// Pending commission from active projects (for info only, not projected)
$pendingStmt = $pdo->prepare("
    SELECT SUM(op.budget * ?) AS pending_ngn
    FROM ongoing_projects op 
    INNER JOIN users u ON op.user_id = u.id 
    WHERE u.referred_by = ? AND op.status IN ('ongoing', 'on-hold')
");
$pendingStmt->execute([$commissionRate, $userId]);
$pendingRow = $pendingStmt->fetch(PDO::FETCH_ASSOC);
$pendingNGN = (float)($pendingRow['pending_ngn'] ?? 0.0);

// Total payouts received
$payoutsStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE user_id = ? AND status = 'approved'");
$payoutsStmt->execute([$userId]);
$totalPayouts = $payoutsStmt->fetchColumn();

// Available balance
$availableBalance = $earningsNGN - $totalPayouts;

// ====== Referrals with projects ======
$referralsQuery = "
    SELECT u.id AS client_id, u.name, u.email, u.phone, u.created_at AS join_date,
           op.id AS project_id, op.title AS project_title, op.budget AS project_budget,
           op.status AS project_status, op.progress AS project_progress
    FROM users u
    LEFT JOIN ongoing_projects op ON op.user_id = u.id
    WHERE u.referred_by = ?
    ORDER BY u.created_at DESC, op.created_at DESC
";
$refStmt = $pdo->prepare($referralsQuery);
$refStmt->execute([$userId]);
$rows = $refStmt->fetchAll(PDO::FETCH_ASSOC);

$clients = [];
foreach ($rows as $row) {
    $cId = $row['client_id'];
    if (!isset($clients[$cId])) {
        $clients[$cId] = [
            'id' => $cId,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'join_date' => $row['join_date'],
            'projects' => []
        ];
    }
    if ($row['project_id'] !== null) {
        $clients[$cId]['projects'][] = [
            'id' => $row['project_id'],
            'title' => $row['project_title'],
            'budget' => (float)$row['project_budget'],
            'status' => $row['project_status'],
            'progress' => (int)$row['project_progress']
        ];
    }
}

// Referral code & link
$userRefCode = '';
try {
    $codeStmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $codeStmt->execute([$userId]);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);
    if ($codeRow && !empty($codeRow['referral_code'])) {
        $userRefCode = $codeRow['referral_code'];
    }
} catch (Exception $e) {}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "https";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$referralLink = $protocol . '://' . $host . $basePath . '/register.php?ref=' . urlencode($userRefCode);
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($referralLink);
?>

<div id="alert-container"></div>

<style>
.ref-glass-card {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.ref-glass-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.1);
}
.ref-stat-card {
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ref-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 36px rgba(0,0,0,0.08);
}
.ref-stat-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 0.75rem;
}
.ref-hero-banner {
    background: linear-gradient(135deg, #0A2D5E 0%, #1e3a8a 50%, #1e40af 100%);
    border-radius: 20px;
    padding: 2rem 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
}
.ref-hero-banner::before {
    content: '';
    position: absolute; top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
}
.ref-hero-banner::after {
    content: '';
    position: absolute; bottom: -30%; left: -5%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(255,102,0,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.ref-hero-banner * { position: relative; z-index: 1; }
.ref-share-card {
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 6px 24px rgba(0,0,0,0.05);
}
.ref-link-input {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 0.85rem;
    color: #334155;
    width: 100%;
    transition: border-color 0.2s;
    outline: none;
}
.ref-link-input:focus { border-color: #3b82f6; }
.ref-code-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 2px solid #93c5fd;
    border-radius: 12px;
    padding: 0.6rem 1.2rem;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e40af;
    letter-spacing: 1px;
}
.ref-btn-copy {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.5rem;
}
.ref-btn-copy:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-1px); }
.ref-btn-copy.copied { background: linear-gradient(135deg, #10b981, #059669); }
.ref-qr-box {
    background: white;
    border-radius: 14px;
    padding: 12px;
    border: 2px solid #e2e8f0;
    display: inline-block;
}
.ref-qr-box img { border-radius: 8px; }
.ref-share-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; border-radius: 10px;
    font-size: 0.78rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s; border: none; cursor: pointer;
}
.ref-share-btn:hover { transform: translateY(-1px); }
.ref-project-badge {
    font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.6rem;
    border-radius: 6px; text-transform: uppercase; letter-spacing: 0.3px;
}
.ref-empty-state {
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(12px);
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
}
.ref-table-wrap {
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,0.04);
}
.ref-table-wrap .table { margin-bottom: 0; }
.ref-table-wrap thead th {
    background: rgba(248,250,252,0.9);
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    padding: 0.85rem 1rem;
}
.ref-table-wrap tbody td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.ref-table-wrap tbody tr:last-child td { border-bottom: none; }
.ref-table-wrap tbody tr:hover { background: rgba(59,130,246,0.03); }
@media (max-width: 767.98px) {
    .ref-hero-banner { padding: 1.5rem; }
    .ref-stat-card { padding: 1rem; }
    .ref-qr-box { display: none; }
}
</style>

<!-- Hero Banner -->
<div class="ref-hero-banner mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:8px;padding:4px 12px;font-size:0.72rem;font-weight:700;color:#ffb380;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.75rem;">
                <i class="fas fa-handshake"></i> Official Partner
            </div>
            <h4 class="fw-bold mb-1" style="color:white;font-family:'Plus Jakarta Sans',sans-serif;">
                Welcome back, <?= htmlspecialchars(explode(' ', $userObj['name'])[0]) ?>!
            </h4>
            <p class="mb-0 small" style="color:#cbd5e1;line-height:1.6;">
                Track your referrals, manage your earnings, and grow your partnership.
                Commission is earned on every completed project — no estimates, only real payouts.
            </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="partnership_letter.php" class="btn rounded-pill px-4 py-2.5 fw-bold shadow-sm" target="_blank" style="background:#ff6600;color:white;border:none;font-size:0.85rem;">
                <i class="fas fa-file-contract me-2"></i> Approval Letter
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="ref-stat-card">
            <div class="ref-stat-icon" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="fw-bold mb-0" style="color:#0A2D5E;"><?= number_format($totalReferrals) ?></h3>
            <span class="small text-muted">Total Referrals</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ref-stat-card">
            <div class="ref-stat-icon" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a;">
                <i class="fas fa-check-double"></i>
            </div>
            <h3 class="fw-bold mb-0" style="color:#0A2D5E;"><?= number_format($successfulProjects) ?></h3>
            <span class="small text-muted">Completed Projects</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ref-stat-card">
            <div class="ref-stat-icon" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a;">
                <i class="fas fa-wallet"></i>
            </div>
            <h3 class="fw-bold mb-0 text-success">₦<?= number_format($earningsNGN, 2) ?></h3>
            <span class="small text-muted">Total Earned</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ref-stat-card">
            <div class="ref-stat-icon" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <h3 class="fw-bold mb-0" style="color:#d97706;">₦<?= number_format($pendingNGN, 2) ?></h3>
            <span class="small text-muted">Pending Commission</span>
        </div>
    </div>
</div>

<!-- Referral Share Card -->
<div class="ref-share-card mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <h6 class="fw-bold text-body mb-1"><i class="fas fa-link me-2 text-primary"></i> Your Referral Link</h6>
            <p class="text-muted small mb-3">Share this link — new signups get <strong>5% off</strong>, and you earn <strong><?= $partnerCommissionPct ?>%</strong> commission on their completed projects.</p>
            <div class="d-flex gap-2 mb-3">
                <input type="text" id="ref-url-input" class="ref-link-input" value="<?= htmlspecialchars($referralLink) ?>" readonly>
                <button class="ref-btn-copy" id="copy-link-btn" onclick="copyLink()">
                    <i class="far fa-copy"></i> Copy
                </button>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div>
                    <span class="small text-muted d-block mb-1 fw-semibold">Your Referral Code</span>
                    <div class="ref-code-badge">
                        <span id="ref-code-txt"><?= htmlspecialchars($userRefCode) ?></span>
                        <button class="btn btn-sm p-0" style="color:#3b82f6;" onclick="copyCode()" title="Copy code">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-center">
            <div class="d-flex flex-column align-items-center gap-2">
                <span class="small text-muted fw-semibold">Scan to Register</span>
                <div class="ref-qr-box">
                    <img src="<?= $qrApiUrl ?>" alt="Referral QR Code" width="120" height="120" style="border-radius:8px;">
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2 flex-wrap">
        <a href="https://wa.me/?text=<?= urlencode("Join WQS through my referral link and get 5% off your first project!\n\n" . $referralLink) ?>" target="_blank" class="ref-share-btn" style="background:#25D366;color:white;">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode("Check out WQS — web development, AI, and more. Use my referral link for 5% off: " . $referralLink) ?>" target="_blank" class="ref-share-btn" style="background:#1DA1F2;color:white;">
            <i class="fab fa-twitter"></i> Twitter
        </a>
        <a href="mailto:?subject=<?= urlencode('Join WQS — 5% Discount Inside') ?>&body=<?= urlencode('Use my referral link to sign up and get 5% off your first project:\n\n' . $referralLink) ?>" class="ref-share-btn" style="background:#ea4335;color:white;">
            <i class="fas fa-envelope"></i> Email
        </a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($referralLink) ?>" target="_blank" class="ref-share-btn" style="background:#0077B5;color:white;">
            <i class="fab fa-linkedin-in"></i> LinkedIn
        </a>
    </div>
</div>

<!-- Commission Breakdown Card -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="ref-glass-card p-4">
            <h6 class="fw-bold text-body mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i> Commission Overview</h6>
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="p-3 rounded-3" style="background:#f0fdf4;">
                        <div class="small fw-bold text-success mb-1">Total Earned</div>
                        <div class="fw-bold fs-5" style="color:#16a34a;">₦<?= number_format($earningsNGN, 2) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 rounded-3" style="background:#eff6ff;">
                        <div class="small fw-bold text-primary mb-1">Total Paid Out</div>
                        <div class="fw-bold fs-5" style="color:#2563eb;">₦<?= number_format($totalPayouts, 2) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 rounded-3" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                        <div class="small fw-bold mb-1" style="color:#0A2D5E;">Available Balance</div>
                        <div class="fw-bold fs-5" style="color:#0A2D5E;">₦<?= number_format($availableBalance, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 p-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-info-circle text-warning"></i>
                    <span class="small text-muted">Commission rate: <strong><?= $partnerCommissionPct ?>%</strong> on completed project budget. Only finalized projects generate commission — no estimates or projections.</span>
                </div>
            </div>
            <div class="mt-3">
                <a href="payout_requests.php" class="btn rounded-pill px-4 py-2 fw-bold" style="background:linear-gradient(135deg,#0A2D5E,#1e40af);color:white;font-size:0.85rem;">
                    <i class="fas fa-hand-holding-usd me-2"></i> Request Payout
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ref-glass-card p-4 h-100 d-flex flex-column">
            <h6 class="fw-bold text-body mb-3"><i class="fas fa-trophy me-2 text-warning"></i> How It Works</h6>
            <div class="flex-grow-1">
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="fw-bold text-primary small">1</span>
                    </div>
                    <div>
                        <div class="small fw-bold text-body">Share Your Link</div>
                        <div class="small text-muted">Send to potential clients</div>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="fw-bold text-success small">2</span>
                    </div>
                    <div>
                        <div class="small fw-bold text-body">Client Signs Up</div>
                        <div class="small text-muted">They get 5% discount</div>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div style="width:32px;height:32px;border-radius:8px;background:#fffbeb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="fw-bold small" style="color:#d97706;">3</span>
                    </div>
                    <div>
                        <div class="small fw-bold text-body">Project Completed</div>
                        <div class="small text-muted">Admin marks as done</div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="fw-bold small" style="color:#16a34a;">4</span>
                    </div>
                    <div>
                        <div class="small fw-bold text-body">Commission Credited</div>
                        <div class="small text-muted"><?= $partnerCommissionPct ?>% of budget goes to you</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Referred Clients -->
<div class="ref-table-wrap mb-4">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center" style="background:rgba(248,250,252,0.5);">
        <h6 class="fw-bold text-body mb-0"><i class="fas fa-users me-2 text-primary"></i> Your Referred Clients</h6>
        <span class="badge rounded-pill" style="background:#eff6ff;color:#2563eb;font-size:0.75rem;"><?= count($clients) ?> total</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle" style="font-size:0.9rem;">
            <thead>
                <tr>
                    <th class="ps-4">Client</th>
                    <th>Joined</th>
                    <th>Projects</th>
                    <th>Commission Earned</th>
                    <th class="pe-4 text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($clients) > 0): ?>
                    <?php foreach ($clients as $client):
                        $projCount = count($client['projects']);
                        $completedProjCount = 0;
                        $clientEarnedNGN = 0;
                        foreach ($client['projects'] as $p) {
                            if ($p['status'] === 'completed') {
                                $completedProjCount++;
                                $clientEarnedNGN += ($p['budget'] * $commissionRate);
                            }
                        }
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <span class="fw-bold text-primary small"><?= strtoupper(substr($client['name'],0,1)) ?></span>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-body-emphasis"><?= htmlspecialchars($client['name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($client['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted"><?= date("d M Y", strtotime($client['join_date'])) ?></td>
                            <td>
                                <span class="badge rounded-pill" style="background:#f1f5f9;color:#475569;font-size:0.75rem;"><?= $projCount ?> project<?= $projCount !== 1 ? 's' : '' ?></span>
                            </td>
                            <td>
                                <?php if ($clientEarnedNGN > 0): ?>
                                    <span class="fw-bold text-success">₦<?= number_format($clientEarnedNGN, 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if ($completedProjCount > 0): ?>
                                    <span class="ref-project-badge" style="background:#dcfce7;color:#16a34a;">
                                        <i class="fas fa-check me-1"></i> <?= $completedProjCount ?> Completed
                                    </span>
                                <?php else: ?>
                                    <span class="ref-project-badge" style="background:#f1f5f9;color:#64748b;">
                                        No completed projects
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($projCount > 0): ?>
                        <tr>
                            <td colspan="5" class="p-0 border-0">
                                <div class="collapse border-bottom" id="client-proj-<?= $client['id'] ?>" style="background:rgba(248,250,252,0.3);">
                                    <div class="px-4 py-3">
                                        <div class="small fw-bold text-body mb-2"><i class="fas fa-folder-open me-1 text-primary"></i> Projects for <?= htmlspecialchars($client['name']) ?></div>
                                        <div class="table-responsive rounded" style="border:1px solid #e2e8f0;">
                                            <table class="table table-sm align-middle mb-0" style="font-size:0.82rem;">
                                                <thead style="background:#f8fafc;">
                                                    <tr>
                                                        <th class="ps-3 py-2">Project</th>
                                                        <th class="py-2">Status</th>
                                                        <th class="py-2">Progress</th>
                                                        <th class="py-2 text-end">Budget</th>
                                                        <th class="pe-3 py-2 text-end">Commission</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($client['projects'] as $p):
                                                        $statusColor = '#64748b';
                                                        $statusBg = '#f1f5f9';
                                                        if ($p['status'] === 'ongoing') { $statusColor = '#2563eb'; $statusBg = '#eff6ff'; }
                                                        if ($p['status'] === 'on-hold') { $statusColor = '#d97706'; $statusBg = '#fffbeb'; }
                                                        if ($p['status'] === 'completed') { $statusColor = '#16a34a'; $statusBg = '#dcfce7'; }
                                                        if ($p['status'] === 'cancelled') { $statusColor = '#dc2626'; $statusBg = '#fef2f2'; }
                                                    ?>
                                                        <tr>
                                                            <td class="ps-3 py-2 fw-semibold text-body-emphasis"><?= htmlspecialchars($p['title']) ?></td>
                                                            <td class="py-2">
                                                                <span class="ref-project-badge" style="background:<?= $statusBg ?>;color:<?= $statusColor ?>;">
                                                                    <?= ucfirst(htmlspecialchars($p['status'])) ?>
                                                                </span>
                                                            </td>
                                                            <td class="py-2" style="width:120px;">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="progress w-100" style="height:5px;">
                                                                        <div class="progress-bar" role="progressbar" style="width:<?= $p['progress'] ?>%;background:<?= $statusColor ?>;"></div>
                                                                    </div>
                                                                    <span class="small fw-semibold text-muted"><?= $p['progress'] ?>%</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2 text-end text-muted">₦<?= number_format($p['budget'], 2) ?></td>
                                                            <td class="pe-3 py-2 text-end">
                                                                <?php if ($p['status'] === 'completed'): ?>
                                                                    <span class="fw-bold text-success">+₦<?= number_format($p['budget'] * $commissionRate, 2) ?></span>
                                                                <?php else: ?>
                                                                    <span class="small text-muted">Pending</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="ref-empty-state">
                                <i class="fas fa-users-slash fa-2x mb-3" style="color:#cbd5e1;"></i>
                                <h6 class="fw-bold text-body mb-1">No referrals yet</h6>
                                <p class="text-muted small mb-0">Share your referral link above to start earning commissions.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function copyLink() {
    const input = document.getElementById('ref-url-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('copy-link-btn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('copied');
        showAlert('Referral link copied to clipboard!');
        setTimeout(() => { btn.innerHTML = '<i class="far fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
    });
}

function copyCode() {
    const code = document.getElementById('ref-code-txt').innerText;
    navigator.clipboard.writeText(code).then(() => {
        showAlert('Referral code copied!');
    });
}

function showAlert(msg) {
    const c = document.getElementById('alert-container');
    c.innerHTML = '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-0" style="background:#d1e7dd;color:#0f5132;"><div class="d-flex align-items-center"><i class="fas fa-check-circle me-2 fs-5"></i><div>' + msg + '</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    setTimeout(() => { c.innerHTML = ''; }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const target = document.querySelector(this.getAttribute('data-bs-target'));
            if (target) {
                target.addEventListener('shown.bs.collapse', () => { icon.className = 'fas fa-folder-open me-1'; });
                target.addEventListener('hidden.bs.collapse', () => { icon.className = 'fas fa-folder me-1'; });
            }
        });
    });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
