<?php
$pageTitle = 'Project Detail';
$currentPage = 'projects';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../lib/Auth.php';

$pdo = DB::getInstance();
$projectId = (int) ($_GET['id'] ?? 0);

if (!$projectId) { header('Location: /admin/projects.php'); exit; }

// Handle build action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'build') {
    // Run build in background
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/run_build.php') . ' ' . (int)$projectId;
    exec($cmd . ' > /dev/null 2>&1 &');
    header('Location: /admin/project_detail.php?id=' . $projectId . '&msg=building');
    exit;
}

$project = $pdo->prepare('SELECT p.*, u.email FROM projects p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
$project->execute([$projectId]);
$project = $project->fetch();

if (!$project) { header('Location: /admin/projects.php'); exit; }

// Generate a JWT for this project's owner so admin can view as that user
$userStmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ?');
$userStmt->execute([$project['user_id']]);
$owner = $userStmt->fetch();
$ownerToken = '';
if ($owner) {
    $r = Auth::login($owner['email'], '___skip___'); // won't work, need direct token
    // Use reflection to create token directly
    $ref = new ReflectionMethod(Auth::class, 'createToken');
    $ref->setAccessible(true);
    $ownerToken = $ref->invoke(null, $owner['id'], $owner['email']);
}

$tasks = $pdo->prepare('SELECT * FROM project_tasks WHERE project_id = ? ORDER BY id');
$tasks->execute([$projectId]);
$tasks = $tasks->fetchAll();

$files = $pdo->prepare('SELECT id, filename, file_type, version, created_at FROM project_files WHERE project_id = ? ORDER BY filename');
$files->execute([$projectId]);
$files = $files->fetchAll();

$runs = $pdo->prepare('SELECT id, agent_type, status, tokens_used, created_at, LEFT(response_received, 200) as preview FROM agent_runs WHERE project_id = ? ORDER BY id DESC LIMIT 20');
$runs->execute([$projectId]);
$runs = $runs->fetchAll();

$totalTokens = $pdo->prepare('SELECT COALESCE(SUM(tokens_used), 0) FROM agent_runs WHERE project_id = ?');
$totalTokens->execute([$projectId]);
$totalTokens = $totalTokens->fetchColumn();
?>

<h2><?= htmlspecialchars($project['name']) ?> <span class="badge badge-<?= $project['status'] ?>"><?= $project['status'] ?></span></h2>
<p class="text-dim" style="margin-bottom:8px;">By: <?= htmlspecialchars($project['email']) ?> | Created: <?= date('M j, Y g:ia', strtotime($project['created_at'])) ?> | Tokens: <?= number_format($totalTokens) ?></p>
<p style="margin-bottom:20px; padding:12px; background:var(--bg); border:1px solid var(--border); border-radius:4px;"><?= htmlspecialchars($project['goal']) ?></p>

<?php if (($_GET['msg'] ?? '') === 'building'): ?>
    <div style="color:var(--green); margin-bottom:16px;">Build started in background. Refresh to see progress.</div>
<?php endif; ?>

<div style="margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <a href="/admin/projects.php" class="btn action-btn">&#8592; Back</a>

    <?php if ($ownerToken): ?>
    <a href="#" onclick="openAsUser()" class="btn action-btn">Open as User</a>
    <script>
    function openAsUser() {
        var w = window.open('/project.php?id=<?= $projectId ?>');
        w.addEventListener('load', function() {
            w.localStorage.setItem('sb_token', '<?= $ownerToken ?>');
            w.localStorage.setItem('sb_user', JSON.stringify({id: <?= $project['user_id'] ?>, email: '<?= htmlspecialchars($owner['email']) ?>'}));
            w.location.reload();
        });
    }
    </script>
    <?php endif; ?>

    <?php if (in_array($project['status'], ['pending', 'failed'])): ?>
    <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="build">
        <button type="submit" class="btn btn-primary action-btn" onclick="this.textContent='Building...'">Run Build</button>
    </form>
    <?php elseif (in_array($project['status'], ['planning', 'building'])): ?>
    <span class="badge badge-building" style="padding:8px 16px;">Building...</span>
    <?php endif; ?>

    <?php
    $config = require __DIR__ . '/../config.php';
    $hasZip = file_exists($config['workspace']['path'] . '/' . $projectId . '.zip');
    $hasTar = file_exists($config['workspace']['path'] . '/' . $projectId . '.tar.gz');
    if ($hasZip || $hasTar): ?>
        <a href="/admin/download.php?id=<?= $projectId ?>" class="btn btn-primary action-btn">Download Output</a>
    <?php endif; ?>
</div>

<h3 style="color:var(--text); margin:20px 0 12px;">Tasks (<?= count($tasks) ?>)</h3>
<table>
    <tr><th>ID</th><th>Order</th><th>Agent</th><th>Status</th><th>Description</th></tr>
    <?php foreach ($tasks as $t): ?>
    <tr>
        <td><?= $t['id'] ?></td>
        <td><?= $t['task_order'] ?></td>
        <td><span class="badge" style="background:var(--border); color:var(--brass);"><?= $t['assigned_agent'] ?></span></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
        <td class="text-dim" title="<?= htmlspecialchars($t['task_description']) ?>"><?= htmlspecialchars(substr($t['task_description'], 0, 120)) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3 style="color:var(--text); margin:20px 0 12px;">Files (<?= count($files) ?>)</h3>
<table>
    <tr><th>Filename</th><th>Type</th><th>Version</th><th></th></tr>
    <?php foreach ($files as $f): ?>
    <tr>
        <td><?= htmlspecialchars($f['filename']) ?></td>
        <td class="text-dim"><?= htmlspecialchars($f['file_type']) ?></td>
        <td class="text-dim">v<?= $f['version'] ?></td>
        <td><a href="/admin/file_view.php?id=<?= $f['id'] ?>" class="btn action-btn">View</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3 style="color:var(--text); margin:20px 0 12px;">Recent Agent Runs</h3>
<table>
    <tr><th>ID</th><th>Agent</th><th>Status</th><th>Tokens</th><th>Time</th><th>Preview</th></tr>
    <?php foreach ($runs as $r): ?>
    <tr>
        <td><?= $r['id'] ?></td>
        <td><span class="badge" style="background:var(--border); color:var(--brass);"><?= $r['agent_type'] ?></span></td>
        <td><span class="badge badge-<?= $r['status'] === 'success' ? 'complete' : 'failed' ?>"><?= $r['status'] ?></span></td>
        <td class="text-dim"><?= number_format($r['tokens_used']) ?></td>
        <td class="text-dim"><?= date('g:ia', strtotime($r['created_at'])) ?></td>
        <td class="text-dim"><?= htmlspecialchars(substr($r['preview'], 0, 100)) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
