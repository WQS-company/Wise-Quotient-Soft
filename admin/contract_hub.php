<?php
$path_to_root = "../";
$page_title = "Contract Agreement Hub";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$user = $headerUser;
if ($user['role'] !== 'admin') {
    die("Unauthorized access.");
}

// Handle Delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delId = wqs_decrypt_id($_GET['delete']);
    if ($delId > 0) $pdo->prepare("DELETE FROM contracts WHERE id = ?")->execute([(int)$delId]);
    echo "<script>window.location.href='contract_hub.php';</script>";
    exit;
}

// Fetch Contracts
$contracts = [];
$stmt = $pdo->query("SELECT * FROM contracts ORDER BY created_at DESC");
if ($stmt) {
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-body-emphasis mb-0"><i class="fas fa-file-contract me-2 text-primary"></i> Contract Agreement Hub</h4>
    <a href="create_contract.php" class="btn btn-primary fw-bold rounded-pill px-4">
        <i class="fas fa-plus me-1"></i> Create Agreement
    </a>
</div>

<div class="card-theme">
    <div class="card-theme-header">
        <h5 class="card-theme-title text-body"><i class="fas fa-list me-2 text-primary"></i> All Contracts</h5>
    </div>
    <div class="card-theme-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
                <thead class="bg-body-tertiary">
                    <tr>
                        <th class="ps-4 py-3">ID</th>
                        <th class="py-3">Client / Organization</th>
                        <th class="py-3">Project Title</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Date Created</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($contracts)): ?>
                        <?php foreach ($contracts as $c): 
                            $statusColors = ['draft'=>'bg-secondary','active'=>'bg-primary','completed'=>'bg-success','terminated'=>'bg-danger'];
                            $sColor = $statusColors[$c['status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td class="ps-4 py-3 fw-bold">#<?= $c['id'] ?></td>
                            <td class="py-3">
                                <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($c['client_name']) ?></div>
                                <?php if (!empty($c['client_org'])): ?>
                                    <div class="small text-muted"><i class="far fa-building me-1"></i><?= htmlspecialchars($c['client_org']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 fw-semibold text-body"><?= htmlspecialchars($c['project_title']) ?></td>
                            <td class="py-3 text-success fw-bold">₦<?= number_format($c['contract_amount'], 2) ?></td>
                            <td class="py-3"><span class="badge <?= $sColor ?>"><?= ucfirst($c['status']) ?></span></td>
                            <td class="py-3 text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            <td class="pe-4 py-3 text-end">
                                <div class="btn-group">
                                    <?php if ($c['status'] === 'pending_signature'): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="copyLink('<?= htmlspecialchars('https://wisequotientsoft.com/sign_contract.php?id='.wqs_encrypt_id($c['id']).'&token='.$c['token']) ?>')" title="Copy Client Signing Link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="view_contract.php?id=<?= wqs_encrypt_id($c['id']) ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View / Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="contract_hub.php?delete=<?= wqs_encrypt_id($c['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this contract?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-file-contract fa-3x mb-3 text-secondary"></i>
                                <h5>No contracts found</h5>
                                <p class="mb-0">Click the 'Create Agreement' button to draft your first contract.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyLink(link) {
    navigator.clipboard.writeText(link).then(function() {
        alert('Signing link copied to clipboard:\n' + link);
    }, function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy. ' + link);
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
