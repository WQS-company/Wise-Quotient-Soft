<?php
$page_title = 'Search Results | Wise Quotient Soft';
require_once __DIR__ . '/includes/public_header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$terms = array_filter(explode(' ', $q));

$services_results = [];
$projects_results = [];
$leadership_results = [];
$total_results = 0;

if (!empty($terms)) {
    // 1. Search Services
    $service_clauses = [];
    $service_params = [];
    foreach ($terms as $term) {
        $service_clauses[] = "(name LIKE ? OR description LIKE ? OR features LIKE ?)";
        $service_params[] = "%$term%";
        $service_params[] = "%$term%";
        $service_params[] = "%$term%";
    }
    
    // Try AND match
    $sql = "SELECT * FROM services WHERE is_active = 1 AND " . implode(" AND ", $service_clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($service_params);
    $services_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback to OR match if empty
    if (empty($services_results)) {
        $sql = "SELECT * FROM services WHERE is_active = 1 AND (" . implode(" OR ", $service_clauses) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($service_params);
        $services_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Search Portfolio Projects
    $project_clauses = [];
    $project_params = [];
    foreach ($terms as $term) {
        $project_clauses[] = "(p.title LIKE ? OR p.description LIKE ?)";
        $project_params[] = "%$term%";
        $project_params[] = "%$term%";
    }
    
    // Try AND match
    $sql = "SELECT p.*, 
            (SELECT image_path FROM project_images WHERE project_id = p.id ORDER BY id ASC LIMIT 1) as cover_image 
            FROM projects p WHERE p.is_visible = 1 AND " . implode(" AND ", $project_clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($project_params);
    $projects_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback to OR match if empty
    if (empty($projects_results)) {
        $sql = "SELECT p.*, 
                (SELECT image_path FROM project_images WHERE project_id = p.id ORDER BY id ASC LIMIT 1) as cover_image 
                FROM projects p WHERE p.is_visible = 1 AND (" . implode(" OR ", $project_clauses) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($project_params);
        $projects_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Search Leadership Team
    $leadership_clauses = [];
    $leadership_params = [];
    foreach ($terms as $term) {
        $leadership_clauses[] = "(u.name LIKE ? OR lt.designation LIKE ? OR lt.bio LIKE ?)";
        $leadership_params[] = "%$term%";
        $leadership_params[] = "%$term%";
        $leadership_params[] = "%$term%";
    }
    
    // Try AND match
    $sql = "SELECT lt.*, u.name, u.picture FROM leadership_team lt 
            JOIN users u ON lt.user_id = u.id 
            WHERE " . implode(" AND ", $leadership_clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($leadership_params);
    $leadership_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback to OR match if empty
    if (empty($leadership_results)) {
        $sql = "SELECT lt.*, u.name, u.picture FROM leadership_team lt 
                JOIN users u ON lt.user_id = u.id 
                WHERE (" . implode(" OR ", $leadership_clauses) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($leadership_params);
        $leadership_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $total_results = count($services_results) + count($projects_results) + count($leadership_results);
}
?>

<style>
  /* ===== SEARCH PAGE CUSTOM PREMIUM STYLES ===== */
  .search-hero {
    background: radial-gradient(circle at 10% 20%, rgba(10, 45, 94, 0.45) 0%, rgba(3, 7, 18, 0.95) 80%), #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .search-hero-glow {
    position: absolute; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, transparent 70%);
    top: -50px; left: 50%; transform: translateX(-50%); filter: blur(80px); pointer-events: none;
  }
  
  .search-bar-premium {
    max-width: 650px;
    margin: 1.5rem auto 0;
    position: relative;
    z-index: 10;
  }
  .search-bar-premium input {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50px;
    color: white;
    padding: 0.9rem 1.5rem 0.9rem 3rem;
    font-size: 1.05rem;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
  }
  .search-bar-premium input:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: #ff6600;
    box-shadow: 0 0 15px rgba(255, 102, 0, 0.25);
    color: white;
  }
  .search-bar-premium .search-icon {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    font-size: 1.1rem;
    pointer-events: none;
  }
  .search-bar-premium button[type="submit"] {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: #ff6600;
    color: white;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.3s;
  }
  .search-bar-premium button[type="submit"]:hover {
    background: #e65c00;
    box-shadow: 0 4px 12px rgba(255, 102, 0, 0.3);
  }

  .nav-pills-custom .nav-link {
    color: #64748b;
    font-weight: 700;
    font-size: 0.9rem;
    border-radius: 50px;
    padding: 0.5rem 1.25rem;
    transition: all 0.25s ease;
    border: 1px solid #e2e8f0;
    background: white;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
  }
  .nav-pills-custom .nav-link:hover {
    color: #ff6600;
    border-color: #ff6600;
  }
  .nav-pills-custom .nav-link.active {
    background: #ff6600 !important;
    border-color: #ff6600 !important;
    color: white !important;
    box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2);
  }

  /* Section Header styling */
  .section-result-header {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 1.35rem;
    color: #00264d;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .result-count-badge {
    background: rgba(10, 45, 94, 0.08);
    color: #00264d;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
  }

  /* Result Cards */
  .result-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
  }
  .result-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.05);
    border-color: #ff6600;
  }
  .result-tag {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #ff6600;
    background: rgba(255, 102, 0, 0.08);
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 0.75rem;
  }

  /* Service Card styling */
  .svc-badge-circle {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  }

  /* Project Image styling */
  .proj-result-image {
    width: 100%;
    height: 160px;
    border-radius: 12px;
    object-fit: cover;
    margin-bottom: 1rem;
    border: 1px solid #f1f5f9;
  }
  .proj-placeholder {
    width: 100%;
    height: 160px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 2rem;
    margin-bottom: 1rem;
  }

  /* Leader avatar styling */
  .leader-avatar-result {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
    margin-bottom: 1rem;
  }
  .leader-avatar-fallback {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00264d 0%, #ff6600 100%);
    color: white;
    font-weight: 800;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  }

  /* Premium 404 Fallback styles */
  .premium-404-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    padding: 4rem 2rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.02);
    text-align: center;
    max-width: 800px;
    margin: 2rem auto;
  }
  .premium-404-icon {
    font-size: 4.5rem;
    color: #ff6600;
    margin-bottom: 1.5rem;
    display: inline-block;
    animation: pulse 2s infinite alternate;
  }
  @keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.9; }
    100% { transform: scale(1.05); opacity: 1; }
  }
  .premium-404-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    color: #00264d;
    margin-bottom: 1rem;
  }
  .tips-list {
    max-width: 500px;
    margin: 1.5rem auto;
    text-align: left;
    background:var(--color-bg);
    border-radius: 16px;
    padding: 1.25rem 1.75rem;
    border: 1px solid #e2e8f0;
  }
  .tips-list li {
    font-size: 0.88rem;
    color: #475569;
    margin-bottom: 0.5rem;
  }
  .tips-list li:last-child {
    margin-bottom: 0;
  }
  .badge-category-trend {
    background: #eef2f6;
    color: #0f3a5d;
    border: 1px solid #e2e8f0;
    padding: 0.45rem 1rem;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-block;
    margin: 0.25rem;
  }
  .badge-category-trend:hover {
    background: #ff6600;
    color: white;
    border-color: #ff6600;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2);
  }
</style>

<!-- Search Hero Section -->
<section class="search-hero">
  <div class="search-hero-glow"></div>
  <div class="container position-relative" style="z-index: 10;">
    <span class="badge mb-2" style="background: rgba(255, 102, 0, 0.15); color: #ff6600; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(255, 102, 0, 0.3); padding: 0.5rem 1.2rem; border-radius: 50px;">
      Explore WQS
    </span>
    <h1 class="fw-extrabold text-white mb-2" style="font-size: clamp(1.8rem, 4vw, 2.8rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">
      Search Results
    </h1>
    <?php if ($q): ?>
      <p class="text-white-50 fs-5 mb-3">Showing matches for &ldquo;<strong class="text-white"><?= htmlspecialchars($q) ?></strong>&rdquo;</p>
    <?php else: ?>
      <p class="text-white-50 fs-5 mb-3">Looking for something specific? Search across our platforms.</p>
    <?php endif; ?>

    <!-- Header Search Bar -->
    <div class="search-bar-premium">
      <form action="search.php" method="GET">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="q" class="form-control shadow-none" placeholder="Search services, portfolio, or leadership team..." required value="<?= htmlspecialchars($q) ?>" />
        <button type="submit">Search</button>
      </form>
    </div>
  </div>
</section>

<!-- Content Container -->
<div class="container py-5" style="background-color: #f8fafc; min-height: 50vh;">

  <?php if ($total_results > 0): ?>
    
    <!-- Navigation Tabs / Category Filter Pills -->
    <div class="d-flex flex-wrap justify-content-center justify-content-md-start mb-5 nav-pills-custom">
      <button class="nav-link active" onclick="filterResults('all')">
        All Results <span class="badge bg-secondary ms-1 rounded-pill"><?= $total_results ?></span>
      </button>
      <?php if (!empty($services_results)): ?>
        <button class="nav-link" onclick="filterResults('services')">
          Services & Pricing <span class="badge bg-secondary ms-1 rounded-pill"><?= count($services_results) ?></span>
        </button>
      <?php endif; ?>
      <?php if (!empty($projects_results)): ?>
        <button class="nav-link" onclick="filterResults('projects')">
          Portfolio Projects <span class="badge bg-secondary ms-1 rounded-pill"><?= count($projects_results) ?></span>
        </button>
      <?php endif; ?>
      <?php if (!empty($leadership_results)): ?>
        <button class="nav-link" onclick="filterResults('leadership')">
          Leadership Team <span class="badge bg-secondary ms-1 rounded-pill"><?= count($leadership_results) ?></span>
        </button>
      <?php endif; ?>
    </div>

    <!-- Results Display -->
    <div class="row g-4">
      
      <!-- 1. Services section -->
      <?php if (!empty($services_results)): ?>
        <div class="col-12 result-section-group" id="section-services">
          <div class="section-result-header">
            <span>Services & Pricing Plans</span>
            <span class="result-count-badge"><?= count($services_results) ?> Matches</span>
          </div>
          <div class="row g-4 mb-5">
            <?php foreach ($services_results as $svc): ?>
              <div class="col-md-6 col-lg-4">
                <div class="result-card">
                  <div class="d-flex justify-content-between align-items-start">
                    <span class="result-tag"><?= htmlspecialchars($svc['category'] === 'pricing' ? 'Pricing Plan' : 'Service') ?></span>
                    <span class="svc-badge-circle" style="background: <?= htmlspecialchars($svc['border_color'] ?: '#ff6600') ?>;">
                      <i class="<?= htmlspecialchars($svc['icon'] ?: 'fas fa-cogs') ?>"></i>
                    </span>
                  </div>
                  <h4 class="fw-bold text-body mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem;">
                    <?= htmlspecialchars($svc['name']) ?>
                  </h4>
                  <p class="text-muted mb-3" style="font-size: 0.85rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.6;">
                    <?= htmlspecialchars($svc['description'] ?: 'No description available.') ?>
                  </p>
                  
                  <?php if ($svc['category'] === 'pricing' && !empty($svc['price'])): ?>
                    <div class="fw-extrabold fs-5 text-body mb-3">
                      <?= htmlspecialchars($svc['currency']) ?><?= number_format($svc['price'], 0) ?>
                      <span class="text-muted fs-6 font-monospace" style="font-weight: 500;"><?= htmlspecialchars($svc['price_label']) ?></span>
                    </div>
                  <?php endif; ?>

                  <div class="mt-auto">
                    <a href="services.php" class="btn btn-outline-dark-text btn-sm w-100 rounded-pill">View All Services</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- 2. Projects section -->
      <?php if (!empty($projects_results)): ?>
        <div class="col-12 result-section-group" id="section-projects">
          <div class="section-result-header">
            <span>Portfolio Projects</span>
            <span class="result-count-badge"><?= count($projects_results) ?> Matches</span>
          </div>
          <div class="row g-4 mb-5">
            <?php foreach ($projects_results as $proj): ?>
              <div class="col-md-6 col-lg-4">
                <div class="result-card">
                  <?php if (!empty($proj['cover_image'])): ?>
                    <img src="admin/<?= htmlspecialchars($proj['cover_image']) ?>" class="proj-result-image" alt="<?= htmlspecialchars($proj['title']) ?>">
                  <?php else: ?>
                    <div class="proj-placeholder"><i class="fas fa-briefcase"></i></div>
                  <?php endif; ?>
                  
                  <span class="result-tag">Project File</span>
                  <h4 class="fw-bold text-body mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem;">
                    <?= htmlspecialchars($proj['title']) ?>
                  </h4>
                  <p class="text-muted mb-3" style="font-size: 0.85rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.6;">
                    <?= strip_tags($proj['description']) ?>
                  </p>
                  <div class="mt-auto">
                    <a href="project_details.php?id=<?= wqs_encrypt_id($proj['id']) ?>" class="btn btn-orange btn-sm w-100 rounded-pill">View Case Study</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- 3. Leadership Team section -->
      <?php if (!empty($leadership_results)): ?>
        <div class="col-12 result-section-group" id="section-leadership">
          <div class="section-result-header">
            <span>Leadership Team Members</span>
            <span class="result-count-badge"><?= count($leadership_results) ?> Matches</span>
          </div>
          <div class="row g-4 mb-5">
            <?php foreach ($leadership_results as $lead): ?>
              <div class="col-md-6 col-lg-4">
                <div class="result-card text-center">
                  <div class="d-flex justify-content-center">
                    <?php if (!empty($lead['picture'])): ?>
                      <img src="<?= htmlspecialchars($lead['picture']) ?>" class="leader-avatar-result" alt="<?= htmlspecialchars($lead['name']) ?>">
                    <?php else: ?>
                      <div class="leader-avatar-fallback mx-auto">
                        <?php
                          $parts = explode(' ', $lead['name']);
                          $initials = '';
                          foreach ($parts as $p) $initials .= strtoupper($p[0] ?? '');
                          echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <span class="result-tag">Leadership</span>
                  <h4 class="fw-bold text-body mb-1" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem;">
                    <?= htmlspecialchars($lead['name']) ?>
                  </h4>
                  <div class="text-orange small fw-bold mb-3" style="font-size: 0.78rem; letter-spacing: 1px; text-transform: uppercase;">
                    <?= htmlspecialchars($lead['designation']) ?>
                  </div>
                  <p class="text-muted mb-4 small" style="line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                    <?= htmlspecialchars($lead['bio']) ?>
                  </p>
                  
                  <div class="mt-auto d-flex justify-content-center gap-2">
                    <a href="about.php" class="btn btn-outline-dark-text btn-sm rounded-pill w-100">About Our Team</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

  <?php else: ?>
    
    <!-- Premium "404 - No Results Found" fallback panel -->
    <div class="premium-404-card">
      <span class="premium-404-icon"><i class="fas fa-search-minus"></i></span>
      <h2 class="premium-404-title">No Matches Found</h2>
      <p class="text-muted fs-6">We couldn't find any results matching &ldquo;<strong><?= htmlspecialchars($q) ?></strong>&rdquo;.</p>
      
      <div class="tips-list">
        <h6 class="fw-bold text-body mb-2"><i class="far fa-lightbulb text-warning me-2"></i>Search Recommendations:</h6>
        <ul class="list-unstyled mb-0 ps-1">
          <li class="mb-2"><i class="fas fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>Check spelling and keywords</li>
          <li class="mb-2"><i class="fas fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>Use more general terms or search filters</li>
          <li class="mb-2"><i class="fas fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>Search by categories (e.g. &quot;Trust&quot;, &quot;SaaS&quot;, &quot;Joseph&quot;)</li>
        </ul>
      </div>

      <div class="mt-4">
        <h5 class="fw-bold mb-3" style="font-size: 1.05rem; color: #00264d;">Browse Popular Links</h5>
        <div class="d-flex flex-wrap justify-content-center">
          <a href="index.php" class="badge-category-trend">Home</a>
          <a href="services.php" class="badge-category-trend">Services & Plans</a>
          <a href="about.php" class="badge-category-trend">About Us</a>
          <a href="portforlio.php" class="badge-category-trend">Portfolio</a>
          <a href="blog.php" class="badge-category-trend">Blog</a>
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>

<script>
  function filterResults(type) {
    // Update active tab styles
    const buttons = document.querySelectorAll('.nav-pills-custom button');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    // Find the button representing this action and highlight it
    event.currentTarget.classList.add('active');

    // Filter cards display
    const sections = document.querySelectorAll('.result-section-group');
    if (type === 'all') {
      sections.forEach(sec => sec.style.display = 'block');
    } else {
      sections.forEach(sec => {
        if (sec.id === 'section-' + type) {
          sec.style.display = 'block';
        } else {
          sec.style.display = 'none';
        }
      });
    }
  }
</script>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
