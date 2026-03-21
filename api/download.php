<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';

// Accept token via query param for direct browser downloads
if (isset($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
$user = Auth::requireAuth();
$pdo = DB::getInstance();
$userId = $user['sub'];
$projectId = (int) ($_GET['id'] ?? 0);

if (!$projectId) {
    http_response_code(400);
    echo 'Missing project id';
    exit;
}

// Verify ownership
$stmt = $pdo->prepare('SELECT name FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $userId]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo 'Project not found';
    exit;
}

$config = require __DIR__ . '/../config.php';
$basePath = $config['workspace']['path'] . '/' . $projectId;
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']);

// Try zip first, fall back to tar.gz
if (file_exists($basePath . '.zip')) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $safeName . '.zip"');
    header('Content-Length: ' . filesize($basePath . '.zip'));
    readfile($basePath . '.zip');
} elseif (file_exists($basePath . '.tar.gz')) {
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $safeName . '.tar.gz"');
    header('Content-Length: ' . filesize($basePath . '.tar.gz'));
    readfile($basePath . '.tar.gz');
} elseif (is_dir($basePath)) {
    // Generate tar.gz on the fly
    $tarPath = $basePath . '.tar.gz';
    exec('tar -czf ' . escapeshellarg($tarPath) . ' -C ' . escapeshellarg($basePath) . ' .', $out, $ret);
    if ($ret === 0 && file_exists($tarPath)) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $safeName . '.tar.gz"');
        header('Content-Length: ' . filesize($tarPath));
        readfile($tarPath);
    } else {
        http_response_code(500);
        echo 'Failed to create archive';
    }
} else {
    http_response_code(404);
    echo 'No download available yet';
}
