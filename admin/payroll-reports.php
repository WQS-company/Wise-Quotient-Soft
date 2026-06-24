<?php
$path_to_root = "../";
$page_title = "Reports";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$reportType = $_GET['type'] ?? 'payroll';

// Get data based on report type
$data = [];
$title = '';
try {
    switch ($reportType) {
        case 'payroll':
            $title = 'Payroll Reports';
            $data = $pdo->prepare("
                SELECT 
                    pp.*,
                    COUNT(pe.id) as employee_count,
                    SUM(pe.net_salary) as total_net,
                    SUM(pe.basic_salary) as total_basic,
                    SUM(pe.allowances) as total_allowances,
                    SUM(pe.bonuses) as total_bonuses,
                    SUM(pe.deductions) as total_deductions,
                    SUM(pe.tax) as total_tax,
                    SUM(pe.pension) as total_pension
                FROM hr_payroll_periods pp
                LEFT JOIN hr_payroll_entries pe ON pp.id = pe.payroll_period_id
                WHERE pp.created_at BETWEEN ? AND ?
                GROUP BY pp.id
                ORDER BY pp.created_at DESC
            ");
            $data->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $data = $data->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'payouts':
            $title = 'Payout Reports';
            $data = $pdo->prepare("
                SELECT hp.*
                FROM hr_payouts hp
                WHERE hp.created_at BETWEEN ? AND ?
                ORDER BY hp.created_at DESC
            ");
            $data->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $data = $data->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'revenue':
            $title = 'Revenue Sharing Reports';
            $data = $pdo->prepare("
                SELECT 
                    hra.*,
                    SUM(hrai.amount) as total_allocated,
                    COUNT(hrai.id) as allocation_items
                FROM hr_revenue_allocations hra
                LEFT JOIN hr_revenue_allocation_items hrai ON hra.id = hrai.allocation_id
                WHERE hra.created_at BETWEEN ? AND ?
                GROUP BY hra.id
                ORDER BY hra.created_at DESC
            ");
            $data->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $data = $data->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'employees':
            $title = 'Employee Reports';
            $data = $pdo->query("
                SELECT 
                    e.*,
                    u.name as user_name,
                    d.name as department_name,
                    t.name as team_name
                FROM hr_employees e
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN hr_departments d ON e.department_id = d.id
                LEFT JOIN hr_teams t ON e.team_id = t.id
                ORDER BY e.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    $data = [];
}

// Calculate totals
$totalAmount = 0;
foreach ($data as $item) {
    if (isset($item['total_net'])) $totalAmount += $item['total_net'];
    elseif (isset($item['amount'])) $totalAmount += $item['amount'];
    elseif (isset($item['project_value'])) $totalAmount += $item['project_value'];
}
?>

<style>
.hr-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.hr-card-header { padding: 1.25rem; border-bottom: 1px solid #e2e8f0; }
.hr-card-header h5 { margin: 0; font-weight: 700; color: #0A2D5E; }
.hr-card-body { padding: 1.5rem; }
.nav-pills .nav-link.active { background-color: #2563eb; }
.status-badge { padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; }
.status-draft { background: #e2e8f0; color: #64748b; }
.status-approved { background: #dbeafe; color: #1d4ed8; }
.status-paid { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Reports</h3>
    <button class="btn btn-success rounded-pill ms-auto" onclick="exportReport()">
        <i class="fas fa-download me-2"></i> Export CSV
    </button>
</div>

<!-- Filters -->
<div class="hr-card mb-4">
    <div class="hr-card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Report Type</label>
                <select class="form-select" name="type" id="report_type">
                    <option value="payroll" <?= $reportType === 'payroll' ? 'selected' : '' ?>>Payroll</option>
                    <option value="payouts" <?= $reportType === 'payouts' ? 'selected' : '' ?>>Payouts</option>
                    <option value="revenue" <?= $reportType === 'revenue' ? 'selected' : '' ?>>Revenue Sharing</option>
                    <option value="employees" <?= $reportType === 'employees' ? 'selected' : '' ?>>Employees</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Total Records</div>
                <div class="fw-bold text-primary" style="font-size:2rem;"><?= count($data) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Total Amount</div>
                <div class="fw-bold text-success" style="font-size:2rem;">₦<?= number_format($totalAmount, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="hr-card">
            <div class="hr-card-body">
                <div class="text-muted small">Date Range</div>
                <div class="fw-bold" style="font-size:1.2rem;"><?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Report Data -->
<div class="hr-card">
    <div class="hr-card-header">
        <h5><i class="fas fa-chart-bar me-2"></i> <?= htmlspecialchars($title) ?></h5>
    </div>
    <div class="hr-card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="reportTable">
                <?php if ($reportType === 'payroll'): ?>
                    <thead>
                        <tr>
                            <th>Period Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Employees</th>
                            <th>Total Basic</th>
                            <th>Allowances</th>
                            <th>Bonuses</th>
                            <th>Deductions</th>
                            <th>Tax</th>
                            <th>Pension</th>
                            <th>Net Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr><td colspan="12" class="text-center py-4 text-muted">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['period_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($row['start_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($row['end_date'])) ?></td>
                                    <td><?= $row['employee_count'] ?></td>
                                    <td>₦<?= number_format($row['total_basic'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_allowances'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_bonuses'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_deductions'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_tax'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_pension'], 2) ?></td>
                                    <td class="fw-bold text-primary">₦<?= number_format($row['total_net'], 2) ?></td>
                                    <td><span class="status-badge status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php elseif ($reportType === 'payouts'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction Ref</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= date('M j, Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($row['recipient_name']) ?></td>
                                    <td><?= ucfirst($row['recipient_type']) ?></td>
                                    <td class="fw-bold">₦<?= number_format($row['amount'], 2) ?></td>
                                    <td><?= ucfirst($row['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($row['transaction_ref'] ?? '-') ?></td>
                                    <td><span class="status-badge status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php elseif ($reportType === 'revenue'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project ID</th>
                            <th>Project Value</th>
                            <th>Allocated</th>
                            <th>Company Retained</th>
                            <th>Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= date('M j, Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($row['project_id']) ?></td>
                                    <td class="fw-bold">₦<?= number_format($row['project_value'], 2) ?></td>
                                    <td>₦<?= number_format($row['total_allocated'] ?? 0, 2) ?></td>
                                    <td class="fw-bold text-success">₦<?= number_format($row['company_retained_amount'], 2) ?></td>
                                    <td><?= $row['allocation_items'] ?? 0 ?></td>
                                    <td><span class="status-badge status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php else: // employees ?>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['user_name'] ?? $row['position']) ?></td>
                                    <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['team_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['position']) ?></td>
                                    <td class="fw-bold">₦<?= number_format($row['salary'], 2) ?></td>
                                    <td><?= $row['hire_date'] ? date('M j, Y', strtotime($row['hire_date'])) : '-' ?></td>
                                    <td><span class="status-badge status-<?= $row['employment_status'] ?>"><?= ucfirst($row['employment_status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const table = document.getElementById('reportTable');
    let csv = [];
    
    for (let i = 0; i < table.rows.length; i++) {
        const row = [];
        for (let j = 0; j < table.rows[i].cells.length; j++) {
            const text = table.rows[i].cells[j].innerText.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const reportName = document.getElementById('report_type').value + '_' + 
        new Date().toISOString().slice(0,10) + '.csv';
    link.setAttribute('href', url);
    link.setAttribute('download', reportName);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        icon: 'success',
        title: 'Exported!',
        text: 'Report exported to CSV successfully',
        timer: 1500
    });
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
