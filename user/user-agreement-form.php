<?php
$path_to_root = "../";
$page_title = "Agreement Document";

$agent = null;
$isAgentAgreement = false;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_to_root . 'config.php';

if (isset($_GET['agent_id'])) {
    $agentId = (int)$_GET['agent_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    $commissionPct = '';
    $partnerNote = '';
    $partnerLevel = '';
    $partnerRole = '';
    if ($agent) {
        $detailsStmt = $pdo->prepare("SELECT commission_percentage, partner_note, partner_level, partner_role FROM agent_requests WHERE user_id = ? AND status = 'approved' LIMIT 1");
        $detailsStmt->execute([$agentId]);
        $details = $detailsStmt->fetch();
        $commissionPct = $details['commission_percentage'] ?? '';
        $partnerNote = $details['partner_note'] ?? '';
        $partnerLevel = $details['partner_level'] ?? '';
        $partnerRole = $details['partner_role'] ?? '';
    }
    if ($agent) {
        $isAgentAgreement = true;
    }
}

require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$companyName = 'Wise Quotient Soft';
$companyAddress = 'No.1 Ibadan Street, Kaduna, Nigeria';
$companyEmail = 'info@wisequotientsoft.com';
$companyPhone = '+234 807 741 6106';
$companyWebsite = 'www.wisequotientsoft.com';
$companyTagline = 'IT & Software Development Agency';
$agreementDate = date("d M Y");
$agreementRef = $isAgentAgreement
    ? 'WQS-REF-AGENT-' . str_pad($agent['id'], 5, '0', STR_PAD_LEFT)
    : 'WQS-PROJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
?>

<style>
.agreement-letterhead {
    max-width: 900px; margin: 0 auto 2rem auto;
    background: #fff; border: 2px solid var(--color-primary, #065f46);
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    position: relative; color: #0f172a;
}
.agreement-letterhead .lh-header {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
    padding: 1.5rem 2.5rem; display: flex; align-items: center; gap: 1.25rem;
    position: relative; overflow: hidden;
}
.agreement-letterhead .lh-header::before {
    content: ''; position: absolute; top: -60%; right: -15%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(16,185,129,0.15), transparent 70%);
    border-radius: 50%;
}
.agreement-letterhead .lh-logo {
    width: 70px; height: 70px; border-radius: 12px; object-fit: contain;
    background: #fff; padding: 6px; flex-shrink: 0; position: relative; z-index: 1;
}
.agreement-letterhead .lh-brand { position: relative; z-index: 1; color: #fff; }
.agreement-letterhead .lh-brand h3 { font-size: 1.15rem; font-weight: 700; margin: 0; letter-spacing: 0.3px; }
.agreement-letterhead .lh-brand small { font-size: 0.72rem; opacity: 0.85; display: block; margin-top: 2px; }
.agreement-letterhead .lh-contacts {
    display: flex; flex-wrap: wrap; gap: 1rem; padding: 0.85rem 2.5rem;
    background: #f0fdf4; border-bottom: 1px solid #d1fae5; font-size: 0.78rem; color: #374151;
}
.agreement-letterhead .lh-contacts span { display: inline-flex; align-items: center; gap: 5px; }
.agreement-letterhead .lh-contacts i { color: #059669; font-size: 0.72rem; }
.agreement-letterhead .lh-body { padding: 2rem 2.5rem; }
.agreement-letterhead .lh-watermark {
    position: absolute; inset: 0;
    background-image: url("<?= $path_to_root ?>LOGO W.png");
    background-repeat: no-repeat; background-position: center;
    background-size: 280px; opacity: 0.03; pointer-events: none; z-index: 0;
}
.agreement-letterhead .lh-body > * { position: relative; z-index: 1; }
.agreement-top-badge {
    display: inline-block; background: var(--color-primary, #065f46);
    color: #fff; padding: 5px 14px; font-size: 0.65rem; font-weight: 700;
    letter-spacing: 1px; border-radius: 6px; text-transform: uppercase; margin-bottom: 1rem;
}
.agreement-sub-title {
    font-size: 1.4rem; font-weight: 700; color: var(--color-primary, #065f46);
    margin-bottom: 0.75rem; text-align: center;
}
.agreement-ref-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 0.85rem; background: #f8fafc; padding: 12px 16px;
    border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;
}
.agreement-section {
    margin-top: 20px; padding: 18px; border: 1px solid #e2e8f0;
    border-radius: 10px; background: #fafbfc;
}
.agreement-section-title {
    font-weight: 700; margin-bottom: 12px; font-size: 1rem;
    color: var(--color-primary, #065f46);
    border-bottom: 2px solid #d1fae5; padding-bottom: 6px;
}
.agreement-form-group { margin-bottom: 8px; font-size: 0.9rem; line-height: 1.6; }
.agreement-signature-section {
    margin-top: 35px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 25px;
}
.agreement-signature-box {
    flex: 1; min-width: 220px; font-size: 0.85rem; text-align: center;
}
.agreement-signature-line {
    display: block; border-bottom: 1.5px solid #475569; margin-top: 35px; margin-bottom: 8px;
    min-height: 40px; display: flex; align-items: flex-end; justify-content: center;
}
.agreement-signature-line img { max-height: 45px; max-width: 180px; object-fit: contain; }
.agreement-witness-section {
    margin-top: 30px; border-top: 1px dashed #e2e8f0; padding-top: 20px;
}
.agreement-witness-title { font-weight: 700; margin-bottom: 12px; color: var(--color-primary, #065f46); font-size: 0.95rem; }
.agreement-witness-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
.agreement-witness-box { border-bottom: 1.5px solid #475569; min-height: 30px; }

/* SIGNATURE CAPTURE SECTION */
.sig-capture-section {
    max-width: 900px; margin: 0 auto 2rem auto;
    background: var(--color-card-bg, #fff); border: 1.5px solid var(--color-border, #e2e8f0);
    border-radius: 16px; padding: 1.75rem 2rem; position: relative;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.sig-capture-section h5 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--color-text, #1a1a2e);
}
.sig-capture-section .sig-subtitle {
    font-size: 0.78rem; color: var(--color-text-light, #6b7280); margin-bottom: 1.25rem;
}
.sig-tabs { display: flex; gap: 6px; margin-bottom: 1rem; }
.sig-tab {
    padding: 8px 20px; border-radius: 10px; border: 1.5px solid var(--color-border, #e2e8f0);
    font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
    background: var(--color-card-bg, #fff); color: var(--color-text-light, #6b7280);
}
.sig-tab:hover { border-color: #10b981; color: #10b981; }
.sig-tab.active { background: #10b981; color: #fff; border-color: #10b981; }
.sig-panel { display: none; }
.sig-panel.active { display: block; }
.sig-canvas-wrap {
    position: relative; border: 2px dashed #d1d5db; border-radius: 12px;
    overflow: hidden; background: #fff;
}
.sig-canvas-wrap canvas { display: block; width: 100%; cursor: crosshair; }
.sig-canvas-placeholder {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; color: #9ca3af; pointer-events: none;
    font-size: 0.82rem; gap: 6px;
}
.sig-canvas-placeholder i { font-size: 1.5rem; }
.sig-canvas-placeholder.hidden { display: none; }
.sig-canvas-actions {
    display: flex; gap: 8px; margin-top: 0.75rem; justify-content: flex-end;
}
.sig-upload-zone {
    border: 2px dashed #d1d5db; border-radius: 12px; padding: 2rem;
    text-align: center; cursor: pointer; transition: all 0.15s;
    background: #f9fafb;
}
.sig-upload-zone:hover { border-color: #10b981; background: #f0fdf4; }
.sig-upload-zone i { font-size: 2rem; color: #9ca3af; margin-bottom: 8px; }
.sig-upload-zone p { font-size: 0.82rem; color: #6b7280; margin: 0; }
.sig-upload-zone small { font-size: 0.7rem; color: #9ca3af; }
.sig-upload-preview { text-align: center; }
.sig-upload-preview img { max-height: 80px; max-width: 280px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb; }
.sig-status {
    display: flex; align-items: center; gap: 8px; padding: 10px 16px;
    border-radius: 10px; font-size: 0.78rem; font-weight: 600; margin-top: 1rem;
}
.sig-status.signed { background: #dcfce7; color: #166534; }
.sig-status.not-signed { background: #fef3c7; color: #92400e; }

/* PRINT / DOWNLOAD BUTTONS */
.sig-action-bar {
    max-width: 900px; margin: 0 auto 2rem auto; display: flex; gap: 12px; justify-content: flex-end;
}
.btn-print-agreement {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff; border: none; padding: 12px 32px; border-radius: 12px;
    font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(16,185,129,0.25); display: inline-flex; align-items: center; gap: 8px;
}
.btn-print-agreement:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.35); }
.btn-print-agreement:disabled {
    background: #d1d5db; color: #9ca3af; cursor: not-allowed; box-shadow: none;
}
.btn-cancel-agreement {
    background: transparent; color: var(--color-text-light, #6b7280);
    border: 1.5px solid var(--color-border, #e5e7eb); padding: 12px 28px;
    border-radius: 12px; font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: all 0.2s;
}
.btn-cancel-agreement:hover { border-color: #ef4444; color: #ef4444; }

/* PRINT STYLES */
@media print {
    .sidebar, .top-navbar, .dashboard-footer, .navbar, .nav-controls,
    .sig-capture-section, .sig-action-bar, #printButton, .navbar-icon-btn { display: none !important; }
    body { background: #fff !important; color: #000 !important; padding: 0 !important; }
    .main-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .content-body { padding: 0 !important; }
    .agreement-letterhead {
        border: none !important; box-shadow: none !important; border-radius: 0 !important;
        max-width: 100% !important; margin: 0 !important;
    }
    .agreement-letterhead .lh-header { background: #065f46 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .agreement-letterhead .lh-watermark { opacity: 0.04 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .agreement-section { page-break-inside: avoid; background: #fff !important; border: 1px solid #000 !important; }
}

@media (max-width: 768px) {
    .agreement-letterhead .lh-header { flex-direction: column; text-align: center; padding: 1.25rem 1.5rem; }
    .agreement-letterhead .lh-contacts { flex-direction: column; gap: 0.5rem; padding: 0.75rem 1.5rem; }
    .agreement-letterhead .lh-body { padding: 1.25rem 1.5rem; }
    .agreement-signature-section { flex-direction: column; }
    .agreement-witness-grid { grid-template-columns: 1fr; }
    .sig-action-bar { flex-direction: column; }
    .btn-print-agreement, .btn-cancel-agreement { width: 100%; justify-content: center; }
}
</style>

<!-- SIGNATURE CAPTURE SECTION -->
<div class="sig-capture-section" id="sigCaptureSection">
    <h5><i class="fas fa-signature me-2" style="color:#10b981;"></i>Signature Required</h5>
    <p class="sig-subtitle">Draw or upload your signature to complete the agreement. Printing is disabled until a signature is provided.</p>

    <div class="sig-tabs">
        <button class="sig-tab active" onclick="switchSigTab('draw')"><i class="fas fa-pen-fancy me-1"></i> Draw</button>
        <button class="sig-tab" onclick="switchSigTab('upload')"><i class="fas fa-cloud-upload-alt me-1"></i> Upload</button>
    </div>

    <!-- DRAW PANEL -->
    <div class="sig-panel active" id="sigDrawPanel">
        <div class="sig-canvas-wrap">
            <canvas id="sigCanvas" width="700" height="160"></canvas>
            <div class="sig-canvas-placeholder" id="sigPlaceholder">
                <i class="fas fa-pen-fancy"></i>
                Draw your signature here
            </div>
        </div>
        <div class="sig-canvas-actions">
            <button class="btn btn-sm btn-outline-danger" onclick="clearSignature()"><i class="fas fa-eraser me-1"></i> Clear</button>
        </div>
    </div>

    <!-- UPLOAD PANEL -->
    <div class="sig-panel" id="sigUploadPanel">
        <div class="sig-upload-zone" id="sigUploadZone" onclick="document.getElementById('sigFileInput').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>Click to upload your signature image</p>
            <small>PNG, JPG or SVG (max 2MB)</small>
        </div>
        <input type="file" id="sigFileInput" accept="image/png,image/jpeg,image/svg+xml" style="display:none" onchange="handleSigUpload(event)">
        <div class="sig-upload-preview" id="sigUploadPreview" style="display:none;">
            <img id="sigPreviewImg" src="" alt="Signature preview">
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-danger" onclick="clearUploadedSig()"><i class="fas fa-times me-1"></i> Remove</button>
            </div>
        </div>
    </div>

    <!-- STATUS -->
    <div class="sig-status not-signed" id="sigStatus">
        <i class="fas fa-exclamation-circle"></i>
        <span>No signature provided yet</span>
    </div>
</div>

<!-- ACTION BAR -->
<div class="sig-action-bar">
    <button class="btn-cancel-agreement" onclick="window.location.reload()"><i class="fas fa-times me-1"></i> Cancel</button>
    <button class="btn-print-agreement" id="printBtn" disabled onclick="printAgreement()">
        <i class="fas fa-print"></i> Print / Download Agreement
    </button>
</div>

<!-- AGREEMENT DOCUMENT -->
<div class="agreement-letterhead" id="agreementDoc">
    <div class="lh-watermark"></div>

    <?php if ($isAgentAgreement): ?>
    <!-- LETTERHEAD HEADER -->
    <div class="lh-header">
        <img class="lh-logo" src="<?= $path_to_root ?>LOGO W.png" alt="WQS Logo" onerror="this.src='<?= $path_to_root ?>images/wqs-logo.png'">
        <div class="lh-brand">
            <h3><?= $companyName ?></h3>
            <small><?= $companyTagline ?></small>
        </div>
    </div>
    <div class="lh-contacts">
        <span><i class="fas fa-map-marker-alt"></i> <?= $companyAddress ?></span>
        <span><i class="fas fa-envelope"></i> <?= $companyEmail ?></span>
        <span><i class="fas fa-phone"></i> <?= $companyPhone ?></span>
        <span><i class="fas fa-globe"></i> <?= $companyWebsite ?></span>
    </div>

    <div class="lh-body">
        <div class="agreement-top-badge">Referral Partner Agreement</div>
        <div class="agreement-sub-title">Referral Partner Agreement</div>

        <div class="agreement-ref-row">
            <div><strong>Agreement Ref:</strong> <span style="color:#059669;font-weight:700;"><?= $agreementRef ?></span></div>
            <div><strong>Date:</strong> <?= $agreementDate ?></div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">1. PARTIES</div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="agreement-form-group"><strong>Company:</strong> <?= $companyName ?></div>
                    <div class="agreement-form-group"><strong>Address:</strong> <?= $companyAddress ?></div>
                    <div class="agreement-form-group"><strong>Email:</strong> <?= $companyEmail ?></div>
                    <div class="agreement-form-group"><strong>Phone:</strong> <?= $companyPhone ?></div>
                </div>
                <div class="col-md-6">
                    <div class="agreement-form-group"><strong>Agent Name:</strong> <?= htmlspecialchars($agent['name']) ?></div>
                    <div class="agreement-form-group"><strong>Agent Email:</strong> <?= htmlspecialchars($agent['email']) ?></div>
                    <div class="agreement-form-group"><strong>Agent Phone:</strong> <?= htmlspecialchars($agent['phone']) ?></div>
                    <div class="agreement-form-group"><strong>Agent Role:</strong> <?= htmlspecialchars($partnerRole ?: 'Registered Referral Agent') ?></div>
                </div>
            </div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">2. SCOPE OF SERVICES & REFERRALS</div>
            <div class="agreement-form-group">
                The Referral Agent agrees to refer prospective clients and project requests (including but not limited to Web Apps, Mobile Apps, and AI/Automation requests) to the Company.
                All referred clients must sign up using the Agent's unique referral link.
            </div>
            <div class="agreement-form-group mt-3">
                The Company agrees to professionally evaluate, design, consult, and execute software solutions for all referred clients.
            </div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">3. COMMISSION STRUCTURE</div>
            <div class="row">
                <div class="col-12">
                    <p class="mb-2"><strong>Commission Rate:</strong></p>
                    <p style="color:#059669;font-weight:700;font-size:1.25rem;"><?= htmlspecialchars($commissionPct ?: '0') ?>% of Final Project Budget</p>
                    <p class="text-muted small mb-0">
                        * Commission is paid ONLY for successful (i.e. completed and fully settled) projects referred by the Agent. No commission will be paid for pending, cancelled, or rejected request submissions.
                    </p>
                </div>
            </div>
        </div>

        <!-- SIGNATURE BLOCK -->
        <div class="agreement-signature-section">
            <div class="agreement-signature-box">
                <div class="agreement-signature-line" id="agentSigLine">
                    <img id="agentSigImg" src="" alt="" style="display:none;">
                </div>
                <strong>Agent Signature & Date</strong>
            </div>
            <div class="agreement-signature-box">
                <span class="agreement-signature-line"></span>
                <strong>Wise Quotient Soft Representative & Date</strong>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- LETTERHEAD HEADER -->
    <div class="lh-header">
        <img class="lh-logo" src="<?= $path_to_root ?>LOGO W.png" alt="WQS Logo" onerror="this.src='<?= $path_to_root ?>images/wqs-logo.png'">
        <div class="lh-brand">
            <h3><?= $companyName ?></h3>
            <small><?= $companyTagline ?></small>
        </div>
    </div>
    <div class="lh-contacts">
        <span><i class="fas fa-map-marker-alt"></i> <?= $companyAddress ?></span>
        <span><i class="fas fa-envelope"></i> <?= $companyEmail ?></span>
        <span><i class="fas fa-phone"></i> <?= $companyPhone ?></span>
        <span><i class="fas fa-globe"></i> <?= $companyWebsite ?></span>
    </div>

    <div class="lh-body">
        <div class="agreement-top-badge">Client Project Agreement</div>
        <div class="agreement-sub-title">Client Project Agreement</div>

        <div class="agreement-ref-row">
            <div><strong>Agreement Ref:</strong> <span style="color:#059669;font-weight:700;"><?= $agreementRef ?></span></div>
            <div><strong>Date:</strong> <?= $agreementDate ?></div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">1. PARTIES</div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="agreement-form-group"><strong>Company:</strong> <?= $companyName ?></div>
                    <div class="agreement-form-group"><strong>Address:</strong> <?= $companyAddress ?></div>
                    <div class="agreement-form-group"><strong>Email:</strong> <?= $companyEmail ?></div>
                    <div class="agreement-form-group"><strong>Phone:</strong> <?= $companyPhone ?></div>
                </div>
                <div class="col-md-6">
                    <div class="agreement-form-group"><strong>Client Name:</strong> __________________________________________</div>
                    <div class="agreement-form-group"><strong>Client Address:</strong> _______________________________________</div>
                    <div class="agreement-form-group"><strong>Client Email:</strong> ________________________________________</div>
                    <div class="agreement-form-group"><strong>Client Phone:</strong> ________________________________________</div>
                </div>
            </div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">2. PROJECT OVERVIEW</div>
            <div class="agreement-form-group">
                <strong>Project Title:</strong><br>
                <div class="pb-2 border-bottom text-muted">__________________________________________________________________________</div>
            </div>
            <div class="agreement-form-group mt-3">
                <strong>Project Description:</strong><br>
                <div class="pb-2 border-bottom text-muted">__________________________________________________________________________</div>
                <div class="pb-2 border-bottom text-muted">__________________________________________________________________________</div>
            </div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">3. TIMELINE & MILESTONES</div>
            <div class="row">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Start Date:</strong></p>
                    <p class="text-muted">_____ / _________ / _________</p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Expected Completion Date:</strong></p>
                    <p class="text-muted">_____ / _________ / _________</p>
                </div>
            </div>
        </div>

        <div class="agreement-section">
            <div class="agreement-section-title">4. PAYMENT TERMS</div>
            <div class="row">
                <div class="col-sm-6 mb-3 mb-sm-0">
                    <p class="mb-1"><strong>Total Project Cost:</strong></p>
                    <p style="color:#059669;font-weight:700;font-size:1.25rem;">₦ _________________________</p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Payment Schedule & Milestones:</strong></p>
                    <p class="text-muted">_______________________________________</p>
                </div>
            </div>
        </div>

        <!-- SIGNATURE BLOCK -->
        <div class="agreement-signature-section">
            <div class="agreement-signature-box">
                <div class="agreement-signature-line" id="clientSigLine">
                    <img id="clientSigImg" src="" alt="" style="display:none;">
                </div>
                <strong>Client Signature & Date</strong>
            </div>
            <div class="agreement-signature-box">
                <span class="agreement-signature-line"></span>
                <strong>Wise Quotient Soft Representative & Date</strong>
            </div>
        </div>

        <div class="agreement-witness-section">
            <h5 class="agreement-witness-title">Witness Details</h5>
            <div class="agreement-witness-grid">
                <div>
                    <div class="agreement-witness-box"></div>
                    <span class="small text-muted">Witness Name & Address</span>
                </div>
                <div>
                    <div class="agreement-witness-box"></div>
                    <span class="small text-muted">Witness Signature & Date</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const isAgent = <?= $isAgentAgreement ? 'true' : 'false' ?>;
let sigDataUrl = null;
let drawing = false;
let hasDrawn = false;
let lastX = 0, lastY = 0;

const canvas = document.getElementById('sigCanvas');
const ctx = canvas.getContext('2d');
const placeholder = document.getElementById('sigPlaceholder');
const printBtn = document.getElementById('printBtn');
const sigStatus = document.getElementById('sigStatus');

function resizeCanvas() {
    const wrap = canvas.parentElement;
    const rect = wrap.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = 160;
    if (sigDataUrl) {
        const img = new Image();
        img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        img.src = sigDataUrl;
    }
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

canvas.addEventListener('mousedown', e => {
    drawing = true;
    const r = canvas.getBoundingClientRect();
    lastX = e.clientX - r.left;
    lastY = e.clientY - r.top;
});
canvas.addEventListener('mousemove', e => {
    if (!drawing) return;
    const r = canvas.getBoundingClientRect();
    const x = e.clientX - r.left;
    const y = e.clientY - r.top;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.strokeStyle = '#1a1a2e';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();
    lastX = x;
    lastY = y;
    if (!hasDrawn) { hasDrawn = true; placeholder.classList.add('hidden'); }
});
canvas.addEventListener('mouseup', () => { drawing = false; saveDrawnSig(); });
canvas.addEventListener('mouseleave', () => { drawing = false; saveDrawnSig(); });

canvas.addEventListener('touchstart', e => {
    e.preventDefault();
    drawing = true;
    const r = canvas.getBoundingClientRect();
    const t = e.touches[0];
    lastX = t.clientX - r.left;
    lastY = t.clientY - r.top;
});
canvas.addEventListener('touchmove', e => {
    e.preventDefault();
    if (!drawing) return;
    const r = canvas.getBoundingClientRect();
    const t = e.touches[0];
    const x = t.clientX - r.left;
    const y = t.clientY - r.top;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.strokeStyle = '#1a1a2e';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();
    lastX = x;
    lastY = y;
    if (!hasDrawn) { hasDrawn = true; placeholder.classList.add('hidden'); }
});
canvas.addEventListener('touchend', e => { e.preventDefault(); drawing = false; saveDrawnSig(); });

function saveDrawnSig() {
    if (!hasDrawn) return;
    sigDataUrl = canvas.toDataURL('image/png');
    onSignatureReady();
}

function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasDrawn = false;
    sigDataUrl = null;
    placeholder.classList.remove('hidden');
    onSignatureCleared();
}

function switchSigTab(tab) {
    document.querySelectorAll('.sig-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sig-panel').forEach(p => p.classList.remove('active'));
    if (tab === 'draw') {
        document.querySelectorAll('.sig-tab')[0].classList.add('active');
        document.getElementById('sigDrawPanel').classList.add('active');
    } else {
        document.querySelectorAll('.sig-tab')[1].classList.add('active');
        document.getElementById('sigUploadPanel').classList.add('active');
    }
}

function handleSigUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ icon: 'warning', title: 'File too large', text: 'Signature image must be under 2MB.' });
        return;
    }
    const reader = new FileReader();
    reader.onload = function(ev) {
        sigDataUrl = ev.target.result;
        document.getElementById('sigPreviewImg').src = sigDataUrl;
        document.getElementById('sigUploadPreview').style.display = 'block';
        document.getElementById('sigUploadZone').style.display = 'none';
        onSignatureReady();
    };
    reader.readAsDataURL(file);
}

function clearUploadedSig() {
    sigDataUrl = null;
    document.getElementById('sigFileInput').value = '';
    document.getElementById('sigUploadPreview').style.display = 'none';
    document.getElementById('sigUploadZone').style.display = 'block';
    onSignatureCleared();
}

function onSignatureReady() {
    printBtn.disabled = false;
    sigStatus.className = 'sig-status signed';
    sigStatus.innerHTML = '<i class="fas fa-check-circle"></i><span>Signature provided — you can now print or download the agreement</span>';

    const sigImg = isAgent ? document.getElementById('agentSigImg') : document.getElementById('clientSigImg');
    const sigLine = isAgent ? document.getElementById('agentSigLine') : document.getElementById('clientSigLine');
    if (sigImg && sigDataUrl) {
        sigImg.src = sigDataUrl;
        sigImg.style.display = 'block';
        sigLine.style.borderBottom = 'none';
    }
}

function onSignatureCleared() {
    printBtn.disabled = true;
    sigStatus.className = 'sig-status not-signed';
    sigStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>No signature provided yet</span>';

    const sigImg = isAgent ? document.getElementById('agentSigImg') : document.getElementById('clientSigImg');
    const sigLine = isAgent ? document.getElementById('agentSigLine') : document.getElementById('clientSigLine');
    if (sigImg) {
        sigImg.src = '';
        sigImg.style.display = 'none';
        sigLine.style.borderBottom = '1.5px solid #475569';
    }
}

function printAgreement() {
    if (!sigDataUrl) {
        Swal.fire({ icon: 'warning', title: 'Signature Required', text: 'Please draw or upload your signature before printing.' });
        return;
    }
    window.print();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
