<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Rate Limit check
    $rate = check_reset_rate_limit($ip, $pdo);
    if (!$rate['allowed']) {
        $error = $rate['message'];
    } elseif (empty($identifier)) {
        $error = 'Email or phone number is required.';
    } else {
        // Detect if email or phone
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

        $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE " . implode(' OR ', $placeholders) . " LIMIT 1");
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Secure security practice: don't reveal if account exists or not
            $success = 'If the account exists, password reset instructions have been sent.';
        } else {
            $res = send_password_reset($user, $pdo);
            if ($res['success']) {
                if ($res['sms_used'] && $res['sms_sent']) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_identifier'] = $identifier;
                    header("Location: reset-password.php?otp_flow=1");
                    exit;
                } else {
                    $success = 'We have sent password reset instructions to your email.';
                }
            } else {
                $error = 'Unable to send password reset message. Please try again later or contact support if the issue persists.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WiseQuotient Soft - Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    :root {
      --primary-color: #002f6c;
      --accent-color: #ff7b00;
      --accent-color-dark: #e86000;
      --focus-shadow: rgba(0, 47, 108, 0.3);
    }

    body {
      background: linear-gradient(to bottom right, #021a40, #07284f);
      font-family: 'Segoe UI', sans-serif;
      height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }

    /* Cloud styles */
    .cloud {
      position: absolute;
      z-index: 0;
      pointer-events: none;
    }

    .cloud svg {
      fill: white;
      opacity: 0.15;
      filter: blur(1px);
    }

    .cloud1 { top: 5%; left: 10%; width: 200px; animation: float 60s linear infinite; }
    .cloud2 { top: 15%; right: 5%; width: 250px; animation: float 80s linear infinite reverse; }
    .cloud3 { bottom: 10%; left: 15%; width: 220px; animation: float 75s linear infinite; }
    .cloud4 { top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg); width: 280px; animation: float 90s linear infinite reverse; }
    .cloud5 { bottom: 5%; right: 10%; width: 240px; animation: float 70s linear infinite; }

    @keyframes float {
      0% { transform: translateX(0) scale(1); }
      50% { transform: translateX(20px) scale(1.02); }
      100% { transform: translateX(0) scale(1); }
    }

    .login-container {
      background: rgba(255, 255, 255, 0.9);
      border-radius: 16px;
      padding: 30px 25px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.25);
      color: #333;
      position: relative;
      z-index: 1;
      backdrop-filter: blur(6px);
    }

    .brand-logo {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      margin-bottom: 20px;
    }

    .brand-logo img {
      height: 60px;
      border-radius: 12px;
      padding: 8px;
    }

    h4 {
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--primary-color);
      margin: 20px 0;
      text-align: center;
    }

    .form-control {
      background-color: #ffffff;
      color: #333;
      border: 1px solid #ccc;
      border-radius: 10px;
      padding-left: 44px;
      font-size: 0.95rem;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.15rem var(--focus-shadow);
      outline: none;
    }

    .form-control::placeholder {
      color: #999;
    }

    .input-group-text {
      background-color: transparent;
      border: none;
      position: absolute;
      z-index: 10;
      top: 50%;
      left: 12px;
      transform: translateY(-50%);
      color: #999;
      font-size: 1rem;
    }

    .position-relative {
      position: relative;
    }

    .login-btn {
      background: linear-gradient(to right, var(--accent-color), #e74c3c);
      color: #fff;
      border: none;
      border-radius: 30px;
      font-weight: 600;
      padding: 10px;
      width: 100%;
      margin-top: 20px;
      transition: background 0.3s ease;
    }

    .login-btn:hover {
      background: linear-gradient(to right, var(--accent-color-dark), #c0392b);
    }

    .register-link {
      text-align: center;
      font-size: 0.9rem;
      color: #555;
      margin-top: 20px;
    }

    .register-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 500;
    }

    .register-link a:hover {
      text-decoration: underline;
    }

    @media screen and (max-width: 400px) {
      .login-container {
        padding: 25px;
        max-width: 90%;
      }
    }

    /* Spinner Overlay */
    .spinner-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(8px);
      z-index: 9999;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }

    .spinner-overlay.show {
      display: flex;
    }

    /* Dots Loader - Orange Brand Color */
    .dots-loader {
      display: inline-block;
      position: relative;
      width: 80px;
      height: 20px;
    }

    .dots-loader div {
      position: absolute;
      top: 8px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #f26522; /* WISE QUOTIENT SOFT orange */
      animation-timing-function: cubic-bezier(0, 1, 1, 0);
    }

    .dots-loader div:nth-child(1) {
      left: 8px;
      animation: dots1 0.6s infinite;
    }

    .dots-loader div:nth-child(2) {
      left: 8px;
      animation: dots2 0.6s infinite;
    }

    .dots-loader div:nth-child(3) {
      left: 32px;
      animation: dots2 0.6s infinite;
    }

    .dots-loader div:nth-child(4) {
      left: 56px;
      animation: dots3 0.6s infinite;
    }

    /* Animation Keyframes */
    @keyframes dots1 {
      0%   { transform: scale(0); }
      100% { transform: scale(1); }
    }

    @keyframes dots2 {
      0%   { transform: translateX(0); }
      100% { transform: translateX(24px); }
    }

    @keyframes dots3 {
      0%   { transform: scale(1); }
      100% { transform: scale(0); }
    }
  </style>
</head>
<body>

<!-- Spinner Overlay -->
<div class="spinner-overlay" id="spinnerOverlay">
  <div class="dots-loader">
    <div></div><div></div><div></div><div></div>
  </div>
  <p class="text-white fw-semibold mt-4">Processing, please wait...</p>
</div>

<!-- Home Button -->
<a href="index.php" class="btn btn-outline-light position-absolute top-0 start-0 m-3 m-md-4 fw-bold" style="border-radius: 30px; z-index: 10;">
  <i class="fas fa-home me-2"></i> Home
</a>

 <!-- SVG Clouds -->
  <div class="cloud cloud1">
    <svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg>
  </div>
  <div class="cloud cloud2">
    <svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg>
  </div>
  <div class="cloud cloud3">
    <svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg>
  </div>
  <div class="cloud cloud4">
    <svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg>
  </div>
  <div class="cloud cloud5">
    <svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg>
  </div>

  <!-- Forgot Password Box -->
  <div class="login-container">
    <div class="brand-logo">
      <img src="LOGO W.png" alt="WiseQuotient Logo">
    </div>

    <h4>Forgot Password</h4>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="border-radius:10px;font-size:.88rem;"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" style="border-radius:10px;font-size:.88rem;"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3 position-relative">
        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
        <input type="text" name="identifier" class="form-control ps-5" placeholder="Enter your email or phone" required>
      </div>

      <button type="submit" class="btn login-btn">Send Reset Link</button>

      <div class="register-link">
        Remembered your password? <a href="login.php">Login</a>
      </div>
    </form>
  </div>

  <script>
    document.querySelector('form').addEventListener('submit', function() {
      document.getElementById('spinnerOverlay').classList.add('show');
    });
  </script>
</body>
</html>
