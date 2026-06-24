<?php
$path_to_root = "../";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];

// Get user info and check if role is agent
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userObj = $userStmt->fetch(PDO::FETCH_ASSOC);
$user_role = $userObj ? strtolower($userObj['role']) : 'user';

if ($user_role !== 'agent') {
    die("Access Denied: You must be an approved partner to view this page.");
}

// Check if signature already exists on disk or DB
$signatureFile = 'uploads/signatures/user_' . $userId . '.png';
$signaturePath = dirname(__DIR__) . '/' . $signatureFile;
$hasSignature = !empty($userObj['signature_path']) && file_exists(dirname(__DIR__) . '/' . $userObj['signature_path']);
if (!$hasSignature && file_exists($signaturePath)) {
    $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?")->execute([$signatureFile, $userId]);
    $userObj['signature_path'] = $signatureFile;
    $hasSignature = true;
}

// ====== Handle Signature Action POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_signature') {
        header('Content-Type: application/json');
        $sigType = $_POST['sig_type'] ?? ''; // 'draw' or 'upload'
        $signatureDir = dirname(__DIR__) . '/uploads/signatures/';
        if (!file_exists($signatureDir)) {
            mkdir($signatureDir, 0777, true);
        }
        
        if ($sigType === 'draw') {
            $sigData = $_POST['sig_data'] ?? '';
            if (empty($sigData)) {
                echo json_encode(['success' => false, 'message' => 'No signature drawing data provided.']);
                exit;
            }
            $filteredData = substr($sigData, strpos($sigData, ",") + 1);
            $decodedData = base64_decode($filteredData);
            if (file_put_contents($signaturePath, $decodedData)) {
                $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?")->execute([$signatureFile, $userId]);
                echo json_encode(['success' => true, 'message' => 'Signature drawn and saved successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save signature image.']);
            }
            exit;
        } elseif ($sigType === 'upload') {
            if (!isset($_FILES['sig_file']) || $_FILES['sig_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                exit;
            }
            $tmpName = $_FILES['sig_file']['tmp_name'];
            if (strpos(mime_content_type($tmpName), 'image') === false) {
                echo json_encode(['success' => false, 'message' => 'File must be an image.']);
                exit;
            }
            if (move_uploaded_file($tmpName, $signaturePath)) {
                $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?")->execute([$signatureFile, $userId]);
                echo json_encode(['success' => true, 'message' => 'Signature uploaded and saved successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
            }
            exit;
        }
    } elseif ($_POST['action'] === 'clear_signature') {
        header('Content-Type: application/json');
        $pdo->prepare("UPDATE users SET signature_path = NULL WHERE id = ?")->execute([$userId]);
        if (file_exists($signaturePath)) {
            @unlink($signaturePath);
        }
        echo json_encode(['success' => true, 'message' => 'Signature cleared.']);
        exit;
    }
}

// Fetch approved request details
$requestStmt = $pdo->prepare("SELECT * FROM agent_requests WHERE user_id = ? AND status = 'approved' LIMIT 1");
$requestStmt->execute([$userId]);
$request = $requestStmt->fetch(PDO::FETCH_ASSOC);

// Get base commission from same source as dashboard (hr_partners or hr_settings)
$baseCommissionPct = 10;
try {
    $partnerChk = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
    $partnerChk->execute([$userId]);
    $partnerRow = $partnerChk->fetch(PDO::FETCH_ASSOC);
    if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
        $baseCommissionPct = (float)$partnerRow['default_commission_percent'];
    } else {
        $setChk = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
        $setRow = $setChk->fetch(PDO::FETCH_ASSOC);
        if ($setRow) $baseCommissionPct = (float)$setRow['setting_value'];
    }
} catch (Exception $e) {}

$partnerLevel = $request ? $request['partner_level'] : 'Bronze Partner';
$approvedDate = $request && !empty($request['approved_at']) ? date("F j, Y", strtotime($request['approved_at'])) : date("F j, Y");

// Calculate tier based on completed projects (same logic as dashboard)
$successCountStmt = $pdo->prepare("SELECT COUNT(*) FROM ongoing_projects WHERE user_id = ? AND status = 'completed'");
$successCountStmt->execute([$userId]);
$successfulProjectsCount = (int)$successCountStmt->fetchColumn();

// Determine current tier & commission rate (tiers are multiples of the admin-set base)
$tierName = "Bronze";
$commissionRate = $baseCommissionPct;
$nextTierName = "Silver";

if ($successfulProjectsCount >= 6) {
    $tierName = "Gold";
    $commissionRate = round($baseCommissionPct * 1.5);
    $nextTierName = "";
} elseif ($successfulProjectsCount >= 3) {
    $tierName = "Silver";
    $commissionRate = round($baseCommissionPct * 1.2);
    $nextTierName = "Gold";
}

$partnerLevel = $tierName . ' Partner';
$partnerLevelPercentage = $commissionRate;

// Generate and store QR Code dynamically
$qrCodeDir = __DIR__ . '/uploads/qrcodes/';
if (!file_exists($qrCodeDir)) {
    mkdir($qrCodeDir, 0777, true);
}
$qrCodeFile = $qrCodeDir . 'partner_' . $userId . '.png';
$qrCodeRelativePath = 'uploads/qrcodes/partner_' . $userId . '.png';

if (!file_exists($qrCodeFile)) {
    // Construct validation URL pointing to the verification page
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    $verificationData = $protocol . '://' . $host . $basePath . '/verify_partner.php?id=' . $userId;
    
    // Call external QR Code Generator API
    $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verificationData);
    
    try {
        $imgData = file_get_contents($apiUrl);
        if ($imgData !== false) {
            file_put_contents($qrCodeFile, $imgData);
        }
    } catch (Exception $e) {
        // Fallback placeholder URL
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Letter of Approval - Wise Quotient Soft</title>
    <!-- Include Bootstrap 5 & Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Alex+Brush&family=Caveat:wght@700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 2rem 0;
        }
        
        .letter-container {
            max-width: 800px;
            background-color: #ffffff;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            border: 1px solid #e2e8f0;
        }
        
        /* Premium Corporate Letterhead Header */
        .letter-header-corporate {
            border-bottom: 2px solid #ff6600 !important;
            background: #ffffff;
        }
        
        /* Watermark Styles */
        .watermark-img {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 380px;
            opacity: 0.04;
            pointer-events: none;
            z-index: 0;
            user-select: none;
        }
        
        .letter-body {
            position: relative;
            z-index: 1;
            padding: 3rem 2.5rem;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .address-grid {
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .company-address, .partner-address {
            font-size: 0.82rem;
            line-height: 1.4;
        }
        .address-title {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 0.4rem;
        }
        
        .agreement-highlight {
            background-color: #f8fafc;
            border-left: 4px solid #ff6600;
            border-top: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem;
            border-radius: 0 12px 12px 0;
            margin: 2rem 0;
        }
        
        /* Bottom passport picture and QR section */
        .verification-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 2.5rem;
        }
        .partner-profile-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .partner-photo-box {
            width: 70px;
            height: 85px;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .partner-photo-box.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: bold;
            color: #94a3b8;
            text-align: center;
        }
        .qr-code-box {
            width: 85px;
            height: 85px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            padding: 3px;
        }
        
        /* Signature Layout */
        .signatures-grid {
            margin-top: 3.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1.5px solid #475569;
            margin-bottom: 0.5rem;
            min-height: 55px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .signature-font {
            font-family: 'Alex Brush', cursive;
            font-size: 2rem;
            color: #0d3870;
            line-height: 1;
        }
        
        .text-orange {
            color: #ff6600 !important;
        }

        /* Signature Capture Styles */
        .nav-pills .nav-link.active {
            background-color: #0A2D5E !important;
            color: #ffffff !important;
        }
        .nav-pills .nav-link {
            color: #64748b;
        }
        .upload-zone {
            transition: all 0.3s ease;
        }
        .upload-zone:hover, .upload-zone.dragover {
            background: #f8fafc !important;
            border-color: #0A2D5E !important;
        }

        /* Hide navbar and controls during prints */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .no-print {
                display: none !important;
            }
            .letter-container {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            .letter-header-corporate {
                border-bottom: 2px solid #ff6600 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .watermark-img {
                opacity: 0.045 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .verification-block {
                background: #ffffff !important;
                border: 1px dashed #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Controls Bar (Sticky top) -->
<div class="container max-width-800 mb-4 no-print" id="controlsBar" style="display: <?= $hasSignature ? 'block' : 'none' ?>;">
    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border">
        <a href="referral_portal.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <div class="d-flex gap-2">
            <button id="changeSignatureBtn" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="fas fa-signature me-1"></i> Change Signature
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4" style="background:#ff6600; border:none;">
                <i class="fas fa-print me-2"></i> Print / Save as PDF
            </button>
        </div>
    </div>
</div>

<div class="container py-5 no-print" id="signatureSetupContainer" style="max-width: 600px; display: <?= $hasSignature ? 'none' : 'block' ?>;">
    <div class="card border-0 shadow-lg p-4 rounded-4 bg-white">
        <div class="text-center mb-4">
            <img src="../LOGO W.png" alt="WQS Logo" style="height: 50px; object-fit: contain;" class="mb-3">
            <h4 class="fw-bold text-body" style="color: #0A2D5E !important;">Signature Required</h4>
            <p class="text-muted small">Please draw or upload your signature to generate and download your Partnership Approval Letter.</p>
        </div>

        <!-- Tab Nav -->
        <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded-pill" id="sigTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-bold" id="draw-tab" data-bs-toggle="tab" data-bs-target="#drawPanel" type="button" role="tab">
                    <i class="fas fa-pen-fancy me-2"></i>Draw Signature
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold" id="upload-tab" data-bs-toggle="tab" data-bs-target="#uploadPanel" type="button" role="tab">
                    <i class="fas fa-file-upload me-2"></i>Upload Image
                </button>
            </li>
        </ul>

        <!-- Tab Panels -->
        <div class="tab-content" id="sigTabContents">
            <!-- Panel 1: Draw -->
            <div class="tab-pane fade show active" id="drawPanel" role="tabpanel">
                <div class="border rounded-4 p-1 mb-3 bg-light position-relative" style="border-style: dashed !important; border-width: 2px !important;">
                    <canvas id="sig-canvas" style="width: 100%; height: 220px; background: #ffffff; border-radius: 12px; cursor: crosshair; touch-action: none;"></canvas>
                    <div class="position-absolute bottom-0 start-0 m-2 small text-muted pointer-events-none" style="opacity: 0.6;">
                        <i class="fas fa-signature me-1"></i> Sign here
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3 rounded-pill" id="sig-clear-btn">
                        <i class="fas fa-undo me-1"></i> Clear
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4 rounded-pill fw-bold" id="sig-submit-draw" style="background: #0A2D5E; border: none;">
                        <i class="fas fa-check me-1"></i> Apply Signature
                    </button>
                </div>
            </div>

            <!-- Panel 2: Upload -->
            <div class="tab-pane fade" id="uploadPanel" role="tabpanel">
                <div class="upload-zone p-4 mb-3 border rounded-4 text-center bg-light" id="sig-upload-zone" onclick="document.getElementById('sig-file-input').click()" style="cursor: pointer; border-style: dashed !important; border-width: 2px !important;">
                    <i class="fas fa-cloud-upload-alt fa-3x mb-2 text-muted"></i>
                    <p class="mb-1 small fw-bold">Click or drag signature file here</p>
                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">PNG or JPG with transparent/white background (Max 2MB)</p>
                    <input type="file" id="sig-file-input" class="d-none" accept="image/*" />
                </div>
                <div id="sig-upload-preview-container" class="mb-3 text-center d-none">
                    <img id="sig-upload-preview" src="" style="max-height: 120px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 4px; background: white;" alt="Signature Preview">
                    <div class="mt-2 text-danger small" id="sig-remove-file" style="cursor: pointer;"><i class="fas fa-trash me-1"></i> Remove photo</div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-primary px-4 rounded-pill fw-bold" id="sig-submit-upload" style="background: #0A2D5E; border: none;" disabled>
                        <i class="fas fa-upload me-1"></i> Upload Signature
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="letter-container" id="letterContainerSection" style="display: <?= $hasSignature ? 'block' : 'none' ?>;">
    <!-- Watermark Logo -->
    <img src="../LOGO W.png" class="watermark-img" alt="WQS Logo" onerror="this.src='../LOGO W.png'">

    <!-- Corporate Letterhead Header -->
    <div class="letter-header-corporate p-4 d-flex align-items-center justify-content-between" style="border-bottom: 2px solid #ff6600; background: #ffffff;">
        <div class="d-flex align-items-center gap-3">
            <img src="../LOGO W.png" alt="WQS Logo" style="height: 70px; object-fit: contain;">
            <div>
                <h3 class="fw-bold mb-0" style="color: #0A2D5E; font-size: 1.55rem; letter-spacing: -0.5px;">WISE QUOTIENT SOFT</h3>
                <span class="small text-muted fw-bold" style="letter-spacing: 0.08em; text-transform: uppercase; font-size: 0.7rem;">Software Engineering & IT Consultants</span>
            </div>
        </div>
        <div class="text-end small text-muted" style="font-size: 0.76rem; line-height: 1.45;">
            <div><i class="fas fa-map-marker-alt me-1 text-orange"></i> No.1 Ibadan Street, Kaduna, Nigeria</div>
            <div><i class="fas fa-phone-alt me-1 text-orange"></i> +234 807 741 6106</div>
            <div><i class="fas fa-envelope me-1 text-orange"></i> info@wisequotientsoft.com</div>
            <div><i class="fas fa-globe me-1 text-orange"></i> www.wisequotientsoft.com</div>
        </div>
    </div>

    <div class="text-center my-4">
        <h4 class="fw-bold uppercase mb-0" style="color: #0A2D5E; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; display: inline-block; padding-bottom: 6px;">
            Partnership Acceptance Letter
        </h4>
    </div>
    
    <!-- Letter Contents -->
    <div class="letter-body">
        
        <!-- Address Blocks -->
        <div class="address-grid">
            <div class="company-address">
                <div class="address-title">From:</div>
                <strong style="color:#0A2D5E;">Wise Quotient Soft</strong><br>
                <span class="text-muted">Partnership Administration Board</span>
            </div>
            <div class="partner-address text-end">
                <div class="address-title">Prepared For:</div>
                <strong><?= htmlspecialchars($userObj['name']) ?></strong><br>
                <span class="text-muted"><?= htmlspecialchars($userObj['email']) ?></span><br>
                <span class="text-muted">Phone: <?= htmlspecialchars($userObj['phone'] !== 'N/A' ? $userObj['phone'] : 'Registered Partner Agent') ?></span><br>
                <span class="badge bg-success-subtle text-success px-2 py-0.5 mt-1" style="font-size: 0.7rem;">Active Affiliate Partner</span>
            </div>
        </div>
        
        <div class="mb-4 text-muted small">
            <strong>Date of Issuance:</strong> <?= date("F j, Y") ?>
        </div>
        
        <div class="mb-4">
            <p>Dear <strong><?= htmlspecialchars($userObj['name']) ?></strong>,</p>
            
            <p>It is with great pleasure and immense anticipation that we at <strong>Wise Quotient Soft</strong> officially accept your proposal for a partnership. We believe that our combined efforts and resources will bring about noteworthy progress in our respective domains and greatly contribute to the achievement of mutual objectives.</p>
            
            <p>Our team is greatly enthused by this partnership opportunity, and we look forward to working in tandem to establish a successful and gratifying alliance that will have positive impacts on both our operations.</p>
            
            <p>Thank you for considering us as your partners. We are eager to commence this journey of cooperative growth and accomplishment.</p>
        </div>
        
        <!-- Agreement highlights -->
        <div class="agreement-highlight">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-file-signature text-orange me-2"></i> Partnership Agreement Highlights</h6>
            <div class="row">
                <div class="col-6">
                    <span class="small text-muted d-block">Agreement Category</span>
                    <strong>Independent Marketing & Affiliation</strong>
                </div>
                <div class="col-6 text-end">
                    <span class="small text-muted d-block">Commission Percentage</span>
                    <strong class="text-success" style="font-size:1.15rem;"><?= htmlspecialchars($partnerLevelPercentage) ?>% of Completed Projects</strong>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-6">
                    <span class="small text-muted d-block">Partnership Rank Tier</span>
                    <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($partnerLevel) ?></span>
                </div>
                <div class="col-6 text-end">
                    <span class="small text-muted d-block">Verification ID</span>
                    <span class="font-monospace fw-bold text-muted" style="font-size:0.8rem;">WQS-REF-AGENT-<?= str_pad($userId, 5, '0', STR_PAD_LEFT) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Passport photo and QR code validations -->
        <div class="verification-block">
            <div class="partner-profile-wrap">
                <?php if (!empty($userObj['picture'])): ?>
                    <img src="<?= htmlspecialchars($userObj['picture']) ?>" alt="Partner Picture" class="partner-photo-box">
                <?php else: ?>
                    <div class="partner-photo-box placeholder">
                        <i class="fas fa-user-circle fa-2x mb-1"></i><br>NO PHOTO
                    </div>
                <?php endif; ?>
                <div>
                    <h6 class="fw-bold mb-1" style="font-size:0.9rem;"><?= htmlspecialchars($userObj['name']) ?></h6>
                    <span class="small text-muted d-block">ID: WQS-AGENT-<?= $userId ?></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size:0.7rem;">Verified Member</span>
                </div>
            </div>
            
            <div class="text-end">
                <?php if (file_exists($qrCodeFile)): ?>
                    <img src="<?= $qrCodeRelativePath ?>" alt="Scan to Verify" class="qr-code-box">
                    <div class="small text-muted mt-1" style="font-size: 0.65rem;"><i class="fas fa-qrcode me-1"></i> Scan to Verify</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Signatures Row -->
        <div class="signatures-grid">
            <div>
                <div class="signature-line">
                    <?php if (!empty($userObj['signature_path']) && file_exists(dirname(__DIR__) . '/' . $userObj['signature_path'])): ?>
                        <img src="<?= $path_to_root . htmlspecialchars($userObj['signature_path']) ?>" style="max-height: 55px; max-width: 170px; object-fit: contain;" alt="Partner Signature">
                    <?php else: ?>
                        <span class="signature-font"><?= htmlspecialchars($userObj['name']) ?></span>
                    <?php endif; ?>
                </div>
                <strong style="font-size:0.85rem; color:#475569;">Partner Signature</strong>
                <div class="text-muted" style="font-size:0.75rem;">Date: <?= $approvedDate ?></div>
            </div>
            <div>
                <div class="signature-line">
                    <span class="signature-font">AbdurRashid Sani</span>
                </div>
                <strong style="font-size:0.85rem; color:#475569;">CEO & Founder Signature</strong>
                <div class="text-muted" style="font-size:0.75rem;">Date: <?= $approvedDate ?></div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Signature drawing canvas functionality
    const canvas = document.getElementById('sig-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let lastX = 0;
        let lastY = 0;

        function getMousePos(canvasDom, touchOrMouseEvent) {
            const rect = canvasDom.getBoundingClientRect();
            const clientX = touchOrMouseEvent.touches ? touchOrMouseEvent.touches[0].clientX : touchOrMouseEvent.clientX;
            const clientY = touchOrMouseEvent.touches ? touchOrMouseEvent.touches[0].clientY : touchOrMouseEvent.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function startDrawing(e) {
            e.preventDefault();
            drawing = true;
            const pos = getMousePos(canvas, e);
            [lastX, lastY] = [pos.x, pos.y];
        }

        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            const pos = getMousePos(canvas, e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            [lastX, lastY] = [pos.x, pos.y];
        }

        function stopDrawing() {
            drawing = false;
        }

        // Mouse listeners
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch listeners for mobile support
        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        // Canvas Responsive Resizer
        function resizeCanvas() {
            const width = canvas.offsetWidth;
            canvas.width = width;
            canvas.height = 220;
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Clear canvas
        document.getElementById('sig-clear-btn').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });

        // Submit drawing
        function isCanvasBlank(canvasDom) {
            const blank = document.createElement('canvas');
            blank.width = canvasDom.width;
            blank.height = canvasDom.height;
            return canvasDom.toDataURL() === blank.toDataURL();
        }

        document.getElementById('sig-submit-draw').addEventListener('click', async () => {
            if (isCanvasBlank(canvas)) {
                alert('Please draw your signature first.');
                return;
            }
            const dataUrl = canvas.toDataURL('image/png');
            const fd = new FormData();
            fd.append('action', 'save_signature');
            fd.append('sig_type', 'draw');
            fd.append('sig_data', dataUrl);

            const btn = document.getElementById('sig-submit-draw');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Applying...';

            try {
                const r = await fetch(location.pathname, { method: 'POST', body: fd });
                const j = await r.json();
                if (j.success) {
                    location.reload();
                } else {
                    alert(j.message || 'Failed to save signature.');
                    btn.disabled = false;
                    btn.innerHTML = 'Apply Signature';
                }
            } catch {
                alert('Network error.');
                btn.disabled = false;
                btn.innerHTML = 'Apply Signature';
            }
        });
    }

    // Signature file upload functionality
    const fileInput = document.getElementById('sig-file-input');
    const uploadZone = document.getElementById('sig-upload-zone');
    const previewContainer = document.getElementById('sig-upload-preview-container');
    const previewImage = document.getElementById('sig-upload-preview');
    const removeFile = document.getElementById('sig-remove-file');
    const uploadBtn = document.getElementById('sig-submit-upload');

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (!file.type.match('image.*')) {
                alert('Please upload a valid signature image file (PNG/JPG).');
                return;
            }
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImage.src = evt.target.result;
                uploadZone.classList.add('d-none');
                previewContainer.classList.remove('d-none');
                uploadBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        });

        if (removeFile) {
            removeFile.addEventListener('click', () => {
                fileInput.value = '';
                previewImage.src = '';
                uploadZone.classList.remove('d-none');
                previewContainer.classList.add('d-none');
                uploadBtn.disabled = true;
            });
        }

        // Drag over support
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        // Submit Uploaded File
        uploadBtn.addEventListener('click', async () => {
            const file = fileInput.files[0];
            if (!file) return;

            const fd = new FormData();
            fd.append('action', 'save_signature');
            fd.append('sig_type', 'upload');
            fd.append('sig_file', file);

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading...';

            try {
                const r = await fetch(location.pathname, { method: 'POST', body: fd });
                const j = await r.json();
                if (j.success) {
                    location.reload();
                } else {
                    alert(j.message || 'Failed to upload signature.');
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = 'Upload Signature';
                }
            } catch {
                alert('Network error.');
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = 'Upload Signature';
            }
        });
    }

    // Change / Reset Signature Button
    const changeSigBtn = document.getElementById('changeSignatureBtn');
    if (changeSigBtn) {
        changeSigBtn.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to clear your current signature? This will remove the signature image and require you to sign again before you can view and print the letter.')) return;

            const fd = new FormData();
            fd.append('action', 'clear_signature');

            try {
                const r = await fetch(location.pathname, { method: 'POST', body: fd });
                const j = await r.json();
                if (j.success) {
                    location.reload();
                } else {
                    alert(j.message || 'Failed to clear signature.');
                }
            } catch {
                alert('Network error.');
            }
        });
    }
});
</script>
</body>
</html>
