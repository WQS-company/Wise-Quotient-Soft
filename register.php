<?php
$page_title = 'Register - Wise Quotient Soft';
$hide_header_footer = true;
require_once __DIR__ . '/includes/public_header.php';

$csrf_token = generate_csrf_token();
$ref_blocked = false;
$ref_block_msg = '';
$ref_code = $_GET['ref'] ?? '';
if (empty($ref_code) && isset($_SESSION['referred_by_code'])) {
    $ref_code = $_SESSION['referred_by_code'];
}
?>
<style>

    :root {
      --primary-color: #002f6c;
      --accent-color: #ff7b00;
      --accent-color-dark: #e86000;
      --focus-shadow: rgba(0, 47, 108, 0.3);
    }

    

    

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 16px;
      padding: 30px 25px;
      width: 100%;
      max-width: 540px;
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
      height:40px;
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

    .divider {
      text-align: center;
      margin: 20px 0;
      color: #888;
      position: relative;
      font-size: 0.9rem;
    }

    .divider::before,
    .divider::after {
      content: "";
      position: absolute;
      top: 50%;
      width: 40%;
      height: 1px;
      background: #ccc;
    }

    .divider::before { left: 0; }
    .divider::after { right: 0; }

    .social-login {
      background: #f5f5f5;
      color: #333;
      border-radius: 10px;
      padding: 10px;
      font-weight: 500;
      width: 100%;
      font-size: 0.9rem;
      border: 1px solid #ccc;
      transition: background 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .social-login img {
      height: 20px;
      margin-right: 8px;
    }

    .social-login:hover {
      background: #e9e9e9;
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

    @media screen and (max-width: 576px) {
      .row-cols-md-2 > * {
        flex: 0 0 100%;
        max-width: 100%;
      }
    }
    /* Spinner Overlay */
.spinner-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(5px);
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

<!-- ✅ Responsive Toast Notification Container -->
<div class="toast-container position-fixed p-3 top-0 start-50 translate-middle-x translate-md-none start-md-auto end-md-0" style="z-index: 11000;">
  <div id="errorToast" class="toast align-items-center text-bg-danger border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMessage">
        Error message goes here.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>


<!-- Spinner Overlay -->
<div class="spinner-overlay" id="spinnerOverlay">
  <div class="dots-loader">
    <div></div><div></div><div></div><div></div>
  </div>
  <p class="text-white fw-semibold mt-4">Submitting, please wait...</p>
</div>



  <!-- Register Box -->
<div class="login-container">
  <div class="brand-logo">
    <img src="LOGO W.png" alt="WiseQuotient Logo">
  </div>

  <h4>Register</h4>

  <form id="registerForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <?php if ($ref_blocked): ?>
    <div class="alert alert-danger py-2 px-3 small mb-3" style="border-radius:10px;">
      <i class="fas fa-shield-alt me-1"></i><?= htmlspecialchars($ref_block_msg) ?>
    </div>
    <?php endif; ?>
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <div class="col position-relative">
        <span class="input-group-text"><i class="fas fa-user"></i></span>
        <input type="text" class="form-control" placeholder="Full Name" name="name" required>
      </div>

      <div class="col position-relative">
        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
        <input type="email" class="form-control" placeholder="Email" name="email" required>
      </div>

      <div class="col position-relative">
        <span class="input-group-text"><i class="fas fa-phone"></i></span>
        <input type="tel" class="form-control" placeholder="Phone Number" name="phone" required>
      </div>

      <div class="col position-relative">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" class="form-control" placeholder="Password" id="register-password" name="password" required>
        <span class="position-absolute top-50 end-0 translate-middle-y me-3" style="cursor: pointer;" onclick="toggleRegisterPassword()">
          <i class="fa-solid fa-eye" id="toggleRegisterIcon"></i>
        </span>
      </div>

      <div class="col position-relative">
        <span class="input-group-text"><i class="fas fa-gift"></i></span>
        <input type="text" class="form-control" placeholder="Referral Code (Optional)" name="referred_by" id="referred_by" value="<?= htmlspecialchars($ref_code) ?>" readonly style="background-color: #f8fafc; cursor: not-allowed;" title="Referral code is set via the referral link">
        <?php if (!empty($ref_code)): ?>
          <div class="form-text text-success small"><i class="fas fa-check-circle me-1"></i>Referred by a partner</div>
        <?php endif; ?>
      </div>
    </div>

    <button type="submit" class="btn login-btn mt-3">Create Account</button>

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

    <div class="register-link mt-3">
      Already have an account? <a href="login.php">Login</a>
    </div>
  </form>
</div>
<!-- Bootstrap Bundle (for Toast functionality) -->

</div>
</div> <!-- /.main-content -->

<script>
  function toggleRegisterPassword() {
    const passwordInput = document.getElementById("register-password");
    const icon = document.getElementById("toggleRegisterIcon");
    if (passwordInput.type === "password") {
      passwordInput.type = "text";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
      passwordInput.type = "password";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    }
  }

  $(document).ready(function() {
    $('#registerForm').submit(function(e) {
      e.preventDefault();
      
      const toastEl = document.getElementById('errorToast');
      const toast = new bootstrap.Toast(toastEl);
      const toastMsg = document.getElementById('toastMessage');

      $('#spinnerOverlay').addClass('show');

      const formData = new FormData(this);
      const jsonData = {};
      formData.forEach((value, key) => {
        jsonData[key] = value;
      });

      $.ajax({
        url: 'auth.php',
        type: 'POST',
        data: JSON.stringify(jsonData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(res) {
          $('#spinnerOverlay').removeClass('show');

          if (res.success) {
            toastEl.classList.remove('text-bg-danger');
            toastEl.classList.add('text-bg-success');
            toastMsg.innerHTML = `<i class="fas fa-check-circle me-2"></i>${res.message}`;
            toast.show();

            setTimeout(() => {
              window.location.href = res.redirect || 'user/dashboard.php';
            }, 1000); 
          } else {
            toastEl.classList.remove('text-bg-success');
            toastEl.classList.add('text-bg-danger');
            toastMsg.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${res.error || 'Registration failed'}`;
            toast.show();
          }
        },
        error: function() {
          $('#spinnerOverlay').removeClass('show');
          toastEl.classList.remove('text-bg-success');
          toastEl.classList.add('text-bg-danger');
          toastMsg.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Network error. Please try again.';
          toast.show();
        }
      });
    });
  });
</script>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>

