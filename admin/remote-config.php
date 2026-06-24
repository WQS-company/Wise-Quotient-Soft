<?php
$path_to_root = "../";
$page_title = "Remote Config";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
if (($user_role ?? '') !== 'admin') { header("Location: {$path_to_root}dashboard"); exit; }
?>

<style>
.rc-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0A2D5E 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white; margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
}
.rc-hero::before {
    content: ''; position: absolute; top: -40%; right: -10%;
    width: 250px; height: 250px;
    background: radial-gradient(circle, rgba(99,102,241,0.2), transparent 70%); border-radius: 50%;
}
.rc-hero * { position: relative; z-index: 1; }
.rc-card {
    background: var(--color-bg, #fff); border: 1.5px solid var(--color-border, #e5e7eb);
    border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1rem;
    transition: all 0.2s;
}
.rc-card:hover { border-color: #93c5fd; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
.rc-param-key {
    font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem;
    font-weight: 700; color: #2563eb; background: #eff6ff;
    padding: 3px 10px; border-radius: 6px; display: inline-block;
}
.rc-type-badge {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    padding: 2px 8px; border-radius: 50px;
}
.rc-toggle {
    width: 42px; height: 22px; border-radius: 11px; border: none;
    cursor: pointer; position: relative; transition: all 0.3s;
    background: #d1d5db;
}
.rc-toggle.active { background: #22c55e; }
.rc-toggle::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 18px; height: 18px; border-radius: 50%;
    background: white; transition: transform 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.rc-toggle.active::after { transform: translateX(20px); }
.rc-empty {
    text-align: center; padding: 3rem; color: #9ca3af;
    border: 2px dashed #e5e7eb; border-radius: 16px;
}
.rc-stat { text-align: center; padding: 1rem; }
.rc-stat-num { font-size: 1.8rem; font-weight: 900; color: #0A2D5E; }
.rc-stat-lbl { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<div class="rc-hero mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <span style="background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.3);padding:4px 12px;border-radius:8px;font-size:0.7rem;font-weight:700;color:#a5b4fc;text-transform:uppercase;letter-spacing:0.5px;">Firebase Remote Config</span>
            <h4 class="fw-bold mt-2 mb-1" style="color:white;">Remote Configuration</h4>
            <p class="small mb-0" style="color:#94a3b8;">Manage site settings that update in real-time without code deployment.</p>
        </div>
        <button class="btn rounded-pill px-4 py-2 fw-bold" style="background:#6366f1;color:white;border:none;" onclick="openParamModal()">
            <i class="fas fa-plus me-1"></i> Add Parameter
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="rc-card"><div class="rc-stat"><div class="rc-stat-num" id="statTotal">0</div><div class="rc-stat-lbl">Total Params</div></div></div></div>
    <div class="col-md-3"><div class="rc-card"><div class="rc-stat"><div class="rc-stat-num" id="statActive" style="color:#22c55e;">0</div><div class="rc-stat-lbl">Active</div></div></div></div>
    <div class="col-md-3"><div class="rc-card"><div class="rc-stat"><div class="rc-stat-num" id="statInactive" style="color:#ef4444;">0</div><div class="rc-stat-lbl">Inactive</div></div></div></div>
    <div class="col-md-3"><div class="rc-card"><div class="rc-stat"><div class="rc-stat-num" id="statTypes" style="color:#8b5cf6;">0</div><div class="rc-stat-lbl">Param Types</div></div></div></div>
</div>

<!-- Parameter List -->
<div class="rc-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-body mb-0"><i class="fas fa-cog me-2 text-primary"></i>Configuration Parameters</h6>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search params..." style="width:200px;border-radius:8px;" oninput="filterParams()">
        </div>
    </div>
    <div id="paramsList">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
    </div>
</div>

<!-- Save/Cancel Modal -->
<div class="modal fade" id="paramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="paramModalTitle">Add Parameter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="paramId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Parameter Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="paramKey" placeholder="e.g. hero_title" style="border-radius:10px;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Type</label>
                        <select class="form-select" id="paramType" style="border-radius:10px;" onchange="updateValueInput()">
                            <option value="string">String</option>
                            <option value="number">Number</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Active</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="paramActive" checked style="cursor:pointer;">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Value</label>
                        <div id="valueInputWrapper">
                            <input type="text" class="form-control" id="paramValue" style="border-radius:10px;">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Default Value (fallback)</label>
                        <input type="text" class="form-control" id="paramDefault" style="border-radius:10px;">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Description</label>
                        <textarea class="form-control" id="paramDesc" rows="2" style="border-radius:10px;" placeholder="What does this parameter control?"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="saveParam()" id="saveParamBtn">
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allParams = [];
const API = '/dashboard/wqs/api/firebase_remote_config.php';

async function loadParams() {
    const res = await fetch(API + '?action=list_params');
    const data = await res.json();
    if (!data.success) return;
    allParams = data.data;
    renderParams(allParams);
    updateStats(allParams);
}

function renderParams(params) {
    const container = document.getElementById('paramsList');
    if (!params.length) {
        container.innerHTML = '<div class="rc-empty"><i class="fas fa-sliders-h fa-2x mb-2"></i><h6>No parameters yet</h6><p class="small mb-0">Add your first Remote Config parameter to get started.</p></div>';
        return;
    }
    container.innerHTML = params.map(p => {
        const typeColors = { string: '#3b82f6', number: '#8b5cf6', boolean: '#22c55e', json: '#f59e0b' };
        const typeColor = typeColors[p.param_type] || '#6b7280';
        const isOn = parseInt(p.is_active) === 1;
        return `
        <div class="rc-card d-flex align-items-center gap-3" data-key="${p.param_key}" data-desc="${(p.description||'').toLowerCase()}">
            <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="rc-param-key">${esc(p.param_key)}</span>
                    <span class="rc-type-badge" style="background:${typeColor}18;color:${typeColor};border:1px solid ${typeColor}30;">${p.param_type}</span>
                    ${!isOn ? '<span class="rc-type-badge" style="background:#fef2f2;color:#dc2626;">Disabled</span>' : ''}
                </div>
                <div class="text-muted small mb-1" style="max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <strong>Value:</strong> <code style="font-size:0.8rem;">${esc(String(p.param_value ?? '').substring(0, 120))}</code>
                </div>
                ${p.description ? `<div class="small text-secondary">${esc(p.description)}</div>` : ''}
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <button class="rc-toggle ${isOn ? 'active' : ''}" onclick="toggleParam(${p.id}, ${isOn ? 0 : 1})" title="Toggle active"></button>
                <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="editParam(${p.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteParam(${p.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    }).join('');
}

function updateStats(params) {
    document.getElementById('statTotal').textContent = params.length;
    document.getElementById('statActive').textContent = params.filter(p => parseInt(p.is_active) === 1).length;
    document.getElementById('statInactive').textContent = params.filter(p => parseInt(p.is_active) === 0).length;
    document.getElementById('statTypes').textContent = [...new Set(params.map(p => p.param_type))].length;
}

function filterParams() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const filtered = allParams.filter(p => p.param_key.toLowerCase().includes(q) || (p.description||'').toLowerCase().includes(q));
    renderParams(filtered);
}

function openParamModal(id = null) {
    document.getElementById('paramId').value = '';
    document.getElementById('paramKey').value = '';
    document.getElementById('paramValue').value = '';
    document.getElementById('paramType').value = 'string';
    document.getElementById('paramDefault').value = '';
    document.getElementById('paramDesc').value = '';
    document.getElementById('paramActive').checked = true;
    document.getElementById('paramModalTitle').textContent = 'Add Parameter';
    updateValueInput();
    new bootstrap.Modal(document.getElementById('paramModal')).show();
}

function editParam(id) {
    const p = allParams.find(x => x.id === id);
    if (!p) return;
    document.getElementById('paramId').value = p.id;
    document.getElementById('paramKey').value = p.param_key;
    document.getElementById('paramValue').value = p.param_value;
    document.getElementById('paramType').value = p.param_type;
    document.getElementById('paramDefault').value = p.default_value;
    document.getElementById('paramDesc').value = p.description;
    document.getElementById('paramActive').checked = parseInt(p.is_active) === 1;
    document.getElementById('paramModalTitle').textContent = 'Edit Parameter';
    updateValueInput();
    new bootstrap.Modal(document.getElementById('paramModal')).show();
}

function updateValueInput() {
    const type = document.getElementById('paramType').value;
    const wrapper = document.getElementById('valueInputWrapper');
    if (type === 'boolean') {
        wrapper.innerHTML = `<select class="form-select" id="paramValue" style="border-radius:10px;"><option value="1">True (enabled)</option><option value="0">False (disabled)</option></select>`;
    } else if (type === 'json') {
        wrapper.innerHTML = `<textarea class="form-control" id="paramValue" rows="3" style="border-radius:10px;font-family:monospace;font-size:0.85rem;" placeholder=\'{"key": "value"}\'></textarea>`;
    } else if (type === 'number') {
        wrapper.innerHTML = `<input type="number" class="form-control" id="paramValue" step="any" style="border-radius:10px;">`;
    } else {
        wrapper.innerHTML = `<input type="text" class="form-control" id="paramValue" style="border-radius:10px;">`;
    }
}

async function saveParam() {
    const id = document.getElementById('paramId').value;
    const fd = new FormData();
    fd.append('action', 'save_param');
    if (id) fd.append('id', id);
    fd.append('param_key', document.getElementById('paramKey').value);
    fd.append('param_value', document.getElementById('paramValue').value);
    fd.append('param_type', document.getElementById('paramType').value);
    fd.append('default_value', document.getElementById('paramDefault').value);
    fd.append('description', document.getElementById('paramDesc').value);
    if (document.getElementById('paramActive').checked) fd.append('is_active', '1');

    const btn = document.getElementById('saveParamBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    const res = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save me-1"></i> Save';

    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('paramModal')).hide();
        Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false });
        loadParams();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

async function toggleParam(id, newVal) {
    const fd = new FormData();
    fd.append('action', 'save_param');
    const p = allParams.find(x => x.id === id);
    fd.append('id', id);
    fd.append('param_key', p.param_key);
    fd.append('param_value', p.param_value);
    fd.append('param_type', p.param_type);
    fd.append('description', p.description || '');
    fd.append('default_value', p.default_value || '');
    if (newVal) fd.append('is_active', '1');
    await fetch(API, { method: 'POST', body: fd });
    loadParams();
}

async function deleteParam(id) {
    const { isConfirmed } = await Swal.fire({ icon: 'warning', title: 'Delete parameter?', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!isConfirmed) return;
    const fd = new FormData(); fd.append('action', 'delete_param'); fd.append('id', id);
    await fetch(API, { method: 'POST', body: fd });
    Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
    loadParams();
}

function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

loadParams();
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
