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

// Only agents (partners) can request an upgrade
if ($user_role !== 'agent') {
    $_SESSION['error_message'] = "Only partners can request an upgrade.";
    header("Location: " . $path_to_root . "user/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_partner'])) {
    $newRole = $_POST['new_role'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Insert or update request with status 'upgrade_requested'
    $checkQuery = $pdo->prepare("SELECT id FROM agent_requests WHERE user_id = ?");
    $checkQuery->execute([$userId]);
    if ($checkQuery->rowCount() > 0) {
        $pdo->prepare("UPDATE agent_requests SET status = 'upgrade_requested', partner_role = ?, reason = ?, updated_at = NOW() WHERE user_id = ?")
            ->execute([$newRole, $reason, $userId]);
    } else {
        $pdo->prepare("INSERT INTO agent_requests (user_id, status, partner_role, reason, created_at, updated_at) VALUES (?, 'upgrade_requested', ?, ?, NOW(), NOW())")
            ->execute([$userId, $newRole, $reason]);
    }

    // Notify admins
    $applicantName = $userObj['name'] ?? 'A partner';
    if (function_exists('add_notification_to_admins')) {
        add_notification_to_admins("Partner Upgrade Request", "Partner $applicantName (ID: $userId) requested an upgrade. New role: $newRole. Reason: $reason", 'partner', '../admin/agent_requests.php', $userId);
    }

    $_SESSION['success_message'] = "Your upgrade request has been submitted. We'll review it shortly.";
    header("Location: " . $path_to_root . "user/dashboard.php");
    exit;
}

$page_title = "Upgrade Partnership";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<div class="container-fluid py-4">
    <div class="row g-4">
        <!-- Left Column: Upgrade Benefits and Tiers -->
        <div class="col-12 col-lg-5">
            <div class="card-theme h-100" style="background: linear-gradient(135deg, var(--color-primary) 0%, #1e3a5f 100%); color: white; border: none; min-height: 400px;">
                <div class="card-theme-body d-flex flex-column justify-content-between p-4 h-100">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span style="background: rgba(225, 85, 1, 0.2); color: #ff8c42; border: 1px solid rgba(225, 85, 1, 0.4); padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">
                                <i class="fas fa-arrow-up me-1"></i> Tier Upgrade
                            </span>
                        </div>
                        <h3 class="fw-bold mb-3" style="font-size: 1.6rem; color: white;">Elevate Your Partnership</h3>
                        <p class="mb-4 text-white-50" style="font-size: 0.9rem; line-height: 1.6;">
                            Unlock higher commission rates, exclusive marketing resources, and direct involvement in key client projects by upgrading your partnership role at Wise Quotient Soft.
                        </p>

                        <!-- Tier Benefits -->
                        <div class="mb-4">
                            <div class="d-flex gap-3 mb-3 align-items-start">
                                <div style="font-size: 1.5rem; line-height: 1;">🥉</div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-white" style="font-size:0.95rem;">Bronze Tier (10% Commission)</h6>
                                    <p class="small text-white-50 mb-0">Our starting program for all newly registered partners.</p>
                                </div>
                            </div>
                            <div class="d-flex gap-3 mb-3 align-items-start">
                                <div style="font-size: 1.5rem; line-height: 1;">🥈</div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-white" style="font-size:0.95rem;">Silver Tier (12% Commission)</h6>
                                    <p class="small text-white-50 mb-0">Requires 3+ successful referred projects. Higher commission and priority PM support.</p>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start">
                                <div style="font-size: 1.5rem; line-height: 1;">🥇</div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-white" style="font-size:0.95rem;">Gold Tier (15% Commission)</h6>
                                    <p class="small text-white-50 mb-0">Requires 6+ successful referred projects. Ultimate payout margins, custom branding resources, and dedicated account manager.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 mt-4" style="border-top: 1px solid rgba(255, 255, 255, 0.15);">
                        <small class="text-white-50"><i class="fas fa-info-circle me-1"></i> Upgrades are manually reviewed by our relations team within 24–48 hours.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Upgrade Form -->
        <div class="col-12 col-lg-7">
            <div class="card-theme h-100">
                <div class="card-theme-header">
                    <h5 class="card-theme-title text-body"><i class="fas fa-file-signature text-primary me-2"></i>Upgrade Request Form</h5>
                </div>
                <div class="card-theme-body p-4">
                    <p class="text-muted small mb-4">Please specify the desired role you wish to take on in the company (e.g. Senior Partner, Tech Liaison, Regional Representative) and detail how you will add value in this role.</p>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="new_role" class="form-label-theme"><i class="fas fa-id-badge text-muted me-2"></i>Desired Partnership Role</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: var(--color-bg); border-color: var(--color-border);">
                                    <i class="fas fa-user-tag text-muted"></i>
                                </span>
                                <input type="text" class="form-control form-control-theme" id="new_role" name="new_role" required placeholder="e.g. Senior Partner, Marketing Lead, Dev Liaison" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.75rem; margin-top: 0.35rem;">This title will be visible on your referral letters and program profile.</div>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label-theme"><i class="fas fa-edit text-muted me-2"></i>Reason & Proposal for Upgrade</label>
                            <textarea class="form-control form-control-theme" id="reason" name="reason" rows="6" required placeholder="Explain why you wish to upgrade, the specific skills or network you bring, and your strategic proposal for client referrals..."></textarea>
                            <div class="form-text text-muted" style="font-size: 0.75rem; margin-top: 0.35rem;">Provide details that help us evaluate your request faster.</div>
                        </div>

                        <div class="d-flex align-items-center gap-3 pt-2">
                            <button type="submit" name="upgrade_partner" class="btn btn-theme px-4 py-2 d-flex align-items-center gap-2">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                            <a href="dashboard.php" class="btn btn-theme-secondary px-4 py-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>

