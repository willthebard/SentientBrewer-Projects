<?php
$pageTitle = 'Agent Runs';
$currentPage = 'runs';
require_once __DIR__ . '/layout.php';

$pdo = DB::getInstance();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = $pdo->query('SELECT COUNT(*) FROM agent_runs')->fetchColumn();
$totalPages = ceil($total / $perPage);

$runs = $pdo->query("
    SELECT ar.*, p.name as project_name
    FROM agent_runs ar JOIN projects p ON ar.project_id = p.id
    ORDER BY ar.id DESC
    LIMIT {$perPage} OFFSET {$offset}
")->fetchAll();

$tokensByAgent = $pdo->query('
    SELECT agent_type, COUNT(*) as runs, SUM(tokens_used) as tokens
    FROM agent_runs GROUP BY agent_type ORDER BY tokens DESC
')->fetchAll();

$totalTokens = $pdo->query('SELECT COALESCE(SUM(tokens_used), 0) FROM agent_runs')->fetchColumn();
// Sonnet pricing: $3/M input + $15/M output. We track combined tokens.
// Estimate ~30% input, ~70% output based on typical agent patterns.
$estimatedInputTokens = $totalTokens * 0.3;
$estimatedOutputTokens = $totalTokens * 0.7;
$estimatedCost = ($estimatedInputTokens / 1000000 * 3) + ($estimatedOutputTokens / 1000000 * 15);

// Today's usage
$todayTokens = $pdo->query("SELECT COALESCE(SUM(tokens_used), 0) FROM agent_runs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$todayCost = ($todayTokens * 0.3 / 1000000 * 3) + ($todayTokens * 0.7 / 1000000 * 15);

// Try to get rate limit info from config
$config = require __DIR__ . '/../config.php';
$model = $config['anthropic']['model'] ?? 'claude-sonnet-4-20250514';
?>

<h2>Agent Runs</h2>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="label">Total Tokens</div>
        <div class="value" style="font-size:20px;"><?= number_format($totalTokens) ?></div>
        <div class="text-dim">Est. cost: $<?= number_format($estimatedCost, 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Today's Usage</div>
        <div class="value" style="font-size:20px;"><?= number_format($todayTokens) ?></div>
        <div class="text-dim">Est. cost: $<?= number_format($todayCost, 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Rate Limit</div>
        <div class="value" style="font-size:20px;">30K/min</div>
        <div class="text-dim">Model: <?= htmlspecialchars($model) ?></div>
    </div>
    <?php foreach ($tokensByAgent as $a): ?>
    <div class="stat-card">
        <div class="label"><?= $a['agent_type'] ?></div>
        <div class="value" style="font-size:20px;"><?= number_format($a['tokens']) ?> tokens</div>
        <div class="text-dim"><?= $a['runs'] ?> runs | $<?= number_format(($a['tokens'] * 0.3 / 1000000 * 3) + ($a['tokens'] * 0.7 / 1000000 * 15), 2) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<table>
    <tr><th>ID</th><th>Project</th><th>Agent</th><th>Status</th><th>Tokens</th><th>Time</th></tr>
    <?php foreach ($runs as $r): ?>
    <tr>
        <td><?= $r['id'] ?></td>
        <td><a href="/admin/project_detail.php?id=<?= $r['project_id'] ?>"><?= htmlspecialchars($r['project_name']) ?></a></td>
        <td><span class="badge" style="background:var(--border); color:var(--brass);"><?= $r['agent_type'] ?></span></td>
        <td><span class="badge badge-<?= $r['status'] === 'success' ? 'complete' : ($r['status'] === 'error' ? 'failed' : 'building') ?>"><?= $r['status'] ?></span></td>
        <td class="text-dim"><?= number_format($r['tokens_used']) ?></td>
        <td class="text-dim"><?= date('M j, g:ia', strtotime($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($totalPages > 1): ?>
<div style="margin-top:16px; display:flex; gap:8px;">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn action-btn">&laquo; Prev</a>
    <?php endif; ?>
    <span class="text-dim" style="padding:6px;">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn action-btn">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
