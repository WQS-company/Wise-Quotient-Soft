<?php
session_start();

// Normalize phone number function
function normalizePhone($input) {
    $digits = preg_replace('/[^0-9]/', '', $input);
    if (strpos($digits, '234') === 0) {
        return '0' . substr($digits, 3);
    } elseif (strpos($digits, '0') === 0) {
        return $digits;
    } else {
        return '0' . $digits;
    }
}

// Handle AJAX profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    require_once dirname(__DIR__) . '/config.php';
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $userId = $_SESSION['user']['id'];
    $action = $_POST['ajax_action'];

    if ($action === 'update_profile') {
        $name   = trim($_POST['name'] ?? '');
        $phone  = normalizePhone(trim($_POST['phone'] ?? ''));
        $bio    = trim($_POST['bio'] ?? '');
        $company= trim($_POST['company'] ?? '');
        
        // Extended professional fields
        $profession = trim($_POST['profession'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $tech_stack = trim($_POST['tech_stack'] ?? '');
        $previous_experience = trim($_POST['previous_experience'] ?? '');
        $projects_developed = trim($_POST['projects_developed'] ?? '');
        $education = trim($_POST['education'] ?? '');
        $brief_history = trim($_POST['brief_history'] ?? '');
        $profile_visibility = in_array($_POST['profile_visibility'] ?? '', ['public', 'private']) ? $_POST['profile_visibility'] : 'public';
        $profile_slug = trim($_POST['profile_slug'] ?? '');
        
        // Social links
        $linkedin   = trim($_POST['linkedin_url'] ?? '');
        $twitter    = trim($_POST['twitter_url'] ?? '');
        $github     = trim($_POST['github_url'] ?? '');
        $facebook   = trim($_POST['facebook_url'] ?? '');
        $instagram  = trim($_POST['instagram_url'] ?? '');
        $website    = trim($_POST['website_url'] ?? '');
        
        // Auto-generate slug if empty
        if (empty($profile_slug)) {
            $profile_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) . '-' . substr(md5(uniqid()), 0, 4);
        }

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Name is required.']);
            exit;
        }

        try {
            $pdo->prepare("UPDATE users SET name=?, phone=?, bio=?, company=?, profession=?, skills=?, tech_stack=?, previous_experience=?, projects_developed=?, education=?, brief_history=?, profile_visibility=?, profile_slug=?, linkedin_url=?, twitter_url=?, github_url=?, facebook_url=?, instagram_url=?, website_url=? WHERE id=?")
                ->execute([$name, $phone, $bio, $company, $profession, $skills, $tech_stack, $previous_experience, $projects_developed, $education, $brief_history, $profile_visibility, $profile_slug, $linkedin, $twitter, $github, $facebook, $instagram, $website, $userId]);
            // Update session
            $_SESSION['user']['name'] = $name;
            echo json_encode(['success' => true, 'slug' => $profile_slug]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            echo json_encode(['success' => false, 'message' => 'All password fields required.']);
            exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, $row['password'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                exit;
            }

            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'upload_avatar') {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!isset($_FILES['avatar'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
            exit;
        }
        $file = $_FILES['avatar'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, WEBP accepted.']);
            exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large. Max 2MB.']);
            exit;
        }

        require_once dirname(__DIR__) . '/includes/cloudinary.php';
        $cloudUrl = uploadToCloudinary($file['tmp_name'], 'avatars', 'image');

        if ($cloudUrl) {
            $pdo->prepare("UPDATE users SET picture = ? WHERE id = ?")->execute([$cloudUrl, $userId]);
            $_SESSION['user']['picture'] = $cloudUrl;
            echo json_encode(['success' => true, 'picture' => $cloudUrl]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload to Cloudinary.']);
        }
        exit;
    }

    if ($action === 'update_theme') {
        $theme = in_array($_POST['theme'] ?? '', ['light', 'dark']) ? $_POST['theme'] : 'light';
        try {
            $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$theme, $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update theme']);
        }
        exit;
    }

    if ($action === 'update_session_timeout') {
        $timeout = (int)($_POST['timeout'] ?? 60);
        try {
            $pdo->prepare("UPDATE users SET session_timeout = ? WHERE id = ?")->execute([$timeout, $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update session timeout']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

$path_to_root = "../";
$page_title = "My Profile & Settings";
$current_page = "profile.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$userId = $headerUser['id'];

// Fetch full user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $profile = $headerUser;
}

// Fetch user notification and ad preferences
$notifPrefs = ['enable_ads' => true, 'enable_push' => true, 'enable_email' => true];
try {
    // Check if table exists first
    $checkTable = $pdo->query("SHOW TABLES LIKE 'user_notification_settings'");
    if ($checkTable->fetch()) {
        $prefStmt = $pdo->prepare("SELECT enable_ads, enable_push_notifications AS enable_push, enable_email_notifications AS enable_email FROM user_notification_settings WHERE user_id = ?");
        $prefStmt->execute([$userId]);
        $fetchedPrefs = $prefStmt->fetch(PDO::FETCH_ASSOC);
        if ($fetchedPrefs) {
            $notifPrefs = array_merge($notifPrefs, $fetchedPrefs);
        }
    }
} catch (Exception $e) {}

// Activity stats
$activityStats = ['projects' => 0, 'requests' => 0, 'tickets' => 0, 'logins' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM ongoing_projects WHERE user_id = ?"); $r->execute([$userId]); $activityStats['projects'] = $r->fetchColumn();
    $r = $pdo->prepare("SELECT COUNT(*) FROM client_requests WHERE user_id = ?"); $r->execute([$userId]); $activityStats['requests'] = $r->fetchColumn();
    $r = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?"); $r->execute([$userId]); $activityStats['tickets'] = $r->fetchColumn();
} catch (Exception $e) {}

$initial = strtoupper(substr($profile['name'] ?? 'U', 0, 1));
$roleMap = ['user' => 'Client', 'agent' => 'Partner', 'developer' => 'Developer', 'admin' => 'Administrator'];
$roleLabel = $roleMap[$profile['role'] ?? 'user'] ?? 'User';
?>

<style>
.profile-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 768px) {
    .profile-layout { grid-template-columns: 1fr; }
}
.profile-sidebar-card {
    background: white; border-radius: 20px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
    position: sticky; top: 1rem;
}
.profile-banner {
    height: 80px;
    background: linear-gradient(135deg, #0A2D5E 0%, #E15501 100%);
}
.profile-avatar-wrapper {
    display: flex; flex-direction: column; align-items: center;
    margin-top: -40px; padding: 0 1.5rem 1.5rem;
}
.profile-avatar-img {
    width: 80px; height: 80px; border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.profile-avatar-placeholder {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #0A2D5E, #2563eb);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.8rem; font-weight: 700;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.profile-nav-link {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1.25rem; color: #64748b;
    font-size: 0.88rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s;
    border-radius: 10px; margin: 0.15rem 0.5rem;
}
.profile-nav-link:hover { background:var(--color-bg); color: #0A2D5E; }
.profile-nav-link.active { background: rgba(10,45,94,0.08); color: #0A2D5E; }
.profile-nav-link i { width: 20px; text-align: center; }

.profile-main-card {
    background: white; border-radius: 20px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
}
.profile-section { display: none; }
.profile-section.active { display: block; }

.profile-section-header {
    padding: 1.5rem 1.75rem; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 0.75rem;
}
.profile-section-header h5 {
    margin: 0; font-size: 1rem; font-weight: 800; color: #0A2D5E;
}
.section-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}

.form-label-premium { font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem; }
.form-control-premium {
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    padding: 0.65rem 0.9rem; font-size: 0.9rem;
    transition: all 0.2s; background:var(--color-bg);
}
.form-control-premium:focus {
    border-color: #0A2D5E; background: white;
    box-shadow: 0 0 0 3px rgba(10,45,94,0.08);
    outline: none;
}
.btn-save-premium {
    background: linear-gradient(135deg, #0A2D5E, #163f7a);
    color: white; border: none; border-radius: 10px;
    padding: 0.65rem 2rem; font-weight: 700; font-size: 0.9rem;
    transition: all 0.25s; cursor: pointer;
}
.btn-save-premium:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(10,45,94,0.25); }

.avatar-upload-btn {
    background: rgba(10,45,94,0.08); border: none; color: #0A2D5E;
    border-radius: 8px; padding: 0.4rem 0.8rem; font-size: 0.78rem;
    font-weight: 700; cursor: pointer; margin-top: 0.5rem; transition: all 0.2s;
}
.avatar-upload-btn:hover { background: rgba(10,45,94,0.15); }

.activity-stat-box {
    border-radius: 12px; padding: 1rem; text-align: center;
    border: 1px solid transparent; transition: all 0.2s;
}
.activity-stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }

/* Mobile Responsive Styles for Profile */
@media (max-width: 991.98px) {
  .profile-sidebar-card {
    position: relative;
    top: 0;
  }
}

@media (max-width: 767.98px) {
  .profile-section-header {
    padding: 1rem 1.25rem;
  }
  .profile-main-card .p-5 {
    padding: 1.25rem !important;
  }
  .btn-save-premium {
    width: 100%;
    text-align: center;
  }
}

@media (max-width: 575.98px) {
  .profile-banner {
    height: 60px;
  }
  .profile-avatar-wrapper {
    margin-top: -30px;
  }
  .profile-avatar-img, .profile-avatar-placeholder {
    width: 60px;
    height: 60px;
  }
  .profile-section-header h5 {
    font-size: 0.95rem;
  }
  .form-label-premium {
    font-size: 0.78rem;
  }
  .form-control-premium {
    font-size: 0.85rem;
    padding: 0.5rem 0.75rem;
  }
}

@media (max-width: 400px) {
  .profile-banner {
    height: 50px;
  }
  .profile-avatar-wrapper {
    margin-top: -25px;
  }
  .profile-avatar-img, .profile-avatar-placeholder {
    width: 50px;
    height: 50px;
  }
  .activity-stat-box {
    padding: 0.75rem;
  }
  .activity-stat-box div:first-child {
    font-size: 1.2rem !important;
  }
  .profile-section-header {
    padding: 0.75rem 1rem;
  }
  .profile-main-card .p-5 {
    padding: 1rem !important;
  }
  .form-label-premium {
    font-size: 0.75rem;
    margin-bottom: 0.35rem;
  }
  .form-control-premium {
    font-size: 0.82rem;
    padding: 0.45rem 0.7rem;
  }
  .btn-save-premium {
    padding: 0.55rem 1.5rem;
    font-size: 0.85rem;
  }
  .profile-nav-link {
    padding: 0.55rem 0.75rem !important;
    font-size: 0.82rem !important;
  }
}
</style>

<div class="profile-layout">
    <!-- Sidebar -->
    <div class="profile-sidebar-card">
        <div class="profile-banner"></div>
        <div class="profile-avatar-wrapper">
            <?php if (!empty($profile['picture'])): ?>
                <img src="<?= htmlspecialchars($profile['picture']) ?>" class="profile-avatar-img" id="profileAvatarDisplay" alt="Profile Photo">
            <?php else: ?>
                <img src="<?= $path_to_root ?>images/default-avatar.png" class="profile-avatar-img" id="profileAvatarDisplay" alt="Profile Photo">
            <?php endif; ?>
            <label class="avatar-upload-btn mt-1" for="avatarFileInput"><i class="fas fa-camera me-1"></i> Change Photo</label>
            <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" onchange="uploadAvatar(this)">
            
            <h6 class="fw-bold text-body mt-2 mb-0"><?= htmlspecialchars($profile['name'] ?? 'User') ?></h6>
            <span style="font-size:0.78rem;font-weight:600;color:#94a3b8;"><?= $roleLabel ?></span>
            <div class="text-muted mt-1" style="font-size:0.75rem;"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($profile['email'] ?? '') ?></div>
            <?php if (!empty($profile['company'])): ?>
                <div class="text-muted" style="font-size:0.75rem;"><i class="fas fa-building me-1"></i><?= htmlspecialchars($profile['company']) ?></div>
            <?php endif; ?>
        </div>
        <hr class="my-0 mx-3">

        <!-- Activity Stats -->
        <div class="p-3">
            <div style="font-size:0.7rem;text-transform:uppercase;font-weight:700;color:#94a3b8;padding:0 0.25rem 0.5rem;">Activity Overview</div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="activity-stat-box" style="background:#eff6ff;border-color:#bfdbfe;">
                        <div style="font-size:1.4rem;font-weight:900;color:#1d4ed8;"><?= $activityStats['projects'] ?></div>
                        <div style="font-size:0.7rem;color:#3b82f6;font-weight:600;">Projects</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="activity-stat-box" style="background:#f0fdf4;border-color:#86efac;">
                        <div style="font-size:1.4rem;font-weight:900;color:#15803d;"><?= $activityStats['requests'] ?></div>
                        <div style="font-size:0.7rem;color:#16a34a;font-weight:600;">Requests</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="activity-stat-box" style="background:#faf5ff;border-color:#c4b5fd;">
                        <div style="font-size:1.4rem;font-weight:900;color:#7c3aed;"><?= $activityStats['tickets'] ?></div>
                        <div style="font-size:0.7rem;color:#8b5cf6;font-weight:600;">Tickets</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="activity-stat-box" style="background:#fff7ed;border-color:#fed7aa;">
                        <div style="font-size:1.4rem;font-weight:900;color:#ea580c;"><?= !empty($profile['created_at']) ? date('Y', strtotime($profile['created_at'])) : '—' ?></div>
                        <div style="font-size:0.7rem;color:#f97316;font-weight:600;">Member Since</div>
                    </div>
                </div>
            </div>
        </div>
        <hr class="my-0 mx-3">
        <!-- Nav links -->
        <div class="p-2">
            <a href="#" class="profile-nav-link active" onclick="switchTab('tab-info', this)">
                <i class="fas fa-user" style="color:#0A2D5E;"></i> Personal Info
            </a>
            <a href="#" class="profile-nav-link" onclick="switchTab('tab-professional', this)">
                <i class="fas fa-briefcase" style="color:#059669;"></i> Professional Profile
            </a>
            <a href="#" class="profile-nav-link" onclick="switchTab('tab-security', this)">
                <i class="fas fa-lock" style="color:#7c3aed;"></i> Security & Password
            </a>
            <a href="#" class="profile-nav-link" onclick="switchTab('tab-notifications', this)">
                <i class="fas fa-bell" style="color:#ea580c;"></i> Notifications
            </a>
        </div>
        <div class="p-3">
            <a href="../logout.php" class="btn w-100 rounded-pill" style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;font-size:0.85rem;font-weight:700;">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Area -->
    <div>
        <!-- Personal Info Tab -->
        <div class="profile-main-card mb-4 profile-section active" id="tab-info">
            <div class="profile-section-header">
                <div class="section-icon" style="background:#eff6ff;color:#1d4ed8;"><i class="fas fa-user"></i></div>
                <div>
                    <h5>Personal Information</h5>
                    <div style="font-size:0.78rem;color:#94a3b8;">Update your name, contact details and professional info.</div>
                </div>
            </div>
            <div class="p-5">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label-premium">Full Name *</label>
                        <input type="text" id="pf_name" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" placeholder="Your full name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Email Address</label>
                        <input type="email" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled style="opacity:0.6;cursor:not-allowed;">
                        <div class="text-muted mt-1" style="font-size:0.75rem;">Contact support to change your email address.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Phone Number</label>
                        <input type="tel" id="pf_phone" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="+234 800 000 0000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Company / Organization</label>
                        <input type="text" id="pf_company" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['company'] ?? '') ?>" placeholder="Your company name">
                    </div>
                    <div class="col-12">
                        <label class="form-label-premium">Bio / About You</label>
                        <textarea id="pf_bio" class="form-control form-control-premium" rows="6" placeholder="Tell us a bit about yourself..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <button class="btn-save-premium" onclick="saveProfile()">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <div style="padding-top:0.6rem;font-size:0.82rem;" id="profileSaveMsg"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Profile Tab -->
        <div class="profile-main-card mb-4 profile-section" id="tab-professional">
            <div class="profile-section-header">
                <div class="section-icon" style="background:#ecfdf5;color:#059669;"><i class="fas fa-briefcase"></i></div>
                <div style="flex-grow:1;">
                    <h5>Professional Profile</h5>
                    <div style="font-size:0.78rem;color:#94a3b8;">Manage your public portfolio, capabilities, and sharing settings.</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-check-label form-label-premium mb-0" for="pf_visibility">Public Profile</label>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="pf_visibility" <?= ($profile['profile_visibility'] ?? 'public') === 'public' ? 'checked' : '' ?> style="width:2.5rem;height:1.3rem;cursor:pointer;">
                    </div>
                </div>
            </div>
            <div class="p-5">
                <?php $slug = htmlspecialchars($profile['profile_slug'] ?? ''); $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $profileUrl = !empty($slug) ? $protocol . "://" . $_SERVER['HTTP_HOST'] . "/dashboard/wqs/dev_profile.php?u=" . $slug : ''; ?>
                <div class="mb-4" style="background:linear-gradient(135deg,rgba(10,45,94,0.03),rgba(37,99,235,0.05));border:1.5px solid #e2e8f0;border-radius:16px;padding:1.25rem 1.5rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#0A2D5E,#2563eb);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(10,45,94,0.2);">
                            <i class="fas fa-link" style="color:white;font-size:1.1rem;"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:0.25rem;">Your Shareable Profile Link</div>
                            <?php if (!empty($slug)): ?>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <span style="font-size:0.92rem;font-weight:700;color:#0A2D5E;word-break:break-all;" id="shareableLinkDisplay"><?= htmlspecialchars($profileUrl) ?></span>
                                    <button onclick="copyProfileLink()" style="background:white;border:1px solid #e2e8f0;border-radius:8px;padding:0.35rem 0.7rem;font-size:0.72rem;font-weight:700;color:#64748b;cursor:pointer;transition:all 0.2s;white-space:nowrap;flex-shrink:0;" onmouseover="this.style.borderColor='#0A2D5E';this.style.color='#0A2D5E';" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b';">
                                        <i class="fas fa-copy me-1"></i>Copy
                                    </button>
                                    <a href="../dev_profile.php?u=<?= $slug ?>" target="_blank" style="background:#0A2D5E;color:white;border:none;border-radius:8px;padding:0.35rem 0.9rem;font-size:0.72rem;font-weight:700;text-decoration:none;transition:all 0.2s;white-space:nowrap;flex-shrink:0;" onmouseover="this.style.background='#163f7a';" onmouseout="this.style.background='#0A2D5E';">
                                        <i class="fas fa-external-link-alt me-1"></i>Open
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.88rem;color:#94a3b8;" id="shareableLinkDisplay">Save your profile to generate a shareable link.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label-premium">Profession / Title</label>
                        <input type="text" id="pf_profession" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['profession'] ?? '') ?>" placeholder="e.g. Senior Full Stack Engineer">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Custom URL Slug</label>
                        <input type="text" id="pf_slug" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['profile_slug'] ?? '') ?>" placeholder="e.g. john-doe-dev">
                    </div>
                    <div class="col-12">
                        <label class="form-label-premium">Top Skills (Comma Separated)</label>
                        <input type="text" id="pf_skills" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['skills'] ?? '') ?>" placeholder="e.g. UI/UX Design, Agile Management, API Integration">
                    </div>
                    <div class="col-12">
                        <label class="form-label-premium">Tech Stack</label>
                        <input type="text" id="pf_tech_stack" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['tech_stack'] ?? '') ?>" placeholder="e.g. PHP, React, MySQL, Node.js, AWS">
                    </div>
                    <div class="col-12">
                        <label class="form-label-premium">Brief Professional History</label>
                        <textarea id="pf_history" class="form-control form-control-premium" rows="6" placeholder="A brief summary of your career journey..."><?= htmlspecialchars($profile['brief_history'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Previous Experience</label>
                        <textarea id="pf_experience" class="form-control form-control-premium" rows="8" placeholder="List your previous roles and companies..."><?= htmlspecialchars($profile['previous_experience'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium">Projects Developed</label>
                        <textarea id="pf_projects" class="form-control form-control-premium" rows="8" placeholder="Highlight key projects you have built..."><?= htmlspecialchars($profile['projects_developed'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label-premium">Education</label>
                        <input type="text" id="pf_education" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['education'] ?? '') ?>" placeholder="e.g. BSc Computer Science, Stanford University">
                    </div>
                </div>

                <!-- Social Links Section -->
                <hr class="my-4">
                <div class="mb-3">
                    <h6 class="fw-bold text-body mb-1"><i class="fas fa-share-alt me-2 text-primary"></i>Social Links & Contact</h6>
                    <small class="text-muted">Connect your professional accounts. These will appear on your public profile and team card.</small>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fab fa-linkedin text-primary me-1"></i>LinkedIn URL</label>
                        <input type="url" id="pf_linkedin" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/in/your-profile">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fab fa-twitter text-info me-1"></i>Twitter / X URL</label>
                        <input type="url" id="pf_twitter" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['twitter_url'] ?? '') ?>" placeholder="https://twitter.com/your-handle">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fab fa-github text-dark me-1"></i>GitHub URL</label>
                        <input type="url" id="pf_github" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['github_url'] ?? '') ?>" placeholder="https://github.com/your-username">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fab fa-facebook text-primary me-1"></i>Facebook URL</label>
                        <input type="url" id="pf_facebook" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/your-profile">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fab fa-instagram text-danger me-1"></i>Instagram URL</label>
                        <input type="url" id="pf_instagram" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/your-handle">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-premium"><i class="fas fa-globe text-success me-1"></i>Personal Website</label>
                        <input type="url" id="pf_website" class="form-control form-control-premium" value="<?= htmlspecialchars($profile['website_url'] ?? '') ?>" placeholder="https://yoursite.com">
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <button class="btn-save-premium" onclick="saveProfile()">
                            <i class="fas fa-save me-2"></i>Save Professional Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="profile-main-card mb-4 profile-section" id="tab-security">
            <div class="profile-section-header">
                <div class="section-icon" style="background:#faf5ff;color:#7c3aed;"><i class="fas fa-lock"></i></div>
                <div>
                    <h5>Password & Security</h5>
                    <div style="font-size:0.78rem;color:#94a3b8;">Keep your account secure with a strong password.</div>
                </div>
            </div>
            <div class="p-5" style="max-width: 500px;">
                <div class="mb-4">
                    <label class="form-label-premium">Current Password</label>
                    <input type="password" id="pw_current" class="form-control form-control-premium" placeholder="••••••••">
                </div>
                <div class="mb-4">
                    <label class="form-label-premium">New Password</label>
                    <input type="password" id="pw_new" class="form-control form-control-premium" placeholder="At least 8 characters">
                </div>
                <div class="mb-4">
                    <label class="form-label-premium">Confirm New Password</label>
                    <input type="password" id="pw_confirm" class="form-control form-control-premium" placeholder="Repeat new password">
                </div>
                <div class="mb-3 p-3 rounded-3" style="background:var(--color-bg);border:1px solid #e2e8f0;font-size:0.8rem;color:#64748b;">
                    <i class="fas fa-shield-alt text-primary me-1"></i>
                    Use at least 8 characters including letters, numbers, and symbols for best security.
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <button class="btn-save-premium" onclick="changePassword()">
                        <i class="fas fa-key me-2"></i>Update Password
                    </button>
                    <div id="pwSaveMsg" style="font-size:0.82rem;"></div>
                </div>

                <hr class="my-5" style="border-color: #e2e8f0;">
                
                <h6 class="fw-bold mb-3" style="color: #0A2D5E;">Session Security</h6>
                <div class="mb-4">
                    <label class="form-label-premium">Auto-Logout Timeout</label>
                    <div class="d-flex gap-3 align-items-center">
                        <select id="session_timeout" class="form-select form-control-premium" style="max-width: 200px;">
                            <?php $currTimeout = $profile['session_timeout'] ?? 60; ?>
                            <option value="15" <?= $currTimeout == 15 ? 'selected' : '' ?>>15 Minutes</option>
                            <option value="30" <?= $currTimeout == 30 ? 'selected' : '' ?>>30 Minutes</option>
                            <option value="60" <?= $currTimeout == 60 ? 'selected' : '' ?>>1 Hour</option>
                            <option value="120" <?= $currTimeout == 120 ? 'selected' : '' ?>>2 Hours</option>
                            <option value="240" <?= $currTimeout == 240 ? 'selected' : '' ?>>4 Hours</option>
                            <option value="0" <?= $currTimeout == 0 ? 'selected' : '' ?>>Never</option>
                        </select>
                        <button class="btn-save-premium btn-sm" style="padding: 0.5rem 1.2rem; font-size: 0.85rem;" onclick="updateSessionTimeout()">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                    </div>
                    <div class="text-muted mt-2" style="font-size:0.75rem;">For your security, we will automatically log you out after this period of inactivity.</div>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="profile-main-card profile-section" id="tab-notifications">
            <div class="profile-section-header">
                <div class="section-icon" style="background:#fff7ed;color:#ea580c;"><i class="fas fa-bell"></i></div>
                <div>
                    <h5>Notification Preferences</h5>
                    <div style="font-size:0.78rem;color:#94a3b8;">Control what alerts and promotions you receive from the platform.</div>
                </div>
            </div>
            <div class="p-5">
                <!-- Ads & Promotions -->
                <div class="mb-4 p-4" style="background:linear-gradient(135deg,rgba(16,185,129,0.05),rgba(59,130,246,0.05));border:1.5px solid #e2e8f0;border-radius:16px;">
                    <h6 class="fw-bold mb-3" style="color: #0f172a;"><i class="fas fa-ad text-primary me-2"></i>Ads & Promotions</h6>
                    <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                        <div>
                            <div class="fw-semibold text-body" style="font-size:0.9rem;">Show Promotional Content</div>
                            <div class="text-muted" style="font-size:0.78rem;">Receive offers, new service announcements, and platform promotions.</div>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox" id="pref_enable_ads" <?= $notifPrefs['enable_ads'] ? 'checked' : '' ?> style="width:2.5rem;height:1.3rem;cursor:pointer;">
                        </div>
                    </div>
                </div>

                <!-- Notification Channels -->
                <h6 class="fw-bold mb-3" style="color: #0A2D5E;"><i class="fas fa-satellite-dish text-info me-2"></i>Notification Channels</h6>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div>
                        <div class="fw-semibold text-body" style="font-size:0.9rem;">Push Notifications</div>
                        <div class="text-muted" style="font-size:0.78rem;">Get browser notifications for important updates.</div>
                    </div>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input" type="checkbox" id="pref_enable_push" <?= $notifPrefs['enable_push'] ? 'checked' : '' ?> style="width:2.5rem;height:1.3rem;cursor:pointer;">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div>
                        <div class="fw-semibold text-body" style="font-size:0.9rem;">Email Notifications</div>
                        <div class="text-muted" style="font-size:0.78rem;">Receive email updates about your account and activity.</div>
                    </div>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input" type="checkbox" id="pref_enable_email" <?= $notifPrefs['enable_email'] ? 'checked' : '' ?> style="width:2.5rem;height:1.3rem;cursor:pointer;">
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn-save-premium" onclick="saveNotifPrefs()">
                        <i class="fas fa-save me-2"></i>Save Preferences
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabId, linkEl) {
    document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.profile-nav-link').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    linkEl.classList.add('active');
    return false;
}

async function saveNotifPrefs() {
    const enableAds = document.getElementById('pref_enable_ads')?.checked ?? true;
    const enablePush = document.getElementById('pref_enable_push')?.checked ?? true;
    const enableEmail = document.getElementById('pref_enable_email')?.checked ?? true;

    try {
        const response = await fetch('../api/update_notification_prefs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                enable_ads: enableAds,
                enable_push: enablePush,
                enable_email: enableEmail
            })
        });

        const result = await response.json();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Preferences Saved',
                text: 'Your notification and ad preferences have been updated.',
                confirmButtonColor: '#0A2D5E'
            });
        } else {
            throw new Error(result.message || 'Failed to save preferences');
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message,
            confirmButtonColor: '#dc2626'
        });
    }
}

function saveProfile() {
    const name    = document.getElementById('pf_name').value.trim();
    const phone   = document.getElementById('pf_phone').value.trim();
    const company = document.getElementById('pf_company').value.trim();
    const bio     = document.getElementById('pf_bio').value.trim();

    // Professional fields
    const profession = document.getElementById('pf_profession') ? document.getElementById('pf_profession').value.trim() : '';
    const slug = document.getElementById('pf_slug') ? document.getElementById('pf_slug').value.trim() : '';
    const skills = document.getElementById('pf_skills') ? document.getElementById('pf_skills').value.trim() : '';
    const techStack = document.getElementById('pf_tech_stack') ? document.getElementById('pf_tech_stack').value.trim() : '';
    const history = document.getElementById('pf_history') ? document.getElementById('pf_history').value.trim() : '';
    const experience = document.getElementById('pf_experience') ? document.getElementById('pf_experience').value.trim() : '';
    const projects = document.getElementById('pf_projects') ? document.getElementById('pf_projects').value.trim() : '';
    const education = document.getElementById('pf_education') ? document.getElementById('pf_education').value.trim() : '';
    const visibility = (document.getElementById('pf_visibility') && document.getElementById('pf_visibility').checked) ? 'public' : 'private';

    // Social links
    const linkedin = document.getElementById('pf_linkedin') ? document.getElementById('pf_linkedin').value.trim() : '';
    const twitter = document.getElementById('pf_twitter') ? document.getElementById('pf_twitter').value.trim() : '';
    const github = document.getElementById('pf_github') ? document.getElementById('pf_github').value.trim() : '';
    const facebook = document.getElementById('pf_facebook') ? document.getElementById('pf_facebook').value.trim() : '';
    const instagram = document.getElementById('pf_instagram') ? document.getElementById('pf_instagram').value.trim() : '';
    const website = document.getElementById('pf_website') ? document.getElementById('pf_website').value.trim() : '';

    if (!name) {
        Swal.fire({icon:'warning', title:'Required', text:'Name cannot be empty.', confirmButtonColor:'#0A2D5E'});
        return;
    }

    const fd = new URLSearchParams();
    fd.append('ajax_action', 'update_profile');
    fd.append('name', name);
    fd.append('phone', phone);
    fd.append('company', company);
    fd.append('bio', bio);
    fd.append('profession', profession);
    fd.append('profile_slug', slug);
    fd.append('skills', skills);
    fd.append('tech_stack', techStack);
    fd.append('brief_history', history);
    fd.append('previous_experience', experience);
    fd.append('projects_developed', projects);
    fd.append('education', education);
    fd.append('profile_visibility', visibility);
    fd.append('linkedin_url', linkedin);
    fd.append('twitter_url', twitter);
    fd.append('github_url', github);
    fd.append('facebook_url', facebook);
    fd.append('instagram_url', instagram);
    fd.append('website_url', website);

    fetch('profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.slug && document.getElementById('shareableLinkDisplay')) {
                document.getElementById('pf_slug').value = data.slug;
                const proto = window.location.protocol;
                const host = window.location.host;
                const url = `${proto}//${host}/dashboard/wqs/dev_profile.php?u=${data.slug}`;
                const disp = document.getElementById('shareableLinkDisplay');
                disp.textContent = url;
                // Show the buttons if hidden
                const btnContainer = disp.closest('.d-flex');
                if (btnContainer && btnContainer.querySelector('button')) {
                    // already has buttons
                } else {
                    // rebuild the buttons area
                    const parent = disp.parentElement;
                    if (parent && !parent.querySelector('button')) {
                        parent.innerHTML = `<span style="font-size:0.92rem;font-weight:700;color:#0A2D5E;word-break:break-all;" id="shareableLinkDisplay">${url}</span>
                            <button onclick="copyProfileLink()" style="background:white;border:1px solid #e2e8f0;border-radius:8px;padding:0.35rem 0.7rem;font-size:0.72rem;font-weight:700;color:#64748b;cursor:pointer;transition:all 0.2s;white-space:nowrap;flex-shrink:0;" onmouseover="this.style.borderColor='#0A2D5E';this.style.color='#0A2D5E';" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b';">
                                <i class="fas fa-copy me-1"></i>Copy
                            </button>
                            <a href="../dev_profile.php?u=${data.slug}" target="_blank" style="background:#0A2D5E;color:white;border:none;border-radius:8px;padding:0.35rem 0.9rem;font-size:0.72rem;font-weight:700;text-decoration:none;transition:all 0.2s;white-space:nowrap;flex-shrink:0;" onmouseover="this.style.background='#163f7a';" onmouseout="this.style.background='#0A2D5E';">
                                <i class="fas fa-external-link-alt me-1"></i>Open
                            </a>`;
                    }
                }
            }
            Swal.fire({icon:'success', title:'Profile Updated!', text:'Your profile details have been saved.', confirmButtonColor:'#0A2D5E', timer:2500});
        } else {
            Swal.fire({icon:'error', title:'Failed', text: data.message || 'Could not update profile.', confirmButtonColor:'#dc3545'});
        }
    });
}

function changePassword() {
    const current = document.getElementById('pw_current').value;
    const newpw   = document.getElementById('pw_new').value;
    const confirm = document.getElementById('pw_confirm').value;

    if (!current || !newpw || !confirm) {
        Swal.fire({icon:'warning', title:'Required', text:'Fill in all password fields.', confirmButtonColor:'#0A2D5E'});
        return;
    }
    if (newpw !== confirm) {
        Swal.fire({icon:'warning', title:'Mismatch', text:'New passwords do not match.', confirmButtonColor:'#0A2D5E'});
        return;
    }

    fetch('profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_password&current_password=${encodeURIComponent(current)}&new_password=${encodeURIComponent(newpw)}&confirm_password=${encodeURIComponent(confirm)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('pw_current').value = '';
            document.getElementById('pw_new').value = '';
            document.getElementById('pw_confirm').value = '';
            Swal.fire({icon:'success', title:'Password Changed!', text:'Your password has been updated successfully.', confirmButtonColor:'#0A2D5E', timer:3000});
        } else {
            Swal.fire({icon:'error', title:'Failed', text: data.message || 'Password update failed.', confirmButtonColor:'#dc3545'});
        }
    });
}

function copyProfileLink() {
    const link = document.getElementById('shareableLinkDisplay');
    if (!link) return;
    const text = link.textContent.trim();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({icon:'success', title:'Copied!', text:'Profile link copied to clipboard.', confirmButtonColor:'#0A2D5E', timer:1800, showConfirmButton:false});
        }).catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}
function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    Swal.fire({icon:'success', title:'Copied!', text:'Profile link copied to clipboard.', confirmButtonColor:'#0A2D5E', timer:1800, showConfirmButton:false});
}

function uploadAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const fd = new FormData();
    fd.append('ajax_action', 'upload_avatar');
    fd.append('avatar', file);

    Swal.fire({
        title: 'Uploading...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('profile.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            const disp = document.getElementById('profileAvatarDisplay');
            const plch = document.getElementById('profileAvatarPlaceholder');
            if (disp) { disp.src = data.picture; }
            else if (plch) {
                const img = document.createElement('img');
                img.src = data.picture;
                img.className = 'profile-avatar-img';
                img.id = 'profileAvatarDisplay';
                plch.replaceWith(img);
            }
            Swal.fire({icon:'success', title:'Avatar Updated!', confirmButtonColor:'#0A2D5E', timer:2000});
        } else {
            Swal.fire({icon:'error', title:'Upload Failed', text: data.message || 'Try again.', confirmButtonColor:'#dc3545'});
        }
    });
}

function saveNotifPrefs() {
    Swal.fire({icon:'success', title:'Preferences Saved!', text:'Your notification preferences have been updated.', confirmButtonColor:'#0A2D5E', timer:2500});
}

function updateSessionTimeout() {
    const timeout = document.getElementById('session_timeout').value;
    const fd = new FormData();
    fd.append('ajax_action', 'update_session_timeout');
    fd.append('timeout', timeout);

    fetch('profile.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({icon:'success', title:'Timeout Updated!', text:'Your session auto-logout time has been saved.', confirmButtonColor:'#0A2D5E', timer:2500});
        } else {
            Swal.fire({icon:'error', title:'Failed', text: data.message || 'Failed to save session timeout.', confirmButtonColor:'#dc3545'});
        }
    })
    .catch(() => Swal.fire({icon:'error', title:'Error', text:'Network error.', confirmButtonColor:'#dc3545'}));
}

// Auto-resize textareas to avoid scrolling
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea.form-control-premium');
    textareas.forEach(textarea => {
        autoResizeTextarea(textarea);
        textarea.addEventListener('input', function() {
            autoResizeTextarea(this);
        });
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
