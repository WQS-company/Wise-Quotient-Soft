<?php
$path_to_root = './';
$page_title = 'Contact Us - Get in Touch | Wise Quotient Soft';
$seo = [
    'title'       => 'Contact Us - Get in Touch | Wise Quotient Soft',
    'description' => 'Contact Wise Quotient Soft for custom software development, AI solutions, IT consulting, and digital transformation. Reach us in Kaduna, Nigeria or worldwide.',
    'keywords'    => 'contact WQS, Wise Quotient Soft contact, software company contact Nigeria, IT consulting contact, Kaduna tech company',
    'canonical'   => 'https://wisequotientsoft.com/contact.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-contact.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Contact Us', 'url' => '/contact.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════════
   WQS PREMIUM CONTACT PAGE — Enterprise SaaS Design
   ══════════════════════════════════════════════════════════════ */

:root {
  --wqs-navy: #0A2D5E;
  --wqs-navy-dark: #061d3a;
  --wqs-orange: #ea580c;
  --wqs-orange-light: #fb923c;
  --wqs-blue: #3b82f6;
  --wqs-blue-light: #60a5fa;
  --wqs-purple: #8b5cf6;
  --wqs-slate: #0f172a;
  --wqs-slate-light: #1e293b;
  --wqs-gray-50: #f8fafc;
  --wqs-gray-100: #f1f5f9;
  --wqs-gray-200: #e2e8f0;
  --wqs-gray-300: #cbd5e1;
  --wqs-gray-400: #94a3b8;
  --wqs-gray-500: #64748b;
  --wqs-gray-600: #475569;
  --wqs-gray-700: #334155;
  --wqs-glass: rgba(255,255,255,0.06);
  --wqs-glass-border: rgba(255,255,255,0.1);
  --wqs-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
  --wqs-shadow: 0 4px 24px rgba(0,0,0,0.08);
  --wqs-shadow-lg: 0 12px 48px rgba(0,0,0,0.12);
  --wqs-shadow-glow: 0 0 60px rgba(59,130,246,0.15);
  --wqs-radius: 16px;
  --wqs-radius-lg: 24px;
  --wqs-radius-xl: 32px;
  --wqs-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Hero Section ── */
.wqs-contact-hero {
  background: linear-gradient(160deg, #061d3a 0%, #0A2D5E 40%, #1a3a6a 100%);
  padding: 5.5rem 0 4rem;
  color: white;
  position: relative;
  overflow: hidden;
}
.wqs-contact-hero::before {
  content: '';
  position: absolute;
  top: -40%;
  right: -15%;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.wqs-contact-hero::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -10%;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(234,88,12,0.08) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.wqs-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(234,88,12,0.12);
  border: 1px solid rgba(234,88,12,0.25);
  color: var(--wqs-orange-light);
  padding: 0.5rem 1.25rem;
  border-radius: 50px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 1.5rem;
}
.wqs-hero-badge i { font-size: 0.7rem; }
.wqs-hero-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800;
  font-size: clamp(2.2rem, 5vw, 3.5rem);
  line-height: 1.15;
  margin-bottom: 1.25rem;
}
.wqs-hero-title .wqs-text-gradient {
  background: linear-gradient(135deg, var(--wqs-orange) 0%, var(--wqs-orange-light) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.wqs-hero-desc {
  color: var(--wqs-gray-300);
  font-size: 1.1rem;
  max-width: 620px;
  margin: 0 auto;
  line-height: 1.7;
}

/* ── Contact Info Cards ── */
.wqs-info-card {
  background: white;
  border-radius: var(--wqs-radius);
  border: 1px solid var(--wqs-gray-200);
  padding: 1.5rem;
  transition: var(--wqs-transition);
  position: relative;
  overflow: hidden;
}
.wqs-info-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--wqs-blue), var(--wqs-purple));
  opacity: 0;
  transition: var(--wqs-transition);
}
.wqs-info-card:hover {
  border-color: rgba(59,130,246,0.2);
  box-shadow: var(--wqs-shadow);
  transform: translateY(-2px);
}
.wqs-info-card:hover::before { opacity: 1; }
.wqs-info-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: 1rem;
  position: relative;
}
.wqs-info-icon::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  opacity: 0.1;
}
.wqs-info-icon.blue { background: rgba(59,130,246,0.1); color: var(--wqs-blue); }
.wqs-info-icon.orange { background: rgba(234,88,12,0.1); color: var(--wqs-orange); }
.wqs-info-icon.purple { background: rgba(139,92,246,0.1); color: var(--wqs-purple); }
.wqs-info-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.wqs-info-title {
  font-weight: 700;
  color: var(--wqs-slate);
  font-size: 0.95rem;
  margin-bottom: 0.35rem;
}
.wqs-info-text {
  color: var(--wqs-gray-500);
  font-size: 0.88rem;
  line-height: 1.6;
  margin: 0;
}
.wqs-info-text a {
  color: var(--wqs-blue);
  text-decoration: none;
  transition: var(--wqs-transition);
}
.wqs-info-text a:hover { color: var(--wqs-navy); text-decoration: underline; }

/* ── Trust Section ── */
.wqs-trust-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0.75rem 0;
}
.wqs-trust-check {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba(16,185,129,0.1);
  color: #10b981;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  flex-shrink: 0;
}
.wqs-trust-text {
  font-weight: 600;
  color: var(--wqs-slate);
  font-size: 0.92rem;
}

/* ── Premium Form ── */
.wqs-form-card {
  background: white;
  border-radius: var(--wqs-radius-lg);
  border: 1px solid var(--wqs-gray-200);
  box-shadow: var(--wqs-shadow);
  padding: 2.5rem;
  position: relative;
}
.wqs-form-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--wqs-navy), var(--wqs-blue), var(--wqs-purple));
  border-radius: var(--wqs-radius-lg) var(--wqs-radius-lg) 0 0;
}
.wqs-form-group {
  margin-bottom: 1.25rem;
}
.wqs-form-label {
  display: block;
  font-weight: 700;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--wqs-gray-500);
  margin-bottom: 0.5rem;
}
.wqs-form-input {
  width: 100%;
  border: 1.5px solid var(--wqs-gray-200);
  border-radius: 12px;
  padding: 0.85rem 1rem;
  font-size: 0.92rem;
  color: var(--wqs-slate);
  background: var(--wqs-gray-50);
  transition: var(--wqs-transition);
  outline: none;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.wqs-form-input::placeholder {
  color: var(--wqs-gray-400);
  font-weight: 400;
}
.wqs-form-input:focus {
  border-color: var(--wqs-blue);
  box-shadow: 0 0 0 4px rgba(59,130,246,0.08);
  background: white;
}
.wqs-form-input:hover {
  border-color: var(--wqs-gray-300);
}
.wqs-form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M4.646 5.646a.5.5 0 0 1 .708 0L8 8.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 1rem center;
  background-size: 16px;
  padding-right: 2.5rem;
}

/* ── Submit Button ── */
.wqs-btn-submit {
  width: 100%;
  padding: 1rem 2rem;
  border: none;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--wqs-navy) 0%, #1a3a6a 100%);
  color: white;
  font-size: 1rem;
  font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer;
  transition: var(--wqs-transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  position: relative;
  overflow: hidden;
}
.wqs-btn-submit::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
  transition: left 0.5s ease;
}
.wqs-btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(10,45,94,0.3);
}
.wqs-btn-submit:hover::before { left: 100%; }
.wqs-btn-submit:active { transform: translateY(0); }
.wqs-btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none !important;
}
.wqs-btn-submit .wqs-btn-arrow {
  transition: transform 0.3s ease;
}
.wqs-btn-submit:hover .wqs-btn-arrow {
  transform: translateX(4px);
}

/* ── Map Section ── */
.wqs-map-section {
  background: var(--wqs-slate);
  padding: 0;
  position: relative;
}
.wqs-map-wrapper {
  position: relative;
  height: 400px;
  border-radius: 0;
  overflow: hidden;
}
.wqs-map-wrapper iframe {
  width: 100%;
  height: 100%;
  border: 0;
  filter: grayscale(0.3) contrast(1.1);
}
.wqs-map-overlay {
  position: absolute;
  top: 1.5rem;
  left: 1.5rem;
  background: rgba(15,23,42,0.9);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  color: white;
  max-width: 280px;
}
.wqs-map-overlay h6 {
  font-weight: 700;
  font-size: 0.95rem;
  margin-bottom: 0.35rem;
}
.wqs-map-overlay p {
  color: var(--wqs-gray-400);
  font-size: 0.82rem;
  margin: 0;
}

/* ── CTA Section ── */
.wqs-cta-section {
  background: linear-gradient(160deg, #061d3a 0%, #0A2D5E 50%, #1a3a6a 100%);
  padding: 5rem 0;
  position: relative;
  overflow: hidden;
}
.wqs-cta-section::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(234,88,12,0.1) 0%, transparent 70%);
  border-radius: 50%;
}
.wqs-cta-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800;
  font-size: clamp(1.8rem, 4vw, 2.8rem);
  color: white;
  margin-bottom: 1rem;
}
.wqs-cta-desc {
  color: var(--wqs-gray-300);
  font-size: 1.05rem;
  max-width: 560px;
  margin: 0 auto 2rem;
  line-height: 1.7;
}
.wqs-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0.9rem 2rem;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.92rem;
  text-decoration: none;
  transition: var(--wqs-transition);
  border: none;
  cursor: pointer;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.wqs-btn-primary.solid {
  background: linear-gradient(135deg, var(--wqs-orange) 0%, #c2410c 100%);
  color: white;
}
.wqs-btn-primary.solid:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(234,88,12,0.3);
  color: white;
  text-decoration: none;
}
.wqs-btn-primary.outline {
  background: transparent;
  color: white;
  border: 1.5px solid rgba(255,255,255,0.25);
}
.wqs-btn-primary.outline:hover {
  background: rgba(255,255,255,0.08);
  border-color: rgba(255,255,255,0.4);
  color: white;
  text-decoration: none;
}

/* ── Success Modal ── */
.wqs-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(6,29,58,0.7);
  backdrop-filter: blur(8px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: var(--wqs-transition);
}
.wqs-modal-backdrop.show {
  opacity: 1;
  visibility: visible;
}
.wqs-modal-card {
  background: white;
  border-radius: var(--wqs-radius-lg);
  padding: 3rem;
  max-width: 440px;
  width: 90%;
  text-align: center;
  box-shadow: var(--wqs-shadow-lg);
  transform: scale(0.9) translateY(20px);
  transition: var(--wqs-transition);
}
.wqs-modal-backdrop.show .wqs-modal-card {
  transform: scale(1) translateY(0);
}
.wqs-modal-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(16,185,129,0.1);
  color: #10b981;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  margin: 0 auto 1.25rem;
}
.wqs-modal-title {
  font-weight: 800;
  font-size: 1.4rem;
  color: var(--wqs-slate);
  margin-bottom: 0.5rem;
}
.wqs-modal-text {
  color: var(--wqs-gray-500);
  font-size: 0.92rem;
  line-height: 1.6;
  margin-bottom: 0.75rem;
}
.wqs-modal-ref {
  display: inline-block;
  background: var(--wqs-gray-100);
  border: 1px solid var(--wqs-gray-200);
  border-radius: 8px;
  padding: 0.4rem 1rem;
  font-family: monospace;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--wqs-navy);
  margin-bottom: 1.5rem;
}
.wqs-modal-btns {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* ── Floating Labels ── */
.wqs-float-group {
  position: relative;
}
.wqs-float-group .wqs-float-label {
  position: absolute;
  top: 50%;
  left: 1rem;
  transform: translateY(-50%);
  color: var(--wqs-gray-400);
  font-size: 0.92rem;
  pointer-events: none;
  transition: var(--wqs-transition);
  background: transparent;
  padding: 0 4px;
}
.wqs-float-group .wqs-form-input:focus ~ .wqs-float-label,
.wqs-float-group .wqs-form-input:not(:placeholder-shown) ~ .wqs-float-label {
  top: -8px;
  left: 0.75rem;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--wqs-blue);
  background: white;
}

/* ── Spinner ── */
.wqs-spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: wqs-spin 0.7s linear infinite;
  display: none;
}
.wqs-btn-submit.loading .wqs-spinner { display: block; }
.wqs-btn-submit.loading .wqs-btn-text { display: none; }
.wqs-btn-submit.loading .wqs-btn-arrow { display: none; }
@keyframes wqs-spin { to { transform: rotate(360deg); } }

/* ── Section Header ── */
.wqs-section-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(59,130,246,0.08);
  border: 1px solid rgba(59,130,246,0.15);
  color: var(--wqs-blue);
  padding: 0.4rem 1rem;
  border-radius: 50px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 1rem;
}
.wqs-section-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800;
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  color: var(--wqs-slate);
  margin-bottom: 0.75rem;
}
.wqs-section-desc {
  color: var(--wqs-gray-500);
  font-size: 1rem;
  max-width: 520px;
  line-height: 1.6;
}

/* ── Responsive ── */
@media (max-width: 991.98px) {
  .wqs-contact-hero { padding: 6rem 0 4rem; }
  .wqs-map-wrapper { height: 300px; }
  .wqs-form-card { padding: 2rem; }
}
@media (max-width: 575.98px) {
  .wqs-contact-hero { padding: 5rem 0 3rem; }
  .wqs-form-card { padding: 1.5rem; }
  .wqs-modal-card { padding: 2rem; }
  .wqs-map-wrapper { height: 250px; }
}
</style>

<!-- ══════════════════════════════════════════════════════════
     SECTION 1: PREMIUM HERO HEADER
     ══════════════════════════════════════════════════════════ -->
<section class="wqs-contact-hero">
  <div class="container position-relative" style="z-index: 10;">
    <div class="text-center">
      <div class="wqs-hero-badge">
        <i class="fas fa-comments"></i>
        Let's Build Something Amazing
      </div>
      <h1 class="wqs-hero-title">
        Get In Touch With<br>
        <span class="wqs-text-gradient">Wise Quotient Soft</span>
      </h1>
      <p class="wqs-hero-desc">
        Have a project idea, need a custom software solution, or want to discuss digital transformation? Our experts are ready to help.
      </p>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     SECTION 2 & 3: CONTACT INFO CARDS + TRUST SECTION
     ══════════════════════════════════════════════════════════ -->
<section class="py-5" style="background: var(--wqs-gray-50);">
  <div class="container">
    <div class="row g-4 align-items-start">
      <!-- Left: Contact Info -->
      <div class="col-lg-5">
        <div class="mb-4">
          <div class="wqs-section-badge"><i class="fas fa-address-card"></i> Contact Info</div>
          <h2 class="wqs-section-title">Let's Start a<br>Conversation</h2>
          <p class="wqs-section-desc">Reach out to us through any of these channels. We respond within 24 hours.</p>
        </div>

        <div class="d-flex flex-column gap-3">
          <!-- Office Address -->
          <div class="wqs-info-card">
            <div class="d-flex align-items-start gap-3">
              <div class="wqs-info-icon blue">
                <i class="fas fa-location-dot"></i>
              </div>
              <div>
                <h6 class="wqs-info-title">Head Office</h6>
                <p class="wqs-info-text">
                  No. 1 Ibadan Street,<br>
                  Kaduna State, Nigeria
                </p>
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="wqs-info-card">
            <div class="d-flex align-items-start gap-3">
              <div class="wqs-info-icon orange">
                <i class="fas fa-envelope"></i>
              </div>
              <div>
                <h6 class="wqs-info-title">Business Email</h6>
                <p class="wqs-info-text">
                  <a href="mailto:wisequotientsoftltd@gmail.com">wisequotientsoftltd@gmail.com</a>
                </p>
              </div>
            </div>
          </div>

          <!-- Phone -->
          <div class="wqs-info-card">
            <div class="d-flex align-items-start gap-3">
              <div class="wqs-info-icon purple">
                <i class="fas fa-phone"></i>
              </div>
              <div>
                <h6 class="wqs-info-title">Call Us</h6>
                <p class="wqs-info-text">
                  <a href="tel:+2348068673647">+234 806 867 3647</a><br>
                  <a href="tel:+2349057201740">+234 905 720 1740</a>
                </p>
              </div>
            </div>
          </div>

          <!-- Working Hours -->
          <div class="wqs-info-card">
            <div class="d-flex align-items-start gap-3">
              <div class="wqs-info-icon green">
                <i class="fas fa-clock"></i>
              </div>
              <div>
                <h6 class="wqs-info-title">Working Hours</h6>
                <p class="wqs-info-text">
                  Monday — Friday<br>
                  8:00 AM — 6:00 PM
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Trust Section -->
        <div class="mt-4 p-4" style="background: white; border-radius: var(--wqs-radius); border: 1px solid var(--wqs-gray-200);">
          <h6 class="fw-bold mb-3" style="color: var(--wqs-slate); font-size: 0.95rem;">
            <i class="fas fa-shield-halved me-2" style="color: var(--wqs-blue);"></i>Why Clients Choose Us
          </h6>
          <div class="d-flex flex-column gap-1">
            <div class="wqs-trust-item">
              <div class="wqs-trust-check"><i class="fas fa-check"></i></div>
              <span class="wqs-trust-text">Experienced Development Team</span>
            </div>
            <div class="wqs-trust-item">
              <div class="wqs-trust-check"><i class="fas fa-check"></i></div>
              <span class="wqs-trust-text">AI-Powered Solutions</span>
            </div>
            <div class="wqs-trust-item">
              <div class="wqs-trust-check"><i class="fas fa-check"></i></div>
              <span class="wqs-trust-text">Secure & Scalable Systems</span>
            </div>
            <div class="wqs-trust-item">
              <div class="wqs-trust-check"><i class="fas fa-check"></i></div>
              <span class="wqs-trust-text">Dedicated Technical Support</span>
            </div>
            <div class="wqs-trust-item">
              <div class="wqs-trust-check"><i class="fas fa-check"></i></div>
              <span class="wqs-trust-text">100% Client-Focused Approach</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Premium Contact Form -->
      <div class="col-lg-7">
        <div class="wqs-form-card">
          <div class="mb-4">
            <div class="wqs-section-badge"><i class="fas fa-paper-plane"></i> Send a Message</div>
            <h3 class="wqs-section-title" style="font-size: 1.5rem;">Request a Consultation</h3>
            <p style="color: var(--wqs-gray-500); font-size: 0.9rem; margin: 0;">Fill out the form below and our team will get back to you within 24 hours.</p>
          </div>

          <form id="contactForm" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Full Name *</label>
                  <input type="text" class="wqs-form-input" name="name" placeholder="John Doe" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Company Name</label>
                  <input type="text" class="wqs-form-input" name="company" placeholder="Acme Inc.">
                </div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Email Address *</label>
                  <input type="email" class="wqs-form-input" name="email" placeholder="john@company.com" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Phone Number</label>
                  <input type="tel" class="wqs-form-input" name="phone" placeholder="+234 800 000 0000">
                </div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Service Needed *</label>
                  <select class="wqs-form-input wqs-form-select" name="service" required>
                    <option value="" disabled selected>Select a service</option>
                    <option value="Web Development">Web Development</option>
                    <option value="Mobile App Development">Mobile App Development</option>
                    <option value="Desktop Software">Desktop Software</option>
                    <option value="AI Development">AI Development</option>
                    <option value="UI/UX Design">UI/UX Design</option>
                    <option value="Hospital Software">Hospital Software</option>
                    <option value="School Management System">School Management System</option>
                    <option value="E-Commerce Platform">E-Commerce Platform</option>
                    <option value="Cloud Solutions">Cloud Solutions</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="wqs-form-group">
                  <label class="wqs-form-label">Budget Range</label>
                  <select class="wqs-form-input wqs-form-select" name="budget">
                    <option value="" disabled selected>Select budget range</option>
                    <option value="Under ₦500,000">Under ₦500,000</option>
                    <option value="₦500,000 - ₦2,000,000">₦500,000 - ₦2,000,000</option>
                    <option value="₦2,000,000 - ₦5,000,000">₦2,000,000 - ₦5,000,000</option>
                    <option value="₦5,000,000 - ₦10,000,000">₦5,000,000 - ₦10,000,000</option>
                    <option value="Above ₦10,000,000">Above ₦10,000,000</option>
                    <option value="Not Sure Yet">Not Sure Yet</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="wqs-form-group">
              <label class="wqs-form-label">Project Timeline</label>
              <select class="wqs-form-input wqs-form-select" name="timeline">
                <option value="" disabled selected>When do you need this?</option>
                <option value="ASAP (1-2 weeks)">ASAP (1-2 weeks)</option>
                <option value="Standard (3-6 weeks)">Standard (3-6 weeks)</option>
                <option value="Relaxed (2-3 months)">Relaxed (2-3 months)</option>
                <option value="Not Sure Yet">Not Sure Yet</option>
              </select>
            </div>

            <div class="wqs-form-group">
              <label class="wqs-form-label">Project Details *</label>
              <textarea class="wqs-form-input" name="message" rows="4" placeholder="Tell us about your project, goals, and any specific requirements..." required style="resize: vertical; min-height: 110px;"></textarea>
            </div>

            <button type="submit" class="wqs-btn-submit" id="submitBtn">
              <span class="wqs-spinner"></span>
              <span class="wqs-btn-text">Request Consultation</span>
              <i class="fas fa-arrow-right wqs-btn-arrow"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     SECTION 8: GOOGLE MAP
     ══════════════════════════════════════════════════════════ -->
<section class="wqs-map-section">
  <div class="wqs-map-wrapper">
    <iframe 
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3963.3!2d7.43!3d10.52!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTDCsDMxJzEyLjAiTiA3wrAyNSc0OC4wIkU!5e0!3m2!1sen!2sng!4v1"
      allowfullscreen="" 
      loading="lazy" 
      referrerpolicy="no-referrer-when-downgrade"
      title="WQS Office Location - Kaduna, Nigeria">
    </iframe>
    <div class="wqs-map-overlay">
      <h6><i class="fas fa-location-dot me-2" style="color: var(--wqs-orange);"></i>Our Office</h6>
      <p>No. 1 Ibadan Street,<br>Kaduna State, Nigeria</p>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     SECTION 9: CTA PANEL
     ══════════════════════════════════════════════════════════ -->
<section class="wqs-cta-section">
  <div class="container position-relative" style="z-index: 10;">
    <div class="text-center">
      <h2 class="wqs-cta-title">Ready To Transform<br>Your Business?</h2>
      <p class="wqs-cta-desc">
        Let's build powerful software solutions that drive growth and innovation for your organization.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     SUCCESS MODAL
     ══════════════════════════════════════════════════════════ -->
<div class="wqs-modal-backdrop" id="successModal">
  <div class="wqs-modal-card">
    <div class="wqs-modal-icon">
      <i class="fas fa-check"></i>
    </div>
    <h4 class="wqs-modal-title">Message Received!</h4>
    <p class="wqs-modal-text">
      Thank you for contacting Wise Quotient Soft.<br>
      Our team will review your request and contact you shortly.
    </p>
    <div class="wqs-modal-ref" id="refNumber">WQS-2026-0001</div>
    <div class="wqs-modal-btns">
      <a href="services.php" class="wqs-btn-primary solid" style="padding: 0.7rem 1.5rem; font-size: 0.85rem;">
        <i class="fas fa-cogs"></i> View Services
      </a>
      <button onclick="closeModal()" class="wqs-btn-primary outline" style="padding: 0.7rem 1.5rem; font-size: 0.85rem;">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════════ -->
<script>
document.getElementById('contactForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const form = this;
  const btn = document.getElementById('submitBtn');
  const formData = new FormData(form);
  
  // Validate required fields
  const name = formData.get('name')?.trim();
  const email = formData.get('email')?.trim();
  const service = formData.get('service');
  const message = formData.get('message')?.trim();
  
  if (!name || !email || !service || !message) {
    showToast('Please fill in all required fields.', 'error');
    return;
  }
  
  // Email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    showToast('Please enter a valid email address.', 'error');
    return;
  }
  
  // Loading state
  btn.classList.add('loading');
  btn.disabled = true;
  
  try {
    // Log analytics event
    if (typeof window.wqsLogEvent === 'function') {
      window.wqsLogEvent('contact_form_submission', service);
    }
    
    // Submit to actual API endpoint
    const response = await fetch('api/contact_submit.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      document.getElementById('refNumber').textContent = result.ref_number;
      // Show success modal
      document.getElementById('successModal').classList.add('show');
      // Reset form
      form.reset();
    } else {
      showToast(result.message || 'Submission failed. Please try again.', 'error');
    }
    
  } catch (err) {
    showToast('Something went wrong. Please try again.', 'error');
  } finally {
    btn.classList.remove('loading');
    btn.disabled = false;
  }
});

function closeModal() {
  document.getElementById('successModal').classList.remove('show');
}

// Close modal on backdrop click
document.getElementById('successModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed; top: 20px; right: 20px; z-index: 99999;
    padding: 1rem 1.5rem; border-radius: 12px;
    font-size: 0.88rem; font-weight: 600; font-family: 'Plus Jakarta Sans', sans-serif;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    ${type === 'error' 
      ? 'background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;' 
      : 'background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;'}
  `;
  toast.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'} me-2"></i>${message}`;
  document.body.appendChild(toast);
  
  requestAnimationFrame(() => {
    toast.style.transform = 'translateX(0)';
  });
  
  setTimeout(() => {
    toast.style.transform = 'translateX(120%)';
    setTimeout(() => toast.remove(), 400);
  }, 4000);
}

// Intersection Observer for scroll animations
const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
    }
  });
}, observerOptions);

document.querySelectorAll('.wqs-info-card, .wqs-form-card, .wqs-trust-item').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
  observer.observe(el);
});
</script>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
