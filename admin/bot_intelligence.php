<?php
$path_to_root = "../";
$page_title = "Bot Intelligence";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// Fetch stats
$stats = ['total_conversations' => 0, 'total_messages' => 0, 'avg_sentiment' => 'neutral', 'top_leads' => [], 'sentiment_dist' => [], 'recent_audit' => [], 'agent_perf' => []];

try {
    $stats['total_conversations'] = (int)$pdo->query("SELECT COUNT(*) FROM bot_conversation_analytics")->fetchColumn();
    $stats['total_messages'] = (int)$pdo->query("SELECT SUM(message_count) FROM bot_conversation_analytics")->fetchColumn();
    $stats['total_memories'] = (int)$pdo->query("SELECT COUNT(*) FROM bot_chat_memory")->fetchColumn();
    $stats['total_rate_limits'] = (int)$pdo->query("SELECT COUNT(*) FROM bot_rate_limits WHERE count > 10")->fetchColumn();

    $sentStmt = $pdo->query("SELECT sentiment, COUNT(*) as cnt FROM bot_chat_sentiment GROUP BY sentiment ORDER BY cnt DESC");
    while ($row = $sentStmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['sentiment_dist'][] = $row;
    }

    $leadStmt = $pdo->query("SELECT bls.user_id, u.name, u.email, bls.score, bls.factors FROM bot_lead_scores bls JOIN users u ON bls.user_id = u.id ORDER BY bls.score DESC LIMIT 10");
    $stats['top_leads'] = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

    $auditStmt = $pdo->query("SELECT * FROM bot_audit_log ORDER BY created_at DESC LIMIT 20");
    $stats['recent_audit'] = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    $followUpStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM bot_follow_ups GROUP BY status");
    $stats['follow_ups'] = $followUpStmt->fetchAll(PDO::FETCH_ASSOC);

    $analyticsStmt = $pdo->query("SELECT DATE(started_at) as day, COUNT(*) as convos, SUM(message_count) as msgs FROM bot_conversation_analytics WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(started_at) ORDER BY day ASC");
    $stats['daily_chart'] = $analyticsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.bi-wrap{font-family:'Inter',system-ui,-apple-system,sans-serif}
.bi-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0f4c75 100%);border-radius:24px;padding:2rem 2.5rem;color:#fff;position:relative;overflow:hidden;margin-bottom:2rem}
.bi-hero::before{content:'';position:absolute;top:-80px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%);border-radius:50%}
.bi-hero h1{font-size:1.75rem;font-weight:800;margin:0 0 0.3rem;background:linear-gradient(135deg,#fff,#93c5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.bi-hero p{color:rgba(255,255,255,0.5);font-size:0.85rem;margin:0}
.bi-stat{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:16px;padding:1.25rem;transition:all 0.3s}
.bi-stat:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.06)}
.bi-stat .num{font-size:1.8rem;font-weight:900;line-height:1}
.bi-stat .label{font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:0.2rem}
.bi-card{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:16px;overflow:hidden}
.bi-card-header{padding:1rem 1.25rem;border-bottom:1px solid rgba(0,0,0,0.04);font-weight:700;font-size:0.88rem;display:flex;align-items:center;gap:8px}
.bi-card-body{padding:1.25rem}
.bi-table{width:100%;border-collapse:collapse;font-size:0.82rem}
.bi-table th{padding:0.6rem 0.75rem;text-align:left;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;border-bottom:1.5px solid rgba(0,0,0,0.04);background:#f8fafc}
.bi-table td{padding:0.6rem 0.75rem;border-bottom:1px solid rgba(0,0,0,0.03)}
.bi-table tr:hover{background:#f8fafc}
.bi-badge{display:inline-flex;align-items:center;gap:4px;padding:0.2rem 0.5rem;border-radius:50px;font-size:0.68rem;font-weight:700}
.bi-positive{background:#dcfce7;color:#15803d}
.bi-neutral{background:#f1f5f9;color:#64748b}
.bi-negative{background:#fef2f2;color:#dc2626}
.bi-bar{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden}
.bi-bar-fill{height:100%;border-radius:4px;transition:width 0.5s}
</style>

<div class="bi-wrap">
<div class="bi-hero">
    <div style="position:relative;z-index:1">
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);padding:0.35rem 0.9rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:rgba(255,255,255,0.8);margin-bottom:0.75rem"><i class="fas fa-brain"></i> Bot Intelligence</div>
        <h1>Analytics & Insights</h1>
        <p>Monitor lead scoring, sentiment, conversation analytics, and audit trails</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="bi-stat">
            <div class="num" style="color:#3b82f6"><?= number_format($stats['total_conversations']) ?></div>
            <div class="label" style="color:#3b82f6">Conversations</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bi-stat">
            <div class="num" style="color:#8b5cf6"><?= number_format($stats['total_messages']) ?></div>
            <div class="label" style="color:#8b5cf6">Total Messages</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bi-stat">
            <div class="num" style="color:#15803d"><?= number_format($stats['total_memories']) ?></div>
            <div class="label" style="color:#15803d">Memories Stored</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bi-stat">
            <div class="num" style="color:#d97706"><?= number_format($stats['total_rate_limits']) ?></div>
            <div class="label" style="color:#d97706">Rate Limited</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Sentiment Distribution -->
    <div class="col-md-4">
        <div class="bi-card">
            <div class="bi-card-header"><i class="fas fa-smile" style="color:#3b82f6;"></i> Sentiment Distribution</div>
            <div class="bi-card-body">
                <?php if (empty($stats['sentiment_dist'])): ?>
                    <p class="text-muted" style="font-size:0.82rem;">No sentiment data yet</p>
                <?php else: ?>
                    <?php
                    $totalSent = array_sum(array_column($stats['sentiment_dist'], 'cnt'));
                    foreach ($stats['sentiment_dist'] as $s):
                        $pct = $totalSent > 0 ? round(($s['cnt'] / $totalSent) * 100) : 0;
                        $color = $s['sentiment'] === 'positive' ? '#22c55e' : ($s['sentiment'] === 'negative' ? '#ef4444' : '#94a3b8');
                    ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="bi-badge bi-<?= $s['sentiment'] ?>"><?= ucfirst($s['sentiment']) ?></span>
                        <div class="bi-bar flex-grow-1"><div class="bi-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
                        <span style="font-size:0.75rem;font-weight:700;color:<?= $color ?>;"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Leads -->
    <div class="col-md-8">
        <div class="bi-card">
            <div class="bi-card-header"><i class="fas fa-fire" style="color:#ef4444;"></i> Top Leads (by Score)</div>
            <div class="bi-card-body" style="padding:0;max-height:250px;overflow-y:auto;">
                <?php if (empty($stats['top_leads'])): ?>
                    <p class="text-muted p-3" style="font-size:0.82rem;">No lead scores yet</p>
                <?php else: ?>
                <table class="bi-table">
                    <thead><tr><th>User</th><th>Email</th><th>Score</th><th>Factors</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['top_leads'] as $l):
                        $factors = json_decode($l['factors'] ?? '{}', true);
                    ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($l['name']) ?></td>
                        <td style="color:#64748b;"><?= htmlspecialchars($l['email']) ?></td>
                        <td><span style="font-weight:900;color:<?= $l['score'] >= 70 ? '#15803d' : ($l['score'] >= 40 ? '#d97706' : '#64748b') ?>;"><?= $l['score'] ?>/100</span></td>
                        <td style="font-size:0.72rem;color:#94a3b8;">
                            <?= $factors['messages'] ?? 0 ?> msgs,
                            <?= $factors['projects'] ?? 0 ?> projects
                            <?= ($factors['engaged'] ?? false) ? ', Engaged' : '' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log -->
<div class="bi-card mb-4">
    <div class="bi-card-header"><i class="fas fa-clipboard-list" style="color:#8b5cf6;"></i> Recent Audit Log <a href="admin/bot_audit_log.php" class="ms-auto" style="font-size:0.75rem;color:#3b82f6;text-decoration:none;">View All</a></div>
    <div class="bi-card-body" style="padding:0;max-height:350px;overflow-y:auto;">
        <?php if (empty($stats['recent_audit'])): ?>
            <p class="text-muted p-3" style="font-size:0.82rem;">No audit entries yet</p>
        <?php else: ?>
        <table class="bi-table">
            <thead><tr><th>Time</th><th>Action</th><th>User ID</th><th>Target</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($stats['recent_audit'] as $a): ?>
            <tr>
                <td style="white-space:nowrap;font-size:0.75rem;color:#64748b;"><?= date('M d, H:i', strtotime($a['created_at'])) ?></td>
                <td><span class="bi-badge" style="background:#f1f5f9;color:#334155;"><?= htmlspecialchars($a['action']) ?></span></td>
                <td><?= $a['user_id'] ?? '—' ?></td>
                <td style="font-size:0.78rem;"><?= ($a['target_type'] ?? '—') . ' #' . ($a['target_id'] ?? '') ?></td>
                <td style="font-size:0.78rem;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['details'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Daily Activity Chart -->
<div class="bi-card mb-4">
    <div class="bi-card-header"><i class="fas fa-chart-line" style="color:#15803d;"></i> Daily Activity (Last 7 Days)</div>
    <div class="bi-card-body">
        <?php if (empty($stats['daily_chart'])): ?>
            <p class="text-muted" style="font-size:0.82rem;">No activity data yet</p>
        <?php else: ?>
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px;">
            <?php
            $maxMsgs = max(array_column($stats['daily_chart'], 'msgs') ?: [1]);
            foreach ($stats['daily_chart'] as $d):
                $h = $maxMsgs > 0 ? round(($d['msgs'] / $maxMsgs) * 100) : 0;
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <span style="font-size:0.65rem;font-weight:700;color:#334155;"><?= $d['msgs'] ?></span>
                <div style="width:100%;height:<?= max($h, 4) ?>%;background:linear-gradient(180deg,#3b82f6,#1d4ed8);border-radius:6px 6px 0 0;transition:height 0.5s;"></div>
                <span style="font-size:0.6rem;color:#94a3b8;"><?= date('D', strtotime($d['day'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>