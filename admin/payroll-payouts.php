<?php
$path_to_root = "../";
$page_title = "Payouts";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Handle AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        if ($act === 'create_payout') {
            $pdo->beginTransaction();
            
            $pdo->prepare("
                INSERT INTO hr_payouts (recipient_type, recipient_id, recipient_name, amount, currency, payment_method, status, notes, created_by) 
                VALUES (?, ?, ?, ?, 'NGN', ?, 'pending', ?, ?)
            ")->execute([
                $_POST['recipient_type'],
                $_POST['recipient_id'] ?: null,
                $_POST['recipient_name'],
                $_POST['amount'],
                $_POST['payment_method'],
                $_POST['notes'],
                $user_id
            ]);
            $payoutId = $pdo->lastInsertId();
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payout created!']);
            exit;
        }
        
        if ($act === 'mark_complete') {
            $pdo->prepare("UPDATE hr_payouts SET status = 'completed', completed_by = ?, completed_at = NOW(), transaction_ref = ? WHERE id = ?")
                ->execute([$user_id, $_POST['transaction_ref'], $_POST['payout_id']]);
            echo json_encode(['success' => true, 'message' => 'Payout marked complete!']);
            exit;
        }
        
        if ($act === 'cancel_payout') {
            $pdo->prepare("UPDATE hr_payouts SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW() WHERE id = ?")
                ->execute([$user_id, $_POST['payout_id']]);
            echo json_encode(['success' => true, 'message' => 'Payout cancelled!']);
            exit;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get data
try {
    $payouts = $pdo->query("
        SELECT hp.*, u1.name as created_by_name, u2.name as completed_by_name 
        FROM hr_payouts hp 
        LEFT JOIN users u1 ON hp.created_by = u1.id 
        LEFT JOIN users u2 ON hp.completed_by = u2.id 
        ORDER BY hp.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees, partners
    $employees = $pdo->query("SELECT e.*, u.name FROM hr_employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.employment_status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    $partners = $pdo->query("SELECT * FROM hr_partners WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payouts = [];
    $employees = [];
    $partners = [];
}

// Calculate stats
$pendingCount = 0; $pendingAmount = 0;
$completedCount = 0; $completedAmount = 0;
$totalPayouts = 0; $totalAmount = 0;

foreach ($payouts as $p) {
    $totalPayouts++;
    $totalAmount += $p['amount'];
    if ($p['status'] === 'pending') {
        $pendingCount++;
        $pendingAmount += $p['amount'];
    } elseif ($p['status'] === 'completed') {
        $completedCount++;
        $completedAmount += $p['amount'];
    }
}
?>

<style>
.hr-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.hr-card-header { padding: 1.25rem; border-bottom: 1px solid #e2e8f0; }
.hr-card-header h5 { margin: 0; font-weight: 700; color: #0A2D5E; }
.hr-card-body { padding: 1.5rem; }
.status-badge { padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Payouts</h3>
    <button class="btn btn-primary rounded-pill ms-auto" data-bs-toggle="modal" data-bs-target="#createPayoutModal">
        <i class="fas fa-plus me-2"></i> Create Payout
    </button>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Pending Payouts</div>
                <div class="fw-bold text-primary" style="font-size:1.8rem;"><?= $pendingCount ?></div>
                <div class="text-muted small mt-1">₦<?= number_format($pendingAmount, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Completed Payouts</div>
                <div class="fw-bold text-success" style="font-size:1.8rem;"><?= $completedCount ?></div>
                <div class="text-muted small mt-1">₦<?= number_format($completedAmount, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Total Payouts</div>
                <div class="fw-bold" style="font-size:1.8rem; color:#0A2D5E;"><?= $totalPayouts ?></div>
                <div class="text-muted small mt-1">₦<?= number_format($totalAmount, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">This Month</div>
                <div class="fw-bold" style="font-size:1.8rem; color:#2563eb;">₦<?= number_format($totalAmount, 2) ?></div>
                <div class="text-muted small mt-1"><?= date('F Y') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Payouts List -->
<div class="hr-card">
    <div class="hr-card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-exchange-alt me-2"></i> All Payouts</h5>
    </div>
    <div class="hr-card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Recipient</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Transaction Ref</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payouts)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No payouts yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($payouts as $p): ?>
                            <tr>
                                <td><?= date('M j, Y H:i', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($p['recipient_name']) ?></div>
                                    <?php if ($p['notes']): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($p['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= ucfirst($p['recipient_type']) ?></td>
                                <td class="fw-bold">₦<?= number_format($p['amount'], 2) ?></td>
                                <td><?= ucfirst($p['payment_method']) ?></td>
                                <td><span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                                <td><?= htmlspecialchars($p['transaction_ref'] ?? '-') ?></td>
                                <td>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <button class="btn btn-success btn-sm me-1" onclick="markComplete(<?= $p['id'] ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="cancelPayout(<?= $p['id'] ?>)">
                                            <i class="fas fa-times"></i>
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

<!-- Create Payout Modal -->
<div class="modal fade" id="createPayoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create New Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPayoutForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Type *</label>
                        <select class="form-select" name="recipient_type" id="payout_recipient_type" required>
                            <option value="employee">Employee</option>
                            <option value="partner">Partner</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3" id="payout_recipient_select_div">
                        <label class="form-label small fw-semibold">Recipient *</label>
                        <select class="form-select" name="recipient_id" id="payout_recipient_select">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Name</label>
                        <input type="text" class="form-control" name="recipient_name" id="payout_recipient_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (₦) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount" required placeholder="100000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paystack">Paystack</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about this payout"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Payout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Complete Modal -->
<div class="modal fade" id="markCompleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Mark Payout as Complete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="markCompleteForm">
                <input type="hidden" name="payout_id" id="complete_payout_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Transaction Reference</label>
                        <input type="text" class="form-control" name="transaction_ref" placeholder="Enter transaction reference or ID" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Complete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function markComplete(payoutId) {
    document.getElementById('complete_payout_id').value = payoutId;
    new bootstrap.Modal(document.getElementById('markCompleteModal')).show();
}

function cancelPayout(payoutId) {
    Swal.fire({
        title: 'Cancel this payout?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Cancel'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'cancel_payout');
            fd.append('payout_id', payoutId);
            fetch('payroll-payouts.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Cancelled!', text: d.message, timer:1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

document.getElementById('payout_recipient_type').addEventListener('change', function() {
    const select = document.getElementById('payout_recipient_select');
    const nameInput = document.getElementById('payout_recipient_name');
    const type = this.value;
    select.innerHTML = '';
    
    if (type === 'employee') {
        <?php foreach ($employees as $e): ?>
            const optE = document.createElement('option');
            optE.value = <?= $e['id'] ?>;
            optE.textContent = '<?= htmlspecialchars($e['name'] ?? $e['position'], ENT_QUOTES) ?>';
            select.appendChild(optE);
        <?php endforeach; ?>
    } else if (type === 'partner') {
        <?php foreach ($partners as $p): ?>
            const optP = document.createElement('option');
            optP.value = <?= $p['id'] ?>;
            optP.textContent = '<?= htmlspecialchars($p['full_name'], ENT_QUOTES) ?>';
            select.appendChild(optP);
        <?php endforeach; ?>
    } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Type a name...';
        select.appendChild(opt);
    }
    
    if (select.options.length > 0) {
        nameInput.value = select.options[0].textContent;
    }
});

document.getElementById('payout_recipient_select').addEventListener('change', function() {
    document.getElementById('payout_recipient_name').value = this.options[this.selectedIndex].textContent;
});

document.getElementById('createPayoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'create_payout');
    Swal.fire({ title:'Creating...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-payouts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Created!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});

document.getElementById('markCompleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'mark_complete');
    Swal.fire({ title:'Updating...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-payouts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Completed!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});

// Initialize recipient select on page load
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('payout_recipient_type');
    if (typeSelect) {
        typeSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
