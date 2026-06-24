<!-- Cookie Consent Banner Styles -->
<style>
.cookie-banner-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  max-width: 460px;
  width: calc(100% - 48px);
  z-index: 9999;
  transform: translateY(150%);
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.cookie-banner-container.show {
  transform: translateY(0);
}
.cookie-banner-card {
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 1.5rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}
.cookie-banner-header-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.cookie-banner-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ff6600, #ff8533);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  box-shadow: 0 8px 16px rgba(255, 102, 0, 0.25);
  flex-shrink: 0;
}
.cookie-banner-title {
  color: #ffffff;
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
}
.cookie-banner-content p {
  color: #94a3b8;
  font-size: 0.85rem;
  line-height: 1.55;
  margin: 0;
}
.cookie-banner-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
}
.cookie-banner-actions .btn-cookie {
  padding: 0.6rem 1.35rem;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s;
}
.btn-cookie-settings {
  background: transparent;
  color: #cbd5e1;
  border: 1.5px solid rgba(255, 255, 255, 0.15);
}
.btn-cookie-settings:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.3);
}
.btn-cookie-accept {
  background: linear-gradient(135deg, #ff6600, #e65c00);
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 12px rgba(255, 102, 0, 0.25);
}
.btn-cookie-accept:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(255, 102, 0, 0.4);
}

/* Modal Overlay styling */
.cookie-modal-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  background: rgba(3, 7, 18, 0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.cookie-modal-overlay.open {
  opacity: 1;
  visibility: visible;
}
.cookie-modal-content {
  background: #ffffff;
  max-width: 540px;
  width: calc(100% - 32px);
  border-radius: 24px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  transform: scale(0.95);
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: #1e293b;
  text-align: left;
}
.cookie-modal-overlay.open .cookie-modal-content {
  transform: scale(1);
}
.cookie-modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.cookie-modal-header h3 {
  font-size: 1.2rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}
.cookie-modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #64748b;
  cursor: pointer;
  transition: color 0.2s;
}
.cookie-modal-close:hover {
  color: #0f172a;
}
.cookie-modal-body {
  padding: 1.5rem;
  max-height: 60vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}
.cookie-intro {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0 0 0.5rem 0;
  line-height: 1.5;
}
.cookie-option-card {
  background:var(--color-bg);
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}
.cookie-option-info {
  flex: 1;
}
.cookie-option-info h5 {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 0.35rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.badge-essential {
  background: #e0f2fe;
  color: #0369a1;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 50px;
}
.cookie-option-info p {
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.45;
  margin: 0;
}

/* Toggle Switches */
.cookie-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}
.cookie-switch input {
  opacity: 0; width: 0; height: 0;
}
.cookie-slider {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #cbd5e1;
  transition: .4s;
  border-radius: 34px;
}
.cookie-slider:before {
  position: absolute;
  content: "";
  height: 16px; width: 16px;
  left: 4px; bottom: 4px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}
.cookie-switch input:checked + .cookie-slider {
  background-color: #ff6600;
}
.cookie-switch input:focus + .cookie-slider {
  box-shadow: 0 0 1px #ff6600;
}
.cookie-switch input:checked + .cookie-slider:before {
  transform: translateX(20px);
}
.cookie-switch input:disabled + .cookie-slider {
  background-color: #94a3b8;
  cursor: not-allowed;
}

.cookie-modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
}
.btn-cookie-save {
  background: linear-gradient(135deg, #0f172a, #1e293b);
  color: white;
  border: none;
  padding: 0.65rem 1.5rem;
  border-radius: 12px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s;
}
.btn-cookie-save:hover {
  background: linear-gradient(135deg, #1e293b, #334155);
  transform: translateY(-1px);
  box-shadow: 0 6px 12px rgba(15, 23, 42, 0.2);
}

@media (max-width: 576px) {
  .cookie-banner-container {
    bottom: 12px;
    right: 12px;
    width: calc(100% - 24px);
  }
}

/* Hide cookie consent elements entirely during printing */
@media print {
  .cookie-banner-container,
  .cookie-modal-overlay,
  #cookieBanner,
  #cookieModal {
    display: none !important;
  }
}
</style>

<!-- Cookie Consent Banner HTML -->
<div id="cookieBanner" class="cookie-banner-container">
  <div class="cookie-banner-card">
    <div class="cookie-banner-header-row">
      <div class="cookie-banner-icon">
        <i class="fas fa-cookie-bite"></i>
      </div>
      <h4 class="cookie-banner-title">Cookie Preference Settings</h4>
    </div>
    <div class="cookie-banner-content">
      <p>
        We use cookies to optimize your experience, analyze website traffic, and display personalized advertisements. By clicking "Accept All", you consent to our use of cookies. You can also customize your preferences.
      </p>
    </div>
    <div class="cookie-banner-actions">
      <button class="btn-cookie btn-cookie-settings" onclick="openCookieSettings()">Preferences</button>
      <button class="btn-cookie btn-cookie-accept" onclick="acceptAllCookies()">Accept All</button>
    </div>
  </div>
</div>

<!-- Cookie Settings Modal HTML -->
<div id="cookieModal" class="cookie-modal-overlay" onclick="handleOutsideModalClick(event)">
  <div class="cookie-modal-content" onclick="event.stopPropagation()">
    <div class="cookie-modal-header">
      <h3>Cookie Settings Panel</h3>
      <button class="cookie-modal-close" onclick="closeCookieSettings()">&times;</button>
    </div>
    <div class="cookie-modal-body">
      <p class="cookie-intro">
        Manage how cookies are used on our platform. Necessary cookies are required for core features and cannot be disabled.
      </p>
      
      <!-- Essential Cookies -->
      <div class="cookie-option-card">
        <div class="cookie-option-info">
          <h5>Necessary Cookies <span class="badge badge-essential">Required</span></h5>
          <p>These cookies enable basic functions like page navigation, secure logins, and local session preferences. Without them, the website cannot function properly.</p>
        </div>
        <div class="cookie-option-toggle">
          <label class="cookie-switch">
            <input type="checkbox" checked disabled>
            <span class="cookie-slider"></span>
          </label>
        </div>
      </div>
      
      <!-- Analytics Cookies -->
      <div class="cookie-option-card">
        <div class="cookie-option-info">
          <h5>Analytics & Performance Cookies</h5>
          <p>We use these cookies to monitor user traffic and interaction patterns, helping us compile statistics and optimize overall site performance.</p>
        </div>
        <div class="cookie-option-toggle">
          <label class="cookie-switch">
            <input type="checkbox" id="cookieOptAnalytics" checked>
            <span class="cookie-slider"></span>
          </label>
        </div>
      </div>
      
      <!-- Marketing Cookies -->
      <div class="cookie-option-card">
        <div class="cookie-option-info">
          <h5>Marketing & Advertisement Cookies</h5>
          <p>These cookies are set to deliver targeted advertising campaigns tailored to your specific tech interests and profile preferences.</p>
        </div>
        <div class="cookie-option-toggle">
          <label class="cookie-switch">
            <input type="checkbox" id="cookieOptMarketing" checked>
            <span class="cookie-slider"></span>
          </label>
        </div>
      </div>
    </div>
    <div class="cookie-modal-footer">
      <button class="btn-cookie btn-cookie-save" onclick="saveCustomCookies()">Save Preferences</button>
    </div>
  </div>
</div>

<!-- Cookie Javascript Logic -->
<script>
// Cookie helper functions
function setWQSCookie(name, value, days) {
  const date = new Date();
  date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
  const expires = "expires=" + date.toUTCString();
  document.cookie = name + "=" + encodeURIComponent(value) + ";" + expires + ";path=/;SameSite=Lax";
}

function getWQSCookie(name) {
  const nameEQ = name + "=";
  const ca = document.cookie.split(';');
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
  }
  return null;
}

// Initialise Banner on load
document.addEventListener('DOMContentLoaded', () => {
  const consent = getWQSCookie('wqs_cookie_consent');
  if (!consent) {
    setTimeout(() => {
      const banner = document.getElementById('cookieBanner');
      if (banner) banner.classList.add('show');
    }, 1500); // Premium delay of 1.5s
  } else {
    try {
      const preferences = JSON.parse(consent);
      applyCookiePreferences(preferences);
    } catch(e) {}
  }
});

function applyCookiePreferences(prefs) {
  if (prefs.analytics) {
    console.log('WQS: Analytics cookies active.');
    
    // Log visitor only once per browser session
    if (!sessionStorage.getItem('wqs_visitor_logged')) {
      const logUrl = '<?= isset($path_to_root) ? $path_to_root : './' ?>log_visitor.php';
      
      // Server-side logging (geolocation handled by server with caching)
      fetch(logUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ip: '',
          country: 'Unknown',
          state: 'Unknown',
          page_url: window.location.href,
          referrer: document.referrer || 'Direct'
        })
      })
      .then(res => res.json())
      .then(result => {
        if (result && result.status === 'success') {
          sessionStorage.setItem('wqs_visitor_logged', 'true');
        }
      })
      .catch(e => console.warn('WQS Tracking unavailable:', e));
    }
  } else {
    console.log('WQS: Analytics cookies deactivated.');
  }

  if (prefs.marketing) {
    console.log('WQS: Marketing & ad cookies active.');
  } else {
    console.log('WQS: Marketing & ad cookies deactivated.');
  }
}

function acceptAllCookies() {
  const preferences = { essential: true, analytics: true, marketing: true };
  setWQSCookie('wqs_cookie_consent', JSON.stringify(preferences), 365);
  applyCookiePreferences(preferences);
  
  const banner = document.getElementById('cookieBanner');
  if (banner) banner.classList.remove('show');
}

function openCookieSettings() {
  const consent = getWQSCookie('wqs_cookie_consent');
  if (consent) {
    try {
      const preferences = JSON.parse(consent);
      document.getElementById('cookieOptAnalytics').checked = !!preferences.analytics;
      document.getElementById('cookieOptMarketing').checked = !!preferences.marketing;
    } catch(e) {}
  }
  
  document.getElementById('cookieModal').classList.add('open');
}

function closeCookieSettings() {
  document.getElementById('cookieModal').classList.remove('open');
}

function handleOutsideModalClick(event) {
  if (event.target.id === 'cookieModal') {
    closeCookieSettings();
  }
}

function saveCustomCookies() {
  const analyticsVal = document.getElementById('cookieOptAnalytics').checked;
  const marketingVal = document.getElementById('cookieOptMarketing').checked;
  
  const preferences = {
    essential: true,
    analytics: analyticsVal,
    marketing: marketingVal
  };
  
  setWQSCookie('wqs_cookie_consent', JSON.stringify(preferences), 365);
  applyCookiePreferences(preferences);
  
  const banner = document.getElementById('cookieBanner');
  if (banner) banner.classList.remove('show');
  closeCookieSettings();
}
</script>
