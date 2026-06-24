<?php
$path_to_root = "../";
$page_title = "Team Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

if ($user_role !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if ($act === 'add_member') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $designation = trim($_POST['designation'] ?? '');
        if (!$uid || !$designation) {
            echo json_encode(['success' => false, 'message' => 'Select a user and enter a designation.']);
            exit;
        }
        try {
            // Check if already a team member
            $stmt = $pdo->prepare("SELECT id FROM team_members WHERE user_id=?");
            $stmt->execute([$uid]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This user is already a team member.']);
                exit;
            }
            $maxOrder = $pdo->query("SELECT COALESCE(MAX(display_order),0)+1 FROM team_members")->fetchColumn();
            $pdo->prepare("INSERT INTO team_members (user_id,designation,display_order,is_active) VALUES (?,?,?,1)")->execute([$uid, $designation, $maxOrder]);
            echo json_encode(['success' => true, 'message' => 'Team member added successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($act === 'remove_member') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($act === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['is_active'] ?? 0);
        try {
            $pdo->prepare("UPDATE team_members SET is_active=? WHERE id=?")->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($act === 'update_designation') {
        $id = (int)($_POST['id'] ?? 0);
        $designation = trim($_POST['designation'] ?? '');
        if (!$designation) {
            echo json_encode(['success' => false, 'message' => 'Designation required.']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE team_members SET designation=? WHERE id=?")->execute([$designation, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($act === 'reorder') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $tid) {
                $order = $i + 1;
                $pdo->prepare("UPDATE team_members SET display_order=? WHERE id=?")->execute([$order, (int)$tid]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Fetch team members with user data
$team = [];
try {
    $team = $pdo->query("
        SELECT tm.*, u.name, u.email, u.phone, u.picture, u.role,
               u.linkedin_url, u.twitter_url, u.github_url, u.facebook_url, u.instagram_url, u.website_url
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        ORDER BY tm.display_order ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Fetch non-team users for the add dropdown
$otherUsers = [];
try {
    $otherUsers = $pdo->query("
        SELECT id, name, email, role FROM users
        WHERE id NOT IN (SELECT user_id FROM team_members)
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<style>
    .team-member-card {
        background: white;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        overflow: hidden;
        transition: all 0.2s;
    }
    .team-member-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }
    .team-member-card.inactive {
        opacity: 0.5;
    }
    .team-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e2e8f0;
    }
    .team-avatar-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0A2D5E, #2563eb);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
        font-weight: 700;
        border: 3px solid #e2e8f0;
    }
    .order-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
        font-size: 0.8rem;
    }
    .order-btn:hover {
        background: #f1f5f9;
        color: #0A2D5E;
    }
    .social-icon-mini {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        transition: all 0.2s;
        text-decoration: none;
    }
    .social-icon-mini:hover {
        transform: translateY(-1px);
    }
    .add-panel {
        background: #f8fafc;
        border: 1.5px dashed #cbd5e1;
        border-radius: 16px;
        padding: 1.5rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-body mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Team Management</h4>
        <p class="text-muted mb-0" style="font-size:0.85rem;">Manage team members displayed on the public Team page. Members appear in order shown below.</p>
    </div>
    <button class="btn rounded-pill px-4 fw-bold text-white" style="background:#0A2D5E;" onclick="showAddPanel()">
        <i class="fas fa-plus me-1"></i>Add Member
    </button>
</div>

<!-- Add Member Panel -->
<div id="addPanel" class="add-panel mb-4" style="display:none;">
    <h6 class="fw-bold mb-3"><i class="fas fa-user-plus text-success me-2"></i>Add New Team Member</h6>
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Select User</label>
            <select id="newUserId" class="form-select">
                <option value="">— Choose a user —</option>
                <?php foreach ($otherUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Designation / Title</label>
            <input type="text" id="newDesignation" class="form-control" placeholder="e.g. Chief Technology Officer">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn rounded-pill px-4 fw-bold text-white" style="background:#0A2D5E;" onclick="addMember()">
                <i class="fas fa-check me-1"></i>Add
            </button>
            <button class="btn btn-outline-secondary rounded-pill" onclick="hideAddPanel()">Cancel</button>
        </div>
    </div>
    <?php if (empty($otherUsers)): ?>
        <div class="text-muted small mt-2">All users are already on the team.</div>
    <?php endif; ?>
</div>

<!-- Current Team Members -->
<?php if (empty($team)): ?>
    <div class="text-center py-5 text-muted">
        <div style="font-size:3.5rem;margin-bottom:1rem;">👥</div>
        <h5>No team members yet</h5>
        <p>Add users to build your team. They'll appear on the public Team page.</p>
        <button class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;" onclick="showAddPanel()">
            <i class="fas fa-plus me-2"></i>Add Your First Member
        </button>
    </div>
<?php else: ?>
    <div id="teamList">
        <?php foreach ($team as $i => $m): ?>
            <div class="team-member-card mb-3" data-id="<?= $m['id'] ?>" id="member-<?= $m['id'] ?>">
                <div class="p-3">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Order controls -->
                        <div class="d-flex flex-column gap-1">
                            <button class="order-btn" onclick="moveUp(this)" title="Move up" <?= $i === 0 ? 'disabled style="opacity:0.3;"' : '' ?>>
                                <i class="fas fa-chevron-up"></i>
                            </button>
                            <span class="text-center small fw-bold text-muted" style="font-size:0.7rem;"><?= $m['display_order'] ?></span>
                            <button class="order-btn" onclick="moveDown(this)" title="Move down" <?= $i === count($team)-1 ? 'disabled style="opacity:0.3;"' : '' ?>>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>

                        <!-- Avatar -->
                        <?php if (!empty($m['picture'])): ?>
                            <img src="<?= htmlspecialchars($m['picture']) ?>" class="team-avatar" alt="">
                        <?php else: ?>
                            <div class="team-avatar-placeholder">
                                <?= strtoupper(substr($m['name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Info -->
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold mb-0"><?= htmlspecialchars($m['name']) ?></h6>
                                <span class="badge rounded-pill" style="background:#e2e8f0;color:#475569;font-size:0.65rem;">
                                    <?= htmlspecialchars($m['role']) ?>
                                </span>
                                <?php if (!$m['is_active']): ?>
                                    <span class="badge rounded-pill bg-warning text-dark" style="font-size:0.65rem;">Hidden</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span id="desig-<?= $m['id'] ?>" class="text-primary fw-semibold" style="font-size:0.85rem;">
                                    <?= htmlspecialchars($m['designation']) ?>
                                </span>
                                <button class="btn btn-sm btn-outline-secondary" style="padding:0 6px;font-size:0.65rem;border:none;" onclick="editDesignation(<?= $m['id'] ?>)" title="Edit designation">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                            <div class="text-muted" style="font-size:0.78rem;">
                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($m['email']) ?>
                                <?php if ($m['phone']): ?> · <i class="fas fa-phone me-1"></i><?= htmlspecialchars($m['phone']) ?><?php endif; ?>
                            </div>
                        </div>

                        <!-- Social links (from user profile) -->
                        <div class="d-flex gap-1">
                            <?php $socials = [
                                'linkedin_url' => ['fab fa-linkedin-in', '#0077b5'],
                                'twitter_url' => ['fab fa-twitter', '#1da1f2'],
                                'github_url' => ['fab fa-github', '#24292e'],
                                'facebook_url' => ['fab fa-facebook-f', '#1877f2'],
                                'instagram_url' => ['fab fa-instagram', '#e4405f'],
                                'website_url' => ['fas fa-globe', '#0A2D5E'],
                            ];
                            foreach ($socials as $col => [$icon, $color]):
                                if (!empty($m[$col])):
                            ?>
                                <a href="<?= htmlspecialchars($m[$col]) ?>" target="_blank" class="social-icon-mini" style="background:<?= $color ?>15;color:<?= $color ?>;" title="<?= ucfirst(str_replace('_url','',$col)) ?>">
                                    <i class="<?= $icon ?>"></i>
                                </a>
                            <?php endif; endforeach; ?>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex flex-column gap-1">
                            <button class="btn btn-sm rounded-pill <?= $m['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?> px-3" style="font-size:0.72rem;" onclick="toggleActive(<?= $m['id'] ?>, <?= $m['is_active'] ? 0 : 1 ?>)">
                                <i class="fas <?= $m['is_active'] ? 'fa-eye' : 'fa-eye-slash' ?> me-1"></i><?= $m['is_active'] ? 'Visible' : 'Hidden' ?>
                            </button>
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:0.72rem;" onclick="removeMember(<?= $m['id'] ?>)">
                                <i class="fas fa-trash me-1"></i>Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function showAddPanel() {
    document.getElementById('addPanel').style.display = 'block';
}
function hideAddPanel() {
    document.getElementById('addPanel').style.display = 'none';
}

function addMember() {
    const uid = document.getElementById('newUserId').value;
    const desig = document.getElementById('newDesignation').value.trim();
    if (!uid || !desig) {
        Swal.fire({icon:'warning', title:'Required', text:'Select a user and enter a designation.', confirmButtonColor:'#0A2D5E'});
        return;
    }
    const fd = new FormData();
    fd.append('ajax_action', 'add_member');
    fd.append('user_id', uid);
    fd.append('designation', desig);
    Swal.fire({title:'Adding...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
    fetch('manage_team.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if (d.success) {
            Swal.fire({icon:'success', title:'Added!', text:d.message, confirmButtonColor:'#0A2D5E', timer:2000}).then(()=>location.reload());
        } else {
            Swal.fire({icon:'error', title:'Error', text:d.message, confirmButtonColor:'#dc3545'});
        }
    }).catch(()=>Swal.fire({icon:'error', title:'Error', text:'Network error.', confirmButtonColor:'#dc3545'}));
}

function removeMember(id) {
    Swal.fire({
        title: 'Remove this member?',
        text: 'They will no longer appear on the Team page.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'remove_member');
        fd.append('id', id);
        Swal.fire({title:'Removing...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
        fetch('manage_team.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
            if (d.success) {
                Swal.fire({icon:'success', title:'Removed!', confirmButtonColor:'#0A2D5E', timer:1500}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'error', title:'Error', text:d.message, confirmButtonColor:'#dc3545'});
            }
        }).catch(()=>Swal.fire({icon:'error', title:'Error', text:'Network error.', confirmButtonColor:'#dc3545'}));
    });
}

function toggleActive(id, newStatus) {
    const fd = new FormData();
    fd.append('ajax_action', 'toggle_active');
    fd.append('id', id);
    fd.append('is_active', newStatus);
    fetch('manage_team.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if (d.success) location.reload();
        else Swal.fire({icon:'error', title:'Error', text:d.message, confirmButtonColor:'#dc3545'});
    }).catch(()=>Swal.fire({icon:'error', title:'Error', text:'Network error.', confirmButtonColor:'#dc3545'}));
}

function editDesignation(id) {
    const current = document.getElementById('desig-' + id).textContent.trim();
    Swal.fire({
        title: 'Edit Designation',
        input: 'text',
        inputValue: current,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-1"></i> Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0A2D5E',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        inputValidator: val => !val.trim() ? 'Designation required.' : null
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'update_designation');
        fd.append('id', id);
        fd.append('designation', r.value.trim());
        Swal.fire({title:'Updating...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
        fetch('manage_team.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
            if (d.success) {
                document.getElementById('desig-' + id).textContent = r.value.trim();
                Swal.fire({icon:'success', title:'Updated!', confirmButtonColor:'#0A2D5E', timer:1500});
            } else {
                Swal.fire({icon:'error', title:'Error', text:d.message, confirmButtonColor:'#dc3545'});
            }
        }).catch(()=>Swal.fire({icon:'error', title:'Error', text:'Network error.', confirmButtonColor:'#dc3545'}));
    });
}

function moveUp(btn) {
    const card = btn.closest('.team-member-card');
    const prev = card.previousElementSibling;
    if (prev) {
        card.parentNode.insertBefore(card, prev);
        saveOrder();
    }
}
function moveDown(btn) {
    const card = btn.closest('.team-member-card');
    const next = card.nextElementSibling;
    if (next) {
        card.parentNode.insertBefore(next, card);
        saveOrder();
    }
}
function saveOrder() {
    const ids = Array.from(document.querySelectorAll('#teamList .team-member-card')).map(el => el.dataset.id);
    const fd = new FormData();
    fd.append('ajax_action', 'reorder');
    ids.forEach(id => fd.append('ids[]', id));
    fetch('manage_team.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        // Update order numbers
        document.querySelectorAll('#teamList .team-member-card').forEach((el, i) => {
            const num = el.querySelector('.small.fw-bold');
            if (num) num.textContent = i + 1;
            const upBtn = el.querySelector('.order-btn:first-child');
            const downBtn = el.querySelector('.order-btn:last-child');
            if (upBtn) upBtn.disabled = i === 0;
            if (downBtn) downBtn.disabled = i === document.querySelectorAll('#teamList .team-member-card').length - 1;
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
