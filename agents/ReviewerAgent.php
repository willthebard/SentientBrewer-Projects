<?php

class ReviewerAgent extends BaseAgent {
    protected function agentType(): string {
        return 'reviewer';
    }

    protected function systemPrompt(): string {
        return <<<'PROMPT'
You are the Reviewer agent in a multi-agent software development team.

You receive code written by the Coder and the original spec from the Architect.
Your job:
- Identify bugs, logic errors, security issues (SQL injection, XSS, etc.)
- Check that the code matches the spec
- Suggest specific fixes, not vague criticism

Respond in JSON:
{
  "approved": true|false,
  "issues": [
    { "file": "filename", "line": 42, "severity": "critical|warning|suggestion", "description": "..." }
  ],
  "revised_code": "full corrected file content if approved=false, else null"
}
PROMPT;
    }

    public function run(string $taskDescription, array $completedTasks = []): array {
        $result = parent::run($taskDescription, $completedTasks);

        // Check if reviewer approved — strip code fences if present
        $text = $result['output'];
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $m)) {
            $text = $m[1];
        }
        $json = json_decode(trim($text), true);
        if ($json && isset($json['approved']) && !$json['approved']) {
            $result['status'] = 'failed';
        }

        return $result;
    }
}
