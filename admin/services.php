<?php
$page_title = 'Manage Services';
$path_to_root = '../';
require_once $path_to_root . 'includes/dashboard_header.php';

// Only allow admins

$message = '';
$messageType = '';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-cogs');
        $category = $_POST['category'] ?? 'service';
        $service_group = trim($_POST['service_group'] ?? 'General');
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
        $price_label = trim($_POST['price_label'] ?? '');
        $currency = trim($_POST['currency'] ?? '₦');
        $features = trim($_POST['features'] ?? '');
        $border_color = trim($_POST['border_color'] ?? '#0984e3');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO services (name, description, icon, category, service_group, price, price_label, currency, features, border_color, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $icon, $category, $service_group, $price, $price_label, $currency, $features, $border_color, $display_order, $is_active]);
            $message = 'Service added successfully!';
            $messageType = 'success';
        }
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-cogs');
        $category = $_POST['category'] ?? 'service';
        $service_group = trim($_POST['service_group'] ?? 'General');
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
        $price_label = trim($_POST['price_label'] ?? '');
        $currency = trim($_POST['currency'] ?? '₦');
        $features = trim($_POST['features'] ?? '');
        $border_color = trim($_POST['border_color'] ?? '#0984e3');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE services SET name=?, description=?, icon=?, category=?, service_group=?, price=?, price_label=?, currency=?, features=?, border_color=?, display_order=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $description, $icon, $category, $service_group, $price, $price_label, $currency, $features, $border_color, $display_order, $is_active, $id]);
            $message = 'Service updated successfully!';
            $messageType = 'success';
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
            $message = 'Service deleted successfully!';
            $messageType = 'warning';
        }
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            $message = 'Service visibility toggled.';
            $messageType = 'info';
        }
    }
}

// Fetch all services
$services = $pdo->query("SELECT * FROM services ORDER BY service_group ASC, category ASC, display_order ASC")->fetchAll();
$serviceItems = array_filter($services, fn($s) => $s['category'] === 'service');
$pricingItems = array_filter($services, fn($s) => $s['category'] === 'pricing');

// If editing, fetch the item
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}
?>

<style>
/* ===== Admin Services Management Premium Styles ===== */
/* Prevent horizontal scroll */
body, .wrapper, .main-wrapper, .container-fluid {
  overflow-x: hidden !important;
}

.svc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}
.svc-header h2 {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    font-weight: 800;
    color: #0f172a;
    font-size: 1.6rem;
    margin: 0;
}
.svc-header .badge-count {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Alert Banner */
.svc-alert {
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
.svc-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.svc-alert.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.svc-alert.info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Section Tabs */
.svc-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0;
}
.svc-tab {
    padding: 0.6rem 1.25rem;
    border: none;
    background: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.9rem;
    color: #64748b;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.25s;
}
.svc-tab:hover { color: #0f172a; }
.svc-tab.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

/* Table Card */
.svc-table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 2rem;
}
.svc-table-card .card-header-bar {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.svc-table-card .card-header-bar h5 {
    font-weight: 700;
    margin: 0;
    color: #1e293b;
    font-size: 1rem;
}
.svc-table {
    width: 100%;
    border-collapse: collapse;
}
.svc-table thead th {
    background:var(--color-bg);
    padding: 0.75rem 1rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.svc-table tbody td {
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.svc-table tbody tr:hover {
    background:var(--color-bg);
}
.svc-table .icon-preview {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0a2d5e, #163f7a);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}
.svc-table .svc-name {
    font-weight: 700;
    color: #0f172a;
}
.svc-table .svc-desc {
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}
.svc-badge-active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    padding: 0.15rem 0.5rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
}
.svc-badge-inactive {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 0.15rem 0.5rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
}
.svc-price-display {
    font-weight: 800;
    color: #0f172a;
    font-size: 0.95rem;
}

/* Action Buttons */
.btn-svc-edit {
    background: #eff6ff;
    color: #3b82f6;
    border: 1px solid #bfdbfe;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-svc-edit:hover {
    background: #3b82f6;
    color: white;
}
.btn-svc-delete {
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-svc-delete:hover {
    background: #ef4444;
    color: white;
}
.btn-svc-toggle {
    background: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-svc-toggle:hover {
    background: #7c3aed;
    color: white;
}

/* Form Card */
.svc-form-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    padding: 2rem;
    margin-bottom: 2rem;
}
.svc-form-card h4 {
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 1.25rem;
    font-size: 1.15rem;
}
.svc-form-card label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #334155;
    margin-bottom: 0.3rem;
}
.svc-form-card .form-control,
.svc-form-card .form-select {
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    padding: 0.6rem 0.9rem;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}
.svc-form-card .form-control:focus,
.svc-form-card .form-select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
.btn-svc-submit {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border: none;
    padding: 0.65rem 1.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.25s;
}
.btn-svc-submit:hover {
    background: linear-gradient(135deg, #4338ca, #6d28d9);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
}
.btn-svc-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 0.65rem 1.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-svc-cancel:hover {
    background: #e2e8f0;
    color: #1e293b;
}

/* Color Swatch Preview */
.color-swatch {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.1);
    vertical-align: middle;
    margin-right: 0.3rem;
}

/* ===== Comprehensive Responsive Styles ===== */
@media (max-width: 991.98px) {
    .svc-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .svc-tabs {
        flex-wrap: wrap;
    }
    .svc-tab {
        flex: 1 1 auto;
        padding: 0.5rem 0.8rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 767.98px) {
    .svc-header h2 {
        font-size: 1.3rem;
    }
    .svc-table-card, .svc-form-card {
        border-radius: 12px;
        padding: 1rem;
    }
    .svc-form-card h4 {
        font-size: 1rem;
    }
    .btn-svc-submit, .btn-svc-cancel {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
    .d-flex.gap-2.mt-4 {
        flex-direction: column;
    }
}

@media (max-width: 575.98px) {
    .svc-header {
        gap: 0.75rem;
    }
    .svc-header h2 {
        font-size: 1.15rem;
    }
    .svc-table thead th, .svc-table tbody td {
        padding: 0.5rem;
        font-size: 0.8rem;
    }
    .icon-preview {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }
    .svc-form-card {
        padding: 1rem;
    }
}

@media (max-width: 479.98px) {
    .svc-header h2 {
        font-size: 1rem;
    }
    .svc-table thead th, .svc-table tbody td {
        padding: 0.4rem;
        font-size: 0.78rem;
    }
}

@media (max-width: 399.98px) {
    .svc-tab {
        font-size: 0.78rem;
        padding: 0.45rem 0.6rem;
    }
    .svc-table thead th, .svc-table tbody td {
        font-size: 0.75rem;
    }
}

/* Section panel toggling */
.svc-panel { display: none; }
.svc-panel.active { display: block; }
</style>

<!-- Services Management Content -->
<div class="container-fluid px-lg-4">

<?php if ($message): ?>
    <div class="svc-alert <?= $messageType ?>">
        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle') ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="svc-header">
    <div>
        <h2><i class="fas fa-cogs me-2" style="color:#4f46e5;"></i> Manage Services & Pricing</h2>
        <p class="text-muted mb-0" style="font-size:0.88rem;">Add, edit, or remove services and pricing plans displayed on the landing page.</p>
    </div>
    <span class="badge-count"><?= count($services) ?> Total Items</span>
</div>

<!-- Tabs -->
<div class="svc-tabs">
    <button class="svc-tab active" data-panel="services-panel" onclick="switchPanel(this)">
        <i class="fas fa-puzzle-piece me-1"></i> Services (<?= count($serviceItems) ?>)
    </button>
    <button class="svc-tab" data-panel="pricing-panel" onclick="switchPanel(this)">
        <i class="fas fa-tags me-1"></i> Pricing (<?= count($pricingItems) ?>)
    </button>
    <button class="svc-tab" data-panel="form-panel" onclick="switchPanel(this)">
        <i class="fas fa-plus-circle me-1"></i> <?= $editItem ? 'Edit Item' : 'Add New' ?>
    </button>
</div>

<!-- Services Table -->
<div id="services-panel" class="svc-panel active">
    <div class="svc-table-card">
        <div class="card-header-bar">
            <h5><i class="fas fa-puzzle-piece me-2" style="color:#4f46e5;"></i> Service Listings</h5>
        </div>
        <div class="table-responsive">
            <table class="svc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Icon</th>
                        <th>Service Name</th>
                        <th>Category / Group</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceItems as $svc): ?>
                    <tr>
                        <td><?= $svc['id'] ?></td>
                        <td><span class="icon-preview"><i class="<?= htmlspecialchars($svc['icon']) ?>"></i></span></td>
                        <td>
                            <div class="svc-name"><?= htmlspecialchars($svc['name']) ?></div>
                            <p class="svc-desc"><?= htmlspecialchars($svc['description'] ?? '') ?></p>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($svc['service_group'] ?? 'General') ?></span></td>
                        <td><?= $svc['display_order'] ?></td>
                        <td>
                            <span class="<?= $svc['is_active'] ? 'svc-badge-active' : 'svc-badge-inactive' ?>">
                                <?= $svc['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="?edit=<?= $svc['id'] ?>" class="btn-svc-edit"><i class="fas fa-pen"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle visibility?')">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $svc['id'] ?>">
                                    <button type="submit" class="btn-svc-toggle"><i class="fas fa-eye"></i></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this service permanently?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $svc['id'] ?>">
                                    <button type="submit" class="btn-svc-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($serviceItems)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No services yet. Add one below!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pricing Table -->
<div id="pricing-panel" class="svc-panel">
    <div class="svc-table-card">
        <div class="card-header-bar">
            <h5><i class="fas fa-tags me-2" style="color:#7c3aed;"></i> Pricing Plans</h5>
        </div>
        <div class="table-responsive">
            <table class="svc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Color</th>
                        <th>Plan Name</th>
                        <th>Price</th>
                        <th>Features</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricingItems as $prc): ?>
                    <tr>
                        <td><?= $prc['id'] ?></td>
                        <td><span class="color-swatch" style="background:<?= htmlspecialchars($prc['border_color']) ?>"></span></td>
                        <td>
                            <div class="svc-name"><?= htmlspecialchars($prc['name']) ?></div>
                            <p class="svc-desc"><?= htmlspecialchars($prc['price_label'] ?? '') ?></p>
                        </td>
                        <td>
                            <span class="svc-price-display"><?= htmlspecialchars($prc['currency']) ?><?= number_format($prc['price'], 0) ?></span>
                        </td>
                        <td style="max-width:200px;white-space:pre-line;font-size:0.78rem;color:#64748b;"><?= htmlspecialchars($prc['features'] ?? '') ?></td>
                        <td><?= $prc['display_order'] ?></td>
                        <td>
                            <span class="<?= $prc['is_active'] ? 'svc-badge-active' : 'svc-badge-inactive' ?>">
                                <?= $prc['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="?edit=<?= $prc['id'] ?>" class="btn-svc-edit"><i class="fas fa-pen"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle visibility?')">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $prc['id'] ?>">
                                    <button type="submit" class="btn-svc-toggle"><i class="fas fa-eye"></i></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this pricing plan permanently?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $prc['id'] ?>">
                                    <button type="submit" class="btn-svc-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pricingItems)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No pricing plans yet. Add one below!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Form -->
<div id="form-panel" class="svc-panel <?= $editItem ? 'active' : '' ?>">
    <div class="svc-form-card">
        <h4>
            <i class="fas <?= $editItem ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2" style="color:#4f46e5;"></i>
            <?= $editItem ? 'Edit: ' . htmlspecialchars($editItem['name']) : 'Add New Service or Pricing Plan' ?>
        </h4>
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Category -->
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" id="categorySelect" onchange="togglePricingFields()">
                        <option value="service" <?= ($editItem && $editItem['category'] === 'service') ? 'selected' : '' ?>>Service</option>
                        <option value="pricing" <?= ($editItem && $editItem['category'] === 'pricing') ? 'selected' : '' ?>>Pricing Plan</option>
                    </select>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
                </div>

                <!-- Service Group -->
                <div class="col-md-12" id="group-field" style="display: <?= ($editItem && $editItem['category'] === 'pricing') ? 'none' : 'block' ?>;">
                    <label class="form-label">Service Group / Categorization</label>
                    <input type="text" name="service_group" class="form-control" list="groupOptions" placeholder="e.g. Fintech Solutions, Healthcare Technology" value="<?= htmlspecialchars($editItem['service_group'] ?? 'General') ?>">
                    <datalist id="groupOptions">
                        <?php 
                        $groups = array_unique(array_column($serviceItems, 'service_group'));
                        foreach($groups as $g): if($g): ?>
                            <option value="<?= htmlspecialchars($g) ?>">
                        <?php endif; endforeach; ?>
                    </datalist>
                    <small class="text-muted">Used to group services together on the landing page.</small>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
                </div>

                <!-- Icon -->
                <div class="col-md-4">
                    <label class="form-label">FontAwesome Icon Class</label>
                    <input type="text" name="icon" class="form-control" placeholder="fas fa-globe" value="<?= htmlspecialchars($editItem['icon'] ?? 'fas fa-cogs') ?>">
                    <small class="text-muted">e.g. fas fa-globe, fas fa-wallet, fas fa-robot</small>
                </div>

                <!-- Display Order -->
                <div class="col-md-4">
                    <label class="form-label">Display Order</label>
                    <input type="number" name="display_order" class="form-control" min="0" value="<?= intval($editItem['display_order'] ?? 0) ?>">
                </div>

                <!-- Active -->
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Visible on Landing Page</label>
                    </div>
                </div>

                <!-- Pricing-only fields -->
                <div id="pricing-fields" style="display: <?= ($editItem && $editItem['category'] === 'pricing') ? 'block' : 'none' ?>;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($editItem['currency'] ?? '₦') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price (numeric)</label>
                            <input type="number" name="price" class="form-control" step="0.01" value="<?= $editItem['price'] ?? '' ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Price Label</label>
                            <input type="text" name="price_label" class="form-control" placeholder="/simple app" value="<?= htmlspecialchars($editItem['price_label'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Border Color</label>
                            <input type="color" name="border_color" class="form-control form-control-color" value="<?= htmlspecialchars($editItem['border_color'] ?? '#0984e3') ?>" style="height:42px;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Features (one per line)</label>
                            <textarea name="features" class="form-control" rows="5" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?= htmlspecialchars($editItem['features'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn-svc-submit">
                    <i class="fas <?= $editItem ? 'fa-save' : 'fa-plus' ?> me-1"></i>
                    <?= $editItem ? 'Update Service' : 'Add Service' ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="services.php" class="btn-svc-cancel">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

</div><!-- /.container-fluid -->

<script>
function switchPanel(btn) {
    document.querySelectorAll('.svc-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.svc-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.panel).classList.add('active');
}

function togglePricingFields() {
    const cat = document.getElementById('categorySelect').value;
    document.getElementById('pricing-fields').style.display = cat === 'pricing' ? 'block' : 'none';
    document.getElementById('group-field').style.display = cat === 'pricing' ? 'none' : 'block';
}

// If editing, auto-switch to form tab
<?php if ($editItem): ?>
document.addEventListener('DOMContentLoaded', () => {
    const formTab = document.querySelector('[data-panel="form-panel"]');
    if (formTab) switchPanel(formTab);
});
<?php endif; ?>
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
