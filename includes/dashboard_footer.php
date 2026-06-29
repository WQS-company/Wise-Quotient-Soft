    </main>
    
    <!-- Professional Bottom Navigation (Mobile) -->
    <nav class="bottom-nav" id="bottomNav">
      <?php if (in_array($user_role, ['admin', 'manager', 'sales', 'support', 'finance', 'ceo', 'secretary'])): ?>
        <!-- Admin Bottom Nav -->
        <a href="<?= $path_to_root ?>admin/dashboard.php" class="bottom-nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
        <a href="<?= $path_to_root ?>admin/client_requests.php" class="bottom-nav-item <?= ($current_page === 'client_requests.php') ? 'active' : '' ?>">
          <i class="fas fa-lightbulb"></i>
          <span>Requests</span>
        </a>
        <a href="<?= $path_to_root ?>admin/invoice_management.php" class="bottom-nav-item <?= ($current_page === 'invoice_management.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i>
          <span>Invoices</span>
        </a>
        <a href="<?= $path_to_root ?>user/profile.php" class="bottom-nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i>
          <span>Profile</span>
        </a>
      <?php elseif ($user_role === 'developer'): ?>
        <!-- Developer Bottom Nav -->
        <a href="<?= $path_to_root ?>user/developer_hub.php" class="bottom-nav-item <?= ($current_page === 'developer_hub.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Hub</span>
        </a>
        <a href="<?= $path_to_root ?>user/client-project.php" class="bottom-nav-item <?= ($current_page === 'client-project.php') ? 'active' : '' ?>">
          <i class="fas fa-briefcase"></i>
          <span>Projects</span>
        </a>
        <a href="<?= $path_to_root ?>user/freelance_jobs.php" class="bottom-nav-item <?= ($current_page === 'freelance_jobs.php') ? 'active' : '' ?>">
          <i class="fas fa-search"></i>
          <span>Jobs</span>
        </a>
        <a href="<?= $path_to_root ?>user/profile.php" class="bottom-nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i>
          <span>Profile</span>
        </a>
      <?php else: ?>
        <!-- Client/Agent Bottom Nav -->
        <a href="<?= $path_to_root ?>user/dashboard.php" class="bottom-nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
        <a href="<?= $path_to_root ?>user/client-project.php" class="bottom-nav-item <?= ($current_page === 'client-project.php') ? 'active' : '' ?>">
          <i class="fas fa-briefcase"></i>
          <span>Projects</span>
        </a>
        <a href="<?= $path_to_root ?>user/client-invoices.php" class="bottom-nav-item <?= ($current_page === 'client-invoices.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i>
          <span>Payments</span>
        </a>
        <a href="<?= $path_to_root ?>user/profile.php" class="bottom-nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-circle"></i>
          <span>Profile</span>
        </a>
      <?php endif; ?>
    </nav>
    
    <!-- Unified Footer -->
    <footer class="dashboard-footer">
      <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <p class="mb-0">&copy; <?= date("Y") ?> <strong><?= htmlspecialchars($footerSettings['copyright_text'] ?? 'Wise Quotient Soft. All rights reserved.') ?></strong></p>
        <div class="dashboard-footer-links">
          <a href="#privacy">Privacy Policy</a>
          <a href="#terms">Terms of Service</a>
          <a href="#support">Support</a>
        </div>
      </div>
    </footer>
    
  </div> <!-- /.main-wrapper -->
</div> <!-- /.wrapper -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Sidebar close handled in dashboard_header.php -->

<?php
// Embed wise-bot chatbot assistant if present
$botPath = $path_to_root . 'includes/wise-bot.php';
if (file_exists($botPath)) {
    include_once $botPath;
}

// Embed cookie consent banner
$cookiePath = $path_to_root . 'includes/cookie_consent.php';
if (file_exists($cookiePath)) {
    include_once $cookiePath;
}

// Embed floating popup widget
$popupWidgetPath = $path_to_root . 'includes/popup_widget.php';
if (file_exists($popupWidgetPath)) {
    include_once $popupWidgetPath;
}
?>



<div class="wqs-customizer-overlay" id="wqsCustomizerOverlay"></div>

<div class="wqs-customizer-panel" id="wqsCustomizerPanel">
  <div class="wqs-customizer-header">
    <h5 class="wqs-customizer-title">
      <i class="fas fa-sliders-h"></i> Display Settings
    </h5>
    <button class="wqs-customizer-close" id="wqsCustomizerClose">
      <i class="fas fa-times"></i>
    </button>
  </div>
  
  <div class="wqs-customizer-body">
    <!-- Sidebar Layout Mode -->
    <div class="wqs-cust-group">
      <div class="wqs-cust-group-title">Sidebar Layout Mode</div>
      <div class="wqs-cust-card" data-sidebar-mode="list" id="sbModeList">
        <div class="card-info">
          <i class="fas fa-align-left text-muted"></i> Full Navigation List
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-sidebar-mode="icons" id="sbModeIcons">
        <div class="card-info">
          <i class="fas fa-th text-muted"></i> Icons & Hover Expand
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
    </div>

    <!-- Sidebar Theme -->
    <div class="wqs-cust-group">
      <div class="wqs-cust-group-title">Sidebar Color Scheme</div>
      <div class="wqs-cust-card" data-sidebar-theme="dark-blue" id="sbThemeDarkBlue">
        <div class="card-info">
          <i class="fas fa-circle" style="color: #0A2D5E;"></i> Dark Blue (Default)
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-sidebar-theme="sleek-dark" id="sbThemeSleekDark">
        <div class="card-info">
          <i class="fas fa-circle" style="color: #0f172a;"></i> Midnight Dark
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-sidebar-theme="light-clean" id="sbThemeLightClean">
        <div class="card-info">
          <i class="fas fa-circle" style="color: #ffffff; border: 1px solid #cbd5e1; border-radius:50%;"></i> Minimalist Light
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
    </div>

    <!-- Header Alignment -->
    <div class="wqs-cust-group">
      <div class="wqs-cust-group-title">Header Placement</div>
      <div class="wqs-cust-card" data-header-align="default" id="hdrAlignDefault">
        <div class="card-info">
          <i class="fas fa-heading text-muted"></i> Title Left, Controls Right
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-header-align="centered" id="hdrAlignCentered">
        <div class="card-info">
          <i class="fas fa-align-center text-muted"></i> Centered Page Title
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-header-align="split" id="hdrAlignSplit">
        <div class="card-info">
          <i class="fas fa-columns text-muted"></i> Expanded Split Headers
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
    </div>

    <!-- Header Theme Style -->
    <div class="wqs-cust-group">
      <div class="wqs-cust-group-title">Header Theme Style</div>
      <div class="wqs-cust-card" data-header-theme="glass" id="hdrThemeGlass">
        <div class="card-info">
          <i class="fas fa-window-maximize text-muted"></i> Glassmorphic Blur
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-header-theme="solid" id="hdrThemeSolid">
        <div class="card-info">
          <i class="fas fa-square text-muted"></i> Solid Clean White
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
      <div class="wqs-cust-card" data-header-theme="minimal" id="hdrThemeMinimal">
        <div class="card-info">
          <i class="fas fa-border-none text-muted"></i> Minimalist (No Borders)
        </div>
        <i class="fas fa-check-circle check-icon"></i>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const panelBtn = document.getElementById('openDisplayCustomizer') || document.getElementById('wqsCustomizerBtn');
  const panelClose = document.getElementById('wqsCustomizerClose');
  const panel = document.getElementById('wqsCustomizerPanel');
  const overlay = document.getElementById('wqsCustomizerOverlay');

  // Toggle Panel open/close
  if (panelBtn) {
    panelBtn.addEventListener('click', function() {
      panel.classList.add('open');
      overlay.classList.add('show');
    });
  }
  
  if (panelClose) {
    panelClose.addEventListener('click', closePanel);
  }
  
  if (overlay) {
    overlay.addEventListener('click', closePanel);
  }

  function closePanel() {
    panel.classList.remove('open');
    overlay.classList.remove('show');
  }

  // Load active customizer selections into UI active state classes
  const currentMode = localStorage.getItem('wqs_sidebar_mode') || 'list';
  const currentSbTheme = localStorage.getItem('wqs_sidebar_theme') || 'dark-blue';
  const currentHdrAlign = localStorage.getItem('wqs_header_align') || 'default';
  const currentHdrTheme = localStorage.getItem('wqs_header_theme') || 'glass';

  // Toggle active selection states inside list options
  document.querySelectorAll('[data-sidebar-mode]').forEach(function(card) {
    if (card.getAttribute('data-sidebar-mode') === currentMode) card.classList.add('active');
    card.addEventListener('click', function() {
      document.querySelectorAll('[data-sidebar-mode]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const val = this.getAttribute('data-sidebar-mode');
      localStorage.setItem('wqs_sidebar_mode', val);
      
      const html = document.documentElement;
      if (val === 'icons') {
        html.classList.add('sidebar-icons-only');
      } else {
        html.classList.remove('sidebar-icons-only');
      }
    });
  });

  document.querySelectorAll('[data-sidebar-theme]').forEach(function(card) {
    if (card.getAttribute('data-sidebar-theme') === currentSbTheme) card.classList.add('active');
    card.addEventListener('click', function() {
      document.querySelectorAll('[data-sidebar-theme]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const val = this.getAttribute('data-sidebar-theme');
      localStorage.setItem('wqs_sidebar_theme', val);
      
      const html = document.documentElement;
      // Remove all previous sidebar theme classes
      html.className = html.className.replace(/\bsb-theme-\S+/g, '');
      html.classList.add('sb-theme-' + val);
    });
  });

  document.querySelectorAll('[data-header-align]').forEach(function(card) {
    if (card.getAttribute('data-header-align') === currentHdrAlign) card.classList.add('active');
    card.addEventListener('click', function() {
      document.querySelectorAll('[data-header-align]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const val = this.getAttribute('data-header-align');
      localStorage.setItem('wqs_header_align', val);
      
      const html = document.documentElement;
      html.className = html.className.replace(/\bhdr-align-\S+/g, '');
      html.classList.add('hdr-align-' + val);
    });
  });

  document.querySelectorAll('[data-header-theme]').forEach(function(card) {
    if (card.getAttribute('data-header-theme') === currentHdrTheme) card.classList.add('active');
    card.addEventListener('click', function() {
      document.querySelectorAll('[data-header-theme]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const val = this.getAttribute('data-header-theme');
      localStorage.setItem('wqs_header_theme', val);
      
      const html = document.documentElement;
      html.className = html.className.replace(/\bhdr-theme-\S+/g, '');
      html.classList.add('hdr-theme-' + val);
    });
  });
});
</script>

</body>
</html>
