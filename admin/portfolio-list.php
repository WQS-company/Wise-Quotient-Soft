<?php
$page_title = 'Manage Portfolio';
$path_to_root = '../';
require_once $path_to_root . 'includes/dashboard_header.php';

// Access check
if (!has_feature_access('admin_portfolio')) {
    $_SESSION['access_denied_msg'] = 'You do not have permission to access that page. Contact an admin to update your feature access.';
    header("Location: " . $path_to_root . "user/profile.php");
    exit;
}

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Portfolio project has been deleted successfully!';
            $messageType = 'success';
        }
    }
    
    if ($action === 'toggle_visibility') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_visible = NOT is_visible WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Project visibility status updated!';
            $messageType = 'info';
        }
    }
}

// Fetch all projects with their first cover image
$query = "SELECT p.*, 
          (SELECT image_path FROM project_images WHERE project_id = p.id ORDER BY id ASC LIMIT 1) as cover_image 
          FROM projects p 
          ORDER BY p.created_at DESC";
$projects = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tech stacks for quick list rendering
$techStacksRaw = $pdo->query("SELECT project_id, stack_name FROM project_tech_stacks")->fetchAll(PDO::FETCH_ASSOC);
$techStacks = [];
foreach ($techStacksRaw as $ts) {
    $techStacks[$ts['project_id']][] = $ts['stack_name'];
}

// Count visibility metrics
$totalProjects = count($projects);
$visibleCount = count(array_filter($projects, fn($p) => $p['is_visible'] == 1));
$hiddenCount = $totalProjects - $visibleCount;
?>

<style>
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

.portfolio-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}
.portfolio-header h2 {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    font-weight: 800;
    color: #0f172a;
    font-size: 1.6rem;
    margin: 0;
}

/* Stat Cards */
.stat-card-premium {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
}
.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.stat-card-icon.blue { background: rgba(59, 130, 246, 0.08); color: #3b82f6; }
.stat-card-icon.green { background: rgba(16, 185, 129, 0.08); color: #10b981; }
.stat-card-icon.orange { background: rgba(245, 158, 11, 0.08); color: #f59e0b; }

.stat-card-info h6 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin: 0 0 0.2rem 0;
    font-weight: 700;
}
.stat-card-info h3 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

/* Alert Banner */
.portfolio-alert {
    padding: 0.85rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    animation: slideDown 0.4s ease;
}
.portfolio-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.portfolio-alert.info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Table Card styling */
.portfolio-table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 2rem;
}
.portfolio-table-card .card-header-bar {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.portfolio-table-card .card-header-bar h5 {
    font-weight: 700;
    margin: 0;
    color: #1e293b;
    font-size: 1rem;
}

/* Search Bar */
.search-wrapper-inline {
    position: relative;
    max-width: 300px;
    width: 100%;
}
.search-wrapper-inline .search-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 0.85rem; pointer-events: none;
}
.search-wrapper-inline input {
    width: 100%; padding: 0.45rem 1rem 0.45rem 2.25rem;
    border: 1px solid #cbd5e1; border-radius: 50px;
    font-size: 0.85rem; background: #fff;
    outline: none; transition: border-color 0.2s;
}
.search-wrapper-inline input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(10, 45, 94, 0.1);
}

.portfolio-table {
    width: 100%;
    border-collapse: collapse;
}
.portfolio-table thead th {
    background: var(--color-bg);
    padding: 0.75rem 1rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.portfolio-table tbody td {
    padding: 0.85rem 1rem;
    font-size: 0.88rem;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.portfolio-table tbody tr:hover {
    background: var(--color-bg);
}

.project-thumb {
    width: 60px;
    height: 45px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
}
.project-title-bold {
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
}
.project-title-bold:hover {
    color: var(--color-accent);
}
.tech-badge {
    font-size: 0.72rem;
    font-weight: 600;
    color: #0f3a5d;
    background: #eef2f6;
    border: 1px solid #e2e8f0;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    display: inline-block;
    margin: 0.1rem;
}

/* Visibility Switch styling */
.switch-status-label {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 50px;
    display: inline-block;
}
.status-visible {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.status-hidden {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Action buttons */
.btn-action-round {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-action-edit {
    background: #eff6ff;
    color: #3b82f6;
    border-color: #bfdbfe;
}
.btn-action-edit:hover {
    background: #3b82f6;
    color: white;
}
.btn-action-toggle {
    background: #f5f3ff;
    color: #7c3aed;
    border-color: #ddd6fe;
}
.btn-action-toggle:hover {
    background: #7c3aed;
    color: white;
}
.btn-action-delete {
    background: #fef2f2;
    color: #ef4444;
    border-color: #fecaca;
}
.btn-action-delete:hover {
    background: #ef4444;
    color: white;
}

/* Responsive elements */
@media (max-width: 768px) {
    .portfolio-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="container-fluid px-lg-4">

    <!-- Action alerts -->
    <?php if ($message): ?>
        <div class="portfolio-alert <?= $messageType ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="portfolio-header">
        <div>
            <h2><i class="fas fa-briefcase me-2" style="color:var(--color-primary);"></i> Manage Portfolio</h2>
            <p class="text-muted mb-0" style="font-size:0.88rem;">Track, update, delete, or hide projects published on the main portfolio workspace.</p>
        </div>
        <a href="create-portfolio.php" class="btn btn-theme"><i class="fas fa-plus-circle me-1"></i> Add Project</a>
    </div>

    <!-- Metrics row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card-premium">
                <div class="stat-card-icon blue"><i class="fas fa-folder-open"></i></div>
                <div class="stat-card-info">
                    <h6>Total Projects</h6>
                    <h3><?= $totalProjects ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card-premium">
                <div class="stat-card-icon green"><i class="fas fa-eye"></i></div>
                <div class="stat-card-info">
                    <h6>Visible</h6>
                    <h3><?= $visibleCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card-premium">
                <div class="stat-card-icon orange"><i class="fas fa-eye-slash"></i></div>
                <div class="stat-card-info">
                    <h6>Hidden / Invisible</h6>
                    <h3><?= $hiddenCount ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="portfolio-table-card">
        <div class="card-header-bar">
            <h5><i class="fas fa-list-ul me-2" style="color:var(--color-primary);"></i> Portfolio Project List</h5>
            <div class="search-wrapper-inline">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="projectSearch" onkeyup="filterProjectTable()" placeholder="Search by title, stack, owner...">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="portfolio-table" id="projectTable">
                <thead>
                    <tr>
                        <th>Thumbnail</th>
                        <th>Project Name</th>
                        <th>Budget ($)</th>
                        <th>Timeline</th>
                        <th>Tech Stack</th>
                        <th>Visibility</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $p): 
                        $encryptedId = wqs_encrypt_id($p['id']);
                        $coverPath = !empty($p['cover_image']) ? $p['cover_image'] : '';
                        if ($coverPath && strpos($coverPath, 'http') !== 0) {
                            $coverPath = $path_to_root . 'admin/' . $coverPath;
                        } elseif (!$coverPath) {
                            $coverPath = $path_to_root . 'tech.png';
                        }
                        
                        $stacks = $techStacks[$p['id']] ?? [];
                    ?>
                    <tr class="project-row">
                        <td>
                            <img src="<?= htmlspecialchars($coverPath) ?>" class="project-thumb" alt="">
                        </td>
                        <td>
                            <a href="edit-portfolio.php?id=<?= $encryptedId ?>" class="project-title-bold search-target-title"><?= htmlspecialchars($p['title']) ?></a>
                            <p class="text-muted small mb-0">Features: <?= $p['num_features'] ?: 'N/A' ?></p>
                        </td>
                        <td>
                            <div class="fw-bold">$<?= number_format($p['actual_amount'] ?: ($p['expected_amount'] ?: 0), 2) ?></div>
                            <small class="text-muted">Expected: $<?= number_format($p['expected_amount'] ?: 0, 2) ?></small>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= $p['start_date'] ? date('M d, Y', strtotime($p['start_date'])) : '--' ?></div>
                            <small class="text-muted">to <?= $p['end_date'] ? date('M d, Y', strtotime($p['end_date'])) : 'Present' ?></small>
                        </td>
                        <td style="max-width: 250px;" class="search-target-stack">
                            <?php foreach (array_slice($stacks, 0, 4) as $s): ?>
                                <span class="tech-badge"><?= htmlspecialchars($s) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($stacks) > 4): ?>
                                <span class="tech-badge bg-secondary text-white">+<?= count($stacks) - 4 ?></span>
                            <?php endif; ?>
                            <?php if (empty($stacks)): ?>
                                <span class="text-muted small">--</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="switch-status-label <?= $p['is_visible'] ? 'status-visible' : 'status-hidden' ?>">
                                <?= $p['is_visible'] ? '<i class="fas fa-eye me-1"></i> Visible' : '<i class="fas fa-eye-slash me-1"></i> Hidden' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <!-- Edit -->
                                <a href="edit-portfolio.php?id=<?= $encryptedId ?>" class="btn-action-round btn-action-edit" title="Edit details">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <!-- Toggle Visibility -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_visibility">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-action-round btn-action-toggle" title="<?= $p['is_visible'] ? 'Hide from public site' : 'Show on public site' ?>">
                                        <i class="fas <?= $p['is_visible'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <button type="button" class="btn-action-round btn-action-delete" onclick="confirmDeleteProject(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')" title="Delete project">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fs-3 d-block mb-3"></i>
                                No portfolio projects created yet. <a href="create-portfolio.php" class="fw-bold">Create your first project!</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Forms for actions -->
<form id="deleteProjectForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteProjectId">
</form>

<script>
function filterProjectTable() {
    const query = document.getElementById('projectSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#projectTable .project-row');
    
    rows.forEach(row => {
        const title = row.querySelector('.search-target-title').textContent.toLowerCase();
        const stack = row.querySelector('.search-target-stack').textContent.toLowerCase();
        
        if (title.includes(query) || stack.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function confirmDeleteProject(id, title) {
    Swal.fire({
        title: 'Delete Portfolio Project?',
        html: `Are you sure you want to permanently delete <strong>"${title}"</strong>? <br><span class="text-danger small"><i class="fas fa-exclamation-triangle"></i> This will delete all associated tech stacks, screenshots, and team records.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteProjectId').value = id;
            document.getElementById('deleteProjectForm').submit();
        }
    });
}
</script>

<?php
require_once $path_to_root . 'includes/dashboard_footer.php';
?>
