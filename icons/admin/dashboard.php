<?php
$path_to_root = "../../";
$page_title = "Admin Dashboard";
require_once dirname(dirname(__DIR__)) . '/includes/dashboard_header.php';

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

// === Fetch Portfolio Projects Counts ===
$projectCount = 0;
$projResult = $db->query("SELECT COUNT(*) as total FROM projects");
if ($projResult && $row = $projResult->fetch_assoc()) {
    $projectCount = $row['total'];
}

// === Fetch Client Requests Counts ===
$requestTotal = 0;
$requestPending = 0;
$reqResult = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM client_requests");
if ($reqResult && $row = $reqResult->fetch_assoc()) {
    $requestTotal = $row['total'];
    $requestPending = $row['pending'];
}

// === Fetch Clients Counts ===
$usersCount = 0;
$usersResult = $db->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
if ($usersResult && $row = $usersResult->fetch_assoc()) {
    $usersCount = $row['total'];
}
?>

<!-- Welcome Banner -->
<div class="card-theme mb-4">
  <div class="card-theme-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <h2 class="h4 fw-bold text-body mb-1">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h2>
      <p class="mb-0 text-muted" style="font-size: 0.9rem;">
        Role: <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Administrator</span> 
        <?php if ($formatted_login): ?>
          <span class="mx-2 text-muted">|</span> Last Login: <?= $formatted_login ?>
        <?php endif; ?>
      </p>
    </div>
    <div>
      <a href="create-portfolio.php" class="btn btn-theme"><i class="fas fa-plus-circle me-2"></i> Add Portfolio Project</a>
    </div>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">
  <!-- Portfolio Projects -->
  <div class="col-12 col-sm-6 col-md-3">
    <a href="client_requests.php" class="text-decoration-none text-body">
      <div class="card-theme h-100 p-4 text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">
          <i class="fas fa-briefcase fa-lg"></i>
        </div>
        <h3 class="card-title-theme fw-semibold mb-1" style="font-size: 1.05rem;">Portfolio Projects</h3>
        <h2 class="display-6 fw-bold text-primary mb-2"><?= intval($projectCount) ?></h2>
        <p class="text-muted small mb-0">Live on website</p>
      </div>
    </a>
  </div>

  <!-- Client Requests -->
  <div class="col-12 col-sm-6 col-md-3">
    <a href="../../admin/client_requests.php" class="text-decoration-none text-body">
      <div class="card-theme h-100 p-4 text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning text-white rounded-circle mb-3" style="width: 50px; height: 50px;">
          <i class="fas fa-lightbulb fa-lg"></i>
        </div>
        <h3 class="card-title-theme fw-semibold mb-1" style="font-size: 1.05rem;">Clients Requests</h3>
        <h2 class="display-6 fw-bold text-warning mb-2"><?= intval($requestTotal) ?></h2>
        <p class="text-muted small mb-0"><?= intval($requestPending) ?> pending review</p>
      </div>
    </a>
  </div>

  <!-- Active Clients -->
  <div class="col-12 col-sm-6 col-md-3">
    <a href="#" class="text-decoration-none text-body">
      <div class="card-theme h-100 p-4 text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width: 50px; height: 50px;">
          <i class="fas fa-users fa-lg"></i>
        </div>
        <h3 class="card-title-theme fw-semibold mb-1" style="font-size: 1.05rem;">Active Clients</h3>
        <h2 class="display-6 fw-bold text-success mb-2"><?= intval($usersCount) ?></h2>
        <p class="text-muted small mb-0">Registered clients</p>
      </div>
    </a>
  </div>

  <!-- Support Tickets -->
  <div class="col-12 col-sm-6 col-md-3">
    <a href="#" class="text-decoration-none text-body">
      <div class="card-theme h-100 p-4 text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-info text-white rounded-circle mb-3" style="width: 50px; height: 50px;">
          <i class="fas fa-ticket-alt fa-lg"></i>
        </div>
        <h3 class="card-title-theme fw-semibold mb-1" style="font-size: 1.05rem;">Support Tickets</h3>
        <h2 class="display-6 fw-bold text-info mb-2">23</h2>
        <p class="text-muted small mb-0">5 open, 18 resolved</p>
      </div>
    </a>
  </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title"><i class="fas fa-chart-line text-primary"></i> Project Submissions</h5>
      </div>
      <div class="card-theme-body text-center text-muted py-5" style="background: repeating-linear-gradient(-45deg, #f8f9fa, #f8f9fa 10px, #e9ecef 10px, #e9ecef 20px);">
        Chart Loading...
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title"><i class="fas fa-receipt text-success"></i> Monthly Revenue</h5>
      </div>
      <div class="card-theme-body text-center text-muted py-5" style="background: repeating-linear-gradient(-45deg, #f8f9fa, #f8f9fa 10px, #e9ecef 10px, #e9ecef 20px);">
        Chart Loading...
      </div>
    </div>
  </div>
</div>

<!-- Support Tickets and Contracts -->
<div class="row g-4">
  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-headset text-primary"></i> Active Support Center</h5>
      </div>
      <div class="card-theme-body">
        <ul class="text-muted small ps-3 mb-3">
          <li class="mb-2">Ticket #2341 - Payment issue (Open)</li>
          <li class="mb-2">Ticket #2318 - Login bug (Resolved)</li>
        </ul>
        <a href="#support" class="btn btn-outline-primary btn-sm">Go to Support tickets</a>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card-theme h-100">
      <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-file-contract text-secondary"></i> Administrative Contracts</h5>
      </div>
      <div class="card-theme-body">
        <ul class="text-muted small ps-3 mb-3">
          <li class="mb-2">Contract #A9 - Active</li>
          <li class="mb-2">Quote #Q4 - Pending</li>
        </ul>
        <a href="#contracts" class="btn btn-outline-secondary btn-sm">View All Contracts</a>
      </div>
    </div>
  </div>
</div>

<?php
require_once dirname(dirname(__DIR__)) . '/includes/dashboard_footer.php';
?>
