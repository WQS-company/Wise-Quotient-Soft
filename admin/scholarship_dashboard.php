<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || !in_array(strtolower($userRoleObj['role']), ['admin','developer'])) {
    header("Location: " . $path_to_root . "login.php"); exit;
}

$page_title = "Scholarship Dashboard";
$current_page = "scholarship_dashboard.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$stats = [];
try {
    $stats['total_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
    $stats['active_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE is_active=1 AND status='published'")->fetchColumn();
    $stats['closed_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status='closed'")->fetchColumn();
    $stats['total_applicants'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
    $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='submitted'")->fetchColumn();
    $stats['approved'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='approved' OR status='awarded'")->fetchColumn();
    $stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='rejected'")->fetchColumn();
    $stats['shortlisted'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='shortlisted'")->fetchColumn();
    $stats['male'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE gender='male'")->fetchColumn();
    $stats['female'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE gender='female'")->fetchColumn();
    $stats['funds'] = $pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='disbursed'")->fetchColumn();
    $stats['interviews'] = $pdo->query("SELECT COUNT(*) FROM application_interviews WHERE interview_date >= CURDATE() AND status='scheduled'")->fetchColumn();
} catch (Exception $e) { $stats = array_fill_keys(['total_scholarships','active_scholarships','closed_scholarships','total_applicants','pending','approved','rejected','shortlisted','male','female','funds','interviews'], 0); }

$recentApps = [];
try {
    $recentApps = $pdo->query("SELECT sa.*, s.title as scholarship_title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id ORDER BY sa.submitted_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$monthlyData = [];
try {
    $monthlyData = $pdo->query("SELECT DATE_FORMAT(submitted_at,'%b %Y') as month, COUNT(*) as count FROM scholarship_applications WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month, DATE_FORMAT(submitted_at,'%Y%m') ORDER BY DATE_FORMAT(submitted_at,'%Y%m')")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$statusData = [];
try {
    $statusData = $pdo->query("SELECT status, COUNT(*) as count FROM scholarship_applications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
?>

<style>
.stat-card{border-radius:14px;padding:1.25rem;background:var(--color-card-bg);border:1px solid var(--color-border);transition:all .3s ease;position:relative;overflow:hidden}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.stat-card .stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.stat-card .stat-value{font-size:1.8rem;font-weight:800;color:var(--color-text);line-height:1}
.stat-card .stat-label{font-size:.78rem;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-top:.25rem}
.chart-container{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:14px;padding:1.5rem;min-height:300px}
.app-row{display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--color-border)}
.app-row:last-child{border-bottom:none}
.app-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0}
.badge-status{padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:600}
</style>

<div class="container-fluid px-3 px-lg-4">
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:2rem;margin-bottom:1.5rem;color:white;">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1"><i class="fas fa-graduation-cap me-2"></i>Scholarship Dashboard</h4>
                <p class="mb-0 opacity-75" style="font-size:.88rem;">Manage scholarships, applications, and awards</p>
            </div>
            <a href="<?= $path_to_root ?>admin/scholarship_create.php" class="btn btn-warning fw-bold mt-2 mt-md-0"><i class="fas fa-plus me-1"></i> New Scholarship</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Total Scholarships', $stats['total_scholarships'], 'fas fa-award', '#3b82f6'],
            ['Active', $stats['active_scholarships'], 'fas fa-check-circle', '#10b981'],
            ['Total Applicants', $stats['total_applicants'], 'fas fa-users', '#8b5cf6'],
            ['Pending Review', $stats['pending'], 'fas fa-clock', '#f59e0b'],
            ['Approved', $stats['approved'], 'fas fa-user-check', '#22c55e'],
            ['Rejected', $stats['rejected'], 'fas fa-user-times', '#ef4444'],
            ['Male Applicants', $stats['male'], 'fas fa-male', '#3b82f6'],
            ['Female Applicants', $stats['female'], 'fas fa-female', '#ec4899'],
            ['Funds Disbursed', '₦' . number_format($stats['funds']), 'fas fa-naira-sign', '#10b981'],
            ['Upcoming Interviews', $stats['interviews'], 'fas fa-video', '#6366f1'],
            ['Shortlisted', $stats['shortlisted'], 'fas fa-star', '#f59e0b'],
            ['Closed', $stats['closed_scholarships'], 'fas fa-lock', '#64748b'],
        ];
        foreach ($cards as $i => $c):
        ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:<?= $c[3] ?>15;color:<?= $c[3] ?>;"><i class="<?= $c[2] ?>"></i></div>
                    <div>
                        <div class="stat-value"><?= $c[1] ?></div>
                        <div class="stat-label"><?= $c[0] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-container">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Applications</h6>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Status Distribution</h6>
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="chart-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Recent Applications</h6>
                    <a href="<?= $path_to_root ?>admin/scholarship_applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php if (empty($recentApps)): ?>
                    <p class="text-muted text-center py-4">No applications yet</p>
                <?php else: ?>
                    <?php foreach ($recentApps as $app): ?>
                        <div class="app-row">
                            <div class="app-avatar" style="background:<?= $app['gender']==='female' ? '#ec4899' : '#3b82f6' ?>;">
                                <?= strtoupper(substr($app['full_name'], 0, 2)) ?>
                            </div>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="fw-semibold" style="font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($app['full_name']) ?></div>
                                <div style="font-size:.75rem;color:var(--color-text-secondary);"><?= htmlspecialchars($app['scholarship_title']) ?></div>
                            </div>
                            <span class="badge-status" style="background:<?php
                                $statusColors = ['submitted'=>'#fef3c7;#92400e','under_review'=>'#dbeafe;#1e40af','shortlisted'=>'#fef3c7;#92400e','approved'=>'#d1fae5;#065f46','rejected'=>'#fee2e2;#991b1b','awarded'=>'#d1fae5;#065f46'];
                                echo $statusColors[$app['status']] ?? '#f1f5f9;#475569';
                            ?>;"><?= ucwords(str_replace('_',' ',$app['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-container">
                <h6 class="fw-bold mb-3"><i class="fas fa-award me-2 text-primary"></i>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="<?= $path_to_root ?>admin/scholarship_create.php" class="btn btn-primary text-start"><i class="fas fa-plus-circle me-2"></i>Create New Scholarship</a>
                    <a href="<?= $path_to_root ?>admin/scholarship_applications.php" class="btn btn-outline-primary text-start"><i class="fas fa-file-alt me-2"></i>Review Applications <span class="badge bg-warning ms-auto"><?= $stats['pending'] ?></span></a>
                    <a href="<?= $path_to_root ?>admin/scholarship_shortlisted.php" class="btn btn-outline-warning text-start"><i class="fas fa-star me-2"></i>Shortlisted Candidates <span class="badge bg-info ms-auto"><?= $stats['shortlisted'] ?></span></a>
                    <a href="<?= $path_to_root ?>admin/scholarship_interviews.php" class="btn btn-outline-info text-start"><i class="fas fa-video me-2"></i>Upcoming Interviews <span class="badge bg-success ms-auto"><?= $stats['interviews'] ?></span></a>
                    <a href="<?= $path_to_root ?>admin/scholarship_categories.php" class="btn btn-outline-secondary text-start"><i class="fas fa-tags me-2"></i>Manage Categories</a>
                    <a href="<?= $path_to_root ?>admin/scholarship_sponsors.php" class="btn btn-outline-secondary text-start"><i class="fas fa-handshake me-2"></i>Manage Sponsors</a>
                    <a href="<?= $path_to_root ?>admin/scholarship_reports.php" class="btn btn-outline-secondary text-start"><i class="fas fa-chart-pie me-2"></i>Reports & Analytics</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthlyData = <?= json_encode($monthlyData) ?>;
    const statusData = <?= json_encode($statusData) ?>;

    if (document.getElementById('monthlyChart')) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(r => r.month),
                datasets: [{
                    label: 'Applications',
                    data: monthlyData.map(r => r.count),
                    backgroundColor: 'rgba(59,130,246,0.8)',
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    if (document.getElementById('statusChart')) {
        const statusLabels = Object.keys(statusData).map(s => s.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
        const statusColors = ['#f59e0b','#3b82f6','#f97316','#8b5cf6','#10b981','#ef4444','#22c55e'];
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{ data: Object.values(statusData), backgroundColor: statusColors, borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } }
        });
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
