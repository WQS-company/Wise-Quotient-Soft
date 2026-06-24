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

$page_title = "Interview Management";
$current_page = "scholarship_interviews.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$shortlistedApps = [];
try {
    $shortlistedApps = $pdo->query("SELECT sa.id, sa.full_name, sa.application_code, s.title as scholarship_title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id WHERE sa.status='shortlisted' ORDER BY sa.full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$stats = ['total'=>0,'scheduled'=>0,'completed'=>0,'cancelled'=>0];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM application_interviews")->fetchColumn();
    $stats['scheduled'] = $pdo->query("SELECT COUNT(*) FROM application_interviews WHERE status='scheduled'")->fetchColumn();
    $stats['completed'] = $pdo->query("SELECT COUNT(*) FROM application_interviews WHERE status='completed'")->fetchColumn();
    $stats['cancelled'] = $pdo->query("SELECT COUNT(*) FROM application_interviews WHERE status='cancelled'")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.sci-stat{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:12px;padding:.6rem .75rem;display:flex;align-items:center;gap:.6rem;transition:all .3s}
.sci-stat:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.sci-stat .si{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.sci-stat .sv{font-size:1.1rem;font-weight:800;line-height:1}
.sci-stat .sl{font-size:.62rem;color:#64748b}
.sci-table-wrap{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden}
.sci-table{width:100%;border-collapse:collapse}
.sci-table th{padding:.6rem .7rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border);background:var(--color-bg);text-align:left;white-space:nowrap}
.sci-table td{padding:.6rem .7rem;font-size:.8rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.sci-table tr:last-child td{border-bottom:none}
.sci-table tr:hover td{background:var(--color-bg)}
.sci-badge{font-size:.63rem;padding:2px 8px;border-radius:50px;font-weight:600;display:inline-block;white-space:nowrap}
.sci-page-btn{padding:.35rem .8rem;border:1px solid var(--color-border);border-radius:8px;font-size:.78rem;font-weight:600;color:var(--color-text);text-decoration:none;transition:all .2s;cursor:pointer;background:var(--color-card-bg)}
.sci-page-btn:hover,.sci-page-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
.sci-calendar{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem}
.sci-cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.sci-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.sci-cal-day{padding:.4rem;text-align:center;font-size:.7rem;font-weight:600;color:#64748b;border-radius:6px}
.sci-cal-date{padding:.5rem;text-align:center;font-size:.78rem;border-radius:8px;cursor:pointer;transition:all .2s;position:relative}
.sci-cal-date:hover{background:var(--color-bg)}
.sci-cal-date.today{background:#dbeafe;color:#1d4ed8;font-weight:700}
.sci-cal-date.has-interview{background:#fef3c7;color:#92400e;font-weight:700}
.sci-cal-date.has-interview::after{content:'';width:5px;height:5px;border-radius:50%;background:#f59e0b;position:absolute;bottom:4px;left:50%;transform:translateX(-50%)}
.sci-form{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem}
.sci-form h5{font-weight:700;font-size:1rem;margin-bottom:1rem}
.detail-row{display:flex;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.78rem;font-weight:600;color:#64748b;min-width:130px}
.detail-value{font-size:.82rem;color:var(--color-text)}
@media(max-width:991.98px){.sci-table-wrap{overflow-x:auto}}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-video me-2"></i>Interview Management</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Schedule and manage scholarship interviews</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadInterviews()"><i class="fas fa-sync me-1"></i> Refresh</button>
            <button class="btn btn-warning fw-bold btn-sm rounded-pill" onclick="showScheduleForm()"><i class="fas fa-plus me-1"></i> Schedule Interview</button>
        </div>
    </div>
    <div class="row g-2 mt-3">
        <?php
        $statCards = [
            ['Total','fas fa-calendar',$stats['total'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Scheduled','fas fa-clock',$stats['scheduled'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Completed','fas fa-check-circle',$stats['completed'],'#10b981','rgba(16,185,129,.12)'],
            ['Cancelled','fas fa-times-circle',$stats['cancelled'],'#ef4444','rgba(239,68,68,.12)'],
        ];
        foreach ($statCards as $s): ?>
        <div class="col-6 col-md-3">
            <div style="background:rgba(255,255,255,.07);border-radius:10px;padding:.5rem .6rem;display:flex;align-items:center;gap:.5rem">
                <div style="width:28px;height:28px;border-radius:7px;background:<?= $s[4] ?>;display:flex;align-items:center;justify-content:center;color:<?= $s[3] ?>;font-size:.7rem"><i class="<?= $s[2] ?>"></i></div>
                <div><div style="font-size:.95rem;font-weight:800;line-height:1"><?= $s[1] ?></div><div style="font-size:.55rem;opacity:.7"><?= $s[0] ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- Schedule Interview Form -->
<div class="sci-form" id="scheduleForm" style="display:none">
    <h5 id="formTitle"><i class="fas fa-calendar-plus me-2 text-primary"></i>Schedule New Interview</h5>
    <input type="hidden" id="editInterviewId">
    <div class="row g-3">
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Application <span class="text-danger">*</span></label>
            <select id="formAppId" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem">
                <option value="">Select applicant...</option>
                <?php foreach ($shortlistedApps as $app): ?>
                    <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['full_name'] . ' (' . $app['application_code'] . ' - ' . $app['scholarship_title'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Date <span class="text-danger">*</span></label>
            <input type="date" id="formDate" class="form-control" style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-md-3">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Time <span class="text-danger">*</span></label>
            <input type="time" id="formTime" class="form-control" style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Venue</label>
            <input type="text" id="formVenue" class="form-control" placeholder="Physical location" style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Meeting Link</label>
            <input type="url" id="formLink" class="form-control" placeholder="https://zoom.us/j/..." style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Panel Members</label>
            <input type="text" id="formPanel" class="form-control" placeholder="Comma-separated names" style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Duration (minutes)</label>
            <input type="number" id="formDuration" class="form-control" value="30" min="15" max="180" style="border-radius:10px;border:1px solid var(--color-border)">
        </div>
        <div class="col-12">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Notes</label>
            <textarea id="formNotes" class="form-control" rows="2" placeholder="Interview notes..." style="border-radius:10px;border:1px solid var(--color-border)"></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.88rem" onclick="saveInterview()"><i class="fas fa-save me-1"></i> Save</button>
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="hideScheduleForm()">Cancel</button>
    </div>
</div>

<!-- Filters & View Toggle -->
<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
            <div class="position-relative">
                <i class="fas fa-search position-absolute text-muted" style="left:14px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search applicant name..." style="padding-left:40px;border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadInterviews()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadInterviews()">
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no_show">No Show</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <input type="date" id="filterDate" class="form-control" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadInterviews()">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" style="font-size:.85rem" id="viewListBtn" onclick="setView('list')"><i class="fas fa-list me-1"></i> List</button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" style="font-size:.85rem" id="viewCalBtn" onclick="setView('calendar')"><i class="fas fa-calendar me-1"></i> Calendar</button>
        </div>
    </div>
</div>

<!-- List View -->
<div id="listView">
    <div class="sci-table-wrap">
        <div style="overflow-x:auto">
            <table class="sci-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Scholarship</th>
                        <th>Date & Time</th>
                        <th>Venue/Link</th>
                        <th>Panel</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="interviewsBody">
                    <tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div style="display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem" id="pagination"></div>
</div>

<!-- Calendar View -->
<div id="calendarView" style="display:none">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="sci-calendar">
                <div class="sci-cal-header">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <h6 class="fw-bold mb-0" id="calMonthYear"></h6>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="sci-cal-grid" id="calGrid"></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="sci-calendar">
                <h6 class="fw-bold mb-3"><i class="fas fa-list me-2 text-primary"></i>Upcoming</h6>
                <div id="upcomingList"></div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Outcome Modal -->
<div class="modal fade" id="outcomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-clipboard-check me-2"></i>Interview Outcome</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="outcomeInterviewId">
                <div class="row g-3">
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Outcome <span class="text-danger">*</span></label>
                        <select id="outcomeStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border)">
                            <option value="passed">Passed</option>
                            <option value="failed">Failed</option>
                            <option value="conditional">Conditional Pass</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Score (0-100)</label>
                        <input type="number" id="outcomeScore" class="form-control" min="0" max="100" style="border-radius:10px;border:1px solid var(--color-border)">
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Recommendation</label>
                        <textarea id="outcomeNotes" class="form-control" rows="3" placeholder="Interview feedback and recommendation..." style="border-radius:10px;border:1px solid var(--color-border)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#7c3aed,#a855f7);border:none" onclick="saveOutcome()"><i class="fas fa-save me-1"></i> Save Outcome</button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 20;
let currentView = 'list';
let calYear, calMonth;
let allInterviews = [];

const today = new Date();
calYear = today.getFullYear();
calMonth = today.getMonth();

const statusColors = {
    scheduled: {bg:'#fef3c7',color:'#92400e'},
    completed: {bg:'#d1fae5',color:'#065f46'},
    cancelled: {bg:'#fee2e2',color:'#991b1b'},
    no_show: {bg:'#f1f5f9',color:'#475569'}
};

function setView(v) {
    currentView = v;
    document.getElementById('listView').style.display = v === 'list' ? 'block' : 'none';
    document.getElementById('calendarView').style.display = v === 'calendar' ? 'block' : 'none';
    document.getElementById('viewListBtn').classList.toggle('btn-primary', v === 'list');
    document.getElementById('viewCalBtn').classList.toggle('btn-primary', v === 'calendar');
    if (v === 'calendar') renderCalendar();
}

async function loadInterviews(page = 0) {
    currentPage = page;
    const body = document.getElementById('interviewsBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_interviews', limit, offset: page * limit };
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('filterStatus').value;
        const filterDate = document.getElementById('filterDate').value;
        if (search) params.search = search;
        if (status) params.status = status;
        if (filterDate) params.interview_date = filterDate;

        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();

        if (!result.success) { body.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">${result.error || 'Error'}</td></tr>`; return; }

        const data = result.data || [];
        allInterviews = data;
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No interviews found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(iv => {
            const st = statusColors[iv.status] || {bg:'#f1f5f9',color:'#475569'};
            const dateStr = iv.interview_date ? new Date(iv.interview_date).toLocaleDateString('en-NG',{day:'2-digit',month:'short',year:'numeric'}) : '—';
            const timeStr = iv.interview_time || '—';
            const venue = iv.meeting_link ? `<a href="${escapeHtml(iv.meeting_link)}" target="_blank" style="color:#3b82f6;font-size:.78rem"><i class="fas fa-video me-1"></i>Join</a>` : escapeHtml(iv.venue || '—');

            return `<tr>
                <td>
                    <div style="min-width:0">
                        <div class="fw-semibold" style="font-size:.82rem">${escapeHtml(iv.full_name || '')}</div>
                        <div style="font-size:.68rem;color:#94a3b8">${escapeHtml(iv.application_code || '')}</div>
                    </div>
                </td>
                <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(iv.scholarship_title || '')}">${escapeHtml(iv.scholarship_title || '—')}</td>
                <td style="font-size:.78rem"><div>${dateStr}</div><div style="font-size:.7rem;color:#94a3b8">${timeStr}</div></td>
                <td style="font-size:.78rem">${venue}</td>
                <td style="font-size:.78rem;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(iv.panel_members || '')}">${escapeHtml(iv.panel_members || '—')}</td>
                <td><span class="sci-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(iv.status)}</span></td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick='viewInterview(${JSON.stringify(iv).replace(/'/g,"&#39;")})' title="View"><i class="fas fa-eye"></i></button>
                        ${iv.status === 'scheduled' ? `
                            <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#ede9fe;color:#7c3aed;border:none;border-radius:6px" onclick="openOutcome(${iv.id})" title="Record Outcome"><i class="fas fa-clipboard-check"></i></button>
                            <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px" onclick="updateStatus(${iv.id},'cancelled')" title="Cancel"><i class="fas fa-times"></i></button>
                        ` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(total, page);
        if (currentView === 'calendar') renderCalendar();
    } catch (err) {
        body.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">${err.message}</td></tr>`;
    }
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / limit);
    if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '';
    if (page > 0) html += `<button class="sci-page-btn" onclick="loadInterviews(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) html += `<button class="sci-page-btn active">${i+1}</button>`;
        else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) html += `<button class="sci-page-btn" onclick="loadInterviews(${i})">${i+1}</button>`;
        else if (Math.abs(i - page) === 3) html += `<span class="sci-page-btn" style="border:none;cursor:default">...</span>`;
    }
    if (page < pages - 1) html += `<button class="sci-page-btn" onclick="loadInterviews(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

function renderCalendar() {
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('calMonthYear').textContent = monthNames[calMonth] + ' ' + calYear;

    const firstDay = new Date(calYear, calMonth, 1).getDay();
    const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    const todayStr = today.toISOString().split('T')[0];

    const interviewDates = {};
    allInterviews.forEach(iv => {
        if (iv.interview_date) {
            const d = iv.interview_date.split('T')[0];
            interviewDates[d] = (interviewDates[d] || 0) + 1;
        }
    });

    let html = '';
    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    dayNames.forEach(d => html += `<div class="sci-cal-day">${d}</div>`);

    for (let i = 0; i < firstDay; i++) html += '<div class="sci-cal-date" style="opacity:.3"></div>';

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = dateStr === todayStr;
        const hasInterview = interviewDates[dateStr];
        const cls = isToday ? 'today' : (hasInterview ? 'has-interview' : '');
        html += `<div class="sci-cal-date ${cls}" title="${hasInterview ? hasInterview + ' interview(s)' : ''}">${d}</div>`;
    }

    document.getElementById('calGrid').innerHTML = html;

    const upcoming = allInterviews.filter(iv => iv.status === 'scheduled').slice(0, 5);
    const upcomingEl = document.getElementById('upcomingList');
    if (upcoming.length === 0) {
        upcomingEl.innerHTML = '<p class="text-muted text-center" style="font-size:.82rem">No upcoming interviews</p>';
    } else {
        upcomingEl.innerHTML = upcoming.map(iv => {
            const dateStr = iv.interview_date ? new Date(iv.interview_date).toLocaleDateString('en-NG',{day:'2-digit',month:'short'}) : '—';
            return `<div style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.82rem">
                <div class="fw-semibold">${escapeHtml(iv.full_name || '')}</div>
                <div style="font-size:.72rem;color:#94a3b8">${dateStr} • ${iv.interview_time || ''}</div>
            </div>`;
        }).join('');
    }
}

function changeMonth(delta) {
    calMonth += delta;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
}

function showScheduleForm(iv = null) {
    const form = document.getElementById('scheduleForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (iv) {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-pen-to-square me-2 text-primary"></i>Edit Interview';
        document.getElementById('editInterviewId').value = iv.id;
        document.getElementById('formAppId').value = iv.application_id || '';
        document.getElementById('formDate').value = iv.interview_date ? iv.interview_date.split('T')[0] : '';
        document.getElementById('formTime').value = iv.interview_time || '';
        document.getElementById('formVenue').value = iv.venue || '';
        document.getElementById('formLink').value = iv.meeting_link || '';
        document.getElementById('formPanel').value = iv.panel_members || '';
        document.getElementById('formDuration').value = iv.duration || 30;
        document.getElementById('formNotes').value = iv.notes || '';
    } else {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-calendar-plus me-2 text-primary"></i>Schedule New Interview';
        document.getElementById('editInterviewId').value = '';
        document.getElementById('formAppId').value = '';
        document.getElementById('formDate').value = '';
        document.getElementById('formTime').value = '';
        document.getElementById('formVenue').value = '';
        document.getElementById('formLink').value = '';
        document.getElementById('formPanel').value = '';
        document.getElementById('formDuration').value = '30';
        document.getElementById('formNotes').value = '';
    }
}

function hideScheduleForm() {
    document.getElementById('scheduleForm').style.display = 'none';
}

async function saveInterview() {
    const editId = document.getElementById('editInterviewId').value;
    const appId = document.getElementById('formAppId').value;
    const date = document.getElementById('formDate').value;
    const time = document.getElementById('formTime').value;
    if (!appId || !date || !time) { alert('Applicant, date, and time are required.'); return; }

    const params = {
        action: editId ? 'update_interview' : 'schedule_interview',
        application_id: parseInt(appId),
        interview_date: date,
        interview_time: time,
        venue: document.getElementById('formVenue').value.trim(),
        meeting_link: document.getElementById('formLink').value.trim(),
        panel_members: document.getElementById('formPanel').value.trim(),
        duration: parseInt(document.getElementById('formDuration').value) || 30,
        notes: document.getElementById('formNotes').value.trim()
    };
    if (editId) params.id = parseInt(editId);

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            hideScheduleForm();
            loadInterviews(currentPage);
        } else alert(result.error || 'Failed to save interview');
    } catch (err) { alert('Error: ' + err.message); }
}

function viewInterview(iv) {
    const st = statusColors[iv.status] || {bg:'#f1f5f9',color:'#475569'};
    const dateStr = iv.interview_date ? new Date(iv.interview_date).toLocaleDateString('en-NG',{day:'2-digit',month:'long',year:'numeric'}) : '—';
    const rows = [
        ['Applicant', iv.full_name],
        ['Application Code', iv.application_code],
        ['Scholarship', iv.scholarship_title],
        ['Date', dateStr],
        ['Time', iv.interview_time || '—'],
        ['Duration', (iv.duration || 30) + ' minutes'],
        ['Venue', iv.venue || '—'],
        ['Meeting Link', iv.meeting_link ? `<a href="${escapeHtml(iv.meeting_link)}" target="_blank" style="color:#3b82f6">${escapeHtml(iv.meeting_link)}</a>` : '—'],
        ['Panel Members', iv.panel_members || '—'],
        ['Status', `<span class="sci-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(iv.status)}</span>`],
        ['Notes', iv.notes || '—'],
        ['Created', iv.created_at ? new Date(iv.created_at).toLocaleString() : '—'],
    ];

    document.getElementById('detailBody').innerHTML = rows.map(([label, value]) =>
        `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value || '—'}</div></div>`
    ).join('');

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function openOutcome(ivId) {
    document.getElementById('outcomeInterviewId').value = ivId;
    document.getElementById('outcomeStatus').value = 'passed';
    document.getElementById('outcomeScore').value = '';
    document.getElementById('outcomeNotes').value = '';
    new bootstrap.Modal(document.getElementById('outcomeModal')).show();
}

async function saveOutcome() {
    const ivId = document.getElementById('outcomeInterviewId').value;
    const params = {
        action: 'update_interview_outcome',
        id: parseInt(ivId),
        outcome: document.getElementById('outcomeStatus').value,
        score: parseInt(document.getElementById('outcomeScore').value) || null,
        notes: document.getElementById('outcomeNotes').value.trim()
    };

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('outcomeModal')).hide();
            loadInterviews(currentPage);
        } else alert(result.error || 'Failed to save outcome');
    } catch (err) { alert('Error: ' + err.message); }
}

async function updateStatus(id, status) {
    if (!confirm(`Are you sure you want to cancel this interview?`)) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'update_interview', id, status }) });
        const result = await resp.json();
        if (result.success) loadInterviews(currentPage);
        else alert(result.error || 'Failed');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function capitalizeFirst(s) { if (!s) return ''; return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' '); }

document.addEventListener('DOMContentLoaded', () => loadInterviews());
</script>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-video me-2"></i>Interview Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailBody" style="max-height:70vh;overflow-y:auto"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
