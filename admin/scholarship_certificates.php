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

$page_title = "Certificate Management";
$current_page = "scholarship_certificates.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$stats = ['total'=>0,'issued'=>0,'pending'=>0,'revoked'=>0];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM scholarship_certificates")->fetchColumn();
    $stats['issued'] = $pdo->query("SELECT COUNT(*) FROM scholarship_certificates WHERE status='issued'")->fetchColumn();
    $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM scholarship_certificates WHERE status='pending'")->fetchColumn();
    $stats['revoked'] = $pdo->query("SELECT COUNT(*) FROM scholarship_certificates WHERE status='revoked'")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.sccf-stat{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:12px;padding:.6rem .75rem;display:flex;align-items:center;gap:.6rem;transition:all .3s}
.sccf-stat:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.sccf-stat .si{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.sccf-stat .sv{font-size:1.1rem;font-weight:800;line-height:1}
.sccf-stat .sl{font-size:.62rem;color:#64748b}
.sccf-table-wrap{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden}
.sccf-table{width:100%;border-collapse:collapse}
.sccf-table th{padding:.6rem .7rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border);background:var(--color-bg);text-align:left;white-space:nowrap}
.sccf-table td{padding:.6rem .7rem;font-size:.8rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.sccf-table tr:last-child td{border-bottom:none}
.sccf-table tr:hover td{background:var(--color-bg)}
.sccf-badge{font-size:.63rem;padding:2px 8px;border-radius:50px;font-weight:600;display:inline-block;white-space:nowrap}
.sccf-page-btn{padding:.35rem .8rem;border:1px solid var(--color-border);border-radius:8px;font-size:.78rem;font-weight:600;color:var(--color-text);text-decoration:none;transition:all .2s;cursor:pointer;background:var(--color-card-bg)}
.sccf-page-btn:hover,.sccf-page-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
.detail-row{display:flex;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.78rem;font-weight:600;color:#64748b;min-width:130px}
.detail-value{font-size:.82rem;color:var(--color-text)}
@media(max-width:991.98px){.sccf-table-wrap{overflow-x:auto}}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-certificate me-2"></i>Certificate Management</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Generate, manage, and download scholarship certificates</p>
        </div>
        <div class="mt-2 mt-md-0">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadCertificates()"><i class="fas fa-sync me-1"></i> Refresh</button>
        </div>
    </div>
    <div class="row g-2 mt-3">
        <?php
        $statCards = [
            ['Total Certificates','fas fa-certificate',$stats['total'],'#8b5cf6','rgba(139,92,246,.12)'],
            ['Issued','fas fa-check-circle',$stats['issued'],'#10b981','rgba(16,185,129,.12)'],
            ['Pending','fas fa-clock',$stats['pending'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Revoked','fas fa-ban',$stats['revoked'],'#ef4444','rgba(239,68,68,.12)'],
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
                <input type="text" id="searchInput" class="form-control" placeholder="Search student, certificate number..." style="padding-left:40px;border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadCertificates()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadCertificates()">
                <option value="">All Status</option>
                <option value="issued">Issued</option>
                <option value="pending">Pending</option>
                <option value="revoked">Revoked</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <input type="text" id="filterCertNo" class="form-control" placeholder="Certificate number..." style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadCertificates()">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary rounded-pill flex-grow-1" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.85rem" onclick="loadCertificates()"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </div>
</div>

<div class="sccf-table-wrap">
    <div style="overflow-x:auto">
        <table class="sccf-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Scholarship</th>
                    <th>Certificate No.</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="certsBody">
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
                <h6 class="modal-title fw-bold"><i class="fas fa-certificate me-2"></i>Certificate Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailBody" style="max-height:70vh;overflow-y:auto"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 20;

const statusColors = {
    issued: {bg:'#d1fae5',color:'#065f46'},
    pending: {bg:'#fef3c7',color:'#92400e'},
    revoked: {bg:'#fee2e2',color:'#991b1b'}
};

async function loadCertificates(page = 0) {
    currentPage = page;
    const body = document.getElementById('certsBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_certificates', limit, offset: page * limit };
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('filterStatus').value;
        const certNo = document.getElementById('filterCertNo').value.trim();
        if (search) params.search = search;
        if (status) params.status = status;
        if (certNo) params.certificate_number = certNo;

        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();

        if (!result.success) { body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">${result.error || 'Error'}</td></tr>`; return; }

        const data = result.data || [];
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No certificates found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(c => {
            const st = statusColors[c.status] || {bg:'#f1f5f9',color:'#475569'};
            const issueDate = c.issued_at ? new Date(c.issued_at).toLocaleDateString('en-NG',{day:'2-digit',month:'short',year:'numeric'}) : '—';

            return `<tr>
                <td>
                    <div style="min-width:0">
                        <div class="fw-semibold" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">${escapeHtml(c.full_name || '')}</div>
                        <div style="font-size:.68rem;color:#94a3b8">${escapeHtml(c.email || '')}</div>
                    </div>
                </td>
                <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(c.scholarship_title || '')}">${escapeHtml(c.scholarship_title || '—')}</td>
                <td><code style="font-size:.72rem;background:#f1f5f9;padding:2px 8px;border-radius:4px">${escapeHtml(c.certificate_number || '—')}</code></td>
                <td style="font-size:.75rem;color:#94a3b8">${issueDate}</td>
                <td><span class="sccf-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(c.status)}</span></td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick='viewCert(${JSON.stringify(c).replace(/'/g,"&#39;")})' title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#d1fae5;color:#065f46;border:none;border-radius:6px" onclick="downloadCert(${c.id})" title="Download PDF"><i class="fas fa-download"></i></button>
                        ${c.status === 'issued' ? `<button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px" onclick="revokeCert(${c.id})" title="Revoke"><i class="fas fa-ban"></i></button>` : ''}
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
    if (page > 0) html += `<button class="sccf-page-btn" onclick="loadCertificates(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) html += `<button class="sccf-page-btn active">${i+1}</button>`;
        else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) html += `<button class="sccf-page-btn" onclick="loadCertificates(${i})">${i+1}</button>`;
        else if (Math.abs(i - page) === 3) html += `<span class="sccf-page-btn" style="border:none;cursor:default">...</span>`;
    }
    if (page < pages - 1) html += `<button class="sccf-page-btn" onclick="loadCertificates(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

function viewCert(c) {
    const issueDate = c.issued_at ? new Date(c.issued_at).toLocaleString() : '—';
    const st = statusColors[c.status] || {bg:'#f1f5f9',color:'#475569'};
    const rows = [
        ['Student', c.full_name],
        ['Email', c.email],
        ['Scholarship', c.scholarship_title],
        ['Certificate Number', `<code style="font-size:.85rem;background:#f1f5f9;padding:4px 10px;border-radius:6px">${escapeHtml(c.certificate_number || '—')}</code>`],
        ['Issue Date', issueDate],
        ['Status', `<span class="sccf-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(c.status)}</span>`],
        ['Notes', c.notes || '—'],
    ];

    document.getElementById('detailBody').innerHTML = rows.map(([label, value]) =>
        `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value || '—'}</div></div>`
    ).join('');

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

async function downloadCert(certId) {
    alert('PDF download will be available once the certificate template is configured.');
}

async function revokeCert(certId) {
    if (!confirm('Are you sure you want to revoke this certificate? This action cannot be undone.')) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'revoke_certificate', id: certId }) });
        const result = await resp.json();
        if (result.success) loadCertificates(currentPage);
        else alert(result.error || 'Failed to revoke certificate');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function capitalizeFirst(s) { if (!s) return ''; return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' '); }

document.addEventListener('DOMContentLoaded', () => loadCertificates());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
