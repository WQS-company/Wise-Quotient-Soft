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

$page_title = "Manage Scholarships";
$current_page = "scholarships.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$stats = ['total'=>0,'active'=>0,'draft'=>0,'closed'=>0];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
    $stats['active'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE is_active=1 AND status='published'")->fetchColumn();
    $stats['draft'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status='draft'")->fetchColumn();
    $stats['closed'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status='closed'")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.sch-stat-mini{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:12px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem;transition:all .3s}
.sch-stat-mini:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.sch-stat-mini .sm-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.sch-stat-mini .sm-val{font-size:1.2rem;font-weight:800;line-height:1}
.sch-stat-mini .sm-lbl{font-size:.68rem;color:#64748b}
.sch-table-wrap{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden}
.sch-table{width:100%;border-collapse:collapse}
.sch-table th{padding:.65rem .75rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border);background:var(--color-bg);text-align:left;white-space:nowrap}
.sch-table td{padding:.65rem .75rem;font-size:.82rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.sch-table tr:last-child td{border-bottom:none}
.sch-table tr:hover td{background:var(--color-bg)}
.sch-type-badge{font-size:.65rem;padding:2px 8px;border-radius:50px;font-weight:600;display:inline-block}
.sch-status{font-size:.65rem;padding:3px 10px;border-radius:50px;font-weight:700;display:inline-block}
.sch-active{background:#dcfce7;color:#15803d}
.sch-draft{background:#dbeafe;color:#1e40af}
.sch-closed{background:#f1f5f9;color:#64748b}
.sch-suspended{background:#fee2e2;color:#dc2626}
.sch-pagination{display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem}
.sch-page-btn{padding:.4rem .9rem;border:1px solid var(--color-border);border-radius:8px;font-size:.82rem;font-weight:600;color:var(--color-text);text-decoration:none;transition:all .2s;cursor:pointer;background:var(--color-card-bg)}
.sch-page-btn:hover,.sch-page-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
@media(max-width:991.98px){.sch-table-wrap{overflow-x:auto}}
@media(max-width:767.98px){.sch-stat-mini{padding:.6rem .75rem}.sch-stat-mini .sm-val{font-size:1rem}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-graduation-cap me-2"></i>Scholarships Management</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">View, manage, and control all scholarship listings</p>
        </div>
        <a href="scholarship_create.php" class="btn btn-warning fw-bold mt-2 mt-md-0 rounded-pill"><i class="fas fa-plus me-1"></i> New Scholarship</a>
    </div>
    <div class="row g-2 mt-3">
        <?php
        $statCards = [
            ['Total','fas fa-layer-group',$stats['total'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Active','fas fa-check-circle',$stats['active'],'#10b981','rgba(16,185,129,.12)'],
            ['Draft','fas fa-edit',$stats['draft'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Closed','fas fa-lock',$stats['closed'],'#64748b','rgba(100,116,139,.12)'],
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

<!-- Flash Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Filter Bar -->
<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <div class="position-relative">
                <i class="fas fa-search position-absolute text-muted" style="left:16px;top:50%;transform:translateY(-50%)"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search by title, code..." style="padding-left:44px;border-radius:10px;border:1px solid var(--color-border);font-size:.88rem" oninput="loadScholarships()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.88rem" onchange="loadScholarships()">
                <option value="">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="closed">Closed</option>
            </select>
        </div>
        <div class="col-6 col-md-4 d-grid">
            <button class="btn btn-primary rounded-pill fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.88rem" onclick="loadScholarships()">
                <i class="fas fa-sync me-1"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Table -->
<div class="sch-table-wrap" id="tableWrap">
    <div style="overflow-x:auto">
        <table class="sch-table" id="scholarshipsTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Slots</th>
                    <th>Applications</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="scholarshipsBody">
                <tr><td colspan="8" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0" style="font-size:.85rem">Loading scholarships...</p>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="sch-pagination" id="pagination"></div>

</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 15;

const typeLabels = {
    fully_funded: {text:'Fully Funded',bg:'#dcfce7',color:'#15803d'},
    partially_funded: {text:'Partially Funded',bg:'#dbeafe',color:'#1e40af'},
    tuition_only: {text:'Tuition Only',bg:'#fef3c7',color:'#92400e'},
    research_grant: {text:'Research Grant',bg:'#ede9fe',color:'#7c3aed'},
    student_support: {text:'Student Support',bg:'#fce7f3',color:'#be185d'}
};

async function loadScholarships(page = 0) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('filterStatus').value;
    const body = document.getElementById('scholarshipsBody');

    body.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_scholarships', limit, offset: page * limit };
        if (search) params.search = search;
        if (status) params.status = status;

        const resp = await fetch(API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(params)
        });
        const result = await resp.json();

        if (!result.success) {
            body.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">${result.error || 'Error loading data'}</td></tr>`;
            return;
        }

        const data = result.data || [];
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No scholarships found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(s => {
            const type = typeLabels[s.scholarship_type] || {text:s.scholarship_type,bg:'#f1f5f9',color:'#475569'};
            const statusCls = s.status === 'published' ? 'sch-active' : (s.status === 'draft' ? 'sch-draft' : 'sch-closed');
            const isActive = s.is_active == 1;
            const bannerThumb = s.banner ? `<img src="${s.banner}" style="width:40px;height:28px;border-radius:6px;object-fit:cover;border:1px solid var(--color-border)" alt="">` : '<div style="width:40px;height:28px;border-radius:6px;background:var(--color-bg);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#94a3b8"><i class="fas fa-image"></i></div>';

            return `<tr>
                <td><code style="font-size:.78rem;background:#f1f5f9;padding:2px 8px;border-radius:4px">${escapeHtml(s.code)}</code></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        ${bannerThumb}
                        <div style="min-width:0">
                            <div class="fw-semibold" style="font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px">${escapeHtml(s.title)}</div>
                            <div style="font-size:.7rem;color:#94a3b8">${s.category_name || 'Uncategorized'}</div>
                        </div>
                    </div>
                </td>
                <td><span style="font-size:.75rem;color:#64748b">${escapeHtml(s.category_name || '—')}</span></td>
                <td><span class="sch-type-badge" style="background:${type.bg};color:${type.color}">${type.text}</span></td>
                <td style="font-size:.82rem;font-weight:600">${parseInt(s.slots) || '∞'}</td>
                <td style="font-size:.82rem;font-weight:600">${parseInt(s.total_applications) || 0}</td>
                <td>
                    <span class="sch-status ${statusCls}">${capitalizeFirst(s.status)}</span>
                    ${isActive ? '' : ' <span style="font-size:.6rem;color:#ef4444" title="Inactive"><i class="fas fa-eye-slash"></i></span>'}
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="scholarship_create.php?edit=${s.id}" class="btn btn-sm" style="font-size:.65rem;padding:2px 10px;background:#ede9fe;color:#7c3aed;border:none;border-radius:6px" title="Edit"><i class="fas fa-pen me-1"></i>Edit</a>
                        <button class="btn btn-sm" style="font-size:.65rem;padding:2px 10px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick="toggleActive(${s.id},${s.is_active})" title="Toggle Active"><i class="fas fa-${isActive ? 'eye' : 'eye-slash'}"></i></button>
                        <button class="btn btn-sm" style="font-size:.65rem;padding:2px 10px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px" onclick="deleteScholarship(${s.id},'${escapeHtml(s.title)}')" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(total, page);
    } catch (err) {
        body.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Network error: ${err.message}</td></tr>`;
    }
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / limit);
    if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }

    let html = '';
    if (page > 0) html += `<button class="sch-page-btn" onclick="loadScholarships(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) {
            html += `<button class="sch-page-btn active">${i+1}</button>`;
        } else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) {
            html += `<button class="sch-page-btn" onclick="loadScholarships(${i})">${i+1}</button>`;
        } else if (Math.abs(i - page) === 3) {
            html += `<span class="sch-page-btn" style="border:none;cursor:default">...</span>`;
        }
    }
    if (page < pages - 1) html += `<button class="sch-page-btn" onclick="loadScholarships(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

async function toggleActive(id, current) {
    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'toggle_scholarship', id, field: 'is_active', value: current ? 0 : 1 })
        });
        const result = await resp.json();
        if (result.success) loadScholarships(currentPage);
        else alert(result.error || 'Failed to toggle');
    } catch (err) { alert('Error: ' + err.message); }
}

async function deleteScholarship(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'delete_scholarship', id })
        });
        const result = await resp.json();
        if (result.success) loadScholarships(currentPage);
        else alert(result.error || 'Failed to delete');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function capitalizeFirst(s) {
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ');
}

document.addEventListener('DOMContentLoaded', () => loadScholarships());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>