<?php
$pageTitle = 'Users';
$currentPage = 'users';
require_once __DIR__ . '/layout.php';

$pdo = DB::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'delete' && $userId) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        header('Location: /admin/users.php?msg=deleted');
        exit;
    }
    if ($action === 'reset_password' && $userId) {
        $newPass = $_POST['new_password'] ?? '';
        if ($newPass) {
            $hash = password_hash($newPass, PASSWORD_ARGON2ID);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            header('Location: /admin/users.php?msg=password_reset');
            exit;
        }
    }
}

$users = $pdo->query('
    SELECT u.*, COUNT(p.id) as project_count
    FROM users u LEFT JOIN projects p ON u.id = p.user_id
    GROUP BY u.id ORDER BY u.created_at DESC
')->fetchAll();

$msg = $_GET['msg'] ?? '';
?>

<h2>Users</h2>

<?php if ($msg === 'deleted'): ?>
    <div style="color:var(--green); margin-bottom:16px;">User deleted.</div>
<?php elseif ($msg === 'password_reset'): ?>
    <div style="color:var(--green); margin-bottom:16px;">Password reset.</div>
<?php endif; ?>

<table>
    <tr><th>ID</th><th>Email</th><th>Name</th><th>Projects</th><th>Joined</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['name'] ?: '—') ?></td>
        <td><?= $u['project_count'] ?></td>
        <td class="text-dim"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
        <td>
            <a href="/admin/projects.php?user_id=<?= $u['id'] ?>" class="btn action-btn">Projects</a>

            <button onclick="document.getElementById('pw-<?= $u['id'] ?>').style.display='inline'" class="btn action-btn">Reset PW</button>
            <form id="pw-<?= $u['id'] ?>" method="POST" style="display:none; margin-top:6px;">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="password" name="new_password" placeholder="New password" required style="width:140px; padding:4px 8px; font-size:12px;">
                <button type="submit" class="btn action-btn">Set</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['email']) ?> and all their projects?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="confirm-delete">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
