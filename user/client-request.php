<?php
$path_to_root = "../";
$page_title = "Project Request & Booking Form";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
.ai-assist-card {
    background: linear-gradient(135deg, #0b1120 0%, #1a2332 100%);
    border-radius: 20px; padding: 2rem; color: white; margin-bottom: 2rem;
    position: relative; overflow: hidden;
}
.ai-assist-card::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(59,130,246,0.15), transparent 70%);
    border-radius: 50%;
}
.ai-assist-card .ai-content { position: relative; z-index: 1; }
.ai-assist-card textarea {
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
    color: white; border-radius: 12px; resize: vertical; font-size: 0.95rem;
}
.ai-assist-card textarea::placeholder { color: rgba(255,255,255,0.5); font-size: 0.9rem; }
.ai-assist-card textarea:focus { background: rgba(255,255,255,0.15); border-color: #60a5fa; box-shadow: 0 0 24px rgba(59,130,246,0.2); color: white; }
.ai-assist-card input.ai-input {
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
    color: white; border-radius: 10px; font-size: 0.9rem;
}
.ai-assist-card input.ai-input::placeholder { color: rgba(255,255,255,0.45); }
.ai-assist-card input.ai-input:focus { background: rgba(255,255,255,0.15); border-color: #60a5fa; box-shadow: 0 0 16px rgba(59,130,246,0.15); color: white; }
.ai-assist-card input.ai-input[type="file"]::file-selector-button {
    background: rgba(59,130,246,0.2); border: 1px solid rgba(59,130,246,0.3); color: white;
    padding: 6px 16px; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.ai-assist-card input.ai-input[type="file"]::file-selector-button:hover { background: rgba(59,130,246,0.3); }
.btn-ai {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6); border: none;
    border-radius: 50px; padding: 10px 28px; font-weight: 700; color: white;
    transition: all 0.3s ease;
}
.btn-ai:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(59,130,246,0.3); color: white; }
.btn-ai:disabled { opacity: 0.6; transform: none; }
.ai-loading { display: none; }
.ai-loading.active { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.ai-step-row { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
.ai-step-num {
    width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700; flex-shrink: 0;
    background: rgba(59,130,246,0.2); border: 1px solid rgba(59,130,246,0.3); color: rgba(255,255,255,0.5);
    transition: all 0.4s ease;
}
.ai-step-row.active .ai-step-num { background: #3b82f6; border-color: #3b82f6; color: white; box-shadow: 0 0 12px rgba(59,130,246,0.4); }
.ai-step-row.done .ai-step-num { background: #22c55e; border-color: #22c55e; color: white; }
.ai-step-label { color: rgba(255,255,255,0.45); transition: all 0.4s ease; }
.ai-step-row.active .ai-step-label { color: white; }
.ai-step-row.done .ai-step-label { color: #22c55e; }
.ai-step-progress {
    height: 2px; flex: 1; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden;
}
.ai-step-progress .fill {
    height: 100%; width: 0%; background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    transition: width 0.6s ease;
}
.ai-step-row.active .ai-step-progress .fill { width: 100%; animation: aiProgress 2s ease-in-out infinite; }
@keyframes aiProgress { 0% { width: 20%; } 50% { width: 80%; } 100% { width: 20%; } }
.ai-step-row.done .ai-step-progress .fill { width: 100%; background: #22c55e; }
.ai-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.3);
    border-radius: 50px; padding: 4px 14px; font-size: 0.7rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.ai-fill-highlight {
    animation: aiFillGlow 1s ease-out;
}
@keyframes aiFillGlow {
    0% { box-shadow: 0 0 0 2px rgba(59,130,246,0.5); background: rgba(59,130,246,0.08); }
    100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); background: transparent; }
}
</style>

<div class="row g-4">
  <!-- Left Column -->
  <div class="col-12 col-lg-4">
    <div class="accordion card-theme border-0 sticky-top" id="guidAccordion" style="top: 90px; z-index: 10;">
      
      <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header" id="headingWelcome">
          <button class="accordion-button fw-semibold text-body" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWelcome" aria-expanded="true" aria-controls="collapseWelcome">
            <i class="fas fa-info-circle me-2 text-primary"></i> Submission Guidelines
          </button>
        </h2>
        <div id="collapseWelcome" class="accordion-collapse collapse show" aria-labelledby="headingWelcome" data-bs-parent="#guidAccordion">
          <div class="accordion-body px-3 py-3 small text-secondary">
            Fill in the form to request a software project and book development milestones. 
            <ul class="ps-3 mt-2 mb-0">
              <li class="mb-1">Specify your target software platforms.</li>
              <li class="mb-1">Provide contact details for communication.</li>
              <li class="mb-0">Acknowledge agreement guidelines before submitting.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header" id="headingCategories">
          <button class="accordion-button collapsed fw-semibold text-body" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategories" aria-expanded="false" aria-controls="collapseCategories">
            <i class="fas fa-list me-2 text-primary"></i> Project Categories
          </button>
        </h2>
        <div id="collapseCategories" class="accordion-collapse collapse" aria-labelledby="headingCategories" data-bs-parent="#guidAccordion">
          <div class="accordion-body px-3 py-2 small">
            <div class="mb-3 border-bottom pb-2"><strong>School:</strong> Admission systems, CBT online exams, fees management, portals.</div>
            <div class="mb-3 border-bottom pb-2"><strong>Company:</strong> CRM, human resources, payroll portals, internal business dashboards.</div>
            <div class="mb-3 border-bottom pb-2"><strong>Startups:</strong> MVPs, POS, inventory management.</div>
            <div class="mb-3 border-bottom pb-2"><strong>E-commerce:</strong> Online stores, shopping carts, delivery integrations.</div>
            <div class="mb-3 border-bottom pb-2"><strong>Healthcare:</strong> Medical records, doctor appointments, telemedicine.</div>
            <div class="mb-0"><strong>Fintech/Pintech:</strong> Wallets, bank integrations, USSD menus, transaction processing.</div>
          </div>
        </div>
      </div>

      <div class="accordion-item border-0">
        <h2 class="accordion-header" id="headingFeatures">
          <button class="accordion-button collapsed fw-semibold text-body" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFeatures" aria-expanded="false" aria-controls="collapseFeatures">
            <i class="fas fa-cogs me-2 text-success"></i> Smart Feature Modules
          </button>
        </h2>
        <div id="collapseFeatures" class="accordion-collapse collapse" aria-labelledby="headingFeatures" data-bs-parent="#guidAccordion">
          <div class="accordion-body px-3 py-2 small">
            <div class="mb-2"><strong>Emailing / SMS:</strong> Receipts, alerts, OTP verifications.</div>
            <div class="mb-2"><strong>Voice Calls:</strong> IVR call campaigns, automated alerts.</div>
            <div class="mb-2"><strong>USSD Interface:</strong> Standalone offline menus for payment or queries.</div>
            <div class="mb-2"><strong>AI Chatbots:</strong> Intelligent helper tools, predictive search.</div>
            <div class="mb-0"><strong>Cloud Uploads:</strong> Secure files, automatic backup protocols.</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Right Column -->
  <div class="col-12 col-lg-8">

    <!-- AI-Assisted Creation -->
    <div class="ai-assist-card">
      <div class="ai-content">
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
          <span class="ai-badge"><i class="fas fa-sparkles"></i> AI-Powered</span>
          <h5 class="mb-0 fw-bold" style="font-size:1.1rem;">Create with AI Assistance</h5>
        </div>
        <p class="mb-3" style="font-size:0.85rem;opacity:0.75;">Describe your project idea below. Optionally share sample images or a reference link so AI can better understand your vision.</p>
        <div class="mb-3">
          <textarea id="aiDescription" class="form-control" rows="3" placeholder="e.g., I need a school management system with online fees payment, exam results portal, and SMS notifications for parents..."></textarea>
        </div>
        <!-- Sample images & links -->
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label style="font-size:0.75rem;opacity:0.6;margin-bottom:4px;display:block;"><i class="fas fa-image me-1"></i>Sample Image (optional)</label>
            <input type="file" id="aiSampleImage" class="form-control ai-input" accept="image/*">
            <div id="aiImagePreview" class="mt-2" style="display:none;">
              <img style="max-height:80px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);">
            </div>
          </div>
          <div class="col-md-6">
            <label style="font-size:0.75rem;opacity:0.6;margin-bottom:4px;display:block;"><i class="fas fa-link me-1"></i>Reference Link (optional)</label>
            <input type="url" id="aiSampleLink" class="form-control ai-input" placeholder="https://figma.com/... or https://example.com">
          </div>
        </div>
        <div class="d-flex align-items-start gap-3 flex-wrap">
          <button class="btn-ai" id="aiGenerateBtn" onclick="generateWithAI()">
            <i class="fas fa-magic me-2"></i>Generate with AI
          </button>
          <div class="ai-loading" id="aiLoading">
            <div class="ai-step-row active" data-step="1">
              <span class="ai-step-num">1</span>
              <span class="ai-step-label">Analyzing your description...</span>
              <span class="ai-step-progress"><span class="fill"></span></span>
            </div>
            <div class="ai-step-row" data-step="2">
              <span class="ai-step-num">2</span>
              <span class="ai-step-label">Processing requirements & samples...</span>
              <span class="ai-step-progress"><span class="fill"></span></span>
            </div>
            <div class="ai-step-row" data-step="3">
              <span class="ai-step-num">3</span>
              <span class="ai-step-label">Generating structured form data...</span>
              <span class="ai-step-progress"><span class="fill"></span></span>
            </div>
            <div class="ai-step-row" data-step="4">
              <span class="ai-step-num">4</span>
              <span class="ai-step-label">Filling your form...</span>
              <span class="ai-step-progress"><span class="fill"></span></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="projectForm">
      
      <!-- Section 1: Project Scope -->
      <div class="card-theme mb-4">
        <div class="card-theme-header">
          <h5 class="card-theme-title text-body"><i class="fas fa-briefcase me-2 text-primary"></i> 1. Specify Project Scope</h5>
          <span class="text-muted small">Step 1 of 4</span>
        </div>
        <div class="card-theme-body">
          <div class="mb-4">
            <label class="form-label-theme">Project Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="aiTitle" class="form-control form-control-theme" required placeholder="e.g., Smart Inventory Management System">
          </div>
          <div class="mb-4">
            <label class="form-label-theme">Full Project Description <span class="text-danger">*</span></label>
            <textarea name="description" id="aiDescriptionField" class="form-control form-control-theme" rows="5" required placeholder="Provide a detailed description of your software requirements..."></textarea>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label-theme mb-2 d-block">Select Category <span class="text-danger">*</span></label>
              <div id="aiCategoryContainer">
              <?php
              $categories = ["School", "Company", "Small Enterprise / Startup", "E-commerce", "Healthcare", "Finance", "Non-Profit", "Pintech App", "Other"];
              foreach ($categories as $cat): 
              ?>
                <div class="form-check mb-2">
                  <input class="form-check-input ai-category" type="radio" name="category" value="<?= $cat ?>" required id="cat_<?= md5($cat) ?>">
                  <label class="form-check-label text-secondary small" for="cat_<?= md5($cat) ?>"><?= $cat ?></label>
                </div>
              <?php endforeach; ?>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label-theme mb-2 d-block">Required Features</label>
              <div id="aiFeaturesContainer">
              <?php
              $features = ["Emailing", "Text Messaging (SMS)", "Voice Call", "USSD", "AI Features", "Machine Learning", "Cloud Storage", "Payment Integration", "Analytics Dashboard"];
              foreach ($features as $feat): 
              ?>
                <div class="form-check mb-2">
                  <input class="form-check-input ai-feature" type="checkbox" name="features[]" value="<?= $feat ?>" id="feat_<?= md5($feat) ?>">
                  <label class="form-check-label text-secondary small" for="feat_<?= md5($feat) ?>"><?= $feat ?></label>
                </div>
              <?php endforeach; ?>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label-theme mb-2 d-block">Software Type <span class="text-danger">*</span></label>
              <div id="aiTypeContainer">
              <?php
              $types = ["Mobile App", "Web App", "Mobile App & Web App", "Desktop App", "Pintech App", "Other"];
              foreach ($types as $type): 
              ?>
                <div class="form-check mb-2">
                  <input class="form-check-input ai-software-type" type="radio" name="software_type" value="<?= $type ?>" required id="type_<?= md5($type) ?>">
                  <label class="form-check-label text-secondary small" for="type_<?= md5($type) ?>"><?= $type ?></label>
                </div>
              <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Client & Company Information -->
      <div class="card-theme mb-4">
        <div class="card-theme-header">
          <h5 class="card-theme-title text-body"><i class="fas fa-building me-2 text-primary"></i> 2. Client & Company Information</h5>
          <span class="text-muted small">Step 2 of 4</span>
        </div>
        <div class="card-theme-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label form-label-theme">Company / Client Name <span class="text-danger">*</span></label>
              <input class="form-control form-control-theme" name="company_name" id="aiCompany" placeholder="Enter Company/Client Name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Contact Person (Full Name) <span class="text-danger">*</span></label>
              <input class="form-control form-control-theme" name="contact_person" id="aiContact" placeholder="Enter Contact Person" required>
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Phone Number <span class="text-danger">*</span></label>
              <input class="form-control form-control-theme" name="phone" id="aiPhone" placeholder="Enter Phone Number" required>
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Address / Location</label>
              <input class="form-control form-control-theme" name="address" placeholder="Enter Location Address">
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Timeline, Budget & Hosting -->
      <div class="card-theme mb-4">
        <div class="card-theme-header">
          <h5 class="card-theme-title text-body"><i class="fas fa-calendar-alt me-2 text-primary"></i> 3. Project Timeline & Budget Details</h5>
          <span class="text-muted small">Step 3 of 4</span>
        </div>
        <div class="card-theme-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label form-label-theme">Preferred Start Date</label>
              <input class="form-control form-control-theme" name="start_date" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Expected Completion Date</label>
              <input class="form-control form-control-theme" name="completion_date" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Estimated Budget</label>
              <input class="form-control form-control-theme" name="budget" id="aiBudget" placeholder="e.g. 1,500,000 or 4,000">
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme d-block">Preferred Currency</label>
              <div class="d-flex gap-3 my-2">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="currency" id="curr_ngn" value="NGN" checked>
                  <label class="form-check-label text-body" for="curr_ngn">NGN (₦)</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="currency" id="curr_usd" value="USD">
                  <label class="form-check-label text-body" for="curr_usd">USD ($)</label>
                </div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label form-label-theme d-block">Deployment Preference</label>
              <div class="d-flex gap-3 my-2">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="deployment" id="dep_cloud" value="Cloud" checked>
                  <label class="form-check-label text-body" for="dep_cloud">Cloud Hosting (AWS/cPanel)</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="deployment" id="dep_prem" value="On-premise">
                  <label class="form-check-label text-body" for="dep_prem">On-premise Servers</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="deployment" id="dep_hybrid" value="Hybrid">
                  <label class="form-check-label text-body" for="dep_hybrid">Hybrid Setup</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Compliance, Attachments & Signatures -->
      <div class="card-theme mb-4">
        <div class="card-theme-header">
          <h5 class="card-theme-title text-body"><i class="fas fa-file-signature me-2 text-primary"></i> 4. Compliance, Attachments & Approval</h5>
          <span class="text-muted small">Step 4 of 4</span>
        </div>
        <div class="card-theme-body">
          <!-- Attachments -->
          <div class="mb-4 p-3 bg-body-tertiary rounded-3 border">
            <label class="form-label-theme mb-2"><i class="fas fa-paperclip me-1 text-primary"></i>Sample Assets / Wireframes (Optional)</label>
            <input type="url" name="asset_url" class="form-control form-control-theme mb-2" placeholder="Paste a link to Google Drive/Figma designs (optional)">
            <input type="file" name="files[]" accept="image/*,video/*" multiple onchange="previewFiles(event)" class="form-control form-control-theme">
            <div id="previewContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
          </div>

          <!-- Agreements -->
          <div class="mb-4">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="sda_agree" id="sda_agree" required>
              <label class="form-check-label text-secondary small" for="sda_agree">I agree to the Software Development Agreement (SDA) terms <span class="text-danger">*</span></label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="nda_agree" id="nda_agree" required>
              <label class="form-check-label text-secondary small" for="nda_agree">I acknowledge the Non-Disclosure Agreement (NDA) compliance <span class="text-danger">*</span></label>
            </div>
          </div>

          <!-- Signature -->
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label form-label-theme">Client Name / Digital Signature <span class="text-danger">*</span></label>
              <input class="form-control form-control-theme" name="client_signature" placeholder="Type your full name as signature" required>
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Signature Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control form-control-theme" name="client_date" required value="<?= date("Y-m-d") ?>">
            </div>
          </div>

          <!-- Smart Recommendation Alerts -->
          <div id="recommendationBox" class="alert alert-info d-none rounded-3 small mb-4"></div>

          <!-- Action Submit -->
          <button type="submit" class="btn btn-theme w-100 py-3 font-semibold">
            <i class="fas fa-paper-plane me-2"></i> Submit & Book Project Request
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
// AI step progress
const AI_STEPS = ['Analyzing your description', 'Processing requirements & samples', 'Generating structured form data', 'Filling your form'];
function setAIStep(step) {
    const rows = document.querySelectorAll('#aiLoading .ai-step-row');
    rows.forEach((r, i) => {
        r.classList.remove('active', 'done');
        if (i + 1 < step) r.classList.add('done');
        if (i + 1 === step) r.classList.add('active');
    });
}
// Image upload preview for AI card
document.getElementById('aiSampleImage')?.addEventListener('change', function() {
    const preview = document.getElementById('aiImagePreview');
    const img = preview?.querySelector('img');
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(this.files[0]);
    } else { preview.style.display = 'none'; img.src = ''; }
});

// AI-Assisted Form Filling
function generateWithAI() {
    const desc = document.getElementById('aiDescription').value.trim();
    if (!desc) {
        Swal.fire({ title: 'Describe your project', text: 'Please describe your project idea first.', icon: 'info', confirmButtonColor: '#0A2D5E' });
        return;
    }

    const btn = document.getElementById('aiGenerateBtn');
    const loading = document.getElementById('aiLoading');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
    loading.classList.add('active');
    setAIStep(1);

    // Read optional image as base64
    const imgInput = document.getElementById('aiSampleImage');
    const sampleLink = document.getElementById('aiSampleLink')?.value.trim() || '';
    const hasImage = imgInput?.files && imgInput.files[0];

    function doFetch(imageBase64) {
        const payload = { action: 'fill_form', description: desc, sample_link: sampleLink };
        if (imageBase64) payload.sample_image = imageBase64;

        fetch('../agent-server.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.error) {
                loading.classList.remove('active');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate with AI';
                Swal.fire({ title: 'AI Error', text: resp.error, icon: 'error', confirmButtonColor: '#E15501' });
                return;
            }
            if (!resp.success || !resp.data) {
                loading.classList.remove('active');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate with AI';
                Swal.fire({ title: 'Parse Error', text: 'AI returned unexpected data.', icon: 'error', confirmButtonColor: '#E15501' });
                return;
            }

        setAIStep(3);

        setTimeout(() => {
            setAIStep(4);
            const d = resp.data;

            // Fill title
            if (d.title) {
                document.getElementById('aiTitle').value = d.title;
                highlightField('aiTitle');
            }
            // Fill description
            if (d.description) {
                document.getElementById('aiDescriptionField').value = d.description;
                highlightField('aiDescriptionField');
            }
            // Fill category
            if (d.category) {
                const cats = document.querySelectorAll('.ai-category');
                cats.forEach(c => { c.checked = c.value.toLowerCase() === d.category.toLowerCase(); });
            }
            // Fill software type
            if (d.software_type) {
                const types = document.querySelectorAll('.ai-software-type');
                types.forEach(t => { t.checked = t.value.toLowerCase() === d.software_type.toLowerCase(); });
            }
            // Fill features
            if (d.features) {
                const featList = d.features.split(',').map(f => f.trim().toLowerCase());
                document.querySelectorAll('.ai-feature').forEach(f => {
                    f.checked = featList.some(feat => f.value.toLowerCase().includes(feat) || feat.includes(f.value.toLowerCase()));
                });
            }
            // Fill budget
            if (d.budget) document.getElementById('aiBudget').value = d.budget;
            // Fill company
            if (d.company_name) document.getElementById('aiCompany').value = d.company_name;
            // Fill contact
            if (d.contact_person) document.getElementById('aiContact').value = d.contact_person;
            // Fill phone
            if (d.phone) document.getElementById('aiPhone').value = d.phone;

            // Trigger recommendations
            if (typeof updateRecommendations === 'function') updateRecommendations();

            loading.classList.remove('active');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate with AI';

            Swal.fire({
                title: 'Form Filled Successfully!',
                text: 'AI has populated the form. Please review and submit.',
                icon: 'success',
                confirmButtonColor: '#0A2D5E',
                timer: 2000,
                timerProgressBar: true
            });

            // Scroll to form
            document.querySelector('.card-theme').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 800);
    })
    .catch(err => {
        loading.classList.remove('active');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic me-2"></i>Generate with AI';
        Swal.fire({ title: 'Connection Error', text: 'Could not reach AI service.', icon: 'error', confirmButtonColor: '#E15501' });
    });
    }

    if (hasImage) {
        setAIStep(2);
        const reader = new FileReader();
        reader.onload = e => { doFetch(e.target.result); };
        reader.readAsDataURL(imgInput.files[0]);
    } else {
        setAIStep(2);
        setTimeout(() => doFetch(null), 600);
    }
}

function highlightField(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('ai-fill-highlight');
    setTimeout(() => el.classList.remove('ai-fill-highlight'), 1500);
}

// File Upload Previews
function previewFiles(event) {
  let files = event.target.files;
  let container = document.getElementById("previewContainer");
  container.innerHTML = "";

  Array.from(files).forEach((file) => {
    let reader = new FileReader();

    reader.onload = function (e) {
      let wrapper = document.createElement("div");
      wrapper.className = "position-relative border rounded p-1 bg-body";
      wrapper.style.width = "100px";

      let size =
        file.size < 1024
          ? file.size + " B"
          : file.size < 1024 * 1024
          ? (file.size / 1024).toFixed(1) + " KB"
          : (file.size / 1024 / 1024).toFixed(1) + " MB";

      if (file.type.startsWith("image")) {
        wrapper.innerHTML = `
          <img src="${e.target.result}" class="rounded w-100" style="height:70px; object-fit:cover;">
          <div class="text-muted text-center mt-1" style="font-size:0.7rem;">${size}</div>
        `;
      } else if (file.type.startsWith("video")) {
        wrapper.innerHTML = `
          <div class="d-flex align-items-center justify-content-center bg-dark text-white rounded w-100" style="height:70px;">
            <i class="fas fa-video"></i>
          </div>
          <div class="text-muted text-center mt-1" style="font-size:0.7rem;">${size}</div>
        `;
      }

      container.appendChild(wrapper);
    };

    reader.readAsDataURL(file);
  });
}

// Form Submission with SweetAlert integration
document.getElementById("projectForm").addEventListener("submit", function (e) {
  e.preventDefault();

  let form = this;
  let btn = form.querySelector("button[type='submit']");
  btn.disabled = true;
  btn.innerHTML = "<i class='fas fa-spinner fa-spin me-2'></i> Submitting... Please wait";

  let formData = new FormData(form);

  fetch("submit_request.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.text())
    .then((data) => {
      Swal.fire({
        title: "Request & Booking Submitted!",
        text: "Your combined project request and booking details have been saved.",
        icon: "success",
        confirmButtonColor: "#0A2D5E",
        confirmButtonText: "Okay",
        timer: 2000,
        timerProgressBar: true
      });

      form.reset();
      document.getElementById("previewContainer").innerHTML = "";
      document.getElementById("recommendationBox").classList.add("d-none");

      setTimeout(() => {
        window.location.href = "my_requests.php";
      }, 2000);
    })
    .catch(() => {
      Swal.fire({
        title: "Submission Error",
        text: "There was a problem submitting your request. Please try again.",
        icon: "error",
        confirmButtonColor: "#E15501",
      });
      btn.disabled = false;
      btn.innerHTML = `<i class="fas fa-paper-plane me-2"></i> Submit & Book Project Request`;
    });
});

// Recommendations Logic
function updateRecommendations() {
  let category = document.querySelector("input[name='category']:checked");
  let features = document.querySelectorAll("input[name='features[]']:checked");
  let type = document.querySelector("input[name='software_type']:checked");
  let box = document.getElementById("recommendationBox");
  let recs = [];

  if (category) {
    switch (category.value) {
      case "School":
        recs.push("<strong>School Recommendation:</strong> Incorporate Learning Management Systems (LMS), computer-based tests, parent reports, and secure payment integrations.");
        break;
      case "E-commerce":
        recs.push("<strong>E-Commerce Recommendation:</strong> Prioritize shopping carts, payment gateways, product searches, inventory triggers, and SMS notification systems.");
        break;
      case "Healthcare":
        recs.push("<strong>Healthcare Recommendation:</strong> Implement doctor consultation dashboards, HIPAA-compliant patient history logs, and SMS reminders.");
        break;
      case "Finance":
      case "Pintech App":
        recs.push("<strong>Financial Security:</strong> Double up on encryption, transaction logs, identity checks (KYC), and automated webhook validation.");
        break;
    }
  }

  features.forEach((f) => {
    switch (f.value) {
      case "AI Features":
        recs.push("<strong>AI Integration:</strong> Consider automated FAQ bots or smart search algorithms.");
        break;
      case "Analytics Dashboard":
        recs.push("<strong>Dashboards:</strong> Incorporate real-time charts (Chart.js) and custom CSV/PDF report download triggers.");
        break;
    }
  });

  if (recs.length > 0) {
    box.innerHTML = "<p class='mb-2 fw-semibold'><i class='fas fa-lightbulb text-warning me-1'></i>WQS Smart Recommendations:</p><ul class='mb-0 ps-3'>" + recs.map((r) => `<li class='mb-1'>${r}</li>`).join("") + "</ul>";
    box.classList.remove("d-none");
  } else {
    box.innerHTML = "";
    box.classList.add("d-none");
  }
}

document.querySelectorAll("input[name='category'], input[name='features[]'], input[name='software_type']")
  .forEach((el) => el.addEventListener("change", updateRecommendations));

// Auto Accordions on Desktop
document.addEventListener("DOMContentLoaded", function () {
  const isDesktop = window.innerWidth >= 992;
  const categories = document.getElementById("collapseCategories");
  const features = document.getElementById("collapseFeatures");
  const catBtn = document.querySelector("[data-bs-target='#collapseCategories']");
  const featBtn = document.querySelector("[data-bs-target='#collapseFeatures']");

  if (isDesktop && categories && features) {
    categories.classList.add("show");
    features.classList.add("show");
    catBtn.classList.remove("collapsed");
    featBtn.classList.remove("collapsed");
  }

  // Dynamic Typing Placeholder Guide for Project Title
  const titleInput = document.getElementById('aiTitle');
  if (titleInput) {
    const placeholders = [
      "e.g., School Management Web Application",
      "e.g., VTU Mobile Application",
      "e.g., School Wallet Mobile Application"
    ];
    let placeholderIdx = 0;
    let charIdx = 0;
    let isDeleting = false;
    let typingDelay = 100;
    let erasingDelay = 40;
    let newTextDelay = 2000;

    function typePlaceholder() {
      const currentText = placeholders[placeholderIdx];
      if (isDeleting) {
        titleInput.placeholder = currentText.substring(0, charIdx - 1);
        charIdx--;
        typingDelay = erasingDelay;
      } else {
        titleInput.placeholder = currentText.substring(0, charIdx + 1);
        charIdx++;
        typingDelay = 100;
      }

      if (!isDeleting && charIdx === currentText.length) {
        typingDelay = newTextDelay;
        isDeleting = true;
      } else if (isDeleting && charIdx === 0) {
        isDeleting = false;
        placeholderIdx = (placeholderIdx + 1) % placeholders.length;
        typingDelay = 500;
      }

      setTimeout(typePlaceholder, typingDelay);
    }
    setTimeout(typePlaceholder, 1000);
  }
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
