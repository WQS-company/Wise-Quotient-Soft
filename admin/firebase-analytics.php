<?php
$page_title = 'Firebase Analytics & FCM Dashboard';
$path_to_root = '../';

require_once $path_to_root . 'config.php';

// Only allow admins
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Helper to read current environment variables directly from file
function getEnvValues($path) {
    $values = [
        'FIREBASE_API_KEY' => '',
        'FIREBASE_AUTH_DOMAIN' => '',
        'FIREBASE_PROJECT_ID' => '',
        'FIREBASE_STORAGE_BUCKET' => '',
        'FIREBASE_MESSAGING_SENDER_ID' => '',
        'FIREBASE_APP_ID' => '',
        'FIREBASE_MEASUREMENT_ID' => '',
        'FIREBASE_VAPID_KEY' => '',
        'FIREBASE_SERVER_KEY' => ''
    ];
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, "\"'"); // strip outer quotes
            if (array_key_exists($name, $values)) {
                $values[$name] = $value;
            }
        }
    }
    return $values;
}

// Helper to write environment variable modifications back to .env
function updateEnvFile($path, $newData) {
    if (!file_exists($path)) {
        $content = "";
    } else {
        $content = file_get_contents($path);
    }

    $lines = explode("\n", $content);
    $keysUpdated = [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }
        
        list($key, $val) = explode('=', $trimmed, 2);
        $key = trim($key);
        
        if (array_key_exists($key, $newData)) {
            $lines[$i] = $key . '="' . $newData[$key] . '"';
            $keysUpdated[$key] = true;
        }
    }

    // Append keys that weren't found
    foreach ($newData as $key => $val) {
        if (!isset($keysUpdated[$key])) {
            $lines[] = $key . '="' . $val . '"';
        }
    }

    $cleanedLines = array_map(function($l) {
        return rtrim($l, "\r\n");
    }, $lines);

    return file_put_contents($path, implode("\n", $cleanedLines)) !== false;
}

$envPath = $path_to_root . '.env';
$alert_msg = '';
$alert_type = '';

// Handle credentials update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_firebase_config') {
    $newData = [
        'FIREBASE_API_KEY' => trim($_POST['api_key'] ?? ''),
        'FIREBASE_AUTH_DOMAIN' => trim($_POST['auth_domain'] ?? ''),
        'FIREBASE_PROJECT_ID' => trim($_POST['project_id'] ?? ''),
        'FIREBASE_STORAGE_BUCKET' => trim($_POST['storage_bucket'] ?? ''),
        'FIREBASE_MESSAGING_SENDER_ID' => trim($_POST['sender_id'] ?? ''),
        'FIREBASE_APP_ID' => trim($_POST['app_id'] ?? ''),
        'FIREBASE_MEASUREMENT_ID' => trim($_POST['measurement_id'] ?? ''),
        'FIREBASE_VAPID_KEY' => trim($_POST['vapid_key'] ?? ''),
        'FIREBASE_SERVER_KEY' => trim($_POST['server_key'] ?? '')
    ];
    
    if (updateEnvFile($envPath, $newData)) {
        $alert_msg = 'Firebase credentials saved to .env config file successfully. Please refresh the page to reload configurations.';
        $alert_type = 'success';
    } else {
        $alert_msg = 'Failed to write credentials to .env. Please check filesystem write permissions.';
        $alert_type = 'danger';
    }
}

// Fetch current configurations
$envValues = getEnvValues($envPath);

// Handle Export action (CSV)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=firebase_analytics_events_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Session ID', 'User ID', 'Event Name', 'Event Value', 'IP Address', 'Country', 'State', 'Referrer', 'Device Type', 'Browser', 'Timestamp']);
    
    try {
        $exportStmt = $pdo->query("SELECT id, session_id, user_id, event_name, event_value, ip_address, country, state, referrer, device_type, browser, created_at FROM firebase_analytics_events ORDER BY created_at DESC");
        while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    } catch (Exception $e) {}
    fclose($output);
    exit;
}

// Handle Purge actions
if (!isset($alert_msg)) {
    $alert_msg = '';
    $alert_type = '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_analytics') {
        try {
            $pdo->exec("TRUNCATE TABLE firebase_analytics_events");
            $alert_msg = 'All Firebase analytics events have been purged successfully.';
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert_msg = 'Error clearing analytics logs: ' . $e->getMessage();
            $alert_type = 'danger';
        }
    } elseif ($_POST['action'] === 'clear_fcm') {
        try {
            $pdo->exec("TRUNCATE TABLE fcm_notification_history");
            $alert_msg = 'All FCM push dispatch history has been purged successfully.';
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert_msg = 'Error clearing push notification history: ' . $e->getMessage();
            $alert_type = 'danger';
        }
    }
}

require_once $path_to_root . 'includes/dashboard_header.php';

// Filter configuration
$range = isset($_GET['range']) ? $_GET['range'] : 'all';

// Build SQL where conditions based on range
$whereClauses = [];
if ($range === 'today') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
} elseif ($range === '7days') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($range === '30days') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Retrieve DB metrics
$totalViews = 0;
$uniqueVisitors = 0;
$activeUsers = 0;
$conversionRate = 0;
$conversionSessions = 0;

try {
    // Total events
    $totalViews = $pdo->query("SELECT COUNT(*) FROM firebase_analytics_events $whereSql")->fetchColumn() ?: 0;
    
    // Unique Visitors (Sessions)
    $uniqueVisitors = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM firebase_analytics_events $whereSql")->fetchColumn() ?: 0;
    
    // Registered Active Users
    $whereUserSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " AND user_id IS NOT NULL" : "WHERE user_id IS NOT NULL";
    $activeUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM firebase_analytics_events $whereUserSql")->fetchColumn() ?: 0;
    
    // Conversion sessions
    $whereConvSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " AND event_name IN ('newsletter_subscription', 'contact_form_submission', 'partnership_submission', 'client_request_submission', 'project_proposal_submission')" : "WHERE event_name IN ('newsletter_subscription', 'contact_form_submission', 'partnership_submission', 'client_request_submission', 'project_proposal_submission')";
    $conversionSessions = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM firebase_analytics_events $whereConvSql")->fetchColumn() ?: 0;
    
    if ($uniqueVisitors > 0) {
        $conversionRate = round(($conversionSessions / $uniqueVisitors) * 100, 2);
    }
} catch (Exception $e) {}

// Device & Browser Chart compiling datasets
$chromeCount = 0; $safariCount = 0; $firefoxCount = 0; $edgeCount = 0; $operaCount = 0; $otherBrowser = 0;
$winCount = 0; $macCount = 0; $linuxCount = 0; $androidCount = 0; $iosCount = 0; $otherDevice = 0;

try {
    $devicesQuery = $pdo->query("SELECT device_type, COUNT(*) as cnt FROM firebase_analytics_events $whereSql GROUP BY device_type");
    while ($r = $devicesQuery->fetch()) {
        $dev = strtolower($r['device_type']);
        if ($dev === 'mobile') $androidCount += $r['cnt']; // mobile device
        elseif ($dev === 'tablet') $iosCount += $r['cnt'];
        elseif ($dev === 'desktop') $winCount += $r['cnt'];
        else $otherDevice += $r['cnt'];
    }
    
    $browsersQuery = $pdo->query("SELECT browser, COUNT(*) as cnt FROM firebase_analytics_events $whereSql GROUP BY browser");
    while ($r = $browsersQuery->fetch()) {
        $br = strtolower($r['browser']);
        if (strpos($br, 'chrome') !== false) $chromeCount += $r['cnt'];
        elseif (strpos($br, 'safari') !== false) $safariCount += $r['cnt'];
        elseif (strpos($br, 'firefox') !== false) $firefoxCount += $r['cnt'];
        elseif (strpos($br, 'edge') !== false) $edgeCount += $r['cnt'];
        elseif (strpos($br, 'opera') !== false) $operaCount += $r['cnt'];
        else $otherBrowser += $r['cnt'];
    }
} catch (Exception $e) {}

// Traffic Channels / Referrers
$referrerLabels = [];
$referrerCounts = [];
try {
    $refQuery = $pdo->query("SELECT referrer, COUNT(*) as cnt FROM firebase_analytics_events $whereSql GROUP BY referrer ORDER BY cnt DESC LIMIT 15");
    $normalizedRefs = [];
    while ($r = $refQuery->fetch()) {
        $ref = strtolower($r['referrer']);
        $source = 'Direct / Internal';
        if (empty($ref) || $ref === 'direct' || strpos($ref, 'localhost') !== false || strpos($ref, 'wisequotient') !== false) {
            $source = 'Direct / Internal';
        } elseif (strpos($ref, 'google') !== false) {
            $source = 'Google';
        } elseif (strpos($ref, 'facebook') !== false || strpos($ref, 'fb') !== false) {
            $source = 'Facebook';
        } elseif (strpos($ref, 'twitter') !== false || strpos($ref, 't.co') !== false || strpos($ref, 'x.com') !== false) {
            $source = 'Twitter / X';
        } elseif (strpos($ref, 'linkedin') !== false) {
            $source = 'LinkedIn';
        } elseif (strpos($ref, 'instagram') !== false) {
            $source = 'Instagram';
        } else {
            $parsed = parse_url($r['referrer'], PHP_URL_HOST);
            $source = $parsed ? $parsed : $r['referrer'];
        }
        
        if (!isset($normalizedRefs[$source])) {
            $normalizedRefs[$source] = 0;
        }
        $normalizedRefs[$source] += $r['cnt'];
    }
    arsort($normalizedRefs);
    $referrerLabels = array_keys(array_slice($normalizedRefs, 0, 5, true));
    $referrerCounts = array_values(array_slice($normalizedRefs, 0, 5, true));
} catch (Exception $e) {}

// Service Popularity
$serviceLabels = [];
$serviceCounts = [];
try {
    $whereSvcSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " AND event_name IN ('service_card_click', 'service_category_view')" : "WHERE event_name IN ('service_card_click', 'service_category_view')";
    $svcQuery = $pdo->query("SELECT event_value, COUNT(*) as cnt FROM firebase_analytics_events $whereSvcSql GROUP BY event_value ORDER BY cnt DESC LIMIT 5");
    while ($r = $svcQuery->fetch()) {
        $serviceLabels[] = $r['event_value'];
        $serviceCounts[] = intval($r['cnt']);
    }
} catch (Exception $e) {}

// Funnel Steps
$funnelVisits = $uniqueVisitors;
$funnelServices = 0;
$funnelInterest = 0;
$funnelConversions = $conversionSessions;

try {
    $whereFunnelSvcSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " AND (event_name IN ('service_card_click', 'service_category_view') OR (event_name = 'page_view' AND event_value = 'services.php'))" : "WHERE (event_name IN ('service_card_click', 'service_category_view') OR (event_name = 'page_view' AND event_value = 'services.php'))";
    $funnelServices = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM firebase_analytics_events $whereFunnelSvcSql")->fetchColumn() ?: 0;
    
    $whereFunnelIntSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) . " AND (event_name = 'pricing_plan_select' OR (event_name = 'page_view' AND event_value = 'contact.php'))" : "WHERE (event_name = 'pricing_plan_select' OR (event_name = 'page_view' AND event_value = 'contact.php'))";
    $funnelInterest = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM firebase_analytics_events $whereFunnelIntSql")->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Trend (Last 7 Days)
$trendDates = [];
$trendEvents = [];
try {
    $trendQuery = $pdo->query("SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM firebase_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY dt ASC");
    while ($r = $trendQuery->fetch()) {
        $trendDates[] = date('M j', strtotime($r['dt']));
        $trendEvents[] = intval($r['cnt']);
    }
} catch (Exception $e) {}

// FCM Stats
$fcmTotalSent = 0;
$fcmSuccess = 0;
$fcmSimulation = 0;
$fcmFailure = 0;
try {
    $fcmTotalSent = $pdo->query("SELECT SUM(recipient_count) FROM fcm_notification_history")->fetchColumn() ?: 0;
    
    $fcmBreakdown = $pdo->query("SELECT status, COUNT(*) as cnt, SUM(recipient_count) as total_recips FROM fcm_notification_history GROUP BY status")->fetchAll();
    foreach ($fcmBreakdown as $b) {
        $st = strtolower($b['status']);
        $recips = intval($b['total_recips']);
        if ($st === 'success') {
            $fcmSuccess += $recips;
        } elseif ($st === 'simulation' || $st === 'mock') {
            $fcmSimulation += $recips;
        } else {
            $fcmFailure += $recips;
        }
    }
} catch (Exception $e) {}

// Recent Logs
$recentEvents = [];
try {
    $recentEvents = $pdo->query("SELECT * FROM firebase_analytics_events ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

// Recent Push dispatches
$recentNotifications = [];
try {
    $recentNotifications = $pdo->query("SELECT * FROM fcm_notification_history ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

// Helper helper functions
function getFCMDeviceIcon($device) {
    $device = strtolower($device);
    if ($device === 'mobile') return 'fas fa-mobile-alt text-success';
    if ($device === 'tablet') return 'fas fa-tablet-alt text-info';
    return 'fas fa-desktop text-primary';
}

function getFCMBrowserIcon($browser) {
    $browser = strtolower($browser);
    if (strpos($browser, 'chrome') !== false) return 'fab fa-chrome text-success';
    if (strpos($browser, 'safari') !== false) return 'fab fa-safari text-primary';
    if (strpos($browser, 'firefox') !== false) return 'fab fa-firefox-browser text-warning';
    if (strpos($browser, 'edge') !== false) return 'fab fa-edge text-info';
    if (strpos($browser, 'opera') !== false) return 'fab fa-opera text-danger';
    return 'fas fa-globe text-muted';
}
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ======= COMPREHENSIVE HIGH-FIDELITY CSS DESIGN ======= */
.firebase-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #1c4980 50%, #2b619c 100%);
    border-radius: 20px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(10, 45, 94, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.firebase-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(225, 85, 1, 0.2); border-radius:50%;
    filter: blur(35px);
}
.firebase-hero h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 900;
    font-size: 2rem;
    letter-spacing: -0.02em;
}

/* Glassmorphism Metric Card Styles */
.analytics-metric-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(0,0,0,0.015);
}
.analytics-metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(15,23,42,0.08);
    border-color: #cbd5e1;
}
.analytics-metric-card .icon-box {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; margin-bottom: 1rem;
}
.analytics-metric-card h4 {
    font-size: 2rem; font-weight: 800;
    color: #0f172a; margin: 0 0 0.2rem;
    letter-spacing: -0.02em;
}
.analytics-metric-card p {
    font-size: 0.8rem; font-weight: 700;
    color: #64748b; margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Chart Canvas Wrapper */
.chart-box {
    background: white;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.01);
}
.chart-box h5 {
    font-weight: 800;
    font-size: 0.95rem;
    color: #0f172a;
    margin-bottom: 1.25rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.canvas-wrapper {
    position: relative;
    height: 250px;
    width: 100%;
}

/* Controls bar */
.controls-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.range-btn-wrap {
    display: flex;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 10px;
}
.range-anchor {
    padding: 0.45rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #475569;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.range-anchor.active {
    background: #0f172a;
    color: white;
}

/* Action button classes */
.btn-dashboard-action {
    height: 40px;
    padding: 0 1.25rem;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-csv {
    background: #eff6ff;
    color: #2563eb;
    border: 1.5px solid #bfdbfe;
}
.btn-csv:hover {
    background: #2563eb;
    color: white;
}
.btn-clear {
    background: #fef2f2;
    color: #dc2626;
    border: 1.5px solid #fecaca;
}
.btn-clear:hover {
    background: #dc2626;
    color: white;
}

/* Table Style custom */
.custom-table {
    font-size: 0.85rem;
}
.custom-table th {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    background: #f8fafc;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}
.custom-table td {
    vertical-align: middle;
}
.badge-simulation {
    background-color: #fef3c7;
    color: #d97706;
    border: 1px solid #fde68a;
}
.badge-success-fcm {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.badge-failed-fcm {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* Live animation */
.live-glow-container {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(225, 85, 1, 0.1);
    color: #ff6600;
    border: 1px solid rgba(225, 85, 1, 0.2);
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
}
.live-pulse-dot {
    width: 8px; height: 8px;
    background-color: #ff6600;
    border-radius: 50%;
    animation: livePulse 1.8s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); opacity: 0.5; }
    50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 10px #ff6600; }
    100% { transform: scale(0.95); opacity: 0.5; }
}

@media (max-width: 767px) {
    .canvas-wrapper {
        height: 200px !important;
    }
    .analytics-metric-card h4 {
        font-size: 1.6rem !important;
    }
}
</style>

<div class="container-fluid px-lg-4">

    <!-- Alert notifications -->
    <?php if ($alert_msg): ?>
        <div class="alert alert-<?= $alert_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show rounded-16 shadow-sm mb-4" role="alert" style="border-radius:12px;">
            <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($alert_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Hero Banner -->
    <div class="firebase-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2><i class="fas fa-fire text-orange me-2 animate-bounce"></i> Firebase Real-Time Dashboard</h2>
                <p class="mb-0 mt-2 text-muted" style="font-size:0.92rem; color:#cbd5e1 !important;">Monitor analytical user logs, track frontend conversions, and review real-time Firebase Cloud Messaging push dispatch history metrics.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                <span class="live-glow-container">
                    <span class="live-pulse-dot"></span> Real-Time Feeds Active
                </span>
                <button type="button" class="btn-dashboard-action" data-bs-toggle="modal" data-bs-target="#firebaseConfigModal" style="background:#ff6600; color:white; border:none;">
                    <i class="fas fa-cog"></i> Firebase Settings
                </button>
                <a href="?export=csv" class="btn-dashboard-action btn-csv">
                    <i class="fas fa-file-csv"></i> Export Logs
                </a>
            </div>
        </div>
    </div>

    <!-- Controls / Filters -->
    <div class="controls-card">
        <div>
            <h5 class="text-body fw-bold mb-0" style="font-size:0.95rem;">Select Analytics Period:</h5>
        </div>
        <div class="range-btn-wrap">
            <a href="?range=all" class="range-anchor <?= $range === 'all' ? 'active' : '' ?>">All Time</a>
            <a href="?range=today" class="range-anchor <?= $range === 'today' ? 'active' : '' ?>">Today</a>
            <a href="?range=7days" class="range-anchor <?= $range === '7days' ? 'active' : '' ?>">Last 7 Days</a>
            <a href="?range=30days" class="range-anchor <?= $range === '30days' ? 'active' : '' ?>">Last 30 Days</a>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="analytics-metric-card">
                <div class="icon-box" style="background:#eff6ff; color:#2563eb;"><i class="fas fa-mouse-pointer"></i></div>
                <h4><?= number_format($totalViews) ?></h4>
                <p>Total Events</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="analytics-metric-card">
                <div class="icon-box" style="background:#fff7ed; color:#ea580c;"><i class="fas fa-eye"></i></div>
                <h4><?= number_format($uniqueVisitors) ?></h4>
                <p>Unique Sessions</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="analytics-metric-card">
                <div class="icon-box" style="background:#f5f3ff; color:#7c3aed;"><i class="fas fa-user-check"></i></div>
                <h4><?= number_format($activeUsers) ?></h4>
                <p>Logged-in Active Users</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="analytics-metric-card">
                <div class="icon-box" style="background:#ecfdf5; color:#10b981;"><i class="fas fa-funnel-dollar"></i></div>
                <h4><?= $conversionRate ?>%</h4>
                <p>Conversions Rate</p>
            </div>
        </div>
    </div>

    <!-- Main Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6 col-md-12">
            <div class="chart-box h-100">
                <h5><i class="fas fa-chart-line text-orange me-2"></i> Event Volume Volume Trend (Last 7 Days)</h5>
                <div class="canvas-wrapper">
                    <canvas id="eventTrendsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="chart-box h-100">
                <h5><i class="fas fa-share-alt text-primary me-2"></i> Traffic Channels</h5>
                <div class="canvas-wrapper">
                    <canvas id="trafficChannelsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="chart-box h-100">
                <h5><i class="fas fa-filter text-success me-2"></i> Conversion Funnel</h5>
                <div class="canvas-wrapper">
                    <canvas id="conversionFunnelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Charts & Service Clicks -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6 col-md-12">
            <div class="chart-box h-100">
                <h5><i class="fas fa-bars-progress text-info me-2"></i> Service/Category Popularity Hits</h5>
                <div class="canvas-wrapper">
                    <canvas id="serviceHitsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="chart-box h-100">
                <h5><i class="fas fa-mobile-alt text-purple me-2"></i> Device Splits</h5>
                <div class="canvas-wrapper">
                    <canvas id="deviceSplitsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="chart-box h-100">
                <h5><i class="fas fa-globe text-primary me-2"></i> Browser Share</h5>
                <div class="canvas-wrapper">
                    <canvas id="browserShareChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- FCM Push Notification KPI Banner -->
    <div class="card-theme mb-4">
        <div class="card-theme-header d-flex justify-content-between align-items-center">
            <h5 class="card-theme-title text-body mb-0"><i class="fas fa-paper-plane text-orange me-2"></i> Firebase Cloud Messaging (FCM) Dispatches</h5>
            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Are you sure you want to clear FCM history?');">
                <input type="hidden" name="action" value="clear_fcm">
                <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold">Clear FCM History</button>
            </form>
        </div>
        <div class="card-theme-body">
            <div class="row g-3 text-center mb-3">
                <div class="col-md-3 col-6 border-end">
                    <h3 class="fw-extrabold text-body"><?= number_format($fcmTotalSent) ?></h3>
                    <p class="text-muted small uppercase fw-bold mb-0">Total Recipients Targeted</p>
                </div>
                <div class="col-md-3 col-6 border-end">
                    <h3 class="fw-extrabold text-success"><?= number_format($fcmSuccess) ?></h3>
                    <p class="text-muted small uppercase fw-bold mb-0">Successful Dispatches</p>
                </div>
                <div class="col-md-3 col-6 border-end">
                    <h3 class="fw-extrabold text-warning"><?= number_format($fcmSimulation) ?></h3>
                    <p class="text-muted small uppercase fw-bold mb-0">Simulated Offlines</p>
                </div>
                <div class="col-md-3 col-6">
                    <h3 class="fw-extrabold text-danger"><?= number_format($fcmFailure) ?></h3>
                    <p class="text-muted small uppercase fw-bold mb-0">Failed Attempts</p>
                </div>
            </div>
            
            <!-- Table of recent FCM notifications -->
            <div class="table-responsive">
                <table class="table table-hover custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Notification Title</th>
                            <th>Message / Payload</th>
                            <th class="text-center">Recipients</th>
                            <th class="text-center">Dispatch Status</th>
                            <th>Date Dispatched</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentNotifications)): ?>
                            <?php foreach ($recentNotifications as $notif): 
                                $status = strtolower($notif['status']);
                                $badgeClass = 'badge-success-fcm';
                                if ($status === 'simulation' || $status === 'mock') {
                                    $badgeClass = 'badge-simulation';
                                } elseif ($status !== 'success') {
                                    $badgeClass = 'badge-failed-fcm';
                                }
                            ?>
                                <tr>
                                    <td class="fw-bold text-body"><?= htmlspecialchars($notif['title']) ?></td>
                                    <td class="text-muted" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($notif['message']) ?></td>
                                    <td class="text-center fw-bold"><?= number_format($notif['recipient_count']) ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?= $badgeClass ?> px-2.5 py-1 text-uppercase font-weight-bold" style="font-size:0.7rem;"><?= htmlspecialchars($notif['status']) ?></span>
                                    </td>
                                    <td class="text-muted"><?= date('M j, Y h:i A', strtotime($notif['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No push dispatches logged yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Real-time Event Streams Logs -->
    <div class="card-theme">
        <div class="card-theme-header d-flex justify-content-between align-items-center">
            <h5 class="card-theme-title text-body mb-0"><i class="fas fa-history text-orange me-2"></i> Real-time Analytics Feed (Last 10 Logs)</h5>
            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Are you sure you want to clear all analytics logs?');">
                <input type="hidden" name="action" value="clear_analytics">
                <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold">Purge Analytics Events</button>
            </form>
        </div>
        <div class="card-theme-body">
            <div class="table-responsive">
                <table class="table table-hover custom-table align-middle">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Geo Location</th>
                            <th>Event Name</th>
                            <th>Event Value</th>
                            <th>Device</th>
                            <th>Browser</th>
                            <th>Referrer</th>
                            <th>Date / Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentEvents)): ?>
                            <?php foreach ($recentEvents as $ev): ?>
                                <tr>
                                    <td><span class="ip-badge"><?= htmlspecialchars($ev['ip_address'] ?: 'N/A') ?></span></td>
                                    <td>
                                        <span class="fw-bold" style="color:var(--color-text-body);"><i class="fas fa-map-marker-alt text-muted me-1"></i><?= htmlspecialchars($ev['state'] ?: 'Unknown') ?>, <?= htmlspecialchars($ev['country'] ?: 'Unknown') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary text-uppercase px-2.5 py-1 font-weight-bold" style="font-size:0.68rem;"><?= htmlspecialchars($ev['event_name']) ?></span>
                                    </td>
                                    <td class="text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($ev['event_value'] ?: 'N/A') ?></td>
                                    <td><i class="<?= getFCMDeviceIcon($ev['device_type']) ?> me-1"></i> <?= htmlspecialchars($ev['device_type']) ?></td>
                                    <td><i class="<?= getFCMBrowserIcon($ev['browser']) ?> me-1"></i> <?= htmlspecialchars($ev['browser']) ?></td>
                                    <td class="text-muted" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($ev['referrer'] ?: 'Direct') ?></td>
                                    <td class="text-muted"><?= date('M j, Y h:i A', strtotime($ev['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No analytical activity recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Chart rendering Javascript config -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Daily Event trends chart (Area)
    const trendsCtx = document.getElementById('eventTrendsChart').getContext('2d');
    const trendsData = <?php echo json_encode($trendEvents); ?>;
    const trendsLabels = <?php echo json_encode($trendDates); ?>;
    
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendsLabels.length ? trendsLabels : ['No Data'],
            datasets: [{
                label: 'Log Events Volume',
                data: trendsData.length ? trendsData : [0],
                borderColor: '#ea580c',
                backgroundColor: 'rgba(234, 88, 12, 0.15)',
                fill: true,
                borderWidth: 3,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Traffic Channels Doughnut
    const trafficCtx = document.getElementById('trafficChannelsChart').getContext('2d');
    const trafficLabels = <?php echo json_encode($referrerLabels); ?>;
    const trafficData = <?php echo json_encode($referrerCounts); ?>;
    
    new Chart(trafficCtx, {
        type: 'doughnut',
        data: {
            labels: trafficLabels.length ? trafficLabels : ['Direct'],
            datasets: [{
                data: trafficData.length ? trafficData : [1],
                backgroundColor: ['#2563eb', '#10b981', '#7c3aed', '#ea580c', '#e11d48']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
            }
        }
    });

    // 3. Conversion Funnel Bar
    const funnelCtx = document.getElementById('conversionFunnelChart').getContext('2d');
    new Chart(funnelCtx, {
        type: 'bar',
        data: {
            labels: ['Unique Sessions', 'Service Clicks', 'Action / Contacts', 'Completed Conversions'],
            datasets: [{
                data: [
                    <?= intval($funnelVisits) ?>,
                    <?= intval($funnelServices) ?>,
                    <?= intval($funnelInterest) ?>,
                    <?= intval($funnelConversions) ?>
                ],
                backgroundColor: ['#64748b', '#3b82f6', '#f59e0b', '#10b981'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                y: { grid: { display: false } }
            }
        }
    });

    // 4. Service category clicks (Horizontal Bar)
    const serviceCtx = document.getElementById('serviceHitsChart').getContext('2d');
    const serviceLabels = <?php echo json_encode($serviceLabels); ?>;
    const serviceCounts = <?php echo json_encode($serviceCounts); ?>;
    
    new Chart(serviceCtx, {
        type: 'bar',
        data: {
            labels: serviceLabels.length ? serviceLabels : ['None'],
            datasets: [{
                data: serviceCounts.length ? serviceCounts : [0],
                backgroundColor: '#10b981',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 5. Devices splits Doughnut
    const deviceCtx = document.getElementById('deviceSplitsChart').getContext('2d');
    new Chart(deviceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Desktop', 'Mobile', 'Tablet', 'Other'],
            datasets: [{
                data: [<?= $winCount ?>, <?= $androidCount ?>, <?= $iosCount ?>, <?= $otherDevice ?>],
                backgroundColor: ['#3b82f6', '#10b981', '#a78bfa', '#94a3b8']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
            }
        }
    });

    // 6. Browser share Pie
    const browserCtx = document.getElementById('browserShareChart').getContext('2d');
    new Chart(browserCtx, {
        type: 'pie',
        data: {
            labels: ['Chrome', 'Safari', 'Firefox', 'Edge', 'Opera', 'Other'],
            datasets: [{
                data: [<?= $chromeCount ?>, <?= $safariCount ?>, <?= $firefoxCount ?>, <?= $edgeCount ?>, <?= $operaCount ?>, <?= $otherBrowser ?>],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#06b6d4', '#ef4444', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
            }
        }
    });
});
</script>

<!-- Firebase Configuration Settings Modal -->
<div class="modal fade" id="firebaseConfigModal" tabindex="-1" aria-labelledby="firebaseConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 15px 40px rgba(15,23,42,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0A2D5E 0%, #1e293b 100%); color: white; border-bottom: none; padding: 1.5rem 2rem;">
                <h5 class="modal-title fw-bold" id="firebaseConfigModalLabel">
                    <i class="fas fa-sliders-h text-orange me-2"></i> Firebase Credentials Configurator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_firebase_config">
                <div class="modal-body" style="padding: 2rem; background: #f8fafc;">
                    <p class="text-muted small mb-4">
                        <i class="fas fa-info-circle me-1 text-primary"></i> Edit the active system credentials for Firebase services. These configurations will be rewritten directly to the core [<strong>.env</strong>] file on save.
                    </p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase API Key</label>
                            <input type="text" name="api_key" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="AIzaSy..." value="<?= htmlspecialchars($envValues['FIREBASE_API_KEY']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase Auth Domain</label>
                            <input type="text" name="auth_domain" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="project-id.firebaseapp.com" value="<?= htmlspecialchars($envValues['FIREBASE_AUTH_DOMAIN']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase Project ID</label>
                            <input type="text" name="project_id" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="project-id" value="<?= htmlspecialchars($envValues['FIREBASE_PROJECT_ID']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase Storage Bucket</label>
                            <input type="text" name="storage_bucket" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="project-id.appspot.com" value="<?= htmlspecialchars($envValues['FIREBASE_STORAGE_BUCKET']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase Messaging Sender ID</label>
                            <input type="text" name="sender_id" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="12-digit number" value="<?= htmlspecialchars($envValues['FIREBASE_MESSAGING_SENDER_ID']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase App ID</label>
                            <input type="text" name="app_id" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="1:sender_id:web:app_id" value="<?= htmlspecialchars($envValues['FIREBASE_APP_ID']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">Firebase Measurement ID (GA4)</label>
                            <input type="text" name="measurement_id" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="G-XXXXXXXXXX" value="<?= htmlspecialchars($envValues['FIREBASE_MEASUREMENT_ID']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">FCM Web VAPID Public Key</label>
                            <input type="text" name="vapid_key" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="Web Push certificate key" value="<?= htmlspecialchars($envValues['FIREBASE_VAPID_KEY'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-body" style="font-size: 0.82rem;">FCM Legacy Server Key</label>
                            <input type="password" name="server_key" class="form-control" style="font-size: 0.85rem; border-radius: 8px;" placeholder="Leave blank to run dispatches in simulated sandbox mode" value="<?= htmlspecialchars($envValues['FIREBASE_SERVER_KEY']) ?>">
                            <div class="form-text small text-muted">
                                If left blank, real push dispatches will be bypassed and logged locally to simulate offline testing.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; padding: 1rem 2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-bs-dismiss="modal" style="border-radius: 8px; font-size: 0.85rem; padding: 0.5rem 1.25rem;">Cancel</button>
                    <button type="submit" class="btn font-weight-bold text-white" style="background: #ff6600; border: none; border-radius: 8px; font-size: 0.85rem; padding: 0.5rem 1.5rem;">
                        <i class="fas fa-save me-1"></i> Save Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
