<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || !in_array(strtolower($userRoleObj['role']), ['admin','developer'])) {
    header("Location: " . $path_to_root . "login.php"); exit;
}

$page_title = "Scholarship Settings";
$current_page = "scholarship_settings.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settingsData = [];
        foreach ($_POST as $key => $value) {
            if ($key === 'save_settings') continue;
            $settingsData[$key] = $value;
        }
        $settingsJson = json_encode($settingsData);
        $check = $pdo->query("SELECT COUNT(*) FROM scholarship_settings WHERE id=1")->fetchColumn();
        if ($check > 0) {
            $stmt = $pdo->prepare("UPDATE scholarship_settings SET settings_json=?, updated_at=NOW() WHERE id=1");
            $stmt->execute([$settingsJson]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarship_settings (settings_json, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([$settingsJson]);
        }
        $_SESSION['success_message'] = 'Settings saved successfully!';
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to save settings: ' . $e->getMessage();
    }
    header("Location: scholarship_settings.php");
    exit;
}

$settings = [];
try {
    $row = $pdo->query("SELECT settings_json FROM scholarship_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['settings_json']) {
        $settings = json_decode($row['settings_json'], true) ?: [];
    }
} catch (Exception $e) {}

$s = function($key, $default = '') use ($settings) {
    return isset($settings[$key]) ? $settings[$key] : $default;
};
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.scs-card{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden;margin-bottom:1.5rem}
.scs-card-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:.75rem}
.scs-card-header h5{font-weight:700;font-size:1rem;margin:0}
.scs-card-body{padding:1.5rem}
.scs-label{font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block}
.scs-input{border:1px solid var(--color-border);border-radius:10px;padding:.6rem .9rem;font-size:.88rem;width:100%;transition:border-color .2s;background:var(--color-card-bg);color:var(--color-text)}
.scs-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.scs-toggle{position:relative;width:44px;height:24px;display:inline-block;cursor:pointer}
.scs-toggle input{opacity:0;width:0;height:0}
.scs-toggle .slider{position:absolute;inset:0;border-radius:12px;transition:background .3s;background:#cbd5e1}
.scs-toggle input:checked + .slider{background:#10b981}
.scs-toggle .slider::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transition:transform .3s}
.scs-toggle input:checked + .slider::before{transform:translateX(20px)}
.scs-divider{border:none;border-top:1px solid var(--color-border);margin:1.5rem 0}
.scs-section-title{font-size:.82rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-cog me-2"></i>Scholarship Settings</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Configure scholarship system preferences and notifications</p>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<form method="POST" id="settingsForm">
<div class="row g-4">
    <!-- General Settings -->
    <div class="col-lg-6">
        <div class="scs-card">
            <div class="scs-card-header">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(59,130,246,.12);display:flex;align-items:center;justify-content:center;color:#3b82f6;font-size:.9rem"><i class="fas fa-cog"></i></div>
                <h5>General Settings</h5>
            </div>
            <div class="scs-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="scs-label">Application Code Prefix</label>
                        <input type="text" name="app_prefix" class="scs-input" value="<?= htmlspecialchars($s('app_prefix', 'SCH')) ?>" placeholder="e.g. SCH">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Default Currency</label>
                        <select name="default_currency" class="scs-input">
                            <option value="NGN" <?= $s('default_currency') === 'NGN' ? 'selected' : '' ?>>NGN (₦)</option>
                            <option value="USD" <?= $s('default_currency') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                            <option value="GBP" <?= $s('default_currency') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                            <option value="EUR" <?= $s('default_currency') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Max Applications Per Student</label>
                        <input type="number" name="max_applications" class="scs-input" value="<?= htmlspecialchars($s('max_applications', '3')) ?>" min="1" max="10">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Application Fee (₦)</label>
                        <input type="number" name="application_fee" class="scs-input" value="<?= htmlspecialchars($s('application_fee', '0')) ?>" min="0" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="scs-label">Auto-Close After Deadline</label>
                        <label class="scs-toggle">
                            <input type="checkbox" name="auto_close" value="1" <?= $s('auto_close', '1') === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="font-size:.78rem;color:#64748b;margin-left:.5rem">Automatically close applications when deadline passes</span>
                    </div>
                    <div class="col-12">
                        <label class="scs-label">Require Email Verification</label>
                        <label class="scs-toggle">
                            <input type="checkbox" name="require_email_verify" value="1" <?= $s('require_email_verify', '1') === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="font-size:.78rem;color:#64748b;margin-left:.5rem">Students must verify email before applying</span>
                    </div>
                    <div class="col-12">
                        <label class="scs-label">Allow Late Applications</label>
                        <label class="scs-toggle">
                            <input type="checkbox" name="allow_late" value="1" <?= $s('allow_late', '0') === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="font-size:.78rem;color:#64748b;margin-left:.5rem">Accept applications after deadline with penalty</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scoring Settings -->
        <div class="scs-card">
            <div class="scs-card-header">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(139,92,246,.12);display:flex;align-items:center;justify-content:center;color:#8b5cf6;font-size:.9rem"><i class="fas fa-star"></i></div>
                <h5>Scoring Weights</h5>
            </div>
            <div class="scs-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="scs-label">Academic Score Weight (%)</label>
                        <input type="number" name="weight_academic" class="scs-input" value="<?= htmlspecialchars($s('weight_academic', '30')) ?>" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Financial Need Weight (%)</label>
                        <input type="number" name="weight_financial" class="scs-input" value="<?= htmlspecialchars($s('weight_financial', '25')) ?>" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Leadership Weight (%)</label>
                        <input type="number" name="weight_leadership" class="scs-input" value="<?= htmlspecialchars($s('weight_leadership', '15')) ?>" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Community Service Weight (%)</label>
                        <input type="number" name="weight_community" class="scs-input" value="<?= htmlspecialchars($s('weight_community', '15')) ?>" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Personal Statement Weight (%)</label>
                        <input type="number" name="weight_statement" class="scs-input" value="<?= htmlspecialchars($s('weight_statement', '15')) ?>" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="scs-label">Auto-Shortlist Threshold</label>
                        <input type="number" name="shortlist_threshold" class="scs-input" value="<?= htmlspecialchars($s('shortlist_threshold', '70')) ?>" min="0" max="100">
                        <span style="font-size:.72rem;color:#94a3b8">Score above which applicants are auto-shortlisted</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Settings -->
    <div class="col-lg-6">
        <!-- Email Notifications -->
        <div class="scs-card">
            <div class="scs-card-header">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;color:#10b981;font-size:.9rem"><i class="fas fa-envelope"></i></div>
                <h5>Email Notifications</h5>
            </div>
            <div class="scs-card-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Application Received</div>
                            <div style="font-size:.72rem;color:#94a3b8">Send confirmation when student applies</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_application_received" value="1" <?= $s('email_application_received', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Status Update</div>
                            <div style="font-size:.72rem;color:#94a3b8">Notify when application status changes</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_status_update" value="1" <?= $s('email_status_update', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Interview Scheduled</div>
                            <div style="font-size:.72rem;color:#94a3b8">Send interview details to applicant</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_interview_scheduled" value="1" <?= $s('email_interview_scheduled', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Award Notification</div>
                            <div style="font-size:.72rem;color:#94a3b8">Notify when scholarship is awarded</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_award" value="1" <?= $s('email_award', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Payment Disbursed</div>
                            <div style="font-size:.72rem;color:#94a3b8">Notify when funds are disbursed</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_payment_disbursed" value="1" <?= $s('email_payment_disbursed', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Certificate Ready</div>
                            <div style="font-size:.72rem;color:#94a3b8">Notify when certificate is generated</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_certificate_ready" value="1" <?= $s('email_certificate_ready', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Rejection Notice</div>
                            <div style="font-size:.72rem;color:#94a3b8">Send polite rejection with feedback</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="email_rejection" value="1" <?= $s('email_rejection', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Notifications -->
        <div class="scs-card">
            <div class="scs-card-header">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:.9rem"><i class="fas fa-sms"></i></div>
                <h5>SMS Notifications</h5>
            </div>
            <div class="scs-card-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Enable SMS Notifications</div>
                            <div style="font-size:.72rem;color:#94a3b8">Master toggle for all SMS alerts</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="sms_enabled" value="1" <?= $s('sms_enabled', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Application Confirmation</div>
                            <div style="font-size:.72rem;color:#94a3b8">SMS on successful application</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="sms_application" value="1" <?= $s('sms_application', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Interview Reminder</div>
                            <div style="font-size:.72rem;color:#94a3b8">Send 24hrs before interview</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="sms_interview_reminder" value="1" <?= $s('sms_interview_reminder', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Award Alert</div>
                            <div style="font-size:.72rem;color:#94a3b8">SMS when scholarship is awarded</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="sms_award" value="1" <?= $s('sms_award', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Payment Alert</div>
                            <div style="font-size:.72rem;color:#94a3b8">SMS when payment is disbursed</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="sms_payment" value="1" <?= $s('sms_payment', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Notifications -->
        <div class="scs-card">
            <div class="scs-card-header">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;color:#ef4444;font-size:.9rem"><i class="fas fa-bell"></i></div>
                <h5>Admin Notifications</h5>
            </div>
            <div class="scs-card-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">New Application Alert</div>
                            <div style="font-size:.72rem;color:#94a3b8">Email admin when new application arrives</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="admin_new_application" value="1" <?= $s('admin_new_application', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-completed align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Daily Summary</div>
                            <div style="font-size:.72rem;color:#94a3b8">Send daily activity summary to admin</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="admin_daily_summary" value="1" <?= $s('admin_daily_summary', '0') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <hr class="scs-divider" style="margin:.5rem 0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:.85rem;font-weight:600">Low Slot Warning</div>
                            <div style="font-size:.72rem;color:#94a3b8">Alert when scholarship slots are running low</div>
                        </div>
                        <label class="scs-toggle"><input type="checkbox" name="admin_low_slots" value="1" <?= $s('admin_low_slots', '1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="text-end mt-4 mb-5">
    <button type="submit" name="save_settings" class="btn btn-primary rounded-pill px-5 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.95rem">
        <i class="fas fa-save me-2"></i> Save Settings
    </button>
</div>
</form>

</div>

<script>
document.querySelectorAll('.scs-toggle input').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const slider = this.nextElementSibling;
        if (slider) slider.style.background = this.checked ? '#10b981' : '#cbd5e1';
    });
    if (toggle.checked) {
        const slider = toggle.nextElementSibling;
        if (slider) slider.style.background = '#10b981';
    }
});

document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
    btn.disabled = true;
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
