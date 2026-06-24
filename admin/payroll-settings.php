<?php
$path_to_root = "../";
$page_title = "Payroll Settings";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        // Manage Departments
        if ($act === 'add_department') {
            $stmt = $pdo->prepare("INSERT INTO hr_departments (name, description, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$_POST['dept_name'], $_POST['dept_description']]);
            log_audit('payroll_settings', 'add_department', null, ['name' => $_POST['dept_name']]);
            echo json_encode(['success' => true, 'message' => 'Department added!']);
            exit;
        }
        if ($act === 'update_department') {
            $stmt = $pdo->prepare("SELECT * FROM hr_departments WHERE id = ?");
            $stmt->execute([$_POST['dept_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE hr_departments SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['dept_name'], $_POST['dept_description'], $_POST['dept_id']]);
            log_audit('payroll_settings', 'update_department', $old, ['name' => $_POST['dept_name']]);
            echo json_encode(['success' => true, 'message' => 'Department updated!']);
            exit;
        }
        if ($act === 'toggle_dept') {
            $stmt = $pdo->prepare("SELECT * FROM hr_departments WHERE id = ?");
            $stmt->execute([$_POST['dept_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $new_status = $old['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE hr_departments SET is_active = ? WHERE id = ?")
                ->execute([$new_status, $_POST['dept_id']]);
            log_audit('payroll_settings', 'toggle_department', $old, ['is_active' => $new_status]);
            echo json_encode(['success' => true, 'message' => 'Department updated!']);
            exit;
        }
        
        // Manage Teams
        if ($act === 'add_team') {
            $stmt = $pdo->prepare("INSERT INTO hr_teams (department_id, name, description, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$_POST['team_dept'], $_POST['team_name'], $_POST['team_description']]);
            log_audit('payroll_settings', 'add_team', null, ['name' => $_POST['team_name'], 'dept_id' => $_POST['team_dept']]);
            echo json_encode(['success' => true, 'message' => 'Team added!']);
            exit;
        }
        if ($act === 'update_team') {
            $stmt = $pdo->prepare("SELECT * FROM hr_teams WHERE id = ?");
            $stmt->execute([$_POST['team_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE hr_teams SET department_id = ?, name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['team_dept'], $_POST['team_name'], $_POST['team_description'], $_POST['team_id']]);
            log_audit('payroll_settings', 'update_team', $old, ['name' => $_POST['team_name']]);
            echo json_encode(['success' => true, 'message' => 'Team updated!']);
            exit;
        }
        if ($act === 'toggle_team') {
            $stmt = $pdo->prepare("SELECT * FROM hr_teams WHERE id = ?");
            $stmt->execute([$_POST['team_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $new_status = $old['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE hr_teams SET is_active = ? WHERE id = ?")
                ->execute([$new_status, $_POST['team_id']]);
            log_audit('payroll_settings', 'toggle_team', $old, ['is_active' => $new_status]);
            echo json_encode(['success' => true, 'message' => 'Team updated!']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle saving settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 5) === 'set__') {
            $settingKey = substr($key, 5);
            try {
                $stmt = $pdo->prepare("INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$settingKey, $value, $value]);
            } catch (Exception $e) {
                // Fail silently
            }
        }
    }
    log_audit('payroll_settings', 'save_settings');
}

// Get all data
try {
    $settings = [];
    $stmt = $pdo->query("SELECT * FROM hr_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $departments = $pdo->query("SELECT * FROM hr_departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $teams = $pdo->query("
        SELECT t.*, d.name as department_name 
        FROM hr_teams t 
        LEFT JOIN hr_departments d ON t.department_id = d.id 
        ORDER BY d.name, t.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $settings = [];
    $departments = [];
    $teams = [];
}
?>

<style>
.hr-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.hr-card-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}
.hr-card-header h5 { margin: 0; font-weight: 700; color: #0A2D5E; }
.hr-card-body { padding: 1.5rem; }
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
}
.badge-success { background: #dcfce7; color: #166534; }
.badge-secondary { background: #e2e8f0; color: #64748b; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">System Settings</h3>
</div>

<div class="row g-4 mb-4">
    <!-- General Settings -->
    <div class="col-lg-6">
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-cog me-2"></i>General Settings</h5>
            </div>
            <div class="hr-card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Currency Symbol</label>
                    <input type="text" class="form-control" name="set__currency" form="settings-form" value="<?= htmlspecialchars($settings['currency'] ?? '₦') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Payroll Cycle</label>
                    <select class="form-select" name="set__payroll_cycle" form="settings-form">
                        <option value="monthly" <?= ($settings['payroll_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="biweekly" <?= ($settings['payroll_cycle'] ?? '') === 'biweekly' ? 'selected' : '' ?>>Bi-weekly</option>
                        <option value="weekly" <?= ($settings['payroll_cycle'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-calculator me-2"></i>Payroll Settings</h5>
            </div>
            <div class="hr-card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tax Percentage (%)</label>
                    <input type="number" class="form-control" name="set__tax_percent" form="settings-form" value="<?= htmlspecialchars($settings['tax_percent'] ?? '7.5') ?>" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pension Percentage (%)</label>
                    <input type="number" class="form-control" name="set__pension_percent" form="settings-form" value="<?= htmlspecialchars($settings['pension_percent'] ?? '8') ?>" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Partner Commission (%)</label>
                    <input type="number" class="form-control" name="set__partner_commission_percent" form="settings-form" value="<?= htmlspecialchars($settings['partner_commission_percent'] ?? '10') ?>" step="0.01" min="0" max="100">
                    <div class="form-text">Default commission percentage for partner referrals. Admin can override per-partner in Partner Management.</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications & SMTP -->
    <div class="col-lg-6">
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-bell me-2"></i>Notification Settings</h5>
            </div>
            <div class="hr-card-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="set__email_enabled" id="email_enabled" value="1" form="settings-form" <?= ($settings['email_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="email_enabled">Enable Email Notifications</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="set__sms_enabled" id="sms_enabled" value="1" form="settings-form" <?= ($settings['sms_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sms_enabled">Enable SMS Notifications</label>
                </div>
            </div>
        </div>
        
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-envelope me-2"></i>SMTP Settings</h5>
            </div>
            <div class="hr-card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMTP Host</label>
                    <input type="text" class="form-control" name="set__smtp_host" form="settings-form" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMTP Port</label>
                    <input type="number" class="form-control" name="set__smtp_port" form="settings-form" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMTP Username</label>
                    <input type="text" class="form-control" name="set__smtp_username" form="settings-form" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMTP Password</label>
                    <input type="password" class="form-control" name="set__smtp_password" form="settings-form" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMTP Encryption</label>
                    <select class="form-select" name="set__smtp_encryption" form="settings-form">
                        <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<hr class="mb-4">

<div class="row g-4">
    <!-- Departments -->
    <div class="col-lg-6">
        <div class="hr-card mb-4">
            <div class="hr-card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-building me-2"></i>Departments</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                    <i class="fas fa-plus me-1"></i> Add Department
                </button>
            </div>
            <div class="hr-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No departments yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($dept['name']) ?></td>
                                        <td>
                                            <span class="badge <?= $dept['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $dept['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm me-1" 
                                                onclick="editDept(<?= htmlspecialchars(json_encode($dept)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" 
                                                onclick="toggleDept(<?= $dept['id'] ?>)">
                                                <i class="fas fa-<?= $dept['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Teams -->
    <div class="col-lg-6">
        <div class="hr-card mb-4">
            <div class="hr-card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-users me-2"></i>Teams</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                    <i class="fas fa-plus me-1"></i> Add Team
                </button>
            </div>
            <div class="hr-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teams)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No teams yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($team['name']) ?></td>
                                        <td><?= htmlspecialchars($team['department_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge <?= $team['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $team['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm me-1" 
                                                onclick="editTeam(<?= htmlspecialchars(json_encode($team)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" 
                                                onclick="toggleTeam(<?= $team['id'] ?>)">
                                                <i class="fas fa-<?= $team['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="settings-form" method="POST">
    <div class="text-end">
        <button type="submit" name="save_settings" class="btn btn-primary btn-lg rounded-pill px-5">
            <i class="fas fa-save me-2"></i> Save Settings
        </button>
    </div>
</form>

<!-- Add Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" class="form-control" id="dept_name" placeholder="e.g., Engineering">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea class="form-control" id="dept_description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDept()">Add Department</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_dept_id">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" class="form-control" id="edit_dept_name">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea class="form-control" id="edit_dept_description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateDept()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Team Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Team</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department *</label>
                    <select class="form-select" id="team_dept">
                        <option value="">Choose department...</option>
                        <?php foreach ($departments as $dept): ?>
                            <?php if ($dept['is_active']): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" class="form-control" id="team_name" placeholder="e.g., Backend Dev">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea class="form-control" id="team_description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTeam()">Add Team</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Team Modal -->
<div class="modal fade" id="editTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Team</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_team_id">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department *</label>
                    <select class="form-select" id="edit_team_dept">
                        <option value="">Choose department...</option>
                        <?php foreach ($departments as $dept): ?>
                            <?php if ($dept['is_active']): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" class="form-control" id="edit_team_name">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea class="form-control" id="edit_team_description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateTeam()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function saveDept() {
    const fd = new FormData();
    fd.append('ajax_action', 'add_department');
    fd.append('dept_name', document.getElementById('dept_name').value);
    fd.append('dept_description', document.getElementById('dept_description').value);
    
    Swal.fire({ title: 'Adding...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Added!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}

function editDept(dept) {
    document.getElementById('edit_dept_id').value = dept.id;
    document.getElementById('edit_dept_name').value = dept.name;
    document.getElementById('edit_dept_description').value = dept.description;
    new bootstrap.Modal(document.getElementById('editDeptModal')).show();
}

function updateDept() {
    const fd = new FormData();
    fd.append('ajax_action', 'update_department');
    fd.append('dept_id', document.getElementById('edit_dept_id').value);
    fd.append('dept_name', document.getElementById('edit_dept_name').value);
    fd.append('dept_description', document.getElementById('edit_dept_description').value);
    
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}

function toggleDept(id) {
    const fd = new FormData();
    fd.append('ajax_action', 'toggle_dept');
    fd.append('dept_id', id);
    
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}

function saveTeam() {
    const fd = new FormData();
    fd.append('ajax_action', 'add_team');
    fd.append('team_dept', document.getElementById('team_dept').value);
    fd.append('team_name', document.getElementById('team_name').value);
    fd.append('team_description', document.getElementById('team_description').value);
    
    Swal.fire({ title: 'Adding...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Added!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}

function editTeam(team) {
    document.getElementById('edit_team_id').value = team.id;
    document.getElementById('edit_team_dept').value = team.department_id;
    document.getElementById('edit_team_name').value = team.name;
    document.getElementById('edit_team_description').value = team.description;
    new bootstrap.Modal(document.getElementById('editTeamModal')).show();
}

function updateTeam() {
    const fd = new FormData();
    fd.append('ajax_action', 'update_team');
    fd.append('team_id', document.getElementById('edit_team_id').value);
    fd.append('team_dept', document.getElementById('edit_team_dept').value);
    fd.append('team_name', document.getElementById('edit_team_name').value);
    fd.append('team_description', document.getElementById('edit_team_description').value);
    
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}

function toggleTeam(id) {
    const fd = new FormData();
    fd.append('ajax_action', 'toggle_team');
    fd.append('team_id', id);
    
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
}
</script>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])): ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Saved!', text: 'Settings saved successfully', timer: 1500 });
    </script>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
