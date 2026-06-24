<?php
$path_to_root = "../";
$page_title = "Manage Developers";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';



// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $devId       = (int)$_POST['developer_id'];
    $projId      = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $title       = trim($_POST['title']);
    $desc        = trim($_POST['description']);
    $priority    = $_POST['priority'];
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $rate        = (float)$_POST['hourly_rate'];
    $adminId     = (int)$_SESSION['user']['id'];
    $pdo->prepare("INSERT INTO developer_tasks (developer_id, project_id, title, description, priority, due_date, hourly_rate, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")
        ->execute([$devId, $projId, $title, $desc, $priority, $dueDate, $rate, $adminId]);
    $_SESSION['success_message'] = "Task assigned successfully.";
    header("Location: manage_developers.php"); exit;
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $taskId    = (int)$_POST['task_id'];
    $newStatus = $_POST['task_status'];
    $pdo->prepare("UPDATE developer_tasks SET status=?, updated_at=NOW() WHERE id=?")
        ->execute([$newStatus, $taskId]);
    header("Location: manage_developers.php"); exit;
}

// Fetch all developers
$developers = [];
$devRes = $pdo->query("
    SELECT u.id, u.name, u.email, u.picture,
        dr.skills, dr.portfolio_url, dr.github_url, dr.hourly_rate_expected, dr.years_experience,
        COUNT(dt.id) AS total_tasks,
        SUM(CASE WHEN dt.status='completed' THEN 1 ELSE 0 END) AS done_tasks,
        SUM(CASE WHEN dt.status IN('assigned','in_progress','review') THEN 1 ELSE 0 END) AS active_tasks
    FROM users u
    LEFT JOIN developer_requests dr ON dr.user_id = u.id AND dr.status='approved'
    LEFT JOIN developer_tasks dt ON dt.developer_id = u.id
    WHERE u.role = 'developer'
    GROUP BY u.id
    ORDER BY u.name ASC
");
if ($devRes) $developers = $devRes->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tasks for management view
$allTasks = [];
$taskRes = $pdo->query("
    SELECT dt.*, u.name AS dev_name, op.title AS proj_title
    FROM developer_tasks dt
    LEFT JOIN users u ON dt.developer_id = u.id
    LEFT JOIN ongoing_projects op ON dt.project_id = op.id
    ORDER BY FIELD(dt.priority,'urgent','high','medium','low'), dt.created_at DESC
    LIMIT 50
");
if ($taskRes) $allTasks = $taskRes->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects for task assignment dropdown
$projects = [];
$projRes = $pdo->query("SELECT id, title FROM ongoing_projects WHERE status != 'cancelled' ORDER BY title ASC");
if ($projRes) $projects = $projRes->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

.admin-dev-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.admin-dev-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%); border-radius:50%; }
.dev-card {
    background: #fff; border: 1px solid var(--color-border); border-radius: 16px;
    padding: 1.5rem; transition: all 0.2s;
}
.dev-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.07); transform: translateY(-2px); }
.dev-avt { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-border); }
.dev-avt-ph { width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1.1rem;flex-shrink:0; }
.task-row { background:#fff;border:1px solid var(--color-border);border-radius:10px;padding:0.85rem 1rem;margin-bottom:0.5rem; }
.priority-dot { width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0; }
.pd-urgent { background:#dc2626; }
.pd-high   { background:#ea580c; }
.pd-medium { background:#3b82f6; }
.pd-low    { background:#16a34a; }
.status-sel { border-radius:50px;padding:0.2rem 0.75rem;font-size:0.72rem;font-weight:700;border:none;background:#f1f5f9;cursor:pointer; }
.assign-btn {
    background: linear-gradient(135deg,#6366f1,#8b5cf6); color:white; border:none;
    border-radius:8px; padding:0.5rem 1rem; font-size:0.82rem; font-weight:600;
    cursor:pointer; transition:all 0.2s;
}
.assign-btn:hover { opacity:0.9; transform:translateY(-1px); }
.skill-mini { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:0.18rem 0.55rem;border-radius:50px;font-size:0.7rem;font-weight:600;display:inline-block;margin:0.1rem; }
</style>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3 shadow-sm mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- Hero -->
<div class="admin-dev-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="dev-badge mb-2" style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(99,102,241,0.25);color:#a5b4fc;border:1px solid rgba(165,180,252,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;text-transform:uppercase;">
                <i class="fas fa-users-cog"></i> Developer Management
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;color:white;margin-bottom:0.3rem;">Manage Developers</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
                <?= count($developers) ?> hired developer<?= count($developers)!=1?'s':''?> · Assign tasks, track progress, manage payouts
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="developer_requests.php" class="btn" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-size:0.82rem;">
                <i class="fas fa-user-clock me-1"></i> Review Applications
            </a>
            <button class="btn" style="background:#6366f1;color:white;border:none;border-radius:8px;font-size:0.82rem;" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="fas fa-plus me-1"></i> Assign Task
            </button>
        </div>
    </div>
</div>

<!-- ===== DEVELOPERS GRID ===== -->
<?php if (empty($developers)): ?>
<div class="card-theme p-5 text-center text-muted mb-4">
    <div style="font-size:3rem;margin-bottom:0.75rem;">👨‍💻</div>
    <h5 class="fw-bold text-body">No Developers Hired Yet</h5>
    <p class="small mb-3">Approve developer applications to start building your team.</p>
    <a href="developer_requests.php" class="btn btn-sm" style="background:#6366f1;color:white;border-radius:8px;">Review Applications</a>
</div>
<?php else: ?>
<h5 class="fw-bold text-body mb-3"><i class="fas fa-code me-2" style="color:#6366f1;"></i>Hired Developers (<?= count($developers) ?>)</h5>
<div class="row g-4 mb-4">
<?php foreach ($developers as $dev):
    $skillArr = json_decode($dev['skills'] ?? '[]', true);
    if (!is_array($skillArr)) $skillArr = [];
    $completionRate = $dev['total_tasks'] > 0 ? round(($dev['done_tasks'] / $dev['total_tasks']) * 100) : 0;
?>
<div class="col-12 col-md-6 col-lg-4">
    <div class="dev-card">
        <div class="d-flex align-items-start gap-3 mb-3">
            <?php if (!empty($dev['picture'])): ?>
            <img src="<?= htmlspecialchars($dev['picture']) ?>" class="dev-avt" alt="">
            <?php else: ?>
            <img src="<?= $path_to_root ?>images/default-avatar.png" class="dev-avt" alt="">
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <div class="fw-bold text-body mb-0" style="font-size:0.95rem;"><?= htmlspecialchars($dev['name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($dev['email']) ?></div>
                <div class="mt-1" style="font-size:0.72rem;color:#6366f1;">
                    <?= $dev['years_experience'] ?>yr exp ·
                    <?php if ($dev['hourly_rate_expected'] > 0): ?>₦<?= number_format($dev['hourly_rate_expected'],0) ?>/hr<?php endif; ?>
                </div>
            </div>
            <span style="background:rgba(16,185,129,0.15);color:#15803d;border:1px solid rgba(16,185,129,0.3);padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;">● Hired</span>
        </div>

        <!-- Skills -->
        <?php if (!empty($skillArr)): ?>
        <div class="mb-3">
            <?php foreach (array_slice($skillArr,0,6) as $sk): ?>
            <span class="skill-mini"><?= htmlspecialchars($sk) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-2 mb-3">
            <div class="col-4 text-center">
                <div style="font-size:1.3rem;font-weight:900;color:#6366f1;"><?= $dev['total_tasks'] ?></div>
                <div style="font-size:0.68rem;color:var(--color-text-light);">Total Tasks</div>
            </div>
            <div class="col-4 text-center">
                <div style="font-size:1.3rem;font-weight:900;color:#10b981;"><?= $dev['done_tasks'] ?></div>
                <div style="font-size:0.68rem;color:var(--color-text-light);">Completed</div>
            </div>
            <div class="col-4 text-center">
                <div style="font-size:1.3rem;font-weight:900;color:#f59e0b;"><?= $dev['active_tasks'] ?></div>
                <div style="font-size:0.68rem;color:var(--color-text-light);">Active</div>
            </div>
        </div>

        <!-- Completion bar -->
        <div style="height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-bottom:1rem;">
            <div style="height:100%;width:<?= $completionRate ?>%;background:linear-gradient(90deg,#6366f1,#10b981);border-radius:3px;"></div>
        </div>

        <div class="d-flex gap-2">
            <button class="assign-btn flex-grow-1" onclick="prefillAssign(<?= $dev['id'] ?>, '<?= htmlspecialchars(addslashes($dev['name'])) ?>')"
                data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="fas fa-plus me-1"></i> Assign Task
            </button>
            <?php if (!empty($dev['portfolio_url'])): ?>
            <a href="<?= htmlspecialchars($dev['portfolio_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;" title="Portfolio">
                <i class="fas fa-external-link-alt"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== ALL TASKS TABLE ===== -->
<div class="card-theme">
    <div class="card-theme-header d-flex justify-content-between align-items-center">
        <h5 class="card-theme-title text-body"><i class="fas fa-list-check me-2" style="color:#6366f1;"></i>All Assigned Tasks</h5>
        <button class="btn btn-sm" style="background:#6366f1;color:white;border-radius:8px;font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#assignModal">
            <i class="fas fa-plus me-1"></i> New Task
        </button>
    </div>
    <div class="card-theme-body p-0">
        <?php if (empty($allTasks)): ?>
        <div class="text-center py-4 text-muted small">No tasks assigned yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                <thead style="background:var(--color-bg);border-bottom:2px solid var(--color-border);">
                    <tr>
                        <th class="ps-4 py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Task</th>
                        <th class="py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Developer</th>
                        <th class="py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Priority</th>
                        <th class="py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Due Date</th>
                        <th class="py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Hours / Earned</th>
                        <th class="pe-4 py-2 text-muted fw-semibold" style="font-size:0.72rem;text-transform:uppercase;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allTasks as $t):
                    $earned = (float)$t['hourly_rate'] * (float)$t['hours_worked'];
                    $priorityColors = ['urgent'=>'#dc2626','high'=>'#ea580c','medium'=>'#3b82f6','low'=>'#16a34a'];
                    $pc = $priorityColors[$t['priority']] ?? '#64748b';
                ?>
                <tr>
                    <td class="ps-4 py-2">
                        <div class="fw-semibold text-body"><?= htmlspecialchars($t['title']) ?></div>
                        <?php if (!empty($t['proj_title'])): ?>
                        <div class="text-muted" style="font-size:0.72rem;"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($t['proj_title']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-2">
                        <span class="fw-semibold text-body"><?= htmlspecialchars($t['dev_name'] ?? '—') ?></span>
                    </td>
                    <td class="py-2">
                        <span class="d-flex align-items-center gap-1">
                            <span class="priority-dot pd-<?= $t['priority'] ?>"></span>
                            <span style="font-size:0.78rem;font-weight:600;color:<?= $pc ?>;"><?= ucfirst($t['priority']) ?></span>
                        </span>
                    </td>
                    <td class="py-2 text-muted"><?= $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : '—' ?></td>
                    <td class="py-2">
                        <div style="font-size:0.78rem;"><?= $t['hours_worked'] ?>h worked</div>
                        <?php if ($earned > 0): ?>
                        <div style="font-size:0.72rem;color:#10b981;font-weight:700;">₦<?= number_format($earned, 0) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 py-2">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="update_task" value="1">
                            <select name="task_status" class="status-sel" onchange="this.form.submit()" style="background:<?= [
                                'assigned'=>'#f1f5f9','in_progress'=>'#dbeafe','review'=>'#fef3c7','completed'=>'#dcfce7','cancelled'=>'#fee2e2'
                            ][$t['status']]??'#f1f5f9'?>;color:<?= [
                                'assigned'=>'#475569','in_progress'=>'#1d4ed8','review'=>'#92400e','completed'=>'#15803d','cancelled'=>'#991b1b'
                            ][$t['status']]??'#475569'?>;">
                                <?php foreach (['assigned','in_progress','review','completed','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $t['status']===$s?'selected':''?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ASSIGN TASK MODAL ===== -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px;">
            <form method="POST">
                <input type="hidden" name="assign_task" value="1">
                <div class="modal-header border-0 px-4 pt-4 pb-0">
                    <h5 class="modal-title fw-bold text-body"><i class="fas fa-plus-circle me-2" style="color:#6366f1;"></i>Assign Task to Developer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Developer <span style="color:#ef4444;">*</span></label>
                            <select name="developer_id" id="assign-dev-select" class="form-select mt-1" required>
                                <option value="">— Select Developer —</option>
                                <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['active_tasks'] ?> active)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Link to Project (optional)</label>
                            <select name="project_id" class="form-select mt-1">
                                <option value="">— No Project Link —</option>
                                <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Task Title <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="title" class="form-control mt-1" placeholder="e.g. Build user authentication module" required>
                        </div>
                        <div class="col-12">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Description</label>
                            <textarea name="description" class="form-control mt-1" rows="3" placeholder="Detailed requirements, acceptance criteria, tech stack hints..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Priority</label>
                            <select name="priority" class="form-select mt-1">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Due Date</label>
                            <input type="date" name="due_date" class="form-control mt-1" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold text-body" style="font-size:0.82rem;">Hourly Rate (₦)</label>
                            <input type="number" name="hourly_rate" class="form-control mt-1" placeholder="e.g. 8000" min="0" step="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;border-radius:8px;font-weight:600;padding:0.5rem 1.5rem;">
                        <i class="fas fa-paper-plane me-1"></i> Assign Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prefillAssign(devId, devName) {
    const select = document.getElementById('assign-dev-select');
    if (select) select.value = devId;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
