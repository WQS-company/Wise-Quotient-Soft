<?php
session_start();
require_once __DIR__ . '/config.php';

$slug = $_GET['u'] ?? '';

if (!$slug) {
    die("Profile not found.");
}

// Fetch the user
$stmt = $pdo->prepare("SELECT * FROM users WHERE profile_slug = ? AND role IN ('developer', 'agent', 'admin', 'user')");
$stmt->execute([$slug]);
$dev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dev) {
    die("Profile not found.");
}

// Check Privacy
$isPrivate = ($dev['profile_visibility'] === 'private');
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';

if ($isPrivate && !$isAdmin) {
    // Show premium private profile screen
    $isAccessDenied = true;
} else {
    $isAccessDenied = false;
}

$page_title = htmlspecialchars($dev['name']) . " | Professional Profile";
require_once __DIR__ . '/includes/public_header.php';
?>
    
    <style>
        :root {
            --primary-blue: #0A2D5E;
            --primary-orange: #E15501;
            --bg-color: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.85);
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }
        .dev-profile-wrapper {
            background-color: var(--bg-color);
            color: var(--text-dark);
            min-height: 100vh;
            background-image: 
                radial-gradient(at 0% 0%, rgba(10, 45, 94, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(225, 85, 1, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
            padding-bottom: 3rem;
        }
        h1, h2, h3, h4, h5, h6, .display-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }
        
        .hero-banner {
            height: 250px;
            background: linear-gradient(135deg, var(--primary-blue), #1e4b8a);
            position: relative;
            overflow: hidden;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            margin-bottom: 100px;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.5;
        }
        
        .profile-avatar-container {
            position: absolute;
            bottom: -80px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            z-index: 10;
        }
        
        .profile-avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 6px solid var(--bg-color);
            background-color: white;
            object-fit: cover;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .profile-avatar-placeholder {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 6px solid var(--bg-color);
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            font-weight: 800;
            font-family: 'Plus Jakarta Sans', sans-serif;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        
        .skill-badge {
            background: rgba(10, 45, 94, 0.08);
            color: var(--primary-blue);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
            margin: 0.25rem;
            border: 1px solid rgba(10, 45, 94, 0.1);
            transition: all 0.2s ease;
        }
        .skill-badge:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        .tech-badge {
            background: rgba(225, 85, 1, 0.08);
            color: var(--primary-orange);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
            margin: 0.25rem;
            border: 1px solid rgba(225, 85, 1, 0.1);
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .section-title i {
            color: var(--primary-orange);
            background: rgba(225, 85, 1, 0.1);
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
        }
        
        .formatted-text {
            line-height: 1.8;
            color: var(--text-muted);
            white-space: pre-wrap;
            font-size: 0.95rem;
        }
        
        /* Private State */
        .private-state-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 60vh;
            text-align: center;
        }
        .private-icon {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        
        /* Contact Button */
        .btn-hire {
            background: linear-gradient(135deg, var(--primary-orange), #ff7b00);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px rgba(225, 85, 1, 0.2);
            transition: all 0.3s ease;
        }
        .btn-hire:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(225, 85, 1, 0.3);
            color: white;
        }
    </style>

<div class="dev-profile-wrapper">
<?php if ($isAccessDenied): ?>
    <div class="container">
        <div class="private-state-container">
            <i class="fas fa-lock private-icon"></i>
            <h2 class="display-font fw-bold" style="color: var(--primary-blue);">Profile is Private</h2>
            <p class="text-muted" style="max-width: 400px; font-size: 1.1rem;">
                <?= htmlspecialchars($dev['name']) ?> has chosen to keep their professional profile private.
            </p>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 mt-3">Return Home</a>
        </div>
    </div>
<?php else: ?>
    <!-- Public Profile -->
    <div class="hero-banner">
        <div class="profile-avatar-container">
            <?php if (!empty($dev['picture'])): ?>
                <?php 
                    // Remove leading '../' if picture path is relative to user dir
                    $pic = ltrim($dev['picture'], '../');
                    // Add leading slash if missing
                    if(strpos($pic, '/') !== 0) $pic = '/' . $pic;
                ?>
                <img src="<?= htmlspecialchars($pic) ?>" alt="Profile Picture" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?= strtoupper(substr($dev['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container" style="max-width: 900px; position: relative; z-index: 20;">
        <!-- Header Info -->
        <div class="text-center mb-5 mt-4">
            <h1 class="display-font fw-bold mb-1" style="color: var(--primary-blue); font-size: 2.5rem;">
                <?= htmlspecialchars($dev['name']) ?>
            </h1>
            <h4 class="text-orange mb-3" style="color: var(--primary-orange); font-weight: 600;">
                <?= htmlspecialchars($dev['profession'] ?: 'Professional') ?>
            </h4>
            <div class="d-flex justify-content-center gap-3 text-muted" style="font-size: 0.95rem;">
                <?php if (!empty($dev['company'])): ?>
                    <span><i class="fas fa-building me-2"></i><?= htmlspecialchars($dev['company']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-map-marker-alt me-2"></i>Remote / Global</span>
            </div>
            
            <div class="mt-4">
                <a href="mailto:<?= htmlspecialchars($dev['email']) ?>" class="btn btn-hire"><i class="fas fa-paper-plane me-2"></i> Get in Touch</a>
            </div>
            
            <?php if ($isPrivate && $isAdmin): ?>
                <div class="mt-4">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fas fa-eye-slash me-1"></i> Private Profile (Visible to Admin)</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- About & Skills -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="glass-card h-100">
                    <h3 class="section-title"><i class="fas fa-user-circle"></i> About Me</h3>
                    <div class="formatted-text"><?= htmlspecialchars($dev['brief_history'] ?: $dev['bio'] ?: 'No summary provided.') ?></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="glass-card h-100">
                    <h3 class="section-title"><i class="fas fa-bolt"></i> Top Skills</h3>
                    <div>
                        <?php 
                        $skills = array_filter(array_map('trim', explode(',', $dev['skills'] ?? '')));
                        if (!empty($skills)): 
                            foreach($skills as $skill): ?>
                                <span class="skill-badge"><?= htmlspecialchars($skill) ?></span>
                            <?php endforeach; 
                        else: ?>
                            <p class="text-muted italic">No skills listed.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tech Stack -->
        <?php if (!empty($dev['tech_stack'])): ?>
        <div class="glass-card">
            <h3 class="section-title"><i class="fas fa-layer-group"></i> Technologies & Tools</h3>
            <div>
                <?php 
                $techs = array_filter(array_map('trim', explode(',', $dev['tech_stack'] ?? '')));
                foreach($techs as $tech): ?>
                    <span class="tech-badge"><i class="fas fa-check-circle me-1" style="opacity:0.5"></i> <?= htmlspecialchars($tech) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Experience & Projects -->
        <div class="row g-4 mb-5">
            <?php if (!empty($dev['previous_experience'])): ?>
            <div class="col-md-6">
                <div class="glass-card h-100">
                    <h3 class="section-title"><i class="fas fa-briefcase"></i> Experience</h3>
                    <div class="formatted-text"><?= htmlspecialchars($dev['previous_experience']) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($dev['projects_developed'])): ?>
            <div class="col-md-6">
                <div class="glass-card h-100">
                    <h3 class="section-title"><i class="fas fa-laptop-code"></i> Key Projects</h3>
                    <div class="formatted-text"><?= htmlspecialchars($dev['projects_developed']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($dev['education'])): ?>
        <div class="glass-card mb-5">
            <h3 class="section-title"><i class="fas fa-graduation-cap"></i> Education & Certifications</h3>
            <div class="formatted-text"><?= htmlspecialchars($dev['education']) ?></div>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
