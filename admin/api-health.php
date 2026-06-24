<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$path_to_root = "../";
$page_title = "API Health Monitor";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']['id'])) {
    header("Location: {$path_to_root}login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
$user_role = strtolower($userRoleObj['role'] ?? '');

if ($user_role !== 'admin') {
    header("Location: {$path_to_root}dashboard");
    exit;
}

require_once dirname(__DIR__) . '/includes/api_health_monitor.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_POST['ajax_action'] === 'run_checks') {
            $tStart = microtime(true);
            $results = APIHealthMonitor::runAllChecks();
            $elapsed = round(microtime(true) - $tStart, 2);
            $okCount = 0;
            $warnCount = 0;
            $failCount = 0;
            foreach ($results as $r) {
                if ($r['status'] === 'ok') $okCount++;
                elseif ($r['status'] === 'warning') $warnCount++;
                else $failCount++;
            }
            echo json_encode([
                'success' => true,
                'results' => $results,
                'elapsed' => $elapsed,
                'summary' => ['ok' => $okCount, 'warning' => $warnCount, 'fail' => $failCount, 'total' => count($results)]
            ]);
            exit;
        }
        if ($_POST['ajax_action'] === 'get_logs') {
            $service = $_POST['service'] ?? '';
            $logs = APIHealthMonitor::getRecentLogs(50, $service);
            echo json_encode(['success' => true, 'logs' => $logs]);
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Run checks on page load for display
$healthResults = APIHealthMonitor::runAllChecks();
$recentLogs = APIHealthMonitor::getRecentLogs(30);
$statusSummary = APIHealthMonitor::getStatusSummary();
?>

<style>
.health-hero {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white; margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
}
.health-hero::before {
    content: ''; position: absolute; top: -40%; right: -10%;
    width: 250px; height: 250px;
    background: radial-gradient(circle, rgba(16,185,129,0.2), transparent 70%); border-radius: 50%;
}
.health-hero * { position: relative; z-index: 1; }
.health-card {
    background: var(--color-card-bg); border: 1.5px solid var(--color-border);
    border-radius: 14px; padding: 1.25rem; transition: all 0.2s;
}
.health-card:hover { border-color: #10b981; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
.health-status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px;
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
}
.health-status-ok { background: #dcfce7; color: #166534; }
.health-status-fail { background: #fef2f2; color: #991b1b; }
.health-status-warning { background: #fef3c7; color: #92400e; }
.health-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.health-dot-ok { background: #22c55e; }
.health-dot-fail { background: #ef4444; animation: pulse-dot 1.5s infinite; }
.health-dot-warning { background: #f59e0b; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
.health-log-row { padding: 0.75rem 1rem; border-bottom: 1px solid var(--color-border); font-size: 0.85rem; }
.health-log-row:last-child { border-bottom: none; }
.health-filter-btn {
    padding: 4px 12px; border-radius: 50px; border: 1.5px solid var(--color-border);
    font-size: 0.72rem; font-weight: 600; color: var(--color-text-light);
    background: var(--color-card-bg); cursor: pointer; transition: all 0.15s;
}
.health-filter-btn:hover { border-color: #10b981; color: #10b981; }
.health-filter-btn.active { background: #10b981; color: white; border-color: #10b981; }

/* Premium Success Modal */
@keyframes wqs-check-scale { 0% { transform: scale(0); opacity: 0; } 50% { transform: scale(1.15); } 100% { transform: scale(1); opacity: 1; } }
@keyframes wqs-check-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.35); } 50% { box-shadow: 0 0 0 14px rgba(16,185,129,0); } }
@keyframes wqs-draw-check { 0% { stroke-dashoffset: 30; } 100% { stroke-dashoffset: 0; } }
@keyframes wqs-ring-glow { 0%, 100% { opacity: 0.4; } 50% { opacity: 0.8; } }
@keyframes wqs-fade-up { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
@keyframes wqs-badge-pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes wqs-stat-in { 0% { opacity: 0; transform: translateY(8px) scale(0.97); } 100% { opacity: 1; transform: translateY(0) scale(1); } }

.wqs-success-icon {
    width: 50px; height: 50px; border-radius: 50%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 0.6rem; position: relative;
    animation: wqs-check-scale 0.5s cubic-bezier(0.34,1.56,0.64,1) forwards,
               wqs-check-pulse 2s ease-in-out 0.6s infinite;
}
.wqs-success-icon::before {
    content: ''; position: absolute; inset: -3px; border-radius: 50%;
    border: 2px solid rgba(16,185,129,0.25);
    animation: wqs-ring-glow 2s ease-in-out infinite;
}
.wqs-success-icon svg { width: 24px; height: 24px; }
.wqs-check-path {
    stroke: white; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
    fill: none; stroke-dasharray: 30; stroke-dashoffset: 30;
    animation: wqs-draw-check 0.4s ease-out 0.3s forwards;
}
.wqs-modal-title {
    font-size: 1.05rem; font-weight: 700; color: var(--color-text, #1a1a2e);
    text-align: center; margin-bottom: 0.15rem;
    animation: wqs-fade-up 0.4s ease-out 0.5s both;
}
.wqs-modal-subtitle {
    font-size: 0.78rem; color: var(--color-text-light, #6b7280);
    text-align: center; margin-bottom: 0.85rem;
    animation: wqs-fade-up 0.4s ease-out 0.6s both;
}
.wqs-result-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.4rem 0.55rem; border-radius: 8px; margin-bottom: 4px;
    background: var(--color-card-bg, #f9fafb); border: 1px solid var(--color-border, #e5e7eb);
    animation: wqs-fade-up 0.35s ease-out both;
}
.wqs-result-row:nth-child(1) { animation-delay: 0.7s; }
.wqs-result-row:nth-child(2) { animation-delay: 0.8s; }
.wqs-result-row:nth-child(3) { animation-delay: 0.9s; }
.wqs-result-row:nth-child(4) { animation-delay: 1.0s; }
.wqs-result-label { font-size: 0.75rem; font-weight: 600; color: var(--color-text, #1a1a2e); }
.wqs-result-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 7px; border-radius: 50px;
    font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    animation: wqs-badge-pop 0.3s ease-out both;
}
.wqs-badge-ok { background: #dcfce7; color: #166534; }
.wqs-badge-warning { background: #fef3c7; color: #92400e; }
.wqs-badge-fail { background: #fef2f2; color: #991b1b; }
.wqs-divider { display: none; }
.wqs-metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.wqs-metric-card {
    background: var(--color-card-bg, #f9fafb); border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 8px; padding: 0.4rem 0.55rem; text-align: center;
    animation: wqs-stat-in 0.35s ease-out both;
}
.wqs-metric-card:nth-child(1) { animation-delay: 1.1s; }
.wqs-metric-card:nth-child(2) { animation-delay: 1.2s; }
.wqs-metric-card:nth-child(3) { animation-delay: 1.3s; }
.wqs-metric-card:nth-child(4) { animation-delay: 1.4s; }
.wqs-metric-value { font-size: 0.8rem; font-weight: 700; color: var(--color-text, #1a1a2e); }
.wqs-metric-label { font-size: 0.55rem; color: var(--color-text-light, #6b7280); text-transform: uppercase; letter-spacing: 0.4px; margin-top: 1px; }
.wqs-exec-time {
    text-align: center; font-size: 0.7rem; color: var(--color-text-light, #6b7280);
    margin-top: 0.5rem; animation: wqs-fade-up 0.3s ease-out 1.5s both;
}
.wqs-exec-time strong { color: var(--color-text, #1a1a2e); }
.wqs-btn-premium {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white; border: none; padding: 8px 20px; border-radius: 8px;
    font-weight: 700; font-size: 0.8rem; cursor: pointer;
    transition: all 0.2s; box-shadow: 0 4px 10px rgba(16,185,129,0.2);
}
.wqs-btn-premium:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(16,185,129,0.3); }
.wqs-btn-secondary {
    background: transparent; color: var(--color-text-light, #6b7280);
    border: 1.5px solid var(--color-border, #e5e7eb); padding: 8px 20px;
    border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer;
    transition: all 0.2s;
}
.wqs-btn-secondary:hover { border-color: #10b981; color: #10b981; }

/* Confetti canvas */
#wqsConfettiCanvas {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; z-index: 10000;
}

/* Dark mode overrides */
@media (prefers-color-scheme: dark) {
    .wqs-success-icon::before { border-color: rgba(16,185,129,0.15); }
}
.swal2-popup.swal2-modal { border-radius: 20px !important; overflow: hidden; }
</style>

<canvas id="wqsConfettiCanvas"></canvas>

<div class="health-hero mb-4">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
        <div>
            <span style="background:rgba(16,185,129,0.2);border:1px solid rgba(16,185,129,0.3);padding:4px 12px;border-radius:8px;font-size:0.7rem;font-weight:700;color:#6ee7b7;text-transform:uppercase;letter-spacing:0.5px;">System Monitoring</span>
            <h4 class="fw-bold mt-2 mb-1" style="color:white;">API Health Monitor</h4>
            <p class="small mb-0" style="color:#a7f3d0;">Real-time monitoring of API credentials, connectivity, and service health.</p>
        </div>
        <button class="btn rounded-pill px-4 py-2 fw-bold text-nowrap" style="background:#10b981;color:white;border:none;white-space:nowrap !important;" onclick="runHealthChecks()" id="runChecksBtn">
            <i class="fas fa-sync-alt me-1"></i> Run Checks
        </button>
    </div>
</div>

<!-- Live Status Cards -->
<div class="row g-3 mb-4" id="healthResults">
    <?php foreach ($healthResults as $r): ?>
    <div class="col-md-3">
        <div class="health-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold text-body mb-0" style="font-size:0.88rem;"><?= htmlspecialchars($r['service']) ?></h6>
                <span class="health-status-badge health-status-<?= $r['status'] ?>">
                    <span class="health-dot health-dot-<?= $r['status'] ?>"></span>
                    <?= $r['status'] === 'ok' ? 'Healthy' : ($r['status'] === 'warning' ? 'Warning' : 'Error') ?>
                </span>
            </div>
            <p class="small mb-2" style="color:var(--color-text-light);line-height:1.4;"><?= htmlspecialchars($r['message'] ?: 'No issues detected.') ?></p>
            <?php if (!empty($r['details'])): ?>
            <div style="font-size:0.75rem;color:var(--color-text-light);">
                <?php foreach ($r['details'] as $k => $v): ?>
                    <div><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $k))) ?>:</strong> <?= htmlspecialchars($v) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Health Logs -->
<div class="health-card">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-body mb-0"><i class="fas fa-list-ul me-2" style="color:#10b981;"></i>Health Logs</h6>
        <div class="d-flex gap-1" id="logFilters">
            <button class="health-filter-btn active" data-filter="">All</button>
            <button class="health-filter-btn" data-filter="Firebase">Firebase</button>
            <button class="health-filter-btn" data-filter="Firebase Config">Config</button>
            <button class="health-filter-btn" data-filter="SMTP">SMTP</button>
            <button class="health-filter-btn" data-filter="Database">Database</button>
        </div>
    </div>
    <div id="healthLogs" style="max-height:500px;overflow-y:auto;">
        <?php if (empty($recentLogs)): ?>
        <div class="text-center py-4 text-muted small">No health logs yet.</div>
        <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
            <div class="health-log-row d-flex align-items-center gap-3">
                <span class="health-dot health-dot-<?= $log['status'] === 'ok' ? 'ok' : ($log['severity'] === 'critical' ? 'fail' : 'warning') ?>"></span>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2">
                        <strong style="font-size:0.82rem;"><?= htmlspecialchars($log['service']) ?></strong>
                        <span class="health-status-badge health-status-<?= $log['status'] === 'ok' ? 'ok' : ($log['severity'] === 'critical' ? 'fail' : 'warning') ?>" style="font-size:0.6rem;padding:2px 8px;">
                            <?= $log['status'] === 'ok' ? 'OK' : ucfirst($log['severity']) ?>
                        </span>
                    </div>
                    <?php if (!empty($log['error_message'])): ?>
                    <div class="small" style="color:var(--color-text-light);margin-top:2px;"><?= htmlspecialchars($log['error_message']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-muted small" style="white-space:nowrap;font-size:0.72rem;">
                    <?= date('M d, g:i A', strtotime($log['checked_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function buildSuccessHTML(results, elapsed, summary) {
    const statusLabel = (s) => s === 'ok' ? 'Healthy' : s === 'warning' ? 'Warning' : 'Error';
    const statusClass = (s) => s === 'ok' ? 'ok' : s === 'warning' ? 'warning' : 'fail';
    const statusIcon = (s) => s === 'ok' ? '<i class="fas fa-check-circle" style="font-size:0.65rem;"></i>' : s === 'warning' ? '<i class="fas fa-exclamation-triangle" style="font-size:0.65rem;"></i>' : '<i class="fas fa-times-circle" style="font-size:0.65rem;"></i>';

    const resultRows = results.map(r => `
        <div class="wqs-result-row">
            <span class="wqs-result-label">${esc(r.service)}</span>
            <span class="wqs-result-badge wqs-badge-${statusClass(r.status)}">
                ${statusIcon(r.status)} ${statusLabel(r.status)}
            </span>
        </div>
    `).join('');

    const metricCards = results.map(r => {
        let val = '--';
        if (r.details) {
            if (r.details.latency_ms !== undefined) val = r.details.latency_ms + ' ms';
            else if (r.details.project_id) val = 'Connected';
            else if (r.details.host) val = r.details.user || 'Set';
        }
        return `<div class="wqs-metric-card">
            <div class="wqs-metric-value">${esc(val)}</div>
            <div class="wqs-metric-label">${esc(r.service)}</div>
        </div>`;
    }).join('');

    return `
        <div class="wqs-success-icon">
            <svg viewBox="0 0 40 40"><path class="wqs-check-path" d="M10 20 L17 27 L30 13"/></svg>
        </div>
        <div class="wqs-modal-title">Health Check Completed Successfully</div>
        <div class="wqs-modal-subtitle">All system diagnostics have been executed successfully.</div>

        <div class="row g-3 text-start">
            <div class="col-md-6">
                <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--color-text-light,#6b7280);margin-bottom:0.4rem;">System Status Summary</div>
                ${resultRows}
            </div>
            <div class="col-md-6">
                <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--color-text-light,#6b7280);margin-bottom:0.4rem;">Performance Metrics</div>
                <div class="wqs-metrics-grid">${metricCards}</div>
            </div>
        </div>

        <div class="wqs-exec-time">
            <i class="fas fa-clock me-1"></i> Execution Time: <strong>${elapsed}s</strong>
            &nbsp;&middot;&nbsp; ${summary.ok} healthy, ${summary.warning} warnings, ${summary.fail} errors
        </div>
    `;
}

function launchConfetti() {
    const canvas = document.getElementById('wqsConfettiCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const particles = [];
    const colors = ['#10b981','#059669','#34d399','#f59e0b','#3b82f6','#8b5cf6','#ec4899'];
    for (let i = 0; i < 60; i++) {
        particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            w: Math.random() * 8 + 4,
            h: Math.random() * 4 + 2,
            color: colors[Math.floor(Math.random() * colors.length)],
            vx: (Math.random() - 0.5) * 3,
            vy: Math.random() * 3 + 2,
            rot: Math.random() * 360,
            rotV: (Math.random() - 0.5) * 8,
            life: 1
        });
    }
    let frame = 0;
    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        let alive = false;
        particles.forEach(p => {
            if (p.life <= 0) return;
            alive = true;
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.06;
            p.rot += p.rotV;
            p.life -= 0.008;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot * Math.PI / 180);
            ctx.globalAlpha = p.life;
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            ctx.restore();
        });
        if (alive && frame < 200) {
            frame++;
            requestAnimationFrame(animate);
        } else {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }
    animate();
}

function refreshDashboard(results) {
    const container = document.getElementById('healthResults');
    container.innerHTML = results.map(r => `
        <div class="col-md-3">
            <div class="health-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold text-body mb-0" style="font-size:0.88rem;">${esc(r.service)}</h6>
                    <span class="health-status-badge health-status-${r.status}">
                        <span class="health-dot health-dot-${r.status}"></span>
                        ${r.status === 'ok' ? 'Healthy' : r.status === 'warning' ? 'Warning' : 'Error'}
                    </span>
                </div>
                <p class="small mb-2" style="color:var(--color-text-light);line-height:1.4;">${esc(r.message || 'No issues detected.')}</p>
                ${r.details ? '<div style="font-size:0.75rem;color:var(--color-text-light);">' + Object.entries(r.details).map(([k,v]) => `<div><strong>${esc(k.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()))}:</strong> ${esc(String(v))}</div>`).join('') + '</div>' : ''}
            </div>
        </div>
    `).join('');
}

async function runHealthChecks() {
    const btn = document.getElementById('runChecksBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Checking...';

    try {
        const fd = new FormData();
        fd.append('ajax_action', 'run_checks');
        const res = await fetch('api-health.php', { method: 'POST', body: fd });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (parseErr) {
            console.error('Invalid JSON from API Health:', raw);
            Swal.fire({ icon: 'error', title: 'Server Error', html: '<div class="text-start"><strong>The API returned an invalid response.</strong><br>Check the browser console for details.</div>' });
            return;
        }

        if (data.success) {
            refreshDashboard(data.results);
            loadLogs();

            const allHealthy = data.summary && data.summary.fail === 0 && data.summary.warning === 0;
            if (allHealthy) launchConfetti();

            const html = buildSuccessHTML(data.results, data.elapsed, data.summary || {ok:0,warning:0,fail:0,total:0});

            Swal.fire({
                html: html,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-chart-line me-1"></i> View Detailed Report',
                cancelButtonText: 'Close',
                confirmButtonColor: '#10b981',
                cancelButtonColor: 'transparent',
                customClass: { confirmButton: 'wqs-btn-premium', cancelButton: 'wqs-btn-secondary', popup: 'wqs-health-popup' },
                buttonsStyling: false,
                width: '560px',
                padding: '1rem 1rem 0.75rem',
                showConfirmButton: true,
                didOpen: () => {
                    const popup = Swal.getPopup();
                    if (popup) {
                        popup.style.borderRadius = '20px';
                        popup.style.overflow = 'hidden';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('healthLogs').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Health Check Failed', text: data.message || 'Unknown error occurred' });
        }
    } catch (err) {
        console.error('Health check fetch error:', err);
        Swal.fire({ icon: 'error', title: 'Check failed', text: err.message });
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Run Checks';
}

async function loadLogs(service = '') {
    try {
        const fd = new FormData();
        fd.append('ajax_action', 'get_logs');
        fd.append('service', service);
        const res = await fetch('api-health.php', { method: 'POST', body: fd });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (parseErr) { console.error('Invalid JSON from loadLogs:', raw); return; }
        if (!data.success) return;

        const container = document.getElementById('healthLogs');
        if (!data.logs.length) {
            container.innerHTML = '<div class="text-center py-4 text-muted small">No logs found.</div>';
            return;
        }
        container.innerHTML = data.logs.map(log => `
            <div class="health-log-row d-flex align-items-center gap-3">
                <span class="health-dot health-dot-${log.status === 'ok' ? 'ok' : log.severity === 'critical' ? 'fail' : 'warning'}"></span>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2">
                        <strong style="font-size:0.82rem;">${esc(log.service)}</strong>
                        <span class="health-status-badge health-status-${log.status === 'ok' ? 'ok' : log.severity === 'critical' ? 'fail' : 'warning'}" style="font-size:0.6rem;padding:2px 8px;">
                            ${log.status === 'ok' ? 'OK' : log.severity.charAt(0).toUpperCase() + log.severity.slice(1)}
                        </span>
                    </div>
                    ${log.error_message ? `<div class="small" style="color:var(--color-text-light);margin-top:2px;">${esc(log.error_message)}</div>` : ''}
                </div>
                <div class="text-muted small" style="white-space:nowrap;font-size:0.72rem;">
                    ${new Date(log.checked_at).toLocaleDateString('en-NG', {month:'short',day:'numeric'})} ${new Date(log.checked_at).toLocaleTimeString('en-NG', {hour:'2-digit',minute:'2-digit'})}
                </div>
            </div>
        `).join('');
    } catch (err) {
        console.error('loadLogs error:', err);
    }
}

document.getElementById('logFilters').addEventListener('click', function(e) {
    const btn = e.target.closest('.health-filter-btn');
    if (!btn) return;
    this.querySelectorAll('.health-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadLogs(btn.dataset.filter);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
