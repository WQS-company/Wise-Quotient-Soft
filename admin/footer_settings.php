<?php
$page_title = 'Footer Settings';
$path_to_root = '../';

require_once $path_to_root . 'config.php';

// Only allow admins
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = strtolower($_SESSION['user']['role'] ?? '');
$alert_msg = '';
$alert_type = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_footer') {
    $keys = [
        'about_text', 'services_list', 'contact_email', 'contact_phone', 'contact_address',
        'facebook_url', 'instagram_url', 'linkedin_url', 'twitter_url', 'github_url', 'youtube_url', 'copyright_text',
        'whatsapp_number',
        'broadcast_smtp_host', 'broadcast_smtp_port', 'broadcast_smtp_secure', 'broadcast_smtp_user', 'broadcast_smtp_pass', 'broadcast_smtp_from_email', 'broadcast_smtp_from_name'
    ];

    try {
        $pdo->beginTransaction();
        
        $uStmt = $pdo->prepare("UPDATE `footer_settings` SET `setting_value` = ? WHERE `setting_key` = ?");
        
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            $uStmt->execute([$val, $key]);
        }

        $pdo->commit();
        $alert_msg = 'Footer settings updated successfully.';
        $alert_type = 'success';
        
        // Refresh local cache for display in this request
        foreach ($keys as $key) {
            $footerSettings[$key] = trim($_POST[$key] ?? '');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $alert_msg = 'Error updating settings: ' . $e->getMessage();
        $alert_type = 'danger';
    }
}

require_once $path_to_root . 'includes/dashboard_header.php';
?>

<div class="container-fluid px-lg-4">
    
    <!-- Status Alerts -->
    <?php if ($alert_msg): ?>
        <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert" style="border-radius:12px;">
            <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($alert_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="card-theme mb-4">
        <div class="card-theme-body" style="background: linear-gradient(135deg, #0A2D5E 0%, #1e293b 100%); color: white; border-radius: 12px; padding: 2rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="background: rgba(255, 255, 255, 0.1); width: 54px; height: 54px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="fas fa-sliders-h text-orange"></i>
                </div>
                <div>
                    <h2 class="mb-1 fw-bold" style="font-size:1.4rem; color: white;">Footer Configuration Panel</h2>
                    <p class="mb-0 text-muted-light" style="font-size:0.88rem; color: #cbd5e1;">Configure links, social media feeds, contacts, and copyright details dynamically rendered across all landing page footers.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Config Form -->
    <div class="card-theme">
        <div class="card-theme-header">
            <h5 class="card-theme-title"><i class="fas fa-edit text-accent me-2"></i> Edit Footer Details</h5>
        </div>
        <div class="card-theme-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_footer">
                
                <div class="row g-4">
                    <!-- About Text Section -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Company Description (About Us)</label>
                        <textarea name="about_text" class="form-control" rows="4" style="font-size: 0.88rem;" required><?= htmlspecialchars($footerSettings['about_text'] ?? '') ?></textarea>
                        <div class="form-text small text-muted">A short description of the company rendered in the first column of the footer.</div>
                    </div>

                    <!-- Services list section -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Footer Services List (One per line)</label>
                        <textarea name="services_list" class="form-control font-monospace" rows="4" style="font-size: 0.88rem;" required><?= htmlspecialchars($footerSettings['services_list'] ?? '') ?></textarea>
                        <div class="form-text small text-muted">Enter the services you want displayed in the services column. Put each service on a separate line.</div>
                    </div>

                    <hr class="my-4 text-muted">

                    <!-- Contacts Info -->
                    <h6 class="fw-bold text-primary mt-3 mb-2"><i class="fas fa-address-book me-2"></i> Contact Details</h6>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Contact Email Address</label>
                        <input type="email" name="contact_email" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['contact_email'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Contact Phone Numbers (One per line)</label>
                        <textarea name="contact_phone" class="form-control" rows="2" style="font-size: 0.88rem;" required><?= htmlspecialchars($footerSettings['contact_phone'] ?? '') ?></textarea>
                        <div class="form-text small text-muted">Separate multiple numbers by putting them on new lines.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-whatsapp text-success me-1"></i> WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['whatsapp_number'] ?? '') ?>" placeholder="e.g. +2348077416106">
                        <div class="form-text small text-muted">Redirection logic will automatically point to this number.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Contact Office Address</label>
                        <input type="text" name="contact_address" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['contact_address'] ?? '') ?>" required>
                    </div>

                    <hr class="my-4 text-muted">

                    <!-- Social Networks -->
                    <h6 class="fw-bold text-primary mt-2 mb-2"><i class="fas fa-share-alt me-2"></i> Social Profiles</h6>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-facebook-f text-primary me-1"></i> Facebook Page URL</label>
                        <input type="url" name="facebook_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['facebook_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-instagram text-danger me-1"></i> Instagram Profile URL</label>
                        <input type="url" name="instagram_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['instagram_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-linkedin-in text-info me-1"></i> LinkedIn Profile URL</label>
                        <input type="url" name="linkedin_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['linkedin_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-twitter text-body me-1"></i> Twitter Profile URL</label>
                        <input type="url" name="twitter_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['twitter_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-github text-muted me-1"></i> GitHub Organization URL</label>
                        <input type="url" name="github_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['github_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;"><i class="fab fa-youtube text-danger me-1"></i> YouTube Channel URL</label>
                        <input type="url" name="youtube_url" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['youtube_url'] ?? '') ?>">
                    </div>

                    <hr class="my-4 text-muted">

                    <!-- Copyright -->
                    <h6 class="fw-bold text-primary mt-2 mb-2"><i class="fas fa-copyright me-2"></i> Copyright Info</h6>

                    <div class="col-md-12">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Copyright Branding Line</label>
                        <input type="text" name="copyright_text" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['copyright_text'] ?? '') ?>" required>
                        <div class="form-text small text-muted">Example: <strong>Wise Quotient Soft. All rights reserved.</strong></div>
                    </div>

                    <hr class="my-4 text-muted">

                    <!-- SMTP / Mail Config -->
                    <h6 class="fw-bold text-primary mt-2 mb-2"><i class="fas fa-envelope-open-text me-2"></i> Email Server Settings (SMTP / Notification Gateway)</h6>
                    <div class="col-md-12 mb-2">
                        <div class="alert alert-info py-2" style="font-size: 0.82rem; border-radius: 8px;">
                            <i class="fas fa-info-circle me-1"></i> These details configure SMTP mail socket transfers so that system notifications (e.g. partner approvals) are automatically dispatched to clients/partners.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">SMTP Host</label>
                        <input type="text" name="broadcast_smtp_host" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_host'] ?? '') ?>" placeholder="e.g. smtp.hostinger.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">SMTP Port</label>
                        <input type="text" name="broadcast_smtp_port" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Encryption</label>
                        <select name="broadcast_smtp_secure" class="form-select" style="font-size: 0.88rem;">
                            <option value="none" <?= ($footerSettings['broadcast_smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                            <option value="tls" <?= ($footerSettings['broadcast_smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($footerSettings['broadcast_smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">SMTP Username</label>
                        <input type="text" name="broadcast_smtp_user" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_user'] ?? '') ?>" placeholder="e.g. info@wisequotient.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">SMTP Password</label>
                        <input type="password" name="broadcast_smtp_pass" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_pass'] ?? '') ?>" placeholder="••••••••">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Sender Email Address</label>
                        <input type="email" name="broadcast_smtp_from_email" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_from_email'] ?? 'info@wisequotient.com') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-body" style="font-size: 0.88rem;">Sender Display Name</label>
                        <input type="text" name="broadcast_smtp_from_name" class="form-control" style="font-size: 0.88rem;" value="<?= htmlspecialchars($footerSettings['broadcast_smtp_from_name'] ?? 'Wise Quotient Soft') ?>">
                    </div>
                    <div class="col-md-12">
                        <div class="form-text small text-muted"><i class="fas fa-bullhorn me-1"></i> You can test the connection by sending a validation email from the <a href="broadcast.php" class="fw-semibold">Broadcast Control Center</a>.</div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-theme px-4"><i class="fas fa-save me-2"></i> Save Changes</button>
                    <a href="dashboard.php" class="btn btn-theme-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
