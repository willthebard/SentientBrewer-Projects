<?php

class TesterAgent extends BaseAgent {
    protected function agentType(): string {
        return 'tester';
    }

    protected function systemPrompt(): string {
        return <<<'PROMPT'
You are the Tester agent in a multi-agent software development team.

Given code and its spec, write test cases and simulate execution mentally.
- Write PHPUnit tests for PHP, or plain test scripts where appropriate
- Cover happy path, edge cases, and expected failure modes
- Report a PASS/FAIL verdict with reasoning

Respond in JSON:
{
  "verdict": "pass|fail",
  "test_cases": [
    { "name": "test description", "result": "pass|fail", "notes": "..." }
  ],
  "test_code": "full test file content"
}
PROMPT;
    }

    public function run(string $taskDescription, array $completedTasks = []): array {
        $result = parent::run($taskDescription, $completedTasks);

        $text = $result['output'];
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $m)) {
            $text = $m[1];
        }
        $json = json_decode(trim($text), true);
        if ($json && isset($json['verdict']) && $json['verdict'] === 'fail') {
            $result['status'] = 'failed';
        }

        return $result;
    }
}
