<?php
$path_to_root = "../";
$page_title = "User Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Only admin can access


// Handle status/role update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean(); // Discard any HTML output from dashboard_header.php
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
            echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
            exit;
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
                // Fetch leadership info if any
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
        $permissions = $_POST['permissions'] ?? ''; // JSON array string
        $is_leader = isset($_POST['is_leader']) && $_POST['is_leader'] == '1';
        $designation = trim($_POST['designation'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $linkedin = trim($_POST['linkedin_url'] ?? '');
        $twitter = trim($_POST['twitter_url'] ?? '');
        $github = trim($_POST['github_url'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);
        
        try {
            $pdo->beginTransaction();
            
            // 1. Update user role and permissions in users table
            $pdo->prepare("UPDATE users SET role = ?, permissions = ? WHERE id = ?")
                ->execute([$role, $permissions ?: null, $uid]);
                
            // 2. Manage leadership team status
            if ($is_leader) {
                $lCheck = $pdo->prepare("SELECT id FROM leadership_team WHERE user_id = ?");
                $lCheck->execute([$uid]);
                if ($lCheck->fetchColumn()) {
                    $pdo->prepare("UPDATE leadership_team SET designation = ?, bio = ?, linkedin_url = ?, twitter_url = ?, github_url = ?, display_order = ? WHERE user_id = ?")
                        ->execute([$designation, $bio, $linkedin, $twitter, $github, $display_order, $uid]);
                } else {
                    $pdo->prepare("INSERT INTO leadership_team (user_id, designation, bio, linkedin_url, twitter_url, github_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$uid, $designation, $bio, $linkedin, $twitter, $github, $display_order]);
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

// Fetch filters
$roleFilter = $_GET['role'] ?? 'all';
$searchQ = trim($_GET['q'] ?? '');
$allowedRoles = ['all', 'user', 'agent', 'developer', 'admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary'];
if (!in_array($roleFilter, $allowedRoles)) $roleFilter = 'all';

$where = [];
$params = [];

if ($roleFilter !== 'all') {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}
if ($searchQ) {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$searchQ%";
    $params[] = "%$searchQ%";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Pagination config
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $limit);

    $stmt = $pdo->prepare("SELECT * FROM users $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $totalRows = 0;
    $totalPages = 0;
}

// Count by role
$roleCounts = [];
try {
    $rc = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    while ($row = $rc->fetch(PDO::FETCH_ASSOC)) {
        $roleCounts[$row['role']] = $row['cnt'];
    }
} catch (Exception $e) {}
?>

<style>
/* Premium User Management Styles */
.um-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%);
    border-radius: 20px; padding: 1.75rem 2rem; color: white;
    position: relative; overflow: hidden; margin-bottom: 1.75rem;
}
.um-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%;
}
.um-hero::after {
    content:''; position:absolute; bottom:-40px; left:-30px;
    width:160px; height:160px; background:rgba(255,255,255,0.05); border-radius:50%;
}
.role-pill {
    font-size: 0.72rem; font-weight: 700; padding: 0.22rem 0.75rem;
    border-radius: 50px; text-transform: uppercase; letter-spacing: 0.04em;
    display: inline-flex; align-items: center; gap: 5px;
}
.role-user     { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.role-agent    { background: #faf5ff; color: #6d28d9; border: 1px solid #c4b5fd; }
.role-developer{ background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; }
.role-admin    { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
.role-manager  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.role-sales    { background: #fdf4ff; color: #a21caf; border: 1px solid #f5d0fe; }
.role-support  { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
.role-finance  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.role-ceo      { background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; }
.role-secretary{ background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

.status-active    { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.status-suspended { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.status-pending   { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }

.um-table-card {
    background: white; border-radius: 18px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
}
.um-table tbody tr { transition: background 0.15s; }
.um-table tbody tr:hover { background:var(--color-bg); }
.um-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    object-fit: cover; border: 2px solid #e2e8f0;
}
.um-avatar-placeholder {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #0A2D5E, #2563eb);
    display: inline-flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 0.85rem;
}
.filter-tab {
    padding: 0.45rem 1.1rem; border-radius: 50px; border: 1px solid #e2e8f0;
    background: white; color: #64748b; font-size: 0.83rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all 0.2s; white-space: nowrap;
}
.filter-tab:hover { border-color: #0A2D5E; color: #0A2D5E; }
.filter-tab.active { background: #0A2D5E; color: white; border-color: #0A2D5E; }
.um-action-btn {
    padding: 0.25rem 0.65rem; border-radius: 6px; border: none;
    font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
@media (min-width: 992px) {
    .um-table-card, .table-responsive {
        overflow: visible !important;
    }
}
.table-responsive {
    min-height: 320px; /* Ensures dropdown has enough space when table has few rows */
}
</style>

<!-- Hero -->
<div class="um-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;">
                <i class="fas fa-users-cog me-1"></i> Admin Control
            </span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">User Management</h1>
        <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">
            View, filter, and manage all <?= array_sum($roleCounts) ?> registered platform users.
        </p>
    </div>
</div>

<!-- Role Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $cardDefs = [
        ['label' => 'All Users', 'role' => 'all', 'icon' => 'fas fa-users', 'bg' => '#eff6ff', 'color' => '#1d4ed8', 'count' => array_sum($roleCounts)],
        ['label' => 'Clients',   'role' => 'user', 'icon' => 'fas fa-user', 'bg' => '#f0fdf4', 'color' => '#15803d', 'count' => $roleCounts['user'] ?? 0],
        ['label' => 'Partners',  'role' => 'agent', 'icon' => 'fas fa-handshake', 'bg' => '#faf5ff', 'color' => '#7c3aed', 'count' => $roleCounts['agent'] ?? 0],
        ['label' => 'Developers','role' => 'developer','icon' => 'fas fa-code', 'bg' => '#f0fdfa', 'color' => '#0d9488', 'count' => $roleCounts['developer'] ?? 0],
        ['label' => 'Managers',  'role' => 'manager', 'icon' => 'fas fa-tasks', 'bg' => '#fffbeb', 'color' => '#b45309', 'count' => $roleCounts['manager'] ?? 0],
        ['label' => 'Sales',     'role' => 'sales', 'icon' => 'fas fa-chart-line', 'bg' => '#fdf4ff', 'color' => '#a21caf', 'count' => $roleCounts['sales'] ?? 0],
        ['label' => 'Support',   'role' => 'support', 'icon' => 'fas fa-headset', 'bg' => '#f0f9ff', 'color' => '#0369a1', 'count' => $roleCounts['support'] ?? 0],
        ['label' => 'Finance',   'role' => 'finance', 'icon' => 'fas fa-file-invoice-dollar', 'bg' => '#ecfdf5', 'color' => '#047857', 'count' => $roleCounts['finance'] ?? 0],
        ['label' => 'CEOs',      'role' => 'ceo', 'icon' => 'fas fa-user-tie', 'bg' => '#fdf2f8', 'color' => '#be185d', 'count' => $roleCounts['ceo'] ?? 0],
        ['label' => 'Secretaries','role' => 'secretary', 'icon' => 'fas fa-concierge-bell', 'bg' => '#f8fafc', 'color' => '#475569', 'count' => $roleCounts['secretary'] ?? 0],
    ];
    foreach ($cardDefs as $cd):
    ?>
    <div class="col-6 col-md-3">
        <a href="manage_users.php?role=<?= $cd['role'] ?>" class="text-decoration-none">
            <div style="background:<?= $cd['bg'] ?>;border:1.5px solid <?= $cd['color'] ?>22;border-radius:14px;padding:1.1rem 1.25rem;transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="width:42px;height:42px;border-radius:12px;background:<?= $cd['color'] ?>;display:flex;align-items:center;justify-content:center;color:white;margin-bottom:0.75rem;">
                    <i class="<?= $cd['icon'] ?>"></i>
                </div>
                <div style="font-size:1.8rem;font-weight:900;color:<?= $cd['color'] ?>;line-height:1;"><?= $cd['count'] ?></div>
                <div style="font-size:0.8rem;font-weight:600;color:<?= $cd['color'] ?>;margin-top:0.2rem;"><?= $cd['label'] ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters & Search -->
<div class="um-table-card mb-0">
    <div class="p-4 border-bottom">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <!-- Role filter tabs -->
            <div class="d-flex gap-2 flex-wrap">
                <?php
                $tabDefs = [
                    ['all', 'All', 'fas fa-globe'],
                    ['user', 'Clients', 'fas fa-user'],
                    ['agent', 'Partners', 'fas fa-handshake'],
                    ['developer', 'Developers', 'fas fa-code'],
                    ['admin', 'Admins', 'fas fa-shield-alt'],
                    ['manager', 'Managers', 'fas fa-tasks'],
                    ['sales', 'Sales', 'fas fa-chart-line'],
                    ['support', 'Support', 'fas fa-headset'],
                    ['finance', 'Finance', 'fas fa-file-invoice-dollar'],
                    ['ceo', 'CEOs', 'fas fa-user-tie'],
                    ['secretary', 'Secretaries', 'fas fa-concierge-bell'],
                ];
                foreach ($tabDefs as [$r, $l, $ic]):
                ?>
                <a href="manage_users.php?role=<?= $r ?>&q=<?= urlencode($searchQ) ?>"
                   class="filter-tab <?= $roleFilter === $r ? 'active' : '' ?>">
                    <i class="<?= $ic ?> me-1"></i><?= $l ?>
                    <span style="opacity:0.65;">(<?= $r === 'all' ? array_sum($roleCounts) : ($roleCounts[$r] ?? 0) ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
            <!-- Search -->
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or email..." value="<?= htmlspecialchars($searchQ) ?>" style="min-width:240px; border-radius:50px; border-color:#e2e8f0;">
                <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill" style="background:#0A2D5E; border-color:#0A2D5E;"><i class="fas fa-search"></i></button>
                <?php if ($searchQ): ?>
                    <a href="manage_users.php?role=<?= $roleFilter ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table align-middle um-table mb-0" style="font-size:0.87rem;">
            <thead class="table-light text-muted fw-bold" style="font-size:0.8rem; border-bottom:2px solid rgba(0,0,0,0.05);">
                <tr>
                    <th class="ps-4 py-3">User</th>
                    <th class="py-3">Email</th>
                    <th class="py-3">Role</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Joined</th>
                    <th class="py-3">Last Login</th>
                    <th class="pe-4 py-3 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-users d-block mb-3 text-secondary" style="font-size:2rem;"></i>
                        No users found matching your criteria.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $u):
                    $statusKey = strtolower($u['status'] ?? 'active');
                    $roleKey = strtolower($u['role'] ?? 'user');
                    $initial = strtoupper(substr($u['name'] ?? 'U', 0, 1));
                ?>
                <tr id="user-row-<?= $u['id'] ?>">
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($u['picture'])): ?>
                                <img src="<?= htmlspecialchars($u['picture']) ?>" alt="<?= htmlspecialchars($u['name']) ?>" class="um-avatar">
                            <?php else: ?>
                                <img src="<?= $path_to_root ?>images/default-avatar.png" alt="<?= htmlspecialchars($u['name']) ?>" class="um-avatar">
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold text-body"><?= htmlspecialchars($u['name']) ?></div>
                                <?php if (!empty($u['phone'])): ?>
                                    <div class="text-muted" style="font-size:0.72rem;"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($u['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="py-3">
                        <div class="dropdown">
                            <span class="role-pill role-<?= $roleKey ?> dropdown-toggle" style="cursor: pointer; transition: all 0.2s;" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="Change Role">
                                <?php
                                $roleIcons = [
                                    'user'=>'fas fa-user', 'agent'=>'fas fa-handshake', 'developer'=>'fas fa-code', 
                                    'admin'=>'fas fa-shield-alt', 'manager'=>'fas fa-tasks', 'sales'=>'fas fa-chart-line', 
                                    'support'=>'fas fa-headset', 'finance'=>'fas fa-file-invoice-dollar',
                                    'ceo'=>'fas fa-user-tie', 'secretary'=>'fas fa-concierge-bell'
                                ];
                                $roleDisplay = [
                                    'user'=>'Client', 'agent'=>'Partner', 'developer'=>'Developer', 'admin'=>'Admin',
                                    'manager'=>'Manager', 'sales'=>'Sales', 'support'=>'Support', 'finance'=>'Finance',
                                    'ceo'=>'CEO', 'secretary'=>'Secretary'
                                ];
                                ?>
                                <i class="<?= $roleIcons[$roleKey] ?? 'fas fa-user' ?>"></i>
                                <?= $roleDisplay[$roleKey] ?? ucfirst($roleKey) ?>
                            </span>
                            <ul class="dropdown-menu shadow-sm" style="font-size:0.8rem; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'user', 'Client')"><i class="fas fa-user me-2 text-primary"></i> Client</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'agent', 'Partner')"><i class="fas fa-handshake me-2" style="color: #6d28d9;"></i> Partner</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'developer', 'Developer')"><i class="fas fa-code me-2 text-info"></i> Developer</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'manager', 'Manager')"><i class="fas fa-tasks me-2" style="color: #b45309;"></i> Manager</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'sales', 'Sales')"><i class="fas fa-chart-line me-2" style="color: #a21caf;"></i> Sales</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'support', 'Support')"><i class="fas fa-headset me-2" style="color: #0369a1;"></i> Support</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'finance', 'Finance')"><i class="fas fa-file-invoice-dollar me-2" style="color: #047857;"></i> Finance</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'ceo', 'CEO')"><i class="fas fa-user-tie me-2" style="color: #be185d;"></i> CEO</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'secretary', 'Secretary')"><i class="fas fa-concierge-bell me-2" style="color: #475569;"></i> Secretary</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeRole(<?= $u['id'] ?>, 'admin', 'Admin')"><i class="fas fa-shield-alt me-2 text-danger"></i> Admin</a></li>
                            </ul>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="role-pill status-<?= $statusKey ?>">
                            <?php if ($statusKey === 'active'): ?><i class="fas fa-circle" style="font-size:0.45rem;"></i><?php endif; ?>
                            <?= ucfirst($statusKey) ?>
                        </span>
                    </td>
                    <td class="py-3 text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td class="py-3 text-muted">
                        <?= !empty($u['last_login']) ? date('M d, Y', strtotime($u['last_login'])) : 'Never' ?>
                    </td>
                    <td class="pe-4 py-3 text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <button class="um-action-btn" style="background:#eff6ff;color:#1d4ed8;"
                                onclick="viewUser(<?= $u['id'] ?>)" title="Manage Role & Settings">
                                <i class="fas fa-user-cog"></i>
                            </button>
                            <?php if ($roleKey !== 'admin'): ?>
                                <?php if ($statusKey === 'active'): ?>
                                    <button class="um-action-btn" style="background:#fef3c7;color:#92400e;"
                                        onclick="toggleStatus(<?= $u['id'] ?>, 'suspended')" title="Suspend User">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="um-action-btn" style="background:#dcfce7;color:#15803d;"
                                        onclick="toggleStatus(<?= $u['id'] ?>, 'active')" title="Activate User">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="um-action-btn" style="background:#fef2f2;color:#dc2626;"
                                    onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')" title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Result count -->
    <div class="px-4 py-3 border-top text-muted d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.8rem;">
        <div>
            Showing <strong><?= count($users) ?></strong> user<?= count($users) != 1 ? 's' : '' ?>
            <?= $searchQ ? ' matching "' . htmlspecialchars($searchQ) . '"' : '' ?>
            <?= $roleFilter !== 'all' ? " with role <strong>$roleFilter</strong>" : '' ?>.
            (Total: <?= $totalRows ?>)
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-1">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link rounded px-2.5 py-1" style="font-size:0.75rem; color:#0A2D5E; border-color:#e2e8f0; background:white;"
                           href="?page=<?= $i ?>&role=<?= urlencode($roleFilter) ?>&q=<?= urlencode($searchQ) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<style>
.pagination .page-item.active .page-link {
    background-color: #0A2D5E !important;
    border-color: #0A2D5E !important;
    color: white !important;
}
.pagination .page-link:hover {
    background-color: #f1f5f9 !important;
}
</style>

<!-- User Detail Modal -->
<div class="modal fade" id="userDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0A2D5E, #163f7a);">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-cog me-2"></i> User Settings & Permissions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="userDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
let userDetailModal;
document.addEventListener("DOMContentLoaded", function() {
    userDetailModal = new bootstrap.Modal(document.getElementById('userDetailModal'));
});

const defaultPermissions = {
    admin: [
        'admin_portfolio', 'admin_requests', 'admin_dev_mgmt', 'admin_services',
        'admin_analytics', 'admin_bot_chats', 'admin_users', 'admin_invoices',
        'admin_support', 'admin_payouts', 'admin_freelance', 'admin_settings'
    ],
    manager: [
        'admin_portfolio', 'admin_requests', 'admin_dev_mgmt', 'admin_services', 'projects', 'dev_hub'
    ],
    sales: [
        'admin_analytics', 'admin_bot_chats', 'admin_requests', 'client_requests', 'partner_hub'
    ],
    support: [
        'admin_support', 'admin_users', 'support_center'
    ],
    finance: [
        'admin_invoices', 'admin_payouts', 'invoices_payments'
    ],
    ceo: [
        'admin_portfolio', 'admin_requests', 'admin_dev_mgmt', 'admin_services',
        'admin_analytics', 'admin_bot_chats', 'admin_users', 'admin_invoices',
        'admin_support', 'admin_payouts', 'admin_freelance', 'admin_settings'
    ],
    secretary: [
        'admin_portfolio', 'admin_requests', 'admin_support', 'admin_settings', 
        'support_center', 'documents_vault'
    ],
    developer: [
        'dev_hub', 'projects', 'support_center', 'documents_vault', 'freelance_board'
    ],
    agent: [
        'projects', 'client_requests', 'invoices_payments', 'support_center', 'documents_vault', 'partner_hub'
    ],
    user: [
        'projects', 'client_requests', 'invoices_payments', 'support_center', 'documents_vault'
    ]
};

const permissionGroups = {
    'Administrative Portals (Admins)': [
        { key: 'admin_users', label: 'User Management' },
        { key: 'admin_portfolio', label: 'Portfolio Management' },
        { key: 'admin_requests', label: 'Client / Agent / Dev Requests' },
        { key: 'admin_dev_mgmt', label: 'Developer Management' },
        { key: 'admin_services', label: 'Services & Pricing Management' },
        { key: 'admin_analytics', label: 'Visitor Analytics Logs' },
        { key: 'admin_bot_chats', label: 'AI Bot Chat Logs' },
        { key: 'admin_invoices', label: 'Invoice & Payment Control' },
        { key: 'admin_support', label: 'Support Ticket Panel' },
        { key: 'admin_payouts', label: 'Payout Approvals' },
        { key: 'admin_freelance', label: 'Freelance Operations' },
        { key: 'admin_settings', label: 'System Reports & Settings' },
    ],
    'Developer Features': [
        { key: 'dev_hub', label: 'Developer Workspace Hub' },
        { key: 'freelance_board', label: 'Job Board & Bidding' }
    ],
    'Client / Partner Features': [
        { key: 'client_requests', label: 'Project Requests Submission' },
        { key: 'invoices_payments', label: 'Invoices & Payment Forms' },
        { key: 'partner_hub', label: 'Partner Commission Hub' }
    ],
    'Shared General Features': [
        { key: 'projects', label: 'Projects Progress Panel' },
        { key: 'support_center', label: 'Support Center (Tickets)' },
        { key: 'documents_vault', label: 'Documents Vault (Shared files)' }
    ]
};

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;")
              .replace(/"/g, "&quot;")
              .replace(/'/g, "&#039;");
}

function toggleLeadershipFields() {
    const isChecked = document.getElementById('edit_is_leader').checked;
    document.getElementById('leadership_fields_wrapper').style.display = isChecked ? 'block' : 'none';
}

function resetToDefaultPermissions() {
    const role = document.getElementById('edit_role').value;
    const defaults = defaultPermissions[role] || [];
    
    document.querySelectorAll('.permission-chk').forEach(chk => {
        chk.checked = defaults.includes(chk.value);
    });
}

function viewUser(uid) {
    document.getElementById('userDetailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    userDetailModal.show();
    fetch('manage_users.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=get_user&user_id=${uid}`
    })
    .then(r => r.json())
    .then(u => {
        if (!u || !u.id) {
            document.getElementById('userDetailBody').innerHTML = '<p class="text-danger">User not found.</p>';
            return;
        }
        
        let currentPermissions = [];
        if (u.permissions && u.permissions.trim() !== '' && u.permissions !== 'null') {
            try {
                currentPermissions = JSON.parse(u.permissions) || [];
            } catch(e) {
                currentPermissions = defaultPermissions[u.role] || [];
            }
        } else {
            currentPermissions = defaultPermissions[u.role] || [];
        }
        
        const isLeader = u.is_leader ? true : false;
        const leader = u.leader_info || {};
        
        const avatar = u.picture
            ? `<img src="${u.picture}" class="rounded-circle mb-2" style="width:70px;height:70px;object-fit:cover;border:2px solid #e2e8f0;">`
            : `<img src="<?= $path_to_root ?>images/default-avatar.png" class="rounded-circle mb-2" style="width:70px;height:70px;object-fit:cover;border:2px solid #e2e8f0;">`;
        
        let html = `
        <div class="row g-4 text-start">
            <!-- Left Column: User details, role, and leadership -->
            <div class="col-md-6 border-end">
                <div class="d-flex align-items-center gap-3 mb-4">
                    ${avatar}
                    <div>
                        <h5 class="fw-bold mb-0 text-body">${escapeHtml(u.name) || '—'}</h5>
                        <p class="text-muted mb-0 small" style="word-break: break-all;">${escapeHtml(u.email) || '—'}</p>
                        <span class="badge bg-secondary-subtle text-secondary small mt-1" style="font-size: 0.72rem;">Joined: ${new Date(u.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
                
                <form id="editUserSettingsForm">
                    <input type="hidden" name="user_id" id="edit_user_id" value="${u.id}">
                    
                    <!-- Role select -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-body small">Platform Role</label>
                        <select class="form-select form-select-sm" name="role" id="edit_role" onchange="resetToDefaultPermissions()">
                            <option value="user" ${u.role === 'user' ? 'selected' : ''}>Client (User)</option>
                            <option value="agent" ${u.role === 'agent' ? 'selected' : ''}>Partner (Agent)</option>
                            <option value="developer" ${u.role === 'developer' ? 'selected' : ''}>Developer</option>
                            <option value="manager" ${u.role === 'manager' ? 'selected' : ''}>Project Manager</option>
                            <option value="sales" ${u.role === 'sales' ? 'selected' : ''}>Sales & Marketing</option>
                            <option value="support" ${u.role === 'support' ? 'selected' : ''}>Customer Support</option>
                            <option value="finance" ${u.role === 'finance' ? 'selected' : ''}>Financial Officer</option>
                            <option value="ceo" ${u.role === 'ceo' ? 'selected' : ''}>CEO / Executive</option>
                            <option value="secretary" ${u.role === 'secretary' ? 'selected' : ''}>Secretary / Admin Asst.</option>
                            <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </div>
                    
                    <!-- Leadership toggle checkbox -->
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_leader" id="edit_is_leader" value="1" ${isLeader ? 'checked' : ''} onchange="toggleLeadershipFields()">
                        <label class="form-check-label fw-bold text-body small" for="edit_is_leader">Is Leadership Team Member?</label>
                    </div>
                    
                    <!-- Collapsible leadership fields -->
                    <div id="leadership_fields_wrapper" style="display: ${isLeader ? 'block' : 'none'}; background:var(--color-bg); padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1rem;">
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold mb-1">Designation</label>
                            <input type="text" class="form-control form-control-sm" name="designation" id="edit_designation" value="${escapeHtml(leader.designation || '')}" placeholder="e.g. CHIEF EXECUTIVE OFFICER">
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold mb-1">Brief Bio</label>
                            <textarea class="form-control form-control-sm" name="bio" id="edit_bio" rows="3" placeholder="Explain expertise and experience...">${escapeHtml(leader.bio || '')}</textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1">LinkedIn URL</label>
                                <input type="text" class="form-control form-control-sm" name="linkedin_url" id="edit_linkedin_url" value="${escapeHtml(leader.linkedin_url || '')}" placeholder="#">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1">Twitter URL</label>
                                <input type="text" class="form-control form-control-sm" name="twitter_url" id="edit_twitter_url" value="${escapeHtml(leader.twitter_url || '')}" placeholder="#">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1">GitHub URL</label>
                                <input type="text" class="form-control form-control-sm" name="github_url" id="edit_github_url" value="${escapeHtml(leader.github_url || '')}" placeholder="#">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1">Display Order</label>
                                <input type="number" class="form-control form-control-sm" name="display_order" id="edit_display_order" value="${leader.display_order || '0'}" min="0">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Right Column: Customized permissions checklist -->
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-body mb-0 small" style="letter-spacing: 0.05em; text-transform: uppercase;">Feature Permissions</h6>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size: 0.72rem; font-weight: 600;" onclick="resetToDefaultPermissions()">
                        <i class="fas fa-undo me-1"></i> Reset to Defaults
                    </button>
                </div>
                
                <div class="permissions-checklist-scroll" style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
        `;
        
        for (const [groupTitle, groupItems] of Object.entries(permissionGroups)) {
            html += `
                <div class="mb-3">
                    <div class="text-muted fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">${groupTitle}</div>
            `;
            for (const item of groupItems) {
                const checked = currentPermissions.includes(item.key) ? 'checked' : '';
                html += `
                    <div class="form-check mb-1">
                        <input class="form-check-input permission-chk" type="checkbox" value="${item.key}" id="chk_perm_${item.key}" ${checked}>
                        <label class="form-check-label text-body small" for="chk_perm_${item.key}" style="font-size: 0.8rem; cursor: pointer;">
                            ${item.label}
                        </label>
                    </div>
                `;
            }
            html += `</div>`;
        }
        
        html += `
                </div>
            </div>
        </div>
        
        <div class="modal-footer border-top-0 px-0 pb-0 pt-3 mt-3 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-sm btn-primary px-3" style="background:#0A2D5E; border-color:#0A2D5E;" onclick="saveUserSettings(${u.id})">Save Settings</button>
        </div>
        `;
        
        document.getElementById('userDetailBody').innerHTML = html;
    })
    .catch(() => {
        document.getElementById('userDetailBody').innerHTML = '<p class="text-danger">Failed to load user data.</p>';
    });
}

function saveUserSettings(uid) {
    const role = document.getElementById('edit_role').value;
    const is_leader = document.getElementById('edit_is_leader').checked ? '1' : '0';
    const designation = document.getElementById('edit_designation') ? document.getElementById('edit_designation').value : '';
    const bio = document.getElementById('edit_bio') ? document.getElementById('edit_bio').value : '';
    const linkedin_url = document.getElementById('edit_linkedin_url') ? document.getElementById('edit_linkedin_url').value : '';
    const twitter_url = document.getElementById('edit_twitter_url') ? document.getElementById('edit_twitter_url').value : '';
    const github_url = document.getElementById('edit_github_url') ? document.getElementById('edit_github_url').value : '';
    const display_order = document.getElementById('edit_display_order') ? document.getElementById('edit_display_order').value : '0';
    
    const permissionsArr = [];
    document.querySelectorAll('.permission-chk:checked').forEach(chk => {
        permissionsArr.push(chk.value);
    });
    
    const permissions = JSON.stringify(permissionsArr);
    
    const params = new URLSearchParams();
    params.append('ajax_action', 'save_user_settings');
    params.append('user_id', uid);
    params.append('role', role);
    params.append('permissions', permissions);
    params.append('is_leader', is_leader);
    params.append('designation', designation);
    params.append('bio', bio);
    params.append('linkedin_url', linkedin_url);
    params.append('twitter_url', twitter_url);
    params.append('github_url', github_url);
    params.append('display_order', display_order);
    
    fetch('manage_users.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved',
                text: 'User settings and permissions updated successfully!',
                confirmButtonColor: '#0A2D5E'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error Saving Settings',
                text: data.message || 'An error occurred.',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: 'Could not connect to the server.',
            confirmButtonColor: '#dc3545'
        });
    });
}

function toggleStatus(uid, newStatus) {
    const actionLabel = newStatus === 'active' ? 'activate' : 'suspend';
    Swal.fire({
        title: `${actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)} User?`,
        text: `This will ${actionLabel} the user account.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Yes, ${actionLabel}`,
        confirmButtonColor: newStatus === 'active' ? '#15803d' : '#d97706',
        cancelButtonColor: '#6c757d'
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch('manage_users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=toggle_status&user_id=${uid}&new_status=${newStatus}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Done!', text: `User has been ${newStatus}.`, confirmButtonColor: '#0A2D5E' })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Action failed.', confirmButtonColor: '#dc3545' });
            }
        });
    });
}

function deleteUser(uid, name) {
    Swal.fire({
        title: `Delete "${name}"?`,
        text: 'This is permanent and cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete Forever',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d'
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch('manage_users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=delete_user&user_id=${uid}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById(`user-row-${uid}`);
                if (row) row.remove();
                Swal.fire({ icon: 'success', title: 'Deleted', text: `${name} has been removed.`, confirmButtonColor: '#0A2D5E', timer: 2500 });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Deletion failed.', confirmButtonColor: '#dc3545' });
            }
        });
    });
}

function quickChangeRole(uid, newRole, roleName) {
    Swal.fire({
        title: `Change role to ${roleName}?`,
        text: `The user will immediately be granted ${roleName} access.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0A2D5E',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, change role'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('manage_users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax_action=quick_change_role&user_id=${uid}&role=${newRole}`
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: 'Role Updated!', showConfirmButton: false, timer: 1500 })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Role change failed.' });
                }
            });
        }
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
