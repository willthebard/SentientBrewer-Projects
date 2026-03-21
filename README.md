# Sentient Brewer

**AI Software Factory — Describe your software. Watch AI agents build it.**

Sentient Brewer is a multi-agent AI system that takes a plain-English description of software and produces working, downloadable executables. Six specialized AI agents collaborate to architect, code, review, test, compile, and document your project automatically.

Built by [Sentient Bean](https://sentientbean.net) / [Will The Bard](https://willthebard.com)

## How It Works

```
User Prompt: "Make a Tetris game for Windows"
    │
    ▼
┌─────────────────────────┐
│   Orchestrator Agent    │  Breaks the goal into tasks
└────────┬────────────────┘
         │ assigns work to
    ┌────┴────────────────────────────────┐
    │  Architect → Coder → Reviewer →    │
    │  Tester → Compiler → Documenter    │
    └────┬────────────────────────────────┘
         │
    ┌────▼────────────┐
    │  Windows .exe   │  Signed, downloadable
    └─────────────────┘
```

1. **Orchestrator** — Reads your goal, creates a task plan, coordinates the other agents
2. **Architect** — Designs the data model, file structure, and API contracts
3. **Coder** — Writes complete, working code (Python + pygame for desktop apps)
4. **Reviewer** — Finds bugs, security issues, and spec mismatches
5. **Tester** — Generates test cases and validates the code
6. **Compiler** — Builds Windows executables via Wine + pyinstaller, then code-signs via SignPath
7. **Documenter** — Produces README, docblocks, and usage instructions

## Live Demo

[https://sentientbrewer.com](https://sentientbrewer.com)

## Tech Stack

- **Backend:** PHP 8.x, MariaDB, Apache
- **Frontend:** Vanilla JavaScript, Server-Sent Events for real-time build streaming
- **AI:** Anthropic Claude API (claude-sonnet-4)
- **Build Pipeline:** Wine + Windows Python + pyinstaller (cross-compiles Windows .exe on Linux)
- **Code Signing:** SignPath.io
- **Auth:** JWT (stateless API authentication)

## Architecture

```
sentientbrewer.com/
├── index.php                # Landing / login
├── dashboard.php            # Project list
├── project.php              # Live build view with SSE terminal
├── api/
│   ├── auth.php             # JWT login/register
│   ├── projects.php         # CRUD for projects
│   ├── run.php              # Kick off agent build (SSE stream)
│   ├── status.php           # Reconnect to in-progress builds
│   ├── history.php          # Agent run history
│   └── download.php         # Download project output
├── agents/
│   ├── Orchestrator.php     # Master coordinator
│   ├── BaseAgent.php        # Shared agent logic
│   ├── ArchitectAgent.php   # System design
│   ├── CoderAgent.php       # Code generation
│   ├── ReviewerAgent.php    # Code review
│   ├── TesterAgent.php      # Test generation
│   ├── CompilerAgent.php    # Build + sign executables
│   └── DocumenterAgent.php  # Documentation
├── lib/
│   ├── ClaudeClient.php     # Anthropic API wrapper
│   ├── DB.php               # PDO MariaDB singleton
│   ├── Auth.php             # JWT helper
│   ├── SSE.php              # Server-Sent Events
│   └── SignPath.php         # Code signing API client
├── admin/                   # Admin dashboard
└── workspace/               # Generated project files
```

## Setup

### Requirements

- PHP 8.x with PDO, curl, zip extensions
- MariaDB / MySQL
- Apache with mod_rewrite
- Wine + Windows Python 3.11 (for .exe builds)
- pyinstaller and pygame (installed in Wine Python)
- Anthropic API key

### Installation

1. Clone the repo and configure Apache to serve it

2. Copy and edit the config:
```php
// config.php — set your database, Anthropic API key, and SignPath credentials
```

3. Create the database:
```bash
php migrate.php
```

4. Set up Wine for Windows builds:
```bash
# Install Wine
sudo apt-get install -y wine64 wine32 xvfb

# Install Windows Python (embeddable)
mkdir -p ~/.wine/drive_c/Python311
wget https://www.python.org/ftp/python/3.11.9/python-3.11.9-embed-amd64.zip
unzip python-3.11.9-embed-amd64.zip -d ~/.wine/drive_c/Python311/

# Enable pip and install dependencies
sed -i 's/#import site/import site/' ~/.wine/drive_c/Python311/python311._pth
wget https://bootstrap.pypa.io/get-pip.py
wine ~/.wine/drive_c/Python311/python.exe get-pip.py
wine ~/.wine/drive_c/Python311/python.exe -m pip install pyinstaller pygame

# Start virtual display for Wine
nohup Xvfb :99 -screen 0 1024x768x24 &
```

5. Create an admin user:
```bash
php -r "
require 'lib/DB.php';
\$pdo = DB::getInstance();
\$hash = password_hash('yourpassword', PASSWORD_ARGON2ID);
\$pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute(['admin', \$hash]);
"
```

## Usage

1. Register at the site
2. Click **New Project**
3. Describe what you want built (e.g., "Make a Pong game for Windows with AI single player and two player keyboard mode")
4. Click **Create & Build**
5. Watch the agents work in real-time via the terminal feed
6. Download your compiled, signed executable

## License

[MIT](LICENSE) — Copyright (c) 2026 Will The Bard / Sentient Bean
