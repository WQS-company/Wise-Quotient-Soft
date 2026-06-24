<?php
$path_to_root = "../";
$page_title = "Revenue Sharing";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Handle AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        if ($act === 'create_allocation') {
            $project_value = floatval($_POST['project_value']);
            $stmt = $pdo->prepare("INSERT INTO hr_revenue_allocations (project_id, project_value, total_allocated_percent, company_retained_percent, company_retained_amount, status) VALUES (?, ?, 0, 100, ?, 'draft')");
            $stmt->execute([$_POST['project_id'], $project_value, $project_value]);
            echo json_encode(['success' => true, 'message' => 'Allocation created!', 'id' => $pdo->lastInsertId()]);
            exit;
        }
        
        if ($act === 'add_allocation_item') {
            $stmt = $pdo->prepare("INSERT INTO hr_revenue_allocation_items (allocation_id, recipient_type, recipient_id, recipient_name, percent, amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['allocation_id'],
                $_POST['recipient_type'],
                $_POST['recipient_id'] ?: null,
                $_POST['recipient_name'],
                $_POST['percent'],
                $_POST['amount'],
                $_POST['notes']
            ]);
            
            // Update totals
            updateAllocationTotals($_POST['allocation_id'], $pdo);
            echo json_encode(['success' => true, 'message' => 'Item added!']);
            exit;
        }
        
        if ($act === 'remove_item') {
            $pdo->prepare("DELETE FROM hr_revenue_allocation_items WHERE id = ?")->execute([$_POST['item_id']]);
            updateAllocationTotals($_POST['allocation_id'], $pdo);
            echo json_encode(['success' => true, 'message' => 'Item removed!']);
            exit;
        }
        
        if ($act === 'finalize_allocation') {
            $pdo->prepare("UPDATE hr_revenue_allocations SET status = 'finalized', finalized_by = ?, finalized_at = NOW() WHERE id = ?")
                ->execute([$user_id, $_POST['allocation_id']]);
            echo json_encode(['success' => true, 'message' => 'Allocation finalized!']);
            exit;
        }
        
        if ($act === 'update_rule') {
            $stmt = $pdo->prepare("UPDATE hr_revenue_rules SET partner_percent = ?, project_manager_percent = ?, dev_team_percent = ?, design_team_percent = ? WHERE id = ?");
            $stmt->execute([$_POST['partner_percent'], $_POST['pm_percent'], $_POST['dev_percent'], $_POST['design_percent'], $_POST['rule_id']]);
            echo json_encode(['success' => true, 'message' => 'Rule updated!']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

function updateAllocationTotals($allocation_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM hr_revenue_allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $alloc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT SUM(percent) as total_pct, SUM(amount) as total_amt FROM hr_revenue_allocation_items WHERE allocation_id = ?");
    $stmt->execute([$allocation_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_pct = floatval($totals['total_pct'] ?? 0);
    $company_pct = 100 - $total_pct;
    $company_amt = $alloc['project_value'] - floatval($totals['total_amt'] ?? 0);
    
    $pdo->prepare("UPDATE hr_revenue_allocations SET total_allocated_percent = ?, company_retained_percent = ?, company_retained_amount = ? WHERE id = ?")
        ->execute([$total_pct, $company_pct, $company_amt, $allocation_id]);
}

// Get data
try {
    $allocations = $pdo->query("
        SELECT ra.*, u.name as finalized_by_name 
        FROM hr_revenue_allocations ra 
        LEFT JOIN users u ON ra.finalized_by = u.id 
        ORDER BY ra.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $rules = $pdo->query("SELECT * FROM hr_revenue_rules WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees, partners, projects
    $employees = $pdo->query("SELECT e.*, u.name FROM hr_employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.employment_status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    $partners = $pdo->query("SELECT * FROM hr_partners WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects from existing project tables
    $projects = [];
    try {
        // Try ongoing projects first
        $projects = $pdo->query("SELECT id, title FROM ongoing_projects")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        try {
            // Try projects table
            $projects = $pdo->query("SELECT id, title FROM projects")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {}
    }
} catch (Exception $e) {
    $allocations = [];
    $rules = [];
    $employees = [];
    $partners = [];
    $projects = [];
}

$selected_allocation = null;
$allocation_items = [];
if (isset($_GET['allocation_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM hr_revenue_allocations WHERE id = ?");
        $stmt->execute([$_GET['allocation_id']]);
        $selected_allocation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM hr_revenue_allocation_items WHERE allocation_id = ?");
        $stmt->execute([$_GET['allocation_id']]);
        $allocation_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
.progress-bar-custom {
    height: 8px; border-radius: 4px; background: #e2e8f0;
}
.progress-fill {
    height: 100%; border-radius: 4px;
}
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Revenue Sharing</h3>
    <button class="btn btn-primary rounded-pill ms-auto" data-bs-toggle="modal" data-bs-target="#createAllocationModal">
        <i class="fas fa-plus me-2"></i> New Allocation
    </button>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-chart-pie me-2"></i> Revenue Rules</h5>
            </div>
            <div class="hr-card-body">
                <?php if (empty($rules)): ?>
                    <div class="text-center py-3 text-muted">No rules configured.</div>
                <?php else: ?>
                    <?php foreach ($rules as $rule): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-body mb-2"><?= htmlspecialchars($rule['name']) ?></div>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><small class="text-muted">Partner: <?= $rule['partner_percent'] ?>%</small></div>
                                <div class="col-6"><small class="text-muted">PM: <?= $rule['project_manager_percent'] ?>%</small></div>
                                <div class="col-6"><small class="text-muted">Dev Team: <?= $rule['dev_team_percent'] ?>%</small></div>
                                <div class="col-6"><small class="text-muted">Design: <?= $rule['design_team_percent'] ?>%</small></div>
                            </div>
                            <button class="btn btn-outline-primary btn-sm w-100" onclick='editRule(<?= json_encode($rule) ?>)'>Edit Rule</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="hr-card">
            <div class="hr-card-header">
                <h5><i class="fas fa-list me-2"></i> All Allocations</h5>
            </div>
            <div class="hr-card-body">
                <?php if (empty($allocations)): ?>
                    <div class="text-center py-3 text-muted">No allocations yet.</div>
                <?php else: ?>
                    <?php foreach ($allocations as $alloc): ?>
                        <a href="?allocation_id=<?= $alloc['id'] ?>" class="text-decoration-none d-block mb-3">
                            <div class="p-3 rounded-12 border" style="border-radius:12px; border:1px solid #e2e8f0; <?= $selected_allocation && $selected_allocation['id'] == $alloc['id'] ? 'background:#eff6ff;border-color:#93c5fd;' : '' ?>">
                                <div class="fw-bold text-body">Project #<?= $alloc['project_id'] ?></div>
                                <div class="text-muted small">Total: ₦<?= number_format($alloc['project_value'], 2) ?></div>
                                <div class="mt-1">
                                    <span class="badge" style="background:<?= $alloc['status'] == 'finalized' ? '#16a34a' : '#64748b' ?>; color:white; padding:0.25rem 0.75rem; border-radius:50px;">
                                        <?= ucfirst($alloc['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <?php if ($selected_allocation): ?>
            <div class="hr-card mb-4">
                <div class="hr-card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-coins me-2"></i> Allocation Details</h5>
                    <?php if ($selected_allocation['status'] == 'draft'): ?>
                        <button class="btn btn-success btn-sm" onclick="finalizeAllocation(<?= $selected_allocation['id'] ?>)">
                            <i class="fas fa-check me-1"></i> Finalize
                        </button>
                    <?php endif; ?>
                </div>
                <div class="hr-card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="text-muted small">Project Value</div>
                            <div class="fw-bold text-primary" style="font-size:1.5rem;">₦<?= number_format($selected_allocation['project_value'], 2) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Allocated</div>
                            <div class="fw-bold" style="font-size:1.5rem;"><?= number_format($selected_allocation['total_allocated_percent'], 2) ?>%</div>
                            <div class="progress-bar-custom mt-2">
                                <div class="progress-fill" style="width:<?= min(100, $selected_allocation['total_allocated_percent']) ?>%; background:#2563eb;"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Company Retained</div>
                            <div class="fw-bold text-success" style="font-size:1.5rem;">₦<?= number_format($selected_allocation['company_retained_amount'], 2) ?></div>
                            <div class="text-muted small">(<?= number_format($selected_allocation['company_retained_percent'], 2) ?>%)</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Allocation Items</h6>
                        <?php if (empty($allocation_items)): ?>
                            <div class="text-center py-3 text-muted">No items added yet.</div>
                        <?php else: ?>
                            <?php foreach ($allocation_items as $item): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 rounded-12 mb-2" style="background:#f8fafc; border-radius:12px;">
                                    <div>
                                        <div class="fw-bold text-body"><?= htmlspecialchars($item['recipient_name']) ?></div>
                                        <div class="text-muted small"><?= ucfirst($item['recipient_type']) ?> • <?= $item['percent'] ?>%</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="fw-bold text-primary">₦<?= number_format($item['amount'], 2) ?></div>
                                        <?php if ($selected_allocation['status'] == 'draft'): ?>
                                            <button class="btn btn-outline-danger btn-sm" onclick="removeItem(<?= $item['id'] ?>, <?= $selected_allocation['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($selected_allocation['status'] == 'draft'): ?>
                        <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus me-2"></i> Add Allocation Item
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="hr-card">
                <div class="hr-card-body text-center py-5">
                    <i class="fas fa-hand-pointer" style="font-size:3rem; opacity:0.5;"></i>
                    <h5 class="mt-3">Select an allocation</h5>
                    <p class="text-muted">Choose an allocation from the list or create a new one.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Allocation Modal -->
<div class="modal fade" id="createAllocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create New Allocation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createAllocationForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project *</label>
                        <select class="form-select" name="project_id" required>
                            <option value="">Choose a project...</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                            <?php if (empty($projects)): ?>
                                <option value="1">Sample Project</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project Value (₦) *</label>
                        <input type="number" step="0.01" class="form-control" name="project_value" required placeholder="1000000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Allocation Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm">
                <input type="hidden" name="allocation_id" value="<?= $selected_allocation['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Type *</label>
                        <select class="form-select" name="recipient_type" id="recipient_type" required>
                            <option value="partner">Partner</option>
                            <option value="employee">Employee</option>
                            <option value="team">Team</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3" id="recipient_select_div">
                        <label class="form-label small fw-semibold">Recipient *</label>
                        <select class="form-select" name="recipient_id" id="recipient_select">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Name</label>
                        <input type="text" class="form-control" name="recipient_name" id="recipient_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Percent (%) *</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control" name="percent" id="alloc_percent" required onchange="calculateAmount()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (₦) *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="alloc_amount" required onchange="calculatePercent()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Revenue Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRuleForm">
                <input type="hidden" name="rule_id" id="edit_rule_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Partner Percent (%)</label>
                            <input type="number" step="0.01" class="form-control" name="partner_percent" id="edit_partner_pct" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Project Manager Percent (%)</label>
                            <input type="number" step="0.01" class="form-control" name="pm_percent" id="edit_pm_pct" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Dev Team Percent (%)</label>
                            <input type="number" step="0.01" class="form-control" name="dev_percent" id="edit_dev_pct" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Design Team Percent (%)</label>
                            <input type="number" step="0.01" class="form-control" name="design_percent" id="edit_design_pct" min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let projectValue = <?= $selected_allocation['project_value'] ?? 0 ?>;

function calculateAmount() {
    const pct = parseFloat(document.getElementById('alloc_percent').value) || 0;
    document.getElementById('alloc_amount').value = (pct * projectValue / 100).toFixed(2);
}

function calculatePercent() {
    const amt = parseFloat(document.getElementById('alloc_amount').value) || 0;
    document.getElementById('alloc_percent').value = projectValue > 0 ? ((amt / projectValue) * 100).toFixed(2) : 0;
}

function finalizeAllocation(id) {
    Swal.fire({
        title: 'Finalize Allocation?',
        text: 'This will lock the allocation and prevent further changes.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Yes, Finalize'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'finalize_allocation');
            fd.append('allocation_id', id);
            fetch('payroll-revenue.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Finalized!', text: d.message, timer:1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

function removeItem(itemId, allocId) {
    Swal.fire({
        title: 'Remove this item?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Remove'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'remove_item');
            fd.append('item_id', itemId);
            fd.append('allocation_id', allocId);
            fetch('payroll-revenue.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Removed!', text: d.message, timer:1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

function editRule(rule) {
    document.getElementById('edit_rule_id').value = rule.id;
    document.getElementById('edit_partner_pct').value = rule.partner_percent;
    document.getElementById('edit_pm_pct').value = rule.project_manager_percent;
    document.getElementById('edit_dev_pct').value = rule.dev_team_percent;
    document.getElementById('edit_design_pct').value = rule.design_team_percent;
    new bootstrap.Modal(document.getElementById('editRuleModal')).show();
}

document.getElementById('recipient_type').addEventListener('change', function() {
    const select = document.getElementById('recipient_select');
    const nameInput = document.getElementById('recipient_name');
    const type = this.value;
    select.innerHTML = '';
    
    if (type === 'partner') {
        <?php foreach ($partners as $p): ?>
            const optP = document.createElement('option');
            optP.value = <?= $p['id'] ?>;
            optP.textContent = '<?= htmlspecialchars($p['full_name'], ENT_QUOTES) ?>';
            select.appendChild(optP);
        <?php endforeach; ?>
    } else if (type === 'employee') {
        <?php foreach ($employees as $e): ?>
            const optE = document.createElement('option');
            optE.value = <?= $e['id'] ?>;
            optE.textContent = '<?= htmlspecialchars($e['name'] ?? $e['position'], ENT_QUOTES) ?>';
            select.appendChild(optE);
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

document.getElementById('recipient_select').addEventListener('change', function() {
    document.getElementById('recipient_name').value = this.options[this.selectedIndex].textContent;
});

document.getElementById('createAllocationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'create_allocation');
    Swal.fire({ title:'Creating...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-revenue.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Created!', text:d.message, timer:1500 }).then(() => window.location.href = '?allocation_id=' + d.id);
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});

document.getElementById('addItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'add_allocation_item');
    Swal.fire({ title:'Adding...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-revenue.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Added!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});

document.getElementById('editRuleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'update_rule');
    Swal.fire({ title:'Saving...', allowOutsideClick:false, didOpen: () => Swal.showLoading() });
    fetch('payroll-revenue.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Saved!', text:d.message, timer:1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.message });
            }
        });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
