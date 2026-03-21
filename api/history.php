<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}

$user = Auth::requireAuth();
$pdo = DB::getInstance();
$userId = $user['sub'];
$projectId = (int) ($_GET['id'] ?? 0);

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'id parameter required']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, agent_type, status, tokens_used, created_at,
            LEFT(response_received, 500) as response_preview
     FROM agent_runs
     WHERE project_id = ?
     ORDER BY id ASC'
);
$stmt->execute([$projectId]);
echo json_encode($stmt->fetchAll());
