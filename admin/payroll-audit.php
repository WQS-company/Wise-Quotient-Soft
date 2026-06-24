<?php
$path_to_root = "../";
$page_title = "Audit Log";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Get filters
$module_filter = $_GET['module'] ?? '';
$user_filter = $_GET['user'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query
try {
    $sql = "SELECT al.*, u.name as user_name FROM hr_audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
    $params = [];
    
    if ($module_filter) {
        $sql .= " AND al.module = ?";
        $params[] = $module_filter;
    }
    
    if ($user_filter) {
        $sql .= " AND al.user_id = ?";
        $params[] = $user_filter;
    }
    
    if ($start_date) {
        $sql .= " AND al.created_at >= ?";
        $params[] = $start_date . " 00:00:00";
    }
    
    if ($end_date) {
        $sql .= " AND al.created_at <= ?";
        $params[] = $end_date . " 23:59:59";
    }
    
    $sql .= " ORDER BY al.created_at DESC LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all unique modules for filter
    $modules_stmt = $pdo->query("SELECT DISTINCT module FROM hr_audit_logs ORDER BY module");
    $modules = $modules_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all users for filter
    $users_stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
    $all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $audit_logs = [];
    $modules = [];
    $all_users = [];
}

?>
<style>
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

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
    .hr-card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #0A2D5E;
    }
    .hr-card-body {
        padding: 1.5rem;
    }
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 700;
    }
    .badge-info {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .badge-success {
        background: #dcfce7;
        color: #166534;
    }
    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    .json-box {
        background: #1e293b;
        color: #e2e8f0;
        padding: 0.75rem;
        border-radius: 8px;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.8rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid #334155;
        line-height: 1.5;
    }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="payroll.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
    <h3 style="margin:0; font-weight:800; color:#0A2D5E;">Audit Log</h3>
    <button class="btn btn-success rounded-pill ms-auto" onclick="exportAuditLog()">
        <i class="fas fa-download me-2"></i> Export CSV
    </button>
</div>

<div class="hr-card mb-4">
    <div class="hr-card-header">
        <h5><i class="fas fa-filter me-2"></i> Filters</h5>
    </div>
    <div class="hr-card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Module</label>
                <select class="form-select" name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $module_filter === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($mod)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">User</label>
                <select class="form-select" name="user">
                    <option value="">All Users</option>
                    <?php foreach ($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $user_filter === (string)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="hr-card">
    <div class="hr-card-header">
        <h5><i class="fas fa-history me-2"></i> Recent Activity</h5>
    </div>
    <div class="hr-card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audit_logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox" style="font-size:2.5rem;"></i>
                                <p class="mb-0">No audit log entries found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($audit_logs as $log): ?>
                            <tr>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                    </small>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars(ucfirst($log['module'])) ?></span></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars(ucfirst($log['action'])) ?></span></td>
                                <td>
                                    <?php if ($log['old_value']): ?>
                                        <?php 
                                        $old_decoded = json_decode($log['old_value'], true);
                                        $old_formatted = $old_decoded ? json_encode($old_decoded, JSON_PRETTY_PRINT) : $log['old_value'];
                                        ?>
                                        <div class="json-box" style="white-space: pre-wrap;"><?= htmlspecialchars($old_formatted) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['new_value']): ?>
                                        <?php 
                                        $new_decoded = json_decode($log['new_value'], true);
                                        $new_formatted = $new_decoded ? json_encode($new_decoded, JSON_PRETTY_PRINT) : $log['new_value'];
                                        ?>
                                        <div class="json-box" style="white-space: pre-wrap;"><?= htmlspecialchars($new_formatted) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportAuditLog() {
    <?php
        // Prepare data for export
        $headers = ['Date', 'User', 'Module', 'Action', 'Old Value', 'New Value', 'IP Address'];
        
        $csv = fopen('php://temp', 'w');
        fputcsv($csv, $headers);
        
        foreach ($audit_logs as $log) {
            fputcsv($csv, [
                date('M d, Y H:i:s', strtotime($log['created_at'])),
                $log['user_name'] ?? 'System',
                ucfirst($log['module']),
                ucfirst($log['action']),
                $log['old_value'] ?? '',
                $log['new_value'] ?? '',
                $log['ip_address'] ?? ''
            ]);
        }
        
        rewind($csv);
        $csv_content = stream_get_contents($csv);
        fclose($csv);
    ?>
    
    const csv_data = <?= json_encode($csv_content, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    
    const blob = new Blob([csv_data], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'audit_log_<?= date('Y_m_d') ?>.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        icon: 'success',
        title: 'Exported!',
        text: 'Audit log exported successfully',
        timer: 1500
    });
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
