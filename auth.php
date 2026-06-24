<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// === OAuth Config ===
$google_client_id = GOOGLE_CLIENT_ID;
$google_client_secret = GOOGLE_CLIENT_SECRET;
$github_client_id = GITHUB_CLIENT_ID;
$github_client_secret = GITHUB_CLIENT_SECRET;
$redirect_uri = 'https://wisequotientsoft.com/auth.php';

// === Get IP and User Agent ===
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// === Detect Browser Type ===
function getBrowser($user_agent) {
    if (strpos($user_agent, 'Edg') !== false) return 'Edge';
    if (strpos($user_agent, 'OPR') !== false || strpos($user_agent, 'Opera') !== false) return 'Opera';
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) return 'Internet Explorer';
    return 'Unknown';
}
$browser_type = getBrowser($user_agent);

// === Manual Registration (JSON POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);

    if (isset($input['name'], $input['email'], $input['phone'], $input['password'])) {
        // CSRF validation
        if (isset($input['csrf_token']) && !validate_csrf_token($input['csrf_token'])) {
            echo json_encode(["success" => false, "error" => "Invalid security token. Please refresh the page and try again."]);
            exit;
        }

        $rawName = $input['name'];
        $rawEmail = $input['email'];
        $rawPhone = normalizePhone($input['phone']);
        $password = password_hash($input['password'], PASSWORD_DEFAULT);

        // Check if user exists (prepared statement)
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$rawEmail]);
        if ($check->rowCount() > 0) {
            echo json_encode(["success" => false, "error" => "User already exists"]);
            exit;
        }

        $referred_by = null;
        $referred_by_code_val = null;
        if (isset($input['referred_by']) && !empty($input['referred_by'])) {
            $rawRef = trim($input['referred_by']);
            // Accept WQS-XXXXXXXX (8 hex) or WQS-XXXXXXXXXXXX (12 hex) format (no legacy numeric IDs)
            if (preg_match('/^WQS-[A-F0-9]{8,12}$/i', $rawRef)) {
                $safeCode = strtoupper($rawRef);
                $refResult = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role != 'admin'");
                $refResult->execute([$safeCode]);
                if ($refResult->rowCount() > 0) {
                    $refRow = $refResult->fetch(PDO::FETCH_ASSOC);
                    $referred_by = (int)$refRow['id'];
                    $referred_by_code_val = $safeCode;
                }
            }
        } 
        
        if ($referred_by === null) {
            // Check session (set by register.php referral link)
            if (isset($_SESSION['referred_by']) && (int)$_SESSION['referred_by'] > 0) {
                $referred_by = (int)$_SESSION['referred_by'];
                if (isset($_SESSION['referred_by_code']) && !empty($_SESSION['referred_by_code'])) {
                    $referred_by_code_val = $_SESSION['referred_by_code'];
                }
            } elseif (isset($_SESSION['referred_by_token']) && !empty($_SESSION['referred_by_token'])) {
                // Validate HMAC-signed token from cookie
                $tokenData = validate_referral_token($_SESSION['referred_by_token']);
                if ($tokenData) {
                    $referred_by = $tokenData['user_id'];
                    $referred_by_code_val = $tokenData['code'];
                }
            } elseif (isset($_COOKIE['referred_by_token']) && !empty($_COOKIE['referred_by_token'])) {
                $tokenData = validate_referral_token($_COOKIE['referred_by_token']);
                if ($tokenData) {
                    $referred_by = $tokenData['user_id'];
                    $referred_by_code_val = $tokenData['code'];
                }
            }
        }

        // Self-referral prevention: check if the registering user's email matches the referrer's email
        if ($referred_by !== null && !empty($rawEmail)) {
            $selfRefCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email = ?");
            $selfRefCheck->execute([$referred_by, $rawEmail]);
            if ($selfRefCheck->rowCount() > 0) {
                $referred_by = null;
                $referred_by_code_val = null;
                unset($_SESSION['referred_by'], $_SESSION['referred_by_code'], $_SESSION['referred_by_token']);
                setcookie('referred_by_token', '', time() - 3600, '/');
            }
        }

        // Generate unique referral code for new user
        $candidate = '';
        do {
            $candidate = 'WQS-' . strtoupper(bin2hex(random_bytes(6)));
            $codeChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE referral_code = ?");
            $codeChk->execute([$candidate]);
            $codeRow = $codeChk->fetch(PDO::FETCH_ASSOC);
        } while ($codeRow && $codeRow['cnt'] > 0);

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

        $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, provider, picture, role, last_login, user_agent, ip_address, referred_by, referral_code, referred_by_code)
                                  VALUES (?, ?, ?, ?, 'form', '', 'user', NOW(), ?, ?, ?, ?, ?)");
        if ($insert->execute([$rawName, $rawEmail, $rawPhone, $password, $user_agent, $ip_address, $referred_by, $candidate, $referred_by_code_val])) {
            $user_id = (int)$pdo->lastInsertId();
            
            add_notification($user_id, "Welcome to Wise Quotient Soft!", "Hello " . htmlspecialchars($rawName) . ", welcome to your dashboard. This is where you will receive important status updates and workflow notifications.", 'welcome', '../user/dashboard.php');
            
            $_SESSION['user'] = [
                "id" => $user_id,
                "name" => $rawName,
                "email" => $rawEmail,
                "provider" => 'form',
                "picture" => '',
                "last_login" => date("Y-m-d H:i:s"),
                "role" => 'user'
            ];

            unset($_SESSION['referred_by']);
            unset($_SESSION['referred_by_code']);
            unset($_SESSION['referred_by_token']);
            setcookie('referred_by_token', '', time() - 3600, '/');

            $logStmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, browser_type, login_type)
                            VALUES (?, NOW(), ?, ?, ?, 'form')");
            $logStmt->execute([$user_id, $ip_address, $user_agent, $browser_type ?? '']);

            echo json_encode(["success" => true, "message" => "Registration successful", "redirect" => "user/dashboard.php", "role" => "user"]);
        } else {
            echo json_encode(["success" => false, "error" => "Registration failed"]);
        }
        exit;
    } else {
        echo json_encode(["success" => false, "error" => "Invalid input data"]);
        exit;
    }
}

// === Phone number normalization helper ===
function normalizePhone($input) {
    $digits = preg_replace('/[^0-9]/', '', $input);
    if (strpos($digits, '234') === 0) {
        return '0' . substr($digits, 3);
    } elseif (strpos($digits, '0') === 0) {
        return $digits;
    } else {
        return '0' . $digits;
    }
}

function getPhoneVariants($input) {
    $digits = preg_replace('/[^0-9]/', '', $input);
    if (!$digits) return [];
    $variants = [$digits];
    if (strpos($digits, '234') === 0) {
        $rest = substr($digits, 3);
        $variants[] = '0' . $rest;
        $variants[] = $rest;
    } elseif (strpos($digits, '0') === 0) {
        $rest = substr($digits, 1);
        $variants[] = '234' . $rest;
        $variants[] = $rest;
    } else {
        $variants[] = '0' . $digits;
        $variants[] = '234' . $digits;
    }
    return array_unique($variants);
}

// === Manual Login (Form POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier'], $_POST['password'])) {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    // Detect if identifier is email or phone
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    $placeholders = ["email = ?"];
    $params = [$identifier];

    if (!$isEmail) {
        // Only generate phone variants if identifier is NOT an email
        $phoneVariants = getPhoneVariants($identifier);
        foreach ($phoneVariants as $v) {
            $placeholders[] = "phone = ?";
            $params[] = $v;
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE " . implode(' OR ', $placeholders) . " LIMIT 1");
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $pdo->prepare("UPDATE users SET last_login = NOW(), user_agent = ?, ip_address = ? WHERE id = ?")->execute([$user_agent, $ip_address, $user['id']]);

            $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, browser_type, login_type) VALUES (?, NOW(), ?, ?, ?, 'form')")
                ->execute([$user['id'], $ip_address, $user_agent, $browser_type]);

            $_SESSION['user'] = [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email'],
                "provider" => $user['provider'],
                "picture" => $user['picture'],
                "last_login" => date("Y-m-d H:i:s"),
                "role" => $user['role']
            ];

            $redirect_url = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';

            echo json_encode([
                "success" => true,
                "message" => "Login successfully",
                "redirect" => $redirect_url,
                "role" => $user['role']
            ]);
        } else {
            echo json_encode(["success" => false, "error" => "Wrong Details provided"]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "User not found"]);
    }
    exit;
}

// === Google OAuth Callback ===
if (isset($_GET['code'], $_GET['provider']) && $_GET['provider'] === 'google') {
    $tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'code' => $_GET['code'],
                'client_id' => $google_client_id,
                'client_secret' => $google_client_secret,
                'redirect_uri' => $redirect_uri . '?provider=google',
                'grant_type' => 'authorization_code'
            ])
        ]
    ]));

    $token_data = json_decode($tokenResponse, true);
    if (isset($token_data['access_token'])) {
        $userInfo = file_get_contents("https://www.googleapis.com/oauth2/v1/userinfo?access_token=" . $token_data['access_token']);
        $info = json_decode($userInfo, true);
        saveOAuthUser($info['name'], $info['email'], 'google', $info['picture'] ?? '');
    } else {
        header("Location: login.php?error=google_auth_failed");
        exit;
    }
    exit;
}

// === GitHub OAuth Callback ===
if (isset($_GET['code'], $_GET['provider']) && $_GET['provider'] === 'github') {
    $tokenUrl = 'https://github.com/login/oauth/access_token?' . http_build_query([
        'client_id' => $github_client_id,
        'client_secret' => $github_client_secret,
        'code' => $_GET['code'],
        'redirect_uri' => $redirect_uri . '?provider=github'
    ]);

    $response = file_get_contents($tokenUrl, false, stream_context_create([
        'http' => ['header' => "Accept: application/json"]
    ]));

    $token_data = json_decode($response, true);
    if (isset($token_data['access_token'])) {
        $opts = [
            'http' => [
                'method' => "GET",
                'header' => [
                    "User-Agent: WiseApp",
                    "Authorization: token " . $token_data['access_token']
                ]
            ]
        ];

        $userInfo = file_get_contents("https://api.github.com/user", false, stream_context_create($opts));
        $info = json_decode($userInfo, true);

        $emailInfo = file_get_contents("https://api.github.com/user/emails", false, stream_context_create($opts));
        $emails = json_decode($emailInfo, true);

        $primaryEmail = '';
        if (is_array($emails)) {
            foreach ($emails as $e) {
                if (!empty($e['primary']) && !empty($e['verified'])) {
                    $primaryEmail = $e['email'];
                    break;
                }
            }
        }

        $name = $info['name'] ?? $info['login'] ?? 'GitHub User';
        $avatar = $info['avatar_url'] ?? '';
        saveOAuthUser($name, $primaryEmail, 'github', $avatar);
    } else {
        header("Location: login.php?error=github_auth_failed");
        exit;
    }
    exit;
}

// === Save OAuth User (Google/GitHub) ===
function saveOAuthUser($name, $email, $provider, $picture = '') {
    global $pdo, $user_agent, $ip_address, $browser_type;

    if (empty($email)) {
        header("Location: login.php?error=missing_email");
        exit;
    }

    $phone = 'N/A';
    $password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);

    $exists = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $exists->execute([$email]);
    if ($exists->rowCount() === 0) {
        $referred_by = null;
        $referred_by_code_val = null;
        if (isset($_SESSION['referred_by']) && (int)$_SESSION['referred_by'] > 0) {
            $referred_by = (int)$_SESSION['referred_by'];
            if (isset($_SESSION['referred_by_code']) && !empty($_SESSION['referred_by_code'])) {
                $referred_by_code_val = $_SESSION['referred_by_code'];
            }
        } elseif (isset($_SESSION['referred_by_token']) && !empty($_SESSION['referred_by_token'])) {
            $tokenData = validate_referral_token($_SESSION['referred_by_token']);
            if ($tokenData) {
                $referred_by = $tokenData['user_id'];
                $referred_by_code_val = $tokenData['code'];
            }
        } elseif (isset($_COOKIE['referred_by_token']) && !empty($_COOKIE['referred_by_token'])) {
            $tokenData = validate_referral_token($_COOKIE['referred_by_token']);
            if ($tokenData) {
                $referred_by = $tokenData['user_id'];
                $referred_by_code_val = $tokenData['code'];
            }
        }

        // Self-referral prevention for OAuth
        if ($referred_by !== null && !empty($email)) {
            $selfRefCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email = ?");
            $selfRefCheck->execute([$referred_by, $email]);
            if ($selfRefCheck->rowCount() > 0) {
                $referred_by = null;
                $referred_by_code_val = null;
            }
        }

        // Generate unique referral code
        do {
            $candidate = 'WQS-' . strtoupper(bin2hex(random_bytes(6)));
            $codeChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE referral_code = ?");
            $codeChk->execute([$candidate]);
            $codeRow = $codeChk->fetch(PDO::FETCH_ASSOC);
        } while ($codeRow && $codeRow['cnt'] > 0);

        $insStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, provider, picture, role, last_login, user_agent, ip_address, referred_by, referral_code, referred_by_code)
                    VALUES (?, ?, ?, ?, ?, ?, 'user', NOW(), ?, ?, ?, ?, ?)");
        $insStmt->execute([$name, $email, $phone, $password, $provider, $picture, $user_agent ?? '', $ip_address ?? '', $referred_by, $candidate, $referred_by_code_val]);

        $new_user_id = (int)$pdo->lastInsertId();
        add_notification($new_user_id, "Welcome to Wise Quotient Soft!", "Hello " . htmlspecialchars($name) . ", welcome to your dashboard. This is where you will receive important status updates and workflow notifications.", 'welcome', '../user/dashboard.php');

        unset($_SESSION['referred_by']);
        unset($_SESSION['referred_by_code']);
        unset($_SESSION['referred_by_token']);
        setcookie('referred_by_token', '', time() - 3600, '/');
    } else {
        $updStmt = $pdo->prepare("UPDATE users SET provider = ?, picture = ?, last_login = NOW(), user_agent = ?, ip_address = ? WHERE email = ?");
        $updStmt->execute([$provider, $picture, $user_agent ?? '', $ip_address ?? '', $email]);
    }

    $result = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $result->execute([$email]);
    $user = $result->fetch(PDO::FETCH_ASSOC);

    $_SESSION['user'] = [
        "id" => $user['id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "provider" => $user['provider'],
        "picture" => $user['picture'],
        "last_login" => $user['last_login'],
        "role" => $user['role']
    ];

    $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, browser_type, login_type)
                VALUES (?, NOW(), ?, ?, ?, ?)")->execute([$user['id'], $ip_address, $user_agent, $browser_type, $provider]);

    $redirect = ($user['role'] === 'user') ? 'user/dashboard.php' : 'admin/dashboard.php';
    header("Location: $redirect");
    exit;
}
?>
