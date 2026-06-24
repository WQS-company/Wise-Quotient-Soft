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

$page_title = "Reports & Analytics";
$current_page = "scholarship_reports.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$stats = ['total_apps'=>0,'total_awards'=>0,'total_disbursed'=>0,'total_scholarships'=>0];
try {
    $stats['total_apps'] = (int)$pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
    $stats['total_awards'] = (int)$pdo->query("SELECT COUNT(*) FROM scholarship_awards")->fetchColumn();
    $stats['total_disbursed'] = (float)$pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='disbursed'")->fetchColumn();
    $stats['total_scholarships'] = (int)$pdo->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.scr-stat{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:14px;padding:1rem 1.25rem;transition:all .3s}
.scr-stat:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.06)}
.scr-stat .scr-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.scr-stat .scr-val{font-size:1.5rem;font-weight:800;line-height:1}
.scr-stat .scr-lbl{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:.2rem}
.scr-card{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1.5rem;min-height:300px}
.scr-card h6{font-weight:700;font-size:.95rem;margin-bottom:1rem}
.report-type-btn{padding:.5rem 1rem;border:2px solid var(--color-border);border-radius:10px;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;background:var(--color-card-bg);color:var(--color-text)}
.report-type-btn:hover{border-color:#3b82f6;color:#3b82f6}
.report-type-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
@media(max-width:767.98px){.scr-stat .scr-val{font-size:1.1rem}}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-pie me-2"></i>Reports & Analytics</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Comprehensive scholarship data analysis and reporting</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadReport()"><i class="fas fa-sync me-1"></i> Refresh</button>
            <div class="dropdown">
                <button class="btn btn-warning fw-bold btn-sm rounded-pill dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-download me-1"></i> Export</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="fas fa-file-pdf me-2 text-danger"></i>Export PDF</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('excel')"><i class="fas fa-file-excel me-2 text-success"></i>Export Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('csv')"><i class="fas fa-file-csv me-2 text-primary"></i>Export CSV</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <?php
        $statCards = [
            ['Total Applications','fas fa-file-alt',$stats['total_apps'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Total Awards','fas fa-award',$stats['total_awards'],'#10b981','rgba(16,185,129,.12)'],
            ['Total Disbursed','₦' . number_format($stats['total_disbursed']),'fas fa-naira-sign','#f59e0b','rgba(245,158,11,.12)'],
            ['Scholarships',$stats['total_scholarships'],'fas fa-graduation-cap','#8b5cf6','rgba(139,92,246,.12)'],
        ];
        foreach ($statCards as $s): ?>
        <div class="col-6 col-md-3">
            <div style="background:rgba(255,255,255,.07);border-radius:12px;padding:.6rem .75rem;display:flex;align-items:center;gap:.6rem">
                <div style="width:32px;height:32px;border-radius:8px;background:<?= $s[4] ?>;display:flex;align-items:center;justify-content:center;color:<?= $s[3] ?>;font-size:.8rem"><i class="<?= $s[2] ?>"></i></div>
                <div><div style="font-size:1.1rem;font-weight:800;line-height:1"><?= $s[1] ?></div><div style="font-size:.62rem;opacity:.7"><?= $s[0] ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Report Type Selector -->
<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="d-flex flex-wrap gap-2">
        <button class="report-type-btn active" onclick="selectReport('monthly',this)"><i class="fas fa-calendar me-1"></i> Monthly</button>
        <button class="report-type-btn" onclick="selectReport('by_state',this)"><i class="fas fa-map-marker-alt me-1"></i> By State</button>
        <button class="report-type-btn" onclick="selectReport('by_institution',this)"><i class="fas fa-university me-1"></i> By Institution</button>
        <button class="report-type-btn" onclick="selectReport('gender',this)"><i class="fas fa-users me-1"></i> Gender</button>
        <button class="report-type-btn" onclick="selectReport('by_scholarship',this)"><i class="fas fa-graduation-cap me-1"></i> By Scholarship</button>
        <button class="report-type-btn" onclick="selectReport('approval_rate',this)"><i class="fas fa-chart-line me-1"></i> Approval Rate</button>
    </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="scr-card">
            <h6 id="chartTitle"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Applications</h6>
            <canvas id="mainChart" height="280"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="scr-card">
            <h6><i class="fas fa-chart-pie me-2 text-primary"></i>Status Distribution</h6>
            <canvas id="pieChart" height="280"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="scr-card">
            <h6><i class="fas fa-chart-line me-2 text-primary"></i>Award Trends</h6>
            <canvas id="lineChart" height="250"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="scr-card">
            <h6><i class="fas fa-chart-bar me-2 text-primary"></i>Disbursement Overview</h6>
            <canvas id="barChart" height="250"></canvas>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const API = '../api/scholarship_api.php';
let currentReport = 'monthly';
let mainChartInstance = null;
let pieChartInstance = null;
let lineChartInstance = null;
let barChartInstance = null;

function selectReport(type, btn) {
    currentReport = type;
    document.querySelectorAll('.report-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadReport();
}

async function loadReport() {
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'reports', report_type: currentReport }) });
        const result = await resp.json();

        if (!result.success) { alert(result.error || 'Error loading report'); return; }

        const data = result.data || {};
        renderCharts(data);
    } catch (err) { alert('Error: ' + err.message); }
}

function renderCharts(data) {
    const chartColors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#f97316','#14b8a6','#6366f1'];

    if (mainChartInstance) mainChartInstance.destroy();
    if (pieChartInstance) pieChartInstance.destroy();
    if (lineChartInstance) lineChartInstance.destroy();
    if (barChartInstance) barChartInstance.destroy();

    const titles = {
        monthly: 'Monthly Applications',
        by_state: 'Applications by State',
        by_institution: 'Applications by Institution',
        gender: 'Gender Distribution',
        by_scholarship: 'Applications by Scholarship',
        approval_rate: 'Approval Rate'
    };
    document.getElementById('chartTitle').innerHTML = `<i class="fas fa-chart-bar me-2 text-primary"></i>${titles[currentReport] || 'Report'}`;

    const mainData = data.main || {labels:[], values:[]};
    const statusData = data.status || {labels:[], values:[]};
    const trendData = data.trends || {labels:[], values:[]};
    const disbData = data.disbursement || {labels:[], values:[]};

    if (mainData.labels && mainData.labels.length) {
        const ctx = document.getElementById('mainChart');
        if (currentReport === 'gender' || currentReport === 'approval_rate') {
            mainChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: mainData.labels,
                    datasets: [{ data: mainData.values, backgroundColor: chartColors.slice(0, mainData.labels.length), borderWidth: 0 }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } }
            });
        } else {
            mainChartInstance = new Chart(ctx, {
                type: currentReport === 'approval_rate' ? 'line' : 'bar',
                data: {
                    labels: mainData.labels,
                    datasets: [{
                        label: 'Count',
                        data: mainData.values,
                        backgroundColor: chartColors.slice(0, mainData.labels.length).map(c => c + 'CC'),
                        borderColor: chartColors.slice(0, mainData.labels.length),
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    }

    if (statusData.labels && statusData.labels.length) {
        pieChartInstance = new Chart(document.getElementById('pieChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{ data: statusData.values, backgroundColor: chartColors.slice(0, statusData.labels.length), borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8 } } } }
        });
    }

    if (trendData.labels && trendData.labels.length) {
        lineChartInstance = new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Awards',
                    data: trendData.values,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#3b82f6'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    if (disbData.labels && disbData.labels.length) {
        barChartInstance = new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: disbData.labels,
                datasets: [{
                    label: 'Amount',
                    data: disbData.values,
                    backgroundColor: ['#10b981CC','#f59e0bCC','#3b82f6CC','#ef4444CC'],
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
        });
    }
}

function exportReport(format) {
    alert('Export as ' + format.toUpperCase() + ' will be available once the export endpoint is configured.');
    return false;
}

document.addEventListener('DOMContentLoaded', () => loadReport());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
