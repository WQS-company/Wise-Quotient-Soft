<?php
$path_to_root = "../";
$page_title = "Popup Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Only admins
if (!in_array($_SESSION['user']['role'] ?? '', ['admin','developer'])) {
    echo '<div class="container py-5 text-center"><h4>Access Denied</h4></div>';
    require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
    exit;
}
$userId = $_SESSION['user']['id'];
?>

<style>
body, .wrapper, .main-wrapper { overflow-x: hidden !important; }

.popup-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}
@media (max-width: 1199.98px) { .popup-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px) { .popup-grid { grid-template-columns: 1fr; } }

.popup-card {
    background: var(--color-card-bg, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.popup-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.1);
}
.popup-card.inactive { opacity: 0.6; }

.popup-preview {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 2rem;
}
.popup-preview img { width: 100%; height: 100%; object-fit: cover; }

.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.68rem;
    font-weight: 600;
}

/* Modal Form */
.form-floating-popup .form-label { font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem; }
.form-floating-popup .form-control, .form-floating-popup .form-select { border-radius: 10px; border-color: #e2e8f0; font-size: 0.88rem; }
.form-floating-popup .form-control:focus, .form-floating-popup .form-select:focus { border-color: #0A2D5E; box-shadow: 0 0 0 3px rgba(10,45,94,0.1); }
</style>

<!-- ─── HEADER ─── -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;font-size:0.65rem;font-weight:700;padding:0.25rem 0.75rem;border-radius:50px;text-transform:uppercase;letter-spacing:0.5px;">
        <i class="fas fa-rocket me-1"></i> Promotions
      </span>
    </div>
    <h3 class="fw-bold text-body mb-0">Popup Manager</h3>
    <p class="text-muted mb-0 mt-1" style="font-size:0.88rem;">Create and manage floating promotional popups across the platform.</p>
  </div>
  <button class="btn rounded-pill px-4" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border:none;font-weight:600;font-size:0.85rem;box-shadow:0 4px 12px rgba(124,58,237,0.3);" onclick="openCreateModal()">
    <i class="fas fa-plus me-1"></i> New Popup
  </button>
</div>

<!-- ─── STATS ─── -->
<div class="row g-3 mb-4" id="popupStats">
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="background:#f5f3ff;border:1px solid #7c3aed15;">
      <div class="stat-icon" style="background:#7c3aed;"><i class="fas fa-rocket"></i></div>
      <div><div style="font-size:1.5rem;font-weight:800;color:#7c3aed;line-height:1;" id="statTotal">0</div><div style="font-size:0.75rem;font-weight:600;color:#7c3aed;opacity:0.8;">Total</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="background:#f0fdf4;border:1px solid #15803d15;">
      <div class="stat-icon" style="background:#15803d;"><i class="fas fa-check-circle"></i></div>
      <div><div style="font-size:1.5rem;font-weight:800;color:#15803d;line-height:1;" id="statActive">0</div><div style="font-size:0.75rem;font-weight:600;color:#15803d;opacity:0.8;">Active</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="background:#eff6ff;border:1px solid #1d4ed815;">
      <div class="stat-icon" style="background:#1d4ed8;"><i class="fas fa-eye"></i></div>
      <div><div style="font-size:1.5rem;font-weight:800;color:#1d4ed8;line-height:1;" id="statViews">0</div><div style="font-size:0.75rem;font-weight:600;color:#1d4ed8;opacity:0.8;">Total Views</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="background:#fef3c7;border:1px solid #d9770615;">
      <div class="stat-icon" style="background:#d97706;"><i class="fas fa-mouse-pointer"></i></div>
      <div><div style="font-size:1.5rem;font-weight:800;color:#d97706;line-height:1;" id="statClicks">0</div><div style="font-size:0.75rem;font-weight:600;color:#d97706;opacity:0.8;">Total Clicks</div></div>
    </div>
  </div>
</div>

<!-- ─── POPUP CARDS ─── -->
<div class="popup-grid" id="popupGrid">
  <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin d-block mb-2" style="font-size:2rem;"></i>Loading popups...</div>
</div>

<!-- ─── CREATE/EDIT MODAL ─── -->
<div class="modal fade" id="popupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-xl rounded-4">
      <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border:none;">
        <h5 class="modal-title fw-bold" id="popupModalTitle"><i class="fas fa-plus-circle me-2"></i>New Popup</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body form-floating-popup">
        <input type="hidden" id="editPopupId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Title *</label>
            <input type="text" class="form-control" id="popupTitle" placeholder="Enter popup title" required>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="popupDesc" rows="3" placeholder="Popup description text..."></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Image URL</label>
            <input type="url" class="form-control" id="popupImage" placeholder="https://example.com/image.jpg">
          </div>
          <div class="col-md-6">
            <label class="form-label">Button Text</label>
            <input type="text" class="form-control" id="popupBtnText" value="Learn More">
          </div>
          <div class="col-md-6">
            <label class="form-label">Button URL</label>
            <input type="url" class="form-control" id="popupBtnUrl" placeholder="https://...">
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="popupStartDate">
          </div>
          <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" id="popupEndDate">
          </div>
          <div class="col-md-4">
            <label class="form-label">Timer (seconds)</label>
            <input type="number" class="form-control" id="popupTimer" value="10" min="3" max="60">
          </div>
          <div class="col-md-4">
            <label class="form-label">Position</label>
            <select class="form-select" id="popupPosition">
              <option value="center">Center</option>
              <option value="top-left">Top Left</option>
              <option value="top-right">Top Right</option>
              <option value="bottom-left">Bottom Left</option>
              <option value="bottom-right">Bottom Right</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Size</label>
            <select class="form-select" id="popupSize">
              <option value="sm">Small</option>
              <option value="md" selected>Medium</option>
              <option value="lg">Large</option>
              <option value="xl">Extra Large</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Trigger</label>
            <select class="form-select" id="popupTrigger">
              <option value="immediate">Show Immediately</option>
              <option value="delay">After X Seconds</option>
              <option value="scroll">After Scroll</option>
              <option value="exit">Exit Intent</option>
              <option value="once_daily">Once Per Day</option>
              <option value="once_user">Once Per User</option>
            </select>
          </div>
          <div class="col-md-4" id="delayGroup" style="display:none;">
            <label class="form-label">Delay (seconds)</label>
            <input type="number" class="form-control" id="popupDelay" value="3" min="1" max="60">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="popupActive">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn rounded-pill px-4" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border:none;font-weight:600;" onclick="savePopup()">
          <i class="fas fa-save me-1"></i> Save Popup
        </button>
      </div>
    </div>
  </div>
</div>

<script>
/* ─── Load all popups ─── */
function loadPopups() {
    fetch('../api/popup_api.php?action=list_popups')
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        var popups = data.popups || [];
        var grid = document.getElementById('popupGrid');
        if (!popups.length) {
            grid.innerHTML = '<div class="text-center py-5 text-muted" style="grid-column:1/-1;"><i class="fas fa-rocket d-block mb-3" style="font-size:2.5rem;color:#cbd5e1;"></i><p class="small">No popups yet. Create your first promotional popup!</p></div>';
            document.getElementById('statTotal').textContent = '0';
            document.getElementById('statActive').textContent = '0';
            document.getElementById('statViews').textContent = '0';
            document.getElementById('statClicks').textContent = '0';
            return;
        }
        var totalViews = 0, totalClicks = 0, totalActive = 0;
        var html = '';
        popups.forEach(function(p) {
            var ctr = p.views > 0 ? ((p.clicks / p.views) * 100).toFixed(1) : '0.0';
            var closeRate = p.views > 0 ? ((p.closes / p.views) * 100).toFixed(1) : '0.0';
            totalViews += parseInt(p.views || 0);
            totalClicks += parseInt(p.clicks || 0);
            if (p.is_active == 1) totalActive++;
            html += '<div class="popup-card ' + (p.is_active == 0 ? 'inactive' : '') + '" id="pc-' + p.id + '">';
            html += '<div class="popup-preview">' + (p.image_url ? '<img src="' + p.image_url + '" alt="">' : '<i class="fas fa-rocket"></i>') + '</div>';
            html += '<div class="p-3 flex-grow-1">';
            html += '<div class="d-flex justify-content-between align-items-start mb-2">';
            html += '<h6 class="fw-bold mb-0" style="font-size:0.92rem;">' + escHtml(p.title) + '</h6>';
            html += '<span class="stat-pill" style="background:' + (p.is_active == 1 ? '#dcfce7;color:#15803d' : '#f1f5f9;color:#64748b') + ';">' + (p.is_active == 1 ? 'Active' : 'Off') + '</span>';
            html += '</div>';
            html += '<p style="font-size:0.78rem;color:#64748b;margin-bottom:0.6rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + escHtml(p.description || 'No description') + '</p>';
            html += '<div class="d-flex gap-2 flex-wrap mb-3">';
            html += '<span class="stat-pill" style="background:#eff6ff;color:#1d4ed8;"><i class="fas fa-eye"></i> ' + p.views + '</span>';
            html += '<span class="stat-pill" style="background:#f0fdf4;color:#15803d;"><i class="fas fa-mouse-pointer"></i> ' + p.clicks + '</span>';
            html += '<span class="stat-pill" style="background:#fef3c7;color:#d97706;">CTR ' + ctr + '%</span>';
            html += '<span class="stat-pill" style="background:#fce7f3;color:#be185d;">Close ' + closeRate + '%</span>';
            html += '</div>';
            html += '<div class="d-flex gap-1">';
            html += '<button class="btn btn-sm rounded-pill flex-grow-1" style="background:#f1f5f9;color:#475569;border:none;font-size:0.72rem;font-weight:600;" onclick="editPopup(' + p.id + ')"><i class="fas fa-pen me-1"></i>Edit</button>';
            html += '<button class="btn btn-sm rounded-pill" style="background:' + (p.is_active == 1 ? '#fef3c7;color:#d97706' : '#dcfce7;color:#15803d') + ';border:none;font-size:0.72rem;font-weight:600;" onclick="togglePopup(' + p.id + ')"><i class="fas fa-' + (p.is_active == 1 ? 'pause' : 'play') + '"></i></button>';
            html += '<button class="btn btn-sm rounded-pill" style="background:#fee2e2;color:#dc2626;border:none;font-size:0.72rem;font-weight:600;" onclick="deletePopup(' + p.id + ',\'' + escHtml(p.title) + '\')"><i class="fas fa-trash"></i></button>';
            html += '</div></div></div>';
        });
        grid.innerHTML = html;
        document.getElementById('statTotal').textContent = popups.length;
        document.getElementById('statActive').textContent = totalActive;
        document.getElementById('statViews').textContent = totalViews.toLocaleString();
        document.getElementById('statClicks').textContent = totalClicks.toLocaleString();
    })
    .catch(() => {
        document.getElementById('popupGrid').innerHTML = '<div class="text-center py-5 text-danger">Failed to load popups.</div>';
    });
}

function escHtml(str) { var d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }

/* ─── Create/Edit modal ─── */
function openCreateModal() {
    document.getElementById('popupModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>New Popup';
    document.getElementById('editPopupId').value = '';
    document.getElementById('popupTitle').value = '';
    document.getElementById('popupDesc').value = '';
    document.getElementById('popupImage').value = '';
    document.getElementById('popupBtnText').value = 'Learn More';
    document.getElementById('popupBtnUrl').value = '';
    document.getElementById('popupStartDate').value = '';
    document.getElementById('popupEndDate').value = '';
    document.getElementById('popupTimer').value = '10';
    document.getElementById('popupPosition').value = 'center';
    document.getElementById('popupSize').value = 'md';
    document.getElementById('popupTrigger').value = 'immediate';
    document.getElementById('popupDelay').value = '3';
    document.getElementById('popupActive').value = '1';
    document.getElementById('delayGroup').style.display = 'none';
    new bootstrap.Modal(document.getElementById('popupModal')).show();
}

function editPopup(id) {
    fetch('../api/popup_api.php?action=get_popup&popup_id=' + id)
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.popup) return;
        var p = data.popup;
        document.getElementById('popupModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Popup';
        document.getElementById('editPopupId').value = p.id;
        document.getElementById('popupTitle').value = p.title;
        document.getElementById('popupDesc').value = p.description || '';
        document.getElementById('popupImage').value = p.image_url || '';
        document.getElementById('popupBtnText').value = p.button_text || 'Learn More';
        document.getElementById('popupBtnUrl').value = p.button_url || '';
        document.getElementById('popupStartDate').value = p.start_date || '';
        document.getElementById('popupEndDate').value = p.end_date || '';
        document.getElementById('popupTimer').value = p.timer_duration || 10;
        document.getElementById('popupPosition').value = p.position || 'center';
        document.getElementById('popupSize').value = p.size || 'md';
        document.getElementById('popupTrigger').value = p.trigger || 'immediate';
        document.getElementById('popupDelay').value = p.trigger_delay || 3;
        document.getElementById('popupActive').value = p.is_active;
        document.getElementById('delayGroup').style.display = p.trigger === 'delay' ? 'block' : 'none';
        new bootstrap.Modal(document.getElementById('popupModal')).show();
    });
}

document.getElementById('popupTrigger').addEventListener('change', function() {
    document.getElementById('delayGroup').style.display = this.value === 'delay' ? 'block' : 'none';
});

/* ─── Save popup ─── */
function savePopup() {
    var id = document.getElementById('editPopupId').value;
    var fd = new FormData();
    fd.append('title', document.getElementById('popupTitle').value.trim());
    fd.append('description', document.getElementById('popupDesc').value.trim());
    fd.append('image_url', document.getElementById('popupImage').value.trim());
    fd.append('button_text', document.getElementById('popupBtnText').value.trim());
    fd.append('button_url', document.getElementById('popupBtnUrl').value.trim());
    fd.append('start_date', document.getElementById('popupStartDate').value);
    fd.append('end_date', document.getElementById('popupEndDate').value);
    fd.append('timer_duration', document.getElementById('popupTimer').value);
    fd.append('position', document.getElementById('popupPosition').value);
    fd.append('size', document.getElementById('popupSize').value);
    fd.append('trigger', document.getElementById('popupTrigger').value);
    fd.append('trigger_delay', document.getElementById('popupDelay').value);
    fd.append('is_active', document.getElementById('popupActive').value);

    if (!fd.get('title')) { Swal.fire({icon:'warning',title:'Required',text:'Title is required.'}); return; }

    if (id) fd.append('popup_id', id);
    var action = id ? 'update_popup' : 'create_popup';

    fetch('../api/popup_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({icon:'success',title:'Saved!',text:'Popup has been saved.',timer:1500,showConfirmButton:false});
            bootstrap.Modal.getInstance(document.getElementById('popupModal')).hide();
            loadPopups();
        } else {
            Swal.fire({icon:'error',title:'Error',text:data.error||'Failed to save.'});
        }
    });
}

/* ─── Toggle active ─── */
function togglePopup(id) {
    fetch('../api/popup_api.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=toggle_popup&popup_id='+id })
    .then(r => r.json())
    .then(data => { if(data.success) loadPopups(); });
}

/* ─── Delete popup ─── */
function deletePopup(id, title) {
    Swal.fire({title:'Delete Popup?',html:'Are you sure you want to delete <strong>'+title+'</strong>?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#6b7280',confirmButtonText:'Yes, delete'}).then(function(res) {
        if (!res.isConfirmed) return;
        fetch('../api/popup_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=delete_popup&popup_id='+id})
        .then(r=>r.json()).then(d=>{ if(d.success){Swal.fire({icon:'success',title:'Deleted',timer:1200,showConfirmButton:false});loadPopups();} });
    });
}

/* ─── Init ─── */
document.addEventListener('DOMContentLoaded', loadPopups);
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
