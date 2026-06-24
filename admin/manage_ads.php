<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || strtolower($userRoleObj['role']) !== 'admin') { header("Location: " . $path_to_root . "login.php"); exit; }

$tablesExist = false;
try { if ($pdo->query("SHOW TABLES LIKE 'ads'")->fetch()) $tablesExist = true; } catch (Exception $e) {}

$uploadAbs = __DIR__ . '/../uploads/ads/';
if (!is_dir($uploadAbs)) @mkdir($uploadAbs, 0755, true);

function uploadAdImage($file, $existing = null) {
    global $uploadAbs;
    $allowed = ['jpg','jpeg','png','webp','gif','avif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return $existing;
    $name = 'ad_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadAbs . $name)) {
        if ($existing && file_exists($uploadAbs . basename($existing))) @unlink($uploadAbs . basename($existing));
        return 'uploads/ads/' . $name;
    }
    return $existing;
}

$editingAd = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablesExist) {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['create','edit'])) {
        $id = $action === 'edit' ? (int)$_POST['id'] : null;
        $title = trim($_POST['title'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $button_text = trim($_POST['button_text'] ?? 'Learn More');
        $button_url = trim($_POST['button_url'] ?? '#');
        $primary_color = trim($_POST['primary_color'] ?? '#10b981');
        $secondary_color = trim($_POST['secondary_color'] ?? '#059669');
        $display_type = $_POST['display_type'] ?? 'modal';

        // Multi-placement (checkboxes)
        $placementsArr = $_POST['placements'] ?? [];
        if (empty($placementsArr)) $placementsArr = ['all_pages'];
        $placement = implode(',', $placementsArr);

        $target_audience = $_POST['target_audience'] ?? 'all';
        $device_target = $_POST['device_target'] ?? 'all';

        // Multi-role targeting
        $targetRoles = $_POST['target_roles'] ?? ['all'];
        if (empty($targetRoles)) $targetRoles = ['all'];
        $targetRolesStr = implode(',', $targetRoles);

        $priority = max(1, min(4, (int)($_POST['priority'] ?? 2)));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $run_status = isset($_POST['run_status']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $rotate_ads = isset($_POST['rotate_ads']) ? 1 : 0;
        $show_close_btn = isset($_POST['show_close_btn']) ? 1 : 0;
        $slider_interval = max(2, min(30, (int)($_POST['slider_interval'] ?? 5)));
        $run_forever = isset($_POST['run_forever']) ? 1 : 0;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        if ($run_forever) { $start_date = null; $end_date = null; }
        $max_views_val = !empty($_POST['max_views']) ? (int)$_POST['max_views'] : null;

        // Popup settings (JSON)
        $popupSettings = json_encode([
            'width' => trim($_POST['popup_width'] ?? '480'),
            'height' => trim($_POST['popup_height'] ?? 'auto'),
            'auto_close' => (int)($_POST['popup_auto_close'] ?? 10),
            'show_delay' => (int)($_POST['popup_show_delay'] ?? 1),
            'close_btn' => isset($_POST['popup_close_btn']) ? 1 : 0,
        ]);

        // Floating settings (JSON)
        $floatingSettings = json_encode([
            'position' => $_POST['floating_position'] ?? 'bottom-right',
        ]);

        $image_url = trim($_POST['image_url'] ?? '');
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) $image_url = uploadAdImage($_FILES['image_file'], $image_url);
        $background_image = trim($_POST['background_image'] ?? '');
        if (isset($_FILES['bg_image_file']) && $_FILES['bg_image_file']['error'] === UPLOAD_ERR_OK) $background_image = uploadAdImage($_FILES['bg_image_file'], $background_image);

        if ($title) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO ads (title,headline,subtitle,description,image_url,background_image,button_text,button_url,primary_color,secondary_color,display_type,placement,target_audience,device_target,target_roles,is_active,run_status,featured,rotate_ads,show_close_btn,slider_interval,priority,start_date,end_date,run_forever,max_views,popup_settings,floating_settings,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$title,$headline,$subtitle,$description,$image_url,$background_image,$button_text,$button_url,$primary_color,$secondary_color,$display_type,$placement,$target_audience,$device_target,$targetRolesStr,$is_active,$run_status,$featured,$rotate_ads,$show_close_btn,$slider_interval,$priority,$start_date,$end_date,$run_forever,$max_views_val,$popupSettings,$floatingSettings,$userIdCheck]);
                    $_SESSION['success_message'] = 'Ad created successfully!';
                } else {
                    $stmt = $pdo->prepare("UPDATE ads SET title=?,headline=?,subtitle=?,description=?,image_url=?,background_image=?,button_text=?,button_url=?,primary_color=?,secondary_color=?,display_type=?,placement=?,target_audience=?,device_target=?,target_roles=?,is_active=?,run_status=?,featured=?,rotate_ads=?,show_close_btn=?,slider_interval=?,priority=?,start_date=?,end_date=?,run_forever=?,max_views=?,popup_settings=?,floating_settings=? WHERE id=?");
                    $stmt->execute([$title,$headline,$subtitle,$description,$image_url,$background_image,$button_text,$button_url,$primary_color,$secondary_color,$display_type,$placement,$target_audience,$device_target,$targetRolesStr,$is_active,$run_status,$featured,$rotate_ads,$show_close_btn,$slider_interval,$priority,$start_date,$end_date,$run_forever,$max_views_val,$popupSettings,$floatingSettings,$id]);
                    $_SESSION['success_message'] = 'Ad updated successfully!';
                }
            } catch (Exception $e) { $_SESSION['error_message'] = 'Error: ' . $e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try { $pdo->prepare("DELETE FROM ads WHERE id=?")->execute([$id]); $_SESSION['success_message'] = 'Ad deleted!'; } catch (Exception $e) {}
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId && $tablesExist) {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id=?");
    $stmt->execute([$editId]);
    $editingAd = $stmt->fetch(PDO::FETCH_ASSOC);
}

$ads = [];
if ($tablesExist) {
    try { $ads = $pdo->query("SELECT a.*, u.name AS creator_name FROM ads a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.featured DESC, a.priority ASC, a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
}

$now = date('Y-m-d H:i:s');
function calcStatus($a, $now) {
    if ($a['run_status'] == 0) return 'disabled';
    if ($a['is_active'] == 0) return 'paused';
    if (!$a['run_forever'] && !empty($a['start_date']) && $a['start_date'] > $now) return 'scheduled';
    if (!$a['run_forever'] && !empty($a['end_date']) && $a['end_date'] < $now) return 'expired';
    if (!empty($a['max_views']) && $a['total_views'] >= $a['max_views']) return 'expired';
    return 'running';
}

$stats = ['total'=>0,'running'=>0,'scheduled'=>0,'expired'=>0,'paused'=>0,'disabled'=>0,'total_views'=>0,'total_clicks'=>0];
foreach ($ads as $a) {
    $stats['total']++;
    $stats['total_views'] += (int)$a['total_views'];
    $stats['total_clicks'] += (int)$a['total_clicks'];
    $s = calcStatus($a, $now);
    if (isset($stats[$s])) $stats[$s]++;
}
$stats['avg_ctr'] = $stats['total_views'] > 0 ? round(($stats['total_clicks'] / $stats['total_views']) * 100, 1) : 0;

// Decode existing popup/floating settings
$editPopup = $editingAd ? json_decode($editingAd['popup_settings'] ?? '{}', true) ?? [] : [];
$editFloat = $editingAd ? json_decode($editingAd['floating_settings'] ?? '{}', true) ?? [] : [];
$editPlacements = $editingAd ? explode(',', $editingAd['placement'] ?? 'all_pages') : ['all_pages'];
$editRoles = $editingAd ? explode(',', $editingAd['target_roles'] ?? 'all') : ['all'];

$page_title = "Ad Management";
require_once $path_to_root . 'includes/dashboard_header.php';
?>
<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}

/* ─── Page Layout ─── */
.ad-page-wrap{display:flex;gap:1.5rem;align-items:flex-start}
.ad-form-col{flex:1;min-width:0}
.ad-preview-col{width:360px;flex-shrink:0;position:sticky;top:90px}
@media(max-width:1199.98px){.ad-page-wrap{flex-direction:column}.ad-preview-col{width:100%;position:static}}

/* ─── Section Cards ─── */
.ad-section{background:var(--color-card-bg,#fff);border:1px solid var(--color-border,#e2e8f0);border-radius:16px;margin-bottom:1.25rem;overflow:hidden}
.ad-section-head{padding:1rem 1.25rem;border-bottom:1px solid var(--color-border,#e2e8f0);display:flex;align-items:center;gap:.75rem;cursor:pointer;user-select:none;transition:background .2s}
.ad-section-head:hover{background:var(--color-bg,#f8fafc)}
.ad-section-head .sec-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.ad-section-head h6{margin:0;font-weight:700;font-size:.9rem;flex:1}
.ad-section-head .sec-toggle{font-size:.7rem;color:#94a3b8;transition:transform .3s}
.ad-section-head.collapsed .sec-toggle{transform:rotate(-90deg)}
.ad-section-body{padding:1.25rem;display:block}
.ad-section-body.collapsed{display:none}

/* ─── Form Labels ─── */
.ad-lbl{font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block}
.ad-lbl small{font-weight:400;color:#94a3b8;margin-left:4px}
.ad-input{border:1px solid var(--color-border,#e2e8f0);border-radius:10px;padding:.5rem .75rem;font-size:.88rem;width:100%;transition:border-color .2s,box-shadow .2s;background:var(--color-card-bg,#fff);color:var(--color-text,#1e293b)}
.ad-input:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
select.ad-input{cursor:pointer}

/* ─── Placement Checkbox Cards ─── */
.placement-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.5rem}
.placement-card{position:relative;cursor:pointer}
.placement-card input{position:absolute;opacity:0;width:0;height:0}
.placement-card .pc-inner{padding:.5rem .75rem;border:2px solid var(--color-border,#e2e8f0);border-radius:10px;font-size:.78rem;font-weight:500;transition:all .2s;display:flex;align-items:center;gap:.5rem}
.placement-card input:checked+.pc-inner{border-color:#7c3aed;background:#ede9fe;color:#7c3aed;font-weight:600}
.placement-card:hover .pc-inner{border-color:#a78bfa}
.placement-card .pc-inner i{font-size:.7rem;width:14px;text-align:center}
.placement-group-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin:.5rem 0 .3rem;grid-column:1/-1}

/* ─── Priority Radio ─── */
.priority-options{display:flex;gap:.75rem;flex-wrap:wrap}
.priority-opt{position:relative;cursor:pointer;flex:1;min-width:100px}
.priority-opt input{position:absolute;opacity:0;width:0;height:0}
.priority-opt .po-inner{padding:.6rem 1rem;border:2px solid var(--color-border,#e2e8f0);border-radius:12px;text-align:center;transition:all .2s}
.priority-opt input:checked+.po-inner{font-weight:700}
.priority-opt:hover .po-inner{border-color:#a78bfa}
.priority-opt .po-badge{display:inline-block;padding:2px 10px;border-radius:50px;font-size:.7rem;font-weight:700;margin-bottom:4px}
.priority-opt input:checked+.po-inner .po-badge{color:white}
.po-highest .po-badge{background:#fee2e2;color:#dc2626}
.po-highest input:checked+.po-inner{border-color:#dc2626;background:#fef2f2}
.po-high .po-badge{background:#ffedd5;color:#ea580c}
.po-high input:checked+.po-inner{border-color:#ea580c;background:#fff7ed}
.po-medium .po-badge{background:#dbeafe;color:#1d4ed8}
.po-medium input:checked+.po-inner{border-color:#1d4ed8;background:#eff6ff}
.po-low .po-badge{background:#f1f5f9;color:#64748b}
.po-low input:checked+.po-inner{border-color:#64748b;background:#f8fafc}

/* ─── Toggle Switch ─── */
.ad-toggle{position:relative;width:44px;height:24px;display:inline-block;vertical-align:middle}
.ad-toggle input{opacity:0;width:0;height:0}
.ad-toggle .slider{position:absolute;inset:0;border-radius:12px;cursor:pointer;transition:background .3s;background:#cbd5e1}
.ad-toggle .slider::before{content:'';position:absolute;width:20px;height:20px;border-radius:50%;background:white;top:2px;left:2px;transition:left .3s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.ad-toggle input:checked+.slider{background:#10b981}
.ad-toggle input:checked+.slider::before{left:22px}

/* ─── Role Checkboxes ─── */
.role-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem}
.role-card{position:relative;cursor:pointer}
.role-card input{position:absolute;opacity:0;width:0;height:0}
.role-card .rc-inner{padding:.4rem .7rem;border:2px solid var(--color-border,#e2e8f0);border-radius:10px;font-size:.75rem;font-weight:500;text-align:center;transition:all .2s}
.role-card input:checked+.rc-inner{border-color:#7c3aed;background:#ede9fe;color:#7c3aed;font-weight:600}
.role-card:hover .rc-inner{border-color:#a78bfa}

/* ─── Floating Position Grid ─── */
.float-pos-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;max-width:250px}
.float-pos-opt{position:relative;cursor:pointer}
.float-pos-opt input{position:absolute;opacity:0;width:0;height:0}
.float-pos-opt .fp-inner{padding:.5rem;border:2px solid var(--color-border,#e2e8f0);border-radius:10px;font-size:.75rem;font-weight:500;text-align:center;transition:all .2s}
.float-pos-opt input:checked+.fp-inner{border-color:#7c3aed;background:#ede9fe;color:#7c3aed;font-weight:600}

/* ─── Preview Panel ─── */
.preview-card{background:var(--color-card-bg,#fff);border:1px solid var(--color-border,#e2e8f0);border-radius:16px;overflow:hidden}
.preview-card .preview-head{padding:.75rem 1rem;border-bottom:1px solid var(--color-border,#e2e8f0);font-weight:700;font-size:.85rem;display:flex;align-items:center;gap:.5rem}
.preview-card .preview-body{padding:1rem;min-height:300px}
.preview-ad{border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08)}
.preview-ad img{width:100%;height:auto;display:block}
.preview-ad .pa-body{padding:1rem}
.preview-ad .pa-title{font-weight:700;font-size:.95rem;margin-bottom:.25rem}
.preview-ad .pa-desc{font-size:.8rem;color:#64748b;margin-bottom:.5rem}
.preview-ad .pa-btn{display:inline-block;padding:6px 20px;border-radius:50px;color:white;font-weight:600;font-size:.78rem;text-decoration:none}

/* ─── Stats Cards ─── */
.stat-mini{background:var(--color-card-bg,#fff);border:1px solid var(--color-border,#e2e8f0);border-radius:12px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem}
.stat-mini .sm-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.stat-mini .sm-val{font-size:1.2rem;font-weight:800;line-height:1}
.stat-mini .sm-lbl{font-size:.68rem;color:#64748b}

/* ─── Ad List Table ─── */
.ad-table-wrap{background:var(--color-card-bg,#fff);border:1px solid var(--color-border,#e2e8f0);border-radius:16px;overflow:hidden}
.ad-table{width:100%;border-collapse:collapse}
.ad-table th{padding:.6rem .75rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border,#e2e8f0);background:var(--color-bg,#f8fafc);text-align:left;white-space:nowrap}
.ad-table td{padding:.6rem .75rem;font-size:.82rem;border-bottom:1px solid var(--color-border,#e2e8f0);vertical-align:middle}
.ad-table tr:last-child td{border-bottom:none}
.ad-table tr:hover td{background:var(--color-bg,#f8fafc)}
.ad-table .ad-thumb-sm{width:48px;height:36px;border-radius:6px;object-fit:cover;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#cbd5e1;flex-shrink:0}
.ad-table .ad-thumb-sm img{width:100%;height:100%;object-fit:cover;border-radius:6px}

.status-tag{font-size:.65rem;padding:2px 8px;border-radius:50px;font-weight:700;display:inline-block}
.status-running{background:#dcfce7;color:#15803d}
.status-scheduled{background:#dbeafe;color:#1e40af}
.status-expired{background:#f1f5f9;color:#64748b}
.status-paused{background:#fef3c7;color:#92400e}
.status-disabled{background:#fee2e2;color:#dc2626}

.bulk-floating{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:1000;background:linear-gradient(135deg,#1e40af,#7c3aed);color:white;padding:.75rem 1.5rem;border-radius:16px;display:none;align-items:center;gap:1rem;box-shadow:0 8px 32px rgba(30,64,175,.4);font-size:.85rem}
.bulk-floating.show{display:flex}
@media(max-width:767.98px){.bulk-floating{flex-wrap:wrap;bottom:80px;font-size:.78rem;padding:.6rem 1rem;width:calc(100% - 2rem);justify-content:center}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- ═══ HERO ═══ -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem;position:relative;overflow:hidden">
    <div style="position:absolute;top:-60%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(124,58,237,.15),transparent 70%);border-radius:50%"></div>
    <div class="row align-items-center position-relative" style="z-index:1">
        <div class="col-lg-6 mb-2 mb-lg-0">
            <h4 class="fw-bold mb-1"><i class="fas fa-bullhorn me-2"></i>Ad Management Center</h4>
            <p class="mb-0 opacity-75" style="font-size:.85rem">Create, target, schedule, and track professional advertisements</p>
        </div>
        <div class="col-lg-6 text-lg-end">
            <?php if ($tablesExist): ?>
            <button class="btn btn-light rounded-pill px-4 fw-semibold" onclick="toggleForm()"><i class="fas fa-plus-circle me-2"></i>Create New Ad</button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($tablesExist): ?>
    <div class="row g-2 mt-3 position-relative" style="z-index:1">
        <?php
        $statCards = [
            ['Total','fas fa-layer-group',$stats['total'],'#7c3aed','rgba(124,58,237,.12)'],
            ['Running','fas fa-play-circle',$stats['running'],'#10b981','rgba(16,185,129,.12)'],
            ['Scheduled','fas fa-clock',$stats['scheduled'],'#3b82f6','rgba(59,130,246,.12)'],
            ['Inactive','fas fa-pause-circle',$stats['expired']+$stats['disabled']+$stats['paused'],'#f59e0b','rgba(245,158,11,.12)'],
            ['Views','fas fa-eye',number_format($stats['total_views']),'#06b6d4','rgba(6,182,212,.12)'],
            ['Clicks','fas fa-mouse-pointer',number_format($stats['total_clicks']),'#8b5cf6','rgba(139,92,246,.12)'],
            ['CTR','fas fa-percentage',$stats['avg_ctr'].'%','#ec4899','rgba(236,72,153,.12)'],
        ];
        foreach ($statCards as [$lbl,$icon,$val,$clr,$bg]): ?>
        <div class="col-6 col-md-4 col-lg">
            <div style="background:rgba(255,255,255,.07);border-radius:12px;padding:.6rem .75rem;display:flex;align-items:center;gap:.6rem">
                <div style="width:32px;height:32px;border-radius:8px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;color:<?= $clr ?>;font-size:.8rem"><i class="<?= $icon ?>"></i></div>
                <div><div style="font-size:1.1rem;font-weight:800;line-height:1"><?= $val ?></div><div style="font-size:.62rem;opacity:.7"><?= $lbl ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php unset($_SESSION['success_message']); endif; ?>
<?php if (isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php unset($_SESSION['error_message']); endif; ?>

<?php if ($tablesExist): ?>
<!-- ═══ CREATE/EDIT FORM + PREVIEW ═══ -->
<div class="ad-page-wrap" id="adFormWrap" style="display:<?= $editingAd ? 'flex' : 'none' ?>">

<!-- ═══ LEFT: FORM ═══ -->
<div class="ad-form-col">
<form method="POST" id="adForm" enctype="multipart/form-data">
<input type="hidden" name="action" id="adAction" value="create">
<input type="hidden" name="id" id="adId" value="<?= $editingAd['id'] ?? '' ?>">

<!-- SECTION 1: Ad Information -->
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-info-circle"></i></div>
        <h6>Ad Information</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <!-- Quick Template Loader -->
        <div class="mb-3 p-3 rounded-3" style="background: rgba(124,58,237,0.05); border: 1px dashed rgba(124,58,237,0.25);">
            <label class="ad-lbl text-primary fw-bold mb-2"><i class="fas fa-magic me-1"></i> Quick Load Premium Template</label>
            <div class="d-flex gap-2">
                <select id="templateSelector" class="ad-input" style="flex: 1; border-color: rgba(124,58,237,0.25); background: white;">
                    <option value="">-- Choose a Premium Template --</option>
                    <option value="ai">Enterprise AI Automation Suite (AI Software)</option>
                    <option value="school">Next-Gen School Portal Solutions (School Website)</option>
                    <option value="hospital">Premium Medical & Hospital Platforms (Hospital Website)</option>
                    <option value="banking">Premium Fintech & Banking App (Banking App)</option>
                    <option value="vtu">Vibrant Telecom & VTU Business Suite (VTU Application)</option>
                    <option value="ecommerce">Premium E-commerce Showcase Platforms (E-commerce Website)</option>
                </select>
                <button type="button" class="btn btn-primary rounded-pill px-3" style="background:linear-gradient(135deg,#7c3aed,#a855f7); border:none; font-size:.8rem; font-weight:600; color:white; transition: all 0.2s;" onclick="loadAdTemplate(document.getElementById('templateSelector').value)"><i class="fas fa-file-import me-1"></i> Load</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="ad-lbl">Advertisement Title <span class="text-danger">*</span></label>
                <input type="text" name="title" id="fTitle" class="ad-input" required placeholder="E.g., Summer Sale — 50% Off" value="<?= htmlspecialchars($editingAd['title'] ?? '') ?>" oninput="updatePreview()">
            </div>
            <div class="col-md-4">
                <label class="ad-lbl">Ad Type</label>
                <select name="display_type" id="fDisplayType" class="ad-input" onchange="updatePreview();toggleAdTypeSections()">
                    <option value="modal" <?=($editingAd['display_type']??'')==='modal'?'selected':'' ?>>Modal Popup</option>
                    <option value="hero_banner" <?=($editingAd['display_type']??'')==='hero_banner'?'selected':'' ?>>Hero Banner</option>
                    <option value="top_bar" <?=($editingAd['display_type']??'')==='top_bar'?'selected':'' ?>>Top Bar</option>
                    <option value="bottom_bar" <?=($editingAd['display_type']??'')==='bottom_bar'?'selected':'' ?>>Bottom Bar</option>
                    <option value="side_panel" <?=($editingAd['display_type']??'')==='side_panel'?'selected':'' ?>>Side Panel</option>
                    <option value="inline_banner" <?=($editingAd['display_type']??'')==='inline_banner'?'selected':'' ?>>Inline Banner</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="ad-lbl">Headline</label>
                <input type="text" name="headline" id="fHeadline" class="ad-input" placeholder="Main catchphrase" value="<?= htmlspecialchars($editingAd['headline'] ?? '') ?>" oninput="updatePreview()">
            </div>
            <div class="col-md-6">
                <label class="ad-lbl">Subtitle</label>
                <input type="text" name="subtitle" id="fSubtitle" class="ad-input" placeholder="Supporting text" value="<?= htmlspecialchars($editingAd['subtitle'] ?? '') ?>" oninput="updatePreview()">
            </div>
            <div class="col-12">
                <label class="ad-lbl">Description</label>
                <textarea name="description" id="fDesc" class="ad-input" rows="2" placeholder="Ad description..." oninput="updatePreview()"><?= htmlspecialchars($editingAd['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="ad-lbl">Image URL</label>
                <input type="url" name="image_url" id="fImageUrl" class="ad-input" placeholder="https://example.com/ad.jpg" value="<?= htmlspecialchars($editingAd['image_url'] ?? '') ?>" oninput="updatePreview()">
            </div>
            <div class="col-md-6">
                <label class="ad-lbl">Image Upload</label>
                <input type="file" name="image_file" class="ad-input" accept="image/*" onchange="previewUpload(this,'fImageUrl')">
            </div>
            <div class="col-md-4">
                <label class="ad-lbl">Background Image URL</label>
                <input type="url" name="background_image" id="fBgImage" class="ad-input" value="<?= htmlspecialchars($editingAd['background_image'] ?? '') ?>" oninput="updatePreview()">
            </div>
            <div class="col-md-4">
                <label class="ad-lbl">Button Text</label>
                <input type="text" name="button_text" id="fBtnText" class="ad-input" value="<?= htmlspecialchars($editingAd['button_text'] ?? 'Learn More') ?>" oninput="updatePreview()">
            </div>
            <div class="col-md-4">
                <label class="ad-lbl">Destination URL</label>
                <input type="url" name="button_url" id="fBtnUrl" class="ad-input" value="<?= htmlspecialchars($editingAd['button_url'] ?? '#') ?>">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Primary Color</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="color" name="primary_color" id="fPrimaryColor" class="form-control form-control-color" value="<?= htmlspecialchars($editingAd['primary_color'] ?? '#10b981') ?>" oninput="updatePreview()">
                    <span id="fPrimaryHex" style="font-size:.75rem;color:#64748b"><?= htmlspecialchars($editingAd['primary_color'] ?? '#10b981') ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Secondary Color</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="color" name="secondary_color" id="fSecondaryColor" class="form-control form-control-color" value="<?= htmlspecialchars($editingAd['secondary_color'] ?? '#059669') ?>" oninput="updatePreview()">
                    <span id="fSecondaryHex" style="font-size:.75rem;color:#64748b"><?= htmlspecialchars($editingAd['secondary_color'] ?? '#059669') ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Carousel Interval <small>(sec)</small></label>
                <input type="number" name="slider_interval" class="ad-input" value="<?= $editingAd['slider_interval'] ?? 5 ?>" min="2" max="30">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Options</label>
                <div class="d-flex gap-3 flex-wrap" style="padding-top:4px">
                    <label class="d-flex align-items-center gap-2" style="font-size:.8rem;cursor:pointer"><label class="ad-toggle"><input type="checkbox" name="is_active" <?= $editingAd&&$editingAd['is_active']==0?'':'checked' ?>><span class="slider"></span></label> Active</label>
                    <label class="d-flex align-items-center gap-2" style="font-size:.8rem;cursor:pointer"><label class="ad-toggle"><input type="checkbox" name="run_status" <?= $editingAd&&$editingAd['run_status']==0?'':'checked' ?>><span class="slider"></span></label> Run Ad</label>
                    <label class="d-flex align-items-center gap-2" style="font-size:.8rem;cursor:pointer"><label class="ad-toggle"><input type="checkbox" name="featured" <?= $editingAd&&$editingAd['featured']?'checked':'' ?>><span class="slider"></span></label> Featured</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: Placement -->
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-map-marked-alt"></i></div>
        <h6>Ad Placement</h6>
        <span class="badge rounded-pill px-2" style="background:#dbeafe;color:#1d4ed8;font-size:.65rem" id="placementCount"><?= count($editPlacements) ?> selected</span>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="placement-grid">
            <div class="placement-group-label"><i class="fas fa-home me-1"></i> Homepage</div>
            <?php
            $homepagePlacements = ['homepage_top'=>'Top Banner','homepage_middle'=>'Middle Banner','homepage_bottom'=>'Bottom Banner','homepage_hero'=>'Hero Banner'];
            foreach($homepagePlacements as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>

            <div class="placement-group-label"><i class="fas fa-layer-group me-1"></i> Sidebar</div>
            <?php foreach(['sidebar_top'=>'Top','sidebar_middle'=>'Middle','sidebar_bottom'=>'Bottom'] as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>

            <div class="placement-group-label"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</div>
            <?php foreach(['dashboard_top'=>'Top','dashboard_middle'=>'Middle','dashboard_bottom'=>'Bottom'] as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>

            <div class="placement-group-label"><i class="fas fa-puzzle-piece me-1"></i> Pages</div>
            <?php foreach(['partner_page'=>'Partner Page','developers_hub'=>'Developers Hub','services_page'=>'Services Page','portfolio_page'=>'Portfolio Page'] as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>

            <div class="placement-group-label"><i class="fas fa-mobile-alt me-1"></i> Mobile</div>
            <?php foreach(['mobile_top'=>'Top','mobile_bottom'=>'Bottom'] as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>

            <div class="placement-group-label"><i class="fas fa-star me-1"></i> Special</div>
            <?php foreach(['popup_ad'=>'Popup Advertisement','floating_ad'=>'Floating Advertisement','all_pages'=>'All Pages'] as $k=>$v): ?>
            <label class="placement-card"><input type="checkbox" name="placements[]" value="<?= $k ?>" <?= in_array($k,$editPlacements)?'checked':'' ?> onchange="updatePlacementCount()"><div class="pc-inner"><i class="fas fa-square"></i> <?= $v ?></div></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTION 3: Priority -->
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-sort-amount-up"></i></div>
        <h6>Priority</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="priority-options">
            <?php
            $prioOpts = [
                [1,'Highest','po-highest','#dc2626'],['2','High','po-high','#ea580c'],
                [3,'Medium','po-medium','#1d4ed8'],['4','Low','po-low','#64748b']
            ];
            foreach ($prioOpts as [$val,$lbl,$cls]): ?>
            <label class="priority-opt <?= $cls ?>">
                <input type="radio" name="priority" value="<?= $val ?>" <?= ($editingAd['priority']??2)==$val?'checked':'' ?> onchange="updatePreview()">
                <div class="po-inner"><span class="po-badge"><?= $lbl ?></span><div style="font-size:.7rem;color:#94a3b8">Priority <?= $val ?></div></div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTION 4: Scheduling -->
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-calendar-alt"></i></div>
        <h6>Scheduling</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="d-flex align-items-center gap-2" style="font-size:.85rem;cursor:pointer">
                    <label class="ad-toggle"><input type="checkbox" name="run_forever" id="fRunForever" <?= $editingAd&&$editingAd['run_forever']?'checked':'' ?> onchange="toggleSchedule()"><span class="slider"></span></label>
                    <span>Run Forever <small style="color:#94a3b8">(no start/end date)</small></span>
                </label>
            </div>
            <div class="col-md-5" id="startDateGroup">
                <label class="ad-lbl">Start Date</label>
                <input type="datetime-local" name="start_date" id="fStartDate" class="ad-input" value="<?= $editingAd&&$editingAd['start_date']?str_replace(' ','T',substr($editingAd['start_date'],0,16)):'' ?>">
            </div>
            <div class="col-md-5" id="endDateGroup">
                <label class="ad-lbl">End Date</label>
                <input type="datetime-local" name="end_date" id="fEndDate" class="ad-input" value="<?= $editingAd&&$editingAd['end_date']?str_replace(' ','T',substr($editingAd['end_date'],0,16)):'' ?>">
            </div>
            <div class="col-md-2" id="maxViewsGroup">
                <label class="ad-lbl">Max Views</label>
                <input type="number" name="max_views" class="ad-input" value="<?= $editingAd['max_views'] ?? '' ?>" min="0" placeholder="0=∞">
            </div>
        </div>
    </div>
</div>

<!-- SECTION 5: Popup Settings (conditional) -->
<div class="ad-section" id="popupSettingsSection" style="display:none">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#fce7f3;color:#be185d"><i class="fas fa-window-maximize"></i></div>
        <h6>Popup Settings</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="ad-lbl">Popup Width <small>(px)</small></label>
                <input type="number" name="popup_width" class="ad-input" value="<?= htmlspecialchars($editPopup['width'] ?? '480') ?>" min="280" max="900">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Popup Height</label>
                <input type="text" name="popup_height" class="ad-input" value="<?= htmlspecialchars($editPopup['height'] ?? 'auto') ?>" placeholder="auto or px">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Auto Close <small>(sec)</small></label>
                <input type="number" name="popup_auto_close" class="ad-input" value="<?= $editPopup['auto_close'] ?? 10 ?>" min="3" max="60">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Show Delay <small>(sec)</small></label>
                <input type="number" name="popup_show_delay" class="ad-input" value="<?= $editPopup['show_delay'] ?? 1 ?>" min="0" max="30">
            </div>
            <div class="col-md-3">
                <label class="ad-lbl">Close Button</label>
                <label class="d-flex align-items-center gap-2" style="padding-top:4px">
                    <label class="ad-toggle"><input type="checkbox" name="popup_close_btn" <?= ($editPopup['close_btn']??1)?'checked':'' ?>><span class="slider"></span></label>
                    <span style="font-size:.82rem">Show</span>
                </label>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 6: Floating Settings (conditional) -->
<div class="ad-section" id="floatingSettingsSection" style="display:none">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#e0e7ff;color:#4f46e5"><i class="fas fa-arrows-alt"></i></div>
        <h6>Floating Ad Position</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="float-pos-grid">
            <?php foreach(['top-left'=>'Top Left','top-right'=>'Top Right','bottom-left'=>'Bottom Left','bottom-right'=>'Bottom Right'] as $k=>$v): ?>
            <label class="float-pos-opt"><input type="radio" name="floating_position" value="<?= $k ?>" <?= ($editFloat['position']??'bottom-right')===$k?'checked':'' ?>><div class="fp-inner"><i class="fas fa-arrows-alt me-1"></i><?= $v ?></div></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTION 7: Targeting -->
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-crosshairs"></i></div>
        <h6>Targeting</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ad-lbl">Device Target</label>
                <select name="device_target" class="ad-input">
                    <option value="all" <?=($editingAd['device_target']??'')==='all'?'selected':'' ?>>All Devices</option>
                    <option value="desktop" <?=($editingAd['device_target']??'')==='desktop'?'selected':'' ?>>Desktop</option>
                    <option value="tablet" <?=($editingAd['device_target']??'')==='tablet'?'selected':'' ?>>Tablet</option>
                    <option value="mobile" <?=($editingAd['device_target']??'')==='mobile'?'selected':'' ?>>Mobile</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="ad-lbl">Legacy Audience</label>
                <select name="target_audience" class="ad-input">
                    <option value="all" <?=($editingAd['target_audience']??'')==='all'?'selected':'' ?>>All Users</option>
                    <option value="guests" <?=($editingAd['target_audience']??'')==='guests'?'selected':'' ?>>Guests</option>
                    <option value="users" <?=($editingAd['target_audience']??'')==='users'?'selected':'' ?>>Registered Users</option>
                    <option value="developers" <?=($editingAd['target_audience']??'')==='developers'?'selected':'' ?>>Developers</option>
                    <option value="partners" <?=($editingAd['target_audience']??'')==='partners'?'selected':'' ?>>Partners</option>
                    <option value="agents" <?=($editingAd['target_audience']??'')==='agents'?'selected':'' ?>>Agents</option>
                    <option value="admins" <?=($editingAd['target_audience']??'')==='admins'?'selected':'' ?>>Admins</option>
                </select>
            </div>
            <div class="col-12">
                <label class="ad-lbl">Role Targeting <small>(select which roles see this ad)</small></label>
                <div class="role-grid">
                    <?php
                    $roleOpts = ['all'=>'All Roles','guest'=>'Guest','user'=>'Client','partner'=>'Partner','developer'=>'Developer','admin'=>'Admin'];
                    foreach($roleOpts as $k=>$v): ?>
                    <label class="role-card"><input type="checkbox" name="target_roles[]" value="<?= $k ?>" <?= in_array($k,$editRoles)?'checked':'' ?>><div class="rc-inner"><i class="fas fa-user-tag me-1" style="font-size:.65rem"></i><?= $v ?></div></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 8: Analytics (edit mode only) -->
<?php if ($editingAd): ?>
<?php
$ctr = $editingAd['total_views'] > 0 ? round(($editingAd['total_clicks'] / $editingAd['total_views']) * 100, 1) : 0;
$adStatus = calcStatus($editingAd, $now);
?>
<div class="ad-section">
    <div class="ad-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-chart-bar"></i></div>
        <h6>Analytics</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="ad-section-body">
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="sm-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-eye"></i></div><div><div class="sm-val" style="color:#1d4ed8"><?= number_format($editingAd['total_views']) ?></div><div class="sm-lbl">Views</div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="sm-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-mouse-pointer"></i></div><div><div class="sm-val" style="color:#15803d"><?= number_format($editingAd['total_clicks']) ?></div><div class="sm-lbl">Clicks</div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="sm-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-percentage"></i></div><div><div class="sm-val" style="color:#d97706"><?= $ctr ?>%</div><div class="sm-lbl">CTR</div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="sm-icon" style="background:<?= $adStatus==='running'?'#dcfce7':'#fee2e2' ?>;color:<?= $adStatus==='running'?'#15803d':'#dc2626' ?>"><i class="fas fa-circle"></i></div><div><div class="sm-val" style="font-size:.9rem"><?= ucfirst($adStatus) ?></div><div class="sm-lbl">Status</div></div></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Save Button -->
<div style="padding:1rem 0 2rem;display:flex;gap:.75rem;flex-wrap:wrap">
    <button type="submit" class="btn rounded-pill px-5 fw-semibold" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border:none;font-size:.9rem;box-shadow:0 4px 16px rgba(124,58,237,.3)"><i class="fas fa-save me-2"></i><?= $editingAd ? 'Update Ad' : 'Create Ad' ?></button>
    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="toggleForm()">Cancel</button>
</div>

</form>
</div>

<!-- ═══ RIGHT: LIVE PREVIEW ═══ -->
<div class="ad-preview-col" id="previewCol">
    <div class="preview-card">
        <div class="preview-head"><i class="fas fa-eye" style="color:#7c3aed"></i> Live Preview</div>
        <div class="preview-body" id="previewBody">
            <div class="preview-ad" id="previewAd">
                <div style="height:160px;background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center;color:white" id="previewImgArea"><i class="fas fa-image" style="font-size:2.5rem;opacity:.5"></i></div>
                <div class="pa-body">
                    <div class="pa-title" id="previewTitle">Your Ad Title</div>
                    <div class="pa-desc" id="previewDesc">Your ad description will appear here...</div>
                    <div class="pa-btn" id="previewBtn" style="background:linear-gradient(135deg,#10b981,#059669)">Learn More</div>
                </div>
            </div>
            <div class="mt-3" style="font-size:.72rem;color:#94a3b8">
                <div class="d-flex justify-content-between mb-1"><span>Ad Type:</span><span id="previewType">Modal Popup</span></div>
                <div class="d-flex justify-content-between mb-1"><span>Placement:</span><span id="previewPlacement">—</span></div>
                <div class="d-flex justify-content-between mb-1"><span>Priority:</span><span id="previewPriority">Medium</span></div>
                <div class="d-flex justify-content-between mb-1"><span>Status:</span><span id="previewStatus">Running</span></div>
            </div>
        </div>
    </div>
</div>

</div><!-- /ad-page-wrap -->
<?php endif; ?>

<?php if ($tablesExist): ?>
<!-- ═══ AD LIST ═══ -->
<div class="ad-table-wrap mt-4">
    <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom:1px solid var(--color-border,#e2e8f0)">
        <h6 class="fw-bold mb-0"><i class="fas fa-list me-2 text-primary"></i>All Ads (<?= count($ads) ?>)</h6>
        <div class="d-flex gap-2">
            <input type="search" id="searchInput" class="ad-input" placeholder="Search..." style="max-width:220px;font-size:.82rem" oninput="filterTable()">
            <select id="filterStatus" class="ad-input" style="max-width:150px;font-size:.82rem" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="running">Running</option>
                <option value="scheduled">Scheduled</option>
                <option value="expired">Expired</option>
                <option value="paused">Paused</option>
                <option value="disabled">Disabled</option>
            </select>
        </div>
    </div>
    <div style="overflow-x:auto">
    <table class="ad-table" id="adsTable">
        <thead><tr>
            <th style="width:30px"><input type="checkbox" class="form-check-input" onchange="toggleAll(this)"></th>
            <th>Ad</th><th>Placement</th><th>Priority</th><th>Device</th><th>Status</th><th>Views</th><th>Clicks</th><th>CTR</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($ads)): ?>
        <tr><td colspan="10" class="text-center py-5 text-muted">No ads yet. Create your first ad above.</td></tr>
        <?php else: foreach($ads as $ad):
            $st = calcStatus($ad, $now);
            $cr = $ad['total_views'] > 0 ? round(($ad['total_clicks'] / $ad['total_views']) * 100, 1) : 0;
            $plArr = explode(',', $ad['placement'] ?? 'all_pages');
            $topPlacement = $plArr[0] ?? 'all_pages';
        ?>
        <tr data-id="<?= $ad['id'] ?>" data-status="<?= $st ?>" data-title="<?= strtolower(htmlspecialchars($ad['title'])) ?>">
            <td><input type="checkbox" class="form-check-input ad-cb" value="<?= $ad['id'] ?>" onchange="updateBulkFloating()"></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="ad-thumb-sm">
                        <?php if($ad['image_url']): 
                            $adImgSrc = $ad['image_url'];
                            if ($adImgSrc && !preg_match('/^https?:\/\//i', $adImgSrc) && !preg_match('/^data:/i', $adImgSrc) && !str_starts_with($adImgSrc, '../')) {
                                $adImgSrc = $path_to_root . $adImgSrc;
                            }
                        ?>
                            <img src="<?= htmlspecialchars($adImgSrc) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-ad"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($ad['title']) ?></div>
                        <div style="font-size:.68rem;color:#94a3b8"><?= htmlspecialchars($ad['creator_name'] ?? 'System') ?></div>
                    </div>
                </div>
            </td>
            <td><span style="font-size:.7rem;padding:2px 8px;border-radius:50px;background:#ede9fe;color:#7c3aed;font-weight:600"><?= count($plArr) ?> placement<?= count($plArr)>1?'s':'' ?></span></td>
            <td><span style="font-size:.7rem;padding:2px 8px;border-radius:50px;font-weight:600;color:<?= ['#','#dc2626','#ea580c','#1d4ed8','#64748b'][$ad['priority']] ?? '#64748b' ?>;background:<?= ['#','#fee2e2','#ffedd5','#dbeafe','#f1f5f9'][$ad['priority']] ?? '#f1f5f9' ?>"><?= ['','Highest','High','Medium','Low'][$ad['priority']] ?? 'Medium' ?></span></td>
            <td style="font-size:.75rem"><?= ucfirst($ad['device_target']??'all') ?></td>
            <td><span class="status-tag status-<?= $st ?>"><?= ucfirst($st) ?></span></td>
            <td style="font-size:.78rem"><?= number_format($ad['total_views']) ?></td>
            <td style="font-size:.78rem"><?= number_format($ad['total_clicks']) ?></td>
            <td style="font-size:.78rem"><?= $cr ?>%</td>
            <td>
                <div class="d-flex gap-1">
                    <a href="?edit=<?= $ad['id'] ?>" class="btn btn-sm rounded-pill" style="font-size:.65rem;padding:2px 10px;background:#ede9fe;color:#7c3aed;border:none"><i class="fas fa-pen me-1"></i>Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this ad?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ad['id'] ?>"><button class="btn btn-sm rounded-pill" style="font-size:.65rem;padding:2px 10px;background:#fee2e2;color:#dc2626;border:none"><i class="fas fa-trash"></i></button></form>
                </div>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Bulk Floating Bar -->
<div class="bulk-floating" id="bulkFloat">
    <span id="bulkCount">0 selected</span>
    <button class="btn btn-sm btn-light rounded-pill" onclick="doBulk('enable')"><i class="fas fa-check me-1"></i>Enable</button>
    <button class="btn btn-sm btn-light rounded-pill" onclick="doBulk('disable')"><i class="fas fa-ban me-1"></i>Disable</button>
    <button class="btn btn-sm btn-light rounded-pill" onclick="doBulk('feature')"><i class="fas fa-star me-1"></i>Feature</button>
    <button class="btn btn-sm btn-danger rounded-pill" onclick="doBulk('delete')"><i class="fas fa-trash me-1"></i>Delete</button>
</div>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()"><img id="lightboxImg" src=""></div>
<style>.lightbox-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99999;display:none;align-items:center;justify-content:center;cursor:zoom-out;padding:2rem}.lightbox-overlay.active{display:flex}.lightbox-overlay img{max-width:95vw;max-height:95vh;object-fit:contain;border-radius:8px}</style>
<?php endif; ?>
</div>

<script>
const API = '../api/ad_management_api.php';

/* ─── Toggle Form ─── */
function toggleForm() {
    const w = document.getElementById('adFormWrap');
    if (w.style.display === 'none' || !w.style.display) {
        w.style.display = 'flex';
        w.scrollIntoView({behavior:'smooth',block:'start'});
    } else {
        w.style.display = 'none';
        if (window.location.search.includes('edit=')) window.location.href = window.location.pathname;
    }
}

<?php if ($editingAd): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('adFormWrap').style.display = 'flex';
    document.getElementById('adAction').value = 'edit';
    toggleAdTypeSections();
    toggleSchedule();
    updatePreview();
});
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    toggleAdTypeSections();
    toggleSchedule();
});
<?php endif; ?>

/* ─── Toggle Section ─── */
function toggleSection(head) {
    const body = head.nextElementSibling;
    head.classList.toggle('collapsed');
    body.classList.toggle('collapsed');
}

/* ─── Toggle ad type conditional sections ─── */
function toggleAdTypeSections() {
    const dt = document.getElementById('fDisplayType')?.value;
    document.getElementById('popupSettingsSection').style.display = dt === 'modal' ? '' : 'none';
    document.getElementById('floatingSettingsSection').style.display = dt === 'side_panel' ? '' : 'none';
}

/* ─── Toggle schedule ─── */
function toggleSchedule() {
    const forever = document.getElementById('fRunForever')?.checked;
    document.getElementById('startDateGroup').style.opacity = forever ? '.4' : '1';
    document.getElementById('endDateGroup').style.opacity = forever ? '.4' : '1';
    document.getElementById('startDateGroup').querySelector('input').disabled = forever;
    document.getElementById('endDateGroup').querySelector('input').disabled = forever;
}

/* ─── Placement count ─── */
function updatePlacementCount() {
    const count = document.querySelectorAll('.placement-card input:checked').length;
    document.getElementById('placementCount').textContent = count + ' selected';
}

/* ─── Live Preview ─── */
function updatePreview() {
    const title = document.getElementById('fTitle')?.value || 'Your Ad Title';
    const headline = document.getElementById('fHeadline')?.value;
    const subtitle = document.getElementById('fSubtitle')?.value;
    const desc = document.getElementById('fDesc')?.value || 'Your ad description will appear here...';
    const btnText = document.getElementById('fBtnText')?.value || 'Learn More';
    const pc = document.getElementById('fPrimaryColor')?.value || '#10b981';
    const sc = document.getElementById('fSecondaryColor')?.value || '#059669';
    const imgUrl = document.getElementById('fImageUrl')?.value;
    const dt = document.getElementById('fDisplayType')?.value || 'modal';
    const prio = document.querySelector('input[name="priority"]:checked')?.value || '2';

    document.getElementById('previewTitle').textContent = headline || title;
    document.getElementById('previewDesc').textContent = subtitle || desc;
    document.getElementById('previewBtn').textContent = btnText;
    document.getElementById('previewBtn').style.background = `linear-gradient(135deg,${pc},${sc})`;

    if (imgUrl) {
        let displayImgUrl = imgUrl;
        if (displayImgUrl && !displayImgUrl.startsWith('http') && !displayImgUrl.startsWith('data:') && !displayImgUrl.startsWith('../')) {
            displayImgUrl = '../' + displayImgUrl;
        }
        document.getElementById('previewImgArea').innerHTML = '<img src="' + displayImgUrl + '" style="width:100%;height:160px;object-fit:cover">';
    } else {
        document.getElementById('previewImgArea').innerHTML = '<i class="fas fa-image" style="font-size:2.5rem;opacity:.5"></i>';
        document.getElementById('previewImgArea').style.background = `linear-gradient(135deg,${pc},${sc})`;
    }

    document.getElementById('previewType').textContent = {'modal':'Modal Popup','hero_banner':'Hero Banner','top_bar':'Top Bar','bottom_bar':'Bottom Bar','side_panel':'Side Panel','inline_banner':'Inline Banner'}[dt] || dt;
    document.getElementById('previewPriority').textContent = {'1':'Highest','2':'High','3':'Medium','4':'Low'}[prio] || 'Medium';

    const plCount = document.querySelectorAll('.placement-card input:checked').length;
    document.getElementById('previewPlacement').textContent = plCount + ' placement' + (plCount !== 1 ? 's' : '');

    document.getElementById('fPrimaryHex').textContent = pc;
    document.getElementById('fSecondaryHex').textContent = sc;
}

/* ─── Upload preview ─── */
function previewUpload(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById(targetId).value = e.target.result; updatePreview(); };
        reader.readAsDataURL(input.files[0]);
    }
}

/* ─── Table filter ─── */
function filterTable() {
    const q = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const st = document.getElementById('filterStatus')?.value;
    document.querySelectorAll('#adsTable tbody tr').forEach(tr => {
        const matchQ = !q || (tr.dataset.title || '').includes(q);
        const matchSt = !st || tr.dataset.status === st;
        tr.style.display = matchQ && matchSt ? '' : 'none';
    });
}

/* ─── Bulk actions ─── */
function toggleAll(el) {
    document.querySelectorAll('.ad-cb').forEach(c => c.checked = el.checked);
    updateBulkFloating();
}
function updateBulkFloating() {
    const count = document.querySelectorAll('.ad-cb:checked').length;
    document.getElementById('bulkFloat').classList.toggle('show', count > 0);
    document.getElementById('bulkCount').textContent = count + ' selected';
}
function getSelectedIds() { return Array.from(document.querySelectorAll('.ad-cb:checked')).map(c => c.value); }
function doBulk(action) {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (action === 'delete' && !confirm('Delete ' + ids.length + ' ads?')) return;
    const fd = new FormData();
    fd.append('action', 'bulk_action');
    fd.append('bulk_action', action);
    fd.append('ids', JSON.stringify(ids));
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => { if (d.success) location.reload(); });
}

/* ─── Lightbox ─── */
function openLightbox(el) { const img = el.querySelector('img'); if (!img) return; document.getElementById('lightboxImg').src = img.src; document.getElementById('lightboxOverlay').classList.add('active'); }
function closeLightbox() { document.getElementById('lightboxOverlay').classList.remove('active'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

/* ─── Premium Templates ─── */
const adTemplates = {
    ai: {
        title: 'Enterprise AI Automation Suite',
        headline: 'Empower Your Business With AI Agents',
        subtitle: 'Smarter Workflows. Zero Hassle.',
        description: 'Deploy state-of-the-art AI software custom-tailored for your organization. Automate redundant tasks, extract insights from big data, and integrate intelligent bots into your platform.',
        image_url: 'uploads/ads/ai_ad.png',
        bg_image: '',
        btn_text: 'Request AI Demo',
        btn_url: 'client_requests.php',
        primary_color: '#6366f1',
        secondary_color: '#a855f7',
        display_type: 'modal',
        priority: '2',
        placements: ['homepage_hero', 'popup_ad'],
        target_roles: ['all']
    },
    school: {
        title: 'Next-Gen School Portal Solutions',
        headline: 'Smart Academic Portals & LMS',
        subtitle: 'Streamline education management effortlessly.',
        description: 'Get a premium school website with automated result processing, learning management system (LMS), online fee payment, student/staff portals, and interactive dashboards.',
        image_url: 'uploads/ads/school_ad.png',
        bg_image: '',
        btn_text: 'Get a Free Quote',
        btn_url: 'client_requests.php',
        primary_color: '#059669',
        secondary_color: '#d97706',
        display_type: 'hero_banner',
        priority: '2',
        placements: ['homepage_top', 'services_page'],
        target_roles: ['all']
    },
    hospital: {
        title: 'Premium Medical & Hospital Platforms',
        headline: 'Digitize Your Clinic & Healthcare Portal',
        subtitle: 'Telemedicine, appointment scheduling, and EHR.',
        description: 'Build a highly secure, HIPAA-compliant patient management system. Enable online consultations, EHR synchronization, digital prescriptions, and live doctor chat support.',
        image_url: 'uploads/ads/hospital_ad.png',
        bg_image: '',
        btn_text: 'Schedule Consultation',
        btn_url: 'client_requests.php',
        primary_color: '#0ea5e9',
        secondary_color: '#0d9488',
        display_type: 'side_panel',
        priority: '3',
        placements: ['homepage_middle', 'popup_ad'],
        target_roles: ['all']
    },
    banking: {
        title: 'Premium Fintech & Banking App',
        headline: 'Launch Your FinTech App Today',
        subtitle: 'Secure payments, wallets, and micro-loans.',
        description: 'Experience state-of-the-art fintech software. Equipped with multi-layered encryption, merchant APIs, bank-transfer automation, savings portfolios, and card issuing.',
        image_url: 'uploads/ads/banking_ad.png',
        bg_image: '',
        btn_text: 'Explore Features',
        btn_url: 'client_requests.php',
        primary_color: '#1e1b4b',
        secondary_color: '#ca8a04',
        display_type: 'modal',
        priority: '1',
        placements: ['homepage_hero', 'popup_ad'],
        target_roles: ['all']
    },
    vtu: {
        title: 'Vibrant Telecom & VTU Business Suite',
        headline: 'Start Your VTU & Airtime Reseller Business',
        subtitle: 'Automated airtime, data, and bill payments.',
        description: 'Get a robust VTU application with instant API delivery for all major networks. Features automatic wallet funding, reseller levels, transaction logs, and WhatsApp bot integration.',
        image_url: 'uploads/ads/vtu_ad.png',
        bg_image: '',
        btn_text: 'Launch App Now',
        btn_url: 'client_requests.php',
        primary_color: '#f97316',
        secondary_color: '#6d28d9',
        display_type: 'inline_banner',
        priority: '2',
        placements: ['homepage_bottom', 'dashboard_middle'],
        target_roles: ['all']
    },
    ecommerce: {
        title: 'Premium E-commerce Showcase Platforms',
        headline: 'Build a Luxury Online Marketplace',
        subtitle: 'Scalable multi-vendor stores & inventory system.',
        description: 'High-conversion e-commerce site with advanced checkout flows, payment gateway integration (Paystack/Flutterwave), real-time order tracking, coupons, and vendor panels.',
        image_url: 'uploads/ads/ecommerce_ad.png',
        bg_image: '',
        btn_text: 'Launch Store',
        btn_url: 'client_requests.php',
        primary_color: '#ec4899',
        secondary_color: '#f43f5e',
        display_type: 'modal',
        priority: '2',
        placements: ['homepage_hero', 'popup_ad'],
        target_roles: ['all']
    }
};

function loadAdTemplate(key) {
    if (!key || !adTemplates[key]) return;
    const t = adTemplates[key];
    
    // Fill text inputs
    document.getElementById('fTitle').value = t.title;
    document.getElementById('fHeadline').value = t.headline;
    document.getElementById('fSubtitle').value = t.subtitle;
    document.getElementById('fDesc').value = t.description;
    document.getElementById('fImageUrl').value = t.image_url;
    document.getElementById('fBgImage').value = t.bg_image;
    document.getElementById('fBtnText').value = t.btn_text;
    document.getElementById('fBtnUrl').value = t.btn_url;
    
    // Colors
    document.getElementById('fPrimaryColor').value = t.primary_color;
    document.getElementById('fSecondaryColor').value = t.secondary_color;
    
    // Display Type
    document.getElementById('fDisplayType').value = t.display_type;
    toggleAdTypeSections();
    
    // Priority
    const pRadio = document.querySelector(`input[name="priority"][value="${t.priority}"]`);
    if (pRadio) pRadio.checked = true;
    
    // Placements
    document.querySelectorAll('.placement-card input').forEach(chk => chk.checked = false);
    t.placements.forEach(p => {
        const chk = document.querySelector(`.placement-card input[value="${p}"]`);
        if (chk) chk.checked = true;
    });
    updatePlacementCount();
    
    // Target Roles
    document.querySelectorAll('.role-card input').forEach(chk => chk.checked = false);
    t.target_roles.forEach(r => {
        const chk = document.querySelector(`.role-card input[value="${r}"]`);
        if (chk) chk.checked = true;
    });
    
    // Update live preview
    updatePreview();
}
</script>
<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
