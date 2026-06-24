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

$page_title = "Manage Scholarship Categories";
$current_page = "scholarship_categories.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.scc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
.scc-card{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden;transition:all .3s}
.scc-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.scc-card-head{padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;border-bottom:1px solid var(--color-border)}
.scc-card-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.scc-card-title{font-weight:700;font-size:.95rem;margin:0}
.scc-card-body{padding:1rem 1.25rem}
.scc-card-desc{font-size:.82rem;color:#64748b;margin-bottom:.75rem;min-height:40px}
.scc-card-meta{display:flex;justify-content:space-between;align-items:center;font-size:.72rem;color:#94a3b8}
.scc-card-actions{display:flex;gap:.5rem;padding:.75rem 1.25rem;border-top:1px solid var(--color-border)}
.scc-form{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem}
.scc-form h5{font-weight:700;font-size:1rem;margin-bottom:1rem}
.scc-input{border:1px solid var(--color-border);border-radius:10px;padding:.6rem .9rem;font-size:.88rem;width:100%;transition:border-color .2s;background:var(--color-card-bg);color:var(--color-text)}
.scc-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.scc-empty{text-align:center;padding:3rem;color:#94a3b8}
.scc-empty i{font-size:3rem;margin-bottom:1rem;display:block;color:#cbd5e1}
@media(max-width:575.98px){.scc-grid{grid-template-columns:1fr}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-tags me-2"></i>Scholarship Categories</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Organize scholarships into structured categories</p>
        </div>
        <button class="btn btn-warning fw-bold rounded-pill mt-2 mt-md-0" onclick="showForm()"><i class="fas fa-plus me-1"></i> New Category</button>
    </div>
</div>

<!-- Flash -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Add/Edit Form -->
<div class="scc-form" id="categoryForm" style="display:none">
    <h5 id="formTitle"><i class="fas fa-plus-circle me-2 text-primary"></i>Add New Category</h5>
    <input type="hidden" id="catId">
    <div class="row g-3">
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Name <span class="text-danger">*</span></label>
            <input type="text" id="catName" class="scc-input" placeholder="e.g. STEM Scholarships" required>
        </div>
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Icon <small style="color:#94a3b8">(Font Awesome class)</small></label>
            <input type="text" id="catIcon" class="scc-input" placeholder="e.g. fas fa-flask" value="fas fa-tag">
        </div>
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Color</label>
            <div class="d-flex align-items-center gap-2">
                <input type="color" id="catColor" value="#3b82f6" style="width:50px;height:44px;border:none;border-radius:10px;cursor:pointer;padding:2px">
                <span id="catColorHex" style="font-size:.78rem;color:#64748b">#3b82f6</span>
            </div>
        </div>
        <div class="col-12">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Description</label>
            <textarea id="catDesc" class="scc-input" rows="2" placeholder="Brief description of this category"></textarea>
        </div>
        <div class="col-12">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Active</label>
            <label style="position:relative;width:44px;height:24px;display:inline-block;cursor:pointer">
                <input type="checkbox" id="catActive" checked style="opacity:0;width:0;height:0">
                <span style="position:absolute;inset:0;border-radius:12px;transition:background .3s;background:#cbd5e1" id="catActiveSlider"></span>
            </label>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.88rem" onclick="saveCategory()"><i class="fas fa-save me-1"></i> Save</button>
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="hideForm()">Cancel</button>
    </div>
</div>

<!-- Category Grid -->
<div class="scc-grid" id="categoriesGrid">
    <div class="scc-empty" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i> Loading categories...</div>
</div>

</div>

<script>
const API = '../api/scholarship_api.php';
const iconOptions = ['fas fa-tag','fas fa-flask','fas fa-graduation-cap','fas fa-book','fas fa-laptop-code','fas fa-heart','fas fa-globe','fas fa-seedling','fas fa-trophy','fas fa-handshake','fas fa-lightbulb','fas fa-users','fas fa-building','fas fa-gavel','fas fa-microscope'];

let categories = [];

document.getElementById('catActive').addEventListener('change', function() {
    document.getElementById('catActiveSlider').style.background = this.checked ? '#10b981' : '#cbd5e1';
});

document.getElementById('catColor').addEventListener('input', function() {
    document.getElementById('catColorHex').textContent = this.value;
});

function showForm(cat = null) {
    const form = document.getElementById('categoryForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (cat) {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-pen-to-square me-2 text-primary"></i>Edit Category';
        document.getElementById('catId').value = cat.id;
        document.getElementById('catName').value = cat.name;
        document.getElementById('catIcon').value = cat.icon || 'fas fa-tag';
        document.getElementById('catColor').value = cat.color || '#3b82f6';
        document.getElementById('catColorHex').textContent = cat.color || '#3b82f6';
        document.getElementById('catDesc').value = cat.description || '';
        document.getElementById('catActive').checked = cat.is_active != 0;
        document.getElementById('catActive').dispatchEvent(new Event('change'));
    } else {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle me-2 text-primary"></i>Add New Category';
        document.getElementById('catId').value = '';
        document.getElementById('catName').value = '';
        document.getElementById('catIcon').value = 'fas fa-tag';
        document.getElementById('catColor').value = '#3b82f6';
        document.getElementById('catColorHex').textContent = '#3b82f6';
        document.getElementById('catDesc').value = '';
        document.getElementById('catActive').checked = true;
        document.getElementById('catActive').dispatchEvent(new Event('change'));
    }
}

function hideForm() {
    document.getElementById('categoryForm').style.display = 'none';
}

async function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'get_categories' }) });
        const result = await resp.json();

        if (!result.success) { grid.innerHTML = `<div class="scc-empty" style="grid-column:1/-1">${result.error || 'Error loading'}</div>`; return; }

        categories = result.data || [];

        if (categories.length === 0) {
            grid.innerHTML = '<div class="scc-empty" style="grid-column:1/-1"><i class="fas fa-tags"></i>No categories yet. Create your first category!</div>';
            return;
        }

        grid.innerHTML = categories.map(c => `
            <div class="scc-card" id="cat-${c.id}">
                <div class="scc-card-head">
                    <div class="scc-card-icon" style="background:${c.color}15;color:${c.color}"><i class="${c.icon || 'fas fa-tag'}"></i></div>
                    <div>
                        <h6 class="scc-card-title">${escapeHtml(c.name)}</h6>
                        <span style="font-size:.68rem;color:${c.is_active ? '#10b981' : '#ef4444'};font-weight:600">${c.is_active ? 'Active' : 'Inactive'}</span>
                    </div>
                </div>
                <div class="scc-card-body">
                    <div class="scc-card-desc">${escapeHtml(c.description || 'No description')}</div>
                    <div class="scc-card-meta">
                        <span><i class="fas fa-clock me-1"></i>${c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}</span>
                        <span style="color:${c.color};font-weight:700">${c.color}</span>
                    </div>
                </div>
                <div class="scc-card-actions">
                    <button class="btn btn-sm flex-grow-1" style="font-size:.75rem;background:#dbeafe;color:#1d4ed8;border:none;border-radius:8px;padding:6px" onclick='editCategory(${JSON.stringify(c).replace(/'/g,"&#39;")})'><i class="fas fa-pen me-1"></i> Edit</button>
                    <button class="btn btn-sm flex-grow-1" style="font-size:.75rem;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px" onclick="deleteCategory(${c.id},'${escapeHtml(c.name).replace(/'/g,"\\'")}')"><i class="fas fa-trash me-1"></i> Delete</button>
                </div>
            </div>
        `).join('');
    } catch (err) {
        grid.innerHTML = `<div class="scc-empty" style="grid-column:1/-1">Error: ${err.message}</div>`;
    }
}

function editCategory(cat) {
    showForm(cat);
}

async function saveCategory() {
    const id = document.getElementById('catId').value;
    const name = document.getElementById('catName').value.trim();
    if (!name) { alert('Category name is required.'); return; }

    const params = {
        action: id ? 'update_category' : 'create_category',
        name,
        description: document.getElementById('catDesc').value.trim(),
        icon: document.getElementById('catIcon').value.trim() || 'fas fa-tag',
        color: document.getElementById('catColor').value,
        is_active: document.getElementById('catActive').checked ? 1 : 0
    };
    if (id) params.id = parseInt(id);

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            hideForm();
            loadCategories();
        } else {
            alert(result.error || 'Failed to save category');
        }
    } catch (err) { alert('Error: ' + err.message); }
}

async function deleteCategory(id, name) {
    if (!confirm(`Delete category "${name}"? Scholarships in this category will become uncategorized.`)) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'delete_category', id }) });
        const result = await resp.json();
        if (result.success) loadCategories();
        else alert(result.error || 'Failed to delete');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => loadCategories());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>