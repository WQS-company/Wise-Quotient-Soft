<?php
// API Endpoint: Log Custom Analytics Events (conversions, user engagement, page visits)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Helper to get client IP safely
if (!function_exists('getClientIP')) {
    function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return trim($ip);
    }
}

// Simple Device and Browser detection
function detectDeviceAndBrowser($ua) {
    $browser = 'Unknown';
    $device = 'Desktop';

    if (preg_match('/mobile/i', $ua)) {
        $device = 'Mobile';
    } elseif (preg_match('/tablet|ipad/i', $ua)) {
        $device = 'Tablet';
    }

    if (preg_match('/chrome/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/edge|edg/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/msie|trident/i', $ua)) {
        $browser = 'IE';
    }
    return [$device, $browser];
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$event_name  = trim($data['event_name'] ?? '');
$event_value = trim($data['event_value'] ?? '');
$page_url    = trim($data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? 'Unknown');
$referrer    = trim($data['referrer'] ?? 'Direct');

if (empty($event_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event name is required.']);
    exit;
}

// Ensure session ID exists for retention funnel tracking
if (!isset($_SESSION['analytics_session_id'])) {
    $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
}
$session_id = $_SESSION['analytics_session_id'];
$userId     = $_SESSION['user']['id'] ?? null;

$ip         = getClientIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
list($device_type, $browser) = detectDeviceAndBrowser($user_agent);

// Use cached geolocation (prevents ipapi.co 429 rate limiting)
$geo = wqs_geolocate_ip($ip);
$country = $geo['country'];
$state   = $geo['state'];

try {
    $stmt = $pdo->prepare("INSERT INTO `firebase_analytics_events` 
        (`session_id`, `user_id`, `event_name`, `event_value`, `ip_address`, `country`, `state`, `user_agent`, `referrer`, `device_type`, `browser`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $session_id,
        $userId,
        $event_name,
        $event_value,
        $ip,
        $country,
        $state,
        $user_agent,
        $referrer,
        $device_type,
        $browser
    ]);

    // Also write simple page views to standard analytics_log to keep compatibility with existing counters if necessary
    if ($event_name === 'page_view') {
        $stmt_old = $pdo->prepare("INSERT INTO `analytics_log` (`ip_address`, `country`, `state`, `user_agent`, `page_url`, `referrer`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_old->execute([$ip, $country, $state, $user_agent, $page_url, $referrer]);
    }

    echo json_encode(['success' => true, 'message' => 'Event logged successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database write failed: ' . $e->getMessage()]);
}
