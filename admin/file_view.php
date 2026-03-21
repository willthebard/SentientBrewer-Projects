<?php
$pageTitle = 'View File';
$currentPage = 'projects';
require_once __DIR__ . '/layout.php';

$pdo = DB::getInstance();
$fileId = (int) ($_GET['id'] ?? 0);

$file = $pdo->prepare('SELECT pf.*, p.name as project_name, p.id as project_id FROM project_files pf JOIN projects p ON pf.project_id = p.id WHERE pf.id = ?');
$file->execute([$fileId]);
$file = $file->fetch();

if (!$file) { header('Location: /admin/projects.php'); exit; }
?>

<h2><?= htmlspecialchars($file['filename']) ?></h2>
<p class="text-dim" style="margin-bottom:16px;">
    Project: <a href="/admin/project_detail.php?id=<?= $file['project_id'] ?>"><?= htmlspecialchars($file['project_name']) ?></a>
    | Version <?= $file['version'] ?>
    | <?= htmlspecialchars($file['file_type']) ?>
</p>

<a href="/admin/project_detail.php?id=<?= $file['project_id'] ?>" class="btn action-btn" style="margin-bottom:16px; display:inline-block;">&#8592; Back</a>

<pre style="background:var(--bg-terminal); border:1px solid var(--border); border-radius:4px; padding:16px; overflow-x:auto; font-size:12px; line-height:1.6; max-height:70vh; overflow-y:auto;"><?= htmlspecialchars($file['content']) ?></pre>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
