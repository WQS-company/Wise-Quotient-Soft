<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || strtolower($userRoleObj['role']) !== 'admin') { header("Location: " . $path_to_root . "login.php"); exit; }

$uploadAbs = __DIR__ . '/../uploads/packages/';
if (!is_dir($uploadAbs)) @mkdir($uploadAbs, 0755, true);

function uploadPackageImage($file, $existing = null) {
    global $uploadAbs;
    $allowed = ['jpg','jpeg','png','webp','gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return $existing;
    $name = 'pkg_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadAbs . $name)) {
        if ($existing && file_exists($uploadAbs . basename($existing))) @unlink($uploadAbs . basename($existing));
        return 'uploads/packages/' . $name;
    }
    return $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (in_array($action, ['create', 'edit'])) {
        $id = $action === 'edit' ? (int)$_POST['id'] : null;
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Software');
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $time_limit = trim($_POST['time_limit'] ?? 'Lifetime Access');
        $access_link = trim($_POST['access_link'] ?? '#');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $image_path = trim($_POST['existing_image'] ?? '');
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $image_path = uploadPackageImage($_FILES['image_file'], $image_path);
        }
        
        if ($title && $description) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO free_packages (title, category, description, features, time_limit, access_link, image_path, is_active) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$title, $category, $description, $features, $time_limit, $access_link, $image_path, $is_active]);
                    $_SESSION['success_message'] = 'Package created successfully!';
                } else {
                    $stmt = $pdo->prepare("UPDATE free_packages SET title=?, category=?, description=?, features=?, time_limit=?, access_link=?, image_path=?, is_active=? WHERE id=?");
                    $stmt->execute([$title, $category, $description, $features, $time_limit, $access_link, $image_path, $is_active, $id]);
                    $_SESSION['success_message'] = 'Package updated successfully!';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM free_packages WHERE id=?")->execute([$id]);
            $_SESSION['success_message'] = 'Package deleted successfully!';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error deleting package.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("UPDATE free_packages SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            $_SESSION['success_message'] = 'Package status toggled!';
        } catch (Exception $e) {}
    }
    
    header("Location: free_packages.php");
    exit;
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingPkg = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM free_packages WHERE id=?");
    $stmt->execute([$editId]);
    $editingPkg = $stmt->fetch(PDO::FETCH_ASSOC);
}

$packages = [];
try {
    $packages = $pdo->query("SELECT * FROM free_packages ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = "Free Packages";
require_once $path_to_root . 'includes/dashboard_header.php';
?>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">
<style>
/* Premium Form Styles */
.premium-form-wrap {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
    margin-bottom: 2.5rem;
}
.premium-form-wrap::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 6px;
    background: linear-gradient(135deg, #7c3aed, #a855f7);
}
.premium-input-group {
    position: relative;
    display: flex;
    align-items: center;
}
.premium-input-group i {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    z-index: 10;
}
.premium-input {
    border-radius: 12px !important;
    padding: 0.75rem 1rem 0.75rem 2.5rem !important;
    border: 1px solid #cbd5e1;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: #f8fafc;
    width: 100%;
}
.premium-input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
    background: #ffffff;
    outline: none;
}
textarea.premium-input {
    padding-top: 0.75rem !important;
    align-items: flex-start;
}
.premium-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
}
.premium-btn {
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: white;
    font-weight: 600;
    padding: 0.8rem 2rem;
    border-radius: 50px;
    border: none;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px -5px rgba(124, 58, 237, 0.4);
}
.premium-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -5px rgba(124, 58, 237, 0.5);
    color: white;
}
.ui-datepicker {
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
    padding: 0.5rem;
}
.ui-widget-header {
    background: #f8fafc;
    border: none;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
    font-weight: 700;
}
.ui-state-default, .ui-widget-content .ui-state-default {
    border-radius: 6px;
    border: 1px solid transparent;
    background: #ffffff;
    font-weight: 500;
    color: #475569;
    text-align: center;
}
.ui-state-hover, .ui-widget-content .ui-state-hover {
    background: #ede9fe;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
}
.ui-state-active, .ui-widget-content .ui-state-active {
    background: #7c3aed;
    color: #ffffff;
    border: 1px solid #7c3aed;
}
</style>

<div class="container-fluid px-3 px-lg-4 pb-5">
    <!-- HERO -->
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem;position:relative;overflow:hidden">
        <div style="position:absolute;top:-60%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(124,58,237,.15),transparent 70%);border-radius:50%"></div>
        <div class="row align-items-center position-relative" style="z-index:1">
            <div class="col-lg-6 mb-2 mb-lg-0">
                <h4 class="fw-bold mb-1"><i class="fas fa-gift me-2"></i>Free Packages</h4>
                <p class="mb-0 opacity-75" style="font-size:.85rem">Manage free software and trials offered to the public</p>
            </div>
            <div class="col-lg-6 text-lg-end">
                <button class="btn btn-light rounded-pill px-4 fw-semibold" onclick="document.getElementById('pkgFormWrap').style.display='block'; window.scrollTo(0,0)"><i class="fas fa-plus-circle me-2"></i>Add Package</button>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_message']); endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error_message']); endif; ?>

    <!-- FORM -->
    <div id="pkgFormWrap" class="premium-form-wrap" style="display:<?= $editingPkg ? 'block' : 'none' ?>;">
        <h5 class="fw-bold mb-4" style="color: #1e293b; font-size: 1.4rem;">
            <i class="fas <?= $editingPkg ? 'fa-pen-square' : 'fa-plus-square' ?> me-2 text-primary" style="color: #7c3aed !important;"></i>
            <?= $editingPkg ? 'Edit Package' : 'Create New Package' ?>
        </h5>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editingPkg ? 'edit' : 'create' ?>">
            <input type="hidden" name="id" value="<?= $editingPkg['id'] ?? '' ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingPkg['image_path'] ?? '') ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="premium-label">Package Title <span class="text-danger">*</span></label>
                    <div class="premium-input-group">
                        <i class="fas fa-heading"></i>
                        <input type="text" name="title" class="premium-input" required placeholder="e.g. Starter Enterprise AI" value="<?= htmlspecialchars($editingPkg['title'] ?? '') ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="premium-label">Category <span class="text-danger">*</span></label>
                    <div class="premium-input-group">
                        <i class="fas fa-tags"></i>
                        <select name="category" class="premium-input" required style="appearance: auto; -moz-appearance: auto; -webkit-appearance: auto;">
                            <?php
                            $categories = ['Software', 'Web Templates', 'Mobile Apps', 'eBooks & Guides', 'API Services', 'Other'];
                            $currentCat = $editingPkg['category'] ?? 'Software';
                            foreach ($categories as $cat) {
                                $selected = (strtolower($currentCat) === strtolower($cat)) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($cat) . "\" $selected>" . htmlspecialchars($cat) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="premium-label">Expiry Date / Time Limit</label>
                    <div class="premium-input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="text" name="time_limit" id="time_limit_picker" class="premium-input" placeholder="Select Expiry Date or enter Lifetime" value="<?= htmlspecialchars($editingPkg['time_limit'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="premium-label">Package Description <span class="text-danger">*</span></label>
                    <div class="premium-input-group" style="align-items: flex-start;">
                        <i class="fas fa-align-left" style="top: 1rem;"></i>
                        <textarea name="description" class="premium-input" rows="3" required placeholder="Describe the benefits of this free package..."><?= htmlspecialchars($editingPkg['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="premium-label">Key Features <small class="text-muted text-lowercase" style="font-weight: 500;">(comma separated)</small></label>
                    <div class="premium-input-group" style="align-items: flex-start;">
                        <i class="fas fa-list-ul" style="top: 1rem;"></i>
                        <textarea name="features" class="premium-input" rows="2" placeholder="Unlimited users, Custom branding, Cloud storage, 24/7 Support"><?= htmlspecialchars($editingPkg['features'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="premium-label">Access / Download Link <span class="text-danger">*</span></label>
                    <div class="premium-input-group">
                        <i class="fas fa-link"></i>
                        <input type="url" name="access_link" class="premium-input" required placeholder="https://..." value="<?= htmlspecialchars($editingPkg['access_link'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="premium-label">Cover Image</label>
                    <div class="premium-input-group">
                        <i class="fas fa-image"></i>
                        <input type="file" name="image_file" class="premium-input" style="padding: 0.5rem 1rem 0.5rem 2.5rem !important;" accept="image/*">
                    </div>
                    <?php if(!empty($editingPkg['image_path'])): ?>
                        <div class="mt-2 p-2" style="background:#f1f5f9; border-radius:10px; display:inline-block;">
                            <img src="../<?= $editingPkg['image_path'] ?>" height="50" style="border-radius:6px; object-fit:cover; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <span class="ms-2 text-muted" style="font-size: 0.8rem;">Current Image</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-12 pt-2">
                    <div class="form-check form-switch" style="padding-left: 3rem;">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveCheck" <?= ($editingPkg['is_active']??1)?'checked':'' ?> style="width: 3em; height: 1.5em; margin-left: -3rem; cursor: pointer;">
                        <label class="form-check-label ms-2" for="isActiveCheck" style="font-size:.9rem;font-weight:700;color:#1e293b;cursor:pointer;padding-top:4px;">
                            Active & Visible to Public
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mt-5 pt-4 border-top d-flex gap-3">
                <button type="submit" class="premium-btn"><i class="fas fa-save me-2"></i>Save Package</button>
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold border shadow-sm" onclick="document.getElementById('pkgFormWrap').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>

    <!-- LIST -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.85rem">
                <thead style="background:#f8fafc">
                    <tr>
                        <th class="py-3 px-4 text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Image</th>
                        <th class="py-3 px-4 text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Title</th>
                        <th class="py-3 px-4 text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Category</th>
                        <th class="py-3 px-4 text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Time Limit</th>
                        <th class="py-3 px-4 text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Status</th>
                        <th class="py-3 px-4 text-uppercase text-secondary text-end" style="font-size:.7rem;letter-spacing:.5px;font-weight:700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td class="px-4 align-middle">
                            <?php if($pkg['image_path']): ?>
                                <img src="../<?= $pkg['image_path'] ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
                            <?php else: ?>
                                <div style="width:48px;height:48px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#94a3b8"><i class="fas fa-box"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 align-middle fw-semibold text-dark"><?= htmlspecialchars($pkg['title']) ?></td>
                        <td class="px-4 align-middle"><span class="badge bg-secondary text-light" style="font-size:.75rem;padding:4px 10px;border-radius:4px"><?= htmlspecialchars($pkg['category'] ?? 'Software') ?></span></td>
                        <td class="px-4 align-middle"><span class="badge" style="background:#e0e7ff;color:#4f46e5"><?= htmlspecialchars($pkg['time_limit']) ?></span></td>
                        <td class="px-4 align-middle">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <?php if($pkg['is_active']): ?>
                                    <button type="submit" class="btn btn-sm" style="background:#dcfce7;color:#15803d;border-radius:50px;font-size:.7rem;font-weight:700" title="Click to disable"><i class="fas fa-check-circle me-1"></i> Active</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border-radius:50px;font-size:.7rem;font-weight:700" title="Click to enable"><i class="fas fa-times-circle me-1"></i> Inactive</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="px-4 align-middle text-end">
                            <a href="?edit=<?= $pkg['id'] ?>" class="btn btn-sm btn-light rounded-circle text-primary me-2"><i class="fas fa-pen"></i></a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this package?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-light rounded-circle text-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($packages)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-secondary">No free packages found. Create one to get started!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>

<!-- jQuery and jQuery UI for Datepicker -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize premium jQuery datepicker
    $("#time_limit_picker").datepicker({
        dateFormat: "MM d, yy", // e.g., "June 25, 2026"
        minDate: 0, // Restrict to today and future
        showAnim: "slideDown",
        changeMonth: true,
        changeYear: true
    });
});
</script>
