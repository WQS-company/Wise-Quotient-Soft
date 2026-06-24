<?php
$page_title = 'Manage Blog Categories';
$path_to_root = '../';
require_once $path_to_root . 'includes/dashboard_header.php';

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#ff6600');
        if (!$slug) $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO blog_categories (name, slug, description, color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $desc, $color]);
                $message = 'Category created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'warning';
            }
        } else {
            $message = 'Category name is required!';
            $messageType = 'warning';
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#ff6600');
        if (!$slug) $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        if ($id && $name) {
            try {
                $stmt = $pdo->prepare("UPDATE blog_categories SET name=?, slug=?, description=?, color=? WHERE id=?");
                $stmt->execute([$name, $slug, $desc, $color, $id]);
                $message = 'Category updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'warning';
            }
        } else {
            $message = 'Invalid request!';
            $messageType = 'warning';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("UPDATE blog_posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$id]);
                $message = 'Category deleted successfully.';
                $messageType = 'warning';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'warning';
            }
        }
    }
}

// Fetch all categories with post count
$categories = $pdo->query("
    SELECT bc.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id = bc.id) AS post_count
    FROM blog_categories bc ORDER BY bc.name ASC
")->fetchAll();

// Editing?
$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}
?>

<style>
.bc-wrap { font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }
.bc-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem; }
.bc-header h2 { font-weight:800; color:#0f172a; font-size:1.6rem; margin:0; }
.bc-alert { padding:.85rem 1.25rem; border-radius:12px; margin-bottom:1.5rem; font-weight:600; font-size:.9rem; display:flex; align-items:center; gap:.6rem; animation:slideDown .4s ease; }
.bc-alert.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.bc-alert.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.bc-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; overflow:hidden; margin-bottom:2rem; }
.bc-card-header { background:linear-gradient(135deg,#f8fafc,#f1f5f9); padding:1rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
.bc-card-header h5 { font-weight:700; margin:0; color:#1e293b; font-size:1rem; }
.bc-table { width:100%; border-collapse:collapse; }
.bc-table thead th { background:var(--color-bg); padding:.75rem 1rem; font-size:.77rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.bc-table tbody td { padding:.75rem 1rem; font-size:.87rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.bc-table tbody tr:hover { background:var(--color-bg); }
.color-swatch { display:inline-block; width:18px; height:18px; border-radius:50%; border:2px solid #e2e8f0; vertical-align:middle; }
.btn-bc-edit { background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; text-decoration:none; display:inline-block; transition:all .2s; }
.btn-bc-edit:hover { background:#3b82f6; color:white; }
.btn-bc-delete { background:#fef2f2; color:#ef4444; border:1px solid #fecaca; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bc-delete:hover { background:#ef4444; color:white; }
.bc-form-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; padding:2rem; margin-bottom:2rem; }
.bc-form-card h4 { font-weight:800; color:#0f172a; margin-bottom:1.25rem; font-size:1.15rem; }
.bc-form-card label { font-weight:600; font-size:.85rem; color:#334155; margin-bottom:.3rem; }
.bc-form-card .form-control,.bc-form-card .form-select { border-radius:10px; border:1.5px solid #e2e8f0; padding:.6rem .9rem; font-size:.9rem; transition:border-color .2s; }
.bc-form-card .form-control:focus,.bc-form-card .form-select:focus { border-color:#ff6600; box-shadow:0 0 0 3px rgba(255,102,0,.1); }
.btn-bc-submit { background:linear-gradient(135deg,#ff6600,#e65c00); color:white; border:none; padding:.65rem 1.5rem; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; transition:all .25s; }
.btn-bc-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(255,102,0,.3); }
.btn-bc-cancel { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding:.65rem 1.5rem; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; text-decoration:none; transition:all .2s; }
.btn-bc-cancel:hover { background:#e2e8f0; color:#1e293b; }
@media(max-width:768px) { .bc-form-card { padding:1.25rem; } }
</style>

<div class="bc-wrap container-fluid px-lg-4">

<?php if ($message): ?>
<div class="bc-alert <?= $messageType ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="bc-header">
    <div>
        <h2><i class="fas fa-folder me-2" style="color:#ff6600;"></i> Blog Categories</h2>
        <p class="text-muted mb-0" style="font-size:.88rem;">Organize blog posts into structured categories.</p>
    </div>
    <a href="?add=1" class="btn btn-sm btn-orange rounded-pill px-3 fw-bold <?= !isset($_GET['add']) && !$editItem ? '' : 'd-none' ?>">
        <i class="fas fa-plus me-1"></i> New Category
    </a>
</div>

<?php if ($editItem || isset($_GET['add'])): ?>
<!-- Add/Edit Form -->
<div class="bc-form-card">
    <h4><i class="fas <?= $editItem ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2" style="color:#ff6600;"></i>
        <?= $editItem ? 'Edit Category' : 'Add New Category' ?></h4>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
        <?php if ($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Cloud Computing" required
                       value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                       oninput="document.getElementsByName('slug')[0].value = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')">
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" placeholder="auto-generated" value="<?= htmlspecialchars($editItem['slug'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" placeholder="Brief description of this category"
                       value="<?= htmlspecialchars($editItem['description'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Color</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" name="color" class="form-control form-control-color" style="width:50px;height:45px;padding:3px;"
                           value="<?= htmlspecialchars($editItem['color'] ?? '#ff6600') ?>">
                    <span class="small text-muted" id="colorValue"><?= htmlspecialchars($editItem['color'] ?? '#ff6600') ?></span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-bc-submit"><i class="fas fa-save me-1"></i> <?= $editItem ? 'Update' : 'Save' ?></button>
            <a href="blog-categories.php" class="btn-bc-cancel">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Categories List -->
<div class="bc-card">
    <div class="bc-card-header">
        <h5><i class="fas fa-list me-2" style="color:#ff6600;"></i> All Categories</h5>
        <span class="badge" style="background:linear-gradient(135deg,#ff6600,#e65c00);color:white;padding:.3rem .75rem;border-radius:50px;font-size:.75rem;font-weight:700;">
            <?= count($categories) ?> Categories
        </span>
    </div>
    <div class="table-responsive">
        <table class="bc-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Color</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Posts</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td><span class="color-swatch" style="background:<?= htmlspecialchars($cat['color']) ?>;"></span></td>
                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                    <td class="text-muted"><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                    <td><span class="badge bg-body-tertiary text-body border px-3 py-2 rounded-pill"><?= (int)$cat['post_count'] ?></span></td>
                    <td class="small text-muted"><?= date('M d, Y', strtotime($cat['created_at'])) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?edit=<?= $cat['id'] ?>" class="btn-bc-edit" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if ((int)$cat['post_count'] === 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn-bc-delete" title="Delete"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-folder-open fa-2x mb-3 d-block" style="color:#cbd5e1;"></i>
                    No categories yet. Create your first category!
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<script>
document.querySelectorAll('input[type="color"]').forEach(el => {
    el.addEventListener('input', function() {
        document.getElementById('colorValue').textContent = this.value;
    });
});
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
