<?php

abstract class BaseAgent {
    protected ClaudeClient $claude;
    protected PDO $db;
    protected int $projectId;

    abstract protected function systemPrompt(): string;
    abstract protected function agentType(): string;

    public function __construct(ClaudeClient $claude, PDO $db, int $projectId) {
        $this->claude = $claude;
        $this->db = $db;
        $this->projectId = $projectId;
    }

    public function run(string $taskDescription, array $completedTasks = []): array {
        $context = $this->buildContext($completedTasks);

        $prompt = $taskDescription;
        if ($context) {
            $prompt = "## Previous work completed:\n{$context}\n\n## Your task:\n{$taskDescription}";
        }

        $projectGoal = $this->getProjectGoal();
        $systemPrompt = $this->systemPrompt() . "\n\nProject goal: {$projectGoal}";

        try {
            $result = $this->claude->chat($systemPrompt, [
                ['role' => 'user', 'content' => $prompt]
            ]);

            $this->logRun($prompt, $result['text'], $result['tokens_used'], 'success');
            $this->extractAndSaveFiles($result['text']);

            return [
                'status' => 'complete',
                'output' => $result['text'],
                'tokens_used' => $result['tokens_used'],
            ];
        } catch (Throwable $e) {
            $this->logRun($prompt, $e->getMessage(), 0, 'error');
            return [
                'status' => 'failed',
                'output' => 'Agent error: ' . $e->getMessage(),
                'tokens_used' => 0,
            ];
        }
    }

    protected function buildContext(array $completedTasks): string {
        if (empty($completedTasks)) return '';

        $lines = [];
        foreach ($completedTasks as $task) {
            $lines[] = "### [{$task['assigned_agent']}] {$task['task_description']}\n{$task['output']}\n";
        }
        return implode("\n---\n", $lines);
    }

    protected function getProjectGoal(): string {
        $stmt = $this->db->prepare('SELECT goal FROM projects WHERE id = ?');
        $stmt->execute([$this->projectId]);
        return $stmt->fetchColumn() ?: '';
    }

    protected function extractAndSaveFiles(string $output): void {
        // Match === filename.ext === blocks
        if (!preg_match_all('/===\s*(.+?)\s*===\s*\n(.*?)(?=\n===|\z)/s', $output, $matches, PREG_SET_ORDER)) {
            return;
        }

        $config = require __DIR__ . '/../config.php';
        $workspacePath = $config['workspace']['path'] . '/' . $this->projectId;

        $oldUmask = umask(0002);
        if (!is_dir($workspacePath)) {
            mkdir($workspacePath, 0775, true);
        }

        foreach ($matches as $match) {
            $filename = trim($match[1]);
            $content = trim($match[2]);

            // Strip markdown code fences if present
            $content = preg_replace('/^```\w*\n?/', '', $content);
            $content = preg_replace('/\n?```$/', '', $content);

            // Save to DB
            $stmt = $this->db->prepare(
                'INSERT INTO project_files (project_id, filename, content, file_type) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE content = VALUES(content), version = version + 1'
            );
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $stmt->execute([$this->projectId, $filename, $content, $ext]);

            // Save to disk
            $filePath = $workspacePath . '/' . $filename;
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($filePath, $content);
        }
        umask($oldUmask);
    }

    protected function logRun(string $prompt, string $response, int $tokens, string $status): void {
        $stmt = $this->db->prepare(
            'INSERT INTO agent_runs (project_id, agent_type, prompt_sent, response_received, tokens_used, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$this->projectId, $this->agentType(), $prompt, $response, $tokens, $status]);
    }
}
