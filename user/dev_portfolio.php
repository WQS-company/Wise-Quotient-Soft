<?php
$path_to_root = "../";

// --- AJAX HANDLING FIRST, BEFORE ANY OUTPUT ---
session_start();
require_once dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $userId = $_SESSION['user']['id'] ?? 0;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    $act = $_POST['ajax_action'];

    // Upload dir
    $uploadDir = dirname(__DIR__) . '/uploads/portfolio/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    function uploadPortfolioImage($file) {
        global $uploadDir;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;
        $name = 'port_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
            return 'uploads/portfolio/' . $name;
        }
        return null;
    }

    try {
        if ($act === 'add_portfolio_batch') {
            $titles = $_POST['title'] ?? [];
            $descs  = $_POST['description'] ?? [];
            $techs  = $_POST['tech_stack'] ?? [];
            $cats   = $_POST['category'] ?? [];
            $lives  = $_POST['live_url'] ?? [];
            $gits   = $_POST['github_url'] ?? [];
            $images = $_FILES['image'] ?? null;

            $added = 0;
            for ($i = 0; $i < count($titles); $i++) {
                $title = trim($titles[$i] ?? '');
                if (!$title) continue;
                $desc = trim($descs[$i] ?? '');
                $tech = trim($techs[$i] ?? '');
                $cat  = trim($cats[$i] ?? 'Web');
                $live = trim($lives[$i] ?? '');
                $git  = trim($gits[$i] ?? '');

                // Upload image for this row
                $imgPath = '';
                if ($images && isset($images['name'][$i]) && $images['error'][$i] === UPLOAD_ERR_OK) {
                    $filePart = [
                        'name'     => $images['name'][$i],
                        'type'     => $images['type'][$i],
                        'tmp_name' => $images['tmp_name'][$i],
                        'error'    => $images['error'][$i],
                        'size'     => $images['size'][$i],
                    ];
                    $imgPath = uploadPortfolioImage($filePart);
                }

                $pdo->prepare("INSERT INTO dev_portfolio_items (developer_id,title,description,tech_stack,live_url,github_url,image_url,category) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$userId,$title,$desc,$tech,$live,$git,$imgPath,$cat]);
                $added++;
            }
            echo json_encode(['success' => $added > 0, 'message' => "$added portfolio item(s) added successfully!", 'count' => $added]);
            exit;
        }

        if ($act === 'update_portfolio') {
            $pid   = (int)($_POST['portfolio_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $desc  = trim($_POST['description'] ?? '');
            $tech  = trim($_POST['tech_stack'] ?? '');
            $live  = trim($_POST['live_url'] ?? '');
            $git   = trim($_POST['github_url'] ?? '');
            $cat   = trim($_POST['category'] ?? 'Web');
            if (!$title) {
                echo json_encode(['success' => false, 'message' => 'Title required.']);
                exit;
            }
            // Handle image upload
            $imgPath = null;
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imgPath = uploadPortfolioImage($_FILES['image']);
            }
            if ($imgPath) {
                $sql = "UPDATE dev_portfolio_items SET title=?,description=?,tech_stack=?,live_url=?,github_url=?,category=?,image_url=? WHERE id=? AND developer_id=?";
                $params = [$title,$desc,$tech,$live,$git,$cat,$imgPath,$pid,$userId];
            } else {
                $sql = "UPDATE dev_portfolio_items SET title=?,description=?,tech_stack=?,live_url=?,github_url=?,category=? WHERE id=? AND developer_id=?";
                $params = [$title,$desc,$tech,$live,$git,$cat,$pid,$userId];
            }
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['success' => true, 'message' => 'Portfolio project updated successfully!']);
            exit;
        }

        if ($act === 'delete_portfolio') {
            $pid = (int)$_POST['portfolio_id'];
            // Delete image file
            try {
                $stmt = $pdo->prepare("SELECT image_url FROM dev_portfolio_items WHERE id=? AND developer_id=?");
                $stmt->execute([$pid, $userId]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(dirname(__DIR__) . '/' . $old)) {
                    unlink(dirname(__DIR__) . '/' . $old);
                }
            } catch (Exception $e) {
            }
            $pdo->prepare("DELETE FROM dev_portfolio_items WHERE id=? AND developer_id=?")->execute([$pid, $userId]);
            echo json_encode(['success' => true, 'message' => 'Portfolio project removed successfully!']);
            exit;
        }

        if ($act === 'add_skill') {
            $skill = trim($_POST['skill_name'] ?? '');
            $level = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced', 'expert']) ? $_POST['level'] : 'intermediate';
            $yrs   = min(30, max(0, (int)($_POST['years_exp'] ?? 1)));
            if (!$skill) {
                echo json_encode(['success' => false, 'message' => 'Skill name required.']);
                exit;
            }
            $pdo->prepare("INSERT INTO developer_skills (developer_id,skill_name,level,years_exp) VALUES (?,?,?,?)")->execute([$userId, $skill, $level, $yrs]);
            echo json_encode(['success' => true, 'message' => 'Skill added successfully!']);
            exit;
        }

        if ($act === 'update_skill') {
            $sid   = (int)($_POST['skill_id'] ?? 0);
            $skill = trim($_POST['skill_name'] ?? '');
            $level = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced', 'expert']) ? $_POST['level'] : 'intermediate';
            $yrs   = min(30, max(0, (int)($_POST['years_exp'] ?? 1)));
            if (!$skill) {
                echo json_encode(['success' => false, 'message' => 'Skill name required.']);
                exit;
            }
            $pdo->prepare("UPDATE developer_skills SET skill_name=?,level=?,years_exp=? WHERE id=? AND developer_id=?")
                ->execute([$skill, $level, $yrs, $sid, $userId]);
            echo json_encode(['success' => true, 'message' => 'Skill updated successfully!']);
            exit;
        }

        if ($act === 'toggle_featured') {
            $pid = (int)$_POST['portfolio_id'];
            // Get current state
            $stmt = $pdo->prepare("SELECT featured FROM dev_portfolio_items WHERE id=? AND developer_id=?");
            $stmt->execute([$pid, $userId]);
            $current = $stmt->fetchColumn();
            $new = $current ? 0 : 1;
            $pdo->prepare("UPDATE dev_portfolio_items SET featured=? WHERE id=? AND developer_id=?")->execute([$new, $pid, $userId]);
            echo json_encode(['success' => true, 'message' => $new ? 'Marked as featured!' : 'Unmarked from featured!', 'featured' => $new]);
            exit;
        }

        if ($act === 'bulk_delete_portfolio') {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No items selected.']);
                exit;
            }
            $deleted = 0;
            foreach ($ids as $id) {
                $pid = (int)$id;
                try {
                    $stmt = $pdo->prepare("SELECT image_url FROM dev_portfolio_items WHERE id=? AND developer_id=?");
                    $stmt->execute([$pid, $userId]);
                    $old = $stmt->fetchColumn();
                    if ($old && file_exists(dirname(__DIR__) . '/' . $old)) {
                        unlink(dirname(__DIR__) . '/' . $old);
                    }
                } catch (Exception $e) {}
                $pdo->prepare("DELETE FROM dev_portfolio_items WHERE id=? AND developer_id=?")->execute([$pid, $userId]);
                $deleted++;
            }
            echo json_encode(['success' => true, 'message' => "$deleted item(s) deleted successfully!", 'count' => $deleted]);
            exit;
        }

        if ($act === 'delete_skill') {
            $sid = (int)$_POST['skill_id'];
            $pdo->prepare("DELETE FROM developer_skills WHERE id=? AND developer_id=?")->execute([$sid, $userId]);
            echo json_encode(['success' => true, 'message' => 'Skill removed successfully!']);
            exit;
        }

        if ($act === 'bulk_delete_skills') {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No skills selected.']);
                exit;
            }
            $deleted = 0;
            foreach ($ids as $id) {
                $sid = (int)$id;
                $pdo->prepare("DELETE FROM developer_skills WHERE id=? AND developer_id=?")->execute([$sid, $userId]);
                $deleted++;
            }
            echo json_encode(['success' => true, 'message' => "$deleted skill(s) removed successfully!", 'count' => $deleted]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// --- Now load the page ---
$page_title = "Developer Portfolio";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
if (!in_array($user_role, ['developer', 'admin'])) {
    header("Location: ../login.php");
    exit;
}
$userId = $headerUser['id'];

// Fetch data
try {
    $portfolio = $pdo->prepare("SELECT * FROM dev_portfolio_items WHERE developer_id=? ORDER BY created_at DESC");
    $portfolio->execute([$userId]);
    $portfolio = $portfolio->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $portfolio = [];
}

try {
    $skills = $pdo->prepare("SELECT * FROM developer_skills WHERE developer_id=? ORDER BY level DESC, skill_name ASC");
    $skills->execute([$userId]);
    $skills = $skills->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $skills = [];
}

$categories = ['Web', 'Mobile', 'Desktop', 'Backend', 'DevOps', 'AI/ML', 'UI/UX', 'Game Dev', 'Other'];
$levelColors = ['beginner' => ['#f0fdf4', '#15803d'], 'intermediate' => ['#eff6ff', '#1d4ed8'], 'advanced' => ['#fef3c7', '#d97706'], 'expert' => ['#fdf4ff', '#9333ea']];
?>

<style>
    .portfolio-hero {
        background: linear-gradient(135deg, #0f2857, #1a3f80);
        border-radius: 20px;
        padding: 1.75rem 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.75rem;
    }

    .portfolio-hero::before {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 220px;
        height: 220px;
        background: rgba(225, 85, 1, 0.15);
        border-radius: 50%;
    }

    .port-card {
        background: white;
        border-radius: 16px;
        border: 1.5px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        transition: all 0.25s;
        position: relative;
    }

    .port-card.featured {
        border-color: rgba(225, 85, 1, 0.4);
        box-shadow: 0 8px 24px rgba(225, 85, 1, 0.15);
    }

    .port-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
        border-color: rgba(10, 45, 94, 0.15);
    }

    .port-img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: linear-gradient(135deg, #0A2D5E, #2563eb);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .tech-tag {
        font-size: 0.68rem;
        background: #f1f5f9;
        color: #475569;
        padding: 0.18rem 0.55rem;
        border-radius: 50px;
        display: inline-block;
        margin: 0.15rem;
        border: 1px solid #e2e8f0;
    }

    .skill-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .skill-row:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .skill-level-bar {
        height: 6px;
        border-radius: 50px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .portfolio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
    }

    @media (max-width:768px) {
        .portfolio-grid {
            grid-template-columns: 1fr;
        }
    }

    .tab-btn {
        padding: 0.55rem 1.5rem;
        border-radius: 50px;
        border: 1.5px solid #e2e8f0;
        background: white;
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }

    .tab-btn.active {
        background: #0A2D5E;
        color: white;
        border-color: #0A2D5E;
    }

    .action-btn-group {
        display: flex;
        gap: 4px;
    }

    .action-btn-group .btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 6px;
        font-size: 0.75rem;
    }

    .featured-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: linear-gradient(135deg, #E15501, #f59e0b);
        color: white;
        padding: 0.2rem 0.7rem;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(225, 85, 1, 0.4);
    }

    .port-checkbox {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 10;
    }

    .port-checkbox input {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .bulk-actions {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        display: none;
    }

    .skill-checkbox {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 5;
    }
    .skill-checkbox input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .skill-row {
        position: relative;
    }
</style>

<!-- Hero -->
<div class="portfolio-hero">
    <div style="position:relative;z-index:1;" class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;">
                    <i class="fas fa-star me-1"></i>Developer Profile
                </span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Portfolio & Skills</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">
                <?= count($portfolio) ?> project<?= count($portfolio) != 1 ? 's' : '' ?> · <?= count($skills) ?> skill<?= count($skills) != 1 ? 's' : '' ?> listed
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn px-4 py-2 fw-bold" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:white;border-radius:10px;" onclick="toggleInlineForm('skillForm')">
                <i class="fas fa-plus me-1"></i>Add Skill
            </button>
            <button class="btn px-4 py-2 fw-bold" style="background:#E15501;border:none;color:white;border-radius:10px;" onclick="toggleInlineForm('portfolioForm')">
                <i class="fas fa-plus me-1"></i>Add Project
            </button>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="d-flex gap-2 mb-4">
    <button class="tab-btn active" onclick="switchTab('portfolio-tab','skills-tab',this)">
        <i class="fas fa-briefcase me-1"></i>Portfolio (<?= count($portfolio) ?>)
    </button>
    <button class="tab-btn" onclick="switchTab('skills-tab','portfolio-tab',this)">
        <i class="fas fa-code me-1"></i>Skills (<?= count($skills) ?>)
    </button>
</div>

<!-- Portfolio Tab -->
<div id="portfolio-tab">
    <!-- Bulk Actions -->
    <div id="bulkActions" class="bulk-actions">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="fw-semibold text-body">
                    <i class="fas fa-check-square text-primary me-1"></i>
                    <span id="selectedCount">0</span> item(s) selected
                </span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="clearSelection()">
                    <i class="fas fa-times me-1"></i>Clear
                </button>
                <button class="btn btn-sm btn-danger rounded-pill" onclick="bulkDeletePortfolio()">
                    <i class="fas fa-trash me-1"></i>Delete Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Inline Add Portfolio Form -->
    <div id="portfolioForm" class="mb-4" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="fas fa-plus-circle text-primary me-2"></i>Add Portfolio Items</span>
                <span class="text-muted" style="font-size:0.75rem;">You can add multiple items at once</span>
            </div>
            <div class="card-body" id="portfolioFormRows">
                <div class="port-row border-bottom pb-3 mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Title *</label>
                            <input type="text" name="port_title[]" class="form-control form-control-sm port-title" placeholder="Project title">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Description</label>
                            <input type="text" name="port_desc[]" class="form-control form-control-sm port-desc" placeholder="Short description">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                            <select name="port_cat[]" class="form-select form-select-sm port-cat">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Image</label>
                            <input type="file" name="port_img[]" class="form-control form-control-sm port-img" accept="image/*">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-sm btn-outline-secondary mt-md-3" onclick="removePortRow(this)" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <input type="url" name="port_live[]" class="form-control form-control-sm port-live" placeholder="Live URL (optional)">
                        </div>
                        <div class="col-md-4">
                            <input type="url" name="port_git[]" class="form-control form-control-sm port-git" placeholder="GitHub URL (optional)">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="port_tech[]" class="form-control form-control-sm port-tech" placeholder="Tech stack (comma-separated)">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="addPortRow()">
                    <i class="fas fa-plus me-1"></i>Add Another Item
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleInlineForm('portfolioForm')">Cancel</button>
                    <button class="btn btn-sm rounded-pill px-4 fw-bold text-white" style="background:#0A2D5E;" onclick="savePortfolioBatch()">
                        <i class="fas fa-save me-1"></i>Save All Items
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php if (empty($portfolio)): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:4rem;margin-bottom:1rem;">🖥️</div>
            <h5>No portfolio items yet</h5>
            <p>Showcase your best work to attract freelance clients.</p>
            <button class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;" onclick="toggleInlineForm('portfolioForm')">
                <i class="fas fa-plus me-2"></i>Add Your First Project
            </button>
        </div>
    <?php else: ?>
        <div class="portfolio-grid">
            <?php foreach ($portfolio as $p):
                $techs = array_filter(array_map('trim', explode(',', $p['tech_stack'] ?? '')));
                $isFeatured = !empty($p['featured']);
            ?>
                <div class="port-card<?= $isFeatured ? ' featured' : '' ?>" id="port-card-<?= $p['id'] ?>" data-pid="<?= $p['id'] ?>">
                    <!-- Featured Badge -->
                    <?php if ($isFeatured): ?>
                        <div class="featured-badge">
                            <i class="fas fa-star me-1"></i>Featured
                        </div>
                    <?php endif; ?>

                    <!-- Checkbox -->
                    <div class="port-checkbox">
                        <input type="checkbox" class="form-check-input port-select" onchange="updateBulkActions()" data-pid="<?= $p['id'] ?>">
                    </div>

                    <div class="port-img" style="<?= $p['image_url'] ? '' : 'background:linear-gradient(135deg,#0A2D5E,#2563eb);' ?>">
                        <?php if ($p['image_url']): ?>
                            <img src="<?= $path_to_root . htmlspecialchars($p['image_url']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="<?= htmlspecialchars($p['title']) ?>">
                        <?php else: ?>
                            <i class="fas fa-code text-white" style="font-size:2.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span style="font-size:0.68rem;font-weight:700;background:#eff6ff;color:#1d4ed8;padding:0.18rem 0.55rem;border-radius:50px;">
                                    <?= htmlspecialchars($p['category']) ?>
                                </span>
                            </div>
                            <div class="action-btn-group">
                                <button class="btn <?= $isFeatured ? 'btn-warning' : 'btn-outline-warning' ?>" onclick="toggleFeatured(<?= $p['id'] ?>)" title="<?= $isFeatured ? 'Unmark as Featured' : 'Mark as Featured' ?>">
                                    <i class="fas fa-star"></i>
                                </button>
                                <button class="btn btn-outline-primary" onclick='editPortfolio(<?= json_encode($p, JSON_HEX_APOS) ?>)' title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deletePortfolio(<?= $p['id'] ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <h6 class="fw-bold text-body mb-2"><?= htmlspecialchars($p['title']) ?></h6>
                        <?php if ($p['description']): ?>
                            <p class="text-muted mb-2" style="font-size:0.82rem;line-height:1.5;">
                                <?= nl2br(htmlspecialchars(substr($p['description'], 0, 120))) ?><?= strlen($p['description']) > 120 ? '…' : '' ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($techs): ?>
                            <div class="mb-3">
                                <?php foreach (array_slice($techs, 0, 6) as $t): ?>
                                    <span class="tech-tag"><?= htmlspecialchars($t) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <?php if ($p['live_url']): ?>
                                <a href="<?= htmlspecialchars($p['live_url']) ?>" target="_blank" class="btn btn-sm rounded-pill px-3" style="background:#0A2D5E;color:white;border:none;font-size:0.78rem;">
                                    <i class="fas fa-external-link-alt me-1"></i>Live
                                </a>
                            <?php endif; ?>
                            <?php if ($p['github_url']): ?>
                                <a href="<?= htmlspecialchars($p['github_url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3" style="font-size:0.78rem;">
                                    <i class="fab fa-github me-1"></i>GitHub
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Skills Tab -->
<div id="skills-tab" style="display:none;">
    <!-- Inline Add Skill Form -->
    <div id="skillForm" class="mb-4" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold py-3">
                <i class="fas fa-plus-circle text-success me-2"></i>Add New Skill
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Skill Name *</label>
                        <input type="text" id="sk_name" class="form-control" placeholder="e.g. React, Laravel, Python">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Proficiency Level</label>
                        <select id="sk_level" class="form-select">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate" selected>Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Years Exp.</label>
                        <input type="number" id="sk_yrs" class="form-control" min="0" max="30" value="1">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button class="btn btn-sm rounded-pill px-4 fw-bold text-white" style="background:#0A2D5E;" onclick="saveSkill()">
                            <i class="fas fa-save me-1"></i>Add
                        </button>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleInlineForm('skillForm')">Cancel</button>
                    </div>
                </div>
                <!-- Quick add chips -->
                <div class="mt-3">
                    <span class="text-muted small me-2">Quick add:</span>
                    <?php foreach (['PHP','JavaScript','React','Laravel','Node.js','Python','Vue.js','MySQL','MongoDB','AWS','Docker','TypeScript','Flutter','Swift','Kotlin','CSS3','Bootstrap','REST API','GraphQL','Git'] as $sk): ?>
                        <button class="btn btn-sm rounded-pill mb-1" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:0.72rem;" onclick="quickAddSkill('<?= $sk ?>')">
                            <?= $sk ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php if (empty($skills)): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:4rem;margin-bottom:1rem;">💡</div>
            <h5>No skills listed yet</h5>
            <p>List your technical skills to attract the right jobs.</p>
            <button class="btn btn-primary rounded-pill px-5" style="background:#0A2D5E;border:none;" onclick="toggleInlineForm('skillForm')">
                <i class="fas fa-plus me-2"></i>Add Your First Skill
            </button>
        </div>
    <?php else: ?>
        <!-- Skill Bulk Actions -->
        <div id="skillBulkActions" class="bulk-actions">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold text-body">
                        <i class="fas fa-check-square text-primary me-1"></i>
                        <span id="skillSelectedCount">0</span> skill(s) selected
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="clearSkillSelection()">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                    <button class="btn btn-sm btn-danger rounded-pill" onclick="bulkDeleteSkills()">
                        <i class="fas fa-trash me-1"></i>Delete Selected
                    </button>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-3">
            <div>
                <input type="checkbox" class="form-check-input" id="skillSelectAll" onchange="toggleSkillSelectAll(this)" style="width:18px;height:18px;cursor:pointer;">
            </div>
            <label for="skillSelectAll" class="small text-muted" style="cursor:pointer;">Select all</label>
        </div>
        <div class="row g-4">
            <?php foreach (['expert','advanced','intermediate','beginner'] as $lv):
                $lvSkills = array_filter($skills, fn($s) => $s['level'] === $lv);
                if (empty($lvSkills)) continue;
                [$lvBg,$lvCl] = $levelColors[$lv];
                $pctMap = ['expert'=>100,'advanced'=>75,'intermediate'=>50,'beginner'=>25];
            ?>
                <div class="col-12 col-md-6">
                    <div style="background:white;border-radius:16px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 16px rgba(0,0,0,0.04);padding:1.25rem;">
                        <div class="fw-bold mb-3" style="color:<?= $lvCl ?>;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em;">
                            <i class="fas fa-star me-1"></i><?= ucfirst($lv) ?>
                        </div>
                        <?php foreach ($lvSkills as $s): ?>
                            <div class="skill-row" style="background:<?= $lvBg ?>;border:1px solid <?= $lvCl ?>22;" id="skill-row-<?= $s['id'] ?>">
                                <!-- Checkbox -->
                                <div class="skill-checkbox">
                                    <input type="checkbox" class="form-check-input skill-select" onchange="updateSkillBulkActions()" data-sid="<?= $s['id'] ?>">
                                </div>
                                <div class="flex-grow-1 me-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold" style="font-size:0.87rem;color:#0A2D5E;">
                                            <?= htmlspecialchars($s['skill_name']) ?>
                                        </span>
                                        <span style="font-size:0.7rem;color:#94a3b8;">
                                            <?= $s['years_exp'] ?? 0 ?> yr<?= ($s['years_exp'] ?? 0) != 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                    <div class="skill-level-bar">
                                        <div style="width:<?= $pctMap[$lv] ?>%;height:100%;background:<?= $lvCl ?>;border-radius:50px;transition:width 0.8s ease;"></div>
                                    </div>
                                </div>
                                <div class="action-btn-group ms-2" style="padding-right:24px;">
                                    <button class="btn btn-outline-primary" onclick='editSkill(<?= json_encode($s, JSON_HEX_APOS) ?>)' title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteSkill(<?= $s['id'] ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Inline Edit Portfolio Form (hidden, appears when editing) -->
<div id="editPortfolioForm" style="display:none;">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-edit text-primary me-2"></i>Edit Portfolio Item</span>
        </div>
        <div class="card-body">
            <input type="hidden" id="edit_port_id" value="">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold text-muted">Project Title *</label>
                    <input type="text" id="edit_port_title" class="form-control" placeholder="Project title">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Category</label>
                    <select id="edit_port_cat" class="form-select">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c ?>"><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Description</label>
                    <textarea id="edit_port_desc" class="form-control" rows="2" placeholder="Short description"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Tech Stack</label>
                    <input type="text" id="edit_port_tech" class="form-control" placeholder="e.g. PHP, MySQL">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Live URL</label>
                    <input type="url" id="edit_port_live" class="form-control" placeholder="https://...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">GitHub URL</label>
                    <input type="url" id="edit_port_git" class="form-control" placeholder="https://...">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Change Image <span class="text-muted fw-normal">(leave empty to keep current)</span></label>
                    <input type="file" id="edit_port_img" class="form-control" accept="image/*">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-sm rounded-pill px-4 fw-bold text-white" style="background:#0A2D5E;" onclick="updatePortfolio()">
                <i class="fas fa-save me-1"></i>Update
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="cancelEditPortfolio()">Cancel</button>
            <span id="editPortMsg" class="text-muted small align-self-center"></span>
        </div>
    </div>
</div>

<script>
function switchTab(showId, hideId, btn) {
    document.getElementById(showId).style.display = 'block';
    document.getElementById(hideId).style.display = 'none';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ==================== BULK ACTIONS ====================
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.port-select:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkActions').style.display = count > 0 ? 'block' : 'none';
}

function clearSelection() {
    document.querySelectorAll('.port-select').forEach(cb => cb.checked = false);
    updateBulkActions();
}

function bulkDeletePortfolio() {
    const checkboxes = document.querySelectorAll('.port-select:checked');
    const ids = Array.from(checkboxes).map(cb => cb.dataset.pid);
    if (ids.length === 0) {
        Swal.fire({ icon: 'info', title: 'No items selected', confirmButtonColor: '#0A2D5E' });
        return;
    }
    Swal.fire({
        title: 'Delete ' + ids.length + ' item(s)?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Delete All',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'bulk_delete_portfolio');
        ids.forEach(id => fd.append('ids[]', id));
        Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch('dev_portfolio.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', text: d.message, confirmButtonColor: '#0A2D5E', timer: 2500, timerProgressBar: true }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message, confirmButtonColor: '#dc3545' });
            }
        }).catch((err) => { Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not delete.', confirmButtonColor: '#dc3545' }); });
    });
}

// ==================== TOGGLE FEATURED ====================
function toggleFeatured(pid) {
    const fd = new FormData();
    fd.append('ajax_action', 'toggle_featured');
    fd.append('portfolio_id', pid);
    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('dev_portfolio.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.success) {
            const card = document.getElementById('port-card-' + pid);
            if (d.featured) {
                card.classList.add('featured');
                // Add featured badge
                let badge = card.querySelector('.featured-badge');
                if (!badge) {
                    badge = document.createElement('div');
                    badge.className = 'featured-badge';
                    badge.innerHTML = '<i class="fas fa-star me-1"></i>Featured';
                    card.insertBefore(badge, card.firstChild);
                }
                // Update button
                const btn = card.querySelector('.btn-outline-warning');
                if (btn) { btn.classList.remove('btn-outline-warning'); btn.classList.add('btn-warning'); btn.title = 'Unmark as Featured'; }
            } else {
                card.classList.remove('featured');
                const badge = card.querySelector('.featured-badge');
                if (badge) badge.remove();
                const btn = card.querySelector('.btn-warning');
                if (btn) { btn.classList.remove('btn-warning'); btn.classList.add('btn-outline-warning'); btn.title = 'Mark as Featured'; }
            }
            Swal.fire({ icon: 'success', title: 'Done!', text: d.message, confirmButtonColor: '#0A2D5E', timer: 2000, timerProgressBar: true });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: d.message, confirmButtonColor: '#dc3545' });
        }
    }).catch((err) => { Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not update.', confirmButtonColor: '#dc3545' }); });
}

function toggleInlineForm(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ==================== PORTFOLIO BATCH ADD ====================

function addPortRow() {
    const rows = document.getElementById('portfolioFormRows');
    const firstRow = rows.querySelector('.port-row');
    if (!firstRow) return;
    const clone = firstRow.cloneNode(true);
    // Clear inputs
    clone.querySelectorAll('input, select, textarea').forEach(el => el.value = '');
    rows.appendChild(clone);
}

function removePortRow(btn) {
    const row = btn.closest('.port-row');
    const rows = document.getElementById('portfolioFormRows');
    if (rows.querySelectorAll('.port-row').length > 1) {
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
    } else {
        Swal.fire({
            icon: 'info',
            title: 'Keep at least one row',
            text: 'Fill it or cancel the form.',
            confirmButtonColor: '#0A2D5E'
        });
    }
}

function savePortfolioBatch() {
    const rows = document.querySelectorAll('#portfolioFormRows .port-row');
    const titles = []; const descs = []; const cats = []; const techs = []; const lives = []; const gits = [];
    let hasTitle = false;
    rows.forEach(r => {
        const t = r.querySelector('.port-title').value.trim();
        titles.push(t);
        descs.push(r.querySelector('.port-desc').value.trim());
        cats.push(r.querySelector('.port-cat').value);
        techs.push(r.querySelector('.port-tech').value.trim());
        lives.push(r.querySelector('.port-live').value.trim());
        gits.push(r.querySelector('.port-git').value.trim());
        if (t) hasTitle = true;
    });
    if (!hasTitle) {
        Swal.fire({
            icon: 'warning',
            title: 'Required',
            text: 'At least one item needs a title.',
            confirmButtonColor: '#0A2D5E'
        });
        return;
    }
    const fd = new FormData();
    fd.append('ajax_action', 'add_portfolio_batch');
    titles.forEach((t, i) => {
        fd.append('title[' + i + ']', t || '');
        fd.append('description[' + i + ']', descs[i] || '');
        fd.append('category[' + i + ']', cats[i] || 'Web');
        fd.append('tech_stack[' + i + ']', techs[i] || '');
        fd.append('live_url[' + i + ']', lives[i] || '');
        fd.append('github_url[' + i + ']', gits[i] || '');
    });
    // Append image files
    const imgInputs = document.querySelectorAll('#portfolioFormRows .port-img');
    imgInputs.forEach((input, i) => {
        if (input.files && input.files[0]) {
            fd.append('image[' + i + ']', input.files[0]);
        }
    });
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    fetch('dev_portfolio.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(d => {
        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Items Added!',
                text: d.message,
                confirmButtonColor: '#0A2D5E',
                timer: 2500,
                timerProgressBar: true
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message,
                confirmButtonColor: '#dc3545'
            });
        }
    }).catch((err) => {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not save. ' + (err.message || ''),
            confirmButtonColor: '#dc3545'
        });
    });
}

// ==================== PORTFOLIO EDIT / DELETE ====================

function editPortfolio(p) {
    document.getElementById('edit_port_id').value = p.id;
    document.getElementById('edit_port_title').value = p.title || '';
    document.getElementById('edit_port_desc').value = p.description || '';
    document.getElementById('edit_port_tech').value = p.tech_stack || '';
    document.getElementById('edit_port_live').value = p.live_url || '';
    document.getElementById('edit_port_git').value = p.github_url || '';
    document.getElementById('edit_port_cat').value = p.category || 'Web';
    document.getElementById('editPortfolioForm').style.display = 'block';
    document.getElementById('editPortfolioForm').scrollIntoView({behavior:'smooth',block:'start'});
}

function cancelEditPortfolio() {
    document.getElementById('editPortfolioForm').style.display = 'none';
}

function updatePortfolio() {
    const id = document.getElementById('edit_port_id').value;
    const title = document.getElementById('edit_port_title').value.trim();
    if (!title) {
        Swal.fire({
            icon: 'warning',
            title: 'Required',
            text: 'Project title is required.',
            confirmButtonColor: '#0A2D5E'
        });
        return;
    }
    const fd = new FormData();
    fd.append('ajax_action', 'update_portfolio');
    fd.append('portfolio_id', id);
    fd.append('title', title);
    fd.append('description', document.getElementById('edit_port_desc').value.trim());
    fd.append('tech_stack', document.getElementById('edit_port_tech').value.trim());
    fd.append('live_url', document.getElementById('edit_port_live').value.trim());
    fd.append('github_url', document.getElementById('edit_port_git').value.trim());
    fd.append('category', document.getElementById('edit_port_cat').value);
    const imgInput = document.getElementById('edit_port_img');
    if (imgInput.files && imgInput.files[0]) {
        fd.append('image', imgInput.files[0]);
    }
    document.getElementById('editPortMsg').textContent = 'Saving...';
    fetch('dev_portfolio.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(d => {
        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: d.message,
                confirmButtonColor: '#0A2D5E',
                timer: 2500,
                timerProgressBar: true
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message,
                confirmButtonColor: '#dc3545'
            });
            document.getElementById('editPortMsg').textContent = '';
        }
    }).catch((err) => {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not update. ' + (err.message || ''),
            confirmButtonColor: '#dc3545'
        });
        document.getElementById('editPortMsg').textContent = '';
    });
}

function deletePortfolio(id) {
    Swal.fire({
        title: 'Delete this project?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'delete_portfolio');
        fd.append('portfolio_id', id);
        Swal.fire({
            title: 'Deleting...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        fetch('dev_portfolio.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const card = document.getElementById('port-card-' + id);
                if (card) {
                    card.style.transition = 'all 0.4s ease';
                    card.style.transform = 'scale(0.8)';
                    card.style.opacity = '0';
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: d.message,
                    confirmButtonColor: '#0A2D5E',
                    timer: 2500,
                    timerProgressBar: true
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: d.message,
                    confirmButtonColor: '#dc3545'
                });
            }
        }).catch((err) => {
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Could not delete. ' + (err.message || ''),
                confirmButtonColor: '#dc3545'
            });
        });
    });
}

// ==================== SKILL CRUD (inline) ====================

function quickAddSkill(name) {
    document.getElementById('sk_name').value = name;
    saveSkill();
}

function saveSkill() {
    const name = document.getElementById('sk_name').value.trim();
    const level = document.getElementById('sk_level').value;
    const yrs = document.getElementById('sk_yrs').value;
    if (!name) {
        Swal.fire({
            icon: 'warning',
            title: 'Required',
            text: 'Skill name is required.',
            confirmButtonColor: '#0A2D5E'
        });
        return;
    }
    const fd = new FormData();
    fd.append('ajax_action', 'add_skill');
    fd.append('skill_name', name);
    fd.append('level', level);
    fd.append('years_exp', yrs);
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    fetch('dev_portfolio.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(d => {
        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Skill Added!',
                text: d.message,
                confirmButtonColor: '#0A2D5E',
                timer: 2000,
                timerProgressBar: true
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message,
                confirmButtonColor: '#dc3545'
            });
        }
    }).catch((err) => {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Could not save. ' + (err.message || ''),
            confirmButtonColor: '#dc3545'
        });
    });
}

function editSkill(s) {
    Swal.fire({
        title: 'Edit Skill',
        html: `
            <input id="swal-sk-name" class="swal2-input" placeholder="Skill name" value="${s.skill_name}">
            <select id="swal-sk-level" class="swal2-input">
                <option value="beginner" ${s.level === 'beginner' ? 'selected' : ''}>Beginner</option>
                <option value="intermediate" ${s.level === 'intermediate' ? 'selected' : ''}>Intermediate</option>
                <option value="advanced" ${s.level === 'advanced' ? 'selected' : ''}>Advanced</option>
                <option value="expert" ${s.level === 'expert' ? 'selected' : ''}>Expert</option>
            </select>
            <input id="swal-sk-yrs" class="swal2-input" type="number" min="0" max="30" value="${s.years_exp || 0}">
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-1"></i> Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0A2D5E',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        preConfirm: () => {
            const n = document.getElementById('swal-sk-name').value.trim();
            const l = document.getElementById('swal-sk-level').value;
            const y = document.getElementById('swal-sk-yrs').value;
            if (!n) {
                Swal.showValidationMessage('Skill name required');
                return false;
            }
            return {name: n, level: l, yrs: y};
        }
    }).then(r => {
        if (!r.isConfirmed || !r.value) return;
        const v = r.value;
        const fd = new FormData();
        fd.append('ajax_action', 'update_skill');
        fd.append('skill_id', s.id);
        fd.append('skill_name', v.name);
        fd.append('level', v.level);
        fd.append('years_exp', v.yrs);
        Swal.fire({
            title: 'Updating...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        fetch('dev_portfolio.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: d.message,
                    confirmButtonColor: '#0A2D5E',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: d.message,
                    confirmButtonColor: '#dc3545'
                });
            }
        }).catch((err) => {
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Could not update. ' + (err.message || ''),
                confirmButtonColor: '#dc3545'
            });
        });
    });
}

// ==================== SKILL BULK ACTIONS ====================
function updateSkillBulkActions() {
    const checkboxes = document.querySelectorAll('.skill-select:checked');
    const count = checkboxes.length;
    document.getElementById('skillSelectedCount').textContent = count;
    document.getElementById('skillBulkActions').style.display = count > 0 ? 'block' : 'none';
}

function clearSkillSelection() {
    document.querySelectorAll('.skill-select').forEach(cb => cb.checked = false);
    document.getElementById('skillSelectAll').checked = false;
    updateSkillBulkActions();
}

function toggleSkillSelectAll(el) {
    document.querySelectorAll('.skill-select').forEach(cb => cb.checked = el.checked);
    updateSkillBulkActions();
}

function bulkDeleteSkills() {
    const checkboxes = document.querySelectorAll('.skill-select:checked');
    const ids = Array.from(checkboxes).map(cb => cb.dataset.sid);
    if (ids.length === 0) {
        Swal.fire({ icon: 'info', title: 'No skills selected', confirmButtonColor: '#0A2D5E' });
        return;
    }
    Swal.fire({
        title: 'Delete ' + ids.length + ' skill(s)?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Delete All',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'bulk_delete_skills');
        ids.forEach(id => fd.append('ids[]', id));
        Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch('dev_portfolio.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', text: d.message, confirmButtonColor: '#0A2D5E', timer: 2500, timerProgressBar: true }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.message, confirmButtonColor: '#dc3545' });
            }
        }).catch((err) => { Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not delete.', confirmButtonColor: '#dc3545' }); });
    });
}

function deleteSkill(id) {
    Swal.fire({
        title: 'Remove this skill?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('ajax_action', 'delete_skill');
        fd.append('skill_id', id);
        Swal.fire({
            title: 'Removing...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        fetch('dev_portfolio.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const row = document.getElementById('skill-row-' + id);
                if (row) {
                    row.style.transition = 'all 0.4s ease';
                    row.style.transform = 'translateX(50px)';
                    row.style.opacity = '0';
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Removed!',
                    text: d.message,
                    confirmButtonColor: '#0A2D5E',
                    timer: 2500,
                    timerProgressBar: true
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: d.message,
                    confirmButtonColor: '#dc3545'
                });
            }
        }).catch((err) => {
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Could not remove. ' + (err.message || ''),
                confirmButtonColor: '#dc3545'
            });
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
