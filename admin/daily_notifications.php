<?php
$path_to_root = "../";
$page_title = "AI Daily Notifications";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$adminId = $headerUser['id'];

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'send_now') {
        // Trigger immediate notification
        $targetIndustry = !empty($_POST['industry']) ? trim($_POST['industry']) : null;
        $targetRole = !empty($_POST['role']) ? trim($_POST['role']) : null;
        
        $secret = 'wqs_daily_ai_cron_2026_SecureKey!';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        $url = $protocol . $host . $baseDir . '/cron_daily_ai_notifications.php?secret=' . $secret . '&test=1';
        if ($targetIndustry) $url .= '&industry=' . urlencode($targetIndustry);
        if ($targetRole) $url .= '&role=' . urlencode($targetRole);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['success' => false, 'message' => 'Curl error: ' . $error]);
        } else {
            $result = json_decode($response, true);
            echo json_encode($result ?: ['success' => false, 'message' => 'Invalid response']);
        }
        exit;
    }

    if ($action === 'preview') {
        // Generate AI content without sending
        $targetIndustry = !empty($_POST['industry']) ? trim($_POST['industry']) : null;
        $targetRole = !empty($_POST['role']) ? trim($_POST['role']) : null;
        
        // Fetch dynamic AI provider
        $aiStmt = $pdo->query("SELECT api_endpoint, api_key, default_model FROM ai_providers WHERE is_active = 1 LIMIT 1");
        $aiConfig = $aiStmt->fetch(PDO::FETCH_ASSOC);
        if (!$aiConfig) {
            echo json_encode(['success' => false, 'message' => 'No active AI provider configured']);
            exit;
        }
        $apiKey = $aiConfig['api_key'];
        $apiEndpoint = $aiConfig['api_endpoint'];
        $apiModel = $aiConfig['default_model'];
        $services = $pdo->query("SELECT name, description FROM services WHERE category='service' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $pricing = $pdo->query("SELECT name, price, price_label FROM services WHERE category='pricing' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        
        $today = date('l, F j, Y');
        $targetContext = "";
        if ($targetIndustry) $targetContext .= "Target industry: {$targetIndustry}. ";
        if ($targetRole) $targetContext .= "Target audience role: {$targetRole}. ";
        if (!$targetIndustry && !$targetRole) $targetContext = "General audience. ";
        
        $servicesList = "";
        foreach ($services as $svc) {
            $servicesList .= "- {$svc['name']}: {$svc['description']}\n";
        }
        
        $prompt = "You are WiseBot AI for WQS software company.\n";
        $prompt .= "Generate ONE daily push notification. Title (max 50 chars, no emojis) and Body (max 150 chars).\n";
        $prompt .= "Target: {$targetContext} Today: {$today}\n";
        $prompt .= "Services:\n{$servicesList}\n";
        $prompt .= "Output JSON: {\"title\": \"...\", \"body\": \"...\"}";
        
        $ch = curl_init($apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $apiModel,
                'messages' => [
                    ['role' => 'system', 'content' => 'Output only valid JSON. Professional marketing copywriter.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.8,
                'max_tokens' => 200
            ]),
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '{}';
        
        if (preg_match('/\{[^{}]*\}/s', $content, $m)) {
            $notif = json_decode($m[0], true);
        } else {
            $notif = ['title' => 'Preview unavailable', 'body' => 'Try again later'];
        }
        
        echo json_encode(['success' => true, 'title' => $notif['title'] ?? '', 'body' => $notif['body'] ?? '']);
        exit;
    }
}

// Fetch stats
$stats = ['total_sent' => 0, 'today_sent' => 0, 'total_users' => 0];
try {
    $stats['total_sent'] = (int)$pdo->query("SELECT COUNT(*) FROM daily_ai_notifications WHERE status='sent'")->fetchColumn();
    $stats['today_sent'] = (int)$pdo->query("SELECT COUNT(*) FROM daily_ai_notifications WHERE status='sent' AND DATE(sent_at) = CURDATE()")->fetchColumn();
    $stats['total_users'] = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_fcm_tokens")->fetchColumn();
} catch (Exception $e) {}

// Fetch industries for targeting
$industries = ['Education', 'Healthcare', 'Fintech', 'E-commerce', 'Real Estate', 'Agriculture', 'Entertainment', 'Logistics'];
$roles = ['Business Owner', 'Startup Founder', 'Student', 'Professional', 'Agency Owner'];

// Fetch recent notifications
$recentStmt = $pdo->query("SELECT * FROM daily_ai_notifications ORDER BY created_at DESC LIMIT 20");
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.ai-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6); border-radius: 16px; }
.stat-card { background: linear-gradient(135deg, #0A2D5E 0%, #1e3a5f 100%); color: white; border-radius: 14px; padding: 1.2rem; }
.stat-card .stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
.stat-card .stat-label { font-size: 0.78rem; opacity: 0.8; margin-top: 0.3rem; }
.preview-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 1.2rem; min-height: 80px; }
.notif-badge { font-size: 0.7rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 600; }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-body mb-1" style="font-size:1.3rem;">
                <i class="fas fa-robot me-2" style="color:#0A2D5E;"></i>AI Daily Notifications
            </h4>
            <p class="text-muted small mb-0">WiseBot generates and sends personalized service notifications to users daily.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-success-subtle text-success"><i class="fas fa-clock me-1"></i>Next: <?= date('M d, Y 9:00 AM') ?></span>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['today_sent'] ?></div>
                <div class="stat-label">Sent Today</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                <div class="stat-value"><?= $stats['total_sent'] ?></div>
                <div class="stat-label">Total Sent</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                <div class="stat-value">AI</div>
                <div class="stat-label">Powered by GPT-4o</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Send Notification -->
        <div class="col-lg-5">
            <div class="ai-card p-4 shadow-sm mb-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-paper-plane me-2 text-primary"></i>Send AI Notification</h6>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Target Industry</label>
                    <select id="targetIndustry" class="form-select form-select-sm" style="border-radius:10px;">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $ind): ?>
                            <option value="<?= $ind ?>"><?= $ind ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Target Role</label>
                    <select id="targetRole" class="form-select form-select-sm" style="border-radius:10px;">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button id="btnPreview" class="btn btn-outline-secondary btn-sm flex-fill" style="border-radius:10px;">
                        <i class="fas fa-eye me-1"></i> Preview
                    </button>
                    <button id="btnSendNow" class="btn btn-primary btn-sm flex-fill" style="border-radius:10px; background: #0A2D5E;">
                        <i class="fas fa-bolt me-1"></i> Send Now
                    </button>
                </div>

                <div class="preview-box" id="previewBox">
                    <div class="text-center text-muted small" id="previewPlaceholder">
                        <i class="fas fa-magic me-1"></i>Click "Preview" to generate AI content
                    </div>
                    <div id="previewContent" style="display:none;">
                        <div class="fw-bold text-body" id="previewTitle"></div>
                        <div class="text-muted small mt-1" id="previewBody"></div>
                    </div>
                </div>
            </div>

            <!-- How it works -->
            <div class="ai-card p-4 shadow-sm">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-info-circle me-2 text-info"></i>How It Works</h6>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <span class="badge bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:22px;height:22px;font-size:0.7rem;">1</span>
                        <span>WiseBot AI generates a compelling notification based on your services and current day theme</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <span class="badge bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:22px;height:22px;font-size:0.7rem;">2</span>
                        <span>Notifications are targeted by user industry and role from their survey preferences</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <span class="badge bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:22px;height:22px;font-size:0.7rem;">3</span>
                        <span>Sent via Firebase Cloud Messaging (browser push) + stored in notification center</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <span class="badge bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:22px;height:22px;font-size:0.7rem;">4</span>
                        <span>Cron runs daily at 9:00 AM. Duplicate sends are prevented automatically.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Notification History -->
        <div class="col-lg-7">
            <div class="ai-card p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-body mb-0"><i class="fas fa-history me-2 text-secondary"></i>Notification History</h6>
                    <span class="badge bg-light text-secondary"><?= count($recent) ?> records</span>
                </div>

                <?php if (empty($recent)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2" style="opacity:0.3;"></i>
                        <p class="small mb-0">No notifications sent yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Target</th>
                                    <th>Sent</th>
                                    <th>Users</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-body"><?= htmlspecialchars($row['title']) ?></div>
                                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars(substr($row['message'], 0, 60)) ?>...</div>
                                    </td>
                                    <td>
                                        <?php if ($row['target_industry']): ?>
                                            <span class="notif-badge bg-info-subtle text-info"><?= htmlspecialchars($row['target_industry']) ?></span>
                                        <?php else: ?>
                                            <span class="notif-badge bg-secondary-subtle text-secondary">All</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted" style="font-size:0.75rem;">
                                        <?= $row['sent_at'] ? date('M d, g:i A', strtotime($row['sent_at'])) : '—' ?>
                                    </td>
                                    <td class="fw-semibold"><?= (int)$row['sent_to_count'] ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'sent'): ?>
                                            <span class="notif-badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Sent</span>
                                        <?php elseif ($row['status'] === 'failed'): ?>
                                            <span class="notif-badge bg-danger-subtle text-danger"><i class="fas fa-times me-1"></i>Failed</span>
                                        <?php else: ?>
                                            <span class="notif-badge bg-warning-subtle text-warning"><?= $row['status'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnPreview')?.addEventListener('click', async () => {
    const industry = document.getElementById('targetIndustry').value;
    const role = document.getElementById('targetRole').value;
    const btn = document.getElementById('btnPreview');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';

    try {
        const fd = new FormData();
        fd.append('action', 'preview');
        if (industry) fd.append('industry', industry);
        if (role) fd.append('role', role);

        const res = await fetch('', { method: 'POST', body: fd });
        const j = await res.json();

        if (j.success) {
            document.getElementById('previewPlaceholder').style.display = 'none';
            document.getElementById('previewContent').style.display = 'block';
            document.getElementById('previewTitle').textContent = j.title;
            document.getElementById('previewBody').textContent = j.body;
        } else {
            Swal.fire('Error', j.message || 'Preview failed', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});

document.getElementById('btnSendNow')?.addEventListener('click', async () => {
    const industry = document.getElementById('targetIndustry').value;
    const role = document.getElementById('targetRole').value;

    const result = await Swal.fire({
        title: 'Send AI Notification Now?',
        html: `<div class="text-start">
            <p class="text-muted mb-2">WiseBot will generate a fresh notification and send it to all${industry ? ' <strong>' + industry + '</strong>' : ''}${role ? ' <strong>' + role + '</strong>' : ''} users.</p>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>This will use 1 AI API call and send FCM push notifications.</p>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0A2D5E',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-bolt me-1"></i> Send Now',
        customClass: { popup: 'swal2-border-radius' }
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnSendNow');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

    try {
        const fd = new FormData();
        fd.append('action', 'send_now');
        if (industry) fd.append('industry', industry);
        if (role) fd.append('role', role);

        const res = await fetch('', { method: 'POST', body: fd });
        const j = await res.json();

        if (j.success) {
            if (j.sent === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Target Users',
                    text: 'No users matched the selected criteria. 0 notifications sent.',
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Notifications Sent!',
                    html: `<p>AI notification "<strong>${j.title}</strong>" sent to <strong>${j.sent}</strong> users.</p>
                           <p class="text-muted small mb-0">${j.failed ? j.failed + ' failed.' : 'All delivered successfully.'}</p>`,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            }
        } else {
            Swal.fire('Error', j.message || 'Send failed', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
