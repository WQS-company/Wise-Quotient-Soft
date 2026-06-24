<?php
$path_to_root = "../";
$page_title = "Document Vault";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
$userId = $headerUser['id'];

// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    ob_end_clean(); // Clear any HTML outputted by dashboard_header.php
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if ($act === 'upload_document') {
        $title = trim($_POST['title']??'');
        $projId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $visibility = $_POST['visibility']==='shared' ? 'shared' : 'private';

        if (!$title) { echo json_encode(['success'=>false,'message'=>'Document title required.']); exit; }
        if (!isset($_FILES['document']) || $_FILES['document']['error']!==0) { echo json_encode(['success'=>false,'message'=>'No file uploaded or upload error.']); exit; }

        $file = $_FILES['document'];
        $allowed = ['application/pdf','image/jpeg','image/png','image/gif','image/webp',
                    'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain'];
        if (!in_array($file['type'], $allowed)) { echo json_encode(['success'=>false,'message'=>'File type not allowed. Accepted: PDF, Images, Word, Excel, Text.']); exit; }
        if ($file['size'] > 10*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large. Max 10MB.']); exit; }

        require_once dirname(__DIR__) . '/includes/cloudinary.php';
        $cloudUrl = uploadToCloudinary($file['tmp_name'], 'documents/' . $userId, 'auto');

        if ($cloudUrl) {
            try {
                $pdo->prepare("INSERT INTO project_documents (user_id,project_id,title,file_name,file_path,file_type,file_size,visibility) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$userId,$projId,$title,$file['name'],$cloudUrl,$file['type'],$file['size'],$visibility]);
                echo json_encode(['success'=>true]);
            } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to upload to Cloudinary.']);
        }
        exit;
    }

    if ($act === 'delete_document') {
        $docId = (int)$_POST['doc_id'];
        try {
            $doc = $pdo->prepare("SELECT file_path FROM project_documents WHERE id=? AND user_id=?");
            $doc->execute([$docId,$userId]); $d=$doc->fetch();
            if ($d) {
                $fp = dirname(__DIR__) . '/' . $d['file_path'];
                if (file_exists($fp)) unlink($fp);
                $pdo->prepare("DELETE FROM project_documents WHERE id=? AND user_id=?")->execute([$docId,$userId]);
            }
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// Fetch documents
try {
    $docs = $pdo->prepare("
        SELECT pd.*, op.title AS project_title
        FROM project_documents pd
        LEFT JOIN ongoing_projects op ON pd.project_id=op.id
        WHERE pd.user_id=?
        ORDER BY pd.created_at DESC
    ");
    $docs->execute([$userId]); $docs=$docs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $docs=[]; }

// User projects for linking
try {
    $projects = $pdo->prepare("SELECT id, title FROM ongoing_projects WHERE user_id=? ORDER BY title");
    $projects->execute([$userId]); $projects=$projects->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $projects=[]; }

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . 'B';
    if ($bytes < 1048576) return round($bytes/1024,1) . 'KB';
    return round($bytes/1048576,1) . 'MB';
}

function fileIcon($type) {
    if (str_contains($type,'pdf')) return ['fas fa-file-pdf','#dc2626'];
    if (str_contains($type,'image')) return ['fas fa-file-image','#7c3aed'];
    if (str_contains($type,'word') || str_contains($type,'document')) return ['fas fa-file-word','#1d4ed8'];
    if (str_contains($type,'excel') || str_contains($type,'sheet')) return ['fas fa-file-excel','#15803d'];
    if (str_contains($type,'text')) return ['fas fa-file-alt','#64748b'];
    return ['fas fa-file','#94a3b8'];
}
?>

<style>
.vault-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.vault-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.doc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; }
@media(max-width:600px) { .doc-grid { grid-template-columns:1fr 1fr; } }
.doc-card { background:white; border-radius:16px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 16px rgba(0,0,0,0.04); padding:1.25rem; transition:all 0.25s; }
.doc-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,0.1); border-color:rgba(10,45,94,0.15); }
.doc-icon-wrap { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:0.85rem; }
.doc-upload-zone { border:2px dashed #bfdbfe; border-radius:16px; padding:2rem; text-align:center; cursor:pointer; transition:all 0.25s; background:#eff6ff; }
.doc-upload-zone:hover { border-color:#0A2D5E; background:#dbeafe; }
</style>

<!-- Hero -->
<div class="vault-hero">
    <div style="position:relative;z-index:1;" class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-folder-open me-1"></i>Document Vault</span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Project Documents</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;"><?=count($docs)?> document<?=count($docs)!=1?'s':''?> stored securely</p>
        </div>
        <button class="btn px-4 py-2 fw-bold" style="background:#E15501;border:none;color:white;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#uploadDocModal"><i class="fas fa-upload me-1"></i>Upload Document</button>
    </div>
</div>

<!-- Info Bar -->
<div class="alert d-flex align-items-center gap-3 mb-4 rounded-3" style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1.5px solid #bae6fd;color:#0c4a6e;">
    <i class="fas fa-shield-alt fa-lg" style="color:#0284c7;"></i>
    <div>
        <strong>Secure & Private</strong> — All documents are encrypted and stored securely. Only you can access private documents. Shared documents can be viewed by the WQS team.
    </div>
</div>

<?php if (empty($docs)): ?>
<!-- Upload Zone CTA -->
<div class="doc-upload-zone mb-4" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
    <div style="font-size:3rem;margin-bottom:1rem;">📁</div>
    <h5 class="fw-bold text-body">No documents yet</h5>
    <p class="text-muted mb-3">Upload project briefs, design specs, contracts, screenshots, or any project-related files.</p>
    <span class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;"><i class="fas fa-upload me-2"></i>Upload Your First Document</span>
</div>
<?php else: ?>
<!-- Documents Grid -->
<div class="doc-grid">
    <?php foreach ($docs as $d):
        [$icon, $iconColor] = fileIcon($d['file_type']??'');
        $bgColor = $iconColor . '18';
    ?>
    <div class="doc-card">
        <div class="doc-icon-wrap" style="background:<?=$bgColor?>;"><i class="<?=$icon?>" style="color:<?=$iconColor?>;"></i></div>
        <h6 class="fw-bold text-body mb-1" style="font-size:0.88rem;" title="<?= htmlspecialchars($d['title']) ?>"><?= htmlspecialchars(strlen($d['title'])>40?substr($d['title'],0,40).'…':$d['title']) ?></h6>
        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($d['file_name']) ?></div>
        <?php if ($d['project_title']): ?><div style="font-size:0.72rem;color:#0A2D5E;font-weight:600;margin-top:0.2rem;"><i class="fas fa-briefcase me-1"></i><?= htmlspecialchars($d['project_title']) ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <span style="font-size:0.68rem;background:<?=$d['visibility']==='shared'?'#dcfce7':'#f1f5f9'?>;color:<?=$d['visibility']==='shared'?'#15803d':'#64748b'?>;padding:0.15rem 0.55rem;border-radius:50px;border:1px solid <?=$d['visibility']==='shared'?'#86efac':'#e2e8f0'?>;font-weight:600;"><?=ucfirst($d['visibility'])?></span>
                <div class="text-muted mt-1" style="font-size:0.68rem;"><?= formatBytes($d['file_size']??0) ?> · <?= date('M d', strtotime($d['created_at'])) ?></div>
            </div>
            <div class="d-flex gap-1">
                <?php
                $fileUrl = $d['file_path'];
                if (!preg_match('/^(https?:)?\/\//i', $fileUrl)) {
                    $fileUrl = $path_to_root . $fileUrl;
                }
                ?>
                <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-sm rounded-circle" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;width:32px;height:32px;display:flex;align-items:center;justify-content:center;" title="Download"><i class="fas fa-download" style="font-size:0.7rem;"></i></a>
                <button class="btn btn-sm rounded-circle" style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;width:32px;height:32px;display:flex;align-items:center;justify-content:center;" title="Delete" onclick="deleteDoc(<?=$d['id']?>)"><i class="fas fa-trash" style="font-size:0.7rem;"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0A2D5E,#163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-upload me-2"></i>Upload Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Document Title *</label>
                    <input type="text" id="doc_title" class="form-control" placeholder="e.g. Project Brief v2, Design Mockup">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">File *</label>
                    <input type="file" id="doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt">
                    <div class="text-muted mt-1" style="font-size:0.75rem;">Accepted: PDF, Images, Word, Excel, Text · Max 10MB</div>
                </div>
                <?php if (!empty($projects)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Link to Project (optional)</label>
                    <select id="doc_project" class="form-select">
                        <option value="">— No specific project —</option>
                        <?php foreach ($projects as $p): ?><option value="<?=$p['id']?>"><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Visibility</label>
                    <select id="doc_vis" class="form-select">
                        <option value="private">🔒 Private (only me)</option>
                        <option value="shared">🌐 Shared (visible to WQS team)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn rounded-pill px-5 fw-bold" style="background:#E15501;border:none;color:white;" onclick="uploadDocument()"><i class="fas fa-upload me-1"></i>Upload</button>
            </div>
        </div>
    </div>
</div>

<script>
function uploadDocument() {
    const title = document.getElementById('doc_title').value.trim();
    const file  = document.getElementById('doc_file').files[0];
    const proj  = document.getElementById('doc_project')?.value||'';
    const vis   = document.getElementById('doc_vis').value;
    if (!title||!file) { Swal.fire({icon:'warning',title:'Required',text:'Title and file are required.',confirmButtonColor:'#0A2D5E'}); return; }
    
    const fd = new FormData();
    fd.append('ajax_action','upload_document');
    fd.append('title',title);
    fd.append('project_id',proj);
    fd.append('visibility',vis);
    fd.append('document',file);
    
    Swal.fire({title:'Uploading...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    fetch('documents.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
        Swal.close();
        if(d.success) Swal.fire({icon:'success',title:'Uploaded!',text:'Document saved to your vault.',confirmButtonColor:'#0A2D5E',timer:2500}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Failed',text:d.message||'Upload failed.',confirmButtonColor:'#dc3545'});
    }).catch(()=>Swal.fire({icon:'error',title:'Error',text:'Network error.',confirmButtonColor:'#dc3545'}));
}

function deleteDoc(id) {
    Swal.fire({title:'Delete Document?',text:'This cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#dc2626'})
    .then(r=>{if(!r.isConfirmed)return;
        const fd=new FormData(); fd.append('ajax_action','delete_document'); fd.append('doc_id',id);
        fetch('documents.php',{method:'POST',body:fd}).then(r=>r.json())
        .then(d=>{
            if(d.success) Swal.fire({icon:'success',title:'Deleted',confirmButtonColor:'#0A2D5E',timer:1800}).then(()=>location.reload());
            else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});
        }).catch(()=>Swal.fire({icon:'error',title:'Error',text:'Network error.',confirmButtonColor:'#dc3545'}));
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
