<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userId = $_SESSION['user']['id'];
$roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
if (strtolower($roleStmt->fetchColumn()) !== 'admin') { header("Location: " . $path_to_root . "login.php"); exit; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $type = $_POST['type'] ?? 'text';
        $options = trim($_POST['options'] ?? '');
        $placeholder = trim($_POST['placeholder'] ?? '');
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $section = trim($_POST['section'] ?? 'general');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $optionsJson = $options ? json_encode(array_map('trim', explode("\n", $options))) : null;

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO survey_questions (question, type, options, placeholder, is_required, sort_order, section, is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$question, $type, $optionsJson, $placeholder, $isRequired, $sortOrder, $section, $isActive]);
            $_SESSION['success_message'] = "Question added.";
        } else {
            $stmt = $pdo->prepare("UPDATE survey_questions SET question=?, type=?, options=?, placeholder=?, is_required=?, sort_order=?, section=?, is_active=? WHERE id=?");
            $stmt->execute([$question, $type, $optionsJson, $placeholder, $isRequired, $sortOrder, $section, $isActive, $id]);
            $_SESSION['success_message'] = "Question updated.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM survey_questions WHERE id = ?")->execute([$id]);
        $_SESSION['success_message'] = "Question deleted.";
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE survey_questions SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    }
    header("Location: manage_survey.php"); exit;
}

$page_title = "Survey Management";
$current_page = "manage_survey.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$questions = $pdo->query("SELECT * FROM survey_questions ORDER BY sort_order ASC")->fetchAll();

// Stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM user_preferences WHERE survey_completed = 1")->fetchColumn();
$totalQuestions = count($questions);
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-poll me-2 text-primary"></i>Onboarding Survey Manager</h4>
            <p class="text-muted mb-0 small">Create and manage questions for the user onboarding survey.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill px-3 py-2"><i class="fas fa-users me-1"></i><?= $totalUsers ?> completed</span>
            <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fas fa-list me-1"></i><?= $totalQuestions ?> questions</span>
            <a href="survey_responses.php" class="btn btn-outline-primary rounded-pill px-4"><i class="fas fa-chart-bar me-2"></i>View Responses</a>
            <button class="btn btn-gradient-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#questionModal" onclick="resetForm()"><i class="fas fa-plus me-2"></i>Add Question</button>
        </div>
    </div>

    <?php if ($questions): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3" style="width:50px;">#</th>
                        <th class="py-3">Question</th>
                        <th class="py-3" style="width:100px;">Type</th>
                        <th class="py-3" style="width:100px;">Section</th>
                        <th class="py-3 text-center" style="width:80px;">Required</th>
                        <th class="py-3 text-center" style="width:80px;">Order</th>
                        <th class="py-3 text-center" style="width:80px;">Active</th>
                        <th class="py-3 text-end pe-4" style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td class="ps-4"><?= (int)$q['id'] ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($q['question']) ?>
                            <?php if ($q['options']): $opts = json_decode($q['options'], true); ?>
                                <br><small class="text-muted"><?= htmlspecialchars(implode(', ', $opts ?: [])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info-subtle text-info-emphasis rounded-pill"><?= $q['type'] ?></span></td>
                        <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= $q['section'] ?></span></td>
                        <td class="text-center"><?= $q['is_required'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>' ?></td>
                        <td class="text-center"><?= (int)$q['sort_order'] ?></td>
                        <td class="text-center">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                                    <i class="fas <?= $q['is_active'] ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted' ?> fa-lg"></i>
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" data-bs-toggle="modal" data-bs-target="#questionModal"
                                onclick="editForm(<?= htmlspecialchars(json_encode($q), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this question?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="fas fa-poll fa-4x text-muted mb-3"></i>
        <h5 class="fw-bold">No Survey Questions Yet</h5>
        <p class="text-muted">Create your first onboarding question to start collecting user preferences.</p>
        <button class="btn btn-gradient-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#questionModal" onclick="resetForm()"><i class="fas fa-plus me-2"></i>Add Question</button>
    </div>
    <?php endif; ?>
</div>

<!-- Question Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #0A2D5E, #0f172a); color: #fff; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold p-3" id="questionModalLabel"><i class="fas fa-question-circle me-2"></i><span id="modalTitle">Add Question</span></h5>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="p-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Question <span class="text-danger">*</span></label>
                        <textarea name="question" id="fQuestion" class="form-control form-control-theme" rows="2" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Type</label>
                        <select name="type" id="fType" class="form-select form-control-theme" onchange="toggleOptions()">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="select">Select (Dropdown)</option>
                            <option value="radio">Radio (Single Choice)</option>
                            <option value="checkbox">Checkbox (Multiple)</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="number">Number</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Section</label>
                        <select name="section" id="fSection" class="form-select form-control-theme">
                            <option value="personal">Personal</option>
                            <option value="professional">Professional</option>
                            <option value="project">Project</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" id="fSortOrder" class="form-control form-control-theme" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Placeholder</label>
                        <input type="text" name="placeholder" id="fPlaceholder" class="form-control form-control-theme">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-3 pb-2">
                        <div class="form-check">
                            <input type="checkbox" name="is_required" id="fRequired" class="form-check-input" checked>
                            <label class="form-check-label">Required</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="fActive" class="form-check-input" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="col-12" id="optionsGroup" style="display:none;">
                        <label class="form-label fw-semibold">Options <small class="text-muted">(one per line)</small></label>
                        <textarea name="options" id="fOptions" class="form-control form-control-theme" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gradient-primary rounded-pill px-4 fw-bold"><i class="fas fa-save me-2"></i>Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '0';
    document.getElementById('modalTitle').textContent = 'Add Question';
    document.getElementById('fQuestion').value = '';
    document.getElementById('fType').value = 'text';
    document.getElementById('fSection').value = 'personal';
    document.getElementById('fSortOrder').value = '0';
    document.getElementById('fPlaceholder').value = '';
    document.getElementById('fRequired').checked = true;
    document.getElementById('fActive').checked = true;
    document.getElementById('fOptions').value = '';
    toggleOptions();
}

function editForm(q) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = q.id;
    document.getElementById('modalTitle').textContent = 'Edit Question';
    document.getElementById('fQuestion').value = q.question;
    document.getElementById('fType').value = q.type;
    document.getElementById('fSection').value = q.section || 'general';
    document.getElementById('fSortOrder').value = q.sort_order;
    document.getElementById('fPlaceholder').value = q.placeholder || '';
    document.getElementById('fRequired').checked = q.is_required == 1;
    document.getElementById('fActive').checked = q.is_active == 1;
    if (q.options) {
        try {
            const opts = JSON.parse(q.options);
            document.getElementById('fOptions').value = Array.isArray(opts) ? opts.join('\n') : opts;
        } catch(e) { document.getElementById('fOptions').value = q.options; }
    } else {
        document.getElementById('fOptions').value = '';
    }
    toggleOptions();
}

function toggleOptions() {
    const type = document.getElementById('fType').value;
    document.getElementById('optionsGroup').style.display = (type === 'select' || type === 'radio' || type === 'checkbox') ? 'block' : 'none';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
