<?php
// Load database config and fetch dynamic services/pricing for the landing page
require_once __DIR__ . '/config.php';

// AI Ads Copy Rotator logic for landing page hero section
$heroVariants = [
    [
        'badge' => 'Leading Software Development Agency',
        'headline' => 'Intelligent Software.<br><span class="hero-gradient-text">Crafted for Scale.</span>',
        'subtitle' => 'Wise Quotient Soft designs and builds state-of-the-art web platforms, mobile applications, and custom desktop clients for businesses globally.'
    ],
    [
        'badge' => 'Custom Application Engineering',
        'headline' => 'Transforming Concepts.<br><span class="hero-gradient-text">Into High-Performance Products.</span>',
        'subtitle' => 'We engineer intuitive iOS/Android applications, robust enterprise web platforms, and tailored digital architectures built for business growth.'
    ],
    [
        'badge' => 'Next-Gen AI & Intelligent Systems',
        'headline' => 'Intelligent Automation.<br><span class="hero-gradient-text">Powered by AI.</span>',
        'subtitle' => 'Optimize your operations, eliminate manual workflows, and unlock deep insights with our custom AI integrations and automated software pipelines.'
    ],
    [
        'badge' => 'Scalable E-Commerce & Fintech Platforms',
        'headline' => 'High-Volume Platforms.<br><span class="hero-gradient-text">Engineered for Transactions.</span>',
        'subtitle' => 'Launch secure digital wallets, custom billing systems, and payment portals protected by robust bank-grade encryption frameworks.'
    ],
    [
        'badge' => 'Your Strategic Technology Partner',
        'headline' => 'Premium Software.<br><span class="hero-gradient-text">Guaranteed Code Quality.</span>',
        'subtitle' => 'Collaborate with a dedicated, top-tier engineering team committed to launching secure, clean-coded software products on time and on budget.'
    ]
];

// Determine visitor impression index (0 to 4) using cookies
$cycleIndex = 0;
if (isset($_COOKIE['wqs_hero_impression_cycle'])) {
    $cycleIndex = (int)$_COOKIE['wqs_hero_impression_cycle'];
    $nextIndex = ($cycleIndex + 1) % count($heroVariants);
} else {
    $nextIndex = 1;
}
// Set cookie for 30 days
setcookie('wqs_hero_impression_cycle', $nextIndex, time() + (30 * 24 * 60 * 60), '/');

// Select source copy
$selectedVariant = $heroVariants[$cycleIndex] ?? $heroVariants[0];

$hero_badge = $selectedVariant['badge'];
$hero_headline = $selectedVariant['headline'];
$hero_subtitle = $selectedVariant['subtitle'];

$landingPricing = [];
try {
    // Top 6 featured services requested by user for the landing page
    $landingServices = [
        ['name' => 'Website Development', 'icon' => 'fas fa-laptop-code'],
        ['name' => 'Mobile App Development', 'icon' => 'fas fa-mobile-alt'],
        ['name' => 'VTU Portals', 'icon' => 'fas fa-satellite-dish'],
        ['name' => 'Fintech Solutions', 'icon' => 'fas fa-wallet'],
        ['name' => 'Desktop Applications', 'icon' => 'fas fa-desktop'],
        ['name' => 'AI & Automation', 'icon' => 'fas fa-robot']
    ];

    $landingPricing = $pdo->query("SELECT * FROM services WHERE category='pricing' AND is_active=1 ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist yet, use empty arrays (fallback)
}
// SEO Variables
$page_title = 'Wise Quotient Soft - Intelligent Software, Crafted for Scale';
$seo = [
    'title'       => 'Wise Quotient Soft - Intelligent Software, Crafted for Scale',
    'description' => 'Wise Quotient Soft builds intelligent, scalable software solutions — custom mobile & web apps, AI/ML, cloud architecture, fintech, and digital transformation for businesses in Nigeria and worldwide.',
    'keywords'    => 'software development company Nigeria, custom software development, mobile app development, web application development, AI solutions, machine learning, fintech solutions, cloud architecture, digital transformation, IT consulting, Wise Quotient Soft, WQS, Kaduna software company',
    'canonical'   => 'https://wisequotientsoft.com/',
    'og_image'    => 'https://wisequotientsoft.com/images/og-homepage.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
    ],
];

require_once __DIR__ . '/includes/public_header.php';
?>

<!-- ===== HERO SECTION ===== -->
<section class="hero-wrapper d-flex align-items-center">
  <!-- Dynamic Background Layers for seamless carousel matching -->
  <div class="hero-bg-layer active" id="hero-bg-0" style="background: linear-gradient(135deg, #123265 0%, #0b1344 50%, #4b2980 100%);"></div>
  <div class="hero-bg-layer" id="hero-bg-1" style="background: linear-gradient(135deg, #03093b 0%, #0e0b42 50%, #1a094f 100%);"></div>
  <div class="hero-bg-layer" id="hero-bg-2" style="background: linear-gradient(135deg, #060c30 0%, #050b2f 50%, #050a30 100%);"></div>

  <div class="hero-glow-1"></div>
  <div class="hero-glow-2"></div>
  <div class="hero-grid-lines"></div>
  <div class="hero-particles">
    <span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <div class="container position-relative" style="z-index: 10;">
    <div class="row align-items-center g-5">
      <!-- Left side: Marketing Content -->
      <div class="col-lg-6 text-center text-lg-start">
        <span class="hero-badge mb-3">
          <span class="hero-badge-dot"></span>
          <?= htmlspecialchars($hero_badge) ?>
        </span>
        <h1 class="hero-heading">
          <?= $hero_headline ?>
        </h1>
        <p class="hero-subtitle">
          <?= htmlspecialchars($hero_subtitle) ?>
        </p>
        <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-3">
          <a href="login.php" class="btn-hero-primary">
            <i class="fas fa-rocket me-2"></i> Start Your Project
          </a>
          <a href="portforlio.php" class="btn-hero-secondary">
            <i class="fas fa-images me-2"></i> View Portfolio
          </a>
        </div>

        <div class="hero-trust-row">
          <div class="trust-avatars">
            <div class="trust-avatar" style="background:#6366f1;">A</div>
            <div class="trust-avatar" style="background:#10b981;">M</div>
            <div class="trust-avatar" style="background:#f59e0b;">K</div>
            <div class="trust-avatar" style="background:#ef4444;">S</div>
            <div class="trust-avatar-more">+</div>
          </div>
          <div class="trust-text">
            <strong>Trusted by 50+</strong> businesses worldwide
          </div>
        </div>
      </div>

      <!-- Right side: Premium Carousel Showcase -->
      <div class="col-lg-6">
        <div class="hero-showcase-wrapper">
          <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
            <div class="carousel-indicators">
              <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Fintech Mobile App"></button>
              <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="E-commerce Marketplace"></button>
              <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Hospital Management System"></button>
            </div>
            <div class="carousel-inner">
              <div class="carousel-item active">
                <div class="carousel-image-container">
                  <img src="images/fintech_carousel.png" class="carousel-img-blur" alt="" loading="lazy" width="800" height="600">
                  <img src="images/fintech_carousel.png" class="carousel-img" alt="Wise Quotient Soft - Fintech Mobile App" loading="lazy" width="800" height="600">
                </div>
              </div>
              <div class="carousel-item">
                <div class="carousel-image-container">
                  <img src="images/ecommerce_carousel.png" class="carousel-img-blur" alt="" loading="lazy" width="800" height="600">
                  <img src="images/ecommerce_carousel.png" class="carousel-img" alt="Wise Quotient Soft - E-commerce Marketplace" loading="lazy" width="800" height="600">
                </div>
              </div>
              <div class="carousel-item">
                <div class="carousel-image-container">
                  <img src="images/hms_carousel.png" class="carousel-img-blur" alt="" loading="lazy" width="800" height="600">
                  <img src="images/hms_carousel.png" class="carousel-img" alt="Wise Quotient Soft - Hospital Management System" loading="lazy" width="800" height="600">
                </div>
              </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll indicator -->
  <div class="scroll-indicator">
    <span>Scroll</span>
    <div class="scroll-mouse">
      <div class="scroll-wheel"></div>
    </div>
  </div>
</section>

<!-- ===== AD BANNER CAROUSEL ===== -->
<section class="position-relative overflow-hidden" style="padding: 1.5rem 0 0;">
  <div class="container">
    <?php require_once __DIR__ . '/includes/ad_banner.php'; ?>
  </div>
</section>

<!-- ===== TRUSTED BY / CLIENT LOGOS ===== -->
<section class="trusted-by-section position-relative overflow-hidden">
  <div class="container position-relative" style="z-index:1;">
    <p class="trusted-by-label">Trusted by innovative companies worldwide</p>
    <div class="trusted-logos">
      <div class="trusted-logo-item"><i class="fas fa-circuit"></i> TechVault</div>
      <div class="trusted-logo-item"><i class="fas fa-database"></i> DataFlow</div>
      <div class="trusted-logo-item"><i class="fas fa-cloud"></i> CloudPeak</div>
      <div class="trusted-logo-item"><i class="fas fa-shield"></i> SecureSync</div>
      <div class="trusted-logo-item"><i class="fas fa-chart-line"></i> Quantix</div>
      <div class="trusted-logo-item"><i class="fas fa-cog"></i> NexusCore</div>
    </div>
  </div>
</section>

<!-- ===== STATS COUNTER ===== -->
<section class="stats-section position-relative overflow-hidden">
  <div class="container position-relative" style="z-index:1;">
    <div class="stats-grid">
      <div class="stat-item reveal-up">
        <div class="stat-icon"><i class="fas fa-code"></i></div>
        <div class="stat-number"><span class="count-up" data-target="150">0</span>+</div>
        <div class="stat-label">Projects Delivered</div>
      </div>
      <div class="stat-item reveal-up" style="transition-delay:0.1s;">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-number"><span class="count-up" data-target="50">0</span>+</div>
        <div class="stat-label">Happy Clients</div>
      </div>
      <div class="stat-item reveal-up" style="transition-delay:0.2s;">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-number"><span class="count-up" data-target="7">0</span>+</div>
        <div class="stat-label">Years Experience</div>
      </div>
      <div class="stat-item reveal-up" style="transition-delay:0.3s;">
        <div class="stat-icon"><i class="fas fa-headset"></i></div>
        <div class="stat-number"><span class="count-up" data-target="24">0</span>/7</div>
        <div class="stat-label">Client Support</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== SERVICES + WELCOME SECTION ===== -->
<section class="services-welcome-section position-relative overflow-hidden">
  <div class="tech-shapes">
    <svg class="shape shape-circles" viewBox="0 0 200 200" fill="none">
      <circle cx="100" cy="100" r="80" stroke="#0077cc" stroke-opacity="0.07" stroke-width="2"/>
      <circle cx="100" cy="100" r="60" stroke="#0077cc" stroke-opacity="0.05" stroke-width="2"/>
    </svg>
    <svg class="shape shape-circuit" viewBox="0 0 200 200" fill="none">
      <path d="M20 50 L50 50 L50 150 L150 150" stroke="#00b8d9" stroke-opacity="0.07" stroke-width="3" />
      <circle cx="20" cy="50" r="3" fill="#00b8d9" fill-opacity="0.1"/>
      <circle cx="150" cy="150" r="3" fill="#00b8d9" fill-opacity="0.1"/>
    </svg>
  </div>
  <div class="container position-relative">
    <div class="text-center mb-5 reveal-up">
      <span class="section-label">What We Do</span>
      <h2 class="section-heading">Our <span class="text-orange">Services</span></h2>
      <p class="section-desc mx-auto">End-to-end software engineering services that transform ideas into scalable digital products.</p>
    </div>
    <div class="row g-4">
      <!-- Left Side: Services Grid -->
      <div class="col-lg-7 mb-5 mb-lg-0">
        <div class="row g-3">
          <?php if (!empty($landingServices)): ?>
            <?php foreach ($landingServices as $svc): ?>
              <div class="col-sm-6 reveal-up">
                <a href="services.php" class="text-decoration-none">
                  <div class="service-card-premium">
                    <div class="service-card-bg"></div>
                    <div class="service-icon-circle">
                      <i class="<?= htmlspecialchars($svc['icon'] ?? 'fas fa-cogs') ?>"></i>
                    </div>
                    <div class="service-card-body">
                      <h5 class="service-card-title"><?= htmlspecialchars($svc['name'] ?: 'General Service') ?></h5>
                      <span class="service-card-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-sm-6 reveal-up">
              <a href="services.php" class="text-decoration-none">
                <div class="service-card-premium">
                  <div class="service-card-bg"></div>
                  <div class="service-icon-circle"><i class="fas fa-globe"></i></div>
                  <div class="service-card-body">
                    <h5 class="service-card-title">Web Development</h5>
                    <span class="service-card-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                  </div>
                </div>
              </a>
            </div>
            <div class="col-sm-6 reveal-up" style="transition-delay:0.1s;">
              <a href="services.php" class="text-decoration-none">
                <div class="service-card-premium">
                  <div class="service-card-bg"></div>
                  <div class="service-icon-circle"><i class="fas fa-mobile-alt"></i></div>
                  <div class="service-card-body">
                    <h5 class="service-card-title">Mobile App Development</h5>
                    <span class="service-card-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                  </div>
                </div>
              </a>
            </div>
            <div class="col-sm-6 reveal-up" style="transition-delay:0.2s;">
              <a href="services.php" class="text-decoration-none">
                <div class="service-card-premium">
                  <div class="service-card-bg"></div>
                  <div class="service-icon-circle"><i class="fas fa-robot"></i></div>
                  <div class="service-card-body">
                    <h5 class="service-card-title">AI & Automation</h5>
                    <span class="service-card-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                  </div>
                </div>
              </a>
            </div>
          <?php endif; ?>
        </div>
        <div class="mt-4 text-center">
          <a href="services.php" class="btn-view-all">View All Services <i class="fas fa-arrow-right ms-2"></i></a>
        </div>
      </div>

      <!-- Right Side: Welcome Editorial Text -->
      <div class="col-lg-5 reveal-up ps-lg-5" style="transition-delay: 0.2s;">
        <div class="about-panel">
          <span class="section-label label-orange">Who We Are</span>
          <h3 class="about-heading">Welcome to <span class="text-orange">Wise Quotient Soft</span></h3>
          <p class="about-text">
            We develop intelligent, custom-built digital products that help modern businesses thrive.
            Our values are the foundation of every solution we deliver:
          </p>
          <div class="values-list">
            <div class="value-item">
              <div class="value-icon bg-orange-soft text-orange">
                <i class="fas fa-check-double"></i>
              </div>
              <div class="value-content">
                <h6>Trust</h6>
                <p>We deliver with consistency and clarity.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon bg-blue-soft text-primary">
                <i class="fas fa-lightbulb"></i>
              </div>
              <div class="value-content">
                <h6>Innovation</h6>
                <p>Smart, scalable solutions that work.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon bg-green-soft text-success">
                <i class="fas fa-award"></i>
              </div>
              <div class="value-content">
                <h6>Excellence</h6>
                <p>Every product reflects top-tier quality.</p>
              </div>
            </div>
          </div>
          <a href="about.php" class="btn-about-premium">
            Discover Our Process <i class="fas fa-arrow-right ms-2"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== AI SOLUTIONS SHOWCASE SECTION ===== -->
<section class="ai-solutions-section position-relative overflow-hidden py-6">
  <div class="container position-relative" style="z-index: 2;">
    <div class="row align-items-center g-5">
      <!-- Left Column: The Premium AI Graphic -->
      <div class="col-lg-6 order-2 order-lg-1 reveal-up">
        <div class="ai-image-wrapper">
<img src="images/ai_showcase.png" alt="Wise Quotient Soft - Futuristic AI Solutions" 
class="img-fluid rounded-4 shadow-lg ai-showcase-img" loading="lazy" width="600" height="400">
        </div>
      </div>
      <!-- Right Column: AI Information & Value Prop -->
      <div class="col-lg-6 order-1 order-lg-2 reveal-up">
        <span class="section-label label-orange"><i class="fas fa-robot me-1"></i> AI Solutions</span>
        <h2 class="section-heading">Empowering Innovation via <span class="text-orange">Artificial Intelligence</span></h2>
        <p class="section-desc">
          We engineer next-generation intelligent agents, machine learning pipelines, and predictive analytics systems to automate your operations and unlock data-driven insights.
        </p>
        <div class="ai-features mt-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="d-flex align-items-center gap-2">
                <span class="text-orange"><i class="fas fa-brain"></i></span>
                <span class="fw-semibold text-body-secondary" style="font-size:0.92rem;">Machine Learning</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex align-items-center gap-2">
                <span class="text-orange"><i class="fas fa-chart-pie"></i></span>
                <span class="fw-semibold text-body-secondary" style="font-size:0.92rem;">Predictive Analytics</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex align-items-center gap-2">
                <span class="text-orange"><i class="fas fa-cogs"></i></span>
                <span class="fw-semibold text-body-secondary" style="font-size:0.92rem;">Workflow Automation</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex align-items-center gap-2">
                <span class="text-orange"><i class="fas fa-cloud"></i></span>
                <span class="fw-semibold text-body-secondary" style="font-size:0.92rem;">Cloud Intelligence</span>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <a href="services.php" class="btn btn-orange rounded-pill px-4 py-2 fw-bold text-white shadow-sm">Explore AI Services <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== HOW WE WORK / PROCESS ===== -->
<section class="process-section position-relative overflow-hidden">
  <div class="container">
    <div class="text-center mb-5 reveal-up">
      <span class="section-label">Our Process</span>
      <h2 class="section-heading">How We <span class="text-orange">Deliver</span></h2>
      <p class="section-desc mx-auto">A proven methodology that ensures quality, transparency, and on-time delivery.</p>
    </div>
    <div class="process-steps">
      <div class="process-step reveal-up">
        <div class="process-step-number">01</div>
        <div class="process-step-icon"><i class="fas fa-lightbulb"></i></div>
        <h4>Discover & Plan</h4>
        <p>We dive deep into your vision, market research, and technical requirements to build a comprehensive roadmap.</p>
      </div>
      <div class="process-connector">
        <div class="connector-dot"></div>
        <div class="connector-line"></div>
        <div class="connector-dot"></div>
      </div>
      <div class="process-step reveal-up" style="transition-delay:0.15s;">
        <div class="process-step-number">02</div>
        <div class="process-step-icon"><i class="fas fa-paint-brush"></i></div>
        <h4>Design & Prototype</h4>
        <p>Our designers craft intuitive UI/UX with interactive prototypes, ensuring every pixel serves a purpose.</p>
      </div>
      <div class="process-connector">
        <div class="connector-dot"></div>
        <div class="connector-line"></div>
        <div class="connector-dot"></div>
      </div>
      <div class="process-step reveal-up" style="transition-delay:0.3s;">
        <div class="process-step-number">03</div>
        <div class="process-step-icon"><i class="fas fa-rocket"></i></div>
        <h4>Build & Launch</h4>
        <p>Agile development, rigorous QA, and seamless deployment get your product to market faster.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="testimonials-section position-relative overflow-hidden">
  <div class="container position-relative" style="z-index:1;">
    <div class="text-center mb-5 reveal-up">
      <span class="section-label label-orange">Testimonials</span>
      <h2 class="section-heading">What Our <span class="text-orange">Clients Say</span></h2>
      <p class="section-desc mx-auto">Real feedback from the businesses we've helped transform.</p>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card reveal-up">
        <div class="testimonial-stars">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"Working with WQS was a game-changer. They delivered a robust fintech platform that scaled effortlessly from day one."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar" style="background:#6366f1;">KO</div>
          <div>
            <strong>Kunle Ogunlade</strong>
            <span>CEO, PayBridge</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card reveal-up" style="transition-delay:0.1s;">
        <div class="testimonial-stars">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"Their AI automation solution cut our operational costs by 40%. Exceptional technical expertise and professional service."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar" style="background:#10b981;">AM</div>
          <div>
            <strong>Adaobi Mbah</strong>
            <span>CTO, DataFlow Analytics</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card reveal-up" style="transition-delay:0.2s;">
        <div class="testimonial-stars">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"From concept to launch, the team at Wise Quotient Soft demonstrated unmatched dedication and technical depth."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar" style="background:#f59e0b;">SK</div>
          <div>
            <strong>Sarah Kalu</strong>
            <span>Founder, EduTech NG</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FINAL CTA ===== -->
<section class="cta-section position-relative overflow-hidden">
  <div class="container">
    <div class="cta-box reveal-up position-relative overflow-hidden">
      <div class="cta-glow"></div>
      <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-lg-8 text-center text-lg-start">
          <span class="cta-label">Ready to Build?</span>
          <h2 class="cta-heading">Let's Create Something <span class="text-orange">Extraordinary</span> Together</h2>
          <p class="cta-text">Turn your vision into reality. Our team is ready to build your next digital product.</p>
        </div>
        <div class="col-lg-4 text-center text-lg-end mt-4 mt-lg-0">
          <a href="login.php" class="btn-cta-primary">
            <i class="fas fa-paper-plane me-2"></i> Start Your Project
          </a>
          <a href="register.php" class="btn-cta-secondary mt-2 d-block d-lg-inline-block ms-lg-2">
            <i class="fas fa-calendar me-2"></i> Book a Call
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
/* ===== HERO CAROUSEL STYLES ===== */
.hero-bg-layer {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 1.5s ease-in-out;
  pointer-events: none;
  z-index: 1;
  will-change: opacity;
  transform: translate3d(0, 0, 0);
  backface-visibility: hidden;
}
.hero-bg-layer.active {
  opacity: 1;
}
.hero-wrapper .container {
  transform: translate3d(0, 0, 0);
  backface-visibility: hidden;
  will-change: transform;
}
.hero-glow-1,
.hero-glow-2,
.hero-grid-lines,
.hero-particles {
  z-index: 2;
  transform: translate3d(0, 0, 0);
  backface-visibility: hidden;
}

.hero-showcase-wrapper {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  padding: 0 !important;
  position: relative;
  overflow: visible !important;
  margin: -0.5rem auto 0;
  max-width: 100%;
  contain: layout style;
}
#heroCarousel,
#heroCarousel .carousel-inner,
#heroCarousel .carousel-item {
  overflow: visible !important;
}
/* Bootstrap carousel-fade: inactive items are absolutely positioned by default.
   We only need to ensure the active item establishes the container height. */
#heroCarousel .carousel-inner {
  position: relative;
}
.carousel-image-container {
  width: 100%;
  aspect-ratio: 4/3;
  background: transparent !important;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible !important;
  position: relative;
}
/* Glowing circle behind the mockup */
.carousel-image-container::before {
  content: '';
  position: absolute;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: radial-gradient(circle at center, rgba(255, 102, 0, 0.12) 0%, rgba(99, 102, 241, 0.10) 50%, transparent 70%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  filter: blur(80px);
  z-index: 1;
  pointer-events: none;
  animation: glowPulse 8s ease-in-out infinite alternate;
}
@keyframes glowPulse {
  0% { transform: translate(-50%, -50%) scale(0.9); opacity: 0.6; }
  100% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
}

/* Base image — centered, contained, no overflow */
.carousel-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  z-index: 2;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  transition: filter 0.3s ease, opacity 0.3s ease;
  filter: drop-shadow(0 15px 35px rgba(0,0,0,0.5));
  transform-origin: center center;
  will-change: transform, opacity;
  backface-visibility: hidden;
  -webkit-mask-image: radial-gradient(circle, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 80%);
  mask-image: radial-gradient(circle, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 80%);
}

/* Ambient glow using blurred duplicate image */
.carousel-img-blur {
  position: absolute;
  width: 100%;
  height: 100%;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  object-fit: contain;
  filter: blur(60px);
  opacity: 0.5;
  z-index: 1;
  pointer-events: none;
  mix-blend-mode: screen;
}

/* Subtle zoom-in animation (Scale 1.0 -> 1.05 over 7s) */
.carousel-item.active .carousel-img {
  animation: kenBurnsZoom 7s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite alternate;
}
.carousel-item.active .carousel-img-blur {
  animation: kenBurnsZoomBlur 7s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite alternate;
}

@keyframes kenBurnsZoom {
  0% { transform: translate(-50%, -50%) scale(1.0); }
  100% { transform: translate(-50%, -50%) scale(1.05); }
}

@keyframes kenBurnsZoomBlur {
  0% { transform: translate(-50%, -50%) scale(1.0); }
  100% { transform: translate(-50%, -50%) scale(1.08); }
}

/* Hover effect */
.carousel-img:hover {
  filter: drop-shadow(0 20px 45px rgba(255, 102, 0, 0.25));
}

/* Fade transitions — opacity only, NO transform to prevent layout shift */
.carousel-fade .carousel-item {
  transition: opacity 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
  transform: none !important;
}
.carousel-fade .carousel-item.active {
  transform: none !important;
}

/* Indicators and navigation */
.carousel-control-prev,
.carousel-control-next {
  width: 40px;
  height: 40px;
  background: rgba(15, 23, 42, 0.5) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 50% !important;
  top: 50% !important;
  transform: translateY(-50%) !important;
  opacity: 0 !important;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}
.hero-showcase-wrapper:hover .carousel-control-prev,
.hero-showcase-wrapper:hover .carousel-control-next {
  opacity: 0.85 !important;
}
.carousel-control-prev:hover,
.carousel-control-next:hover {
  background: #ff6600 !important;
  border-color: #ff6600 !important;
  color: white !important;
  transform: translateY(-50%) scale(1.1) !important;
}
.carousel-control-prev { left: -10px !important; }
.carousel-control-next { right: -10px !important; }
.carousel-control-prev-icon,
.carousel-control-next-icon {
  width: 14px !important;
  height: 14px !important;
}
.carousel-indicators {
  bottom: -25px !important;
  margin-bottom: 0 !important;
  gap: 6px;
  z-index: 10;
}
.carousel-indicators [data-bs-target] {
  width: 8px !important;
  height: 8px !important;
  border-radius: 50% !important;
  background-color: rgba(255, 255, 255, 0.25) !important;
  border: none !important;
  transition: all 0.3s ease !important;
}
.carousel-indicators .active {
  background-color: #ff6600 !important;
  transform: scale(1.2) !important;
  width: 20px !important;
  border-radius: 4px !important;
}

/* Tablet Responsive Improvements */
@media (max-width: 991.98px) {
  .carousel-control-prev { left: 10px !important; }
  .carousel-control-next { right: 10px !important; }
}

/* Mobile Responsive Improvements */
@media (max-width: 575.98px) {
  .carousel-image-container {
    aspect-ratio: 1/1;
  }
  .carousel-indicators {
    bottom: -15px !important;
  }
}

/* ===== AI SOLUTIONS SECTION ===== */
.ai-solutions-section {
  background-color: #ffffff;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
}
.ai-image-wrapper {
  border-radius: 24px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  line-height: 0;
}
.ai-showcase-img {
  width: 100%;
  height: auto;
  display: block;
}

/* ===== HERO SECTION - Premium Overhaul ===== */
.hero-wrapper {
  background: #020617; /* Fallback for no-JS */
  color: white;
  min-height: 100vh;
  position: relative;
  padding: 5.5rem 0 4rem;
  overflow: hidden;
  display: flex;
  align-items: center;
}
.hero-wrapper::before {
  content: '';
  position: absolute; inset: 0;
  background: url('images/index_hero_bg.png') center/cover no-repeat;
  opacity: 0.08;
  mix-blend-mode: overlay;
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
.hero-particles span:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
.hero-particles span:nth-child(2) { left: 25%; top: 60%; animation-delay: 2s; width: 3px; height: 3px; }
.hero-particles span:nth-child(3) { left: 55%; top: 10%; animation-delay: 4s; }
.hero-particles span:nth-child(4) { left: 70%; top: 70%; animation-delay: 6s; width: 5px; height: 5px; }
.hero-particles span:nth-child(5) { left: 85%; top: 40%; animation-delay: 8s; }
.hero-particles span:nth-child(6) { left: 40%; top: 80%; animation-delay: 1s; width: 3px; height: 3px; }
.hero-particles span:nth-child(7) { left: 90%; top: 15%; animation-delay: 3s; }
.hero-particles span:nth-child(8) { left: 15%; top: 85%; animation-delay: 5s; width: 5px; height: 5px; }
.hero-particles span:nth-child(9) { left: 60%; top: 45%; animation-delay: 7s; }
.hero-particles span:nth-child(10) { left: 35%; top: 30%; animation-delay: 9s; width: 3px; height: 3px; }
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
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 102, 0, 0.1);
  border: 1px solid rgba(255, 102, 0, 0.25);
  color: #ff6600;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 0.45rem 1.2rem;
  border-radius: 50px;
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
  font-weight: 800;
  line-height: 1.1;
  color: #fff;
  margin-bottom: 1.2rem;
  letter-spacing: -0.03em;
}
.hero-subtitle {
  font-size: 1.05rem;
  line-height: 1.65;
  max-width: 500px;
  color: #94a3b8;
  margin-bottom: 1.5rem;
}
.btn-hero-primary {
  display: inline-flex;
  align-items: center;
  background: linear-gradient(135deg, #ff6600, #e65c00);
  color: #fff !important;
  font-weight: 700;
  padding: 0.8rem 2rem;
  border-radius: 50px;
  text-decoration: none;
  box-shadow: 0 8px 25px rgba(255, 102, 0, 0.35);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  border: none;
}
.btn-hero-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 35px rgba(255, 102, 0, 0.45);
  color: #fff;
}
.btn-hero-secondary {
  display: inline-flex;
  align-items: center;
  background: rgba(255,255,255,0.06);
  color: #e2e8f0 !important;
  font-weight: 600;
  padding: 0.8rem 2rem;
  border-radius: 50px;
  text-decoration: none;
  border: 1.5px solid rgba(255,255,255,0.2);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  backdrop-filter: blur(10px);
}
.btn-hero-secondary:hover {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.35);
  transform: translateY(-3px);
  color: #fff;
}
.hero-trust-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1rem;
}
.trust-avatars {
  display: flex;
  align-items: center;
}
.trust-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.7rem; font-weight: 700; color: #fff;
  border: 2px solid #020617;
  margin-right: -8px;
}
.trust-avatar-more {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem; font-weight: 700; color: #94a3b8;
  background: rgba(255,255,255,0.08);
  border: 2px solid #020617;
  margin-right: 0;
}
.trust-text {
  font-size: 0.85rem; color: #94a3b8;
}
.trust-text strong { color: #e2e8f0; }

/* Scroll indicator */
.scroll-indicator {
  position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%);
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
  color: #64748b; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase;
  opacity: 0.5; animation: fadeInUp 2s ease 1s forwards;
}
.scroll-mouse {
  width: 22px; height: 35px; border: 2px solid #64748b; border-radius: 12px;
  display: flex; justify-content: center; padding-top: 6px;
}
.scroll-wheel {
  width: 3px; height: 8px; background: #ff6600; border-radius: 2px;
  animation: scrollWheel 2s ease-in-out infinite;
}
@keyframes scrollWheel {
  0% { transform: translateY(0); opacity: 1; }
  100% { transform: translateY(12px); opacity: 0; }
}

/* Hero showcase */
.hero-showcase-wrapper {
  background: rgba(15, 23, 42, 0.3);
  border-radius: 20px;
  padding: 1.5rem;
  border: 1px solid rgba(255,255,255,0.06);
  backdrop-filter: blur(20px);
  box-shadow: 0 30px 60px rgba(0,0,0,0.4);
}
/* ===== ILLUSTRATION SYSTEM ===== */
.illustration-container {
  position: relative;
  background: rgba(15, 23, 42, 0.4);
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.08);
  padding: 0.5rem;
  backdrop-filter: blur(8px);
  box-shadow: 0 25px 60px rgba(0,0,0,0.5);
  min-height: 280px;
  display: flex;
  align-items: center;
}
.ill-panel {
  display: none;
  width: 100%;
  animation: illFadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.ill-panel.active { display: block; }
@keyframes illFadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}
.illustration-tabs .ill-tab {
  width: 42px; height: 42px; border-radius: 12px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: #64748b;
  font-size: 1rem;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.3s ease;
}
.illustration-tabs .ill-tab:hover {
  background: rgba(255,255,255,0.1);
  color: #cbd5e1;
}
.illustration-tabs .ill-tab.active {
  background: rgba(225,85,1,0.15);
  border-color: #E15501;
  color: #E15501;
  box-shadow: 0 0 20px rgba(225,85,1,0.15);
}
.ill-labels .ill-label {
  font-size: 0.75rem; font-weight: 600; color: #64748b;
  display: flex; align-items: center; gap: 0.4rem;
  cursor: pointer; transition: color 0.3s ease;
}
.ill-labels .ill-label.active { color: #E15501; }
.ill-labels .ill-label:hover { color: #cbd5e1; }
.ill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

/* ===== TRUSTED BY SECTION ===== */
.trusted-by-section {
  padding: 3rem 0;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
}
.trusted-by-label {
  text-align: center;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: #94a3b8;
  margin-bottom: 2rem;
}
.trusted-logos {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 2rem 3rem;
}
.trusted-logo-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #64748b;
  opacity: 0.6;
  transition: all 0.3s ease;
}
.trusted-logo-item i { font-size: 1.2rem; color: #0A2D5E; }
.trusted-logo-item:hover { opacity: 1; color: #0f172a; }

/* ===== STATS SECTION ===== */
.stats-section {
  background: #0A2D5E;
  padding: 4rem 0;
  position: relative;
  overflow: hidden;
}
.stats-section::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle at 20% 50%, rgba(255,102,0,0.08) 0%, transparent 50%),
              radial-gradient(circle at 80% 50%, rgba(255,255,255,0.03) 0%, transparent 50%);
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
  position: relative; z-index: 2;
}
.stat-item {
  text-align: center;
  padding: 1.5rem;
}
.stat-icon {
  width: 56px; height: 56px; margin: 0 auto 1rem;
  background: rgba(255,102,0,0.12);
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; color: #ff6600;
}
.stat-number {
  font-size: 2.5rem;
  font-weight: 800;
  color: #ffffff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  letter-spacing: -0.03em;
  margin-bottom: 0.3rem;
}
.stat-label {
  font-size: 0.85rem;
  color: #94a3b8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* ===== SERVICES & WELCOME SECTION ===== */
.services-welcome-section {
  padding: 6rem 0;
  background: #f8fafc;
}
.section-label {
  display: inline-block;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: #0A2D5E;
  margin-bottom: 0.75rem;
}
.label-orange { color: #ff6600; }
.section-heading {
  font-size: 2.2rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.03em;
  margin-bottom: 1rem;
}
.section-desc {
  max-width: 540px;
  color: #64748b;
  font-size: 1.05rem;
  line-height: 1.7;
}
.text-orange { color: #ff6600 !important; }

/* Premium service cards */
.service-card-premium {
  position: relative;
  background: #ffffff;
  padding: 1.5rem;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  height: 100%;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 1rem;
}
.service-card-premium::before {
  content: '';
  position: absolute; top: 0; left: 0; width: 100%; height: 3px;
  background: linear-gradient(90deg, #ff6600, #ff9933);
  transform: scaleX(0); transform-origin: left;
  transition: transform 0.4s ease;
}
.service-card-premium:hover::before { transform: scaleX(1); }
.service-card-premium:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 40px rgba(10, 45, 94, 0.08);
  border-color: rgba(255, 102, 0, 0.15);
}
.service-card-bg {
  position: absolute; top: 50%; right: -20px; width: 120px; height: 120px;
  border-radius: 50%; background: rgba(255,102,0,0.03);
  pointer-events: none; transition: all 0.4s ease;
}
.service-card-premium:hover .service-card-bg {
  transform: scale(2); background: rgba(255,102,0,0.06);
}
.service-icon-circle {
  width: 50px; height: 50px; border-radius: 14px;
  background: rgba(255, 102, 0, 0.08);
  color: #ff6600; display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; flex-shrink: 0;
  transition: all 0.3s ease;
  position: relative; z-index: 2;
}
.service-card-premium:hover .service-icon-circle {
  background: #ff6600; color: #fff; transform: scale(1.1) rotate(-5deg);
}
.service-card-body { flex-grow: 1; position: relative; z-index: 2; }
.service-card-title {
  font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem;
  letter-spacing: -0.01em;
}
.service-card-link {
  font-size: 0.8rem; color: #94a3b8; font-weight: 600;
  transition: all 0.3s ease;
}
.service-card-premium:hover .service-card-link { color: #ff6600; }

.btn-view-all {
  display: inline-flex; align-items: center;
  color: #0A2D5E; font-weight: 700; font-size: 0.9rem;
  text-decoration: none; padding: 0.6rem 1.5rem;
  border: 2px solid #0A2D5E; border-radius: 50px;
  transition: all 0.3s ease;
}
.btn-view-all:hover {
  background: #0A2D5E; color: #fff; transform: translateY(-2px);
}

/* About panel */
.about-panel { padding-top: 1rem; }
.about-heading {
  font-size: 2.2rem; font-weight: 800; color: #0f172a;
  letter-spacing: -0.03em; line-height: 1.2; margin-bottom: 1rem;
}
.about-text {
  color: #64748b; font-size: 1rem; line-height: 1.7; margin-bottom: 1.5rem;
}
.values-list { display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 2rem; }
.value-item { display: flex; align-items: flex-start; gap: 1rem; }
.value-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.value-content h6 { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 0.15rem; }
.value-content p { font-size: 0.85rem; color: #64748b; margin: 0; }
.bg-orange-soft { background: rgba(225, 85, 1, 0.1); }
.bg-blue-soft { background: rgba(10, 45, 94, 0.1); }
.bg-green-soft { background: rgba(16, 185, 129, 0.1); }

.btn-about-premium {
  display: inline-flex; align-items: center;
  background: linear-gradient(135deg, #0A2D5E, #1e4b8a);
  color: #fff !important; font-weight: 700; padding: 0.75rem 2rem;
  border-radius: 50px; text-decoration: none;
  box-shadow: 0 10px 20px rgba(10, 45, 94, 0.2);
  transition: all 0.3s ease; border: none;
}
.btn-about-premium:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(10, 45, 94, 0.3);
  color: #fff;
}

/* Tech shapes */
.tech-shapes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
.shape { position: absolute; opacity: 0.8; }
.shape-circles { top: 10%; left: 5%; width: 120px; height: 120px; }
.shape-circuit { bottom: 10%; right: 5%; width: 150px; height: 150px; }

/* ===== PROCESS SECTION ===== */
.process-section {
  padding: 6rem 0;
  background: #ffffff;
}
.process-steps {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  gap: 0;
  max-width: 960px;
  margin: 0 auto;
}
.process-step {
  flex: 1;
  text-align: center;
  padding: 2rem;
  position: relative;
}
.process-step-number {
  font-size: 3rem;
  font-weight: 800;
  color: rgba(10, 45, 94, 0.06);
  font-family: 'Plus Jakarta Sans', sans-serif;
  line-height: 1;
  margin-bottom: 0.5rem;
}
.process-step-icon {
  width: 64px; height: 64px; margin: 0 auto 1rem;
  background: rgba(255,102,0,0.1); border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #ff6600;
  transition: all 0.3s ease;
}
.process-step:hover .process-step-icon {
  background: #ff6600; color: #fff; transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(255,102,0,0.25);
}
.process-step h4 {
  font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;
}
.process-step p {
  font-size: 0.9rem; color: #64748b; line-height: 1.6;
  max-width: 260px; margin: 0 auto;
}
.process-connector {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding-top: 3rem; flex-shrink: 0;
}
.connector-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: #ff6600; border: 2px solid #fff;
  box-shadow: 0 0 0 3px rgba(255,102,0,0.2);
}
.connector-line {
  width: 2px; height: 60px;
  background: linear-gradient(180deg, #ff6600, rgba(255,102,0,0.2));
}

/* ===== TESTIMONIALS SECTION ===== */
.testimonials-section {
  padding: 6rem 0;
  background: #f8fafc;
}
.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}
.testimonial-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 2rem;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.testimonial-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 40px rgba(0,0,0,0.06);
}
.testimonial-stars { color: #f59e0b; margin-bottom: 1rem; font-size: 0.85rem; letter-spacing: 2px; }
.testimonial-text {
  font-size: 0.95rem; color: #334155; line-height: 1.7;
  margin-bottom: 1.5rem; font-style: italic;
}
.testimonial-author {
  display: flex; align-items: center; gap: 0.75rem;
  padding-top: 1rem; border-top: 1px solid #f1f5f9;
}
.testimonial-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 700; color: #fff; flex-shrink: 0;
}
.testimonial-author strong { display: block; font-size: 0.9rem; color: #0f172a; }
.testimonial-author span { display: block; font-size: 0.8rem; color: #64748b; }

/* ===== CTA SECTION ===== */
.cta-section {
  padding: 5rem 0;
  background: #0A2D5E;
  position: relative;
  overflow: hidden;
}
.cta-box {
  background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 24px;
  padding: 3.5rem;
  position: relative;
  backdrop-filter: blur(10px);
  overflow: hidden;
}
.cta-glow {
  position: absolute; top: -50%; right: -20%;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(255,102,0,0.1) 0%, transparent 70%);
  filter: blur(60px); pointer-events: none;
}
.cta-label {
  font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 2px; color: #ff6600; margin-bottom: 0.5rem; display: block;
}
.cta-heading {
  font-size: 2rem; font-weight: 800; color: #ffffff;
  letter-spacing: -0.03em; margin-bottom: 0.75rem;
}
.cta-text {
  font-size: 1rem; color: #94a3b8; line-height: 1.7; margin-bottom: 0;
}
.btn-cta-primary {
  display: inline-flex; align-items: center;
  background: linear-gradient(135deg, #ff6600, #e65c00);
  color: #fff !important; font-weight: 700; padding: 0.85rem 2rem;
  border-radius: 50px; text-decoration: none;
  box-shadow: 0 8px 25px rgba(255,102,0,0.35);
  transition: all 0.3s ease; border: none;
}
.btn-cta-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(255,102,0,0.45); color: #fff; }
.btn-cta-secondary {
  display: inline-flex; align-items: center;
  background: rgba(255,255,255,0.08); color: #e2e8f0 !important;
  font-weight: 600; padding: 0.85rem 2rem;
  border-radius: 50px; text-decoration: none;
  border: 1px solid rgba(255,255,255,0.15);
  transition: all 0.3s ease;
}
.btn-cta-secondary:hover { background: rgba(255,255,255,0.15); color: #fff !important; transform: translateY(-3px); }

/* ===== PRICING SECTION ===== */
.pricing-section {
  background-color: #ffffff;
  position: relative;
  padding: 6rem 0;
}
.pricing-section::before {
  content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
  background: radial-gradient(circle at top right, rgba(10, 45, 94, 0.03), transparent 50%),
              radial-gradient(circle at bottom left, rgba(225, 85, 1, 0.03), transparent 50%);
  pointer-events: none;
}
.pricing-box {
  background: #ffffff;
  padding: 2.5rem 1.5rem;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  display: flex;
  flex-direction: column;
  font-size: 0.95rem;
  height: 100%;
  position: relative;
  overflow: hidden;
  z-index: 2;
}
.pricing-box:hover {
  transform: translateY(-8px);
  box-shadow: 0 30px 60px rgba(0, 0, 0, 0.08);
}
.plan-title { font-weight: 800; font-size: 1.35rem; color: #0f172a; letter-spacing: -0.02em; }
.price { font-size: 1.15rem; font-weight: 800; color: #ff6600; }
.price small { font-size: 0.8rem; color: #64748b; display: block; margin-top: 0.2rem; }
.price-naira { font-size: 0.85rem; margin-top: 0.4rem; color: #64748b; font-weight: 600; }
.plan-features li {
  margin-bottom: 0.75rem; font-size: 0.95rem; color: #334155;
  text-align: left; display: flex; align-items: flex-start; gap: 0.6rem;
}
.plan-features li i { margin-top: 0.2rem; }
.border-starter { border: 2px solid rgba(16, 185, 129, 0.5); }
.border-standard { border: 2px solid rgba(59, 130, 246, 0.5); }
.border-professional {
  border: 2px solid #ff6600;
  box-shadow: 0 15px 35px rgba(255, 102, 0, 0.1);
  background: #ffffff;
  transform: scale(1.03);
}
.border-professional::after {
  content: 'Most Popular';
  position: absolute; top: 15px; right: -30px;
  background: #ff6600; color: #fff;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
  padding: 4px 30px; transform: rotate(45deg);
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.border-professional:hover { transform: scale(1.05) translateY(-5px); }
.border-enterprise {
  border: 2px solid #0A2D5E;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}
.shapes { display: none; }

/* Referral box */
.referral-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 2.5rem;
  text-align: center;
  margin-top: 3rem;
}
.referral-icon {
  width: 56px; height: 56px; margin: 0 auto 1rem;
  background: rgba(255,102,0,0.1); border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; color: #ff6600;
}
.referral-heading {
  font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;
}
.referral-text {
  color: #64748b; font-size: 0.95rem; line-height: 1.7; max-width: 560px; margin: 0 auto 1.5rem;
}

/* ===== RESPONSIVE - COMPREHENSIVE BREAKPOINTS ===== */

/* Large screens */
@media (max-width: 1200px) {
  .hero-heading { font-size: 2.8rem; }
  .section-heading { font-size: 2rem; }
}

/* Tablet landscape */
@media (max-width: 991px) {
  .hero-wrapper {
    text-align: center;
    padding: 4rem 0 2.5rem;
    min-height: auto;
  }
  .hero-subtitle { margin-left: auto; margin-right: auto; }
  .hero-trust-row { justify-content: center; }
  .hero-showcase-wrapper { padding: 1rem; margin-top: 1.5rem; }
  .scroll-indicator { display: none; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .process-steps { flex-direction: column; align-items: center; }
  .process-connector { flex-direction: row; padding: 0; }
  .process-connector .connector-line { width: 60px; height: 2px; background: linear-gradient(90deg, #ff6600, rgba(255,102,0,0.2)); }
  .testimonials-grid { grid-template-columns: 1fr; max-width: 560px; margin: 0 auto; }
  .cta-box { padding: 2.5rem; }
  .about-panel { margin-top: 2rem; }
}

/* Tablet portrait */
@media (max-width: 768px) {
  .hero-wrapper { padding: 3.5rem 0 2rem; }
  .hero-heading { font-size: 2.2rem; }
  .hero-subtitle { font-size: 1rem; }
  .hero-showcase-wrapper { padding: 1rem; }
  .section-heading { font-size: 1.8rem; }
  .stat-number { font-size: 2rem; }
  .about-heading { font-size: 1.8rem; }
  .cta-box { padding: 2rem; }
  .cta-heading { font-size: 1.6rem; }
  .pricing-box { padding: 2rem 1.25rem; }
  .testimonial-card { padding: 1.5rem; }
  .referral-box { padding: 2rem; }
}

/* 576px screens */
@media (max-width: 576px) {
  .hero-wrapper { padding: 3rem 0 1.75rem; }
  .hero-heading { font-size: 1.9rem; }
  .hero-subtitle { font-size: 0.95rem; }
  .hero-badge { font-size: 0.7rem; padding: 0.4rem 1rem; }
  .btn-hero-primary, .btn-hero-secondary { padding: 0.7rem 1.6rem; font-size: 0.88rem; }
  .section-heading { font-size: 1.65rem; }
  .section-desc { font-size: 0.95rem; }
  .stat-number { font-size: 1.8rem; }
  .stat-label { font-size: 0.78rem; }
  .service-card-premium { padding: 1.25rem; }
  .process-step { padding: 1.5rem; }
  .process-step-number { font-size: 2.5rem; }
  .process-step-icon { width: 56px; height: 56px; font-size: 1.3rem; }
  .cta-box { padding: 1.75rem; }
  .cta-heading { font-size: 1.45rem; }
  .pricing-box { padding: 1.75rem 1.1rem; }
  .testimonial-card { padding: 1.4rem; }
  .testimonial-text { font-size: 0.9rem; }
  .referral-box { padding: 1.75rem; }
  .about-heading { font-size: 1.6rem; }
  .trusted-logos { gap: 1.2rem 2rem; }
  .trusted-logo-item { font-size: 0.85rem; }
}

/* 420px screens */
@media (max-width: 420px) {
  .hero-wrapper { padding: 2.5rem 0 1.5rem; }
  .hero-heading { font-size: 1.75rem; }
  .hero-showcase-wrapper { padding: 0.75rem; }
  .illustration-tabs .ill-tab { width: 36px; height: 36px; font-size: 0.85rem; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
  .stat-item { padding: 1rem; }
  .testimonials-grid { grid-template-columns: 1fr; }
  .process-steps { gap: 0; }
  .process-connector .connector-line { width: 40px; height: 2px; }
  .cta-heading { font-size: 1.35rem; }
  .pricing-box { padding: 1.5rem 1rem; }
}

/* iPhone SE / 375px screens */
@media (max-width: 375px) {
  .hero-heading { font-size: 1.6rem; }
  .hero-subtitle { font-size: 0.88rem; }
  .hero-badge { font-size: 0.65rem; padding: 0.35rem 0.8rem; }
  .btn-hero-primary, .btn-hero-secondary { padding: 0.65rem 1.4rem; font-size: 0.82rem; }
  .section-heading { font-size: 1.5rem; }
  .section-desc { font-size: 0.9rem; }
  .stat-number { font-size: 1.6rem; }
  .stat-label { font-size: 0.72rem; }
  .process-step { padding: 1rem; }
  .process-step-number { font-size: 2rem; }
  .process-step-icon { width: 48px; height: 48px; font-size: 1.2rem; }
  .cta-box { padding: 1.5rem; }
  .cta-heading { font-size: 1.3rem; }
  .pricing-box { padding: 1.5rem 1rem; }
  .testimonial-card { padding: 1.25rem; }
  .testimonial-text { font-size: 0.85rem; }
  .referral-box { padding: 1.5rem; }
  .referral-heading { font-size: 1.05rem; }
  .referral-text { font-size: 0.88rem; }
  .about-heading { font-size: 1.45rem; }
  .about-text { font-size: 0.92rem; }
  .btn-hero-primary, .btn-hero-secondary { font-size: 0.82rem; padding: 0.6rem 1.3rem; }
  .trust-avatars .trust-avatar { width: 30px; height: 30px; font-size: 0.65rem; }
  .trust-avatar-more { width: 30px; height: 30px; font-size: 0.8rem; }
  .trust-text { font-size: 0.8rem; }
  .service-card-title { font-size: 0.88rem; }
  .value-content h6 { font-size: 0.88rem; }
  .value-content p { font-size: 0.8rem; }
  .value-icon { width: 38px; height: 38px; font-size: 1rem; }
  .btn-about-premium { padding: 0.65rem 1.5rem; font-size: 0.85rem; }
  .btn-view-all { padding: 0.5rem 1.2rem; font-size: 0.82rem; }
}

/* 320px screens */
@media (max-width: 320px) {
  .hero-wrapper { padding: 2rem 0 1.25rem; }
  .hero-heading { font-size: 1.4rem; }
  .hero-subtitle { font-size: 0.82rem; line-height: 1.6; }
  .hero-badge { font-size: 0.6rem; padding: 0.3rem 0.7rem; letter-spacing: 1px; }
  .btn-hero-primary, .btn-hero-secondary { padding: 0.55rem 1.2rem; font-size: 0.78rem; }
  .section-heading { font-size: 1.35rem; }
  .section-desc { font-size: 0.85rem; }
  .trust-avatars .trust-avatar { width: 28px; height: 28px; font-size: 0.6rem; }
  .trust-avatar-more { width: 28px; height: 28px; font-size: 0.7rem; }
  .trust-text { font-size: 0.75rem; }
  .stat-item { padding: 0.75rem; }
  .stat-number { font-size: 1.4rem; }
  .stat-label { font-size: 0.7rem; letter-spacing: 0.5px; }
  .stat-icon { width: 44px; height: 44px; font-size: 1.1rem; }
  .service-card-premium { padding: 1rem; }
  .service-icon-circle { width: 40px; height: 40px; font-size: 1.1rem; }
  .service-card-title { font-size: 0.82rem; }
  .service-card-link { font-size: 0.75rem; }
  .process-step-number { font-size: 2rem; }
  .process-step-icon { width: 48px; height: 48px; font-size: 1.2rem; }
  .process-step h4 { font-size: 1rem; }
  .process-step p { font-size: 0.82rem; }
  .cta-box { padding: 1.25rem; }
  .cta-heading { font-size: 1.2rem; }
  .cta-text { font-size: 0.88rem; }
  .pricing-box { padding: 1.25rem 0.85rem; }
  .plan-title { font-size: 1.15rem; }
  .plan-features li { font-size: 0.85rem; margin-bottom: 0.6rem; }
  .testimonial-card { padding: 1.1rem; }
  .testimonial-text { font-size: 0.82rem; }
  .testimonial-avatar { width: 36px; height: 36px; font-size: 0.7rem; }
  .testimonial-author strong { font-size: 0.82rem; }
  .testimonial-author span { font-size: 0.72rem; }
  .referral-box { padding: 1.25rem; }
  .referral-icon { width: 48px; height: 48px; font-size: 1.2rem; }
  .referral-heading { font-size: 0.95rem; }
  .referral-text { font-size: 0.82rem; }
  .about-heading { font-size: 1.3rem; }
  .about-text { font-size: 0.85rem; }
  .about-panel { padding-top: 0.5rem; }
  .values-list { gap: 0.6rem; }
  .value-item { gap: 0.75rem; }
  .value-icon { width: 34px; height: 34px; font-size: 0.9rem; border-radius: 10px; }
  .value-content h6 { font-size: 0.82rem; }
  .value-content p { font-size: 0.75rem; }
  .btn-about-premium { padding: 0.6rem 1.3rem; font-size: 0.82rem; }
  .btn-view-all { padding: 0.45rem 1rem; font-size: 0.78rem; }
  .trusted-logo-item { font-size: 0.78rem; gap: 0.35rem; }
  .trusted-logo-item i { font-size: 1rem; }
  .hero-showcase-wrapper { border-radius: 14px; }
  .connector-dot { width: 8px; height: 8px; }
}
</style>

<!-- PRICING SECTION START -->
<section id="pricing" class="pricing-section position-relative overflow-hidden">
  <div class="container text-center position-relative" style="z-index:1;">
    <div class="reveal-up">
      <span class="section-label label-orange">Pricing</span>
      <h2 class="section-heading">Transparent <span class="text-orange">Project Pricing</span></h2>
      <p class="section-desc mx-auto mb-5">
        Custom software development pricing tailored to your needs.
        <a href="/policy.html" class="text-decoration-underline text-orange" target="_blank" rel="noopener">Project Policy</a>,
        <a href="/pricing-info.html" class="text-decoration-underline text-orange" target="_blank" rel="noopener">Pricing Details</a>,
        <a href="#referral-details" class="text-decoration-underline text-orange">Referral Rewards</a>.
      </p>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4 g-4">
      <?php if (!empty($landingPricing)): ?>
        <?php foreach ($landingPricing as $prc): ?>
          <div class="col">
            <article class="pricing-box d-flex flex-column justify-content-between h-100 p-3 bg-body rounded shadow-sm" style="border: 2px solid <?= htmlspecialchars($prc['border_color'] ?? '#0984e3') ?>;">
              <div>
                <h3 class="plan-title mb-2 text-orange"><?= htmlspecialchars($prc['name']) ?></h3>
                <div class="price mb-2">
                  <span class="h5" style="color:var(--color-primary-dark); font-weight:800;"><?= htmlspecialchars($prc['currency']) ?><?= number_format($prc['price'], 0) ?></span>
                  <?php if (!empty($prc['price_label'])): ?>
                    <small><?= htmlspecialchars($prc['price_label']) ?></small>
                  <?php endif; ?>
                  <?php
                    $usd_formatted = '';
                    if ($prc['currency'] === '₦' && $prc['price'] > 0) {
                      $usd = round($prc['price'] / 1550);
                      $usd_formatted = '$' . number_format($usd, 0) . ' USD equivalent';
                    }
                  ?>
                  <?php if ($usd_formatted): ?>
                    <div class="price-naira text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($usd_formatted) ?></div>
                  <?php endif; ?>
                </div>
                <ul class="plan-features list-unstyled mb-4 text-start">
                  <?php
                    $featuresList = array_filter(array_map('trim', explode("\n", $prc['features'] ?? '')));
                    foreach ($featuresList as $feat):
                      if (empty($feat)) continue;
                      $isNegative = (stripos($feat, 'No ') === 0 || stripos($feat, 'Without ') === 0);
                  ?>
                    <li>
                      <?php if ($isNegative): ?>
                        <i class="fas fa-times-circle text-danger me-2"></i>
                      <?php else: ?>
                        <i class="fas fa-check-circle text-success me-2"></i>
                      <?php endif; ?>
                      <?= htmlspecialchars($feat) ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php
                $btnLabel = (stripos($prc['name'], 'Enterprise') !== false) ? 'Request Enterprise Quote' : 'Start ' . htmlspecialchars($prc['name']);
                $btnClass = (stripos($prc['name'], 'Enterprise') !== false) ? 'btn-outline-dark' : 'btn-orange';
              ?>
              <a href="login.php" class="btn <?= $btnClass ?> w-100 mt-auto"><?= $btnLabel ?></a>
            </article>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Starter Project (UNCHANGED) -->
        <div class="col">
          <article class="pricing-box border-starter d-flex flex-column justify-content-between h-100 p-3 bg-body rounded shadow-sm">
            <div>
              <h3 class="plan-title mb-2 text-orange">Starter Project</h3>
              <div class="price mb-2">
                <span class="h5" style="color:var(--color-primary-dark); font-weight:800;">₦155,000</span> <small>/simple app</small>
                <div class="price-naira text-muted" style="font-size:0.75rem;">$100 USD equivalent</div>
              </div>
              <ul class="plan-features list-unstyled mb-4 text-start">
                <li><i class="fas fa-check-circle text-success me-2"></i> Web <strong>or</strong> Mobile App</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> 1 Week Delivery</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Static Features</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Basic Support</li>
                <li><i class="fas fa-times-circle text-danger me-2"></i> No AI/ML Features</li>
              </ul>
            </div>
            <a href="login.php" class="btn btn-orange w-100 mt-auto">Start Starter Project</a>
          </article>
        </div>

        <!-- Standard Project (INCREASED) -->
        <div class="col">
          <article class="pricing-box border-standard d-flex flex-column justify-content-between h-100 p-3 bg-body rounded shadow-sm">
            <div>
              <h3 class="plan-title mb-2 text-orange">Standard Project</h3>
              <div class="price mb-2">
                <span class="h5" style="color:var(--color-primary-dark); font-weight:800;">₦3,875,000</span> <small>/single platform</small>
                <div class="price-naira text-muted" style="font-size:0.75rem;">$2,500 USD equivalent</div>
              </div>
              <ul class="plan-features list-unstyled mb-4 text-start">
                <li><i class="fas fa-check-circle text-success me-2"></i> Web <strong>or</strong> Mobile App</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> 3–4 Weeks Delivery</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> 3 Revisions</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Dynamic Features</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Basic Database Integration</li>
              </ul>
            </div>
            <a href="login.php" class="btn btn-orange w-100 mt-auto">Start Standard Project</a>
          </article>
        </div>

        <!-- Professional Project (INCREASED) -->
        <div class="col">
          <article class="pricing-box border-professional d-flex flex-column justify-content-between h-100 p-3 bg-body rounded shadow-sm">
            <div>
              <h3 class="plan-title mb-2 text-orange">Professional Project</h3>
              <div class="price mb-2">
                <span class="h5" style="color:var(--color-primary-dark); font-weight:800;">₦11,625,000</span> <small>/advanced solution</small>
                <div class="price-naira text-muted" style="font-size:0.75rem;">$7,500 USD equivalent</div>
              </div>
              <ul class="plan-features list-unstyled mb-4 text-start">
                <li><i class="fas fa-check-circle text-success me-2"></i> Advanced Web <strong>or</strong> Mobile App</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> 6–8 Weeks Delivery</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Unlimited Revisions</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Dynamic Features</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Cloud & API Integration</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Security Optimization</li>
              </ul>
            </div>
            <a href="login.php" class="btn btn-orange w-100 mt-auto">Start Professional Project</a>
          </article>
        </div>

        <!-- Enterprise Solution (INCREASED) -->
        <div class="col">
          <article class="pricing-box border-enterprise d-flex flex-column justify-content-between h-100 p-3 bg-body rounded shadow-sm">
            <div>
              <h3 class="plan-title mb-2 text-orange">Enterprise Software</h3>
              <div class="price mb-2">
                <span class="h5" style="color:var(--color-primary-dark); font-weight:800;">₦38,750,000+</span> <small>/business-grade</small>
                <div class="price-naira text-muted" style="font-size:0.75rem;">$25,000+ USD equivalent</div>
              </div>
              <ul class="plan-features list-unstyled mb-4 text-start">
                <li><i class="fas fa-check-circle text-success me-2"></i> <strong>Web + Mobile Applications</strong></li>
                <li><i class="fas fa-check-circle text-success me-2"></i> AI & Analytics</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Advanced Dynamic Features</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Dedicated Dev Team</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> DevOps & Cloud Infrastructure</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Priority 24/7 Support</li>
              </ul>
            </div>
            <a href="login.php" class="btn btn-outline-dark w-100 mt-auto">Request Enterprise Quote</a>
          </article>
        </div>
      <?php endif; ?>
    </div>

    <!-- Referral Program -->
    <div id="referral-details" class="referral-box reveal-up">
      <div class="referral-icon"><i class="fas fa-gift"></i></div>
      <h5 class="referral-heading">Referral Program & Rewards</h5>
      <p class="referral-text">
        Get a <strong>10% discount</strong> if you were referred by a past client. Refer someone and earn up to <strong>20% commission</strong> based on the project's value.
      </p>
      <a href="login.php" class="btn btn-orange rounded-pill px-4 py-2 fw-bold">Learn More</a>
    </div>
  </div>
</section>
<!-- PRICING SECTION END -->

<!-- Count-Up Animation Script -->
<script>
(function() {
  const counters = document.querySelectorAll('.count-up');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const counter = entry.target;
        const target = parseInt(counter.dataset.target);
        const duration = 2000;
        const step = Math.max(1, Math.floor(target / 60));
        let current = 0;

        const update = () => {
          current += step;
          if (current >= target) {
            counter.textContent = target;
            observer.unobserve(counter);
            return;
          }
          counter.textContent = current;
          requestAnimationFrame(update);
        };
        update();
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => observer.observe(c));
})();

// Dynamic Hero Background Carousel Synchronization
(function() {
  const carousel = document.getElementById('heroCarousel');
  if (!carousel) return;
  carousel.addEventListener('slide.bs.carousel', function (e) {
    const nextIndex = e.to;
    const bgLayers = document.querySelectorAll('.hero-bg-layer');
    bgLayers.forEach((layer, idx) => {
      if (idx === nextIndex) {
        layer.classList.add('active');
      } else {
        layer.classList.remove('active');
      }
    });
  });
})();


</script>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>