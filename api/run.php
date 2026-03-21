<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClaudeClient.php';
require_once __DIR__ . '/../lib/SSE.php';
require_once __DIR__ . '/../agents/BaseAgent.php';
require_once __DIR__ . '/../agents/Orchestrator.php';
require_once __DIR__ . '/../agents/ArchitectAgent.php';
require_once __DIR__ . '/../agents/CoderAgent.php';
require_once __DIR__ . '/../agents/ReviewerAgent.php';
require_once __DIR__ . '/../agents/TesterAgent.php';
require_once __DIR__ . '/../agents/DocumenterAgent.php';
require_once __DIR__ . '/../agents/CompilerAgent.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = Auth::requireAuth();
$pdo = DB::getInstance();
$userId = $user['sub'];

$input = json_decode(file_get_contents('php://input'), true);
$projectId = (int) ($input['project_id'] ?? 0);

if (!$projectId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

// Verify ownership
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, $userId]);
$project = $stmt->fetch();

if (!$project) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

// Start SSE stream
SSE::init();

$claude = new ClaudeClient();
$orchestrator = new Orchestrator($claude, $pdo, $projectId);

try {
    // Step 1: Create plan if none exists
    $taskCheck = $pdo->prepare('SELECT COUNT(*) FROM project_tasks WHERE project_id = ?');
    $taskCheck->execute([$projectId]);

    if ((int) $taskCheck->fetchColumn() === 0) {
        SSE::send('status', ['agent' => 'orchestrator', 'status' => 'planning', 'message' => 'Creating development plan...']);
        $plan = $orchestrator->createPlan($project['goal']);
        SSE::send('plan', ['agent' => 'orchestrator', 'status' => 'success', 'message' => $plan['plan_summary'], 'tasks' => $plan['tasks']]);
    }

    // Step 2: Execute tasks
    $maxIterations = 50; // Safety limit
    $iteration = 0;

    while (!$orchestrator->isComplete() && $iteration < $maxIterations) {
        $iteration++;
        $result = $orchestrator->runNextTask();

        if (!$result) break;

        SSE::send('task', [
            'agent' => $result['agent'],
            'status' => $result['status'],
            'task_id' => $result['task_id'],
            'message' => substr($result['output'], 0, 500),
            'tokens_used' => $result['tokens_used'],
        ]);

        if (connection_aborted()) break;
    }

    // Step 3: Zip workspace if complete
    if ($orchestrator->isComplete()) {
        $config = require __DIR__ . '/../config.php';
        $workDir = $config['workspace']['path'] . '/' . $projectId;
        $zipPath = $config['workspace']['path'] . '/' . $projectId . '.zip';

        if (is_dir($workDir)) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir));
                    foreach ($files as $file) {
                        if ($file->isFile()) {
                            $relativePath = substr($file->getPathname(), strlen($workDir) + 1);
                            $zip->addFile($file->getPathname(), $relativePath);
                        }
                    }
                    $zip->close();
                }
            } else {
                $tarPath = $config['workspace']['path'] . '/' . $projectId . '.tar.gz';
                exec('tar -czf ' . escapeshellarg($tarPath) . ' -C ' . escapeshellarg($workDir) . ' .');
            }
        }

        SSE::send('complete', ['status' => 'complete', 'message' => 'Project build complete!', 'download' => "/api/download.php?id={$projectId}"]);
    }

} catch (Throwable $e) {
    SSE::send('error', ['status' => 'error', 'message' => $e->getMessage()]);

    $stmt = $pdo->prepare('UPDATE projects SET status = ? WHERE id = ?');
    $stmt->execute(['failed', $projectId]);
}

SSE::close();
