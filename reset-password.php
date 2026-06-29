<?php
session_start();
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$otp_flow = (isset($_GET['otp_flow']) && $_GET['otp_flow'] == '1') || (isset($_POST['otp_flow']) && $_POST['otp_flow'] == '1');
$error = '';
$success = '';

if ($otp_flow && empty($_SESSION['reset_user_id'])) {
    header('Location: forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Enforce the same strong password requirements here for safety
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        if ($otp_flow) {
            $otp = trim($_POST['otp'] ?? '');
            $user_id = $_SESSION['reset_user_id'];
            if (empty($otp)) {
                $error = 'Please enter the verification code.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$user_id]);
                    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$reset) {
                        $error = 'Invalid or expired verification code.';
                    } elseif ($reset['attempts'] >= 5) {
                        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
                        $error = 'Too many failed verification attempts. This code is now invalidated. Please request a new code.';
                    } elseif ($reset['otp'] !== $otp) {
                        $new_attempts = $reset['attempts'] + 1;
                        if ($new_attempts >= 5) {
                            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
                            $error = 'Too many failed verification attempts. This code is now invalidated. Please request a new code.';
                        } else {
                            $pdo->prepare("UPDATE password_resets SET attempts = ? WHERE id = ?")->execute([$new_attempts, $reset['id']]);
                            $error = 'Invalid verification code. Attempts remaining: ' . (5 - $new_attempts);
                        }
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $user_id]);
                        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
                        
                        unset($_SESSION['reset_user_id']);
                        unset($_SESSION['reset_identifier']);
                        $success = 'Password reset successful! You can now log in.';
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred. Please try again.';
                }
            }
        } else {
            $token = $_POST['token'] ?? '';
            if (empty($token)) {
                $error = 'Invalid token.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$token]);
                    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$reset) {
                        $error = 'This reset link has expired or is invalid.';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $reset['user_id']]);
                        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
                        $success = 'Password reset successful! You can now log in.';
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred. Please try again.';
                }
            }
        }
    }
} elseif (!$otp_flow) {
    if (empty($token)) {
        header('Location: forgot-password.php');
        exit;
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$token]);
            if (!$stmt->fetch()) {
                $error = 'This reset link has expired or is invalid.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WiseQuotient Soft - Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    :root{--primary-color:#002f6c;--accent-color:#ff7b00;--accent-color-dark:#e86000;--focus-shadow:rgba(0,47,108,0.3)}
    body{background:linear-gradient(to bottom right,#021a40,#07284f);font-family:'Segoe UI',sans-serif;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
    .cloud{position:absolute;z-index:0;pointer-events:none}.cloud svg{fill:white;opacity:.15;filter:blur(1px)}
    .cloud1{top:5%;left:10%;width:200px;animation:float 60s linear infinite}
    .cloud2{top:15%;right:5%;width:250px;animation:float 80s linear infinite reverse}
    .cloud3{bottom:10%;left:15%;width:220px;animation:float 75s linear infinite}
    @keyframes float{0%{transform:translateX(0) scale(1)}50%{transform:translateX(20px) scale(1.02)}100%{transform:translateX(0) scale(1)}}
    .login-container{background:#fff;border-radius:16px;padding:35px;width:100%;max-width:400px;box-shadow:0 15px 35px rgba(0,0,0,.3);position:relative;z-index:5}
    .brand-logo{text-align:center;margin-bottom:20px}.brand-logo img{width:80px}
    h4{text-align:center;color:var(--primary-color);font-weight:700;margin-bottom:25px}
    .form-control{border-radius:30px;padding:12px 15px 12px 40px;border:1px solid #ddd;font-size:.95rem;transition:border-color .3s,box-shadow .3s}
    .form-control:focus{border-color:var(--primary-color);box-shadow:0 0 0 .15rem var(--focus-shadow);outline:none}
    .input-group-text{background-color:transparent;border:none;position:absolute;z-index:10;top:50%;left:12px;transform:translateY(-50%);color:#999;font-size:1rem}
    .position-relative{position:relative}
    .login-btn{background:linear-gradient(to right,var(--accent-color),#e74c3c);color:#fff;border:none;border-radius:30px;font-weight:600;padding:12px;width:100%;margin-top:20px;transition:background .3s}
    .login-btn:hover{background:linear-gradient(to right,var(--accent-color-dark),#c0392b)}
    .register-link{text-align:center;font-size:.9rem;color:#555;margin-top:20px}
    .register-link a{color:var(--primary-color);text-decoration:none;font-weight:500}
    .register-link a:hover{text-decoration:underline}
    .alert{border-radius:10px;font-size:.88rem}
    @media screen and (max-width:400px){.login-container{padding:25px;max-width:90%}}

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

<a href="index.php" class="btn btn-outline-light position-absolute top-0 start-0 m-3 m-md-4 fw-bold" style="border-radius:30px;z-index:10">
  <i class="fas fa-home me-2"></i> Home
</a>
<div class="cloud cloud1"><svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg></div>
<div class="cloud cloud2"><svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg></div>
<div class="cloud cloud3"><svg viewBox="0 0 64 64"><path d="M20,40c-6.627,0-12-5.373-12-12s5.373-12,12-12c1.654,0,3.217,0.337,4.656,0.942C26.515,10.929,33.796,6,42,6c10.493,0,19,8.507,19,19s-8.507,19-19,19H20z"/></svg></div>
<div class="login-container">
  <div class="brand-logo"><img src="LOGO W.png" alt="WiseQuotient Logo"></div>
  <h4>Reset Password</h4>
  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
    <div class="register-link"><a href="login.php">Go to Login</a></div>
  <?php else: ?>
    <form method="POST">
      <?php if ($otp_flow): ?>
        <input type="hidden" name="otp_flow" value="1">
        <div class="mb-2 text-center text-muted small">
          We've sent a 6-digit OTP code to your phone (<strong><?= htmlspecialchars($_SESSION['reset_identifier'] ?? '') ?></strong>)
        </div>
        <div class="mb-3 position-relative">
          <span class="input-group-text"><i class="fas fa-sms"></i></span>
          <input type="text" name="otp" class="form-control ps-5" placeholder="6-digit verification code" required maxlength="6" pattern="\d{6}">
        </div>
      <?php else: ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <?php endif; ?>

      <div class="mb-3 position-relative">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" name="password" id="password" class="form-control ps-5" placeholder="New strong password" required minlength="8" oninput="checkStrength()">
      </div>

      <!-- Password Strength Indicator -->
      <div class="mb-3">
        <div class="progress" style="height: 6px; border-radius: 3px; background-color: #e9ecef;">
          <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <span id="strength-text" class="small fw-bold text-muted" style="font-size: 0.75rem;">Password Strength</span>
          <span class="small text-muted" style="font-size: 0.72rem;">Min 8 chars, A-Z, a-z, 0-9, special</span>
        </div>
      </div>

      <div class="mb-3 position-relative">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" name="confirm_password" class="form-control ps-5" placeholder="Confirm new password" required minlength="8">
      </div>
      <button type="submit" class="btn login-btn">Reset Password</button>
      <div class="register-link">Remembered your password? <a href="login.php">Login</a></div>
    </form>
  <?php endif; ?>
</div>

<script>
function checkStrength() {
    const password = document.getElementById('password').value;
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    
    let score = 0;
    
    if (password.length >= 8) score += 20;
    if (/[A-Z]/.test(password)) score += 20;
    if (/[a-z]/.test(password)) score += 20;
    if (/[0-9]/.test(password)) score += 20;
    if (/[^A-Za-z0-9]/.test(password)) score += 20;
    
    let color = '';
    let text = '';
    let width = score + '%';
    
    if (score === 0) {
        color = 'bg-secondary';
        text = 'Too short';
        width = '0%';
    } else if (score <= 40) {
        color = 'bg-danger';
        text = 'Weak (add letters/numbers)';
    } else if (score <= 80) {
        color = 'bg-warning';
        text = 'Medium (add special characters)';
    } else {
        color = 'bg-success';
        text = 'Strong password';
    }
    
    strengthBar.className = 'progress-bar ' + color;
    strengthBar.style.width = width;
    strengthText.textContent = text;
    if (score === 100) {
        strengthText.className = 'small fw-bold text-success';
    } else if (score >= 60) {
        strengthText.className = 'small fw-bold text-warning';
    } else {
        strengthText.className = 'small fw-bold text-danger';
    }
}
</script>
<script>
  if (document.querySelector('form')) {
    document.querySelector('form').addEventListener('submit', function() {
      // Show loader only if the form validation passes
      if (this.checkValidity()) {
        document.getElementById('spinnerOverlay').classList.add('show');
      }
    });
  }
</script>
</div>
</body>
</html>
