<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/DB.php';

$pdo = DB::getInstance();
$projectId = (int) ($_GET['id'] ?? 0);

$project = $pdo->prepare('SELECT name FROM projects WHERE id = ?');
$project->execute([$projectId]);
$project = $project->fetch();

if (!$project) { http_response_code(404); echo 'Not found'; exit; }

$config = require __DIR__ . '/../config.php';
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']);
$basePath = $config['workspace']['path'] . '/' . $projectId;

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
} else {
    http_response_code(404);
    echo 'No download available';
}
