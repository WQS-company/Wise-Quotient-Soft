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

$discId = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
if (!$discId) {
    header("Location: dev_hub_discussions.php"); exit;
}

// Cloudinary config (hardcoded)
$cloudName = 'dbrngv7eg';                 
$apiKey    = '989193635679214';           
$apiSecret = 'p0LTokA9aOAjAYiSIU8dathFSTk';

// Handle Reply Submission
$message = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $content = trim($_POST['content'] ?? '');
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

    if ($content) {
        $stmt = $pdo->prepare("INSERT INTO hub_discussion_replies (discussion_id, user_id, content, attachment_url) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$discId, $userId, $content, $attachment_url])) {
            $message = "Reply posted successfully!";
            $msgType = "success";
        } else {
            $message = "Failed to post reply.";
            $msgType = "danger";
        }
    }
}

// Mark Solution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_solution') {
    $replyId = (int)$_POST['reply_id'];
    
    // Verify current user owns the discussion or is admin
    $check = $pdo->prepare("SELECT user_id FROM hub_discussions WHERE id = ?");
    $check->execute([$discId]);
    $discOwner = $check->fetchColumn();
    
    if ($discOwner == $userId || $userRole === 'admin') {
        // Reset all
        $pdo->prepare("UPDATE hub_discussion_replies SET is_solution = 0 WHERE discussion_id = ?")->execute([$discId]);
        // Set new
        $pdo->prepare("UPDATE hub_discussion_replies SET is_solution = 1 WHERE id = ?")->execute([$replyId]);
        $message = "Solution marked successfully!";
        $msgType = "success";
    }
}

// Fetch discussion
$stmt = $pdo->prepare("
    SELECT d.*, u.name as author_name, u.picture as author_picture, u.role as author_role, u.profile_slug 
    FROM hub_discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$discId]);
$discussion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$discussion) {
    header("Location: dev_hub_discussions.php"); exit;
}

// Increment views (simple)
if (!isset($_SESSION["disc_viewed_$discId"])) {
    $pdo->query("UPDATE hub_discussions SET views = views + 1 WHERE id = $discId");
    $_SESSION["disc_viewed_$discId"] = true;
}

// Fetch replies
$replyStmt = $pdo->prepare("
    SELECT r.*, u.name as author_name, u.picture as author_picture, u.role as author_role, u.profile_slug 
    FROM hub_discussion_replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.discussion_id = ?
    ORDER BY r.is_solution DESC, r.created_at ASC
");
$replyStmt->execute([$discId]);
$replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

$tags = array_filter(array_map('trim', explode(',', $discussion['tags'] ?? '')));
$isOwnerOrAdmin = ($discussion['user_id'] == $userId || $userRole === 'admin');

$page_title = "Discussion: " . htmlspecialchars($discussion['title']);
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<style>
.thread-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}

.thread-header {
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
}

.thread-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.8rem;
    font-weight: 800;
    color: #0A2D5E;
    margin-bottom: 1rem;
}

.author-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.thread-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.thread-content {
    font-size: 1.05rem;
    line-height: 1.7;
    color: #334155;
    white-space: pre-wrap;
}

.reply-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
}
.reply-card.is-solution {
    background: rgba(16, 185, 129, 0.05);
    border-color: #34d399;
}
.solution-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #10b981;
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
}

.reply-content {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #475569;
    white-space: pre-wrap;
    margin-top: 1rem;
}

.disc-tag {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 0.3rem;
    display: inline-block;
}

pre {
    background: #0f172a;
    color: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
}
code {
    font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
    font-size: 0.9rem;
}

.attachment-preview {
    margin-top: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.5rem;
    background: #f8fafc;
    display: inline-block;
    max-width: 100%;
}
.attachment-preview img, .attachment-preview video {
    max-width: 100%;
    border-radius: 4px;
    max-height: 400px;
    object-fit: contain;
}
.attachment-file {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #e0e7ff;
    color: #4338ca;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
}
.attachment-file:hover {
    background: #c7d2fe;
    color: #3730a3;
}
</style>

<div class="container-fluid py-2" style="max-width: 900px;">
    <div class="mb-4">
        <a href="dev_hub_discussions.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-1"></i> Back to Discussions</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Thread -->
    <div class="thread-container">
        <div class="thread-header">
            <div>
                <?php foreach ($tags as $t): ?>
                    <span class="disc-tag mb-3"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>
            
            <h1 class="thread-title"><?= htmlspecialchars($discussion['title']) ?></h1>
            
            <div class="author-meta">
                <?php 
                    $pic = $discussion['author_picture'];
                    if (!empty($pic) && strpos($pic, '../') === 0) {
                        $pic = substr($pic, 3);
                    }
                    if($pic && strpos($pic, '/') !== 0) $pic = '/' . $pic;
                ?>
                <?php if ($pic): ?>
                    <img src="<?= htmlspecialchars($pic) ?>" class="thread-avatar" alt="">
                <?php else: ?>
                    <div class="thread-avatar" style="background:#0A2D5E; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem;">
                        <?= strtoupper(substr($discussion['author_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <div class="fw-bold text-body">
                        <a href="../dev_profile.php?u=<?= htmlspecialchars($discussion['profile_slug']) ?>" target="_blank" class="text-decoration-none text-body">
                            <?= htmlspecialchars($discussion['author_name']) ?>
                        </a>
                        <span class="badge bg-light text-muted ms-1"><?= ucfirst($discussion['author_role']) ?></span>
                    </div>
                    <div class="text-muted small">
                        Posted on <?= date('F j, Y \a\t H:i', strtotime($discussion['created_at'])) ?>
                        • <?= $discussion['views'] ?> Views
                    </div>
                </div>
            </div>
        </div>

        <div class="thread-content"><?= htmlspecialchars($discussion['content']) ?></div>
        
        <?php if (!empty($discussion['attachment_url'])): 
            $ext = strtolower(pathinfo(parse_url($discussion['attachment_url'], PHP_URL_PATH), PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            $isVideo = in_array($ext, ['mp4','webm','ogg']);
        ?>
            <div class="attachment-preview">
                <div class="small fw-bold text-muted mb-2"><i class="fas fa-paperclip"></i> Attachment</div>
                <?php if ($isImage): ?>
                    <a href="<?= htmlspecialchars($discussion['attachment_url']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($discussion['attachment_url']) ?>" alt="Attachment">
                    </a>
                <?php elseif ($isVideo): ?>
                    <video src="<?= htmlspecialchars($discussion['attachment_url']) ?>" controls></video>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($discussion['attachment_url']) ?>" target="_blank" class="attachment-file">
                        <i class="fas fa-file-download"></i> Download File
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Replies Section -->
    <h4 class="fw-bold mb-4" style="color: #0A2D5E;"><?= count($replies) ?> Replies</h4>

    <?php foreach ($replies as $reply): ?>
        <div class="reply-card <?= $reply['is_solution'] ? 'is-solution' : '' ?>">
            <?php if ($reply['is_solution']): ?>
                <div class="solution-badge"><i class="fas fa-check-circle me-1"></i> Accepted Solution</div>
            <?php endif; ?>

            <div class="author-meta" style="gap: 0.75rem;">
                <?php 
                    $rpic = $reply['author_picture'];
                    if (!empty($rpic) && strpos($rpic, '../') === 0) {
                        $rpic = substr($rpic, 3);
                    }
                    if($rpic && strpos($rpic, '/') !== 0) $rpic = '/' . $rpic;
                ?>
                <?php if ($rpic): ?>
                    <img src="<?= htmlspecialchars($rpic) ?>" class="thread-avatar" style="width:40px; height:40px;" alt="">
                <?php else: ?>
                    <div class="thread-avatar" style="width:40px; height:40px; background:#64748b; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                        <?= strtoupper(substr($reply['author_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <div class="fw-bold" style="font-size: 0.9rem;">
                        <a href="../dev_profile.php?u=<?= htmlspecialchars($reply['profile_slug']) ?>" target="_blank" class="text-decoration-none text-body">
                            <?= htmlspecialchars($reply['author_name']) ?>
                        </a>
                    </div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <?= date('M d, Y H:i', strtotime($reply['created_at'])) ?>
                    </div>
                </div>
            </div>

            <div class="reply-content"><?= htmlspecialchars($reply['content']) ?></div>
            
            <?php if (!empty($reply['attachment_url'])): 
                $ext = strtolower(pathinfo(parse_url($reply['attachment_url'], PHP_URL_PATH), PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                $isVideo = in_array($ext, ['mp4','webm','ogg']);
            ?>
                <div class="attachment-preview mt-3">
                    <?php if ($isImage): ?>
                        <a href="<?= htmlspecialchars($reply['attachment_url']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($reply['attachment_url']) ?>" alt="Attachment" style="max-height: 250px;">
                        </a>
                    <?php elseif ($isVideo): ?>
                        <video src="<?= htmlspecialchars($reply['attachment_url']) ?>" controls style="max-height: 250px;"></video>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($reply['attachment_url']) ?>" target="_blank" class="attachment-file">
                            <i class="fas fa-file-download"></i> View File
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($isOwnerOrAdmin && !$reply['is_solution']): ?>
                <div class="mt-3 pt-3 border-top text-end">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_solution">
                        <input type="hidden" name="reply_id" value="<?= $reply['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill" style="font-weight: 600; font-size: 0.75rem;">
                            <i class="fas fa-check me-1"></i> Mark as Solution
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Reply Form -->
    <div class="card border-0 shadow-sm mt-5" style="border-radius: 16px;">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3" style="color: #0A2D5E;">Post a Reply</h5>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="reply">
                <div class="mb-3">
                    <textarea name="content" class="form-control" rows="5" placeholder="Write your response here... Markdown code blocks are supported by typing ``` code ``` " required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size: 0.85rem;"><i class="fas fa-paperclip me-1"></i> Attach Image or File <small class="text-muted">(Optional)</small></label>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*,video/*,.pdf,.zip,.rar">
                </div>
                <div class="text-end mt-2">
                    <button type="submit" class="btn text-white rounded-pill px-4" style="background: #E15501; font-weight: 600;">Post Reply</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
// Simple script to syntax highlight markdown code blocks in the frontend if needed
document.addEventListener('DOMContentLoaded', () => {
    const contents = document.querySelectorAll('.thread-content, .reply-content');
    contents.forEach(el => {
        let html = el.innerHTML;
        // Basic Markdown code block replacement
        html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // Inline code
        html = html.replace(/`([^`]+)`/g, '<code style="background:#f1f5f9; color:#e11d48; padding:0.1rem 0.3rem; border-radius:4px;">$1</code>');
        el.innerHTML = html;
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
