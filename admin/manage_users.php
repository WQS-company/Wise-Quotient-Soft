<?php
$path_to_root = "../";
$page_title = "User Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'suspended';
        try {
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'")->execute([$newStatus, $uid]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$uid]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'quick_change_role') {
        $uid = (int)$_POST['user_id'];
        $newRole = $_POST['role'];
        $allowed = ['user', 'agent', 'developer', 'admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary'];
        if (!in_array($newRole, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role.']); exit;
        }
        try {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'get_user') {
        $uid = (int)$_POST['user_id'];
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at, last_login, picture, permissions FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $lStmt = $pdo->prepare("SELECT designation, bio, linkedin_url, twitter_url, github_url, display_order FROM leadership_team WHERE user_id = ?");
                $lStmt->execute([$uid]);
                $lead = $lStmt->fetch(PDO::FETCH_ASSOC);
                $u['is_leader'] = $lead ? true : false;
                $u['leader_info'] = $lead ?: null;
            }
            echo json_encode($u ?: []);
        } catch (Exception $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'save_user_settings') {
        $uid = (int)$_POST['user_id'];
        $role = $_POST['role'];
        $permissions = $_POST['permissions'] ?? '';
        $is_leader = isset($_POST['is_leader']) && $_POST['is_leader'] == '1';
        $designation = trim($_POST['designation'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $linkedin = trim($_POST['linkedin_url'] ?? '');
        $twitter = trim($_POST['twitter_url'] ?? '');
        $github = trim($_POST['github_url'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET role = ?, permissions = ? WHERE id = ?")->execute([$role, $permissions ?: null, $uid]);
            if ($is_leader) {
                $lCheck = $pdo->prepare("SELECT id FROM leadership_team WHERE user_id = ?");
                $lCheck->execute([$uid]);
                if ($lCheck->fetchColumn()) {
                    $pdo->prepare("UPDATE leadership_team SET designation = ?, bio = ?, linkedin_url = ?, twitter_url = ?, github_url = ?, display_order = ? WHERE user_id = ?")->execute([$designation, $bio, $linkedin, $twitter, $github, $display_order, $uid]);
                } else {
                    $pdo->prepare("INSERT INTO leadership_team (user_id, designation, bio, linkedin_url, twitter_url, github_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$uid, $designation, $bio, $linkedin, $twitter, $github, $display_order]);
                }
            } else {
                $pdo->prepare("DELETE FROM leadership_team WHERE user_id = ?")->execute([$uid]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    exit;
}

$roleFilter = $_GET['role'] ?? 'all';
$searchQ = trim($_GET['q'] ?? '');
$allowedRoles = ['all', 'user', 'agent', 'developer', 'admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary'];
if (!in_array($roleFilter, $allowedRoles)) $roleFilter = 'all';

$where = [];
$params = [];
if ($roleFilter !== 'all') { $where[] = "role = ?"; $params[] = $roleFilter; }
if ($searchQ) { $where[] = "(name LIKE ? OR email LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $limit);

    $stmt = $pdo->prepare("SELECT * FROM users $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $users = []; $totalRows = 0; $totalPages = 0; }

$roleCounts = [];
try {
    $rc = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    while ($row = $rc->fetch(PDO::FETCH_ASSOC)) { $roleCounts[$row['role']] = $row['cnt']; }
} catch (Exception $e) {}

$statusCounts = ['active' => 0, 'suspended' => 0];
try {
    $sc = $pdo->query("SELECT status, COUNT(*) as cnt FROM users GROUP BY status");
    while ($row = $sc->fetch(PDO::FETCH_ASSOC)) { $statusCounts[$row['status']] = $row['cnt']; }
} catch (Exception $e) {}

$newToday = 0;
try {
    $nt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
    $newToday = (int)$nt->fetchColumn();
} catch (Exception $e) {}

$roleIcons = ['user'=>'fas fa-user','agent'=>'fas fa-handshake','developer'=>'fas fa-code','admin'=>'fas fa-shield-alt','manager'=>'fas fa-tasks','sales'=>'fas fa-chart-line','support'=>'fas fa-headset','finance'=>'fas fa-file-invoice-dollar','ceo'=>'fas fa-user-tie','secretary'=>'fas fa-concierge-bell'];
$roleDisplay = ['user'=>'Client','agent'=>'Partner','developer'=>'Developer','admin'=>'Admin','manager'=>'Manager','sales'=>'Sales','support'=>'Support','finance'=>'Finance','ceo'=>'CEO','secretary'=>'Secretary'];
$roleColors = ['user'=>'#1d4ed8','agent'=>'#7c3aed','developer'=>'#0d9488','admin'=>'#dc2626','manager'=>'#b45309','sales'=>'#a21caf','support'=>'#0369a1','finance'=>'#047857','ceo'=>'#be185d','secretary'=>'#475569'];
$roleBgs = ['user'=>'#eff6ff','agent'=>'#faf5ff','developer'=>'#f0fdfa','admin'=>'#fef2f2','manager'=>'#fffbeb','sales'=>'#fdf4ff','support'=>'#f0f9ff','finance'=>'#ecfdf5','ceo'=>'#fdf2f8','secretary'=>'#f8fafc'];
?>

<style>
.um-wrap{font-family:'Inter',system-ui,-apple-system,sans-serif}
.um-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0f4c75 100%);border-radius:24px;padding:2rem 2.5rem;color:#fff;position:relative;overflow:hidden;margin-bottom:2rem}
.um-hero::before{content:'';position:absolute;top:-80px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%);border-radius:50%}
.um-hero::after{content:'';position:absolute;bottom:-50px;left:20%;width:200px;height:200px;background:radial-gradient(circle,rgba(59,130,246,0.15) 0%,transparent 70%);border-radius:50%}
.um-hero .hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);padding:0.35rem 0.9rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:rgba(255,255,255,0.8);margin-bottom:0.75rem}
.um-hero h1{font-size:1.75rem;font-weight:800;margin:0 0 0.3rem;background:linear-gradient(135deg,#fff,#93c5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.um-hero p{color:rgba(255,255,255,0.5);font-size:0.85rem;margin:0}

.um-stat-card{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:16px;padding:1.25rem;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);position:relative;overflow:hidden}
.um-stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,0.08)}
.um-stat-card .stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:0.75rem;transition:transform 0.3s}
.um-stat-card:hover .stat-icon{transform:scale(1.1)}
.um-stat-card .stat-num{font-size:1.8rem;font-weight:900;line-height:1}
.um-stat-card .stat-label{font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:0.2rem}
.um-stat-card::after{content:'';position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;opacity:0.04}

.um-card{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:hidden}
.um-toolbar{padding:1rem 1.5rem;border-bottom:1px solid rgba(0,0,0,0.04);display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between}
.um-search{position:relative}
.um-search input{width:280px;padding:0.55rem 1rem 0.55rem 2.5rem;border:1.5px solid #e2e8f0;border-radius:12px;font-size:0.85rem;background:#f8fafc;transition:all 0.2s;outline:none}
.um-search input:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
.um-search i{position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.85rem}

.um-filter-pills{display:flex;gap:0.4rem;flex-wrap:wrap;padding:0.75rem 1.5rem;border-bottom:1px solid rgba(0,0,0,0.04);overflow-x:auto}
.um-pill{padding:0.4rem 0.9rem;border-radius:50px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-size:0.75rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.um-pill:hover{border-color:#3b82f6;color:#3b82f6}
.um-pill.active{background:#0f172a;color:#fff;border-color:#0f172a}
.um-pill .pill-count{background:rgba(0,0,0,0.08);padding:0.1rem 0.4rem;border-radius:50px;font-size:0.65rem}
.um-pill.active .pill-count{background:rgba(255,255,255,0.2)}

.um-table{width:100%;border-collapse:separate;border-spacing:0}
.um-table thead th{padding:0.85rem 1rem;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#94a3b8;border-bottom:1.5px solid rgba(0,0,0,0.04);background:#f8fafc;white-space:nowrap}
.um-table thead th:first-child{padding-left:1.5rem}
.um-table thead th:last-child{padding-right:1.5rem;text-align:right}
.um-table tbody tr{transition:all 0.15s}
.um-table tbody tr:hover{background:#f8fafc}
.um-table tbody td{padding:0.85rem 1rem;border-bottom:1px solid rgba(0,0,0,0.03);vertical-align:middle;font-size:0.85rem}
.um-table tbody td:first-child{padding-left:1.5rem}
.um-table tbody td:last-child{padding-right:1.5rem}

.um-avatar{width:40px;height:40px;border-radius:12px;object-fit:cover;border:2px solid #e2e8f0;transition:border-color 0.2s}
.um-avatar:hover{border-color:#3b82f6}
.um-avatar-fallback{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:#fff;transition:transform 0.2s}
.um-avatar-fallback:hover{transform:scale(1.05)}

.um-role-badge{display:inline-flex;align-items:center;gap:5px;padding:0.25rem 0.65rem;border-radius:8px;font-size:0.7rem;font-weight:700;cursor:pointer;transition:all 0.2s;border:1.5px solid transparent}
.um-role-badge:hover{box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.um-status-badge{display:inline-flex;align-items:center;gap:5px;padding:0.25rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700}
.um-status-active{background:#dcfce7;color:#15803d}
.um-status-suspended{background:#fef3c7;color:#92400e}

.um-action-btn{width:32px;height:32px;border-radius:8px;border:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s;font-size:0.75rem}
.um-action-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.um-action-btn.primary{background:#eff6ff;color:#1d4ed8}
.um-action-btn.warning{background:#fef3c7;color:#92400e}
.um-action-btn.danger{background:#fef2f2;color:#dc2626}
.um-action-btn.success{background:#dcfce7;color:#15803d}

.um-empty{text-align:center;padding:3rem 1rem}
.um-empty i{font-size:3rem;color:#e2e8f0;margin-bottom:1rem}
.um-empty h5{font-weight:700;color:#334155;margin-bottom:0.5rem}
.um-empty p{color:#94a3b8;font-size:0.85rem}

.um-pagination{display:flex;gap:0.3rem;align-items:center}
.um-pagination a{width:34px;height:34px;border-radius:10px;border:1.5px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:600;color:#64748b;text-decoration:none;transition:all 0.2s;background:#fff}
.um-pagination a:hover{border-color:#3b82f6;color:#3b82f6}
.um-pagination a.active{background:#0f172a;color:#fff;border-color:#0f172a}

.um-footer-bar{padding:0.85rem 1.5rem;border-top:1px solid rgba(0,0,0,0.04);display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;color:#94a3b8}

.um-modal .modal-content{border:none;border-radius:20px;overflow:hidden}
.um-modal .modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(0,0,0,0.04)}
.um-modal .modal-body{padding:0}
.um-modal-tabs{display:flex;border-bottom:1px solid rgba(0,0,0,0.04);padding:0 1.5rem}
.um-modal-tab{padding:0.75rem 1rem;font-size:0.8rem;font-weight:600;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;transition:all 0.2s}
.um-modal-tab:hover{color:#334155}
.um-modal-tab.active{color:#0f172a;border-bottom-color:#3b82f6}
.um-modal-tab-content{padding:1.5rem;display:none}
.um-modal-tab-content.active{display:block}

.um-user-header{display:flex;align-items:center;gap:1rem;padding:1.5rem;background:#f8fafc;border-bottom:1px solid rgba(0,0,0,0.04)}
.um-user-header .uh-avatar{width:56px;height:56px;border-radius:14px;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.um-user-header .uh-avatar-fallback{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.2rem;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.um-user-header h5{font-weight:700;margin:0;font-size:1rem}
.um-user-header .uh-meta{font-size:0.78rem;color:#94a3b8}

.um-form-group{margin-bottom:1rem}
.um-form-group label{display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.4rem}
.um-form-group input,.um-form-group select,.um-form-group textarea{width:100%;padding:0.55rem 0.85rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.85rem;background:#f8fafc;transition:all 0.2s;outline:none}
.um-form-group input:focus,.um-form-group select:focus,.um-form-group textarea:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}

.um-perm-group{margin-bottom:1rem}
.um-perm-group-title{font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;padding:0.4rem 0;border-bottom:1px solid rgba(0,0,0,0.04);margin-bottom:0.5rem}
.um-perm-item{display:flex;align-items:center;gap:0.5rem;padding:0.3rem 0;font-size:0.8rem;color:#334155;cursor:pointer}
.um-perm-item input[type="checkbox"]{accent-color:#3b82f6;width:15px;height:15px}

.um-dropdown{position:relative;display:inline-block}
.um-dropdown-menu{position:absolute;right:0;top:calc(100% + 4px);background:#fff;border:1px solid rgba(0,0,0,0.06);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.12);z-index:1050;min-width:160px;padding:0.4rem;display:none;animation:umDropIn 0.15s ease}
.um-dropdown-menu.show{display:block}
.um-dropdown-menu a{display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;font-size:0.78rem;font-weight:500;color:#334155;border-radius:8px;text-decoration:none;transition:background 0.15s}
.um-dropdown-menu a:hover{background:#f1f5f9}
.um-dropdown-menu hr{margin:0.2rem 0;border-color:rgba(0,0,0,0.06)}
@keyframes umDropIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:768px){
    .um-hero{padding:1.5rem}
    .um-hero h1{font-size:1.3rem}
    .um-toolbar{flex-direction:column;align-items:stretch}
    .um-search input{width:100%}
    .um-table{font-size:0.8rem}
    .um-table thead{display:none}
    .um-table tbody tr{display:block;padding:1rem;border-bottom:1px solid rgba(0,0,0,0.04)}
    .um-table tbody td{display:block;padding:0.25rem 0;border:none;text-align:left}
    .um-table tbody td:first-child{padding-left:0}
    .um-table tbody td:last-child{padding-right:0;padding-top:0.5rem}
    .um-filter-pills{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch}
    .um-footer-bar{flex-direction:column;gap:0.75rem;text-align:center}
}
</style>

<div class="um-wrap">
<div class="um-hero">
    <div style="position:relative;z-index:1">
        <div class="hero-badge"><i class="fas fa-shield-alt"></i> Admin Panel</div>
        <h1>User Management</h1>
        <p>Manage <?= number_format(array_sum($roleCounts)) ?> registered platform users, roles, and permissions</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['label'=>'Total Users','icon'=>'fas fa-users','num'=>array_sum($roleCounts),'color'=>'#3b82f6','bg'=>'#eff6ff'],
        ['label'=>'Active','icon'=>'fas fa-circle-check','num'=>$statusCounts['active']??0,'color'=>'#15803d','bg'=>'#dcfce7'],
        ['label'=>'Suspended','icon'=>'fas fa-ban','num'=>$statusCounts['suspended']??0,'color'=>'#d97706','bg'=>'#fef3c7'],
        ['label'=>'New Today','icon'=>'fas fa-user-plus','num'=>$newToday,'color'=>'#7c3aed','bg'=>'#faf5ff'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="col-6 col-lg-3">
        <div class="um-stat-card">
            <div class="stat-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
            <div class="stat-num" style="color:<?= $s['color'] ?>"><?= number_format($s['num']) ?></div>
            <div class="stat-label" style="color:<?= $s['color'] ?>"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="um-card mb-4">
    <div class="um-toolbar">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-body" style="font-size:0.9rem;">Users</span>
            <span class="badge bg-light text-muted" style="font-size:0.7rem;"><?= number_format($totalRows) ?></span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
                <div class="um-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search by name or email..." value="<?= htmlspecialchars($searchQ) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#0f172a;border-color:#0f172a;border-radius:10px;padding:0.5rem 1rem;"><i class="fas fa-search"></i></button>
                <?php if ($searchQ): ?>
                    <a href="manage_users.php?role=<?= $roleFilter ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="um-filter-pills">
        <?php
        $tabs = [
            ['all','All'],['user','Clients'],['agent','Partners'],['developer','Developers'],['admin','Admins'],
            ['manager','Managers'],['sales','Sales'],['support','Support'],['finance','Finance'],['ceo','CEOs'],['secretary','Secretaries']
        ];
        foreach ($tabs as [$r,$l]):
            $cnt = $r === 'all' ? array_sum($roleCounts) : ($roleCounts[$r] ?? 0);
        ?>
        <a href="manage_users.php?role=<?= $r ?>&q=<?= urlencode($searchQ) ?>" class="um-pill <?= $roleFilter === $r ? 'active' : '' ?>">
            <?= $l ?> <span class="pill-count"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($users)): ?>
    <div class="um-empty">
        <i class="fas fa-users d-block"></i>
        <h5>No users found</h5>
        <p>Try adjusting your search or filter criteria</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="um-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Last Login</th>
                <th style="text-align:right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $rk = strtolower($u['role'] ?? 'user');
                $sk = strtolower($u['status'] ?? 'active');
                $initial = strtoupper(substr($u['name'] ?? 'U', 0, 1));
                $rc2 = $roleColors[$rk] ?? '#64748b';
                $rbg = $roleBgs[$rk] ?? '#f1f5f9';
            ?>
            <tr id="user-row-<?= $u['id'] ?>">
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($u['picture'])): ?>
                            <img src="<?= htmlspecialchars($u['picture']) ?>" alt="" class="um-avatar">
                        <?php else: ?>
                            <div class="um-avatar-fallback" style="background:linear-gradient(135deg,<?= $rc2 ?>,<?= $rc2 ?>dd)"><?= $initial ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;color:#1e293b;"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($u['email']) ?></div>
                            <?php if (!empty($u['phone'])): ?>
                                <div style="font-size:0.7rem;color:#cbd5e1;"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($u['phone']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="um-dropdown">
                        <span class="um-role-badge" style="background:<?= $rbg ?>;color:<?= $rc2 ?>;border-color:<?= $rc2 ?>22;" onclick="this.nextElementSibling.classList.toggle('show')" title="Click to change role">
                            <i class="<?= $roleIcons[$rk] ?? 'fas fa-user' ?>"></i>
                            <?= $roleDisplay[$rk] ?? ucfirst($rk) ?>
                            <i class="fas fa-chevron-down" style="font-size:0.55rem;opacity:0.5;"></i>
                        </span>
                        <div class="um-dropdown-menu">
                            <?php foreach (['user'=>'Client','agent'=>'Partner','developer'=>'Developer','manager'=>'Manager','sales'=>'Sales','support'=>'Support','finance'=>'Finance','ceo'=>'CEO','secretary'=>'Secretary','admin'=>'Admin'] as $rk2=>$rl): ?>
                            <a href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>,'<?= $rk2 ?>','<?= $rl ?>');this.closest('.um-dropdown-menu').classList.remove('show')">
                                <i class="<?= $roleIcons[$rk2] ?? 'fas fa-user' ?>" style="color:<?= $roleColors[$rk2] ?>;width:16px;text-align:center;"></i> <?= $rl ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="um-status-badge um-status-<?= $sk ?>">
                        <?php if ($sk === 'active'): ?><i class="fas fa-circle" style="font-size:0.35rem;"></i><?php endif; ?>
                        <?= ucfirst($sk) ?>
                    </span>
                </td>
                <td style="color:#64748b;font-size:0.8rem;white-space:nowrap;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td style="color:#64748b;font-size:0.8rem;white-space:nowrap;">
                    <?= !empty($u['last_login']) ? date('M d, Y', strtotime($u['last_login'])) : '<span style="color:#cbd5e1;">Never</span>' ?>
                </td>
                <td style="text-align:right">
                    <div class="d-flex gap-1 justify-content-end">
                        <button class="um-action-btn primary" onclick="viewUser(<?= $u['id'] ?>)" title="View & Edit">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($rk !== 'admin'): ?>
                            <?php if ($sk === 'active'): ?>
                                <button class="um-action-btn warning" onclick="toggleStatus(<?= $u['id'] ?>,'suspended')" title="Suspend">
                                    <i class="fas fa-ban"></i>
                                </button>
                            <?php else: ?>
                                <button class="um-action-btn success" onclick="toggleStatus(<?= $u['id'] ?>,'active')" title="Activate">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="um-action-btn danger" onclick="deleteUser(<?= $u['id'] ?>,'<?= htmlspecialchars(addslashes($u['name'])) ?>')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div class="um-footer-bar">
        <div>
            Showing <?= count($users) ?> of <?= number_format($totalRows) ?> user<?= $totalRows != 1 ? 's' : '' ?>
            <?php if ($searchQ): ?> matching "<strong><?= htmlspecialchars($searchQ) ?></strong>"<?php endif; ?>
            <?php if ($roleFilter !== 'all'): ?> with role <strong><?= ucfirst($roleFilter) ?></strong><?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="um-pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&role=<?= urlencode($roleFilter) ?>&q=<?= urlencode($searchQ) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- User Detail Modal -->
<div class="modal fade um-modal" id="userDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#f8fafc;">
                <h6 class="fw-bold mb-0" style="font-size:0.95rem;"><i class="fas fa-user-cog me-2" style="color:#3b82f6;"></i>User Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="userDetailBody">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
let userDetailModal;
document.addEventListener("DOMContentLoaded", function() {
    userDetailModal = new bootstrap.Modal(document.getElementById('userDetailModal'));
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.um-dropdown')) {
            document.querySelectorAll('.um-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });
});

const defaultPermissions = {
    admin:['admin_portfolio','admin_requests','admin_dev_mgmt','admin_services','admin_analytics','admin_bot_chats','admin_users','admin_invoices','admin_support','admin_payouts','admin_freelance','admin_settings'],
    manager:['admin_portfolio','admin_requests','admin_dev_mgmt','admin_services','projects','dev_hub'],
    sales:['admin_analytics','admin_bot_chats','admin_requests','client_requests','partner_hub'],
    support:['admin_support','admin_users','support_center'],
    finance:['admin_invoices','admin_payouts','invoices_payments'],
    ceo:['admin_portfolio','admin_requests','admin_dev_mgmt','admin_services','admin_analytics','admin_bot_chats','admin_users','admin_invoices','admin_support','admin_payouts','admin_freelance','admin_settings'],
    secretary:['admin_portfolio','admin_requests','admin_support','admin_settings','support_center','documents_vault'],
    developer:['dev_hub','projects','support_center','documents_vault','freelance_board'],
    agent:['projects','client_requests','invoices_payments','support_center','documents_vault','partner_hub'],
    user:['projects','client_requests','invoices_payments','support_center','documents_vault']
};

const permissionGroups = {
    'Administrative Portals':['admin_users','admin_portfolio','admin_requests','admin_dev_mgmt','admin_services','admin_analytics','admin_bot_chats','admin_invoices','admin_support','admin_payouts','admin_freelance','admin_settings'],
    'Developer Features':['dev_hub','freelance_board'],
    'Client / Partner Features':['client_requests','invoices_payments','partner_hub'],
    'Shared Features':['projects','support_center','documents_vault']
};

const permLabels = {
    admin_users:'User Management',admin_portfolio:'Portfolio',admin_requests:'Requests',admin_dev_mgmt:'Dev Management',
    admin_services:'Services & Pricing',admin_analytics:'Analytics',admin_bot_chats:'Bot Chat Logs',admin_invoices:'Invoices',
    admin_support:'Support',admin_payouts:'Payouts',admin_freelance:'Freelance',admin_settings:'Settings',
    dev_hub:'Developer Hub',freelance_board:'Job Board',client_requests:'Project Requests',invoices_payments:'Payments',
    partner_hub:'Partner Hub',projects:'Projects',support_center:'Support',documents_vault:'Documents'
};

const roleColors = {user:'#1d4ed8',agent:'#7c3aed',developer:'#0d9488',admin:'#dc2626',manager:'#b45309',sales:'#a21caf',support:'#0369a1',finance:'#047857',ceo:'#be185d',secretary:'#475569'};
const roleBgs = {user:'#eff6ff',agent:'#faf5ff',developer:'#f0fdfa',admin:'#fef2f2',manager:'#fffbeb',sales:'#fdf4ff',support:'#f0f9ff',finance:'#ecfdf5',ceo:'#fdf2f8',secretary:'#f8fafc'};
const roleIcons = {user:'fas fa-user',agent:'fas fa-handshake',developer:'fas fa-code',admin:'fas fa-shield-alt',manager:'fas fa-tasks',sales:'fas fa-chart-line',support:'fas fa-headset',finance:'fas fa-file-invoice-dollar',ceo:'fas fa-user-tie',secretary:'fas fa-concierge-bell'};
const roleDisplay = {user:'Client',agent:'Partner',developer:'Developer',admin:'Admin',manager:'Manager',sales:'Sales',support:'Support',finance:'Finance',ceo:'CEO',secretary:'Secretary'};

function esc(s){if(!s)return'';return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")}

function viewUser(uid){
    document.getElementById('userDetailBody').innerHTML='<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
    userDetailModal.show();
    fetch('manage_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=get_user&user_id=${uid}`})
    .then(r=>r.json()).then(u=>{
        if(!u||!u.id){document.getElementById('userDetailBody').innerHTML='<div class="text-center py-4 text-danger">User not found.</div>';return;}
        let perms=[];
        if(u.permissions&&u.permissions.trim()&&u.permissions!=='null'){try{perms=JSON.parse(u.permissions)||[]}catch(e){perms=defaultPermissions[u.role]||[]}}else{perms=defaultPermissions[u.role]||[]}
        const rk=u.role||'user';
        const rc=roleColors[rk]||'#64748b';
        const rb=roleBgs[rk]||'#f1f5f9';
        const initial=(u.name||'U').charAt(0).toUpperCase();
        const avatar=u.picture?`<img src="${u.picture}" class="uh-avatar">`:`<div class="uh-avatar-fallback" style="background:linear-gradient(135deg,${rc},${rc}dd)">${initial}</div>`;
        const statusCls=u.status==='active'?'um-status-active':'um-status-suspended';
        const statusTxt=u.status==='active'?'Active':'Suspended';

        let html=`
        <div class="um-user-header">
            ${avatar}
            <div>
                <h5>${esc(u.name)}</h5>
                <div class="uh-meta">${esc(u.email)}</div>
                <div class="d-flex gap-2 mt-2">
                    <span class="um-role-badge" style="background:${rb};color:${rc};border-color:${rc}22;font-size:0.7rem;"><i class="${roleIcons[rk]}"></i> ${roleDisplay[rk]||rk}</span>
                    <span class="um-status-badge ${statusCls}" style="font-size:0.7rem;"><i class="fas fa-circle" style="font-size:0.35rem;"></i> ${statusTxt}</span>
                    <span style="font-size:0.7rem;color:#94a3b8;"><i class="fas fa-clock me-1"></i>Joined ${new Date(u.created_at).toLocaleDateString()}</span>
                </div>
            </div>
        </div>

        <div class="um-modal-tabs">
            <div class="um-modal-tab active" onclick="switchTab(this,'tab-profile')">Profile</div>
            <div class="um-modal-tab" onclick="switchTab(this,'tab-permissions')">Permissions</div>
            <div class="um-modal-tab" onclick="switchTab(this,'tab-leadership')">Leadership</div>
        </div>

        <form id="editUserForm">
        <input type="hidden" name="user_id" value="${u.id}">

        <div class="um-modal-tab-content active" id="tab-profile">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="um-form-group"><label>Full Name</label><input type="text" value="${esc(u.name)}" disabled style="opacity:0.7;"></div>
                </div>
                <div class="col-md-6">
                    <div class="um-form-group"><label>Email</label><input type="email" value="${esc(u.email)}" disabled style="opacity:0.7;"></div>
                </div>
                <div class="col-md-6">
                    <div class="um-form-group"><label>Phone</label><input type="text" value="${esc(u.phone||'')}" disabled style="opacity:0.7;"></div>
                </div>
                <div class="col-md-6">
                    <div class="um-form-group">
                        <label>Role</label>
                        <select name="role" id="edit_role" onchange="resetPerms()">
                            ${Object.entries(roleDisplay).map(([k,v])=>`<option value="${k}" ${u.role===k?'selected':''}>${v}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <div style="padding:0.75rem 1rem;background:#f8fafc;border-radius:10px;font-size:0.8rem;color:#64748b;">
                        <i class="fas fa-info-circle me-1"></i> Last login: ${u.last_login?new Date(u.last_login).toLocaleString():'Never'}
                    </div>
                </div>
            </div>
        </div>

        <div class="um-modal-tab-content" id="tab-permissions">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span style="font-size:0.75rem;color:#94a3b8;">Select features this user can access</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:0.7rem;border-radius:8px;padding:0.3rem 0.6rem;" onclick="resetPerms()"><i class="fas fa-undo me-1"></i>Reset</button>
            </div>
            ${Object.entries(permissionGroups).map(([group,keys])=>`
                <div class="um-perm-group">
                    <div class="um-perm-group-title">${group}</div>
                    ${keys.map(k=>`
                        <label class="um-perm-item">
                            <input type="checkbox" class="perm-chk" value="${k}" ${perms.includes(k)?'checked':''}>
                            ${permLabels[k]||k}
                        </label>
                    `).join('')}
                </div>
            `).join('')}
        </div>

        <div class="um-modal-tab-content" id="tab-leadership">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="is_leader" id="edit_is_leader" value="1" ${u.is_leader?'checked':''} onchange="document.getElementById('leaderFields').style.display=this.checked?'block':'none'">
                <label class="form-check-label fw-bold small" for="edit_is_leader">Leadership Team Member</label>
            </div>
            <div id="leaderFields" style="display:${u.is_leader?'block':'none'}">
                <div class="row g-3">
                    <div class="col-md-6"><div class="um-form-group"><label>Designation</label><input type="text" name="designation" value="${esc((u.leader_info||{}).designation||'')}" placeholder="e.g. Chief Executive Officer"></div></div>
                    <div class="col-md-6"><div class="um-form-group"><label>Display Order</label><input type="number" name="display_order" value="${(u.leader_info||{}).display_order||0}" min="0"></div></div>
                    <div class="col-12"><div class="um-form-group"><label>Bio</label><textarea name="bio" rows="3" placeholder="Brief bio...">${esc((u.leader_info||{}).bio||'')}</textarea></div></div>
                    <div class="col-md-4"><div class="um-form-group"><label>LinkedIn</label><input type="text" name="linkedin_url" value="${esc((u.leader_info||{}).linkedin_url||'')}" placeholder="#"></div></div>
                    <div class="col-md-4"><div class="um-form-group"><label>Twitter</label><input type="text" name="twitter_url" value="${esc((u.leader_info||{}).twitter_url||'')}" placeholder="#"></div></div>
                    <div class="col-md-4"><div class="um-form-group"><label>GitHub</label><input type="text" name="github_url" value="${esc((u.leader_info||{}).github_url||'')}" placeholder="#"></div></div>
                </div>
            </div>
        </div>

        <div style="padding:1rem 1.5rem;border-top:1px solid rgba(0,0,0,0.04);display:flex;justify-content:flex-end;gap:0.5rem;">
            <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;padding:0.5rem 1.2rem;" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-primary" style="background:#0f172a;border-color:#0f172a;border-radius:10px;padding:0.5rem 1.2rem;" onclick="saveUser(${u.id})">Save Changes</button>
        </div>
        </form>`;
        document.getElementById('userDetailBody').innerHTML=html;
    }).catch(()=>{document.getElementById('userDetailBody').innerHTML='<div class="text-center py-4 text-danger">Failed to load.</div>'});
}

function switchTab(el,id){
    el.closest('.um-modal').querySelectorAll('.um-modal-tab').forEach(t=>t.classList.remove('active'));
    el.classList.add('active');
    el.closest('.modal-content').querySelectorAll('.um-modal-tab-content').forEach(c=>c.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}

function resetPerms(){
    const role=document.getElementById('edit_role').value;
    const defs=defaultPermissions[role]||[];
    document.querySelectorAll('.perm-chk').forEach(c=>{c.checked=defs.includes(c.value)});
}

function saveUser(uid){
    const role=document.getElementById('edit_role').value;
    const is_leader=document.getElementById('edit_is_leader')?.checked?'1':'0';
    const p=new URLSearchParams();
    p.append('ajax_action','save_user_settings');
    p.append('user_id',uid);
    p.append('role',role);
    p.append('is_leader',is_leader);
    const perms=[];
    document.querySelectorAll('.perm-chk:checked').forEach(c=>perms.push(c.value));
    p.append('permissions',JSON.stringify(perms));
    ['designation','bio','linkedin_url','twitter_url','github_url','display_order'].forEach(f=>{
        const el=document.querySelector(`[name="${f}"]`);
        if(el)p.append(f,el.value);
    });
    fetch('manage_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()})
    .then(r=>r.json()).then(d=>{
        if(d.success){Swal.fire({icon:'success',title:'Saved!',text:'User settings updated.',confirmButtonColor:'#0f172a'}).then(()=>location.reload())}
        else{Swal.fire({icon:'error',title:'Error',text:d.message||'Failed.',confirmButtonColor:'#dc3545'})}
    }).catch(()=>{Swal.fire({icon:'error',title:'Failed',text:'Network error.',confirmButtonColor:'#dc3545'})});
}

function toggleStatus(uid,newStatus){
    const label=newStatus==='active'?'activate':'suspend';
    Swal.fire({title:`${label.charAt(0).toUpperCase()+label.slice(1)} user?`,text:`This will ${label} the account.`,icon:'question',showCancelButton:true,confirmButtonText:`Yes, ${label}`,confirmButtonColor:newStatus==='active'?'#15803d':'#d97706',cancelButtonColor:'#6c757d'}).then(r=>{
        if(!r.isConfirmed)return;
        fetch('manage_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=toggle_status&user_id=${uid}&new_status=${newStatus}`})
        .then(r=>r.json()).then(d=>{
            if(d.success){Swal.fire({icon:'success',title:'Done!',text:`User has been ${newStatus}.`,confirmButtonColor:'#0f172a'}).then(()=>location.reload())}
            else{Swal.fire({icon:'error',title:'Failed',text:d.message||'Error.',confirmButtonColor:'#dc3545'})}
        });
    });
}

function deleteUser(uid,name){
    Swal.fire({title:`Delete "${name}"?`,text:'This action cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete Forever',confirmButtonColor:'#dc2626',cancelButtonColor:'#6c757d'}).then(r=>{
        if(!r.isConfirmed)return;
        fetch('manage_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=delete_user&user_id=${uid}`})
        .then(r=>r.json()).then(d=>{
            if(d.success){document.getElementById(`user-row-${uid}`)?.remove();Swal.fire({icon:'success',title:'Deleted',text:`${name} removed.`,confirmButtonColor:'#0f172a',timer:2000})}
            else{Swal.fire({icon:'error',title:'Failed',text:d.message||'Error.',confirmButtonColor:'#dc3545'})}
        });
    });
}

function quickChangeRole(uid,newRole,roleName){
    Swal.fire({title:`Change role to ${roleName}?`,text:'The user will immediately get new access.',icon:'question',showCancelButton:true,confirmButtonColor:'#0f172a',cancelButtonColor:'#6c757d',confirmButtonText:'Yes, change'}).then(r=>{
        if(!r.isConfirmed)return;
        fetch('manage_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=quick_change_role&user_id=${uid}&role=${newRole}`})
        .then(r=>r.json()).then(d=>{
            if(d.success){Swal.fire({icon:'success',title:'Role Updated!',showConfirmButton:false,timer:1500}).then(()=>location.reload())}
            else{Swal.fire({icon:'error',title:'Failed',text:d.message||'Error.'})}
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>