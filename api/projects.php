<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$user = Auth::requireAuth();
$pdo = DB::getInstance();
$userId = $user['sub'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
            $stmt->execute([(int) $_GET['id'], $userId]);
            $project = $stmt->fetch();

            if (!$project) {
                http_response_code(404);
                echo json_encode(['error' => 'Project not found']);
                exit;
            }

            // Include tasks
            $taskStmt = $pdo->prepare('SELECT * FROM project_tasks WHERE project_id = ? ORDER BY task_order');
            $taskStmt->execute([$project['id']]);
            $project['tasks'] = $taskStmt->fetchAll();

            // Include files
            $fileStmt = $pdo->prepare('SELECT id, filename, file_type, version, created_at FROM project_files WHERE project_id = ?');
            $fileStmt->execute([$project['id']]);
            $project['files'] = $fileStmt->fetchAll();

            echo json_encode($project);
        } else {
            $stmt = $pdo->prepare('SELECT id, name, goal, status, created_at, updated_at FROM projects WHERE user_id = ? ORDER BY updated_at DESC');
            $stmt->execute([$userId]);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $goal = trim($input['goal'] ?? '');

        if (!$name || !$goal) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and goal are required']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO projects (user_id, name, goal) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $goal]);
        $projectId = (int) $pdo->lastInsertId();

        // Create workspace directory
        $config = require __DIR__ . '/../config.php';
        $workDir = $config['workspace']['path'] . '/' . $projectId;
        if (!is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        echo json_encode([
            'id' => $projectId,
            'name' => $name,
            'goal' => $goal,
            'status' => 'pending',
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
