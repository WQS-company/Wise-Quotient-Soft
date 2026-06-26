<?php
// Start output buffering to prevent headers already sent warnings on redirects
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default path to root if not provided
if (!isset($path_to_root)) {
    $path_to_root = './';
}
// Ensure path_to_root ends with a forward slash
$path_to_root = rtrim($path_to_root, '/\\') . '/';

// Compute web-relative path for JavaScript fetch URLs
$_headerScript = $_SERVER['SCRIPT_NAME'] ?? '/';
if (strpos($_headerScript, '/admin/') !== false) {
    $_headerWebPath = '../';
} elseif (strpos($_headerScript, '/user/') !== false) {
    $_headerWebPath = '../';
} else {
    $_headerWebPath = './';
}

// Redirect to login if user session is missing
if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Establish DB connection using central config if not already loaded
require_once rtrim($path_to_root, '/\\') . DIRECTORY_SEPARATOR . 'config.php';

// Fetch the most up-to-date user details from database
$userIdForHeader = $_SESSION['user']['id'];
$headerUserStmt = $pdo->prepare("SELECT id, name, email, phone, provider, picture, last_login, role, theme, session_timeout FROM users WHERE id = ?");
$headerUserStmt->execute([$userIdForHeader]);
$headerUser = $headerUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$headerUser) {
    // Session user doesn't exist in DB anymore
    session_destroy();
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Session Timeout Auto-Logout Logic
$timeout_minutes = (int)($headerUser['session_timeout'] ?? 0);
if ($timeout_minutes > 0) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ($timeout_minutes * 60)) {
        session_unset();
        session_destroy();
        header("Location: " . $path_to_root . "login.php?timeout=1");
        exit;
    }
}
$_SESSION['last_activity'] = time();

$userTheme = $headerUser['theme'] ?? 'light';

// Sync session role in case it was modified
$_SESSION['user']['role'] = $headerUser['role'];
$_SESSION['user']['name'] = $headerUser['name'];
$_SESSION['user']['picture'] = $headerUser['picture'];

$current_page = basename($_SERVER['SCRIPT_NAME']);
$user_role = strtolower($headerUser['role']);

// ===== CENTRALIZED ROUTING PROTECTION =====
// Map page filenames to required permission keys.
// If a user accesses a page they lack permission for, redirect to profile.
$page_permission_map = [
    // Admin pages
    'create-portfolio.php'    => 'admin_portfolio',
    'client_requests.php'     => 'admin_requests',
    'contact_messages.php'    => 'admin_requests',
    'agent_requests.php'      => 'admin_requests',
    'developer_requests.php'  => 'admin_requests',
    'manage_developers.php'   => 'admin_dev_mgmt',
    'services.php'            => 'admin_services',
    'analytics.php'           => 'admin_analytics',
    'firebase-analytics.php'  => 'admin_analytics',
    'remote-config.php'       => 'admin_analytics',
    'ab-testing.php'          => 'admin_analytics',
    'api-health.php'          => 'admin_analytics',
    'bot_chats.php'           => 'admin_bot_chats',
    'manage_users.php'        => 'admin_users',
    'manage_team.php'         => 'admin_users',
    'invoice_management.php'  => 'admin_invoices',
    'support_center.php'      => 'admin_support',
    'payout_approvals.php'    => 'admin_payouts',
    'freelance_admin.php'     => 'admin_freelance',
    'reports.php'             => 'admin_settings',
    'broadcast.php'           => 'admin_settings',
    'footer_settings.php'     => 'admin_settings',
    'manage_blog.php'         => 'admin_settings',
    'blog-categories.php'     => 'admin_settings',
    'blog-tags.php'           => 'admin_settings',
    'payroll.php'             => 'admin_settings',
    'payroll-employees.php'   => 'admin_settings',
    'payroll-partners.php'    => 'admin_settings',
    'payroll-settings.php'    => 'admin_settings',
    'payroll-periods.php'     => 'admin_settings',
    'payroll-revenue.php'     => 'admin_settings',
    'payroll-payouts.php'     => 'admin_settings',
    'payroll-reports.php'     => 'admin_settings',
    'payroll-audit.php'       => 'admin_settings',
    // Scholarship pages
    'scholarship_dashboard.php'   => 'admin_scholarships',
    'scholarship_create.php'      => 'admin_scholarships',
    'scholarships.php'            => 'admin_scholarships',
    'scholarship_edit.php'        => 'admin_scholarships',
    'scholarship_applications.php'=> 'admin_scholarships',
    'scholarship_shortlisted.php' => 'admin_scholarships',
    'scholarship_approved.php'    => 'admin_scholarships',
    'scholarship_disqualified.php'=> 'admin_scholarships',
    'scholarship_interviews.php'  => 'admin_scholarships',
    'scholarship_categories.php'  => 'admin_scholarships',
    'scholarship_sponsors.php'    => 'admin_scholarships',
    'scholarship_payments.php'    => 'admin_scholarships',
    'scholarship_certificates.php'=> 'admin_scholarships',
    'scholarship_reports.php'     => 'admin_scholarships',
    'scholarship_settings.php'    => 'admin_scholarships',
    // Developer pages
    'developer_hub.php'       => 'dev_hub',
    'freelance_jobs.php'      => 'freelance_board',
    'freelance_bid.php'       => 'freelance_board',
    'dev_portfolio.php'       => 'dev_hub',
    // Shared client / agent / dev pages
    'client-project.php'      => 'projects',
    'my_requests.php'         => 'projects',
    'client-request.php'      => 'client_requests',
    'client-invoices.php'     => 'invoices_payments',
    'client-support.php'      => 'support_center',
    'documents.php'           => 'documents_vault',
    'agent_hub.php'           => 'partner_hub',
    'referral_portal.php'     => 'partner_hub',
];

// Only enforce on pages that have a mapped permission
if (isset($page_permission_map[$current_page])) {
    $required_perm = $page_permission_map[$current_page];
    if (!has_feature_access($required_perm)) {
        $_SESSION['access_denied_msg'] = 'You do not have permission to access that page. Contact an admin to update your feature access.';
        header("Location: " . $path_to_root . "user/profile.php");
        exit;
    }
}
// ===== END ROUTING PROTECTION =====
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($userTheme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?> - Wise Quotient Soft</title>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Premium Custom Styling -->
  <link href="<?= $path_to_root ?>includes/theme.css?v=3" rel="stylesheet">
  <style>
    /* Force bottom nav hidden on desktop with inline style */
    @media (min-width: 576px) {
      .bottom-nav {
        display: none !important;
      }
    }
  </style>
  <!-- Firebase Client-Side Scripts -->
  <?php
  $fbLoader = (isset($path_to_root) ? $path_to_root : './') . 'includes/firebase_js.php';
  if (file_exists($fbLoader)) {
      include $fbLoader;
  }
  ?>
</head>
<body>

<div class="wrapper">
  
  <!-- Shared Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex justify-content-center align-items-center py-3">
      <img src="<?= $path_to_root ?>LOGO W.png" alt="Wise Quotient Soft" class="sidebar-logo" style="max-height: 48px; object-fit: contain;">
    </div>
    
    <div class="px-3 mb-3">
      <div class="input-group">
        <span class="input-group-text">
          <i class="fas fa-search text-muted"></i>
        </span>
        <input type="text" id="sidebar-search" class="form-control" placeholder="Search navigation...">
      </div>
    </div>
    
    <nav class="sidebar-nav" id="sidebar-nav">
      <?php if (in_array($user_role, ['admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary'])): ?>
        <!-- Admin Navigation Links -->
        <a href="<?= $path_to_root ?>admin/dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <?php if (has_feature_access('admin_portfolio')): ?>
        <div class="nav-item-dropdown">
          <a class="nav-link d-flex align-items-center justify-content-between <?= (in_array($current_page, ['create-portfolio.php', 'portfolio-list.php', 'edit-portfolio.php'])) ? 'active' : '' ?>" 
             data-bs-toggle="collapse" 
             href="#portfolioCollapse" 
             role="button" 
             aria-expanded="<?= (in_array($current_page, ['create-portfolio.php', 'portfolio-list.php', 'edit-portfolio.php'])) ? 'true' : 'false' ?>" 
             aria-controls="portfolioCollapse">
            <span><i class="fas fa-briefcase"></i> Portfolio</span>
            <i class="fas fa-chevron-down dropdown-arrow" style="font-size: 0.8rem;"></i>
          </a>
          <div class="collapse <?= (in_array($current_page, ['create-portfolio.php', 'portfolio-list.php', 'edit-portfolio.php'])) ? 'show' : '' ?>" id="portfolioCollapse">
            <div class="d-flex flex-column ps-3 mt-1">
              <a href="<?= $path_to_root ?>admin/portfolio-list.php" class="nav-link <?= ($current_page === 'portfolio-list.php' || $current_page === 'edit-portfolio.php') ? 'active' : '' ?>" style="font-size: 0.88rem; padding-left: 1.25rem !important; margin-bottom: 0.1rem;">
                <i class="fas fa-list-ul" style="font-size:0.95rem;"></i> Portfolio List
              </a>
              <a href="<?= $path_to_root ?>admin/create-portfolio.php" class="nav-link <?= ($current_page === 'create-portfolio.php') ? 'active' : '' ?>" style="font-size: 0.88rem; padding-left: 1.25rem !important; margin-bottom: 0.1rem;">
                <i class="fas fa-plus-circle" style="font-size:0.95rem;"></i> Add Portfolio
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php if (has_feature_access('admin_requests')): ?>
        <a href="<?= $path_to_root ?>admin/client_requests.php" class="nav-link <?= ($current_page === 'client_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-lightbulb"></i> Clients Requests
        </a>
        <a href="<?= $path_to_root ?>admin/contact_messages.php" class="nav-link <?= ($current_page === 'contact_messages.php') ? 'active' : '' ?>">
          <i class="fas fa-envelope-open-text"></i> Contact Messages
        </a>
        <a href="<?= $path_to_root ?>admin/agent_requests.php" class="nav-link <?= ($current_page === 'agent_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-handshake"></i> Partner Requests
        </a>
        <a href="<?= $path_to_root ?>admin/developer_requests.php" class="nav-link <?= ($current_page === 'developer_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-code"></i> Dev Applications
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_dev_mgmt')): ?>
        <a href="<?= $path_to_root ?>admin/manage_developers.php" class="nav-link <?= ($current_page === 'manage_developers.php') ? 'active' : '' ?>">
          <i class="fas fa-users-cog"></i> Manage Developers
        </a>
        <a href="<?= $path_to_root ?>user/dev_hub.php" class="nav-link <?= (in_array($current_page, ['dev_hub.php', 'dev_network.php', 'dev_hub_discussions.php', 'dev_hub_discussion_view.php', 'dev_hub_snippets.php'])) ? 'active' : '' ?>">
          <i class="fas fa-globe"></i> Developers Hub
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_services')): ?>
        <a href="<?= $path_to_root ?>admin/services.php" class="nav-link <?= ($current_page === 'services.php') ? 'active' : '' ?>">
          <i class="fas fa-cogs"></i> Manage Services
        </a>
        <a href="<?= $path_to_root ?>admin/manage_ads.php" class="nav-link <?= ($current_page === 'manage_ads.php') ? 'active' : '' ?>">
          <i class="fas fa-bullhorn"></i> Manage Ads
        </a>
        <a href="<?= $path_to_root ?>admin/free_packages.php" class="nav-link <?= ($current_page === 'free_packages.php') ? 'active' : '' ?>">
          <i class="fas fa-gift text-pink animate-pulse"></i> Free Packages
        </a>
        <a href="<?= $path_to_root ?>admin/manage_survey.php" class="nav-link <?= ($current_page === 'manage_survey.php' || $current_page === 'survey_responses.php') ? 'active' : '' ?>">
          <i class="fas fa-poll"></i> Survey Manager
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_analytics')): ?>
        <a href="<?= $path_to_root ?>admin/analytics.php" class="nav-link <?= ($current_page === 'analytics.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-line"></i> Visitor Analytics
        </a>
        <a href="<?= $path_to_root ?>admin/firebase-analytics.php" class="nav-link <?= ($current_page === 'firebase-analytics.php') ? 'active' : '' ?>">
          <i class="fas fa-fire text-orange animate-pulse"></i> Firebase Analytics & FCM
        </a>
        <a href="<?= $path_to_root ?>admin/remote-config.php" class="nav-link <?= ($current_page === 'remote-config.php') ? 'active' : '' ?>">
          <i class="fas fa-sliders-h text-purple"></i> Remote Config
        </a>
        <a href="<?= $path_to_root ?>admin/ab-testing.php" class="nav-link <?= ($current_page === 'ab-testing.php') ? 'active' : '' ?>">
          <i class="fas fa-flask text-pink"></i> A/B Testing
        </a>
        <a href="<?= $path_to_root ?>admin/api-health.php" class="nav-link <?= ($current_page === 'api-health.php') ? 'active' : '' ?>">
          <i class="fas fa-heartbeat text-success"></i> API Health
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_bot_chats')): ?>
        <a href="<?= $path_to_root ?>admin/bot_chats.php" class="nav-link <?= ($current_page === 'bot_chats.php') ? 'active' : '' ?>">
          <i class="fas fa-comments"></i> Bot Chats
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_users')): ?>
        <a href="<?= $path_to_root ?>admin/manage_users.php" class="nav-link <?= ($current_page === 'manage_users.php') ? 'active' : '' ?>">
          <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="<?= $path_to_root ?>admin/manage_team.php" class="nav-link <?= ($current_page === 'manage_team.php') ? 'active' : '' ?>">
          <i class="fas fa-users-cog"></i> Team Management
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_invoices')): ?>
        <a href="<?= $path_to_root ?>admin/invoice_management.php" class="nav-link <?= ($current_page === 'invoice_management.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> Invoices
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_support')): ?>
        <a href="<?= $path_to_root ?>admin/support_center.php" class="nav-link <?= ($current_page === 'support_center.php') ? 'active' : '' ?>">
          <i class="fas fa-headset"></i> Support Center
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_support')): ?>
        <a href="<?= $path_to_root ?>admin/popup_manager.php" class="nav-link <?= ($current_page === 'popup_manager.php') ? 'active' : '' ?>">
          <i class="fas fa-rocket"></i> Popup Manager
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_payouts')): ?>
        <a href="<?= $path_to_root ?>admin/payout_approvals.php" class="nav-link <?= ($current_page === 'payout_approvals.php') ? 'active' : '' ?>">
          <i class="fas fa-money-check-alt"></i> Payout Approvals
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('admin_freelance')): ?>
        <a href="<?= $path_to_root ?>admin/freelance_admin.php" class="nav-link <?= ($current_page === 'freelance_admin.php') ? 'active' : '' ?>">
          <i class="fas fa-gavel"></i> Freelance Admin
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>admin/contract_hub.php" class="nav-link <?= ($current_page === 'contract_hub.php' || $current_page === 'create_contract.php' || $current_page === 'view_contract.php') ? 'active' : '' ?>">
          <i class="fas fa-file-contract"></i> Contract Hub
        </a>

        <!-- Scholarship Management Section -->
        <div class="nav-link" style="cursor:default; font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; padding:0.5rem 1rem 0.25rem !important;">
          <i class="fas fa-graduation-cap me-1" style="font-size:0.7rem;"></i> Scholarship Management
        </div>
        <a href="<?= $path_to_root ?>admin/scholarship_dashboard.php" class="nav-link <?= ($current_page === 'scholarship_dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i> Scholarship Dashboard
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_create.php" class="nav-link <?= ($current_page === 'scholarship_create.php') ? 'active' : '' ?>">
          <i class="fas fa-plus-circle"></i> Create Scholarship
        </a>
        <a href="<?= $path_to_root ?>admin/scholarships.php" class="nav-link <?= ($current_page === 'scholarships.php') ? 'active' : '' ?>">
          <i class="fas fa-list-ul"></i> All Scholarships
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_applications.php" class="nav-link <?= ($current_page === 'scholarship_applications.php') ? 'active' : '' ?>">
          <i class="fas fa-file-alt"></i> Applications
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_shortlisted.php" class="nav-link <?= ($current_page === 'scholarship_shortlisted.php') ? 'active' : '' ?>">
          <i class="fas fa-star"></i> Shortlisted
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_approved.php" class="nav-link <?= ($current_page === 'scholarship_approved.php') ? 'active' : '' ?>">
          <i class="fas fa-check-circle"></i> Approved
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_disqualified.php" class="nav-link <?= ($current_page === 'scholarship_disqualified.php') ? 'active' : '' ?>">
          <i class="fas fa-times-circle"></i> Disqualified
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_interviews.php" class="nav-link <?= ($current_page === 'scholarship_interviews.php') ? 'active' : '' ?>">
          <i class="fas fa-video"></i> Interviews
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_categories.php" class="nav-link <?= ($current_page === 'scholarship_categories.php') ? 'active' : '' ?>">
          <i class="fas fa-tags"></i> Categories
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_sponsors.php" class="nav-link <?= ($current_page === 'scholarship_sponsors.php') ? 'active' : '' ?>">
          <i class="fas fa-handshake"></i> Sponsors
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_payments.php" class="nav-link <?= ($current_page === 'scholarship_payments.php') ? 'active' : '' ?>">
          <i class="fas fa-money-check-alt"></i> Payments
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_certificates.php" class="nav-link <?= ($current_page === 'scholarship_certificates.php') ? 'active' : '' ?>">
          <i class="fas fa-certificate"></i> Certificates
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_reports.php" class="nav-link <?= ($current_page === 'scholarship_reports.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-pie"></i> Reports
        </a>
        <a href="<?= $path_to_root ?>admin/scholarship_settings.php" class="nav-link <?= ($current_page === 'scholarship_settings.php') ? 'active' : '' ?>">
          <i class="fas fa-cog"></i> Settings
        </a>

        <!-- Payroll & Finance Section -->
        <div class="nav-link" style="cursor:default; font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; padding:0.5rem 1rem 0.25rem !important;">
          <i class="fas fa-coins me-1" style="font-size:0.7rem;"></i> Payroll & Finance
        </div>
        <a href="<?= $path_to_root ?>admin/payroll.php" class="nav-link <?= ($current_page === 'payroll.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Payroll Dashboard
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-employees.php" class="nav-link <?= ($current_page === 'payroll-employees.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Employee Management
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-partners.php" class="nav-link <?= ($current_page === 'payroll-partners.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Partner Management
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-periods.php" class="nav-link <?= ($current_page === 'payroll-periods.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Payroll Periods
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-revenue.php" class="nav-link <?= ($current_page === 'payroll-revenue.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Revenue Sharing
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-payouts.php" class="nav-link <?= ($current_page === 'payroll-payouts.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Payouts
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-reports.php" class="nav-link <?= ($current_page === 'payroll-reports.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Reports
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-audit.php" class="nav-link <?= ($current_page === 'payroll-audit.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Audit Log
        </a>
        <a href="<?= $path_to_root ?>admin/payroll-settings.php" class="nav-link <?= ($current_page === 'payroll-settings.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Payroll Settings
        </a>
        
        <?php if (has_feature_access('admin_settings')): ?>
        <a href="<?= $path_to_root ?>admin/reports.php" class="nav-link <?= ($current_page === 'reports.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="<?= $path_to_root ?>admin/broadcast.php" class="nav-link <?= ($current_page === 'broadcast.php') ? 'active' : '' ?>">
          <i class="fas fa-bullhorn"></i> Broadcast
        </a>
        <a href="<?= $path_to_root ?>admin/daily_notifications.php" class="nav-link <?= ($current_page === 'daily_notifications.php') ? 'active' : '' ?>">
          <i class="fas fa-robot"></i> AI Daily Notifs
        </a>
        <a href="<?= $path_to_root ?>admin/agent_setup.php" class="nav-link <?= ($current_page === 'agent_setup.php') ? 'active' : '' ?>">
          <i class="fas fa-microchip"></i> Agent Setup
        </a>
        <?php if (has_feature_access('admin_voice_providers')): ?>
        <a href="<?= $path_to_root ?>admin/voice_providers.php" class="nav-link <?= ($current_page === 'voice_providers.php' || $current_page === 'voice_analytics.php') ? 'active' : '' ?>">
          <i class="fas fa-headset"></i> AI Voice Providers
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>admin/footer_settings.php" class="nav-link <?= ($current_page === 'footer_settings.php') ? 'active' : '' ?>">
          <i class="fas fa-sliders-h"></i> Footer Settings
        </a>
        <div class="nav-link" style="cursor:default; font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; padding:0.5rem 1rem 0.25rem !important;">
          <i class="far fa-newspaper me-1" style="font-size:0.7rem;"></i> Blog Management
        </div>
        <a href="<?= $path_to_root ?>admin/manage_blog.php" class="nav-link <?= ($current_page === 'manage_blog.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> All Articles
        </a>
        <a href="<?= $path_to_root ?>admin/blog-categories.php" class="nav-link <?= ($current_page === 'blog-categories.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Categories
        </a>
        <a href="<?= $path_to_root ?>admin/blog-tags.php" class="nav-link <?= ($current_page === 'blog-tags.php') ? 'active' : '' ?>" style="padding-left:1.75rem !important;">
          <i class="fas fa-chevron-right" style="font-size:0.55rem;"></i> Tags
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/profile.php" class="nav-link <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i> My Profile
        </a>
      <?php elseif ($user_role === 'developer'): ?>
        <!-- Developer Navigation Links -->
        <?php if (has_feature_access('dev_hub')): ?>
        <a href="<?= $path_to_root ?>user/developer_hub.php" class="nav-link <?= ($current_page === 'developer_hub.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i> Dev Dashboard
        </a>
        <a href="<?= $path_to_root ?>user/dev_hub.php" class="nav-link <?= (in_array($current_page, ['dev_hub.php', 'dev_network.php', 'dev_hub_discussions.php', 'dev_hub_discussion_view.php', 'dev_hub_snippets.php'])) ? 'active' : '' ?>">
          <i class="fas fa-globe"></i> Developers Hub
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('projects')): ?>
        <a href="<?= $path_to_root ?>user/client-project.php" class="nav-link <?= ($current_page === 'client-project.php') ? 'active' : '' ?>">
          <i class="fas fa-briefcase"></i> My Projects
        </a>
        <a href="<?= $path_to_root ?>user/my_requests.php" class="nav-link <?= ($current_page === 'my_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-file-signature"></i> Project Proposals
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/user-agreement-form.php" class="nav-link <?= ($current_page === 'user-agreement-form.php') ? 'active' : '' ?>">
          <i class="fas fa-file-contract"></i> Contracts Form
        </a>
        <a href="<?= $path_to_root ?>user/payout_requests.php" class="nav-link <?= ($current_page === 'payout_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-money-check-alt"></i> Payout Requests
        </a>
        <?php if (has_feature_access('support_center')): ?>
        <a href="<?= $path_to_root ?>user/client-support.php" class="nav-link <?= ($current_page === 'client-support.php') ? 'active' : '' ?>">
          <i class="fas fa-headset"></i> Support Center
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('documents_vault')): ?>
        <a href="<?= $path_to_root ?>user/documents.php" class="nav-link <?= ($current_page === 'documents.php') ? 'active' : '' ?>">
          <i class="fas fa-folder-open"></i> Documents Vault
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('dev_hub')): ?>
        <a href="<?= $path_to_root ?>user/dev_portfolio.php" class="nav-link <?= ($current_page === 'dev_portfolio.php') ? 'active' : '' ?>">
          <i class="fas fa-laptop-code"></i> Portfolio & Skills
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('freelance_board')): ?>
        <a href="<?= $path_to_root ?>user/freelance_jobs.php" class="nav-link <?= ($current_page === 'freelance_jobs.php') ? 'active' : '' ?>">
          <i class="fas fa-search"></i> Job Board
        </a>
        <a href="<?= $path_to_root ?>user/freelance_bid.php" class="nav-link <?= ($current_page === 'freelance_bid.php') ? 'active' : '' ?>">
          <i class="fas fa-gavel"></i> My Bids
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/profile.php" class="nav-link <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i> My Profile
        </a>
      <?php else: ?>

        <!-- User/Client Navigation Links -->
        <a href="<?= $path_to_root ?>user/dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <?php if (has_feature_access('projects')): ?>
        <a href="<?= $path_to_root ?>user/client-project.php" class="nav-link <?= ($current_page === 'client-project.php') ? 'active' : '' ?>">
          <i class="fas fa-briefcase"></i> My Projects
        </a>
        <a href="<?= $path_to_root ?>user/my_requests.php" class="nav-link <?= ($current_page === 'my_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-file-signature"></i> Project Proposals
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('client_requests')): ?>
        <a href="<?= $path_to_root ?>user/client-request.php" class="nav-link <?= ($current_page === 'client-request.php') ? 'active' : '' ?>">
          <i class="fas fa-plus-circle"></i> Request Project
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('invoices_payments')): ?>
        <a href="<?= $path_to_root ?>user/client-invoices.php" class="nav-link <?= ($current_page === 'client-invoices.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> Invoices & Payments
        </a>
        <?php endif; ?>
        <?php if (has_feature_access('support_center')): ?>
        <a href="<?= $path_to_root ?>user/client-support.php" class="nav-link <?= ($current_page === 'client-support.php') ? 'active' : '' ?>">
          <i class="fas fa-headset"></i> Support Center
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/book_meeting.php" class="nav-link <?= ($current_page === 'book_meeting.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-alt"></i> Book Meeting
        </a>
        <?php if (has_feature_access('documents_vault')): ?>
        <a href="<?= $path_to_root ?>user/documents.php" class="nav-link <?= ($current_page === 'documents.php') ? 'active' : '' ?>">
          <i class="fas fa-folder-open"></i> Documents Vault
        </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/user-agreement-form.php" class="nav-link <?= ($current_page === 'user-agreement-form.php') ? 'active' : '' ?>">
          <i class="fas fa-file-contract"></i> Contracts Form
        </a>
        <?php if ($user_role === 'agent' && has_feature_access('partner_hub')): ?>
          <a href="<?= $path_to_root ?>user/agent_hub.php" class="nav-link <?= ($current_page === 'agent_hub.php') ? 'active' : '' ?>">
            <i class="fas fa-handshake"></i> Agent Hub
          </a>
          <a href="<?= $path_to_root ?>user/payout_requests.php" class="nav-link <?= ($current_page === 'payout_requests.php') ? 'active' : '' ?>">
            <i class="fas fa-money-check-alt"></i> Payout Requests
          </a>
        <?php elseif ($user_role !== 'agent'): ?>
          <a href="<?= $path_to_root ?>user/upgrade_partner.php" class="nav-link <?= ($current_page === 'upgrade_partner.php') ? 'active' : '' ?>">
            <i class="fas fa-user-plus"></i> Become a Partner
          </a>
        <?php endif; ?>
        <a href="<?= $path_to_root ?>user/upgrade_developer.php" class="nav-link <?= ($current_page === 'upgrade_developer.php') ? 'active' : '' ?>">
          <i class="fas fa-code"></i> Become a Developer
        </a>
        <a href="<?= $path_to_root ?>user/profile.php" class="nav-link <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i> My Profile
        </a>
      <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
      <a href="<?= $path_to_root ?>logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Sidebar Backdrop for mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar();"></div>

  <!-- Premium Ad & Notification System -->
  <?php if (file_exists($path_to_root . 'includes/ad_placer.php')) include $path_to_root . 'includes/ad_placer.php'; ?>
  <?php if (file_exists($path_to_root . 'includes/ad_system.php')) include $path_to_root . 'includes/ad_system.php'; ?>

  <!-- Onboarding Survey Modal -->
  <?php
  // Only show on non-admin pages and only for regular users (not admins testing)
  $isAdminPage = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
  if (!$isAdminPage):
  ?>
  <div class="modal fade" id="onboardingSurveyModal" data-bs-backdrop="static" data-bs-keyboard="true" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-xl rounded-4">
        <!-- Header with gradient -->
        <div class="modal-header border-0" style="background: linear-gradient(135deg, #0A2D5E 0%, #1e3a5f 50%, #0f172a 100%); color: #fff; padding: 1.75rem 2rem;">
          <div class="w-100">
            <div class="d-flex align-items-start justify-content-between">
              <div class="d-flex align-items-center gap-3 mb-1">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255,255,255,0.12); flex-shrink: 0;">
                  <i class="fas fa-magic fs-5" style="color: #fbbf24;"></i>
                </div>
                <div>
                  <h4 class="fw-bold mb-0" style="font-size: 1.25rem;">Help Us Serve You Better</h4>
                  <p class="mb-0" style="opacity:0.75;font-size:0.85rem;">Personalize your experience in just a few steps</p>
                </div>
              </div>
              <button type="button" class="btn p-0 d-flex align-items-center justify-content-center flex-shrink-0" data-bs-dismiss="modal" aria-label="Close" style="width:34px;height:34px;background:rgba(255,255,255,0.2);border-radius:50%;border:1px solid rgba(255,255,255,0.35);color:#fff;font-size:16px;"><i class="fas fa-times"></i></button>
            </div>
            <!-- Step Progress Indicator -->
            <div class="mt-3" id="surveyProgressWrap" style="display:none;">
              <div class="d-flex align-items-center justify-content-between position-relative" id="surveyStepDots">
                <!-- dots injected by JS -->
              </div>
              <div class="progress mt-2" style="height: 4px; background: rgba(255,255,255,0.15); border-radius: 2px;">
                <div class="progress-bar" id="surveyProgressBar" style="width:0%; background: linear-gradient(90deg, #fbbf24, #f59e0b); border-radius: 2px; transition: width 0.4s ease;"></div>
              </div>
            </div>
          </div>
        </div>
        <!-- Body -->
        <div class="modal-body p-0" id="surveyBody">
          <div class="text-center py-5" style="background: #f8fafc;">
            <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted small">Preparing your survey...</p>
          </div>
        </div>
        <!-- Footer -->
        <div class="modal-footer border-0 px-4 py-3 d-flex justify-content-between" style="background: #f8fafc;">
          <div>
            <small class="text-muted"><i class="fas fa-lock me-1"></i>Your information is kept private &amp; secure</small>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="dismissSurvey()" style="font-size:0.82rem;"><i class="fas fa-clock me-1"></i>Not now</button>
          </div>
          <button class="btn fw-bold px-5 d-none" id="surveySubmitBtn" onclick="submitSurvey()" style="background: linear-gradient(135deg, #0A2D5E, #1e3a5f); color: #fff; border-radius: 50px; padding: 0.6rem 2rem; border: none; transition: all 0.3s;">
            <i class="fas fa-check-circle me-2"></i>Save & Continue
          </button>
        </div>
      </div>
    </div>
  </div>

  <style>
  .survey-section-title {
    font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800;
    color: #0A2D5E; margin-top: 1.75rem; margin-bottom: 1rem;
    padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;
    display: flex; align-items: center; gap: 0.5rem;
  }
  .survey-section-title i { font-size: 0.85rem; color: #3b82f6; }
  .survey-question {
    margin-bottom: 1.25rem; padding: 1rem 1.25rem;
    background: #fff; border-radius: 14px;
    border: 1.5px solid #eef2f6;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  }
  .survey-question:hover { border-color: #d1d9e6; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
  .survey-question:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.08); }
  .survey-question label {
    font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; display: block;
    color: #1e293b;
  }
  .survey-question .required-star { color: #ef4444; margin-left: 2px; }
  .survey-question .form-control, .survey-question .form-select {
    border-radius: 10px; border: 1.5px solid #e2e8f0;
    padding: 0.6rem 1rem; font-size: 0.88rem;
    background: #fafbfc; transition: all 0.2s;
  }
  .survey-question .form-control:focus, .survey-question .form-select:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
    background: #fff;
  }
  .survey-step { display: none; opacity: 0; transform: translateX(20px); transition: all 0.35s ease; }
  .survey-step.active { display: block; opacity: 1; transform: translateX(0); }
  .survey-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eef2f6; }
  .survey-profile-card {
    background: linear-gradient(135deg, #f0f4ff, #e8f0fe);
    border-radius: 16px; padding: 1.25rem 1.5rem;
    border: 1.5px solid #d0ddf5;
    display: flex; align-items: center; gap: 1rem;
    margin-bottom: 1.5rem;
  }
  .survey-profile-card .avatar { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #0A2D5E, #3b82f6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem; font-weight: 700; flex-shrink: 0; box-shadow: 0 4px 12px rgba(10,45,94,0.2); }
  .survey-profile-card .info { flex: 1; min-width: 0; }
  .survey-profile-card .info .name { font-weight: 700; font-size: 1rem; color: #0A2D5E; }
  .survey-profile-card .info .detail { font-size: 0.82rem; color: #4a5568; }
  .survey-profile-card .badge-auto { background: #dbeafe; color: #1d4ed8; font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 50px; white-space: nowrap; }
  .survey-step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.5); cursor: default; transition: all 0.3s; position: relative; z-index: 2; }
  .survey-step-dot.active { background: #fbbf24; color: #0A2D5E; box-shadow: 0 0 0 3px rgba(251,191,36,0.3); }
  .survey-step-dot.done { background: #34d399; color: #fff; }
  .survey-step-label { font-size: 0.65rem; text-align: center; color: rgba(255,255,255,0.5); font-weight: 500; margin-top: 4px; white-space: nowrap; }
  .survey-step-dot-wrap { display: flex; flex-direction: column; align-items: center; flex: 1; }
  .form-check-input:checked { background-color: #0A2D5E; border-color: #0A2D5E; }
  .form-check-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
  </style>

  <script>
  let surveyQuestions = [];
  let surveyStep = 0;
  let surveyTotalSteps = 0;
  let surveyUserInfo = null;

  document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= $_headerWebPath ?>api/survey.php?action=status')
      .then(r => r.json())
      .then(data => {
        if (!data.completed) {
          loadSurveyQuestions();
        }
      })
      .catch(() => {});
  });

  function loadSurveyQuestions() {
    fetch('<?= $_headerWebPath ?>api/survey.php?action=questions')
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.questions.length) return;
        surveyQuestions = data.questions;
        surveyUserInfo = data.user_info || null;
        renderSurvey();
        const modal = new bootstrap.Modal(document.getElementById('onboardingSurveyModal'));
        modal.show();
      })
      .catch(() => {});
  }

  function buildStepDots(total) {
    const names = ['', 'Professional', 'Project', 'Preferences'];
    let html = '';
    for (let i = 0; i < total; i++) {
      const label = names[i + 1] || 'Step';
      html += '<div class="survey-step-dot-wrap">';
      html += '<div class="survey-step-dot' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' + (i + 1) + '</div>';
      html += '<div class="survey-step-label">' + label + '</div>';
      html += '</div>';
    }
    return html;
  }

  function updateStepDots(idx) {
    const dots = document.querySelectorAll('.survey-step-dot');
    dots.forEach((dot, i) => {
      dot.classList.remove('active', 'done');
      if (i < idx) dot.classList.add('done');
      else if (i === idx) dot.classList.add('active');
    });
    const pct = ((idx + 1) / surveyTotalSteps) * 100;
    const bar = document.getElementById('surveyProgressBar');
    if (bar) bar.style.width = pct + '%';
  }

  function renderSurvey() {
    const sections = {};
    surveyQuestions.forEach(q => {
      if (!sections[q.section]) sections[q.section] = [];
      sections[q.section].push(q);
    });
    const sectionNames = { personal: 'Personal Information', professional: 'Professional Profile', project: 'Project Preferences', general: 'General Information' };
    const sectionIcons = { personal: 'fa-user', professional: 'fa-briefcase', project: 'fa-rocket', general: 'fa-info-circle' };
    const sectionKeys = Object.keys(sections);
    surveyTotalSteps = sectionKeys.length;

    // Build progress dots
    document.getElementById('surveyProgressWrap').style.display = 'block';
    document.getElementById('surveyStepDots').innerHTML = buildStepDots(surveyTotalSteps);

    // Profile card (auto-filled from session)
    const name = surveyUserInfo ? surveyUserInfo.name || '' : '';
    const email = surveyUserInfo ? surveyUserInfo.email || '' : '';
    const phone = surveyUserInfo ? surveyUserInfo.phone || '' : '';
    const initial = name ? name.charAt(0).toUpperCase() : 'U';

    let profileCard = '';
    if (name || email) {
      profileCard = '<div class="survey-profile-card">';
      profileCard += '<div class="avatar">' + htmlspecialchars(initial) + '</div>';
      profileCard += '<div class="info">';
      if (name) profileCard += '<div class="name">' + htmlspecialchars(name) + '</div>';
      if (email) profileCard += '<div class="detail"><i class="far fa-envelope me-1" style="width:14px;"></i>' + htmlspecialchars(email) + '</div>';
      if (phone) profileCard += '<div class="detail"><i class="fas fa-phone me-1" style="width:14px;"></i>' + htmlspecialchars(phone) + '</div>';
      profileCard += '</div>';
      profileCard += '<span class="badge-auto"><i class="fas fa-check-circle me-1"></i>Auto-filled</span>';
      profileCard += '</div>';
    }

    let html = '<div class="survey-steps-container p-4">';
    html += profileCard;
    sectionKeys.forEach((sec, idx) => {
      html += '<div class="survey-step' + (idx === 0 ? ' active' : '') + '" data-step="' + idx + '">';
      html += '<div class="survey-section-title"><i class="fas ' + (sectionIcons[sec] || 'fa-circle') + '"></i>' + (sectionNames[sec] || sec) + '</div>';
      sections[sec].forEach(q => {
        const isReq = q.is_required ? '<span class="required-star">*</span>' : '';
        const placeholder = q.placeholder ? 'placeholder="' + htmlspecialchars(q.placeholder) + '"' : '';
        html += '<div class="survey-question">';
        html += '<label for="q_' + q.id + '">' + htmlspecialchars(q.question) + isReq + '</label>';

        if (q.type === 'textarea') {
          html += '<textarea id="q_' + q.id + '" class="form-control" rows="3" ' + placeholder + (q.is_required ? ' required' : '') + '></textarea>';
        } else if (q.type === 'select' && q.options) {
          html += '<select id="q_' + q.id + '" class="form-select"' + (q.is_required ? ' required' : '') + '><option value="">' + (placeholder || 'Select an option...') + '</option>';
          q.options.forEach(o => { html += '<option value="' + htmlspecialchars(o) + '">' + htmlspecialchars(o) + '</option>'; });
          html += '</select>';
        } else if ((q.type === 'radio' || q.type === 'checkbox') && q.options) {
          html += '<div class="d-flex flex-wrap gap-2 mt-1">';
          q.options.forEach(o => {
            const safeId = o.replace(/[^a-z0-9]/gi, '_');
            html += '<div class="form-check"><input class="form-check-input" type="' + q.type + '" name="q_' + q.id + '" id="q_' + q.id + '_' + safeId + '" value="' + htmlspecialchars(o) + '"' + (q.is_required ? ' required' : '') + '><label class="form-check-label" for="q_' + q.id + '_' + safeId + '">' + htmlspecialchars(o) + '</label></div>';
          });
          html += '</div>';
        } else {
          html += '<input type="' + q.type + '" id="q_' + q.id + '" class="form-control" ' + placeholder + (q.is_required ? ' required' : '') + '>';
        }
        html += '</div>';
      });

      // Navigation
      html += '<div class="survey-nav">';
      if (idx > 0) html += '<button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="surveyPrevStep()" style="font-size:0.88rem;"><i class="fas fa-arrow-left me-2"></i>Previous</button>';
      else html += '<div></div>';
      html += '<span class="small text-muted fw-medium">Step ' + (idx + 1) + ' of ' + sectionKeys.length + '</span>';
      if (idx < sectionKeys.length - 1) html += '<button type="button" class="btn px-4 fw-bold" onclick="surveyNextStep()" style="background:linear-gradient(135deg,#0A2D5E,#1e3a5f);color:#fff;border-radius:50px;border:none;font-size:0.88rem;padding:0.5rem 1.5rem;">Next<i class="fas fa-arrow-right ms-2"></i></button>';
      else html += '<button type="button" class="btn px-4 fw-bold" onclick="submitSurvey()" style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;border-radius:50px;border:none;font-size:0.88rem;padding:0.5rem 1.5rem;"><i class="fas fa-check-circle me-2"></i>Finish</button>';
      html += '</div>';

      html += '</div>';
    });
    html += '</div>';
    document.getElementById('surveyBody').innerHTML = html;

    // Show/hide footer submit button
    const footerBtn = document.getElementById('surveySubmitBtn');
    if (footerBtn) {
      footerBtn.classList.toggle('d-none', surveyTotalSteps > 1);
    }
  }

  function surveyNextStep() {
    const current = document.querySelector('.survey-step.active');
    if (!current) return;
    const required = current.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
      if (!el.value || (el.type === 'radio' && !current.querySelector('input[name="' + el.name + '"]:checked'))) {
        el.classList.add('is-invalid');
        valid = false;
      } else {
        el.classList.remove('is-invalid');
      }
    });
    if (!valid) {
      Swal.fire({ title: 'Required Fields', text: 'Please fill in all required fields before continuing.', icon: 'warning', confirmButtonColor: '#0A2D5E' });
      return;
    }
    current.classList.remove('active');
    const next = current.nextElementSibling;
    if (next && next.classList.contains('survey-step')) {
      next.classList.add('active');
      surveyStep++;
      updateStepDots(surveyStep);
    }
  }

  function surveyPrevStep() {
    const current = document.querySelector('.survey-step.active');
    if (!current) return;
    current.classList.remove('active');
    const prev = current.previousElementSibling;
    if (prev && prev.classList.contains('survey-step')) {
      prev.classList.add('active');
      surveyStep--;
      updateStepDots(surveyStep);
    }
  }

  function htmlspecialchars(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function dismissSurvey() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('onboardingSurveyModal'));
    if (modal) modal.hide();
    // Remember dismissal for 7 days
    try { localStorage.setItem('survey_dismissed', Date.now().toString()); } catch(e) {}
  }

  // Override modal show to check dismissal cooldown (defer until Bootstrap loads)
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const origModalShow = bootstrap.Modal.prototype.show;
      bootstrap.Modal.prototype.show = function() {
        if (this._element && this._element.id === 'onboardingSurveyModal') {
          const dismissed = parseInt(localStorage.getItem('survey_dismissed') || '0');
          const cooldown = 7 * 24 * 60 * 60 * 1000;
          if (dismissed && (Date.now() - dismissed < cooldown)) {
            return;
          }
        }
        return origModalShow.apply(this, arguments);
      };
    }
  });

  function submitSurvey() {
    const responses = [];
    for (const q of surveyQuestions) {
      const el = document.getElementById('q_' + q.id);
      if (!el) continue;
      let val = '';
      if (q.type === 'radio') {
        const checked = document.querySelector('input[name="q_' + q.id + '"]:checked');
        val = checked ? checked.value : '';
      } else if (q.type === 'checkbox') {
        const checked = document.querySelectorAll('input[name="q_' + q.id + '"]:checked');
        val = Array.from(checked).map(c => c.value).join(', ');
      } else {
        val = el.value.trim();
      }
      if (q.is_required && !val) {
        el.classList.add('is-invalid');
        el.focus();
        Swal.fire({ title: 'Required Fields', text: 'Please fill in all required fields.', icon: 'warning', confirmButtonColor: '#0A2D5E' });
        return;
      }
      responses.push({ question_id: q.id, response: val });
    }

    const btns = document.querySelectorAll('[onclick="submitSurvey()"]');
    btns.forEach(b => { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...'; });

    fetch('<?= $_headerWebPath ?>api/survey.php?action=submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ responses: responses })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('onboardingSurveyModal'));
        if (modal) modal.hide();
        Swal.fire({
          title: 'Welcome Aboard!',
          text: 'Thank you for sharing your preferences. We\'ll tailor your experience.',
          icon: 'success',
          timer: 2500,
          timerProgressBar: true,
          confirmButtonColor: '#0A2D5E',
          showConfirmButton: false
        });
      } else {
        Swal.fire({ title: 'Error', text: data.error || 'Could not save.', icon: 'error', confirmButtonColor: '#E15501' });
        btns.forEach(b => { b.disabled = false; b.innerHTML = '<i class="fas fa-check-circle me-2"></i>Save & Continue'; });
      }
    })
    .catch(() => {
      Swal.fire({ title: 'Error', text: 'Connection error.', icon: 'error', confirmButtonColor: '#E15501' });
      btns.forEach(b => { b.disabled = false; b.innerHTML = '<i class="fas fa-check-circle me-2"></i>Save & Continue'; });
    });
  }
  </script>
  <?php endif; ?>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper">
    
    <!-- Unified Top Navbar -->
    <header class="top-navbar">
      <div class="d-flex align-items-center gap-3">
        <!-- Toggle button for mobile screens -->
        <button class="mobile-hamburger" id="sidebarToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title-header"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></h1>
      </div>
      <div class="nav-controls">
        <button class="navbar-icon-btn" id="themeToggleBtn" aria-label="Toggle Theme">
          <i class="fas fa-<?= $userTheme === 'dark' ? 'sun' : 'moon' ?>"></i>
        </button>
        <div class="dropdown">
          <button class="navbar-icon-btn position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
            <i class="far fa-bell"></i>
            <span id="notif-badge" class="badge bg-danger rounded-pill d-none">0</span>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0 rounded-3 dropdown-menu-notif" aria-labelledby="notificationsDropdown">
            <div class="notif-header">
              <div>
                <span class="notif-header-title">Notifications</span>
                <span id="notif-unread-count" class="ms-1" style="font-size:0.72rem;color:#9ca3af;"></span>
              </div>
              <button id="mark-all-read-btn" title="Mark all as read"><i class="fas fa-check-double me-1"></i>Mark all read</button>
            </div>
            <div class="notif-list-container" id="notif-list">
              <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div></div>
            </div>
            <div class="notif-footer">
              <a href="<?= $path_to_root ?>user/all-notifications.php" class="notif-view-all">View All Notifications <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
          </div>
        </div>
        
        <div class="dropdown">
          <a href="#" class="user-profile-summary d-flex align-items-center gap-2 text-decoration-none" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if (!empty($headerUser['picture'])): ?>
              <img src="<?= htmlspecialchars($headerUser['picture']) ?>" alt="User Avatar" class="user-profile-img">
            <?php else: ?>
              <img src="<?= $path_to_root ?>images/default-avatar.png" alt="User Avatar" class="user-profile-img">
            <?php endif; ?>
            <div class="user-profile-info">
              <span class="user-profile-name"><?= htmlspecialchars($headerUser['name']) ?></span>
              <span class="user-profile-role">
                <?php 
                  if ($user_role === 'admin') echo 'Administrator';
                  elseif ($user_role === 'manager') echo 'Project Manager';
                  elseif ($user_role === 'sales') echo 'Sales Executive';
                  elseif ($user_role === 'support') echo 'Support Staff';
                  elseif ($user_role === 'finance') echo 'Financial Officer';
                  elseif ($user_role === 'ceo') echo 'Chief Executive Officer';
                  elseif ($user_role === 'secretary') echo 'Secretary';
                  elseif ($user_role === 'agent') echo 'Referral Partner';
                  elseif ($user_role === 'developer') echo 'WQS Developer';
                  else echo 'Client';
                ?>
              </span>
            </div>
            <i class="fas fa-chevron-down text-muted" style="font-size: 0.75rem;"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3" aria-labelledby="profileDropdown">
            <li><h6 class="dropdown-header">Signed in as <br><strong><?= htmlspecialchars($headerUser['email']) ?></strong></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2" href="<?= $path_to_root ?><?= in_array($user_role, ['admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary']) ? 'admin/dashboard.php' : 'user/dashboard.php' ?>"><i class="fas fa-tachometer-alt me-2 text-muted"></i> Dashboard</a></li>
            <li><a class="dropdown-item py-2" href="<?= $path_to_root ?>user/profile.php"><i class="fas fa-user me-2 text-muted"></i> My Profile</a></li>
            <li><a class="dropdown-item py-2" href="<?= $path_to_root ?>user/profile.php#tab-security"><i class="fas fa-cog me-2 text-muted"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="<?= $path_to_root ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </header>

    <style>
    /* --- Mobile Header & Navigation --- */
    @media (max-width: 575.98px) {
      .page-title-header {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 140px;
        font-size: 1rem !important;
      }
      .top-navbar {
        height: 56px !important;
        padding: 0 0.6rem !important;
      }
      .content-body {
        padding-top: 0.75rem !important;
      }
      .dropdown-menu {
        max-width: calc(100vw - 1rem) !important;
      }
      /* Profile dropdown full width on tiny */
      .user-profile-summary .user-profile-info {
        display: none !important;
      }
    }
    @media (max-width: 359.98px) {
      .page-title-header {
        max-width: 100px;
        font-size: 0.9rem !important;
      }
      .nav-controls {
        gap: 0.4rem !important;
      }
    }
    /* --- Notification System Dropdown Styles --- */
    .dropdown-menu-notif {
      width: 380px;
      max-height: 520px;
      overflow: visible;
      border: 1px solid var(--color-border) !important;
      background-color: var(--color-card-bg) !important;
      z-index: 1100;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }
    .dropdown-menu-notif.show {
      display: flex !important;
      flex-direction: column;
    }
    .notif-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.875rem 1.25rem;
      border-bottom: 1px solid var(--color-border);
      background-color: #fafbfc;
    }
    .notif-header-title {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--color-text-body);
      margin: 0;
    }
    #mark-all-read-btn {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--color-accent);
      text-decoration: none;
      background: none;
      border: none;
      padding: 4px 8px;
      cursor: pointer;
      border-radius: 6px;
      transition: all 0.15s ease;
    }
    #mark-all-read-btn:hover {
      color: var(--color-accent-hover);
      background: rgba(225,85,1,0.06);
    }
    .notif-list-container {
      overflow-y: auto;
      max-height: 380px;
      flex-grow: 1;
    }
    .notif-list-container::-webkit-scrollbar { width: 5px; }
    .notif-list-container::-webkit-scrollbar-track { background: transparent; }
    .notif-list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .notif-item {
      display: flex;
      gap: 0.75rem;
      padding: 0.75rem 1.1rem;
      border-bottom: 1px solid var(--color-border);
      text-decoration: none;
      color: var(--color-text) !important;
      transition: all 0.15s ease;
      position: relative;
      border-left: 3px solid transparent;
      background-color: var(--color-card-bg);
      text-align: left;
      cursor: pointer;
    }
    .notif-item:hover {
      background-color: var(--color-bg) !important;
    }
    .notif-item:active {
      transform: scale(0.985);
    }
    .notif-item.unread {
      background: linear-gradient(90deg, rgba(99,102,241,0.04) 0%, rgba(255,255,255,0) 100%) !important;
      border-left-color: #6366f1;
    }
    .notif-item.unread .notif-title-text { font-weight: 800; }
    .notif-icon-wrap {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 0.9rem;
      transition: all 0.2s ease;
    }
    .notif-content-wrap {
      flex-grow: 1;
      min-width: 0;
    }
    .notif-title-text {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--color-text-body);
      margin-bottom: 0.1rem;
      line-height: 1.3;
    }
    .notif-msg-text {
      font-size: 0.78rem;
      color: var(--color-text-light);
      line-height: 1.35;
      margin-bottom: 0.2rem;
      word-wrap: break-word;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .notif-time-text {
      font-size: 0.68rem;
      color: var(--color-text-light);
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-weight: 500;
    }
    .notif-time-text i { font-size: 0.6rem; }
    .notif-read-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #6366f1;
      position: absolute;
      top: 1.1rem; right: 1rem;
      flex-shrink: 0;
    }
    .notif-footer {
      border-top: 1px solid var(--color-border);
      padding: 0.6rem;
      text-align: center;
      background: #fafbfc;
    }
    .notif-view-all {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--color-accent);
      text-decoration: none;
      padding: 4px 12px;
      border-radius: 6px;
      transition: all 0.15s;
    }
    .notif-view-all:hover {
      background: rgba(225,85,1,0.06);
      color: var(--color-accent-hover);
    }
    .notif-empty-state {
      padding: 3rem 1.5rem;
      text-align: center;
      color: var(--color-text-light);
    }
    .notif-empty-state i {
      font-size: 2rem;
      color: var(--color-border);
      margin-bottom: 0.5rem;
      display: block;
    }
    .notif-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      color: var(--color-text-light);
      font-size: 0.85rem;
      gap: 0.5rem;
    }
    @media (max-width: 575.98px) {
      .dropdown-menu-notif {
        width: calc(100vw - 1.5rem);
        max-height: 70vh;
        position: fixed;
        top: auto;
        right: 0.75rem;
        margin-top: 0.5rem !important;
      }
      .notif-icon-wrap { width: 32px; height: 32px; font-size: 0.8rem; border-radius: 8px; }
      .notif-item { padding: 0.65rem 0.9rem; }
      .notif-title-text { font-size: 0.78rem; }
      .notif-msg-text { font-size: 0.73rem; }
      .notif-header { padding: 0.7rem 0.9rem; }
    }
    @media (max-width: 359.98px) {
      .dropdown-menu-notif {
        width: calc(100vw - 1rem);
        right: 0.5rem;
      }
      .notif-icon-wrap { width: 28px; height: 28px; font-size: 0.75rem; }
      .notif-item { gap: 0.5rem; padding: 0.55rem 0.75rem; }
      .notif-header-title { font-size: 0.82rem; }
    }
    </style>

    <script>
    // Global toggle function for inline onclick
    function toggleSidebar() {
        console.log("toggleSidebar() called!");
        const sidebar = document.getElementById("sidebar");
        const sidebarBackdrop = document.getElementById("sidebarBackdrop");
        if (sidebar && sidebarBackdrop) {
            console.log("Toggling sidebar, current show class:", sidebar.classList.contains("show"));
            sidebar.classList.toggle("show");
            sidebarBackdrop.classList.toggle("show");
            console.log("After toggle, show class:", sidebar.classList.contains("show"));
        } else {
            console.error("toggleSidebar(): Sidebar or backdrop element not found!");
        }
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("DOM fully loaded!");
        
        const notifBadge = document.getElementById("notif-badge");
        const notifList = document.getElementById("notif-list");
        const markAllReadBtn = document.getElementById("mark-all-read-btn");
        const notifDropdownBtn = document.getElementById("notificationsDropdown");
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebarToggle");
        const mainWrapper = document.querySelector(".main-wrapper");
        
        // Debug logs
        console.log("Sidebar element:", sidebar);
        console.log("Sidebar toggle button:", sidebarToggle);
        
        const apiPath = "<?= $_headerWebPath ?>notifications_api.php";
        
        // Also keep the event listener as backup
        if (sidebarToggle) {
            console.log("Sidebar toggle found, adding click listener!");
            sidebarToggle.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log("Hamburger click listener fired!");
                toggleSidebar();
            });
        } else {
            console.error("Sidebar toggle button NOT found!");
        }
        
        // Close sidebar when clicking on the backdrop or outside
        document.addEventListener("click", function(e) {
            if (window.innerWidth <= 991.98) {
                if (sidebar.classList.contains("show")) {
                    // Check if click is on the sidebar itself or the toggle button
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        console.log("Clicked outside, closing sidebar");
                        sidebar.classList.remove("show");
                    }
                }
            }
        });
        
        // Function to update the unread count badge
        function updateBadgeCount() {
            fetch(apiPath + "?action=count")
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const count = data.unread_count;
                        if (count > 0) {
                            notifBadge.textContent = count > 99 ? '99+' : count;
                            notifBadge.classList.remove("d-none");
                        } else {
                            notifBadge.classList.add("d-none");
                        }
                        const unreadLabel = document.getElementById("notif-unread-count");
                        if (unreadLabel) {
                            unreadLabel.textContent = count > 0 ? '(' + count + ' unread)' : '';
                        }
                    }
                })
                .catch(err => console.error("Error fetching notification count:", err));
        }
        
        // Function to fetch and display notifications list
        function loadNotifications() {
            notifList.innerHTML = '<div class="notif-loading"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
            
            fetch(apiPath + "?action=fetch&limit=15")
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderNotifications(data.notifications);
                    } else {
                        notifList.innerHTML = '<div class="notif-empty-state"><i class="fas fa-exclamation-circle"></i><p class="mb-0 small">Failed to load notifications.</p></div>';
                    }
                })
                .catch(err => {
                    console.error("Error loading notifications:", err);
                    notifList.innerHTML = '<div class="notif-empty-state"><i class="fas fa-wifi"></i><p class="mb-0 small">Network error.</p></div>';
                });
        }
        
        // Render notification items with clickable routing
        function renderNotifications(items) {
            if (!items || items.length === 0) {
                notifList.innerHTML = `
                    <div class="notif-empty-state">
                        <i class="far fa-bell"></i>
                        <p class="mb-0 fw-medium small">All caught up!</p>
                        <span class="text-muted" style="font-size: 0.75rem;">No new notifications.</span>
                    </div>
                `;
                return;
            }
            
            notifList.innerHTML = "";
            items.forEach(item => {
                const isUnread = item.is_read === 0;
                const itemClass = isUnread ? "notif-item unread" : "notif-item";
                const icon = item.icon || { icon: 'fa-bell', color: '#6b7280', bg: '#f9fafb' };
                const targetUrl = item.target_url || '#';
                const targetId = item.target_id || '';
                
                // Build URL with target_id as query param if present
                let clickUrl = targetUrl;
                if (targetUrl && targetUrl !== '#' && targetId) {
                    clickUrl += (targetUrl.includes('?') ? '&' : '?') + 'id=' + targetId;
                }
                
                const itemHtml = `
                    <div class="${itemClass}" data-notif-id="${item.id}" onclick="handleNotifClick(${item.id}, '${clickUrl.replace(/'/g, "\\'")}', ${item.is_read})">
                        <div class="notif-icon-wrap" style="background:${icon.bg};color:${icon.color};">
                            <i class="fas ${icon.icon}"></i>
                        </div>
                        <div class="notif-content-wrap">
                            <div class="notif-title-text">${escapeHtml(item.title)}</div>
                            <div class="notif-msg-text">${escapeHtml(item.message)}</div>
                            <div class="notif-time-text">
                                <i class="far fa-clock"></i> ${item.time_ago}
                            </div>
                        </div>
                        ${isUnread ? '<div class="notif-read-dot"></div>' : ''}
                    </div>
                `;
                notifList.insertAdjacentHTML("beforeend", itemHtml);
            });
        }
        
        // Handle notification click — mark read + navigate
        window.handleNotifClick = function(notifId, url, isRead) {
            if (url === '#') return;
            
            // Mark as read via API
            if (!isRead) {
                const fd = new FormData();
                fd.append('action', 'mark_single_read');
                fd.append('id', notifId);
                fetch(apiPath, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(() => {
                        // Update UI instantly
                        const item = document.querySelector(`[data-notif-id="${notifId}"]`);
                        if (item) {
                            item.classList.remove('unread');
                            const dot = item.querySelector('.notif-read-dot');
                            if (dot) dot.remove();
                        }
                        // Decrease badge
                        const badge = document.getElementById('notif-badge');
                        const current = parseInt(badge.textContent) || 0;
                        if (current > 1) {
                            badge.textContent = current - 1;
                        } else {
                            badge.classList.add('d-none');
                        }
                        const unreadLabel = document.getElementById('notif-unread-count');
                        if (unreadLabel) {
                            const c = Math.max(0, current - 1);
                            unreadLabel.textContent = c > 0 ? '(' + c + ' unread)' : '';
                        }
                    })
                    .catch(() => {});
            }
            
            // Navigate to target page
            window.location.href = url;
        };
        
        // Escape HTML helper
        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }
        
        // Initial load and periodic checking
        updateBadgeCount();
        setInterval(updateBadgeCount, 30000);
        
        // Load list when dropdown is opened
        if (notifDropdownBtn) {
            notifDropdownBtn.addEventListener("show.bs.dropdown", loadNotifications);
        }
        
        // Mark all as read click event
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                markAllReadBtn.disabled = true;
                markAllReadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                fetch(apiPath + "?action=mark_read", { method: "POST" })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            notifBadge.classList.add("d-none");
                            notifBadge.textContent = "0";
                            document.querySelectorAll(".notif-item.unread").forEach(el => {
                                el.classList.remove("unread");
                                const dot = el.querySelector('.notif-read-dot');
                                if (dot) dot.remove();
                            });
                            const unreadLabel = document.getElementById("notif-unread-count");
                            if (unreadLabel) unreadLabel.textContent = '';
                        }
                        markAllReadBtn.disabled = false;
                        markAllReadBtn.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark all read';
                    })
                    .catch(err => {
                        console.error("Error marking read:", err);
                        markAllReadBtn.disabled = false;
                        markAllReadBtn.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark all read';
                    });
            });
        }

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById("themeToggleBtn");
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener("click", function() {
                const htmlEl = document.documentElement;
                const currentTheme = htmlEl.getAttribute("data-bs-theme");
                const newTheme = currentTheme === "dark" ? "light" : "dark";
                
                htmlEl.setAttribute("data-bs-theme", newTheme);
                themeToggleBtn.innerHTML = newTheme === "dark" ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
                
                // Save preference via AJAX to profile endpoint
                const fd = new FormData();
                fd.append("ajax_action", "update_theme");
                fd.append("theme", newTheme);
                fetch("<?= $_headerWebPath ?>user/profile.php", {
                    method: "POST",
                    body: fd
                }).catch(err => console.error("Error saving theme:", err));
            });
        }



        // Sidebar search functionality
        const sidebarSearch = document.getElementById('sidebar-search');
        const sidebarNav = document.getElementById('sidebar-nav');
        if (sidebarSearch && sidebarNav) {
            sidebarSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const navItems = sidebarNav.querySelectorAll('.nav-link');
                navItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    });
    
    // Silent API health check for admins — runs once per session
    <?php if (($user_role ?? '') === 'admin' && !($_SESSION['api_health_checked'] ?? false)): ?>
    $_SESSION['api_health_checked'] = true;
    (async function() {
        try {
            const res = await fetch('<?= $_headerWebPath ?>api/health_check.php', { method: 'GET' });
            if (res.ok) {
                const data = await res.json();
                if (data.has_failures) {
                    // Update bell indicator for API alerts
                    const badge = document.getElementById('notif-badge');
                    if (badge && badge.classList.contains('d-none')) {
                        badge.textContent = '!';
                        badge.classList.remove('d-none');
                        badge.style.background = '#f59e0b';
                    }
                }
            }
        } catch(e) {}
    })();
    <?php endif; ?>
    </script>
    
    <!-- Specific Content Area -->
    <main class="content-body">
