<?php
// First handle AJAX before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Load config to get $pdo
    require_once dirname(__DIR__) . '/config.php';
    
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        if ($act === 'add_employee') {
            $stmt = $pdo->prepare("INSERT INTO hr_employees (user_id, department_id, team_id, position, salary, bank_name, account_name, account_number, employment_status, hire_date, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['user_id'] ?: null,
                $_POST['department_id'] ?: null,
                $_POST['team_id'] ?: null,
                $_POST['position'],
                $_POST['salary'],
                $_POST['bank_name'],
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['employment_status'],
                $_POST['hire_date'] ?: null,
                $_POST['address'],
                $_POST['phone']
            ]);
            log_audit('employee', 'add', null, ['position' => $_POST['position']]);
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
            exit;
        }
        
        if ($act === 'update_employee') {
            $stmt = $pdo->prepare("SELECT * FROM hr_employees WHERE id=?");
            $stmt->execute([$_POST['employee_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE hr_employees SET user_id=?, department_id=?, team_id=?, position=?, salary=?, bank_name=?, account_name=?, account_number=?, employment_status=?, hire_date=?, address=?, phone=? WHERE id=?");
            $stmt->execute([
                $_POST['user_id'] ?: null,
                $_POST['department_id'] ?: null,
                $_POST['team_id'] ?: null,
                $_POST['position'],
                $_POST['salary'],
                $_POST['bank_name'],
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['employment_status'],
                $_POST['hire_date'] ?: null,
                $_POST['address'],
                $_POST['phone'],
                $_POST['employee_id']
            ]);
            log_audit('employee', 'update', $old, ['position' => $_POST['position']]);
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
            exit;
        }
        
        if ($act === 'delete_employee') {
            $stmt = $pdo->prepare("SELECT * FROM hr_employees WHERE id=?");
            $stmt->execute([$_POST['employee_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM hr_employees WHERE id=?");
            $stmt->execute([$_POST['employee_id']]);
            log_audit('employee', 'delete', $old);
            echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
            exit;
        }
        
        if ($act === 'create_user_for_employee') {
            $employee_id = $_POST['employee_id'];
            $stmt = $pdo->prepare("SELECT e.*, u.name as user_name FROM hr_employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.id=?");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit;
            }
            
            // Default password
            $default_password = bin2hex(random_bytes(8));
            
            // Create user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'developer', 'active')");
            $user_email = $_POST['email'] ?: $employee['phone'] . '@temp.com';
            $user_name = $_POST['name'] ?: ($employee['user_name'] ?: $employee['position']);
            
            $stmt->execute([
                $user_name,
                $user_email,
                password_hash($default_password, PASSWORD_DEFAULT)
            ]);
            $new_user_id = $pdo->lastInsertId();
            
            // Update employee to link user
            $pdo->prepare("UPDATE hr_employees SET user_id=? WHERE id=?")->execute([$new_user_id, $employee_id]);
            
            log_audit('employee', 'create_user', $employee, ['user_id' => $new_user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'User account created successfully!',
                'password' => $default_password,
                'email' => $user_email,
                'name' => $user_name
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$path_to_root = "../";
$page_title = "Employee Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Get data
try {
    $employees = $pdo->query("
        SELECT e.*, u.name as user_name, d.name as department_name, t.name as team_name 
        FROM hr_employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN hr_departments d ON e.department_id = d.id 
        LEFT JOIN hr_teams t ON e.team_id = t.id 
        ORDER BY e.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $departments = $pdo->query("SELECT * FROM hr_departments WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $teams = $pdo->query("SELECT * FROM hr_teams WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $users = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('admin', 'developer', 'manager') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
    $departments = [];
    $teams = [];
    $users = [];
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
    display: flex; justify-content: space-between; align-items: center;
}
.hr-card-header h5 { margin: 0; font-weight: 700; color: #0A2D5E; }
.hr-card-body { padding: 1.5rem; }

.employee-item {
    display: flex; align-items: center;
    padding: 1rem; border-radius: 12px;
    margin-bottom: 0.75rem; border: 1px solid #e2e8f0;
    transition: all 0.2s;
}
.employee-item:hover { background: #f8fafc; border-color: #bfdbfe; }
.employee-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #0A2D5E, #2563eb);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 1.2rem;
    margin-right: 1rem; flex-shrink: 0;
}
.employee-info { flex: 1; min-width: 0; }
.employee-name { font-weight: 700; color: #0A2D5E; margin-bottom: 0.25rem; }
.employee-details { font-size: 0.85rem; color: #64748b; }
.employee-status {
    padding: 0.25rem 0.75rem; border-radius: 50px;
    font-size: 0.75rem; font-weight: 600;
}
.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #f1f5f9; color: #64748b; }
.status-on_leave { background: #fef3c7; color: #d97706; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Employee Management</h3>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="hr-card">
            <div class="hr-card-header">
                <h5><i class="fas fa-users me-2"></i>All Employees</h5>
                <button class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fas fa-plus me-1"></i> Add Employee
                </button>
            </div>
            <div class="hr-card-body">
                <?php if (empty($employees)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-user-tie" style="font-size:3rem; opacity:0.5;"></i>
                        <h5 class="mt-3">No Employees Yet</h5>
                        <p class="mb-3">Start by adding your first employee</p>
                        <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="fas fa-plus me-1"></i> Add First Employee
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <div class="employee-item">
                            <div class="employee-avatar">
                                <?= strtoupper(substr($emp['user_name'] ?: $emp['position'], 0, 1)) ?>
                            </div>
                            <div class="employee-info">
                                <div class="employee-name">
                                    <?= htmlspecialchars($emp['user_name'] ?: 'Unlinked Employee') ?>
                                </div>
                                <div class="employee-details">
                                    <span class="me-3"><i class="fas fa-briefcase me-1"></i><?= htmlspecialchars($emp['position']) ?></span>
                                    <?php if ($emp['department_name']): ?>
                                        <span class="me-3"><i class="fas fa-building me-1"></i><?= htmlspecialchars($emp['department_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="me-3"><i class="fas fa-wallet me-1"></i>₦<?= number_format($emp['salary'], 2) ?></span>
                                </div>
                            </div>
                            <span class="employee-status status-<?= $emp['employment_status'] ?> me-3">
                                <?= ucfirst(str_replace('_', ' ', $emp['employment_status'])) ?>
                            </span>
                            <div class="d-flex gap-2">
                                <?php if (!$emp['user_id']): ?>
                                    <button class="btn btn-outline-success btn-sm" onclick="createUserForEmployee(<?= $emp['id'] ?>)">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary btn-sm" onclick='editEmployee(<?= json_encode($emp) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteEmployee(<?= $emp['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="hr-card mb-4">
            <div class="hr-card-header">
                <h5><i class="fas fa-building me-2"></i>Departments</h5>
            </div>
            <div class="hr-card-body">
                <?php foreach ($departments as $dept): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong><?= htmlspecialchars($dept['name']) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars($dept['description'] ?: 'No description') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="hr-card">
            <div class="hr-card-header">
                <h5><i class="fas fa-users-cog me-2"></i>Teams</h5>
            </div>
            <div class="hr-card-body">
                <?php foreach ($teams as $team): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong><?= htmlspecialchars($team['name']) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars($team['description'] ?: 'No description') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEmployeeForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Link to User (Optional)</label>
                            <select class="form-select" name="user_id">
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Position *</label>
                            <input type="text" class="form-control" name="position" required placeholder="e.g. Senior Developer">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Team</label>
                            <select class="form-select" name="team_id">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Salary *</label>
                            <input type="number" class="form-control" name="salary" required placeholder="0.00" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name" placeholder="Bank Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Name</label>
                            <input type="text" class="form-control" name="account_name" placeholder="Account Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Number</label>
                            <input type="text" class="form-control" name="account_number" placeholder="Account Number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Employment Status</label>
                            <select class="form-select" name="employment_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" class="form-control" name="phone" placeholder="Phone Number">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEmployeeForm">
                <input type="hidden" id="edit_employee_id" name="employee_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Link to User (Optional)</label>
                            <select class="form-select" id="edit_user_id" name="user_id">
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Position *</label>
                            <input type="text" class="form-control" id="edit_position" name="position" required placeholder="e.g. Senior Developer">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Department</label>
                            <select class="form-select" id="edit_department_id" name="department_id">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Team</label>
                            <select class="form-select" id="edit_team_id" name="team_id">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Salary *</label>
                            <input type="number" class="form-control" id="edit_salary" name="salary" required placeholder="0.00" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bank Name</label>
                            <input type="text" class="form-control" id="edit_bank_name" name="bank_name" placeholder="Bank Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Name</label>
                            <input type="text" class="form-control" id="edit_account_name" name="account_name" placeholder="Account Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number" name="account_number" placeholder="Account Number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Employment Status</label>
                            <select class="form-select" id="edit_employment_status" name="employment_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Hire Date</label>
                            <input type="date" class="form-control" id="edit_hire_date" name="hire_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" placeholder="Phone Number">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2" placeholder="Address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create User for Employee Modal -->
<div class="modal fade" id="createEmployeeUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createEmployeeUserForm">
                <input type="hidden" id="create_emp_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Name</label>
                        <input type="text" class="form-control" id="create_emp_user_name" placeholder="Employee's full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" class="form-control" id="create_emp_user_email" placeholder="employee@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function createUserForEmployee(employeeId) {
    document.getElementById('create_emp_user_id').value = employeeId;
    new bootstrap.Modal(document.getElementById('createEmployeeUserModal')).show();
}

function editEmployee(emp) {
    document.getElementById('edit_employee_id').value = emp.id;
    document.getElementById('edit_user_id').value = emp.user_id || '';
    document.getElementById('edit_position').value = emp.position;
    document.getElementById('edit_department_id').value = emp.department_id || '';
    document.getElementById('edit_team_id').value = emp.team_id || '';
    document.getElementById('edit_salary').value = emp.salary;
    document.getElementById('edit_bank_name').value = emp.bank_name || '';
    document.getElementById('edit_account_name').value = emp.account_name || '';
    document.getElementById('edit_account_number').value = emp.account_number || '';
    document.getElementById('edit_employment_status').value = emp.employment_status;
    document.getElementById('edit_hire_date').value = emp.hire_date || '';
    document.getElementById('edit_phone').value = emp.phone || '';
    document.getElementById('edit_address').value = emp.address || '';
    new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
}

function deleteEmployee(id) {
    Swal.fire({
        title: 'Delete Employee?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'delete_employee');
            fd.append('employee_id', id);
            fetch('payroll-employees.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted!', text: d.message, timer: 1500 }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: d.message });
                    }
                });
        }
    });
}

document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'add_employee');
    Swal.fire({ title: 'Adding...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-employees.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Added!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
});

document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'update_employee');
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-employees.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
});

document.getElementById('createEmployeeUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('ajax_action', 'create_user_for_employee');
    fd.append('employee_id', document.getElementById('create_emp_user_id').value);
    fd.append('name', document.getElementById('create_emp_user_name').value);
    fd.append('email', document.getElementById('create_emp_user_email').value);
    
    Swal.fire({ title: 'Creating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-employees.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Created!',
                    html: `
                        <p><strong>Name:</strong> ${d.name}</p>
                        <p><strong>Email:</strong> ${d.email}</p>
                        <p><strong>Temporary Password:</strong> <code>${d.password}</code></p>
                        <p style="font-size: 0.85rem; color: #64748b;">Please share these credentials with the employee.</p>
                    `,
                    confirmButtonText: 'Copy Password & Close'
                }).then(() => {
                    navigator.clipboard.writeText(d.password).then(() => {
                        Swal.fire({
                            icon: 'info',
                            title: 'Password Copied!',
                            timer: 1500
                        });
                    }).catch(() => {});
                    location.reload();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
