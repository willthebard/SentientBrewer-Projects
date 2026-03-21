<?php

class Orchestrator {
    private ClaudeClient $claude;
    private PDO $db;
    private int $projectId;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Orchestrator — the master planner of a multi-agent software development system called Sentient Brewer.

Your job:
1. Receive a user's software goal in plain English
2. Break it into an ordered list of concrete tasks
3. Assign each task to the correct specialist agent: architect, coder, reviewer, tester, documenter, or compiler
4. After each agent completes a task, review the output and decide: accept it, send it back for revision, or escalate an error
5. Track overall project state and declare the project complete when all tasks are done and passing

IMPORTANT — Platform & compilation guidelines:
- The build server is Linux (Ubuntu). Available tools: python3, pyinstaller, node/npm, gcc/g++.
- For desktop apps and games (Windows, cross-platform): ALWAYS use Python with pygame. Use pygame for ALL GUI apps AND games — do NOT use tkinter, PyQt, or any other GUI framework, as they are not available in the build environment. Use pyinstaller to bundle into a standalone .exe. NEVER use C/C++ with SDL2 or raylib.
- For games without a platform specified: prefer HTML5/JavaScript (runs everywhere, no compilation needed). Mention this choice in your plan summary.
- For web apps: use PHP/JS/HTML (the server's native stack).
- For mobile apps: recommend a PWA (HTML5) approach since we cannot build native iOS/Android binaries.
- Always include a "compiler" task as the LAST task (before documenter) if the project needs compilation (Python→pyinstaller, etc). The compiler agent will build the binary.
- The coder MUST write all code in a SINGLE Python file when possible. Keep it simple.
- The documenter task should always be last and should note what platform the output targets and how to run it.

Respond ONLY in valid JSON (no markdown fences, no extra text) matching this schema:
{
  "plan_summary": "string",
  "tasks": [
    {
      "order": 1,
      "agent": "architect|coder|reviewer|tester|documenter|compiler",
      "description": "specific instruction for that agent",
      "depends_on": []
    }
  ]
}
PROMPT;

    public function __construct(ClaudeClient $claude, PDO $db, int $projectId) {
        $this->claude = $claude;
        $this->db = $db;
        $this->projectId = $projectId;
    }

    public function createPlan(string $goal): array {
        $this->updateProjectStatus('planning');

        $result = $this->claude->chat(self::SYSTEM_PROMPT, [
            ['role' => 'user', 'content' => "Create a development plan for this software project:\n\n{$goal}"]
        ]);

        $this->logRun('orchestrator', $goal, $result['text'], $result['tokens_used']);

        $text = $result['text'];
        // Strip markdown code fences if Claude wrapped the JSON
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $jsonMatch)) {
            $text = $jsonMatch[1];
        }
        $plan = json_decode(trim($text), true);
        if (!$plan || !isset($plan['tasks'])) {
            throw new RuntimeException('Orchestrator returned invalid plan JSON: ' . substr($result['text'], 0, 300));
        }

        foreach ($plan['tasks'] as $task) {
            $stmt = $this->db->prepare(
                'INSERT INTO project_tasks (project_id, task_order, task_description, assigned_agent) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->projectId,
                $task['order'],
                $task['description'],
                $task['agent'],
            ]);
        }

        return $plan;
    }

    public function runNextTask(): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM project_tasks WHERE project_id = ? AND status = ? ORDER BY task_order ASC LIMIT 1'
        );
        $stmt->execute([$this->projectId, 'pending']);
        $task = $stmt->fetch();

        if (!$task) return null;

        // Mark in progress
        $update = $this->db->prepare('UPDATE project_tasks SET status = ? WHERE id = ?');
        $update->execute(['in_progress', $task['id']]);

        $this->updateProjectStatus('building');

        // Gather context: all completed task outputs
        $contextStmt = $this->db->prepare(
            'SELECT task_description, assigned_agent, output FROM project_tasks WHERE project_id = ? AND status = ? ORDER BY task_order ASC'
        );
        $contextStmt->execute([$this->projectId, 'complete']);
        $completedTasks = $contextStmt->fetchAll();

        // Route to the correct agent
        $agent = $this->resolveAgent($task['assigned_agent']);
        $result = $agent->run($task['task_description'], $completedTasks);

        // Save output
        $update = $this->db->prepare('UPDATE project_tasks SET status = ?, output = ? WHERE id = ?');
        $update->execute([$result['status'], $result['output'], $task['id']]);

        // If reviewer rejected, re-queue coder
        if ($task['assigned_agent'] === 'reviewer' && $result['status'] === 'failed') {
            $this->requeueWithFeedback($task, $result['output']);
        }

        // If tester failed, re-queue coder + reviewer
        if ($task['assigned_agent'] === 'tester' && $result['status'] === 'failed') {
            $this->requeueWithFeedback($task, $result['output']);
        }

        // If compiler failed, re-queue coder with compile errors, then re-queue compiler (max 3 attempts)
        if ($task['assigned_agent'] === 'compiler' && $result['status'] === 'failed') {
            $attempts = $this->getCompileAttempts();
            if ($attempts <= 3) {
                $stmt = $this->db->prepare(
                    'INSERT INTO project_tasks (project_id, task_order, task_description, assigned_agent, status) VALUES (?, ?, ?, ?, ?)'
                );
                // Re-queue coder to fix the code
                $stmt->execute([
                    $this->projectId,
                    $task['task_order'],
                    "FIX COMPILE ERRORS (attempt {$attempts} of 3): The code failed to compile. Fix ALL of the following errors. Output the corrected files using === filename === headers.\n\nCompile errors:\n" . $result['output'],
                    'coder',
                    'pending',
                ]);
                // Re-queue compiler to try again after coder fix
                $compileStmt = $this->db->prepare(
                    'INSERT INTO project_tasks (project_id, task_order, task_description, assigned_agent, status) VALUES (?, ?, ?, ?, ?)'
                );
                $compileStmt->execute([
                    $this->projectId,
                    $task['task_order'] + 1,
                    $task['task_description'],
                    'compiler',
                    'pending',
                ]);
            }
        }

        return [
            'task_id' => $task['id'],
            'agent' => $task['assigned_agent'],
            'status' => $result['status'],
            'output' => $result['output'],
            'tokens_used' => $result['tokens_used'],
        ];
    }

    public function isComplete(): bool {
        // Check for pending/in-progress tasks
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND status IN (?, ?)'
        );
        $stmt->execute([$this->projectId, 'pending', 'in_progress']);
        $remaining = (int) $stmt->fetchColumn();

        if ($remaining > 0) return false;

        // Only fail if compiler failed — reviewer/tester failures are non-blocking
        $failedStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND assigned_agent = ? AND status = ?'
        );
        $failedStmt->execute([$this->projectId, 'compiler', 'failed']);
        $compilerFailed = (int) $failedStmt->fetchColumn();

        // Check if there's a successful compiler task (later retry may have succeeded)
        $successStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND assigned_agent = ? AND status = ?'
        );
        $successStmt->execute([$this->projectId, 'compiler', 'complete']);
        $compilerSucceeded = (int) $successStmt->fetchColumn();

        if ($compilerFailed > 0 && $compilerSucceeded === 0) {
            $this->updateProjectStatus('failed');
            return true;
        }

        $this->updateProjectStatus('complete');
        return true;
    }

    private function resolveAgent(string $type): object {
        return match ($type) {
            'architect' => new ArchitectAgent($this->claude, $this->db, $this->projectId),
            'coder' => new CoderAgent($this->claude, $this->db, $this->projectId),
            'reviewer' => new ReviewerAgent($this->claude, $this->db, $this->projectId),
            'tester' => new TesterAgent($this->claude, $this->db, $this->projectId),
            'documenter' => new DocumenterAgent($this->claude, $this->db, $this->projectId),
            'compiler' => new CompilerAgent($this->claude, $this->db, $this->projectId),
            default => throw new RuntimeException("Unknown agent type: {$type}"),
        };
    }

    private function requeueWithFeedback(array $failedTask, string $feedback): void {
        // Count how many coder revisions we've already done for this agent type
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND assigned_agent = ? AND status = ? AND task_description LIKE ?'
        );
        $stmt->execute([$this->projectId, 'coder', 'complete', 'REVISION%']);
        $revisions = (int) $stmt->fetchColumn();
        if ($revisions >= 3) return;

        $stmt = $this->db->prepare(
            'INSERT INTO project_tasks (project_id, task_order, task_description, assigned_agent, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->projectId,
            $failedTask['task_order'],
            "REVISION (attempt " . ($revisions + 1) . " of 3): " . $failedTask['task_description'] . "\n\nFeedback from previous review:\n" . $feedback,
            'coder',
            'pending',
        ]);
    }

    private function getCompileAttempts(): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND assigned_agent = ? AND status = ?'
        );
        $stmt->execute([$this->projectId, 'compiler', 'failed']);
        return (int) $stmt->fetchColumn();
    }

    private function updateProjectStatus(string $status): void {
        $stmt = $this->db->prepare('UPDATE projects SET status = ? WHERE id = ?');
        $stmt->execute([$status, $this->projectId]);
    }

    private function logRun(string $agentType, string $prompt, string $response, int $tokens): void {
        $stmt = $this->db->prepare(
            'INSERT INTO agent_runs (project_id, agent_type, prompt_sent, response_received, tokens_used, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$this->projectId, $agentType, $prompt, $response, $tokens, 'success']);
    }
}
