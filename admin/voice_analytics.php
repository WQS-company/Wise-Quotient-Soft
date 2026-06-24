<?php
session_start();
$path_to_root = "../";
require_once $path_to_root . 'config.php';

if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Filters for voice logs
$log_provider_filter = $_GET['log_provider'] ?? '';
$log_type_filter = $_GET['log_type'] ?? '';
$log_status_filter = $_GET['log_status'] ?? '';

// Pagination for conversations
$conv_page = isset($_GET['conv_page']) ? max(1, intval($_GET['conv_page'])) : 1;
$conv_limit = 15;
$conv_offset = ($conv_page - 1) * $conv_limit;

// Pagination for logs
$log_page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
$log_limit = 15;
$log_offset = ($log_page - 1) * $log_limit;

// --- Statistics ---
$total_chats = 0;
$total_minutes = 0;
$most_used_provider = 'N/A';
$success_rate = 0;
$provider_chart_labels = [];
$provider_chart_data = [];
$conversations = [];
$conv_total_count = 0;
$logs = [];
$log_total_count = 0;
$all_providers = [];

if (isset($pdo)) {
    // Total chats
    try {
        $total_chats = (int) $pdo->query("SELECT COUNT(*) FROM voice_conversations")->fetchColumn();
    } catch (Exception $e) {}

    // Total minutes (audio_duration is in seconds)
    try {
        $total_seconds = (float) $pdo->query("SELECT COALESCE(SUM(audio_duration), 0) FROM voice_conversations")->fetchColumn();
        $total_minutes = round($total_seconds / 60, 1);
    } catch (Exception $e) {}

    // Most used provider
    try {
        $row = $pdo->query("SELECT provider_name, COUNT(*) as cnt FROM voice_conversations GROUP BY provider_name ORDER BY cnt DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) $most_used_provider = $row['provider_name'];
    } catch (Exception $e) {}

    // Success rate from voice_logs
    try {
        $total_logs_count = (int) $pdo->query("SELECT COUNT(*) FROM voice_logs")->fetchColumn();
        $success_logs_count = (int) $pdo->query("SELECT COUNT(*) FROM voice_logs WHERE status = 'success'")->fetchColumn();
        $success_rate = $total_logs_count > 0 ? round(($success_logs_count / $total_logs_count) * 100, 1) : 0;
    } catch (Exception $e) {}

    // Provider usage for chart
    try {
        $provider_rows = $pdo->query("SELECT provider_name, COUNT(*) as cnt FROM voice_conversations GROUP BY provider_name ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($provider_rows as $pr) {
            $provider_chart_labels[] = $pr['provider_name'];
            $provider_chart_data[] = (int) $pr['cnt'];
        }
    } catch (Exception $e) {}

    // Distinct provider names for filter dropdown
    try {
        $all_providers = $pdo->query("SELECT DISTINCT provider_name FROM voice_conversations UNION SELECT DISTINCT provider FROM voice_logs ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    // Recent conversations with user join
    try {
        $conv_sql = "SELECT vc.*, COALESCE(u.name, CONCAT('User #', vc.user_id)) as user_name
                     FROM voice_conversations vc
                     LEFT JOIN users u ON vc.user_id = u.id
                     ORDER BY vc.created_at DESC LIMIT ? OFFSET ?";
        $conv_stmt = $pdo->prepare($conv_sql);
        $conv_stmt->bindValue(1, $conv_limit, PDO::PARAM_INT);
        $conv_stmt->bindValue(2, $conv_offset, PDO::PARAM_INT);
        $conv_stmt->execute();
        $conversations = $conv_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $conversations = [];
    }

    try {
        $conv_total_count = (int) $pdo->query("SELECT COUNT(*) FROM voice_conversations")->fetchColumn();
    } catch (Exception $e) {}

    // Voice logs with filters
    try {
        $log_conditions = [];
        $log_params = [];

        if ($log_provider_filter !== '') {
            $log_conditions[] = "provider = ?";
            $log_params[] = $log_provider_filter;
        }
        if ($log_type_filter !== '') {
            $log_conditions[] = "request_type = ?";
            $log_params[] = $log_type_filter;
        }
        if ($log_status_filter !== '') {
            $log_conditions[] = "status = ?";
            $log_params[] = $log_status_filter;
        }

        $log_where = '';
        if (!empty($log_conditions)) {
            $log_where = "WHERE " . implode(" AND ", $log_conditions);
        }

        $log_sql = "SELECT * FROM voice_logs $log_where ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $log_stmt = $pdo->prepare($log_sql);
        $pi = 1;
        foreach ($log_params as $lp) {
            $log_stmt->bindValue($pi++, $lp);
        }
        $log_stmt->bindValue($pi++, $log_limit, PDO::PARAM_INT);
        $log_stmt->bindValue($pi++, $log_offset, PDO::PARAM_INT);
        $log_stmt->execute();
        $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count for pagination
        $count_sql = "SELECT COUNT(*) FROM voice_logs $log_where";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($log_params);
        $log_total_count = (int) $count_stmt->fetchColumn();
    } catch (Exception $e) {
        $logs = [];
    }
}

$conv_total_pages = max(1, ceil($conv_total_count / $conv_limit));
$log_total_pages = max(1, ceil($log_total_count / $log_limit));

$page_title = "Voice Agent Analytics - WQS Engine";
require_once $path_to_root . 'includes/dashboard_header.php';
?>

<style>
.bg-purple { background-color: #7c3aed !important; color: #fff !important; }
.text-purple { color: #7c3aed !important; }
.va-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f766e 100%);
    border-radius: 20px;
    padding: 3rem 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
}
.va-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
}
.va-hero::after {
    content: '\f3c5';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    bottom: -20%; right: 5%;
    font-size: 12rem;
    color: rgba(255,255,255,0.03);
    transform: rotate(-15deg);
}
.va-stat-card {
    border: none;
    border-radius: 16px;
    background: #ffffff;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    height: 100%;
}
.va-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
.va-stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
}
.va-chart-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.01);
}
.va-chart-card h5 {
    font-weight: 800;
    font-size: 0.98rem;
    color: #0f172a;
    margin-bottom: 1.25rem;
}
.va-chart-wrapper {
    position: relative;
    height: 300px;
    width: 100%;
}
.va-section-card {
    background: white;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.va-section-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fdfdfd;
}
.va-section-header h5 {
    font-weight: 800;
    font-size: 0.98rem;
    color: #0f172a;
    margin-bottom: 0;
}
.va-filter-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.va-filter-bar .form-select {
    max-width: 180px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    font-size: 0.85rem;
    height: 38px;
}
.va-filter-bar .form-select:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15,118,110,0.1);
}
.va-badge-success {
    background: #dcfce7;
    color: #166534;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.25rem 0.65rem;
    border-radius: 50px;
}
.va-badge-failed {
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.25rem 0.65rem;
    border-radius: 50px;
}
.va-badge-timeout {
    background: #fef3c7;
    color: #92400e;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.25rem 0.65rem;
    border-radius: 50px;
}
.va-badge-provider {
    background: #ede9fe;
    color: #5b21b6;
    font-weight: 700;
    font-size: 0.73rem;
    padding: 0.2rem 0.55rem;
    border-radius: 50px;
}
.va-badge-type {
    background: #e0f2fe;
    color: #075985;
    font-weight: 700;
    font-size: 0.73rem;
    padding: 0.2rem 0.55rem;
    border-radius: 50px;
}
.va-text-truncate {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}
.va-pagination-info {
    font-size: 0.82rem;
    color: #64748b;
}
@media (max-width: 991.98px) {
    .va-hero { padding: 1.8rem !important; border-radius: 16px; }
    .va-hero h2 { font-size: 1.4rem !important; }
    .va-chart-wrapper { height: 250px !important; }
}
@media (max-width: 767.98px) {
    .va-hero { padding: 1.4rem !important; border-radius: 14px; }
    .va-hero h2 { font-size: 1.2rem !important; }
    .va-chart-wrapper { height: 220px !important; }
    .va-filter-bar { flex-direction: column; align-items: stretch; }
    .va-filter-bar .form-select { max-width: 100% !important; }
    .va-section-header { flex-direction: column !important; gap: 0.75rem !important; }
    table { font-size: 0.78rem !important; }
    th, td { padding: 0.5rem !important; }
}
</style>

<div class="container-fluid px-3 px-md-4 pb-5">

    <!-- Hero -->
    <div class="va-hero">
        <div class="row align-items-center position-relative" style="z-index: 1;">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-headset text-info me-2"></i> Voice Analytics
                </div>
                <h2 class="fw-bold mb-2">Voice Agent Analytics</h2>
                <p class="mb-0" style="opacity: 0.9; font-size: 1.05rem; max-width: 600px;">
                    Monitor WiseBot voice conversation performance, provider usage, and speech processing metrics.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <a href="voice_providers.php" class="btn btn-premium rounded-pill px-4 py-2" style="background:#ffffff;color:#0f766e;font-weight:600;border:none;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    <i class="fas fa-cog me-2"></i> Manage Providers
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="va-stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="va-stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-comments"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Voice Chats</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= number_format($total_chats) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="va-stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="va-stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Minutes Used</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= number_format($total_minutes, 1) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="va-stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="va-stat-icon bg-purple bg-opacity-10 text-purple"><i class="fas fa-microphone"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Most Used Provider</div>
                        <div class="h6 fw-bold mb-0 text-body-emphasis text-truncate" style="max-width:140px;"><?= htmlspecialchars($most_used_provider) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="va-stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="va-stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Success Rate</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= $success_rate ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Provider Usage Chart -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="va-chart-card">
                <h5><i class="fas fa-chart-bar text-success me-2"></i> Provider Usage Breakdown</h5>
                <?php if (empty($provider_chart_labels)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3"><i class="fas fa-chart-bar text-muted" style="font-size:2.5rem;opacity:0.3;"></i></div>
                        <p class="text-muted">No voice conversation data available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="va-chart-wrapper">
                        <canvas id="providerChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Voice Conversations -->
    <div class="va-section-card">
        <div class="va-section-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
            <h5 class="mb-0"><i class="fas fa-comments me-2 text-success"></i> Recent Voice Conversations</h5>
            <div class="va-pagination-info">
                Showing <?= $conv_total_count > 0 ? $conv_offset + 1 : 0 ?>-<?= min($conv_total_count, $conv_offset + $conv_limit) ?> of <?= number_format($conv_total_count) ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:130px;">User</th>
                        <th style="width:110px;">Provider</th>
                        <th>Text</th>
                        <th class="text-center" style="width:100px;">STT (ms)</th>
                        <th class="text-center" style="width:100px;">TTS (ms)</th>
                        <th class="text-center" style="width:90px;">Status</th>
                        <th class="pe-3" style="width:150px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($conversations)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-microphone-alt d-block mb-3" style="font-size:2.5rem;opacity:0.3;"></i>
                                No voice conversations recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-body" style="font-size:0.88rem;"><?= htmlspecialchars($conv['user_name']) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;">ID: #<?= $conv['user_id'] ?></div>
                                </td>
                                <td><span class="va-badge-provider"><?= htmlspecialchars($conv['provider_name'] ?: 'N/A') ?></span></td>
                                <td>
                                    <span class="va-text-truncate text-muted" title="<?= htmlspecialchars($conv['conversation_text'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($conv['conversation_text'] ?? '', 0, 120, '...')) ?>
                                    </span>
                                </td>
                                <td class="text-center fw-semibold"><?= number_format($conv['stt_duration']) ?></td>
                                <td class="text-center fw-semibold"><?= number_format($conv['tts_duration']) ?></td>
                                <td class="text-center">
                                    <?php if ($conv['status'] === 'completed'): ?>
                                        <span class="va-badge-success"><i class="fas fa-check me-1"></i>Success</span>
                                    <?php elseif ($conv['status'] === 'active'): ?>
                                        <span class="va-badge-type"><i class="fas fa-spinner me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="va-badge-failed"><i class="fas fa-times me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-muted" style="white-space:nowrap;"><?= date('M j, Y - g:i A', strtotime($conv['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($conv_total_pages > 1): ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 p-3 border-top">
            <div class="va-pagination-info">
                Page <?= $conv_page ?> of <?= $conv_total_pages ?>
            </div>
            <nav aria-label="Conversations pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $conv_page === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?conv_page=<?= max(1, $conv_page - 1) ?>#conversations"><i class="fas fa-angle-left"></i></a>
                    </li>
                    <?php
                    $conv_start = max(1, $conv_page - 2);
                    $conv_end = min($conv_total_pages, $conv_page + 2);
                    for ($i = $conv_start; $i <= $conv_end; $i++):
                    ?>
                        <li class="page-item <?= $conv_page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?conv_page=<?= $i ?>#conversations"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $conv_page === $conv_total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?conv_page=<?= min($conv_total_pages, $conv_page + 1) ?>#conversations"><i class="fas fa-angle-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- Voice Logs Section -->
    <div class="va-section-card" id="logs">
        <div class="va-section-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
            <h5 class="mb-0"><i class="fas fa-list-alt me-2 text-success"></i> Voice Logs</h5>
            <form method="GET" class="va-filter-bar">
                <select name="log_provider" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Providers</option>
                    <?php foreach ($all_providers as $ap): ?>
                        <option value="<?= htmlspecialchars($ap) ?>" <?= $log_provider_filter === $ap ? 'selected' : '' ?>><?= htmlspecialchars($ap) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="log_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="stt" <?= $log_type_filter === 'stt' ? 'selected' : '' ?>>STT</option>
                    <option value="tts" <?= $log_type_filter === 'tts' ? 'selected' : '' ?>>TTS</option>
                    <option value="s2s" <?= $log_type_filter === 's2s' ? 'selected' : '' ?>>S2S</option>
                    <option value="health" <?= $log_type_filter === 'health' ? 'selected' : '' ?>>Health</option>
                </select>
                <select name="log_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="success" <?= $log_status_filter === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $log_status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="timeout" <?= $log_status_filter === 'timeout' ? 'selected' : '' ?>>Timeout</option>
                </select>
                <?php if ($log_provider_filter || $log_type_filter || $log_status_filter): ?>
                    <a href="#logs" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fas fa-times me-1"></i>Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:130px;">Provider</th>
                        <th style="width:100px;">Request Type</th>
                        <th class="text-center" style="width:90px;">Status</th>
                        <th class="text-center" style="width:110px;">Duration (ms)</th>
                        <th>Error</th>
                        <th class="pe-3" style="width:150px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-list d-block mb-3" style="font-size:2.5rem;opacity:0.3;"></i>
                                No voice logs found matching the selected filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-3"><span class="va-badge-provider"><?= htmlspecialchars($log['provider']) ?></span></td>
                                <td><span class="va-badge-type"><?= strtoupper($log['request_type']) ?></span></td>
                                <td class="text-center">
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="va-badge-success"><i class="fas fa-check me-1"></i>Success</span>
                                    <?php elseif ($log['status'] === 'timeout'): ?>
                                        <span class="va-badge-timeout"><i class="fas fa-clock me-1"></i>Timeout</span>
                                    <?php else: ?>
                                        <span class="va-badge-failed"><i class="fas fa-times me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold"><?= number_format($log['duration_ms']) ?></td>
                                <td>
                                    <?php if (!empty($log['error_message'])): ?>
                                        <span class="text-danger small" title="<?= htmlspecialchars($log['error_message']) ?>">
                                            <i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars(mb_strimwidth($log['error_message'], 0, 80, '...')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-muted" style="white-space:nowrap;"><?= date('M j, Y - g:i A', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($log_total_pages > 1): ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 p-3 border-top">
            <div class="va-pagination-info">
                Page <?= $log_page ?> of <?= $log_total_pages ?> (<?= number_format($log_total_count) ?> total)
            </div>
            <nav aria-label="Logs pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $log_page === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= http_build_query(array_merge($_GET, ['log_page' => max(1, $log_page - 1)])) ?>#logs"><i class="fas fa-angle-left"></i></a>
                    </li>
                    <?php
                    $log_start = max(1, $log_page - 2);
                    $log_end = min($log_total_pages, $log_page + 2);
                    for ($i = $log_start; $i <= $log_end; $i++):
                    ?>
                        <li class="page-item <?= $log_page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= http_build_query(array_merge($_GET, ['log_page' => $i])) ?>#logs"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $log_page === $log_total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= http_build_query(array_merge($_GET, ['log_page' => min($log_total_pages, $log_page + 1)])) ?>#logs"><i class="fas fa-angle-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if (!empty($provider_chart_labels)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('providerChart').getContext('2d');
    const labels = <?= json_encode($provider_chart_labels) ?>;
    const data = <?= json_encode($provider_chart_data) ?>;

    const colors = [
        '#0f766e', '#7c3aed', '#2563eb', '#ea580c', '#16a34a',
        '#dc2626', '#0891b2', '#c026d3', '#ca8a04', '#4f46e5'
    ];
    const bgColors = labels.map((_, i) => colors[i % colors.length]);
    const borderColors = labels.map((_, i) => colors[i % colors.length]);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Conversations',
                data: data,
                backgroundColor: bgColors.map(c => c + 'cc'),
                borderColor: borderColors,
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 13 },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' conversations';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                        color: '#64748b',
                        precision: 0
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                        color: '#334155'
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
