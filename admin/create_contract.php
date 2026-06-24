<?php
$path_to_root = "../";
$page_title = "Create Contract Agreement";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
if ($user['role'] !== 'admin') {
    die("Unauthorized access.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = $_POST['client_name'] ?? '';
    $client_org = $_POST['client_org'] ?? '';
    $client_email = $_POST['client_email'] ?? '';
    $client_phone = $_POST['client_phone'] ?? '';
    $client_address = $_POST['client_address'] ?? '';
    $project_title = $_POST['project_title'] ?? '';
    $project_description = $_POST['project_description'] ?? '';
    $contract_amount = (float)($_POST['contract_amount'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $client_signature = $_POST['client_signature'] ?? '';
    $admin_signature = $_POST['admin_signature'] ?? '';
    $witness_name = $_POST['witness_name'] ?? '';
    $witness_signature = $_POST['witness_signature'] ?? '';

    // Handle File Uploads (Passport)
    $passport_photo = '';
    if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
        require_once dirname(__DIR__) . '/includes/cloudinary.php';
        $cloudUrl = uploadToCloudinary($_FILES['passport_photo']['tmp_name'], 'contracts', 'image');
        if ($cloudUrl) {
            $passport_photo = $cloudUrl;
        }
    }

    if (empty($project_title) || empty($start_date)) {
        $error = "Please fill in all required fields.";
    } else {
        $token = bin2hex(random_bytes(16));
        $status = empty($client_signature) ? 'pending_signature' : 'active';

        $stmt = $pdo->prepare("INSERT INTO contracts (
            client_name, client_org, client_email, client_phone, client_address, 
            project_title, project_description, contract_amount, start_date, end_date, 
            passport_photo, client_signature, admin_signature, witness_name, witness_signature,
            token, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([
            $client_name, $client_org, $client_email, $client_phone, $client_address,
            $project_title, $project_description, $contract_amount, $start_date, $end_date,
            $passport_photo, $client_signature, $admin_signature, $witness_name, $witness_signature,
            $token, $status
        ])) {
            $newId = $pdo->lastInsertId();
            echo "<script>window.location.href='view_contract.php?id=" . wqs_encrypt_id($newId) . "';</script>";
            exit;
        } else {
            $error = "Error saving contract.";
        }
    }
}
?>

<style>
    .signature-pad {
        border: 2px dashed var(--color-border);
        border-radius: 8px;
        background: var(--color-bg);
        cursor: crosshair;
        touch-action: none; /* Prevent scrolling while drawing */
        width: 100%;
        max-width: 500px;
        height: 200px;
    }
    .passport-upload-box {
        border: 2px dashed var(--color-border);
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
        background: var(--color-bg);
        transition: border-color 0.3s;
    }
    .passport-upload-box:hover {
        border-color: var(--color-primary);
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
        color: var(--color-text-muted);
        font-size: 0.85rem;
        padding: 10px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-body-emphasis mb-0"><i class="fas fa-file-signature me-2 text-primary"></i> Create Agreement</h4>
    <a href="contract_hub.php" class="btn btn-outline-secondary fw-bold rounded-pill px-4">
        <i class="fas fa-arrow-left me-1"></i> Back to Hub
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
<?php endif; ?>

<div class="card-theme">
    <div class="card-theme-body p-4">
        <form method="POST" enctype="multipart/form-data" id="contractForm">
            <!-- Part 1: Client Info -->
            <h5 class="fw-bold text-primary mb-3"><i class="fas fa-user-tie me-2"></i> Client Information</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Client Full Name <span class="text-muted small">(Optional)</span></label>
                    <input type="text" class="form-control form-control-theme" name="client_name">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Organization / Company Name</label>
                    <input type="text" class="form-control form-control-theme" name="client_org" placeholder="Optional">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control form-control-theme" name="client_email">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" class="form-control form-control-theme" name="client_phone">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Physical Address</label>
                    <textarea class="form-control form-control-theme" name="client_address" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Passport Photograph <span class="text-muted small">(Optional but recommended)</span></label>
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

            <hr class="my-4" style="border-color: var(--color-border);">

            <!-- Part 2: Project Details -->
            <h5 class="fw-bold text-primary mb-3"><i class="fas fa-briefcase me-2"></i> Project & Terms</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Project Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-theme" name="project_title" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Contract Amount (₦) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control form-control-theme" name="contract_amount" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-theme" name="start_date" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">End Date (Estimated)</label>
                    <input type="date" class="form-control form-control-theme" name="end_date">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Detailed Scope of Work & Terms <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-theme" name="project_description" rows="6" required placeholder="Enter the full contract terms, deliverables, and clauses here..."></textarea>
                </div>
            </div>

            <hr class="my-4" style="border-color: var(--color-border);">

            <!-- Part 3: Witness Details -->
            <div class="p-4 mb-4 rounded border border-info bg-info bg-opacity-10">
                <h5 class="fw-bold text-info mb-3"><i class="fas fa-eye me-2"></i> Witness Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Witness Full Name <span class="text-muted small">(Optional)</span></label>
                        <input type="text" class="form-control form-control-theme" name="witness_name">
                    </div>
                </div>
            </div>

            <hr class="my-4" style="border-color: var(--color-border);">

            <!-- Part 4: Signatures -->
            <h5 class="fw-bold text-primary mb-3"><i class="fas fa-pen-nib me-2"></i> Signatures</h5>
            <div class="row g-4 mb-4">
                
                <!-- Client Signature Block -->
                <div class="col-md-6">
                    <div class="p-3 border rounded">
                        <label class="form-label fw-bold text-body-emphasis mb-2 d-block">Client Signature</label>
                        <ul class="nav nav-pills mb-3" id="clientSigTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#client-draw" type="button" role="tab">Draw</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#client-upload" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="clientSigContent">
                            <div class="tab-pane fade show active" id="client-draw" role="tabpanel">
                                <canvas id="clientCanvas" class="signature-pad"></canvas>
                                <div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearCanvas(clientCanvas, clientCtx)"><i class="fas fa-eraser me-1"></i>Clear</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="client-upload" role="tabpanel">
                                <input type="file" class="form-control form-control-theme" id="client_sig_file" accept="image/*" onchange="convertImageToBase64(this, 'client_signature_input')">
                                <div class="form-text text-muted">Upload a clear photo or scan of the signature.</div>
                            </div>
                        </div>
                        <input type="hidden" name="client_signature" id="client_signature_input">
                    </div>
                </div>
                
                <!-- Admin Signature Block -->
                <div class="col-md-6">
                    <div class="p-3 border rounded">
                        <label class="form-label fw-bold text-body-emphasis mb-2 d-block">WQS Admin Signature</label>
                        <ul class="nav nav-pills mb-3" id="adminSigTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#admin-draw" type="button" role="tab">Draw</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#admin-upload" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="adminSigContent">
                            <div class="tab-pane fade show active" id="admin-draw" role="tabpanel">
                                <canvas id="adminCanvas" class="signature-pad"></canvas>
                                <div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearCanvas(adminCanvas, adminCtx)"><i class="fas fa-eraser me-1"></i>Clear</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="admin-upload" role="tabpanel">
                                <input type="file" class="form-control form-control-theme" id="admin_sig_file" accept="image/*" onchange="convertImageToBase64(this, 'admin_signature_input')">
                            </div>
                        </div>
                        <input type="hidden" name="admin_signature" id="admin_signature_input">
                    </div>
                </div>
                
                <!-- Witness Signature Block -->
                <div class="col-md-6">
                    <div class="p-3 border rounded border-info bg-info bg-opacity-10">
                        <label class="form-label fw-bold text-info mb-2 d-block">Witness Signature</label>
                        <ul class="nav nav-pills mb-3" id="witnessSigTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#witness-draw" type="button" role="tab">Draw</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#witness-upload" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="witnessSigContent">
                            <div class="tab-pane fade show active" id="witness-draw" role="tabpanel">
                                <canvas id="witnessCanvas" class="signature-pad" style="background:#fff; border-color:#0dcaf0;"></canvas>
                                <div class="mt-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="clearCanvas(witnessCanvas, witnessCtx)"><i class="fas fa-eraser me-1"></i>Clear</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="witness-upload" role="tabpanel">
                                <input type="file" class="form-control form-control-theme" id="witness_sig_file" accept="image/*" onchange="convertImageToBase64(this, 'witness_signature_input')">
                            </div>
                        </div>
                        <input type="hidden" name="witness_signature" id="witness_signature_input">
                    </div>
                </div>
            </div>

            <div class="d-grid mt-5">
                <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill" onclick="saveSignatures()">
                    <i class="fas fa-save me-2"></i> Generate & Save Contract
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- Passport Preview Logic ---
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

    // --- Signature Pad Logic ---
    function setupCanvas(canvasId) {
        const canvas = document.getElementById(canvasId);
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        
        // Adjust canvas resolution to match display size for crisp lines
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = rect.height;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000000'; // Always black ink
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

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
    const { canvas: adminCanvas, ctx: adminCtx } = setupCanvas('adminCanvas');
    const { canvas: witnessCanvas, ctx: witnessCtx } = setupCanvas('witnessCanvas');

    function clearCanvas(canvas, ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function isCanvasBlank(canvas) {
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        return canvas.toDataURL() === blank.toDataURL();
    }

    function saveSignatures() {
        if (!isCanvasBlank(clientCanvas)) {
            document.getElementById('client_signature_input').value = clientCanvas.toDataURL('image/png');
        }
        if (!isCanvasBlank(adminCanvas)) {
            document.getElementById('admin_signature_input').value = adminCanvas.toDataURL('image/png');
        }
        if (!isCanvasBlank(witnessCanvas)) {
            document.getElementById('witness_signature_input').value = witnessCanvas.toDataURL('image/png');
        }
    }

    // --- Image to Base64 Logic ---
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

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
