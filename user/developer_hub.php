<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: ../login.php"); exit; }

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];

// Verify developer role
$roleStmt = $pdo->prepare("SELECT role, name, email FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userObj = $roleStmt->fetch(PDO::FETCH_ASSOC);
if (!$userObj || strtolower($userObj['role']) !== 'developer') {
    header("Location: upgrade_developer.php"); exit;
}

// === Handle AJAX: Update task status or log hours ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'update_status') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $db->real_escape_string($_POST['status']);
        $note = $db->real_escape_string($_POST['developer_note'] ?? '');
        $allowed = ['assigned','in_progress','review','completed'];
        if (in_array($newStatus, $allowed)) {
            $db->query("UPDATE developer_tasks SET status='$newStatus', developer_note='$note', updated_at=NOW() WHERE id=$taskId AND developer_id=$userId");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
        }
    } elseif ($action === 'log_hours') {
        $taskId = (int)$_POST['task_id'];
        $hours  = (float)$_POST['hours'];
        $db->query("UPDATE developer_tasks SET hours_worked = hours_worked + $hours, updated_at=NOW() WHERE id=$taskId AND developer_id=$userId");
        echo json_encode(['success' => true]);
    }
    exit;
}

// === Fetch Developer Data ===
// Tasks
$tasks = [];
$taskRes = $db->query("
    SELECT dt.*, op.title AS project_title 
    FROM developer_tasks dt 
    LEFT JOIN ongoing_projects op ON dt.project_id = op.id 
    WHERE dt.developer_id = $userId 
    ORDER BY 
        FIELD(dt.priority,'urgent','high','medium','low'),
        dt.due_date ASC
");
if ($taskRes) { while ($row = $taskRes->fetch_assoc()) $tasks[] = $row; }

// Skills
$skills = [];
$skillRes = $db->query("SELECT * FROM developer_skills WHERE developer_id = $userId ORDER BY level DESC");
if ($skillRes) { while ($row = $skillRes->fetch_assoc()) $skills[] = $row; }
// Fallback: parse from developer_requests
if (empty($skills)) {
    $drRes = $db->query("SELECT skills FROM developer_requests WHERE user_id = $userId LIMIT 1");
    if ($drRes && $dr = $drRes->fetch_assoc()) {
        $parsedSkills = json_decode($dr['skills'] ?? '[]', true);
        if (is_array($parsedSkills)) {
            foreach ($parsedSkills as $s) {
                $skills[] = ['skill_name' => $s, 'level' => 'intermediate'];
            }
        }
    }
}

// Earnings
$totalEarnings = 0; $pendingEarnings = 0;
$earningsRes = $db->query("SELECT status, SUM(hourly_rate * hours_worked) AS earned FROM developer_tasks WHERE developer_id=$userId GROUP BY status");
if ($earningsRes) {
    while ($er = $earningsRes->fetch_assoc()) {
        if ($er['status'] === 'completed') $totalEarnings += (float)$er['earned'];
        elseif (in_array($er['status'], ['assigned','in_progress','review'])) $pendingEarnings += (float)$er['earned'];
    }
}

// Task counts
$taskCounts = ['total'=>0,'completed'=>0,'in_progress'=>0,'assigned'=>0,'review'=>0];
foreach ($tasks as $t) {
    $taskCounts['total']++;
    if (isset($taskCounts[$t['status']])) $taskCounts[$t['status']]++;
}
$completionRate = $taskCounts['total'] > 0 ? round(($taskCounts['completed'] / $taskCounts['total']) * 100) : 0;

// Developer request info
$devInfo = [];
$devInfoRes = $db->query("SELECT * FROM developer_requests WHERE user_id=$userId LIMIT 1");
if ($devInfoRes) $devInfo = $devInfoRes->fetch_assoc() ?? [];

$page_title = "Developer Hub";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
/* ===== DEVELOPER HUB STYLES ===== */
.dev-hub-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f2027 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.dev-hub-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:260px; height:260px;
    background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
    border-radius:50%;
}
.dev-hub-hero::after {
    content:''; position:absolute; bottom:-60px; left:-40px;
    width:200px; height:200px;
    background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%);
    border-radius:50%;
}
.dev-stat-card {
    border-radius:14px; padding:1.25rem 1.5rem;
    border:1px solid transparent; position:relative; overflow:hidden;
    text-decoration:none; display:block; transition:all 0.3s;
}
.dev-stat-card:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(0,0,0,0.08); }
.dev-stat-card .bg-dot { position:absolute;top:-15px;right:-15px;width:70px;height:70px;border-radius:50%;opacity:0.07; }
.dev-stat-card .icon-w { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:0.75rem; }
.dev-stat-card .s-val  { font-size:1.6rem;font-weight:900;line-height:1;margin-bottom:0.15rem; }
.dev-stat-card .s-lbl  { font-size:0.8rem;font-weight:600;margin-bottom:0.1rem; }
.dev-stat-card .s-sub  { font-size:0.7rem; }
.dsc-indigo { background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-color:#c7d2fe; }
.dsc-indigo .icon-w { background:#6366f1;color:white; } .dsc-indigo .s-val,.dsc-indigo .s-lbl { color:#4338ca; } .dsc-indigo .s-sub { color:#6366f1; }
.dsc-green  { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-color:#86efac; }
.dsc-green  .icon-w { background:#10b981;color:white; } .dsc-green .s-val,.dsc-green .s-lbl { color:#059669; } .dsc-green .s-sub { color:#10b981; }
.dsc-amber  { background:linear-gradient(135deg,#fffbeb,#fef3c7); border-color:#fde68a; }
.dsc-amber  .icon-w { background:#f59e0b;color:white; } .dsc-amber .s-val,.dsc-amber .s-lbl { color:#d97706; } .dsc-amber .s-sub { color:#f59e0b; }
.dsc-blue   { background:linear-gradient(135deg,#eff6ff,#dbeafe); border-color:#bfdbfe; }
.dsc-blue   .icon-w { background:#3b82f6;color:white; } .dsc-blue .s-val,.dsc-blue .s-lbl { color:#1d4ed8; } .dsc-blue .s-sub { color:#3b82f6; }

.priority-badge { padding:0.2rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;display:inline-block; }
.p-urgent { background:#fef2f2;color:#dc2626;border:1px solid #fca5a5; }
.p-high   { background:#fff7ed;color:#ea580c;border:1px solid #fed7aa; }
.p-medium { background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe; }
.p-low    { background:#f0fdf4;color:#16a34a;border:1px solid #86efac; }

.status-badge-t { padding:0.2rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;display:inline-block; }
.st-assigned    { background:#f1f5f9;color:#475569; }
.st-in_progress { background:#dbeafe;color:#1d4ed8; }
.st-review      { background:#fef3c7;color:#92400e; }
.st-completed   { background:#dcfce7;color:#15803d; }
.st-cancelled   { background:#fee2e2;color:#991b1b; }

.task-card {
    background:#fff; border:1px solid var(--color-border); border-radius:14px;
    padding:1.25rem; transition:all 0.2s; cursor:pointer;
    border-left:4px solid transparent;
}
.task-card:hover { box-shadow:0 6px 20px rgba(0,0,0,0.07); transform:translateY(-2px); }
.task-card.priority-urgent-card { border-left-color:#dc2626; }
.task-card.priority-high-card   { border-left-color:#ea580c; }
.task-card.priority-medium-card { border-left-color:#3b82f6; }
.task-card.priority-low-card    { border-left-color:#16a34a; }

.skill-chip {
    padding:0.35rem 0.85rem;border-radius:50px;font-size:0.78rem;font-weight:600;
    display:inline-flex;align-items:center;gap:0.4rem;margin:0.2rem;
}
.sc-beginner     { background:#f1f5f9;color:#475569;border:1px solid #e2e8f0; }
.sc-intermediate { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
.sc-advanced     { background:#f0fdf4;color:#15803d;border:1px solid #86efac; }
.sc-expert       { background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;border:1px solid #fcd34d; }

.perf-ring-wrap { position:relative;width:90px;height:90px;margin:0 auto; }
.perf-ring-text { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:1.1rem;font-weight:900;color:#0f172a; }

.due-soon  { color:#dc2626;font-weight:700; }
.due-ok    { color:#16a34a; }
.due-today { color:#ea580c;font-weight:700; }
</style>

<!-- ===== HERO ===== -->
<div class="dev-hub-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(99,102,241,0.25);color:#a5b4fc;border:1px solid rgba(165,180,252,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;">
                    <i class="fas fa-code me-1"></i> WQS Developer
                </span>
                <span style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.72rem;font-weight:600;">● Hired & Active</span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.4rem;">
                Developer Hub — <?= htmlspecialchars($userObj['name']) ?>
            </h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
                <?= $taskCounts['in_progress'] ?> task<?= $taskCounts['in_progress']!=1?'s':''?> in progress ·
                <?= $taskCounts['assigned'] ?> assigned ·
                <strong style="color:#86efac;">₦<?= number_format($totalEarnings, 0) ?></strong> earned total
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!empty($devInfo['portfolio_url'])): ?>
            <a href="<?= htmlspecialchars($devInfo['portfolio_url']) ?>" target="_blank" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.82rem;">
                <i class="fas fa-external-link-alt me-1"></i> Portfolio
            </a>
            <?php endif; ?>
            <?php if (!empty($devInfo['github_url'])): ?>
            <a href="<?= htmlspecialchars($devInfo['github_url']) ?>" target="_blank" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.82rem;">
                <i class="fab fa-github me-1"></i> GitHub
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dev-stat-card dsc-indigo">
            <div class="bg-dot" style="background:#4338ca;"></div>
            <div class="icon-w"><i class="fas fa-tasks"></i></div>
            <div class="s-val"><?= $taskCounts['total'] ?></div>
            <div class="s-lbl">Total Tasks</div>
            <div class="s-sub"><?= $taskCounts['in_progress'] ?> active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dev-stat-card dsc-green">
            <div class="bg-dot" style="background:#059669;"></div>
            <div class="icon-w"><i class="fas fa-check-double"></i></div>
            <div class="s-val"><?= $taskCounts['completed'] ?></div>
            <div class="s-lbl">Completed</div>
            <div class="s-sub"><?= $completionRate ?>% rate</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dev-stat-card dsc-green">
            <div class="bg-dot" style="background:#059669;"></div>
            <div class="icon-w"><i class="fas fa-naira-sign"></i></div>
            <div class="s-val" style="font-size:1.2rem;">₦<?= number_format($totalEarnings, 0) ?></div>
            <div class="s-lbl">Total Earned</div>
            <div class="s-sub">From completed tasks</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dev-stat-card dsc-amber">
            <div class="bg-dot" style="background:#d97706;"></div>
            <div class="icon-w"><i class="fas fa-hourglass-half"></i></div>
            <div class="s-val" style="font-size:1.2rem;">₦<?= number_format($pendingEarnings, 0) ?></div>
            <div class="s-lbl">Pending Payout</div>
            <div class="s-sub">On completion</div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ===== TASKS PANEL ===== -->
    <div class="col-lg-8">
        <div class="card-theme">
            <div class="card-theme-header d-flex justify-content-between align-items-center">
                <h5 class="card-theme-title text-body"><i class="fas fa-clipboard-list me-2" style="color:#6366f1;"></i>My Assigned Tasks</h5>
                <div class="d-flex gap-2">
                    <select id="taskFilter" class="form-select form-select-sm" style="width:auto;font-size:0.78rem;" onchange="filterTasks(this.value)">
                        <option value="all">All Tasks</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="review">In Review</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="card-theme-body p-3" id="tasks-container">
                <?php if (empty($tasks)): ?>
                <div class="text-center py-5 text-muted">
                    <div style="font-size:3rem;margin-bottom:0.75rem;">📋</div>
                    <h6 class="fw-semibold text-body">No Tasks Yet</h6>
                    <p class="small mb-0">The admin will assign tasks to you shortly. Check back here.</p>
                </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3" id="task-list">
                    <?php foreach ($tasks as $task):
                        $dueStr = ''; $dueClass = 'due-ok';
                        if (!empty($task['due_date'])) {
                            $dueTs = strtotime($task['due_date']);
                            $diffDays = ceil(($dueTs - time()) / 86400);
                            if ($diffDays < 0) { $dueStr = 'Overdue!'; $dueClass = 'due-soon'; }
                            elseif ($diffDays === 0) { $dueStr = 'Due Today'; $dueClass = 'due-today'; }
                            elseif ($diffDays <= 2) { $dueStr = "Due in {$diffDays}d"; $dueClass = 'due-soon'; }
                            else { $dueStr = date('M d', $dueTs); }
                        }
                        $earned = (float)$task['hourly_rate'] * (float)$task['hours_worked'];
                    ?>
                    <div class="task-card priority-<?= $task['priority'] ?>-card" data-status="<?= $task['status'] ?>"
                         onclick="openTaskModal(<?= $task['id'] ?>)"
                         data-task='<?= json_encode($task) ?>'>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div style="flex:1;min-width:0;">
                                <div class="fw-bold text-body mb-1" style="font-size:0.95rem;"><?= htmlspecialchars($task['title']) ?></div>
                                <?php if (!empty($task['project_title'])): ?>
                                <div class="text-muted" style="font-size:0.75rem;"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($task['project_title']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 flex-wrap ms-2">
                                <span class="priority-badge p-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                                <span class="status-badge-t st-<?= $task['status'] ?>"><?= ucfirst(str_replace('_',' ',$task['status'])) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                        <p class="text-muted mb-2" style="font-size:0.82rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= htmlspecialchars($task['description']) ?>
                        </p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                            <div class="d-flex gap-3" style="font-size:0.75rem;color:var(--color-text-light);">
                                <?php if ($dueStr): ?>
                                <span class="<?= $dueClass ?>"><i class="fas fa-calendar me-1"></i><?= $dueStr ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock me-1"></i><?= $task['hours_worked'] ?>h logged</span>
                                <?php if ($task['hourly_rate'] > 0): ?>
                                <span style="color:#10b981;font-weight:600;"><i class="fas fa-naira-sign me-1"></i>₦<?= number_format($task['hourly_rate'], 0) ?>/hr</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($earned > 0): ?>
                            <span style="background:#dcfce7;color:#15803d;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.72rem;font-weight:700;">
                                ₦<?= number_format($earned, 0) ?> earned
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT SIDEBAR ===== -->
    <div class="col-lg-4">

        <!-- Performance -->
        <div class="card-theme mb-4">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-chart-pie me-2 text-primary"></i>Performance</h5>
            </div>
            <div class="card-theme-body">
                <!-- Completion Ring -->
                <div class="text-center mb-3">
                    <div class="perf-ring-wrap">
                        <svg width="90" height="90" viewBox="0 0 90 90" style="transform:rotate(-90deg);">
                            <circle cx="45" cy="45" r="36" fill="none" stroke="#e2e8f0" stroke-width="8"/>
                            <circle cx="45" cy="45" r="36" fill="none" stroke="url(#devGrad)" stroke-width="8"
                                stroke-linecap="round"
                                stroke-dasharray="<?= 2*M_PI*36 ?>"
                                stroke-dashoffset="<?= 2*M_PI*36 * (1 - $completionRate/100) ?>"/>
                            <defs>
                                <linearGradient id="devGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#6366f1"/>
                                    <stop offset="100%" style="stop-color:#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="perf-ring-text"><?= $completionRate ?>%</div>
                    </div>
                    <div style="font-size:0.78rem;color:var(--color-text-light);margin-top:0.3rem;">Completion Rate</div>
                </div>
                <div class="row g-2">
                    <?php
                    $perfItems = [
                        ['In Progress', $taskCounts['in_progress'], '#3b82f6'],
                        ['In Review', $taskCounts['review'], '#f59e0b'],
                        ['Completed', $taskCounts['completed'], '#10b981'],
                        ['Assigned', $taskCounts['assigned'], '#6366f1'],
                    ];
                    foreach ($perfItems as [$label, $count, $color]):
                    ?>
                    <div class="col-6">
                        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:8px;padding:0.6rem;text-align:center;">
                            <div style="font-size:1.3rem;font-weight:900;color:<?= $color ?>;"><?= $count ?></div>
                            <div style="font-size:0.7rem;color:var(--color-text-light);"><?= $label ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Skills Board -->
        <div class="card-theme mb-4">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-code me-2" style="color:#6366f1;"></i>My Skills</h5>
            </div>
            <div class="card-theme-body">
                <?php if (!empty($skills)): ?>
                <div>
                    <?php foreach ($skills as $skill): ?>
                    <span class="skill-chip sc-<?= $skill['level'] ?>">
                        <?= htmlspecialchars($skill['skill_name']) ?>
                        <span style="font-size:0.65rem;opacity:0.7;"><?= ucfirst($skill['level']) ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3 small">No skills listed yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Earnings Breakdown -->
        <div class="card-theme">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-wallet me-2 text-success"></i>Earnings Summary</h5>
            </div>
            <div class="card-theme-body">
                <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:10px;padding:1rem;margin-bottom:0.75rem;">
                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.06em;color:#15803d;font-weight:700;margin-bottom:0.2rem;"><i class="fas fa-check-circle me-1"></i>Confirmed</div>
                    <div style="font-size:1.8rem;font-weight:900;color:#166534;line-height:1;">₦<?= number_format($totalEarnings, 0) ?></div>
                    <div style="font-size:0.72rem;color:#15803d;">From <?= $taskCounts['completed'] ?> completed task<?= $taskCounts['completed']!=1?'s':''?></div>
                </div>
                <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fcd34d;border-radius:10px;padding:1rem;">
                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.06em;color:#92400e;font-weight:700;margin-bottom:0.2rem;"><i class="fas fa-hourglass-half me-1"></i>Pending</div>
                    <div style="font-size:1.8rem;font-weight:900;color:#78350f;line-height:1;">₦<?= number_format($pendingEarnings, 0) ?></div>
                    <div style="font-size:0.72rem;color:#92400e;">Releases when tasks complete</div>
                </div>
                <?php if (!empty($devInfo['hourly_rate_expected'])): ?>
                <div class="mt-3 pt-2 mb-2" style="border-top:1px dashed var(--color-border);font-size:0.78rem;color:var(--color-text-light);">
                    <i class="fas fa-info-circle me-1"></i> Your expected rate: <strong style="color:var(--color-text-body);">₦<?= number_format($devInfo['hourly_rate_expected'], 0) ?>/hr</strong>
                </div>
                <?php endif; ?>
                <div class="d-grid mt-2">
                    <a href="payout_requests.php" class="btn btn-sm btn-outline-success rounded-pill fw-bold" style="font-size: 0.78rem;">
                        <i class="fas fa-hand-holding-usd me-1"></i> Payout Console & Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TASK UPDATE MODAL ===== -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0" style="padding:1.5rem 1.5rem 0.5rem;">
                <h5 class="modal-title fw-bold text-body" id="modalTaskTitle">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="modalTaskContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
function filterTasks(status) {
    document.querySelectorAll('.task-card').forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function openTaskModal(taskId) {
    const card = document.querySelector(`[onclick="openTaskModal(${taskId})"]`);
    const task = JSON.parse(card.dataset.task);
    const earned = (parseFloat(task.hourly_rate) * parseFloat(task.hours_worked)).toLocaleString('en-NG', {maximumFractionDigits:0});
    const statusOptions = ['assigned','in_progress','review','completed'].map(s =>
        `<option value="${s}" ${task.status===s?'selected':''}>${s.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase())}</option>`
    ).join('');

    document.getElementById('modalTaskTitle').textContent = task.title;
    document.getElementById('modalTaskContent').innerHTML = `
        <div class="row g-3">
            <div class="col-md-8">
                ${task.project_title ? `<div class="text-muted small mb-2"><i class="fas fa-folder me-1"></i>${task.project_title}</div>` : ''}
                <p style="font-size:0.9rem;line-height:1.7;color:#334155;">${task.description || 'No description provided.'}</p>
            </div>
            <div class="col-md-4">
                <div style="background:var(--color-bg);border:1px solid #e2e8f0;border-radius:10px;padding:1rem;font-size:0.82rem;">
                    <div class="mb-2"><span class="text-muted">Priority:</span> <span class="priority-badge p-${task.priority} ms-1">${task.priority}</span></div>
                    <div class="mb-2"><span class="text-muted">Due Date:</span> <strong>${task.due_date || 'Not set'}</strong></div>
                    <div class="mb-2"><span class="text-muted">Rate:</span> <strong style="color:#10b981;">₦${parseFloat(task.hourly_rate).toLocaleString()}/hr</strong></div>
                    <div class="mb-2"><span class="text-muted">Hours Logged:</span> <strong>${task.hours_worked}h</strong></div>
                    <div><span class="text-muted">Earned:</span> <strong style="color:#15803d;">₦${earned}</strong></div>
                </div>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:0.82rem;">Update Status</label>
                <select id="modal-status" class="form-select form-select-sm">${statusOptions}</select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:0.82rem;">Log Additional Hours</label>
                <div class="input-group input-group-sm">
                    <input type="number" id="modal-hours" class="form-control" placeholder="e.g. 2.5" step="0.5" min="0">
                    <span class="input-group-text">hrs</span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" style="font-size:0.82rem;">Progress Note</label>
                <textarea id="modal-note" class="form-control form-control-sm" rows="3" placeholder="Brief update on your progress...">${task.developer_note||''}</textarea>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:8px;" onclick="saveTaskUpdate(${task.id})">
                    <i class="fas fa-save me-1"></i> Save Update
                </button>
            </div>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

function saveTaskUpdate(taskId) {
    const status = document.getElementById('modal-status').value;
    const note   = document.getElementById('modal-note').value;
    const hours  = parseFloat(document.getElementById('modal-hours').value || 0);

    const promises = [];
    promises.push(fetch('developer_hub.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `ajax_action=update_status&task_id=${taskId}&status=${status}&developer_note=${encodeURIComponent(note)}`
    }));
    if (hours > 0) {
        promises.push(fetch('developer_hub.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `ajax_action=log_hours&task_id=${taskId}&hours=${hours}`
        }));
    }
    Promise.all(promises).then(() => { location.reload(); });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
