<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: ../login.php"); exit; }

require_once dirname(__DIR__) . '/config.php';

$userId = $_SESSION['user']['id'];
$userRole = strtolower($_SESSION['user']['role']);

if (!in_array($userRole, ['developer', 'admin', 'agent'])) {
    header("Location: ../index.php"); exit;
}

// Cloudinary config (hardcoded)
$cloudName = 'dbrngv7eg';                 
$apiKey    = '989193635679214';           
$apiSecret = 'p0LTokA9aOAjAYiSIU8dathFSTk'; 

// Handle Form Submission
$message = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_discussion') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $attachment_url = null;
    
    // Process Cloudinary Upload if file exists
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['attachment']['tmp_name'];
        $timestamp = time();
        $signature = sha1("timestamp={$timestamp}{$apiSecret}");
        
        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $cfile = new CURLFile($tmpName, $_FILES['attachment']['type'], $_FILES['attachment']['name']);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $cfile,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => 'wqs/hub_attachments'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $json = json_decode($response, true);
            if (isset($json['secure_url'])) {
                $attachment_url = $json['secure_url'];
            }
        }
    }
    
    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO hub_discussions (user_id, title, content, tags, attachment_url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $title, $content, $tags, $attachment_url])) {
            $message = "Discussion started successfully!";
            $msgType = "success";
        } else {
            $message = "Failed to start discussion.";
            $msgType = "danger";
        }
    } else {
        $message = "Title and content are required.";
        $msgType = "warning";
    }
}

// Fetch discussions
$stmt = $pdo->query("
    SELECT d.*, u.name as author_name, u.picture as author_picture, u.role as author_role,
           (SELECT COUNT(*) FROM hub_discussion_replies WHERE discussion_id = d.id) as reply_count
    FROM hub_discussions d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.created_at DESC
");
$discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Community Discussions";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
.disc-hero {
    background: linear-gradient(135deg, #E15501 0%, #a33c00 100%);
    border-radius: 20px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.disc-hero::after {
    content:''; position:absolute; bottom:-50px; right:-50px;
    width:200px; height:200px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius:50%;
}

.disc-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}
.disc-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border-color: #E15501;
}

.disc-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}
.disc-avatar-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #0A2D5E;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.disc-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
    margin-bottom: 0.3rem;
    display: inline-block;
}
.disc-title:hover {
    color: #E15501;
}

.disc-meta {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.disc-tag {
    background: #f1f5f9;
    color: #475569;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-right: 0.3rem;
}

.disc-stats {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 60px;
    gap: 0.5rem;
}
.stat-box {
    text-align: center;
    font-size: 0.8rem;
    color: #64748b;
}
.stat-box.replies {
    background: rgba(225, 85, 1, 0.08);
    color: #E15501;
    padding: 0.3rem 0.5rem;
    border-radius: 8px;
    font-weight: 700;
    border: 1px solid rgba(225, 85, 1, 0.2);
}
</style>

<div class="container-fluid py-2">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="dev_hub.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-1"></i> Back to Hub</a>
        <button class="btn btn-sm" style="background: #E15501; color: white; font-weight: 600; border-radius: 50px; padding: 0.4rem 1.2rem;" data-bs-toggle="modal" data-bs-target="#newDiscModal">
            <i class="fas fa-plus me-1"></i> Start Discussion
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="disc-hero">
        <div class="position-relative" style="z-index: 10;">
            <h2 class="fw-extrabold" style="font-family: 'Plus Jakarta Sans', sans-serif;">Community Discussions</h2>
            <p style="color: rgba(255,255,255,0.8); margin-bottom: 0; max-width: 500px;">
                Ask questions, share architectural ideas, and collaborate with the platform's developer community.
            </p>
        </div>
    </div>

    <!-- Discussions List -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-body p-4">
            
            <?php if (empty($discussions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-comments fa-3x mb-3" style="opacity: 0.3;"></i>
                    <h5>No discussions yet</h5>
                    <p>Be the first to start a conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($discussions as $disc): 
                    $tags = array_filter(array_map('trim', explode(',', $disc['tags'] ?? '')));
                ?>
                <div class="disc-card">
                    <div>
                        <?php if (!empty($disc['author_picture'])): ?>
                            <?php 
                                $pic = $disc['author_picture'];
                                if (strpos($pic, '../') === 0) {
                                    $pic = substr($pic, 3);
                                }
                                if($pic && strpos($pic, '/') !== 0) $pic = '/' . $pic;
                            ?>
                            <img src="<?= htmlspecialchars($pic) ?>" class="disc-avatar" alt="">
                        <?php else: ?>
                            <div class="disc-avatar-placeholder">
                                <?= strtoupper(substr($disc['author_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="flex-grow: 1;">
                        <a href="dev_hub_discussion_view.php?id=<?= wqs_encrypt_id($disc['id']) ?>" class="disc-title">
                            <?= htmlspecialchars($disc['title']) ?>
                        </a>
                        <div class="disc-meta">
                            By <strong><?= htmlspecialchars($disc['author_name']) ?></strong> 
                            <span style="opacity: 0.5;">(<?= ucfirst($disc['author_role']) ?>)</span> 
                            • <?= date('M d, Y H:i', strtotime($disc['created_at'])) ?>
                        </div>
                        <div>
                            <?php foreach ($tags as $t): ?>
                                <span class="disc-tag"><?= htmlspecialchars($t) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="disc-stats">
                        <div class="stat-box replies">
                            <div style="font-size: 1rem;"><?= $disc['reply_count'] ?></div>
                            <div style="font-size: 0.65rem; text-transform: uppercase;">Replies</div>
                        </div>
                        <div class="stat-box">
                            <div style="font-weight: 600; color: #0f172a;"><?= $disc['views'] ?></div>
                            <div style="font-size: 0.65rem;">Views</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- New Discussion Modal -->
<div class="modal fade" id="newDiscModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="new_discussion">
                
                <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0.5rem;">
                    <h5 class="modal-title fw-bold" style="color: #0A2D5E;"><i class="fas fa-edit me-2"></i> Start New Discussion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Discussion Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Best practices for Redis caching in PHP" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tags (Comma-separated)</label>
                        <input type="text" name="tags" class="form-control" placeholder="e.g. PHP, Redis, Architecture">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content *</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Describe your question or idea..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-paperclip me-1"></i> Attach Image or File <small class="text-muted">(Optional)</small></label>
                        <input type="file" name="attachment" class="form-control" accept="image/*,video/*,.pdf,.zip,.rar">
                        <div class="form-text" style="font-size: 0.75rem;">Allowed: Images, Videos, PDFs, ZIPs. Uploaded securely via Cloudinary.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0.5rem 1.5rem 1.5rem;">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-pill" style="background: #E15501; font-weight: 600;">Post Discussion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
