<?php
$path_to_root = "../";
$page_title = "Invoice Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Only admin


// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'create_invoice') {
        $uid = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $currency = in_array($_POST['currency'], ['₦','$','€']) ? $_POST['currency'] : '₦';
        $due = trim($_POST['due_date']);
        $notes = trim($_POST['notes'] ?? '');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if ($uid && $amount > 0 && $due) {
            try {
                $invoiceNum = 'WQS-' . strtoupper(substr(md5(uniqid()), 0, 8));
                $ins = $pdo->prepare("INSERT INTO invoices (user_id, project_id, invoice_number, amount, currency, due_date, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, NOW())");
                $ins->execute([$uid, $projectId, $invoiceNum, $amount, $currency, $due, $notes]);
                $invId = $pdo->lastInsertId();

                // Notify user
                add_notification($uid, "New Invoice: $invoiceNum", "An invoice of {$currency}" . number_format($amount, 2) . " has been issued. Due: $due.", 'invoice', '../user/client-invoices.php', $projectId);

                echo json_encode(['success' => true, 'invoice_number' => $invoiceNum]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
        }
        exit;
    }

    if ($action === 'update_status') {
        $invId = (int)$_POST['invoice_id'];
        $newStatus = in_array($_POST['status'], ['unpaid','paid','overdue']) ? $_POST['status'] : null;
        if ($invId && $newStatus) {
            try {
                $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?")->execute([$newStatus, $invId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        exit;
    }

    if ($action === 'edit_invoice') {
        $invId = (int)$_POST['invoice_id'];
        $uid = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $currency = in_array($_POST['currency'], ['₦','$','€']) ? $_POST['currency'] : '₦';
        $due = trim($_POST['due_date']);
        $notes = trim($_POST['notes'] ?? '');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if ($invId && $uid && $amount > 0 && $due) {
            try {
                $stmt = $pdo->prepare("UPDATE invoices SET user_id=?, project_id=?, amount=?, currency=?, due_date=?, notes=? WHERE id=?");
                $stmt->execute([$uid, $projectId, $amount, $currency, $due, $notes, $invId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
        }
        exit;
    }

    if ($action === 'delete_invoice') {
        $invId = (int)$_POST['invoice_id'];
        try {
            $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    exit;
}

// Fetch all invoices with user info
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all','unpaid','paid','overdue'])) $statusFilter = 'all';

$whereClause = $statusFilter !== 'all' ? "WHERE i.status = ?" : "";
$params = $statusFilter !== 'all' ? [$statusFilter] : [];

try {
    $stmt = $pdo->prepare("
        SELECT i.*, u.name AS client_name, u.email AS client_email, op.title AS project_title
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        LEFT JOIN ongoing_projects op ON i.project_id = op.id
        $whereClause
        ORDER BY i.created_at DESC
    ");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $invoices = [];
}

// Summary stats
$summaryStats = ['total' => 0, 'unpaid' => 0, 'paid' => 0, 'overdue' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'unpaid_amount' => 0];
try {
    $sr = $pdo->query("SELECT COUNT(*) AS cnt, status, SUM(amount) AS total FROM invoices GROUP BY status");
    while ($sr_row = $sr->fetch()) {
        $s = $sr_row['status'];
        $summaryStats[$s] = $sr_row['cnt'];
        $summaryStats['total_amount'] += $sr_row['total'];
        if ($s === 'paid') $summaryStats['paid_amount'] = $sr_row['total'];
        if (in_array($s, ['unpaid','overdue'])) $summaryStats['unpaid_amount'] += $sr_row['total'];
    }
    $summaryStats['total'] = array_sum([$summaryStats['unpaid'], $summaryStats['paid'], $summaryStats['overdue']]);
} catch (Exception $e) {}

// Fetch clients and projects for invoice creation
$clients = [];
try {
    $c = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('user','agent') ORDER BY name");
    $clients = $c->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$projects = [];
try {
    $p = $pdo->query("SELECT id, title, user_id FROM ongoing_projects ORDER BY title");
    $projects = $p->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$transactions = [];
$txnFilterStatus = $_GET['txn_status'] ?? 'all';
$allowedTxnStatuses = ['all','success','failed','pending'];
if (!in_array($txnFilterStatus, $allowedTxnStatuses)) $txnFilterStatus = 'all';
$txnFrom = $_GET['txn_from'] ?? '';
$txnTo   = $_GET['txn_to'] ?? '';

$whereTx = [];
$paramsTx = [];
if ($txnFilterStatus !== 'all') {
    $whereTx[] = "pt.status = ?";
    $paramsTx[] = $txnFilterStatus;
}
if ($txnFrom) {
    $whereTx[] = "pt.created_at >= ?";
    $paramsTx[] = $txnFrom . ' 00:00:00';
}
if ($txnTo) {
    $whereTx[] = "pt.created_at <= ?";
    $paramsTx[] = $txnTo . ' 23:59:59';
}

$whereTxSql = '';
if (!empty($whereTx)) {
    $whereTxSql = 'WHERE ' . implode(' AND ', $whereTx);
}

try {
    $txStmt = $pdo->prepare("SELECT pt.*, i.invoice_number, i.currency, i.user_id, u.name AS client_name, op.title AS project_title
        FROM payment_transactions pt
        LEFT JOIN invoices i ON pt.invoice_id = i.id
        LEFT JOIN users u ON pt.user_id = u.id
        LEFT JOIN ongoing_projects op ON pt.project_id = op.id
        " . $whereTxSql . "
        ORDER BY pt.created_at DESC");
    $txStmt->execute($paramsTx);
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transactions = [];
}

$transactionSummary = ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0];
foreach ($transactions as $tx) {
    $transactionSummary['total']++;
    $transactionSummary[$tx['status']] = ($transactionSummary[$tx['status']] ?? 0) + 1;
}
?>

<style>
.inv-mgmt-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 20px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.inv-mgmt-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.inv-stat-card {
    border-radius: 14px; padding: 1.2rem 1.4rem;
    border: 1px solid transparent; transition: all 0.2s;
    position: relative; overflow: hidden;
}
.inv-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.inv-status-badge {
    padding: 0.25rem 0.75rem; border-radius: 50px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.inv-unpaid  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.inv-paid    { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.inv-overdue { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.inv-table-card {
    background: white; border-radius: 18px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
}
.filter-tab-inv {
    padding: 0.4rem 1rem; border-radius: 50px; border: 1px solid #e2e8f0;
    background: white; color: #64748b; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.filter-tab-inv:hover { border-color: #0A2D5E; color: #0A2D5E; }
.filter-tab-inv.active { background: #0A2D5E; color: white; border-color: #0A2D5E; }
</style>

<!-- Hero -->
<div class="inv-mgmt-hero">
    <div style="position:relative;z-index:1;" class="d-flex flex-md-row flex-column justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Billing Control
                </span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Invoice Management</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">
                <?= $summaryStats['total'] ?> total invoices · ₦<?= number_format($summaryStats['paid_amount'], 0) ?> collected
            </p>
        </div>
        <button class="btn px-4 py-2 fw-bold" style="background:#E15501;border:none;color:white;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#newInvoiceModal">
            <i class="fas fa-plus me-1"></i> Issue Invoice
        </button>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="inv-stat-card" style="background:#eff6ff;border-color:#bfdbfe;">
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#1e40af;margin-bottom:0.4rem;"><i class="fas fa-file-invoice me-1"></i> Total Invoices</div>
            <div style="font-size:2rem;font-weight:900;color:#1d4ed8;line-height:1;"><?= $summaryStats['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card" style="background:#dcfce7;border-color:#86efac;">
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#166534;margin-bottom:0.4rem;"><i class="fas fa-check-circle me-1"></i> Paid</div>
            <div style="font-size:2rem;font-weight:900;color:#15803d;line-height:1;"><?= $summaryStats['paid'] ?></div>
            <div style="font-size:0.75rem;color:#16a34a;margin-top:0.2rem;">₦<?= number_format($summaryStats['paid_amount'], 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card" style="background:#fee2e2;border-color:#fca5a5;">
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#991b1b;margin-bottom:0.4rem;"><i class="fas fa-clock me-1"></i> Unpaid</div>
            <div style="font-size:2rem;font-weight:900;color:#dc2626;line-height:1;"><?= $summaryStats['unpaid'] ?></div>
            <div style="font-size:0.75rem;color:#ef4444;margin-top:0.2rem;">₦<?= number_format($summaryStats['unpaid_amount'], 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card" style="background:#fef3c7;border-color:#fde68a;">
            <div style="font-size:0.72rem;text-transform:uppercase;font-weight:700;color:#92400e;margin-bottom:0.4rem;"><i class="fas fa-exclamation-triangle me-1"></i> Overdue</div>
            <div style="font-size:2rem;font-weight:900;color:#d97706;line-height:1;"><?= $summaryStats['overdue'] ?></div>
        </div>
    </div>
</div>

<!-- Filter + Table -->
<div class="inv-table-card">
    <div class="p-4 border-bottom">
        <div class="page-tab-btns mb-3">
            <button class="page-tab-btn active" data-target="tab-invoices" onclick="switchAdminTab(this)">Invoices</button>
            <button class="page-tab-btn" data-target="tab-transactions" onclick="switchAdminTab(this)">Transaction Ledger</button>
        </div>

        <div id="tab-invoices" class="page-tab-panel active">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <a href="?status=all"    class="filter-tab-inv <?= $statusFilter === 'all'    ? 'active' : '' ?>">All (<?= $summaryStats['total'] ?>)</a>
                <a href="?status=unpaid" class="filter-tab-inv <?= $statusFilter === 'unpaid' ? 'active' : '' ?>">Unpaid (<?= $summaryStats['unpaid'] ?>)</a>
                <a href="?status=paid"   class="filter-tab-inv <?= $statusFilter === 'paid'   ? 'active' : '' ?>">Paid (<?= $summaryStats['paid'] ?>)</a>
                <a href="?status=overdue"class="filter-tab-inv <?= $statusFilter === 'overdue'? 'active' : '' ?>">Overdue (<?= $summaryStats['overdue'] ?>)</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" style="font-size:0.87rem;">
            <thead class="table-light text-muted fw-bold" style="font-size:0.8rem; border-bottom:2px solid rgba(0,0,0,0.05);">
                <tr>
                    <th class="ps-4 py-3">Invoice #</th>
                    <th class="py-3">Client</th>
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
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-file-invoice-dollar d-block mb-3 text-secondary" style="font-size:2rem;"></i>
                        No invoices found. Issue one using the button above.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv):
                    $statusKey = strtolower($inv['status']);
                    $isOverdue = ($statusKey === 'unpaid' && strtotime($inv['due_date']) < time());
                    if ($isOverdue && $statusKey !== 'paid') $statusKey = 'overdue';
                ?>
                <tr id="inv-row-<?= $inv['id'] ?>">
                    <td class="ps-4 py-3">
                        <div class="fw-bold text-body"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= date('M d, Y', strtotime($inv['created_at'])) ?></div>
                    </td>
                    <td class="py-3">
                        <div class="fw-semibold text-body"><?= htmlspecialchars($inv['client_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($inv['client_email'] ?? '') ?></div>
                    </td>
                    <td class="py-3 text-muted"><?= htmlspecialchars($inv['project_title'] ?? 'General') ?></td>
                    <td class="py-3 fw-bold text-body"><?= htmlspecialchars($inv['currency']) . number_format($inv['amount'], 2) ?></td>
                    <td class="py-3 text-muted"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                    <td class="py-3">
                        <span class="inv-status-badge inv-<?= $statusKey ?>">
                            <?php if ($statusKey === 'paid'): ?><i class="fas fa-check-circle me-1"></i>Paid
                            <?php elseif ($statusKey === 'overdue'): ?><i class="fas fa-exclamation-circle me-1"></i>Overdue
                            <?php else: ?><i class="fas fa-clock me-1"></i>Unpaid
                            <?php endif; ?>
                        </span>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <a href="../generate_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3" style="font-size:0.75rem;">
                                <i class="fas fa-file-invoice"></i> View
                            </a>
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size:0.75rem;"
                                onclick="openEditModal(<?= $inv['id'] ?>, <?= (int)$inv['user_id'] ?>, <?= (int)$inv['project_id'] ?>, '<?= htmlspecialchars($inv['currency'], ENT_QUOTES) ?>', <?= (float)$inv['amount'] ?>, '<?= htmlspecialchars($inv['due_date'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($inv['notes'] ?? '')) ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if ($statusKey !== 'paid'): ?>
                            <button class="btn btn-sm btn-success rounded-pill px-3" style="font-size:0.75rem;"
                                onclick="markPaid(<?= $inv['id'] ?>)">
                                <i class="fas fa-check me-1"></i>Mark Paid
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:0.75rem;"
                                onclick="deleteInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['invoice_number'])) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-top text-muted" style="font-size:0.8rem;">
        Showing <strong><?= count($invoices) ?></strong> invoice<?= count($invoices) != 1 ? 's' : '' ?>.
    </div>
</div>

<div id="tab-transactions" class="page-tab-panel" style="display:none;">
    <div class="p-4 border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2">
                <a href="?txn_status=all" class="filter-tab-inv <?= $txnFilterStatus === 'all' ? 'active' : '' ?>">All (<?= $transactionSummary['total'] ?>)</a>
                <a href="?txn_status=success" class="filter-tab-inv <?= $txnFilterStatus === 'success' ? 'active' : '' ?>">Success (<?= $transactionSummary['success'] ?>)</a>
                <a href="?txn_status=failed" class="filter-tab-inv <?= $txnFilterStatus === 'failed' ? 'active' : '' ?>">Failed (<?= $transactionSummary['failed'] ?>)</a>
                <a href="?txn_status=pending" class="filter-tab-inv <?= $txnFilterStatus === 'pending' ? 'active' : '' ?>">Pending (<?= $transactionSummary['pending'] ?>)</a>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <label class="small text-muted mb-0">From</label>
                <input type="date" id="txnFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($txnFrom) ?>">
                <label class="small text-muted mb-0">To</label>
                <input type="date" id="txnTo" class="form-control form-control-sm" value="<?= htmlspecialchars($txnTo) ?>">
                <button class="btn btn-sm btn-outline-primary" onclick="applyTxnFilter()">Filter</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportTransactionsCsv()">Export CSV</button>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0" style="font-size:0.87rem;" id="transactionLedgerTable">
            <thead class="table-light text-muted fw-bold" style="font-size:0.8rem; border-bottom:2px solid rgba(0,0,0,0.05);">
                <tr>
                    <th class="ps-4 py-3">Reference</th>
                    <th class="py-3">Invoice #</th>
                    <th class="py-3">Client</th>
                    <th class="py-3">Project</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Method</th>
                    <th class="py-3">Date</th>
                    <th class="py-3">Status</th>
                    <th class="pe-4 py-3 text-end">Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="fas fa-history d-block mb-3 text-secondary" style="font-size:2rem;"></i>
                        No payment transactions found.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="ps-4 py-3" style="font-family:monospace;"><?= htmlspecialchars($tx['paystack_reference']) ?></td>
                        <td class="py-3"><?= htmlspecialchars($tx['invoice_number']) ?></td>
                        <td class="py-3"><?= htmlspecialchars($tx['client_name'] ?? 'N/A') ?></td>
                        <td class="py-3 text-muted"><?= htmlspecialchars($tx['project_title'] ?? 'General') ?></td>
                        <td class="py-3 fw-bold"><?= htmlspecialchars($tx['currency']) . number_format($tx['amount'], 2) ?></td>
                        <td class="py-3 text-capitalize"><?= htmlspecialchars($tx['payment_method'] ?? 'N/A') ?></td>
                        <td class="py-3 text-muted"><?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?></td>
                        <td class="py-3">
                            <span class="inv-status-badge inv-<?= htmlspecialchars($tx['status']) ?>">
                                <?= htmlspecialchars(ucfirst($tx['status'])) ?>
                            </span>
                        </td>
                        <td class="pe-4 py-3 text-end">
                            <a href="../generate_invoice.php?id=<?= htmlspecialchars($tx['invoice_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size:0.75rem;">
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

<!-- New Invoice Modal -->
<div class="modal fade" id="newInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0A2D5E, #163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Issue New Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Client</label>
                    <select id="inv_client" class="form-select">
                        <option value="">— Select Client —</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['name']) ?> &lt;<?= htmlspecialchars($cl['email']) ?>&gt;</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Linked Project (optional)</label>
                    <select id="inv_project" class="form-select">
                        <option value="">— No specific project —</option>
                        <?php foreach ($projects as $pj): ?>
                            <option value="<?= $pj['id'] ?>"><?= htmlspecialchars($pj['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label fw-semibold small text-muted">Currency</label>
                        <select id="inv_currency" class="form-select">
                            <option value="₦" selected>₦ NGN</option>
                            <option value="$">$ USD</option>
                            <option value="€">€ EUR</option>
                        </select>
                    </div>
                    <div class="col-8">
                        <label class="form-label fw-semibold small text-muted">Amount</label>
                        <input type="number" id="inv_amount" class="form-control" placeholder="e.g. 150000" min="1" step="0.01">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Due Date</label>
                    <input type="date" id="inv_due" class="form-control" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted">Notes / Description (optional)</label>
                    <textarea id="inv_notes" class="form-control" rows="2" placeholder="e.g. Website development deposit — Phase 1"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn flex-grow-1 fw-bold" style="background:#0A2D5E;color:white;border:none;" onclick="submitInvoice()">
                        <i class="fas fa-paper-plane me-1"></i> Issue Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Invoice Modal -->
<div class="modal fade" id="editInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0A2D5E, #163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Edit Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_inv_id">
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Client</label>
                    <select id="edit_inv_client" class="form-select">
                        <option value="">— Select Client —</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['name']) ?> &lt;<?= htmlspecialchars($cl['email']) ?>&gt;</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Linked Project (optional)</label>
                    <select id="edit_inv_project" class="form-select">
                        <option value="">— No specific project —</option>
                        <?php foreach ($projects as $pj): ?>
                            <option value="<?= $pj['id'] ?>"><?= htmlspecialchars($pj['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label fw-semibold small text-muted">Currency</label>
                        <select id="edit_inv_currency" class="form-select">
                            <option value="₦">₦ NGN</option>
                            <option value="$">$ USD</option>
                            <option value="€">€ EUR</option>
                        </select>
                    </div>
                    <div class="col-8">
                        <label class="form-label fw-semibold small text-muted">Amount</label>
                        <input type="number" id="edit_inv_amount" class="form-control" placeholder="e.g. 150000" min="1" step="0.01">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Due Date</label>
                    <input type="date" id="edit_inv_due" class="form-control">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted">Notes / Description (optional)</label>
                    <textarea id="edit_inv_notes" class="form-control" rows="2" placeholder="e.g. Website development deposit — Phase 1"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn flex-grow-1 fw-bold" style="background:#0A2D5E;color:white;border:none;" onclick="submitEditInvoice()">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal(invId, userId, projectId, currency, amount, dueDate, notes) {
    document.getElementById('edit_inv_id').value = invId;
    document.getElementById('edit_inv_client').value = userId;
    document.getElementById('edit_inv_project').value = projectId;
    document.getElementById('edit_inv_currency').value = currency;
    document.getElementById('edit_inv_amount').value = amount;
    document.getElementById('edit_inv_due').value = dueDate;
    document.getElementById('edit_inv_notes').value = notes;

    const editModal = new bootstrap.Modal(document.getElementById('editInvoiceModal'));
    editModal.show();
}

function submitEditInvoice() {
    const invId = document.getElementById('edit_inv_id').value;
    const uid = document.getElementById('edit_inv_client').value;
    const amount = document.getElementById('edit_inv_amount').value;
    const currency = document.getElementById('edit_inv_currency').value;
    const due = document.getElementById('edit_inv_due').value;
    const notes = document.getElementById('edit_inv_notes').value;
    const projectId = document.getElementById('edit_inv_project').value;

    if (!uid || !amount || !due) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please fill in all required fields.', confirmButtonColor: '#0A2D5E' });
        return;
    }

    const body = `ajax_action=edit_invoice&invoice_id=${invId}&user_id=${uid}&amount=${amount}&currency=${encodeURIComponent(currency)}&due_date=${due}&notes=${encodeURIComponent(notes)}&project_id=${projectId}`;

    fetch('invoice_management.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Invoice Updated!', text: 'Changes saved successfully.', confirmButtonColor: '#0A2D5E' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not update invoice.', confirmButtonColor: '#dc3545' });
        }
    });
}

function submitInvoice() {
    const uid = document.getElementById('inv_client').value;
    const amount = document.getElementById('inv_amount').value;
    const currency = document.getElementById('inv_currency').value;
    const due = document.getElementById('inv_due').value;
    const notes = document.getElementById('inv_notes').value;
    const projectId = document.getElementById('inv_project').value;

    if (!uid || !amount || !due) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please fill in all required fields.', confirmButtonColor: '#0A2D5E' });
        return;
    }

    const body = `ajax_action=create_invoice&user_id=${uid}&amount=${amount}&currency=${encodeURIComponent(currency)}&due_date=${due}&notes=${encodeURIComponent(notes)}&project_id=${projectId}`;

    fetch('invoice_management.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Invoice Issued!', text: `Invoice ${data.invoice_number} sent to client.`, confirmButtonColor: '#0A2D5E' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not create invoice.', confirmButtonColor: '#dc3545' });
        }
    });
}

function markPaid(invId) {
    Swal.fire({
        title: 'Mark as Paid?',
        text: 'This will update the invoice status to Paid.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Mark Paid',
        confirmButtonColor: '#15803d'
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch('invoice_management.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=update_status&invoice_id=${invId}&status=paid`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Invoice marked as paid.', confirmButtonColor: '#0A2D5E', timer: 2000 })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed.', confirmButtonColor: '#dc3545' });
            }
        });
    });
}

function deleteInvoice(invId, invNum) {
    Swal.fire({
        title: `Delete Invoice ${invNum}?`,
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d'
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch('invoice_management.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=delete_invoice&invoice_id=${invId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById(`inv-row-${invId}`);
                if (row) row.remove();
                Swal.fire({ icon: 'success', title: 'Deleted', confirmButtonColor: '#0A2D5E', timer: 1800 });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message || '', confirmButtonColor: '#dc3545' });
            }
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
