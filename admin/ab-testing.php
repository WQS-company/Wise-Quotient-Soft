<?php
$path_to_root = "../";
$page_title = "A/B Testing";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
if (($user_role ?? '') !== 'admin') { header("Location: {$path_to_root}dashboard"); exit; }
?>

<style>
.ab-hero {
    background: linear-gradient(135deg, #0f172a 0%, #4c1d95 50%, #6d28d9 100%);
    border-radius: 20px; padding: 2rem 2.5rem; color: white; margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
}
.ab-hero::before {
    content: ''; position: absolute; top: -40%; right: -10%;
    width: 250px; height: 250px;
    background: radial-gradient(circle, rgba(236,72,153,0.2), transparent 70%); border-radius: 50%;
}
.ab-hero * { position: relative; z-index: 1; }
.ab-card {
    background: var(--color-bg, #fff); border: 1.5px solid var(--color-border, #e5e7eb);
    border-radius: 14px; padding: 1.5rem; margin-bottom: 1rem;
    transition: all 0.2s;
}
.ab-card:hover { border-color: #c084fc; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
.ab-status {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px;
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
}
.ab-variant-bar {
    height: 8px; border-radius: 4px; background: #f1f5f9; overflow: hidden; margin-top: 4px;
}
.ab-variant-fill { height: 100%; border-radius: 4px; transition: width 0.5s; }
.ab-stat { text-align: center; padding: 0.75rem; }
.ab-stat-num { font-size: 1.5rem; font-weight: 900; }
.ab-stat-lbl { font-size: 0.68rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
.variant-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;
    border: 1.5px solid #e5e7eb; margin-right: 6px; margin-bottom: 6px;
}
.variant-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
</style>

<div class="ab-hero mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <span style="background:rgba(236,72,153,0.2);border:1px solid rgba(236,72,153,0.3);padding:4px 12px;border-radius:8px;font-size:0.7rem;font-weight:700;color:#f9a8d4;text-transform:uppercase;letter-spacing:0.5px;">Firebase A/B Testing</span>
            <h4 class="fw-bold mt-2 mb-1" style="color:white;">Experiments Dashboard</h4>
            <p class="small mb-0" style="color:#c4b5fd;">Create and manage A/B tests to optimize your site experience with data-driven decisions.</p>
        </div>
        <button class="btn rounded-pill px-4 py-2 fw-bold" style="background:#8b5cf6;color:white;border:none;" onclick="openTestModal()">
            <i class="fas fa-flask me-1"></i> New Experiment
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" id="statTotal" style="color:#6d28d9;">0</div><div class="ab-stat-lbl">Total Tests</div></div></div></div>
    <div class="col-md-3"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" id="statRunning" style="color:#22c55e;">0</div><div class="ab-stat-lbl">Running</div></div></div></div>
    <div class="col-md-3"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" id="statCompleted" style="color:#3b82f6;">0</div><div class="ab-stat-lbl">Completed</div></div></div></div>
    <div class="col-md-3"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" id="statParticipants" style="color:#f59e0b;">0</div><div class="ab-stat-lbl">Total Participants</div></div></div></div>
</div>

<!-- Test List -->
<div class="ab-card">
    <h6 class="fw-bold text-body mb-3"><i class="fas fa-flask me-2 text-purple"></i>Experiments</h6>
    <div id="testsList">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
    </div>
</div>

<!-- Test Modal -->
<div class="modal fade" id="testModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="testModalTitle">New Experiment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="testId" value="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small">Experiment Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="testName" placeholder="e.g. Hero CTA Button Color Test" style="border-radius:10px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Status</label>
                        <select class="form-select" id="testStatus" style="border-radius:10px;">
                            <option value="draft">Draft</option>
                            <option value="running">Running</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small">Remote Config Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="testParamKey" placeholder="e.g. hero_cta_color" style="border-radius:10px;">
                        <div class="form-text">Must match an existing Remote Config parameter key.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Traffic %</label>
                        <input type="number" class="form-control" id="testTraffic" value="100" min="1" max="100" style="border-radius:10px;">
                        <div class="form-text">Percentage of visitors to include.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Description</label>
                        <textarea class="form-control" id="testDesc" rows="2" style="border-radius:10px;" placeholder="What are you testing?"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Variants <span class="text-danger">*</span></label>
                        <div id="variantsContainer">
                            <div class="d-flex gap-2 mb-2 align-items-center variant-row">
                                <input type="text" class="form-control form-control-sm" placeholder="Variant name (e.g. control)" style="border-radius:8px;width:150px;" data-variant-name>
                                <input type="text" class="form-control form-control-sm" placeholder="Value for this variant" style="border-radius:8px;flex:1;" data-variant-value>
                                <button class="btn btn-sm btn-outline-danger rounded-circle" onclick="this.closest('.variant-row').remove()" style="width:30px;height:30px;"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary rounded-pill mt-1" onclick="addVariantRow()"><i class="fas fa-plus me-1"></i> Add Variant</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="saveTest()" id="saveTestBtn">
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="detailTitle">Experiment Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>
</div>

<script>
let allTests = [];
const API = '/dashboard/wqs/api/firebase_remote_config.php';
const variantColors = ['#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'];

async function loadTests() {
    const res = await fetch(API + '?action=list_tests');
    const data = await res.json();
    if (!data.success) return;
    allTests = data.data;
    renderTests(allTests);
    updateStats(allTests);
}

function renderTests(tests) {
    const container = document.getElementById('testsList');
    if (!tests.length) {
        container.innerHTML = '<div class="text-center py-5" style="color:#9ca3af;"><i class="fas fa-flask fa-2x mb-2"></i><h6>No experiments yet</h6><p class="small mb-0">Create your first A/B test to start optimizing.</p></div>';
        return;
    }
    container.innerHTML = tests.map(t => {
        const statusStyles = {
            draft: { bg: '#f1f5f9', color: '#64748b', icon: 'fa-pen' },
            running: { bg: '#dcfce7', color: '#166534', icon: 'fa-play' },
            paused: { bg: '#fef3c7', color: '#92400e', icon: 'fa-pause' },
            completed: { bg: '#dbeafe', color: '#1e40af', icon: 'fa-flag-checkered' },
        };
        const ss = statusStyles[t.status] || statusStyles.draft;
        const variants = JSON.parse(t.variants || '{}');
        const vKeys = Object.keys(variants);
        const convRate = t.total_assigned > 0 ? ((t.total_converted / t.total_assigned) * 100).toFixed(1) : 0;
        return `
        <div class="ab-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h6 class="fw-bold text-body mb-0">${esc(t.name)}</h6>
                        <span class="ab-status" style="background:${ss.bg};color:${ss.color};"><i class="fas ${ss.icon}"></i> ${t.status}</span>
                    </div>
                    <div class="small text-muted mb-2">${esc(t.description || '')} · Key: <code>${esc(t.param_key)}</code> · Traffic: ${t.traffic_pct}%</div>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-info rounded-pill" onclick="viewDetail(${t.id})" title="View Results"><i class="fas fa-chart-bar"></i></button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="editTest(${t.id})" title="Edit"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteTest(${t.id})" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div class="d-flex flex-wrap mb-2">
                ${vKeys.map((v, i) => `
                    <div class="variant-chip">
                        <span class="variant-dot" style="background:${variantColors[i % variantColors.length]};"></span>
                        <strong>${esc(v)}</strong>: <code style="font-size:0.75rem;">${esc(String(variants[v]).substring(0, 50))}</code>
                    </div>
                `).join('')}
            </div>
            <div class="d-flex gap-4 small text-muted">
                <span><i class="fas fa-users me-1"></i> ${t.total_assigned} participants</span>
                <span><i class="fas fa-check-circle me-1"></i> ${t.total_converted} conversions</span>
                <span><i class="fas fa-percentage me-1"></i> ${convRate}% rate</span>
            </div>
        </div>`;
    }).join('');
}

function updateStats(tests) {
    document.getElementById('statTotal').textContent = tests.length;
    document.getElementById('statRunning').textContent = tests.filter(t => t.status === 'running').length;
    document.getElementById('statCompleted').textContent = tests.filter(t => t.status === 'completed').length;
    document.getElementById('statParticipants').textContent = tests.reduce((s, t) => s + (t.total_assigned || 0), 0);
}

function addVariantRow(name = '', value = '') {
    const c = document.getElementById('variantsContainer');
    const div = document.createElement('div');
    div.className = 'd-flex gap-2 mb-2 align-items-center variant-row';
    div.innerHTML = `
        <input type="text" class="form-control form-control-sm" placeholder="Variant name" value="${esc(name)}" style="border-radius:8px;width:150px;" data-variant-name>
        <input type="text" class="form-control form-control-sm" placeholder="Value" value="${esc(value)}" style="border-radius:8px;flex:1;" data-variant-value>
        <button class="btn btn-sm btn-outline-danger rounded-circle" onclick="this.closest('.variant-row').remove()" style="width:30px;height:30px;"><i class="fas fa-times"></i></button>`;
    c.appendChild(div);
}

function openTestModal() {
    document.getElementById('testId').value = '';
    document.getElementById('testName').value = '';
    document.getElementById('testDesc').value = '';
    document.getElementById('testParamKey').value = '';
    document.getElementById('testStatus').value = 'draft';
    document.getElementById('testTraffic').value = 100;
    document.getElementById('testModalTitle').textContent = 'New Experiment';
    document.getElementById('variantsContainer').innerHTML = '';
    addVariantRow('control', '');
    addVariantRow('variant_a', '');
    new bootstrap.Modal(document.getElementById('testModal')).show();
}

function editTest(id) {
    const t = allTests.find(x => x.id === id);
    if (!t) return;
    document.getElementById('testId').value = t.id;
    document.getElementById('testName').value = t.name;
    document.getElementById('testDesc').value = t.description;
    document.getElementById('testParamKey').value = t.param_key;
    document.getElementById('testStatus').value = t.status;
    document.getElementById('testTraffic').value = t.traffic_pct;
    document.getElementById('testModalTitle').textContent = 'Edit Experiment';
    document.getElementById('variantsContainer').innerHTML = '';
    const variants = JSON.parse(t.variants || '{}');
    Object.entries(variants).forEach(([k, v]) => addVariantRow(k, String(v)));
    if (!Object.keys(variants).length) { addVariantRow('control', ''); addVariantRow('variant_a', ''); }
    new bootstrap.Modal(document.getElementById('testModal')).show();
}

async function saveTest() {
    const variants = {};
    document.querySelectorAll('.variant-row').forEach(row => {
        const n = row.querySelector('[data-variant-name]').value.trim();
        const v = row.querySelector('[data-variant-value]').value.trim();
        if (n) variants[n] = v;
    });

    const fd = new FormData();
    fd.append('action', 'save_test');
    const id = document.getElementById('testId').value;
    if (id) fd.append('id', id);
    fd.append('name', document.getElementById('testName').value);
    fd.append('description', document.getElementById('testDesc').value);
    fd.append('param_key', document.getElementById('testParamKey').value);
    fd.append('status', document.getElementById('testStatus').value);
    fd.append('traffic_pct', document.getElementById('testTraffic').value);
    fd.append('variants', JSON.stringify(variants));

    const btn = document.getElementById('saveTestBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    const res = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save me-1"></i> Save';

    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('testModal')).hide();
        Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false });
        loadTests();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

async function deleteTest(id) {
    const { isConfirmed } = await Swal.fire({ icon: 'warning', title: 'Delete experiment?', text: 'All assignments will be lost.', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!isConfirmed) return;
    const fd = new FormData(); fd.append('action', 'delete_test'); fd.append('id', id);
    await fetch(API, { method: 'POST', body: fd });
    Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
    loadTests();
}

async function viewDetail(id) {
    const res = await fetch(API + '?action=get_test_detail&id=' + id);
    const data = await res.json();
    if (!data.success) return Swal.fire({ icon: 'error', text: data.message });

    const t = data.data;
    const stats = data.variant_stats;
    const vKeys = Object.keys(stats);
    const maxAssigned = Math.max(...vKeys.map(v => stats[v].assigned), 1);

    let html = `
        <div class="mb-3"><strong>${esc(t.name)}</strong> · <span class="text-muted">${esc(t.description||'')}</span></div>
        <div class="row g-3 mb-3">
            <div class="col-4"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" style="color:#6d28d9;">${t.traffic_pct}%</div><div class="ab-stat-lbl">Traffic</div></div></div></div>
            <div class="col-4"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" style="color:#22c55e;">${vKeys.reduce((s,v)=>s+stats[v].assigned,0)}</div><div class="ab-stat-lbl">Participants</div></div></div></div>
            <div class="col-4"><div class="ab-card"><div class="ab-stat"><div class="ab-stat-num" style="color:#3b82f6;">${vKeys.reduce((s,v)=>s+stats[v].converted,0)}</div><div class="ab-stat-lbl">Conversions</div></div></div></div>
        </div>
        <h6 class="fw-bold mb-3">Variant Breakdown</h6>`;

    vKeys.forEach((v, i) => {
        const s = stats[v];
        const barWidth = maxAssigned > 0 ? (s.assigned / maxAssigned * 100) : 0;
        const convBar = s.assigned > 0 ? (s.converted / s.assigned * 100) : 0;
        const isWinner = t.winner_variant === v;
        html += `
        <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="variant-dot" style="background:${variantColors[i % variantColors.length]};width:10px;height:10px;"></span>
                    <strong>${esc(v)}</strong>
                    ${isWinner ? '<span class="ab-status" style="background:#dcfce7;color:#166534;">Winner</span>' : ''}
                </div>
                <code style="font-size:0.8rem;">${esc(String(JSON.parse(t.variants)[v]||'').substring(0,60))}</code>
            </div>
            <div class="d-flex gap-4 small text-muted mb-2">
                <span>Assigned: <strong>${s.assigned}</strong></span>
                <span>Converted: <strong>${s.converted}</strong></span>
                <span>Rate: <strong>${s.rate}%</strong></span>
            </div>
            <div class="ab-variant-bar"><div class="ab-variant-fill" style="width:${convBar}%;background:${variantColors[i % variantColors.length]};"></div></div>
        </div>`;
    });

    document.getElementById('detailTitle').textContent = t.name + ' — Results';
    document.getElementById('detailBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

loadTests();
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
