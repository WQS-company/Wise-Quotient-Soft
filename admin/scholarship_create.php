<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || !in_array(strtolower($userRoleObj['role']), ['admin','developer'])) {
    header("Location: " . $path_to_root . "login.php"); exit;
}

$isEdit = isset($_GET['edit']) && !empty($_GET['edit']);
$editData = null;
$categories = [];
$sponsors = [];

try {
    $categories = $pdo->query("SELECT id, name, icon, color FROM scholarship_categories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

try {
    $sponsors = $pdo->query("SELECT id, name, logo FROM scholarship_sponsors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$page_title = $isEdit ? "Edit Scholarship" : "Create Scholarship";
$current_page = "scholarship_create.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$bannerDir = __DIR__ . '/../uploads/scholarships/';
if (!is_dir($bannerDir)) @mkdir($bannerDir, 0755, true);

function genScholarshipCode() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = 'SCH';
    for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    return $code;
}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.sc-form-section{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;margin-bottom:1.25rem;overflow:hidden}
.sc-form-section-head{padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:.75rem;cursor:pointer;user-select:none;transition:background .2s}
.sc-form-section-head:hover{background:var(--color-bg)}
.sc-form-section-head .sec-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.sc-form-section-head h6{margin:0;font-weight:700;font-size:.9rem;flex:1}
.sc-form-section-head .sec-toggle{font-size:.7rem;color:#94a3b8;transition:transform .3s}
.sc-form-section-head.collapsed .sec-toggle{transform:rotate(-90deg)}
.sc-form-section-body{padding:1.25rem;display:block}
.sc-form-section-body.collapsed{display:none}
.sc-lbl{font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block}
.sc-lbl small{font-weight:400;color:#94a3b8;margin-left:4px}
.sc-input{border:1px solid var(--color-border);border-radius:10px;padding:.6rem .9rem;font-size:.88rem;width:100%;transition:border-color .2s,box-shadow .2s;background:var(--color-card-bg);color:var(--color-text)}
.sc-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
select.sc-input{cursor:pointer}
.sc-toggle{position:relative;width:44px;height:24px;display:inline-block;vertical-align:middle}
.sc-toggle input{opacity:0;width:0;height:0}
.sc-toggle .slider{position:absolute;inset:0;border-radius:12px;cursor:pointer;transition:background .3s;background:#cbd5e1}
.sc-toggle .slider::before{content:'';position:absolute;width:20px;height:20px;border-radius:50%;background:white;top:2px;left:2px;transition:left .3s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.sc-toggle input:checked+.slider{background:#10b981}
.sc-toggle input:checked+.slider::before{left:22px}
.level-chips{display:flex;flex-wrap:wrap;gap:.5rem}
.level-chip{position:relative;cursor:pointer}
.level-chip input{position:absolute;opacity:0;width:0;height:0}
.level-chip .lc-inner{padding:.4rem .75rem;border:2px solid var(--color-border);border-radius:10px;font-size:.78rem;font-weight:500;transition:all .2s;display:inline-block}
.level-chip input:checked+.lc-inner{border-color:#3b82f6;background:#dbeafe;color:#1d4ed8;font-weight:600}
.level-chip:hover .lc-inner{border-color:#93c5fd}
.banner-preview{max-width:100%;max-height:200px;border-radius:12px;border:2px dashed var(--color-border);object-fit:cover;display:none;margin-top:.75rem}
@media(max-width:767.98px){.sc-form-section-body{padding:1rem}}
</style>

<div class="container-fluid px-3 px-lg-4">

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-graduation-cap me-2"></i><?= $isEdit ? 'Edit Scholarship' : 'Create New Scholarship' ?></h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem"><?= $isEdit ? 'Update scholarship details and settings' : 'Fill in the details to publish a new scholarship opportunity' ?></p>
        </div>
        <a href="scholarships.php" class="btn btn-outline-light btn-sm rounded-pill mt-2 mt-md-0"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" style="font-size:.88rem">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- FORM -->
<form id="scholarshipForm" enctype="multipart/form-data">
<input type="hidden" name="id" id="scholarshipId" value="<?= $editData['id'] ?? '' ?>">

<!-- SECTION 1: Basic Information -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-info-circle"></i></div>
        <h6>Basic Information</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="sc-lbl">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" id="fTitle" class="sc-input" required placeholder="e.g. Federal Government Scholarship 2026" value="<?= htmlspecialchars($editData['title'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Code</label>
                <input type="text" name="code" id="fCode" class="sc-input" placeholder="Auto-generated" value="<?= htmlspecialchars($editData['code'] ?? genScholarshipCode()) ?>" readonly style="background:#f8fafc">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Category</label>
                <select name="category_id" class="sc-input" id="fCategory">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($editData['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Sponsor</label>
                <select name="sponsor_id" class="sc-input" id="fSponsor">
                    <option value="">-- Select Sponsor --</option>
                    <?php foreach ($sponsors as $sp): ?>
                        <option value="<?= $sp['id'] ?>" <?= ($editData['sponsor_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Scholarship Type <span class="text-danger">*</span></label>
                <select name="scholarship_type" class="sc-input" required>
                    <?php
                    $types = ['fully_funded'=>'Fully Funded','partially_funded'=>'Partially Funded','tuition_only'=>'Tuition Only','research_grant'=>'Research Grant','student_support'=>'Student Support'];
                    foreach ($types as $val => $lbl):
                    ?>
                        <option value="<?= $val ?>" <?= ($editData['scholarship_type'] ?? 'fully_funded') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="sc-lbl">Description</label>
                <textarea name="description" class="sc-input" rows="4" placeholder="Describe the scholarship opportunity..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="sc-lbl">Eligibility Criteria</label>
                <textarea name="eligibility" class="sc-input" rows="3" placeholder="Who is eligible to apply?"><?= htmlspecialchars($editData['eligibility'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="sc-lbl">Benefits</label>
                <textarea name="benefits" class="sc-input" rows="3" placeholder="What does the scholarship cover?"><?= htmlspecialchars($editData['benefits'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: Slots & Financials -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-coins"></i></div>
        <h6>Slots & Financial Details</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="sc-lbl">Available Slots</label>
                <input type="number" name="slots" class="sc-input" min="0" value="<?= $editData['slots'] ?? 0 ?>" placeholder="0 for unlimited">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Award Amount</label>
                <input type="number" name="amount" class="sc-input" step="0.01" min="0" value="<?= $editData['amount'] ?? 0 ?>" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Currency</label>
                <select name="currency" class="sc-input">
                    <?php foreach (['NGN'=>'₦ NGN','USD'=>'$ USD','GBP'=>'£ GBP','EUR'=>'€ EUR'] as $cVal => $cLbl): ?>
                        <option value="<?= $cVal ?>" <?= ($editData['currency'] ?? 'NGN') === $cVal ? 'selected' : '' ?>><?= $cLbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3: Academic Levels -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-user-graduate"></i></div>
        <h6>Academic Levels</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <label class="sc-lbl">Select eligible academic levels <small>(hold Ctrl/Cmd to select multiple)</small></label>
        <div class="level-chips" id="levelChips">
            <?php
            $levels = ['Secondary School','Diploma','NCE','HND','Undergraduate','Masters','PhD'];
            $savedLevels = !empty($editData['academic_level']) ? explode(',', $editData['academic_level']) : [];
            foreach ($levels as $level):
            ?>
            <label class="level-chip">
                <input type="checkbox" name="academic_levels[]" value="<?= $level ?>" <?= in_array($level, $savedLevels) ? 'checked' : '' ?>>
                <div class="lc-inner"><?= $level ?></div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTION 4: Location & Institution -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-map-marker-alt"></i></div>
        <h6>Location & Institution</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="sc-lbl">Country</label>
                <input type="text" name="country" class="sc-input" placeholder="e.g. Nigeria" value="<?= htmlspecialchars($editData['country'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">State</label>
                <input type="text" name="state" class="sc-input" placeholder="e.g. Lagos" value="<?= htmlspecialchars($editData['state'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Institution</label>
                <input type="text" name="institution" class="sc-input" placeholder="e.g. University of Lagos" value="<?= htmlspecialchars($editData['institution'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="sc-lbl">Course Restrictions <small>(comma-separated, leave blank for all)</small></label>
                <input type="text" name="course_restrictions" class="sc-input" placeholder="e.g. Computer Science, Engineering, Medicine" value="<?= htmlspecialchars($editData['course_restrictions'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

<!-- SECTION 5: Dates -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#fce7f3;color:#be185d"><i class="fas fa-calendar-alt"></i></div>
        <h6>Important Dates</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="sc-lbl">Start Date</label>
                <input type="date" name="start_date" class="sc-input" value="<?= $editData['start_date'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Closing Date</label>
                <input type="date" name="closing_date" class="sc-input" value="<?= $editData['closing_date'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Interview Date</label>
                <input type="date" name="interview_date" class="sc-input" value="<?= $editData['interview_date'] ?? '' ?>">
            </div>
        </div>
    </div>
</div>

<!-- SECTION 6: Banner & Terms -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#e0e7ff;color:#4f46e5"><i class="fas fa-image"></i></div>
        <h6>Banner & Terms</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="sc-lbl">Banner Image</label>
                <input type="file" name="banner" class="sc-input" accept="image/*" id="bannerInput" onchange="previewBanner(this)">
                <?php if (!empty($editData['banner'])): ?>
                    <img src="<?= $path_to_root . htmlspecialchars($editData['banner']) ?>" class="banner-preview" id="bannerPreview" style="display:block">
                <?php else: ?>
                    <img class="banner-preview" id="bannerPreview">
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="sc-lbl">Terms & Conditions</label>
                <textarea name="terms" class="sc-input" rows="6" placeholder="Scholarship terms, conditions, and disclaimers..."><?= htmlspecialchars($editData['terms'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 7: Status & Toggles -->
<div class="sc-form-section">
    <div class="sc-form-section-head" onclick="toggleSection(this)">
        <div class="sec-icon" style="background:#d1fae5;color:#065f46"><i class="fas fa-toggle-on"></i></div>
        <h6>Status & Visibility</h6>
        <i class="fas fa-chevron-down sec-toggle"></i>
    </div>
    <div class="sc-form-section-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="sc-lbl">Publication Status</label>
                <select name="status" class="sc-input">
                    <option value="draft" <?= ($editData['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($editData['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="closed" <?= ($editData['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Active Status</label>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <label class="sc-toggle"><input type="checkbox" name="is_active" id="fActive" <?= !empty($editData['is_active']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    <span style="font-size:.85rem" id="activeLabel"><?= !empty($editData['is_active']) ? 'Active' : 'Inactive' ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="sc-lbl">Featured</label>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <label class="sc-toggle"><input type="checkbox" name="is_featured" id="fFeatured" <?= !empty($editData['is_featured']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    <span style="font-size:.85rem" id="featuredLabel"><?= !empty($editData['is_featured']) ? 'Featured' : 'Not Featured' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit -->
<div class="d-flex gap-3 mb-5">
    <button type="submit" id="submitBtn" class="btn btn-primary rounded-pill px-5 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.9rem;box-shadow:0 4px 16px rgba(59,130,246,.3)">
        <i class="fas fa-save me-2"></i><?= $isEdit ? 'Update Scholarship' : 'Create Scholarship' ?>
    </button>
    <a href="scholarships.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">Cancel</a>
</div>

</form>
</div>

<script>
const API = '../api/scholarship_api.php';

function toggleSection(head) {
    const body = head.nextElementSibling;
    head.classList.toggle('collapsed');
    body.classList.toggle('collapsed');
}

document.getElementById('scholarshipForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

    try {
        const fd = new FormData(this);

        const levels = [];
        document.querySelectorAll('input[name="academic_levels[]"]:checked').forEach(cb => levels.push(cb.value));
        fd.delete('academic_levels[]');
        fd.append('academic_level', levels.join(','));

        const id = document.getElementById('scholarshipId').value;
        fd.append('action', id ? 'update_scholarship' : 'create_scholarship');
        if (id) fd.append('id', id);

        fd.append('is_active', document.getElementById('fActive').checked ? 1 : 0);
        fd.append('is_featured', document.getElementById('fFeatured').checked ? 1 : 0);

        const resp = await fetch(API, { method: 'POST', body: fd });
        const result = await resp.json();

        if (result.success) {
            if (!id && result.id) {
                document.getElementById('scholarshipId').value = result.id;
                window.history.replaceState(null, '', '?edit=' + result.id);
            }
            showAlert('success', 'Scholarship saved successfully!');
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Saved!';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = originalText; }, 2000);
        } else {
            showAlert('danger', result.error || 'Failed to save scholarship.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        showAlert('danger', 'Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

function previewBanner(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('bannerPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('fActive').addEventListener('change', function() {
    document.getElementById('activeLabel').textContent = this.checked ? 'Active' : 'Inactive';
});
document.getElementById('fFeatured').addEventListener('change', function() {
    document.getElementById('featuredLabel').textContent = this.checked ? 'Featured' : 'Not Featured';
});

function showAlert(type, message) {
    const existing = document.querySelector('.alert-dismissible');
    if (existing) existing.remove();

    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const bg = type === 'success' ? 'linear-gradient(135deg,#d1fae5,#a7f3d0)' : 'linear-gradient(135deg,#fee2e2,#fca5a5)';
    const color = type === 'success' ? '#065f46' : '#7f1d1d';

    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show rounded-3 mb-3`;
    div.style.cssText = `background:${bg};color:${color};font-size:.88rem;border:none`;
    div.innerHTML = `<div class="d-flex align-items-center"><i class="fas ${icon} me-2 fs-5"></i><div>${message}</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;

    const hero = document.querySelector('.container-fluid > div');
    if (hero && hero.nextElementSibling) {
        hero.parentNode.insertBefore(div, hero.nextElementSibling);
    }
}

<?php if ($isEdit): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('fActive').dispatchEvent(new Event('change'));
    document.getElementById('fFeatured').dispatchEvent(new Event('change'));
});
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>