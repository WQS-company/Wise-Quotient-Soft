<?php
header('Content-Type: application/json');

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database configuration
require_once __DIR__ . '/config.php';

// Load Cloudinary upload helper
require_once __DIR__ . '/includes/cloudinary.php';

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
    echo json_encode(['reply' => "❌ AI System Configuration Error: " . $e->getMessage()]);
    exit;
}

// === PDF Text Extractor Helper ===
function extractTextFromPdf($filename) {
    $content = file_get_contents($filename);
    if (!$content) return "";
    
    $result = "";
    
    // Extract standard Tj / TJ brackets text
    preg_match_all("/\((.*?)\)\s*(?:Tj|TJ)/s", $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $m) {
            // Unescape octal codes e.g. \376
            $m = preg_replace_callback('/\\\\([0-7]{3})/', function($match) {
                return chr(octdec($match[1]));
            }, $m);
            $result .= $m . " ";
        }
    }
    
    // Fallback block analysis
    if (empty($result)) {
        preg_match_all("/BT(.*?)ET/s", $content, $blocks);
        if (!empty($blocks[1])) {
            foreach ($blocks[1] as $block) {
                preg_match_all("/\((.*?)\)/s", $block, $submatches);
                if (!empty($submatches[1])) {
                    $result .= implode(" ", $submatches[1]) . " ";
                }
            }
        }
    }
    
    // Format escape characters
    $result = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $result);
    return trim(strip_tags($result));
}

// === Error logging helper: shows friendly message to users, logs details for admins ===
function botError($pdo, $userMessage, $errorDetail) {
    // Log the actual error to a file
    $logLine = "[" . date('Y-m-d H:i:s') . "] USER: " . ($_SESSION['user']['name'] ?? 'Guest') . " (" . ($_SESSION['user']['id'] ?? '0') . ") | " . $errorDetail . "\n";
    @file_put_contents(__DIR__ . '/bot_errors.log', $logLine, FILE_APPEND | LOCK_EX);
    // Also log to database if the table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bot_error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            error_message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $stmt = $pdo->prepare("INSERT INTO bot_error_logs (user_id, error_message) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user']['id'] ?? null, $errorDetail]);
    } catch (Exception $e) {}
    return "🤖 I'm a little bit busy right now. Please try again in a moment.";
}

// === Get the JSON payload from JS ===
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$fileData = $input['file'] ?? null;
$action = $input['action'] ?? '';

if (!$userMessage && !$fileData && !$action) {
    echo json_encode(['reply' => "❌ Message is empty."]);
    exit;
}

// === Custom Endpoints for Chat History & Sessions ===
if ($action === 'fetch_sessions') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
    }
    $uid = $_SESSION['user']['id'];
    try {
        $stmt = $pdo->prepare("SELECT session_id, topic, created_at FROM bot_chat_sessions WHERE user_id = ? AND is_deleted_by_user = 0 ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$uid]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'sessions' => $sessions]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_session_history') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
    }
    $targetSession = $input['target_session'] ?? '';
    $uid = $_SESSION['user']['id'];
    try {
        // Only fetch if session belongs to user and is not soft deleted
        $check = $pdo->prepare("SELECT 1 FROM bot_chat_sessions WHERE session_id = ? AND user_id = ? AND is_deleted_by_user = 0");
        $check->execute([$targetSession, $uid]);
        if (!$check->fetch()) {
             echo json_encode(['success' => false, 'error' => 'Session not found']); exit;
        }

        $stmt = $pdo->prepare("SELECT role, message, created_at FROM bot_chats WHERE session_id = ? AND is_deleted_by_user = 0 ORDER BY created_at ASC");
        $stmt->execute([$targetSession]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update current session to be this one so they can resume
        session_id($targetSession); 
        
        echo json_encode(['success' => true, 'history' => $history]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_session') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
    }
    $targetSession = $input['target_session'] ?? '';
    $uid = $_SESSION['user']['id'];
    try {
        $pdo->prepare("UPDATE bot_chat_sessions SET is_deleted_by_user = 1 WHERE session_id = ? AND user_id = ?")->execute([$targetSession, $uid]);
        $pdo->prepare("UPDATE bot_chats SET is_deleted_by_user = 1 WHERE session_id = ? AND user_id = ?")->execute([$targetSession, $uid]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


// === Chat Real-Time Endpoints ===
if ($action === 'set_typing_status') {
    $targetSession = $input['target_session'] ?? session_id();
    $isTyping = $input['is_typing'] ?? false;
    $role = $input['role'] ?? 'user'; // 'user' or 'admin'
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bot_chat_typing (
            session_id VARCHAR(100) NOT NULL,
            role VARCHAR(20) NOT NULL,
            last_typed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id, role)
        ) ENGINE=InnoDB");
        
        if ($isTyping) {
            $stmt = $pdo->prepare("INSERT INTO bot_chat_typing (session_id, role, last_typed) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_typed = NOW()");
            $stmt->execute([$targetSession, $role]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM bot_chat_typing WHERE session_id = ? AND role = ?");
            $stmt->execute([$targetSession, $role]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'poll_chat_updates') {
    $targetSession = $input['target_session'] ?? session_id();
    $lastId = $input['last_id'] ?? 0;
    
    try {
        // Fetch new messages
        $stmt = $pdo->prepare("SELECT id, role, message, created_at FROM bot_chats WHERE session_id = ? AND is_deleted_by_user = 0 AND id > ? ORDER BY id ASC");
        $stmt->execute([$targetSession, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch typing status
        $pdo->exec("CREATE TABLE IF NOT EXISTS bot_chat_typing (
            session_id VARCHAR(100) NOT NULL,
            role VARCHAR(20) NOT NULL,
            last_typed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id, role)
        ) ENGINE=InnoDB");
        $stmtTyping = $pdo->prepare("SELECT role FROM bot_chat_typing WHERE session_id = ? AND last_typed > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        $stmtTyping->execute([$targetSession]);
        $typingRoles = $stmtTyping->fetchAll(PDO::FETCH_COLUMN);
        
        // Check online status if admin is polling
        $isOnline = false;
        $stmtUid = $pdo->prepare("SELECT user_id FROM bot_chats WHERE session_id = ? AND user_id IS NOT NULL LIMIT 1");
        $stmtUid->execute([$targetSession]);
        $uidRow = $stmtUid->fetch(PDO::FETCH_ASSOC);
        if ($uidRow && $uidRow['user_id']) {
            $uStmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
            $uStmt->execute([$uidRow['user_id']]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['last_login']) {
                $lastActive = strtotime($user['last_login']);
                if (time() - $lastActive < 300) {
                    $isOnline = true;
                }
            }
        }
        
        echo json_encode(['success' => true, 'messages' => $messages, 'typing' => $typingRoles, 'is_online' => $isOnline]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_chat_meta') {
    $targetSession = $input['target_session'] ?? session_id();
    try {
        // Find user_id from bot_chats
        $stmt = $pdo->prepare("SELECT user_id FROM bot_chats WHERE session_id = ? AND user_id IS NOT NULL LIMIT 1");
        $stmt->execute([$targetSession]);
        $uidRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($uidRow && $uidRow['user_id']) {
            $uStmt = $pdo->prepare("SELECT name, email, last_login FROM users WHERE id = ?");
            $uStmt->execute([$uidRow['user_id']]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if online (active within last 5 mins)
            $isOnline = false;
            if ($user && $user['last_login']) {
                $lastActive = strtotime($user['last_login']);
                if (time() - $lastActive < 300) {
                    $isOnline = true;
                }
            }
            
            echo json_encode([
                'success' => true,
                'name' => $user['name'] ?? 'Guest',
                'email' => $user['email'] ?? '',
                'is_online' => $isOnline
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'name' => 'Guest User',
                'email' => '',
                'is_online' => false
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AI form fill for client-request.php
if ($action === 'fill_form') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['error' => 'Not logged in.']); exit;
    }
    $userDesc = trim($input['description'] ?? '');
    if (!$userDesc) {
        echo json_encode(['error' => 'Description is required.']); exit;
    }
    $sampleLink = trim($input['sample_link'] ?? '');
    $sampleImage = trim($input['sample_image'] ?? '');
    $extraContext = '';
    if ($sampleLink) {
        $extraContext .= "\nThe user also provided a reference link for inspiration: {$sampleLink}\n";
    }
    if ($sampleImage) {
        $extraContext .= "\nThe user also shared a sample image (base64). Analyze the image visually to extract any design cues, themes, or layout ideas that may inform the project.\n";
    }
    $fillPrompt = "You are a professional project analyst assistant for Wise Quotient Soft (WQS). A user has provided a description of their project idea. " .
                  "Your task is to refine this input into a highly professional, grammatically correct project proposal. " .
                  "Please perform the following corrections:\n" .
                  "1. Title Correction: Correct and polish the project title. If the user input is a brief phrase or contains grammar/casing issues (e.g., 'Farm mobile App'), correct the grammar and casing to be a formal, professional software project title (e.g., 'Agricultural Management Mobile Application' or 'Farm Mobile Application').\n" .
                  "2. Description Grammar Correction: Rewrite and polish the user's description. Correct all grammatical errors, typos, spelling, and phrasing. Make the output sound highly professional and present it as a 2-3 sentence summary.\n\n" .
                  "Return ONLY valid JSON (no markdown fences, no code block wrapping) with these exact keys: title, description, category (one of: School, Company, Startup, E-commerce, Healthcare, Fintech, Other), software_type (one of: Web App, Mobile App, Mobile App & Web App, Desktop App, Other), features (comma-separated list of relevant features from: Emailing, SMS, Voice Call, USSD, AI Features, Machine Learning, Cloud Storage, Payment Integration, Analytics Dashboard), budget (estimated budget range), timeline (estimated timeline), company_name, contact_person, phone.\n\nUser description: " . $userDesc . $extraContext;

    // Build messages array (support multimodal if sample image provided)
    $messages = [];
    if ($sampleImage) {
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $fillPrompt],
                ['type' => 'image_url', 'image_url' => ['url' => $sampleImage]]
            ]
        ];
    } else {
        $messages[] = ['role' => 'user', 'content' => $fillPrompt];
    }

    $ch = curl_init($apiEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => $apiModel, 'messages' => $messages])
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || !$resp) {
        echo json_encode(['error' => 'AI service unavailable.']); exit;
    }
    $dec = json_decode($resp, true);
    $content = $dec['choices'][0]['message']['content'] ?? '';
    $content = trim($content);
    // Strip any markdown code fences
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```\s*$/', '', $content);
    $data = json_decode($content, true);
    if (!$data) {
        echo json_encode(['error' => 'Failed to parse AI response.', 'raw' => $content]); exit;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Handle project request creation from bot
if ($action === 'create_request') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in to create a project request."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $reqTitle = trim($input['title'] ?? '');
    $reqDesc = trim($input['description'] ?? '');
    $reqCategory = trim($input['category'] ?? 'Other');
    $reqSoftwareType = trim($input['software_type'] ?? 'Web App');
    $reqFeatures = trim($input['features'] ?? '');
    $reqBudget = trim($input['budget'] ?? '');
    $reqCompany = trim($input['company_name'] ?? '');
    $reqContact = trim($input['contact_person'] ?? '');
    $reqPhone = trim($input['phone'] ?? '');
    $reqTimeline = trim($input['timeline'] ?? '');

    if (!$reqTitle || !$reqDesc) {
        echo json_encode(['reply' => "❌ Title and description are required."]);
        exit;
    }

    // Build compiled description with all details
    $compiledDesc = "PROJECT DETAILS & DESCRIPTION:\n" . $reqDesc . "\n\n";
    if ($reqCompany || $reqContact) {
        $compiledDesc .= "--- CLIENT INFORMATION ---\n" .
            "Company/Client Name: " . $reqCompany . "\n" .
            "Contact Person: " . $reqContact . "\n" .
            "Phone Number: " . $reqPhone . "\n\n";
    }
    $compiledDesc .= "--- TIMELINE & BUDGET ---\n" .
        "Timeline: " . $reqTimeline . "\n" .
        "Budget: " . $reqBudget . "\n\n";
    $compiledDesc .= "--- FEATURES ---\n" . $reqFeatures;

    try {
        $stmt = $pdo->prepare("INSERT INTO client_requests (user_id, title, description, categories, software_type, features, recommendations) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $reqTitle, $compiledDesc, $reqCategory, $reqSoftwareType, $reqFeatures, '']);
        $requestId = $pdo->lastInsertId();
        add_notification($userId, "Project Request Created by WiseBot", "Your project request '{$reqTitle}' has been created by WiseBot and is pending review.", 'project', '../user/my_requests.php', $requestId);
        echo json_encode(['reply' => "✅ Project request **created successfully!** Your request ID is `#$requestId`. You can track it anytime by asking me, or visit your dashboard.\n\n*Would you like me to help with anything else?*"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "create_project: " . $e->getMessage())]);
        exit;
    }
}

// === Handle partnership request from bot ===
if ($action === 'partner_request') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in to apply for partnership."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    try {
        $check = $pdo->query("SELECT id FROM agent_requests WHERE user_id = $userId")->fetch();
        if ($check) {
            $pdo->query("UPDATE agent_requests SET status = 'pending', updated_at = NOW() WHERE user_id = $userId");
        } else {
            $pdo->exec("INSERT INTO agent_requests (user_id, status, created_at, updated_at) VALUES ($userId, 'pending', NOW(), NOW())");
        }
        add_notification($userId, "Partnership Application Submitted", "Your partnership application has been submitted! We'll review it within 24–48 hours.", 'partner', '../user/upgrade_partner.php');
        echo json_encode(['reply' => "✅ **Partnership application submitted!** We'll review it within 24–48 hours and notify you once approved. In the meantime, feel free to ask about our referral benefits or commission structure."]);
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "partner_request: " . $e->getMessage())]);
    }
    exit;
}

// === Handle developer upgrade request from bot ===
if ($action === 'developer_request') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in to apply as a developer."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $skills = trim($input['skills'] ?? '');
    $experience = trim($input['experience'] ?? '');
    $portfolioUrl = trim($input['portfolio_url'] ?? '');
    $githubUrl = trim($input['github_url'] ?? '');
    $yearsExp = (int)($input['years_experience'] ?? 0);
    $hourlyRate = (float)($input['hourly_rate'] ?? 0);

    if (!$skills || !$experience) {
        echo json_encode(['reply' => "❌ Skills and experience are required for developer application."]);
        exit;
    }

    try {
        $check = $pdo->query("SELECT id, status FROM developer_requests WHERE user_id = $userId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $skillsJson = json_encode(array_map('trim', explode(',', $skills)));
        if ($check) {
            $pdo->prepare("UPDATE developer_requests SET skills=?, portfolio_url=?, github_url=?, experience=?, years_experience=?, hourly_rate_expected=?, status='pending', updated_at=NOW() WHERE user_id=?")
                ->execute([$skillsJson, $portfolioUrl, $githubUrl, $experience, $yearsExp, $hourlyRate, $userId]);
        } else {
            $pdo->prepare("INSERT INTO developer_requests (user_id, skills, portfolio_url, github_url, experience, years_experience, hourly_rate_expected, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,'pending',NOW(),NOW())")
                ->execute([$userId, $skillsJson, $portfolioUrl, $githubUrl, $experience, $yearsExp, $hourlyRate]);
        }
        add_notification($userId, "Developer Application Submitted", "Your developer application has been submitted! We'll review it within 24–48 hours.", 'project', '../user/developer_hub.php');
        echo json_encode(['reply' => "✅ **Developer application submitted!** We'll review your application within 24–48 hours. Once approved, you'll get access to the Developer Hub with task boards and project assignments.\n\n*Would you like to ask anything else?*"]);
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "developer_request: " . $e->getMessage())]);
    }
    exit;
}

// === Handle profile update from bot ===
if ($action === 'update_profile') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in to update your profile."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $bio = trim($input['bio'] ?? '');
    $company = trim($input['company'] ?? '');
    $profession = trim($input['profession'] ?? '');
    $skills = trim($input['skills'] ?? '');
    $tech_stack = trim($input['tech_stack'] ?? '');
    $previous_experience = trim($input['previous_experience'] ?? '');
    $education = trim($input['education'] ?? '');
    $linkedin = trim($input['linkedin_url'] ?? '');
    $twitter = trim($input['twitter_url'] ?? '');
    $github = trim($input['github_url'] ?? '');
    $website = trim($input['website_url'] ?? '');

    if (!$name) {
        echo json_encode(['reply' => "❌ Name is required to update your profile."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, bio=?, company=?, profession=?, skills=?, tech_stack=?, previous_experience=?, education=?, linkedin_url=?, twitter_url=?, github_url=?, website_url=? WHERE id=?");
        $stmt->execute([$name, $phone, $bio, $company, $profession, $skills, $tech_stack, $previous_experience, $education, $linkedin, $twitter, $github, $website, $userId]);
        if ($name) $_SESSION['user']['name'] = $name;
        echo json_encode(['reply' => "✅ **Profile updated successfully!** Your changes have been saved.\n\n*Is there anything else I can help you with?*"]);
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "update_profile: " . $e->getMessage())]);
    }
    exit;
}

// === Cancel partnership request from bot ===
if ($action === 'cancel_partner') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM agent_requests WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['reply' => "✅ **Partnership application cancelled.** Your application has been withdrawn. You can re-apply anytime you're ready."]);
        } else {
            echo json_encode(['reply' => "ℹ️ You don't have a pending partnership application to cancel."]);
        }
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "cancel_partner: " . $e->getMessage())]);
    }
    exit;
}

// === Cancel developer request from bot ===
if ($action === 'cancel_developer') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM developer_requests WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['reply' => "✅ **Developer application cancelled.** Your application has been withdrawn. You can re-apply anytime."]);
        } else {
            echo json_encode(['reply' => "ℹ️ You don't have a pending developer application to cancel."]);
        }
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "cancel_developer: " . $e->getMessage())]);
    }
    exit;
}

// === Update developer request from bot ===
if ($action === 'update_developer_request') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $fields = [];
    $params = [];
    $allowed = ['skills', 'portfolio_url', 'github_url', 'experience', 'years_experience', 'hourly_rate'];
    foreach ($allowed as $f) {
        if (isset($input[$f])) {
            if ($f === 'skills') {
                $val = json_encode(array_map('trim', explode(',', $input[$f])));
            } elseif ($f === 'years_experience') {
                $val = (int)$input[$f];
            } elseif ($f === 'hourly_rate') {
                $val = (float)$input[$f];
            } else {
                $val = trim($input[$f]);
            }
            $fields[] = "$f = ?";
            $params[] = $val;
        }
    }
    if (empty($fields)) {
        echo json_encode(['reply' => "❌ No fields provided to update."]);
        exit;
    }
    $fields[] = "status = 'pending'";
    $fields[] = "updated_at = NOW()";
    $params[] = $userId;
    try {
        $pdo->prepare("UPDATE developer_requests SET " . implode(', ', $fields) . " WHERE user_id = ?")->execute($params);
        echo json_encode(['reply' => "✅ **Developer application updated!** Your changes have been saved and the application is now pending review."]);
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "update_developer_request: " . $e->getMessage())]);
    }
    exit;
}

// === Toggle theme from bot ===
if ($action === 'toggle_theme') {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['reply' => "❌ You must be logged in to change theme."]);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $targetTheme = trim($input['theme'] ?? '');
    try {
        $currentStmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
        $currentStmt->execute([$userId]);
        $currentTheme = $currentStmt->fetchColumn() ?: 'light';
        if ($targetTheme && in_array($targetTheme, ['light', 'dark'])) {
            $newTheme = $targetTheme;
        } else {
            $newTheme = $currentTheme === 'light' ? 'dark' : 'light';
        }
        $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$newTheme, $userId]);
        $icon = $newTheme === 'dark' ? '🌙' : '☀️';
        $label = $newTheme === 'dark' ? 'Dark' : 'Light';
        echo json_encode(['reply' => "{$icon} **Theme switched to {$label} mode!** The change is saved and will apply across all pages on your next load.",
                         'theme' => $newTheme]);
    } catch (Exception $e) {
        echo json_encode(['reply' => botError($pdo, $userMessage, "toggle_theme: " . $e->getMessage())]);
    }
    exit;
}

// === Find ad images action (Unsplash search) ===
if ($action === 'find_ad_images') {
    $query = trim($input['query'] ?? 'software development');
    $url = "https://api.unsplash.com/search/photos?query=" . urlencode($query) . "&per_page=6&orientation=landscape";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Client-ID jG9h2M7RqD4sK8wX1pL3nF6bV9cA0eT5yU8iO2fH']);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http >= 200 && $http < 300) {
        $data = json_decode($resp, true);
        $images = [];
        foreach (($data['results'] ?? []) as $img) {
            $images[] = [
                'url' => $img['urls']['regular'],
                'thumb' => $img['urls']['thumb'],
                'alt' => $img['alt_description'] ?? 'Stock image',
                'author' => $img['user']['name'] ?? '',
                'author_url' => $img['user']['links']['html'] ?? ''
            ];
        }
        echo json_encode(['success' => true, 'images' => $images, 'query' => $query]);
    } else {
        echo json_encode(['error' => 'Could not fetch images.']);
    }
    exit;
}

// === Personalize hero copy via AI ===
if ($action === 'personalize_hero') {
    $industry = trim($input['industry'] ?? '');
    if (!$industry) {
        echo json_encode(['error' => 'Industry is required.']);
        exit;
    }

    // Call OpenRouter AI
    $prompt = "You are a professional conversion copywriter for Wise Quotient Soft (WQS), a premium software development agency. " .
              "Your task is to rewrite the website's main hero section to target a visitor from the following industry: \"{$industry}\".\n" .
              "Please generate:\n" .
              "1. A badge/label (e.g. \"Custom Logistics Software Solutions\" or \"Innovative Agriculture Technology\") under 40 characters.\n" .
              "2. A compelling headline (e.g. \"Intelligent Software.<br><span class=\\\"hero-gradient-text\\\">Built for Logistics.</span>\") under 60 characters. It MUST use the format with <br> and a span with class \"hero-gradient-text\" to color the second line.\n" .
              "3. A descriptive subtitle explaining what WQS does for their industry (e.g., how we build state-of-the-art platforms or custom systems to automate operations or drive growth) under 180 characters.\n\n" .
              "Return ONLY a valid JSON object (no markdown, no code block wrapper) with these keys: badge, headline, subtitle.";

    $messages = [['role' => 'user', 'content' => $prompt]];

    $ch = curl_init($apiEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => $apiModel, 'messages' => $messages])
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) {
        // Fallback in case of OpenRouter error
        $fallbackBadge = ucfirst($industry) . " Software Agency";
        $fallbackHeadline = "Intelligent Software.<br><span class=\"hero-gradient-text\">Built for " . ucfirst($industry) . ".</span>";
        $fallbackSubtitle = "Wise Quotient Soft designs and builds state-of-the-art web platforms, mobile applications, and custom desktop clients for businesses globally.";
        echo json_encode([
            'success' => true,
            'data' => [
                'badge' => $fallbackBadge,
                'headline' => $fallbackHeadline,
                'subtitle' => $fallbackSubtitle
            ]
        ]);
        exit;
    }

    $dec = json_decode($resp, true);
    $content = $dec['choices'][0]['message']['content'] ?? '';
    $content = trim($content);
    // Strip markdown code blocks
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```\s*$/', '', $content);
    
    $data = json_decode($content, true);
    if (!$data || !isset($data['badge']) || !isset($data['headline']) || !isset($data['subtitle'])) {
        // Fallback in case of parsing error
        $fallbackBadge = ucfirst($industry) . " Software Solutions";
        $fallbackHeadline = "Intelligent Software.<br><span class=\"hero-gradient-text\">Built for " . ucfirst($industry) . ".</span>";
        $fallbackSubtitle = "Wise Quotient Soft designs and builds state-of-the-art web platforms, mobile applications, and custom desktop clients for businesses globally.";
        echo json_encode([
            'success' => true,
            'data' => [
                'badge' => $fallbackBadge,
                'headline' => $fallbackHeadline,
                'subtitle' => $fallbackSubtitle
            ]
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

$user_id = $_SESSION['user']['id'] ?? null;
$session_id = session_id();

// Bridge any previous guest messages under the same session to this user if logged in
if ($user_id) {
    try {
        $bridgeStmt = $pdo->prepare("UPDATE bot_chats SET user_id = ? WHERE session_id = ? AND user_id IS NULL");
        $bridgeStmt->execute([$user_id, $session_id]);
    } catch (Exception $e) {
        // Fail-safe
    }
}

// === Build Platform Knowledge Base & Company Context ===
$company_kb = "Wise Quotient Soft (WQS) - Platform Knowledge:\n";

// Fetch Services
try {
    $services = $pdo->query("SELECT name, description FROM services WHERE category='service' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
    $company_kb .= "\nOffered Services:\n";
    foreach ($services as $svc) {
        $company_kb .= "- " . $svc['name'] . ": " . $svc['description'] . "\n";
    }
} catch (Exception $e) {
    $company_kb .= "- Custom Software Development, Web & Mobile Applications, AI & ML Automation, cloud systems, and School Portals.\n";
}

// Fetch Pricing Tiers
try {
    $pricing = $pdo->query("SELECT name, price, price_label, features FROM services WHERE category='pricing' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
    $company_kb .= "\nPricing and Budget Tiers:\n";
    foreach ($pricing as $prc) {
        $features = str_replace("\n", ", ", $prc['features'] ?? '');
        $company_kb .= "- " . $prc['name'] . ": ₦" . number_format($prc['price'], 2) . " " . $prc['price_label'] . " (" . $features . ")\n";
    }
} catch (Exception $e) {
    $company_kb .= "- Starter Project: ₦155,000\n- Standard Project: ₦3,875,000\n- Professional Project: ₦11,625,000\n- Enterprise Software: ₦38,750,000+\n";
}

// Fetch Company Contact Info
try {
    $fRes = $pdo->query("SELECT setting_key, setting_value FROM footer_settings");
    $settings = [];
    while ($row = $fRes->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $company_kb .= "\nCompany Contacts:\n";
    $company_kb .= "- Address: " . ($settings['contact_address'] ?? 'No.1 Ibadan Street Kaduna, Nigeria') . "\n";
    $company_kb .= "- Phone: " . ($settings['contact_phone'] ?? '+2348077416106') . "\n";
    $company_kb .= "- Email: " . ($settings['contact_email'] ?? 'info@wisequotient.com') . "\n";
} catch (Exception $e) {
    $company_kb .= "\nCompany Contacts:\n- Phone: +2348077416106\n- Email: info@wisequotient.com\n- Address: No.1 Ibadan Street Kaduna, Nigeria\n";
}

// === Build Active User Profile Context ===
$user_context = "";
if ($user_id) {
    try {
        // Get user details
        $uStmt = $pdo->prepare("SELECT name, email, phone, role, last_login FROM users WHERE id = ?");
        $uStmt->execute([$user_id]);
        $profile = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $user_context .= "Active User Profile:\n";
            $user_context .= "- User Name: " . $profile['name'] . "\n";
            $user_context .= "- User Email: " . $profile['email'] . "\n";
            $user_context .= "- User Phone: " . ($profile['phone'] ?? 'N/A') . "\n";
            $user_context .= "- Account Role: " . $profile['role'] . "\n";
            $user_context .= "- Last Login: " . $profile['last_login'] . "\n";
        }

        // Get user ongoing projects
        $pStmt = $pdo->prepare("SELECT title, status, budget, progress FROM ongoing_projects WHERE user_id = ?");
        $pStmt->execute([$user_id]);
        $projects = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($projects)) {
            $user_context .= "\nUser's Projects in Progress:\n";
            foreach ($projects as $proj) {
                $user_context .= "- " . $proj['title'] . " (Status: " . $proj['status'] . ", Progress: " . $proj['progress'] . "%, Budget: ₦" . number_format($proj['budget'], 2) . ")\n";
            }
        } else {
            $user_context .= "\nUser's Projects: None currently in development.\n";
        }

        // Get user project requests
        $rStmt = $pdo->prepare("SELECT id, title, description, categories, software_type, features, recommendations, status, budget, cancel_requested, suspend_requested, suspend_start_date, suspend_end_date, created_at FROM client_requests WHERE user_id = ?");
        $rStmt->execute([$user_id]);
        $requests = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($requests)) {
            $user_context .= "\nUser's Project Requests:\n";
            foreach ($requests as $req) {
                $cancelNote = intval($req['cancel_requested']) ? ' [Cancellation Pending]' : '';
                $suspendNote = '';
                if (intval($req['suspend_requested'])) {
                    $suspendNote = ' [Suspension Pending]';
                    if (!empty($req['suspend_start_date']) && !empty($req['suspend_end_date'])) {
                        $suspendNote .= ' (' . $req['suspend_start_date'] . ' to ' . $req['suspend_end_date'] . ')';
                    }
                }
                $editable = in_array($req['status'], ['pending', 'reviewed', 'rejected']) ? ' [Editable]' : '';
                $user_context .= "- [Request ID: " . $req['id'] . "] " . $req['title'] . "\n";
                $user_context .= "  Status: " . $req['status'] . $cancelNote . $suspendNote . $editable . "\n";
                $user_context .= "  Category: " . ($req['categories'] ?: 'N/A') . " | Type: " . ($req['software_type'] ?: 'N/A') . " | Budget: ₦" . number_format($req['budget'], 2) . "\n";
                $user_context .= "  Description: " . substr($req['description'], 0, 150) . (strlen($req['description']) > 150 ? '...' : '') . "\n";
                if (!empty($req['features'])) $user_context .= "  Features: " . substr($req['features'], 0, 100) . "\n";
                $user_context .= "  Submitted: " . $req['created_at'] . "\n";
            }
        } else {
            $user_context .= "\nUser's Project Requests: None submitted.\n";
        }
    } catch (Exception $e) {
        $user_context .= "Error retrieving user profile context.\n";
    }
} else {
    $user_context = "User is browsing as a Guest (not logged in).\n";
}

// === Process File Upload (If Any) ===
$attachmentPath = null;
$fileContentContext = "";
$isFileImage = false;

if ($fileData && isset($fileData['data']) && isset($fileData['name']) && isset($fileData['type'])) {
    $base64Data = $fileData['data'];
    $fileName = $fileData['name'];
    $fileType = $fileData['type'];

    // Decode base64 data
    $parts = explode(',', $base64Data);
    $rawData = base64_decode(end($parts));

    if ($rawData) {
        // Create uploads directory
        $uploadDir = __DIR__ . '/uploads/bot_files/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uniqueName = uniqid('bot_', true) . '.' . $fileExt;
        $targetFile = $uploadDir . $uniqueName;

        if (file_put_contents($targetFile, $rawData)) {
            $attachmentPath = 'uploads/bot_files/' . $uniqueName;
            
            if (strpos($fileType, 'image/') === 0) {
                $isFileImage = true;
                $userMessage .= "\n[Attached Image: " . $attachmentPath . "]";
            } else {
                $userMessage .= "\n[Attached File: " . $fileName . " | " . $attachmentPath . "]";
                
                // Parse text if PDF
                if ($fileExt === 'pdf') {
                    $extracted = extractTextFromPdf($targetFile);
                    if (!empty($extracted)) {
                        $fileContentContext = "\n--- EXTRACTED TEXT FROM PDF FILE ($fileName) ---\n" . $extracted . "\n--------------------------------------------\n";
                    }
                }
            }
        }
    }
}

// === Determine if the message is critical ===
$criticalKeywords = [
    'payment', 'billing', 'invoice', 'charge', 'pay', 
    'error', 'bug', 'crash', 'broken', 'failed', 'issue', 
    'hack', 'hacker', 'security', 'vulnerability', 'compromise',
    'urgent', 'critical', 'support', 'admin', 'help', 'immediate'
];
$is_critical = 0;
foreach ($criticalKeywords as $keyword) {
    if (stripos($userMessage, $keyword) !== false) {
        $is_critical = 1;
        break;
    }
}

// Save User Message to Database
try {
    // Check if session exists in bot_chat_sessions
    $sessStmt = $pdo->prepare("SELECT topic FROM bot_chat_sessions WHERE session_id = ?");
    $sessStmt->execute([$session_id]);
    if (!$sessStmt->fetch()) {
        $topic = mb_strimwidth($userMessage, 0, 40, '...');
        if (empty($topic) && $fileData) $topic = "File Upload";
        $pdo->prepare("INSERT INTO bot_chat_sessions (session_id, user_id, topic) VALUES (?, ?, ?)")
            ->execute([$session_id, $user_id, $topic]);
    }

    $insertStmt = $pdo->prepare("INSERT INTO bot_chats (session_id, user_id, role, message, is_critical) VALUES (?, ?, 'user', ?, ?)");
    $insertStmt->execute([$session_id, $user_id, $userMessage, $is_critical]);
} catch (Exception $e) {
    echo json_encode(['reply' => botError($pdo, $userMessage, "db_save_user_msg: " . $e->getMessage())]);
    exit;
}

// === Urgent Support Notification Logic ===
$urgentRequest = false;
$connectRequest = preg_match('/\b(connect\s*(me|with)|talk\s*(to|with)|speak\s*(to|with)|human|agent|person|real\s*person|transfer|escalate)\b/i', $userMessage);
$emergencyRequest = preg_match('/\b(emergency|urgent|immediate|asap|critical|help\s*now)\b/i', $userMessage);

if ($user_id && ($emergencyRequest || ($connectRequest && $is_critical) || $is_critical > 1)) {
    $urgentRequest = true;
    // Notify all admin users in-app
    try {
        $adminList = $pdo->query("SELECT id, phone, email FROM users WHERE role IN ('admin','developer')");
        $userName = $_SESSION['user']['name'] ?? 'A user';
        $notifTitle = "⚠️ Urgent: $userName needs support";
        $notifMsg = "User $userName said: " . substr($userMessage, 0, 200);
        while ($admin = $adminList->fetch(PDO::FETCH_ASSOC)) {
            add_notification($admin['id'], $notifTitle, $notifMsg, 'support', '../admin/dashboard.php');
            // Send SMS if emergency and admin has phone
            if ($emergencyRequest && !empty($admin['phone'])) {
                @send_termii_sms($admin['phone'], "URGENT: $userName needs help on WQS. \"$notifMsg\"", $pdo);
            }
        }
    } catch (Exception $e) {}
} elseif ($user_id && $connectRequest) {
    $urgentRequest = true;
    // User just wants to connect to a person - notify admins
    try {
        $adminList = $pdo->query("SELECT id, phone, email FROM users WHERE role IN ('admin','developer')");
        $userName = $_SESSION['user']['name'] ?? 'A user';
        $notifMsg = "User $userName is requesting to speak with a human agent.";
        while ($admin = $adminList->fetch(PDO::FETCH_ASSOC)) {
            add_notification($admin['id'], "🔔 Support Handoff Request", $notifMsg, 'support', '../admin/dashboard.php');
        }
    } catch (Exception $e) {}
}

// Fetch conversation context history (last 15 messages)
$history = [];
try {
    $histStmt = $pdo->prepare("
        SELECT role, message FROM (
            SELECT role, message, created_at FROM bot_chats 
            WHERE (session_id = ? OR (user_id IS NOT NULL AND user_id = ?)) AND is_deleted_by_user = 0
            ORDER BY created_at DESC LIMIT 15
        ) tmp ORDER BY created_at ASC
    ");
    $histStmt->execute([$session_id, $user_id]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $history = [['role' => 'user', 'message' => $userMessage]];
}

// Build system prompt incorporating Platform Knowledge and User Profile Context
$systemPrompt = "You are WiseBot, a premium AI Assistant for the software development company Wise Quotient Soft (WQS).\n";
$systemPrompt .= "You have access to the platform knowledge, services catalog, pricing tiers, and the current user's details.\n";
$systemPrompt .= "Answer support queries professionally, referencing their profile and company info.\n\n";
$systemPrompt .= "--- COMPANY INFO & SERVICES ---\n" . $company_kb . "\n";
$systemPrompt .= "--- CURRENT ACTIVE USER CONTEXT ---\n" . $user_context . "\n";

// Append user preferences from survey for smarter responses
$userPrefsNote = '';
if ($user_id) {
    try {
        $prefStmt = $pdo->prepare("SELECT preferences_json FROM user_preferences WHERE user_id = ? AND survey_completed = 1");
        $prefStmt->execute([$user_id]);
        $prefRow = $prefStmt->fetch(PDO::FETCH_ASSOC);
        if ($prefRow && $prefRow['preferences_json']) {
            $prefs = json_decode($prefRow['preferences_json'], true);
            if (!empty($prefs)) {
                $userPrefsNote = "--- USER SURVEY PREFERENCES ---\n";
                foreach ($prefs as $key => $val) {
                    $label = str_replace('_', ' ', $key);
                    $userPrefsNote .= "- {$label}: {$val}\n";
                }
                $userPrefsNote .= "\nCRITICAL: Based on the above preferences, you MUST automatically create ONE hyper-targeted ad for the most relevant WQS service. ";
                $userPrefsNote .= "Match their industry/role to the closest service (e.g., Fintech→payment solutions, E-commerce→web stores, Education→LMS, Healthcare→patient portals, Startup→MVP development). ";
                $userPrefsNote .= "Output [CREATE_AD: 1-2 sentence description of the service tailored to their specific industry, role, budget, and timeline] ONCE as part of your greeting response. ";
                $userPrefsNote .= "Do NOT ask permission — just create it. ";
                $userPrefsNote .= "If they already have a matching ad, you can skip this. Only create ONE ad per conversation.\n";
            }
        }
    } catch (Exception $e) {}
}
$systemPrompt .= $userPrefsNote;
$systemPrompt .= "Instructions:\n";
$systemPrompt .= "1. If the user is logged in, address them by name.\n";
$systemPrompt .= "2. If they ask about their project requests or ongoing projects, reference their details.\n";
$systemPrompt .= "3. If a guest asks about creating a project, explain services and pricing, direct them to log in.\n";
$systemPrompt .= "4. Keep answers professional, precise, and polite.\n\n";
$systemPrompt .= "5. **SAMPLE COLLECTION** — When a user describes a project, always ask if they have sample images, wireframes, mockups, or reference links (Figma, Dribbble, GitHub, etc.).\n";
$systemPrompt .= "   - If they say they have images or want to share samples, include the marker [HIGHLIGHT_UPLOAD] at the end of your response to highlight the file upload button, and tell them: 'Great! Click the highlighted 📎 paperclip button below to attach your images.'\n";
$systemPrompt .= "   - If they say they will share a link (no images), include the marker [FOCUS_INPUT] at the end and tell them: 'Perfect! Please paste your reference link in the chat input above.'\n";
$systemPrompt .= "   - If they explicitly say 'I have images' or 'let me upload', include the marker [TRIGGER_FILE_UPLOAD] at the end of your response to automatically open the file picker.\n";
$systemPrompt .= "   - Use any shared samples (images or links) to better understand the user's design preference and project scope.\n\n";
$systemPrompt .= "6. **DISCUSSION SYSTEM** — Each project request has a discussion thread where admins and the bot can talk about the project.\n";
$systemPrompt .= "   - If an admin asks you about a specific project request (by ID or title), you can fetch the discussion log by replying with [FETCH_DISCUSSION: request_id] on a line by itself. The system will replace that tag with the recent discussion messages.\n";
$systemPrompt .= "   - To add a note to a project's discussion, reply with [ADD_DISCUSSION_NOTE: request_id | your note text]. The system will save the note.\n";
$systemPrompt .= "   - Use the discussion history to provide context-aware answers about project requirements, scope, and decisions.\n\n";
$systemPrompt .= "7. **PROJECT REQUEST CREATION (REVIEW → CONFIRM → SAVE)** — You can create project requests for logged-in users. Follow these steps IN ORDER:\n";
$systemPrompt .= "   - Step A (COLLECT): Ask for any missing details: project title, description, category (School/Company/Startup/E-commerce/Healthcare/Fintech/Other), software type (Web/Mobile/Desktop), features, budget, timeline, company name, contact person, phone. Collect all info conversationally.\n";
$systemPrompt .= "   - Step B (SAMPLES): Always ask: 'Do you have any sample images, wireframes, or a reference link to help me better understand your vision?'\n";
$systemPrompt .= "   - Step C (PREVIEW): When all details are gathered, present a clean **preview** to the user with all the project details listed clearly, then ask: 'Please review the details above. Shall I go ahead and save this project request for you?'\n";
$systemPrompt .= "   - Step D (CONFIRM): Only if the user explicitly says yes (e.g., 'yes', 'save', 'create', 'go ahead'), respond with EXACTLY this JSON on its own line at the end of your message (no markdown, no code block):\n";
$systemPrompt .= "     ```\n";
$systemPrompt .= "     [CREATE_REQUEST: {\"title\":\"...\",\"description\":\"...\",\"category\":\"...\",\"software_type\":\"...\",\"features\":\"...\",\"budget\":\"...\",\"timeline\":\"...\",\"company_name\":\"...\",\"contact_person\":\"...\",\"phone\":\"...\"}]\n";
$systemPrompt .= "     ```\n";
$systemPrompt .= "   - If the user says no or requests changes, update the details and repeat Step C.\n";
$systemPrompt .= "   - DO NOT create a request unless the user explicitly confirms.\n";
$systemPrompt .= "   - The system will automatically save the request when it sees the tag and confirm to you.\n\n";
$systemPrompt .= "8. **PERSONALIZED RECOMMENDATIONS** — Use the user's survey preferences (industry, role, software type, budget, timeline) to recommend relevant WQS services.\n";
$systemPrompt .= "   - Fintech users → recommend payment integration, security, compliance services\n";
$systemPrompt .= "   - E-commerce users → recommend scalable storefronts, inventory management, checkout UX\n";
$systemPrompt .= "   - Education users → recommend LMS, student portals, virtual classrooms\n";
$systemPrompt .= "   - Healthcare users → recommend HIPAA-compliant portals, telemedicine, patient management\n";
$systemPrompt .= "   - Startup Founders → recommend MVP development, rapid prototyping, lean methodology\n";
$systemPrompt .= "   - Business Owners → recommend automation, CRM, business intelligence\n";
$systemPrompt .= "   - Agency Owners → recommend white-label solutions, API integrations, scalability\n";
$systemPrompt .= "   - Low/Medium budget → emphasize affordable packages, payment plans, cost-effective solutions\n";
$systemPrompt .= "   - High budget → emphasize premium features, dedicated team, white-glove service\n";
$systemPrompt .= "   - ASAP timeline → create urgency ('Launch in weeks, not months')\n";
$systemPrompt .= "   - Relaxed timeline → emphasize thorough planning, custom solutions, quality\n";
$systemPrompt .= "   - Always explain WHY a service is relevant to their specific profile.\n";
$systemPrompt .= "   - CRITICAL: If a user asks about services, pricing, or solutions, check their profile and recommend the most relevant service.\n\n";
$systemPrompt .= "9. **PARTNERSHIP APPLICATION (REVIEW → CONFIRM → SUBMIT)** — You can submit a partnership (referral agent) application for logged-in users.\n";
$systemPrompt .= "   - If a user says 'become a partner', 'join as partner', 'referral program', or similar, explain the benefits (commission on referrals, printable agreement, referral portal access).\n";
$systemPrompt .= "   - Collect confirmation: ask 'Would you like me to submit your partnership application? It takes just a moment.'\n";
$systemPrompt .= "   - Only when they explicitly confirm, add at the end of your response: [PARTNER_REQUEST]\n";
$systemPrompt .= "   - The system will submit the application and notify the admin team.\n";
$systemPrompt .= "   - If they're already a partner (role=agent), tell them their status and direct them to the referral portal.\n";
$systemPrompt .= "   - **Cancel application**: If a user says 'cancel my partnership', 'withdraw application', or similar, ask for confirmation. On explicit yes, add [CANCEL_PARTNER] at the end.\n";
$systemPrompt .= "   - **Re-apply**: If their application was rejected and they want to try again, you can re-submit using [PARTNER_REQUEST] which resets their status to pending.\n\n";
$systemPrompt .= "10. **DEVELOPER APPLICATION (REVIEW → CONFIRM → SUBMIT)** — You can submit a developer application for logged-in users.\n";
$systemPrompt .= "    - If a user says 'become a developer', 'join dev team', 'apply as developer', or similar, explain the benefits (developer hub, task boards, project assignments).\n";
$systemPrompt .= "    - Collect these details conversationally: skills (comma-separated), years of experience, portfolio URL (optional), GitHub URL (optional), expected hourly rate, and brief experience summary.\n";
$systemPrompt .= "    - Step A (COLLECT): Ask for any missing details from the list above. Ask about their experience: 'What kind of projects have you worked on?'\n";
$systemPrompt .= "    - Step B (PREVIEW): Present a clean preview with all details and ask: 'Please review. Shall I submit your developer application?'\n";
$systemPrompt .= "    - Step C (CONFIRM): Only on explicit yes, add EXACTLY this JSON at the end (no markdown, no code block):\n";
$systemPrompt .= "      [DEVELOPER_REQUEST: {\"skills\":\"...\",\"experience\":\"...\",\"portfolio_url\":\"...\",\"github_url\":\"...\",\"years_experience\":...|\"hourly_rate\":...}]\n";
$systemPrompt .= "    - DO NOT submit unless the user explicitly confirms.\n";
$systemPrompt .= "    - If they're already a developer, tell them and suggest visiting the Developer Hub.\n";
$systemPrompt .= "    - **Cancel application**: If a user says 'cancel my developer application', 'withdraw dev application', or similar, ask for confirmation. On explicit yes, add [CANCEL_DEVELOPER] at the end.\n";
$systemPrompt .= "    - **Update application**: If a user wants to update their skills, experience, or any details in an existing application, collect the new info conversationally. Present a preview comparing old vs new. On confirmation, output [UPDATE_DEVELOPER: {...}] with the same JSON fields as DEVELOPER_REQUEST (only include changed fields). The system will update the application and reset status to pending.\n\n";
$systemPrompt .= "11. **PROFILE UPDATE (REVIEW → CONFIRM → SAVE)** — You can update a logged-in user's profile information.\n";
$systemPrompt .= "    - If a user says 'update my profile', 'change my name', 'update my info', or asks to modify any personal details, help them.\n";
$systemPrompt .= "    - Collect the fields they want to change conversationally: name, phone, bio, company, profession, skills, tech stack, previous experience, education, LinkedIn URL, Twitter URL, GitHub URL, website URL.\n";
$systemPrompt .= "    - Step A (COLLECT): Ask which fields they'd like to update. Get the new values.\n";
$systemPrompt .= "    - Step B (PREVIEW): Show a preview of what will change and ask: 'Shall I go ahead and update your profile with these changes?'\n";
$systemPrompt .= "    - Step C (CONFIRM): Only on explicit yes, add EXACTLY this JSON at the end (no markdown, no code block):\n";
$systemPrompt .= "      [UPDATE_PROFILE: {\"name\":\"...\",\"phone\":\"...\",\"bio\":\"...\",\"company\":\"...\",\"profession\":\"...\",\"skills\":\"...\",\"tech_stack\":\"...\",\"previous_experience\":\"...\",\"education\":\"...\",\"linkedin_url\":\"...\",\"twitter_url\":\"...\",\"github_url\":\"...\",\"website_url\":\"...\"}]\n";
$systemPrompt .= "    - Include ONLY the fields the user wants to change; omit fields they don't mention.\n";
$systemPrompt .= "    - DO NOT update unless the user explicitly confirms.\n\n";
$systemPrompt .= "12. **THEME TOGGLE** — You can switch the user interface between light and dark mode for logged-in users.\n";
$systemPrompt .= "    - If a user says 'switch to dark mode', 'dark theme', 'light mode', 'change theme', or similar, just confirm and output [TOGGLE_THEME: dark] or [TOGGLE_THEME: light] at the end.\n";
$systemPrompt .= "    - No preview or complex confirmation needed — just do it and confirm the change.\n";
$systemPrompt .= "    - Example: User says 'dark mode please' → respond with '🌙 Switching to dark mode!' and add [TOGGLE_THEME: dark]\n\n";
$systemPrompt .= "13. **PROFILE PICTURE UPDATE** — You can update a logged-in user's profile picture when they upload an image via the paperclip 📎 button.\n";
$systemPrompt .= "    - When a user says 'set this as my profile picture', 'update my avatar', 'make this my profile photo', 'change my picture', or similar, after they have shared an image, reply with confirmation and add [SET_AVATAR] at the end.\n";
$systemPrompt .= "    - Example: User uploads a photo and says 'make this my profile picture' → respond with '🎉 Great choice! Setting this as your profile picture...' and add [SET_AVATAR]\n";
$systemPrompt .= "    - Only use [SET_AVATAR] when the user explicitly asks to update their profile picture AND has shared an image in the same message.\n";
$systemPrompt .= "    - If the user asks to update their picture but hasn't uploaded one yet, tell them: 'Please click the 📎 paperclip button below to upload your new profile photo!' and include [TRIGGER_FILE_UPLOAD].\n\n";
$systemPrompt .= "14. **HUMAN HANDOFF & URGENT SUPPORT** — You can connect users with a real support agent:\n";
$systemPrompt .= "    - If a user says 'talk to human', 'connect me with a person', 'speak to agent', or similar, tell them: 'I'll notify our support team right away!' Then include the marker [TRIGGER_HANDOFF] at the end of your response.\n";
$systemPrompt .= "    - If a user mentions something urgent, critical, or says 'emergency', 'urgent', 'I need help now', respond with empathy and include [TRIGGER_HANDOFF] to escalate immediately.\n";
$systemPrompt .= "    - For critical issues like payment failure, security breach, or system downtime, include [TRIGGER_HANDOFF] and say: 'I've flagged this as critical. Our team has been alerted.'\n";
$systemPrompt .= "    - Always be empathetic and reassure the user when escalating. The system will create a ticket and notify support staff automatically.\n\n";
$systemPrompt .= "15. **PROJECT CANCELLATION REQUEST (FOR APPROVED PROJECTS)** — You can submit cancellation requests for approved projects.\n";
$systemPrompt .= "    - When a user says 'cancel my project', 'I want to cancel', 'cancel my approved project', 'stop my project', or similar, check their project list in the User Context above.\n";
$systemPrompt .= "    - If the project status is 'completed', tell them: 'This project has already been completed and cannot be cancelled.'\n";
$systemPrompt .= "    - If the project status is NOT 'approved' and NOT 'completed', tell them they can cancel it directly from the My Proposals page.\n";
$systemPrompt .= "    - If the project status IS 'approved', explain: 'Since this project has been approved, it cannot be deleted directly. I'll need to submit a cancellation request to the admin for approval.'\n";
$systemPrompt .= "    - Ask them to provide a reason for the cancellation: 'Could you please tell me why you want to cancel this project? This reason will be shared with the admin.'\n";
$systemPrompt .= "    - Once they provide a reason, present a confirmation: 'I'll submit a cancellation request for [project title] with the reason: [reason]. The admin will review and you'll be notified. Shall I proceed?'\n";
$systemPrompt .= "    - Only when they explicitly confirm (yes, proceed, go ahead, do it), add this marker at the end of your response:\n";
$systemPrompt .= "      [REQUEST_CANCEL: {\"request_id\": <the_request_id_number>, \"reason\": \"<the_user_reason>\"}]\n";
$systemPrompt .= "    - If the user says no or wants to change the reason, update and repeat confirmation.\n";
$systemPrompt .= "    - DO NOT submit unless the user explicitly confirms.\n";
$systemPrompt .= "    - If a user has multiple approved projects, ask which one they want to cancel by listing them with their IDs.\n";
$systemPrompt .= "    - If a cancellation is already pending (cancel_requested=1), tell them: 'A cancellation request for this project is already pending admin approval. You'll be notified once a decision is made.'\n";
$systemPrompt .= "    - The system will save the cancellation request and notify the admin team automatically.\n\n";
$systemPrompt .= "16. **EDIT PROJECT REQUEST (REVIEW → CONFIRM → SAVE)** — You can edit project requests for logged-in users.\n";
$systemPrompt .= "    - When a user says 'edit my project', 'update my project request', 'change my project details', 'modify my request', or similar, check their project list in the User Context above.\n";
$systemPrompt .= "    - Only projects with status 'pending', 'reviewed', or 'rejected' can be edited (marked [Editable] in the context). Approved and completed projects cannot be edited.\n";
$systemPrompt .= "    - If the project is approved or completed, tell them: 'This project has been approved/completed and its details cannot be edited. If you need changes, please contact support or request a cancellation.'\n";
$systemPrompt .= "    - If a user has multiple editable projects, ask which one they want to edit by listing them with their IDs and titles.\n";
$systemPrompt .= "    - Collect the fields they want to change conversationally: title, description, category, software type, features, recommendations.\n";
$systemPrompt .= "    - Step A (COLLECT): Show current values and ask what they'd like to change. Get the new values.\n";
$systemPrompt .= "    - Step B (PREVIEW): Show a preview comparing old vs new values and ask: 'Shall I save these changes?'\n";
$systemPrompt .= "    - Step C (CONFIRM): Only on explicit yes, add EXACTLY this JSON at the end (no markdown, no code block):\n";
$systemPrompt .= "      [UPDATE_REQUEST: {\"request_id\": <id>, \"title\":\"...\", \"description\":\"...\", \"category\":\"...\", \"software_type\":\"...\", \"features\":\"...\", \"recommendations\":\"...\"}]\n";
$systemPrompt .= "    - Include ONLY the fields the user wants to change; omit fields they don't mention.\n";
$systemPrompt .= "    - DO NOT save unless the user explicitly confirms.\n";
$systemPrompt .= "    - The system will update the request and confirm the changes.\n\n";
$systemPrompt .= "17. **CHECK PROJECT PROGRESS** — You can check and report project progress for logged-in users.\n";
$systemPrompt .= "    - When a user says 'check my project progress', 'how is my project going', 'project status', 'what's the progress on my project', or similar, reference their projects in the User Context above.\n";
$systemPrompt .= "    - For ongoing projects, report: title, status, progress percentage, budget, start/end dates, and team info.\n";
$systemPrompt .= "    - For completed projects, congratulate them and mention the delivery links (Live Preview, Download, Documentation) if available.\n";
$systemPrompt .= "    - If they ask about a specific project, match by title or ID from their project list.\n";
$systemPrompt .= "    - Format the response nicely with progress visualization (e.g., progress bar using text: [████░░░░░] 45%).\n";
$systemPrompt .= "    - If no projects exist, tell them: 'You don't have any ongoing projects yet. Would you like to create a project request?'\n";
$systemPrompt .= "    - You can also proactively mention project updates when relevant (e.g., if progress is high, suggest reviewing delivery).\n\n";
$systemPrompt .= "18. **PROJECT SUSPENSION REQUEST (FOR APPROVED PROJECTS)** — You can submit suspension requests for approved projects.\n";
$systemPrompt .= "    - When a user says 'suspend my project', 'pause my project', 'I want to pause', 'put my project on hold', 'take a break from my project', or similar, check their project list in the User Context above.\n";
$systemPrompt .= "    - If the project status is 'completed', tell them: 'This project has already been completed and cannot be suspended.'\n";
$systemPrompt .= "    - If the project status is NOT 'approved' and NOT 'completed', tell them they can only suspend approved projects.\n";
$systemPrompt .= "    - If the project status IS 'approved', explain: 'Since this project has been approved, I can submit a suspension request to the admin for approval. The project will be paused during the specified period.'\n";
$systemPrompt .= "    - Ask them to provide: (1) a reason for the suspension, (2) the start date for suspension, and (3) the expected resume date.\n";
$systemPrompt .= "    - Collect the dates conversationally: 'When would you like to pause the project? And when do you expect to resume?'\n";
$systemPrompt .= "    - Once they provide all details, present a confirmation: 'I'll submit a suspension request for [project title] from [start date] to [end date] with the reason: [reason]. The admin will review and you'll be notified. Shall I proceed?'\n";
$systemPrompt .= "    - Only when they explicitly confirm (yes, proceed, go ahead, do it), add this marker at the end of your response:\n";
$systemPrompt .= "      [REQUEST_SUSPEND: {\"request_id\": <the_request_id_number>, \"reason\": \"<the_user_reason>\", \"start_date\": \"<YYYY-MM-DD>\", \"end_date\": \"<YYYY-MM-DD>\"}]\n";
$systemPrompt .= "    - If the user says no or wants to change the details, update and repeat confirmation.\n";
$systemPrompt .= "    - DO NOT submit unless the user explicitly confirms.\n";
$systemPrompt .= "    - If a user has multiple approved projects, ask which one they want to suspend by listing them with their IDs.\n";
$systemPrompt .= "    - If a suspension is already pending (suspend_requested=1), tell them: 'A suspension request for this project is already pending admin approval. You'll be notified once a decision is made.'\n";
$systemPrompt .= "    - The system will save the suspension request and notify the admin team automatically.\n\n";

$systemPrompt .= "19. **GUEST LEAD CAPTURE (CONTACT FORM)** — If a guest (not logged in) asks to start a project, get a quote, or contact the team, collect their details.\n";
$systemPrompt .= "    - Step A (COLLECT): Ask for their Name, Email, Phone number (optional), Service needed (e.g. Web Development), Budget, Timeline, and a brief Message/Project Details.\n";
$systemPrompt .= "    - Step B (PREVIEW): Show them a clean summary of their contact details.\n";
$systemPrompt .= "    - Step C (CONFIRM): Ask if they want you to submit this to the team. If they say yes, add EXACTLY this JSON at the end:\n";
$systemPrompt .= "      [SUBMIT_CONTACT: {\"name\":\"...\", \"email\":\"...\", \"phone\":\"...\", \"service\":\"...\", \"budget\":\"...\", \"timeline\":\"...\", \"message\":\"...\", \"company\":\"...\"}]\n";
$systemPrompt .= "    - The system will submit their message to the Contact form and return a reference number.\n\n";

// Build messages array for OpenRouter
$messages = [];
$messages[] = [
    'role' => 'system',
    'content' => $systemPrompt
];

// Append history
foreach ($history as $idx => $msg) {
    $role = $msg['role'];
    if ($role === 'bot') {
        $role = 'assistant';
    }
    
    // Check if this is the user's latest message and contains a multimodal image
    if ($idx === count($history) - 1 && $role === 'user' && $isFileImage && $attachmentPath) {
        $messages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $msg['message']
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $base64Data
                    ]
                ]
            ]
        ];
    } else {
        // Standard message (including PDF context if latest)
        $content = $msg['message'];
        if ($idx === count($history) - 1 && $role === 'user' && !empty($fileContentContext)) {
            $content .= $fileContentContext;
        }
        $messages[] = [
            'role' => $role,
            'content' => $content
        ];
    }
}

// === Helper: send messages to OpenRouter with retry ===
function sendToOpenRouter($apiKey, $messages, &$errorOut) {
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    $data = [
        'model' => 'openai/gpt-4o-mini',
        'messages' => $messages
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || !$response) {
        $errorOut = $error ?: 'Empty response';
        return null;
    }
    return json_decode($response, true);
}

// Try sending multimodal; fall back to text-only if model rejects images
$decoded = sendToOpenRouter($apiKey, $messages, $curlError);

if (!$decoded && $curlError) {
    botError($pdo, $userMessage, "OpenRouter connection failed: " . $curlError);
    echo json_encode(['reply' => "🤖 I'm a little bit busy right now. Please try again in a moment."]);
    exit;
}

if (isset($decoded['error'])) {
    $errMsg = strtolower($decoded['error']['message'] ?? '');
    // If the error is about image support, fall back to text-only
    if ($isFileImage && (strpos($errMsg, 'image') !== false || strpos($errMsg, 'vision') !== false || strpos($errMsg, 'multimodal') !== false)) {
        botError($pdo, $userMessage, "Model rejected image, falling back to text: " . ($decoded['error']['message'] ?? ''));
        // Rebuild messages without image content
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        foreach ($history as $idx => $msg) {
            $role = $msg['role'] === 'bot' ? 'assistant' : $msg['role'];
            $content = $msg['message'];
            if ($idx === count($history) - 1 && $role === 'user' && !empty($fileContentContext)) {
                $content .= $fileContentContext;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $decoded = sendToOpenRouter($apiKey, $messages, $curlError);
        if (!$decoded) {
            botError($pdo, $userMessage, "Fallback OpenRouter call failed: " . $curlError);
            echo json_encode(['reply' => "🤖 I'm a little bit busy right now. Please try again in a moment."]);
            exit;
        }
        // Update reply to acknowledge the image limitation
        $reply = $decoded['choices'][0]['message']['content'] ?? null;
        if ($reply) {
            $reply = "📎 I can see you shared an image, but I'm not able to view it directly. Could you describe what's in the image or let me know how you'd like me to help?\n\n" . $reply;
        }
    } else {
        $errMessage = $decoded['error']['message'] ?? json_encode($decoded['error']);
        botError($pdo, $userMessage, "OpenRouter API error: " . $errMessage);
        echo json_encode(['reply' => "🤖 I'm a little bit busy right now. Please try again in a moment."]);
        exit;
    }
} else {
    $reply = $decoded['choices'][0]['message']['content'] ?? null;
}

if (!$reply) {
    botError($pdo, $userMessage, "OpenRouter empty response");
    echo json_encode(['reply' => "🤖 I'm a little bit busy right now. Please try again in a moment."]);
    exit;
}

// === Helper: create a project request from parsed data ===
function createProjectFromData($pdo, $user_id, $data, &$reply, $tag = null) {
    $reqTitle = trim($data['title'] ?? '');
    $reqDesc = trim($data['description'] ?? '');
    if (empty($reqTitle) || empty($reqDesc)) return false;
    $reqCategory = trim($data['category'] ?? 'Other');
    $reqSoftwareType = trim($data['software_type'] ?? 'Web App');
    $reqFeatures = trim($data['features'] ?? '');
    $reqBudget = trim($data['budget'] ?? '');
    $reqCompany = trim($data['company_name'] ?? '');
    $reqContact = trim($data['contact_person'] ?? '');
    $reqPhone = trim($data['phone'] ?? '');
    $reqTimeline = trim($data['timeline'] ?? '');

    $compiledDesc = "PROJECT DETAILS & DESCRIPTION:\n" . $reqDesc . "\n\n";
    if ($reqCompany || $reqContact) {
        $compiledDesc .= "--- CLIENT INFORMATION ---\n" .
            "Company/Client Name: " . $reqCompany . "\n" .
            "Contact Person: " . $reqContact . "\n" .
            "Phone Number: " . $reqPhone . "\n\n";
    }
    $compiledDesc .= "--- TIMELINE & BUDGET ---\n" .
        "Timeline: " . $reqTimeline . "\n" .
        "Budget: " . $reqBudget . "\n\n";
    $compiledDesc .= "--- FEATURES ---\n" . $reqFeatures;

    try {
        $stmt = $pdo->prepare("INSERT INTO client_requests (user_id, title, description, categories, software_type, features, recommendations) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$user_id, $reqTitle, $compiledDesc, $reqCategory, $reqSoftwareType, $reqFeatures, '']);
        $requestId = $pdo->lastInsertId();
        if ($tag) $reply = str_replace($tag, '', $reply);

        // Notify the client
        add_notification($user_id, "Project Request Created by WiseBot", "Your project request '{$reqTitle}' has been created by WiseBot and is pending review.", 'project', '../user/my_requests.php', $requestId);

        // Notify ALL admin users
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
        $clientName = '';
        $clientStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $clientStmt->execute([$user_id]);
        $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
        if ($clientRow) $clientName = $clientRow['name'];
        foreach ($admins as $admin) {
            add_notification($admin['id'], "New Project Request: {$reqTitle}",
                "{$clientName} submitted a new project request '{$reqTitle}' via WiseBot. Review it in the admin panel.", 'project', '../admin/client_requests.php', $requestId);
        }

        // Save initial discussion entry from bot
        try {
            $discStmt = $pdo->prepare("INSERT INTO request_discussions (request_id, user_id, message, is_from_bot) VALUES (?, ?, ?, 1)");
            $discStmt->execute([$requestId, $user_id, "Project request created by WiseBot. Title: {$reqTitle}\nDescription: {$reqDesc}\nCategory: {$reqCategory}\nType: {$reqSoftwareType}\nBudget: {$reqBudget}\nTimeline: {$reqTimeline}"]);
        } catch (Exception $e) { /* fail-safe */ }

        $reply .= "\n\n✅ **Project request created successfully!** Your request ID is `#$requestId`. I've notified the team. They'll review and get back to you. Anything else?";
        return true;
    } catch (Exception $e) {
        if ($tag) $reply = str_replace($tag, '', $reply);
        @file_put_contents(__DIR__ . '/bot_errors.log', "[" . date('Y-m-d H:i:s') . "] create_project_via_marker: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        $reply .= "\n\n🤖 I'm a little bit busy right now. Please try again in a moment.";
        return false;
    }
}

// === Auto-create project request from bot reply ===
$requestCreated = false;
// Search for CREATE_REQUEST marker anywhere in the reply (not just at end)
if ($user_id && preg_match('/\[CREATE_REQUEST:\s*(\{.+?\})\]/s', $reply, $match)) {
    $reqData = json_decode($match[1], true);
    if ($reqData) {
        $requestCreated = createProjectFromData($pdo, $user_id, $reqData, $reply, $match[0]);
    }
}

// If not found in current reply, scan chat history for an unprocessed marker
if ($user_id && !$requestCreated) {
    try {
        $histCheck = $pdo->prepare("SELECT id, message FROM bot_chats WHERE (session_id = ? OR user_id = ?) AND role = 'assistant' AND is_critical = 0 ORDER BY created_at DESC LIMIT 10");
        $histCheck->execute([$session_id, $user_id]);
        $recentBots = $histCheck->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recentBots as $hb) {
            $updated = false;
            $origMsg = $hb['message'];
            $newMsg = $origMsg;

            // Check for CREATE_REQUEST
            if (preg_match('/\[CREATE_REQUEST:\s*(\{.+?\})\]/s', $newMsg, $hbMatch)) {
                $hbData = json_decode($hbMatch[1], true);
                if ($hbData && !empty($hbData['title']) && !empty($hbData['description'])) {
                    $newMsg = str_replace($hbMatch[0], '', $newMsg);
                    $requestCreated = createProjectFromData($pdo, $user_id, $hbData, $reply);
                    if ($requestCreated) { $updated = true; break; }
                }
            }

            // Check for PARTNER_REQUEST
            if (!$updated && strpos($newMsg, '[PARTNER_REQUEST]') !== false) {
                $newMsg = str_replace('[PARTNER_REQUEST]', '', $newMsg);
                try {
                    $chk = $pdo->query("SELECT id FROM agent_requests WHERE user_id = $user_id")->fetch();
                    if ($chk) {
                        $pdo->query("UPDATE agent_requests SET status = 'pending', updated_at = NOW() WHERE user_id = $user_id");
                    } else {
                        $pdo->exec("INSERT INTO agent_requests (user_id, status, created_at, updated_at) VALUES ($user_id, 'pending', NOW(), NOW())");
                    }
                    $updated = true;
                } catch (Exception $e) {}
            }

            // Check for DEVELOPER_REQUEST
            if (!$updated && preg_match('/\[DEVELOPER_REQUEST:\s*(\{.+?\})\]/s', $newMsg, $hbDr)) {
                $hbDrData = json_decode($hbDr[1], true);
                if ($hbDrData && !empty($hbDrData['skills'])) {
                    $newMsg = str_replace($hbDr[0], '', $newMsg);
                    try {
                        $drSkills = json_encode(array_map('trim', explode(',', $hbDrData['skills'])));
                        $chkDev = $pdo->query("SELECT id FROM developer_requests WHERE user_id = $user_id LIMIT 1")->fetch();
                        if ($chkDev) {
                            $pdo->prepare("UPDATE developer_requests SET skills=?, portfolio_url=?, github_url=?, experience=?, years_experience=?, hourly_rate_expected=?, status='pending', updated_at=NOW() WHERE user_id=?")
                                ->execute([$drSkills, $hbDrData['portfolio_url']??'', $hbDrData['github_url']??'', $hbDrData['experience']??'', (int)($hbDrData['years_experience']??0), (float)($hbDrData['hourly_rate']??0), $user_id]);
                        } else {
                            $pdo->prepare("INSERT INTO developer_requests (user_id, skills, portfolio_url, github_url, experience, years_experience, hourly_rate_expected, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,'pending',NOW(),NOW())")
                                ->execute([$user_id, $drSkills, $hbDrData['portfolio_url']??'', $hbDrData['github_url']??'', $hbDrData['experience']??'', (int)($hbDrData['years_experience']??0), (float)($hbDrData['hourly_rate']??0)]);
                        }
                        $updated = true;
                    } catch (Exception $e) {}
                }
            }

            // Check for UPDATE_PROFILE
            if (!$updated && preg_match('/\[UPDATE_PROFILE:\s*(\{.+?\})\]/s', $newMsg, $hbUp)) {
                $hbUpData = json_decode($hbUp[1], true);
                if ($hbUpData) {
                    $newMsg = str_replace($hbUp[0], '', $newMsg);
                    $fields = []; $params = [];
                    $allowedFields = ['name','phone','bio','company','profession','skills','tech_stack','previous_experience','education','linkedin_url','twitter_url','github_url','website_url'];
                    foreach ($allowedFields as $f) {
                        if (isset($hbUpData[$f])) { $fields[] = "$f = ?"; $params[] = trim($hbUpData[$f]); }
                    }
                    if (!empty($fields)) {
                        $params[] = $user_id;
                        try {
                            $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
                            $updated = true;
                        } catch (Exception $e) {}
                    }
                }
            }

            // Check for CANCEL_PARTNER
            if (!$updated && strpos($newMsg, '[CANCEL_PARTNER]') !== false) {
                $newMsg = str_replace('[CANCEL_PARTNER]', '', $newMsg);
                try {
                    $pdo->prepare("DELETE FROM agent_requests WHERE user_id = ?")->execute([$user_id]);
                    $updated = true;
                } catch (Exception $e) {}
            }

            // Check for TOGGLE_THEME
            if (!$updated && preg_match('/\[TOGGLE_THEME:\s*(dark|light)\]/i', $newMsg, $hbTt)) {
                $newMsg = str_replace($hbTt[0], '', $newMsg);
                try {
                    $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([strtolower($hbTt[1]), $user_id]);
                    $updated = true;
                } catch (Exception $e) {}
            }

            // Check for SET_AVATAR — skip in history (temp file no longer exists)
            if (!$updated && strpos($newMsg, '[SET_AVATAR]') !== false) {
                $newMsg = str_replace('[SET_AVATAR]', '', $newMsg);
                $updated = true;
            }

            // Check for CANCEL_DEVELOPER
            if (!$updated && strpos($newMsg, '[CANCEL_DEVELOPER]') !== false) {
                $newMsg = str_replace('[CANCEL_DEVELOPER]', '', $newMsg);
                try {
                    $pdo->prepare("DELETE FROM developer_requests WHERE user_id = ?")->execute([$user_id]);
                    $updated = true;
                } catch (Exception $e) {}
            }

            // Check for UPDATE_DEVELOPER
            if (!$updated && preg_match('/\[UPDATE_DEVELOPER:\s*(\{.+?\})\]/s', $newMsg, $hbUd)) {
                $hbUdData = json_decode($hbUd[1], true);
                if ($hbUdData) {
                    $newMsg = str_replace($hbUd[0], '', $newMsg);
                    $fields = []; $params = [];
                    $allowedUd = ['skills' => 'skills', 'portfolio_url' => 'portfolio_url', 'github_url' => 'github_url', 'experience' => 'experience', 'years_experience' => 'years_experience', 'hourly_rate' => 'hourly_rate_expected'];
                    foreach ($allowedUd as $key => $col) {
                        if (isset($hbUdData[$key])) {
                            if ($key === 'skills') $val = json_encode(array_map('trim', explode(',', $hbUdData[$key])));
                            elseif ($key === 'years_experience') $val = (int)$hbUdData[$key];
                            elseif ($key === 'hourly_rate') $val = (float)$hbUdData[$key];
                            else $val = trim($hbUdData[$key]);
                            $fields[] = "$col = ?"; $params[] = $val;
                        }
                    }
                    if (!empty($fields)) {
                        $fields[] = "status = 'pending'";
                        $fields[] = "updated_at = NOW()";
                        $params[] = $user_id;
                        try {
                            $pdo->prepare("UPDATE developer_requests SET " . implode(', ', $fields) . " WHERE user_id = ?")->execute($params);
                            $updated = true;
                        } catch (Exception $e) {}
                    }
                }
            }

            // Save updated message if changed
            if ($updated && $newMsg !== $origMsg) {
                $cleanStmt = $pdo->prepare("UPDATE bot_chats SET message = ? WHERE id = ?");
                $cleanStmt->execute([$newMsg, $hb['id']]);
            }
            if ($requestCreated || $updated) break;
        }
    } catch (Exception $e) {
        // Fail-safe
    }
}

// === Process discussion markers ===
// [FETCH_DISCUSSION: request_id] - fetches discussion messages and replaces the tag
if (preg_match('/\[FETCH_DISCUSSION:\s*(\d+)\]/', $reply, $fdMatch)) {
    $fdReqId = (int)$fdMatch[1];
    try {
        $fdStmt = $pdo->prepare("SELECT rd.*, u.name AS user_name FROM request_discussions rd LEFT JOIN users u ON u.id = rd.user_id WHERE rd.request_id = ? ORDER BY rd.created_at ASC LIMIT 20");
        $fdStmt->execute([$fdReqId]);
        $fdMsgs = $fdStmt->fetchAll(PDO::FETCH_ASSOC);
        $fdText = "\n\n--- Discussion for Request #{$fdReqId} ---\n";
        if (count($fdMsgs) === 0) {
            $fdText .= "No discussion messages yet.\n";
        } else {
            foreach ($fdMsgs as $fdm) {
                $role = $fdm['is_from_bot'] ? 'WiseBot' : ($fdm['user_name'] ?? 'User');
                $fdText .= "[{$role}] {$fdm['message']}\n";
            }
        }
        $fdText .= "--- End of Discussion ---\n";
        $reply = str_replace($fdMatch[0], $fdText, $reply);
    } catch (Exception $e) {
        $reply = str_replace($fdMatch[0], "\n\n[Unable to fetch discussion: {$e->getMessage()}]\n", $reply);
    }
}

// [ADD_DISCUSSION_NOTE: request_id | note text] - saves a note to discussion
if (preg_match('/\[ADD_DISCUSSION_NOTE:\s*(\d+)\s*\|\s*(.+?)\]/s', $reply, $adMatch)) {
    $adReqId = (int)$adMatch[1];
    $adNote = trim($adMatch[2]);
    try {
        $adStmt = $pdo->prepare("INSERT INTO request_discussions (request_id, user_id, message, is_from_bot) VALUES (?, ?, ?, 1)");
        $adStmt->execute([$adReqId, $user_id ?? 0, $adNote]);
        $reply = str_replace($adMatch[0], "\n\n📝 Note saved to Request #{$adReqId} discussion.", $reply);
    } catch (Exception $e) {
        $reply = str_replace($adMatch[0], "\n\n[Failed to save note: {$e->getMessage()}]", $reply);
    }
}

// [FIND_IMAGES: keywords] - searches Unsplash for images
if (preg_match('/\[FIND_IMAGES:\s*(.+?)\]/s', $reply, $fiMatch)) {
    $fiQuery = urlencode(trim($fiMatch[1]));
    $fiCh = curl_init("https://api.unsplash.com/search/photos?query={$fiQuery}&per_page=4&orientation=landscape");
    curl_setopt($fiCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($fiCh, CURLOPT_HTTPHEADER, ['Authorization: Client-ID jG9h2M7RqD4sK8wX1pL3nF6bV9cA0eT5yU8iO2fH']);
    $fiResp = curl_exec($fiCh);
    $fiHttp = curl_getinfo($fiCh, CURLINFO_HTTP_CODE);
    curl_close($fiCh);
    $fiReply = "\n\n--- 🖼️ Image Search Results for '{$fiMatch[1]}' ---\n";
    if ($fiHttp >= 200 && $fiHttp < 300) {
        $fiData = json_decode($fiResp, true);
        $results = $fiData['results'] ?? [];
        if (count($results) > 0) {
            foreach (array_slice($results, 0, 4) as $img) {
                $alt = htmlspecialchars($img['alt_description'] ?? 'Image');
                $fiReply .= "![]({$img['urls']['thumb']})\n";
                $fiReply .= "[{$alt}]({$img['urls']['regular']}) - Photo by {$img['user']['name']}\n\n";
            }
        } else {
            $fiReply .= "No images found for this query.\n";
        }
    } else {
        $fiReply .= "Could not search for images.\n";
    }
    $reply = str_replace($fiMatch[0], $fiReply, $reply);
}

// [PARTNER_REQUEST] - Submit partnership application
if ($user_id && strpos($reply, '[PARTNER_REQUEST]') !== false) {
    $reply = str_replace('[PARTNER_REQUEST]', '', $reply);
    try {
        $check = $pdo->query("SELECT id FROM agent_requests WHERE user_id = $user_id")->fetch();
        if ($check) {
            $pdo->query("UPDATE agent_requests SET status = 'pending', updated_at = NOW() WHERE user_id = $user_id");
        } else {
            $pdo->exec("INSERT INTO agent_requests (user_id, status, created_at, updated_at) VALUES ($user_id, 'pending', NOW(), NOW())");
        }
        add_notification($user_id, "Partnership Application Submitted via WiseBot", "Your partnership application has been submitted! We'll review it within 24–48 hours.", 'partner', '../user/upgrade_partner.php');
        // Notify admins
        $adminList = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_ASSOC);
        $userName = $_SESSION['user']['name'] ?? 'A user';
        foreach ($adminList as $admin) {
            add_notification($admin['id'], "New Partnership Application", "{$userName} submitted a partnership application via WiseBot. Review in admin panel.", 'partner', '../admin/agent_requests.php');
        }
        $reply .= "\n\n✅ **Partnership application submitted!** We'll review it within 24–48 hours and notify you. In the meantime, you can learn about our referral benefits.";
    } catch (Exception $e) {
        $reply .= "\n\n⚠️ Failed to submit partnership application: " . $e->getMessage();
    }
}

// [DEVELOPER_REQUEST: {...}] - Submit developer application
if ($user_id && preg_match('/\[DEVELOPER_REQUEST:\s*(\{.+?\})\]/s', $reply, $drMatch)) {
    $drData = json_decode($drMatch[1], true);
    if ($drData) {
        $drSkills = trim($drData['skills'] ?? '');
        $drExperience = trim($drData['experience'] ?? '');
        $drPortfolio = trim($drData['portfolio_url'] ?? '');
        $drGithub = trim($drData['github_url'] ?? '');
        $drYears = (int)($drData['years_experience'] ?? 0);
        $drRate = (float)($drData['hourly_rate'] ?? 0);
        $reply = str_replace($drMatch[0], '', $reply);
        try {
            $checkDev = $pdo->query("SELECT id FROM developer_requests WHERE user_id = $user_id LIMIT 1")->fetch();
            $skillsJson = json_encode(array_map('trim', explode(',', $drSkills)));
            if ($checkDev) {
                $pdo->prepare("UPDATE developer_requests SET skills=?, portfolio_url=?, github_url=?, experience=?, years_experience=?, hourly_rate_expected=?, status='pending', updated_at=NOW() WHERE user_id=?")
                    ->execute([$skillsJson, $drPortfolio, $drGithub, $drExperience, $drYears, $drRate, $user_id]);
            } else {
                $pdo->prepare("INSERT INTO developer_requests (user_id, skills, portfolio_url, github_url, experience, years_experience, hourly_rate_expected, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,'pending',NOW(),NOW())")
                    ->execute([$user_id, $skillsJson, $drPortfolio, $drGithub, $drExperience, $drYears, $drRate]);
            }
            add_notification($user_id, "Developer Application Submitted via WiseBot", "Your developer application has been submitted! We'll review it within 24–48 hours.", 'project', '../user/developer_hub.php');
            // Notify admins
            $adminList = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_ASSOC);
            $userName = $_SESSION['user']['name'] ?? 'A user';
            foreach ($adminList as $admin) {
                add_notification($admin['id'], "New Developer Application", "{$userName} submitted a developer application via WiseBot. Review in admin panel.", 'project', '../admin/developer_requests.php');
            }
            $reply .= "\n\n✅ **Developer application submitted!** We'll review it within 24–48 hours. Once approved, you'll get access to the Developer Hub.";
        } catch (Exception $e) {
            $reply .= "\n\n⚠️ Failed to submit developer application: " . $e->getMessage();
        }
    }
}

// [UPDATE_PROFILE: {...}] - Update user profile
if ($user_id && preg_match('/\[UPDATE_PROFILE:\s*(\{.+?\})\]/s', $reply, $upMatch)) {
    $upData = json_decode($upMatch[1], true);
    if ($upData) {
        $reply = str_replace($upMatch[0], '', $reply);
        $fields = [];
        $params = [];
        $allowedFields = ['name','phone','bio','company','profession','skills','tech_stack','previous_experience','education','linkedin_url','twitter_url','github_url','website_url'];
        foreach ($allowedFields as $f) {
            if (isset($upData[$f])) {
                $fields[] = "$f = ?";
                $params[] = trim($upData[$f]);
            }
        }
        if (!empty($fields)) {
            $params[] = $user_id;
            try {
                $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
                if (isset($upData['name'])) $_SESSION['user']['name'] = $upData['name'];
                $reply .= "\n\n✅ **Profile updated successfully!** Your changes have been saved.";
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ Failed to update profile: " . $e->getMessage();
            }
        }
    }
}

// [TOGGLE_THEME: dark|light] - Switch user theme
$themeChanged = '';
if ($user_id && preg_match('/\[TOGGLE_THEME:\s*(dark|light)\]/i', $reply, $ttMatch)) {
    $ttTheme = strtolower($ttMatch[1]);
    $reply = str_replace($ttMatch[0], '', $reply);
    try {
        $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$ttTheme, $user_id]);
        $icon = $ttTheme === 'dark' ? '🌙' : '☀️';
        $label = $ttTheme === 'dark' ? 'Dark' : 'Light';
        $reply .= "\n\n{$icon} **Theme switched to {$label} mode!**";
        $themeChanged = $ttTheme;
    } catch (Exception $e) {
        $reply .= "\n\n⚠️ Failed to change theme: " . $e->getMessage();
    }
}

// [SET_AVATAR] - Update user profile picture from uploaded image
$avatarChanged = '';
if ($user_id && strpos($reply, '[SET_AVATAR]') !== false) {
    $reply = str_replace('[SET_AVATAR]', '', $reply);
    if ($attachmentPath && $isFileImage) {
        $fullPath = __DIR__ . '/' . $attachmentPath;
        if (file_exists($fullPath)) {
            try {
                $cloudUrl = uploadToCloudinary($fullPath, 'avatars', 'image');
                if ($cloudUrl) {
                    $pdo->prepare("UPDATE users SET picture = ? WHERE id = ?")->execute([$cloudUrl, $user_id]);
                    $_SESSION['user']['picture'] = $cloudUrl;
                    $avatarChanged = $cloudUrl;
                    $reply .= "\n\n✅ **Profile picture updated!** Your new avatar has been set.";
                } else {
                    $reply .= "\n\n⚠️ I couldn't upload the image to our server. Please try again or use the profile page to set your avatar.";
                }
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ I'm having trouble updating your profile picture right now. Please try again later.";
                @botError($pdo, 'SET_AVATAR upload failed: ' . $e->getMessage());
            }
        } else {
            $reply .= "\n\n⚠️ The uploaded image file could not be found. Please try uploading again.";
        }
    } else {
        $reply .= "\n\n⚠️ No image was found in this message. Please upload a photo using the 📎 paperclip button first.";
    }
}

// [CANCEL_PARTNER] - Cancel/delete partnership application
if ($user_id && strpos($reply, '[CANCEL_PARTNER]') !== false) {
    $reply = str_replace('[CANCEL_PARTNER]', '', $reply);
    try {
        $stmt = $pdo->prepare("DELETE FROM agent_requests WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            $reply .= "\n\n✅ **Partnership application cancelled.** Your application has been withdrawn. You can re-apply anytime you're ready.";
        } else {
            $reply .= "\n\nℹ️ You don't have a pending partnership application to cancel.";
        }
    } catch (Exception $e) {
        $reply .= "\n\n⚠️ Failed to cancel partnership application: " . $e->getMessage();
    }
}

// [CANCEL_DEVELOPER] - Cancel/delete developer application
if ($user_id && strpos($reply, '[CANCEL_DEVELOPER]') !== false) {
    $reply = str_replace('[CANCEL_DEVELOPER]', '', $reply);
    try {
        $stmt = $pdo->prepare("DELETE FROM developer_requests WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            $reply .= "\n\n✅ **Developer application cancelled.** Your application has been withdrawn. You can re-apply anytime.";
        } else {
            $reply .= "\n\nℹ️ You don't have a pending developer application to cancel.";
        }
    } catch (Exception $e) {
        $reply .= "\n\n⚠️ Failed to cancel developer application: " . $e->getMessage();
    }
}

// [REQUEST_CANCEL: {request_id, reason}] - Submit cancellation request for an approved project
if ($user_id && preg_match('/\[REQUEST_CANCEL:\s*(\{.+?\})\]/s', $reply, $cancelMatch)) {
    $cancelData = json_decode($cancelMatch[1], true);
    $reply = str_replace($cancelMatch[0], '', $reply);

    if ($cancelData && isset($cancelData['request_id']) && isset($cancelData['reason'])) {
        $reqId = (int)$cancelData['request_id'];
        $cancelReason = trim($cancelData['reason']);

        if ($reqId > 0 && !empty($cancelReason)) {
            try {
                // Verify the request belongs to the user and is approved
                $checkStmt = $pdo->prepare("SELECT id, title, status, cancel_requested FROM client_requests WHERE id = ? AND user_id = ?");
                $checkStmt->execute([$reqId, $user_id]);
                $reqRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$reqRow) {
                    $reply .= "\n\n⚠️ Project request not found or you don't have permission to cancel it.";
                } elseif ($reqRow['status'] === 'completed') {
                    $reply .= "\n\n⚠️ This project has already been **completed** and cannot be cancelled.";
                } elseif ($reqRow['status'] !== 'approved') {
                    $reply .= "\n\n⚠️ Only approved projects require admin cancellation review. Your project status is **" . ucfirst($reqRow['status']) . "**. You can cancel it directly from the My Proposals page.";
                } elseif ($reqRow['cancel_requested']) {
                    $reply .= "\n\nℹ️ A cancellation request for **" . $reqRow['title'] . "** is already pending admin approval. You'll be notified once a decision is made.";
                } else {
                    // Submit the cancellation request
                    $updStmt = $pdo->prepare("UPDATE client_requests SET cancel_requested = 1, cancel_reason = ? WHERE id = ?");
                    $updStmt->execute([$cancelReason, $reqId]);

                    // Notify the client
                    add_notification($user_id, "Cancellation Request Submitted", "Your cancellation request for project '{$reqRow['title']}' has been submitted and is pending admin review.", 'project', '../user/my_requests.php', $reqId);

                    // Notify all admin users
                    $adminList = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                    $adminList->execute();
                    $admins = $adminList->fetchAll(PDO::FETCH_ASSOC);
                    $userName = $_SESSION['user']['name'] ?? 'A user';
                    foreach ($admins as $admin) {
                        add_notification($admin['id'], "🚨 Project Cancellation Request",
                            "{$userName} has requested cancellation for approved project '{$reqRow['title']}' via WiseBot. Reason: {$cancelReason}",
                            'project', '../admin/client_requests.php', $reqId);
                    }

                    $reply .= "\n\n✅ **Cancellation request submitted!** I've sent your cancellation request for **{$reqRow['title']}** to the admin team for review. They'll notify you once a decision is made. Is there anything else I can help with?";
                }
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ Failed to submit cancellation request: " . $e->getMessage();
                @file_put_contents(__DIR__ . '/bot_errors.log', "[" . date('Y-m-d H:i:s') . "] REQUEST_CANCEL error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            $reply .= "\n\n⚠️ I couldn't process the cancellation request. Please make sure you've provided a valid project ID and reason.";
        }
    } else {
        $reply .= "\n\n⚠️ I couldn't process the cancellation request. Please try again.";
    }
}

// [REQUEST_SUSPEND: {request_id, reason, start_date, end_date}] - Submit suspension request for an approved project
if ($user_id && preg_match('/\[REQUEST_SUSPEND:\s*(\{.+?\})\]/s', $reply, $suspendMatch)) {
    $suspendData = json_decode($suspendMatch[1], true);
    $reply = str_replace($suspendMatch[0], '', $reply);

    if ($suspendData && isset($suspendData['request_id']) && isset($suspendData['reason']) && isset($suspendData['start_date']) && isset($suspendData['end_date'])) {
        $reqId = (int)$suspendData['request_id'];
        $suspendReason = trim($suspendData['reason']);
        $startDate = trim($suspendData['start_date']);
        $endDate = trim($suspendData['end_date']);

        if ($reqId > 0 && !empty($suspendReason) && !empty($startDate) && !empty($endDate)) {
            // Validate date format
            $startObj = DateTime::createFromFormat('Y-m-d', $startDate);
            $endObj = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$startObj || !$endObj || $endObj <= $startObj) {
                $reply .= "\n\n⚠️ Invalid dates. The resume date must be after the start date. Please try again.";
            } else {
                try {
                    // Verify the request belongs to the user and is approved
                    $checkStmt = $pdo->prepare("SELECT id, title, status, cancel_requested, suspend_requested FROM client_requests WHERE id = ? AND user_id = ?");
                    $checkStmt->execute([$reqId, $user_id]);
                    $reqRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$reqRow) {
                        $reply .= "\n\n⚠️ Project request not found or you don't have permission to suspend it.";
                    } elseif ($reqRow['status'] === 'completed') {
                        $reply .= "\n\n⚠️ This project has already been **completed** and cannot be suspended.";
                    } elseif ($reqRow['status'] !== 'approved') {
                        $reply .= "\n\n⚠️ Only approved projects can be suspended. Your project status is **" . ucfirst($reqRow['status']) . "**.";
                    } elseif ($reqRow['cancel_requested']) {
                        $reply .= "\n\n⚠️ A cancellation request for **" . $reqRow['title'] . "** is already pending. Please wait for that to be resolved first.";
                    } elseif ($reqRow['suspend_requested']) {
                        $reply .= "\n\nℹ️ A suspension request for **" . $reqRow['title'] . "** is already pending admin approval. You'll be notified once a decision is made.";
                    } else {
                        // Submit the suspension request
                        $updStmt = $pdo->prepare("UPDATE client_requests SET suspend_requested = 1, suspend_reason = ?, suspend_start_date = ?, suspend_end_date = ? WHERE id = ?");
                        $updStmt->execute([$suspendReason, $startDate, $endDate, $reqId]);

                        // Notify the client
                        add_notification($user_id, "Suspension Request Submitted", "Your suspension request for project '{$reqRow['title']}' ({$startDate} to {$endDate}) has been submitted and is pending admin review.", 'project', '../user/my_requests.php', $reqId);

                        // Notify all admin users
                        $adminList = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                        $adminList->execute();
                        $admins = $adminList->fetchAll(PDO::FETCH_ASSOC);
                        $userName = $_SESSION['user']['name'] ?? 'A user';
                        foreach ($admins as $admin) {
                            add_notification($admin['id'], "⏸️ Project Suspension Request",
                                "{$userName} has requested suspension for approved project '{$reqRow['title']}' via WiseBot from {$startDate} to {$endDate}. Reason: {$suspendReason}",
                                'project', '../admin/client_requests.php', $reqId);
                        }

                        $reply .= "\n\n✅ **Suspension request submitted!** I've sent your suspension request for **{$reqRow['title']}** (from {$startDate} to {$endDate}) to the admin team for review. They'll notify you once a decision is made. Is there anything else I can help with?";
                    }
                } catch (Exception $e) {
                    $reply .= "\n\n⚠️ Failed to submit suspension request: " . $e->getMessage();
                    @file_put_contents(__DIR__ . '/bot_errors.log', "[" . date('Y-m-d H:i:s') . "] REQUEST_SUSPEND error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }
            }
        } else {
            $reply .= "\n\n⚠️ I couldn't process the suspension request. Please make sure you've provided a valid project ID, reason, start date, and resume date.";
        }
    } else {
        $reply .= "\n\n⚠️ I couldn't process the suspension request. Please try again.";
    }
}

// [UPDATE_REQUEST: {request_id, title, description, category, software_type, features, recommendations}] - Edit a project request
if ($user_id && preg_match('/\[UPDATE_REQUEST:\s*(\{.+?\})\]/s', $reply, $upMatch)) {
    $upData = json_decode($upMatch[1], true);
    $reply = str_replace($upMatch[0], '', $reply);

    if ($upData && isset($upData['request_id'])) {
        $reqId = (int)$upData['request_id'];

        if ($reqId > 0) {
            try {
                // Verify ownership and get current status
                $checkStmt = $pdo->prepare("SELECT id, title, status FROM client_requests WHERE id = ? AND user_id = ?");
                $checkStmt->execute([$reqId, $user_id]);
                $reqRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$reqRow) {
                    $reply .= "\n\n⚠️ Project request not found or you don't have permission to edit it.";
                } elseif (!in_array($reqRow['status'], ['pending', 'reviewed', 'rejected'])) {
                    $reply .= "\n\n⚠️ This project has been **" . ucfirst($reqRow['status']) . "** and its details cannot be edited. Please contact support if you need changes.";
                } else {
                    // Build update query with only provided fields
                    $fields = [];
                    $params = [];
                    $allowedFields = [
                        'title' => 'title',
                        'description' => 'description',
                        'category' => 'categories',
                        'software_type' => 'software_type',
                        'features' => 'features',
                        'recommendations' => 'recommendations'
                    ];

                    foreach ($allowedFields as $key => $column) {
                        if (isset($upData[$key]) && trim($upData[$key]) !== '') {
                            $fields[] = $column . " = ?";
                            $params[] = trim($upData[$key]);
                        }
                    }

                    if (empty($fields)) {
                        $reply .= "\n\n⚠️ No fields to update. Please specify what you'd like to change.";
                    } else {
                        $params[] = $reqId;
                        $params[] = $user_id;
                        $sql = "UPDATE client_requests SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
                        $updStmt = $pdo->prepare($sql);
                        $updStmt->execute($params);

                        $updatedFields = array_keys($allowedFields);
                        $changedNames = [];
                        foreach ($upData as $k => $v) {
                            if ($k !== 'request_id' && !empty($v)) $changedNames[] = $k;
                        }

                        add_notification($user_id, "Project Request Updated via WiseBot", "Your project request '{$reqRow['title']}' was updated. Changed: " . implode(', ', $changedNames), 'project', '../user/my_requests.php', $reqId);

                        $reply .= "\n\n✅ **Project request updated successfully!** Your changes for **{$reqRow['title']}** have been saved. The admin team will see the updated details. Is there anything else?";
                    }
                }
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ Failed to update project request: " . $e->getMessage();
                @file_put_contents(__DIR__ . '/bot_errors.log', "[" . date('Y-m-d H:i:s') . "] UPDATE_REQUEST error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            $reply .= "\n\n⚠️ Invalid request ID. Please try again.";
        }
    } else {
        $reply .= "\n\n⚠️ I couldn't process the update. Please try again.";
    }
}

// [UPDATE_DEVELOPER: {...}] - Update developer application
if ($user_id && preg_match('/\[UPDATE_DEVELOPER:\s*(\{.+?\})\]/s', $reply, $udMatch)) {
    $udData = json_decode($udMatch[1], true);
    if ($udData) {
        $reply = str_replace($udMatch[0], '', $reply);
        $fields = []; $params = [];
        $allowedDev = ['skills', 'portfolio_url', 'github_url', 'experience', 'years_experience', 'hourly_rate'];
        $fieldMap = ['skills' => 'skills', 'portfolio_url' => 'portfolio_url', 'github_url' => 'github_url', 'experience' => 'experience', 'years_experience' => 'years_experience', 'hourly_rate' => 'hourly_rate_expected'];
        foreach ($allowedDev as $f) {
            if (isset($udData[$f])) {
                if ($f === 'skills') {
                    $val = json_encode(array_map('trim', explode(',', $udData[$f])));
                } elseif ($f === 'years_experience') {
                    $val = (int)$udData[$f];
                } elseif ($f === 'hourly_rate') {
                    $val = (float)$udData[$f];
                } else {
                    $val = trim($udData[$f]);
                }
                $fields[] = $fieldMap[$f] . " = ?";
                $params[] = $val;
            }
        }
        if (!empty($fields)) {
            $fields[] = "status = 'pending'";
            $fields[] = "updated_at = NOW()";
            $params[] = $user_id;
            try {
                $pdo->prepare("UPDATE developer_requests SET " . implode(', ', $fields) . " WHERE user_id = ?")->execute($params);
                add_notification($user_id, "Developer Application Updated via WiseBot", "Your developer application has been updated and is pending review.", 'project', '../user/developer_hub.php');
                $reply .= "\n\n✅ **Developer application updated!** Your changes have been saved and the application is now pending review.";
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ Failed to update developer application: " . $e->getMessage();
            }
        }
    }
}

// [SUBMIT_CONTACT: {...}] - Guest Contact Form Lead Capture
if (preg_match('/\[SUBMIT_CONTACT:\s*(\{.+?\})\]/s', $reply, $scMatch)) {
    $scData = json_decode($scMatch[1], true);
    if ($scData) {
        $reply = str_replace($scMatch[0], '', $reply);
        $cName = trim($scData['name'] ?? '');
        $cEmail = trim($scData['email'] ?? '');
        $cPhone = trim($scData['phone'] ?? '');
        $cService = trim($scData['service'] ?? '');
        $cBudget = trim($scData['budget'] ?? '');
        $cTimeline = trim($scData['timeline'] ?? '');
        $cMessage = trim($scData['message'] ?? '');
        $cCompany = trim($scData['company'] ?? '');
        
        if ($cName && $cEmail && $cMessage) {
            try {
                $refNumber = 'WQS-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, service, budget, timeline, message, company, ref_number, status) VALUES (?,?,?,?,?,?,?,?,?,'new')");
                $stmt->execute([$cName, $cEmail, $cPhone, $cService, $cBudget, $cTimeline, $cMessage, $cCompany, $refNumber]);
                
                // Notify Admins
                $adminList = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
                while ($admin = $adminList->fetch(PDO::FETCH_ASSOC)) {
                    add_notification($admin['id'], "New Contact Message", "You have a new contact form message from {$cName}.", "info", "../admin/contact_messages.php");
                }
                
                $reply .= "\n\n✅ **Message sent successfully!** Your reference number is `#$refNumber`. Our team will review your project details and get back to you shortly at $cEmail.\n\n*In the meantime, feel free to ask me anything else about Wise Quotient Soft!*";
            } catch (Exception $e) {
                $reply .= "\n\n⚠️ Failed to submit contact form. Please try again or use the Contact Us page directly.";
            }
        } else {
            $reply .= "\n\n⚠️ Missing required fields (Name, Email, or Message). Please provide them so I can submit the form.";
        }
    }
}

// [TRIGGER_HANDOFF] - Create support ticket and notify admin team
if ($user_id && strpos($reply, '[TRIGGER_HANDOFF]') !== false) {
    $reply = str_replace('[TRIGGER_HANDOFF]', '', $reply);
    try {
        $pdo->beginTransaction();
        // Generate ticket number
        $maxStmt = $pdo->query("SELECT MAX(id) FROM support_tickets");
        $maxId = (int)$maxStmt->fetchColumn();
        $ticketNum = 'WQS-' . str_pad($maxId + 1000, 5, '0', STR_PAD_LEFT);
        $userName = $_SESSION['user']['name'] ?? 'User';
        $subject = "Urgent: $userName needs assistance";
        $tStmt = $pdo->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, category, priority, status, origin) VALUES (?, ?, ?, 'technical', 'high', 'open', 'bot')");
        $tStmt->execute([$user_id, $ticketNum, $subject]);
        $ticketId = $pdo->lastInsertId();
        // Insert initial message
        $rStmt = $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $rStmt->execute([$ticketId, $user_id, "Urgent support requested via chat. Message: " . substr($userMessage, 0, 500)]);
        // Notify all admins/agents
        $adminList = $pdo->query("SELECT id, phone FROM users WHERE role IN ('admin','developer')");
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
        while ($admin = $adminList->fetch(PDO::FETCH_ASSOC)) {
            $notifStmt->execute([$admin['id'], "🔔 Urgent: $ticketNum - $subject", "Click to take this chat and help $userName."]);
            // SMS if phone available
            if (!empty($admin['phone'])) {
                @send_termii_sms($admin['phone'], "URGENT: $userName needs help on WQS. Ticket #$ticketNum. Please check the admin panel.", $pdo);
            }
        }
        $pdo->commit();
        $reply .= "\n\n✅ **Support ticket #$ticketNum created!** Our team has been notified and will be with you shortly.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $reply .= "\n\n⚠️ I tried to create a support ticket but ran into an issue. Please click the 'Link me with a person' button in the chat instead.";
    }
}

// Save Bot Reply to Database
try {
    $botStmt = $pdo->prepare("INSERT INTO bot_chats (session_id, user_id, role, message, is_critical) VALUES (?, ?, 'bot', ?, 0)");
    $botStmt->execute([$session_id, $user_id, $reply]);
} catch (Exception $e) {
    // Fail-safe
}

$responseData = ['reply' => $reply];
if ($themeChanged) {
    $responseData['theme'] = $themeChanged;
}
if ($avatarChanged) {
    $responseData['avatar'] = $avatarChanged;
}
echo json_encode($responseData);
?>
