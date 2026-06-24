<?php
$page_title = "Partnership Verification";
require_once __DIR__ . '/config.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partner = null;
$request = null;

if ($userId > 0) {
    try {
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $partner = $uStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($partner && strtolower($partner['role']) === 'agent') {
            $rStmt = $pdo->prepare("SELECT * FROM agent_requests WHERE user_id = ? AND status = 'approved' LIMIT 1");
            $rStmt->execute([$userId]);
            $request = $rStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

$isVerified = ($partner && $request);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Verification - Wise Quotient Soft</title>
    <!-- Include Bootstrap 5 and FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: #0f172a;
        }
        .verify-card {
            max-width: 520px;
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            text-align: center;
            transition: transform 0.3s;
        }
        .verify-card:hover {
            transform: translateY(-5px);
        }
        .card-header-bar {
            background: linear-gradient(135deg, #0A2D5E 0%, #1e293b 100%);
            padding: 2rem 1rem;
            color: white;
            position: relative;
        }
        .logo-img {
            height: 48px;
            margin-bottom: 0.5rem;
        }
        .status-badge {
            margin-top: -34px;
            position: relative;
            z-index: 2;
        }
        .badge-circle {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .badge-verified {
            background: #10b981;
            color: white;
            border: 4px solid #ffffff;
        }
        .badge-unverified {
            background: #ef4444;
            color: white;
            border: 4px solid #ffffff;
        }
        .details-list {
            text-align: left;
            background: #f1f5f9;
            border-radius: 16px;
            padding: 1.25rem;
            margin: 1.5rem 0;
            border: 1px solid #e2e8f0;
        }
        .details-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #cbd5e1;
            font-size: 0.92rem;
        }
        .details-item:last-child {
            border-bottom: none;
        }
        .details-label {
            font-weight: 700;
            color: #64748b;
        }
        .details-val {
            font-weight: 800;
            color: #0f172a;
        }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="card-header-bar">
        <img src="LOGO W.png" alt="WQS Logo" class="logo-img" onerror="this.src='/dashboard/wqs/LOGO W.png'">
        <h5 class="fw-bold mb-0">Wise Quotient Soft</h5>
        <span class="small text-white-50">Partnership Authenticator</span>
    </div>
    
    <div class="status-badge">
        <?php if ($isVerified): ?>
            <div class="badge-circle badge-verified">
                <i class="fas fa-shield-alt"></i>
            </div>
        <?php else: ?>
            <div class="badge-circle badge-unverified">
                <i class="fas fa-times"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="p-4 pt-2">
        <?php if ($isVerified): ?>
            <h4 class="fw-extrabold text-success mb-1">Partnership Verified</h4>
            <p class="text-muted small">This partner agent is officially registered and active.</p>
            
            <div class="details-list">
                <div class="details-item">
                    <span class="details-label">Partner Name</span>
                    <span class="details-val"><?= htmlspecialchars($partner['name']) ?></span>
                </div>
                <div class="details-item">
                    <span class="details-label">Partner ID</span>
                    <span class="details-val">WQS-PARTNER-<?= str_pad($partner['id'], 5, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="details-item">
                    <span class="details-label">Partnership Level</span>
                    <span class="details-val text-primary"><?= htmlspecialchars($request['partner_level'] ?: 'Bronze Partner') ?></span>
                </div>
                <div class="details-item">
                    <span class="details-label">Commission Rate</span>
                    <span class="details-val text-success"><?= htmlspecialchars($request['commission_percentage']) ?>%</span>
                </div>
                <div class="details-item">
                    <span class="details-label">Approval Date</span>
                    <span class="details-val"><?= date("d M Y", strtotime($request['approved_at'])) ?></span>
                </div>
            </div>
            
            <div class="text-muted small mt-2">
                <i class="fas fa-lock text-success me-1"></i> Securing digital partnerships through intelligent software.
            </div>
        <?php else: ?>
            <h4 class="fw-extrabold text-danger mb-1">Invalid ID / Unverified</h4>
            <p class="text-muted small">This verification link is invalid, expired, or refers to an unapproved user ID.</p>
            
            <div class="alert alert-danger border-0 rounded-3 text-start small mt-3" style="background:#fee2e2; color:#991b1b;">
                <i class="fas fa-exclamation-triangle me-2"></i> The requested verification data was not found in the official Wise Quotient Soft registry. If this is a mistake, please contact the administrator.
            </div>
        <?php endif; ?>
        
        <div class="mt-4 pt-3 border-top">
            <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                <i class="fas fa-home me-1"></i> Visit Homepage
            </a>
        </div>
    </div>
</div>

</body>
</html>
