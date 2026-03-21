<?php
$pageTitle = 'Projects';
$currentPage = 'projects';
require_once __DIR__ . '/layout.php';

$pdo = DB::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $projectId = (int) ($_POST['project_id'] ?? 0);

    if ($action === 'delete' && $projectId) {
        $pdo->prepare('DELETE FROM agent_runs WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('DELETE FROM project_tasks WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('DELETE FROM project_files WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $config = require __DIR__ . '/../config.php';
        $ws = $config['workspace']['path'] . '/' . $projectId;
        if (is_dir($ws)) exec('rm -rf ' . escapeshellarg($ws));
        @unlink($config['workspace']['path'] . '/' . $projectId . '.zip');
        @unlink($config['workspace']['path'] . '/' . $projectId . '.tar.gz');
        header('Location: /admin/projects.php?msg=deleted');
        exit;
    }
    if ($action === 'reset' && $projectId) {
        $pdo->prepare('DELETE FROM agent_runs WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('DELETE FROM project_tasks WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('DELETE FROM project_files WHERE project_id = ?')->execute([$projectId]);
        $pdo->prepare('UPDATE projects SET status = ? WHERE id = ?')->execute(['pending', $projectId]);
        $config = require __DIR__ . '/../config.php';
        $ws = $config['workspace']['path'] . '/' . $projectId;
        if (is_dir($ws)) exec('rm -rf ' . escapeshellarg($ws));
        @unlink($config['workspace']['path'] . '/' . $projectId . '.zip');
        @unlink($config['workspace']['path'] . '/' . $projectId . '.tar.gz');
        header('Location: /admin/projects.php?msg=reset');
        exit;
    }
}

$filterUser = (int) ($_GET['user_id'] ?? 0);
$where = $filterUser ? 'WHERE p.user_id = ' . $filterUser : '';

$projects = $pdo->query("
    SELECT p.*, u.email,
        (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) as task_count,
        (SELECT COALESCE(SUM(tokens_used), 0) FROM agent_runs WHERE project_id = p.id) as tokens
    FROM projects p JOIN users u ON p.user_id = u.id
    {$where}
    ORDER BY p.created_at DESC
")->fetchAll();

$msg = $_GET['msg'] ?? '';
?>

<h2>Projects <?= $filterUser ? '(filtered by user)' : '' ?></h2>
<?php if ($filterUser): ?>
    <a href="/admin/projects.php" style="margin-bottom:16px; display:inline-block;">Show all projects</a>
<?php endif; ?>

<?php if ($msg === 'deleted'): ?>
    <div style="color:var(--green); margin-bottom:16px;">Project deleted.</div>
<?php elseif ($msg === 'reset'): ?>
    <div style="color:var(--green); margin-bottom:16px;">Project reset to pending.</div>
<?php endif; ?>

<table>
    <tr><th>ID</th><th>Name</th><th>User</th><th>Goal</th><th>Status</th><th>Tasks</th><th>Tokens</th><th>Created</th><th>Actions</th></tr>
    <?php foreach ($projects as $p): ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td class="text-dim"><?= htmlspecialchars($p['email']) ?></td>
        <td class="text-dim" title="<?= htmlspecialchars($p['goal']) ?>"><?= htmlspecialchars(substr($p['goal'], 0, 60)) ?><?= strlen($p['goal']) > 60 ? '...' : '' ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
        <td><?= $p['task_count'] ?></td>
        <td class="text-dim"><?= number_format($p['tokens']) ?></td>
        <td class="text-dim"><?= date('M j, g:ia', strtotime($p['created_at'])) ?></td>
        <td>
            <a href="/admin/project_detail.php?id=<?= $p['id'] ?>" class="btn action-btn">View</a>
            <?php
            $config = require __DIR__ . '/../config.php';
            $hasDownload = file_exists($config['workspace']['path'] . '/' . $p['id'] . '.zip') || file_exists($config['workspace']['path'] . '/' . $p['id'] . '.tar.gz');
            if ($hasDownload): ?>
                <a href="/admin/download.php?id=<?= $p['id'] ?>" class="btn action-btn">Download</a>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Reset this project?')">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn action-btn">Reset</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('DELETE this project permanently?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                <button type="submit" class="confirm-delete">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
