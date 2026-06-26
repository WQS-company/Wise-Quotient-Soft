<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// === Check Session Status ===
if ($action === 'check-session') {
    $isLoggedIn = isset($_SESSION['user']['id']) && !empty($_SESSION['user']['id']);
    $response = ['success' => true, 'logged_in' => $isLoggedIn];
    if ($isLoggedIn) {
        $response['user'] = [
            'name' => $_SESSION['user']['name'] ?? '',
            'email' => $_SESSION['user']['email'] ?? '',
            'role' => $_SESSION['user']['role'] ?? 'user'
        ];
    }
    echo json_encode($response);
    exit;
}

// === Register from Chat ===
if ($action === 'register') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $referred_by_code = trim($input['referred_by'] ?? '');

    // Server-side validation
    $errors = [];
    if (empty($name) || strlen($name) < 2) $errors[] = 'Please enter your full name (at least 2 characters).';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (empty($phone)) $errors[] = 'Please enter your phone number.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check password strength
    if (strlen($password) >= 6) {
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain both letters and numbers.';
        }
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
        exit;
    }

    // Normalize phone
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '234') === 0) {
        $phone = '0' . substr($digits, 3);
    } elseif (strpos($digits, '0') !== 0) {
        $phone = '0' . $digits;
    } else {
        $phone = $digits;
    }

    // Check existing user
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'An account with this email already exists. Please try logging in instead.']);
        exit;
    }

    // Check phone uniqueness
    $phoneCheck = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $phoneCheck->execute([$phone]);
    if ($phoneCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'An account with this phone number already exists. Please try logging in instead.']);
        exit;
    }

    // Handle referral code
    $referred_by = null;
    $referred_by_code_val = null;
    if (!empty($referred_by_code)) {
        $rawRef = strtoupper($referred_by_code);
        if (preg_match('/^WQS-[A-F0-9]{8,12}$/i', $rawRef)) {
            $refResult = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role != 'admin'");
            $refResult->execute([$rawRef]);
            if ($refResult->rowCount() > 0) {
                $refRow = $refResult->fetch(PDO::FETCH_ASSOC);
                $referred_by = (int)$refRow['id'];
                $referred_by_code_val = $rawRef;
            }
        }
    }

    // Fallback to session referral
    if ($referred_by === null) {
        if (isset($_SESSION['referred_by']) && (int)$_SESSION['referred_by'] > 0) {
            $referred_by = (int)$_SESSION['referred_by'];
            $referred_by_code_val = $_SESSION['referred_by_code'] ?? null;
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
    }

    // Self-referral prevention
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

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $user_agent_str = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '';

    $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, provider, picture, role, last_login, user_agent, ip_address, referred_by, referral_code, referred_by_code)
                             VALUES (?, ?, ?, ?, 'form', '', 'user', NOW(), ?, ?, ?, ?, ?)");
    if ($insert->execute([$name, $email, $phone, $hashedPassword, $user_agent_str, $ip_addr, $referred_by, $candidate, $referred_by_code_val])) {
        $user_id = (int)$pdo->lastInsertId();

        add_notification($user_id, "Welcome to Wise Quotient Soft!", "Hello " . htmlspecialchars($name) . ", welcome to your dashboard. This is where you will receive important status updates and workflow notifications.", 'welcome', '../user/dashboard.php');

        $_SESSION['user'] = [
            "id" => $user_id,
            "name" => $name,
            "email" => $email,
            "provider" => 'form',
            "picture" => '',
            "last_login" => date("Y-m-d H:i:s"),
            "role" => 'user'
        ];

        unset($_SESSION['referred_by'], $_SESSION['referred_by_code'], $_SESSION['referred_by_token']);
        setcookie('referred_by_token', '', time() - 3600, '/');

        $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, browser_type, login_type) VALUES (?, NOW(), ?, ?, ?, 'chat')")
            ->execute([$user_id, $ip_addr, $user_agent_str, '']);

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user' => ['name' => $name, 'email' => $email, 'role' => 'user'],
            'redirect' => 'user/dashboard.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
    }
    exit;
}

// === Login from Chat ===
if ($action === 'login') {
    $identifier = trim($input['identifier'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Please provide both your email/phone and password.']);
        exit;
    }

    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    $placeholders = ["email = ?"];
    $params = [$identifier];

    if (!$isEmail) {
        $digits = preg_replace('/[^0-9]/', '', $identifier);
        $variants = [$digits];
        if (strpos($digits, '234') === 0) {
            $variants[] = '0' . substr($digits, 3);
            $variants[] = substr($digits, 3);
        } elseif (strpos($digits, '0') === 0) {
            $variants[] = '234' . substr($digits, 1);
            $variants[] = substr($digits, 1);
        } else {
            $variants[] = '0' . $digits;
            $variants[] = '234' . $digits;
        }
        $variants = array_unique($variants);
        foreach ($variants as $v) {
            $placeholders[] = "phone = ?";
            $params[] = $v;
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE " . implode(' OR ', $placeholders) . " LIMIT 1");
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $user_agent_str = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '';

        $pdo->prepare("UPDATE users SET last_login = NOW(), user_agent = ?, ip_address = ? WHERE id = ?")
            ->execute([$user_agent_str, $ip_addr, $user['id']]);

        $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, browser_type, login_type) VALUES (?, NOW(), ?, ?, ?, 'chat')")
            ->execute([$user['id'], $ip_addr, $user_agent_str, '']);

        $_SESSION['user'] = [
            "id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "provider" => $user['provider'],
            "picture" => $user['picture'],
            "last_login" => date("Y-m-d H:i:s"),
            "role" => $user['role']
        ];

        $redirect_url = in_array($user['role'], ['admin','ceo','manager','sales','support','finance','secretary','developer']) ? 'admin/dashboard.php' : 'user/dashboard.php';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']],
            'redirect' => $redirect_url
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email/phone or password. Please try again.']);
    }
    exit;
}

// === Forgot Password from Chat ===
if ($action === 'forgot-password') {
    $identifier = trim($input['identifier'] ?? '');

    if (empty($identifier)) {
        echo json_encode(['success' => false, 'error' => 'Please enter your email address or phone number.']);
        exit;
    }

    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    $placeholders = ["email = ?"];
    $params = [$identifier];

    if (!$isEmail) {
        $digits = preg_replace('/[^0-9]/', '', $identifier);
        $variants = [$digits];
        if (strpos($digits, '234') === 0) {
            $variants[] = '0' . substr($digits, 3);
            $variants[] = substr($digits, 3);
        } elseif (strpos($digits, '0') === 0) {
            $variants[] = '234' . substr($digits, 1);
            $variants[] = substr($digits, 1);
        } else {
            $variants[] = '0' . $digits;
            $variants[] = '234' . $digits;
        }
        $variants = array_unique($variants);
        foreach ($variants as $v) {
            $placeholders[] = "phone = ?";
            $params[] = $v;
        }
    }

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE " . implode(' OR ', $placeholders) . " LIMIT 1");
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always show success message to prevent user enumeration
    $successMsg = "We've sent password reset instructions to your registered email if an account exists. Please check your inbox.";

    if ($user) {
        // Generate a reset token
        $resetToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        try {
            // Store the token (create table if needed)
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user (user_id)
            )");

            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$user['id'], $resetToken, $expires]);

            // Send email (best-effort)
            $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/reset-password.php?token=' . $resetToken;

            $subject = "WQS - Password Reset Request";
            $body = "Hello " . htmlspecialchars($user['name']) . ",\n\n";
            $body .= "We received a request to reset your password.\n\n";
            $body .= "Click the link below to reset your password:\n";
            $body .= $resetUrl . "\n\n";
            $body .= "This link expires in 1 hour.\n\n";
            $body .= "If you didn't request this, please ignore this email.\n\n";
            $body .= "Best regards,\nWise Quotient Soft Team";

            $headers = "From: noreply@wisequotientsoft.com\r\n";
            $headers .= "Reply-To: support@wisequotientsoft.com\r\n";

            // Try sending via PHP mail or SMTP if configured
            @mail($user['email'], $subject, $body, $headers);
        } catch (Exception $e) {
            // Fail silently — don't reveal errors
        }
    }

    echo json_encode(['success' => true, 'message' => $successMsg]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action.']);
