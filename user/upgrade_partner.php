<?php
$path_to_root = "../";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];

// Fetch user info
$roleStmt = $pdo->prepare("SELECT role, name, email FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userObj = $roleStmt->fetch(PDO::FETCH_ASSOC);
$user_role = $userObj ? strtolower($userObj['role']) : 'user';

// Already an agent — go to portal
if ($user_role === 'agent') {
    header("Location: referral_portal.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// AJAX handler for partner application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_application') {
    header('Content-Type: application/json');

    // Rate limit: max 3 applications per hour per user
    $rateCheck = $pdo->prepare("SELECT COUNT(*) FROM agent_requests WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $rateCheck->execute([$userId]);
    if ($rateCheck->fetchColumn() >= 3) {
        echo json_encode(['success' => false, 'message' => 'Too many applications. Please wait before applying again.']);
        exit;
    }

    // CSRF validation
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
        exit;
    }

    $partnerRole = trim($_POST['partner_role'] ?? '');

    // Validate
    if (empty($partnerRole)) {
        echo json_encode(['success' => false, 'message' => 'Please describe your role in the partnership.']);
        exit;
    }
    if (strlen($partnerRole) < 10) {
        echo json_encode(['success' => false, 'message' => 'Role description must be at least 10 characters.']);
        exit;
    }
    if (strlen($partnerRole) > 500) {
        echo json_encode(['success' => false, 'message' => 'Role description must not exceed 500 characters.']);
        exit;
    }

    // Check for existing active application (pending or approved)
    $existingCheck = $pdo->prepare("SELECT id, status FROM agent_requests WHERE user_id = ? AND status IN ('pending','approved')");
    $existingCheck->execute([$userId]);
    $existing = $existingCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing && $existing['status'] === 'pending') {
        echo json_encode(['success' => false, 'message' => 'You already have a pending application. Please wait for a decision.']);
        exit;
    }
    if ($existing && $existing['status'] === 'approved') {
        echo json_encode(['success' => false, 'message' => 'Your application is already approved! Visit your referral portal.']);
        exit;
    }

    // Generate unique application ID
    $applicationId = 'WPA-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

    try {
        $pdo->beginTransaction();

        if ($existing && $existing['status'] === 'rejected') {
            // Re-apply: update existing record
            $stmt = $pdo->prepare("UPDATE agent_requests SET status = 'pending', partner_role = ?, application_id = ?, rejection_reason = NULL, rejected_by = NULL, rejected_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$partnerRole, $applicationId, $existing['id']]);
            $requestId = $existing['id'];
        } else {
            // New application
            $stmt = $pdo->prepare("INSERT INTO agent_requests (user_id, status, partner_role, application_id, created_at, updated_at) VALUES (?, 'pending', ?, ?, NOW(), NOW())");
            $stmt->execute([$userId, $partnerRole, $applicationId]);
            $requestId = $pdo->lastInsertId();
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Partner application DB error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
        exit;
    }

    // --- Notifications ---

    $applicantName = $userObj['name'] ?? 'A user';
    $applicantEmail = $userObj['email'] ?? '';
    $submissionTime = date('M d, Y \a\t g:i A');

    // 1. Database notification to all admins (also triggers FCM push via add_notification)
    $notifMsg = "A new partner application has been submitted and requires review.\n\nApplicant: $applicantName\nEmail: $applicantEmail\nApplication ID: $applicationId\nSubmitted: $submissionTime";
    add_notification_to_admins("New Partner Application", $notifMsg, 'partner', '../admin/agent_requests.php', $applicationId);

    // 2. Email notification to admins
    $adminEmails = [];
    try {
        $adminStmt = $pdo->query("SELECT name, email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
        $adminEmails = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $emailSubject = "New Partner Application Submitted";
    $emailBody = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>";
    $emailBody .= "<div style='background:linear-gradient(135deg,#0A2D5E,#1a4a8a);padding:24px;border-radius:12px 12px 0 0;'>";
    $emailBody .= "<h2 style='color:white;margin:0;'>🔔 New Partner Application</h2></div>";
    $emailBody .= "<div style='background:#f8fafc;padding:24px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;'>";
    $emailBody .= "<p style='color:#374151;font-size:15px;'>A new partner application has been submitted through the Wise Quotient Soft Partner Program and is awaiting review.</p>";
    $emailBody .= "<table style='width:100%;border-collapse:collapse;margin:16px 0;'>";
    $emailBody .= "<tr><td style='padding:8px 12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;'>Applicant Name</td><td style='padding:8px 12px;color:#111827;border-bottom:1px solid #e5e7eb;'>{$applicantName}</td></tr>";
    $emailBody .= "<tr><td style='padding:8px 12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;'>Email</td><td style='padding:8px 12px;color:#111827;border-bottom:1px solid #e5e7eb;'>{$applicantEmail}</td></tr>";
    $emailBody .= "<tr><td style='padding:8px 12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;'>Application ID</td><td style='padding:8px 12px;color:#111827;border-bottom:1px solid #e5e7eb;font-family:monospace;'><strong>{$applicationId}</strong></td></tr>";
    $emailBody .= "<tr><td style='padding:8px 12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;'>Submitted</td><td style='padding:8px 12px;color:#111827;border-bottom:1px solid #e5e7eb;'>{$submissionTime}</td></tr>";
    $emailBody .= "<tr><td style='padding:8px 12px;color:#6b7280;font-weight:600;'>Status</td><td style='padding:8px 12px;'><span style='background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:50px;font-size:12px;font-weight:700;'>Pending Review</span></td></tr>";
    $emailBody .= "</table>";
    $emailBody .= "<p style='color:#6b7280;font-size:13px;margin-top:20px;'>Log in to the admin dashboard to review this application.</p>";
    $emailBody .= "</div></div>";

    foreach ($adminEmails as $admin) {
        if (!empty($admin['email'])) {
            send_smtp_email($admin['email'], $emailSubject, $emailBody, $pdo);
        }
    }

    // 3. Audit log
    log_audit('partner_application', 'submit', null, [
        'application_id' => $applicationId,
        'request_id' => $requestId,
        'user_id' => $userId,
        'partner_role' => $partnerRole,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your partner application has been submitted successfully!',
        'application_id' => $applicationId,
        'request_id' => $requestId,
    ]);
    exit;
}

// Fetch current request status
$requestStatus = null;
$requestStmt = $pdo->prepare("SELECT * FROM agent_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$requestStmt->execute([$userId]);
$requestStatus = $requestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Get commission rate from settings
$exampleCommissionPct = 10;
try {
    $setChk = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
    $setRow = $setChk->fetch(PDO::FETCH_ASSOC);
    if ($setRow) $exampleCommissionPct = (float)$setRow['setting_value'];
} catch (Exception $e) {}

$page_title = "Become a Partner";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
/* ===== PARTNER PAGE CUSTOM STYLES ===== */
.partner-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #1a4a8a 50%, #0e3870 100%);
    border-radius: 20px;
    padding: 3.5rem 2rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    color: white;
}
.partner-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 320px; height: 320px;
    background: rgba(225, 85, 1, 0.18);
    border-radius: 50%;
    pointer-events: none;
}
.partner-hero::after {
    content: '';
    position: absolute;
    bottom: -100px; left: -60px;
    width: 260px; height: 260px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 50%;
    pointer-events: none;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(225, 85, 1, 0.25);
    border: 1px solid rgba(225, 85, 1, 0.5);
    color: #ffb380;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 0.35rem 1rem;
    border-radius: 50px;
    margin-bottom: 1.25rem;
}
.hero-title {
    font-size: clamp(1.8rem, 4vw, 2.6rem);
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 1rem;
}
.hero-title span {
    color: #E15501;
}
.hero-subtitle {
    font-size: 1rem;
    color: rgba(255,255,255,0.75);
    max-width: 520px;
    line-height: 1.7;
}
.hero-stat-pill {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    border-radius: 12px;
    padding: 0.9rem 1.5rem;
    text-align: center;
    min-width: 110px;
}
.hero-stat-pill .stat-num {
    font-size: 1.5rem;
    font-weight: 800;
    color: #ffb380;
    line-height: 1;
}
.hero-stat-pill .stat-label {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.65);
    margin-top: 0.3rem;
    font-weight: 500;
}

/* Steps */
.step-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 1.75rem 1.5rem;
    text-align: center;
    position: relative;
    transition: all 0.3s ease;
    overflow: hidden;
}
.step-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 16px 16px 0 0;
}
.step-card.s1::before { background: linear-gradient(90deg, #0A2D5E, #1a5db5); }
.step-card.s2::before { background: linear-gradient(90deg, #E15501, #f87232); }
.step-card.s3::before { background: linear-gradient(90deg, #10b981, #34d399); }
.step-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(0,0,0,0.08);
}
.step-icon-wrap {
    width: 64px; height: 64px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}
.step-number {
    position: absolute;
    top: 1rem; right: 1rem;
    font-size: 3rem;
    font-weight: 900;
    color: rgba(0,0,0,0.04);
    line-height: 1;
    pointer-events: none;
}

/* Tier Cards */
.tier-card {
    border-radius: 16px;
    padding: 1.75rem;
    border: 2px solid transparent;
    position: relative;
    transition: all 0.3s ease;
    overflow: hidden;
}
.tier-card:hover { transform: translateY(-3px); }
.tier-card.bronze {
    background: linear-gradient(135deg, #fef9f0, #fef3e2);
    border-color: #d97706;
}
.tier-card.silver {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-color: #64748b;
}
.tier-card.gold {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border-color: #ca8a04;
}
.tier-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.3rem 0.75rem;
    border-radius: 50px;
    margin-bottom: 1rem;
}
.tier-badge.bronze { background: #fef3c7; color: #b45309; }
.tier-badge.silver { background: #e2e8f0; color: #475569; }
.tier-badge.gold   { background: #fef08a; color: #854d0e; }
.tier-commission {
    font-size: 2.4rem;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 0.25rem;
}
.tier-card.bronze .tier-commission { color: #b45309; }
.tier-card.silver .tier-commission { color: #475569; }
.tier-card.gold   .tier-commission { color: #854d0e; }
.tier-requirement {
    font-size: 0.8rem;
    color: var(--color-text-light);
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px dashed rgba(0,0,0,0.1);
}
.tier-perks li {
    font-size: 0.85rem;
    color: var(--color-text);
    padding: 0.3rem 0;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.tier-perks li i { margin-top: 3px; flex-shrink: 0; }

/* Earnings Calculator */
.calc-card {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 20px;
    padding: 2.5rem;
    color: white;
}
.calc-slider {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: rgba(255,255,255,0.2);
    outline: none;
    margin: 1rem 0;
}
.calc-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: #E15501;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(225,85,1,0.5);
    border: 3px solid white;
}
.calc-result {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
}
.calc-amount {
    font-size: 2.2rem;
    font-weight: 900;
    color: #ffb380;
}
.calc-note { font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-top: 0.25rem; }

/* FAQ */
.faq-item {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    margin-bottom: 0.75rem;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.faq-item:hover { box-shadow: var(--shadow-md); }
.faq-question {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    cursor: pointer;
    font-weight: 600;
    color: var(--color-text-body);
    background: white;
    border: none;
    width: 100%;
    text-align: left;
}
.faq-question i.fa-chevron-down {
    transition: transform 0.3s ease;
    color: var(--color-text-light);
    flex-shrink: 0;
}
.faq-question[aria-expanded="true"] i.fa-chevron-down { transform: rotate(180deg); }
.faq-answer {
    padding: 0 1.25rem 1rem;
    font-size: 0.9rem;
    color: var(--color-text-light);
    line-height: 1.7;
    background: white;
}

/* Status States */
.status-pending-card, .status-rejected-card, .status-success-card {
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
}
.status-pending-card {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 2px solid #f59e0b;
}
.status-rejected-card {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 2px solid #ef4444;
}
.timeline-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin: 1.5rem 0;
    flex-wrap: nowrap;
}
.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    position: relative;
}
.timeline-step .circle {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    border: 2px solid;
}
.timeline-step .label {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--color-text-light);
    text-align: center;
    max-width: 70px;
}
.timeline-step.done .circle   { background: #10b981; border-color: #10b981; color: white; }
.timeline-step.active .circle { background: #f59e0b; border-color: #f59e0b; color: white; }
.timeline-step.idle .circle   { background: white; border-color: var(--color-border); color: var(--color-text-light); }
.timeline-line {
    flex: 1;
    height: 2px;
    background: var(--color-border);
    min-width: 30px;
    max-width: 60px;
    margin-bottom: 22px;
}
.timeline-line.done { background: #10b981; }
.apply-btn {
    background: linear-gradient(135deg, #E15501 0%, #f87232 100%);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 0.875rem 2.5rem;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(225, 85, 1, 0.35);
}
.apply-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(225, 85, 1, 0.45);
    color: white;
}
.apply-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Mobile Responsive */
@media (max-width: 767.98px) {
    .partner-hero { padding: 2rem 1.25rem !important; border-radius: 14px !important; }
    .hero-title { font-size: 1.5rem !important; }
    .hero-subtitle { font-size: 0.9rem !important; }
    .hero-stat-pill { padding: 0.7rem 1rem !important; min-width: 90px !important; }
    .hero-stat-pill .stat-num { font-size: 1.2rem !important; }
    .tier-card { padding: 1.25rem !important; }
    .tier-commission { font-size: 1.8rem !important; }
    .step-card { padding: 1.25rem !important; }
    .calc-card { padding: 1.5rem !important; border-radius: 14px !important; }
    .calc-amount { font-size: 1.7rem !important; }
    .status-pending-card, .status-rejected-card, .status-success-card { padding: 1.5rem !important; }
}
@media (max-width: 480px) {
    .partner-hero { padding: 1.5rem 1rem !important; }
    .hero-title { font-size: 1.3rem !important; }
    .hero-badge { font-size: 0.7rem !important; padding: 0.3rem 0.75rem !important; }
    .hero-stat-pill { padding: 0.6rem 0.8rem !important; min-width: 80px !important; }
    .hero-stat-pill .stat-num { font-size: 1.1rem !important; }
    .hero-stat-pill .stat-label { font-size: 0.65rem !important; }
    .tier-card { padding: 1rem !important; border-radius: 12px !important; }
    .tier-commission { font-size: 1.5rem !important; }
    .calc-card { padding: 1.25rem !important; }
    .calc-amount { font-size: 1.5rem !important; }
    .faq-question { padding: 0.85rem 1rem !important; font-size: 0.88rem !important; }
}
</style>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3 shadow-sm mb-4 py-3" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- ===== HERO SECTION ===== -->
<div class="partner-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <div class="hero-badge">
                <i class="fas fa-star" style="font-size:0.65rem;"></i> Exclusive Partner Program
            </div>
            <h1 class="hero-title">
                Turn Your Network Into<br>
                <span>Real Commission Income</span>
            </h1>
            <p class="hero-subtitle">
                Join the WQS Referral Partner Program and earn up to 15% commission on every successful client project. No experience required — just your connections.
            </p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <div class="hero-stat-pill">
                    <span class="stat-num"><?= $exampleCommissionPct ?>%+</span>
                    <span class="stat-label">Commission Rate</span>
                </div>
                <div class="hero-stat-pill">
                    <span class="stat-num">3 Tiers</span>
                    <span class="stat-label">Bronze → Gold</span>
                </div>
                <div class="hero-stat-pill">
                    <span class="stat-num">Fast</span>
                    <span class="stat-label">Instant Payouts</span>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-center d-none d-lg-block">
            <div style="font-size: 8rem; opacity: 0.15; line-height: 1; position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); pointer-events:none;">
                🤝
            </div>
            <div style="position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; display: inline-block;">
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem;">Example earnings per project</div>
                    <?php
                        $exampleBudget = 500000;
                        $exampleCommission = $exampleBudget * ($exampleCommissionPct / 100);
                    ?>
                    <div style="font-size: 2.8rem; font-weight: 900; color: #ffb380; line-height: 1;">₦<?= number_format($exampleCommission, 0) ?></div>
                    <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 0.4rem;">on a ₦<?= number_format($exampleBudget, 0) ?> project at <?= $exampleCommissionPct ?>% commission</div>
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <span style="background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); border-radius: 50px; padding: 0.2rem 0.75rem; font-size: 0.75rem;">Bronze Tier · <?= $exampleCommissionPct ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== HOW IT WORKS ===== -->
<h5 class="fw-bold text-body mb-3"><i class="fas fa-map-signs text-primary me-2"></i>How It Works</h5>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="step-card s1 h-100">
            <span class="step-number">1</span>
            <div class="step-icon-wrap" style="background: rgba(10,45,94,0.08);">
                <i class="fas fa-user-plus text-primary"></i>
            </div>
            <h6 class="fw-bold text-body mb-2">Apply & Get Approved</h6>
            <p class="text-muted small mb-0">Submit your application. Our team reviews it within 24–48 hrs. Once approved, your account is instantly upgraded to Partner status.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="step-card s2 h-100">
            <span class="step-number">2</span>
            <div class="step-icon-wrap" style="background: rgba(225,85,1,0.08);">
                <i class="fas fa-share-nodes" style="color: #E15501;"></i>
            </div>
            <h6 class="fw-bold text-body mb-2">Share Your Link</h6>
            <p class="text-muted small mb-0">Get a personalized referral link. Share it with business owners, entrepreneurs, and anyone who needs custom software built.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="step-card s3 h-100">
            <span class="step-number">3</span>
            <div class="step-icon-wrap" style="background: rgba(16,185,129,0.08);">
                <i class="fas fa-money-bill-wave" style="color: #10b981;"></i>
            </div>
            <h6 class="fw-bold text-body mb-2">Earn Commission</h6>
                <p class="text-muted small mb-0">When your referred client's project completes, you earn <?= $exampleCommissionPct ?>%+ of the total project budget — paid directly to you.</p>
        </div>
    </div>
</div>

<!-- ===== TIER SYSTEM + EARNINGS CALCULATOR ===== -->
<div class="row g-4 mb-4">
    <!-- Tiers -->
    <div class="col-lg-7">
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-trophy text-warning me-2"></i>Partner Tier System</h5>
                <span class="badge bg-primary-subtle text-primary" style="font-size: 0.72rem;">Upgrade automatically</span>
            </div>
            <div class="card-theme-body">
                <p class="text-muted small mb-3">Your commission rate grows as you bring more successful clients. Tiers are calculated on completed projects.</p>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <div class="tier-card bronze">
                            <span class="tier-badge bronze"><i class="fas fa-medal"></i> Bronze</span>
                            <div class="tier-commission"><?= $exampleCommissionPct ?>%</div>
                            <div class="tier-requirement">Commission · 0–2 projects</div>
                            <ul class="tier-perks list-unstyled mb-0">
                                <li><i class="fas fa-check text-success fa-xs"></i> Referral link access</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Partner portal</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Basic analytics</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Partner certificate</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="tier-card silver">
                            <span class="tier-badge silver"><i class="fas fa-shield-alt"></i> Silver</span>
                            <div class="tier-commission"><?= round($exampleCommissionPct * 1.2) ?>%</div>
                            <div class="tier-requirement">Commission · 3–5 projects</div>
                            <ul class="tier-perks list-unstyled mb-0">
                                <li><i class="fas fa-check text-success fa-xs"></i> Everything in Bronze</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Priority support</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Monthly reports</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Featured listing</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="tier-card gold">
                            <span class="tier-badge gold"><i class="fas fa-crown"></i> Gold</span>
                            <div class="tier-commission"><?= round($exampleCommissionPct * 1.5) ?>%</div>
                            <div class="tier-requirement">Commission · 6+ projects</div>
                            <ul class="tier-perks list-unstyled mb-0">
                                <li><i class="fas fa-check text-success fa-xs"></i> Everything in Silver</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Dedicated manager</li>
                                <li><i class="fas fa-check text-success fa-xs"></i> Bonus incentives</li>
                                <li><i class="fas fa-star" style="color:#ca8a04; font-size: 0.65rem; margin-top: 3px;"></i> <strong>Highest rate</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Calculator -->
    <div class="col-lg-5">
        <div class="calc-card h-100">
            <h5 class="fw-bold mb-1" style="color: white;">💰 Earnings Calculator</h5>
            <p style="color: rgba(255,255,255,0.55); font-size: 0.85rem; margin-bottom: 1.5rem;">Estimate your referral commission</p>

            <label style="font-size: 0.8rem; color: rgba(255,255,255,0.7); font-weight: 600;">Project Budget (₦)</label>
            <div class="d-flex align-items-center justify-content-between">
                <span style="font-size: 0.78rem; color: rgba(255,255,255,0.45);">₦100,000</span>
                <span id="calc-budget-display" style="font-size: 1rem; font-weight: 700; color: #ffb380;">₦500,000</span>
                <span style="font-size: 0.78rem; color: rgba(255,255,255,0.45);">₦5,000,000</span>
            </div>
            <input type="range" class="calc-slider" id="calc-budget" min="100000" max="5000000" step="50000" value="500000" oninput="updateCalc()">

            <label style="font-size: 0.8rem; color: rgba(255,255,255,0.7); font-weight: 600; margin-top: 0.75rem; display: block;">Number of Referrals per Month</label>
            <div class="d-flex align-items-center justify-content-between">
                <span style="font-size: 0.78rem; color: rgba(255,255,255,0.45);">1</span>
                <span id="calc-refs-display" style="font-size: 1rem; font-weight: 700; color: #ffb380;">2 clients</span>
                <span style="font-size: 0.78rem; color: rgba(255,255,255,0.45);">10</span>
            </div>
            <input type="range" class="calc-slider" id="calc-refs" min="1" max="10" step="1" value="2" oninput="updateCalc()">

            <label style="font-size: 0.8rem; color: rgba(255,255,255,0.7); font-weight: 600; margin-top: 0.75rem; display: block;">Your Partner Tier</label>
            <select id="calc-tier" class="form-select form-select-sm mb-3" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px;" onchange="updateCalc()">
                <option value="<?= $exampleCommissionPct / 100 ?>" style="color:#000">🥉 Bronze — <?= $exampleCommissionPct ?>%</option>
                <option value="<?= ($exampleCommissionPct * 1.2) / 100 ?>" style="color:#000">🥈 Silver — <?= round($exampleCommissionPct * 1.2) ?>%</option>
                <option value="<?= ($exampleCommissionPct * 1.5) / 100 ?>" style="color:#000">🥇 Gold — <?= round($exampleCommissionPct * 1.5) ?>%</option>
                <option value="0.20" style="color:#000">💠 Platinum — 20%</option>
                <option value="0.40" style="color:#000">💎 Diamond — 40%</option>
                <option value="0.50" style="color:#000">👑 Elite — 50%</option>
            </select>

            <div class="calc-result">
                <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.25rem;">Estimated Monthly Earnings</div>
                <div class="calc-amount" id="calc-result-ngn">₦100,000</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TERMS & APPLICATION ===== -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-file-contract text-primary me-2"></i>Program Terms & Conditions</h5>
            </div>
            <div class="card-theme-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(10,45,94,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-cookie-bite text-primary fa-sm"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Cookie Tracking</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Referrals auto-tracked via 30-day cookies from signup links.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(16,185,129,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check-double text-success fa-sm"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Completion-Based</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Commission released on successful project completion only.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(245,158,11,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-bolt text-warning fa-sm"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Instant Payout</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Earnings released immediately after project launch & sign-off.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(225,85,1,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-print" style="color: #E15501; font-size: 0.85rem;"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Official Agreement</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Digital partner agreement issued and printable on approval.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(10,45,94,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-chart-line text-primary fa-sm"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Auto-Tier Upgrades</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Rates upgrade automatically as your closed projects grow.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div style="width: 36px; height: 36px; background: rgba(16,185,129,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-infinity text-success fa-sm"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body" style="font-size: 0.88rem;">Unlimited Referrals</div>
                                <div class="text-muted" style="font-size: 0.8rem;">No cap on referrals or earnings. Refer as many as you want.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Card -->
    <div class="col-lg-5">
        <?php if ($requestStatus === null): ?>
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-paper-plane text-primary me-2"></i>Apply Now</h5>
            </div>
            <div class="card-theme-body text-center">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #E15501, #f87232); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; box-shadow: 0 8px 24px rgba(225,85,1,0.3);">
                    <i class="fas fa-handshake fa-2x text-white"></i>
                </div>
                <h5 class="fw-bold text-body mb-2">Ready to Start Earning?</h5>
                <p class="text-muted small mb-4" style="max-width: 320px; margin: 0 auto 1.5rem;">Submit your application today. Our team reviews it within 24–48 hours and you'll receive an email notification.</p>
                <div class="d-flex flex-column gap-2 mb-4 text-start" style="max-width: 280px; margin: 0 auto;">
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="fas fa-check-circle text-success"></i> Free to join — no upfront cost
                    </div>
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="fas fa-check-circle text-success"></i> Instant dashboard upgrade on approval
                    </div>
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="fas fa-check-circle text-success"></i> Printable partner agreement included
                    </div>
                </div>
                <form method="POST" id="partnerForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="ajax_action" value="submit_application">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="partner_role">Role Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="partner_role" name="partner_role" rows="3" placeholder="Describe the role you will play in the partnership (e.g., lead developer, marketer)" required minlength="10" maxlength="500"></textarea>
                        <div class="form-text"><span id="roleCharCount">0</span>/500 characters</div>
                    </div>
                    <button type="submit" name="apply_partner" class="apply-btn" id="applyBtn">
                        <i class="fas fa-paper-plane me-2"></i> Submit Application
                    </button>
                    <div id="formError" class="text-danger small mt-2" style="display:none;"></div>
                </form>
                <p class="text-muted mt-3" style="font-size: 0.75rem;">By applying you agree to our Partner Program Terms.</p>
            </div>
        </div>

        <?php elseif ($requestStatus['status'] === 'pending'): ?>
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-hourglass-half text-warning me-2"></i>Application Status</h5>
            </div>
            <div class="card-theme-body">
                <div class="status-pending-card mb-3">
                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">⏳</div>
                    <h6 class="fw-bold mb-1" style="color: #92400e;">Under Review</h6>
                    <p class="mb-0 small" style="color: #78350f;">Your application is currently being reviewed by our team. This typically takes 24–48 hours.</p>
                    <?php if (!empty($requestStatus['application_id'])): ?>
                    <div class="mt-2"><span class="badge" style="background:#fef3c7;color:#92400e;font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($requestStatus['application_id']) ?></span></div>
                    <?php endif; ?>
                </div>
                <!-- Timeline -->
                <div class="timeline-steps">
                    <div class="timeline-step done">
                        <div class="circle"><i class="fas fa-check fa-xs"></i></div>
                        <div class="label">Submitted</div>
                    </div>
                    <div class="timeline-line done"></div>
                    <div class="timeline-step active">
                        <div class="circle"><i class="fas fa-search fa-xs"></i></div>
                        <div class="label">In Review</div>
                    </div>
                    <div class="timeline-line"></div>
                    <div class="timeline-step idle">
                        <div class="circle"><i class="fas fa-star fa-xs"></i></div>
                        <div class="label">Approved</div>
                    </div>
                    <div class="timeline-line"></div>
                    <div class="timeline-step idle">
                        <div class="circle"><i class="fas fa-rocket fa-xs"></i></div>
                        <div class="label">Active</div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <span class="text-muted small">Submitted: <?= date("M d, Y", strtotime($requestStatus['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <?php elseif ($requestStatus['status'] === 'rejected'): ?>
        <div class="card-theme h-100">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-times-circle text-danger me-2"></i>Application Status</h5>
            </div>
            <div class="card-theme-body">
                <div class="status-rejected-card mb-3">
                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">❌</div>
                    <h6 class="fw-bold mb-1" style="color: #991b1b;">Application Declined</h6>
                    <p class="mb-0 small" style="color: #7f1d1d;">Your recent application was not approved. You may resubmit at any time — we encourage you to try again!</p>
                </div>
                <form method="POST" class="text-center">
                    <button type="submit" name="apply_partner" class="apply-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                        <i class="fas fa-redo me-2"></i> Re-apply Now
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FAQ ===== -->
<div class="card-theme mb-4">
    <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-question-circle text-primary me-2"></i>Frequently Asked Questions</h5>
    </div>
    <div class="card-theme-body">
        <div class="row g-0">
            <div class="col-lg-6 pe-lg-3">
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false">
                        How are referrals tracked?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq1" class="collapse"><div class="faq-answer">When someone visits our site through your unique referral link, a 30-day cookie is set in their browser. Even if they close and return later, you still get credit when they sign up.</div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                        When do I get paid?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq2" class="collapse"><div class="faq-answer">Commission is released immediately upon the successful completion and launch of your referred client's project. There is no waiting period after that milestone.</div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                        Is there a limit to how much I can earn?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq3" class="collapse"><div class="faq-answer">Absolutely not! There is no cap on your referrals or earnings. The more clients you bring, the more you earn — and your commission rate grows with you.</div></div>
                </div>
            </div>
            <div class="col-lg-6 ps-lg-3">
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                        How do I move to a higher tier?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq4" class="collapse"><div class="faq-answer">Tiers upgrade automatically based on successful completed projects. Bronze starts at 0, Silver at 3, and Gold at 6 completed projects. You don't need to do anything — it's automatic.</div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false">
                        What is the Partner Agreement document?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq5" class="collapse"><div class="faq-answer">Upon approval, WQS generates a digital Referral Partner Agreement with your name and details. You can print and file it as official documentation of your partnership.</div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question collapsed" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false">
                        Can I refer international clients?
                        <i class="fas fa-chevron-down fa-sm"></i>
                    </button>
                    <div id="faq6" class="collapse"><div class="faq-answer">Yes! WQS works with clients globally. Your commission is calculated in Nigerian Naira (₦), and you can refer clients from anywhere in the world.</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateCalc() {
    const budget = parseInt(document.getElementById('calc-budget').value);
    const refs   = parseInt(document.getElementById('calc-refs').value);
    const rate   = parseFloat(document.getElementById('calc-tier').value);

    document.getElementById('calc-budget-display').textContent = '₦' + budget.toLocaleString();
    document.getElementById('calc-refs-display').textContent   = refs + (refs === 1 ? ' client' : ' clients');

    const earningsNGN = budget * refs * rate;

    document.getElementById('calc-result-ngn').textContent = '₦' + earningsNGN.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:0});
}
updateCalc();

// Character counter for role description
const roleTextarea = document.getElementById('partner_role');
const charCount = document.getElementById('roleCharCount');
if (roleTextarea && charCount) {
    roleTextarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
}

// AJAX form submission
const partnerForm = document.getElementById('partnerForm');
const applyBtn = document.getElementById('applyBtn');
const formError = document.getElementById('formError');

if (partnerForm) {
    partnerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Reset states
        formError.style.display = 'none';
        formError.textContent = '';

        const roleDesc = roleTextarea.value.trim();
        if (roleDesc.length < 10) {
            formError.textContent = 'Please describe your role (at least 10 characters).';
            formError.style.display = 'block';
            return;
        }

        // Disable button, show spinner
        applyBtn.disabled = true;
        applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

        try {
            const formData = new FormData(partnerForm);

            const response = await fetch('upgrade_partner.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Success! Show SweetAlert2 toast and reload
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Application Submitted Successfully',
                        html: '<p style="color:#374151;margin:0;">Your partner application has been received and is currently under review.</p><p style="color:#6b7280;margin:8px 0 0;font-size:0.85rem;">Application ID: <strong>' + result.application_id + '</strong></p><p style="color:#6b7280;margin:4px 0 0;font-size:0.85rem;">You will be notified once a decision has been made.</p>',
                        confirmButtonColor: '#0A2D5E',
                        confirmButtonText: 'View Status',
                        timer: 8000,
                        timerProgressBar: true,
                    }).then(() => {
                        window.location.reload();
                    });
                    // Auto-reload after 3 seconds even if user doesn't click
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    alert(result.message + '\n\nApplication ID: ' + result.application_id);
                    window.location.reload();
                }
            } else {
                formError.textContent = result.message;
                formError.style.display = 'block';
                applyBtn.disabled = false;
                applyBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
            }
        } catch (err) {
            console.error('Partner application error:', err);
            formError.textContent = 'A network error occurred. Please try again.';
            formError.style.display = 'block';
            applyBtn.disabled = false;
            applyBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
        }
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
