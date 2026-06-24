<?php
$path_to_root = "../";
$page_title = "Payroll & Commission Dashboard";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// === HR Stats ===
try {
    // Employee count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hr_employees WHERE employment_status = 'active'");
    $activeEmployees = $stmt->fetchColumn();
    
    // Partner count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hr_partners WHERE status = 'active'");
    $activePartners = $stmt->fetchColumn();
    
    // Total payroll this month
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM hr_payroll_periods WHERE DATE_FORMAT(start_date, '%Y-%m') = ? AND status = 'paid'");
    $stmt->execute([$currentMonth]);
    $totalPayrollPaid = $stmt->fetchColumn();
    
    // Pending commissions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hr_commissions WHERE status = 'pending'");
    $pendingCommissions = $stmt->fetchColumn();
    
    // Total commissions paid
    $stmt = $pdo->query("SELECT COALESCE(SUM(commission_amount), 0) FROM hr_commissions WHERE status = 'paid'");
    $totalCommissionsPaid = $stmt->fetchColumn();
    
    // Pending payouts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hr_payouts WHERE status = 'pending'");
    $pendingPayouts = $stmt->fetchColumn();
} catch (Exception $e) {
    $activeEmployees = 0;
    $activePartners = 0;
    $totalPayrollPaid = 0;
    $pendingCommissions = 0;
    $totalCommissionsPaid = 0;
    $pendingPayouts = 0;
}
?>

<style>
.hr-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 60%, #1a4a8a 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.hr-hero::before {
    content:''; position:absolute; top:-80px; right:-80px;
    width:280px; height:280px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.hr-stat-card {
    border-radius: 16px; padding: 1.5rem;
    border: 1px solid #e2e8f0; background: white;
    transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.hr-stat-card:hover {
    transform: translateY(-3px); box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.hr-stat-card .icon-wrap {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; margin-bottom: 1rem;
}
.hr-stat-card .stat-value {
    font-size: 2rem; font-weight: 900; line-height: 1; margin-bottom: 0.25rem;
}
.hr-stat-card .stat-label { font-size: 0.85rem; font-weight: 600; color: #64748b; }

.hr-section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem;
}
.hr-section-header h5 { font-weight: 700; color: #0A2D5E; margin: 0; }

.hr-quick-action {
    border-radius: 12px; padding: 1.25rem;
    background: white; border: 1px solid #e2e8f0;
    cursor: pointer; transition: all 0.2s;
}
.hr-quick-action:hover {
    border-color: #2563eb; background: #eff6ff;
}
.hr-quick-action .icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 0.75rem;
}
.hr-quick-action h6 { font-weight: 700; margin-bottom: 0.25rem; }
.hr-quick-action p { font-size: 0.8rem; color: #64748b; margin: 0; }
</style>

<!-- Hero Section -->
<div class="hr-hero">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <h2 style="font-weight:900; margin-bottom:0.5rem;">Payroll & Commission System</h2>
            <p style="color:rgba(255,255,255,0.8); margin-bottom:0;">
                Manage employees, partners, payroll, commissions, and financial operations
            </p>
        </div>
        <div class="col-md-4 text-end d-none d-md-block">
            <i class="fas fa-coins" style="font-size:5rem; opacity:0.2;"></i>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #2563eb, #1d4ed8); color:white;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $activeEmployees ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #16a34a, #15803d); color:white;">
                <i class="fas fa-handshake"></i>
            </div>
            <div class="stat-value"><?= $activePartners ?></div>
            <div class="stat-label">Active Partners</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #e11d48, #be123c); color:white;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value">₦<?= number_format($totalPayrollPaid, 2) ?></div>
            <div class="stat-label">Payroll Paid This Month</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #7c3aed, #6d28d9); color:white;">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-value"><?= $pendingCommissions ?></div>
            <div class="stat-label">Pending Commissions</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #059669, #047857); color:white;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value">₦<?= number_format($totalCommissionsPaid, 2) ?></div>
            <div class="stat-label">Total Commissions Paid</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="hr-stat-card">
            <div class="icon-wrap" style="background:linear-gradient(135deg, #f59e0b, #d97706); color:white;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?= $pendingPayouts ?></div>
            <div class="stat-label">Pending Payouts</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-4">
    <div class="hr-section-header">
        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
    </div>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <a href="payroll-employees.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#dbeafe; color:#2563eb;"><i class="fas fa-user-tie"></i></div>
                    <h6 class="text-dark">Employees</h6>
                    <p>Manage employees & teams</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-partners.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#dcfce7; color:#16a34a;"><i class="fas fa-handshake"></i></div>
                    <h6 class="text-dark">Partners</h6>
                    <p>Manage partners & commissions</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-periods.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#fff7ed; color:#ea580c;"><i class="fas fa-calendar-check"></i></div>
                    <h6 class="text-dark">Payroll</h6>
                    <p>Generate & manage payroll</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-settings.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#f3f4f6; color:#4b5563;"><i class="fas fa-cog"></i></div>
                    <h6 class="text-dark">Settings</h6>
                    <p>Configure system settings</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-revenue.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#ede9fe; color:#7c3aed;"><i class="fas fa-chart-pie"></i></div>
                    <h6 class="text-dark">Revenue Share</h6>
                    <p>Allocate project revenue</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-payouts.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#fef3c7; color:#f59e0b;"><i class="fas fa-exchange-alt"></i></div>
                    <h6 class="text-dark">Payouts</h6>
                    <p>Process salary & commission payouts</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-reports.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#eff6ff; color:#1d4ed8;"><i class="fas fa-file-alt"></i></div>
                    <h6 class="text-dark">Reports</h6>
                    <p>Financial & payroll reports</p>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="payroll-audit.php" class="text-decoration-none">
                <div class="hr-quick-action">
                    <div class="icon" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-history"></i></div>
                    <h6 class="text-dark">Audit Log</h6>
                    <p>System activity log</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
