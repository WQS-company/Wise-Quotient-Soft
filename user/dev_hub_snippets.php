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

// Handle Snippet Submission
$message = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_snippet') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language = trim($_POST['language'] ?? 'plaintext');
    $code = trim($_POST['code'] ?? '');
    $github_link = trim($_POST['github_link'] ?? '');
    
    if ($title && ($code || $github_link)) {
        $stmt = $pdo->prepare("INSERT INTO hub_code_snippets (user_id, title, description, code, language, github_link) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $title, $description, $code, $language, $github_link])) {
            $message = "Snippet shared successfully!";
            $msgType = "success";
        } else {
            $message = "Failed to share snippet.";
            $msgType = "danger";
        }
    } else {
        $message = "Title and either Code or GitHub Link are required.";
        $msgType = "warning";
    }
}

// Fetch Snippets
$stmt = $pdo->query("
    SELECT s.*, u.name as author_name, u.picture as author_picture, u.profile_slug 
    FROM hub_code_snippets s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
");
$snippets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Code & Repos";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
?>

<!-- Prism.js for syntax highlighting -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />

<style>
.snippet-hero {
    background: linear-gradient(135deg, #6366f1 0%, #312e81 100%);
    border-radius: 20px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.snippet-hero::after {
    content:''; position:absolute; top:-30px; right:-30px;
    width:150px; height:150px;
    background: url('data:image/svg+xml;utf8,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><path d="M50 0L100 25V75L50 100L0 75V25Z" fill="rgba(255,255,255,0.05)"/></svg>') repeat;
    opacity: 0.5;
}

.snippet-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: transform 0.2s ease;
}
.snippet-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}

.snippet-header {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.snippet-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 0.25rem;
}

.snippet-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: #64748b;
}

.snippet-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
}

.lang-badge {
    background: #e0e7ff;
    color: #4338ca;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.snippet-body {
    padding: 1.5rem;
}

.snippet-desc {
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.code-container {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}
.copy-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: #cbd5e1;
    border-radius: 6px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    z-index: 10;
}
.copy-btn:hover {
    background: rgba(255,255,255,0.2);
    color: white;
}

.github-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #24292e;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: background 0.2s;
}
.github-link:hover {
    background: #000000;
    color: white;
}
</style>

<div class="container-fluid py-2">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="dev_hub.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-1"></i> Back to Hub</a>
        <button class="btn btn-sm" style="background: #6366f1; color: white; font-weight: 600; border-radius: 50px; padding: 0.4rem 1.2rem;" data-bs-toggle="modal" data-bs-target="#newSnippetModal">
            <i class="fas fa-plus me-1"></i> Share Snippet or Repo
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="snippet-hero">
        <div class="position-relative" style="z-index: 10;">
            <h2 class="fw-extrabold" style="font-family: 'Plus Jakarta Sans', sans-serif;"><i class="fas fa-laptop-code me-2"></i>Code & Repositories</h2>
            <p style="color: rgba(255,255,255,0.8); margin-bottom: 0; max-width: 550px;">
                Share reusable algorithms, UI components, or link directly to your open-source GitHub repositories for the community to review.
            </p>
        </div>
    </div>

    <!-- Snippets List -->
    <div class="row">
        <?php if (empty($snippets)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fab fa-github fa-3x mb-3" style="opacity: 0.3;"></i>
                <h5>No code shared yet</h5>
                <p>Be the first to share a snippet or repository!</p>
            </div>
        <?php else: ?>
            <?php foreach ($snippets as $snip): ?>
            <div class="col-lg-6">
                <div class="snippet-card">
                    <div class="snippet-header">
                        <div>
                            <h4 class="snippet-title"><?= htmlspecialchars($snip['title']) ?></h4>
                            <div class="snippet-meta">
                                <?php 
                                    $pic = $snip['author_picture'];
                                    if (!empty($pic) && strpos($pic, '../') === 0) {
                                        $pic = substr($pic, 3);
                                    }
                                    if($pic && strpos($pic, '/') !== 0) $pic = '/' . $pic;
                                ?>
                                <?php if ($pic): ?>
                                    <img src="<?= htmlspecialchars($pic) ?>" class="snippet-avatar" alt="">
                                <?php else: ?>
                                    <div class="snippet-avatar" style="background:#6366f1; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.8rem;">
                                        <?= strtoupper(substr($snip['author_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span>
                                    By <a href="../dev_profile.php?u=<?= htmlspecialchars($snip['profile_slug']) ?>" target="_blank" class="text-decoration-none fw-bold text-body"><?= htmlspecialchars($snip['author_name']) ?></a>
                                    • <?= date('M d, Y', strtotime($snip['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($snip['language']): ?>
                            <span class="lang-badge"><?= htmlspecialchars($snip['language']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="snippet-body">
                        <?php if (!empty($snip['description'])): ?>
                            <div class="snippet-desc"><?= nl2br(htmlspecialchars($snip['description'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($snip['code'])): ?>
                            <div class="code-container">
                                <button class="copy-btn" onclick="copyCode(this)">Copy</button>
                                <pre><code class="language-<?= htmlspecialchars(strtolower($snip['language'])) ?>"><?= htmlspecialchars($snip['code']) ?></code></pre>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($snip['github_link'])): ?>
                            <div class="mt-3 text-end">
                                <a href="<?= htmlspecialchars($snip['github_link']) ?>" target="_blank" class="github-link">
                                    <i class="fab fa-github"></i> View Repository
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- New Snippet Modal -->
<div class="modal fade" id="newSnippetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <form method="POST">
                <input type="hidden" name="action" value="add_snippet">
                
                <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0.5rem;">
                    <h5 class="modal-title fw-bold" style="color: #312e81;"><i class="fas fa-laptop-code me-2"></i> Share Code Snippet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. JWT Authentication Middleware" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Language</label>
                            <select name="language" class="form-select">
                                <option value="php">PHP</option>
                                <option value="javascript">JavaScript / Node.js</option>
                                <option value="python">Python</option>
                                <option value="java">Java</option>
                                <option value="csharp">C#</option>
                                <option value="html">HTML / CSS</option>
                                <option value="sql">SQL</option>
                                <option value="bash">Bash</option>
                                <option value="plaintext">Plain Text</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Briefly explain what this code does..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Code Snippet</label>
                            <textarea name="code" class="form-control" rows="8" placeholder="Paste your code here..." style="font-family: monospace;"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">GitHub Repository Link <small class="text-muted">(Optional)</small></label>
                            <input type="url" name="github_link" class="form-control" placeholder="https://github.com/username/repo">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0.5rem 1.5rem 1.5rem;">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-pill" style="background: #6366f1; font-weight: 600;">Share Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Prism.js Script for Syntax Highlighting -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup-templating.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-java.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-csharp.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>

<script>
function copyCode(btn) {
    const codeBlock = btn.nextElementSibling.querySelector('code');
    navigator.clipboard.writeText(codeBlock.innerText).then(() => {
        const originalText = btn.innerText;
        btn.innerText = 'Copied!';
        btn.style.background = '#10b981';
        btn.style.color = 'white';
        btn.style.borderColor = '#10b981';
        setTimeout(() => {
            btn.innerText = originalText;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
