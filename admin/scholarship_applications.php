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

$page_title = "Scholarship Applications";
$current_page = "scholarship_applications.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$scholarships = [];
try {
    $scholarships = $pdo->query("SELECT id, title, code FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$stats = ['total'=>0,'submitted'=>0,'under_review'=>0,'shortlisted'=>0,'approved'=>0,'rejected'=>0];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
    $stats['submitted'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='submitted'")->fetchColumn();
    $stats['under_review'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='under_review'")->fetchColumn();
    $stats['shortlisted'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='shortlisted'")->fetchColumn();
    $stats['approved'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='approved' OR status='awarded'")->fetchColumn();
    $stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='rejected'")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.sca-stat{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:12px;padding:.6rem .75rem;display:flex;align-items:center;gap:.6rem;transition:all .3s}
.sca-stat:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.sca-stat .si{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.sca-stat .sv{font-size:1.1rem;font-weight:800;line-height:1}
.sca-stat .sl{font-size:.62rem;color:#64748b}
.sca-table-wrap{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden}
.sca-table{width:100%;border-collapse:collapse}
.sca-table th{padding:.6rem .7rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border);background:var(--color-bg);text-align:left;white-space:nowrap}
.sca-table td{padding:.6rem .7rem;font-size:.8rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.sca-table tr:last-child td{border-bottom:none}
.sca-table tr:hover td{background:var(--color-bg)}
.sca-app-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#fff;flex-shrink:0}
.sca-badge{font-size:.63rem;padding:2px 8px;border-radius:50px;font-weight:600;display:inline-block;white-space:nowrap}
.sca-page-btn{padding:.35rem .8rem;border:1px solid var(--color-border);border-radius:8px;font-size:.78rem;font-weight:600;color:var(--color-text);text-decoration:none;transition:all .2s;cursor:pointer;background:var(--color-card-bg)}
.sca-page-btn:hover,.sca-page-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
.sca-bulk{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:1000;background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;padding:.7rem 1.5rem;border-radius:14px;display:none;align-items:center;gap:1rem;box-shadow:0 8px 32px rgba(30,64,175,.4);font-size:.85rem}
.sca-bulk.show{display:flex}
@media(max-width:767.98px){.sca-bulk{flex-wrap:wrap;bottom:80px;font-size:.78rem;padding:.6rem 1rem;width:calc(100% - 2rem);justify-content:center}}
.modal-lg-custom{max-width:800px}
.detail-row{display:flex;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.78rem;font-weight:600;color:#64748b;min-width:130px}
.detail-value{font-size:.82rem;color:var(--color-text)}
@media(max-width:991.98px){.sca-table-wrap{overflow-x:auto}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-file-alt me-2"></i>Scholarship Applications</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Review, score, and manage all scholarship applications</p>
        </div>
        <div class="mt-2 mt-md-0">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadApplications()"><i class="fas fa-sync me-1"></i> Refresh</button>
        </div>
    </div>
    <div class="row g-2 mt-3">
        <?php
        $statCards = [
            ['Total','fas fa-layer-group',$stats['total'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Submitted','fas fa-inbox',$stats['submitted'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Under Review','fas fa-eye',$stats['under_review'],'#8b5cf6','rgba(139,92,246,.12)'],
            ['Shortlisted','fas fa-star',$stats['shortlisted'],'#06b6d4','rgba(6,182,212,.12)'],
            ['Approved','fas fa-user-check',$stats['approved'],'#10b981','rgba(16,185,129,.12)'],
            ['Rejected','fas fa-user-times',$stats['rejected'],'#ef4444','rgba(239,68,68,.12)'],
        ];
        foreach ($statCards as $s): ?>
        <div class="col-4 col-md-2">
            <div style="background:rgba(255,255,255,.07);border-radius:10px;padding:.5rem .6rem;display:flex;align-items:center;gap:.5rem">
                <div style="width:28px;height:28px;border-radius:7px;background:<?= $s[4] ?>;display:flex;align-items:center;justify-content:center;color:<?= $s[3] ?>;font-size:.7rem"><i class="<?= $s[2] ?>"></i></div>
                <div><div style="font-size:.95rem;font-weight:800;line-height:1"><?= $s[1] ?></div><div style="font-size:.55rem;opacity:.7"><?= $s[0] ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- Filters -->
<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-3">
            <div class="position-relative">
                <i class="fas fa-search position-absolute text-muted" style="left:14px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search name, email, code..." style="padding-left:40px;border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadApplications()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterScholarship" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadApplications()">
                <option value="">All Scholarships</option>
                <?php foreach ($scholarships as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadApplications()">
                <option value="">All Status</option>
                <option value="submitted">Submitted</option>
                <option value="under_review">Under Review</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="approved">Approved</option>
                <option value="awarded">Awarded</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary rounded-pill flex-grow-1" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.85rem" onclick="loadApplications()"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </div>
</div>

<!-- Table -->
<div class="sca-table-wrap" id="tableWrap">
    <div style="overflow-x:auto">
        <table class="sca-table" id="applicationsTable">
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" class="form-check-input" onchange="toggleAll(this)"></th>
                    <th>Applicant</th>
                    <th>Code</th>
                    <th>Scholarship</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="appsBody">
                <tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>
<div style="display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem" id="pagination"></div>

<!-- Bulk Bar -->
<div class="sca-bulk" id="bulkBar">
    <span id="bulkCount">0 selected</span>
    <select id="bulkStatus" style="border-radius:8px;border:none;padding:4px 8px;font-size:.82rem">
        <option value="">Change Status To...</option>
        <option value="under_review">Under Review</option>
        <option value="shortlisted">Shortlisted</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
    <button class="btn btn-sm btn-light rounded-pill" onclick="doBulkStatus()"><i class="fas fa-check me-1"></i> Apply</button>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg-custom modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-graduate me-2"></i>Application Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailBody" style="max-height:70vh;overflow-y:auto"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Score Modal -->
<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-star me-2"></i>Score Application</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="scoreAppId">
                <div class="row g-3">
                    <?php
                    $scoreFields = [
                        ['academic_score','Academic Score',100],
                        ['financial_score','Financial Need Score',100],
                        ['leadership_score','Leadership Score',100],
                        ['community_score','Community Service Score',100],
                        ['statement_score','Personal Statement Score',100],
                    ];
                    foreach ($scoreFields as [$id,$lbl,$max]):
                    ?>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569"><?= $lbl ?> <small style="color:#94a3b8">(0-<?= $max ?>)</small></label>
                        <input type="number" id="<?= $id ?>" class="form-control" min="0" max="<?= $max ?>" value="0" style="border-radius:10px;border:1px solid var(--color-border)">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569">Notes</label>
                        <textarea id="scoreNotes" class="form-control" rows="2" style="border-radius:10px;border:1px solid var(--color-border)" placeholder="Optional scoring notes..."></textarea>
                    </div>
                    <div class="col-12">
                        <div style="font-size:.85rem;font-weight:700;color:var(--color-text)">Total: <span id="scoreTotal">0</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#7c3aed,#a855f7);border:none" onclick="saveScore()"><i class="fas fa-save me-1"></i> Save Score</button>
            </div>
        </div>
    </div>
</div>

</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 20;
let selectedIds = new Set();

const statusColors = {
    submitted: {bg:'#fef3c7',color:'#92400e'},
    under_review: {bg:'#dbeafe',color:'#1e40af'},
    shortlisted: {bg:'#fef3c7',color:'#d97706'},
    approved: {bg:'#d1fae5',color:'#065f46'},
    awarded: {bg:'#d1fae5',color:'#065f46'},
    rejected: {bg:'#fee2e2',color:'#991b1b'}
};

async function loadApplications(page = 0) {
    currentPage = page;
    selectedIds.clear();
    updateBulkBar();
    const body = document.getElementById('appsBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_applications', limit, offset: page * limit };
        const search = document.getElementById('searchInput').value.trim();
        const scholarship = document.getElementById('filterScholarship').value;
        const status = document.getElementById('filterStatus').value;
        if (search) params.search = search;
        if (scholarship) params.scholarship_id = scholarship;
        if (status) params.status = status;

        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();

        if (!result.success) { body.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">${result.error || 'Error'}</td></tr>`; return; }

        const data = result.data || [];
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No applications found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(a => {
            const st = statusColors[a.status] || {bg:'#f1f5f9',color:'#475569'};
            const genderColor = a.gender === 'female' ? '#ec4899' : '#3b82f6';
            const initials = (a.full_name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
            const appDate = a.submitted_at ? new Date(a.submitted_at).toLocaleDateString('en-NG',{day:'2-digit',month:'short',year:'numeric'}) : '—';

            return `<tr>
                <td><input type="checkbox" class="form-check-input app-cb" value="${a.id}" onchange="updateBulkBar()"></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="sca-app-avatar" style="background:${genderColor}">${initials}</div>
                        <div style="min-width:0">
                            <div class="fw-semibold" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">${escapeHtml(a.full_name)}</div>
                            <div style="font-size:.68rem;color:#94a3b8">${escapeHtml(a.email)}</div>
                        </div>
                    </div>
                </td>
                <td><code style="font-size:.72rem;background:#f1f5f9;padding:2px 6px;border-radius:4px">${escapeHtml(a.application_code)}</code></td>
                <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(a.scholarship_title)}">${escapeHtml(a.scholarship_title)}</td>
                <td><span class="sca-badge" style="background:${st.bg};color:${st.color}">${formatStatus(a.status)}</span></td>
                <td style="font-size:.78rem;font-weight:600">${a.total_score || '—'}</td>
                <td style="font-size:.75rem;color:#94a3b8">${appDate}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick='viewApplication(${JSON.stringify(a).replace(/'/g,"&#39;")})' title="View Details"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#ede9fe;color:#7c3aed;border:none;border-radius:6px" onclick="openScore(${a.id})" title="Score"><i class="fas fa-star"></i></button>
                        <select class="form-select form-select-sm" style="width:auto;font-size:.6rem;padding:2px 6px;border-radius:6px;border:1px solid var(--color-border)" onchange="changeStatus(${a.id},this.value);this.value=''">
                            <option value="">Status</option>
                            <option value="under_review">Under Review</option>
                            <option value="shortlisted">Shortlisted</option>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(total, page);
    } catch (err) {
        body.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">${err.message}</td></tr>`;
    }
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / limit);
    if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '';
    if (page > 0) html += `<button class="sca-page-btn" onclick="loadApplications(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) html += `<button class="sca-page-btn active">${i+1}</button>`;
        else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) html += `<button class="sca-page-btn" onclick="loadApplications(${i})">${i+1}</button>`;
        else if (Math.abs(i - page) === 3) html += `<span class="sca-page-btn" style="border:none;cursor:default">...</span>`;
    }
    if (page < pages - 1) html += `<button class="sca-page-btn" onclick="loadApplications(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

async function changeStatus(id, status) {
    if (!status) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'update_application_status', id, status }) });
        const result = await resp.json();
        if (result.success) loadApplications(currentPage);
        else alert(result.error || 'Failed');
    } catch (err) { alert('Error: ' + err.message); }
}

function viewApplication(app) {
    const st = statusColors[app.status] || {bg:'#f1f5f9',color:'#475569'};
    const rows = [
        ['Application Code', app.application_code],
        ['Full Name', app.full_name],
        ['Gender', app.gender ? capitalizeFirst(app.gender) : '—'],
        ['Date of Birth', app.date_of_birth || '—'],
        ['Email', app.email],
        ['Phone', app.phone || '—'],
        ['Address', app.address || '—'],
        ['State', app.state || '—'],
        ['Country', app.country || '—'],
        ['Institution', app.institution || '—'],
        ['Faculty', app.faculty || '—'],
        ['Department', app.department || '—'],
        ['Course', app.course || '—'],
        ['Level', app.level || '—'],
        ['CGPA', app.cgpa || '—'],
        ['Scholarship', app.scholarship_title],
        ['Status', `<span class="sca-badge" style="background:${st.bg};color:${st.color}">${formatStatus(app.status)}</span>`],
        ['Score', app.total_score || '—'],
        ['Submitted', app.submitted_at ? new Date(app.submitted_at).toLocaleString() : '—'],
        ['Personal Statement', app.personal_statement ? `<div style="font-size:.82rem;max-height:100px;overflow-y:auto;padding:8px;background:var(--color-bg);border-radius:8px;margin-top:4px">${escapeHtml(app.personal_statement)}</div>` : '—'],
    ];

    if (app.passport_photo) rows.push(['Passport', `<a href="../${app.passport_photo}" target="_blank" style="color:#3b82f6">View File</a>`]);
    if (app.admission_letter) rows.push(['Admission Letter', `<a href="../${app.admission_letter}" target="_blank" style="color:#3b82f6">View File</a>`]);
    if (app.academic_transcript) rows.push(['Transcript', `<a href="../${app.academic_transcript}" target="_blank" style="color:#3b82f6">View File</a>`]);

    document.getElementById('detailBody').innerHTML = rows.map(([label, value]) =>
        `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value || '—'}</div></div>`
    ).join('');

    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
}

function openScore(appId) {
    document.getElementById('scoreAppId').value = appId;
    ['academic_score','financial_score','leadership_score','community_score','statement_score'].forEach(id => {
        document.getElementById(id).value = 0;
    });
    document.getElementById('scoreNotes').value = '';
    document.getElementById('scoreTotal').textContent = '0';
    const modal = new bootstrap.Modal(document.getElementById('scoreModal'));
    modal.show();
}

document.querySelectorAll('#scoreModal input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        let total = 0;
        ['academic_score','financial_score','leadership_score','community_score','statement_score'].forEach(id => {
            total += parseInt(document.getElementById(id).value) || 0;
        });
        document.getElementById('scoreTotal').textContent = total;
    });
});

async function saveScore() {
    const appId = document.getElementById('scoreAppId').value;
    const params = {
        action: 'save_score',
        application_id: parseInt(appId),
        academic_score: parseInt(document.getElementById('academic_score').value) || 0,
        financial_score: parseInt(document.getElementById('financial_score').value) || 0,
        leadership_score: parseInt(document.getElementById('leadership_score').value) || 0,
        community_score: parseInt(document.getElementById('community_score').value) || 0,
        statement_score: parseInt(document.getElementById('statement_score').value) || 0,
        notes: document.getElementById('scoreNotes').value
    };

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('scoreModal')).hide();
            loadApplications(currentPage);
        } else alert(result.error || 'Failed to save score');
    } catch (err) { alert('Error: ' + err.message); }
}

function toggleAll(el) {
    document.querySelectorAll('.app-cb').forEach(cb => cb.checked = el.checked);
    updateBulkBar();
}

function updateBulkBar() {
    selectedIds.clear();
    document.querySelectorAll('.app-cb:checked').forEach(cb => selectedIds.add(cb.value));
    const bar = document.getElementById('bulkBar');
    const count = selectedIds.size;
    bar.classList.toggle('show', count > 0);
    document.getElementById('bulkCount').textContent = count + ' selected';
}

async function doBulkStatus() {
    const status = document.getElementById('bulkStatus').value;
    if (!status || selectedIds.size === 0) { alert('Select applications and a status first.'); return; }
    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'bulk_action', ids: Array.from(selectedIds), status })
        });
        const result = await resp.json();
        if (result.success) loadApplications(currentPage);
        else alert(result.error || 'Failed');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function capitalizeFirst(s) { if (!s) return ''; return s.charAt(0).toUpperCase() + s.slice(1); }
function formatStatus(s) { return (s || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()); }

document.addEventListener('DOMContentLoaded', () => loadApplications());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>