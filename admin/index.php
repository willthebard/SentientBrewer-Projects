<?php
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/layout.php';

$pdo = DB::getInstance();
$userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$projectCount = $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$completedCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'complete'")->fetchColumn();
$failedCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'failed'")->fetchColumn();
$totalRuns = $pdo->query('SELECT COUNT(*) FROM agent_runs')->fetchColumn();
$totalTokens = $pdo->query('SELECT COALESCE(SUM(tokens_used), 0) FROM agent_runs')->fetchColumn();

$recentProjects = $pdo->query('
    SELECT p.id, p.name, p.goal, p.status, p.created_at, u.email
    FROM projects p JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC LIMIT 10
')->fetchAll();
?>

<h2>Dashboard</h2>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Users</div>
        <div class="value"><?= $userCount ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Projects</div>
        <div class="value"><?= $projectCount ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Completed</div>
        <div class="value"><?= $completedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Failed</div>
        <div class="value"><?= $failedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Agent Runs</div>
        <div class="value"><?= $totalRuns ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Tokens Used</div>
        <div class="value"><?= number_format($totalTokens) ?></div>
    </div>
</div>

<h3 style="color:var(--text); margin-bottom:12px;">Recent Projects</h3>
<table>
    <tr><th>ID</th><th>Name</th><th>User</th><th>Status</th><th>Created</th><th></th></tr>
    <?php foreach ($recentProjects as $p): ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td class="text-dim"><?= htmlspecialchars($p['email']) ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
        <td class="text-dim"><?= date('M j, g:ia', strtotime($p['created_at'])) ?></td>
        <td><a href="/admin/project_detail.php?id=<?= $p['id'] ?>" class="btn action-btn">View</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
