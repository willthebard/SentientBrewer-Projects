<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/SSE.php';

if (isset($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}
$user = Auth::requireAuth();
$pdo = DB::getInstance();
$userId = $user['sub'];
$projectId = (int) ($_GET['id'] ?? 0);

if (!$projectId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'id parameter required']);
    exit;
}

// Verify ownership
$stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $userId]);
if (!$stmt->fetch()) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

SSE::init();

$lastRunId = 0;

while (true) {
    $stmt = $pdo->prepare(
        'SELECT ar.id, ar.agent_type, ar.status, ar.tokens_used, ar.created_at,
                LEFT(ar.response_received, 500) as response_preview
         FROM agent_runs ar
         WHERE ar.project_id = ? AND ar.id > ?
         ORDER BY ar.id ASC'
    );
    $stmt->execute([$projectId, $lastRunId]);
    $runs = $stmt->fetchAll();

    foreach ($runs as $run) {
        SSE::send('agent_update', $run);
        $lastRunId = $run['id'];
    }

    // Check if project is done
    $projStmt = $pdo->prepare('SELECT status FROM projects WHERE id = ?');
    $projStmt->execute([$projectId]);
    $status = $projStmt->fetchColumn();

    if (in_array($status, ['complete', 'failed'])) {
        SSE::send('project_status', ['status' => $status]);
        break;
    }

    if (connection_aborted()) break;

    SSE::keepAlive();
    sleep(2);
}

SSE::close();
