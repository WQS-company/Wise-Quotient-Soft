<?php
$path_to_root = "../";
$page_title = "Broadcast Notifications";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$adminId = $headerUser['id'];

// Auto-Migration for multi-channel fields
try {
    // Add columns if they don't exist
    $cols = ['send_email' => 'TINYINT(1) DEFAULT 0', 'send_sms' => 'TINYINT(1) DEFAULT 0', 'send_portal' => 'TINYINT(1) DEFAULT 1', 'email_sent_count' => 'INT DEFAULT 0', 'sms_sent_count' => 'INT DEFAULT 0', 'email_error' => 'TEXT NULL', 'sms_error' => 'TEXT NULL'];
    $stmt = $pdo->query("SHOW COLUMNS FROM broadcast_notifications");
    $existing_cols = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $existing_cols[] = $row['Field']; }
    foreach($cols as $col => $def) {
        if (!in_array($col, $existing_cols)) {
            $pdo->exec("ALTER TABLE broadcast_notifications ADD COLUMN $col $def");
        }
    }
} catch (Exception $e) {}

// Auto-seed gateway settings in footer_settings
try {
    $defaults = [
        'broadcast_termii_api_key' => '',
        'broadcast_termii_sender_id' => '',
        'broadcast_termii_base_url' => 'https://api.ng.termii.com',
        'broadcast_termii_channel' => 'generic',
        'broadcast_smtp_host' => '',
        'broadcast_smtp_port' => '587',
        'broadcast_smtp_secure' => 'tls',
        'broadcast_smtp_user' => '',
        'broadcast_smtp_pass' => '',
        'broadcast_smtp_from_email' => 'no-reply@wqs.com',
        'broadcast_smtp_from_name' => 'WQS Admin',
        'forgot_password_email_method' => 'smtp',
        'forgot_password_use_sms' => '0'
    ];
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM footer_settings WHERE setting_key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO footer_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $checkStmt->execute([$k]);
        if ($checkStmt->fetchColumn() == 0) {
            $insertStmt->execute([$k, $v]);
        }
    }
} catch (Exception $e) {}

// Helper function to get setting
function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM footer_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

require_once dirname(__DIR__) . '/includes/sms_helper.php';

// SMTP Helper (Socket-based pure PHP) — now defined in config.php as shared helper
// broadcast.php no longer redefines send_smtp_email / send_php_mail

// Fetch all settings for UI
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM footer_settings WHERE setting_key LIKE 'broadcast_%'");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax_action'] === 'save_gateway_settings') {
        $keys = [
            'broadcast_termii_api_key', 'broadcast_termii_sender_id', 'broadcast_termii_base_url', 'broadcast_termii_channel', 
            'broadcast_smtp_host', 'broadcast_smtp_port', 'broadcast_smtp_secure', 'broadcast_smtp_user', 'broadcast_smtp_pass', 
            'broadcast_smtp_from_email', 'broadcast_smtp_from_name',
            'forgot_password_email_method', 'forgot_password_use_sms'
        ];
        try {
            $stmt = $pdo->prepare("UPDATE footer_settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    $stmt->execute([$_POST[$k], $k]);
                }
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit;
    }

    if ($_POST['ajax_action'] === 'test_smtp') {
        $email = trim($_POST['email'] ?? '');
        if (!$email) { echo json_encode(['success' => false, 'message' => 'Email required']); exit; }
        $res = send_smtp_email($email, "WQS SMTP Test", "This is a test email to verify SMTP gateway settings.", $pdo);
        echo json_encode($res);
        exit;
    }

    if ($_POST['ajax_action'] === 'test_sms') {
        $phone = trim($_POST['phone'] ?? '');
        if (!$phone) { echo json_encode(['success' => false, 'message' => 'Phone required']); exit; }
        $res = send_termii_sms($phone, "WQS SMS Test: Gateway is configured correctly.", $pdo);
        echo json_encode($res);
        exit;
    }

    if ($_POST['ajax_action'] === 'get_target_list') {
        $target = in_array($_POST['target_role']??'',['all','user','agent','developer']) ? $_POST['target_role'] : 'all';
        $title = trim($_POST['title']??'');
        $msg = trim($_POST['message']??'');
        $type = in_array($_POST['type']??'',['info','warning','success','alert']) ? $_POST['type'] : 'info';
        
        try {
            if ($target === 'all') {
                $uStmt = $pdo->query("SELECT id, name, email, phone FROM users WHERE role != 'admin'");
            } else {
                $uStmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE role=?");
                $uStmt->execute([$target]);
            }
            $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);
            $count = count($users);

            if ($count > 0) {
                // Create broadcast record first to get ID
                $stmt = $pdo->prepare("INSERT INTO broadcast_notifications (admin_id,title,message,target_role,type,sent_count,send_email,send_sms,send_portal) VALUES (?,?,?,?,?,?,?,?,?)");
                $send_email = !empty($_POST['send_email']) && $_POST['send_email'] === 'true' ? 1 : 0;
                $send_sms = !empty($_POST['send_sms']) && $_POST['send_sms'] === 'true' ? 1 : 0;
                
                $stmt->execute([$adminId, $title, $msg, $target, $type, $count, $send_email, $send_sms, 1]);
                $broadcast_id = $pdo->lastInsertId();
            } else {
                $broadcast_id = 0;
            }

            echo json_encode(['success' => true, 'users' => $users, 'broadcast_id' => $broadcast_id, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['ajax_action'] === 'send_individual_broadcast') {
        $broadcast_id = (int)($_POST['broadcast_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        $send_email = !empty($_POST['send_email']) && $_POST['send_email'] === 'true';
        $send_sms = !empty($_POST['send_sms']) && $_POST['send_sms'] === 'true';

        $errors = [];

        try {
            // Send Portal Notification
            add_notification($user_id, $title, $msg, 'announcement', '../user/dashboard.php');

            // Send Email
            if ($send_email && $email) {
                $email_msg = str_replace("{name}", $name, $msg);
                $res = send_smtp_email($email, $title, nl2br($email_msg), $pdo);
                if ($res['success']) {
                    $pdo->exec("UPDATE broadcast_notifications SET email_sent_count = email_sent_count + 1 WHERE id = $broadcast_id");
                } else {
                    $errors[] = "Email to $email: " . $res['message'];
                }
            }

            // Send SMS
            if ($send_sms && $phone) {
                $sms_msg = strip_tags(str_replace("{name}", $name, $msg));
                $res = send_termii_sms($phone, $sms_msg, $pdo);
                if ($res['success']) {
                    $pdo->exec("UPDATE broadcast_notifications SET sms_sent_count = sms_sent_count + 1 WHERE id = $broadcast_id");
                } else {
                    $errors[] = "SMS to $phone: " . $res['message'];
                }
            }

            // Record errors if any
            if (!empty($errors)) {
                $err_str = implode(" | ", $errors) . "\n";
                $stmt = $pdo->prepare("UPDATE broadcast_notifications SET email_error = CONCAT(IFNULL(email_error, ''), ?) WHERE id = ?");
                $stmt->execute([$err_str, $broadcast_id]);
            }

            echo json_encode(['success' => true, 'errors' => $errors]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// Fetch broadcast history
try {
    $history = $pdo->prepare("
        SELECT bn.*, u.name AS admin_name
        FROM broadcast_notifications bn
        LEFT JOIN users u ON bn.admin_id=u.id
        ORDER BY bn.created_at DESC
        LIMIT 50
    ");
    $history->execute(); $history=$history->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $history=[]; }

// User counts
$userCounts = [];
try {
    $uc = $pdo->query("SELECT role, COUNT(*) as c FROM users WHERE role!='admin' GROUP BY role");
    while ($r=$uc->fetch()) $userCounts[$r['role']] = $r['c'];
    $userCounts['all'] = array_sum($userCounts);
} catch (Exception $e) {}

$typeColors = ['info'=>['#eff6ff','#1d4ed8','fas fa-info-circle'],'warning'=>['#fef3c7','#d97706','fas fa-exclamation-triangle'],'success'=>['#dcfce7','#15803d','fas fa-check-circle'],'alert'=>['#fef2f2','#dc2626','fas fa-bell']];
?>

<style>
.broadcast-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.broadcast-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.audience-btn { border-radius:12px; border:2px solid #e2e8f0; padding:1rem; cursor:pointer; text-align:center; transition:all 0.2s; background:white; }
.audience-btn:hover { border-color:#0A2D5E; background:#f0f7ff; }
.audience-btn.selected { border-color:#0A2D5E; background:rgba(10,45,94,0.06); box-shadow:0 0 0 3px rgba(10,45,94,0.1); }
.type-btn { border-radius:10px; border:2px solid #e2e8f0; padding:0.65rem 1rem; cursor:pointer; transition:all 0.2s; background:white; font-size:0.82rem; font-weight:600; text-align:center; }
.type-btn:hover { border-color:#0A2D5E; }
.type-btn.selected { border-color: var(--tc); background: var(--tbg); }
.hist-card { background:white; border-radius:14px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 14px rgba(0,0,0,0.04); padding:1.25rem; margin-bottom:0.75rem; }
</style>

<div class="broadcast-hero">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-bullhorn me-1"></i>Admin Broadcasting</span>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Broadcast Notifications</h1>
        <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">Send platform-wide announcements, alerts, and news to users.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Main Left Column -->
    <div class="col-lg-7">
        <!-- Tabs Navigation -->
        <ul class="nav nav-pills mb-4 gap-2" id="broadcastTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-bold px-4" id="compose-tab" data-bs-toggle="pill" data-bs-target="#compose-pane" type="button" role="tab" aria-selected="true" style="border:1.5px solid transparent; transition:all 0.2s;"><i class="fas fa-paper-plane me-2"></i>Compose Alert</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold px-4" id="settings-tab" data-bs-toggle="pill" data-bs-target="#settings-pane" type="button" role="tab" aria-selected="false" style="border:1.5px solid transparent; transition:all 0.2s; color:#64748b; background:white; border-color:#e2e8f0;"><i class="fas fa-cogs me-2"></i>Gateway Settings</button>
            </li>
        </ul>
        <style>
            #broadcastTabs .nav-link.active { background:#0A2D5E !important; color:white !important; border-color:#0A2D5E !important; box-shadow:0 4px 10px rgba(10,45,94,0.2); }
            #broadcastTabs .nav-link:not(.active):hover { background:var(--color-bg); border-color:#cbd5e1 !important; color:#0f172a !important; }
        </style>

        <div class="tab-content" id="broadcastTabsContent">
            <!-- Compose Tab Pane -->
            <div class="tab-pane fade show active" id="compose-pane" role="tabpanel" aria-labelledby="compose-tab">
                <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:2rem;">
                    <h5 class="fw-bold text-body mb-4"><i class="fas fa-edit me-2 text-primary"></i>Compose Message</h5>

            <!-- Audience -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Target Audience</label>
                <div class="row g-2">
                    <?php
                    $audiences = [
                        ['all','fas fa-globe','All Users',$userCounts['all']??0,'#0A2D5E'],
                        ['user','fas fa-user','Clients',$userCounts['user']??0,'#1d4ed8'],
                        ['agent','fas fa-handshake','Partners',$userCounts['agent']??0,'#6d28d9'],
                        ['developer','fas fa-code','Developers',$userCounts['developer']??0,'#0d9488'],
                    ];
                    foreach ($audiences as [$key,$icon,$label,$count,$color]):
                    ?>
                    <div class="col-6 col-sm-3">
                        <div class="audience-btn<?= $key==='all'?' selected':'' ?>" onclick="selectAudience('<?=$key?>',this)">
                            <i class="<?=$icon?>" style="font-size:1.3rem;color:<?=$color?>;margin-bottom:0.35rem;display:block;"></i>
                            <div class="fw-bold" style="font-size:0.82rem;color:#0A2D5E;"><?=$label?></div>
                            <div style="font-size:0.7rem;color:#94a3b8;"><?=$count?> user<?=$count!=1?'s':''?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="bc_target" value="all">
            </div>

            <!-- Type -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Notification Type</label>
                <div class="row g-2">
                    <?php foreach ($typeColors as $tkey => [$tbg,$tcl,$ticon]): ?>
                    <div class="col-6 col-sm-3">
                        <div class="type-btn<?= $tkey==='info'?' selected':'' ?>" onclick="selectType('<?=$tkey?>',this,'<?=$tcl?>','<?=$tbg?>')" style="--tc:<?=$tcl?>;--tbg:<?=$tbg?>;">
                            <i class="<?=$ticon?>" style="color:<?=$tcl?>;margin-right:0.3rem;"></i><?= ucfirst($tkey) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="bc_type" value="info">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Notification Title *</label>
                <input type="text" id="bc_title" class="form-control" placeholder="e.g. 🚀 New Feature Release — v2.5" style="border-radius:10px;border-color:#e2e8f0;">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Message Body *</label>
                <textarea id="bc_message" class="form-control" rows="5" placeholder="Write your announcement here..." style="border-radius:10px;border-color:#e2e8f0;resize:vertical;"></textarea>
                <div class="text-muted mt-1" style="font-size:0.75rem;"><span id="char_count">0</span> / 500 characters</div>
            </div>

            <!-- Delivery Channels -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Delivery Channels</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" id="chan_portal" checked disabled>
                        <label class="form-check-label fw-bold text-body" for="chan_portal" style="font-size:0.85rem;"><i class="fas fa-bell text-warning me-1"></i> Platform Alert (Default)</label>
                    </div>
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" id="chan_email">
                        <label class="form-check-label fw-bold text-body" for="chan_email" style="font-size:0.85rem;"><i class="fas fa-envelope text-primary me-1"></i> Email Gateway</label>
                    </div>
                    <div class="form-check form-switch custom-switch">
                        <input class="form-check-input" type="checkbox" id="chan_sms">
                        <label class="form-check-label fw-bold text-body" for="chan_sms" style="font-size:0.85rem;"><i class="fas fa-sms text-success me-1"></i> Termii SMS</label>
                    </div>
                </div>
                <style>.custom-switch .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }</style>
            </div>

            <!-- Preview -->
            <div class="mb-4 p-3 rounded-3" style="background:var(--color-bg);border:1.5px solid #e2e8f0;" id="bc_preview">
                <div class="text-muted small mb-1 fw-semibold">Preview</div>
                <div class="d-flex align-items-start gap-2">
                    <div style="width:36px;height:36px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;" id="prev_icon_wrap">
                        <i class="fas fa-info-circle" style="color:#1d4ed8;"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:0.87rem;color:#0A2D5E;" id="prev_title">—</div>
                        <div class="text-muted" style="font-size:0.8rem;" id="prev_msg">Your message will appear here...</div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar UI (Hidden initially) -->
            <div id="bc_progress_container" style="display:none; margin-bottom:1.5rem;">
                <div class="d-flex justify-content-between text-muted small fw-bold mb-1">
                    <span id="bc_status_text">Preparing broadcast...</span>
                    <span id="bc_percent">0%</span>
                </div>
                <div class="progress" style="height:10px; border-radius:10px;">
                    <div id="bc_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="d-flex justify-content-between text-muted mt-2" style="font-size:0.75rem;">
                    <span id="bc_sent_count">0 sent</span>
                    <span id="bc_error_count" class="text-danger">0 errors</span>
                </div>
            </div>

            <button id="sendBtn" class="btn w-100 py-3 fw-bold rounded-pill" style="background:linear-gradient(135deg,#E15501,#c94400);border:none;color:white;font-size:0.95rem;" onclick="sendBroadcast()">
                <i class="fas fa-paper-plane me-2"></i>Send Broadcast
            </button>
        </div>
    </div><!-- End Compose Tab -->

    <!-- Gateway Settings Tab Pane -->
    <div class="tab-pane fade" id="settings-pane" role="tabpanel" aria-labelledby="settings-tab">
        <form id="gatewaySettingsForm" onsubmit="saveGatewaySettings(event)">
            <!-- Termii SMS Gateway Card -->
            <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:2rem;margin-bottom:1.5rem;">
                <h5 class="fw-bold text-body mb-4"><i class="fas fa-sms me-2 text-success"></i>Termii SMS Gateway</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">API Key</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="broadcast_termii_api_key" value="<?= htmlspecialchars($settings['broadcast_termii_api_key'] ?? '') ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Sender ID</label>
                        <input type="text" class="form-control" name="broadcast_termii_sender_id" value="<?= htmlspecialchars($settings['broadcast_termii_sender_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Base URL</label>
                        <input type="text" class="form-control" name="broadcast_termii_base_url" value="<?= htmlspecialchars($settings['broadcast_termii_base_url'] ?? 'https://api.ng.termii.com') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Channel</label>
                        <input type="text" class="form-control" name="broadcast_termii_channel" value="<?= htmlspecialchars($settings['broadcast_termii_channel'] ?? 'generic') ?>">
                    </div>
                </div>
                <div class="mt-4 p-3 rounded" style="background:var(--color-bg); border:1px solid #e2e8f0;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="test_sms_phone" class="form-control form-control-sm" placeholder="Phone (e.g. 080123...)">
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold text-nowrap" onclick="testSMS()"><i class="fas fa-paper-plane me-1"></i>Test SMS</button>
                    </div>
                </div>
            </div>

            <!-- Email SMTP Gateway Card -->
            <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:2rem;margin-bottom:1.5rem;">
                <h5 class="fw-bold text-body mb-4"><i class="fas fa-envelope me-2 text-primary"></i>Email SMTP Gateway</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">SMTP Host (Leave blank for PHP mail)</label>
                        <input type="text" class="form-control" name="broadcast_smtp_host" value="<?= htmlspecialchars($settings['broadcast_smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Port</label>
                        <input type="text" class="form-control" name="broadcast_smtp_port" value="<?= htmlspecialchars($settings['broadcast_smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Security</label>
                        <select class="form-select" name="broadcast_smtp_secure">
                            <option value="none" <?= ($settings['broadcast_smtp_secure']??'')=='none'?'selected':'' ?>>None</option>
                            <option value="tls" <?= ($settings['broadcast_smtp_secure']??'tls')=='tls'?'selected':'' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['broadcast_smtp_secure']??'')=='ssl'?'selected':'' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <input type="text" class="form-control" name="broadcast_smtp_user" value="<?= htmlspecialchars($settings['broadcast_smtp_user'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="broadcast_smtp_pass" value="<?= htmlspecialchars($settings['broadcast_smtp_pass'] ?? '') ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">From Email</label>
                        <input type="email" class="form-control" name="broadcast_smtp_from_email" value="<?= htmlspecialchars($settings['broadcast_smtp_from_email'] ?? 'no-reply@wqs.com') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">From Name</label>
                        <input type="text" class="form-control" name="broadcast_smtp_from_name" value="<?= htmlspecialchars($settings['broadcast_smtp_from_name'] ?? 'WQS Admin') ?>">
                    </div>
                </div>
                <div class="mt-4 p-3 rounded" style="background:var(--color-bg); border:1px solid #e2e8f0;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="email" id="test_smtp_email" class="form-control form-control-sm" placeholder="Test email address">
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold text-nowrap" onclick="testSMTP()"><i class="fas fa-paper-plane me-1"></i>Test Email</button>
                    </div>
                </div>
            </div>

            <!-- Forgot Password Settings Card -->
            <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:2rem;margin-bottom:1.5rem;">
                <h5 class="fw-bold text-body mb-4"><i class="fas fa-key me-2 text-warning"></i>Forgot Password Settings</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Email Sending Method</label>
                        <select class="form-select" name="forgot_password_email_method">
                            <option value="smtp" <?= ($settings['forgot_password_email_method']??'smtp')=='smtp'?'selected':'' ?>>SMTP Gateway</option>
                            <option value="mail" <?= ($settings['forgot_password_email_method']??'')=='mail'?'selected':'' ?>>PHP mail() Function</option>
                        </select>
                        <div class="form-text small text-muted">Choose how reset password emails should be dispatched.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">SMS Integration (Termii)</label>
                        <select class="form-select" name="forgot_password_use_sms">
                            <option value="0" <?= ($settings['forgot_password_use_sms']??'0')=='0'?'selected':'' ?>>Email Only (No SMS)</option>
                            <option value="1" <?= ($settings['forgot_password_use_sms']??'')=='1'?'selected':'' ?>>Email & SMS (via Termii)</option>
                        </select>
                        <div class="form-text small text-muted">Enable or disable SMS OTP alongside password reset link.</div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4" id="saveGatewayBtn"><i class="fas fa-save me-2"></i>Save Configurations</button>
            </div>
        </form>
    </div><!-- End Settings Tab -->

</div><!-- End tab-content -->
</div><!-- End col-lg-7 -->

<!-- History Column -->
<div class="col-lg-5">
        <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:1.5rem;">
            <h5 class="fw-bold text-body mb-3"><i class="fas fa-history me-2 text-muted"></i>Broadcast History</h5>
            <?php if (empty($history)): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-bullhorn d-block mb-3 text-secondary" style="font-size:2.5rem;"></i><p class="small">No broadcasts sent yet.</p></div>
            <?php else: ?>
            <?php foreach ($history as $h):
                [$hBg,$hCl,$hIc] = $typeColors[$h['type']??'info'];
            ?>
            <div class="hist-card">
                <div class="d-flex gap-3 align-items-start">
                    <div style="width:38px;height:38px;border-radius:10px;background:<?=$hBg?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="<?=$hIc?>" style="color:<?=$hCl?>;"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-body" style="font-size:0.87rem;"><?= htmlspecialchars($h['title']) ?></div>
                        <div class="text-muted" style="font-size:0.78rem;margin-top:0.15rem;"><?= htmlspecialchars(substr($h['message'],0,100)) ?>...</div>
                        <div class="d-flex gap-2 mt-2 align-items-center flex-wrap">
                            <span style="font-size:0.68rem;background:#f1f5f9;color:#475569;padding:0.15rem 0.5rem;border-radius:50px;">Target: <?= ucfirst($h['target_role']) ?></span>
                            <span style="font-size:0.68rem;color:#94a3b8;" title="Portal Notifications"><i class="fas fa-bell me-1"></i><?= $h['sent_count'] ?></span>
                            <?php if (!empty($h['send_email'])): ?>
                                <span style="font-size:0.68rem;color:#94a3b8;" title="Emails Sent"><i class="fas fa-envelope me-1"></i><?= $h['email_sent_count'] ?? 0 ?></span>
                            <?php endif; ?>
                            <?php if (!empty($h['send_sms'])): ?>
                                <span style="font-size:0.68rem;color:#94a3b8;" title="SMS Sent"><i class="fas fa-sms me-1"></i><?= $h['sms_sent_count'] ?? 0 ?></span>
                            <?php endif; ?>
                            <span style="font-size:0.68rem;color:#94a3b8;"><?= date('M d, H:i', strtotime($h['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const typeIcons = { info:'fas fa-info-circle', warning:'fas fa-exclamation-triangle', success:'fas fa-check-circle', alert:'fas fa-bell' };
const typeBgs   = { info:'#eff6ff', warning:'#fef3c7', success:'#dcfce7', alert:'#fef2f2' };
const typeCls   = { info:'#1d4ed8', warning:'#d97706', success:'#15803d', alert:'#dc2626' };

function selectAudience(val, el) {
    document.getElementById('bc_target').value = val;
    document.querySelectorAll('.audience-btn').forEach(b=>b.classList.remove('selected'));
    el.classList.add('selected');
}
function selectType(val, el, cl, bg) {
    document.getElementById('bc_type').value = val;
    document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('selected'));
    el.classList.add('selected');
    el.style.setProperty('--tc',cl); el.style.setProperty('--tbg',bg);
    updatePreviewIcon();
}
function updatePreviewIcon() {
    const type = document.getElementById('bc_type').value;
    const wrap = document.getElementById('prev_icon_wrap');
    wrap.style.background = typeBgs[type];
    wrap.innerHTML = `<i class="${typeIcons[type]}" style="color:${typeCls[type]};"></i>`;
}

function togglePassword(btn) {
    const input = btn.previousElementSibling;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.getElementById('bc_title').addEventListener('input', function() {
    document.getElementById('prev_title').textContent = this.value || '—';
});
document.getElementById('bc_message').addEventListener('input', function() {
    document.getElementById('prev_msg').textContent = this.value || 'Your message will appear here...';
    document.getElementById('char_count').textContent = this.value.length;
});

function sendBroadcast() {
    const title  = document.getElementById('bc_title').value.trim();
    const msg    = document.getElementById('bc_message').value.trim();
    const target = document.getElementById('bc_target').value;
    const type   = document.getElementById('bc_type').value;
    const sendEmail = document.getElementById('chan_email').checked;
    const sendSms = document.getElementById('chan_sms').checked;

    if (!title||!msg) { Swal.fire({icon:'warning',title:'Required',text:'Title and message cannot be empty.',confirmButtonColor:'#0A2D5E'}); return; }

    const targetLabels = {all:'All Users',user:'All Clients',agent:'All Partners',developer:'All Developers'};
    
    let chText = "Portal";
    if (sendEmail) chText += ", Email";
    if (sendSms) chText += ", SMS";

    Swal.fire({
        title:'Confirm Broadcast',
        html:`Send via <strong>${chText}</strong> to <strong>${targetLabels[target]}</strong>?<br><br><em style="font-size:0.85rem;">"${title}"</em>`,
        icon:'warning',showCancelButton:true,confirmButtonText:'Start Sending',confirmButtonColor:'#E15501'
    }).then(r=>{
        if(!r.isConfirmed) return;
        
        document.getElementById('sendBtn').disabled = true;
        document.getElementById('bc_progress_container').style.display = 'block';
        
        // 1. Get targets and create record
        fetch('broadcast.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`ajax_action=get_target_list&title=${encodeURIComponent(title)}&message=${encodeURIComponent(msg)}&target_role=${target}&type=${type}&send_email=${sendEmail}&send_sms=${sendSms}`})
        .then(r=>r.json()).then(d=>{
            if(!d.success) {
                Swal.fire({icon:'error',title:'Failed',text:d.message||'Could not prepare broadcast.',confirmButtonColor:'#dc3545'});
                return;
            }

            const users = d.users;
            const total = d.count;
            const broadcast_id = d.broadcast_id;

            if (total === 0) {
                Swal.fire({icon:'info', title:'No Users', text:'No users found in this group.'});
                document.getElementById('bc_progress_container').style.display = 'none';
                document.getElementById('sendBtn').disabled = false;
                return;
            }

            // 2. Start Batch Processing
            let currentIdx = 0;
            let successCount = 0;
            let errorCount = 0;

            document.getElementById('bc_status_text').textContent = `Sending 1 of ${total}...`;

            function sendNext() {
                if (currentIdx >= total) {
                    Swal.fire({icon:'success',title:'Broadcast Complete!',html:`Processed <strong>${total}</strong> users.<br>Success: ${successCount}<br>Errors: ${errorCount}`,confirmButtonColor:'#0A2D5E'}).then(()=>location.reload());
                    return;
                }

                const u = users[currentIdx];
                const pct = Math.floor((currentIdx / total) * 100);
                document.getElementById('bc_percent').textContent = pct + '%';
                document.getElementById('bc_progress_bar').style.width = pct + '%';
                document.getElementById('bc_status_text').textContent = `Sending ${currentIdx + 1} of ${total} (to ${u.name})...`;

                fetch('broadcast.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:`ajax_action=send_individual_broadcast&broadcast_id=${broadcast_id}&user_id=${u.id}&name=${encodeURIComponent(u.name)}&email=${encodeURIComponent(u.email)}&phone=${encodeURIComponent(u.phone)}&title=${encodeURIComponent(title)}&message=${encodeURIComponent(msg)}&send_email=${sendEmail}&send_sms=${sendSms}`})
                .then(r=>r.json()).then(res=>{
                    if (res.success && res.errors.length === 0) successCount++;
                    else errorCount++;
                    
                    document.getElementById('bc_sent_count').textContent = `${successCount} sent`;
                    if (errorCount > 0) document.getElementById('bc_error_count').textContent = `${errorCount} errors`;

                    currentIdx++;
                    sendNext(); // Loop to next item
                }).catch(err => {
                    errorCount++;
                    currentIdx++;
                    sendNext();
                });
            }

            sendNext();
        });
    });
}

function saveGatewaySettings(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams(fd);
    params.append('ajax_action', 'save_gateway_settings');
    const btn = document.getElementById('saveGatewayBtn');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    btn.disabled = true;

    fetch('broadcast.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
    .then(r => r.json()).then(d => {
        btn.innerHTML = origHtml; btn.disabled = false;
        if (d.success) Swal.fire({ icon: 'success', title: 'Saved', text: 'Gateway settings saved successfully!', timer: 2000, showConfirmButton: false });
        else Swal.fire({ icon: 'error', title: 'Error', text: d.message || 'Failed to save settings.' });
    }).catch(err => {
        btn.innerHTML = origHtml; btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error occurred.' });
    });
}

function testSMTP() {
    const email = document.getElementById('test_smtp_email').value.trim();
    if (!email) { Swal.fire({icon:'warning',text:'Please enter an email address.'}); return; }
    Swal.fire({title:'Sending...', text:'Testing SMTP connection...', allowOutsideClick:false, didOpen:()=>{Swal.showLoading()}});
    fetch('broadcast.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax_action=test_smtp&email=${encodeURIComponent(email)}` })
    .then(r => r.json()).then(d => {
        if (d.success) Swal.fire({ icon: 'success', title: 'SMTP Test Passed', text: 'Test email sent successfully.' });
        else Swal.fire({ icon: 'error', title: 'SMTP Test Failed', text: d.message });
    }).catch(err => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error occurred.' }));
}

function testSMS() {
    const phone = document.getElementById('test_sms_phone').value.trim();
    if (!phone) { Swal.fire({icon:'warning',text:'Please enter a phone number.'}); return; }
    Swal.fire({title:'Sending...', text:'Testing Termii SMS Gateway...', allowOutsideClick:false, didOpen:()=>{Swal.showLoading()}});
    fetch('broadcast.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax_action=test_sms&phone=${encodeURIComponent(phone)}` })
    .then(r => r.json()).then(d => {
        if (d.success) Swal.fire({ icon: 'success', title: 'SMS Test Passed', text: `Test SMS sent! Message ID: ${d.message_id}` });
        else Swal.fire({ icon: 'error', title: 'SMS Test Failed', text: d.message });
    }).catch(err => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error occurred.' }));
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
