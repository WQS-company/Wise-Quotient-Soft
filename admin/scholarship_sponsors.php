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

$page_title = "Manage Scholarship Sponsors";
$current_page = "scholarship_sponsors.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.scs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem}
.scs-card{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden;transition:all .3s}
.scs-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.scs-card-logo{height:120px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--color-bg),#f1f5f9);border-bottom:1px solid var(--color-border);overflow:hidden}
.scs-card-logo img{max-height:80px;max-width:80%;object-fit:contain;border-radius:8px}
.scs-card-logo .placeholder{font-size:2.5rem;color:#cbd5e1}
.scs-card-body{padding:1rem 1.25rem}
.scs-card-name{font-weight:700;font-size:1rem;margin-bottom:.25rem}
.scs-card-contact{font-size:.78rem;color:#64748b;margin-bottom:.5rem}
.scs-card-contact i{width:16px;text-align:center;margin-right:6px;color:#94a3b8}
.scs-card-actions{display:flex;gap:.5rem;padding:.75rem 1.25rem;border-top:1px solid var(--color-border)}
.scs-form{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem}
.scs-form h5{font-weight:700;font-size:1rem;margin-bottom:1rem}
.scs-input{border:1px solid var(--color-border);border-radius:10px;padding:.6rem .9rem;font-size:.88rem;width:100%;transition:border-color .2s;background:var(--color-card-bg);color:var(--color-text)}
.scs-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.scs-empty{text-align:center;padding:3rem;color:#94a3b8;grid-column:1/-1}
.scs-empty i{font-size:3rem;margin-bottom:1rem;display:block;color:#cbd5e1}
.logo-preview{max-height:60px;border-radius:8px;border:2px dashed var(--color-border);display:none;margin-top:.5rem}
@media(max-width:575.98px){.scs-grid{grid-template-columns:1fr}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-handshake me-2"></i>Scholarship Sponsors</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Manage scholarship sponsors, partners, and organizations</p>
        </div>
        <button class="btn btn-warning fw-bold rounded-pill mt-2 mt-md-0" onclick="showForm()"><i class="fas fa-plus me-1"></i> New Sponsor</button>
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
<div class="scs-form" id="sponsorForm" style="display:none">
    <h5 id="formTitle"><i class="fas fa-plus-circle me-2 text-primary"></i>Add New Sponsor</h5>
    <input type="hidden" id="spId">
    <div class="row g-3">
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Organization Name <span class="text-danger">*</span></label>
            <input type="text" id="spName" class="scs-input" placeholder="e.g. MTN Foundation" required>
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Contact Person</label>
            <input type="text" id="spContactPerson" class="scs-input" placeholder="e.g. John Doe">
        </div>
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Email</label>
            <input type="email" id="spEmail" class="scs-input" placeholder="info@example.com">
        </div>
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Phone</label>
            <input type="text" id="spPhone" class="scs-input" placeholder="+234 800 000 0000">
        </div>
        <div class="col-md-4">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Website</label>
            <input type="url" id="spWebsite" class="scs-input" placeholder="https://example.com">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Address</label>
            <input type="text" id="spAddress" class="scs-input" placeholder="Full address">
        </div>
        <div class="col-md-6">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Logo</label>
            <input type="file" id="spLogo" class="scs-input" accept="image/*" onchange="previewLogo(this)">
            <img class="logo-preview" id="logoPreview">
        </div>
        <div class="col-12">
            <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Description</label>
            <textarea id="spDescription" class="scs-input" rows="2" placeholder="Brief description about the sponsor"></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.88rem" onclick="saveSponsor()"><i class="fas fa-save me-1"></i> Save</button>
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="hideForm()">Cancel</button>
    </div>
</div>

<!-- Sponsors Grid -->
<div class="scs-grid" id="sponsorsGrid">
    <div class="scs-empty"><i class="fas fa-spinner fa-spin"></i> Loading sponsors...</div>
</div>

</div>

<script>
const API = '../api/scholarship_api.php';
let sponsors = [];

function showForm(sp = null) {
    const form = document.getElementById('sponsorForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (sp) {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-pen-to-square me-2 text-primary"></i>Edit Sponsor';
        document.getElementById('spId').value = sp.id;
        document.getElementById('spName').value = sp.name || '';
        document.getElementById('spContactPerson').value = sp.contact_person || '';
        document.getElementById('spEmail').value = sp.email || '';
        document.getElementById('spPhone').value = sp.phone || '';
        document.getElementById('spWebsite').value = sp.website || '';
        document.getElementById('spAddress').value = sp.address || '';
        document.getElementById('spDescription').value = sp.description || '';
        if (sp.logo) {
            const preview = document.getElementById('logoPreview');
            preview.src = '../' + sp.logo;
            preview.style.display = 'block';
        }
    } else {
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle me-2 text-primary"></i>Add New Sponsor';
        ['spId','spName','spContactPerson','spEmail','spPhone','spWebsite','spAddress','spDescription'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('logoPreview').style.display = 'none';
    }
}

function hideForm() {
    document.getElementById('sponsorForm').style.display = 'none';
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('logoPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function loadSponsors() {
    const grid = document.getElementById('sponsorsGrid');
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'get_sponsors' }) });
        const result = await resp.json();

        if (!result.success) { grid.innerHTML = `<div class="scs-empty">${result.error || 'Error loading'}</div>`; return; }

        sponsors = result.data || [];

        if (sponsors.length === 0) {
            grid.innerHTML = '<div class="scs-empty"><i class="fas fa-handshake"></i>No sponsors yet. Add your first sponsor!</div>';
            return;
        }

        grid.innerHTML = sponsors.map(s => {
            const logo = s.logo ? `<img src="../${s.logo}" alt="${escapeHtml(s.name)}">` : '<div class="placeholder"><i class="fas fa-building"></i></div>';
            const contactItems = [];
            if (s.email) contactItems.push(`<div><i class="fas fa-envelope"></i>${escapeHtml(s.email)}</div>`);
            if (s.phone) contactItems.push(`<div><i class="fas fa-phone"></i>${escapeHtml(s.phone)}</div>`);
            if (s.website) contactItems.push(`<div><i class="fas fa-globe"></i><a href="${escapeHtml(s.website)}" target="_blank" style="color:#3b82f6;text-decoration:none">${escapeHtml(s.website.replace(/^https?:\/\//, ''))}</a></div>`);
            if (s.contact_person) contactItems.push(`<div><i class="fas fa-user"></i>${escapeHtml(s.contact_person)}</div>`);

            return `<div class="scs-card" id="sp-${s.id}">
                <div class="scs-card-logo">${logo}</div>
                <div class="scs-card-body">
                    <div class="scs-card-name">${escapeHtml(s.name)}</div>
                    <div class="scs-card-contact">${contactItems.join('') || '<div style="color:#cbd5e1;font-style:italic">No contact info</div>'}</div>
                </div>
                <div class="scs-card-actions">
                    <button class="btn btn-sm flex-grow-1" style="font-size:.75rem;background:#dbeafe;color:#1d4ed8;border:none;border-radius:8px;padding:6px" onclick='editSponsor(${JSON.stringify(s).replace(/'/g,"&#39;")})'><i class="fas fa-pen me-1"></i> Edit</button>
                    <button class="btn btn-sm flex-grow-1" style="font-size:.75rem;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px" onclick="deleteSponsor(${s.id},'${escapeHtml(s.name).replace(/'/g,"\\'")}')"><i class="fas fa-trash me-1"></i> Delete</button>
                </div>
            </div>`;
        }).join('');
    } catch (err) {
        grid.innerHTML = `<div class="scs-empty">Error: ${err.message}</div>`;
    }
}

function editSponsor(sp) {
    showForm(sp);
}

async function saveSponsor() {
    const id = document.getElementById('spId').value;
    const name = document.getElementById('spName').value.trim();
    if (!name) { alert('Sponsor name is required.'); return; }

    const fd = new FormData();
    fd.append('action', id ? 'update_sponsor' : 'create_sponsor');
    if (id) fd.append('id', id);
    fd.append('name', name);
    fd.append('contact_person', document.getElementById('spContactPerson').value.trim());
    fd.append('email', document.getElementById('spEmail').value.trim());
    fd.append('phone', document.getElementById('spPhone').value.trim());
    fd.append('website', document.getElementById('spWebsite').value.trim());
    fd.append('address', document.getElementById('spAddress').value.trim());
    fd.append('description', document.getElementById('spDescription').value.trim());

    const logoInput = document.getElementById('spLogo');
    if (logoInput.files && logoInput.files[0]) {
        fd.append('logo', logoInput.files[0]);
    }

    try {
        const resp = await fetch(API, { method: 'POST', body: fd });
        const result = await resp.json();
        if (result.success) {
            hideForm();
            loadSponsors();
        } else {
            alert(result.error || 'Failed to save sponsor');
        }
    } catch (err) { alert('Error: ' + err.message); }
}

async function deleteSponsor(id, name) {
    if (!confirm(`Delete sponsor "${name}"?`)) return;
    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'delete_sponsor', id }) });
        const result = await resp.json();
        if (result.success) loadSponsors();
        else alert(result.error || 'Failed to delete');
    } catch (err) { alert('Error: ' + err.message); }
}

function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => loadSponsors());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>