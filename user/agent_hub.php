<?php
$path_to_root = "../";
$page_title = "Agent & Partner Hub";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Only for agents and admins
if (!in_array($user_role, ['agent','admin'])) { header("Location: ../login.php"); exit; }
$userId = $headerUser['id'];

// Generate referral link for agent (secure HTTPS, referral code format)
$agentRefCode = $headerUser['referral_code'] ?? '';
if (empty($agentRefCode)) {
    // Generate code if missing
    do {
        $agentRefCode = 'WQS-' . strtoupper(bin2hex(random_bytes(6)));
        $codeChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE referral_code = ?");
        $codeChk->execute([$agentRefCode]);
        $codeRow = $codeChk->fetch(PDO::FETCH_ASSOC);
    } while ($codeRow && $codeRow['cnt'] > 0);
    $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$agentRefCode, $userId]);
}
$refLink = "https://" . $_SERVER['HTTP_HOST'] . "/register.php?ref=" . urlencode($agentRefCode);

// Stats
$stats = ['total_referrals'=>0, 'active_projects'=>0, 'total_earnings'=>0, 'pending_payouts'=>0];
try {
    // Get configurable commission percentage
    $hubCommissionPct = 10;
    try {
        $partnerChk = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
        $partnerChk->execute([$userId]);
        $partnerRow = $partnerChk->fetch(PDO::FETCH_ASSOC);
        if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
            $hubCommissionPct = (float)$partnerRow['default_commission_percent'];
        } else {
            $setChk = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
            $setRow = $setChk->fetch(PDO::FETCH_ASSOC);
            if ($setRow) $hubCommissionPct = (float)$setRow['setting_value'];
        }
    } catch (Exception $e) {}
    $hubCommissionRate = $hubCommissionPct / 100;

    // We assume any user whose referred_by matches this agent is a referral
    // Since we don't have referred_by column explicitly added in this session, we'll try to check if it exists or use a mock
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'referred_by'")->fetch();
    if (!$cols) {
        // Add referred_by column dynamically if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN referred_by INT NULL DEFAULT NULL AFTER role");
    }

    $r = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
    $r->execute([$userId]); $stats['total_referrals'] = (int)$r->fetchColumn();

    // Active projects by referred clients
    $p = $pdo->prepare("SELECT COUNT(*) FROM ongoing_projects WHERE user_id IN (SELECT id FROM users WHERE referred_by=?) AND status='ongoing'");
    $p->execute([$userId]); $stats['active_projects'] = (int)$p->fetchColumn();

    // Total Earnings (Commission from invoices paid by referred clients)
    $earn = $pdo->prepare("
        SELECT IFNULL(SUM(amount * ?), 0) FROM invoices 
        WHERE user_id IN (SELECT id FROM users WHERE referred_by=?) AND status='paid'
    ");
    $earn->execute([$hubCommissionRate, $userId]); $stats['total_earnings'] = (float)$earn->fetchColumn();

    // Pending payouts specific to Agent Commissions (Mocking this value based on unpaid invoices or unwithdrawn)
    $stats['pending_payouts'] = $stats['total_earnings'] * 0.2; // Example: 20% still pending payout

} catch (Exception $e) {}

// Fetch referred users list
try {
    $referrals = $pdo->prepare("SELECT id, name, email, role, created_at FROM users WHERE referred_by=? ORDER BY created_at DESC");
    $referrals->execute([$userId]);
    $referrals = $referrals->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $referrals=[]; }

?>

<style>
.agent-hero { background:linear-gradient(135deg,#2e1065,#4c1d95); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.agent-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(255,255,255,0.1); border-radius:50%; }
.agent-card { background:white; border-radius:16px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 16px rgba(0,0,0,0.04); transition:all 0.2s; padding:1.25rem; }
.agent-card:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.08); }
.ref-link-box { background:var(--color-bg); border:2px dashed #cbd5e1; border-radius:12px; padding:1.2rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.ref-url { font-family:monospace; font-size:1.1rem; color:#0f172a; font-weight:700; word-break:break-all; }
</style>

<!-- Hero -->
<div class="agent-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(255,255,255,0.2);color:#e9d5ff;border:1px solid rgba(255,255,255,0.3);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-handshake me-1"></i>Partner Network</span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Agent & Referral Hub</h1>
        <p style="color:rgba(255,255,255,0.7);font-size:0.85rem;margin:0;">Invite clients, track their projects, and earn commissions automatically.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <!-- Referral Link -->
        <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:1.5rem;">
            <h5 class="fw-bold text-body mb-1"><i class="fas fa-link me-2 text-primary"></i>Your Referral Link</h5>
            <p class="text-muted small mb-3">Share this link with potential clients or developers. You earn 10% lifetime commission on their project payments.</p>
            
            <div class="ref-link-box">
                <div class="ref-url" id="refLinkUrl"><?= htmlspecialchars($refLink) ?></div>
                <button class="btn btn-dark fw-bold px-4 rounded-pill flex-shrink-0 ms-3" onclick="copyRef()"><i class="fas fa-copy me-1"></i>Copy</button>
            </div>
            
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fab fa-twitter me-1"></i>Share on Twitter</button>
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fab fa-linkedin me-1"></i>Share on LinkedIn</button>
                <button class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fab fa-whatsapp me-1"></i>WhatsApp</button>
            </div>
        </div>

        <!-- Referrals List -->
        <div class="mt-4" style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:1.5rem;">
            <h5 class="fw-bold text-body mb-3"><i class="fas fa-users me-2 text-success"></i>My Network (<?= count($referrals) ?>)</h5>
            <?php if (empty($referrals)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-user-friends d-block mb-2" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p>You haven't referred anyone yet.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($referrals as $r): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-body"><?= htmlspecialchars($r['name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($r['email']) ?></div>
                            </td>
                            <td><span class="badge bg-primary rounded-pill text-uppercase"><?= htmlspecialchars($r['role']) ?></span></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Sidebar -->
    <div class="col-lg-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="agent-card">
                    <div style="width:40px;height:40px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#15803d;margin-bottom:0.75rem;"><i class="fas fa-coins fa-lg"></i></div>
                    <div class="text-muted fw-bold mb-1" style="font-size:0.75rem;text-transform:uppercase;">Total Earnings</div>
                    <div style="font-size:1.8rem;font-weight:900;color:#14532d;line-height:1;">₦<?= number_format($stats['total_earnings'],0) ?></div>
                </div>
            </div>
            <div class="col-12">
                <div class="agent-card">
                    <div style="width:40px;height:40px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;color:#1d4ed8;margin-bottom:0.75rem;"><i class="fas fa-users fa-lg"></i></div>
                    <div class="text-muted fw-bold mb-1" style="font-size:0.75rem;text-transform:uppercase;">Total Referrals</div>
                    <div style="font-size:1.8rem;font-weight:900;color:#1e40af;line-height:1;"><?= $stats['total_referrals'] ?></div>
                </div>
            </div>
            <div class="col-12">
                <div class="agent-card">
                    <div style="width:40px;height:40px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;margin-bottom:0.75rem;"><i class="fas fa-briefcase fa-lg"></i></div>
                    <div class="text-muted fw-bold mb-1" style="font-size:0.75rem;text-transform:uppercase;">Active Network Projects</div>
                    <div style="font-size:1.8rem;font-weight:900;color:#b45309;line-height:1;"><?= $stats['active_projects'] ?></div>
                </div>
            </div>
            
            <div class="col-12 mt-4">
                <div class="alert alert-primary p-3 rounded-3" style="background:#f0f7ff;border:1px solid #bfdbfe;">
                    <div class="fw-bold mb-1"><i class="fas fa-info-circle me-1"></i>How it works</div>
                    <p class="small mb-0 text-muted">When a client signs up via your link and pays for a project, 10% of the payment value is automatically credited to your total earnings.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyRef() {
    const text = document.getElementById('refLinkUrl').innerText;
    navigator.clipboard.writeText(text).then(()=>{
        Swal.fire({icon:'success',title:'Copied!',text:'Link copied to clipboard.',timer:1500,showConfirmButton:false});
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
