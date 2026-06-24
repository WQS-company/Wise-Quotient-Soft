<?php
$path_to_root = "../";

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];

// Fetch current role
$roleStmt = $pdo->prepare("SELECT role, name, email FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userObj = $roleStmt->fetch(PDO::FETCH_ASSOC);
$user_role = $userObj ? strtolower($userObj['role']) : 'user';

// Already a developer — redirect to hub
if ($user_role === 'developer') {
    header("Location: developer_hub.php");
    exit;
}

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_developer'])) {
    $skills         = trim($_POST['skills'] ?? '');
    $portfolioUrl   = trim($_POST['portfolio_url'] ?? '');
    $githubUrl      = trim($_POST['github_url'] ?? '');
    $experience     = trim($_POST['experience'] ?? '');
    $yearsExp       = (int)($_POST['years_experience'] ?? 0);
    $expectedRate   = (float)($_POST['hourly_rate_expected'] ?? 0);

    $check = $pdo->prepare("SELECT id, status FROM developer_requests WHERE user_id = ? LIMIT 1");
    $check->execute([$userId]);
    if ($check->rowCount() > 0) {
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing['status'] === 'rejected') {
            $pdo->prepare("UPDATE developer_requests SET skills=?, portfolio_url=?, github_url=?, experience=?, years_experience=?, hourly_rate_expected=?, status='pending', updated_at=NOW() WHERE user_id=?")
                ->execute([$skills, $portfolioUrl, $githubUrl, $experience, $yearsExp, $expectedRate, $userId]);
        }
    } else {
        $pdo->prepare("INSERT INTO developer_requests (user_id, skills, portfolio_url, github_url, experience, years_experience, hourly_rate_expected, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())")
            ->execute([$userId, $skills, $portfolioUrl, $githubUrl, $experience, $yearsExp, $expectedRate]);
    }
    $_SESSION['success_message'] = "Your developer application has been submitted! We'll review it within 24–48 hours.";
    header("Location: upgrade_developer.php");
    exit;
}

// Fetch current request status
$requestStatus = null;
$reqStmt = $pdo->prepare("SELECT * FROM developer_requests WHERE user_id = ? LIMIT 1");
$reqStmt->execute([$userId]);
if ($reqStmt->rowCount() > 0) {
    $requestStatus = $reqStmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = "Join as Developer";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
/* ===== DEVELOPER APPLICATION PAGE ===== */
.dev-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2027 100%);
    border-radius: 20px; padding: 3.5rem 2rem;
    position: relative; overflow: hidden; margin-bottom: 2rem; color: white;
}
.dev-hero::before {
    content:''; position:absolute; top:-80px; right:-80px;
    width:320px; height:320px;
    background: radial-gradient(circle, rgba(99,102,241,0.25) 0%, transparent 70%);
    border-radius:50%;
}
.dev-hero::after {
    content:''; position:absolute; bottom:-80px; left:-60px;
    width:260px; height:260px;
    background: radial-gradient(circle, rgba(16,185,129,0.15) 0%, transparent 70%);
    border-radius:50%;
}
.dev-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.4);
    color: #a5b4fc; font-size: 0.78rem; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    padding: 0.35rem 1rem; border-radius: 50px; margin-bottom: 1.25rem;
}
.hero-stat-pill-dev {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px; padding: 0.9rem 1.5rem; text-align: center; min-width: 100px;
}
.hero-stat-pill-dev .num { font-size: 1.5rem; font-weight: 800; color: #a5b4fc; line-height: 1; }
.hero-stat-pill-dev .lbl { font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-top: 0.3rem; font-weight: 500; }

.benefit-card {
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 14px; padding: 1.5rem; text-align: center;
    position: relative; overflow: hidden; transition: all 0.3s;
}
.benefit-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
.benefit-card.b1::before { background: linear-gradient(90deg,#6366f1,#8b5cf6); }
.benefit-card.b2::before { background: linear-gradient(90deg,#10b981,#34d399); }
.benefit-card.b3::before { background: linear-gradient(90deg,#f59e0b,#fbbf24); }
.benefit-card.b4::before { background: linear-gradient(90deg,#0ea5e9,#38bdf8); }
.benefit-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px rgba(0,0,0,0.08); }
.benefit-icon {
    width: 58px; height: 58px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 1rem; font-size: 1.4rem;
}

.skill-tag {
    display: inline-flex; align-items: center; gap: 0.3rem;
    background: linear-gradient(135deg,#eff6ff,#dbeafe);
    border: 1px solid #bfdbfe; color: #1d4ed8;
    padding: 0.3rem 0.75rem; border-radius: 50px;
    font-size: 0.8rem; font-weight: 600; margin: 0.2rem;
}
.skill-tag .remove-skill {
    cursor: pointer; color: #64748b; font-size: 0.75rem;
    padding: 0 0.1rem; line-height: 1;
}
.skill-tag .remove-skill:hover { color: #ef4444; }
#skill-input-container {
    min-height: 48px; border: 1.5px solid var(--color-border); border-radius: 10px;
    padding: 0.4rem 0.6rem; display: flex; flex-wrap: wrap; align-items: center;
    gap: 0.2rem; cursor: text; transition: border-color 0.2s;
    background: white;
}
#skill-input-container:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
#skill-input-container input {
    border: none; outline: none; font-size: 0.88rem; flex: 1; min-width: 120px;
    background: transparent; padding: 0.2rem 0.4rem;
}
.form-premium {
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 16px; padding: 2rem;
}
.form-label-premium {
    font-size: 0.82rem; font-weight: 700; color: var(--color-text-body);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;
}
.input-premium {
    border: 1.5px solid var(--color-border); border-radius: 10px;
    padding: 0.7rem 1rem; font-size: 0.9rem; transition: all 0.2s;
    width: 100%;
}
.input-premium:focus {
    border-color: #6366f1; outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.dev-apply-btn {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; border: none; border-radius: 50px;
    padding: 0.875rem 2.5rem; font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: all 0.3s;
    box-shadow: 0 4px 20px rgba(99,102,241,0.35);
}
.dev-apply-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(99,102,241,0.45); }

.tech-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
.quick-skill {
    background:var(--color-bg); border: 1px solid var(--color-border);
    border-radius: 50px; padding: 0.25rem 0.75rem;
    font-size: 0.78rem; cursor: pointer; transition: all 0.15s;
    color: var(--color-text-light);
}
.quick-skill:hover { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
.timeline-step .circle { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; border: 2px solid; }
.timeline-step.done .circle   { background: #10b981; border-color: #10b981; color: white; }
.timeline-step.active .circle { background: #f59e0b; border-color: #f59e0b; color: white; }
.timeline-step.idle .circle   { background: white; border-color: var(--color-border); color: var(--color-text-light); }
.timeline-line { flex: 1; height: 2px; background: var(--color-border); min-width: 30px; max-width: 60px; margin-bottom: 22px; }
.timeline-line.done { background: #10b981; }
</style>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3 shadow-sm mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- ===== HERO ===== -->
<div class="dev-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7" style="position:relative;z-index:1;">
            <div class="dev-badge"><i class="fas fa-code" style="font-size:0.65rem;"></i> Developer Program</div>
            <h1 style="font-size:clamp(1.8rem,4vw,2.5rem);font-weight:800;line-height:1.2;margin-bottom:1rem;color:white;">
                Build. Earn.<br><span style="color:#a5b4fc;">Get Hired by WQS.</span>
            </h1>
            <p style="font-size:1rem;color:rgba(255,255,255,0.7);max-width:500px;line-height:1.7;margin-bottom:1.5rem;">
                Join the Wise Quotient Soft developer team. Work on real client projects, earn competitive rates in Naira, and grow your career with one of Nigeria's top software firms.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <div class="hero-stat-pill-dev"><div class="num">₦5k+</div><div class="lbl">Per Hour Rate</div></div>
                <div class="hero-stat-pill-dev"><div class="num">Real</div><div class="lbl">Client Projects</div></div>
                <div class="hero-stat-pill-dev"><div class="num">Fast</div><div class="lbl">Task Payouts</div></div>
                <div class="hero-stat-pill-dev"><div class="num">Remote</div><div class="lbl">Friendly</div></div>
            </div>
        </div>
        <div class="col-lg-5 d-none d-lg-flex justify-content-center align-items-center" style="position:relative;z-index:1;">
            <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);backdrop-filter:blur(12px);border-radius:20px;padding:2rem;font-family:monospace;font-size:0.85rem;line-height:1.8;color:rgba(255,255,255,0.8);min-width:280px;">
                <div style="color:#86efac;">// WQS Developer Portal</div>
                <div><span style="color:#93c5fd;">const</span> <span style="color:#fbbf24;">developer</span> = {</div>
                <div>&nbsp;&nbsp;<span style="color:#f9a8d4;">role</span>: <span style="color:#86efac;">"hired"</span>,</div>
                <div>&nbsp;&nbsp;<span style="color:#f9a8d4;">tasks</span>: <span style="color:#fbbf24;">assigned</span>,</div>
                <div>&nbsp;&nbsp;<span style="color:#f9a8d4;">earnings</span>: <span style="color:#86efac;">growing</span>,</div>
                <div>&nbsp;&nbsp;<span style="color:#f9a8d4;">team</span>: <span style="color:#86efac;">"WQS Elite"</span></div>
                <div>};</div>
                <div style="margin-top:0.5rem;color:#a5b4fc;">→ status: <span style="color:#4ade80;">ACTIVE</span></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== BENEFITS ===== -->
<h5 class="fw-bold text-body mb-3"><i class="fas fa-star me-2" style="color:#6366f1;"></i>Why Join as a Developer?</h5>
<div class="row g-4 mb-4">
    <div class="col-md-3 col-6">
        <div class="benefit-card b1 h-100">
            <div class="benefit-icon" style="background:rgba(99,102,241,0.1);"><i class="fas fa-money-bill-wave" style="color:#6366f1;"></i></div>
            <h6 class="fw-bold text-body mb-1">Competitive Pay</h6>
            <p class="text-muted small mb-0">Earn ₦5,000–₦50,000+ per task based on complexity and your skill level.</p>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="benefit-card b2 h-100">
            <div class="benefit-icon" style="background:rgba(16,185,129,0.1);"><i class="fas fa-tasks" style="color:#10b981;"></i></div>
            <h6 class="fw-bold text-body mb-1">Real Projects</h6>
            <p class="text-muted small mb-0">Work on live client projects across fintech, healthcare, e-commerce and more.</p>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="benefit-card b3 h-100">
            <div class="benefit-icon" style="background:rgba(245,158,11,0.1);"><i class="fas fa-chart-line" style="color:#f59e0b;"></i></div>
            <h6 class="fw-bold text-body mb-1">Career Growth</h6>
            <p class="text-muted small mb-0">Build your portfolio, earn performance bonuses, and get promoted to senior roles.</p>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="benefit-card b4 h-100">
            <div class="benefit-icon" style="background:rgba(14,165,233,0.1);"><i class="fas fa-laptop-house" style="color:#0ea5e9;"></i></div>
            <h6 class="fw-bold text-body mb-1">Remote Flexible</h6>
            <p class="text-muted small mb-0">Work from anywhere in Nigeria. Set your own schedule, deliver quality results.</p>
        </div>
    </div>
</div>

<!-- ===== APPLICATION + STATUS ===== -->
<div class="row g-4 mb-4">
    <!-- Application Form -->
    <div class="col-lg-7">
        <?php if ($requestStatus === null || $requestStatus['status'] === 'rejected'): ?>
        <div class="card-theme">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body">
                    <i class="fas fa-paper-plane me-2" style="color:#6366f1;"></i>
                    <?= ($requestStatus && $requestStatus['status']==='rejected') ? 'Re-apply as Developer' : 'Developer Application' ?>
                </h5>
            </div>
            <div class="card-theme-body">
                <?php if ($requestStatus && $requestStatus['status']==='rejected'): ?>
                <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:#991b1b;">
                    <i class="fas fa-times-circle me-2"></i> Your previous application was not approved.
                    <?php if (!empty($requestStatus['admin_note'])): ?>
                    <strong>Reason:</strong> <?= htmlspecialchars($requestStatus['admin_note']) ?>
                    <?php endif; ?>
                    You may resubmit below.
                </div>
                <?php endif; ?>

                <form method="POST" id="devApplicationForm">
                    <!-- Skills -->
                    <div class="mb-4">
                        <label class="form-label-premium">Your Technical Skills <span style="color:#ef4444;">*</span></label>
                        <p class="text-muted mb-2" style="font-size:0.8rem;">Type a skill and press Enter or comma to add. Or click quick-add below.</p>
                        <div id="skill-input-container">
                            <input type="text" id="skill-text-input" placeholder="e.g. React, Node.js, PHP...">
                        </div>
                        <input type="hidden" name="skills" id="skills-hidden">
                        <!-- Quick skills -->
                        <div class="tech-grid mt-2">
                            <?php
                            $quickSkills = ['PHP','JavaScript','React','Vue.js','Node.js','Python','Laravel','MySQL','MongoDB','Flutter','Kotlin','Swift','TypeScript','Next.js','Docker','AWS','UI/UX Design','WordPress','REST APIs','Git'];
                            foreach ($quickSkills as $qs):
                            ?>
                            <span class="quick-skill" onclick="addSkill('<?= $qs ?>')"><?= $qs ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-premium">Portfolio URL</label>
                            <input type="url" name="portfolio_url" class="input-premium" placeholder="https://yourportfolio.com" value="<?= htmlspecialchars($requestStatus['portfolio_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-premium">GitHub / GitLab URL</label>
                            <input type="url" name="github_url" class="input-premium" placeholder="https://github.com/username" value="<?= htmlspecialchars($requestStatus['github_url'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-premium">Years of Experience</label>
                            <select name="years_experience" class="input-premium">
                                <?php for ($y=0; $y<=20; $y++): ?>
                                <option value="<?= $y ?>" <?= ($requestStatus['years_experience'] ?? 0) == $y ? 'selected' : '' ?>>
                                    <?= $y ?> year<?= $y!=1?'s':''?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-premium">Expected Hourly Rate (₦)</label>
                            <input type="number" name="hourly_rate_expected" class="input-premium" placeholder="e.g. 8000" min="0" value="<?= htmlspecialchars($requestStatus['hourly_rate_expected'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-premium">Experience & Background <span style="color:#ef4444;">*</span></label>
                        <textarea name="experience" class="input-premium" rows="4" placeholder="Tell us about your experience, past projects, what you specialize in, and why you'd be a great fit for the WQS dev team..."><?= htmlspecialchars($requestStatus['experience'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <p class="text-muted small mb-0">By applying you agree to WQS Developer Terms.</p>
                        <button type="submit" name="apply_developer" class="dev-apply-btn" id="devApplyBtn" onclick="prepareSubmit()">
                            <i class="fas fa-paper-plane me-2"></i> Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($requestStatus['status'] === 'pending'): ?>
        <div class="card-theme">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-hourglass-half text-warning me-2"></i>Application Status</h5>
            </div>
            <div class="card-theme-body">
                <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #f59e0b;border-radius:14px;padding:2rem;text-align:center;margin-bottom:1.25rem;">
                    <div style="font-size:2.5rem;margin-bottom:0.75rem;">⏳</div>
                    <h6 class="fw-bold mb-1" style="color:#92400e;">Application Under Review</h6>
                    <p class="mb-0 small" style="color:#78350f;">Our technical team is evaluating your profile. Expected response: 24–48 hours.</p>
                </div>
                <!-- Timeline -->
                <div class="d-flex align-items-center justify-content-center gap-0">
                    <div class="timeline-step done text-center" style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;">
                        <div class="circle"><i class="fas fa-check fa-xs"></i></div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--color-text-light);">Applied</div>
                    </div>
                    <div class="timeline-line done"></div>
                    <div class="timeline-step active text-center" style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;">
                        <div class="circle"><i class="fas fa-search fa-xs"></i></div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--color-text-light);">Review</div>
                    </div>
                    <div class="timeline-line"></div>
                    <div class="timeline-step idle text-center" style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;">
                        <div class="circle"><i class="fas fa-user-check fa-xs"></i></div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--color-text-light);">Hired</div>
                    </div>
                    <div class="timeline-line"></div>
                    <div class="timeline-step idle text-center" style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;">
                        <div class="circle"><i class="fas fa-code fa-xs"></i></div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--color-text-light);">Active Dev</div>
                    </div>
                </div>
                <div class="text-center mt-3 text-muted small">Submitted: <?= date("M d, Y", strtotime($requestStatus['created_at'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info Panel -->
    <div class="col-lg-5">
        <div class="card-theme mb-4">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-clipboard-list me-2" style="color:#6366f1;"></i>What We Look For</h5>
            </div>
            <div class="card-theme-body">
                <?php
                $criteria = [
                    ['fas fa-code','Strong Coding Skills','Proficiency in at least 2 languages or frameworks (PHP, JS, Python, etc.)'],
                    ['fas fa-project-diagram','Project Experience','Prior work on real-world apps, whether freelance, employed, or personal projects'],
                    ['fas fa-clock','Reliability','Ability to meet deadlines and communicate progress consistently'],
                    ['fas fa-users','Team Player','Comfortable working in a remote team with code reviews and collaboration'],
                    ['fas fa-graduation-cap','Growth Mindset','Willingness to learn new technologies as projects demand'],
                ];
                foreach ($criteria as [$icon, $title, $desc]):
                ?>
                <div class="d-flex gap-3 mb-3">
                    <div style="width:36px;height:36px;background:rgba(99,102,241,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="<?= $icon ?>" style="color:#6366f1;font-size:0.85rem;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold text-body" style="font-size:0.88rem;"><?= $title ?></div>
                        <div class="text-muted" style="font-size:0.78rem;"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-theme">
            <div class="card-theme-header">
                <h5 class="card-theme-title text-body"><i class="fas fa-question-circle me-2 text-primary"></i>Process FAQ</h5>
            </div>
            <div class="card-theme-body">
                <?php
                $faqs = [
                    ['How long does review take?', 'Our technical team reviews applications within 24–48 business hours.'],
                    ['Is there a test or interview?', 'For senior roles, we may request a brief code test. Most applicants go straight to task assignment.'],
                    ['Can I work part-time?', 'Yes — you can pick tasks that fit your schedule. There is no minimum hours requirement.'],
                    ['How do I get paid?', 'Earnings are tracked per task (hourly rate × hours logged). Payments are processed monthly via bank transfer.'],
                ];
                foreach ($faqs as $i => [$q, $a]):
                ?>
                <div style="border:1px solid var(--color-border);border-radius:10px;margin-bottom:0.6rem;overflow:hidden;">
                    <button class="faq-question collapsed w-100 text-start p-3 bg-body border-0 fw-semibold" style="font-size:0.85rem;color:var(--color-text-body);display:flex;justify-content:space-between;align-items:center;" data-bs-toggle="collapse" data-bs-target="#dfaq<?= $i ?>">
                        <?= $q ?> <i class="fas fa-chevron-down fa-xs text-muted" style="transition:transform 0.3s;"></i>
                    </button>
                    <div id="dfaq<?= $i ?>" class="collapse">
                        <div class="p-3 pt-0 text-muted" style="font-size:0.82rem;"><?= $a ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
let skills = [];

function renderSkills() {
    const container = document.getElementById('skill-input-container');
    // Remove existing tags
    container.querySelectorAll('.skill-tag').forEach(el => el.remove());
    const input = container.querySelector('input');
    skills.forEach((skill, idx) => {
        const tag = document.createElement('span');
        tag.className = 'skill-tag';
        tag.innerHTML = `${skill} <span class="remove-skill" onclick="removeSkill(${idx})">×</span>`;
        container.insertBefore(tag, input);
    });
    document.getElementById('skills-hidden').value = JSON.stringify(skills);
}

function addSkill(name) {
    const clean = name.trim();
    if (clean && !skills.includes(clean)) {
        skills.push(clean);
        renderSkills();
    }
}

function removeSkill(idx) {
    skills.splice(idx, 1);
    renderSkills();
}

document.getElementById('skill-text-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addSkill(this.value.replace(',',''));
        this.value = '';
    }
});
document.getElementById('skill-input-container').addEventListener('click', function() {
    this.querySelector('input').focus();
});

function prepareSubmit() {
    document.getElementById('skills-hidden').value = JSON.stringify(skills);
}

// Pre-fill skills if re-applying
<?php if ($requestStatus && !empty($requestStatus['skills'])): ?>
const existingSkills = <?= $requestStatus['skills'] ?>;
if (Array.isArray(existingSkills)) { skills = existingSkills; renderSkills(); }
<?php endif; ?>

// FAQ chevron rotation
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.querySelector('.fa-chevron-down').style.transform =
            this.classList.contains('collapsed') ? '' : 'rotate(180deg)';
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
