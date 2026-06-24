<?php
$page_title = 'Services & Solutions - Wise Quotient Soft';
$seo = [
    'title'       => 'Services & Solutions - Custom Software, AI, Cloud | Wise Quotient Soft',
    'description' => 'Wise Quotient Soft offers custom software development, mobile & web apps, AI/ML solutions, cloud architecture, VTU portals, fintech systems, and IT consulting for businesses.',
    'keywords'    => 'custom software development, mobile app development, web development, AI solutions, machine learning, cloud architecture, fintech solutions, VTU portal, IT consulting, digital transformation',
    'canonical'   => 'https://wisequotientsoft.com/services.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-services.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Services', 'url' => '/services.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';

// Fetch dynamic services and pricing plans
$servicesData = [];
$landingPricing = [];
try {
    // Fetch all services and group them in PHP
    $allServices = $pdo->query("SELECT * FROM services WHERE category='service' AND is_active=1 ORDER BY service_group ASC, display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allServices as $svc) {
        $group = $svc['service_group'] ?: 'General Services';
        if (!isset($servicesData[$group])) {
            $servicesData[$group] = [
                'icon' => $svc['icon'] ?: 'fas fa-cogs', // Use the first icon found for the group
                'items' => []
            ];
        }
        $servicesData[$group]['items'][] = $svc;
    }
    
    $landingPricing = $pdo->query("SELECT * FROM services WHERE category='pricing' AND is_active=1 ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail-safe fallback arrays
}

$keySellingFeatures = [
    "Custom Software Development",
    "AI & Machine Learning Solutions",
    "Fintech & Digital Payment Systems",
    "React Native Mobile Applications",
    "Web & Cloud Solutions",
    "Healthcare Technology Systems",
    "Enterprise Business Automation",
    "SMS & Communication Platforms",
    "Cybersecurity & Data Protection",
    "24/7 Technical Support",
    "Scalable Cloud Infrastructure",
    "End-to-End Digital Transformation"
];
?>

<style>
  /* Hero Section */
  .services-hero {
    background: linear-gradient(rgba(3, 7, 18, 0.75), rgba(3, 7, 18, 0.9)), url('images/services_hero_bg.png') center/cover no-repeat, #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .services-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 1;
  }
  .hero-content {
    position: relative;
    z-index: 2;
  }
  .motto-text {
    font-size: clamp(1.2rem, 3vw, 1.8rem);
    font-weight: 300;
    color: #e2e8f0;
    letter-spacing: 1px;
    margin-top: 1.5rem;
    font-style: italic;
  }

  /* Services Grid */
  .category-section {
    padding: 5rem 0;
    background-color: #f8fafc;
  }
  .cat-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 2rem;
    height: 100%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
  }
  .cat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
  }
  .cat-icon-wrap {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(255, 102, 0, 0.1), rgba(255, 102, 0, 0.05));
    color: #ff6600;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
  }
  .cat-card:hover .cat-icon-wrap {
    background: #ff6600;
    color: #ffffff;
    transform: scale(1.05);
  }
  .cat-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 1.3rem;
    color: #0f172a;
    margin-bottom: 1.2rem;
  }
  .service-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .service-list li {
    font-size: 1.05rem;
    color: #475569;
    margin-bottom: 0.8rem;
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px dashed #e2e8f0;
  }
  .service-list li:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
  }
  .service-list li i {
    color: #3b82f6;
    margin-top: 0.35rem;
    font-size: 0.9rem;
  }

  /* Vertical Tabs Styling */
  .nav-pills-custom .nav-link {
    color: #475569;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 0.8rem;
    font-weight: 600;
    text-align: left;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.8rem;
  }
  .nav-pills-custom .nav-link i {
    font-size: 1.2rem;
    color: #94a3b8;
    transition: all 0.3s ease;
  }
  .nav-pills-custom .nav-link:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
  }
  .nav-pills-custom .nav-link.active {
    background: linear-gradient(135deg, rgba(255,102,0,0.1), rgba(255,102,0,0.05));
    color: #ff6600;
    border-color: rgba(255,102,0,0.3);
  }
  .nav-pills-custom .nav-link.active i {
    color: #ff6600;
  }
  
  /* Tab Pane Content Card */
  .tab-pane-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 3rem;
    box-shadow: var(--shadow-soft);
    border: 1px solid #e2e8f0;
    height: 100%;
    transition: var(--transition-smooth);
  }
  .tab-pane-card .cat-icon-wrap {
    width: 80px;
    height: 80px;
    font-size: 2.2rem;
  }
  .tab-pane-card .cat-title {
    font-size: 1.8rem;
    margin-bottom: 2rem;
  }
  @media (max-width: 991px) {
    .nav-pills-custom {
      display: flex;
      flex-wrap: nowrap;
      overflow-x: auto;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    .nav-pills-custom .nav-link {
      white-space: nowrap;
      margin-bottom: 0;
      margin-right: 0.8rem;
    }
    .tab-pane-card {
      padding: 1.5rem;
    }
  }

  /* Key Features Section */
  .features-section {
    background: #0f172a;
    color: white;
    padding: 5rem 0;
    position: relative;
    overflow: hidden;
  }
  .features-section::before {
    content: '';
    position: absolute;
    top: -50%; left: -10%;
    width: 50%; height: 200%;
    background: radial-gradient(circle, rgba(255,102,0,0.1) 0%, transparent 70%);
    transform: rotate(30deg);
  }
  .feature-pill {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50px;
    padding: 0.8rem 1.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #e2e8f0;
    transition: all 0.3s ease;
    width: 100%;
  }
  .feature-pill:hover {
    background: rgba(255,102,0,0.15);
    border-color: rgba(255,102,0,0.3);
    transform: translateX(5px);
  }
  .feature-pill i {
    color: #10b981;
    font-size: 1.1rem;
  }

  /* Pricing styling */
  .pricing-box-premium {
    background: #fff;
    padding: 2.5rem 2rem;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .pricing-box-premium:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
  }
  .pricing-box-premium ul li {
    margin-bottom: 0.8rem;
    font-size: 0.92rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
</style>

<!-- Services Hero Section -->
<section class="services-hero">
  <div class="container hero-content">
    <span class="badge mb-3" style="background: rgba(225, 85, 1, 0.15); color: #ff6600; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(225, 85, 1, 0.3); padding: 0.5rem 1.2rem; border-radius: 50px;">
      World-Class Solutions
    </span>
    <h1 class="fw-extrabold mb-2" style="font-size: clamp(2.5rem, 6vw, 4rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 900; line-height: 1.2;">
      Intelligent <span class="text-orange">Digital</span> Solutions
    </h1>
    <div class="motto-text">
      "Transforming Ideas into Intelligent Digital Solutions."
    </div>
  </div>
</section>

<!-- Main Services Tab UI -->
<section class="py-6 bg-body">
  <div class="container py-4">
    <div class="text-center mb-5 reveal-fade">
      <h2 class="fw-extrabold text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">Our Expertise <span class="text-orange">Categories</span></h2>
      <p class="text-muted">Select a category below to explore specific services.</p>
    </div>

    <div class="row g-5">
      <?php if (!empty($servicesData)): ?>
        <!-- Vertical Nav Pills -->
        <div class="col-lg-4 col-xl-3 reveal-up">
          <div class="nav flex-column nav-pills nav-pills-custom" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <?php 
            $i = 0;
            foreach ($servicesData as $groupName => $groupData): 
              $isActive = ($i === 0) ? 'active' : '';
              $tabId = 'v-pills-' . md5($groupName);
            ?>
              <button class="nav-link <?= $isActive ?>" id="<?= $tabId ?>-tab" data-bs-toggle="pill" data-bs-target="#<?= $tabId ?>" type="button" role="tab" aria-controls="<?= $tabId ?>" aria-selected="<?= ($i === 0) ? 'true' : 'false' ?>">
                <i class="<?= htmlspecialchars($groupData['icon']) ?>"></i>
                <?= htmlspecialchars($groupName) ?>
              </button>
            <?php $i++; endforeach; ?>
          </div>
        </div>

        <!-- Tabs Content (Right) -->
        <div class="col-lg-8 col-xl-9 reveal-up" style="transition-delay: 0.2s;">
          <div class="tab-content" id="v-pills-tabContent">
            <?php 
            $i = 0;
            foreach ($servicesData as $groupName => $groupData): 
              $isActive = ($i === 0) ? 'show active' : '';
              $tabId = 'v-pills-' . md5($groupName);
            ?>
              <div class="tab-pane fade <?= $isActive ?>" id="<?= $tabId ?>" role="tabpanel" aria-labelledby="<?= $tabId ?>-tab" tabindex="0">
                <div class="tab-pane-card">
                  <div class="cat-icon-wrap">
                    <i class="<?= htmlspecialchars($groupData['icon']) ?>"></i>
                  </div>
                  <h3 class="cat-title"><?= htmlspecialchars($groupName) ?></h3>
                  <div class="row">
                    <?php 
                    // Split items into two columns for wider display
                    $items = $groupData['items'];
                    $half = ceil(count($items) / 2);
                    $col1 = array_slice($items, 0, $half);
                    $col2 = array_slice($items, $half);
                    ?>
                    <div class="col-md-6">
                      <ul class="service-list">
                        <?php foreach ($col1 as $item): ?>
                          <li>
                            <i class="fas fa-check-circle text-primary"></i> 
                            <span><?= htmlspecialchars($item['name']) ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                      <ul class="service-list">
                        <?php foreach ($col2 as $item): ?>
                          <li>
                            <i class="fas fa-check-circle text-primary"></i> 
                            <span><?= htmlspecialchars($item['name']) ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            <?php $i++; endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="col-12"><p class="text-center text-muted">No services published yet.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<!-- Key Selling Features -->
<section class="features-section py-6">
  <div class="container position-relative z-1 py-4">
    <div class="row align-items-center">
      <div class="col-lg-5 mb-5 mb-lg-0 reveal-up">
        <span class="badge mb-3" style="background: rgba(225, 85, 1, 0.2); color: #ff6600; font-size: 0.75rem; letter-spacing: 1px;">THE WQS ADVANTAGE</span>
        <h2 class="fw-extrabold mb-4 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.5rem;">Why Choose<br><span class="text-orange">Wise Quotient Soft?</span></h2>
        <p class="mb-4" style="color: #cbd5e1; font-size: 1.1rem; line-height: 1.7;">
          We don't just build software; we engineer digital ecosystems. Our commitment to cutting-edge technology, unparalleled security, and 24/7 support ensures your business stays ahead of the curve.
        </p>
        <a href="contact.php" class="btn btn-orange btn-lg rounded-pill fw-bold px-5 py-3">Partner With Us</a>
      </div>
      <div class="col-lg-6 offset-lg-1">
        <div class="row g-3">
          <?php foreach ($keySellingFeatures as $idx => $feature): ?>
            <div class="col-md-6 reveal-up" style="transition-delay: <?= ($idx * 0.1) ?>s;">
              <div class="feature-pill">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($feature) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Pricing Plans Section -->
<section class="py-5 bg-body border-top">
  <div class="container py-5 text-center">
    <h2 class="fw-extrabold text-body mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">Flexible <span class="text-orange">Pricing Plans</span></h2>
    <p class="text-muted mb-5 mx-auto" style="max-width: 520px;">Transparent, budget-friendly tiers with calculated USD equivalents at standard exchange rates.</p>

    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-4 text-start">
      <?php if (!empty($landingPricing)): ?>
        <?php foreach ($landingPricing as $prc): ?>
          <div class="col">
            <div class="pricing-box-premium" style="border: 2px solid <?= htmlspecialchars($prc['border_color'] ?? '#0984e3') ?>;">
              <div>
                <h4 class="plan-title mb-2 fw-bold" style="color: #00264d; font-size: 1.15rem;"><?= htmlspecialchars($prc['name']) ?></h4>
                <p class="text-muted small mb-4" style="line-height: 1.5;"><?= htmlspecialchars($prc['description'] ?: '') ?></p>
                <div class="price mb-4">
                  <span class="h3 fw-bold text-body"><?= htmlspecialchars($prc['currency']) ?><?= number_format($prc['price'], 0) ?></span>
                  <?php if (!empty($prc['price_label'])): ?>
                    <small class="text-muted"><?= htmlspecialchars($prc['price_label']) ?></small>
                  <?php endif; ?>
                  
                  <?php
                    $usd_formatted = '';
                    if ($prc['currency'] === '₦' && $prc['price'] > 0) {
                      $usd = round($prc['price'] / 1550);
                      $usd_formatted = '$' . number_format($usd, 0) . ' USD equivalent';
                    }
                  ?>
                  <?php if ($usd_formatted): ?>
                    <div class="text-muted mt-1" style="font-size:0.78rem; font-weight: 600;"><?= htmlspecialchars($usd_formatted) ?></div>
                  <?php endif; ?>
                </div>

                <ul class="list-unstyled mb-4">
                  <?php
                    $featuresList = array_filter(array_map('trim', explode("\n", $prc['features'] ?? '')));
                    foreach ($featuresList as $feat):
                      if (empty($feat)) continue;
                      $isNegative = (stripos($feat, 'No ') === 0 || stripos($feat, 'Without ') === 0);
                  ?>
                    <li>
                      <?php if ($isNegative): ?>
                        <i class="fas fa-times-circle text-danger"></i>
                      <?php else: ?>
                        <i class="fas fa-check-circle text-success"></i>
                      <?php endif; ?>
                      <span><?= htmlspecialchars($feat) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              
              <div class="mt-auto pt-3">
                <a href="login.php" class="btn btn-orange w-100 py-2.5 rounded-3 fw-bold">Select Plan</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-center text-muted w-100">No pricing plans compiled yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
