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

<!-- Mobile Navigation Sidebar Close on Outside Click -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const toggleBtn = document.getElementById("sidebarToggle");
    
    if (toggleBtn && sidebar) {
        // Close sidebar if clicked outside on mobile viewports
        document.addEventListener("click", function(e) {
            if (window.innerWidth < 992 && sidebar.classList.contains("show")) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                    sidebar.classList.remove("show");
                }
            }
        });
    }
});
</script>

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

</body>
</html>
