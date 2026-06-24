<?php
$page_title = 'Visitor Analytics';
$path_to_root = '../';

// Trigger CSV Export before headers are sent
require_once $path_to_root . 'config.php';

// Only allow admins
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = strtolower($_SESSION['user']['role'] ?? '');
// Handle Export action
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=visitor_analytics_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'IP Address', 'Country', 'State', 'User Agent', 'Page URL', 'Referrer', 'Timestamp']);
    
    try {
        $exportStmt = $pdo->query("SELECT id, ip_address, country, state, user_agent, page_url, referrer, created_at FROM analytics_log ORDER BY created_at DESC");
        while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    } catch (Exception $e) {}
    fclose($output);
    exit;
}

// Handle Delete/Purge logs action
$alert_msg = '';
$alert_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    try {
        $pdo->exec("TRUNCATE TABLE analytics_log");
        $alert_msg = 'All visitor logs have been purged successfully.';
        $alert_type = 'success';
    } catch (Exception $e) {
        $alert_msg = 'Error clearing logs: ' . $e->getMessage();
        $alert_type = 'danger';
    }
}

require_once $path_to_root . 'includes/dashboard_header.php';

// Geolocation Helpers
function getDeviceIcon($ua) {
    $ua = strtolower($ua);
    if (strpos($ua, 'windows') !== false) return 'fab fa-windows text-primary';
    if (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os x') !== false) return 'fab fa-apple text-body';
    if (strpos($ua, 'android') !== false) return 'fab fa-android text-success';
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) return 'fab fa-apple text-info';
    if (strpos($ua, 'linux') !== false) return 'fab fa-linux text-warning';
    return 'fas fa-desktop text-muted';
}

function getBrowserIcon($ua) {
    $ua = strtolower($ua);
    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edge') !== false) return 'fab fa-edge text-info';
    if (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) return 'fab fa-opera text-danger';
    if (strpos($ua, 'firefox') !== false) return 'fab fa-firefox-browser text-warning';
    if (strpos($ua, 'chrome') !== false) return 'fab fa-chrome text-success';
    if (strpos($ua, 'safari') !== false) return 'fab fa-safari text-primary';
    return 'fas fa-globe text-muted';
}

// Filters configuration
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$range = isset($_GET['range']) ? $_GET['range'] : 'all';

// Build SQL where conditions based on range and search
$whereClauses = [];
$params = [];

if ($search) {
    $whereClauses[] = "(ip_address LIKE ? OR country LIKE ? OR state LIKE ? OR page_url LIKE ? OR referrer LIKE ?)";
    $searchWildcard = "%$search%";
    $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
}

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

// Metrics queries with filters
$totalViews = 0;
$uniqueVisitors = 0;
$returningVisitors = 0;
$bounceCount = 0;

try {
    // Total Views
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM analytics_log $whereSql");
    $stmt->execute($params);
    $totalViews = $stmt->fetchColumn();

    // Unique Visitors
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM analytics_log $whereSql");
    $stmt->execute($params);
    $uniqueVisitors = $stmt->fetchColumn();

    // Returning Visitors (visited > 1 time)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT ip_address FROM analytics_log $whereSql GROUP BY ip_address HAVING COUNT(*) > 1) as returning_users");
    $stmt->execute($params);
    $returningVisitors = $stmt->fetchColumn();

    // Bounce Visitors (only visited 1 page total)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT ip_address FROM analytics_log $whereSql GROUP BY ip_address HAVING COUNT(*) = 1) as bounce_users");
    $stmt->execute($params);
    $bounceCount = $stmt->fetchColumn();
} catch (Exception $e) {}

$bounceRate = $uniqueVisitors > 0 ? round(($bounceCount / $uniqueVisitors) * 100) : 0;

// Device & Browser Chart compiling datasets
$chromeCount = 0; $safariCount = 0; $firefoxCount = 0; $edgeCount = 0; $operaCount = 0; $otherBrowser = 0;
$winCount = 0; $macCount = 0; $linuxCount = 0; $androidCount = 0; $iosCount = 0; $otherDevice = 0;

try {
    $stmt = $pdo->prepare("SELECT user_agent FROM analytics_log $whereSql");
    $stmt->execute($params);
    $uaList = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($uaList as $ua) {
        $uaLower = strtolower($ua);
        // Browser detection
        if (strpos($uaLower, 'edg/') !== false || strpos($uaLower, 'edge') !== false) $edgeCount++;
        elseif (strpos($uaLower, 'opr/') !== false || strpos($uaLower, 'opera') !== false) $operaCount++;
        elseif (strpos($uaLower, 'firefox') !== false) $firefoxCount++;
        elseif (strpos($uaLower, 'chrome') !== false) $chromeCount++;
        elseif (strpos($uaLower, 'safari') !== false) $safariCount++;
        else $otherBrowser++;

        // Device detection
        if (strpos($uaLower, 'windows') !== false) $winCount++;
        elseif (strpos($uaLower, 'macintosh') !== false || strpos($uaLower, 'mac os x') !== false) {
            if (strpos($uaLower, 'iphone') !== false || strpos($uaLower, 'ipad') !== false) $iosCount++;
            else $macCount++;
        }
        elseif (strpos($uaLower, 'android') !== false) $androidCount++;
        elseif (strpos($uaLower, 'linux') !== false) $linuxCount++;
        else $otherDevice++;
    }
} catch (Exception $e) {}

// Visitor Trends (Last 7 Days line chart)
$trendDates = [];
$trendViews = [];
$trendUniques = [];
try {
    $trendStmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT ip_address) as uniques 
                              FROM analytics_log 
                              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                              GROUP BY DATE(created_at) 
                              ORDER BY date ASC");
    while ($row = $trendStmt->fetch(PDO::FETCH_ASSOC)) {
        $trendDates[] = date('M j', strtotime($row['date']));
        $trendViews[] = intval($row['views']);
        $trendUniques[] = intval($row['uniques']);
    }
} catch (Exception $e) {}

// Top Countries Breakdown
$countriesList = [];
try {
    $stmt = $pdo->prepare("SELECT country, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_users 
                           FROM analytics_log 
                           $whereSql 
                           GROUP BY country 
                           ORDER BY views DESC 
                           LIMIT 5");
    $stmt->execute($params);
    $countriesList = $stmt->fetchAll();
} catch (Exception $e) {}

// Top States Breakdown
$statesList = [];
try {
    $stmt = $pdo->prepare("SELECT state, country, COUNT(*) as views 
                           FROM analytics_log 
                           $whereSql 
                           GROUP BY state, country 
                           ORDER BY views DESC 
                           LIMIT 5");
    $stmt->execute($params);
    $statesList = $stmt->fetchAll();
} catch (Exception $e) {}

// Popular pages
$pagesList = [];
try {
    $stmt = $pdo->prepare("SELECT page_url, COUNT(*) as views 
                           FROM analytics_log 
                           $whereSql 
                           GROUP BY page_url 
                           ORDER BY views DESC 
                           LIMIT 5");
    $stmt->execute($params);
    $pagesList = $stmt->fetchAll();
} catch (Exception $e) {}

// Visitor log listing with pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$visitorLog = [];
$totalLogCount = 0;
try {
    // Count total rows matching filters
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM analytics_log $whereSql");
    $countStmt->execute($params);
    $totalLogCount = $countStmt->fetchColumn();

    // Fetch matching logs
    $logSql = "SELECT * FROM analytics_log $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($logSql);
    
    // Bind parameters for filters
    $i = 1;
    foreach ($params as $paramVal) {
        $stmt->bindValue($i++, $paramVal);
    }
    $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $visitorLog = $stmt->fetchAll();
} catch (Exception $e) {}

$totalPages = ceil($totalLogCount / $limit);
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== Visitor Analytics Premium Styling Upgrade ===== */
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

.analytics-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    border-radius: 24px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.analytics-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; background:rgba(255, 102, 0, 0.15); border-radius:50%;
    filter: blur(40px);
}
.analytics-hero h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 1.85rem;
    letter-spacing: -0.025em;
}
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.2);
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
}
.live-dot {
    width: 8px; height: 8px;
    background-color: #22c55e;
    border-radius: 50%;
    animation: pulseGlow 1.8s infinite;
}
@keyframes pulseGlow {
    0% { transform: scale(0.9); opacity: 0.6; }
    50% { transform: scale(1.2); opacity: 1; box-shadow: 0 0 10px #22c55e; }
    100% { transform: scale(0.9); opacity: 0.6; }
}

/* Glassmorphic Metrics */
.metric-card-upgraded {
    background: white;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 1.6rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(0,0,0,0.015);
}
.metric-card-upgraded:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(15,23,42,0.08);
    border-color: #cbd5e1;
}
.metric-card-upgraded .icon-box {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; margin-bottom: 1.1rem;
}
.metric-card-upgraded h4 {
    font-size: 2.1rem; font-weight: 800;
    color: #0f172a; margin: 0 0 0.25rem;
    letter-spacing: -0.02em;
}
.metric-card-upgraded p {
    font-size: 0.82rem; font-weight: 700;
    color: #64748b; margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Interactive filter bars */
.filter-bar-card {
    background: white;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.search-input-group {
    position: relative;
    max-width: 320px;
    width: 100%;
}
.search-input-group i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}
.search-input-group .form-control {
    padding-left: 2.3rem;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    font-size: 0.88rem;
    height: 40px;
}
.search-input-group .form-control:focus {
    border-color: #ff6600;
    box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
}
.range-btn-group {
    display: flex;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 10px;
}
.range-btn {
    border: none;
    background: none;
    padding: 0.45rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #475569;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.range-btn.active {
    background: #0f172a;
    color: white;
}

/* Charts grid styling */
.chart-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.01);
}
.chart-card h5 {
    font-weight: 800;
    font-size: 0.98rem;
    color: #0f172a;
    margin-bottom: 1.25rem;
}
.chart-wrapper {
    position: relative;
    height: 240px;
    width: 100%;
}

/* Action button premium designs */
.btn-action-premium {
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
.btn-export-csv {
    background: #eff6ff;
    color: #2563eb;
    border: 1.5px solid #bfdbfe;
}
.btn-export-csv:hover {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}
.btn-purge-logs {
    background: #fef2f2;
    color: #dc2626;
    border: 1.5px solid #fecaca;
}
.btn-purge-logs:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

/* Location and Pages Breakdown Progress Bars */
.prog-container {
    margin-bottom: 1.2rem;
}
.prog-container:last-child {
    margin-bottom: 0;
}
.prog-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--color-text-body);
    margin-bottom: 0.45rem;
}
.location-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    max-width: 75%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.prog-bar-wrapper {
    height: 8px;
    background-color: var(--color-bg);
    border-radius: 4px;
    overflow: hidden;
}
.prog-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* IP Badges & UA Icons */
.ip-badge {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    font-family: monospace;
    font-size: 0.82rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    display: inline-block;
}
.ua-icon {
    transition: transform 0.2s;
    cursor: pointer;
}
.ua-icon:hover {
    transform: scale(1.25);
}

/* ===== Comprehensive Responsive Styles for Analytics ===== */
@media (max-width: 991.98px) {
    .analytics-hero {
        padding: 1.8rem !important;
        border-radius: 20px;
    }
    .analytics-hero h2 {
        font-size: 1.5rem !important;
    }
    .filter-bar-card {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .search-input-group {
        max-width: 100% !important;
    }
    .range-btn-group {
        width: 100% !important;
        justify-content: space-around !important;
    }
    .chart-wrapper {
        height: 220px !important;
    }
}

@media (max-width: 767.98px) {
    .analytics-hero {
        padding: 1.5rem !important;
    }
    .analytics-hero h2 {
        font-size: 1.3rem !important;
    }
    .metric-card-upgraded {
        padding: 1.2rem !important;
        border-radius: 16px;
    }
    .metric-card-upgraded h4 {
        font-size: 1.7rem !important;
    }
    .metric-card-upgraded p {
        font-size: 0.78rem !important;
    }
    .chart-card {
        padding: 1.2rem !important;
        border-radius: 16px;
    }
    .range-btn {
        padding: 0.35rem 0.7rem !important;
        font-size: 0.75rem !important;
    }
    .btn-action-premium {
        padding: 0 1rem !important;
        font-size: 0.8rem !important;
        height: 36px !important;
    }
}

@media (max-width: 575.98px) {
    .analytics-hero {
        padding: 1.2rem !important;
        border-radius: 14px;
    }
    .analytics-hero h2 {
        font-size: 1.15rem !important;
    }
    .analytics-hero p {
        font-size: 0.85rem !important;
    }
    .metric-card-upgraded {
        padding: 1rem !important;
        border-radius: 14px;
    }
    .metric-card-upgraded .icon-box {
        width: 42px !important;
        height: 42px !important;
        font-size: 1.1rem !important;
    }
    .metric-card-upgraded h4 {
        font-size: 1.5rem !important;
    }
    .chart-card {
        padding: 1rem !important;
        border-radius: 14px;
    }
    .chart-wrapper {
        height: 200px !important;
    }
    .range-btn-group {
        flex-wrap: wrap !important;
        gap: 0.25rem !important;
    }
    .range-btn {
        flex: 1 1 45% !important;
        text-align: center !important;
    }
    .prog-label {
        font-size: 0.8rem !important;
    }
    table {
        font-size: 0.78rem !important;
    }
    th, td {
        padding: 0.5rem !important;
    }
    .btn-action-premium {
        width: 100% !important;
        justify-content: center !important;
    }
}

@media (max-width: 479.98px) {
    .analytics-hero {
        padding: 1rem !important;
        border-radius: 12px;
    }
    .metric-card-upgraded {
        padding: 0.9rem !important;
    }
    .metric-card-upgraded h4 {
        font-size: 1.4rem !important;
    }
    .chart-card {
        padding: 0.9rem !important;
    }
    .chart-wrapper {
        height: 190px !important;
    }
}

@media (max-width: 399.98px) {
    .analytics-hero {
        padding: 0.85rem !important;
    }
    .analytics-hero h2 {
        font-size: 1rem !important;
    }
    .metric-card-upgraded {
        padding: 0.8rem !important;
    }
    .metric-card-upgraded h4 {
        font-size: 1.3rem !important;
    }
    .chart-wrapper {
        height: 180px !important;
    }
    .range-btn {
        font-size: 0.7rem !important;
        padding: 0.3rem 0.6rem !important;
    }
}
</style>

<!-- Visitor Analytics Dashboard UI -->
<div class="container-fluid px-lg-4">

    <!-- Alerts if cleared/error -->
    <?php if ($alert_msg): ?>
        <div class="alert alert-<?= $alert_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show rounded-16 shadow-sm mb-4" role="alert" style="border-radius:12px;">
            <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($alert_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Premium Hero Section -->
    <div class="analytics-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2><i class="fas fa-chart-pie text-orange me-2"></i> Premium Visitor Analytics</h2>
                <p class="mb-0 mt-2 text-muted" style="font-size:0.92rem; color:#cbd5e1 !important;">Geolocate customer traffic, track trends with Chart.js visualization, and analyze key engagement performance variables.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                <span class="live-indicator">
                    <span class="live-dot"></span> Active Logging Enabled
                </span>
                <a href="?export=csv" class="btn-action-premium btn-export-csv">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Are you sure you want to delete all visitor logs? This action is irreversible.');">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn-action-premium btn-purge-logs">
                        <i class="fas fa-trash-alt"></i> Purge Logs
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Filter & Search Panel -->
    <div class="filter-bar-card">
        <!-- Search bar form -->
        <form method="GET" class="search-input-group">
            <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Search by IP, Country, State, URL..." value="<?= htmlspecialchars($search) ?>">
        </form>

        <!-- Date ranges selector -->
        <div class="range-btn-group">
            <a href="?range=all&search=<?= urlencode($search) ?>" class="range-btn <?= $range === 'all' ? 'active' : '' ?>">All Time</a>
            <a href="?range=today&search=<?= urlencode($search) ?>" class="range-btn <?= $range === 'today' ? 'active' : '' ?>">Today</a>
            <a href="?range=7days&search=<?= urlencode($search) ?>" class="range-btn <?= $range === '7days' ? 'active' : '' ?>">Last 7 Days</a>
            <a href="?range=30days&search=<?= urlencode($search) ?>" class="range-btn <?= $range === '30days' ? 'active' : '' ?>">Last 30 Days</a>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="metric-card-upgraded">
                <div class="icon-box" style="background:#eff6ff; color:#3b82f6;"><i class="fas fa-chart-line"></i></div>
                <h4><?= number_format($totalViews) ?></h4>
                <p>Total Views</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card-upgraded">
                <div class="icon-box" style="background:#fff7ed; color:#ea580c;"><i class="fas fa-user-friends"></i></div>
                <h4><?= number_format($uniqueVisitors) ?></h4>
                <p>Unique Visitors</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card-upgraded">
                <div class="icon-box" style="background:#f0fdf4; color:#16a34a;"><i class="fas fa-history"></i></div>
                <h4><?= number_format($returningVisitors) ?></h4>
                <p>Returning Users</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card-upgraded">
                <div class="icon-box" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-door-open"></i></div>
                <h4><?= $bounceRate ?>%</h4>
                <p>Estimated Bounce Rate</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Visitor trends line chart -->
        <div class="col-12 col-lg-6">
            <div class="chart-card h-100">
                <h5><i class="fas fa-chart-area text-orange me-2"></i> Visitor Trends (Last 7 Days)</h5>
                <div class="chart-wrapper">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Device & browser donut charts -->
        <div class="col-12 col-lg-3 col-md-6">
            <div class="chart-card h-100">
                <h5><i class="fas fa-desktop text-orange me-2"></i> Device Layout</h5>
                <div class="chart-wrapper">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3 col-md-6">
            <div class="chart-card h-100">
                <h5><i class="fas fa-browser text-orange me-2"></i> Browsers Share</h5>
                <div class="chart-wrapper">
                    <canvas id="browsersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Location & Popular Pages Breakdowns -->
    <div class="row g-3 mb-4">
        <!-- Top Countries -->
        <div class="col-12 col-lg-4 col-md-6">
            <div class="card-theme h-100 mb-0">
                <div class="card-theme-header">
                    <h5 class="card-theme-title"><i class="fas fa-globe-africa me-2 text-accent"></i> Countries Breakdown</h5>
                </div>
                <div class="card-theme-body">
                    <?php 
                    foreach ($countriesList as $c): 
                        $pct = $totalViews > 0 ? round(($c['views'] / $totalViews) * 100) : 0;
                    ?>
                        <div class="prog-container">
                            <div class="prog-label">
                                <span class="location-tag"><i class="fas fa-map-pin text-muted me-1"></i> <?= htmlspecialchars($c['country'] ?: 'Unknown') ?></span>
                                <span><?= number_format($c['views']) ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="prog-bar-wrapper">
                                <div class="prog-bar-fill" style="width: <?= $pct ?>%; background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($countriesList)): ?>
                        <p class="text-center text-muted py-4">No country logs found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top States -->
        <div class="col-12 col-lg-4 col-md-6">
            <div class="card-theme h-100 mb-0">
                <div class="card-theme-header">
                    <h5 class="card-theme-title"><i class="fas fa-map-marked-alt me-2 text-accent"></i> States Breakdown</h5>
                </div>
                <div class="card-theme-body">
                    <?php 
                    foreach ($statesList as $s): 
                        $pct = $totalViews > 0 ? round(($s['views'] / $totalViews) * 100) : 0;
                    ?>
                        <div class="prog-container">
                            <div class="prog-label">
                                <span class="location-tag"><i class="fas fa-location-arrow text-muted me-1"></i> <?= htmlspecialchars($s['state'] ?: 'Unknown') ?>, <small class="text-muted"><?= htmlspecialchars($s['country'] ?: '') ?></small></span>
                                <span><?= number_format($s['views']) ?></span>
                            </div>
                            <div class="prog-bar-wrapper">
                                <div class="prog-bar-fill" style="width: <?= $pct ?>%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($statesList)): ?>
                        <p class="text-center text-muted py-4">No state logs found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Popular pages -->
        <div class="col-12 col-lg-4 col-md-12">
            <div class="card-theme h-100 mb-0">
                <div class="card-theme-header">
                    <h5 class="card-theme-title"><i class="fas fa-file-invoice me-2 text-accent"></i> Active Pages</h5>
                </div>
                <div class="card-theme-body">
                    <?php 
                    foreach ($pagesList as $p): 
                        $path = parse_url($p['page_url'], PHP_URL_PATH);
                        $filename = basename($path);
                        if (empty($filename) || $filename === '/') $filename = 'index.php (Home)';
                    ?>
                        <div class="prog-container">
                            <div class="prog-label">
                                <span style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($p['page_url']) ?>">
                                    <i class="fas fa-link text-muted me-1"></i> <?= htmlspecialchars($filename) ?>
                                </span>
                                <span class="fw-bold"><?= number_format($p['views']) ?> views</span>
                            </div>
                            <div class="prog-bar-wrapper">
                                <div class="prog-bar-fill" style="width: <?= $totalViews > 0 ? ($p['views'] / $totalViews) * 100 : 0 ?>%; background: linear-gradient(90deg, #7c3aed, #a78bfa);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($pagesList)): ?>
                        <p class="text-center text-muted py-4">No URL logs found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Live log -->
    <div class="card-theme">
        <div class="card-theme-header flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
            <h5 class="card-theme-title"><i class="fas fa-clipboard-list me-2 text-accent"></i> Detailed Geolocation Visitor Logs</h5>
            <?php if ($search): ?>
                <span class="badge bg-secondary rounded-pill">Search Filters: "<?= htmlspecialchars($search) ?>"</span>
            <?php endif; ?>
        </div>
        <div class="card-theme-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-striped mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3" style="width: 180px;">Timestamp</th>
                            <th style="width: 140px;">IP Address</th>
                            <th style="width: 150px;">Country</th>
                            <th style="width: 150px;">State / Region</th>
                            <th>Referrer URL</th>
                            <th>Logged Page</th>
                            <th class="text-center" style="width: 100px;">OS</th>
                            <th class="text-center" style="width: 100px;">Browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitorLog as $log): ?>
                        <tr>
                            <td class="px-3 text-muted" style="white-space: nowrap;">
                                <?= date('M j, Y - H:i A', strtotime($log['created_at'])) ?>
                            </td>
                            <td><span class="ip-badge"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                            <td class="fw-bold text-body"><?= htmlspecialchars($log['country'] ?: 'Unknown') ?></td>
                            <td><?= htmlspecialchars($log['state'] ?: 'Unknown') ?></td>
                            <td>
                                <div style="max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['referrer']) ?>">
                                    <?= htmlspecialchars($log['referrer'] ?: 'Direct') ?>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['page_url']) ?>">
                                    <span class="text-muted"><?= htmlspecialchars(basename(parse_url($log['page_url'], PHP_URL_PATH) ?: '/')) ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <i class="<?= getDeviceIcon($log['user_agent']) ?> ua-icon" style="font-size: 1.15rem;" title="<?= htmlspecialchars($log['user_agent']) ?>"></i>
                            </td>
                            <td class="text-center">
                                <i class="<?= getBrowserIcon($log['user_agent']) ?> ua-icon" style="font-size: 1.15rem;" title="<?= htmlspecialchars($log['user_agent']) ?>"></i>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($visitorLog)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No analytics log entries matching the selected parameters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 p-3 border-top">
            <div class="text-muted" style="font-size: 0.82rem;">
                Showing entries <strong><?= $offset + 1 ?></strong> to <strong><?= min($totalLogCount, $offset + $limit) ?></strong> of <strong><?= number_format($totalLogCount) ?></strong> records
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=1&range=<?= $range ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-double-left"></i></a>
                    </li>
                    <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&range=<?= $range ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-left"></i></a>
                    </li>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&range=<?= $range ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&range=<?= $range ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-right"></i></a>
                    </li>
                    <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $totalPages ?>&range=<?= $range ?>&search=<?= urlencode($search) ?>"><i class="fas fa-angle-double-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Render Javascript Chart.js configurations -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Visitor Trends Chart (7 Days Line Chart)
    const trendCtx = document.getElementById('trendsChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendDates) ?>,
            datasets: [
                {
                    label: 'Pageviews',
                    data: <?= json_encode($trendViews) ?>,
                    borderColor: '#ff6600',
                    backgroundColor: 'rgba(255, 102, 0, 0.08)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#ff6600',
                    pointRadius: 4
                },
                {
                    label: 'Unique Visitors',
                    data: <?= json_encode($trendUniques) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.04)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.35,
                    pointBackgroundColor: '#3b82f6',
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { family: 'Plus Jakarta Sans', weight: '700', size: 11 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } }
                }
            }
        }
    });

    // 2. Devices Share Doughnut Chart
    const devCtx = document.getElementById('deviceChart').getContext('2d');
    new Chart(devCtx, {
        type: 'doughnut',
        data: {
            labels: ['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Other'],
            datasets: [{
                data: [
                    <?= $winCount ?>, 
                    <?= $macCount ?>, 
                    <?= $linuxCount ?>, 
                    <?= $androidCount ?>, 
                    <?= $iosCount ?>, 
                    <?= $otherDevice ?>
                ],
                backgroundColor: ['#2563eb', '#0f172a', '#f59e0b', '#22c55e', '#06b6d4', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: { family: 'Plus Jakarta Sans', weight: '700', size: 9 }
                    }
                }
            },
            cutout: '65%'
        }
    });

    // 3. Browsers Share Doughnut Chart
    const brCtx = document.getElementById('browsersChart').getContext('2d');
    new Chart(brCtx, {
        type: 'doughnut',
        data: {
            labels: ['Chrome', 'Safari', 'Firefox', 'Edge', 'Opera', 'Other'],
            datasets: [{
                data: [
                    <?= $chromeCount ?>, 
                    <?= $safariCount ?>, 
                    <?= $firefoxCount ?>, 
                    <?= $edgeCount ?>, 
                    <?= $operaCount ?>, 
                    <?= $otherBrowser ?>
                ],
                backgroundColor: ['#16a34a', '#2563eb', '#ea580c', '#06b6d4', '#dc2626', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: { family: 'Plus Jakarta Sans', weight: '700', size: 9 }
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
