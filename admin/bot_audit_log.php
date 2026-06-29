<?php
$path_to_root = "../";
$page_title = "Bot Audit Log";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$actionFilter = $_GET['action'] ?? '';
$searchQ = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($actionFilter) { $where[] = "action = ?"; $params[] = $actionFilter; }
if ($searchQ) { $where[] = "(action LIKE ? OR details LIKE ? OR target_type LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bot_audit_log $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $limit);

    $stmt = $pdo->prepare("SELECT al.*, u.name as user_name, u.email as user_email FROM bot_audit_log al LEFT JOIN users u ON al.user_id = u.id $whereSql ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actions = $pdo->query("SELECT DISTINCT action FROM bot_audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $logs = []; $totalRows = 0; $actions = []; }
?>

<style>
.audit-wrap{font-family:'Inter',system-ui,-apple-system,sans-serif}
.audit-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0f4c75 100%);border-radius:24px;padding:2rem 2.5rem;color:#fff;position:relative;overflow:hidden;margin-bottom:2rem}
.audit-hero::before{content:'';position:absolute;top:-80px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(139,92,246,0.2) 0%,transparent 70%);border-radius:50%}
.audit-hero h1{font-size:1.75rem;font-weight:800;margin:0;background:linear-gradient(135deg,#fff,#c4b5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.audit-hero p{color:rgba(255,255,255,0.5);font-size:0.85rem;margin:0}
.audit-card{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:16px;overflow:hidden}
.audit-toolbar{padding:1rem 1.5rem;border-bottom:1px solid rgba(0,0,0,0.04);display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center}
.audit-table{width:100%;border-collapse:collapse;font-size:0.82rem}
.audit-table th{padding:0.7rem 1rem;text-align:left;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;border-bottom:1.5px solid rgba(0,0,0,0.04);background:#f8fafc}
.audit-table td{padding:0.7rem 1rem;border-bottom:1px solid rgba(0,0,0,0.03)}
.audit-table tr:hover{background:#f8fafc}
.audit-badge{display:inline-flex;align-items:center;gap:4px;padding:0.2rem 0.6rem;border-radius:8px;font-size:0.7rem;font-weight:700;background:#f1f5f9;color:#334155}
.audit-action-filter{padding:0.4rem 0.8rem;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-size:0.75rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s}
.audit-action-filter:hover{border-color:#3b82f6;color:#3b82f6}
.audit-action-filter.active{background:#0f172a;color:#fff;border-color:#0f172a}
.audit-pagination{display:flex;gap:0.3rem}
.audit-pagination a{width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:600;color:#64748b;text-decoration:none;transition:all 0.2s;background:#fff}
.audit-pagination a:hover{border-color:#3b82f6;color:#3b82f6}
.audit-pagination a.active{background:#0f172a;color:#fff;border-color:#0f172a}
</style>

<div class="audit-wrap">
<div class="audit-hero">
    <div style="position:relative;z-index:1">
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);padding:0.35rem 0.9rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:rgba(255,255,255,0.8);margin-bottom:0.75rem"><i class="fas fa-clipboard-list"></i> Audit Trail</div>
        <h1>Bot Audit Log</h1>
        <p>Track all sensitive actions performed through the bot — <?= number_format($totalRows) ?> entries</p>
    </div>
</div>

<div class="audit-card mb-4">
    <div class="audit-toolbar">
        <form method="GET" class="d-flex gap-2 flex-grow-1 flex-wrap">
            <input type="hidden" name="action" value="<?= htmlspecialchars($actionFilter) ?>">
            <div style="position:relative;flex-grow:1;max-width:300px;">
                <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.82rem;"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Search actions, details..." style="width:100%;padding:0.55rem 1rem 0.55rem 2.5rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.85rem;background:#f8fafc;">
            </div>
            <button type="submit" class="btn btn-sm btn-primary" style="background:#0f172a;border-color:#0f172a;border-radius:10px;padding:0.5rem 1rem;"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div style="padding:0.75rem 1.5rem;display:flex;gap:0.4rem;flex-wrap:wrap;border-bottom:1px solid rgba(0,0,0,0.04);">
        <a href="bot_audit_log.php" class="audit-action-filter <?= empty($actionFilter) ? 'active' : '' ?>">All</a>
        <?php foreach ($actions as $a): ?>
        <a href="bot_audit_log.php?action=<?= urlencode($a) ?>&q=<?= urlencode($searchQ) ?>" class="audit-action-filter <?= $actionFilter === $a ? 'active' : '' ?>"><?= htmlspecialchars($a) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($logs)): ?>
    <div class="text-center py-5"><i class="fas fa-clipboard-list d-block mb-3" style="font-size:2rem;color:#e2e8f0;"></i><h5 class="fw-bold text-body">No audit entries found</h5><p class="text-muted" style="font-size:0.85rem;">Audit logs appear when users perform sensitive actions via the bot.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="audit-table">
        <thead><tr><th>Time</th><th>Action</th><th>User</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td style="white-space:nowrap;font-size:0.78rem;color:#64748b;"><?= date('M d, Y H:i', strtotime($l['created_at'])) ?></td>
            <td><span class="audit-badge"><?= htmlspecialchars($l['action']) ?></span></td>
            <td>
                <?php if ($l['user_name']): ?>
                    <div class="fw-bold" style="font-size:0.82rem;"><?= htmlspecialchars($l['user_name']) ?></div>
                    <div style="font-size:0.7rem;color:#94a3b8;"><?= htmlspecialchars($l['user_email'] ?? '') ?></div>
                <?php else: ?>
                    <span style="color:#cbd5e1;">Guest #<?= $l['user_id'] ?? '—' ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:0.78rem;"><?= ($l['target_type'] ?? '—') . ' #' . ($l['target_id'] ?? '') ?></td>
            <td style="font-size:0.78rem;color:#64748b;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['details'] ?? '') ?></td>
            <td style="font-size:0.72rem;color:#94a3b8;font-family:monospace;"><?= htmlspecialchars($l['ip_address'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div style="padding:0.85rem 1.5rem;border-top:1px solid rgba(0,0,0,0.04);display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;color:#94a3b8;">
        <div>Showing <?= count($logs) ?> of <?= number_format($totalRows) ?> entries</div>
        <?php if ($totalPages > 1): ?>
        <div class="audit-pagination">
            <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                <a href="?page=<?= $i ?>&action=<?= urlencode($actionFilter) ?>&q=<?= urlencode($searchQ) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>