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

$page_title = "Approved Applicants";
$current_page = "scholarship_approved.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$scholarships = [];
try {
    $scholarships = $pdo->query("SELECT id, title, code FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$stats = ['total'=>0,'awarded'=>0,'pending_award'=>0,'certified'=>0];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='approved' OR status='awarded'")->fetchColumn();
    $stats['awarded'] = $pdo->query("SELECT COUNT(*) FROM scholarship_awards WHERE payment_status='disbursed'")->fetchColumn();
    $stats['pending_award'] = $pdo->query("SELECT COUNT(*) FROM scholarship_awards WHERE payment_status='pending' OR payment_status='processing'")->fetchColumn();
    $stats['certified'] = $pdo->query("SELECT COUNT(*) FROM scholarship_certificates WHERE status='issued'")->fetchColumn();
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
.detail-row{display:flex;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.78rem;font-weight:600;color:#64748b;min-width:130px}
.detail-value{font-size:.82rem;color:var(--color-text)}
@media(max-width:991.98px){.sca-table-wrap{overflow-x:auto}}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-user-check me-2"></i>Approved Applicants</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Manage approved scholarship recipients and awards</p>
        </div>
        <div class="mt-2 mt-md-0">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadApproved()"><i class="fas fa-sync me-1"></i> Refresh</button>
        </div>
    </div>
    <div class="row g-2 mt-3">
        <?php
        $statCards = [
            ['Total Approved','fas fa-user-check',$stats['total'],'#10b981','rgba(16,185,129,.12)'],
            ['Awarded','fas fa-award',$stats['awarded'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Pending Award','fas fa-clock',$stats['pending_award'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Certified','fas fa-certificate',$stats['certified'],'#8b5cf6','rgba(139,92,246,.12)'],
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

<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
            <div class="position-relative">
                <i class="fas fa-search position-absolute text-muted" style="left:14px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search name, email..." style="padding-left:40px;border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadApproved()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterScholarship" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadApproved()">
                <option value="">All Scholarships</option>
                <?php foreach ($scholarships as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select id="filterAward" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadApproved()">
                <option value="">All</option>
                <option value="awarded">Awarded</option>
                <option value="not_awarded">Not Awarded</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary rounded-pill flex-grow-1" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.85rem" onclick="loadApproved()"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </div>
</div>

<div class="sca-table-wrap">
    <div style="overflow-x:auto">
        <table class="sca-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Email</th>
                    <th>Scholarship</th>
                    <th>Score</th>
                    <th>Award Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="approvedBody">
                <tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>
<div style="display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem" id="pagination"></div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-graduate me-2"></i>Applicant Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailBody" style="max-height:70vh;overflow-y:auto"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Award Modal -->
<div class="modal fade" id="awardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#10b981,#059669);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-award me-2"></i>Award Scholarship</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="awardAppId">
                <div class="row g-3">
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Award Amount <span class="text-danger">*</span></label>
                        <input type="number" id="awardAmount" class="form-control" min="0" step="0.01" placeholder="0.00" style="border-radius:10px;border:1px solid var(--color-border)">
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Currency</label>
                        <select id="awardCurrency" class="form-select" style="border-radius:10px;border:1px solid var(--color-border)">
                            <option value="NGN">NGN (₦)</option>
                            <option value="USD">USD ($)</option>
                            <option value="GBP">GBP (£)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Notes</label>
                        <textarea id="awardNotes" class="form-control" rows="2" placeholder="Award notes..." style="border-radius:10px;border:1px solid var(--color-border)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#10b981,#059669);border:none" onclick="saveAward()"><i class="fas fa-save me-1"></i> Award</button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 20;

async function loadApproved(page = 0) {
    currentPage = page;
    const body = document.getElementById('approvedBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_applications', status: 'approved', limit, offset: page * limit };
        const search = document.getElementById('searchInput').value.trim();
        const scholarship = document.getElementById('filterScholarship').value;
        if (search) params.search = search;
        if (scholarship) params.scholarship_id = scholarship;

        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();

        if (!result.success) { body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">${result.error || 'Error'}</td></tr>`; return; }

        const data = result.data || [];
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No approved applicants found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(a => {
            const genderColor = a.gender === 'female' ? '#ec4899' : '#3b82f6';
            const initials = (a.full_name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
            const isAwarded = a.award_status === 'awarded' || a.status === 'awarded';
            const awardBadge = isAwarded
                ? '<span class="sca-badge" style="background:#d1fae5;color:#065f46">Awarded</span>'
                : '<span class="sca-badge" style="background:#fef3c7;color:#d97706">Pending Award</span>';

            return `<tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="sca-app-avatar" style="background:${genderColor}">${initials}</div>
                        <div style="min-width:0">
                            <div class="fw-semibold" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">${escapeHtml(a.full_name)}</div>
                            <div style="font-size:.68rem;color:#94a3b8">${escapeHtml(a.application_code || '')}</div>
                        </div>
                    </div>
                </td>
                <td style="font-size:.78rem">${escapeHtml(a.email)}</td>
                <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(a.scholarship_title)}">${escapeHtml(a.scholarship_title)}</td>
                <td style="font-size:.78rem;font-weight:600">${a.total_score || '—'}</td>
                <td>${awardBadge}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick='viewApp(${JSON.stringify(a).replace(/'/g,"&#39;")})' title="View"><i class="fas fa-eye"></i></button>
                        ${!isAwarded ? `<button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#d1fae5;color:#065f46;border:none;border-radius:6px" onclick="openAward(${a.id})" title="Award"><i class="fas fa-award"></i></button>` : ''}
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#ede9fe;color:#7c3aed;border:none;border-radius:6px" onclick="generateCertificate(${a.id})" title="Generate Certificate"><i class="fas fa-certificate"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(total, page);
    } catch (err) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">${err.message}</td></tr>`;
    }
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / limit);
    if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '';
    if (page > 0) html += `<button class="sca-page-btn" onclick="loadApproved(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) html += `<button class="sca-page-btn active">${i+1}</button>`;
        else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) html += `<button class="sca-page-btn" onclick="loadApproved(${i})">${i+1}</button>`;
        else if (Math.abs(i - page) === 3) html += `<span class="sca-page-btn" style="border:none;cursor:default">...</span>`;
    }
    if (page < pages - 1) html += `<button class="sca-page-btn" onclick="loadApproved(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

function viewApp(app) {
    const rows = [
        ['Application Code', app.application_code],
        ['Full Name', app.full_name],
        ['Email', app.email],
        ['Phone', app.phone || '—'],
        ['Gender', app.gender ? capitalizeFirst(app.gender) : '—'],
        ['State', app.state || '—'],
        ['Institution', app.institution || '—'],
        ['Department', app.department || '—'],
        ['CGPA', app.cgpa || '—'],
        ['Scholarship', app.scholarship_title],
        ['Score', app.total_score || '—'],
        ['Status', capitalizeFirst(app.status)],
        ['Approved', app.updated_at ? new Date(app.updated_at).toLocaleString() : '—'],
    ];

    document.getElementById('detailBody').innerHTML = rows.map(([label, value]) =>
        `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value || '—'}</div></div>`
    ).join('');

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function openAward(appId) {
    document.getElementById('awardAppId').value = appId;
    document.getElementById('awardAmount').value = '';
    document.getElementById('awardCurrency').value = 'NGN';
    document.getElementById('awardNotes').value = '';
    new bootstrap.Modal(document.getElementById('awardModal')).show();
}

async function saveAward() {
    const appId = document.getElementById('awardAppId').value;
    const amount = document.getElementById('awardAmount').value;
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid award amount.'); return; }

    const params = {
        action: 'create_award',
        application_id: parseInt(appId),
        award_amount: parseFloat(amount),
        currency: document.getElementById('awardCurrency').value,
        notes: document.getElementById('awardNotes').value.trim()
    };

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('awardModal')).hide();
            loadApproved(currentPage);
        } else alert(result.error || 'Failed to create award');
    } catch (err) { alert('Error: ' + err.message); }
}

async function generateCertificate(appId) {
    if (!confirm('Generate certificate for this applicant?')) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'generate_certificate', application_id: parseInt(appId) }) });
        const result = await resp.json();
        if (result.success) alert('Certificate generated successfully!');
        else alert(result.error || 'Failed to generate certificate');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function capitalizeFirst(s) { if (!s) return ''; return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' '); }

document.addEventListener('DOMContentLoaded', () => loadApproved());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
