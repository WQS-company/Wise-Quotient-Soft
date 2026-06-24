<?php
// Ensure $footerSettings is loaded
if (!isset($footerSettings)) {
    $footerSettings = [
        'about_text' => 'We build smart, scalable software solutions that help businesses grow through innovation, automation, and digital transformation.',
        'services_list' => "Custom Software Development\nMobile & Web Applications\nAI & Machine Learning Solutions\nCloud Architecture\nIT Consulting & Strategy",
        'contact_email' => 'info@wisequotient.com',
        'contact_phone' => '+2348077416106',
        'contact_address' => 'No.1 Ibadan Street Kaduna, Nigeria',
        'facebook_url' => 'https://www.facebook.com/share/1B3LW3nV7T/',
        'instagram_url' => 'https://www.instagram.com/wise_quotient_soft?igsh=YzljYTk1ODg3Zg==',
        'linkedin_url' => 'https://www.linkedin.com/in/wise-quotient-soft-51933a376',
        'twitter_url' => 'https://x.com/Wise_Quotient_Soft',
        'github_url' => 'http://github.com/WQS-company',
        'youtube_url' => 'https://www.youtube.com/channel/UCnpqd2bn7N5DZl1W2lhen3w',
        'copyright_text' => 'Wise Quotient Soft. All rights reserved.'
    ];
}
?>
<?php if(empty($hide_header_footer)): ?>
<!-- Footer HTML -->
<footer class="footer-section">
  <!-- Decorative Clouds -->
  <div class="cloud-svg cloud-top-left">
    <svg viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg">
      <path d="M30,60 C50,20 140,20 160,60 C180,80 20,80 30,60 Z" fill="#ffffff" />
    </svg>
  </div>
  <div class="cloud-svg cloud-center">
    <svg viewBox="0 0 250 100" xmlns="http://www.w3.org/2000/svg">
      <path d="M50,80 C70,30 180,30 200,80 C220,100 30,100 50,80 Z" fill="#ffffff" />
    </svg>
  </div>
  <div class="cloud-svg cloud-bottom-right">
    <svg viewBox="0 0 220 90" xmlns="http://www.w3.org/2000/svg">
      <path d="M40,70 C60,40 160,40 180,70 C200,90 20,90 40,70 Z" fill="#ffffff" />
    </svg>
  </div>

  <div class="container footer-grid">
    <!-- Col 1: About -->
    <div class="footer-col">
      <h5>About WQS</h5>
      <p><?= htmlspecialchars($footerSettings['about_text']) ?></p>
      <div class="social-icons">
        <?php if (!empty($footerSettings['facebook_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['facebook_url']) ?>" aria-label="Facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
        <?php endif; ?>
        <?php if (!empty($footerSettings['instagram_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['instagram_url']) ?>" aria-label="Instagram" target="_blank"><i class="fab fa-instagram"></i></a>
        <?php endif; ?>
        <?php if (!empty($footerSettings['linkedin_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['linkedin_url']) ?>" aria-label="LinkedIn" target="_blank"><i class="fab fa-linkedin-in"></i></a>
        <?php endif; ?>
        <?php if (!empty($footerSettings['twitter_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['twitter_url']) ?>" aria-label="Twitter" target="_blank"><i class="fab fa-twitter"></i></a>
        <?php endif; ?>
        <?php if (!empty($footerSettings['github_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['github_url']) ?>" aria-label="GitHub" target="_blank"><i class="fab fa-github"></i></a>
        <?php endif; ?>
        <?php if (!empty($footerSettings['youtube_url'])): ?>
          <a href="<?= htmlspecialchars($footerSettings['youtube_url']) ?>" aria-label="YouTube" target="_blank"><i class="fab fa-youtube"></i></a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Col 2: Services -->
    <div class="footer-col">
      <h5>Our Services</h5>
      <ul class="footer-links-list">
        <?php 
        $services = array_filter(array_map('trim', explode("\n", $footerSettings['services_list'] ?? '')));
        foreach ($services as $svc):
        ?>
          <li><a href="services.php"><?= htmlspecialchars($svc) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Col 3: Links -->
    <div class="footer-col">
      <h5>Resources & Legal</h5>
      <ul class="footer-links-list">
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="scholarships.php"><i class="fas fa-graduation-cap me-1"></i>Scholarships</a></li>
        <li><a href="team.php">Our Team</a></li>
        <li><a href="blog.php">Blog & Insights</a></li>
        <li><a href="privacy.php">Privacy Policy</a></li>
        <li><a href="terms.php">Terms of Service</a></li>
        <li><a href="cookies.php">Cookie Policy</a></li>
        <li><a href="javascript:void(0)" onclick="openCookieSettings()">Cookie Settings</a></li>
      </ul>
    </div>

    <!-- Col 4: Contact & Newsletter -->
    <div class="footer-col">
      <h5>Contact Us</h5>
      <ul class="footer-contact-info">
        <?php if (!empty($footerSettings['contact_address'])): ?>
          <li>
            <i class="fas fa-map-marker-alt"></i>
            <span><?= htmlspecialchars($footerSettings['contact_address']) ?></span>
          </li>
        <?php endif; ?>
        <?php if (!empty($footerSettings['contact_phone'])): 
          $phones = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $footerSettings['contact_phone']))));
          foreach ($phones as $phone):
            $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        ?>
          <li>
            <i class="fas fa-phone-alt"></i>
            <a href="tel:<?= htmlspecialchars($clean_phone) ?>"><?= htmlspecialchars($phone) ?></a>
          </li>
        <?php 
          endforeach;
        endif; 
        ?>
        <?php if (!empty($footerSettings['whatsapp_number'])): 
          $clean_wa = preg_replace('/[^0-9]/', '', $footerSettings['whatsapp_number']);
        ?>
          <li>
            <i class="fab fa-whatsapp" style="color: #25D366;"></i>
            <a href="https://wa.me/<?= htmlspecialchars($clean_wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Me</a>
          </li>
        <?php endif; ?>
        <?php if (!empty($footerSettings['contact_email'])): ?>
          <li>
            <i class="fas fa-envelope"></i>
            <a href="mailto:<?= htmlspecialchars($footerSettings['contact_email']) ?>"><?= htmlspecialchars($footerSettings['contact_email']) ?></a>
          </li>
        <?php endif; ?>
      </ul>
      
      <div class="mt-4">
        <form class="newsletter-form">
          <input type="email" placeholder="Your email address" required aria-label="Email Address">
          <button type="submit">Subscribe</button>
        </form>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container">
      &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($footerSettings['copyright_text']) ?></strong>. All rights reserved.
    </div>
  </div>
</footer>
<?php endif; ?>

<!-- Back to Top Button -->
<a href="#" id="backToTop" aria-label="Back to top">
  <i class="fas fa-chevron-up"></i>
</a>

</div> <!-- /.main-content -->

<!-- JavaScript bundles -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Shared preloader, sidebar and search interactions script -->
<script>
  // Preloader with smooth fade-out and optimized loading logic
  (function() {
    const preloader = document.querySelector('.preloader');
    if (preloader) {
      const startTime = performance.now();
      const minDisplayTime = 600; // Minimum time in ms to show the branded spinner
      const maxTimeout = 2000;    // Failsafe timeout in ms to force reveal the page
      let preloaderDismissed = false;

      const dismissPreloader = () => {
        if (preloaderDismissed) return;
        preloaderDismissed = true;

        const elapsedTime = performance.now() - startTime;
        const delay = Math.max(0, minDisplayTime - elapsedTime);

        setTimeout(() => {
          preloader.classList.add('fade-out');
          setTimeout(() => {
            preloader.style.display = 'none';
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
              mainContent.style.display = 'block';
            }
          }, 400);
        }, delay);
      };

      // Attempt to dismiss on DOMContentLoaded (DOM ready & head stylesheets parsed)
      if (document.readyState === 'interactive' || document.readyState === 'complete') {
        dismissPreloader();
      } else {
        document.addEventListener('DOMContentLoaded', dismissPreloader);
      }

      // Fallback: window load event
      window.addEventListener('load', dismissPreloader);

      // Failsafe: Force fade-out after maxTimeout
      setTimeout(dismissPreloader, maxTimeout);
    }
  })();

  // Sidebar toggle
  const hamburger = document.getElementById('hamburgerBtn');
  const sidebar = document.getElementById('uniqueSidebar');

  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function(e) {
      e.stopPropagation();
      sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function(e) {
      if (!sidebar.contains(e.target) && e.target !== hamburger) {
        sidebar.classList.remove('open');
      }
    });
  }

  // Search trigger toggle
  function toggleSearchBar() {
    const panel = document.getElementById("searchPanel");
    if (panel) {
      panel.classList.toggle("open");
      document.body.classList.toggle("no-scroll", panel.classList.contains("open"));
    }
  }

  // Live search keyup event handling
  const headerSearchInput = document.getElementById('headerSearchInput');
  const liveSearchResults = document.getElementById('liveSearchResults');
  const liveSearchResultsList = document.getElementById('liveSearchResultsList');
  const defaultSearchTopics = document.getElementById('defaultSearchTopics');
  let searchTimeout = null;

  if (headerSearchInput) {
    headerSearchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const query = this.value.trim();

      if (query.length < 2) {
        liveSearchResults.style.display = 'none';
        defaultSearchTopics.style.display = 'block';
        return;
      }

      // Show temporary loading indicator
      liveSearchResults.style.display = 'block';
      defaultSearchTopics.style.display = 'none';
      liveSearchResultsList.innerHTML = `
        <div class="text-center py-4 text-muted">
          <i class="fas fa-spinner fa-spin me-2"></i>Searching...
        </div>
      `;

      searchTimeout = setTimeout(() => {
        fetch(`search_api.php?q=${encodeURIComponent(query)}`)
          .then(res => res.json())
          .then(data => {
            if (data && data.length > 0) {
              let html = '';
              data.forEach(item => {
                let imgHtml = '';
                if (item.image) {
                  imgHtml = `<img src="${item.image}" alt="" class="rounded" style="width: 45px; height: 45px; object-fit: cover;">`;
                } else {
                  imgHtml = `
                    <div class="rounded d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; background: ${item.color || '#ff6600'}; color: white; font-size: 1.1rem; flex-shrink: 0;">
                      <i class="${item.icon || 'fas fa-search'}"></i>
                    </div>
                  `;
                }

                html += `
                  <a href="${item.url}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 border-0 border-bottom">
                    ${imgHtml}
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-body">${item.title}</h6>
                        <span class="badge rounded-pill text-uppercase px-2" style="font-size: 0.65rem; background: ${item.color || '#ff6600'}; color: white;">${item.type}</span>
                      </div>
                      <p class="mb-0 text-muted small text-truncate" style="max-width: 500px;">${item.desc}</p>
                    </div>
                  </a>
                `;
              });
              liveSearchResultsList.innerHTML = html;
            } else {
              liveSearchResultsList.innerHTML = `
                <div class="text-center py-4 text-muted">
                  <i class="fas fa-search-minus me-2"></i>No matches found.
                </div>
              `;
            }
          })
          .catch(() => {
            liveSearchResultsList.innerHTML = `
              <div class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Error fetching results.
              </div>
            `;
          });
      }, 300);
    });
  }

  // Show/hide back to top button
  window.addEventListener("scroll", () => {
    const btn = document.getElementById("backToTop");
    if (btn) {
      if (window.scrollY > 300) {
        btn.style.display = "block";
      } else {
        btn.style.display = "none";
      }
    }
  });

  // Smooth scroll to top
  const backToTopBtn = document.getElementById("backToTop");
  if (backToTopBtn) {
    backToTopBtn.addEventListener("click", (e) => {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    });
  }

  // Scroll Reveal Animation Observer
  const revealElements = document.querySelectorAll('.reveal-up, .reveal-fade');
  if (revealElements.length > 0) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
          observer.unobserve(entry.target); // Only animate once
        }
      });
    }, {
      root: null,
      threshold: 0.1,
      rootMargin: "0px 0px -50px 0px"
    });

    revealElements.forEach(el => revealObserver.observe(el));
  }

  // Analytics tracking for user conversion events and clicks
  document.addEventListener('DOMContentLoaded', function() {
    // Newsletter Form submissions
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
      newsletterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const emailInput = this.querySelector('input[type="email"]');
        if (emailInput) {
          const email = emailInput.value.trim();
          if (typeof window.wqsLogEvent === 'function') {
            window.wqsLogEvent('newsletter_subscription', email);
          }
          if (window.Swal) {
            Swal.fire({
              title: 'Subscribed!',
              text: 'Thank you for subscribing to our newsletter.',
              icon: 'success',
              confirmButtonColor: '#ff6600'
            });
          } else {
            alert('Thank you for subscribing to our newsletter.');
          }
          this.reset();
        }
      });
    }

    // Home service cards clicks
    const serviceCards = document.querySelectorAll('.service-card-premium');
    serviceCards.forEach(card => {
      card.addEventListener('click', function() {
        const titleEl = this.querySelector('.service-card-title');
        const serviceName = titleEl ? titleEl.textContent.trim() : 'Unknown Service';
        if (typeof window.wqsLogEvent === 'function') {
          window.wqsLogEvent('service_card_click', serviceName);
        }
      });
    });

    // Service category tab clicks
    const serviceTabs = document.querySelectorAll('#v-pills-tab .nav-link');
    serviceTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabName = this.textContent.trim();
        if (typeof window.wqsLogEvent === 'function') {
          window.wqsLogEvent('service_category_view', tabName);
        }
      });
    });

    // Pricing plan clicks
    const pricingButtons = document.querySelectorAll('.pricing-box-premium a');
    pricingButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        const pricingBox = this.closest('.pricing-box-premium');
        if (pricingBox) {
          const titleEl = pricingBox.querySelector('.plan-title');
          const planName = titleEl ? titleEl.textContent.trim() : 'Unknown Plan';
          if (typeof window.wqsLogEvent === 'function') {
            window.wqsLogEvent('pricing_plan_select', planName);
          }
        }
      });
    });
  });
</script>

<!-- ElevenLabs widget script -->
<script 
    src="https://unpkg.com/@elevenlabs/convai-widget-embed" 
    async 
    type="text/javascript">
</script>

<!-- Embedded Cookie preferences check -->
<?php
$cookiePath = __DIR__ . '/cookie_consent.php';
if (file_exists($cookiePath)) {
    include_once $cookiePath;
}

// Embed wise-bot chatbot assistant if present
$botPath = __DIR__ . '/wise-bot.php';
if (file_exists($botPath)) {
    include_once $botPath;
}

// Embed floating popup widget
$popupWidgetPath = __DIR__ . '/popup_widget.php';
if (file_exists($popupWidgetPath)) {
    include_once $popupWidgetPath;
}
?>

<!-- Remove announcement bar from all public pages -->
<script>
(function() {
  var banner = document.getElementById('wqs-announcement-banner');
  if (banner) banner.remove();
  var observer = new MutationObserver(function() {
    var b = document.getElementById('wqs-announcement-banner');
    if (b) { b.remove(); observer.disconnect(); }
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();
</script>

</body>
</html>
