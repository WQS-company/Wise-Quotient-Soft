<?php
require_once 'config.php';

$id = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
$token = $_GET['token'] ?? '';

if (!$id || !$token) {
    die("Invalid signing link.");
}

$stmt = $db->prepare("SELECT * FROM contracts WHERE id = ? AND token = ?");
$stmt->bind_param("is", $id, $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Contract not found or invalid token.");
}

$contract = $res->fetch_assoc();

if ($contract['status'] !== 'pending_signature') {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'>
        <h2>This contract has already been signed.</h2>
        <p>Status: " . ucfirst($contract['status']) . "</p>
        </div>");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = $db->real_escape_string($_POST['client_name'] ?? '');
    $client_org = $db->real_escape_string($_POST['client_org'] ?? '');
    $client_email = $db->real_escape_string($_POST['client_email'] ?? '');
    $client_phone = $db->real_escape_string($_POST['client_phone'] ?? '');
    $client_address = $db->real_escape_string($_POST['client_address'] ?? '');
    $witness_name = $db->real_escape_string($_POST['witness_name'] ?? '');
    
    $client_signature = $db->real_escape_string($_POST['client_signature'] ?? '');
    $witness_signature = $db->real_escape_string($_POST['witness_signature'] ?? '');

    if (empty($client_name) || empty($client_signature)) {
        $error = "Client Name and Signature are required.";
    } else {
        // Handle Passport Upload
        $passport_photo = $contract['passport_photo'];
        if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/includes/cloudinary.php';
            $cloudUrl = uploadToCloudinary($_FILES['passport_photo']['tmp_name'], 'contracts', 'image');
            if ($cloudUrl) {
                $passport_photo = $cloudUrl;
            }
        }

        $updateSql = "UPDATE contracts SET 
            client_name = '$client_name', 
            client_org = '$client_org', 
            client_email = '$client_email', 
            client_phone = '$client_phone', 
            client_address = '$client_address', 
            witness_name = '$witness_name', 
            client_signature = '$client_signature', 
            witness_signature = '$witness_signature', 
            passport_photo = '$passport_photo',
            status = 'active' 
            WHERE id = $id AND token = '$token'";

        if ($db->query($updateSql)) {
            $success = "Contract successfully signed and submitted!";
            $contract['status'] = 'active'; // Update local variable so the form hides
        } else {
            $error = "Error saving signature: " . $db->error;
        }
    }
}

$amountFormatted = number_format($contract['contract_amount'], 2);
$startDate = date('F j, Y', strtotime($contract['start_date']));
$endDate = !empty($contract['end_date']) && $contract['end_date'] !== '0000-00-00' ? date('F j, Y', strtotime($contract['end_date'])) : 'To Be Determined';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Contract | Wise Quotient Soft</title>
    <!-- Use Bootstrap for simple layout -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .contract-container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-top: 5px solid #0d6efd;
        }
        .header-logo {
            height: 60px;
            margin-bottom: 20px;
        }
        .contract-details {
            background: #f1f5f9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #0d6efd;
        }
        .form-section {
            border: 1px solid #dee2e6;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .signature-pad {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            background: #fff;
            cursor: crosshair;
            touch-action: none;
            width: 100%;
            height: 200px;
        }
        .passport-upload-box {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            width: 150px;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: #f8f9fa;
            transition: border-color 0.3s;
        }
        .passport-upload-box:hover {
            border-color: #0d6efd;
        }
        .passport-upload-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            display: none;
        }
        .passport-upload-box .placeholder-text {
            color: #6c757d;
            font-size: 0.85rem;
            padding: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="contract-container">
        
        <div class="text-center mb-4">
            <img src="LOGO W.png" alt="WQS Logo" class="header-logo" onerror="this.src='tech.png'">
            <h2 class="fw-bold text-primary">Project Development Agreement</h2>
            <p class="text-muted">Please review the terms and provide your information and signature below.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success text-center py-4">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <h4 class="alert-heading">Thank You!</h4>
                <p class="mb-0"><?= $success ?></p>
            </div>
        <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
        <?php endif; ?>

        <div class="contract-details">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Contract Overview</h5>
            <div class="row">
                <div class="col-md-6 mb-2"><strong>Project Title:</strong> <?= htmlspecialchars($contract['project_title']) ?></div>
                <div class="col-md-6 mb-2"><strong>Total Amount:</strong> ₦<?= $amountFormatted ?></div>
                <div class="col-md-6 mb-2"><strong>Start Date:</strong> <?= $startDate ?></div>
                <div class="col-md-6 mb-2"><strong>End Date:</strong> <?= $endDate ?></div>
            </div>
            
            <h6 class="fw-bold mt-4 mb-2">Scope of Work & Terms</h6>
            <div class="bg-white p-3 border rounded" style="max-height: 250px; overflow-y: auto; white-space: pre-wrap; font-size: 0.95rem;">
<?= htmlspecialchars($contract['project_description']) ?>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="signForm">
            
            <div class="form-section">
                <h5 class="fw-bold text-primary mb-3"><i class="fas fa-user-tie me-2"></i> Your Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="client_name" value="<?= htmlspecialchars($contract['client_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Organization / Company</label>
                        <input type="text" class="form-control" name="client_org" value="<?= htmlspecialchars($contract['client_org']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control" name="client_email" value="<?= htmlspecialchars($contract['client_email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" class="form-control" name="client_phone" value="<?= htmlspecialchars($contract['client_phone']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Physical Address</label>
                        <textarea class="form-control" name="client_address" rows="2"><?= htmlspecialchars($contract['client_address']) ?></textarea>
                    </div>
                    <div class="col-12 mt-4">
                        <label class="form-label fw-semibold">Passport Photograph <span class="text-muted small">(Optional)</span></label>
                        <div class="passport-upload-box" onclick="document.getElementById('passport_input').click()">
                            <div class="placeholder-text" id="passport_placeholder">
                                <i class="fas fa-camera fa-2x mb-2 text-primary"></i><br>
                                Click to upload passport
                            </div>
                            <img id="passport_preview" src="" alt="Passport Preview">
                        </div>
                        <input type="file" id="passport_input" name="passport_photo" accept="image/*" style="display: none;" onchange="previewPassport(this)">
                    </div>
                </div>
            </div>

            <div class="form-section border-info bg-info bg-opacity-10">
                <h5 class="fw-bold text-info mb-3"><i class="fas fa-eye me-2"></i> Witness Information <span class="text-muted small">(Optional)</span></h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Witness Full Name</label>
                        <input type="text" class="form-control" name="witness_name" value="<?= htmlspecialchars($contract['witness_name']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="fw-bold text-primary mb-3"><i class="fas fa-pen-nib me-2"></i> Signatures</h5>
                
                <div class="row g-4">
                    <!-- Client Signature -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold d-block">Your Signature <span class="text-danger">*</span></label>
                        <ul class="nav nav-pills mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#client-draw" type="button" role="tab">Draw</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#client-upload" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="client-draw" role="tabpanel">
                                <canvas id="clientCanvas" class="signature-pad"></canvas>
                                <div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearCanvas(clientCanvas, clientCtx)"><i class="fas fa-eraser me-1"></i>Clear</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="client-upload" role="tabpanel">
                                <input type="file" class="form-control" accept="image/*" onchange="convertImageToBase64(this, 'client_signature_input')">
                                <div class="form-text text-muted">Upload a clear photo or scan of your signature.</div>
                            </div>
                        </div>
                        <input type="hidden" name="client_signature" id="client_signature_input">
                    </div>

                    <!-- Witness Signature -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold d-block text-info">Witness Signature</label>
                        <ul class="nav nav-pills mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#witness-draw" type="button" role="tab">Draw</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#witness-upload" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="witness-draw" role="tabpanel">
                                <canvas id="witnessCanvas" class="signature-pad" style="border-color:#0dcaf0;"></canvas>
                                <div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="clearCanvas(witnessCanvas, witnessCtx)"><i class="fas fa-eraser me-1"></i>Clear</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="witness-upload" role="tabpanel">
                                <input type="file" class="form-control" accept="image/*" onchange="convertImageToBase64(this, 'witness_signature_input')">
                            </div>
                        </div>
                        <input type="hidden" name="witness_signature" id="witness_signature_input">
                    </div>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill" onclick="saveSignatures()">
                    <i class="fas fa-check-circle me-2"></i> Submit Signed Contract
                </button>
            </div>
            
            <p class="text-center mt-3 small text-muted">By clicking submit, you agree to the terms and conditions outlined in the contract overview.</p>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function previewPassport(input) {
        const preview = document.getElementById('passport_preview');
        const placeholder = document.getElementById('passport_placeholder');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function setupCanvas(canvasId) {
        const canvas = document.getElementById(canvasId);
        if(!canvas) return {};
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        
        function resizeCanvas() {
            const rect = canvas.parentElement.getBoundingClientRect();
            // set real width instead of 100% css
            canvas.width = rect.width - 30; // padding adj
            canvas.height = 200;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000000';
        }
        window.addEventListener('resize', resizeCanvas);
        // timeout to allow tab rendering
        setTimeout(resizeCanvas, 100);

        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }

        function stopDrawing() {
            isDrawing = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            let x, y;
            if (e.type.includes('mouse')) {
                x = e.clientX - rect.left;
                y = e.clientY - rect.top;
            } else if (e.type.includes('touch')) {
                x = e.touches[0].clientX - rect.left;
                y = e.touches[0].clientY - rect.top;
            }
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('touchstart', startDrawing, {passive: false});
        canvas.addEventListener('touchmove', draw, {passive: false});
        canvas.addEventListener('touchend', stopDrawing);

        return { canvas, ctx };
    }

    const { canvas: clientCanvas, ctx: clientCtx } = setupCanvas('clientCanvas');
    const { canvas: witnessCanvas, ctx: witnessCtx } = setupCanvas('witnessCanvas');

    // Fix canvas sizing when tabs switch
    document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function(e) {
            window.dispatchEvent(new Event('resize'));
        });
    });

    function clearCanvas(canvas, ctx) {
        if(ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function isCanvasBlank(canvas) {
        if(!canvas) return true;
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        return canvas.toDataURL() === blank.toDataURL();
    }

    function saveSignatures() {
        if (clientCanvas && !isCanvasBlank(clientCanvas)) {
            // Only overwrite if the hidden input isn't already populated by an image upload
            if(!document.getElementById('client_signature_input').value.startsWith('data:image')) {
                document.getElementById('client_signature_input').value = clientCanvas.toDataURL('image/png');
            }
        }
        if (witnessCanvas && !isCanvasBlank(witnessCanvas)) {
            if(!document.getElementById('witness_signature_input').value.startsWith('data:image')) {
                document.getElementById('witness_signature_input').value = witnessCanvas.toDataURL('image/png');
            }
        }
    }

    function convertImageToBase64(input, targetInputId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(targetInputId).value = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            document.getElementById(targetInputId).value = "";
        }
    }
</script>

</body>
</html>
