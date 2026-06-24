<?php
/**
 * WiseBot Daily AI Notification Cron Job
 * 
 * Generates AI-powered service notifications and sends them to users
 * via Firebase Cloud Messaging + DB notifications.
 * 
 * Usage:
 *   CLI:  php cron_daily_ai_notifications.php
 *   HTTP: https://yoursite.com/cron_daily_ai_notifications.php?secret=YOUR_CRON_SECRET
 *   HTTP (test): https://yoursite.com/cron_daily_ai_notifications.php?secret=YOUR_CRON_SECRET&test=1&industry=Fintech
 * 
 * Schedule (add to crontab):
 *   0 9 * * * php /path/to/cron_daily_ai_notifications.php
 *   (runs daily at 9:00 AM)
 */

// Security: CLI-only or require secret key
$isCLI = (php_sapi_name() === 'cli');
$CRON_SECRET = 'wqs_daily_ai_cron_2026_SecureKey!';

if (!$isCLI) {
    // HTTP access requires secret
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals($CRON_SECRET, $secret)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    // Set content type for HTTP responses
    header('Content-Type: application/json');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fcm_helper.php';

// === Dynamic AI Provider Configuration ===
try {
    $aiStmt = $pdo->query("SELECT api_endpoint, api_key, default_model FROM ai_providers WHERE is_active = 1 LIMIT 1");
    $aiConfig = $aiStmt->fetch(PDO::FETCH_ASSOC);
    if (!$aiConfig) {
        throw new Exception("No active AI provider configured.");
    }
    $apiKey = $aiConfig['api_key'];
    $apiEndpoint = $aiConfig['api_endpoint'];
    $apiModel = $aiConfig['default_model'];
} catch (Exception $e) {
    if (!$isCLI) { echo json_encode(['error' => "AI System Configuration Error: " . $e->getMessage()]); }
    else { echo "AI System Configuration Error: " . $e->getMessage() . "\n"; }
    exit;
}
$testMode = isset($_GET['test']) && $_GET['test'] == '1';
$testIndustry = $_GET['industry'] ?? null;
$testRole = $_GET['role'] ?? null;

$logFile = __DIR__ . '/logs/daily_ai_notifications.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMsg($msg) {
    global $logFile, $isCLI;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if ($isCLI) {
        echo $line;
    }
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function generateAINotificationContent($services, $pricing, $industry = null, $role = null) {
    global $apiKey, $apiEndpoint, $apiModel;
    
    $today = date('l, F j, Y');
    $dayOfWeek = date('l');
    
    $targetContext = "";
    if ($industry) $targetContext .= "Target industry: {$industry}. ";
    if ($role) $targetContext .= "Target audience role: {$role}. ";
    if (!$industry && !$role) $targetContext = "General audience (all users). ";
    
    // Determine theme based on day of week
    $themes = [
        'Monday' => 'motivation and new beginnings for the week',
        'Tuesday' => 'innovation and technology trends',
        'Wednesday' => 'mid-week productivity and business growth',
        'Thursday' => 'digital transformation and automation',
        'Friday' => 'celebrating achievements and looking ahead',
        'Saturday' => 'weekend learning and exploration',
        'Sunday' => 'planning and preparation for success'
    ];
    $theme = $themes[$dayOfWeek] ?? 'WQS services and solutions';

    $servicesList = "";
    foreach ($services as $svc) {
        $servicesList .= "- {$svc['name']}: {$svc['description']}\n";
    }
    
    $pricingList = "";
    foreach ($pricing as $prc) {
        $pricingList .= "- {$prc['name']}: ₦" . number_format($prc['price'], 2) . " ({$prc['price_label']})\n";
    }

    $prompt = "You are WiseBot, the AI assistant for Wise Quotient Soft (WQS), a premium software development company.\n\n";
    $prompt .= "TASK: Generate a SINGLE daily push notification message for users. This will be sent as a browser push notification.\n\n";
    $prompt .= "RULES:\n";
    $prompt .= "- Title: Maximum 50 characters, catchy and professional, NO emojis\n";
    $prompt .= "- Body: Maximum 150 characters, compelling, actionable, professional\n";
    $prompt .= "- Must be relevant to: {$targetContext}\n";
    $prompt .= "- Theme today: {$theme}\n";
    $prompt .= "- DO NOT use markdown, HTML, or code blocks\n";
    $prompt .= "- DO NOT include greetings like 'Hello' or 'Hi'\n";
    $prompt .= "- Be specific about ONE service or benefit, not generic\n";
    $prompt .= "- Include a subtle call-to-action (e.g., 'Learn more', 'Explore now', 'Get started')\n\n";
    $prompt .= "COMPANY SERVICES:\n{$servicesList}\n";
    $prompt .= "PRICING TIERS:\n{$pricingList}\n";
    $prompt .= "Today is {$today} ({$dayOfWeek}).\n\n";
    $prompt .= "OUTPUT FORMAT (strict JSON, no markdown):\n";
    $prompt .= '{"title": "...", "body": "...", "target_service": "..."}';

    $ch = curl_init($apiEndpoint);
    $data = [
        'model' => $apiModel,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional marketing copywriter for a software company. Output only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.8,
        'max_tokens' => 300
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error || !$response || $httpCode !== 200) {
        logMsg("AI API error: {$error} (HTTP {$httpCode})");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!$decoded || !isset($decoded['choices'][0]['message']['content'])) {
        logMsg("AI API unexpected response: " . substr($response, 0, 500));
        return null;
    }

    $rawContent = trim($decoded['choices'][0]['message']['content']);
    
    // Try to extract JSON from the response
    if (preg_match('/\{[^{}]*\}/s', $rawContent, $jsonMatch)) {
        $notification = json_decode($jsonMatch[0], true);
    } else {
        $notification = json_decode($rawContent, true);
    }

    if (!$notification || !isset($notification['title']) || !isset($notification['body'])) {
        logMsg("Failed to parse AI notification JSON: " . substr($rawContent, 0, 300));
        return null;
    }

    return [
        'title' => substr($notification['title'], 0, 50),
        'body' => substr($notification['body'], 0, 150),
        'target_service' => $notification['target_service'] ?? null,
        'ai_prompt' => $prompt
    ];
}

function getTargetUsers($pdo, $industry = null, $role = null) {
    $sql = "SELECT u.id, u.name, u.email, up.preferences_json 
            FROM users u 
            LEFT JOIN user_preferences up ON u.id = up.user_id 
            WHERE u.role = 'user' AND u.status != 'blocked'";
    
    $params = [];
    if ($industry) {
        $sql .= " AND up.preferences_json LIKE ?";
        $params[] = '%"' . $industry . '"%';
    }
    if ($role) {
        $sql .= " AND up.preferences_json LIKE ?";
        $params[] = '%"' . $role . '"%';
    }
    
    $sql .= " ORDER BY u.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEnabledPushUsers($pdo, $industry = null, $role = null) {
    $sql = "SELECT DISTINCT u.id, u.name, u.email 
            FROM users u 
            LEFT JOIN user_notification_settings uns ON u.id = uns.user_id
            LEFT JOIN user_preferences up ON u.id = up.user_id
            WHERE u.role = 'user' 
            AND u.status != 'blocked'
            AND (uns.enable_push_notifications IS NULL OR uns.enable_push_notifications = 1)";
    
    $params = [];
    if ($industry) {
        $sql .= " AND up.preferences_json LIKE ?";
        $params[] = '%"' . $industry . '"%';
    }
    if ($role) {
        $sql .= " AND up.preferences_json LIKE ?";
        $params[] = '%"' . $role . '"%';
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===================== MAIN EXECUTION =====================
logMsg("=== Daily AI Notification Job Started ===");

// Check if we already sent today (unless test mode)
if (!$testMode) {
    $todayCheck = $pdo->prepare("SELECT COUNT(*) as cnt FROM daily_ai_notifications WHERE DATE(sent_at) = CURDATE() AND status = 'sent'");
    $todayCheck->execute();
    $todayRow = $todayCheck->fetch(PDO::FETCH_ASSOC);
    if ($todayRow && $todayRow['cnt'] > 0) {
        logMsg("Already sent today. Skipping.");
        echo json_encode(['success' => true, 'message' => 'Already sent today', 'skipped' => true]);
        exit;
    }
}

// Fetch services and pricing
$services = $pdo->query("SELECT name, description FROM services WHERE category='service' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$pricing = $pdo->query("SELECT name, price, price_label, features FROM services WHERE category='pricing' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);

if (empty($services)) {
    $services = [
        ['name' => 'Custom Software Development', 'description' => 'Tailored software solutions for businesses'],
        ['name' => 'Web & Mobile Applications', 'description' => 'Modern web and mobile app development'],
        ['name' => 'AI & Automation', 'description' => 'Machine learning and intelligent automation'],
    ];
}

// Generate AI content
logMsg("Generating AI notification content...");
$notification = generateAINotificationContent($services, $pricing, $testIndustry, $testRole);

if (!$notification) {
    logMsg("Failed to generate notification content. Using fallback.");
    // Fallback content
    $fallbackServices = [
        ['title' => 'Build Your Dream App', 'body' => 'From idea to launch — WQS delivers custom web & mobile apps. Explore our services today!', 'target_service' => 'Web & Mobile Development'],
        ['title' => 'Automate Your Business', 'body' => 'AI-powered solutions to streamline operations. Let WQS transform your workflow.', 'target_service' => 'AI & Automation'],
        ['title' => 'Scale With Cloud', 'body' => 'Enterprise cloud architecture for growing businesses. Start your digital journey.', 'target_service' => 'Cloud Integration'],
    ];
    $notification = $fallbackServices[array_rand($fallbackServices)];
    $notification['ai_prompt'] = 'fallback_content';
}

logMsg("Notification: [{$notification['title']}] {$notification['body']}");

// Get target users
$targetIndustry = $testIndustry ?: null;
$targetRole = $testRole ?: null;

$users = getEnabledPushUsers($pdo, $targetIndustry, $targetRole);
$totalUsers = count($users);
logMsg("Target users: {$totalUsers}" . ($targetIndustry ? " (industry: {$targetIndustry})" : "") . ($targetRole ? " (role: {$targetRole})" : ""));

if ($totalUsers === 0) {
    logMsg("No target users found. Exiting.");
    echo json_encode(['success' => true, 'message' => 'No target users', 'sent' => 0]);
    exit;
}

// Record the notification
$insStmt = $pdo->prepare("INSERT INTO daily_ai_notifications (title, message, target_industry, target_role, notification_type, sent_to_count, ai_model, ai_prompt_used, sent_at, status, created_by) VALUES (?, ?, ?, ?, 'service_promo', ?, ?, ?, NOW(), 'sent', NULL)");
$insStmt->execute([
    $notification['title'],
    $notification['body'],
    $targetIndustry,
    $targetRole,
    $totalUsers,
    $apiModel,
    $notification['ai_prompt'] ?? null
]);
$notifId = $pdo->lastInsertId();
logMsg("Notification record created: ID {$notifId}");

// Send to all target users
$successCount = 0;
$failCount = 0;
$clickUrl = '/dashboard/wqs/user/dashboard.php';

foreach ($users as $user) {
    try {
        // Create DB notification
        $dbStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, notification_type, target_url, target_id, is_read) VALUES (?, ?, ?, 'service_promo', ?, ?, 0)");
        $dbStmt->execute([$user['id'], $notification['title'], $notification['body'], $clickUrl, $notifId]);
        
        // Send FCM push
        $fcmResult = FCMHelper::sendNotificationToUser($user['id'], $notification['title'], $notification['body'], ['click_action' => $clickUrl]);
        
        // Count as success since DB notification was successfully saved
        $successCount++;
    } catch (Exception $e) {
        $failCount++;
        logMsg("Error sending to user {$user['id']}: " . $e->getMessage());
    }
}

logMsg("=== Job Complete: {$successCount} sent, {$failCount} failed, {$totalUsers} total ===");

// Update the record with actual counts
$pdo->prepare("UPDATE daily_ai_notifications SET sent_to_count = ? WHERE id = ?")->execute([$successCount, $notifId]);

echo json_encode([
    'success' => true,
    'notification_id' => (int)$notifId,
    'title' => $notification['title'],
    'body' => $notification['body'],
    'target_industry' => $targetIndustry,
    'target_role' => $targetRole,
    'total_users' => $totalUsers,
    'sent' => $successCount,
    'failed' => $failCount,
    'sent_at' => date('Y-m-d H:i:s')
]);
