<?php

class CompilerAgent extends BaseAgent {
    protected function agentType(): string {
        return 'compiler';
    }

    protected function systemPrompt(): string {
        $winPython = '/var/www/.wine/drive_c/Python311/python.exe';
        return <<<PROMPT
You are the Compiler agent in a multi-agent software development team.

Your job is to provide the exact shell commands needed to compile/package the project source code into a runnable binary. You do NOT write code — you only build what the Coder has written.

Available tools on this Linux (Ubuntu) build server:
- wine {$winPython} — Windows Python 3.11 with pyinstaller and pygame (builds REAL Windows .exe files)
- python3 / pyinstaller (Linux native binaries)
- node / npm (JavaScript bundling)
- gcc / g++ (native Linux binaries)
- tar / zip (packaging)

Rules:
- NEVER use apt-get, wget, curl, pip install, or any download/install commands.
- For Python desktop apps and games that need a Windows .exe:
  - Use Wine + Windows Python to build a real Windows executable
  - Command: wine {$winPython} -m PyInstaller --onefile --windowed --name=PROJECTNAME main.py
  - Use --windowed for GUI apps/games, --console for CLI apps
  - The output .exe will be in dist/ folder: cp dist/PROJECTNAME.exe ./PROJECTNAME.exe
- For Linux-only Python apps:
  - Use native pyinstaller: pyinstaller --onefile --name=PROJECTNAME main.py
  - Copy: cp dist/PROJECTNAME ./PROJECTNAME
- For JavaScript/HTML5 projects: no compilation needed
- Always copy the final binary to the project root directory
- Also keep the .py source files in the download

Respond ONLY in valid JSON (no markdown fences):
{
  "compile_commands": ["command1", "command2"],
  "output_file": "projectname.exe",
  "platform": "windows|linux|web",
  "notes": "any notes about running the binary"
}
PROMPT;
    }

    public function run(string $taskDescription, array $completedTasks = []): array {
        // Override context — compiler only needs a file listing, not full code
        // Full code context confuses Claude into rewriting code instead of compiling
        $config = require __DIR__ . '/../config.php';
        $workDir = $config['workspace']['path'] . '/' . $this->projectId;
        $fileList = '';
        if (is_dir($workDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && !str_contains($file->getPathname(), '/build/') && !str_contains($file->getPathname(), '/dist/')) {
                    $rel = substr($file->getPathname(), strlen($workDir) + 1);
                    $fileList .= "- {$rel}\n";
                }
            }
        }

        $focusedTask = $taskDescription . "\n\nFiles in workspace:\n" . $fileList . "\nRespond ONLY with JSON containing compile_commands. Do NOT rewrite or output any code.";
        $result = parent::run($focusedTask, []); // Pass empty context

        if ($result['status'] !== 'complete') {
            return $result;
        }

        // Parse the compile commands and execute them
        $text = $result['output'];
        // Try to find JSON in the output — may be wrapped in code fences or mixed with text
        $json = null;
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $m)) {
            $json = json_decode(trim($m[1]), true);
        }
        if (!$json) {
            // Try to find raw JSON object in the text
            if (preg_match('/\{[^{}]*"compile_commands"\s*:.*\}/s', $text, $m)) {
                $json = json_decode(trim($m[0]), true);
            }
        }

        if (!$json || empty($json['compile_commands'])) {
            $result['status'] = 'failed';
            $result['output'] .= "\n\nCompiler error: No compile commands found in response. The compiler agent must return JSON with compile_commands.";
            return $result;
        }

        $config = require __DIR__ . '/../config.php';
        $workDir = $config['workspace']['path'] . '/' . $this->projectId;

        if (!is_dir($workDir)) {
            $result['output'] .= "\n\nCompile error: workspace directory not found.";
            $result['status'] = 'failed';
            return $result;
        }

        $compileLog = [];
        $allPassed = true;

        foreach ($json['compile_commands'] as $cmd) {
            // Sanitize: only allow known safe commands
            if (!$this->isSafeCommand($cmd)) {
                $compileLog[] = "BLOCKED (unsafe command): {$cmd}";
                $allPassed = false;
                continue;
            }

            $output = [];
            $returnCode = 0;
            $env = "cd " . escapeshellarg($workDir) . " && WINEPREFIX=/var/www/.wine DISPLAY=:99 ";
            exec($env . $cmd . " 2>&1", $output, $returnCode);
            $outputStr = implode("\n", $output);
            $compileLog[] = "$ {$cmd}\n{$outputStr}\nExit code: {$returnCode}";

            if ($returnCode !== 0) {
                $allPassed = false;
            }
        }

        $logStr = implode("\n\n", $compileLog);
        $result['output'] .= "\n\n--- COMPILE LOG ---\n" . $logStr;

        if (!$allPassed) {
            $result['status'] = 'failed';
            return $result;
        }

        // Sign the exe if it exists
        $outputFile = $json['output_file'] ?? '';
        $exePath = $workDir . '/' . $outputFile;
        if ($outputFile && file_exists($exePath) && preg_match('/\.exe$/i', $outputFile)) {
            try {
                require_once __DIR__ . '/../lib/SignPath.php';
                $signer = new SignPath();
                $signed = $signer->signExe($exePath);
                if ($signed) {
                    $result['output'] .= "\n\n--- CODE SIGNING ---\nSuccessfully signed: {$outputFile}";
                } else {
                    $result['output'] .= "\n\n--- CODE SIGNING ---\nWarning: Signing timed out or failed. Exe is unsigned.";
                }
            } catch (Throwable $e) {
                $result['output'] .= "\n\n--- CODE SIGNING ---\nWarning: " . $e->getMessage();
            }
        }

        return $result;
    }

    private function isSafeCommand(string $cmd): bool {
        // Allow only known compiler/build commands
        $allowed = [
            'gcc', 'g++', 'cc',
            'x86_64-w64-mingw32-gcc', 'x86_64-w64-mingw32-g++',
            'i686-w64-mingw32-gcc', 'i686-w64-mingw32-g++',
            'python3', 'pyinstaller',
            'wine',
            'npm', 'npx', 'node',
            'make', 'cmake',
            'cp', 'mv', 'mkdir', 'chmod',
            'tar', 'zip',
            'pkg-config',
            'windres',
        ];

        // Get the first word of the command (the executable)
        $firstWord = preg_split('/\s+/', trim($cmd))[0];
        // Strip path prefixes
        $binary = basename($firstWord);

        if (!in_array($binary, $allowed)) {
            return false;
        }

        // Block dangerous patterns
        $dangerous = ['rm -rf', '> /dev', '/etc/', '/root', 'sudo', 'chmod 777', 'curl', 'wget', 'eval', '$(', '`'];
        foreach ($dangerous as $pattern) {
            if (stripos($cmd, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }
}
