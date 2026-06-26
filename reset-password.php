<?php
session_start();
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (empty($token)) {
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
} elseif (empty($token)) {
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
  </style>
</head>
<body>
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
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="mb-3 position-relative">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" name="password" class="form-control ps-5" placeholder="New password (min 6 chars)" required minlength="6">
      </div>
      <div class="mb-3 position-relative">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" name="confirm_password" class="form-control ps-5" placeholder="Confirm new password" required minlength="6">
      </div>
      <button type="submit" class="btn login-btn">Reset Password</button>
      <div class="register-link">Remembered your password? <a href="login.php">Login</a></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
