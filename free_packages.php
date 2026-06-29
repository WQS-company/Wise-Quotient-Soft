<?php
$page_title = 'Free Packages & Software - Wise Quotient Soft';
$seo = [
    'title'       => 'Free Packages & Software Tools | Wise Quotient Soft',
    'description' => 'Explore free software tools, trials, and premium packages offered by Wise Quotient Soft. Start accelerating your business growth for free today.',
    'keywords'    => 'free software, free software tools, free tech packages, business growth, free trial, Wise Quotient Soft',
    'canonical'   => 'https://wisequotientsoft.com/free_packages.php',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Free Packages', 'url' => '/free_packages.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';

// Fetch Active Free Packages
$packages = [];
try {
    $packages = $pdo->query("SELECT * FROM free_packages WHERE is_active=1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail-safe
}
?>

<style>
  /* Hero Section */
  .packages-hero {
    background: linear-gradient(rgba(3, 7, 18, 0.75), rgba(3, 7, 18, 0.9)), url('images/hero-bg.png') center/cover no-repeat, #030712;
    padding: 6.5rem 0 5rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .packages-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at 50% 100%, rgba(124, 58, 237, 0.15), transparent 60%);
    z-index: 1;
  }
  .hero-content {
    position: relative;
    z-index: 2;
  }

  /* Grid and Cards */
  .packages-grid-section {
    background-color: #f8fafc;
    padding: 6rem 0;
    position: relative;
  }
  .packages-grid-section::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 100px;
    background: linear-gradient(to bottom, rgba(248, 250, 252, 0) 0%, #f8fafc 100%);
    pointer-events: none;
  }

  .packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 2.5rem;
  }
  
  .pkg-card {
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 15px 35px -10px rgba(15, 23, 42, 0.08), 0 5px 15px -5px rgba(15, 23, 42, 0.04);
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
  }
  
  .pkg-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15), 0 10px 25px -5px rgba(15, 23, 42, 0.08);
    border-color: rgba(255, 102, 0, 0.3);
  }
  
  .pkg-image-wrapper {
    width: 100%;
    height: 220px;
    overflow: hidden;
    background: #f1f5f9;
    position: relative;
  }
  
  .pkg-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
  }
  
  .pkg-card:hover .pkg-image-wrapper img {
    transform: scale(1.08);
  }
  
  .pkg-placeholder-icon {
    font-size: 4rem;
    color: #cbd5e1;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }

  .pkg-card-body {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
  }
  
  .pkg-time-limit {
    display: inline-flex;
    align-items: center;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 50px;
    margin-bottom: 1.2rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: 1px solid rgba(99, 102, 241, 0.2);
  }
  
  .pkg-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 1.4rem;
    color: #0f172a;
    margin-bottom: 0.75rem;
    line-height: 1.3;
  }
  
  .pkg-desc {
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    flex-grow: 1;
  }
  
  .pkg-features {
    list-style: none;
    padding: 0;
    margin: 0 0 2rem 0;
    border-top: 1px solid #f1f5f9;
    padding-top: 1.5rem;
  }
  
  .pkg-features li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 0.8rem;
  }
  
  .pkg-features li i {
    color: #10b981;
    margin-top: 0.25rem;
    font-size: 1.1rem;
  }
  
  .pkg-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #ff6600, #ff8c00);
    color: white;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
    z-index: 1;
  }
  
  .pkg-btn::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, #e65c00, #ff6600);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  
  .pkg-btn:hover {
    box-shadow: 0 10px 25px -5px rgba(255, 102, 0, 0.4);
    color: white;
    transform: translateY(-2px);
  }
  
  .pkg-btn:hover::before {
    opacity: 1;
  }

  /* Filter Styles */
  .filter-btn {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 8px 18px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .filter-btn:hover {
    background: #f1f5f9;
    color: #ff6600;
    border-color: #ff6600;
    transform: translateY(-1px);
  }
  .filter-btn.active {
    background: linear-gradient(135deg, #ff6600, #ff8c00);
    border-color: #ff6600;
    color: white;
    box-shadow: 0 8px 20px -6px rgba(255, 102, 0, 0.4);
  }
  #pkg-search:focus {
    border-color: #ff6600 !important;
    box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.15) !important;
  }
</style>

<!-- Hero Section -->
<section class="packages-hero">
  <div class="container hero-content">
    <span class="badge mb-3" style="background: rgba(255, 255, 255, 0.1); color: #fff; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(255, 255, 255, 0.2); padding: 0.5rem 1.2rem; border-radius: 50px;">
      Empowering Your Growth
    </span>
    <h1 class="fw-extrabold mb-3" style="font-size: clamp(2.5rem, 6vw, 4rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 900; line-height: 1.2;">
      Free Premium <span class="text-orange">Packages</span>
    </h1>
    <p class="mx-auto" style="max-width: 600px; font-size: 1.1rem; color: #cbd5e1; line-height: 1.7;">
      Discover our curated selection of high-quality software, tools, and resources available for you to use completely free of charge. Accelerate your business with Wise Quotient Soft today.
    </p>
  </div>
</section>

<!-- Packages Grid -->
<section class="packages-grid-section">
  <div class="container position-relative" style="z-index: 2;">
    
    <!-- Search & Filter Bar -->
    <div class="filter-wrapper mb-5" style="max-width: 900px; margin: -3.5rem auto 3.5rem auto; position: relative; z-index: 10;">
        <div class="filter-card" style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 24px; padding: 1.5rem; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1);">
            <div class="row g-3 align-items-center">
                <div class="col-lg-5">
                    <div class="search-box" style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem;"></i>
                        <input type="text" id="pkg-search" placeholder="Search packages or features..." style="width: 100%; padding: 0.8rem 1rem 0.8rem 3rem; border-radius: 50px; border: 1px solid #e2e8f0; outline: none; transition: all 0.3s; font-family: 'Outfit', sans-serif; font-size: 0.95rem; background: #f8fafc;" onkeyup="filterPackages()">
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="filter-tabs d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button class="filter-btn active" data-category="all" onclick="setCategoryFilter('all')">All</button>
                        <button class="filter-btn" data-category="software" onclick="setCategoryFilter('software')">Software</button>
                        <button class="filter-btn" data-category="web templates" onclick="setCategoryFilter('web templates')">Web Templates</button>
                        <button class="filter-btn" data-category="mobile apps" onclick="setCategoryFilter('mobile apps')">Mobile Apps</button>
                        <button class="filter-btn" data-category="ebooks & guides" onclick="setCategoryFilter('ebooks & guides')">eBooks & Guides</button>
                        <button class="filter-btn" data-category="api services" onclick="setCategoryFilter('api services')">API Services</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($packages)): ?>
        <div class="text-center py-6">
            <i class="fas fa-box-open text-muted" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.5;"></i>
            <h3 class="fw-bold text-dark mb-3">Check back soon!</h3>
            <p class="text-muted mx-auto" style="max-width: 500px;">We are currently preparing some amazing free packages for you. Stay tuned and check back frequently for updates.</p>
        </div>
    <?php else: ?>
        <div class="packages-grid">
            <?php foreach ($packages as $pkg): ?>
                <div class="pkg-card reveal-up" data-title="<?= htmlspecialchars(strtolower($pkg['title'])) ?>" data-desc="<?= htmlspecialchars(strtolower($pkg['description'] . ' ' . $pkg['features'])) ?>" data-category="<?= htmlspecialchars(strtolower($pkg['category'] ?? 'software')) ?>">
                    <div class="pkg-image-wrapper">
                        <?php if(!empty($pkg['image_path'])): ?>
                            <img src="<?= htmlspecialchars($pkg['image_path']) ?>" alt="<?= htmlspecialchars($pkg['title']) ?>">
                        <?php else: ?>
                            <i class="fas fa-laptop-code pkg-placeholder-icon"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pkg-card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php 
                            $raw_limit = $pkg['time_limit'] ?: 'Lifetime Access';
                            $is_lifetime = (strtolower(trim($raw_limit)) === 'lifetime access' || strtolower(trim($raw_limit)) === 'lifetime');
                            $display_limit = $is_lifetime ? 'Lifetime Access' : 'Expires: ' . $raw_limit;
                            ?>
                            <span class="pkg-time-limit mb-0"><i class="fas <?= $is_lifetime ? 'fa-infinity' : 'fa-clock' ?> me-2"></i> <?= htmlspecialchars($display_limit) ?></span>
                            <span class="badge bg-secondary text-light px-2.5 py-1.5 rounded-pill ms-2" style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85;"><?= htmlspecialchars($pkg['category'] ?? 'Software') ?></span>
                        </div>
                        
                        <h3 class="pkg-title"><?= htmlspecialchars($pkg['title']) ?></h3>
                        <p class="pkg-desc"><?= nl2br(htmlspecialchars($pkg['description'])) ?></p>
                        
                        <?php if (!empty($pkg['features'])): ?>
                            <ul class="pkg-features">
                                <?php 
                                $featuresList = array_filter(array_map('trim', explode(',', $pkg['features'])));
                                foreach ($featuresList as $feat): 
                                ?>
                                    <li><i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($feat) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <a href="<?= htmlspecialchars($pkg['access_link']) ?>" class="pkg-btn" target="_blank">
                                Access Now <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </div>
</section>

<script>
let currentCategory = 'all';

function setCategoryFilter(category) {
    currentCategory = category;
    
    // Update active tab styling
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.getAttribute('data-category') === category) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    filterPackages();
}

function filterPackages() {
    const searchVal = document.getElementById('pkg-search').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.pkg-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const desc = card.getAttribute('data-desc') || '';
        const category = card.getAttribute('data-category') || '';
        
        const matchesSearch = title.includes(searchVal) || desc.includes(searchVal);
        const matchesCategory = currentCategory === 'all' || category === currentCategory;
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'flex';
            card.style.opacity = '1';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Handle empty state
    let emptyState = document.getElementById('no-results-state');
    if (visibleCount === 0) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.id = 'no-results-state';
            emptyState.className = 'text-center py-5 w-100 mt-4';
            emptyState.innerHTML = `
                <i class="fas fa-search-minus text-muted" style="font-size: 3.5rem; margin-bottom: 1.25rem; opacity: 0.5;"></i>
                <h4 class="fw-bold text-dark mb-2">No packages match your search criteria</h4>
                <p class="text-muted mx-auto" style="max-width: 400px;">Try adjusting your keywords or switching categories to find what you're looking for.</p>
            `;
            document.querySelector('.packages-grid').appendChild(emptyState);
        }
    } else {
        if (emptyState) {
            emptyState.remove();
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
