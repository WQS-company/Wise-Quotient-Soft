<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userId = $_SESSION['user']['id'];
$roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
if (strtolower($roleStmt->fetchColumn()) !== 'admin') { header("Location: " . $path_to_root . "login.php"); exit; }

$page_title = "Survey Responses";
$current_page = "survey_responses.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch all completed surveys
$completed = $pdo->query("SELECT up.*, u.name, u.email, u.phone, u.created_at AS registered_at
    FROM user_preferences up JOIN users u ON u.id = up.user_id
    WHERE up.survey_completed = 1 ORDER BY up.survey_completed_at DESC")->fetchAll();

// All questions for column headers
$questions = $pdo->query("SELECT id, question FROM survey_questions WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$questionIds = array_column($questions, 'id');

// Fetch individual responses
$allResponses = [];
if ($questionIds) {
    $ids = implode(',', $questionIds);
    $respStmt = $pdo->query("SELECT sr.user_id, sr.question_id, sr.response FROM survey_responses sr WHERE sr.question_id IN ($ids)");
    while ($r = $respStmt->fetch(PDO::FETCH_ASSOC)) {
        $allResponses[$r['user_id']][$r['question_id']] = $r['response'];
    }
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="survey_responses.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Phone', 'Registered', 'Completed', ...array_column($questions, 'question')]);
    foreach ($completed as $c) {
        $row = [$c['name'], $c['email'], $c['phone'], $c['registered_at'], $c['survey_completed_at']];
        foreach ($questionIds as $qId) {
            $row[] = $allResponses[$c['user_id']][$qId] ?? '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-pie me-2 text-primary"></i>Survey Responses</h4>
            <p class="text-muted mb-0 small">Monitor all user onboarding survey submissions.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill px-3 py-2"><i class="fas fa-users me-1"></i><?= count($completed) ?> completed</span>
            <span class="badge bg-info rounded-pill px-3 py-2"><i class="fas fa-question me-1"></i><?= count($questions) ?> questions</span>
            <a href="?export=csv" class="btn btn-outline-success rounded-pill px-4"><i class="fas fa-download me-2"></i>Export CSV</a>
            <a href="manage_survey.php" class="btn btn-outline-primary rounded-pill px-4"><i class="fas fa-cog me-2"></i>Manage Survey</a>
        </div>
    </div>

    <?php if ($completed): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="responsesTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">User</th>
                        <th class="py-3">Email / Phone</th>
                        <?php foreach ($questions as $q): ?>
                        <th class="py-3" style="max-width:200px; white-space:nowrap;" title="<?= htmlspecialchars($q['question']) ?>"><?= htmlspecialchars(mb_substr($q['question'], 0, 30)) ?></th>
                        <?php endforeach; ?>
                        <th class="py-3 text-nowrap">Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed as $c): ?>
                    <tr>
                        <td class="ps-4 fw-medium"><?= htmlspecialchars($c['name']) ?></td>
                        <td><small><?= htmlspecialchars($c['email']) ?><br><?= htmlspecialchars($c['phone'] ?? '') ?></small></td>
                        <?php foreach ($questionIds as $qId): ?>
                        <td><small><?= htmlspecialchars(mb_substr($allResponses[$c['user_id']][$qId] ?? '', 0, 60)) ?></small></td>
                        <?php endforeach; ?>
                        <td class="text-nowrap small text-muted"><?= $c['survey_completed_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
        <h5 class="fw-bold">No Responses Yet</h5>
        <p class="text-muted">Users haven't completed the onboarding survey yet.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
