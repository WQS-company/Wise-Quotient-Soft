<?php
$page_title = 'Manage Blog Tags';
$path_to_root = '../';
require_once $path_to_root . 'includes/dashboard_header.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (!$slug) $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO blog_tags (name, slug) VALUES (?, ?)");
                $stmt->execute([$name, $slug]);
                $message = 'Tag created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') ? 'Tag already exists.' : $e->getMessage());
                $messageType = 'warning';
            }
        } else {
            $message = 'Tag name is required!';
            $messageType = 'warning';
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (!$slug) $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        if ($id && $name) {
            try {
                $stmt = $pdo->prepare("UPDATE blog_tags SET name=?, slug=? WHERE id=?");
                $stmt->execute([$name, $slug, $id]);
                $message = 'Tag updated successfully!';
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
                $pdo->prepare("DELETE FROM blog_post_tags WHERE tag_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM blog_tags WHERE id = ?")->execute([$id]);
                $message = 'Tag deleted successfully.';
                $messageType = 'warning';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'warning';
            }
        }
    }
}

// Fetch all tags with post count
$tags = $pdo->query("
    SELECT bt.*, (SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = bt.id) AS post_count
    FROM blog_tags bt ORDER BY bt.name ASC
")->fetchAll();

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM blog_tags WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}
?>

<style>
.bt-wrap { font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }
.bt-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem; }
.bt-header h2 { font-weight:800; color:#0f172a; font-size:1.6rem; margin:0; }
.bt-alert { padding:.85rem 1.25rem; border-radius:12px; margin-bottom:1.5rem; font-weight:600; font-size:.9rem; display:flex; align-items:center; gap:.6rem; animation:slideDown .4s ease; }
.bt-alert.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.bt-alert.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.bt-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; overflow:hidden; margin-bottom:2rem; }
.bt-card-header { background:linear-gradient(135deg,#f8fafc,#f1f5f9); padding:1rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
.bt-card-header h5 { font-weight:700; margin:0; color:#1e293b; font-size:1rem; }
.bt-table { width:100%; border-collapse:collapse; }
.bt-table thead th { background:var(--color-bg); padding:.75rem 1rem; font-size:.77rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.bt-table tbody td { padding:.75rem 1rem; font-size:.87rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.bt-table tbody tr:hover { background:var(--color-bg); }
.tag-badge { display:inline-block; background:#f1f5f9; color:#475569; padding:.15rem .6rem; border-radius:50px; font-size:.75rem; font-weight:600; border:1px solid #e2e8f0; }
.btn-bt-edit { background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; text-decoration:none; display:inline-block; transition:all .2s; }
.btn-bt-edit:hover { background:#3b82f6; color:white; }
.btn-bt-delete { background:#fef2f2; color:#ef4444; border:1px solid #fecaca; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bt-delete:hover { background:#ef4444; color:white; }
.bt-form-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; padding:2rem; margin-bottom:2rem; }
.bt-form-card h4 { font-weight:800; color:#0f172a; margin-bottom:1.25rem; font-size:1.15rem; }
.bt-form-card label { font-weight:600; font-size:.85rem; color:#334155; margin-bottom:.3rem; }
.bt-form-card .form-control { border-radius:10px; border:1.5px solid #e2e8f0; padding:.6rem .9rem; font-size:.9rem; transition:border-color .2s; }
.bt-form-card .form-control:focus { border-color:#ff6600; box-shadow:0 0 0 3px rgba(255,102,0,.1); }
.btn-bt-submit { background:linear-gradient(135deg,#ff6600,#e65c00); color:white; border:none; padding:.65rem 1.5rem; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; transition:all .25s; }
.btn-bt-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(255,102,0,.3); }
.btn-bt-cancel { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding:.65rem 1.5rem; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; text-decoration:none; transition:all .2s; }
.btn-bt-cancel:hover { background:#e2e8f0; color:#1e293b; }
@media(max-width:768px) { .bt-form-card { padding:1.25rem; } }
.bt-quick-add { display:flex; gap:.5rem; margin-bottom:1.25rem; }
.bt-quick-add input { flex:1; }
</style>

<div class="bt-wrap container-fluid px-lg-4">

<?php if ($message): ?>
<div class="bt-alert <?= $messageType ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="bt-header">
    <div>
        <h2><i class="fas fa-tags me-2" style="color:#ff6600;"></i> Blog Tags</h2>
        <p class="text-muted mb-0" style="font-size:.88rem;">Create and manage tags for blog post categorization.</p>
    </div>
    <a href="?add=1" class="btn btn-sm btn-orange rounded-pill px-3 fw-bold <?= !isset($_GET['add']) && !$editItem ? '' : 'd-none' ?>">
        <i class="fas fa-plus me-1"></i> New Tag
    </a>
</div>

<?php if ($editItem || isset($_GET['add'])): ?>
<!-- Quick Add / Edit Form -->
<div class="bt-form-card">
    <h4><i class="fas <?= $editItem ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2" style="color:#ff6600;"></i>
        <?= $editItem ? 'Edit Tag' : 'Add New Tag' ?></h4>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
        <?php if ($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Tag Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. JavaScript" required
                       value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                       oninput="document.getElementsByName('slug')[0].value = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')">
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" placeholder="auto-generated" value="<?= htmlspecialchars($editItem['slug'] ?? '') ?>">
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-bt-submit"><i class="fas fa-save me-1"></i> <?= $editItem ? 'Update' : 'Save' ?></button>
            <a href="blog-tags.php" class="btn-bt-cancel">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Tags List -->
<div class="bt-card">
    <div class="bt-card-header">
        <h5><i class="fas fa-list me-2" style="color:#ff6600;"></i> All Tags</h5>
        <span class="badge" style="background:linear-gradient(135deg,#ff6600,#e65c00);color:white;padding:.3rem .75rem;border-radius:50px;font-size:.75rem;font-weight:700;">
            <?= count($tags) ?> Tags
        </span>
    </div>
    <div class="table-responsive">
        <table class="bt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tag</th>
                    <th>Slug</th>
                    <th>Posts</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><?= $tag['id'] ?></td>
                    <td><span class="tag-badge"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($tag['name']) ?></span></td>
                    <td><code><?= htmlspecialchars($tag['slug']) ?></code></td>
                    <td><span class="badge bg-body-tertiary text-body border px-3 py-2 rounded-pill"><?= (int)$tag['post_count'] ?></span></td>
                    <td class="small text-muted"><?= date('M d, Y', strtotime($tag['created_at'])) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?edit=<?= $tag['id'] ?>" class="btn-bt-edit" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tag? It will be removed from all posts.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                <button type="submit" class="btn-bt-delete" title="Delete"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tags)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="fas fa-tags fa-2x mb-3 d-block" style="color:#cbd5e1;"></i>
                    No tags yet. Create your first tag!
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
