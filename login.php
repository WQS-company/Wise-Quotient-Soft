<?php
$page_title = 'Login - Wise Quotient Soft';
$hide_header_footer = true;
require_once __DIR__ . '/includes/public_header.php';
?>
<style>

    :root {
      --primary-color: #002f6c;
      --accent-color: #ff7b00;
      --accent-color-dark: #e86000;
      --focus-shadow: rgba(0, 47, 108, 0.3);
    }

    * {
      box-sizing: border-box;
    }

    

    

    /* Home Button - Responsive */
    

    

    @media (min-width: 576px) {
      
    }

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 32px 24px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
      color: #333;
      position: relative;
      z-index: 1;
      backdrop-filter: blur(10px);
    }

    .brand-logo {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      margin-bottom: 24px;
    }

    .brand-logo img {
      height: 44px;
      border-radius: 12px;
      padding: 10px;
      background: rgba(255,255,255,0.5);
    }

    h4 {
      font-weight: 700;
      font-size: 1.6rem;
      color: var(--primary-color);
      margin: 16px 0 24px;
      text-align: center;
      letter-spacing: -0.3px;
    }

    .form-control {
      background-color: #ffffff;
      color: #333;
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      padding: 14px 16px 14px 48px;
      font-size: 0.95rem;
      height: auto;
      transition: all 0.2s ease;
    }

    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem var(--focus-shadow);
      outline: none;
      background: #fff;
    }

    .form-control::placeholder {
      color: #9e9e9e;
    }

    .input-group-text {
      background-color: transparent;
      border: none;
      position: absolute;
      z-index: 10;
      top: 50%;
      left: 16px;
      transform: translateY(-50%);
      color: #757575;
      font-size: 1.05rem;
      padding: 0;
    }

    .position-relative {
      position: relative;
    }

    .login-btn {
      background: linear-gradient(135deg, var(--accent-color), #e74c3c);
      color: #fff;
      border: none;
      border-radius: 30px;
      font-weight: 700;
      padding: 13px;
      width: 100%;
      margin-top: 24px;
      transition: all 0.3s ease;
      font-size: 1rem;
      letter-spacing: 0.3px;
      box-shadow: 0 4px 15px rgba(255, 123, 0, 0.35);
    }

    .login-btn:hover {
      background: linear-gradient(135deg, var(--accent-color-dark), #c0392b);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 123, 0, 0.45);
    }

    .divider {
      text-align: center;
      margin: 24px 0;
      color: #888;
      position: relative;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .divider::before,
    .divider::after {
      content: "";
      position: absolute;
      top: 50%;
      width: 38%;
      height: 1px;
      background: linear-gradient(to right, transparent, #e0e0e0, transparent);
    }

    .divider::before { left: 0; }
    .divider::after { right: 0; }

    .forgot-password {
      text-align: right;
      font-size: 0.875rem;
      color: #555;
      margin-top: 8px;
    }

    .forgot-password a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s ease;
    }

    .forgot-password a:hover {
      color: var(--accent-color);
      text-decoration: underline;
    }

  .social-login {
      background: #fff;
      color: #424242;
      border-radius: 12px;
      padding: 12px;
      font-weight: 600;
      width: 100%;
      font-size: 0.9rem;
      border: 1px solid #e0e0e0;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
    }

    .social-login:hover {
      background: #fafafa;
      border-color: #bdbdbd;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .register-link {
      text-align: center;
      font-size: 0.9rem;
      color: #555;
      margin-top: 24px;
    }

    .register-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s ease;
    }

    .register-link a:hover {
      color: var(--accent-color);
      text-decoration: underline;
    }

    /* ===== RESPONSIVE: Small phones (max 480px) ===== */
    @media (max-width: 480px) {
      
      
      .login-container {
        padding: 26px 18px;
        border-radius: 16px;
        max-width: 100%;
      }

      .brand-logo img {
        height: 36px;
        padding: 8px;
      }

      h4 {
        font-size: 1.35rem;
        margin: 12px 0 20px;
      }

      .form-control {
        font-size: 0.9rem;
        padding: 12px 14px 12px 42px;
        border-radius: 10px;
      }

      .input-group-text {
        left: 12px;
        font-size: 0.95rem;
      }

      .login-btn {
        padding: 12px;
        font-size: 0.95rem;
        margin-top: 20px;
      }

      .divider {
        font-size: 0.85rem;
        margin: 20px 0;
      }

      .divider::before,
      .divider::after {
        width: 35%;
      }

      .social-login {
        font-size: 0.85rem;
        padding: 10px;
      }

      .register-link {
        font-size: 0.85rem;
        margin-top: 20px;
      }

      .forgot-password {
        font-size: 0.8rem;
      }
    }

    /* ===== RESPONSIVE: Tiny phones (max 380px) ===== */
    @media (max-width: 380px) {
      
      
      .login-container {
        padding: 20px 14px;
        border-radius: 14px;
      }

      .brand-logo {
        margin-bottom: 16px;
      }

      .brand-logo img {
        height: 32px;
        padding: 6px;
      }

      h4 {
        font-size: 1.2rem;
        margin: 10px 0 16px;
      }

      .form-control {
        font-size: 0.85rem;
        padding: 11px 12px 11px 38px;
      }

      .input-group-text {
        left: 10px;
        font-size: 0.85rem;
      }

      .login-btn {
        padding: 11px;
        font-size: 0.9rem;
        margin-top: 18px;
        border-radius: 24px;
      }

      .social-login {
        font-size: 0.8rem;
        padding: 9px;
      }

      .divider {
        margin: 16px 0;
        font-size: 0.78rem;
      }

      .divider::before,
      .divider::after {
        width: 32%;
      }

      .register-link {
        font-size: 0.8rem;
        margin-top: 16px;
      }

      .forgot-password {
        font-size: 0.78rem;
      }

      /* Stack social buttons vertically on tiny screens */
      .row.g-2 > .col-6 {
        width: 100%;
      }

      .row.g-2 > .col-6 + .col-6 {
        margin-top: 10px;
      }

      .mb-3 {
        margin-bottom: 0.75rem !important;
      }

      .mb-2 {
        margin-bottom: 0.5rem !important;
      }
    }

    /* ===== RESPONSIVE: Extra tiny phones (max 320px) ===== */
    @media (max-width: 320px) {
      

      .login-container {
        padding: 16px 12px;
        border-radius: 12px;
      }

      .brand-logo {
        margin-bottom: 12px;
      }

      .brand-logo img {
        height: 28px;
        padding: 5px;
      }

      h4 {
        font-size: 1.1rem;
        margin: 8px 0 14px;
      }

      .form-control {
        font-size: 0.8rem;
        padding: 10px 10px 10px 34px;
        border-radius: 8px;
      }

      .input-group-text {
        left: 8px;
        font-size: 0.8rem;
      }

      .login-btn {
        padding: 10px;
        font-size: 0.85rem;
        margin-top: 16px;
      }

      .social-login {
        font-size: 0.75rem;
        padding: 8px;
      }

      .register-link {
        font-size: 0.75rem;
        margin-top: 14px;
      }

      .forgot-password {
        font-size: 0.72rem;
      }
    }

    /* ===== RESPONSIVE: Foldable / very short screens ===== */
    @media (max-height: 620px) {
      
      
      .login-container {
        padding-top: 18px;
        padding-bottom: 18px;
      }

      .brand-logo {
        margin-bottom: 12px;
      }

      .brand-logo img {
        height: 30px;
      }

      h4 {
        margin: 10px 0;
      }

      .mb-3 {
        margin-bottom: 0.5rem !important;
      }

      .mb-2 {
        margin-bottom: 0.35rem !important;
      }

      .login-btn {
        margin-top: 14px;
        padding: 10px;
      }

      .divider {
        margin: 14px 0;
      }

      .register-link {
        margin-top: 14px;
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

  
    .login-wrapper {
      background: linear-gradient(to bottom right, #021a40, #07284f);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 12px;
    }
    
</style>

<div class="login-wrapper position-relative">

<!-- Home Button -->
<a href="index.php" class="btn btn-outline-light position-absolute top-0 start-0 m-3 m-md-4" style="border-radius: 20px; backdrop-filter: blur(5px); z-index: 100;">
  <i class="fas fa-home me-2"></i>Back to Home
</a>

<!-- Spinner Overlay -->
<div class="spinner-overlay" id="spinnerOverlay">
  <div class="dots-loader">
    <div></div><div></div><div></div><div></div>
  </div>
  <p class="text-white fw-semibold mt-4">Verifying, please wait...</p>
</div>



<!-- Login Container -->
<div class="login-container">
  <div class="brand-logo">
    <img src="LOGO W.png" alt="WiseQuotient Logo">
  </div>

  <h4>Login</h4>

  <div id="alertBox"></div>

  <form id="loginForm">
    <div class="mb-3 position-relative">
      <span class="input-group-text"><i class="fas fa-envelope"></i></span>
      <input type="text" class="form-control ps-5" name="identifier" placeholder="Email or phone" required>
    </div>

    <div class="mb-2 position-relative">
      <span class="input-group-text"><i class="fas fa-lock"></i></span>
      <input type="password" class="form-control ps-5 pe-5" name="password" id="password-input" placeholder="Password" required>
      <span class="position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;" onclick="togglePassword()">
        <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
      </span>
    </div>

    <div class="forgot-password">
      <a href="forgot-password.php">Forgot password?</a>
    </div>

    <button type="submit" class="btn login-btn">Login</button>

    <div class="divider">or</div>

    <div class="row g-2">
      <div class="col-6">
        <a href="https://accounts.google.com/o/oauth2/v2/auth?<?= http_build_query([
          'client_id' => '906854785856-9c5jd8cvg8fv2u60ds22ovuuvgc0t9g2.apps.googleusercontent.com',
          'redirect_uri' => 'https://wisequotientsoft.com/auth.php?provider=google',
          'response_type' => 'code',
          'scope' => 'openid email profile',
          'access_type' => 'online',
          'prompt' => 'select_account'
        ]) ?>" class="btn social-login w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="fab fa-google text-danger"></i> Google
        </a>
      </div>
      <div class="col-6">
        <a href="https://github.com/login/oauth/authorize?<?= http_build_query([
          'client_id' => 'Ov23liy1CTotWhCTuOHa',
          'redirect_uri' => 'https://wisequotientsoft.com/auth.php?provider=github',
          'scope' => 'read:user user:email'
        ]) ?>" class="btn social-login w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="fab fa-github text-body"></i> GitHub
        </a>
      </div>
    </div>


    <div class="register-link">
      Don't have an account? <a href="register.php">Register</a>
    </div>
  </form>
</div>

</div>
</div> <!-- /.main-content -->
<script>
  function togglePassword() {
    const passwordInput = document.getElementById("password-input");
    const icon = document.getElementById("togglePasswordIcon");
    if (passwordInput.type === "password") {
      passwordInput.type = "text";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
      passwordInput.type = "password";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    }
  }

  $('#loginForm').submit(function(e) {
    e.preventDefault();
    $('#alertBox').html('');
    $('#spinnerOverlay').addClass('show');

    $.ajax({
      url: 'auth.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(res) {
        $('#spinnerOverlay').removeClass('show');

        if (res.success) {
          $('#alertBox').html(`<div class="alert alert-success">${res.message}</div>`);

          setTimeout(() => {
            // If backend sends role in response, use it to redirect
            if (res.role === 'admin') {
              window.location.href = 'admin/dashboard.php';
            } else if (res.role === 'user') {
              window.location.href = 'user/dashboard.php';
            } else {
              // fallback redirect
              window.location.href = res.redirect || 'user/dashboard.php';
            }
          }, 1000); 
        } else {
          $('#alertBox').html(`<div class="alert alert-danger">${res.error || 'Login failed'}</div>`);
        }
      },
      error: function() {
        $('#spinnerOverlay').removeClass('show');
        $('#alertBox').html('<div class="alert alert-danger">Network error. Please try again.</div>');
      }
    });
  });
</script>


<?php require_once __DIR__ . '/includes/public_footer.php'; ?>

