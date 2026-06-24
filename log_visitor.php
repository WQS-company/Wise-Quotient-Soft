<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return trim($_SERVER['HTTP_CLIENT_IP']);
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) $data = $_POST;

$ip = trim($data['ip'] ?? $data['ip_address'] ?? getClientIP());
$country = trim($data['country'] ?? 'Unknown');
$state = trim($data['state'] ?? $data['region'] ?? 'Unknown');
$page_url = trim($data['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? 'Unknown');
$referrer = trim($data['referrer'] ?? 'Direct');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Use cached geolocation (prevents ipapi.co 429 rate limiting)
if (($country === 'Unknown' || $state === 'Unknown') && !empty($ip)) {
    $geo = wqs_geolocate_ip($ip);
    $country = $geo['country'];
    $state = $geo['state'];
}

try {
    $stmt = $pdo->prepare("INSERT INTO `analytics_log` (`ip_address`, `country`, `state`, `user_agent`, `page_url`, `referrer`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ip, $country, $state, $user_agent, $page_url, $referrer]);
    echo json_encode(["status" => "success", "message" => "Visit recorded"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database write failed"]);
}
