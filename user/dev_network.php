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

$page_title = "Developer Network";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch developers
$stmt = $pdo->query("SELECT id, name, email, profile_slug, picture, role, skills, profession, profile_visibility 
                     FROM users 
                     WHERE role IN ('developer', 'agent', 'admin') 
                     ORDER BY name ASC");
$developers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.network-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #00122e 100%);
    border-radius: 20px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.network-hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    margin-bottom: 0.5rem;
}
.network-hero-text {
    color: rgba(255,255,255,0.7);
    margin-bottom: 0;
}

.dev-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
}
.dev-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}

.dev-avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 1rem;
    border: 3px solid #f8fafc;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.dev-avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, #0A2D5E, #E15501);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 800;
    border: 3px solid #f8fafc;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.dev-name {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.2rem;
}
.dev-profession {
    color: #E15501;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.skill-badge {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.6rem;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-block;
    margin: 0.15rem;
}

.dev-actions {
    margin-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
    padding-top: 1.5rem;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.dev-private-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(225, 85, 1, 0.1);
    color: #E15501;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
}
</style>

<div class="container-fluid py-2">
    <div class="mb-3">
        <a href="dev_hub.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-1"></i> Back to Hub</a>
    </div>

    <div class="network-hero">
        <div>
            <h2 class="network-hero-title">Developer Network</h2>
            <p class="network-hero-text">Discover, connect, and collaborate with other developers on the platform.</p>
        </div>
        <div>
            <i class="fas fa-project-diagram fa-3x text-white opacity-25"></i>
        </div>
    </div>

    <!-- Search/Filter -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text border-0 bg-transparent text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="devSearch" class="form-control border-0 shadow-none" placeholder="Search by name, skill, or profession...">
            </div>
        </div>
    </div>

    <div class="row g-4" id="devGrid">
        <?php foreach ($developers as $dev): 
            $skills = array_filter(array_map('trim', explode(',', $dev['skills'] ?? '')));
            $isPrivate = ($dev['profile_visibility'] === 'private');
        ?>
        <div class="col-md-4 col-lg-3 dev-item" data-search="<?= strtolower(htmlspecialchars($dev['name'] . ' ' . $dev['profession'] . ' ' . ($dev['skills'] ?? ''))) ?>">
            <div class="dev-card">
                <?php if ($isPrivate): ?>
                    <div class="dev-private-badge"><i class="fas fa-lock"></i> Private</div>
                <?php endif; ?>

                <?php if (!empty($dev['picture'])): ?>
                    <?php 
                        $pic = $dev['picture'];
                        if (strpos($pic, '../') === 0) {
                            $pic = substr($pic, 3);
                        }
                        if($pic && strpos($pic, '/') !== 0) $pic = '/' . $pic;
                    ?>
                    <img src="<?= htmlspecialchars($pic) ?>" alt="<?= htmlspecialchars($dev['name']) ?>" class="dev-avatar">
                <?php else: ?>
                    <div class="dev-avatar-placeholder">
                        <?= strtoupper(substr($dev['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <h4 class="dev-name"><?= htmlspecialchars($dev['name']) ?></h4>
                <div class="dev-profession"><?= htmlspecialchars($dev['profession'] ?: ucfirst($dev['role'])) ?></div>

                <div class="mb-3">
                    <?php if (!empty($skills)): ?>
                        <?php 
                        // Show max 3 skills
                        $showSkills = array_slice($skills, 0, 3);
                        foreach ($showSkills as $s): ?>
                            <span class="skill-badge"><?= htmlspecialchars($s) ?></span>
                        <?php endforeach; ?>
                        <?php if(count($skills) > 3): ?>
                            <span class="skill-badge bg-light text-muted">+<?= count($skills)-3 ?> more</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small">No skills listed</span>
                    <?php endif; ?>
                </div>

                <div class="dev-actions">
                    <a href="mailto:<?= htmlspecialchars($dev['email']) ?>" class="btn btn-sm btn-light" title="Email"><i class="fas fa-envelope text-muted"></i></a>
                    <a href="../dev_profile.php?u=<?= htmlspecialchars($dev['profile_slug']) ?>" target="_blank" class="btn btn-sm" style="background: rgba(10,45,94,0.05); color: #0A2D5E; font-weight: 600;">View Profile</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.getElementById('devSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.dev-item').forEach(item => {
        const searchData = item.getAttribute('data-search');
        if (searchData.includes(term)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
