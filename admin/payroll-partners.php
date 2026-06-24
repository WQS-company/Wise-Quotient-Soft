<?php
// Normalize phone number function
function normalizePhone($input) {
    $digits = preg_replace('/[^0-9]/', '', $input);
    if (strpos($digits, '234') === 0) {
        return '0' . substr($digits, 3);
    } elseif (strpos($digits, '0') === 0) {
        return $digits;
    } else {
        return '0' . $digits;
    }
}

// First handle AJAX before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Load config to get $pdo
    require_once dirname(__DIR__) . '/config.php';
    
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];
    
    try {
        if ($act === 'add_partner') {
            $stmt = $pdo->prepare("INSERT INTO hr_partners (user_id, full_name, business_name, email, phone, address, bank_name, account_name, account_number, default_commission_percent, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['user_id'] ?: null,
                $_POST['full_name'],
                $_POST['business_name'],
                $_POST['email'],
                normalizePhone($_POST['phone'] ?? ''),
                $_POST['address'],
                $_POST['bank_name'],
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['default_commission_percent'],
                $_POST['status']
            ]);
            log_audit('partner', 'add', null, ['full_name' => $_POST['full_name']]);
            echo json_encode(['success' => true, 'message' => 'Partner added successfully']);
            exit;
        }
        
        if ($act === 'update_partner') {
            $stmt = $pdo->prepare("SELECT * FROM hr_partners WHERE id=?");
            $stmt->execute([$_POST['partner_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE hr_partners SET user_id=?, full_name=?, business_name=?, email=?, phone=?, address=?, bank_name=?, account_name=?, account_number=?, default_commission_percent=?, status=? WHERE id=?");
            $stmt->execute([
                $_POST['user_id'] ?: null,
                $_POST['full_name'],
                $_POST['business_name'],
                $_POST['email'],
                normalizePhone($_POST['phone'] ?? ''),
                $_POST['address'],
                $_POST['bank_name'],
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['default_commission_percent'],
                $_POST['status'],
                $_POST['partner_id']
            ]);
            log_audit('partner', 'update', $old, ['full_name' => $_POST['full_name']]);
            echo json_encode(['success' => true, 'message' => 'Partner updated successfully']);
            exit;
        }
        
        if ($act === 'delete_partner') {
            $stmt = $pdo->prepare("SELECT * FROM hr_partners WHERE id=?");
            $stmt->execute([$_POST['partner_id']]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM hr_partners WHERE id=?");
            $stmt->execute([$_POST['partner_id']]);
            log_audit('partner', 'delete', $old);
            echo json_encode(['success' => true, 'message' => 'Partner deleted successfully']);
            exit;
        }
        
        if ($act === 'create_user_for_partner') {
            $partner_id = $_POST['partner_id'];
            $stmt = $pdo->prepare("SELECT * FROM hr_partners WHERE id=?");
            $stmt->execute([$partner_id]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$partner) {
                echo json_encode(['success' => false, 'message' => 'Partner not found']);
                exit;
            }
            
            // Default password
            $default_password = bin2hex(random_bytes(8));
            
            // Create user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'agent', 'active')");
            $stmt->execute([
                $partner['full_name'],
                $partner['email'],
                password_hash($default_password, PASSWORD_DEFAULT)
            ]);
            $new_user_id = $pdo->lastInsertId();
            
            // Update partner to link user
            $pdo->prepare("UPDATE hr_partners SET user_id=? WHERE id=?")->execute([$new_user_id, $partner_id]);
            
            log_audit('partner', 'create_user', $partner, ['user_id' => $new_user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'User account created successfully!',
                'password' => $default_password,
                'email' => $partner['email'],
                'name' => $partner['full_name']
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$path_to_root = "../";
$page_title = "Partner Management";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Get data
try {
    $partners = $pdo->query("
        SELECT p.*, u.name as user_name 
        FROM hr_partners p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $users = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('admin', 'agent', 'user') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $partners = [];
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

.partner-item {
    display: flex; align-items: center;
    padding: 1rem; border-radius: 12px;
    margin-bottom: 0.75rem; border: 1px solid #e2e8f0;
    transition: all 0.2s;
}
.partner-item:hover { background: #f8fafc; border-color: #bbf7d0; }
.partner-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #15803d);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 1.2rem;
    margin-right: 1rem; flex-shrink: 0;
}
.partner-info { flex: 1; min-width: 0; }
.partner-name { font-weight: 700; color: #0A2D5E; margin-bottom: 0.25rem; }
.partner-details { font-size: 0.85rem; color: #64748b; }
.partner-status {
    padding: 0.25rem 0.75rem; border-radius: 50px;
    font-size: 0.75rem; font-weight: 600;
}
.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #f1f5f9; color: #64748b; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Partner Management</h3>
</div>

<div class="hr-card mb-4">
    <div class="hr-card-header">
        <h5><i class="fas fa-handshake me-2"></i>All Partners</h5>
        <button class="btn btn-success btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
            <i class="fas fa-plus me-1"></i> Add Partner
        </button>
    </div>
    <div class="hr-card-body">
        <?php if (empty($partners)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-handshake" style="font-size:3rem; opacity:0.5;"></i>
                <h5 class="mt-3">No Partners Yet</h5>
                <p class="mb-3">Start by adding your first partner</p>
                <button class="btn btn-success rounded-pill" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
                    <i class="fas fa-plus me-1"></i> Add First Partner
                </button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($partners as $partner): ?>
                    <div class="col-md-6">
                        <div class="partner-item">
                            <div class="partner-avatar">
                                <?= strtoupper(substr($partner['full_name'], 0, 1)) ?>
                            </div>
                            <div class="partner-info">
                                <div class="partner-name"><?= htmlspecialchars($partner['full_name']) ?></div>
                                <div class="partner-details">
                                    <span class="me-3"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($partner['email']) ?></span>
                                    <?php if ($partner['business_name']): ?>
                                        <span class="me-3"><i class="fas fa-building me-1"></i><?= htmlspecialchars($partner['business_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="me-3"><i class="fas fa-percent me-1"></i><?= htmlspecialchars($partner['default_commission_percent']) ?>% commission</span>
                                </div>
                            </div>
                            <span class="partner-status status-<?= $partner['status'] ?> me-3">
                                <?= ucfirst($partner['status']) ?>
                            </span>
                            <div class="d-flex gap-2">
                                <?php if (!$partner['user_id']): ?>
                                    <button class="btn btn-outline-success btn-sm" onclick="createUserForPartner(<?= $partner['id'] ?>)">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary btn-sm" onclick='editPartner(<?= json_encode($partner) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deletePartner(<?= $partner['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Partner Modal -->
<div class="modal fade" id="addPartnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Partner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPartnerForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required placeholder="Partner Full Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email *</label>
                            <input type="email" class="form-control" name="email" required placeholder="partner@example.com">
                        </div>
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
                            <label class="form-label small fw-semibold">Business Name</label>
                            <input type="text" class="form-control" name="business_name" placeholder="Business Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" class="form-control" name="phone" placeholder="Phone Number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Default Commission %</label>
                            <input type="number" class="form-control" name="default_commission_percent" value="15" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
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
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Partner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Partner Modal -->
<div class="modal fade" id="editPartnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Partner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPartnerForm">
                <input type="hidden" id="edit_partner_id" name="partner_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
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
                            <label class="form-label small fw-semibold">Business Name</label>
                            <input type="text" class="form-control" id="edit_business_name" name="business_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Default Commission %</label>
                            <input type="number" class="form-control" id="edit_default_commission_percent" name="default_commission_percent" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bank Name</label>
                            <input type="text" class="form-control" id="edit_bank_name" name="bank_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Name</label>
                            <input type="text" class="form-control" id="edit_account_name" name="account_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number" name="account_number">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Partner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function createUserForPartner(partnerId) {
    Swal.fire({
        title: 'Create User Account for Partner?',
        text: 'This will create a login account for this partner and generate a temporary password.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Yes, Create Account',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'create_user_for_partner');
            fd.append('partner_id', partnerId);
            Swal.fire({ title: 'Creating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch('payroll-partners.php', { method: 'POST', body: fd })
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
                                <p style="font-size: 0.85rem; color: #64748b;">Please share these credentials with the partner.</p>
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
        }
    });
}

function editPartner(partner) {
    document.getElementById('edit_partner_id').value = partner.id;
    document.getElementById('edit_full_name').value = partner.full_name;
    document.getElementById('edit_email').value = partner.email;
    document.getElementById('edit_user_id').value = partner.user_id || '';
    document.getElementById('edit_business_name').value = partner.business_name || '';
    document.getElementById('edit_phone').value = partner.phone || '';
    document.getElementById('edit_default_commission_percent').value = partner.default_commission_percent;
    document.getElementById('edit_status').value = partner.status;
    document.getElementById('edit_bank_name').value = partner.bank_name || '';
    document.getElementById('edit_account_name').value = partner.account_name || '';
    document.getElementById('edit_account_number').value = partner.account_number || '';
    document.getElementById('edit_address').value = partner.address || '';
    new bootstrap.Modal(document.getElementById('editPartnerModal')).show();
}

function deletePartner(id) {
    Swal.fire({
        title: 'Delete Partner?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('ajax_action', 'delete_partner');
            fd.append('partner_id', id);
            fetch('payroll-partners.php', { method: 'POST', body: fd })
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

document.getElementById('addPartnerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'add_partner');
    Swal.fire({ title: 'Adding...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-partners.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Added!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
});

document.getElementById('editPartnerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('ajax_action', 'update_partner');
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('payroll-partners.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: d.message, timer: 1500 }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message });
            }
        });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
