<?php
$path_to_root = "../";
$page_title = "My Projects";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$userId = $_SESSION['user']['id'];

$requestQuery = $mysqli->prepare("SELECT * FROM client_requests WHERE user_id = ? ORDER BY created_at DESC");
$requestQuery->bind_param("i", $userId);
$requestQuery->execute();
$requestResult = $requestQuery->get_result();
$clientRequests = $requestResult->fetch_all(MYSQLI_ASSOC);

$projects = [];
$projQuery = $mysqli->prepare("SELECT * FROM ongoing_projects WHERE request_id = ?");
foreach ($clientRequests as $request) {
    $projQuery->bind_param("i", $request['id']);
    $projQuery->execute();
    $projResult = $projQuery->get_result();
    $projects[$request['id']] = $projResult->fetch_all(MYSQLI_ASSOC);
}

$projectFiles = [];
$fileQuery = $mysqli->prepare("SELECT * FROM client_request_files WHERE request_id = ?");
foreach ($clientRequests as $request) {
    $fileQuery->bind_param("i", $request['id']);
    $fileQuery->execute();
    $res = $fileQuery->get_result();
    $projectFiles[$request['id']] = $res->fetch_all(MYSQLI_ASSOC);
}

$projectTeams = [];
$teamQuery = $mysqli->prepare("SELECT pt.*, u.name, u.email, u.phone, u.picture, u.profession FROM project_team pt JOIN users u ON pt.user_id = u.id WHERE pt.project_id = ?");
foreach ($projects as $requestId => $projList) {
    foreach ($projList as $proj) {
        $teamQuery->bind_param("i", $proj['id']);
        $teamQuery->execute();
        $res = $teamQuery->get_result();
        $projectTeams[$proj['id']] = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$projectStacks = [];
$stackQuery = $mysqli->prepare("SELECT * FROM project_tech_stacks WHERE project_id = ?");
foreach ($projects as $requestId => $projList) {
    foreach ($projList as $proj) {
        $stackQuery->bind_param("i", $proj['id']);
        $stackQuery->execute();
        $res = $stackQuery->get_result();
        $projectStacks[$proj['id']] = array_column($res->fetch_all(MYSQLI_ASSOC), 'stack_name');
    }
}

$requestQuery->close(); $projQuery->close(); $fileQuery->close(); $teamQuery->close(); $stackQuery->close();
?>

<style>
/* ======= MY PROJECTS PREMIUM STYLES ======= */
.projects-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 16px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.projects-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.project-tab-btn {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid transparent;
    background: transparent; width: 100%; text-align: left; cursor: pointer;
    font-size: 0.88rem; font-weight: 500; color: var(--color-text-light);
    transition: all 0.2s; margin-bottom: 0.35rem;
}
.project-tab-btn:hover { background: var(--color-bg); color: var(--color-text-body); }
.project-tab-btn.active {
    background: linear-gradient(135deg, #0A2D5E, #1a5db5) !important;
    color: white !important; border-color: transparent;
    box-shadow: 0 4px 12px rgba(10,45,94,0.3);
}
.project-tab-btn .tab-status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.progress-ring-wrap {
    position: relative; width: 110px; height: 110px; margin: 0 auto 1rem;
}
.progress-ring-svg { transform: rotate(-90deg); }
.progress-ring-circle-bg { fill: none; stroke: #e2e8f0; stroke-width: 8; }
.progress-ring-circle-fill {
    fill: none; stroke: url(#grad); stroke-width: 8;
    stroke-linecap: round; transition: stroke-dashoffset 1s ease;
}
.progress-ring-text {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    font-size: 1.3rem; font-weight: 900; color: #0A2D5E; line-height: 1;
}
.info-pill {
    display: flex; flex-direction: column; gap: 0.15rem;
    background:var(--color-bg); border: 1px solid var(--color-border);
    border-radius: 10px; padding: 0.75rem 1rem;
}
.info-pill .pill-label { font-size: 0.72rem; color: var(--color-text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.info-pill .pill-value { font-size: 0.92rem; font-weight: 700; color: var(--color-text-body); }
.stack-tag {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #bfdbfe; color: #1d4ed8;
    padding: 0.3rem 0.75rem; border-radius: 50px;
    font-size: 0.78rem; font-weight: 600;
}
.team-card {
    background: #fff; border: 1px solid var(--color-border); border-radius: 12px;
    padding: 1rem; transition: all 0.2s;
}
.team-card:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.06); transform: translateY(-2px); }
.team-avatar {
    width: 46px; height: 46px; border-radius: 50%; object-fit: cover;
    border: 2px solid var(--color-border);
}
.action-btn {
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.6rem 0.75rem; border-radius: 10px; font-size: 0.82rem;
    font-weight: 600; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer;
    width: 100%;
}
.action-btn:hover { opacity: 0.88; transform: translateY(-1px); }
.pending-state-card {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1.5px solid #fcd34d; border-radius: 16px; padding: 2.5rem;
    text-align: center;
}
</style>

<!-- Hero -->
<div class="projects-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.25rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;">
                    <i class="fas fa-briefcase me-1"></i> My Projects
                </span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.4rem;">Project Portfolio</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin:0;">
                Track progress, download builds, and view your development team.
                <?php if (!empty($clientRequests)): ?>
                <span style="color:#ffb380;font-weight:600;"><?= count($clientRequests) ?> project<?= count($clientRequests)>1?'s':''?></span> found.
                <?php endif; ?>
            </p>
        </div>
        <a href="client-request.php" class="btn" style="background:#E15501;border:none;color:white;border-radius:8px;font-size:0.85rem;white-space:nowrap;position:relative;z-index:1;">
            <i class="fas fa-plus me-1"></i> New Request
        </a>
    </div>
</div>

<?php if (empty($clientRequests)): ?>
<!-- Empty State -->
<div class="card-theme" style="padding: 4rem 2rem; text-align: center;">
    <div style="font-size: 4rem; margin-bottom: 1rem;">📂</div>
    <h4 class="fw-bold text-body">No Projects Yet</h4>
    <p class="text-muted mb-4">Submit a project request and our team will get started building your vision.</p>
    <a href="client-request.php" class="btn" style="background:linear-gradient(135deg,#0A2D5E,#1a5db5);color:white;border-radius:10px;padding:0.75rem 2rem;font-weight:600;">
        <i class="fas fa-paper-plane me-2"></i> Submit Your First Request
    </a>
</div>

<?php else: ?>
<div class="row g-4">
    <!-- Left: Tab Nav -->
    <div class="col-12 col-lg-3">
        <div class="card-theme p-3">
            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-text-light);font-weight:700;padding:0 0.5rem;margin-bottom:0.75rem;">
                <i class="fas fa-folder me-1"></i> Your Proposals
            </div>
            <div class="nav flex-column" id="projectTabs" role="tablist">
                <?php foreach ($clientRequests as $index => $request):
                    $hasProject = !empty($projects[$request['id']]);
                    $reqStatus = $request['status'];
                    $dotColors = ['pending'=>'#f59e0b','reviewed'=>'#0ea5e9','approved'=>'#10b981','rejected'=>'#ef4444'];
                    $dotColor = $dotColors[$reqStatus] ?? '#94a3b8';
                ?>
                <button class="project-tab-btn <?= ($index===0)?'active':'' ?>"
                    data-bs-toggle="tab" data-bs-target="#content-<?= $request['id'] ?>"
                    type="button" role="tab">
                    <span class="tab-status-dot" style="background:<?= $dotColor ?>;"></span>
                    <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($request['title']) ?></span>
                    <?php if ($hasProject): ?>
                    <i class="fas fa-chevron-right" style="font-size:0.65rem;opacity:0.6;flex-shrink:0;"></i>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Tab Content -->
    <div class="col-12 col-lg-9">
        <div class="tab-content" id="projectTabsContent">
            <?php foreach ($clientRequests as $index => $request):
                $requestProjects = $projects[$request['id']] ?? [];
            ?>
            <div class="tab-pane fade <?= ($index===0)?'show active':'' ?>" id="content-<?= $request['id'] ?>" role="tabpanel">

                <?php if (empty($requestProjects)): ?>
                <div class="pending-state-card">
                    <div style="font-size:3rem;margin-bottom:1rem;">⏳</div>
                    <h5 class="fw-bold" style="color:#92400e;">Project Workspace Pending</h5>
                    <p class="text-muted mb-0" style="max-width:400px;margin:0 auto;">Your request is <strong><?= ucfirst($request['status']) ?></strong>. Our team will initialize your project workspace and notify you once everything is ready.</p>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <?php
                        $statusInfo = [
                            'pending'  => ['⏳','Awaiting Review','#92400e'],
                            'reviewed' => ['🔍','Under Review','#1e40af'],
                            'approved' => ['✅','Approved – Setting Up','#15803d'],
                            'rejected' => ['❌','Not Approved','#991b1b'],
                        ];
                        [$sIcon,$sLabel,$sColor] = $statusInfo[$request['status']] ?? ['•','Unknown','#64748b'];
                        ?>
                        <span style="background:<?= $sColor ?>11;color:<?= $sColor ?>;border:1px solid <?= $sColor ?>33;padding:0.35rem 1rem;border-radius:50px;font-size:0.82rem;font-weight:700;"><?= $sIcon ?> <?= $sLabel ?></span>
                    </div>
                </div>

                <?php else: foreach ($requestProjects as $proj):
                    $projFiles = $projectFiles[$request['id']] ?? [];
                    $projTeam  = $projectTeams[$proj['id']] ?? [];
                    $projStack = $projectStacks[$proj['id']] ?? [];
                    $progressVal = (int)$proj['progress'];
                    $radius = 45; $circumference = 2 * M_PI * $radius;
                    $offset = $circumference - ($progressVal / 100) * $circumference;
                ?>
                <div class="card-theme mb-4">

                    <!-- Status Bar -->
                    <?php
                    $barColors = ['ongoing'=>'#2563eb','on-hold'=>'#f59e0b','completed'=>'#10b981','cancelled'=>'#ef4444'];
                    $bc = $barColors[$proj['status']] ?? '#0A2D5E';
                    ?>
                    <div style="height:4px;background:linear-gradient(90deg,<?= $bc ?>,<?= $bc ?>88);border-radius:16px 16px 0 0;"></div>

                    <!-- Gallery -->
                    <?php if (!empty($projFiles)): $imgCount = 0; ?>
                    <div id="carousel-<?= $proj['id'] ?>" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" style="max-height:360px;background:#020617;border-radius:0;">
                            <?php foreach ($projFiles as $file): if ($file['file_type']==='image'):
                            $fileUrl = $file['file_path'];
                            if (!preg_match('/^(https?:)?\/\//i', $fileUrl)) {
                                $fileUrl = $path_to_root . $fileUrl;
                            }
                            ?>
                            <div class="carousel-item <?= ($imgCount===0)?'active':'' ?>">
                                <img src="<?= htmlspecialchars($fileUrl) ?>" class="d-block w-100" style="object-fit:contain;max-height:360px;" alt="Project">
                            </div>
                            <?php $imgCount++; endif; endforeach; ?>
                        </div>
                        <?php if ($imgCount > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?= $proj['id'] ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?= $proj['id'] ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="p-4">
                        <!-- Title + Status -->
                        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                            <h3 class="fw-bold text-body mb-0" style="font-size:1.3rem;"><?= htmlspecialchars($proj['title']) ?></h3>
                            <span style="background:<?= $bc ?>15;color:<?= $bc ?>;border:1px solid <?= $bc ?>44;padding:0.3rem 0.85rem;border-radius:50px;font-size:0.78rem;font-weight:700;text-transform:uppercase;">
                                <?= ucfirst($proj['status']) ?>
                            </span>
                        </div>

                        <div class="row g-4">
                            <!-- Left Details -->
                            <div class="col-lg-8">
                                <?php if (!empty($proj['description'])): ?>
                                <p class="text-muted mb-4" style="font-size:0.9rem;line-height:1.7;"><?= nl2br(htmlspecialchars($proj['description'])) ?></p>
                                <?php endif; ?>

                                <!-- Info Pills -->
                                <div class="row g-2 mb-4">
                                    <div class="col-6 col-sm-4">
                                        <div class="info-pill">
                                            <span class="pill-label">Start Date</span>
                                            <span class="pill-value"><?= date("M d, Y", strtotime($proj['start_date'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="info-pill">
                                            <span class="pill-label">Deadline</span>
                                            <span class="pill-value"><?= date("M d, Y", strtotime($proj['end_date'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <div class="info-pill">
                                            <span class="pill-label">Budget (NGN)</span>
                                            <span class="pill-value" style="color:#0A2D5E;">₦<?= number_format($proj['budget'], 0) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="info-pill">
                                            <span class="pill-label">Budget (USD)</span>
                                            <span class="pill-value" style="color:#64748b;font-size:0.8rem;">$<?= number_format($proj['final_budget'], 0) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="info-pill">
                                            <span class="pill-label">Last Updated</span>
                                            <span class="pill-value"><?= date("M d, Y", strtotime($proj['updated_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tech Stack -->
                                <?php if (!empty($projStack)): ?>
                                <div class="mb-3">
                                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-text-light);font-weight:700;margin-bottom:0.6rem;"><i class="fas fa-code me-1"></i> Tech Stack</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($projStack as $stack): ?>
                                        <span class="stack-tag"><?= htmlspecialchars($stack) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Progress & Actions -->
                            <div class="col-lg-4">
                                <div style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border:1px solid #bfdbfe;border-radius:14px;padding:1.5rem;text-align:center;">
                                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:#1e40af;font-weight:700;margin-bottom:1rem;">Overall Progress</div>
                                    <!-- SVG Ring -->
                                    <div class="progress-ring-wrap">
                                        <svg class="progress-ring-svg" width="110" height="110" viewBox="0 0 110 110">
                                            <defs>
                                                <linearGradient id="grad-<?= $proj['id'] ?>" x1="0%" y1="0%" x2="100%" y2="0%">
                                                    <stop offset="0%" style="stop-color:#0A2D5E;stop-opacity:1" />
                                                    <stop offset="100%" style="stop-color:#2563eb;stop-opacity:1" />
                                                </linearGradient>
                                            </defs>
                                            <circle class="progress-ring-circle-bg" cx="55" cy="55" r="<?= $radius ?>"/>
                                            <circle class="progress-ring-circle-fill" cx="55" cy="55" r="<?= $radius ?>"
                                                stroke="url(#grad-<?= $proj['id'] ?>)"
                                                stroke-dasharray="<?= $circumference ?>"
                                                stroke-dashoffset="<?= $offset ?>"/>
                                        </svg>
                                        <div class="progress-ring-text"><?= $progressVal ?>%</div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex flex-column gap-2">
                                        <?php if (!empty($proj['live_url'])): ?>
                                        <a href="<?= htmlspecialchars($proj['live_url']) ?>" target="_blank" class="action-btn" style="background:linear-gradient(135deg,#0A2D5E,#1a5db5);color:white;">
                                            <i class="fas fa-eye"></i> Live Preview
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($proj['download_url'])): ?>
                                        <a href="<?= $path_to_root . htmlspecialchars($proj['download_url']) ?>" download class="action-btn" style="background:linear-gradient(135deg,#15803d,#16a34a);color:white;">
                                            <i class="fas fa-download"></i> Download Build
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($proj['doc_url'])): ?>
                                        <a href="<?= $path_to_root . htmlspecialchars($proj['doc_url']) ?>" target="_blank" class="action-btn" style="background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;">
                                            <i class="fas fa-file-alt"></i> Documentation
                                        </a>
                                        <?php endif; ?>
                                        <?php if (empty($proj['live_url']) && empty($proj['download_url']) && empty($proj['doc_url'])): ?>
                                        <div style="background:var(--color-bg);border:1px dashed #e2e8f0;border-radius:10px;padding:0.75rem;font-size:0.78rem;color:var(--color-text-light);">
                                            <i class="fas fa-clock me-1"></i> Files will appear when ready
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Members -->
                        <?php if (!empty($projTeam)): ?>
                        <div class="mt-4 pt-4" style="border-top:2px solid var(--color-border);">
                            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-text-light);font-weight:700;margin-bottom:1rem;"><i class="fas fa-users me-1"></i> Project Team</div>
                            <div class="row g-3">
                                <?php foreach ($projTeam as $member): $isPm = ($member['role']==='manager'); ?>
                                <div class="col-12 col-md-4">
                                    <div class="team-card">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <img src="<?= !empty($member['picture']) ? htmlspecialchars($member['picture']) : $path_to_root . 'images/default-avatar.png' ?>" class="team-avatar" alt="<?= htmlspecialchars($member['name']) ?>">
                                            <div style="min-width:0;flex:1;">
                                                <div class="fw-semibold text-body text-truncate" style="font-size:0.9rem;"><?= htmlspecialchars($member['name']) ?></div>
                                                <?php
                                                $roleText = 'Developer';
                                                $bgStyle = '#eff6ff';
                                                $colorStyle = '#1d4ed8';

                                                if ($isPm) {
                                                    $roleText = 'Project Manager';
                                                    $bgStyle = '#0A2D5E';
                                                    $colorStyle = 'white';
                                                } else {
                                                    $prof = !empty($member['profession']) ? trim($member['profession']) : '';
                                                    if (!empty($prof)) {
                                                        $roleText = $prof;
                                                        $profLower = strtolower($prof);
                                                        if (strpos($profLower, 'designer') !== false || strpos($profLower, 'ui') !== false || strpos($profLower, 'ux') !== false) {
                                                            $bgStyle = '#faf5ff'; // purple
                                                            $colorStyle = '#7c3aed';
                                                        } elseif (strpos($profLower, 'backend') !== false || strpos($profLower, 'database') !== false) {
                                                            $bgStyle = '#f0fdf4'; // green
                                                            $colorStyle = '#16a34a';
                                                        } elseif (strpos($profLower, 'frontend') !== false || strpos($profLower, 'web') !== false || strpos($profLower, 'react') !== false) {
                                                            $bgStyle = '#ecfeff'; // cyan
                                                            $colorStyle = '#0891b2';
                                                        } elseif (strpos($profLower, 'qa') !== false || strpos($profLower, 'tester') !== false || strpos($profLower, 'testing') !== false) {
                                                            $bgStyle = '#fff7ed'; // orange/amber
                                                            $colorStyle = '#ea580c';
                                                        } elseif (strpos($profLower, 'devops') !== false || strpos($profLower, 'cloud') !== false) {
                                                            $bgStyle = '#fff1f2'; // rose/red
                                                            $colorStyle = '#e11d48';
                                                        } elseif (strpos($profLower, 'ai') !== false || strpos($profLower, 'intelligence') !== false || strpos($profLower, 'ml') !== false || strpos($profLower, 'machine') !== false) {
                                                            $bgStyle = '#fffbeb'; // amber
                                                            $colorStyle = '#d97706';
                                                        } else {
                                                            $bgStyle = '#eff6ff'; // blue
                                                            $colorStyle = '#1d4ed8';
                                                        }
                                                    }
                                                }
                                                ?>
                                                <span style="background:<?= $bgStyle ?>;color:<?= $colorStyle ?>;padding:0.15rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;text-transform:uppercase;display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($roleText) ?>">
                                                    <?= htmlspecialchars($roleText) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if (!empty($member['task'])): ?>
                                        <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:8px;padding:0.5rem 0.75rem;font-size:0.78rem;color:var(--color-text-light);margin-bottom:0.5rem;">
                                            <i class="fas fa-tasks me-1 text-muted"></i><?= htmlspecialchars($member['task']) ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($isPm): ?>
                                        <div class="d-flex gap-2">
                                            <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="action-btn" style="background:#eff6ff;color:#1d4ed8;font-size:0.75rem;padding:0.45rem;">
                                                <i class="fas fa-envelope"></i> Email
                                            </a>
                                            <?php if (!empty($member['phone']) && $member['phone']!=='N/A'): ?>
                                            <a href="tel:<?= htmlspecialchars($member['phone']) ?>" class="action-btn" style="background:#f0fdf4;color:#15803d;font-size:0.75rem;padding:0.45rem;">
                                                <i class="fas fa-phone"></i> Call
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
