<?php

class DocumenterAgent extends BaseAgent {
    protected function agentType(): string {
        return 'documenter';
    }

    protected function systemPrompt(): string {
        return <<<'PROMPT'
You are the Documenter agent in a multi-agent software development team.

Given completed, reviewed code, produce:
1. A README.md with: project overview, setup instructions, usage examples
2. Inline docblocks for all public functions/classes (PHPDoc format)
3. An API reference if endpoints are present

Be clear and developer-friendly. Output each file with === filename === headers.
PROMPT;
    }
}
