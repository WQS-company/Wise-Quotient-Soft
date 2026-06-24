<?php
$page_title = 'Portfolio - Software Projects Showcase | Wise Quotient Soft';
$seo = [
    'title'       => 'Portfolio - Software Projects Showcase | Wise Quotient Soft',
    'description' => 'Explore our portfolio of successful software projects — custom mobile apps, web platforms, fintech solutions, and AI systems built by Wise Quotient Soft.',
    'keywords'    => 'software portfolio, app development portfolio, WQS projects, custom software examples, mobile app showcase, web development portfolio Nigeria',
    'canonical'   => 'https://wisequotientsoft.com/portforlio.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-portfolio.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Portfolio', 'url' => '/portforlio.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';

// Fetch projects and their associated tech stacks / images
$projects = [];
try {
    $projects = $pdo->query("SELECT * FROM projects WHERE is_visible = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as &$project) {
        $stmt = $pdo->prepare("SELECT image_path, caption FROM project_images WHERE project_id = ?");
        $stmt->execute([$project['id']]);
        $raw_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $project['images'] = [];
        foreach ($raw_images as $row) {
            $paths = explode(';', $row['image_path']);
            foreach ($paths as $img) {
                $cleaned = trim($img);
                if (!empty($cleaned)) {
                    $isHttp = strpos($cleaned, 'http') === 0;
                    $project['images'][] = [
                        'path' => $isHttp ? $cleaned : 'admin/' . $cleaned,
                        'caption' => $row['caption'] ?? ''
                    ];
                }
            }
        }

        $stack_stmt = $pdo->prepare("SELECT stack_name FROM project_tech_stacks WHERE project_id = ?");
        $stack_stmt->execute([$project['id']]);
        $project['tech_stacks'] = $stack_stmt->fetchAll(PDO::FETCH_COLUMN);

        $project['enable_download'] = (bool)$project['enable_download'];
    }
} catch (Exception $e) {
    // Fail-safe
}
?>

<!-- ===== HERO SECTION ===== -->
<section class="portfolio-hero">
  <div class="hero-glow-1"></div>
  <div class="hero-glow-2"></div>
  <div class="hero-grid-lines"></div>
  <div class="hero-particles">
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <div class="portfolio-hero-illustration d-none d-lg-block">
    <img src="images/svg/web-development.svg" alt="Web Development" class="img-fluid" style="max-width:340px; opacity:0.07; position:absolute; right:5%; bottom:10%; pointer-events:none;">
  </div>
  <div class="container position-relative text-center" style="z-index:10;">
    <span class="hero-badge mb-4">
      <span class="hero-badge-dot"></span>
      Our Creations
    </span>
    <h1 class="hero-heading">Engineering Showcase <span class="hero-gradient-text">&amp; Projects</span></h1>
    <p class="hero-subtitle mx-auto">Explore our index of state-of-the-art web systems, mobile applications, and enterprise platforms custom-designed by WQS.</p>
    <div class="hero-stats-mini">
      <div class="hero-stat-item">
        <span class="hero-stat-num"><?= count($projects) ?></span>
        <span class="hero-stat-label">Projects</span>
      </div>
      <div class="hero-stat-divider"></div>
      <div class="hero-stat-item">
        <span class="hero-stat-num">
          <?php
            $allStacks = [];
            foreach ($projects as $p) { $allStacks = array_merge($allStacks, $p['tech_stacks']); }
            echo count(array_unique($allStacks));
          ?>
        </span>
        <span class="hero-stat-label">Technologies</span>
      </div>
      <div class="hero-stat-divider"></div>
      <div class="hero-stat-item">
        <span class="hero-stat-num">7+</span>
        <span class="hero-stat-label">Years Experience</span>
      </div>
    </div>
  </div>
  <div class="scroll-indicator">
    <span>Scroll</span>
    <div class="scroll-mouse"><div class="scroll-wheel"></div></div>
  </div>
</section>

<!-- ===== FILTER BAR ===== -->
<div class="container">
  <div class="filter-bar">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 w-100">
      <div class="d-flex flex-wrap gap-2">
        <button class="filter-pill active" onclick="filterCategory('all', event)">All Projects</button>
        <button class="filter-pill" onclick="filterCategory('web', event)">Web Apps</button>
        <button class="filter-pill" onclick="filterCategory('mobile', event)">Mobile Apps</button>
        <button class="filter-pill" onclick="filterCategory('enterprise', event)">Enterprise</button>
      </div>
      <div class="d-flex gap-2 align-items-center flex-shrink-0" style="min-width: 0;">
        <div class="search-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input type="text" id="portfolioSearch" onkeyup="handleSearch()" class="search-input" placeholder="Search projects..." aria-label="Search">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== PORTFOLIO GRID ===== -->
<section class="portfolio-section">
  <div class="container">
    <div class="row g-4" id="portfolioGrid">
      <?php if (!empty($projects)): ?>
        <?php foreach ($projects as $proj):
          $stacksStr = strtolower(implode(' ', $proj['tech_stacks']));
          $category = 'enterprise';
          if (strpos($stacksStr, 'react native') !== false || strpos($stacksStr, 'flutter') !== false || strpos($stacksStr, 'swift') !== false || strpos($stacksStr, 'android') !== false || strpos($stacksStr, 'kotlin') !== false || strpos($stacksStr, 'ios') !== false) {
              $category = 'mobile';
          } elseif (strpos($stacksStr, 'html') !== false || strpos($stacksStr, 'css') !== false || strpos($stacksStr, 'react') !== false || strpos($stacksStr, 'vue') !== false || strpos($stacksStr, 'php') !== false || strpos($stacksStr, 'laravel') !== false) {
              $category = 'web';
          }

          $firstImg = !empty($proj['images']) ? $proj['images'][0]['path'] : 'tech.png';
          $hasLiveUrl = !empty($proj['live_url']);
          $plain_desc = strip_tags($proj['description']);
          $short_desc = (strlen($plain_desc) > 140) ? substr($plain_desc, 0, 137) . '...' : $plain_desc;
        ?>
          <div class="col-md-6 col-lg-4 project-item" data-category="<?= $category ?>" data-title="<?= htmlspecialchars(strtolower($proj['title'])) ?>" data-stacks="<?= htmlspecialchars($stacksStr) ?>">
            <div class="portfolio-card">
              <div class="portfolio-card-img">
                <img src="<?= htmlspecialchars($firstImg) ?>" alt="<?= htmlspecialchars($proj['title']) ?>" loading="lazy">
                <div class="portfolio-card-overlay">
                  <div class="overlay-actions">
                    <a href="project_details.php?id=<?= wqs_encrypt_id($proj['id']) ?>" class="overlay-btn">
                      <i class="fas fa-expand-alt"></i>
                    </a>
                    <?php if ($hasLiveUrl): ?>
                      <a href="<?= htmlspecialchars($proj['live_url']) ?>" target="_blank" class="overlay-btn">
                        <i class="fas fa-external-link-alt"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="portfolio-card-badge badge-<?= $category ?>"><?= ucfirst($category) ?></span>
              </div>
              <div class="portfolio-card-body">
                <h4 class="portfolio-card-title"><?= htmlspecialchars($proj['title']) ?></h4>
                <p class="portfolio-card-desc"><?= htmlspecialchars($short_desc) ?></p>
                <div class="portfolio-card-stacks">
                  <?php foreach (array_slice($proj['tech_stacks'], 0, 4) as $tag): ?>
                    <span class="stack-tag"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($proj['tech_stacks']) > 4): ?>
                    <span class="stack-tag stack-more">+<?= count($proj['tech_stacks']) - 4 ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="portfolio-card-footer">
                <a href="project_details.php?id=<?= wqs_encrypt_id($proj['id']) ?>" class="btn-card-primary">
                  View Details <i class="fas fa-arrow-right ms-1"></i>
                </a>
                <?php if ($hasLiveUrl): ?>
                  <a href="<?= htmlspecialchars($proj['live_url']) ?>" target="_blank" class="btn-card-secondary">
                    <i class="fas fa-external-link-alt"></i> Live
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-briefcase"></i></div>
            <h3>No Projects Published</h3>
            <p>Explore back later, our developers are actively updating custom builds.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<style>
/* ===== HERO ===== */
.portfolio-hero {
  background: linear-gradient(135deg, #020617 0%, #0a1628 50%, #020617 100%);
  color: white;
  min-height: 70vh;
  position: relative;
  padding: 5.5rem 0 4rem;
  overflow: hidden;
  display: flex; align-items: center;
}
.portfolio-hero::before {
  content: '';
  position: absolute; inset: 0;
  background: url('images/portfolio_hero_bg.png') center/cover no-repeat;
  opacity: 0.06; mix-blend-mode: overlay;
}
.hero-glow-1 {
  position: absolute; width: 500px; height: 500px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 102, 0, 0.12) 0%, transparent 70%);
  top: -150px; left: -100px; filter: blur(80px); pointer-events: none;
  animation: orbPulse 8s ease-in-out infinite alternate;
}
.hero-glow-2 {
  position: absolute; width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(10, 45, 94, 0.25) 0%, transparent 70%);
  bottom: -200px; right: -150px; filter: blur(100px); pointer-events: none;
  animation: orbPulse 10s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
  0% { transform: scale(1); opacity: 0.6; }
  100% { transform: scale(1.3); opacity: 1; }
}
.hero-grid-lines {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
}
.hero-particles {
  position: absolute; inset: 0; overflow: hidden; pointer-events: none;
}
.hero-particles span {
  position: absolute; width: 4px; height: 4px;
  background: #ff6600; border-radius: 50%;
  opacity: 0; animation: particleFloat 12s infinite;
}
.hero-particles span:nth-child(1) { left: 20%; top: 30%; animation-delay: 0s; }
.hero-particles span:nth-child(2) { left: 50%; top: 15%; animation-delay: 3s; }
.hero-particles span:nth-child(3) { left: 80%; top: 50%; animation-delay: 6s; }
.hero-particles span:nth-child(4) { left: 30%; top: 70%; animation-delay: 9s; }
.hero-particles span:nth-child(5) { left: 70%; top: 80%; animation-delay: 2s; }
@keyframes particleFloat {
  0% { opacity: 0; transform: translateY(0) scale(0); }
  20% { opacity: 0.8; transform: translateY(-20px) scale(1); }
  80% { opacity: 0.6; transform: translateY(-60px) scale(0.8); }
  100% { opacity: 0; transform: translateY(-100px) scale(0); }
}
.hero-gradient-text {
  background: linear-gradient(135deg, #ff6600, #ff9933, #ff6600);
  background-size: 200% 200%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradientShift 4s ease-in-out infinite;
}
@keyframes gradientShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: rgba(255, 102, 0, 0.1); border: 1px solid rgba(255, 102, 0, 0.25);
  color: #ff6600; font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1.5px; padding: 0.45rem 1.2rem; border-radius: 50px;
  backdrop-filter: blur(10px);
}
.hero-badge-dot {
  width: 6px; height: 6px; background: #ff6600; border-radius: 50%;
  animation: badgePulse 2s ease-in-out infinite;
}
@keyframes badgePulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.7); }
}
.hero-heading {
  font-size: clamp(2.2rem, 5vw, 3.5rem);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800; line-height: 1.1; color: #fff;
  margin-bottom: 1rem; letter-spacing: -0.03em;
}
.hero-subtitle {
  font-size: 1.05rem; line-height: 1.7; max-width: 560px;
  color: #94a3b8; margin-bottom: 2rem;
}
.hero-stats-mini {
  display: inline-flex; align-items: center; gap: 1.5rem;
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px; padding: 1rem 2rem; backdrop-filter: blur(10px);
}
.hero-stat-item { text-align: center; }
.hero-stat-num {
  font-size: 1.5rem; font-weight: 800; color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif; display: block; line-height: 1.2;
}
.hero-stat-label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
.hero-stat-divider { width: 1px; height: 35px; background: rgba(255,255,255,0.1); }

/* Scroll indicator */
.scroll-indicator {
  position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%);
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
  color: #64748b; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase;
  opacity: 0.5;
}
.scroll-mouse { width: 22px; height: 35px; border: 2px solid #64748b; border-radius: 12px; display: flex; justify-content: center; padding-top: 6px; }
.scroll-wheel { width: 3px; height: 8px; background: #ff6600; border-radius: 2px; animation: scrollWheel 2s ease-in-out infinite; }
@keyframes scrollWheel {
  0% { transform: translateY(0); opacity: 1; }
  100% { transform: translateY(12px); opacity: 0; }
}

/* ===== FILTER BAR ===== */
.filter-bar {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(0, 0, 0, 0.05);
  border-radius: 20px;
  padding: 1.25rem 2rem;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
  margin-top: -2.5rem;
  position: relative;
  z-index: 20;
}
.filter-pill {
  background: transparent; border: 1px solid transparent;
  color: #475569; font-weight: 600; font-size: 0.85rem;
  padding: 0.5rem 1.25rem; border-radius: 50px;
  transition: all 0.3s ease; cursor: pointer;
}
.filter-pill.active, .filter-pill:hover {
  background: rgba(255, 102, 0, 0.08);
  color: #ff6600; border-color: rgba(255, 102, 0, 0.2);
}
.search-wrapper {
  position: relative;
  width: 100%;
  min-width: 220px;
}
.search-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: #94a3b8; font-size: 0.85rem; pointer-events: none;
}
.search-input {
  width: 100%; padding: 0.55rem 1rem 0.55rem 2.5rem;
  border: 1px solid #e2e8f0; border-radius: 50px;
  font-size: 0.85rem; background: #f8fafc;
  transition: all 0.3s ease; outline: none;
}
.search-input:focus { border-color: #ff6600; box-shadow: 0 0 0 3px rgba(255,102,0,0.08); background: #fff; }
.search-input::placeholder { color: #94a3b8; }

/* ===== PORTFOLIO SECTION ===== */
.portfolio-section {
  padding: 4rem 0 6rem;
  background: #f8fafc;
  min-height: 50vh;
}

/* ===== PORTFOLIO CARD ===== */
.portfolio-card {
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.portfolio-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 30px 60px rgba(10, 45, 94, 0.08);
  border-color: #e2e8f0;
}

/* Card image */
.portfolio-card-img {
  position: relative;
  height: 220px;
  overflow: hidden;
  background: #f1f5f9;
}
.portfolio-card-img img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}
.portfolio-card:hover .portfolio-card-img img {
  transform: scale(1.08);
}
.portfolio-card-overlay {
  position: absolute; inset: 0;
  background: rgba(3, 7, 18, 0.6);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: all 0.4s ease;
}
.portfolio-card:hover .portfolio-card-overlay {
  opacity: 1;
}
.overlay-actions {
  display: flex; gap: 0.75rem; transform: translateY(10px);
  transition: transform 0.4s ease;
}
.portfolio-card:hover .overlay-actions {
  transform: translateY(0);
}
.overlay-btn {
  width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-size: 1rem; text-decoration: none;
  transition: all 0.3s ease;
}
.overlay-btn:hover { background: #ff6600; border-color: #ff6600; color: #fff; }

/* Card badge */
.portfolio-card-badge {
  position: absolute; top: 1rem; left: 1rem;
  font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; padding: 0.3rem 0.8rem; border-radius: 50px;
  color: #fff; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1);
}
.badge-web { background: rgba(59, 130, 246, 0.7); }
.badge-mobile { background: rgba(16, 185, 129, 0.7); }
.badge-enterprise { background: rgba(139, 92, 246, 0.7); }

/* Card body */
.portfolio-card-body {
  padding: 1.5rem 1.5rem 1rem;
  flex: 1;
  display: flex; flex-direction: column;
}
.portfolio-card-title {
  font-size: 1.1rem; font-weight: 800; color: #0f172a;
  font-family: 'Plus Jakarta Sans', sans-serif;
  letter-spacing: -0.02em; margin-bottom: 0.5rem;
  line-height: 1.3;
}
.portfolio-card-desc {
  font-size: 0.85rem; color: #64748b; line-height: 1.6;
  margin-bottom: 1rem; flex: 1;
}

/* Tech stacks */
.portfolio-card-stacks {
  display: flex; flex-wrap: wrap; gap: 0.35rem;
}
.stack-tag {
  font-size: 0.7rem; font-weight: 600; color: #0f3a5d;
  background: #eef2f6; border: 1px solid #e2e8f0;
  padding: 0.2rem 0.55rem; border-radius: 6px;
  transition: all 0.2s ease;
}
.stack-tag:hover { background: rgba(255,102,0,0.1); border-color: rgba(255,102,0,0.2); color: #ff6600; }
.stack-more { color: #64748b; background: #f1f5f9; }

/* Card footer */
.portfolio-card-footer {
  display: flex; gap: 0.5rem;
  padding: 0 1.5rem 1.5rem;
}
.btn-card-primary {
  flex: 1; display: inline-flex; align-items: center; justify-content: center;
  gap: 0.35rem; background: linear-gradient(135deg, #ff6600, #e65c00);
  color: #fff !important; font-weight: 700; font-size: 0.82rem;
  padding: 0.6rem 1rem; border-radius: 10px; text-decoration: none;
  transition: all 0.3s ease; border: none;
}
.btn-card-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,102,0,0.3); }
.btn-card-secondary {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 0.35rem; background: transparent; color: #0f172a !important;
  font-weight: 600; font-size: 0.82rem; padding: 0.6rem 1rem;
  border: 1.5px solid #e2e8f0; border-radius: 10px; text-decoration: none;
  transition: all 0.3s ease;
}
.btn-card-secondary:hover { border-color: #ff6600; color: #ff6600 !important; }

/* ===== EMPTY STATE ===== */
.empty-state {
  text-align: center; padding: 5rem 2rem;
}
.empty-icon i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem; }
.empty-state h3 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
.empty-state p { color: #64748b; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .portfolio-hero { min-height: auto; padding: 5.5rem 0 4rem; }
  .hero-stats-mini { padding: 0.75rem 1.25rem; gap: 1rem; }
  .hero-stat-num { font-size: 1.2rem; }
  .filter-bar { padding: 1rem 1.25rem; margin-top: -2rem; }
  .search-wrapper { min-width: 100%; }
  .portfolio-card-img { height: 180px; }
}
</style>

<!-- ===== FILTER + SEARCH SCRIPT ===== -->
<script>
function filterCategory(cat, event) {
  document.querySelectorAll('.filter-pill').forEach(btn => btn.classList.remove('active'));
  if (event) { event.target.classList.add('active'); }

  const items = document.querySelectorAll('.project-item');
  items.forEach(item => {
    if (cat === 'all' || item.dataset.category === cat) {
      item.style.display = 'block';
      item.style.opacity = '0';
      item.style.transform = 'translateY(20px)';
      setTimeout(() => {
        item.style.transition = 'all 0.4s ease';
        item.style.opacity = '1';
        item.style.transform = 'translateY(0)';
      }, 50);
    } else {
      item.style.opacity = '0';
      item.style.transform = 'translateY(10px)';
      setTimeout(() => { item.style.display = 'none'; }, 300);
    }
  });
}

function handleSearch() {
  const query = document.getElementById('portfolioSearch').value.toLowerCase().trim();
  const items = document.querySelectorAll('.project-item');
  items.forEach(item => {
    const title = item.dataset.title;
    const stacks = item.dataset.stacks;
    if (title.includes(query) || stacks.includes(query)) {
      item.style.display = 'block';
    } else {
      item.style.display = 'none';
    }
  });
}

// Scroll reveal for cards
document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.project-item');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, i * 80);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

  cards.forEach((card, i) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = `all 0.6s cubic-bezier(0.16, 1, 0.3, 1) ${i * 0.08}s`;
    observer.observe(card);
  });
});
</script>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
