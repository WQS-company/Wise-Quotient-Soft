<?php
$path_to_root = "./";
$page_title = "My Account Profile";
require_once __DIR__ . '/includes/dashboard_header.php';

$user = $headerUser;
$user_id = $user['id'];

// Format last login
$formatted_login = '';
if (!empty($user['last_login'])) {
    $timestamp = strtotime($user['last_login']);
    if ($timestamp !== false) {
        $formatted_login = date('F j, Y \a\t h:i A', $timestamp);
    } else {
        $formatted_login = htmlspecialchars($user['last_login']);
    }
}
?>

<div class="card-theme max-width-md mx-auto my-4">
  <div class="card-theme-header">
    <h5 class="card-theme-title">
      <i class="fas fa-user-circle text-primary"></i> 
      Account Profile Details
    </h5>
    <span class="text-muted small">Wise Quotient Soft Account</span>
  </div>
  
  <div class="card-theme-body text-center">
    <div class="mb-4">
      <?php if (!empty($user['picture'])): ?>
        <img src="<?= htmlspecialchars($user['picture']) ?>" alt="Profile Picture" class="rounded-circle border border-2 border-primary p-1 shadow-sm" style="width: 110px; height: 110px; object-fit: cover;">
      <?php else: ?>
        <img src="<?= $path_to_root ?>images/default-avatar.png" alt="Profile Picture" class="rounded-circle border border-2 border-primary p-1 shadow-sm" style="width: 110px; height: 110px; object-fit: cover;">
      <?php endif; ?>
      <h3 class="mt-3 text-body fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h3>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fs-7">
        <?= htmlspecialchars(ucfirst($user['role'])) ?> Account
      </span>
    </div>
    
    <div class="text-start max-width-sm mx-auto my-4 bg-body-tertiary p-3 rounded-3 border">
      <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="text-body small text-uppercase">Email Address</strong> 
        <span class="text-muted small"><?= htmlspecialchars($user['email']) ?></span>
      </div>
      <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="text-body small text-uppercase">Phone Number</strong> 
        <span class="text-muted small"><?= htmlspecialchars($user['phone'] ?: 'Not Provided') ?></span>
      </div>
      <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="text-body small text-uppercase">Login Provider</strong> 
        <span class="text-muted small text-uppercase"><?= htmlspecialchars($user['provider']) ?></span>
      </div>
      <div class="py-2 d-flex justify-content-between align-items-center">
        <strong class="text-body small text-uppercase">Last Login Time</strong> 
        <span class="text-muted small"><?= $formatted_login ?: 'N/A' ?></span>
      </div>
    </div>

    <div class="d-flex justify-content-center gap-2 mt-4">
      <?php if (strtolower($user['role']) === 'admin'): ?>
        <a href="admin/dashboard.php" class="btn btn-theme px-4">
          <i class="fas fa-tachometer-alt me-2"></i> Go to Admin Area
        </a>
      <?php else: ?>
        <a href="user/dashboard.php" class="btn btn-theme px-4">
          <i class="fas fa-tachometer-alt me-2"></i> Go to Client Dashboard
        </a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-danger px-4">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/dashboard_footer.php';
?>
