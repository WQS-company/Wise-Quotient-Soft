<?php
$page_title = 'Manage Blog Posts';
$path_to_root = '../';
require_once $path_to_root . 'includes/dashboard_header.php';

$message = '';
$messageType = '';

// Upload directory
$blogUploadDir = __DIR__ . '/uploads/blog_images';
if (!is_dir($blogUploadDir)) mkdir($blogUploadDir, 0755, true);

// ===== Helper: generate unique slug =====
function generateSlug($title, $pdo, $excludeId = null) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($title)));
    $slug = trim($slug, '-');
    if (empty($slug)) $slug = 'post-' . time();
    $original = $slug;
    $i = 1;
    while (true) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?" . ($excludeId ? " AND id != ?" : ""));
        if ($excludeId) { $q->execute([$slug, $excludeId]); } else { $q->execute([$slug]); }
        if ((int)$q->fetchColumn() === 0) break;
        $slug = $original . '-' . $i++;
    }
    return $slug;
}

// ===== Handle POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title          = trim($_POST['title'] ?? '');
        $slug           = trim($_POST['slug'] ?? '');
        $content        = trim($_POST['content'] ?? '');
        $excerpt        = trim($_POST['excerpt'] ?? '');
        $category_id    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $tag_ids        = $_POST['tag_ids'] ?? [];
        $author_id      = (int)($_POST['author_id'] ?? 0);
        $post_status    = in_array($_POST['status'] ?? '', ['draft','published','trash']) ? $_POST['status'] : 'draft';
        $is_featured    = !empty($_POST['is_featured']) ? 1 : 0;
        $meta_title     = trim($_POST['meta_title'] ?? '');
        $meta_desc      = trim($_POST['meta_description'] ?? '');
        $published_at   = trim($_POST['published_at'] ?? '');
        $cover_image    = null;
        $media_type     = 'image';
        $editId         = $action === 'edit' ? (int)($_POST['id'] ?? 0) : 0;

        // Generate slug if empty
        if (empty($slug)) {
            $slug = generateSlug($title, $pdo, $editId ?: null);
        } else {
            $slug = generateSlug($slug, $pdo, $editId ?: null);
        }

        if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES['cover_image']['tmp_name'];
            $fileMime = mime_content_type($tmpName);
            $is_video = strpos($fileMime, 'video') !== false;
            $media_type = $is_video ? 'video' : 'image';
            require_once dirname(__DIR__) . '/includes/cloudinary.php';
            $cloudUrl = uploadToCloudinary($tmpName, 'blog_media', 'auto');
            if ($cloudUrl) $cover_image = $cloudUrl;
        }

        if ($action === 'add') {
            if ($title && $content && $author_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, category_id, cover_image, media_type, status, author_id, is_featured, meta_title, meta_description, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $content, $excerpt, $category_id, $cover_image, $media_type, $post_status, $author_id, $is_featured, $meta_title, $meta_desc, $published_at ?: null]);
                $newId = $pdo->lastInsertId();

                // Attach tags
                if (!empty($tag_ids)) {
                    $insTag = $pdo->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                    foreach ($tag_ids as $tid) { $insTag->execute([$newId, (int)$tid]); }
                }

                // Push notification when blog is published
                if ($post_status === 'published') {
                    require_once __DIR__ . '/../includes/fcm_helper.php';
                    FCMHelper::sendNotificationToAll(
                        "New Blog Post: " . $title,
                        $excerpt ?: "A new article has been published on WQS. Tap to read more.",
                        ['click_action' => '/dashboard/wqs/blog.php?slug=' . $slug]
                    );
                }

                $message = $post_status === 'published' ? 'Blog post published successfully!' : 'Blog post saved as draft!';
                $messageType = 'success';
            } else {
                $message = 'Please fill in all required fields!';
                $messageType = 'warning';
            }
        }

        if ($action === 'edit') {
            if ($editId && $title && $content && $author_id > 0) {
                if ($cover_image) {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, category_id=?, cover_image=?, media_type=?, status=?, author_id=?, is_featured=?, meta_title=?, meta_description=?, published_at=? WHERE id=?");
                    $stmt->execute([$title, $slug, $content, $excerpt, $category_id, $cover_image, $media_type, $post_status, $author_id, $is_featured, $meta_title, $meta_desc, $published_at ?: null, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, category_id=?, status=?, author_id=?, is_featured=?, meta_title=?, meta_description=?, published_at=? WHERE id=?");
                    $stmt->execute([$title, $slug, $content, $excerpt, $category_id, $post_status, $author_id, $is_featured, $meta_title, $meta_desc, $published_at ?: null, $editId]);
                }

                // Re-attach tags
                $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$editId]);
                if (!empty($tag_ids)) {
                    $insTag = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                    foreach ($tag_ids as $tid) { $insTag->execute([$editId, (int)$tid]); }
                }

                $message = 'Blog post updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Please fill in all required fields!';
                $messageType = 'warning';
            }
        }
    }

    if ($action === 'quick_status') {
        $id         = (int)($_POST['id'] ?? 0);
        $new_status = in_array($_POST['new_status'] ?? '', ['draft','published','trash']) ? $_POST['new_status'] : null;
        if ($id && $new_status) {
            $pdo->prepare("UPDATE blog_posts SET status=? WHERE id=?")->execute([$new_status, $id]);
            $label = ['draft'=>'moved to Drafts','published'=>'published','trash'=>'moved to Trash'];
            $message = 'Post ' . ($label[$new_status] ?? 'updated') . '.';
            $messageType = $new_status === 'published' ? 'success' : 'warning';

            // Push notification when published
            if ($new_status === 'published') {
                $pTitle = $pdo->prepare("SELECT title, slug, excerpt FROM blog_posts WHERE id = ?");
                $pTitle->execute([$id]);
                $post = $pTitle->fetch(PDO::FETCH_ASSOC);
                if ($post) {
                    require_once __DIR__ . '/../includes/fcm_helper.php';
                    FCMHelper::sendNotificationToAll(
                        "New Blog Post: " . $post['title'],
                        $post['excerpt'] ?: "A new article has been published on WQS. Tap to read more.",
                        ['click_action' => '/dashboard/wqs/blog.php?slug=' . $post['slug']]
                    );
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $row = $pdo->prepare("SELECT cover_image FROM blog_posts WHERE id = ?");
            $row->execute([$id]);
            $old = $row->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) @unlink(__DIR__ . '/../' . $old);
            $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
            $message = 'Blog post permanently deleted.';
            $messageType = 'warning';
        }
    }

    if ($action === 'bulk_action') {
        $bulkAction = $_POST['bulk_action'] ?? '';
        $postIds    = $_POST['post_ids'] ?? [];
        if ($bulkAction && !empty($postIds)) {
            $ids = array_map('intval', $postIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'publish') {
                $pdo->prepare("UPDATE blog_posts SET status='published' WHERE id IN ($placeholders)")->execute($ids);
                $message = count($ids) . ' post(s) published.';
            } elseif ($bulkAction === 'draft') {
                $pdo->prepare("UPDATE blog_posts SET status='draft' WHERE id IN ($placeholders)")->execute($ids);
                $message = count($ids) . ' post(s) moved to drafts.';
            } elseif ($bulkAction === 'trash') {
                $pdo->prepare("UPDATE blog_posts SET status='trash' WHERE id IN ($placeholders)")->execute($ids);
                $message = count($ids) . ' post(s) moved to trash.';
            } elseif ($bulkAction === 'delete') {
                $rows = $pdo->prepare("SELECT id, cover_image FROM blog_posts WHERE id IN ($placeholders)");
                $rows->execute($ids);
                foreach ($rows as $r) {
                    if ($r['cover_image'] && file_exists(__DIR__ . '/../' . $r['cover_image'])) @unlink(__DIR__ . '/../' . $r['cover_image']);
                }
                $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders)")->execute($ids);
                $message = count($ids) . ' post(s) permanently deleted.';
            } elseif ($bulkAction === 'feature') {
                $pdo->prepare("UPDATE blog_posts SET is_featured = CASE WHEN id IN ($placeholders) THEN 1 ELSE 0 END")->execute($ids);
                $message = count($ids) . ' post(s) set as featured.';
            }
            $messageType = 'success';
        }
    }
}

// ===== Fetch with search + filtering + pagination =====
$activeTab = in_array($_GET['tab'] ?? '', ['published','draft','trash']) ? $_GET['tab'] : 'all';
$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['p'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where = "1";
$params = [];
if ($activeTab !== 'all') {
    $where .= " AND bp.status = ?";
    $params[] = $activeTab;
}
if ($search) {
    $where .= " AND (bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Count total
$countQ = $pdo->prepare("SELECT COUNT(*) FROM blog_posts bp WHERE $where");
$countQ->execute($params);
$totalPosts = (int)$countQ->fetchColumn();
$totalPages = max(1, ceil($totalPosts / $perPage));

// Fetch posts
$sql = "SELECT bp.*, u.name as author_name, bc.name as category_name, bc.color as category_color
        FROM blog_posts bp
        JOIN users u ON bp.author_id = u.id
        LEFT JOIN blog_categories bc ON bp.category_id = bc.id
        WHERE $where
        ORDER BY bp.created_at DESC LIMIT $perPage OFFSET $offset";
$posts = $pdo->prepare($sql);
$posts->execute($params);
$posts = $posts->fetchAll();

// Fetch tags for each post
$postTags = [];
if (!empty($posts)) {
    $ids = array_column($posts, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tagStmt = $pdo->prepare("SELECT bpt.post_id, bt.id, bt.name FROM blog_post_tags bpt JOIN blog_tags bt ON bpt.tag_id = bt.id WHERE bpt.post_id IN ($placeholders)");
    $tagStmt->execute($ids);
    foreach ($tagStmt->fetchAll() as $t) {
        $postTags[$t['post_id']][] = $t;
    }
}

// Counts for tabs
$counts = ['all'=>0,'published'=>0,'draft'=>0,'trash'=>0];
$cRows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM blog_posts GROUP BY status")->fetchAll();
foreach ($cRows as $cr) {
    $counts[$cr['status']] = (int)$cr['cnt'];
    $counts['all'] += (int)$cr['cnt'];
}

// Authors
$authors = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin','developer') ORDER BY name ASC")->fetchAll();

// Categories
$categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();

// Tags
$allTags = $pdo->query("SELECT * FROM blog_tags ORDER BY name ASC")->fetchAll();

// Editing?
$editItem = null;
$editTags = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
    if ($editItem) {
        $et = $pdo->prepare("SELECT tag_id FROM blog_post_tags WHERE post_id = ?");
        $et->execute([$editId]);
        $editTags = $et->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.25.1/standard/ckeditor.js"></script>

<style>
/* ===== Professional Blog Admin Styles ===== */
.bam-wrap { font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }

/* Header */
.bam-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem; }
.bam-header h2 { font-weight:800; color:#0f172a; font-size:1.6rem; margin:0; }

/* Alerts */
.bam-alert { padding:.85rem 1.25rem; border-radius:12px; margin-bottom:1.5rem; font-weight:600; font-size:.9rem; display:flex; align-items:center; gap:.6rem; animation:slideDown .4s ease; }
.bam-alert.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.bam-alert.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

/* Status Tabs */
.bam-tabs { display:flex; gap:.35rem; margin-bottom:1.25rem; border-bottom:2px solid #e2e8f0; flex-wrap:wrap; }
.bam-tab { padding:.5rem 1.1rem; border:none; background:none; cursor:pointer; font-weight:700; font-size:.85rem; color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
.bam-tab:hover { color:#0f172a; }
.bam-tab.active { color:#ff6600; border-bottom-color:#ff6600; }
.bam-tab .tab-count { background:#f1f5f9; color:#64748b; padding:.1rem .45rem; border-radius:50px; font-size:.72rem; font-weight:700; }
.bam-tab.active .tab-count { background:rgba(255,102,0,.12); color:#ff6600; }

/* Search bar */
.bam-search-wrap { position:relative; max-width:300px; }
.bam-search-wrap input { border-radius:50px; border:1.5px solid #e2e8f0; padding:.45rem 1rem .45rem 2.2rem; font-size:.85rem; width:100%; background:#fff; transition:all .2s; }
.bam-search-wrap input:focus { border-color:#ff6600; box-shadow:0 0 0 3px rgba(255,102,0,.1); outline:none; }
.bam-search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.85rem; }

/* Table card */
.bam-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; overflow:hidden; margin-bottom:2rem; }
.bam-card-header { background:linear-gradient(135deg,#f8fafc,#f1f5f9); padding:1rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
.bam-card-header h5 { font-weight:700; margin:0; color:#1e293b; font-size:1rem; }
.bam-table { width:100%; border-collapse:collapse; }
.bam-table thead th { background:var(--color-bg); padding:.65rem .85rem; font-size:.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #e2e8f0; white-space:nowrap; vertical-align:middle; }
.bam-table tbody td { padding:.65rem .85rem; font-size:.85rem; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.bam-table tbody tr { transition:background .15s; }
.bam-table tbody tr:hover { background:var(--color-bg); }

/* Status badges */
.bam-badge { padding:.25rem .7rem; border-radius:50px; font-size:.72rem; font-weight:700; display:inline-flex; align-items:center; gap:.3rem; }
.bam-published { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.bam-draft     { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
.bam-trash     { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Action buttons */
.btn-bam-edit    { background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; text-decoration:none; display:inline-block; transition:all .2s; }
.btn-bam-edit:hover { background:#3b82f6; color:white; }
.btn-bam-pub     { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bam-pub:hover { background:#16a34a; color:white; }
.btn-bam-draft   { background:#fefce8; color:#ca8a04; border:1px solid #fef08a; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bam-draft:hover { background:#ca8a04; color:white; }
.btn-bam-trash   { background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bam-trash:hover { background:#ea580c; color:white; }
.btn-bam-delete  { background:#fef2f2; color:#ef4444; border:1px solid #fecaca; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-bam-delete:hover { background:#ef4444; color:white; }
.btn-bam-view    { background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; padding:.28rem .6rem; border-radius:8px; font-size:.76rem; font-weight:600; text-decoration:none; display:inline-block; transition:all .2s; }
.btn-bam-view:hover { background:#0284c7; color:white; }

/* Form */
.bam-form-card { background:white; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.04); border:1px solid #e2e8f0; padding:2rem; margin-bottom:2rem; }
.bam-form-card h4 { font-weight:800; color:#0f172a; margin-bottom:1.25rem; font-size:1.15rem; }
.bam-form-card label { font-weight:600; font-size:.82rem; color:#334155; margin-bottom:.3rem; }
.bam-form-card .form-control, .bam-form-card .form-select { border-radius:10px; border:1.5px solid #e2e8f0; padding:.55rem .85rem; font-size:.88rem; transition:border-color .2s; }
.bam-form-card .form-control:focus, .bam-form-card .form-select:focus { border-color:#ff6600; box-shadow:0 0 0 3px rgba(255,102,0,.1); }
.bam-form-card .form-text { font-size:.75rem; color:#94a3b8; margin-top:.25rem; }
.cover-preview { width:100%; max-height:200px; object-fit:cover; border-radius:12px; border:1px solid #e2e8f0; display:block; margin-bottom:.75rem; }
.cover-drop-zone { border:2px dashed #cbd5e1; border-radius:12px; padding:1.5rem; text-align:center; cursor:pointer; transition:all .25s; background:var(--color-bg); position:relative; }
.cover-drop-zone:hover { border-color:#ff6600; background:rgba(255,102,0,.02); }
.cover-drop-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.btn-bam-submit { background:linear-gradient(135deg,#ff6600,#e65c00); color:white; border:none; padding:.6rem 1.5rem; border-radius:10px; font-weight:700; font-size:.88rem; cursor:pointer; transition:all .25s; }
.btn-bam-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(255,102,0,.3); }
.btn-bam-cancel { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding:.6rem 1.5rem; border-radius:10px; font-weight:700; font-size:.88rem; cursor:pointer; text-decoration:none; transition:all .2s; display:inline-block; }
.btn-bam-cancel:hover { background:#e2e8f0; color:#1e293b; }

/* Panel switching */
.bam-panel { display:none; }
.bam-panel.active { display:block; }

/* Status select styles */
.status-select-pub  { border-color:#86efac !important; background:#f0fdf4 !important; color:#15803d !important; font-weight:700; }
.status-select-draft{ border-color:#fde047 !important; background:#fefce8 !important; color:#a16207 !important; font-weight:700; }

.blog-cover-thumb { width:56px; height:40px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; }
.blog-title-cell { font-weight:700; color:#0f172a; }

/* Tag display */
.post-tags-display { display:flex; gap:.25rem; flex-wrap:wrap; }
.post-tag-mini { background:#f1f5f9; color:#475569; padding:.1rem .45rem; border-radius:4px; font-size:.65rem; font-weight:600; border:1px solid #e2e8f0; }

/* Tag selector */
.tag-selector-wrap { border:1.5px solid #e2e8f0; border-radius:10px; padding:.5rem; min-height:44px; max-height:120px; overflow-y:auto; display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; background:#fff; }
.tag-selector-wrap:focus-within { border-color:#ff6600; box-shadow:0 0 0 3px rgba(255,102,0,.1); }
.tag-option { background:#f1f5f9; color:#475569; padding:.15rem .5rem; border-radius:6px; font-size:.78rem; font-weight:600; cursor:pointer; border:1px solid #e2e8f0; transition:all .15s; user-select:none; }
.tag-option:hover { border-color:#94a3b8; background:#e2e8f0; }
.tag-option.selected { background:rgba(255,102,0,.1); color:#e65c00; border-color:#ff6600; }
.tag-option .fa-times { margin-left:.25rem; font-size:.6rem; opacity:.6; }

/* Pagination */
.bam-pagination { display:flex; justify-content:center; gap:.3rem; padding:1rem 1.5rem; flex-wrap:wrap; border-top:1px solid #f1f5f9; }
.bam-page { padding:.35rem .75rem; border-radius:8px; font-size:.82rem; font-weight:600; text-decoration:none; color:#64748b; border:1px solid #e2e8f0; transition:all .2s; }
.bam-page:hover { background:#f1f5f9; color:#1e293b; }
.bam-page.active { background:#ff6600; color:white; border-color:#ff6600; }

/* Bulk actions bar */
.bulk-bar { display:flex; align-items:center; gap:.6rem; padding:.6rem 1rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.bulk-bar .form-check-input:checked { background-color:#ff6600; border-color:#ff6600; }
.bulk-bar select { border-radius:8px; border:1.5px solid #e2e8f0; padding:.3rem .6rem; font-size:.8rem; font-weight:600; color:#475569; }
.bulk-bar .btn-apply { background:#0f172a; color:white; border:none; padding:.3rem .85rem; border-radius:8px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .2s; }
.bulk-bar .btn-apply:hover { background:#ff6600; }
.bulk-bar .select-info { font-size:.78rem; color:#64748b; }

/* Category badge */
.cat-badge { padding:.15rem .55rem; border-radius:50px; font-size:.7rem; font-weight:700; display:inline-block; }

/* Featured star */
.feat-star { color:#f59e0b; }
.feat-star.inactive { color:#e2e8f0; }

/* SEO panel */
.seo-preview { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; margin-top:.5rem; }
.seo-preview-title { color:#1a0dab; font-size:.95rem; font-weight:600; line-height:1.3; }
.seo-preview-url { color:#006621; font-size:.8rem; }
.seo-preview-desc { color:#545454; font-size:.82rem; }

/* Responsive */
@media(max-width:768px) {
    .bam-table { font-size:.8rem; }
    .bam-table thead th, .bam-table tbody td { padding:.4rem .5rem; }
    .bam-form-card { padding:1.25rem; }
    .bam-search-wrap { max-width:100%; }
}
</style>

<div class="bam-wrap container-fluid px-lg-4">

<?php if ($message): ?>
<div class="bam-alert <?= $messageType ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="bam-header">
    <div>
        <h2><i class="far fa-newspaper me-2" style="color:#ff6600;"></i> Professional Blog Manager</h2>
        <p class="text-muted mb-0" style="font-size:.88rem;">Publish, draft, schedule, and manage all blog content from one place.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="blog-categories.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold"><i class="fas fa-folder me-1"></i> Categories</a>
        <a href="blog-tags.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold"><i class="fas fa-tags me-1"></i> Tags</a>
        <button class="btn btn-sm btn-orange rounded-pill px-3 fw-bold" onclick="switchBamPanel('form-panel')">
            <i class="fas fa-plus me-1"></i> New Article
        </button>
    </div>
</div>

<!-- Status Tabs + Search -->
<div class="bam-tabs">
    <?php
    $tabDefs = [
        'all'       => ['label'=>'All Articles', 'icon'=>'fas fa-list'],
        'published' => ['label'=>'Published',    'icon'=>'fas fa-globe'],
        'draft'     => ['label'=>'Drafts',       'icon'=>'fas fa-lock'],
        'trash'     => ['label'=>'Trash',        'icon'=>'fas fa-trash-alt'],
    ];
    foreach ($tabDefs as $tabKey => $tabMeta):
        $url = "?tab=$tabKey";
        if ($search) $url .= "&search=" . urlencode($search);
    ?>
        <a href="<?= $url ?>" class="bam-tab <?= $activeTab === $tabKey ? 'active' : '' ?>">
            <i class="<?= $tabMeta['icon'] ?>"></i>
            <?= $tabMeta['label'] ?>
            <span class="tab-count"><?= $counts[$tabKey] ?></span>
        </a>
    <?php endforeach; ?>
    <div class="ms-auto d-flex align-items-center gap-2 py-1">
        <div class="bam-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="blogSearchInput" placeholder="Search posts..." value="<?= htmlspecialchars($search) ?>"
                   onkeydown="if(event.key==='Enter'){window.location.href='?tab=<?= $activeTab ?>&search='+encodeURIComponent(this.value)}">
        </div>
        <?php if ($search): ?>
            <a href="?tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2"><i class="fas fa-times"></i></a>
        <?php endif; ?>
        <button class="bam-tab" data-panel="form-panel" onclick="switchBamPanelByBtn(this)" style="border-bottom:none;">
            <i class="fas fa-plus-circle" style="color:#ff6600;"></i>
        </button>
    </div>
</div>

<!-- ===== ARTICLES LIST PANEL ===== -->
<div id="articles-panel" class="bam-panel active">
    <div class="bam-card">
        <div class="bam-card-header">
            <h5><i class="far fa-newspaper me-2" style="color:#ff6600;"></i>
                <?= $activeTab === 'all' ? 'All Blog Posts' : ucfirst($activeTab) . ' Posts' ?>
                <?php if ($search): ?> <span class="text-muted fw-normal ms-2" style="font-size:.8rem;">&mdash; searching &ldquo;<?= htmlspecialchars($search) ?>&rdquo;</span><?php endif; ?>
            </h5>
            <span class="badge" style="background:linear-gradient(135deg,#ff6600,#e65c00);color:white;padding:.3rem .75rem;border-radius:50px;font-size:.75rem;font-weight:700;">
                <?= $totalPosts ?> Post<?= $totalPosts !== 1 ? 's' : '' ?>
            </span>
        </div>

        <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_action">
        <?php if (!empty($posts)): ?>
        <div class="bulk-bar">
            <input type="checkbox" id="selectAllPosts" onchange="toggleSelectAll(this)">
            <label for="selectAllPosts" class="small mb-0 fw-semibold select-info">Select All</label>
            <select name="bulk_action" id="bulkActionSelect">
                <option value="">Bulk Actions</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to Draft</option>
                <option value="trash">Move to Trash</option>
                <option value="delete">Delete Permanently</option>
                <option value="feature">Toggle Featured</option>
            </select>
            <button type="button" class="btn-apply" onclick="applyBulkAction()">Apply</button>
            <span class="select-info" id="selectedCount">0 selected</span>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="bam-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="selectAllPosts2" onchange="toggleSelectAll(this)"></th>
                        <th style="width:52px;">Cover</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Tags</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post):
                    $pts = $postTags[$post['id']] ?? [];
                ?>
                    <tr>
                        <td><input type="checkbox" name="post_ids[]" value="<?= $post['id'] ?>" class="post-checkbox" onchange="updateSelectedCount()"></td>
                        <td>
                            <?php if (!empty($post['cover_image'])): ?>
                                <?php if (($post['media_type'] ?? 'image') === 'video'): ?>
                                    <video src="<?= htmlspecialchars($post['cover_image']) ?>" class="blog-cover-thumb" muted></video>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($post['cover_image']) ?>" class="blog-cover-thumb" alt="">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="blog-cover-thumb d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border-radius:6px;">
                                    <i class="fas fa-image text-muted" style="font-size:.7rem;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="blog-title-cell d-flex align-items-center gap-1">
                                <?php if (!empty($post['is_featured'])): ?>
                                    <i class="fas fa-star feat-star" title="Featured Post" style="font-size:.65rem;"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($post['title']) ?>
                            </div>
                            <?php if (!empty($post['excerpt'])): ?>
                                <p class="text-muted small mb-0 text-truncate" style="max-width:240px;"><?= htmlspecialchars(strip_tags($post['excerpt'])) ?></p>
                            <?php else: ?>
                                <p class="text-muted small mb-0 text-truncate" style="max-width:240px;"><?= strip_tags($post['content']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($post['slug'])): ?>
                                <code style="font-size:.6rem;color:#94a3b8;">/<?= htmlspecialchars($post['slug']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($post['category_name'])): ?>
                                <span class="cat-badge" style="background:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>20; color:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>;">
                                    <?= htmlspecialchars($post['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="post-tags-display">
                                <?php if (!empty($pts)): ?>
                                    <?php foreach (array_slice($pts, 0, 3) as $pt): ?>
                                        <span class="post-tag-mini"><?= htmlspecialchars($pt['name']) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($pts) > 3): ?>
                                        <span class="post-tag-mini">+<?= count($pts) - 3 ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.7rem;">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong style="font-size:.8rem;"><?= htmlspecialchars($post['author_name']) ?></strong></td>
                        <td>
                            <?php $s = $post['status'] ?? 'published'; ?>
                            <span class="bam-badge bam-<?= $s ?>">
                                <?php if ($s === 'published'): ?><i class="fas fa-globe"></i> Pub
                                <?php elseif ($s === 'draft'): ?><i class="fas fa-lock"></i> Draft
                                <?php else: ?><i class="fas fa-trash-alt"></i> Trash<?php endif; ?>
                            </span>
                        </td>
                        <td><span class="text-muted small"><i class="fas fa-eye me-1" style="font-size:.7rem;"></i><?= number_format($post['views'] ?? 0) ?></span></td>
                        <td><span class="small text-muted" style="font-size:.75rem;"><?= date('M d, Y', strtotime($post['created_at'])) ?></span></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="?edit=<?= $post['id'] ?>&tab=<?= $activeTab ?>" class="btn-bam-edit" title="Edit"><i class="fas fa-pen"></i></a>

                                <?php if (($post['status'] ?? '') === 'published'): ?>
                                    <a href="../blog_detail.php?id=<?= wqs_encrypt_id($post['id']) ?>" target="_blank" class="btn-bam-view" title="View"><i class="fas fa-eye"></i></a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="quick_status">
                                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="new_status" value="draft">
                                        <button type="submit" class="btn-bam-draft" title="Draft"><i class="fas fa-file-alt"></i></button>
                                    </form>
                                <?php elseif (($post['status'] ?? '') === 'draft'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="quick_status">
                                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="new_status" value="published">
                                        <button type="submit" class="btn-bam-pub" title="Publish"><i class="fas fa-globe"></i></button>
                                    </form>
                                <?php elseif (($post['status'] ?? '') === 'trash'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="quick_status">
                                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="new_status" value="draft">
                                        <button type="submit" class="btn-bam-draft" title="Restore"><i class="fas fa-undo"></i></button>
                                    </form>
                                <?php endif; ?>

                                <?php if (($post['status'] ?? '') !== 'trash'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="quick_status">
                                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="new_status" value="trash">
                                        <button type="submit" class="btn-bam-trash" title="Trash"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete? This cannot be undone.')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                        <button type="submit" class="btn-bam-delete" title="Delete Forever"><i class="fas fa-times"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">
                        <i class="far fa-newspaper fa-2x mb-3 d-block" style="color:#cbd5e1;"></i>
                        <?php if ($search): ?>
                            No posts match your search.
                            <br><a href="?tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary mt-2 rounded-pill">Clear Search</a>
                        <?php else: ?>
                            No <?= $activeTab === 'all' ? '' : $activeTab ?> articles found.
                            <br><button class="btn btn-sm btn-orange mt-2 rounded-pill" onclick="switchBamPanel('form-panel')"><i class="fas fa-plus me-1"></i> Write your first post</button>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bam-pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++):
                $url = "?tab=$activeTab&p=$i";
                if ($search) $url .= "&search=" . urlencode($search);
            ?>
                <a href="<?= $url ?>" class="bam-page <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ADD/EDIT FORM PANEL ===== -->
<div id="form-panel" class="bam-panel <?= $editItem ? 'active' : '' ?>">
    <div class="bam-form-card">
        <h4>
            <i class="fas <?= $editItem ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2" style="color:#ff6600;"></i>
            <?= $editItem ? 'Edit: ' . htmlspecialchars($editItem['title']) : 'Create New Blog Article' ?>
        </h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Cover Media Upload -->
                <div class="col-12">
                    <label class="form-label">Cover Media <small class="text-muted">(Image or Video · Recommended: 1200×630px)</small></label>
                    <?php if (!empty($editItem['cover_image'])): ?>
                        <?php if (($editItem['media_type'] ?? 'image') === 'video'): ?>
                            <video src="<?= htmlspecialchars($editItem['cover_image']) ?>" class="cover-preview" controls></video>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($editItem['cover_image']) ?>" id="coverPreviewImg" class="cover-preview" alt="Current Cover">
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="" id="coverPreviewImg" class="cover-preview" style="display:none;" alt="Preview">
                        <video src="" id="coverPreviewVid" class="cover-preview" style="display:none;" controls></video>
                    <?php endif; ?>
                    <div class="cover-drop-zone" id="coverDropZone">
                        <input type="file" name="cover_image" id="coverImageInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" onchange="previewMedia(this)">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2 d-block"></i>
                        <p class="text-muted small mb-0"><strong>Click or drag to upload media</strong><br>JPG, PNG, WebP or MP4 · Max 100MB</p>
                    </div>
                </div>

                <!-- Title -->
                <div class="col-md-8">
                    <label class="form-label">Article Title *</label>
                    <input type="text" name="title" id="postTitle" class="form-control" placeholder="e.g. Scalability and Redis Caching Strategies" required
                           value="<?= htmlspecialchars($editItem['title'] ?? '') ?>"
                           oninput="autoSlug(this.value)">
                </div>

                <!-- Slug -->
                <div class="col-md-4">
                    <label class="form-label">URL Slug</label>
                    <input type="text" name="slug" id="postSlug" class="form-control" placeholder="auto-generated"
                           value="<?= htmlspecialchars($editItem['slug'] ?? '') ?>">
                    <div class="form-text">Leave empty to auto-generate from title</div>
                </div>

                <!-- Category -->
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— No Category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editItem && $editItem['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tags -->
                <div class="col-md-4">
                    <label class="form-label">Tags</label>
                    <div class="tag-selector-wrap" id="tagSelector" onclick="document.getElementById('tagSearchInput').focus()">
                        <input type="text" id="tagSearchInput" class="form-control" style="border:none;padding:0;margin:0;width:100px;min-height:auto;box-shadow:none;font-size:.82rem;background:transparent;outline:none;"
                               placeholder="Type to filter tags..." oninput="filterTags(this.value)">
                        <div id="tagChips" style="display:contents;"></div>
                    </div>
                    <div id="tagDropdown" style="display:none;position:absolute;background:#fff;border:1px solid #e2e8f0;border-radius:10px;max-height:150px;overflow-y:auto;z-index:100;width:auto;min-width:200px;box-shadow:0 8px 24px rgba(0,0,0,.08);"></div>
                    <input type="hidden" name="tag_ids[]" id="tagIds" value="">
                    <div class="form-text">Click tags to assign. Selected tags appear above.</div>
                </div>

                <!-- Author -->
                <div class="col-md-4">
                    <label class="form-label">Author *</label>
                    <select name="author_id" class="form-select" required>
                        <option value="">— Choose Author —</option>
                        <?php foreach ($authors as $auth): ?>
                            <option value="<?= $auth['id'] ?>" <?= ($editItem && $editItem['author_id'] == $auth['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($auth['name']) ?> (<?= htmlspecialchars(ucfirst($auth['role'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status & Featured -->
                <div class="col-md-6">
                    <label class="form-label">Publication Status</label>
                    <select name="status" id="statusSelect" class="form-select" onchange="updateStatusStyle(this)">
                        <option value="published" <?= (!$editItem || ($editItem['status'] ?? '') === 'published') ? 'selected' : '' ?>>🌐 Publish Now</option>
                        <option value="draft"     <?= ($editItem && ($editItem['status'] ?? '') === 'draft')     ? 'selected' : '' ?>>🔒 Save as Draft</option>
                    </select>
                    <small class="text-muted">Drafts are hidden from public view.</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Schedule Publish</label>
                    <input type="datetime-local" name="published_at" class="form-control"
                           value="<?= !empty($editItem['published_at']) ? date('Y-m-d\TH:i', strtotime($editItem['published_at'])) : '' ?>">
                    <small class="text-muted">Leave empty for immediate publish</small>
                </div>

                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured" value="1"
                               style="width:1.1rem;height:1.1rem;cursor:pointer;"
                               <?= ($editItem && !empty($editItem['is_featured'])) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="isFeatured" style="cursor:pointer;">
                            <i class="fas fa-star text-warning me-1"></i> Mark as Featured Post
                        </label>
                        <div class="form-text">Featured posts appear first on the blog</div>
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="col-12">
                    <label class="form-label">Excerpt <small class="text-muted">(Short summary for blog listings)</small></label>
                    <textarea name="excerpt" class="form-control" rows="3" placeholder="Brief summary of the article…"><?= htmlspecialchars($editItem['excerpt'] ?? '') ?></textarea>
                </div>

                <!-- Content -->
                <div class="col-12">
                    <label class="form-label">Article Content *</label>
                    <textarea name="content" id="blogContentEditor" rows="15" class="form-control" required><?= htmlspecialchars($editItem['content'] ?? '') ?></textarea>
                </div>

                <!-- SEO Fields -->
                <div class="col-12 mt-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-search text-muted" style="font-size:.9rem;"></i>
                        <span class="fw-bold" style="font-size:.95rem;color:#1e293b;">SEO &amp; Social Preview</span>
                        <span class="text-muted" style="font-size:.75rem;">— Optimize how this post appears in search engines</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Meta Title <small class="text-muted">(Optional — defaults to article title)</small></label>
                            <input type="text" name="meta_title" class="form-control" placeholder="SEO Title — 60 chars max"
                                   value="<?= htmlspecialchars($editItem['meta_title'] ?? '') ?>"
                                   oninput="updateSeoPreview()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" class="form-control" rows="2" placeholder="Short SEO description — 160 chars max"
                                      oninput="updateSeoPreview()"><?= htmlspecialchars($editItem['meta_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <!-- SEO Preview -->
                    <div class="seo-preview" id="seoPreview">
                        <div class="seo-preview-title" id="seoPreviewTitle">
                            <?= htmlspecialchars($editItem['meta_title'] ?? $editItem['title'] ?? 'Article Title — Wise Quotient Soft Blog') ?>
                        </div>
                        <div class="seo-preview-url" id="seoPreviewUrl">
                            wqs.com/blog/<?= htmlspecialchars($editItem['slug'] ?? 'article-slug') ?>
                        </div>
                        <div class="seo-preview-desc" id="seoPreviewDesc">
                            <?= htmlspecialchars($editItem['meta_description'] ?? $editItem['excerpt'] ?? strip_tags($editItem['content'] ?? 'Article description will appear here…')) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="submit" class="btn-bam-submit">
                    <i class="fas <?= $editItem ? 'fa-save' : 'fa-upload' ?> me-1"></i>
                    <?= $editItem ? 'Update Post' : 'Save Article' ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="manage_blog.php?tab=<?= $activeTab ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="btn-bam-cancel">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn-bam-cancel" onclick="switchBamPanel('articles-panel')">Back to List</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

</div><!-- /.bam-wrap -->

<script>
// ===== Panel Switching =====
function switchBamPanel(panelId) {
    document.querySelectorAll('.bam-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
}
function switchBamPanelByBtn(btn) {
    switchBamPanel(btn.dataset.panel);
}

// Auto-switch to form if editing
<?php if ($editItem): ?>
document.addEventListener('DOMContentLoaded', () => switchBamPanel('form-panel'));
<?php endif; ?>

// CKEditor
CKEDITOR.replace('blogContentEditor');

// Status select style
function updateStatusStyle(sel) {
    sel.className = 'form-select';
    if (sel.value === 'published') sel.classList.add('status-select-pub');
    else if (sel.value === 'draft') sel.classList.add('status-select-draft');
}
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('statusSelect');
    if (sel) updateStatusStyle(sel);
});

// Media preview
function previewMedia(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const isVideo = file.type.startsWith('video/');
        const img = document.getElementById('coverPreviewImg');
        const vid = document.getElementById('coverPreviewVid');
        const reader = new FileReader();
        reader.onload = function(e) {
            if (isVideo) {
                if (img) img.style.display = 'none';
                if (vid) { vid.src = e.target.result; vid.style.display = 'block'; }
            } else {
                if (vid) vid.style.display = 'none';
                if (img) { img.src = e.target.result; img.style.display = 'block'; }
            }
        };
        reader.readAsDataURL(file);
    }
}

// Drag & drop
const dz = document.getElementById('coverDropZone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = '#ff6600'; });
    dz.addEventListener('dragleave', () => dz.style.borderColor = '#cbd5e1');
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.style.borderColor = '#cbd5e1';
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('coverImageInput').files = files;
            previewMedia(document.getElementById('coverImageInput'));
        }
    });
}

// Auto slug
function autoSlug(title) {
    const slugField = document.getElementById('postSlug');
    if (!slugField.dataset.userEdited) {
        slugField.value = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
}
document.getElementById('postSlug')?.addEventListener('input', function() {
    this.dataset.userEdited = this.value.length > 0 ? '1' : '';
});

// ===== Tag Selector =====
const allTags = <?= json_encode(array_map(function($t) { return ['id' => (int)$t['id'], 'name' => $t['name']]; }, $allTags)) ?>;
const editTagIds = <?= json_encode($editTags) ?>;
let selectedTags = [];

function initTags() {
    if (editTagIds.length > 0) {
        editTagIds.forEach(id => {
            const found = allTags.find(t => t.id === id);
            if (found) selectedTags.push(found);
        });
    }
    renderTagChips();
}

function renderTagChips() {
    const container = document.getElementById('tagChips');
    container.innerHTML = selectedTags.map(t =>
        `<span class="tag-option selected" onclick="removeTag(${t.id})">
            ${t.name} <i class="fas fa-times"></i>
        </span>`
    ).join('');
    updateTagIds();
}

function filterTags(q) {
    const dropdown = document.getElementById('tagDropdown');
    if (!q.trim()) { dropdown.style.display = 'none'; return; }
    const filtered = allTags.filter(t =>
        t.name.toLowerCase().includes(q.toLowerCase()) &&
        !selectedTags.find(s => s.id === t.id)
    );
    if (filtered.length === 0) { dropdown.style.display = 'none'; return; }
    dropdown.style.display = 'block';
    dropdown.innerHTML = filtered.map(t =>
        `<div class="tag-option" style="padding:.4rem .8rem;border-radius:0;border:none;border-bottom:1px solid #f1f5f9;"
              onclick="addTag(${t.id}, '${t.name}')">${t.name}</div>`
    ).join('');
}

function addTag(id, name) {
    selectedTags.push({id, name});
    renderTagChips();
    document.getElementById('tagSearchInput').value = '';
    document.getElementById('tagDropdown').style.display = 'none';
}

function removeTag(id) {
    selectedTags = selectedTags.filter(t => t.id !== id);
    renderTagChips();
}

function updateTagIds() {
    document.getElementById('tagIds').value = selectedTags.map(t => t.id).join(',');
    // Also populate hidden inputs for form submission
    const container = document.getElementById('tagSelector');
    container.querySelectorAll('input[name="tag_ids[]"]').forEach(el => el.remove());
    selectedTags.forEach(t => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'tag_ids[]';
        inp.value = t.id;
        container.appendChild(inp);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initTags();
    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#tagSelector')) {
            document.getElementById('tagDropdown').style.display = 'none';
        }
    });
});

// ===== SEO Preview =====
function updateSeoPreview() {
    const title = document.querySelector('input[name="meta_title"]').value || document.querySelector('input[name="title"]').value;
    const desc = document.querySelector('textarea[name="meta_description"]').value || document.querySelector('textarea[name="excerpt"]').value;
    const slug = document.getElementById('postSlug').value || 'article-slug';
    document.getElementById('seoPreviewTitle').textContent = title || 'Article Title — Wise Quotient Soft Blog';
    document.getElementById('seoPreviewUrl').textContent = 'wqs.com/blog/' + slug;
    document.getElementById('seoPreviewDesc').textContent = desc || 'Search description will appear here…';
}

// ===== Bulk Actions =====
function toggleSelectAll(master) {
    document.querySelectorAll('.post-checkbox, #selectAllPosts, #selectAllPosts2').forEach(cb => cb.checked = master.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.post-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selected';
}

function applyBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    const checked = document.querySelectorAll('.post-checkbox:checked');
    if (!action) { alert('Please select a bulk action.'); return; }
    if (checked.length === 0) { alert('Please select at least one post.'); return; }
    if (action === 'delete' && !confirm('Permanently delete ' + checked.length + ' post(s)? This cannot be undone.')) return;
    document.getElementById('bulkForm').submit();
}
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
