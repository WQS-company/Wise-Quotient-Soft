<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: ../login.php"); exit; }

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];
$userRole = strtolower($_SESSION['user']['role']);

// Allow developers, agents, and admins
if (!in_array($userRole, ['developer', 'admin', 'agent'])) {
    header("Location: ../index.php"); exit;
}

$page_title = "Developers Hub";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch quick stats
$devCount = $db->query("SELECT COUNT(*) as c FROM users WHERE role IN ('developer','agent','admin')")->fetch_assoc()['c'] ?? 0;
$discCount = $db->query("SELECT COUNT(*) as c FROM hub_discussions")->fetch_assoc()['c'] ?? 0;
$snipCount = $db->query("SELECT COUNT(*) as c FROM hub_code_snippets")->fetch_assoc()['c'] ?? 0;
?>

<style>
.hub-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #1a4a8c 100%);
    border-radius: 20px;
    padding: 3.5rem 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(10, 45, 94, 0.15);
}
.hub-hero::before {
    content:''; position:absolute; top:-100px; right:-100px;
    width:400px; height:400px;
    background: radial-gradient(circle, rgba(225,85,1,0.2) 0%, transparent 70%);
    border-radius:50%; pointer-events:none;
}
.hub-hero::after {
    content:''; position:absolute; bottom:-100px; left:-100px;
    width:300px; height:300px;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
    border-radius:50%; pointer-events:none;
}

.hub-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 2.5rem 2rem;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    display: block;
    color: #0f172a;
}
.hub-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border-color: #cbd5e1;
    color: #0f172a;
}
.hub-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: transparent;
    transition: all 0.3s ease;
}
.hub-card.hc-blue:hover::before { background: #0A2D5E; }
.hub-card.hc-orange:hover::before { background: #E15501; }
.hub-card.hc-purple:hover::before { background: #6366f1; }

.hub-icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1.5rem;
    transition: all 0.3s ease;
}
.hub-card.hc-blue .hub-icon-wrapper { background: rgba(10, 45, 94, 0.1); color: #0A2D5E; }
.hub-card.hc-orange .hub-icon-wrapper { background: rgba(225, 85, 1, 0.1); color: #E15501; }
.hub-card.hc-purple .hub-icon-wrapper { background: rgba(99, 102, 241, 0.1); color: #6366f1; }

.hub-card:hover .hub-icon-wrapper {
    transform: scale(1.1);
}
.hub-card.hc-blue:hover .hub-icon-wrapper { background: #0A2D5E; color: white; }
.hub-card.hc-orange:hover .hub-icon-wrapper { background: #E15501; color: white; }
.hub-card.hc-purple:hover .hub-icon-wrapper { background: #6366f1; color: white; }

.hub-card-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
}
.hub-card-text {
    color: #64748b;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.hub-stat-badge {
    display: inline-block;
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.hub-card.hc-blue .hub-stat-badge { background: #f1f5f9; color: #475569; }
.hub-card.hc-orange .hub-stat-badge { background: #f1f5f9; color: #475569; }
.hub-card.hc-purple .hub-stat-badge { background: #f1f5f9; color: #475569; }
</style>

<div class="container-fluid py-2">
    <!-- Hero Section -->
    <div class="hub-hero">
        <div class="position-relative" style="z-index: 10;">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span style="background: rgba(255,255,255,0.2); color: white; padding: 0.3rem 1rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                    <i class="fas fa-satellite-dish me-1"></i> WQS Developers
                </span>
            </div>
            <h1 class="display-5 fw-extrabold mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif;">Developers Hub</h1>
            <p style="font-size: 1.1rem; color: rgba(255,255,255,0.8); max-width: 600px; margin-bottom: 0;">
                Connect with the engineering community, share knowledge, ask questions, and collaborate on advanced code snippets.
            </p>
        </div>
    </div>

    <!-- Navigation Cards -->
    <div class="row g-4 mb-5">
        
        <!-- Network -->
        <div class="col-md-4">
            <a href="dev_network.php" class="hub-card hc-blue">
                <div class="hub-icon-wrapper">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h3 class="hub-card-title">Developer Network</h3>
                <p class="hub-card-text">
                    Browse the directory of verified developers on the platform. View skills, profiles, and expand your professional connections.
                </p>
                <div class="mt-auto">
                    <span class="hub-stat-badge"><?= $devCount ?> Members</span>
                </div>
            </a>
        </div>

        <!-- Discussions -->
        <div class="col-md-4">
            <a href="dev_hub_discussions.php" class="hub-card hc-orange">
                <div class="hub-icon-wrapper">
                    <i class="fas fa-comments"></i>
                </div>
                <h3 class="hub-card-title">Community Discussions</h3>
                <p class="hub-card-text">
                    Stuck on a bug? Want to discuss architecture? Open a thread and get answers from the community.
                </p>
                <div class="mt-auto">
                    <span class="hub-stat-badge"><?= $discCount ?> Active Threads</span>
                </div>
            </a>
        </div>

        <!-- Snippets -->
        <div class="col-md-4">
            <a href="dev_hub_snippets.php" class="hub-card hc-purple">
                <div class="hub-icon-wrapper">
                    <i class="fab fa-github"></i>
                </div>
                <h3 class="hub-card-title">Code & Repos</h3>
                <p class="hub-card-text">
                    Share your genius code snippets, explore advanced algorithms from others, and link your open-source GitHub repositories.
                </p>
                <div class="mt-auto">
                    <span class="hub-stat-badge"><?= $snipCount ?> Snippets Shared</span>
                </div>
            </a>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
