<?php
/**
 * generate_invoice.php
 * Professional printable invoice with company header, logo watermark, and payment history.
 * Access: clients see their own invoices; admins see all.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

// Must be logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: login.php");
    exit;
}

$userId      = (int)$_SESSION['user']['id'];
$userRole    = strtolower($_SESSION['user']['role'] ?? 'user');
$isAdmin     = in_array($userRole, ['admin','manager','finance','ceo','secretary']);

$invoiceId   = (int)($_GET['id'] ?? 0);

if (!$invoiceId) { die("Invalid invoice ID."); }

// Fetch invoice
try {
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT i.*, u.name AS client_name, u.email AS client_email, u.phone AS client_phone,
                   op.title AS project_title
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN ongoing_projects op ON i.project_id = op.id
            WHERE i.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT i.*, u.name AS client_name, u.email AS client_email, u.phone AS client_phone,
                   op.title AS project_title
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN ongoing_projects op ON i.project_id = op.id
            WHERE i.id = ? AND i.user_id = ?
        ");
    }
    $params = $isAdmin ? [$invoiceId] : [$invoiceId, $userId];
    $stmt->execute($params);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

if (!$invoice) {
    header("Location: " . ($isAdmin ? "admin/invoice_management.php" : "user/client-invoices.php"));
    exit;
}

// Fetch payment transaction for this invoice (if paid)
$transaction = null;
try {
    $txStmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE invoice_id = ? AND status = 'success' ORDER BY paid_at DESC LIMIT 1");
    $txStmt->execute([$invoiceId]);
    $transaction = $txStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* fail-safe */ }

// Company info
$company = [
    'name'     => 'Wise Quotient Soft Ltd.',
    'tagline'  => 'Building Smart. Scaling Fast.',
    'address'  => 'No.1 Ibadan Street, Kaduna, Nigeria',
    'email'    => 'info@wisequotient.com',
    'phone'    => '+2348077416106',
    'website'  => 'www.wisequotient.com',
    'rc_no'    => 'RC-7891234',
    'logo'     => __DIR__ . '/LOGO W.png',
    'logo_web' => 'LOGO W.png',
];

$isPaid     = strtolower($invoice['status']) === 'paid';
$isOverdue  = !$isPaid && strtotime($invoice['due_date']) < time();
$statusLabel = $isPaid ? 'PAID' : ($isOverdue ? 'OVERDUE' : 'UNPAID');
$invoiceNum = $invoice['invoice_number'];
$amount     = (float)$invoice['amount'];
$currency   = $invoice['currency'] ?? '₦';
$vatRate    = 0; // Set to e.g. 7.5 for Nigerian VAT
$vatAmount  = $amount * ($vatRate / 100);
$totalDue   = $amount + $vatAmount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= htmlspecialchars($invoiceNum) ?> | Wise Quotient Soft</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f0f4f8;
    color: #1a1a2e;
    min-height: 100vh;
    padding: 2rem 1rem;
}

/* Toolbar (hidden on print) */
.inv-toolbar {
    max-width: 900px; margin: 0 auto 1.5rem;
    display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
}
.inv-toolbar a, .inv-toolbar button {
    padding: .55rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: .88rem;
    cursor: pointer; text-decoration: none; border: none; display: inline-flex; align-items: center; gap: .4rem;
}
.btn-inv-back    { background: #e2e8f0; color: #475569; }
.btn-inv-back:hover { background: #cbd5e1; color: #1e293b; }
.btn-inv-print   { background: linear-gradient(135deg,#0A2D5E,#163f7a); color: white; }
.btn-inv-print:hover { opacity: .9; }

/* Invoice Paper */
.invoice-paper {
    max-width: 900px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,.12);
    overflow: hidden;
    position: relative;
}

/* Logo Watermark */
.watermark-logo {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 420px;
    opacity: 0.04;
    pointer-events: none;
    z-index: 0;
    filter: grayscale(100%);
}

/* All content above watermark */
.invoice-inner { position: relative; z-index: 1; }

/* Header band */
.inv-header {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 60%, #1a4a8a 100%);
    padding: 2.5rem 2.75rem;
    position: relative;
    overflow: hidden;
}
.inv-header::before {
    content: '';
    position: absolute; top: -80px; right: -80px;
    width: 250px; height: 250px;
    background: rgba(225,85,1,0.15); border-radius: 50%;
}
.inv-header::after {
    content: '';
    position: absolute; bottom: -50px; left: 40%;
    width: 160px; height: 160px;
    background: rgba(255,255,255,0.04); border-radius: 50%;
}
.company-logo-header {
    height: 52px;
    object-fit: contain;
    filter: brightness(0) invert(1);
}
.inv-number-badge {
    background: rgba(225,85,1,0.25);
    border: 1px solid rgba(225,85,1,0.4);
    color: #ffb380;
    padding: .3rem .9rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
}
.company-title { color: #ffffff; font-size: 1.4rem; font-weight: 800; margin: 0; }
.company-sub   { color: rgba(255,255,255,.55); font-size: .8rem; margin: 0; }

/* Status stamp */
.status-stamp {
    display: inline-block;
    padding: .6rem 1.5rem;
    border-radius: 8px;
    font-size: 1.5rem;
    font-weight: 900;
    letter-spacing: .12em;
    transform: rotate(-4deg);
    border: 4px solid;
}
.stamp-paid    { color: #15803d; border-color: #15803d; background: rgba(21,128,61,.06); }
.stamp-unpaid  { color: #d97706; border-color: #d97706; background: rgba(217,119,6,.06); }
.stamp-overdue { color: #dc2626; border-color: #dc2626; background: rgba(220,38,38,.06); }

/* Info grid */
.inv-body    { padding: 2.5rem 2.75rem; }
.inv-meta-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.inv-meta-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem 1.3rem;
}
.inv-meta-box label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; display: block; margin-bottom: .3rem; }
.inv-meta-box .value { font-size: .92rem; font-weight: 700; color: #1e293b; }

/* Bill To / From */
.inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.inv-party-box {
    border-radius: 12px; padding: 1.3rem;
}
.inv-from { background: linear-gradient(135deg, #0A2D5E, #163f7a); color: white; }
.inv-to   { background: #f8fafc; border: 1px solid #e2e8f0; }
.inv-party-box .party-label { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; margin-bottom: .6rem; opacity: .65; }
.inv-from .party-label { color: rgba(255,255,255,.65); }
.inv-to .party-label   { color: #64748b; }
.inv-party-box .party-name { font-size: 1.05rem; font-weight: 800; margin-bottom: .25rem; }
.inv-from .party-name { color: white; }
.inv-to .party-name   { color: #1e293b; }
.inv-party-box .party-info { font-size: .82rem; line-height: 1.8; }
.inv-from .party-info { color: rgba(255,255,255,.7); }
.inv-to .party-info   { color: #475569; }

/* Line items table */
.inv-items-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; border-radius: 12px; overflow: hidden; }
.inv-items-table thead { background: #0A2D5E; }
.inv-items-table thead th { padding: .85rem 1.1rem; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.75); border: none; }
.inv-items-table tbody td { padding: .85rem 1.1rem; font-size: .9rem; color: #334155; border-bottom: 1px solid #f1f5f9; }
.inv-items-table tbody tr:last-child td { border-bottom: none; }
.inv-items-table tbody tr:hover { background: #f8fafc; }

/* Totals */
.inv-totals { display: flex; justify-content: flex-end; margin-bottom: 2rem; }
.inv-totals-box { min-width: 300px; }
.inv-total-row { display: flex; justify-content: space-between; padding: .55rem 0; font-size: .9rem; color: #64748b; border-bottom: 1px dashed #e2e8f0; }
.inv-total-row.grand { padding: .85rem 0; font-size: 1.1rem; font-weight: 800; color: #0A2D5E; border-bottom: none; border-top: 2px solid #0A2D5E; }

/* Payment info */
.inv-payment-box {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid #bae6fd;
    border-radius: 14px;
    padding: 1.4rem 1.6rem;
    margin-bottom: 2rem;
}
.inv-payment-box h6 { font-weight: 800; color: #0A2D5E; font-size: .9rem; margin-bottom: .75rem; }
.inv-payment-box .bank-detail { font-size: .85rem; color: #334155; line-height: 1.9; }

/* Footer band */
.inv-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 1.4rem 2.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.inv-footer .footer-tagline { font-size: .8rem; color: #64748b; }
.inv-footer .footer-terms   { font-size: .75rem; color: #94a3b8; text-align: right; }

/* Transaction section */
.inv-txn-box {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px;
    padding: 1.4rem 1.6rem; margin-bottom: 2rem;
}
.inv-txn-box h6 { font-weight: 800; color: #15803d; font-size: .9rem; margin-bottom: .75rem; }
.inv-txn-row { font-size: .85rem; color: #334155; line-height: 2; }

/* Print styles */
@media print {
    body { background: white; padding: 0; }
    .inv-toolbar, .no-print { display: none !important; }
    .invoice-paper { box-shadow: none; border-radius: 0; max-width: 100%; margin: 0; }
    .inv-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .inv-items-table thead { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .inv-from { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 0.5cm; size: A4; }
}

@media (max-width: 768px) {
    .inv-meta-row { grid-template-columns: 1fr 1fr; }
    .inv-parties  { grid-template-columns: 1fr; }
    .inv-body { padding: 1.5rem; }
    .inv-header { padding: 1.75rem; }
    .inv-footer { padding: 1rem 1.5rem; }
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="inv-toolbar no-print">
    <?php if ($isAdmin): ?>
        <a href="admin/invoice_management.php" class="btn-inv-back"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
    <?php else: ?>
        <a href="user/client-invoices.php" class="btn-inv-back"><i class="fas fa-arrow-left"></i> My Invoices</a>
    <?php endif; ?>
    <button class="btn-inv-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Save as PDF</button>
    <?php if (!$isPaid && !$isAdmin): ?>
        <a href="user/client-invoices.php" class="btn-inv-print" style="background:linear-gradient(135deg,#16a34a,#15803d);">
            <i class="fas fa-credit-card me-1"></i> Pay Now
        </a>
    <?php endif; ?>
</div>

<!-- Invoice Paper -->
<div class="invoice-paper">
    <!-- Logo Watermark -->
    <img src="<?= htmlspecialchars($company['logo_web']) ?>" class="watermark-logo" alt="">

    <div class="invoice-inner">

        <!-- Header -->
        <div class="inv-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3" style="position:relative;z-index:1;">
                <div>
                    <img src="<?= htmlspecialchars($company['logo_web']) ?>" class="company-logo-header mb-2" alt="WQS Logo">
                    <p class="company-title"><?= htmlspecialchars($company['name']) ?></p>
                    <p class="company-sub"><?= htmlspecialchars($company['tagline']) ?></p>
                    <p class="company-sub mt-1"><?= htmlspecialchars($company['address']) ?></p>
                    <p class="company-sub"><?= htmlspecialchars($company['phone']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($company['email']) ?></p>
                </div>
                <div class="text-end">
                    <span class="inv-number-badge mb-2 d-inline-block">Invoice</span>
                    <div style="font-size:1.6rem;font-weight:900;color:white;letter-spacing:-.01em;"><?= htmlspecialchars($invoiceNum) ?></div>
                    <div style="color:rgba(255,255,255,.55);font-size:.82rem;">RC: <?= htmlspecialchars($company['rc_no']) ?></div>
                    <!-- Status Stamp -->
                    <div class="mt-3">
                        <span class="status-stamp stamp-<?= strtolower($statusLabel) === 'unpaid' ? 'unpaid' : (strtolower($statusLabel) === 'paid' ? 'paid' : 'overdue') ?>">
                            <?= $statusLabel ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="inv-body">

            <!-- Meta row: Invoice #, Issue Date, Due Date -->
            <div class="inv-meta-row">
                <div class="inv-meta-box">
                    <label>Invoice Number</label>
                    <div class="value"><?= htmlspecialchars($invoiceNum) ?></div>
                </div>
                <div class="inv-meta-box">
                    <label>Issue Date</label>
                    <div class="value"><?= date('M d, Y', strtotime($invoice['created_at'])) ?></div>
                </div>
                <div class="inv-meta-box" style="<?= $isOverdue ? 'border-color:#fca5a5;background:#fef2f2;' : '' ?>">
                    <label>Due Date</label>
                    <div class="value" style="<?= $isOverdue ? 'color:#dc2626;' : '' ?>">
                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                        <?= $isOverdue ? ' <span style="font-size:.7rem;font-weight:700;color:#dc2626;">OVERDUE</span>' : '' ?>
                    </div>
                </div>
            </div>

            <!-- Bill From / To -->
            <div class="inv-parties">
                <div class="inv-party-box inv-from">
                    <div class="party-label">From</div>
                    <div class="party-name"><?= htmlspecialchars($company['name']) ?></div>
                    <div class="party-info">
                        <?= htmlspecialchars($company['address']) ?><br>
                        <?= htmlspecialchars($company['phone']) ?><br>
                        <?= htmlspecialchars($company['email']) ?><br>
                        <?= htmlspecialchars($company['website']) ?>
                    </div>
                </div>
                <div class="inv-party-box inv-to">
                    <div class="party-label">Bill To</div>
                    <div class="party-name"><?= htmlspecialchars($invoice['client_name'] ?? 'Client') ?></div>
                    <div class="party-info">
                        <?= htmlspecialchars($invoice['client_email'] ?? '') ?><br>
                        <?php if (!empty($invoice['client_phone'])): ?>
                            <?= htmlspecialchars($invoice['client_phone']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($invoice['project_title'])): ?>
                            <strong>Project:</strong> <?= htmlspecialchars($invoice['project_title']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <table class="inv-items-table">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th>Description</th>
                        <th style="width:12%;text-align:center;">Qty</th>
                        <th style="width:18%;text-align:right;">Unit Price</th>
                        <th style="width:18%;text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            <strong><?= !empty($invoice['project_title']) ? htmlspecialchars($invoice['project_title']) : 'Professional Software Services' ?></strong>
                            <?php if (!empty($invoice['notes'])): ?>
                                <div style="font-size:.8rem;color:#64748b;margin-top:.2rem;"><?= htmlspecialchars($invoice['notes']) ?></div>
                            <?php elseif (!empty($invoice['description'])): ?>
                                <div style="font-size:.8rem;color:#64748b;margin-top:.2rem;"><?= htmlspecialchars($invoice['description']) ?></div>
                            <?php else: ?>
                                <div style="font-size:.8rem;color:#64748b;margin-top:.2rem;">Contract payment per agreed scope of work</div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">1</td>
                        <td style="text-align:right;font-weight:600;"><?= htmlspecialchars($currency) . number_format($amount, 2) ?></td>
                        <td style="text-align:right;font-weight:700;color:#0A2D5E;"><?= htmlspecialchars($currency) . number_format($amount, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="inv-totals">
                <div class="inv-totals-box">
                    <div class="inv-total-row">
                        <span>Subtotal</span>
                        <span><?= htmlspecialchars($currency) . number_format($amount, 2) ?></span>
                    </div>
                    <div class="inv-total-row">
                        <span>VAT (<?= $vatRate ?>%)</span>
                        <span><?= htmlspecialchars($currency) . number_format($vatAmount, 2) ?></span>
                    </div>
                    <div class="inv-total-row grand">
                        <span>Total Due</span>
                        <span><?= htmlspecialchars($currency) . number_format($totalDue, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Info (if unpaid) -->
            <?php if (!$isPaid): ?>
            <div class="inv-payment-box">
                <h6><i class="fas fa-university me-2"></i>Payment Instructions</h6>
                <div class="bank-detail">
                    <strong>Bank Name:</strong> &nbsp; Zenith Bank / GTBank Nigeria<br>
                    <strong>Account Number:</strong> &nbsp; 1234567890<br>
                    <strong>Account Name:</strong> &nbsp; Wise Quotient Soft Ltd.<br>
                    <strong>Reference:</strong> &nbsp; <?= htmlspecialchars($invoiceNum) ?><br>
                    <br>
                    <em style="color:#0284c7;">You can also pay online instantly via your dashboard — <strong>Invoices &amp; Payments</strong> → <strong>Pay Now</strong>.</em>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transaction confirmation (if paid) -->
            <?php if ($isPaid && $transaction): ?>
            <div class="inv-txn-box">
                <h6><i class="fas fa-check-circle me-2"></i>Payment Confirmed</h6>
                <div class="inv-txn-row">
                    <strong>Transaction Reference:</strong> &nbsp; <?= htmlspecialchars($transaction['paystack_reference']) ?><br>
                    <strong>Payment Method:</strong> &nbsp; <?= htmlspecialchars(ucfirst($transaction['payment_method'] ?? 'Card')) ?><br>
                    <strong>Amount Paid:</strong> &nbsp; <?= htmlspecialchars($transaction['currency'] ?? '₦') . number_format($transaction['amount'], 2) ?><br>
                    <strong>Date Paid:</strong> &nbsp; <?= $transaction['paid_at'] ? date('M d, Y h:i A', strtotime($transaction['paid_at'])) : date('M d, Y', strtotime($invoice['updated_at'])) ?><br>
                    <strong>Status:</strong> &nbsp; <span style="color:#16a34a;font-weight:700;">✅ SUCCESS</span>
                </div>
            </div>
            <?php elseif ($isPaid): ?>
            <div class="inv-txn-box">
                <h6><i class="fas fa-check-circle me-2"></i>Payment Confirmed</h6>
                <div class="inv-txn-row" style="color:#15803d;">This invoice has been marked as paid.</div>
            </div>
            <?php endif; ?>

        </div><!-- /.inv-body -->

        <!-- Footer -->
        <div class="inv-footer">
            <div class="footer-tagline">
                <strong><?= htmlspecialchars($company['name']) ?></strong><br>
                <?= htmlspecialchars($company['tagline']) ?> · <?= htmlspecialchars($company['website']) ?>
            </div>
            <div class="footer-terms">
                Payment is due by <?= date('M d, Y', strtotime($invoice['due_date'])) ?>.<br>
                Thank you for choosing <?= htmlspecialchars($company['name']) ?>.
            </div>
        </div>

    </div><!-- /.invoice-inner -->
</div><!-- /.invoice-paper -->

<div class="text-center mt-4 no-print" style="color:#94a3b8;font-size:.8rem;">
    This is a system-generated invoice from <?= htmlspecialchars($company['name']) ?>.
    For queries contact <a href="mailto:<?= htmlspecialchars($company['email']) ?>" style="color:#0A2D5E;"><?= htmlspecialchars($company['email']) ?></a>.
</div>

</body>
</html>
