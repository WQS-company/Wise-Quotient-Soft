<?php
$page_title = 'About Us - Driving Scale & Innovation | Wise Quotient Soft';
$seo = [
    'title'       => 'About Us - Driving Scale & Innovation | Wise Quotient Soft',
    'description' => 'Learn about Wise Quotient Soft — a leading software development company in Nigeria specializing in custom software, AI, mobile apps, and digital transformation solutions.',
    'keywords'    => 'about Wise Quotient Soft, WQS Nigeria, software company Kaduna, tech company Nigeria, about us, our mission, our team',
    'canonical'   => 'https://wisequotientsoft.com/about.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-about.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'About Us', 'url' => '/about.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
  .about-hero {
    background: linear-gradient(rgba(3, 7, 18, 0.75), rgba(3, 7, 18, 0.9)), url('images/about_hero_bg.png') center/cover no-repeat, #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .about-hero-glow {
    position: absolute; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, transparent 70%);
    top: -50px; left: 50%; transform: translateX(-50%); filter: blur(80px); pointer-events: none;
  }
  .about-card-premium {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 2.5rem;
    transition: var(--transition-smooth);
    box-shadow: var(--shadow-soft);
  }
  .about-card-premium:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #cbd5e1;
  }
  .value-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    transition: var(--transition-smooth);
    height: 100%;
    box-shadow: var(--shadow-soft);
  }
  .value-box:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #ff6600;
  }
  .value-icon {
    width: 50px; height: 50px;
    background-color: rgba(255, 102, 0, 0.08);
    color: #ff6600;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; margin-bottom: 1.25rem;
    transition: all 0.3s ease;
  }
  .value-box:hover .value-icon {
    background-color: #ff6600;
    color: #ffffff;
  }
  .team-avatar {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00264d 0%, #ff6600 100%);
    color: white;
    font-size: 1.8rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 6px 15px rgba(0, 38, 77, 0.15);
  }
  .team-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem 1.5rem;
    text-align: center;
    transition: var(--transition-smooth);
    box-shadow: var(--shadow-soft);
    height: 100%;
  }
  .team-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #cbd5e1;
  }
  .team-social {
    display: flex; justify-content: center; gap: 0.75rem; margin-top: 1rem;
  }
  .team-social a {
    color: #94a3b8; font-size: 0.95rem; transition: color 0.2s ease;
  }
  .team-social a:hover {
    color: #ff6600;
  }
</style>

<!-- About Hero Section -->
<section class="about-hero">
  <div class="about-hero-glow"></div>
  <div class="container position-relative" style="z-index: 10;">
    <span class="badge mb-3" style="background: rgba(225, 85, 1, 0.15); color: #ff6600; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(225, 85, 1, 0.3); padding: 0.5rem 1.2rem; border-radius: 50px;">
      Who We Are
    </span>
    <h1 class="fw-extrabold text-white mb-3" style="font-size: clamp(2rem, 5vw, 3rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">
      Crafting Scalable <span class="text-orange">Digital Solutions</span>
    </h1>
    <p class="text-muted mx-auto" style="max-width: 600px; color: #cbd5e1 !important; font-size: 1.05rem;">
      We bridge the gap between complex engineering and elegant, user-friendly experiences to help businesses scale globally.
    </p>
  </div>
</section>

<!-- Company Overview -->
<section class="py-6 bg-body">
  <div class="container py-4">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal-up">
        <div class="about-card-premium">
          <h2 class="fw-bold mb-4" style="color: #00264d; font-family: 'Plus Jakarta Sans', sans-serif;">Our Mission & Vision</h2>
          <p class="text-muted" style="line-height: 1.7;">
            At Wise Quotient Soft (WQS), our mission is to build highly reliable, performant, and secure software platforms that empower businesses. We believe that technology should be an asset, not a bottleneck, which is why we follow industry-standard coding standards and rigorous QA audits.
          </p>
          <p class="text-muted mb-0" style="line-height: 1.7;">
            From web frameworks to high-performance desktop clients and mobile applications, we leverage top-tier technical stacks to build solutions engineered for growth and robust scalability.
          </p>
        </div>
      </div>
      <div class="col-lg-6 reveal-up" style="transition-delay: 0.2s;">
        <div class="row g-4">
          <div class="col-6">
            <div class="border rounded-4 p-4 text-center bg-body-tertiary">
              <h3 class="fw-bold text-orange mb-1" style="font-size: 2.2rem; font-family: 'Outfit', sans-serif;">50+</h3>
              <p class="text-muted mb-0 font-monospace" style="font-size: 0.82rem;">PROJECTS COMPLETED</p>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded-4 p-4 text-center bg-body-tertiary">
              <h3 class="fw-bold text-orange mb-1" style="font-size: 2.2rem; font-family: 'Outfit', sans-serif;">99%</h3>
              <p class="text-muted mb-0 font-monospace" style="font-size: 0.82rem;">CLIENT SATISFACTION</p>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded-4 p-4 text-center bg-body-tertiary">
              <h3 class="fw-bold text-orange mb-1" style="font-size: 2.2rem; font-family: 'Outfit', sans-serif;">24/7</h3>
              <p class="text-muted mb-0 font-monospace" style="font-size: 0.82rem;">PRIORITY SUPPORT</p>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded-4 p-4 text-center bg-body-tertiary">
              <h3 class="fw-bold text-orange mb-1" style="font-size: 2.2rem; font-family: 'Outfit', sans-serif;">15+</h3>
              <p class="text-muted mb-0 font-monospace" style="font-size: 0.82rem;">SENIOR ARCHITECTS</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Core Values -->
<section class="py-6 bg-body-tertiary border-top">
  <div class="container py-4">
    <div class="text-center mb-5 reveal-fade">
      <h2 class="fw-extrabold text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">Our Core <span class="text-orange">Values</span></h2>
      <p class="text-muted">The foundational principles that guide every single line of code we write.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4 reveal-up">
        <div class="value-box">
          <div class="value-icon"><i class="fas fa-lightbulb"></i></div>
          <h4 class="fw-bold text-body mb-3" style="font-size: 1.15rem;">Continuous Innovation</h4>
          <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.6;">We stay at the bleeding edge of software architecture and automation, integrating state-of-the-art AI systems and tools into everyday platforms.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="value-box">
          <div class="value-icon"><i class="fas fa-shield-alt"></i></div>
          <h4 class="fw-bold text-body mb-3" style="font-size: 1.15rem;">Uncompromising Quality</h4>
          <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.6;">Our software is designed with secure standard configurations. Everything is double-reviewed and optimized for code sustainability.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="value-box">
          <div class="value-icon"><i class="fas fa-handshake"></i></div>
          <h4 class="fw-bold text-body mb-3" style="font-size: 1.15rem;">Trust & Transparency</h4>
          <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.6;">We maintain direct alignment with our partners and clients, providing transparent development progress tracking, detailed analytics, and active support.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Hired Team Profiles -->
<section class="py-6 bg-body border-top">
  <div class="container py-4">
    <div class="text-center mb-5 reveal-fade">
      <h2 class="fw-extrabold text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">Our Leadership <span class="text-orange">Team</span></h2>
      <p class="text-muted">Driven by seasoned technologists committed to digital excellence.</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php
      try {
          $lead_stmt = $pdo->query("SELECT lt.*, u.name, u.picture FROM leadership_team lt 
                                    JOIN users u ON lt.user_id = u.id 
                                    ORDER BY lt.display_order ASC");
          $leaders = $lead_stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {
          $leaders = [];
      }
      
      if (!empty($leaders)):
          foreach ($leaders as $leader):
              $name = $leader['name'];
              $designation = $leader['designation'];
              $bio = $leader['bio'];
              $picture = $leader['picture'];
              $linkedin = $leader['linkedin_url'];
              $twitter = $leader['twitter_url'];
              $github = $leader['github_url'];
              
              // Initials fallback logic
              $parts = explode(' ', $name);
              $initials = '';
              foreach ($parts as $p) {
                  $initials .= strtoupper($p[0] ?? '');
              }
              $initials = substr($initials, 0, 2);
      ?>
              <div class="col-md-4 reveal-up">
                <div class="team-card d-flex flex-column">
                  <div>
                    <?php if (!empty($picture)): ?>
                      <img src="<?= htmlspecialchars($picture) ?>" alt="<?= htmlspecialchars($name) ?>" class="team-avatar" style="object-fit: cover;">
                    <?php else: ?>
                      <div class="team-avatar"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <h4 class="fw-bold text-body mb-1" style="font-size: 1.15rem;"><?= htmlspecialchars($name) ?></h4>
                    <p class="text-orange small font-monospace mb-3" style="text-transform: uppercase; letter-spacing: 1px;"><?= htmlspecialchars($designation) ?></p>
                    <p class="text-muted mb-3" style="font-size: 0.88rem; line-height: 1.6; min-height: 80px;"><?= htmlspecialchars($bio) ?></p>
                  </div>
                  <div class="team-social mt-auto">
                    <?php if (!empty($linkedin) && $linkedin !== '#'): ?>
                      <a href="<?= htmlspecialchars($linkedin) ?>" aria-label="LinkedIn" target="_blank"><i class="fab fa-linkedin"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($twitter) && $twitter !== '#'): ?>
                      <a href="<?= htmlspecialchars($twitter) ?>" aria-label="Twitter" target="_blank"><i class="fab fa-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($github) && $github !== '#'): ?>
                      <a href="<?= htmlspecialchars($github) ?>" aria-label="GitHub" target="_blank"><i class="fab fa-github"></i></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
      <?php 
          endforeach;
      else:
      ?>
          <div class="text-center text-muted py-5">
              <i class="fas fa-users fa-3x mb-3" style="color: #cbd5e1;"></i>
              <p>Our team profiles are currently being initialized. Check back shortly!</p>
          </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
