<?php
$path_to_root = "../";
$page_title = "Payroll Periods";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Handle AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        if ($act === 'create_period') {
            $stmt = $pdo->prepare("INSERT INTO hr_payroll_periods (period_name, start_date, end_date, status, created_by, total_amount) VALUES (?, ?, ?, 'draft', ?, 0)");
            $stmt->execute([$_POST['period_name'], $_POST['start_date'], $_POST['end_date'], $user_id]);
            $period_id = $pdo->lastInsertId();
            
            // Add all active employees to payroll
            $employees = $pdo->query("SELECT id, salary FROM hr_employees WHERE employment_status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            $total = 0;
            foreach ($employees as $emp) {
                // Calculate tax and pension (use default settings if available)
                $tax_pct = 7.5; $pension_pct = 8;
                $tax = ($emp['salary'] * $tax_pct) / 100;
                $pension = ($emp['salary'] * $pension_pct) / 100;
                $net = $emp['salary'] - $tax - $pension;
                
                $pdo->prepare("INSERT INTO hr_payroll_entries (payroll_period_id, employee_id, entry_type, basic_salary, allowances, bonuses, deductions, tax, pension, net_salary, status) VALUES (?, ?, 'employee', ?, 0, 0, 0, ?, ?, ?, 'pending')")
                    ->execute([$period_id, $emp['id'], $emp['salary'], $tax, $pension, $net]);
                $total += $net;
            }

            // Add partner commissions from completed projects within the period
            $partnerCommissionPct = 10;
            try {
                $setRow = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'")->fetch(PDO::FETCH_ASSOC);
                if ($setRow) $partnerCommissionPct = (float)$setRow['setting_value'];
            } catch (Exception $e) {}
            $pctRate = $partnerCommissionPct / 100;

            $partnerUsers = $pdo->query("SELECT id, default_commission_percent FROM hr_partners WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($partnerUsers as $partner) {
                $effectivePct = ($partner['default_commission_percent'] > 0) ? (float)$partner['default_commission_percent'] : $partnerCommissionPct;
                $rate = $effectivePct / 100;
                $partnerUserId = $partner['id'];

                // Sum completed projects for this partner's referrals within the period date range
                $commStmt = $pdo->prepare("
                    SELECT IFNULL(SUM(op.budget * ?), 0) AS commission_ngn
                    FROM ongoing_projects op
                    INNER JOIN users u ON op.user_id = u.id
                    WHERE u.referred_by = ? AND op.status = 'completed'
                    AND op.updated_at BETWEEN ? AND ?
                ");
                $commStmt->execute([$rate, $partnerUserId, $_POST['start_date'], $_POST['end_date']]);
                $commissionNg = (float)$commStmt->fetchColumn();

                if ($commissionNg > 0) {
                    $pdo->prepare("INSERT INTO hr_payroll_entries (payroll_period_id, employee_id, entry_type, partner_user_id, basic_salary, allowances, bonuses, deductions, tax, pension, net_salary, status, notes) VALUES (?, NULL, 'partner', ?, ?, 0, 0, 0, 0, 0, ?, 'pending', ?)")
                        ->execute([$period_id, $partnerUserId, $commissionNg, $commissionNg, "Partner commission ($effectivePct%) for period " . $_POST['start_date'] . ' to ' . $_POST['end_date']]);
                    $total += $commissionNg;
                }
            }
            
            $pdo->prepare("UPDATE hr_payroll_periods SET total_amount = ? WHERE id = ?")->execute([$total, $period_id]);
            log_audit('payroll', 'create_period', null, ['period_name' => $_POST['period_name'], 'period_id' => $period_id]);
            echo json_encode(['success' => true, 'message' => 'Payroll period created!']);
            exit;
        }
        
        if ($act === 'approve_period') {
            $stmt = $pdo->prepare("SELECT * FROM hr_payroll_periods WHERE id = ?");
            $stmt->execute([$_POST['period_id']]);
            $old_period = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $pdo->prepare("UPDATE hr_payroll_periods SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$user_id, $_POST['period_id']]);
            $pdo->prepare("UPDATE hr_payroll_entries SET status = 'approved' WHERE payroll_period_id = ?")
                ->execute([$_POST['period_id']]);
                
            log_audit('payroll', 'approve_period', $old_period, ['status' => 'approved']);
            echo json_encode(['success' => true, 'message' => 'Payroll approved!']);
            exit;
        }
        
        if ($act === 'mark_paid') {
            $stmt = $pdo->prepare("SELECT * FROM hr_payroll_periods WHERE id = ?");
            $stmt->execute([$_POST['period_id']]);
            $old_period = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $pdo->prepare("UPDATE hr_payroll_periods SET status = 'paid', paid_at = NOW() WHERE id = ?")
                ->execute([$_POST['period_id']]);
            $pdo->prepare("UPDATE hr_payroll_entries SET status = 'paid' WHERE payroll_period_id = ?")
                ->execute([$_POST['period_id']]);
                
            log_audit('payroll', 'mark_paid', $old_period, ['status' => 'paid']);
            echo json_encode(['success' => true, 'message' => 'Payroll marked as paid!']);
            exit;
        }
        
        if ($act === 'update_entry') {
            $stmt = $pdo->prepare("SELECT * FROM hr_payroll_entries WHERE id = ?");
            $stmt->execute([$_POST['entry_id']]);
            $old_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $net = $_POST['basic_salary'] + $_POST['allowances'] + $_POST['bonuses'] - $_POST['deductions'] - $_POST['tax'] - $_POST['pension'];
            $stmt = $pdo->prepare("UPDATE hr_payroll_entries SET basic_salary = ?, allowances = ?, bonuses = ?, deductions = ?, tax = ?, pension = ?, net_salary = ? WHERE id = ?");
            $stmt->execute([$_POST['basic_salary'], $_POST['allowances'], $_POST['bonuses'], $_POST['deductions'], $_POST['tax'], $_POST['pension'], $net, $_POST['entry_id']]);
            
            // Update total for period
            $stmt = $pdo->prepare("SELECT SUM(net_salary) as total FROM hr_payroll_entries WHERE payroll_period_id = ?");
            $stmt->execute([$_POST['period_id']]);
            $total = $stmt->fetchColumn();
            $pdo->prepare("UPDATE hr_payroll_periods SET total_amount = ? WHERE id = ?")->execute([$total, $_POST['period_id']]);
            
            log_audit('payroll', 'update_entry', $old_entry, [
                'entry_id' => $_POST['entry_id'],
                'basic_salary' => $_POST['basic_salary'],
                'net_salary' => $net
            ]);
            echo json_encode(['success' => true, 'message' => 'Entry updated!']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get payroll periods
try {
    $periods = $pdo->query("
        SELECT pp.*, u1.name as created_by_name, u2.name as approved_by_name 
        FROM hr_payroll_periods pp 
        LEFT JOIN users u1 ON pp.created_by = u1.id 
        LEFT JOIN users u2 ON pp.approved_by = u2.id 
        ORDER BY pp.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for selects
    $employees = $pdo->query("
        SELECT e.*, u.name as user_name, d.name as department_name 
        FROM hr_employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN hr_departments d ON e.department_id = d.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $periods = [];
    $employees = [];
}

// If a specific period is selected, get its entries
$selected_period = null;
$entries = [];
if (isset($_GET['period_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM hr_payroll_periods WHERE id = ?");
        $stmt->execute([$_GET['period_id']]);
        $selected_period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT pe.*, e.position, 
                   CASE WHEN pe.entry_type = 'partner' THEN u2.name ELSE u.name END as employee_name,
                   pe.entry_type, pe.partner_user_id
            FROM hr_payroll_entries pe 
            LEFT JOIN hr_employees e ON pe.employee_id = e.id 
            LEFT JOIN users u ON e.user_id = u.id 
            LEFT JOIN users u2 ON pe.partner_user_id = u2.id
            WHERE pe.payroll_period_id = ?
        ");
        $stmt->execute([$_GET['period_id']]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<style>
.hr-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.hr-card-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}
.hr-card-header h5 { margin: 0; font-weight: 700; color: #0A2D5E; }
.hr-card-body { padding: 1.5rem; }
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
}
.status-draft { background: #e2e8f0; color: #64748b; }
.status-approved { background: #dbeafe; color: #1d4ed8; }
.status-paid { background: #dcfce7; color: #16a34a; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Payroll Periods</h3>
    <button class="btn btn-primary rounded-pill ms-auto" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
        <i class="fas fa-plus me-2"></i> Create Payroll Period
    </button>
</div>

<div class="row g-4">
    <div class="<?= $selected_period ? 'col-lg-4' : 'col-12' ?>">
        <div class="hr-card">
            <div class="hr-card-header">
                <h5><i class="fas fa-calendar-alt me-2"></i> All Payroll Periods</h5>
            </div>
            <div class="hr-card-body">
                <?php if (empty($periods)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times" style="font-size:2rem; opacity:0.5;"></i>
                        <p class="mb-0">No payroll periods yet. Create one to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($periods as $period): ?>
                        <a href="?period_id=<?= $period['id'] ?>" class="text-decoration-none d-block mb-3">
                            <div class="p-3 rounded-12 border" style="border-radius:12px; border:1px solid #e2e8f0; <?= $selected_period && $selected_period['id'] == $period['id'] ? 'background:#eff6ff;border-color:#93c5fd;' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-body"><?= htmlspecialchars($period['period_name']) ?></div>
                                        <div class="text-muted small">
                                            <?= date('M j, Y', strtotime($period['start_date'])) ?> - <?= date('M j, Y', strtotime($period['end_date'])) ?>
                                        </div>
                                        <div class="text-primary fw-bold mt-1">₦<?= number_format($period['total_amount'], 2) ?></div>
                                    </div>
                                    <span class="status-badge status-<?= $period['status'] ?>"><?= ucfirst($period['status']) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($selected_period): ?>
        <div class="col-lg-8">
            <div class="hr-card mb-4">
                <div class="hr-card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-file-invoice-dollar me-2"></i> <?= htmlspecialchars($selected_period['period_name']) ?> - Payroll Entries</h5>
                    <div class="d-flex gap-2">
                        <?php if ($selected_period['status'] === 'draft'): ?>
                            <button class="btn btn-success btn-sm" onclick="approvePeriod(<?= $selected_period['id'] ?>)">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        <?php endif; ?>
                        <?php if ($selected_period['status'] === 'approved'): ?>
                            <button class="btn btn-primary btn-sm" onclick="markPaid(<?= $selected_period['id'] ?>)">
                                <i class="fas fa-money-bill-wave me-1"></i> Mark as Paid
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hr-card-body">
                    <?php if (empty($entries)): ?>
                        <div class="text-center py-4 text-muted">No entries in this payroll period.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Tax</th>
                                        <th>Pension</th>
                                        <th>Net</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($entry['employee_name'] ?? 'Unknown') ?>
                                            </td>
                                            <td>
                                                <?php if (($entry['entry_type'] ?? 'employee') === 'partner'): ?>
                                                    <span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:50px;font-size:0.72rem;font-weight:600;">Partner</span>
                                                <?php else: ?>
                                                    <span style="background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:50px;font-size:0.72rem;font-weight:600;">Employee</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₦<?= number_format($entry['basic_salary'], 2) ?></td>
                                            <td>₦<?= number_format($entry['tax'], 2) ?></td>
                                            <td>₦<?= number_format($entry['pension'], 2) ?></td>
                                            <td class="fw-bold text-primary">₦<?= number_format($entry['net_salary'], 2) ?></td>
                                            <td><span class="status-badge status-<?= $entry['status'] ?>"><?= ucfirst($entry['status']) ?></span></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick='editEntry(<?= json_encode($entry) ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Create Period Modal -->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPeriodForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Period Name *</label>
                        <input type="text" class="form-control" name="period_name" required placeholder="e.g., June 2026 Payroll">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Period</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Entry Modal -->
<div class="modal fade" id="editEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Payroll Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEntryForm">
                <input type="hidden" name="entry_id" id="edit_entry_id">
                <input type="hidden" name="period_id" value="<?= $selected_period['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Basic Salary</label>
                        <input type="number" step="0.01" class="form-control" name="basic_salary" id="edit_basic_salary">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Allowances</label>
                            <input type="number" step="0.01" class="form-control" name="allowances" id="edit_allowances" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bonuses</label>
                            <input type="number" step="0.01" class="form-control" name="bonuses" id="edit_bonuses" value="0">
                        </div>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Deductions</label>
                            <input type="number" step="0.01" class="form-control" name="deductions" id="edit_deductions" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tax</label>
                            <input type="number" step="0.01" class="form-control" name="tax" id="edit_tax" value="0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Pension</label>
                        <input type="number" step="0.01" class="form-control" name="pension" id="edit_pension" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approvePeriod(id) {
    Swal.fire({
        title: 'Approve Payroll?',
        text: 'This will approve all entries in this payroll period.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Yes, Approve'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'approve_period');
            fd.append('period_id', id);
            fetch('payroll-periods.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Approved!', text: d.message, timer:1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

function markPaid(id) {
    Swal.fire({
        title: 'Mark as Paid?',
        text: 'This will mark the entire payroll period as paid.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Mark as Paid'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'mark_paid');
            fd.append('period_id', id);
            fetch('payroll-periods.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Paid!', text: d.message, timer:1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

function editEntry(entry) {
    document.getElementById('edit_entry_id').value = entry.id;
    document.getElementById('edit_basic_salary').value = entry.basic_salary;
    document.getElementById('edit_allowances').value = entry.allowances;
    document.getElementById('edit_bonuses').value = entry.bonuses;
    document.getElementById('edit_deductions').value = entry.deductions;
    document.getElementById('edit_tax').value = entry.tax;
    document.getElementById('edit_pension').value = entry.pension;
    new bootstrap.Modal(document.getElementById('editEntryModal')).show();
}

document.getElementById('createPeriodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'create_period');
    Swal.fire({ title:'Creating...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-periods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Created!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});

document.getElementById('editEntryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'update_entry');
    Swal.fire({ title:'Updating...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-periods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Updated!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
