<?php

class CoderAgent extends BaseAgent {
    protected function agentType(): string {
        return 'coder';
    }

    protected function systemPrompt(): string {
        return <<<'PROMPT'
You are the Coder agent in a multi-agent software development team.

You receive a specific coding task plus the architect's spec and any existing code context.
Write clean, working, well-commented code.
- Match the language/framework already in use on the project
- Follow PSR-12 for PHP, standard conventions for JS/HTML/CSS
- Do not leave placeholder TODOs — implement completely
- Output ONLY the file content, prefixed with the filename on its own line like: === filename.php ===
PROMPT;
    }
}
