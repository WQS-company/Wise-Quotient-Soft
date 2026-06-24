<?php
$path_to_root = "../";
$page_title = "Invoices & Payments";

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']['id'])) {
    header("Location: ../login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';
$userId    = $_SESSION['user']['id'];
$userEmail = $headerUser['email'] ?? $_SESSION['user']['email'] ?? '';
$userName  = $headerUser['name'] ?? $_SESSION['user']['name'] ?? '';

require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch client invoices
$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all','unpaid','paid','overdue'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = 'all';

$query  = "SELECT i.*, op.title AS project_title FROM invoices i LEFT JOIN ongoing_projects op ON i.project_id = op.id WHERE i.user_id = ?";
$params = [$userId];
if ($statusFilter !== 'all') { $query .= " AND i.status = ?"; $params[] = $statusFilter; }
$query .= " ORDER BY i.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $invoices = [];
}

// Summary stats
$stats = ['total'=>0,'unpaid'=>0,'paid'=>0,'overdue'=>0,'total_amount'=>0,'paid_amount'=>0,'unpaid_amount'=>0];
try {
    $sr = $pdo->prepare("SELECT COUNT(*) AS cnt, status, SUM(amount) AS total FROM invoices WHERE user_id = ? GROUP BY status");
    $sr->execute([$userId]);
    while ($row = $sr->fetch()) {
        $s = $row['status'];
        $stats[$s] = (int)$row['cnt'];
        $stats['total'] += (int)$row['cnt'];
        $stats['total_amount'] += $row['total'];
        if ($s === 'paid')   $stats['paid_amount']   = $row['total'];
        if ($s === 'unpaid') $stats['unpaid_amount'] = $row['total'];
    }
} catch (Exception $e) {}

// Fetch payment transactions history
try {
    $txStmt = $pdo->prepare("
        SELECT pt.*, i.invoice_number, i.currency, op.title AS project_title
        FROM payment_transactions pt
        JOIN invoices i ON pt.invoice_id = i.id
        LEFT JOIN ongoing_projects op ON i.project_id = op.id
        WHERE pt.user_id = ?
        ORDER BY pt.created_at DESC
    ");
    $txStmt->execute([$userId]);
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transactions = [];
}
?>

<!-- Paystack Inline JS -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<style>
/* ===== Invoices & Payments Premium Styles ===== */
.inv-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 60%, #1a4a8a 100%);
    border-radius: 20px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 2rem;
}
.inv-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:200px; height:200px; background:rgba(225,85,1,.15); border-radius:50%;
}
.inv-stat-card {
    border-radius: 14px; padding: 1.2rem 1.4rem;
    border: 1px solid transparent; transition: all .2s; cursor: pointer;
}
.inv-stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.08); }
.inv-stat-card.selected { box-shadow: 0 0 0 3px #0A2D5E; }

/* Filter tabs */
.inv-filter-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.inv-filter-tab { padding:.4rem 1rem; border-radius:50px; border:1px solid #e2e8f0; background:white; color:#64748b; font-size:.82rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
.inv-filter-tab:hover { border-color:#0A2D5E; color:#0A2D5E; }
.inv-filter-tab.active { background:#0A2D5E; color:white; border-color:#0A2D5E; }

/* Section tabs */
.page-tab-btns { display:flex; gap:.5rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.5rem; }
.page-tab-btn { padding:.6rem 1.25rem; border:none; background:none; font-weight:700; font-size:.9rem; color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px; cursor:pointer; transition:all .2s; }
.page-tab-btn.active { color:#0A2D5E; border-bottom-color:#0A2D5E; }
.page-tab-panel { display:none; }
.page-tab-panel.active { display:block; }

/* Invoice card */
.inv-table-card { background:white; border-radius:18px; border:1px solid rgba(0,0,0,.06); box-shadow:0 4px 20px rgba(0,0,0,.04); overflow:hidden; }

/* Status badges */
.inv-badge { padding:.28rem .75rem; border-radius:50px; font-size:.72rem; font-weight:700; display:inline-flex; align-items:center; gap:.3rem; }
.inv-badge-unpaid  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.inv-badge-paid    { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.inv-badge-overdue { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.inv-badge-success { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.inv-badge-pending { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
.inv-badge-failed  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Payment modal */
.pay-modal-header { background:linear-gradient(135deg,#0A2D5E,#163f7a); }
.pay-method-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:1rem; }
.pay-method-btn { border:1.5px solid #e2e8f0; background:white; border-radius:12px; padding:.85rem .5rem; text-align:center; cursor:pointer; transition:all .2s; display:flex; flex-direction:column; align-items:center; gap:.35rem; font-size:.82rem; font-weight:700; }
.pay-method-btn:hover { border-color:#0A2D5E; }
.pay-method-btn.active { border-color:#0A2D5E; background:rgba(10,45,94,.05); color:#0A2D5E; }
.pay-method-btn i { font-size:1.5rem; }

/* Paystack branding */
.paystack-branding { background:rgba(10,45,94,.04); border-radius:10px; padding:.75rem 1rem; margin-top:.75rem; display:flex; align-items:center; gap:.6rem; font-size:.8rem; color:#64748b; }
.paystack-branding img { height:22px; }
</style>

<div class="container-fluid py-4">

    <!-- Hero -->
    <div class="inv-hero">
        <div class="row align-items-center g-3" style="position:relative;z-index:1;">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span style="background:rgba(225,85,1,.25);color:#ffb380;border:1px solid rgba(225,85,1,.4);padding:.2rem .7rem;border-radius:50px;font-size:.7rem;font-weight:700;text-transform:uppercase;">
                        <i class="fas fa-file-invoice-dollar me-1"></i> Billing
                    </span>
                </div>
                <h4 class="fw-extrabold mb-1" style="color:white;font-size:1.5rem;font-weight:900;">Invoices &amp; Payments</h4>
                <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin:0;">Manage project invoices and process payments securely.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div style="color:rgba(255,255,255,.7);font-size:.82rem;">
                    <i class="fas fa-check-circle me-1" style="color:#86efac;"></i>
                    <?= $stats['paid'] ?> paid &nbsp;·&nbsp;
                    <i class="fas fa-clock me-1" style="color:#fde047;"></i>
                    <?= $stats['unpaid'] ?> pending &nbsp;·&nbsp;
                    Total: ₦<?= number_format($stats['total_amount'], 0) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="?status=all" class="text-decoration-none">
                <div class="inv-stat-card <?= $statusFilter==='all'?'selected':'' ?>" style="background:#eff6ff;border-color:#bfdbfe;">
                    <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:#1e40af;margin-bottom:.4rem;"><i class="fas fa-file-invoice me-1"></i>Total</div>
                    <div style="font-size:2rem;font-weight:900;color:#1d4ed8;line-height:1;"><?= $stats['total'] ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?status=paid" class="text-decoration-none">
                <div class="inv-stat-card <?= $statusFilter==='paid'?'selected':'' ?>" style="background:#dcfce7;border-color:#86efac;">
                    <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:#166534;margin-bottom:.4rem;"><i class="fas fa-check-circle me-1"></i>Paid</div>
                    <div style="font-size:2rem;font-weight:900;color:#15803d;line-height:1;"><?= $stats['paid'] ?></div>
                    <div style="font-size:.75rem;color:#16a34a;">₦<?= number_format($stats['paid_amount'], 0) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?status=unpaid" class="text-decoration-none">
                <div class="inv-stat-card <?= $statusFilter==='unpaid'?'selected':'' ?>" style="background:#fee2e2;border-color:#fca5a5;">
                    <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:#991b1b;margin-bottom:.4rem;"><i class="fas fa-clock me-1"></i>Unpaid</div>
                    <div style="font-size:2rem;font-weight:900;color:#dc2626;line-height:1;"><?= $stats['unpaid'] ?></div>
                    <div style="font-size:.75rem;color:#ef4444;">₦<?= number_format($stats['unpaid_amount'], 0) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?status=overdue" class="text-decoration-none">
                <div class="inv-stat-card <?= $statusFilter==='overdue'?'selected':'' ?>" style="background:#fef3c7;border-color:#fde68a;">
                    <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:#92400e;margin-bottom:.4rem;"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</div>
                    <div style="font-size:2rem;font-weight:900;color:#d97706;line-height:1;"><?= $stats['overdue'] ?></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Section Tabs: Invoices | Payment History -->
    <div class="page-tab-btns">
        <button class="page-tab-btn active" data-target="tab-invoices" onclick="switchPageTab(this)">
            <i class="fas fa-file-invoice-dollar me-1"></i> Invoices
        </button>
        <button class="page-tab-btn" data-target="tab-history" onclick="switchPageTab(this)">
            <i class="fas fa-history me-1"></i> Payment History
            <?php if (!empty($transactions)): ?>
                <span class="badge" style="background:#0A2D5E;color:white;font-size:.7rem;padding:.2rem .5rem;border-radius:50px;"><?= count($transactions) ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ===== INVOICES TAB ===== -->
    <div id="tab-invoices" class="page-tab-panel active">

        <!-- Filter row -->
        <div class="inv-filter-tabs mb-3">
            <a href="?status=all"    class="inv-filter-tab <?= $statusFilter==='all'    ?'active':'' ?>">All (<?= $stats['total'] ?>)</a>
            <a href="?status=unpaid" class="inv-filter-tab <?= $statusFilter==='unpaid' ?'active':'' ?>">Unpaid (<?= $stats['unpaid'] ?>)</a>
            <a href="?status=paid"   class="inv-filter-tab <?= $statusFilter==='paid'   ?'active':'' ?>">Paid (<?= $stats['paid'] ?>)</a>
            <a href="?status=overdue"class="inv-filter-tab <?= $statusFilter==='overdue'?'active':'' ?>">Overdue (<?= $stats['overdue'] ?>)</a>
        </div>

        <div class="inv-table-card">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" style="font-size:.88rem;">
                    <thead class="table-light text-muted fw-bold" style="font-size:.8rem;border-bottom:2px solid rgba(0,0,0,.05);">
                        <tr>
                            <th class="ps-4 py-3">Invoice #</th>
                            <th class="py-3">Project</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Due Date</th>
                            <th class="py-3">Status</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-file-invoice-dollar d-block mb-3 text-secondary" style="font-size:2.5rem;"></i>
                                No invoices found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv):
                            $s = strtolower($inv['status']);
                            $isOvr = ($s === 'unpaid' && strtotime($inv['due_date']) < time());
                            $displayStatus = $isOvr ? 'overdue' : $s;
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-body"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Issued: <?= date('M d, Y', strtotime($inv['created_at'])) ?></div>
                            </td>
                            <td class="py-3"><?= $inv['project_title'] ? htmlspecialchars($inv['project_title']) : 'General / Consulting' ?></td>
                            <td class="py-3 fw-bold text-body"><?= htmlspecialchars($inv['currency']) . number_format($inv['amount'], 2) ?></td>
                            <td class="py-3 text-muted"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                            <td class="py-3">
                                <span class="inv-badge inv-badge-<?= $displayStatus ?>">
                                    <?php if ($displayStatus === 'paid'): ?>    <i class="fas fa-check-circle"></i> Paid
                                    <?php elseif ($displayStatus === 'overdue'): ?><i class="fas fa-exclamation-circle"></i> Overdue
                                    <?php else: ?>                               <i class="fas fa-clock"></i> Unpaid
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="pe-4 py-3 text-end">
                                <a href="../generate_invoice.php?id=<?= $inv['id'] ?>" target="_blank"
                                   class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-file-invoice me-1"></i>View Invoice
                                </a>
                                <?php if ($displayStatus !== 'paid'): ?>
                                    <button class="btn btn-sm btn-success rounded-pill px-3 ms-1 fw-bold"
                                        onclick='openPaymentModal(<?= json_encode($inv) ?>)'>
                                        <i class="fas fa-credit-card me-1"></i>Pay Now
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== PAYMENT HISTORY TAB ===== -->
    <div id="tab-history" class="page-tab-panel">
        <div class="inv-table-card">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold" style="color:#0A2D5E;"><i class="fas fa-history me-2"></i>Full Payment History</h6>
                <span class="text-muted small"><?= count($transactions) ?> transaction(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" style="font-size:.87rem;">
                    <thead class="table-light text-muted fw-bold" style="font-size:.78rem;border-bottom:2px solid rgba(0,0,0,.05);">
                        <tr>
                            <th class="ps-4 py-3">Reference</th>
                            <th class="py-3">Invoice #</th>
                            <th class="py-3">Project</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Method</th>
                            <th class="py-3">Date &amp; Time</th>
                            <th class="py-3">Status</th>
                            <th class="pe-4 py-3 text-end">Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-history d-block mb-3 text-secondary" style="font-size:2.5rem;"></i>
                                No payment transactions yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-body" style="font-size:.78rem;font-family:monospace;"><?= htmlspecialchars($tx['paystack_reference']) ?></div>
                            </td>
                            <td class="py-3 fw-bold" style="color:#0A2D5E;"><?= htmlspecialchars($tx['invoice_number']) ?></td>
                            <td class="py-3 text-muted"><?= $tx['project_title'] ? htmlspecialchars($tx['project_title']) : 'General' ?></td>
                            <td class="py-3 fw-bold"><?= htmlspecialchars($tx['currency'] ?? '₦') . number_format($tx['amount'], 2) ?></td>
                            <td class="py-3">
                                <span class="badge bg-body-tertiary text-body border" style="font-size:.75rem;">
                                    <?php
                                    $method = strtolower($tx['payment_method'] ?? 'card');
                                    $icon = match($method) {
                                        'card'  => '<i class="fas fa-credit-card me-1"></i>',
                                        'bank'  => '<i class="fas fa-university me-1"></i>',
                                        'ussd'  => '<i class="fas fa-mobile-alt me-1"></i>',
                                        default => '<i class="fas fa-money-bill me-1"></i>',
                                    };
                                    echo $icon . ucfirst($method);
                                    ?>
                                </span>
                            </td>
                            <td class="py-3 text-muted" style="font-size:.82rem;">
                                <?= $tx['paid_at'] ? date('M d, Y h:i A', strtotime($tx['paid_at'])) : date('M d, Y', strtotime($tx['created_at'])) ?>
                            </td>
                            <td class="py-3">
                                <span class="inv-badge inv-badge-<?= $tx['status'] ?>">
                                    <?php if ($tx['status'] === 'success'): ?>
                                        <i class="fas fa-check-circle"></i> Success
                                    <?php elseif ($tx['status'] === 'failed'): ?>
                                        <i class="fas fa-times-circle"></i> Failed
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> Pending
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="pe-4 py-3 text-end">
                                <a href="../generate_invoice.php?id=<?= $tx['invoice_id'] ?>" target="_blank"
                                   class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size:.75rem;">
                                    <i class="fas fa-file-invoice me-1"></i>View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /.container-fluid -->

<!-- ===== Payment Modal ===== -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header pay-modal-header text-white border-0" style="border-radius:16px 16px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fas fa-shield-alt me-2"></i>Secure Checkout</h5>
                    <div style="font-size:.78rem;opacity:.7;">Powered by Paystack · SSL Encrypted</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <!-- Summary -->
                <div class="mb-4 p-3 rounded-3 d-flex justify-content-between align-items-center" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div>
                        <div class="text-muted small">Invoice Amount</div>
                        <h4 class="fw-bold text-body mb-0" id="checkoutAmount">₦0.00</h4>
                        <div class="text-muted" style="font-size:.75rem;" id="checkoutProject"></div>
                    </div>
                    <span class="badge px-3 py-2" style="background:#0A2D5E;font-size:.8rem;" id="checkoutInvoiceNum">INV-000</span>
                </div>

                <!-- Payment Method -->
                <label class="form-label fw-bold small text-muted mb-2">Choose Payment Method</label>
                <div class="pay-method-grid">
                    <div class="pay-method-btn active" data-method="card" onclick="selectMethod('card')">
                        <i class="fas fa-credit-card text-primary"></i><span>Card</span>
                    </div>
                    <div class="pay-method-btn" data-method="bank" onclick="selectMethod('bank')">
                        <i class="fas fa-university text-success"></i><span>Transfer</span>
                    </div>
                    <div class="pay-method-btn" data-method="ussd" onclick="selectMethod('ussd')">
                        <i class="fas fa-mobile-alt text-warning"></i><span>USSD</span>
                    </div>
                </div>
                <input type="hidden" id="selectedMethod" value="card">
                <input type="hidden" id="activeInvoiceId" value="">

                <!-- Paystack Branding -->
                <div class="paystack-branding">
                    <svg width="80" viewBox="0 0 120 30" fill="none" xmlns="http://www.w3.org/2000/svg"><text y="22" font-size="18" font-weight="bold" fill="#011B33" font-family="sans-serif">Paystack</text></svg>
                    <span>Your payment is secured with 256-bit SSL encryption.</span>
                </div>

                <hr class="my-3">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn fw-bold flex-grow-1 text-white" id="paystackPayBtn"
                        style="background:linear-gradient(135deg,#0A2D5E,#163f7a);"
                        onclick="initiatePaystackPayment()">
                        <i class="fas fa-lock me-2"></i>Pay Securely
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PAYSTACK_PUBLIC_KEY = '<?= PAYSTACK_PUBLIC_KEY ?>';
const CLIENT_EMAIL = '<?= htmlspecialchars($userEmail, ENT_QUOTES) ?>';
const CLIENT_NAME  = '<?= htmlspecialchars($userName, ENT_QUOTES) ?>';

let paymentModalBS = null;
let activeInvoice  = null;

document.addEventListener('DOMContentLoaded', () => {
    paymentModalBS = new bootstrap.Modal(document.getElementById('paymentModal'));
});

// ===== Section Tab Switching =====
function switchPageTab(btn) {
    document.querySelectorAll('.page-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.page-tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.target).classList.add('active');
}

// ===== Open Payment Modal =====
function openPaymentModal(inv) {
    activeInvoice = inv;
    const amt = parseFloat(inv.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 });
    document.getElementById('checkoutAmount').textContent = inv.currency + amt;
    document.getElementById('checkoutInvoiceNum').textContent = inv.invoice_number;
    document.getElementById('checkoutProject').textContent = inv.project_title || 'General Services';
    document.getElementById('activeInvoiceId').value = inv.id;
    selectMethod('card');
    paymentModalBS.show();
}

// ===== Select Payment Method =====
function selectMethod(method) {
    document.querySelectorAll('.pay-method-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.pay-method-btn[data-method="${method}"]`).classList.add('active');
    document.getElementById('selectedMethod').value = method;
}

// ===== Initiate Paystack Payment =====
function initiatePaystackPayment() {
    if (!activeInvoice) return;

    const amountInKobo = Math.round(parseFloat(activeInvoice.amount) * 100);
    const reference    = 'WQS-' + activeInvoice.id + '-' + Date.now();
    const method       = document.getElementById('selectedMethod').value;

    const paystackConfig = {
        key:       PAYSTACK_PUBLIC_KEY,
        email:     CLIENT_EMAIL,
        amount:    amountInKobo,
        currency:  'NGN',
        ref:       reference,
        metadata: {
            custom_fields: [
                { display_name: 'Invoice Number', variable_name: 'invoice_number', value: activeInvoice.invoice_number },
                { display_name: 'Client Name',    variable_name: 'client_name',    value: CLIENT_NAME },
                { display_name: 'Invoice ID',     variable_name: 'invoice_id',     value: activeInvoice.id },
            ]
        },
        channels: method === 'bank' ? ['bank_transfer'] : method === 'ussd' ? ['ussd'] : ['card'],
        callback: function(response) {
            // Payment successful — verify on backend
            paymentModalBS.hide();
            verifyPayment(response.reference, activeInvoice.id);
        },
        onClose: function() {
            // User closed Paystack popup without completing
            Swal.fire({
                icon: 'info',
                title: 'Payment Cancelled',
                text: 'You closed the payment window. Your invoice is still pending.',
                confirmButtonColor: '#0A2D5E',
                timer: 3000,
                timerProgressBar: true,
            });
        }
    };

    paymentModalBS.hide();

    // Open Paystack popup
    const handler = PaystackPop.setup(paystackConfig);
    handler.openIframe();
}

// ===== Verify Payment with Backend =====
function verifyPayment(reference, invoiceId) {
    Swal.fire({
        title: 'Verifying Payment…',
        text: 'Please wait while we confirm your transaction.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../paystack_verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `reference=${encodeURIComponent(reference)}&invoice_id=${invoiceId}`
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '🎉 Payment Confirmed!',
                html: `Your payment has been verified.<br><small style="color:#64748b;">Reference: <strong>${reference}</strong></small>`,
                confirmButtonColor: '#0A2D5E'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Verification Failed',
                text: data.message || 'Could not verify your payment. Please contact support.',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(err => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not reach our server. Please check your connection and try again.',
            confirmButtonColor: '#dc3545'
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
