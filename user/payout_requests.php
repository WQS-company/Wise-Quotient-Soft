<?php
$path_to_root = "../";
$page_title = "Payout Requests";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: ../login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';
$userId = $_SESSION['user']['id'];

// Get user role from DB
$roleStmt = $pdo->prepare("SELECT role, name FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userObj = $roleStmt->fetch(PDO::FETCH_ASSOC);
$user_role = $userObj ? strtolower($userObj['role']) : 'user';

if ($user_role !== 'developer' && $user_role !== 'agent') {
    header("Location: dashboard.php");
    exit;
}

// === Calculate Payout Balances ===
$confirmedEarnings = 0.0;
$pendingEarnings = 0.0;

if ($user_role === 'agent') {
    // Get configurable commission percentage
    $agentCommissionPct = 10;
    try {
        $partnerChk = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
        $partnerChk->execute([$userId]);
        $partnerRow = $partnerChk->fetch(PDO::FETCH_ASSOC);
        if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
            $agentCommissionPct = (float)$partnerRow['default_commission_percent'];
        } else {
            $setChk = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
            $setRow = $setChk->fetch(PDO::FETCH_ASSOC);
            if ($setRow) $agentCommissionPct = (float)$setRow['setting_value'];
        }
    } catch (Exception $e) {}
    $agentCommissionRate = $agentCommissionPct / 100;

    // 1. Confirmed earnings (configurable% of completed project budgets)
    $earningsStmt = $pdo->prepare("
        SELECT SUM(op.budget * ?) AS earnings_ngn
        FROM ongoing_projects op 
        INNER JOIN users u ON op.user_id = u.id 
        WHERE u.referred_by = ? AND op.status = 'completed'
    ");
    $earningsStmt->execute([$agentCommissionRate, $userId]);
    $confirmedEarnings = (float)($earningsStmt->fetchColumn() ?? 0.0);

    // 2. Pending earnings (configurable% of ongoing/on-hold project budgets)
    $pendingStmt = $pdo->prepare("
        SELECT SUM(op.budget * ?) AS pending_ngn
        FROM ongoing_projects op 
        INNER JOIN users u ON op.user_id = u.id 
        WHERE u.referred_by = ? AND op.status IN ('ongoing', 'on-hold')
    ");
    $pendingStmt->execute([$agentCommissionRate, $userId]);
    $pendingEarnings = (float)($pendingStmt->fetchColumn() ?? 0.0);

} elseif ($user_role === 'developer') {
    // 1. Confirmed earnings (hourly_rate * hours_worked for completed tasks)
    $earningsStmt = $pdo->prepare("
        SELECT SUM(hourly_rate * hours_worked) AS earned
        FROM developer_tasks 
        WHERE developer_id = ? AND status = 'completed'
    ");
    $earningsStmt->execute([$userId]);
    $confirmedEarnings = (float)($earningsStmt->fetchColumn() ?? 0.0);

    // 2. Pending earnings (hourly_rate * hours_worked for assigned/in-progress/review tasks)
    $pendingStmt = $pdo->prepare("
        SELECT SUM(hourly_rate * hours_worked) AS earned
        FROM developer_tasks 
        WHERE developer_id = ? AND status IN ('assigned', 'in_progress', 'review')
    ");
    $pendingStmt->execute([$userId]);
    $pendingEarnings = (float)($pendingStmt->fetchColumn() ?? 0.0);
}

// 3. Sum up already requested payout requests (status in 'pending', 'processed')
$requestedStmt = $pdo->prepare("SELECT SUM(amount) FROM payout_requests WHERE user_id = ? AND status != 'rejected'");
$requestedStmt->execute([$userId]);
$totalRequested = (float)($requestedStmt->fetchColumn() ?? 0.0);

// Withdraw Balance = Confirmed Earnings - Total Requested
$availableWithdraw = max(0.0, $confirmedEarnings - $totalRequested);

// Handle Payout request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payout'])) {
    $amount = (float)($_POST['amount'] ?? 0.0);
    $method = trim($_POST['payment_method'] ?? '');
    $details = trim($_POST['payment_details'] ?? '');

    if ($amount > 0 && $method && $details) {
        if ($amount <= $availableWithdraw) {
            try {
                $ins = $pdo->prepare("INSERT INTO payout_requests (user_id, amount, currency, payment_method, payment_details, status) VALUES (?, ?, '₦', ?, ?, 'pending')");
                $ins->execute([$userId, $amount, $method, $details]);

                // Create alert notification
                $notifTitle = "Payout Request Submitted: ₦" . number_format($amount, 2);
                $notifMsg = "Your payout request of ₦" . number_format($amount, 2) . " via " . htmlspecialchars($method) . " has been logged and is awaiting administrator approval.";
                $notifIns = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `is_read`) VALUES (?, ?, ?, 0)");
                $notifIns->execute([$userId, $notifTitle, $notifMsg]);

                header("Location: payout_requests.php?status=success");
                exit;
            } catch (Exception $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Insufficient withdrawable balance. You can withdraw up to ₦" . number_format($availableWithdraw, 2);
        }
    } else {
        $errorMsg = "Please fill in all the required payout form fields.";
    }
}

require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch payout requests history
try {
    $histStmt = $pdo->prepare("SELECT * FROM payout_requests WHERE user_id = ? ORDER BY created_at DESC");
    $histStmt->execute([$userId]);
    $payouts = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payouts = [];
}
?>

<style>
/* Premium Payouts Styles */
.payout-card-box {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 24px rgba(0,0,0,0.02);
    overflow: hidden;
}
.balance-card-payout {
    border-radius: 14px;
    padding: 1.35rem 1.5rem;
    position: relative;
    overflow: hidden;
    color: white;
}
.balance-card-payout.bcp-primary { background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%); }
.balance-card-payout.bcp-success { background: linear-gradient(135deg, #15803d 0%, #16a34a 100%); }
.balance-card-payout.bcp-warning { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }

.payout-status-badge {
    padding: 0.25rem 0.65rem;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.p-badge-pending { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
.p-badge-processed { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.p-badge-rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
</style>

<div class="container-fluid py-4">
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-1"></i> Payout request submitted successfully! WQS administration will process the transfer and update status.</div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-body mb-1">Payout Ledger</h4>
            <p class="text-muted small mb-0">Withdraw your confirmed affiliate and task commissions to your bank account.</p>
        </div>
        <?php if ($availableWithdraw > 0): ?>
            <button class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#requestPayoutModal">
                <i class="fas fa-hand-holding-usd me-1"></i> Request Payout
            </button>
        <?php else: ?>
            <button class="btn btn-success rounded-pill px-4" disabled title="No withdrawable balance available.">
                <i class="fas fa-hand-holding-usd me-1"></i> Request Payout
            </button>
        <?php endif; ?>
    </div>

    <!-- Balance stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="balance-card-payout bcp-primary">
                <div class="text-white-50 small mb-1">Total Confirmed Earnings</div>
                <h2 class="fw-bold text-white mb-1">₦<?= number_format($confirmedEarnings, 2) ?></h2>
                <div class="small text-white-50">Accumulated commissions to date</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="balance-card-payout bcp-success">
                <div class="text-white-50 small mb-1">Available For Withdrawal</div>
                <h2 class="fw-bold text-white mb-1">₦<?= number_format($availableWithdraw, 2) ?></h2>
                <div class="small text-white-50">Withdrawable balance (Confirmed - Payouts)</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="balance-card-payout bcp-warning">
                <div class="text-white-50 small mb-1">Pending Clearance</div>
                <h2 class="fw-bold text-white mb-1">₦<?= number_format($pendingEarnings, 2) ?></h2>
                <div class="small text-white-50">Linked to ongoing projects/tasks</div>
            </div>
        </div>
    </div>

    <!-- Payout request history ledger -->
    <div class="payout-card-box">
        <div class="p-3 bg-body-tertiary border-bottom fw-bold text-body" style="font-size: 0.85rem;"><i class="fas fa-history me-1"></i> Withdrawal Logs</div>
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light text-muted fw-bold" style="font-size: 0.85rem; border-bottom: 2px solid rgba(0,0,0,0.05);">
                    <tr>
                        <th class="ps-4 py-3">Date</th>
                        <th class="py-3">Method</th>
                        <th class="py-3">Payment Details</th>
                        <th class="py-3">Amount Requested</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payouts)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-money-check-alt d-block mb-3 text-secondary" style="font-size: 2.5rem;"></i>
                                No withdrawal request logs recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payouts as $p): 
                            $status = strtolower($p['status']);
                            $badgeClass = 'p-badge-' . $status;
                        ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <span class="text-body fw-bold"><?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($p['created_at'])) ?></div>
                                </td>
                                <td class="py-3 text-body"><?= htmlspecialchars($p['payment_method']) ?></td>
                                <td class="py-3 text-muted small" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($p['payment_details']) ?>
                                </td>
                                <td class="py-3 text-body fw-bold"><?= htmlspecialchars($p['currency']) . number_format($p['amount'], 2) ?></td>
                                <td class="py-3">
                                    <span class="payout-status-badge <?= $badgeClass ?>">
                                        <?php if ($status === 'processed'): ?>
                                            <i class="fas fa-check-circle"></i> Processed
                                        <?php elseif ($status === 'rejected'): ?>
                                            <i class="fas fa-times-circle"></i> Rejected
                                        <?php else: ?>
                                            <i class="fas fa-clock"></i> Pending
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end text-muted small">
                                    <?= $p['admin_notes'] ? htmlspecialchars($p['admin_notes']) : 'Awaiting review' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Request Payout Modal -->
<div class="modal fade" id="requestPayoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background-color: #15803d;">
                <h5 class="modal-title fw-bold"><i class="fas fa-hand-holding-usd me-2"></i>Withdraw Confirmed Funds</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="payout_requests.php" method="POST">
                    <input type="hidden" name="submit_payout" value="1">
                    <div class="mb-3 p-3 bg-body-tertiary rounded-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Available Withdraw Balance</div>
                            <h4 class="fw-bold text-success mb-0">₦<?= number_format($availableWithdraw, 2) ?></h4>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">Amount to Withdraw (₦)</label>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. 50000" min="1000" max="<?= $availableWithdraw ?>" required step="0.01">
                        <span class="text-muted small" style="font-size: 0.72rem;">Minimum withdrawal amount: ₦1,000.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">Payout Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Bank Transfer">Bank Transfer (Nigeria Local Bank)</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Other">Other Wallet (USDT / Payoneer)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-muted">Payout Account / Destination Details</label>
                        <textarea name="payment_details" class="form-control" rows="3" placeholder="Provide Account Number, Bank Name, Account Name, or PayPal Email details..." required></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success flex-grow-1">Confirm Withdrawal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
