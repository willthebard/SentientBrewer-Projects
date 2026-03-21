<?php require_once __DIR__ . '/auth.php'; requireAdmin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> — Sentient Brewer</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .admin-layout { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
        .sidebar { background: var(--bg-panel); border-right: 1px solid var(--border); padding: 20px; }
        .sidebar h3 { color: var(--brass); font-size: 14px; margin-bottom: 20px; letter-spacing: 2px; }
        .sidebar a { display: block; padding: 10px 12px; color: var(--text-dim); border-radius: 4px; margin-bottom: 4px; font-size: 13px; }
        .sidebar a:hover, .sidebar a.active { color: var(--brass); background: rgba(200,149,108,0.1); }
        .admin-main { padding: 30px; overflow-y: auto; }
        .admin-main h2 { color: var(--brass); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: var(--bg-panel); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }
        .stat-card .label { color: var(--text-dim); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .value { color: var(--brass); font-size: 28px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--brass); border-bottom: 1px solid var(--border); padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .action-btn { padding: 4px 10px; font-size: 11px; }
        .text-dim { color: var(--text-dim); }
        .confirm-delete { color: var(--red); cursor: pointer; background: none; border: 1px solid var(--red); padding: 4px 10px; font-family: var(--font-mono); font-size: 11px; border-radius: 4px; }
        .confirm-delete:hover { background: var(--red); color: var(--bg); }
    </style>
</head>
<body>
    <div class="admin-layout">
        <nav class="sidebar">
            <h3>&#9881; Admin</h3>
            <a href="/admin/" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="/admin/users.php" class="<?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>">Users</a>
            <a href="/admin/projects.php" class="<?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>">Projects</a>
            <a href="/admin/runs.php" class="<?= ($currentPage ?? '') === 'runs' ? 'active' : '' ?>">Agent Runs</a>
            <hr style="border-color: var(--border); margin: 16px 0;">
            <a href="/admin/logout.php">Logout</a>
            <a href="/" style="margin-top:8px;">&#8592; Back to Site</a>
        </nav>
        <main class="admin-main">
