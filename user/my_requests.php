<?php 
/*
CREATE TABLE `client_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `categories` varchar(255) DEFAULT NULL,
  `software_type` varchar(50) NOT NULL,
  `features` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `status` enum('pending','reviewed','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `client_request_files` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('image','video') NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
*/
session_start();
if (isset($_GET['action']) || isset($_POST['action'])) {
    ini_set('display_errors', '0');
}
if (!isset($_SESSION['user']['id'])) {
    // If AJAX request, return json; otherwise redirect to login
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    } else {
        header("Location: login.php");
        exit;
    }
}

require_once dirname(__DIR__) . '/config.php';

$user_id = intval($_SESSION['user']['id']);

// Helper: sanitize output
function e($v) {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Where AJAX requests are handled
$action = $_REQUEST['action'] ?? '';

if ($action === 'create') {
    // Create new request
    header('Content-Type: application/json');

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $software_type = trim($_POST['software_type'] ?? '');
    // features: allow newline separated from textarea; store raw
    $features = isset($_POST['features']) ? trim($_POST['features']) : '';
    $recommendations = isset($_POST['recommendations']) ? trim($_POST['recommendations']) : '';

    if ($title === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'Title and Description are required.']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO client_requests (user_id, title, description, categories, software_type, features, recommendations) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
        exit;
    }
    $stmt->bind_param("issssss", $user_id, $title, $description, $category, $software_type, $features, $recommendations);
    if ($stmt->execute()) {
        $request_id = $stmt->insert_id;

        // Handle file uploads
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = __DIR__ . "/uploads/projects/";
            require_once dirname(__DIR__) . '/includes/cloudinary.php';
            foreach ($_FILES['files']['name'] as $key => $filename) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                if (!is_uploaded_file($tmpName)) continue;
                $fileType = mime_content_type($tmpName);
                $type = (strpos($fileType, 'video') !== false) ? 'video' : ((strpos($fileType, 'image') !== false) ? 'image' : 'raw');

                $cloudUrl = uploadToCloudinary($tmpName, 'project_requests', $type === 'image' || $type === 'video' ? $type : 'auto');

                if ($cloudUrl) {
                    $ins = $db->prepare("INSERT INTO client_request_files (request_id, file_path, file_type) VALUES (?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param("iss", $request_id, $cloudUrl, $type);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Project request submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request. ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'fetch') {
    // Return HTML card grid for current user's requests
    // We'll directly output HTML snippet to replace container
    $sql = "SELECT * FROM client_requests WHERE user_id = ? ORDER BY created_at DESC";
    $s = $db->prepare($sql);
    $s->bind_param("i", $user_id);
    $s->execute();
    $requests = $s->get_result();

    if ($requests->num_rows > 0) {
        ob_start();
        echo '<div class="row g-4">';
        while ($req = $requests->fetch_assoc()) {
            // fetch files
            $fileStmt = $db->prepare("SELECT * FROM client_request_files WHERE request_id = ?");
            $fileStmt->bind_param("i", $req['id']);
            $fileStmt->execute();
            $files = $fileStmt->get_result();
            ?>
            <div class="col-12 col-lg-8">
              <div class="req-card">
                <div class="req-card-inner">
                  <!-- LEFT: Title + Status + Description -->
                  <div class="req-col req-col-main">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="req-title"><?= e($req['title']) ?></span>
                      <?php
                      $status = strtolower($req['status']);
                      $statusColors = [
                        'pending'   => ['bg'=>'#fef3c7','text'=>'#92400e','dot'=>'#f59e0b'],
                        'reviewed'  => ['bg'=>'#dbeafe','text'=>'#1e40af','dot'=>'#3b82f6'],
                        'approved'  => ['bg'=>'#dcfce7','text'=>'#166534','dot'=>'#22c55e'],
                        'rejected'  => ['bg'=>'#fee2e2','text'=>'#991b1b','dot'=>'#ef4444'],
                      ];
                      $sc = $statusColors[$status] ?? ['bg'=>'#f1f5f9','text'=>'#475569','dot'=>'#94a3b8'];
                      ?>
                      <span class="req-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>;">
                        <span class="req-dot" style="background:<?= $sc['dot'] ?>;"></span>
                        <?= e(ucfirst($req['status'])) ?>
                      </span>
                      <?php if (intval($req['cancel_requested']) === 1): ?>
                        <span class="req-badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">
                          <span class="req-dot" style="background:#ef4444;animation:pulse-dot 1.5s ease-in-out infinite;"></span>
                          Cancel Pending
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="req-desc" id="desc-<?= intval($req['id']) ?>">
                      <?= nl2br(e($req['description'])) ?>
                    </div>
                    <?php if (strlen($req['description']) > 120): ?>
                      <button class="req-toggle-desc" onclick="toggleDesc(<?= intval($req['id']) ?>)" id="toggle-<?= intval($req['id']) ?>">View More</button>
                    <?php endif; ?>
                    <?php if ($req['categories'] || $req['software_type']): ?>
                      <div class="req-tags mt-2">
                        <?php if ($req['categories']): ?><span class="req-tag"><?= e($req['categories']) ?></span><?php endif; ?>
                        <?php if ($req['software_type']): ?><span class="req-tag"><?= e($req['software_type']) ?></span><?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- MIDDLE: Budget + Timeline + Date -->
                  <div class="req-col req-col-meta">
                    <div class="req-meta-item">
                      <span class="req-meta-label">Budget</span>
                      <span class="req-meta-value">₦<?= number_format((float)($req['budget'] ?? 0), 2) ?></span>
                    </div>
                    <div class="req-meta-item">
                      <span class="req-meta-label">Timeline</span>
                      <span class="req-meta-value"><?= e($req['timeline'] ?? '—') ?></span>
                    </div>
                    <div class="req-meta-item">
                      <span class="req-meta-label">Submitted</span>
                      <span class="req-meta-value"><?= date("d M Y", strtotime($req['created_at'])) ?></span>
                    </div>
                  </div>

                    <!-- RIGHT: Actions -->
                  <div class="req-col req-col-actions">
                    <button class="req-btn req-btn-view" onclick="viewRequest(<?= intval($req['id']) ?>)" title="View Details">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <?php if (intval($req['cancel_requested']) === 1): ?>
                      <div class="d-flex flex-column gap-1">
                        <button class="req-btn" disabled title="Cancellation Pending Approval" style="opacity: 0.85; cursor: not-allowed; background: rgba(239, 68, 68, 0.08); color: #dc2626; border-color: rgba(239, 68, 68, 0.2); font-size: 0.72rem;">
                          <i class="fas fa-hourglass-half"></i> Cancel Pending
                        </button>
                        <?php if (!empty($req['cancel_reason'])): ?>
                          <small class="text-muted px-1" style="font-size:0.65rem; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="<?= e($req['cancel_reason']) ?>">
                            <i class="fas fa-quote-left me-1" style="font-size:0.55rem;opacity:0.5;"></i><?= e($req['cancel_reason']) ?>
                          </small>
                        <?php endif; ?>
                      </div>
                    <?php elseif (intval($req['suspend_requested']) === 1): ?>
                      <div class="d-flex flex-column gap-1">
                        <button class="req-btn" disabled title="Suspension Pending Approval" style="opacity: 0.85; cursor: not-allowed; background: rgba(245, 158, 11, 0.08); color: #d97706; border-color: rgba(245, 158, 11, 0.2); font-size: 0.72rem;">
                          <i class="fas fa-pause-circle"></i> Suspend Pending
                        </button>
                        <?php if (!empty($req['suspend_reason'])): ?>
                          <small class="text-muted px-1" style="font-size:0.65rem; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="<?= e($req['suspend_reason']) ?>">
                            <i class="fas fa-quote-left me-1" style="font-size:0.55rem;opacity:0.5;"></i><?= e($req['suspend_reason']) ?>
                          </small>
                        <?php endif; ?>
                        <?php if (!empty($req['suspend_start_date']) && !empty($req['suspend_end_date'])): ?>
                          <small class="text-muted px-1" style="font-size:0.6rem;">
                            <i class="fas fa-calendar-alt me-1" style="font-size:0.5rem;opacity:0.5;"></i><?= date("d M", strtotime($req['suspend_start_date'])) ?> — <?= date("d M Y", strtotime($req['suspend_end_date'])) ?>
                          </small>
                        <?php endif; ?>
                      </div>
                    <?php elseif ($status === 'completed'): ?>
                      <span class="req-btn" style="opacity: 0.6; cursor: default; background: rgba(16, 185, 129, 0.08); color: #059669; border-color: rgba(16, 185, 129, 0.2); font-size: 0.72rem;">
                        <i class="fas fa-check-circle"></i> Completed
                      </span>
                    <?php elseif ($status === 'approved'): ?>
                      <button class="req-btn req-btn-edit btn-edit" data-id="<?= intval($req['id']) ?>" title="Edit Request">
                        <i class="fas fa-pen"></i> Edit
                      </button>
                      <button class="req-btn btn-suspend" data-id="<?= intval($req['id']) ?>" data-title="<?= e($req['title']) ?>" title="Request Suspension" style="background: rgba(245, 158, 11, 0.08); color: #d97706; border-color: rgba(245, 158, 11, 0.2);">
                        <i class="fas fa-pause-circle"></i> Suspend
                      </button>
                      <button class="req-btn req-btn-delete btn-delete" data-id="<?= intval($req['id']) ?>" data-status="<?= e($req['status']) ?>" title="Cancel Request">
                        <i class="fas fa-times"></i> Cancel
                      </button>
                    <?php else: ?>
                      <button class="req-btn req-btn-edit btn-edit" data-id="<?= intval($req['id']) ?>" title="Edit Request">
                        <i class="fas fa-pen"></i> Edit
                      </button>
                      <button class="req-btn req-btn-delete btn-delete" data-id="<?= intval($req['id']) ?>" data-status="<?= e($req['status']) ?>" title="Cancel Request">
                        <i class="fas fa-times"></i> Cancel
                      </button>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if ($files->num_rows > 0): ?>
                <div class="req-files-bar">
                  <?php while ($f = $files->fetch_assoc()): ?>
                    <?php if ($f['file_type'] === 'image'): ?>
                      <img src="<?= e($f['file_path']) ?>" alt="File" class="req-file-thumb zoomable" data-file-id="<?= intval($f['id']) ?>" />
                    <?php else: ?>
                      <div class="req-file-thumb req-file-video" onclick="window.open('<?= e($f['file_path']) ?>','_blank')">
                        <i class="fas fa-play"></i>
                      </div>
                    <?php endif; ?>
                  <?php endwhile; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php
            $fileStmt->close();
        }
        echo '</div>';
        $html = ob_get_clean();
        echo $html;
    } else {
        echo '<div class="text-center py-5" style="background:var(--color-bg,#fff);border:2px dashed var(--color-border,#e5e7eb);border-radius:16px;">
                <i class="fas fa-inbox fa-3x mb-3" style="color:#cbd5e1;"></i>
                <h5 class="fw-bold text-body mb-1">No project requests yet</h5>
                <p class="text-muted small mb-3">Submit your first project request to get started!</p>
                <a href="client-request.php" class="btn btn-theme"><i class="fas fa-plus-circle me-1"></i> New Request</a>
              </div>';
    }
    $s->close();
    exit;
}

if ($action === 'get_request') {
    // return a single request's data as JSON for edit form
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM client_requests WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    $req = $res->fetch_assoc();
    
    // Clean up double-escaped strings for JSON output
    $req['title'] = stripcslashes($req['title']);
    $req['description'] = stripcslashes($req['description']);
    $req['categories'] = stripcslashes($req['categories']);
    $req['software_type'] = stripcslashes($req['software_type']);
    $req['features'] = stripcslashes($req['features']);
    $req['recommendations'] = stripcslashes($req['recommendations']);

    $fileStmt = $db->prepare("SELECT * FROM client_request_files WHERE request_id = ?");
    $fileStmt->bind_param("i", $id);
    $fileStmt->execute();
    $fileRes = $fileStmt->get_result();
    $files = [];
    while ($f = $fileRes->fetch_assoc()) $files[] = $f;

    echo json_encode(['success' => true, 'data' => $req, 'files' => $files]);
    exit;
}

if ($action === 'update') {
    // Update a request
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);

    // Verify ownership
    $check = $db->prepare("SELECT id FROM client_requests WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $id, $user_id);
    $check->execute();
    $r = $check->get_result();
    if ($r->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or permission denied.']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $software_type = trim($_POST['software_type'] ?? '');
    $features = isset($_POST['features']) ? trim($_POST['features']) : '';
    $recommendations = isset($_POST['recommendations']) ? trim($_POST['recommendations']) : '';

    if ($title === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'Title and Description are required.']);
        exit;
    }

    $upd = $db->prepare("UPDATE client_requests SET title = ?, description = ?, categories = ?, software_type = ?, features = ?, recommendations = ? WHERE id = ? AND user_id = ?");
    $upd->bind_param("ssssssii", $title, $description, $category, $software_type, $features, $recommendations, $id, $user_id);
    if ($upd->execute()) {
        // New file uploads (append)
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = __DIR__ . "/uploads/projects/";
            require_once dirname(__DIR__) . '/includes/cloudinary.php';
            foreach ($_FILES['files']['name'] as $key => $filename) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                if (!is_uploaded_file($tmpName)) continue;
                $fileType = mime_content_type($tmpName);
                $type = (strpos($fileType, 'video') !== false) ? 'video' : ((strpos($fileType, 'image') !== false) ? 'image' : 'raw');

                $cloudUrl = uploadToCloudinary($tmpName, 'project_requests', $type === 'image' || $type === 'video' ? $type : 'auto');

                if ($cloudUrl) {
                    $ins = $db->prepare("INSERT INTO client_request_files (request_id, file_path, file_type) VALUES (?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param("iss", $id, $cloudUrl, $type);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Project request updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $upd->error]);
    }
    $upd->close();
    exit;
}

if ($action === 'delete') {
    // Delete request and its files
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);

    // Verify ownership
    $check = $db->prepare("SELECT id FROM client_requests WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $id, $user_id);
    $check->execute();
    $r = $check->get_result();
    if ($r->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or permission denied.']);
        exit;
    }

    // Delete files from filesystem and table
    $fileStmt = $db->prepare("SELECT * FROM client_request_files WHERE request_id = ?");
    $fileStmt->bind_param("i", $id);
    $fileStmt->execute();
    $fr = $fileStmt->get_result();
    while ($f = $fr->fetch_assoc()) {
        $path = __DIR__ . "/" . $f['file_path'];
        if (file_exists($path)) @unlink($path);
    }
    $fileStmt->close();

    $delFiles = $db->prepare("DELETE FROM client_request_files WHERE request_id = ?");
    $delFiles->bind_param("i", $id);
    $delFiles->execute();
    $delFiles->close();

    $del = $db->prepare("DELETE FROM client_requests WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $id, $user_id);
    if ($del->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project request deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete request.']);
    }
    $del->close();
    exit;
}

if ($action === 'delete_file') {
    // Delete a single file by id (AJAX)
    header('Content-Type: application/json');
    $file_id = intval($_POST['file_id'] ?? 0);

    $stmt = $db->prepare("SELECT cr.user_id, cr.id as reqid, f.file_path FROM client_request_files f JOIN client_requests cr ON f.request_id = cr.id WHERE f.id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit;
    }
    $row = $res->fetch_assoc();
    if (intval($row['user_id']) !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    $path = __DIR__ . "/" . $row['file_path'];
    if (file_exists($path)) @unlink($path);
    $del = $db->prepare("DELETE FROM client_request_files WHERE id = ?");
    $del->bind_param("i", $file_id);
    if ($del->execute()) {
        echo json_encode(['success' => true, 'message' => 'File deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete file.']);
    }
    exit;
}

if ($action === 'request_cancel') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    $reason = trim($_POST['cancel_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'Valid ID and cancellation reason are required.']);
        exit;
    }

    // Verify ownership and status is approved
    $check = $db->prepare("SELECT id, status, title FROM client_requests WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $id, $user_id);
    $check->execute();
    $r = $check->get_result();
    if ($r->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or permission denied.']);
        exit;
    }
    $reqData = $r->fetch_assoc();
    if ($reqData['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved projects require administrative cancellation review.']);
        exit;
    }

    // Update cancel_requested and cancel_reason
    $upd = $db->prepare("UPDATE client_requests SET cancel_requested = 1, cancel_reason = ? WHERE id = ?");
    $upd->bind_param("si", $reason, $id);
    if ($upd->execute()) {
        // Notify admin
        if (function_exists('add_notification_to_admins')) {
            add_notification_to_admins(
                "🚨 Project Cancellation Request",
                "Client has requested cancellation for approved project '{$reqData['title']}' with reason: {$reason}",
                "project",
                "../admin/client_requests.php",
                $id
            );
        }
        echo json_encode(['success' => true, 'message' => 'Cancellation request submitted to administrator successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit cancellation request: ' . $db->error]);
    }
    $upd->close();
    exit;
}

if ($action === 'request_suspend') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    $reason = trim($_POST['suspend_reason'] ?? '');
    $startDate = trim($_POST['suspend_start_date'] ?? '');
    $endDate = trim($_POST['suspend_end_date'] ?? '');

    if ($id <= 0 || $reason === '' || $startDate === '' || $endDate === '') {
        echo json_encode(['success' => false, 'message' => 'Valid ID, reason, start date, and resume date are required.']);
        exit;
    }

    if (strtotime($endDate) <= strtotime($startDate)) {
        echo json_encode(['success' => false, 'message' => 'Resume date must be after the start date.']);
        exit;
    }

    // Verify ownership and status is approved
    $check = $db->prepare("SELECT id, status, title, cancel_requested, suspend_requested FROM client_requests WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $id, $user_id);
    $check->execute();
    $r = $check->get_result();
    if ($r->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or permission denied.']);
        exit;
    }
    $reqData = $r->fetch_assoc();
    if ($reqData['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved projects can be suspended.']);
        exit;
    }
    if ($reqData['cancel_requested']) {
        echo json_encode(['success' => false, 'message' => 'A cancellation request is already pending for this project.']);
        exit;
    }
    if ($reqData['suspend_requested']) {
        echo json_encode(['success' => false, 'message' => 'A suspension request is already pending for this project.']);
        exit;
    }

    // Update suspend fields
    $upd = $db->prepare("UPDATE client_requests SET suspend_requested = 1, suspend_reason = ?, suspend_start_date = ?, suspend_end_date = ? WHERE id = ?");
    $upd->bind_param("sssi", $reason, $startDate, $endDate, $id);
    if ($upd->execute()) {
        // Notify admin
        if (function_exists('add_notification_to_admins')) {
            add_notification_to_admins(
                "⏸️ Project Suspension Request",
                "Client has requested suspension for approved project '{$reqData['title']}' from {$startDate} to {$endDate}. Reason: {$reason}",
                "project",
                "../admin/client_requests.php",
                $id
            );
        }
        echo json_encode(['success' => true, 'message' => 'Suspension request submitted to administrator successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit suspension request: ' . $db->error]);
    }
    $upd->close();
    exit;
}

// If no action: render the HTML page (initial load)
?>
<?php
$path_to_root = "../";
$page_title = "Project Proposals";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<div class="row mb-4">
  <div class="col-12 d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold text-body mb-1">Project Proposals</h4>
      <p class="text-muted small mb-0">View, update, or cancel your submitted project proposals.</p>
    </div>
    <a href="client-request.php" class="btn btn-theme rounded-pill px-4"><i class="fas fa-plus-circle me-1"></i> New Request</a>
  </div>
</div>
<style>
  /* ===== REQUEST CARD DESIGN ===== */
  .req-card {
    background: var(--color-bg, #fff);
    border: 1.5px solid var(--color-border, #e5e7eb);
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.25s ease;
    margin-bottom: 1rem;
  }
  .req-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
    transform: translateY(-2px);
  }
  .req-card-inner {
    display: grid;
    grid-template-columns: 1fr 200px 140px;
    gap: 0;
    align-items: stretch;
  }
  .req-col {
    padding: 1.25rem 1.5rem;
    border-right: 1px solid var(--color-border, #e5e7eb);
  }
  .req-col:last-child { border-right: none; }
  .req-col-main { min-width: 0; }
  .req-col-meta {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.6rem;
  }
  .req-col-actions {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: stretch;
    gap: 0.4rem;
    padding: 1rem;
  }
  .req-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--color-text-body, #111827);
    line-height: 1.3;
  }
  .req-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .req-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .req-desc {
    font-size: 0.85rem;
    color: var(--color-text-light, #6b7280);
    line-height: 1.55;
    margin-top: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: all 0.3s ease;
  }
  .req-desc.expanded {
    -webkit-line-clamp: unset;
    display: block;
  }
  .req-toggle-desc {
    background: none; border: none;
    color: #3b82f6; font-size: 0.78rem;
    font-weight: 600; cursor: pointer;
    padding: 2px 0; margin-top: 4px;
    transition: color 0.2s;
  }
  .req-toggle-desc:hover { color: #1d4ed8; text-decoration: underline; }
  .req-tags {
    display: flex; flex-wrap: wrap; gap: 6px;
  }
  .req-tag {
    display: inline-block;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
  }
  .req-meta-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
  }
  .req-meta-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-light, #9ca3af);
  }
  .req-meta-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-body, #111827);
  }
  .req-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1.5px solid transparent;
  }
  .req-btn-view {
    background: #eff6ff; color: #2563eb; border-color: #bfdbfe;
  }
  .req-btn-view:hover { background: #dbeafe; border-color: #93c5fd; }
  .req-btn-edit {
    background: #fffbeb; color: #92400e; border-color: #fde68a;
  }
  .req-btn-edit:hover { background: #fef3c7; border-color: #fcd34d; }
  .req-btn-delete {
    background: #fef2f2; color: #991b1b; border-color: #fecaca;
  }
  .req-btn-delete:hover { background: #fee2e2; border-color: #fca5a5; }
  .req-files-bar {
    display: flex;
    gap: 8px;
    padding: 0.75rem 1.5rem;
    background: var(--color-bg-secondary, #f9fafb);
    border-top: 1px solid var(--color-border, #e5e7eb);
    overflow-x: auto;
  }
  .req-file-thumb {
    width: 48px; height: 48px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid var(--color-border, #e5e7eb);
    cursor: zoom-in;
    transition: transform 0.2s;
    flex-shrink: 0;
  }
  .req-file-thumb:hover { transform: scale(1.08); }
  .req-file-video {
    display: flex; align-items: center; justify-content: center;
    background: #1e293b; color: white; font-size: 0.7rem; cursor: pointer;
  }

  /* Responsive: Tablet */
  @media (max-width: 991.98px) {
    .req-card-inner {
      grid-template-columns: 1fr 160px;
    }
    .req-col-actions {
      grid-column: 1 / -1;
      flex-direction: row;
      justify-content: flex-end;
      gap: 0.5rem;
      border-top: 1px solid var(--color-border, #e5e7eb);
      border-right: none;
      padding: 0.75rem 1.25rem;
    }
  }

  /* Responsive: Mobile */
  @media (max-width: 575.98px) {
    .req-card-inner {
      grid-template-columns: 1fr;
    }
    .req-col {
      border-right: none;
      border-bottom: 1px solid var(--color-border, #e5e7eb);
      padding: 1rem 1.25rem;
    }
    .req-col:last-child { border-bottom: none; }
    .req-col-meta {
      flex-direction: row;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .req-col-actions {
      flex-direction: row;
      flex-wrap: wrap;
      gap: 0.5rem;
      padding: 1rem 1.25rem;
    }
    .req-btn { flex: 1; min-width: 0; }
  }

  /* Zoom Modal */
  .zoom-modal {
    display: none; position: fixed; z-index: 2000;
    left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.9);
    justify-content: center; align-items: center;
  }
  .zoom-modal img {
    max-width: 90%; max-height: 90%;
    border-radius: 10px; box-shadow: 0 0 20px rgba(255,255,255,0.2);
    cursor: zoom-out; transition: transform 0.3s ease;
  }
  .zoom-modal.show { display: flex; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
  }

  /* Modal */
  .modal-dialog { max-width: 920px; }
  .modal-content { border-radius: 14px; overflow: hidden; }
  .modal-body { max-height: calc(100vh - 220px); overflow: auto; padding-bottom: 1rem; }
  .modal-footer {
    position: sticky; bottom: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.00), rgba(255,255,255,0.98));
    backdrop-filter: blur(2px); z-index: 1150;
    padding-top: 12px; padding-bottom: 12px;
    display: flex; gap: 8px; align-items: center; justify-content: flex-end;
  }
  .modal-footer .btn { min-width: 120px; }
  @media (max-width: 575.98px) {
    .modal-dialog { margin: 0 12px; max-width: calc(100% - 24px); }
    .modal-footer { flex-direction: column-reverse; align-items: stretch; gap: 10px; padding-left: 12px; padding-right: 12px; }
    .modal-footer .btn { width: 100%; }
  }

  /* Toast */
  #toastContainer { position: fixed; top: 90px; right: 20px; z-index: 3000; }

  .file-upload-preview { display: flex; flex-wrap: wrap; margin-top: 8px; gap: 8px; }
  .file-upload-preview .preview-item { border: 1px dashed #ddd; padding: 6px; border-radius: 6px; font-size: 12px; }

  /* Premium Form & Modal Overrides */
  .form-label-theme {
    font-weight: 600;
    color: var(--color-text-dark, #0f172a);
    margin-bottom: 0.4rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
  }
  .form-control-theme {
    border: 1.5px solid var(--color-border, #e2e8f0) !important;
    border-radius: 10px !important;
    padding: 0.7rem 0.9rem !important;
    font-size: 0.9rem !important;
    background-color: var(--color-bg, #f8fafc) !important;
    color: var(--color-text-dark, #0f172a) !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
  }
  .form-control-theme:focus {
    border-color: var(--color-primary, #0A2D5E) !important;
    background-color: var(--color-card-bg, #ffffff) !important;
    box-shadow: 0 0 0 4px var(--color-primary-light, rgba(10, 45, 94, 0.1)) !important;
    outline: none !important;
  }
  .upload-zone {
    border: 2px dashed var(--color-border, #e2e8f0);
    background-color: var(--color-bg, #f8fafc);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .upload-zone:hover {
    border-color: var(--color-primary, #0A2D5E);
    background-color: var(--color-card-bg, #ffffff);
  }
  .modal-footer .btn-theme {
    background-color: var(--color-accent, #E15501);
    color: white;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    padding: 0.625rem 1.5rem;
    font-size: 0.88rem;
    transition: all 0.2s ease;
  }
  .modal-footer .btn-theme:hover {
    background-color: var(--color-accent-hover, #c94a00);
    transform: translateY(-1px);
  }
  .modal-footer .btn-theme-secondary {
    background-color: #64748b;
    color: white;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    padding: 0.625rem 1.5rem;
    font-size: 0.88rem;
    transition: all 0.2s ease;
  }
  .modal-footer .btn-theme-secondary:hover {
    background-color: #475569;
    transform: translateY(-1px);
  }
</style>

<!-- Main Requests Grid Container -->
<div class="container-fluid px-0 py-3">
  <div id="requestsContainer">
    <!-- Cards will load dynamically via AJAX fetch -->
  </div>
</div>

<!-- Image Zoom Modal -->
<div id="zoomModal" class="zoom-modal">
  <img id="zoomImage" src="" alt="Zoomed Image" />
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <h5 class="modal-title fw-bold text-body" id="viewRequestModalLabel">Project Proposal Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-3" style="max-height: calc(100vh - 220px); overflow-y: auto;">
        <!-- Filled dynamically by viewRequest() -->
      </div>
      <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2 justify-content-end">
        <!-- Filled dynamically -->
      </div>
    </div>
  </div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <form id="requestForm" enctype="multipart/form-data">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <h5 class="modal-title fw-bold text-body" id="requestModalLabel">Edit Project Scope</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body px-4 py-3" style="max-height: calc(100vh - 220px); overflow-y: auto;">
          <input type="hidden" name="action" id="formAction" value="create">
          <input type="hidden" name="id" id="requestId" value="">

          <div class="mb-3">
            <label class="form-label form-label-theme">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="title" class="form-control form-control-theme" required />
          </div>

          <div class="mb-3">
            <label class="form-label form-label-theme">Description <span class="text-danger">*</span></label>
            <textarea name="description" id="description" class="form-control form-control-theme" rows="5" required></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label form-label-theme">Category</label>
              <input type="text" name="category" id="category" class="form-control form-control-theme" placeholder="e.g. Web Development" />
            </div>
            <div class="col-md-6">
              <label class="form-label form-label-theme">Software Type</label>
              <input type="text" name="software_type" id="software_type" class="form-control form-control-theme" placeholder="e.g. Mobile App" />
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label class="form-label form-label-theme">Features (one per line)</label>
            <textarea name="features" id="features" class="form-control form-control-theme" rows="3" placeholder="Feature 1&#10;Feature 2"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-theme">Recommendations</label>
            <textarea name="recommendations" id="recommendations" class="form-control form-control-theme" rows="2"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-theme">Attach files (images/videos) — you can upload multiple</label>
            <div class="upload-zone p-4" onclick="document.getElementById('files').click()">
              <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
              <p class="mb-1 small fw-bold">Click to upload files</p>
              <p class="text-muted small mb-0">Images (PNG, JPG) or Videos (MP4)</p>
              <input type="file" name="files[]" id="files" class="d-none" multiple accept="image/*,video/*" />
            </div>
            <div class="file-upload-preview mt-2" id="filePreview"></div>
          </div>

          <div id="existingFiles" class="mb-3"></div>

        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2 justify-content-end">
          <button type="button" class="btn btn-theme-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="submitBtn" class="btn btn-theme">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <div class="modal-header border-0 pb-0 px-3 pt-3">
        <h5 class="modal-title fw-bold text-danger" id="confirmDeleteLabel">Cancel Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-3 py-2">
        <p class="mb-0 text-secondary" style="font-size:0.88rem;">Are you sure you want to cancel and delete this proposal? This action cannot be undone.</p>
        <div class="mt-3 d-flex justify-content-end gap-2">
          <button class="btn btn-theme-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
          <button id="confirmDeleteBtn" class="btn btn-danger btn-sm px-3">Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Request Modal (for Approved Projects) -->
<div class="modal fade" id="cancelRequestModal" tabindex="-1" aria-labelledby="cancelRequestLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <form id="cancelRequestForm">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:42px;height:42px;background:rgba(239,68,68,0.1);color:#ef4444;">
              <i class="fas fa-exclamation-triangle" style="font-size:1.1rem;"></i>
            </span>
            <div>
              <h5 class="modal-title fw-bold text-danger mb-0" id="cancelRequestLabel">Request Project Cancellation</h5>
              <small class="text-muted">This requires admin approval</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <input type="hidden" name="action" value="request_cancel">
          <input type="hidden" name="id" id="cancelRequestId" value="">
          
          <div class="alert border-0 rounded-3 mb-3 small d-flex gap-2" style="background: rgba(245, 158, 11, 0.08); color: #92400e; border-left: 3px solid #f59e0b !important;">
            <i class="fas fa-info-circle mt-1" style="color:#f59e0b;"></i>
            <div>
              <strong>Important:</strong> Since this project has been <span class="fw-bold text-success">approved</span>, it cannot be deleted directly. Your cancellation request will be sent to the admin for review. You will be notified once a decision is made.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-theme fw-semibold text-secondary small">Reason for Cancellation <span class="text-danger">*</span></label>
            <textarea name="cancel_reason" id="cancelReason" class="form-control form-control-theme" rows="4" placeholder="Please provide a detailed reason for requesting this cancellation..." required style="border-radius: 10px;"></textarea>
            <small class="text-muted mt-1 d-block">Be as specific as possible to help the admin understand your request.</small>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2 justify-content-end">
          <button type="button" class="btn btn-theme-secondary btn-sm px-3" data-bs-dismiss="modal">
            <i class="fas fa-arrow-left me-1"></i> Go Back
          </button>
          <button type="submit" id="submitCancelRequestBtn" class="btn btn-danger btn-sm px-4 fw-semibold">
            <i class="fas fa-paper-plane me-1"></i> Send Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Suspend Request Modal (for Approved Projects) -->
<div class="modal fade" id="suspendRequestModal" tabindex="-1" aria-labelledby="suspendRequestLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <form id="suspendRequestForm">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:42px;height:42px;background:rgba(245,158,11,0.1);color:#d97706;">
              <i class="fas fa-pause-circle" style="font-size:1.1rem;"></i>
            </span>
            <div>
              <h5 class="modal-title fw-bold text-warning mb-0" id="suspendRequestLabel">Request Project Suspension</h5>
              <small class="text-muted">This requires admin approval</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <input type="hidden" name="action" value="request_suspend">
          <input type="hidden" name="id" id="suspendRequestId" value="">
          
          <div class="alert border-0 rounded-3 mb-3 small d-flex gap-2" style="background: rgba(245, 158, 11, 0.08); color: #92400e; border-left: 3px solid #f59e0b !important;">
            <i class="fas fa-info-circle mt-1" style="color:#f59e0b;"></i>
            <div>
              <strong>Important:</strong> Suspending this project will pause all work. Please specify the suspension period. The admin will review your request and you will be notified once a decision is made.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-theme fw-semibold text-secondary small">Reason for Suspension <span class="text-danger">*</span></label>
            <textarea name="suspend_reason" id="suspendReason" class="form-control form-control-theme" rows="3" placeholder="Please provide a detailed reason for requesting this suspension..." required style="border-radius: 10px;"></textarea>
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label form-label-theme fw-semibold text-secondary small">Suspension Start Date <span class="text-danger">*</span></label>
              <input type="date" name="suspend_start_date" id="suspendStartDate" class="form-control form-control-theme" required style="border-radius: 10px;" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label form-label-theme fw-semibold text-secondary small">Expected Resume Date <span class="text-danger">*</span></label>
              <input type="date" name="suspend_end_date" id="suspendEndDate" class="form-control form-control-theme" required style="border-radius: 10px;" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
          </div>
          <small class="text-muted mt-2 d-block">Specify when you want the project to be paused and when it should resume.</small>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2 justify-content-end">
          <button type="button" class="btn btn-theme-secondary btn-sm px-3" data-bs-dismiss="modal">
            <i class="fas fa-arrow-left me-1"></i> Go Back
          </button>
          <button type="submit" id="submitSuspendRequestBtn" class="btn btn-warning btn-sm px-4 fw-semibold" style="color: #92400e;">
            <i class="fas fa-paper-plane me-1"></i> Send Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const requestModalEl = document.getElementById('requestModal');
  const requestModal = new bootstrap.Modal(requestModalEl);
  const viewRequestModalEl = document.getElementById('viewRequestModal');
  const viewRequestModal = new bootstrap.Modal(viewRequestModalEl);
  const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
  const cancelRequestModalEl = document.getElementById('cancelRequestModal');
  const cancelRequestModal = new bootstrap.Modal(cancelRequestModalEl);
  const suspendRequestModalEl = document.getElementById('suspendRequestModal');
  const suspendRequestModal = new bootstrap.Modal(suspendRequestModalEl);
  const toastContainer = document.getElementById('toastContainer');

  // Ensure modal body scroll resets to top and footer remains visible
  [requestModalEl, viewRequestModalEl].forEach(modalEl => {
    modalEl.addEventListener('shown.bs.modal', () => {
      const body = modalEl.querySelector('.modal-body');
      if (body) body.scrollTop = 0;
    });
  });

  // ===================== TOAST UTILITY =====================
  function showToast(message, type = 'success', timeout = 4000) {
    const id = 't' + Date.now();
    const toastHTML = `
      <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`;
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const el = document.getElementById(id);
    const bs = new bootstrap.Toast(el, { delay: timeout });
    bs.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  // ===================== TOGGLE DESCRIPTION =====================
  function toggleDesc(id) {
    const desc = document.getElementById('desc-' + id);
    const btn = document.getElementById('toggle-' + id);
    if (desc.classList.contains('expanded')) {
      desc.classList.remove('expanded');
      btn.textContent = 'View More';
    } else {
      desc.classList.add('expanded');
      btn.textContent = 'Show Less';
    }
  }

  // ===================== VIEW REQUEST DETAIL =====================
  async function viewRequest(id) {
    const res = await fetch(location.pathname + '?action=get_request&id=' + encodeURIComponent(id));
    const data = await res.json();
    if (!data.success) { showToast(data.message || 'Failed to load', 'danger'); return; }
    const r = data.data;
    const files = data.files || [];

    let filesHtml = '';
    if (files.length) {
      filesHtml = '<div class="mt-3"><strong>Attachments:</strong><div class="d-flex flex-wrap gap-2 mt-2">';
      files.forEach(f => {
        if (f.file_type === 'image') {
          filesHtml += `<img src="${f.file_path}" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid #e5e7eb;cursor:pointer;" onclick="window.open('${f.file_path}','_blank')">`;
        } else {
          filesHtml += `<a href="${f.file_path}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-video me-1"></i> Video</a>`;
        }
      });
      filesHtml += '</div></div>';
    }

    let featuresHtml = '';
    if (r.features) {
      const feats = r.features.split('\n').filter(f => f.trim());
      if (feats.length) {
        featuresHtml = '<div class="mt-3"><strong>Features:</strong><ul class="ps-3 mt-1 mb-0">';
        feats.forEach(f => { featuresHtml += `<li class="small text-secondary mb-1">${escapeHtml(f.trim())}</li>`; });
        featuresHtml += '</ul></div>';
      }
    }

    const statusColors = {
      pending: '#f59e0b', reviewed: '#3b82f6', approved: '#22c55e', rejected: '#ef4444'
    };
    const sc = statusColors[r.status?.toLowerCase()] || '#94a3b8';

    const html = `
      <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <h5 class="mb-0 fw-bold">${escapeHtml(r.title)}</h5>
          <span class="req-badge" style="background:${sc}20;color:${sc};">
            <span class="req-dot" style="background:${sc};"></span> ${escapeHtml(r.status)}
          </span>
          ${parseInt(r.cancel_requested) === 1 ? `
            <span class="req-badge" style="background:#fee2e2;color:#991b1b;">
              <span class="req-dot" style="background:#ef4444;"></span> Cancellation Pending
            </span>
          ` : ''}
        </div>
        <div class="d-flex gap-3 text-muted small mb-3">
          <span><i class="fas fa-money-bill-wave me-1"></i> ₦${numberFormat(r.budget || 0)}</span>
          <span><i class="fas fa-calendar me-1"></i> ${escapeHtml(r.timeline || '—')}</span>
          <span><i class="far fa-clock me-1"></i> ${new Date(r.created_at).toLocaleDateString('en-GB', {day:'numeric',month:'long',year:'numeric'})}</span>
        </div>
      </div>
      <div class="mb-3">
        <strong>Description:</strong>
        <p class="text-secondary mt-1 mb-0" style="line-height:1.6;">${escapeHtml(r.description).replace(/\n/g, '<br>')}</p>
      </div>
      ${r.categories || r.software_type ? `<div class="mb-3">${r.categories ? `<span class="req-tag me-1">${escapeHtml(r.categories)}</span>` : ''}${r.software_type ? `<span class="req-tag">${escapeHtml(r.software_type)}</span>` : ''}</div>` : ''}
      ${featuresHtml}
      ${r.recommendations ? `<div class="bg-body-tertiary p-3 rounded-3 mb-3 small border"><strong>Recommendations:</strong><br>${escapeHtml(r.recommendations).replace(/\n/g, '<br>')}</div>` : ''}
      ${filesHtml}
    `;

    document.getElementById('viewRequestModalLabel').textContent = r.title;
    document.querySelector('#viewRequestModal .modal-body').innerHTML = html;
    document.querySelector('#viewRequestModal .modal-footer').innerHTML = `
      <button type="button" class="btn btn-theme-secondary" data-bs-dismiss="modal">Close</button>
      ${parseInt(r.cancel_requested) !== 1 ? `
        <button type="button" class="btn btn-theme" data-bs-dismiss="modal" onclick="openEditForm('${r.id}')"><i class="fas fa-pen me-1"></i> Edit</button>
      ` : ''}
    `;
    viewRequestModal.show();
  }

  function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  }

  function numberFormat(n) {
    return parseFloat(n).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  // ===================== LOAD REQUESTS =====================
  async function loadRequests() {
    const container = document.getElementById('requestsContainer');
    container.innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
      </div>`;
    const res = await fetch(location.pathname + '?action=fetch');
    const html = await res.text();
    container.innerHTML = html;
    attachCardListeners();
  }

  // ===================== CARD LISTENERS =====================
  function attachCardListeners() {

    // Image zoom
    document.querySelectorAll('.zoomable').forEach(img => {
      img.addEventListener('click', e => {
        document.getElementById('zoomImage').src = e.target.src;
        document.getElementById('zoomModal').classList.add('show');
      });
    });

    document.getElementById('zoomModal').addEventListener('click', function(e){
      if (e.target === this || e.target === document.getElementById('zoomImage')) {
        this.classList.remove('show');
      }
    });

    // Edit buttons
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        openEditForm(btn.getAttribute('data-id'));
      });
    });

    // Delete buttons
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const status = btn.getAttribute('data-status')?.toLowerCase();
        if (status === 'approved') {
          document.getElementById('cancelRequestId').value = id;
          document.getElementById('cancelReason').value = '';
          cancelRequestModal.show();
        } else {
          confirmDeleteModal._requestId = id;
          confirmDeleteModal.show();
        }
      });
    });

    // Suspend buttons
    document.querySelectorAll('.btn-suspend').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        document.getElementById('suspendRequestId').value = id;
        document.getElementById('suspendReason').value = '';
        document.getElementById('suspendStartDate').value = '';
        document.getElementById('suspendEndDate').value = '';
        document.getElementById('suspendStartDate').min = new Date().toISOString().split('T')[0];
        document.getElementById('suspendEndDate').min = new Date(Date.now() + 86400000).toISOString().split('T')[0];
        suspendRequestModal.show();
      });
    });
  }

  // ===================== FILE PREVIEW =====================
  document.getElementById('files').addEventListener('change', (e) => {
    const preview = document.getElementById('filePreview');
    preview.innerHTML = '';
    Array.from(e.target.files).forEach(f => {
      const div = document.createElement('div');
      div.className = 'preview-item';
      div.textContent = f.name;
      preview.appendChild(div);
    });
  });

  // ===================== OPEN EDIT FORM =====================
  async function openEditForm(id) {
    const res = await fetch(location.pathname + '?action=get_request&id=' + encodeURIComponent(id));
    const data = await res.json();

    if (!data.success) {
      showToast(data.message || 'Failed to load request', 'danger');
      return;
    }

    const req = data.data;

    document.getElementById('formAction').value = 'update';
    document.getElementById('requestId').value = req.id;
    document.getElementById('title').value = req.title;
    document.getElementById('description').value = req.description;
    document.getElementById('category').value = req.categories;
    document.getElementById('software_type').value = req.software_type;
    document.getElementById('features').value = req.features;
    document.getElementById('recommendations').value = req.recommendations;
    document.getElementById('requestModalLabel').textContent = 'Edit Project Scope';
    document.getElementById('submitBtn').textContent = 'Update Request';

    document.getElementById('filePreview').innerHTML = '';
    const existingFilesDiv = document.getElementById('existingFiles');
    existingFilesDiv.innerHTML = '';

    if (Array.isArray(data.files) && data.files.length) {
      data.files.forEach(f => {
        const item = document.createElement('div');
        item.className = 'd-flex align-items-center justify-content-between mb-2';
        item.innerHTML = `
          <div class="d-flex align-items-center">
            ${f.file_type === 'image'
              ? `<img src="${f.file_path}" style="max-width:70px;max-height:70px;border-radius:6px;margin-right:8px;">`
              : '<i class="fas fa-video me-2"></i>'}
            <small class="text-muted">${f.file_path.split('/').pop()}</small>
          </div>
          <button class="btn btn-sm btn-outline-danger btn-delete-file" data-file-id="${f.id}">
            <i class="fas fa-trash"></i>
          </button>`;
        existingFilesDiv.appendChild(item);
      });

      existingFilesDiv.querySelectorAll('.btn-delete-file').forEach(b => {
        b.addEventListener('click', async () => {
          if (!confirm('Delete this file?')) return;
          const fd = new FormData();
          fd.append('action', 'delete_file');
          fd.append('file_id', b.dataset.fileId);
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          j.success ? showToast('File deleted') : showToast('Delete failed', 'danger');
          openEditForm(id);
        });
      });
    } else {
      existingFilesDiv.innerHTML = '<div class="text-muted">No files attached.</div>';
    }

    requestModal.show();
  }

  // ===================== SUBMIT FORM =====================
  document.getElementById('requestForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form = e.target;
    const fd = new FormData(form);
    fd.set('action', document.getElementById('formAction').value);

    const btn = document.getElementById('submitBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    try {
      const res = await fetch(location.pathname, { method: 'POST', body: fd });
      const j = await res.json();

      if (j.success) {
        showToast(j.message || 'Updated successfully');
        requestModal.hide();
        form.reset();
        document.getElementById('filePreview').innerHTML = '';
        await loadRequests();
      } else {
        showToast(j.message || 'Error occurred', 'danger');
      }
    } catch {
      showToast('Network error', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // ===================== CONFIRM DELETE =====================
  document.getElementById('confirmDeleteBtn').addEventListener('click', async (e) => {
    const id = confirmDeleteModal._requestId;
    if (!id) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    const btn = e.target;
    const old = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    const res = await fetch(location.pathname, { method: 'POST', body: fd });
    const j = await res.json();

    j.success ? showToast('Deleted') : showToast('Delete failed', 'danger');
    confirmDeleteModal.hide();
    await loadRequests();

    btn.disabled = false;
    btn.textContent = old;
  });

  // ===================== SUBMIT CANCEL REQUEST =====================
  document.getElementById('cancelRequestForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form = e.target;
    const reason = document.getElementById('cancelReason').value.trim();

    if (!reason) {
      showToast('Please provide a reason for cancellation', 'danger');
      return;
    }

    // SweetAlert2 confirmation
    const result = await Swal.fire({
      title: 'Submit Cancellation Request?',
      html: `<div class="text-start">
        <p class="text-muted mb-2">You are about to submit a cancellation request for this approved project.</p>
        <div class="p-2 rounded-3 mb-2" style="background: #fef2f2; border: 1px solid #fecaca;">
          <strong class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>This action requires admin approval.</strong>
        </div>
        <p class="text-muted small mb-0">The admin will review your request and you will be notified of the decision.</p>
      </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280',
      confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Yes, Send Request',
      cancelButtonText: 'Go Back',
      customClass: { popup: 'swal2-border-radius' },
      reverseButtons: true
    });

    if (!result.isConfirmed) return;

    const fd = new FormData(form);

    const btn = document.getElementById('submitCancelRequestBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

    try {
      const res = await fetch(location.pathname, { method: 'POST', body: fd });
      const j = await res.json();

      if (j.success) {
        Swal.fire({
          icon: 'success',
          title: 'Request Submitted!',
          html: `<p>Your cancellation request has been sent to the admin for review.</p>
                 <p class="text-muted small mb-0">You will be notified once a decision is made.</p>`,
          confirmButtonColor: '#0A2D5E',
          customClass: { popup: 'swal2-border-radius' }
        }).then(async () => {
          cancelRequestModal.hide();
          form.reset();
          await loadRequests();
        });
      } else {
        showToast(j.message || 'Error submitting request', 'danger');
      }
    } catch {
      showToast('Network error', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // ===================== SUBMIT SUSPEND REQUEST =====================
  document.getElementById('suspendRequestForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form = e.target;
    const reason = document.getElementById('suspendReason').value.trim();
    const startDate = document.getElementById('suspendStartDate').value;
    const endDate = document.getElementById('suspendEndDate').value;

    if (!reason) {
      showToast('Please provide a reason for suspension', 'danger');
      return;
    }
    if (!startDate || !endDate) {
      showToast('Please select both start and resume dates', 'danger');
      return;
    }
    if (new Date(endDate) <= new Date(startDate)) {
      showToast('Resume date must be after the start date', 'danger');
      return;
    }

    // SweetAlert2 confirmation
    const result = await Swal.fire({
      title: 'Submit Suspension Request?',
      html: `<div class="text-start">
        <p class="text-muted mb-2">You are about to submit a suspension request for this project.</p>
        <div class="p-2 rounded-3 mb-2" style="background: #fffbeb; border: 1px solid #fde68a;">
          <strong style="color:#92400e;"><i class="fas fa-pause-circle me-1"></i>This action requires admin approval.</strong>
        </div>
        <div class="p-2 rounded-3 mb-2" style="background: #f8fafc; border: 1px solid #e2e8f0;">
          <small class="text-muted">
            <strong>Period:</strong> ${new Date(startDate).toLocaleDateString('en-US', {month:'short', day:'numeric'})} — ${new Date(endDate).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}
          </small>
        </div>
        <p class="text-muted small mb-0">The admin will review your request and you will be notified of the decision.</p>
      </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d97706',
      cancelButtonColor: '#6b7280',
      confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Yes, Send Request',
      cancelButtonText: 'Go Back',
      customClass: { popup: 'swal2-border-radius' },
      reverseButtons: true
    });

    if (!result.isConfirmed) return;

    const fd = new FormData(form);

    const btn = document.getElementById('submitSuspendRequestBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

    try {
      const res = await fetch(location.pathname, { method: 'POST', body: fd });
      const j = await res.json();

      if (j.success) {
        Swal.fire({
          icon: 'success',
          title: 'Request Submitted!',
          html: `<p>Your suspension request has been sent to the admin for review.</p>
                 <p class="text-muted small mb-0">You will be notified once a decision is made.</p>`,
          confirmButtonColor: '#0A2D5E',
          customClass: { popup: 'swal2-border-radius' }
        }).then(async () => {
          suspendRequestModal.hide();
          form.reset();
          await loadRequests();
        });
      } else {
        showToast(j.message || 'Error submitting request', 'danger');
      }
    } catch {
      showToast('Network error', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // ===================== INITIAL LOAD =====================
  loadRequests();
</script>

<?php
require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
?>
