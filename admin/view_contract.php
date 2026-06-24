<?php
$path_to_root = "../";
$page_title = "View Contract";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
if ($user['role'] !== 'admin') {
    die("Unauthorized access.");
}

$id = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ?");
$stmt->execute([(int)$id]);
if ($stmt->rowCount() === 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Contract not found.</div></div>");
}
$c = $stmt->fetch(PDO::FETCH_ASSOC);

// Format numbers and dates
$amountFormatted = number_format($c['contract_amount'], 2);
$startDate = date('F j, Y', strtotime($c['start_date']));
$endDate = !empty($c['end_date']) && $c['end_date'] !== '0000-00-00' ? date('F j, Y', strtotime($c['end_date'])) : 'To Be Determined';
$createdDate = date('F j, Y', strtotime($c['created_at']));
?>

<style>
/* Contract Formal Styles */
.contract-paper {
    background: #ffffff;
    color: #000000;
    font-family: 'Times New Roman', Times, serif; /* Formal legal font */
    padding: 3rem 4rem;
    max-width: 850px;
    margin: 0 auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
    line-height: 1.6;
}
.contract-paper h1, .contract-paper h2, .contract-paper h3, .contract-paper h4 {
    font-family: 'Times New Roman', Times, serif;
    color: #000000;
    font-weight: bold;
}
.contract-header {
    border-bottom: 2px solid #000;
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    display: flex;
    flex-direction: column;
}
.contract-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.company-info {
    text-align: right;
    font-size: 0.95rem;
}
.company-info strong {
    font-size: 1.2rem;
    color: #E15501; /* WQS Brand Orange */
}
.contract-header img.logo {
    height: 60px;
    margin-bottom: 1rem;
}
.contract-title {
    font-size: 1.8rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 0.5rem;
    text-align: center;
    font-weight: 900;
    text-decoration: underline;
}
.contract-meta-info {
    text-align: center;
    font-style: italic;
    font-size: 0.95rem;
}
.contract-section {
    margin-bottom: 1.5rem;
}
.contract-section-title {
    font-size: 1.1rem;
    text-transform: uppercase;
    text-decoration: underline;
    margin-bottom: 0.8rem;
    margin-top: 2rem;
}
.passport-box {
    width: 120px;
    height: 140px;
    border: 2px solid #000;
    padding: 3px;
    margin-top: 1rem;
    align-self: flex-end;
}
.passport-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.signature-block {
    margin-top: 4rem;
}
.sig-line {
    border-top: 1px solid #000;
    width: 250px;
    margin-top: 3.5rem;
    padding-top: 0.5rem;
}
.sig-img {
    max-width: 200px;
    max-height: 80px;
    margin-bottom: -3.5rem;
    position: relative;
    z-index: 10;
}
.contract-text {
    font-size: 1.05rem;
    white-space: pre-wrap;
    text-align: justify;
}
.info-table {
    width: 100%;
    margin-bottom: 1.5rem;
    border-collapse: collapse;
}
.info-table th {
    text-align: left;
    padding: 0.4rem 0;
    width: 180px;
    font-weight: bold;
}
.info-table td {
    padding: 0.4rem 0;
}

@media print {
    body * {
        visibility: hidden;
    }
    .contract-paper, .contract-paper * {
        visibility: visible;
    }
    .contract-paper {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border: none;
    }
    .print-actions {
        display: none !important;
    }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 print-actions">
    <div>
        <h4 class="fw-bold text-body-emphasis mb-1">Contract Document</h4>
        <div class="text-muted small">ID: #<?= $c['id'] ?> | Status: <?= ucfirst($c['status']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="contract_hub.php" class="btn btn-outline-secondary rounded-pill fw-bold"><i class="fas fa-arrow-left me-1"></i> Back</a>
        <button onclick="window.print()" class="btn btn-primary rounded-pill fw-bold px-4"><i class="fas fa-print me-2"></i> Print / PDF</button>
    </div>
</div>

<div class="contract-paper">
    <div class="contract-header">
        <div class="contract-header-top">
            <!-- Assuming logo is at root -->
            <img src="../LOGO W.png" alt="Wise Quotient Soft" class="logo" onerror="this.src='../tech.png'">
            <div class="company-info">
                <strong>Wise Quotient Soft</strong><br>
                IT & Software Development Agency<br>
                www.wisequotientsoft.com<br>
                contact@wisequotientsoft.com<br>
                +234 (0) 800 000 0000
            </div>
        </div>

        <div class="contract-title">Project Development Agreement</div>
        
        <?php if ($c['status'] === 'pending_signature'): ?>
            <div style="text-align:center; color: #dc3545; font-weight:bold; border: 2px dashed #dc3545; padding: 10px; margin: 10px 0;">
                DRAFT - PENDING CLIENT SIGNATURE
            </div>
        <?php endif; ?>

        <div class="contract-meta-info">
            <div>Date of Agreement: <strong><?= $createdDate ?></strong></div>
            <div style="font-size:0.85rem; margin-top:0.3rem;">Ref Number: <strong>WQS-AGR-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></strong></div>
        </div>

        <?php if (!empty($c['passport_photo'])): ?>
            <div class="passport-box">
                <img src="<?= htmlspecialchars(strpos($c['passport_photo'], 'http') === 0 ? $c['passport_photo'] : '../' . $c['passport_photo']) ?>" alt="Client Passport">
            </div>
        <?php endif; ?>
    </div>

    <div class="contract-section">
        <div class="contract-text">
            This Agreement is made and entered into on this <strong><?= $createdDate ?></strong>, by and between:
        </div>
        <div style="margin-top: 1rem; padding-left: 2rem;">
            <strong>Wise Quotient Soft (WQS)</strong><br>
            (Hereinafter referred to as the "Developer" or "Company")
            <br><br>
            AND
            <br><br>
            <strong><?= htmlspecialchars($c['client_name']) ?></strong> 
            <?php if (!empty($c['client_org'])) echo "- " . htmlspecialchars($c['client_org']); ?><br>
            <?= htmlspecialchars($c['client_address']) ?><br>
            <?= htmlspecialchars($c['client_email']) ?> | <?= htmlspecialchars($c['client_phone']) ?><br>
            (Hereinafter referred to as the "Client")
        </div>
    </div>

    <div class="contract-section">
        <div class="contract-section-title">1. Project Details</div>
        <table class="info-table">
            <tr>
                <th>Project Title:</th>
                <td><?= htmlspecialchars($c['project_title']) ?></td>
            </tr>
            <tr>
                <th>Start Date:</th>
                <td><?= $startDate ?></td>
            </tr>
            <tr>
                <th>Estimated Completion:</th>
                <td><?= $endDate ?></td>
            </tr>
            <tr>
                <th>Total Consideration:</th>
                <td><strong>₦<?= $amountFormatted ?></strong></td>
            </tr>
        </table>
    </div>

    <div class="contract-section">
        <div class="contract-section-title">2. Scope of Work & Terms</div>
        <div class="contract-text"><?= htmlspecialchars($c['project_description']) ?></div>
    </div>

    <div class="contract-section">
        <div class="contract-section-title">3. General Conditions</div>
        <div class="contract-text">
a. <strong>Confidentiality:</strong> Both parties agree to keep all intellectual property, business operations, and sensitive data confidential.
b. <strong>Payment Terms:</strong> The agreed amount of ₦<?= $amountFormatted ?> shall be paid according to the milestone schedule provided separately or embedded within the Scope of Work. 
c. <strong>Termination:</strong> Either party may terminate this agreement with valid written notice in the event of a material breach.
d. <strong>Governing Law:</strong> This agreement shall be governed by and interpreted in accordance with applicable corporate IT laws.
        </div>
    </div>

    <div class="signature-block">
        <div style="margin-bottom: 2rem;">
            By signing below, the Parties acknowledge that they have read, understood, and agreed to be bound by the terms and conditions outlined in this Agreement.
        </div>
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2rem;">
            <div>
                <?php if (!empty($c['client_signature'])): ?>
                    <img src="<?= $c['client_signature'] ?>" alt="Client Signature" class="sig-img">
                <?php endif; ?>
                <div class="sig-line">
                    <strong>Client / Representative</strong><br>
                    <?= htmlspecialchars($c['client_name']) ?><br>
                    Date: __________________
                </div>
            </div>

            <?php if (!empty($c['witness_name']) || !empty($c['witness_signature'])): ?>
            <div>
                <?php if (!empty($c['witness_signature'])): ?>
                    <img src="<?= $c['witness_signature'] ?>" alt="Witness Signature" class="sig-img">
                <?php endif; ?>
                <div class="sig-line">
                    <strong>Witness</strong><br>
                    <?= htmlspecialchars($c['witness_name']) ?><br>
                    Date: __________________
                </div>
            </div>
            <?php endif; ?>

            <div>
                <?php if (!empty($c['admin_signature'])): ?>
                    <img src="<?= $c['admin_signature'] ?>" alt="Admin Signature" class="sig-img">
                <?php endif; ?>
                <div class="sig-line">
                    <strong>For: Wise Quotient Soft</strong><br>
                    Authorized Signatory<br>
                    Date: __________________
                </div>
            </div>
        </div>
    </div>
</div>

<br><br>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
